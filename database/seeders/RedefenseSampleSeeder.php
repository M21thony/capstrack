<?php

namespace Database\Seeders;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\DefenseOutcomeRecorder;
use App\Support\DefenseOutcome;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Two sample teams that sat their Pre-proposal Defense and did not pass it,
 * so the two redefense outcomes can be seen end to end:
 *
 *   Sample Redefense  - "Redefense with major revision": a second attempt opens
 *                       with the same panel, waiting for a date.
 *   Sample New Title  - "Redefense with a new title": a second attempt opens
 *                       and the team's pre-proposals are handed back.
 *
 * The outcome is recorded through DefenseOutcomeRecorder, the same code the
 * Panel Chair's button runs. Safe to run twice: an existing sample is left alone.
 *
 *   php artisan db:seed --class=RedefenseSampleSeeder
 */
class RedefenseSampleSeeder extends Seeder
{
    private const PANEL = ['Panel Chair' => 92, 'Panel Member 1' => 101, 'Panel Member 2' => 106];

    private const EXPERTS = ['adviser' => 108, 'consultant' => 109, 'grammarian' => 107];

    public function run(): void
    {
        $this->sample('Sample Redefense', 'redefense', 'Smart Parking Finder for the CSPC Campus', DefenseOutcome::REDEFENSE,
            'The objectives do not match the problem. Rework Chapter 1 and defend again.');
        $this->sample('Sample New Title', 'newtitle', 'Online Canteen Ordering with Queue Numbers', DefenseOutcome::REDEFENSE_NEW_TITLE,
            'The system already exists in the college. Come back with a new title.');
    }

    private function sample(string $name, string $slug, string $title, string $outcome, string $remarks): void
    {
        if (Team::where('team_name', $name)->exists()) {
            $this->command?->info("{$name} already exists - skipped.");

            return;
        }

        // Section BSIT-3A, so the sample shows up on that CaPSA's pages.
        $students = collect(range(1, 3))->map(fn ($i) => Student::factory()->withCompleteProfile()->create([
            'email' => "sample.{$slug}.{$i}@my.cspc.edu.ph",
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]));

        $team = Team::create([
            'team_name' => $name,
            'project_title' => $title,
            'team_status' => 'Active',
            'is_confirmed' => true,
            'member_count' => 3,
            'leader_id' => $students->first()->student_id,
        ]);

        $enrolments = [];
        foreach ($students as $student) {
            $enrolments[] = StudentSchoolYear::create([
                'student_id' => $student->student_id,
                'team_id' => $team->team_id,
                'school_year' => '2026-2027',
                'program' => 'BSIT',
                'student_status' => 'Active',
            ]);
        }

        foreach (self::EXPERTS as $role => $expertId) {
            $expertRoleId = DB::table('expert_roles')
                ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
                ->where('expert_roles.expert_id', $expertId)
                ->where('roles.role_name', $role)
                ->value('expert_roles.expert_role_id');

            if ($expertRoleId) {
                DB::table('capstone_expert_application')->insert([
                    'team_id' => $team->team_id, 'expert_id' => $expertId, 'expert_role_id' => $expertRoleId,
                    'status' => 'approved', 'message' => 'Sample data', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // Two titles, the first chosen - what a new-title outcome hands back.
        foreach ([1 => $title, 2 => 'Barangay Clearance Request Tracker'] as $slot => $proposal) {
            // Every pre-proposal is filed against the duplication check it passed.
            $checkId = DB::table('duplication_checks')->insertGetId([
                'student_s_y_id' => $enrolments[0]->s_y_id, 'document_path' => 'sample/'.$slug.'-'.$slot.'.pdf',
                'concepts' => json_encode(['title' => $proposal]), 'top_score' => 12, 'verdict' => 'Passed',
                'matches' => '[]', 'instructors_suggested' => '[]', 'created_at' => now(), 'updated_at' => now(),
            ]);

            $id = DB::table('capstone_pre_proposals')->insertGetId([
                'team_id' => $team->team_id, 'duplication_check_id' => $checkId, 'slot' => $slot, 'title' => $proposal,
                'sdg_goals' => 'SDG 11 – Sustainable Cities and Communities', 'research_agenda' => '["Applications and Systems Development"]',
                'problem_statement' => 'Sample problem statement for '.$proposal.'.', 'proposed_solution' => 'A web-based system.',
                'scope' => 'CSPC campus only.', 'limitations' => 'Needs internet access.', 'sdm' => 'Agile Development Methodology',
                'duplication_status' => 'free',
                'approved_status' => $slot === 1, 'submitted_at' => now()->subWeek(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            if ($slot === 1) {
                $team->update(['selected_pre_proposal_id' => $id]);
            }
        }

        $defense = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => DefenseOutcome::PRE_PROPOSAL,
            'attempt' => 1,
            'schedule_date' => now()->subDays(2)->setTime(8, 0),
            'end_date' => now()->subDays(2)->setTime(10, 0),
            'venue' => 'Mac Lab',
        ]);

        $rubric = GradingRubric::with('criteria')
            ->where('is_active', true)->where('is_archived', false)
            ->whereRaw('LOWER(rubric_type) = ?', ['pre-proposal defense'])
            ->first();

        // Each panelist leans a different way, so the three copies differ.
        $lean = ['Panel Chair' => 3, 'Panel Member 1' => 2, 'Panel Member 2' => 2];

        foreach (self::PANEL as $role => $expertId) {
            DefensePanel::create(['defense_id' => $defense->defense_id, 'expert_id' => $expertId, 'panel_role' => $role, 'created_at' => now()]);

            if (! $rubric) {
                continue;
            }

            $scores = $rubric->criteria->mapWithKeys(function ($c, $i) use ($lean, $role) {
                $levels = $c->decodedScoreLevels();
                $pick = min(count($levels) - 1, $lean[$role] - ($i % 2));

                return [(string) $c->criterion_id => $levels ? (float) $levels[max(0, $pick)]['score'] : (float) $c->max_points / 2];
            });

            PanelGrade::create([
                'defense_id' => $defense->defense_id,
                'expert_id' => $expertId,
                'rubric_id' => $rubric->rubric_id,
                'scores' => $scores->all(),
                'total_score' => $scores->sum(),
                'remarks' => "{$role}: ".$remarks,
                'status' => 'submitted',
                'submitted_at' => now()->subDays(2)->setTime(10, 30),
            ]);
        }

        app(DefenseOutcomeRecorder::class)->record($defense, $outcome, CapstoneExpert::find(self::PANEL['Panel Chair']), $remarks);

        $this->command?->info("{$name}: ".DefenseOutcome::label($outcome, DefenseOutcome::PRE_PROPOSAL).' recorded.');
    }
}
