<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Accepts cell when an expert's cap is split across programs.
 *
 * A split cap ("2 - BSIT, 1 - BSIS") used to render as a stack of bare
 * "max n" numbers with the program floating far left of them - two unrelated
 * columns that also said nothing about how full each program already was.
 * It now draws one bar plus n/max per program, in the shape of the single-cap
 * row beside it, so both states of the cell read the same way.
 */
class ExpertAcceptsColumnTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): CapstoneExpert
    {
        $admin = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true, 'sections' => [],
        ]);

        $admin->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $admin;
    }

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-4A'],
        ]);

        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    /**
     * Takes 2 BSIT + 1 BSIS teams, and already advises one approved team in
     * each program - so the cell has real numbers to draw with.
     */
    private function splitExpert(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => false,
            'max_teams' => 3,
            'program_slots' => '2 - BSIT, 1 - BSIS',
        ]);

        $adviserRole = Role::firstOrCreate(['role_name' => 'adviser']);
        $capsaRole = Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE]);
        // The admin's copy of the list only shows CaPSAs, so the expert wears
        // both roles the way a real one does: runs the subject and advises.
        $expert->roles()->attach([$adviserRole->role_id, $capsaRole->role_id]);

        $link = DB::table('expert_roles')
            ->where('expert_id', $expert->expert_id)->where('role_id', $adviserRole->role_id)
            ->value('expert_role_id');

        foreach (['BSIT' => 'bsit.lead@my.cspc.edu.ph', 'BSIS' => 'bsis.lead@my.cspc.edu.ph'] as $program => $email) {
            $student = Student::create([
                'email' => $email, 'f_name' => 'Lead', 'l_name' => ucfirst(strtolower($program)),
                'course' => $program, 'year_section' => '4th · A',
            ]);
            StudentRoster::create([
                'email' => $student->email, 'name' => 'Lead '.ucfirst(strtolower($program)),
                'section' => '4th · A', 'program' => $program,
                'school_year' => Term::currentSchoolYear(),
            ]);
            $team = Team::create(['team_name' => 'Team '.$program, 'leader_id' => $student->student_id]);
            StudentSchoolYear::create([
                'student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => $program,
            ]);

            DB::table('capstone_expert_application')->insert([
                'team_id' => $team->team_id,
                'expert_id' => $expert->expert_id,
                'expert_role_id' => $link,
                'status' => 'approved',
            ]);
        }

        return $expert;
    }

    /** Each program shows its own filled count, not a bare cap. */
    public function test_a_split_cap_shows_each_programs_progress(): void
    {
        $this->splitExpert();

        $html = $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.experts'))->assertOk()->getContent();

        // One approved team in each program: 1 of the 2 BSIT slots, the only
        // BSIS slot, each with its bar drawn to match.
        $this->assertStringContainsString('<span class="capacity-count">1/2</span>', $html);
        $this->assertStringContainsString('<span class="capacity-count">1/1</span>', $html);
        $this->assertStringContainsString('width:50%', $html);
        $this->assertStringContainsString('width:100%', $html);

        // The old shape: a bare "max n" count and the total footer under it.
        $this->assertStringNotContainsString('<span class="capacity-count">max', $html);
        $this->assertStringNotContainsString('total assigned', $html);
    }

    /** The CaPSA's copy of the list reads the same data the same way. */
    public function test_the_capsa_copy_of_the_list_shows_the_same_progress(): void
    {
        $this->splitExpert();

        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.experts'))->assertOk()->getContent();

        $this->assertStringContainsString('<span class="capacity-count">1/2</span>', $html);
        $this->assertStringContainsString('<span class="capacity-count">1/1</span>', $html);
        $this->assertStringNotContainsString('total assigned', $html);
    }

    /**
     * Grom's case: the teams were approved while the enrolment rows still
     * carried no program, so the count used to see zero and the bar stayed
     * empty however full the expert actually was.
     */
    public function test_teams_without_a_program_still_count_from_a_members_course(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => false,
            'max_teams' => 3,
            'program_slots' => '2 - BSIT, 1 - BSIS',
        ]);

        $adviserRole = Role::firstOrCreate(['role_name' => 'adviser']);
        $expert->roles()->attach([
            $adviserRole->role_id,
            Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id,
        ]);

        $link = DB::table('expert_roles')
            ->where('expert_id', $expert->expert_id)->where('role_id', $adviserRole->role_id)
            ->value('expert_role_id');

        foreach (['one' => 'bsit.one@my.cspc.edu.ph', 'two' => 'bsit.two@my.cspc.edu.ph'] as $nth => $email) {
            $student = Student::create([
                'email' => $email, 'f_name' => 'Lead', 'l_name' => ucfirst($nth),
                // The full course name, the way the real accounts carry it.
                'course' => 'Bachelor of Science in Information Technology',
                'year_section' => '4th · A',
            ]);
            $team = Team::create(['team_name' => 'Legacy '.$nth, 'leader_id' => $student->student_id]);

            // Written the way the old paths wrote them: no program at all.
            DB::table('student_s_y')->insert([
                'student_id' => $student->student_id,
                'team_id' => $team->team_id,
                'school_year' => Term::currentSchoolYear(),
            ]);

            DB::table('capstone_expert_application')->insert([
                'team_id' => $team->team_id,
                'expert_id' => $expert->expert_id,
                'expert_role_id' => $link,
                'status' => 'approved',
            ]);
        }

        $html = $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.experts'))->assertOk()->getContent();

        // BSIT is full from two approved teams whose rows name no program;
        // BSIS shows its empty slot row beside it.
        $this->assertStringContainsString('<span class="capacity-count">2/2</span>', $html);
        $this->assertStringContainsString('<span class="capacity-count">0/1</span>', $html);
        $this->assertStringContainsString('width:100%', $html);
    }

    /** A single overall cap keeps its own long-standing row shape. */
    public function test_a_single_all_cap_still_shows_the_overall_row(): void
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'max_teams' => 2,
        ]);
        $adviserRole = Role::firstOrCreate(['role_name' => 'adviser']);
        $expert->roles()->attach([
            $adviserRole->role_id,
            Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id,
        ]);

        $student = Student::create([
            'email' => 'all.lead@my.cspc.edu.ph', 'f_name' => 'Lead', 'l_name' => 'Er',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        StudentRoster::create([
            'email' => $student->email, 'name' => 'Lead Er', 'section' => '4th · A',
            'program' => 'BSIT', 'school_year' => Term::currentSchoolYear(),
        ]);
        $team = Team::create(['team_name' => 'Overall', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create([
            'student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT',
        ]);
        DB::table('capstone_expert_application')->insert([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => DB::table('expert_roles')
                ->where('expert_id', $expert->expert_id)->where('role_id', $adviserRole->role_id)
                ->value('expert_role_id'),
            'status' => 'approved',
        ]);

        $html = $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.experts'))->assertOk()->getContent();

        $this->assertStringContainsString('1/2 · 1 left', $html);
    }
}
