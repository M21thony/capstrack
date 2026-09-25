<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marking notifications read changes how they look, never whether they are
 * there: the bell keeps listing them, without the unread highlight.
 */
class NotificationsStayAfterReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_students_notifications_stay_in_the_bell_after_mark_all_read(): void
    {
        $team = Team::create(['team_name' => 'Bell Team', 'project_title' => 'Bells', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);
        Notification::create(['student_id' => $student->student_id, 'type' => 'announcement', 'title' => 'Chapter 1 is due Friday', 'message' => 'Hand it in.', 'is_read' => false]);
        Notification::create(['student_id' => $student->student_id, 'type' => 'announcement', 'title' => 'An older one already read', 'message' => 'Old.', 'is_read' => true]);

        // Before: both listed, the unread one highlighted, and the button offered.
        $this->actingAs($student)->get(route('student.progress'))
            ->assertOk()
            ->assertSee('Chapter 1 is due Friday')
            ->assertSee('An older one already read')
            ->assertSee('class="notif-item is-unread"', false)
            ->assertSee('id="notif-mark-all-btn"', false);

        $this->actingAs($student)->patchJson(route('notifications.mark-all-read'))->assertOk();

        $this->assertSame(2, Notification::where('student_id', $student->student_id)->count(), 'nothing is deleted');
        $this->assertSame(0, Notification::where('student_id', $student->student_id)->where('is_read', false)->count());

        // After: still both listed, none highlighted, and nothing left to mark.
        $this->actingAs($student)->get(route('student.progress'))
            ->assertOk()
            ->assertSee('Chapter 1 is due Friday')
            ->assertSee('An older one already read')
            ->assertDontSee('class="notif-item is-unread"', false)
            ->assertDontSee('id="notif-mark-all-btn"', false);
    }

    public function test_an_experts_notifications_stay_in_the_bell_after_mark_all_read(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        Notification::create(['expert_id' => $expert->expert_id, 'type' => 'document_submitted', 'title' => 'A team sent Chapter 2', 'message' => 'Review it.', 'is_read' => false]);

        $this->actingAs($expert, 'expert')->patchJson(route('instructor.notifications.mark-all-read'))->assertOk();

        $this->assertSame(1, Notification::where('expert_id', $expert->expert_id)->count(), 'nothing is deleted');

        $this->actingAs($expert, 'expert')->get(route('instructor.grading'))
            ->assertOk()
            ->assertSee('A team sent Chapter 2')
            ->assertDontSee('notif-item is-unread', false);
    }
}
