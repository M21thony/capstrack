<?php

namespace Tests\Feature;

use App\Models\Student;
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
            'year' => '4th Year',
            'section' => 'BSCS-4A',
            'student_id' => '2021-00456',
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

    public function test_profile_update_rejects_invalid_avatar_preset(): void
    {
        $student = Student::factory()->create([
            'email' => 'profile.api@my.cspc.edu.ph',
            'f_name' => 'Maria',
            'l_name' => 'Santos',
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

    public function test_completed_user_visiting_onboarding_is_redirected_to_dashboard(): void
    {
        $student = Student::factory()->create([
            'email' => 'done@my.cspc.edu.ph',
            'f_name' => 'Pedro',
            'l_name' => 'Garcia',
            'p_picture' => 'avatar-05',
        ]);

        $this->actingAs($student)
            ->get(route('student.personal-info'))
            ->assertRedirect(route('student.dashboard'));
    }
}
