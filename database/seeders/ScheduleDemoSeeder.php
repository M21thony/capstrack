<?php

namespace Database\Seeders;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Dummy BSIT-3A/3B teams for showing Generate schedule on the Pre-proposal
 * Defense: nine teams across three panels - one of them with no grammarian,
 * which is only a warning - plus one team with no panel, so the "not ready"
 * list has something too.
 * Every account is @example.test, so saving a schedule emails no real person.
 *
 *   php artisan db:seed --class=ScheduleDemoSeeder
 */
class ScheduleDemoSeeder extends Seeder
{
    private const STAGE = 'Pre-proposal Defense';

    /** Team name => [project title, section letter, panel (null = none), has grammarian]. */
    private const TEAMS = [
        'Demo Byte Builders' => ['Smart Parking Finder for the Campus', 'A', 'A', true],
        'Demo Code Crafters' => ['Online Canteen Ordering with Queue Numbers', 'A', 'A', true],
        'Demo Pixel Pioneers' => ['Barangay Clearance Request Tracker', 'A', 'A', true],
        'Demo Logic Legends' => ['Clinic Appointment and Medicine Inventory System', 'A', 'B', true],
        'Demo Stack Squad' => ['Library Book Reservation with SMS Alerts', 'B', 'B', true],
        'Demo Data Dragons' => ['Evacuation Center Monitoring System', 'B', 'B', true],
        'Demo Cloud Coders' => ['Tricycle Fare Calculator and Route Guide', 'B', 'C', true],
        'Demo Net Ninjas' => ['Farm Produce Price Board', 'B', 'C', true],
        'Demo Bug Busters' => ['Scholarship Application Tracker', 'A', null, true],
        'Demo Script Kiddos' => ['Lost and Found Board for the Campus', 'B', 'C', false],
    ];

    /** Panel => [chair, member 1, member 2], each [first name, last name, highest degree, degree title]. */
    private const PANELS = [
        'A' => [['Ramon', 'Dela Cruz', 'doctorate', 'DIT'], ['Liza', 'Bautista', 'master', 'MIT'], ['Jomar', 'Reyes', 'bachelor', 'BSIT']],
        'B' => [['Teresa', 'Villanueva', 'doctorate', 'PhD'], ['Carlo', 'Mendoza', 'master', 'MSCS'], ['Anna', 'Santos', 'bachelor', 'BSCS']],
        'C' => [['Rogelio', 'Aquino', 'master', 'MIT'], ['Grace', 'Ramos', 'master', 'MIS'], ['Paolo', 'Garcia', 'bachelor', 'BSIT']],
    ];

    public function run(): void
    {
        if (Team::where('team_name', 'Demo Byte Builders')->exists()) {
            $this->command?->info('The schedule demo teams already exist - skipped.');

            return;
        }

        $roles = collect(['adviser', 'consultant', 'grammarian'])
            ->mapWithKeys(fn (string $name) => [$name => Role::firstOrCreate(['role_name' => $name])]);

        $panels = collect(self::PANELS)->map(fn (array $people) => array_map(
            fn (array $person) => $this->expert($person, ['adviser', 'consultant'], $roles),
            $people,
        ));

        // The teams' own experts are kept off every panel, since a team's
        // adviser or consultant may not sit on its panel.
        $staff = [
            'adviser' => $this->expert(['Marites', 'Castillo', 'master', 'MIT'], ['adviser'], $roles),
            'consultant' => $this->expert(['Dennis', 'Navarro', 'master', 'MSIT'], ['consultant'], $roles),
            'grammarian' => $this->expert(['Rosario', 'Torres', 'master', 'MAEd'], ['grammarian'], $roles),
        ];

        $number = 0;
        foreach (self::TEAMS as $name => [$title, $section, $panel, $hasGrammarian]) {
            $team = $this->team($name, $title, $section, ++$number);
            $this->checkOffRequirementsBeforeTheDefense($team);

            foreach ($staff as $roleName => $expert) {
                if ($roleName === 'grammarian' && ! $hasGrammarian) {
                    continue;
                }

                TeamExpertAssignment::create([
                    'team_id' => $team->team_id,
                    'expert_id' => $expert->expert_id,
                    'status' => 'approved',
                    'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)
                        ->where('role_id', $roles[$roleName]->role_id)
                        ->value('expert_role_id'),
                ]);
            }

            if ($panel === null) {
                continue;
            }

            $schedule = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => self::STAGE, 'attempt' => 1]);

            foreach (['Panel Chair', 'Panel Member 1', 'Panel Member 2'] as $seat => $panelRole) {
                DefensePanel::create([
                    'defense_id' => $schedule->defense_id,
                    'expert_id' => $panels[$panel][$seat]->expert_id,
                    'panel_role' => $panelRole,
                    'created_at' => now(),
                ]);
            }
        }

        // Next week's weekdays, so every slot Generate suggests is still ahead.
        $monday = now()->next('Monday');
        $friday = $monday->copy()->addDays(4);

        DefenseWindow::updateOrCreate(['defense_type' => self::STAGE], [
            'start_date' => $monday->toDateString(),
            'end_date' => $friday->toDateString(),
        ]);

        $this->command?->info('Added 10 demo teams. Pre-proposal Defense window: '.$monday->toFormattedDateString().' to '.$friday->toFormattedDateString().'.');
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: string}  $person
     * @param  list<string>  $roleNames
     * @param  Collection<string, Role>  $roles
     */
    private function expert(array $person, array $roleNames, Collection $roles): CapstoneExpert
    {
        [$firstName, $lastName, $degree, $degreeTitle] = $person;

        $expert = CapstoneExpert::factory()->create([
            'email' => 'demo.'.strtolower(str_replace(' ', '', $firstName.'.'.$lastName)).'@example.test',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'highest_degree' => $degree,
            'degree_title' => $degreeTitle,
            'approved_status' => true,
            'first_login_at' => now(),
        ]);

        foreach ($roleNames as $roleName) {
            $expert->roles()->attach($roles[$roleName]->role_id);
        }

        return $expert;
    }

    /**
     * Marks done every checklist line before the Pre-proposal Defense, so the
     * section CaPSA/TSA's checklist does not hold the demo teams back.
     * ponytail: a CaPSA/TSA check closes forms and tasks, not documents - enough for the BSIT checklist.
     */
    public function checkOffRequirementsBeforeTheDefense(Team $team): void
    {
        foreach ($team->checklistProgress()?->items ?? [] as $row) {
            if ($row->item->kind === Deliverable::DEFENSE && $row->item->defense_type === self::STAGE) {
                return;
            }

            DeliverableCheck::updateOrCreate(
                ['deliverable_id' => $row->item->id, 'team_id' => $team->team_id],
                ['status' => DeliverableCheck::COMPLETE, 'checked_at' => now()],
            );
        }
    }

    private function team(string $name, string $title, string $section, int $number): Team
    {
        $students = collect(range(1, 3))->map(fn (int $i) => Student::factory()->withCompleteProfile()->create([
            'email' => "demo.team{$number}.member{$i}@example.test",
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · '.$section,
        ]));

        $team = Team::create([
            'team_name' => $name,
            'project_title' => $title,
            'team_status' => 'Active',
            'is_confirmed' => true,
            'member_count' => 3,
            'leader_id' => $students->first()->student_id,
        ]);

        foreach ($students as $student) {
            StudentSchoolYear::create([
                'student_id' => $student->student_id,
                'team_id' => $team->team_id,
                'school_year' => '2026-2027',
                'program' => 'BSIT',
                'student_status' => 'Active',
            ]);
        }

        return $team;
    }
}
