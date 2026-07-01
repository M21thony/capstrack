<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalInfoOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function validPersonalInfoPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan',
            'middle_name' => null,
            'last_name' => 'Dela Cruz',
            'course' => 'Bachelor of Science in Computer Science',
            'year' => '4th',
            'section' => 'A',
            'student_id' => '221001135',
            'avatar' => 'avatar-01',
        ], $overrides);
    }

    public function test_guest_cannot_access_student_dashboard(): void
    {
        $this->get(route('student.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_incomplete_profile_redirected_from_dashboard_to_onboarding(): void
    {
        $student = Student::factory()->create([
            'email' => 'new.student@my.cspc.edu.ph',
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertRedirect(route('student.personal-info'));
    }

    public function test_incomplete_profile_can_access_personal_info_page(): void
    {
        $student = Student::factory()->create([
            'email' => 'new.student@my.cspc.edu.ph',
        ]);

        $this->actingAs($student)
            ->get(route('student.personal-info'))
            ->assertOk()
            ->assertSee('Complete your profile');
    }

    public function test_completing_personal_info_grants_dashboard_access(): void
    {
        $student = Student::factory()->create([
            'email' => 'complete.me@my.cspc.edu.ph',
        ]);

        $this->actingAs($student)
            ->post(route('student.personal-info.store'), $this->validPersonalInfoPayload())
            ->assertRedirect(route('student.dashboard'));

        $student->refresh();

        $this->assertTrue($student->hasCompletedProfile());
        $this->assertEquals('avatar-01', $student->p_picture);
        $this->assertEquals('221001135', $student->student_number);
        $this->assertNotEquals('221001135', (string) $student->student_id);
        $this->assertEquals('4th · A', $student->year_section);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk();
    }

    public function test_invalid_avatar_preset_is_rejected(): void
    {
        $student = Student::factory()->create([
            'email' => 'avatar.test@my.cspc.edu.ph',
        ]);

        $this->actingAs($student)
            ->post(route('student.personal-info.store'), $this->validPersonalInfoPayload([
                'avatar' => 'custom-upload.png',
            ]))
            ->assertSessionHasErrors(['avatar']);

        $this->assertFalse($student->fresh()->hasCompletedProfile());
    }

    public function test_invalid_student_id_format_is_rejected(): void
    {
        $student = Student::factory()->create([
            'email' => 'format.test@my.cspc.edu.ph',
        ]);

        $this->actingAs($student)
            ->post(route('student.personal-info.store'), $this->validPersonalInfoPayload([
                'student_id' => '2021-00456',
            ]))
            ->assertSessionHasErrors(['student_id']);

        $this->assertNull($student->fresh()->student_number);
    }

    public function test_duplicate_student_id_is_rejected(): void
    {
        Student::factory()->create([
            'email' => 'existing@my.cspc.edu.ph',
            'student_number' => '221001135',
        ]);

        $student = Student::factory()->create([
            'email' => 'duplicate.test@my.cspc.edu.ph',
        ]);

        $this->actingAs($student)
            ->post(route('student.personal-info.store'), $this->validPersonalInfoPayload([
                'student_id' => '221001135',
            ]))
            ->assertSessionHasErrors(['student_id']);
    }

    public function test_profile_update_allows_changing_student_number(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'profile.api@my.cspc.edu.ph',
            'student_number' => '221001135',
        ]);

        $this->actingAs($student)
            ->postJson(route('profile.update'), [
                'first_name' => $student->f_name,
                'last_name' => $student->l_name,
                'student_id' => '221009999',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals('221009999', $student->fresh()->student_number);
    }

    public function test_profile_update_rejects_invalid_avatar_preset(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'profile.api@my.cspc.edu.ph',
            'p_picture' => 'avatar-03',
        ]);

        $this->actingAs($student)
            ->postJson(route('profile.update'), [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'avatar' => '../../storage/evil.jpg',
            ])
            ->assertUnprocessable()
            ->assertJson(['success' => false]);

        $this->assertEquals('avatar-03', $student->fresh()->p_picture);
    }

    public function test_has_completed_profile_requires_student_number(): void
    {
        $student = Student::factory()->create([
            'email' => 'missing.id@my.cspc.edu.ph',
            'f_name' => 'Ana',
            'l_name' => 'Reyes',
            'p_picture' => 'avatar-02',
        ]);

        $this->assertFalse($student->hasCompletedProfile());
    }

    public function test_completed_user_visiting_onboarding_is_redirected_to_dashboard(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'done@my.cspc.edu.ph',
            'f_name' => 'Pedro',
            'l_name' => 'Garcia',
            'p_picture' => 'avatar-05',
        ]);

        $this->actingAs($student)
            ->get(route('student.personal-info'))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_student_in_team_can_access_edit_page_with_locked_fields(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'teamed.student@my.cspc.edu.ph',
        ]);

        $team = Team::create(['team_name' => 'Test Team']);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'school_year' => 2026,
            'program' => 'BSCS',
        ]);

        $this->actingAs($student)
            ->get(route('student.personal-info.edit'))
            ->assertOk()
            ->assertSee('Locked while in a team');
    }

    public function test_student_in_team_can_update_avatar_only(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'update.avatar@my.cspc.edu.ph',
            'p_picture' => 'avatar-01',
        ]);

        $team = Team::create(['team_name' => 'Avatar Team']);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'school_year' => 2026,
            'program' => 'BSCS',
        ]);

        $this->actingAs($student)
            ->post(route('student.personal-info.store'), $this->validPersonalInfoPayload(['avatar' => 'avatar-03']))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('status', 'Your avatar has been updated.');

        $student->refresh();
        $this->assertEquals('avatar-03', $student->p_picture);
    }

    public function test_student_in_team_cannot_change_other_fields(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'locked.fields@my.cspc.edu.ph',
            'f_name' => 'Original',
            'l_name' => 'Name',
            'course' => 'Bachelor of Science in Computer Science',
            'p_picture' => 'avatar-01',
        ]);

        $team = Team::create(['team_name' => 'Locked Fields Team']);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'school_year' => 2026,
            'program' => 'BSCS',
        ]);

        $this->actingAs($student)
            ->post(route('student.personal-info.store'), $this->validPersonalInfoPayload([
                'first_name' => 'Hacked',
                'last_name' => 'Changed',
                'avatar' => 'avatar-02',
            ]))
            ->assertRedirect(route('student.dashboard'));

        $student->refresh();
        $this->assertEquals('Original', $student->f_name);
        $this->assertEquals('Name', $student->l_name);
        $this->assertEquals('avatar-02', $student->p_picture);
    }

    public function test_student_in_team_cannot_ajax_update_profile(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'ajax.blocked@my.cspc.edu.ph',
        ]);

        $team = Team::create(['team_name' => 'Ajax Team']);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'school_year' => 2026,
            'program' => 'BSCS',
        ]);

        $this->actingAs($student)
            ->postJson(route('profile.update'), [
                'first_name' => 'NewName',
                'last_name' => 'NewLast',
            ])
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'You must leave your team first before editing your profile.',
            ]);
    }

    public function test_student_without_team_can_access_edit_page(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'free.edit@my.cspc.edu.ph',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'school_year' => 2026,
            'program' => 'BSCS',
            'team_id' => null,
        ]);

        $this->actingAs($student)
            ->get(route('student.personal-info.edit'))
            ->assertOk()
            ->assertSee('Update your profile');
    }
}
