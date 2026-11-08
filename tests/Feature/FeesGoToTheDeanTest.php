<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpensePost;
use App\Models\FinancialRecord;
use App\Models\Student;
use App\Support\FeeBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The CaPSA verifies each receipt, then sends the verified ones to the dean,
 * who only reads the totals. A sent receipt cannot be changed.
 */
class FeesGoToTheDeanTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private ExpensePost $fee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fee = ExpensePost::create(['title' => 'Title Defense Fee', 'course' => 'BSIT', 'year_level' => '3rd', 'defense_type' => 'Title Defense', 'amount' => 500, 'is_active' => true]);
    }

    private function receipt(Student $student, string $status = 'verified', array $extra = []): FinancialRecord
    {
        return FinancialRecord::create($extra + [
            'student_id' => $student->student_id,
            'expense_post_id' => $this->fee->id,
            'f_record_status' => $status,
            'receipt_image' => 'receipts/test.jpg',
            'payment_for' => $this->fee->title,
            'amount' => 500,
            'uploaded_at' => now(),
        ]);
    }

    private function send(CapstoneExpert $capsa)
    {
        return $this->actingAs($capsa, 'expert')->postJson(route('api.admin.financial.send-to-dean'));
    }

    public function test_only_verified_receipts_of_her_own_sections_go(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $mine] = $this->teamInSection('Alpha');
        [, $waiting] = $this->teamInSection('Bravo');
        [, $other] = $this->teamInSection('Charlie', yearSection: '3rd · B');

        $sent = $this->receipt($mine);
        $held = $this->receipt($waiting, 'pending');
        $notMine = $this->receipt($other);

        $this->send($capsa)->assertOk()->assertJsonPath('message', 'Sent 1 receipt to the dean.');

        $this->assertNotNull($sent->fresh()->sent_to_dean_at);
        $this->assertSame($capsa->name, $sent->fresh()->sent_to_dean_by);
        $this->assertNull($held->fresh()->sent_to_dean_at);
        $this->assertNull($notMine->fresh()->sent_to_dean_at);
    }

    public function test_nothing_left_to_send_is_refused_and_the_date_stays(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $student] = $this->teamInSection('Alpha');
        $receipt = $this->receipt($student, extra: ['sent_to_dean_at' => now()->subDay()]);

        $this->send($capsa)->assertStatus(422);

        $this->assertTrue($receipt->fresh()->sent_to_dean_at->lt(now()->subHours(23)));
    }

    public function test_a_sent_receipt_cannot_be_sent_back(): void
    {
        $capsa = $this->capsaFor(['BSIT-3A']);
        [, $student] = $this->teamInSection('Alpha');
        $receipt = $this->receipt($student, extra: ['sent_to_dean_at' => now()]);

        $this->actingAs($capsa, 'expert')
            ->patch(route('capsa.financial.receipt.update', $receipt), ['status' => 'rejected', 'rejection_reason' => 'Blurry'])
            ->assertSessionHasErrors('status');

        $this->assertSame('verified', $receipt->fresh()->f_record_status);
    }

    public function test_the_dean_sees_each_section_sent_or_not(): void
    {
        [, $sent] = $this->teamInSection('Alpha');
        [, $held] = $this->teamInSection('Bravo', yearSection: '3rd · B');
        $this->receipt($sent, extra: ['sent_to_dean_at' => now(), 'sent_to_dean_by' => 'Ms. CaPSA']);
        $this->receipt($held);

        $rows = collect(app(FeeBoard::class)->forDean())->keyBy('section');

        $this->assertTrue($rows['BSIT-3A']['is_sent']);
        $this->assertSame(500.0, $rows['BSIT-3A']['amount']);
        $this->assertSame(['Title Defense' => 500.0], $rows['BSIT-3A']['stages']);
        $this->assertSame('Ms. CaPSA', $rows['BSIT-3A']['sent_by']);

        $this->assertFalse($rows['BSIT-3B']['is_sent']);
        $this->assertSame(0.0, $rows['BSIT-3B']['amount']);
        $this->assertSame(1, $rows['BSIT-3B']['held']);
    }

    /** The dean reads; only a CaPSA sends. */
    public function test_a_plain_admin_cannot_send(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);

        $this->send($admin)->assertForbidden();
    }

    /** The dean's archive is view only: the page offers no writes and the API refuses them. */
    public function test_a_plain_admin_cannot_change_the_archive(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);

        $this->actingAs($admin, 'expert')->get(route('admin.archive'))->assertOk()->assertDontSee('Add Capstone');
        $this->actingAs($admin, 'expert')->postJson(route('api.admin.archive.store'))->assertForbidden();
    }

    /** Every tile, button and warning on the dean's dashboard opens a page the dean may see. */
    public function test_every_link_on_the_admin_dashboard_opens(): void
    {
        $admin = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]);

        $html = $this->actingAs($admin, 'expert')->get(route('admin.dashboard'))->assertOk()->getContent();
        preg_match('~<main class="db db-admin">.*?</main>~s', $html, $main);
        preg_match_all('~href="(http[^"]+)"~', $main[0], $links);

        $this->assertNotEmpty($links[1]);
        foreach (array_unique($links[1]) as $link) {
            $this->actingAs($admin, 'expert')->get($link)->assertOk();
        }
    }
}
