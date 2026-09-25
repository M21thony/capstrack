<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A student can mistype the OR number they read off their receipt. The
 * CaPSA, looking at the photo, puts it right as she verifies - never on its
 * own, and only in the format a student would have to type it in.
 */
class CapsaCorrectsOrNumberTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true]));
    }

    /** Only the expert guard, as EnsureExpertIsAuthenticated leaves it in production. */
    private function asCapsa(): static
    {
        $this->app['auth']->guard('expert')->setUser($this->capsa);

        return $this;
    }

    private function receipt(string $orNumber = 'OR 00132'): FinancialRecord
    {
        $post = ExpensePost::create(['admin_id' => $this->capsa->expert_id, 'title' => 'Title Defense Fee', 'course' => 'BSIT', 'amount' => 150]);

        return FinancialRecord::create([
            'student_id' => Student::factory()->withCompleteProfile()->create()->student_id,
            'expense_post_id' => $post->id,
            'receipt_image' => 'receipts/or.jpg',
            'receipt_number' => $orNumber,
            'f_record_status' => 'pending',
            'payment_for' => $post->title,
            'amount' => 150,
            'uploaded_at' => now(),
        ]);
    }

    private function verify(FinancialRecord $record, array $extra)
    {
        return $this->asCapsa()->patchJson(route('api.admin.financial.update', $record->id), ['status' => 'approved'] + $extra);
    }

    public function test_the_capsa_corrects_a_mistyped_or_number_as_she_verifies(): void
    {
        $record = $this->receipt('OR 00132');

        $this->verify($record, ['or_confirmed' => true, 'receipt_number' => '  or   00123 '])->assertOk();

        $record->refresh();
        $this->assertSame('verified', $record->f_record_status);
        $this->assertSame('OR 00123', $record->receipt_number, 'saved the way numbers are compared');
    }

    public function test_leaving_the_number_alone_keeps_what_the_student_typed(): void
    {
        $record = $this->receipt('OR 00132');

        $this->verify($record, ['or_confirmed' => true])->assertOk();

        $this->assertSame('OR 00132', $record->fresh()->receipt_number);
    }

    public function test_a_correction_is_not_saved_without_confirming_it_against_the_photo(): void
    {
        $record = $this->receipt('OR 00132');

        $this->verify($record, ['receipt_number' => 'OR 00123'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Check that the OR number on the photo is OR 00123, then tick the box to verify.']);

        $record->refresh();
        $this->assertSame('pending', $record->f_record_status);
        $this->assertSame('OR 00132', $record->receipt_number, 'nothing is saved on its own');
    }

    public function test_a_correction_must_look_like_an_or_number(): void
    {
        $record = $this->receipt('OR 00132');

        foreach (['', '   ', '<script>', str_repeat('9', 41)] as $bad) {
            $this->verify($record, ['or_confirmed' => true, 'receipt_number' => $bad])
                ->assertStatus(422)
                ->assertJsonValidationErrors('receipt_number');
        }

        $record->refresh();
        $this->assertSame('pending', $record->f_record_status);
        $this->assertSame('OR 00132', $record->receipt_number);
    }

    public function test_a_verified_receipts_number_cannot_be_changed(): void
    {
        $record = $this->receipt('OR 00132');
        $record->update(['f_record_status' => 'verified', 'verified_at' => now()]);

        $this->verify($record, ['or_confirmed' => true, 'receipt_number' => 'OR 00123'])->assertStatus(422);

        $this->assertSame('OR 00132', $record->fresh()->receipt_number);
    }

    public function test_the_receipt_screen_edits_the_number_and_asks_before_verifying(): void
    {
        $screen = file_get_contents(resource_path('js/financial/capsa.js'));

        $this->assertStringContainsString('id="c-or"', $screen, 'the OR number is an editable field while the receipt waits');
        $this->assertStringContainsString("confirmLabel: 'Yes, verify'", $screen);
        $this->assertStringContainsString("cancelLabel: 'No'", $screen);
        $this->assertStringContainsString('await verifyOne(selected)', $screen, 'the button and the V key both ask first');
        $this->assertSame(2, substr_count($screen, 'await verifyOne(selected)'));
    }
}
