<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Student;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The data every student view shares - the bell, join-request popups, the
 * sidebar's profile panel - is worked out once a request.
 *
 * A student page renders the page itself and several student partials, and
 * the composer runs for each of them. It used to query everything again each
 * time, which made every student page slow.
 */
class StudentViewDataTest extends TestCase
{
    use RefreshDatabase;

    private function bellQueriesDuring(callable $request): int
    {
        $bellQueries = 0;

        DB::listen(function (QueryExecuted $query) use (&$bellQueries): void {
            // The bell's list: the latest fifteen, read or not. Only the
            // composer asks for exactly this.
            if (str_contains($query->sql, 'from "notifications"') && str_contains($query->sql, 'limit 15')) {
                $bellQueries++;
            }
        });

        $request();

        return $bellQueries;
    }

    public function test_a_student_page_asks_for_the_bell_once(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();

        $queries = $this->bellQueriesDuring(
            fn () => $this->actingAs($student, 'web')->get(route('student.dashboard'))->assertOk()
        );

        $this->assertSame(1, $queries);
    }

    /** Kept for one request only: the next page shows what arrived since. */
    public function test_the_next_request_sees_a_new_notification(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();

        $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Your adviser left a comment');

        Notification::create([
            'student_id' => $student->student_id,
            'type' => 'announcement',
            'title' => 'Your adviser left a comment',
            'message' => 'See chapter 2.',
            'link' => route('student.dashboard'),
        ]);

        $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Your adviser left a comment');
    }
}
