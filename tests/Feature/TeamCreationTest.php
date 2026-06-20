<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_creation_sets_team_id_in_student_s_y(): void
    {
        $student = Student::create([
            'email' => 'teamleader@my.cspc.edu.ph',
            'f_name' => 'Team',
            'l_name' => 'Leader',
        ]);

        $this->actingAs($student, 'web');

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => null,
        ]);

        $response = $this->post('/team', [
            'team_name' => 'Bulbasaur',
            'team_status' => 'Active',
        ]);

        $response->assertSessionHas('status');
        $response->assertRedirect(route('student.team'));

        $team = Team::where('team_name', 'Bulbasaur')->first();
        $this->assertNotNull($team, 'Team was not created');

        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $this->assertNotNull($sy, 'student_s_y row missing');
        $this->assertEquals($team->team_id, $sy->team_id, 'team_id not synced to student_s_y');
    }
}
