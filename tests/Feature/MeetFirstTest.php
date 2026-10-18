<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ConsultationSchedule;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Models\Term;
use App\Support\ConsultationBoard;
use App\Support\TeamApplications;
use App\Support\TeamTodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Meet first: an expert who wants to talk to a team before answering asks
 * them to book one of their consultation times, and decides after.
 */
class MeetFirstTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $expert;
    private Student $leader;
    private TeamExpertAssignment $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expert = CapstoneExpert::factory()->create([
            'first_name' => 'Grom', 'last_name' => 'Hellscream', 'approved_status' => true, 'is_admin' => false,
        ]);
        $role = Role::firstOrCreate(['role_name' => 'adviser']);
        $this->expert->roles()->attach($role->role_id);

        $this->leader = Student::create([
            'email' => 'leader@my.cspc.edu.ph', 'f_name' => 'Mark', 'l_name' => 'B',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        StudentRoster::create([
            'email' => $this->leader->email, 'name' => 'Mark B', 'section' => '4th · A',
            'program' => 'BSIT', 'school_year' => Term::currentSchoolYear(),
        ]);
        $team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $this->leader->student_id]);
        StudentSchoolYear::create(['student_id' => $this->leader->student_id, 'team_id' => $team->team_id]);

        $this->application = TeamExpertAssignment::create([
            'team_id' => $team->team_id, 'expert_id' => $this->expert->expert_id,
            'role_id' => $role->role_id, 'status' => 'pending',
        ]);
    }

    private function ask(array $body, ?CapstoneExpert $as = null, ?TeamExpertAssignment $on = null)
    {
        return $this->actingAs($as ?? $this->expert, 'expert')
            ->patchJson('/instructor/assignments/'.($on ?? $this->application)->assignment_id, $body + ['status' => 'meet']);
    }

    private function mondaySlot(): ConsultationSchedule
    {
        return ConsultationSchedule::create([
            'expert_id' => $this->expert->expert_id, 'available_date' => 'Monday',
            'start_time' => '09:00', 'end_time' => '10:00', 'consultation_limit' => 2,
            'slot_type' => 'consult', 'recur' => 'weekly', 'is_available' => true,
        ]);
    }

    private function bookMonday(ConsultationSchedule $slot)
    {
        return $this->actingAs($this->leader, 'web')->postJson('/student/consultation/book', [
            'schedule_id' => $slot->schedule_id,
            'session_date' => now()->next(Carbon::MONDAY)->toDateString(),
        ]);
    }

    private function todoWhy(): ?string
    {
        return app(TeamTodo::class)->forExpert($this->expert, collect(), 'expert')
            ->firstWhere('kind', 'invite')?->why;
    }

    /** The note is kept and the leader is told, with where to book. */
    public function test_asking_to_meet_stores_the_note_and_tells_the_leader(): void
    {
        $this->ask(['reason' => 'Bring your scope and the problem statement.'])
            ->assertOk()->assertJson(['success' => true]);

        $a = $this->application->fresh();
        $this->assertSame('pending', $a->status);
        $this->assertSame('Bring your scope and the problem statement.', $a->meet_note);
        $this->assertNotNull($a->meet_asked_at);

        $n = Notification::where('student_id', $this->leader->student_id)->where('type', 'expert_meet_first')->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('Bring your scope', $n->message);
    }

    /** A note too short to tell the team anything, or asking twice, is refused. */
    public function test_a_short_note_and_a_second_ask_are_refused(): void
    {
        $this->ask(['reason' => 'Meet me'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->assertNull($this->application->fresh()->meet_asked_at);

        $this->ask(['reason' => 'Bring your scope please.'])->assertOk();
        $this->ask(['reason' => 'Bring your scope please.'])->assertStatus(422)
            ->assertJson(['message' => 'You already asked this team to meet first.']);
        $this->assertSame(1, Notification::where('type', 'expert_meet_first')->count());
    }

    /** Only the expert asked can ask, and only while the request is still open. */
    public function test_another_expert_or_an_answered_request_is_refused(): void
    {
        $other = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        $this->ask(['reason' => 'Bring your scope please.'], $other)->assertNotFound();

        $this->application->update(['status' => 'rejected']);
        $this->ask(['reason' => 'Bring your scope please.'])->assertStatus(422);
        $this->assertNull($this->application->fresh()->meet_asked_at);
    }

    /** Booking one of the offered times turns "waiting" into "Meeting booked". */
    public function test_booking_an_offered_time_marks_the_meeting_booked(): void
    {
        $slot = $this->mondaySlot();
        $this->ask(['reason' => 'Bring your scope please.'])->assertOk();

        $times = app(ConsultationBoard::class)->openTimes((int) $this->expert->expert_id);
        $this->assertNotEmpty($times);
        $this->assertSame($slot->schedule_id, $times[0]['schedule_id']);
        $this->assertSame(Carbon::MONDAY, Carbon::parse($times[0]['date'])->dayOfWeek);

        $details = fn () => app(TeamApplications::class)->forExpert($this->expert)['details'][$this->application->assignment_id]['meet'];
        $this->assertNull($details()['booked']);

        $this->bookMonday($slot)->assertSuccessful();

        $this->assertNotNull($this->application->fresh()->meeting());
        $this->assertStringContainsString('9:00 AM', $details()['booked']);
    }

    /** The expert's To do drops the request while they wait, and brings it back once booked. */
    public function test_the_todo_waits_for_the_booking(): void
    {
        $slot = $this->mondaySlot();
        $this->assertNotNull($this->todoWhy());

        $this->ask(['reason' => 'Bring your scope please.'])->assertOk();
        $this->assertNull($this->todoWhy());

        $html = $this->actingAs($this->leader, 'web')->get(route('student.experts', ['role' => 'adviser']))->assertOk()->getContent();
        $this->assertStringContainsString('wants to meet your team first', $html);
        $this->assertStringContainsString('Book this time', $html);

        $this->bookMonday($slot)->assertSuccessful();
        $this->assertStringStartsWith('Meeting booked', $this->todoWhy());
    }
}
