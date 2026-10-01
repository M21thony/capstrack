<?php

namespace Tests\Feature;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\PdfRemark;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Checking a revision: the reviewer says, for each remark they left on the
 * earlier version, whether the team fixed it. That needs a remark to stay
 * the same remark from one save to the next, and the answer to be kept on it.
 */
class RevisionCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A team's first submission and its revision of the same chapter, each
     * reviewed by the same consultant.
     *
     * @return array{expert: CapstoneExpert, first: CapstoneDocument, current: CapstoneDocument, earlier: CapstoneDocumentReview, review: CapstoneDocumentReview}
     */
    private function twoRounds(): array
    {
        Storage::fake('local');
        $team = Team::create(['team_name' => 'Alpha', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        $doc = fn (int $version) => tap(CapstoneDocument::create([
            'student_id' => $student->student_id, 'file_name' => 'Chapter 3', 'chapter' => 'Chapter 3',
            'file_path' => "docs/ch3-v{$version}.pdf", 'file_version' => $version, 'document_status' => 'Pending',
        ]), fn ($d) => Storage::disk('local')->put($d->file_path, '%PDF-1.4'));

        $first = $doc(1);
        $current = $doc(2);
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $earlier = CapstoneDocumentReview::create(['file_id' => $first->file_id, 'expert_id' => $expert->expert_id, 'role' => 'consultant', 'review_status' => 'Reviewed', 'version' => '1']);
        $review = CapstoneDocumentReview::create(['file_id' => $current->file_id, 'expert_id' => $expert->expert_id, 'role' => 'consultant', 'review_status' => 'To Review', 'version' => '2']);

        return compact('expert', 'first', 'current', 'earlier', 'review');
    }

    private function remarkOn(CapstoneDocumentReview $review, string $comment = 'Foreign key is not shown.'): PdfRemark
    {
        return $review->pdfRemarks()->create([
            'page_number' => 2, 'top_position' => 0.2, 'left_position' => 0.1, 'highlight_width' => 0.5, 'highlight_height' => 0.3,
            'color' => 'pink', 'comment' => $comment, 'mark_type' => 'box', 'figure_label' => 'Figure 3.2',
        ]);
    }

    private function box(array $extra = []): array
    {
        return $extra + [
            'type' => 'box', 'page_number' => 1, 'text' => 'Label this.', 'color' => 'yellow',
            'highlightRects' => [['top_position' => 0.2, 'left_position' => 0.1, 'highlight_width' => 0.3, 'highlight_height' => 0.1]],
        ];
    }

    public function test_a_remark_keeps_its_id_from_one_save_to_the_next(): void
    {
        ['expert' => $expert, 'review' => $review] = $this->twoRounds();
        $this->actingAs($expert, 'expert');
        $url = route('reviews.remarks.store', $review->review_id);

        // A new remark is told the id it was given.
        $first = $this->postJson($url, ['comments' => [$this->box(['id' => 'new-1'])]])->assertOk();
        $id = PdfRemark::where('review_id', $review->review_id)->sole()->id;
        $first->assertJsonPath('ids.new-1', $id);

        // Saved again under that id, it is the same row, changed.
        $this->postJson($url, ['comments' => [$this->box(['id' => $id, 'text' => 'Label this flow.'])]])->assertOk();
        $remark = PdfRemark::where('review_id', $review->review_id)->sole();
        $this->assertSame($id, $remark->id);
        $this->assertSame('Label this flow.', $remark->comment);

        // Left out, it is gone.
        $this->postJson($url, ['comments' => []])->assertOk();
        $this->assertSame(0, PdfRemark::where('review_id', $review->review_id)->count());
    }

    public function test_saving_never_touches_another_reviews_remark(): void
    {
        ['expert' => $expert, 'review' => $review, 'earlier' => $earlier] = $this->twoRounds();
        $theirs = $this->remarkOn($earlier);
        $this->actingAs($expert, 'expert');

        // An id that belongs to another review is a new remark here, not an edit of that one.
        $this->postJson(route('reviews.remarks.store', $review->review_id), ['comments' => [$this->box(['id' => $theirs->id, 'text' => 'Overwritten?'])]])->assertOk();

        $this->assertSame('Foreign key is not shown.', $theirs->fresh()->comment);
        $this->assertSame((int) $earlier->review_id, (int) $theirs->fresh()->review_id);
        $this->assertSame('Overwritten?', PdfRemark::where('review_id', $review->review_id)->sole()->comment);
    }

    public function test_the_reviewer_records_whether_an_earlier_remark_was_fixed(): void
    {
        ['expert' => $expert, 'review' => $review, 'earlier' => $earlier, 'first' => $first] = $this->twoRounds();
        $remark = $this->remarkOn($earlier);
        $this->actingAs($expert, 'expert');
        $url = route('reviews.versions.remarks.revision', [$review->review_id, $first->file_id, $remark->id]);

        $this->postJson($url, ['status' => 'fixed'])->assertOk()->assertJsonPath('revision_status', 'fixed');
        $this->assertSame('fixed', $remark->fresh()->revision_status);
        $this->assertNotNull($remark->fresh()->revision_checked_at);

        // The earlier version's remarks carry the answer, so it is there when the page opens again.
        $this->getJson(route('reviews.versions.remarks', [$review->review_id, $first->file_id]))
            ->assertOk()
            ->assertJsonPath('0.revision_status', 'fixed');

        $this->postJson($url, ['status' => 'not_fixed'])->assertOk();
        $this->assertSame('not_fixed', $remark->fresh()->revision_status);

        // Clearing the answer clears when it was given.
        $this->postJson($url, ['status' => null])->assertOk();
        $this->assertNull($remark->fresh()->revision_status);
        $this->assertNull($remark->fresh()->revision_checked_at);
    }

    public function test_a_made_up_answer_is_refused(): void
    {
        ['expert' => $expert, 'review' => $review, 'earlier' => $earlier, 'first' => $first] = $this->twoRounds();
        $remark = $this->remarkOn($earlier);

        $this->actingAs($expert, 'expert')
            ->postJson(route('reviews.versions.remarks.revision', [$review->review_id, $first->file_id, $remark->id]), ['status' => 'maybe'])
            ->assertStatus(422);
        $this->assertNull($remark->fresh()->revision_status);
    }

    public function test_only_the_reviewers_own_earlier_remarks_can_be_answered(): void
    {
        ['expert' => $expert, 'review' => $review, 'first' => $first, 'current' => $current] = $this->twoRounds();

        // Another reviewer's remark on the same earlier version.
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $theirReview = CapstoneDocumentReview::create(['file_id' => $first->file_id, 'expert_id' => $adviser->expert_id, 'role' => 'adviser', 'review_status' => 'Reviewed', 'version' => '1']);
        $theirs = $this->remarkOn($theirReview);

        $this->actingAs($expert, 'expert')
            ->postJson(route('reviews.versions.remarks.revision', [$review->review_id, $first->file_id, $theirs->id]), ['status' => 'fixed'])
            ->assertNotFound();

        // A remark on this version is not an earlier one.
        $here = $this->remarkOn($review);
        $this->actingAs($expert, 'expert')
            ->postJson(route('reviews.versions.remarks.revision', [$review->review_id, $current->file_id, $here->id]), ['status' => 'fixed'])
            ->assertNotFound();

        $this->assertNull($theirs->fresh()->revision_status);
        $this->assertNull($here->fresh()->revision_status);
    }

    public function test_another_expert_cannot_answer_for_this_review(): void
    {
        ['review' => $review, 'earlier' => $earlier, 'first' => $first] = $this->twoRounds();
        $remark = $this->remarkOn($earlier);
        $stranger = CapstoneExpert::factory()->create(['approved_status' => true]);

        $this->actingAs($stranger, 'expert')
            ->postJson(route('reviews.versions.remarks.revision', [$review->review_id, $first->file_id, $remark->id]), ['status' => 'fixed'])
            ->assertForbidden();
        $this->assertNull($remark->fresh()->revision_status);
    }

    public function test_saving_the_earlier_review_again_keeps_the_answer(): void
    {
        ['expert' => $expert, 'earlier' => $earlier] = $this->twoRounds();
        $remark = $this->remarkOn($earlier);
        $remark->update(['revision_status' => 'fixed', 'revision_checked_at' => now()]);

        $this->actingAs($expert, 'expert')->postJson(route('reviews.remarks.store', $earlier->review_id), ['comments' => [[
            'id' => $remark->id, 'type' => 'box', 'page_number' => 2, 'text' => 'Foreign key is not shown.', 'color' => 'pink', 'figure_label' => 'Figure 3.2',
            'highlightRects' => [['top_position' => 0.2, 'left_position' => 0.1, 'highlight_width' => 0.5, 'highlight_height' => 0.3]],
        ]]])->assertOk();

        $this->assertSame($remark->id, PdfRemark::where('review_id', $earlier->review_id)->sole()->id);
        $this->assertSame('fixed', $remark->fresh()->revision_status);
    }

    public function test_a_remark_carried_over_as_not_fixed_remembers_where_it_came_from(): void
    {
        ['expert' => $expert, 'review' => $review, 'earlier' => $earlier] = $this->twoRounds();
        $remark = $this->remarkOn($earlier);

        $this->actingAs($expert, 'expert')->postJson(route('reviews.remarks.store', $review->review_id), ['comments' => [
            $this->box(['id' => 'new-9', 'text' => 'Still not fixed (from First submission): Foreign key is not shown.', 'figure_label' => 'Figure 3.2', 'carried_from_id' => $remark->id]),
        ]])->assertOk();

        $carried = PdfRemark::where('review_id', $review->review_id)->sole();
        $this->assertSame($remark->id, (int) $carried->carried_from_id);
        $this->assertSame('Figure 3.2: Still not fixed (from First submission): Foreign key is not shown.', $carried->printedComment());
    }
}
