<?php

namespace Database\Seeders;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\ExpensePost;
use App\Models\ExpertRemovalRequest;
use App\Models\ExpertRole;
use App\Models\FinancialRecord;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Models\TeamRating;
use App\Models\TeamRatingForm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dummy rows for the three features that had nothing to show:
 *
 *   Team Rating       the members of every "Demo ..." team in the first
 *                     rating form rate each other, all but the last team
 *   Change expert     one waiting request that names the expert and role
 *                     the team wants instead
 *   Returnees         one team retaking the Pre Oral Defense and one the
 *                     Final Oral Defense, shown as Returnee (POD) / (FOD)
 *   Generate schedule six teams ready for the Pre Oral Defense, three in
 *                     BSIT-3A and three in BSIT-3G, sharing two panels - so
 *                     two CaPSAs generating one after the other shows no
 *                     panelist is booked twice. One team per section retakes
 *                     the POD. Needs ScheduleDemoSeeder's panelists.
 *
 * Running it again replaces the demo.
 *
 *   php artisan db:seed --class=FeatureShowcaseDemoSeeder
 *
 * To take it all out again:
 *
 *   php artisan tinker --execute "Database\Seeders\FeatureShowcaseDemoSeeder::clean();"
 */
class FeatureShowcaseDemoSeeder extends Seeder
{
    private const REASON = 'Demo: our expert has not answered our messages for three weeks, and we need comments before the defense.';

    /** Team => the stage it retakes. Both have that defense waiting for a date. */
    private const RETURNEES = [117 => 'Pre Oral Defense', 8 => 'Final Oral Defense'];

    private const POD = 'Pre Oral Defense';

    /** Team => [project title, section letter, panel, retakes the POD]. */
    private const POD_TEAMS = [
        'Circuit Breakers' => ['Dormitory Finder with Room Reservation', 'A', 'A', false],
        'Null Pointers' => ['Water Refilling Station Delivery Tracker', 'A', 'B', false],
        'Night Owls' => ['Alumni Tracer and Job Board', 'A', 'A', true],
        'Data Dynamos' => ['Rice Mill Inventory and Sales Monitoring', 'G', 'A', false],
        'Kernel Coders' => ['Barangay Health Center Records System', 'G', 'B', false],
        'Syntax Squad' => ['Thesis Book Archive with Search', 'G', 'B', true],
    ];

    /** Panel => chair, member 1, member 2, by the address ScheduleDemoSeeder gave them. */
    private const POD_PANELS = [
        'A' => ['demo.ramon.delacruz', 'demo.liza.bautista', 'demo.jomar.reyes'],
        'B' => ['demo.teresa.villanueva', 'demo.carlo.mendoza', 'demo.anna.santos'],
    ];

    private const POD_STAFF = ['demo.marites.castillo', 'demo.dennis.navarro', 'demo.rosario.torres'];

    public function run(): void
    {
        self::clean();

        $this->command?->info('Team ratings: '.$this->ratings());
        $this->command?->info('Change expert: '.$this->changeExpert());
        // After the request above, which takes the newest approved expert.
        $this->command?->info('Generate schedule: '.$this->podTeams());

        foreach (self::RETURNEES as $teamId => $stage) {
            Team::whereKey($teamId)->update(['returnee_stage' => $stage]);
            Student::whereIn('student_id', StudentSchoolYear::where('team_id', $teamId)->pluck('student_id'))->update(['is_returnee' => true]);
        }
    }

