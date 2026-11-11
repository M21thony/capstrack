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
 * Each member uploads their own receipt, with its OR number, and it goes
 * straight to the CaPSA/TSA, waiting to be checked - there is no stop with the
 * team leader. A receipt the CaPSA/TSA has, or has verified, is final until she
 * sends it back; a sent-back one is replaced and waits again. The CaPSA/TSA sees
 * the fees and receipts of her own sections.
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

    public function test_a_member_uploads_the_or_number_with_the_photo(): void
    {
        [, $student] = $this->teamInSection('Alpha');
        $fee = $this->fee();

        $this->upload($student, $fee, ' or 0012345 ')->assertSessionHasNoErrors();

        $record = FinancialRecord::sole();
        $this->assertSame('OR 0012345', $record->receipt_number);
        $this->assertSame('pending', $record->f_record_status);
        $this->assertNotNull($record->receipt_image);
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

    /** The CaPSA/TSA is told when the same OR number is on another receipt. */
    public function test_the_same_or_number_on_two_receipts_is_flagged(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $a] = $this->teamInSection('Alpha');
        [, $b] = $this->teamInSection('Bravo');
        $fee = $this->fee();
        $this->upload($a, $fee, '0012345');
        $this->upload($b, $fee, '0012345');

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

        $this->assertSame(['pending', null], [FinancialRecord::sole()->f_record_status, FinancialRecord::sole()->rejection_reason]);
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

    public function test_an_upload_goes_straight_to_the_capsa(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, , $member] = $this->teamOfThree();
        $fee = $this->fee();

        $this->upload($member, $fee)->assertSessionHasNoErrors();

        $this->assertSame('pending', FinancialRecord::sole()->f_record_status);

        $this->actingAs($capsa, 'expert')->get(route('capsa.financial'))->assertOk()
            ->assertViewHas('state', fn ($state) => array_keys($state['receipts']) === [$fee->id.':'.$member->student_id]);

        $this->actingAs($member)->get(route('student.team', ['tab' => 'financial']))->assertOk()
            ->assertViewHas('financialState', fn ($state) => $state['receipts'][$fee->id.':'.$member->student_id]['status'] === 'pending');
    }

    /** Nobody waits on the rest of the team: each receipt reaches the CaPSA/TSA as it is uploaded. */
    public function test_each_members_receipt_reaches_the_capsa_on_its_own(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $leader, $a] = $this->teamOfThree();
        $fee = $this->fee();

        $this->upload($a, $fee, 'OR-2');
        $this->actingAs($capsa, 'expert')->get(route('capsa.financial'))
            ->assertViewHas('state', fn ($state) => count($state['receipts']) === 1);

        $this->upload($leader, $fee, 'OR-1');
        $this->actingAs($capsa, 'expert')->get(route('capsa.financial'))
            ->assertViewHas('state', fn ($state) => count($state['receipts']) === 2);

        $this->assertSame(['pending', 'pending'], FinancialRecord::pluck('f_record_status')->all());
    }

    /** The leader's "send the team's receipts" step is gone. */
    public function test_there_is_no_team_send_step(): void
    {
        [, $leader] = $this->teamOfThree();
        $fee = $this->fee();

        $this->actingAs($leader)->postJson('/api/student/financial/team-submit', ['expense_post_id' => $fee->id])
            ->assertNotFound();

        $this->actingAs($leader)->get(route('student.team', ['tab' => 'financial']))->assertOk()
            ->assertDontSee('s-team-submit', false)
            ->assertDontSee('team leader sends', false);
    }

    public function test_replacing_a_sent_back_receipt_sends_only_that_one_again(): void
    {
        [, $leader, $a, $b] = $this->teamOfThree();
        $fee = $this->fee();
        foreach ([$leader, $a, $b] as $i => $who) {
            $this->upload($who, $fee, 'OR-'.$i);
        }
        FinancialRecord::where('student_id', $a->student_id)->update(['f_record_status' => 'rejected', 'rejection_reason' => 'Blurry']);
        FinancialRecord::where('student_id', $b->student_id)->update(['f_record_status' => 'verified']);

        $this->upload($a, $fee, 'OR-9')->assertSessionHasNoErrors();

        $this->assertSame('pending', FinancialRecord::where('student_id', $a->student_id)->value('f_record_status'));
        $this->assertSame('verified', FinancialRecord::where('student_id', $b->student_id)->value('f_record_status'));
        $this->assertSame('pending', FinancialRecord::where('student_id', $leader->student_id)->value('f_record_status'));
    }

    public function test_a_receipt_waiting_on_the_capsa_cannot_be_replaced(): void
    {
        [, $student] = $this->teamInSection('Solo');
        $fee = $this->fee();
        $this->upload($student, $fee, '0012345');

        $this->upload($student, $fee, '0012399')->assertSessionHasErrors('receipt_image');

        $this->assertSame(['pending', '0012345'], [FinancialRecord::sole()->f_record_status, FinancialRecord::sole()->receipt_number]);
    }

    public function test_the_capsa_can_check_a_receipt_as_soon_as_it_is_uploaded(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $student] = $this->teamInSection('Quick');
        $fee = $this->fee();
        $this->upload($student, $fee);
        $record = FinancialRecord::sole();

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('api.admin.financial.update', $record), ['status' => 'approved', 'or_confirmed' => true])
            ->assertOk();

        $this->assertSame('verified', $record->fresh()->f_record_status);
    }

    /** Receipts a leader was still holding when the change shipped go to the CaPSA/TSA. */
    public function test_receipts_still_held_by_a_leader_are_released_to_the_capsa(): void
    {
        [, $student] = $this->teamInSection('Held');
        $fee = $this->fee();
        $this->upload($student, $fee);
        FinancialRecord::query()->update(['f_record_status' => 'held']);

        (require database_path('migrations/2026_10_20_175311_release_held_receipts_to_the_capsa.php'))->up();

        $this->assertSame('pending', FinancialRecord::sole()->f_record_status);
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

    public function test_the_receipt_form_is_on_the_tab_and_not_in_a_pop_up(): void
    {
        [, $student] = $this->teamInSection('Alpha');
        $this->fee();

        $html = $this->actingAs($student)->get(route('student.team', ['tab' => 'financial']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<form class="fx-sb-form" id="u-form"[^>]*hidden>/', $html);
        $this->assertStringContainsString('<input type="file" id="u-file" accept="image/*" aria-label="Photo of your receipt">', $html);
        $this->assertMatchesRegularExpression('/id="u-send" disabled>/', $html);
        $this->assertStringNotContainsString('id="fx-d-upload"', $html);
        $this->assertStringContainsString('id="fx-d-image"', $html, 'the full-size receipt viewer stays');
        $this->assertStringContainsString('id="tm-toprow-end"', $html, 'the fee tabs go beside the page tabs');
    }

    public function test_a_sent_receipt_waits_for_the_capsa_in_the_senders_row_of_the_whole_team(): void
    {
        [, $leader, $member] = $this->teamOfThree();
        $fee = $this->fee();
        $this->upload($member, $fee)->assertSessionHasNoErrors();

        $this->actingAs($member)->get(route('student.team', ['tab' => 'financial']))->assertOk()
            ->assertViewHas('financialState', function ($state) use ($fee, $member, $leader) {
                return count($state['members']) === 3
                    && $state['receipts'][$fee->id.':'.$member->student_id]['status'] === 'pending'
                    && ! isset($state['receipts'][$fee->id.':'.$leader->student_id]);
            });
    }

    public function test_a_sent_back_receipt_comes_back_with_the_reason_and_its_or_number(): void
    {
        [, $student] = $this->teamInSection('Alpha');
        $fee = $this->fee();
        $this->upload($student, $fee, 'OR-0099');
        FinancialRecord::query()->update(['f_record_status' => 'rejected', 'rejection_reason' => 'The OR number does not match the photo.']);

        $this->actingAs($student)->get(route('student.team', ['tab' => 'financial']))->assertOk()
            ->assertViewHas('financialState', function ($state) use ($fee, $student) {
                $receipt = $state['receipts'][$fee->id.':'.$student->student_id];

                return $receipt['status'] === 'sent_back'
                    && $receipt['reason'] === 'The OR number does not match the photo.'
                    && $receipt['or'] === 'OR-0099';
            });

        $this->upload($student, $fee, 'OR-0100')->assertSessionHasNoErrors();
        $this->assertSame('pending', FinancialRecord::where('student_id', $student->student_id)->latest('id')->value('f_record_status'));
    }
}
