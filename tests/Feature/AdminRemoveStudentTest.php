<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An Admin removes a student's account from Student Accounts, and the
 * student's team is left whole.
 */
class AdminRemoveStudentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => 'admin.remove@test.com', 'first_name' => 'Admin', 'last_name' => 'User',
            'is_admin' => true, 'approved_status' => true,
        ]);
    }

    /** @return array{0: Team, 1: array<int, Student>} */
    private function team(string $name, int $members): array
    {
        $students = [];

        for ($i = 0; $i < $members; $i++) {
            $students[] = Student::factory()->withCompleteProfile()->create(['f_name' => $name.$i, 'l_name' => 'Tester']);
        }

        $team = Team::create(['team_name' => $name, 'team_status' => 'Active', 'leader_id' => $students[0]->student_id]);

        foreach ($students as $student) {
            StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);
        }

        return [$team, $students];
    }

    private function remove(Student $student)
    {
        return $this->actingAs($this->admin(), 'expert')->deleteJson(route('admin.students.destroy', $student->student_id));
    }

    public function test_the_page_offers_remove(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();

        $this->actingAs($this->admin(), 'expert')->get(route('admin.students'))
            ->assertOk()
            ->assertSee(route('admin.students.destroy', $student->student_id), false)
            ->assertSee('Remove student');
    }

    public function test_a_member_is_removed_and_leaves_their_team(): void
    {
        [$team, [$leader, $member]] = $this->team('Squirtle', 2);

        $this->remove($member)->assertOk()->assertJson(['success' => true, 'message' => 'Squirtle1 Tester was removed.']);

        $this->assertModelMissing($member);
        $this->assertSame([$leader->student_id], StudentSchoolYear::where('team_id', $team->team_id)->pluck('student_id')->all());
        $this->assertSame($leader->student_id, (int) $team->fresh()->leader_id);
    }

    public function test_a_leaders_team_passes_to_the_next_member(): void
    {
        [$team, [$leader, $next]] = $this->team('Charmander', 3);

        $this->remove($leader)->assertOk()
            ->assertJsonPath('message', 'Charmander0 Tester was removed. Charmander1 Tester now leads Charmander.');

        $this->assertSame($next->student_id, (int) $team->fresh()->leader_id);
    }

    /** No Torchic: a team with nobody left goes with its last member. */
    public function test_a_team_with_no_one_left_is_removed_too(): void
    {
        [$team, [$only]] = $this->team('Torchic', 1);

        $this->remove($only)->assertOk()->assertJsonPath('message', 'Torchic0 Tester was removed. Torchic had no one else, so it was removed too.');

        $this->assertModelMissing($team);
    }

    public function test_only_an_admin_can_remove(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]));

        $this->actingAs($capsa, 'expert')->deleteJson(route('admin.students.destroy', $student->student_id))->assertForbidden();
        $this->actingAs($student, 'web')->deleteJson(route('admin.students.destroy', $student->student_id))->assertForbidden();

        $this->assertModelExists($student);
    }
}