    private function podTeams(): string
    {
        $expert = fn (string $name) => CapstoneExpert::where('email', $name.'@example.test')->first();
        $panels = array_map(fn (array $names) => array_map($expert, $names), self::POD_PANELS);
        $staff = array_map($expert, self::POD_STAFF);

        if (in_array(null, [...$staff, ...array_merge(...array_values($panels))], true)) {
            return 'the demo panelists are missing - run ScheduleDemoSeeder first, skipped';
        }

        $fee = ExpensePost::where('defense_type', self::POD)->first();
        $number = 0;

        foreach (self::POD_TEAMS as $name => [$title, $section, $panel, $returnee]) {
            $number++;
            $students = collect(range(1, 3))->map(fn (int $i) => Student::factory()->withCompleteProfile()->create([
                'email' => "pod.team{$number}.member{$i}@example.test",
                'course' => 'Bachelor of Science in Information Technology',
                'year_section' => '3rd · '.$section,
                'is_returnee' => $returnee,
            ]));

            $team = Team::create([
                'team_name' => $name, 'project_title' => $title, 'team_status' => 'Active',
                'is_confirmed' => true, 'member_count' => 3, 'leader_id' => $students->first()->student_id,
            ]);
            $team->forceFill(['panel_status' => Team::PANEL_FINAL, 'returnee_stage' => $returnee ? self::POD : null])->save();

            foreach ($students as $student) {
                StudentSchoolYear::create([
                    'student_id' => $student->student_id, 'team_id' => $team->team_id,
                    'school_year' => '2026-2027', 'program' => 'BSIT', 'student_status' => 'Active',
                ]);

                if ($fee) {
                    FinancialRecord::create([
                        'student_id' => $student->student_id, 'expense_post_id' => $fee->id, 'f_record_status' => 'verified', 'receipt_image' => '',
                        'payment_for' => $fee->title, 'amount' => $fee->amount, 'uploaded_at' => now(), 'verified_at' => now(),
                    ]);
                }
            }

            foreach ($staff as $person) {
                TeamExpertAssignment::create([
                    'team_id' => $team->team_id, 'expert_id' => $person->expert_id, 'status' => 'approved',
                    'expert_role_id' => ExpertRole::where('expert_id', $person->expert_id)->value('expert_role_id'),
                ]);
            }

            // The two defenses before it, passed; then the POD itself, waiting for a date.
            foreach (['Pre-proposal Defense' => 60, 'Title Defense' => 30, self::POD => null] as $stage => $daysAgo) {
                $defense = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => $stage, 'attempt' => 1] + ($daysAgo ? [
                    'schedule_date' => now()->subDays($daysAgo)->setTime(9, 0), 'end_date' => now()->subDays($daysAgo)->setTime(10, 0),
                    'venue' => 'Room 101', 'outcome' => 'passed',
                ] : []));

                foreach (['Panel Chair', 'Panel Member 1', 'Panel Member 2'] as $seat => $role) {
                    DefensePanel::create(['defense_id' => $defense->defense_id, 'expert_id' => $panels[$panel][$seat]->expert_id, 'panel_role' => $role, 'created_at' => now()]);
                }
            }

