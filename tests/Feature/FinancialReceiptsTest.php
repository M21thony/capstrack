<?php

namespace Tests\Feature;

use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Each member uploads their own receipt - no receipt number - and may swap a
 * photo that is still waiting or was sent back. A verified receipt is final.
 * The CaPSA sees the fees and receipts of her own sections.
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
        $this->assertSame('pending', $record->f_record_status);
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
            ->get(route('student.financial'))
            ->assertOk()
            ->assertViewHas('state', fn ($state) => collect($state['fees'])->pluck('stage')->all() === ['Pre Oral Defense']);
    }

    public function test_the_capsa_sees_fees_and_receipts_for_her_sections_only(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [$mine, $member] = $this->teamInSection('Mine');
        [, $outsider] = $this->teamInSection('Theirs', yearSection: '3rd · B');
        $fee = $this->fee();
        $this->upload($member, $fee);
        $this->upload($outsider, $fee);

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.financial'))
            ->assertOk()
            ->assertViewHas('state', function ($state) use ($mine, $fee, $member) {
                return collect($state['teams'])->pluck('name')->all() === [$mine->team_name]
                    && array_keys($state['receipts']) === [$fee->id.':'.$member->student_id]
                    && $state['receipts'][$fee->id.':'.$member->student_id]['status'] === 'pending';
            });
    }
}
