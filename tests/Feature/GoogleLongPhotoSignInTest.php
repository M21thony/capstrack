<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A school Google account's photo address can run past 255 characters. It is
 * saved on every sign-in, and the column refused it: those students hit a 500
 * each time and could not get in.
 */
class GoogleLongPhotoSignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_photo_columns_hold_any_length(): void
    {
        $this->assertSame('text', Schema::getColumnType('students', 'p_picture'));
        $this->assertSame('text', Schema::getColumnType('capstone_experts', 'profile_picture'));
    }

    public function test_a_student_with_a_long_photo_address_signs_in(): void
    {
        $photo = 'https://lh3.googleusercontent.com/a-/ALV-Uj'.str_repeat('W-qMbjMwn-O37rCKDBMMG1nRgdPUhjwTq4yICNNnsRk', 9).'=s96-c';
        $this->assertGreaterThan(255, strlen($photo));

        StudentRoster::create([
            'email' => 'tester@my.cspc.edu.ph', 'name' => 'Tester, Test', 'student_number' => '221001155',
            'section' => '4th · B', 'program' => 'BSIT', 'school_year' => '2026-2027',
        ]);
        Student::factory()->create(['email' => 'tester@my.cspc.edu.ph', 'student_number' => '221001155']);

        $this->withSession(['oauth_state' => 's']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::response(['email' => 'tester@my.cspc.edu.ph', 'given_name' => 'Test', 'family_name' => 'Tester', 'picture' => $photo]),
        ]);

        $this->get('/login/google/callback?state=s&code=c')->assertRedirect(route('student.dashboard'));

        $this->assertSame($photo, Student::where('email', 'tester@my.cspc.edu.ph')->value('p_picture'));
    }
}