            // Every checklist line before the POD, so the board shows the team as clear.
            foreach ($team->checklistProgress()?->items ?? [] as $row) {
                if ($row->item->kind === Deliverable::DEFENSE && $row->item->defense_type === self::POD) {
                    break;
                }
                DeliverableCheck::updateOrCreate(
                    ['deliverable_id' => $row->item->id, 'team_id' => $team->team_id],
                    ['status' => DeliverableCheck::COMPLETE, 'checked_at' => now()],
                );
            }
        }

        // Next week's weekdays, so every slot Generate suggests is still ahead.
        $monday = now()->next('Monday');
        DefenseWindow::updateOrCreate(['defense_type' => self::POD, 'program' => null], [
            'start_date' => $monday->toDateString(), 'end_date' => $monday->copy()->addDays(4)->toDateString(),
        ]);

        return $number.' teams, window '.$monday->toFormattedDateString().' to '.$monday->copy()->addDays(4)->toFormattedDateString();
    }

    private function ratings(): string
    {
        $form = TeamRatingForm::with('items')->orderBy('id')->first();
        if (! $form) {
            return 'no rating form yet, skipped';
        }

        $teams = $form->teams()->filter(fn ($t) => str_starts_with($t['team']->team_name, 'Demo'))->slice(0, -1);
        $n = $form->rubric()->count();
        $made = 0;

        foreach ($teams as $t) {
            foreach ($t['members'] as $rater) {
                foreach ($t['members'] as $ratee) {
                    if ($rater->student_id === $ratee->student_id) {
                        continue;
                    }
                    foreach ($form->items as $item) {
                        // The same scores every run, but different for each pair.
                        $scores = array_map(fn ($i) => [10, 10, 7, 7, 5, 3][crc32("$rater->student_id-$ratee->student_id-$item->id-$i") % 6], range(1, $n));
                        TeamRating::create([
                            'form_id' => $form->id, 'item_id' => $item->id, 'team_id' => $t['team']->team_id,
                            'rater_id' => $rater->student_id, 'ratee_id' => $ratee->student_id,
                            'scores' => $scores, 'total' => array_sum($scores), 'submitted_at' => now()->subDays(2),
                        ]);
                        $made++;
                    }
                }
            }
        }

        return "$made ratings in {$teams->count()} teams";
    }

    private function changeExpert(): string
    {
        $waiting = ExpertRemovalRequest::where('status', ExpertRemovalRequest::PENDING)->pluck('assignment_id');

        $assignment = DB::table('capstone_expert_application as a')
            ->join('expert_roles as r', 'r.expert_role_id', '=', 'a.expert_role_id')
            ->where('a.status', 'approved')->whereNotIn('a.assignment_id', $waiting)
            ->orderByDesc('a.assignment_id')->select('a.*', 'r.role_id')->first();

        // Someone else who holds the same role.
        $instead = $assignment ? DB::table('expert_roles as r')
            ->join('capstone_experts as e', 'e.expert_id', '=', 'r.expert_id')
            ->where('r.role_id', $assignment->role_id)->where('r.expert_id', '!=', $assignment->expert_id)
            ->where('e.approved_status', 1)->whereNull('e.deactivated_at')->orderBy('r.expert_role_id')->first() : null;

        $student = $assignment ? StudentSchoolYear::where('team_id', $assignment->team_id)->value('student_id') : null;

        if (! $instead || ! $student) {
            return 'no approved expert with a second person in the same role, skipped';
        }

        ExpertRemovalRequest::create([
            'team_id' => $assignment->team_id, 'assignment_id' => $assignment->assignment_id, 'expert_id' => $assignment->expert_id,
            'requested_by' => $student, 'reason' => self::REASON, 'status' => ExpertRemovalRequest::PENDING,
            'replacement_expert_id' => $instead->expert_id, 'replacement_expert_role_id' => $instead->expert_role_id,
            'replacement_message' => 'Good day! We would like to ask you to take our team.',
        ]);

        return "team {$assignment->team_id} asks to swap expert {$assignment->expert_id} for {$instead->expert_id}";
    }

    public static function clean(): void
    {
        $students = Student::where('email', 'like', 'pod.team%@example.test')->pluck('student_id');
        $teams = StudentSchoolYear::whereIn('student_id', $students)->pluck('team_id')->unique();
        DefensePanel::whereIn('defense_id', DefenseSchedule::whereIn('team_id', $teams)->pluck('defense_id'))->delete();
        DefenseSchedule::whereIn('team_id', $teams)->delete();
        DeliverableCheck::whereIn('team_id', $teams)->delete();
        TeamExpertAssignment::whereIn('team_id', $teams)->delete();
        FinancialRecord::whereIn('student_id', $students)->delete();
        StudentSchoolYear::whereIn('student_id', $students)->delete();
        Team::whereIn('team_id', $teams)->delete();
        Student::whereIn('student_id', $students)->delete();

        TeamRating::whereIn('team_id', Team::where('team_name', 'like', 'Demo%')->pluck('team_id'))->delete();
        ExpertRemovalRequest::where('reason', self::REASON)->delete();
        Team::whereIn('team_id', array_keys(self::RETURNEES))->update(['returnee_stage' => null]);
        Student::whereIn('student_id', StudentSchoolYear::whereIn('team_id', array_keys(self::RETURNEES))->pluck('student_id'))->update(['is_returnee' => false]);
    }
}
