<?php

namespace Tests\Feature;

use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Expert Load's "Enough experts?" box: per role, the teams still needing
 * one against the open slots of the experts who take it, now and after the
 * students not yet in a team form theirs; requests waiting on full experts.
 */
class EnoughExpertsTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    public function test_each_role_weighs_teams_needing_one_against_open_slots(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$alpha] = $this->teamInSection('Alpha');
        $this->teamInSection('Bravo');
        $this->teamInSection('Charlie');
        $this->expert('adviser', '2-BSIT', 2, $alpha);
        $this->expert('consultant', '5-BSIT', 5);

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', 'experts'))
            ->assertOk()
            ->assertSee('Enough experts?')
            ->assertViewHas('table', function ($t) {
                [$adviser, $consultant, $grammarian] = $t['enough']['roles'];

                return $adviser['need'] === 2 && $adviser['open'] === 1 && $adviser['now'] === 'Short by 1' && $adviser['when'] === 'needed before a panel'
                    && $consultant['need'] === 3 && $consultant['open'] === 5 && $consultant['now'] === 'Enough'
                    && $grammarian['takers'] === 0 && $grammarian['now'] === 'Short by 3' && $grammarian['when'] === 'may come later'
                    && $t['enough']['all']['need'] === 8 && $t['enough']['all']['open'] === 6 && $t['enough']['all']['now'] === 'Short by 2';
            });
    }

    public function test_the_program_filter_narrows_teams_experts_and_their_slots(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A', 'BSIS-3A']);
        $this->teamInSection('Alpha');
        [$sigma] = $this->teamInSection('Sigma', 'BSIS');
        $this->expert('adviser', '3-BSIT', 3);
        $both = $this->expert('adviser', '2-BSIT, 3-BSIS', 5, $sigma);
        $isOnly = $this->expert('adviser', '3-BSIS', 3);
        $this->actingAs($capsa, 'expert');

        // BSIT only: the BSIS team and the BSIS-only expert drop out; the two-program expert offers their 2 BSIT slots.
        $this->get(route('capsa.reports.show', ['report' => 'experts', 'program' => 'BSIT']))
            ->assertOk()
            ->assertViewHas('table', fn ($t) => $t['enough']['program'] === 'BSIT' && $t['enough']['teams'] === 1
                && $t['enough']['roles'][0]['takers'] === 2 && $t['enough']['roles'][0]['open'] === 5
                && ! collect($t['rows'])->contains(fn ($r) => $r['cells']['expert'][1] === $isOnly->name));

        // All programs: both teams, all three experts, the two-program expert's 4 slots left of 5.
        $this->get(route('capsa.reports.show', 'experts'))
            ->assertOk()
            ->assertViewHas('table', fn ($t) => $t['enough']['program'] === 'All programs' && $t['enough']['teams'] === 2
                && $t['enough']['roles'][0]['takers'] === 3 && $t['enough']['roles'][0]['open'] === 10
                && collect($t['rows'])->contains(fn ($r) => $r['cells']['expert'][1] === $both->name));
    }

    public function test_students_not_in_a_team_count_as_new_teams_of_five(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$alpha] = $this->teamInSection('Alpha');
        foreach (range(1, 6) as $i) {
            $this->loose('BSIT');
        }
        $this->loose('BSIS'); // Another program's student does not count here.
        $this->expert('adviser', '3-BSIT', 3, $alpha);
        $this->expert('consultant', '1-BSIT', 1);

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', 'experts'))
            ->assertOk()
            ->assertViewHas('table', function ($t) {
                $en = $t['enough'];

                return $en['loose'] === 6 && $en['later'] === 2
                    && $en['roles'][0]['after'] === 'Enough'
                    && $en['roles'][1]['now'] === 'Enough' && $en['roles'][1]['after'] === 'Short by 2'
                    && $en['all']['now'] === 'Enough' && $en['all']['after'] === 'Short by 5';
            });
    }

    public function test_requests_waiting_on_a_full_expert_are_flagged_with_a_suggestion(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$alpha, $leader] = $this->teamInSection('Alpha');
        [$bravo] = $this->teamInSection('Bravo');
        [$charlie] = $this->teamInSection('Charlie');
        $full = $this->expert('adviser', '1-BSIT', 1, $alpha);
        $this->assign($bravo, $full, 'adviser', 'pending');
        $this->assign($charlie, $full, 'adviser', 'pending');
        $free = $this->expert('consultant', '3-BSIT', 3);
        $this->assign($bravo, $free, 'consultant', 'pending');

        // A quick finished review with every slot taken: "Increase slots".
        $review = CapstoneDocumentReview::create([
            'file_id' => $this->upload($leader, null), 'expert_id' => $full->expert_id, 'role' => 'adviser',
            'review_status' => 'Reviewed', 'version' => '1', 'reviewed_at' => now()->subDay(),
        ]);
        $review->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', 'experts'))
            ->assertOk()
            ->assertViewHas('table', function ($t) use ($full, $free) {
                $row = fn ($e) => collect($t['rows'])->first(fn ($r) => $r['cells']['expert'][1] === $e->name)['cells'];

                return $row($full)['waiting'] === ['tone', '2 · full', 'bad', 2]
                    && $row($full)['suggestion'] === ['tone', 'Increase slots', 'ok']
                    && $row($free)['waiting'] === ['num', 1] && $row($free)['suggestion'] === '—'
                    && $t['total']['waiting'] === ['b', '3']
                    && $t['enough']['waiting'] === 3 && $t['enough']['stuck'] === [[$full->name, 2]];
            });
    }

    public function test_the_excel_file_carries_the_box_and_the_new_columns(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$alpha] = $this->teamInSection('Alpha');
        $this->expert('adviser', '2-BSIT', 2, $alpha);

        $xlsx = $this->actingAs($capsa, 'expert')
            ->get(route('capsa.reports.show', ['report' => 'experts', 'download' => 'xlsx']))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'rpt');
        file_put_contents($path, $xlsx->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        // Title, scope, then the box's lines from row 4, a blank row, then the headings.
        $this->assertSame('Expert Load', $rows[0][0]);
        $this->assertStringContainsString('BSIT', (string) $rows[1][0]);
        $this->assertStringStartsWith('Enough experts? BSIT · 1 team', (string) $rows[3][0]);
        $this->assertStringStartsWith('Adviser (needed before a panel): 1 teams with one, 0 still need one', (string) $rows[4][0]);
        $this->assertStringStartsWith('Requests waiting: 0', (string) $rows[7][0]);
        $this->assertStringContainsString('no limit on panel seats', (string) $rows[8][0]);
        $headings = $rows[10];
        $this->assertContains('Requests waiting', $headings);
        $this->assertContains('Suggestion', $headings);
        $this->assertSame(array_search('Slots used', $headings) + 1, array_search('Requests waiting', $headings));
    }

    private function expert(string $role, ?string $slots, ?int $max, ?Team $team = null): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'program_slots' => $slots, 'max_teams' => $max]);
        Role::firstOrCreate(['role_name' => $role]);
        if ($team) {
            $this->assign($team, $expert, $role, 'approved');
        } else {
            $expert->roles()->syncWithoutDetaching([Role::where('role_name', $role)->value('role_id')]);
        }

        return $expert;
    }

    private function assign(Team $team, CapstoneExpert $expert, string $roleName, string $status): void
    {
        $role = Role::firstOrCreate(['role_name' => $roleName]);
        $expert->roles()->syncWithoutDetaching([$role->role_id]);

        TeamExpertAssignment::create([
            'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => $status,
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
        ]);
    }

    /** A student enrolled this year who has not joined a team. */
    private function loose(string $course): void
    {
        $student = Student::factory()->create(['email' => 'loose.'.uniqid().'@my.cspc.edu.ph', 'course' => $course, 'year_section' => '3rd · A']);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => null]);
    }
}
