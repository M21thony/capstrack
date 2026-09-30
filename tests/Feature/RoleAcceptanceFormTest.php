<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Parser;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * An expert who approved the team gets a Role Acceptance Form the team can
 * download filled in, print and have signed - the CCS takes no e-signatures.
 */
class RoleAcceptanceFormTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    private Student $leader;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->team, $this->leader] = $this->teamInSection('Bulbasaur');
        $this->leader->update(['f_name' => 'Jolina', 'm_name' => 'Bautista', 'l_name' => 'Rabusa', 'suffix' => null]);
    }

    private function assignment(string $status = 'approved', ?Team $team = null): TeamExpertAssignment
    {
        $expert = CapstoneExpert::factory()->create([
            'first_name' => 'Maricris', 'middle_name' => 'Lopez', 'last_name' => 'Ramizares', 'suffix' => null, 'credentials' => 'MIT',
        ]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return TeamExpertAssignment::create([
            'team_id' => ($team ?? $this->team)->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->value('expert_role_id'),
            'status' => $status,
        ]);
    }

    public function test_a_member_downloads_the_form_filled_in(): void
    {
        $assignment = $this->assignment();
        $member = Student::factory()->withCompleteProfile()->create(['f_name' => 'Mark Anthony', 'm_name' => 'Bonto', 'l_name' => 'Balilla', 'suffix' => null]);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $this->team->team_id]);

        $response = $this->actingAs($member)->get(route('student.experts.role-acceptance', $assignment));

        $response->assertOk()->assertDownload('Role_Acceptance_Form_Adviser_Bulbasaur.pdf');

        $text = preg_replace('/\s+/', ' ', (new Parser)->parseContent($response->getContent())->getText());

        $this->assertStringContainsString('To: MARICRIS L. RAMIZARES, MIT', $text);
        $this->assertStringContainsString('Date: '.$assignment->created_at->format('d/m/Y'), $text);
        $this->assertStringContainsString('degree in Bachelor of Science in Information Technology', $text);
        $this->assertStringContainsString('serve as our Adviser', $text);
        $this->assertStringContainsString('Adviser of the team Bulbasaur', $text);
        // The leader signs first, whatever the alphabet says.
        $this->assertMatchesRegularExpression('/Jolina B\. Rabusa,.*Mark Anthony B\. Balilla/', $text);
    }

    public function test_another_teams_form_is_refused(): void
    {
        [$otherTeam] = $this->teamInSection('Charmander');

        $this->actingAs($this->leader)
            ->get(route('student.experts.role-acceptance', $this->assignment('approved', $otherTeam)))
            ->assertForbidden();
    }

    public function test_there_is_no_form_before_the_expert_approves(): void
    {
        $this->actingAs($this->leader)
            ->get(route('student.experts.role-acceptance', $this->assignment('pending')))
            ->assertNotFound();
    }

    public function test_the_experts_page_links_the_form_for_an_approved_expert(): void
    {
        $assignment = $this->assignment();

        $this->actingAs($this->leader)->get(route('student.experts'))
            ->assertOk()
            ->assertSee(route('student.experts.role-acceptance', $assignment), false)
            ->assertSee('Print form');
    }
}
