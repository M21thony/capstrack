<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefenseSchedule;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Database\Seeders\RedefenseSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The sample re-defense teams and their sample students come off the live
 * system; real teams and students stay.
 */
class RemoveSampleRedefenseDataTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        (require base_path('database/migrations/2026_09_24_080125_remove_sample_redefense_teams_and_students.php'))->up();
    }

    private function seedSamples(): void
    {
        // The panel and experts the seeder names by id.
        foreach ([92, 101, 106, 107, 108, 109] as $id) {
            CapstoneExpert::factory()->create(['expert_id' => $id, 'approved_status' => true]);
        }

        $this->seed(RedefenseSampleSeeder::class);
    }

    public function test_the_sample_teams_and_students_are_removed_with_what_they_own(): void
    {
        $this->seedSamples();
        $real = Student::factory()->withCompleteProfile()->create();
        $realTeam = Team::create(['team_name' => 'Torchic Fans', 'team_status' => 'Active', 'leader_id' => $real->student_id]);
        StudentSchoolYear::create(['student_id' => $real->student_id, 'team_id' => $realTeam->team_id]);
        $sampleIds = Student::where('email', 'like', 'sample.%')->pluck('student_id');
        $this->assertCount(6, $sampleIds);
        DB::table('activity_log')->insert(['student_id' => $sampleIds->first(), 'type' => 'x', 'description' => 'x']);

        $this->runMigration();

        $this->assertSame(0, Team::whereIn('team_name', ['Sample Redefense', 'Sample New Title'])->count());
        $this->assertSame(0, Student::whereIn('student_id', $sampleIds)->count());
        $this->assertSame(0, StudentSchoolYear::whereIn('student_id', $sampleIds)->count());
        $this->assertSame(0, DB::table('activity_log')->whereIn('student_id', $sampleIds)->count());
        $this->assertSame(0, CapstonePreProposal::count());
        $this->assertSame(0, DefenseSchedule::count());
        $this->assertSame(0, DB::table('capstone_expert_application')->count());
        $this->assertSame(0, Notification::where('message', 'like', '%Sample%')->count());

        $this->assertModelExists($real);
        $this->assertModelExists($realTeam);
    }

    public function test_a_sample_team_a_real_student_joined_is_kept(): void
    {
        $this->seedSamples();
        $team = Team::where('team_name', 'Sample Redefense')->sole();
        $real = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $real->student_id, 'team_id' => $team->team_id]);

        $this->runMigration();

        $this->assertModelExists($team);
        $this->assertSame([$real->student_id], StudentSchoolYear::where('team_id', $team->team_id)->pluck('student_id')->all());
        $this->assertSame(0, Student::where('email', 'like', 'sample.%')->count());
    }

    public function test_nothing_happens_without_samples(): void
    {
        $student = Student::factory()->withCompleteProfile()->create(['email' => 'sample.person@my.cspc.edu.ph']);

        $this->runMigration();

        $this->assertModelExists($student);
    }
}
