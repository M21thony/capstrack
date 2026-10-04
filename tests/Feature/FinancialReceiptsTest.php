<?php

namespace Tests\Feature;

use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Each member uploads their own receipt, with its OR number, to their team
 * leader, and may swap a photo the leader still holds or one that was sent
 * back. The leader sends the team's receipts to the CaPSA together, only once
 * every member has one. A receipt sent to the CaPSA, or verified, is final.
 * The CaPSA sees the fees and receipts of her own sections, and nothing that
 * a leader is still holding.
 */
class FinancialReceiptsTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function fee(string $stage = 'Title Defense'): ExpensePost
    {
        return ExpensePost::create(['title' => $stage.' Fee', 'course' => 'BSIT', 'year_level' => '3rd', 'defense_type' => $stage, 'amount' => 500, 'is_active' => true]);
    }

    private function upload($student, ExpensePost $fee, ?string $or = '0012345')
    {
        return $this->actingAs($student)->post(route('student.financial.submit'), array_filter([
            'expense_post_id' => $fee->id,
            'receipt_number' => $or,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
        ], fn ($v) => $v !== null));
    }

    public function test_a_member_uploads_the_or_number_with_the_photo_and_can_replace_a_waiting_one(): void
    {
        [, $student] = $this->teamInSection('Alpha');
        $fee = $this->fee();

        $this->upload($student, $fee, ' or 0012345 ')->assertSessionHasNoErrors();
        $first = FinancialRecord::sole()->receipt_image;
        $this->assertSame('OR 0012345', FinancialRecord::sole()->receipt_number);

        $this->upload($student, $fee, '0012399')->assertSessionHasNoErrors();

        $record = FinancialRecord::sole();
        $this->assertSame('held', $record->f_record_status);
        $this->assertSame('0012399', $record->receipt_number);
        $this->assertNotSame($first, $record->receipt_image);
    }

    /** The OR number and the photo go together: either alone is refused. */
    public function test_a_receipt_needs_both_the_or_number_and_the_photo(): void
    {
        [, $student] = $this->teamInSection('Alpha');
        $fee = $this->fee();

        $this->upload($student, $fee, null)->assertSessionHasErrors('receipt_number');
        $this->actingAs($student)->post(route('student.financial.submit'), ['expense_post_id' => $fee->id, 'receipt_number' => '0012345'])
            ->assertSessionHasErrors('receipt_image');

        $this->assertSame(0, FinancialRecord::count());
    }

    /** The CaPSA is told when the same OR number is on another receipt. */
    public function test_the_same_or_number_on_two_receipts_is_flagged(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $a] = $this->teamInSection('Alpha');
        [, $b] = $this->teamInSection('Bravo');
        $fee = $this->fee();
        $this->upload($a, $fee, '0012345');
        $this->upload($b, $fee, '0012345');
        FinancialRecord::query()->update(['f_record_status' => 'pending']);

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.financial'))
            ->assertOk()
            ->assertViewHas('state', fn ($state) => $state['receipts'][$fee->id.':'.$a->student_id]['or'] === '0012345'
                && $state['receipts'][$fee->id.':'.$a->student_id]['or_also'] === [trim($b->f_name.' '.$b->l_name)]);
    }

    public function test_a_sent_back_receipt_waits_again_once_replaced(): void
    {
        [, $student] = $this->teamInSection('Alpha');
        $fee = $this->fee();
        $this->upload($student, $fee);
        FinancialRecord::sole()->update(['f_record_status' => 'rejected', 'rejection_reason' => 'Blurry']);

        $this->upload($student, $fee)->assertSessionHasNoErrors();

        $this->assertSame(['held', null], [FinancialRecord::sole()->f_record_status, FinancialRecord::sole()->rejection_reason]);
    }

    public function test_a_verified_receipt_cannot_be_replaced(): void
    {
        [, $student] = $this->teamInSection('Alpha');
        $fee = $this->fee();
        $this->upload($student, $fee);
        FinancialRecord::sole()->update(['f_record_status' => 'verified']);

        $this->upload($student, $fee)->assertSessionHasErrors('receipt_image');

        $this->assertSame('verified', FinancialRecord::sole()->f_record_status);
    }

    public function test_the_student_page_lists_only_defense_fees(): void
    {
        [, $student] = $this->teamInSection('Alpha');
        $this->fee('Pre Oral Defense');
        ExpensePost::create(['title' => 'Binding', 'course' => 'BSIT', 'year_level' => '4th', 'amount' => 100, 'is_active' => true]);

        $this->actingAs($student)
            ->get(route('student.team', ['tab' => 'financial']))
            ->assertOk()
            ->assertViewHas('financialState', fn ($state) => collect($state['fees'])->pluck('stage')->all() === ['Pre Oral Defense']);
    }

    public function test_the_capsa_sees_fees_and_receipts_for_her_sections_only(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$mine, $member] = $this->teamInSection('Mine');
        [, $outsider] = $this->teamInSection('Theirs', yearSection: '3rd · B');
        $fee = $this->fee();
        $this->upload($member, $fee);
        $this->upload($outsider, $fee);
        FinancialRecord::query()->update(['f_record_status' => 'pending']);

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.financial'))
            ->assertOk()
            ->assertViewHas('state', function ($state) use ($mine, $fee, $member) {
                return collect($state['teams'])->pluck('name')->all() === [$mine->team_name]
                    && array_keys($state['receipts']) === [$fee->id.':'.$member->student_id]
                    && $state['receipts'][$fee->id.':'.$member->student_id]['status'] === 'pending';
            });
    }

    /** A team with a leader and two more members. */
    private function teamOfThree(string $name = 'Trio'): array
    {
        [$team, $leader] = $this->teamInSection($name);

        $members = collect([1, 2])->map(function () use ($team) {
            $member = Student::factory()->withCompleteProfile()->create([
                'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
            ]);
            StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

            return $member;
        });

        return [$team, $leader, $members[0], $members[1]];
    }

    private function sendTeam(Student $student, ExpensePost $fee)
    {
        return $this->actingAs($student)->postJson(route('api.student.financial.submit-team'), ['expense_post_id' => $fee->id]);
    }

    public function test_an_upload_goes_to_the_leader_and_the_capsa_does_not_see_it_yet(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $leader, $member] = $this->teamOfThree();
        $fee = $this->fee();

        $this->upload($member, $fee)->assertSessionHasNoErrors();

        $this->assertSame('held', FinancialRecord::sole()->f_record_status);

        $this->actingAs($capsa, 'expert')->get(route('capsa.financial'))->assertOk()
            ->assertViewHas('state', fn ($state) => $state['receipts'] === []);

        $this->actingAs($leader)->get(route('student.team', ['tab' => 'financial']))->assertOk()
            ->assertViewHas('financialState', fn ($state) => $state['is_leader'] === true
                && $state['receipts'][$fee->id.':'.$member->student_id]['status'] === 'held');
        $this->actingAs($member)->get(route('student.team', ['tab' => 'financial']))
            ->assertViewHas('financialState', fn ($state) => $state['is_leader'] === false);
    }

    public function test_the_leader_cannot_send_the_team_until_every_member_has_a_receipt(): void
    {
        [, $leader, $a] = $this->teamOfThree();
        $fee = $this->fee();
        $this->upload($leader, $fee, 'OR-1');
        $this->upload($a, $fee, 'OR-2');

        $this->sendTeam($leader, $fee)->assertStatus(422)
            ->assertJsonPath('errors.expense_post_id.0', '1 of 3 teammates still needs to upload a receipt for this fee.');

        $this->assertSame(['held', 'held'], FinancialRecord::pluck('f_record_status')->all());
    }

    public function test_the_leader_sends_all_receipts_at_once_and_the_capsa_then_sees_them(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $leader, $a, $b] = $this->teamOfThree();
        $fee = $this->fee();
        $this->upload($leader, $fee, 'OR-1');
        $this->upload($a, $fee, 'OR-2');
        $this->upload($b, $fee, 'OR-3');

        $this->sendTeam($leader, $fee)->assertOk()->assertJsonPath('data.sent', 3);

        $this->assertSame(['pending', 'pending', 'pending'], FinancialRecord::pluck('f_record_status')->all());
        $this->actingAs($capsa, 'expert')->get(route('capsa.financial'))
            ->assertViewHas('state', fn ($state) => count($state['receipts']) === 3);

        $this->sendTeam($leader, $fee)->assertStatus(422);
    }

    public function test_only_the_leader_can_send_the_team(): void
    {
        [, $leader, $a, $b] = $this->teamOfThree();
        $fee = $this->fee();
        foreach ([$leader, $a, $b] as $i => $who) {
            $this->upload($who, $fee, 'OR-'.$i);
        }

        $this->sendTeam($a, $fee)->assertStatus(422)
            ->assertJsonPath('errors.expense_post_id.0', 'Only your team leader can send the receipts to the CaPSA.');

        $this->assertSame(['held', 'held', 'held'], FinancialRecord::pluck('f_record_status')->all());
    }

    public function test_a_sent_back_receipt_blocks_a_resend_until_replaced_and_only_that_one_goes_again(): void
    {
        [, $leader, $a, $b] = $this->teamOfThree();
        $fee = $this->fee();
        foreach ([$leader, $a, $b] as $i => $who) {
            $this->upload($who, $fee, 'OR-'.$i);
        }
        $this->sendTeam($leader, $fee)->assertOk();
        FinancialRecord::where('student_id', $a->student_id)->update(['f_record_status' => 'rejected', 'rejection_reason' => 'Blurry']);
        FinancialRecord::where('student_id', $b->student_id)->update(['f_record_status' => 'verified']);

        $this->sendTeam($leader, $fee)->assertStatus(422);

        $this->upload($a, $fee, 'OR-9')->assertSessionHasNoErrors();
        $this->sendTeam($leader, $fee)->assertOk()->assertJsonPath('data.sent', 1);

        $this->assertSame('pending', FinancialRecord::where('student_id', $a->student_id)->value('f_record_status'));
        $this->assertSame('verified', FinancialRecord::where('student_id', $b->student_id)->value('f_record_status'));
    }

    public function test_a_receipt_already_sent_to_the_capsa_cannot_be_replaced(): void
    {
        [, $student] = $this->teamInSection('Solo');
        $fee = $this->fee();
        $this->upload($student, $fee);
        $this->sendTeam($student, $fee)->assertOk();

        $this->upload($student, $fee)->assertSessionHasErrors('receipt_image');

        $this->assertSame('pending', FinancialRecord::sole()->f_record_status);
    }

    public function test_the_capsa_cannot_verify_or_reject_a_receipt_a_leader_is_still_holding(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $student] = $this->teamInSection('Holding');
        $fee = $this->fee();
        $this->upload($student, $fee);
        $record = FinancialRecord::sole();

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('api.admin.financial.update', $record), ['status' => 'approved', 'or_confirmed' => true])
            ->assertStatus(422);
        $this->actingAs($capsa, 'expert')
            ->patchJson(route('api.admin.financial.update', $record), ['status' => 'rejected', 'rejection_reason' => 'x'])
            ->assertStatus(422);

        $this->assertSame('held', $record->fresh()->f_record_status);
    }

    /** Financial Records is Team Management's second tab; the old address opens it. */
    public function test_the_old_address_opens_the_financial_tab_of_team_management(): void
    {
        [, $student] = $this->teamInSection('Alpha');

        $this->actingAs($student)->get(route('student.financial'))
            ->assertRedirect(route('student.team', ['tab' => 'financial']));
    }

    public function test_team_management_carries_both_tabs_and_opens_on_the_one_asked_for(): void
    {
        [, $student] = $this->teamInSection('Alpha');
        $this->fee();

        $team = $this->actingAs($student)->get(route('student.team'))->assertOk()->getContent();
        $this->assertStringContainsString('data-tm-tab="financial"', $team);
        $this->assertStringContainsString('id="fx-student"', $team, 'the receipts are on the page, behind their tab');
        $this->assertMatchesRegularExpression('/id="tm-panel-financial"[^>]*hidden/', $team);
        $this->assertStringContainsString('<body class="">', $team);

        $fin = $this->actingAs($student)->get(route('student.team', ['tab' => 'financial']))->assertOk()->getContent();
        $this->assertStringContainsString('<body class="tm-on-fin">', $fin);
        $this->assertDoesNotMatchRegularExpression('/id="tm-panel-financial"[^>]*hidden/', $fin);
        $this->assertMatchesRegularExpression('/id="tm-tab-financial"[^>]*aria-selected="true"/', $fin);
    }

    public function test_the_sidebar_has_no_separate_financial_records_link(): void
    {
        [, $student] = $this->teamInSection('Alpha');

        $html = $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-tour-target="student.financial"', $html);
        $this->assertStringNotContainsString('href="'.route('student.financial').'"', $html);
    }
}
