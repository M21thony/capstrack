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
        ], $overrides);
    }

    public function test_guest_cannot_access_student_dashboard(): void
    {
        $this->get(route('student.dashboard'))
            ->assertRedirect(route('login'));
    }

    // Regression test: the dashboard (and every other student.* route
    // behind the profile.complete middleware) used to redirect an
    // incomplete profile to the onboarding form. That's intentionally
    // disabled now - there's no way yet to determine a student's number
    // from their institutional email alone, so new sign-ins go straight
    // in instead of being blocked on a form the panel asked to drop.
    public function test_incomplete_profile_can_access_dashboard_directly(): void
    {
        $student = Student::factory()->create([
            'email' => 'new.student@my.cspc.edu.ph',
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk();
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
        $this->assertEquals('221001135', $student->student_number);
        $this->assertNotEquals('221001135', (string) $student->student_id);
        $this->assertEquals('4th · A', $student->year_section);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk();
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
            ->putJson(route('profile.update'), [
                'first_name' => $student->f_name,
                'last_name' => $student->l_name,
                'student_id' => '221009999',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals('221009999', $student->fresh()->student_number);
    }

    public function test_profile_update_does_not_let_avatar_be_tampered_with(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'profile.api@my.cspc.edu.ph',
            'p_picture' => 'https://lh3.googleusercontent.com/a/real-photo',
        ]);

        $this->actingAs($student)
            ->putJson(route('profile.update'), [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'avatar' => '../../storage/evil.jpg',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals('https://lh3.googleusercontent.com/a/real-photo', $student->fresh()->p_picture);
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

    public function test_student_in_team_submitting_form_is_a_no_op(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'locked.student@my.cspc.edu.ph',
            'p_picture' => 'https://lh3.googleusercontent.com/a/real-photo',
        ]);

        $team = Team::create(['team_name' => 'Locked Team']);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'school_year' => 2026,
            'program' => 'BSCS',
        ]);

        $this->actingAs($student)
            ->post(route('student.personal-info.store'), $this->validPersonalInfoPayload())
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('status', 'Your profile is locked while you\'re in a team.');

        $student->refresh();
        $this->assertEquals('https://lh3.googleusercontent.com/a/real-photo', $student->p_picture);
    }

    public function test_student_in_team_cannot_change_other_fields(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'locked.fields@my.cspc.edu.ph',
            'f_name' => 'Original',
            'l_name' => 'Name',
            'course' => 'Bachelor of Science in Computer Science',
            'p_picture' => 'https://lh3.googleusercontent.com/a/real-photo',
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
            ]))
            ->assertRedirect(route('student.dashboard'));

        $student->refresh();
        $this->assertEquals('Original', $student->f_name);
        $this->assertEquals('Name', $student->l_name);
        $this->assertEquals('https://lh3.googleusercontent.com/a/real-photo', $student->p_picture);
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
            ->putJson(route('profile.update'), [
                'first_name' => 'NewName',
                'last_name' => 'NewLast',
            ])
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'You must leave your team first before editing your profile.',
            ]);
    }

    public function test_student_with_team_but_incomplete_profile_can_still_complete_onboarding(): void
    {
        // A student can be assigned a team_id before ever finishing their
        // profile (e.g. added directly by an admin). The lock is meant for
        // an ALREADY-completed profile, not just having a team_id - this
        // student must still be able to submit the onboarding form.
        $student = Student::factory()->create([
            'email' => 'preassigned.team@my.cspc.edu.ph',
        ]);

        $team = Team::create(['team_name' => 'Pre-assigned Team']);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
        ]);

        $this->assertFalse($student->hasCompletedProfile());

        $this->actingAs($student)
            ->post(route('student.personal-info.store'), $this->validPersonalInfoPayload())
            ->assertRedirect(route('student.dashboard'));

        $student->refresh();
        $this->assertTrue($student->hasCompletedProfile());
        $this->assertEquals('221001135', $student->student_number);
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
