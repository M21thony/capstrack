<?php

namespace Tests\Feature;

use App\Jobs\SendWebPush;
use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Turning a notification off has to actually stop it.
 *
 * The switches are stored as only the OFFs, so an account that has never
 * opened the settings holds nothing and gets everything - the way every
 * account behaved before the column existed.
 */
class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function expert(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    private function student(): Student
    {
        return Student::factory()->withCompleteProfile()->create();
    }

    public function test_an_account_that_has_never_opened_the_settings_gets_everything(): void
    {
        $expert = $this->expert();

        $this->assertTrue($expert->wants('consultation_booked', 'web'));
        $this->assertTrue($expert->wants('consultation_booked', 'email'));
        $this->assertTrue($this->student()->wants('review_finished', 'web'));
    }

    public function test_saving_keeps_only_the_switches_that_were_turned_off(): void
    {
        $expert = $this->expert();

        $this->actingAs($expert, 'expert')
            ->putJson('/instructor/notifications/preferences', [
                'all' => true,
                'on' => [
                    'web.consultation_booked' => false,
                    'email.consultation_booked' => true,
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $expert->refresh();

        $this->assertFalse($expert->wants('consultation_booked', 'web'));
        $this->assertTrue($expert->wants('consultation_booked', 'email'));

        // Anything the form did not tick is off, including types that were
        // not on screen at all.
        $this->assertFalse($expert->wants('document_submitted', 'web'));
    }

    public function test_the_top_switch_turns_everything_off_at_once(): void
    {
        $expert = $this->expert();

        $this->actingAs($expert, 'expert')
            ->putJson('/instructor/notifications/preferences', ['all' => false, 'on' => []])
            ->assertOk();

        $expert->refresh();

        foreach (['consultation_booked', 'document_submitted', 'review_reminder'] as $type) {
            $this->assertFalse($expert->wants($type, 'web'), $type.' should be off');
            $this->assertFalse($expert->wants($type, 'email'), $type.' email should be off');
        }
    }

    public function test_a_switched_off_notification_is_never_written_or_pushed(): void
    {
        Queue::fake();

        $expert = $this->expert();
        $expert->saveNotificationPrefs(['web.consultation_booked' => false], true);

        Notifier::toExpert($expert, 'consultation_booked', 'Consultation booked', 'A team booked you.');

        $this->assertSame(0, Notification::where('expert_id', $expert->expert_id)->count());
        Queue::assertNotPushed(SendWebPush::class);
    }

    public function test_a_switched_on_notification_still_goes_out(): void
    {
        Queue::fake();

        $expert = $this->expert();

        Notifier::toExpert($expert, 'consultation_booked', 'Consultation booked', 'A team booked you.');

        $this->assertSame(1, Notification::where('expert_id', $expert->expert_id)->count());
        Queue::assertPushed(SendWebPush::class);
    }

    public function test_a_student_saves_their_own_switches_on_their_own_route(): void
    {
        $student = $this->student();

        $this->actingAs($student, 'web')
            ->putJson('/notifications/preferences', [
                'all' => true,
                'on' => ['web.review_finished' => true, 'web.milestone_due' => false],
            ])
            ->assertOk();

        $student->refresh();

        $this->assertTrue($student->wants('review_finished', 'web'));
        $this->assertFalse($student->wants('milestone_due', 'web'));
    }
}
