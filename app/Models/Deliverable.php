<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One thing inside a milestone that a team hands in or passes.
 *
 * The kind decides what closes it. A signed paper - a form, or a CLF the
 * adviser or consultant signed by hand - closes when the CaPSA/TSA checks the
 * uploaded scan, because the system has no e-signing. An upload closes on
 * being uploaded, a defense on the panel's recorded pass.
 */
class Deliverable extends Model
{
    use SoftDeletes;

    public const FORM = 'form';

    public const DOCUMENT = 'document';

    /** A signed CLF; role says whether the adviser or the consultant signs it. */
    public const APPROVAL = 'approval';

    public const DEFENSE = 'defense';

    public const CUSTOM = 'custom';

    /** A Bluebook requirement: a file or a Drive video link the dean's office checks, not the CaPSA/TSA. */
    public const BLUEBOOK = 'bluebook';

    public const KINDS = [self::FORM, self::DOCUMENT, self::APPROVAL, self::DEFENSE, self::CUSTOM, self::BLUEBOOK];

    /** The signed papers and tasks: sent as a scan, opened by the CaPSA/TSA as the file itself. */
    public const CHECKED_BY_CAPSA = [self::FORM, self::APPROVAL, self::CUSTOM];

    /** The kinds only the CaPSA/TSA's check closes: everything but a defense, which closes on its result. */
    public const CLOSED_BY_CAPSA = [self::FORM, self::DOCUMENT, self::APPROVAL, self::CUSTOM];

    /**
     * The Recommended Pre-Proposal Title Form. The CaPSA/TSA recommends a title in
     * CapsTrack itself, so the line closes on that and nothing is uploaded for it.
     */
    public const TITLE_RECOMMENDATION = 'pre_proposal_title_form';

    /**
     * The Archive Entry: title, abstract and proponents, typed on the archive
     * page. The line closes when the CaPSA/TSA publishes it; nothing is uploaded.
     */
    public const ARCHIVE_ENTRY = 'archive_entry';

    /**
     * The Bluebook Clearance itself: nothing is uploaded, it closes when the
     * dean clears the team.
     */
    public const BLUEBOOK_CLEARANCE = 'bluebook_clearance';

    /**
     * The file type a CaPSA/TSA can pick for a line: what the choice is called,
     * and the file endings it takes. '*' is any file; a link takes no file.
     */
    public const FILE_TYPES = [
        'pdf' => ['PDF', ['pdf']],
        'jpg' => ['JPG or PNG', ['jpg', 'jpeg', 'png']],
        'link' => ['Link', []],
        'txt' => ['TXT', ['txt']],
        'zip' => ['ZIP', ['zip']],
        'docx' => ['DOCX', ['docx']],
        'any' => ['Any file', ['*']],
    ];

    protected $fillable = [
        'file_type',
        'checklist_milestone_id',
        'template_key',
        'title',
        'instructions',
        'kind',
        'role',
        'chapter',
        'defense_type',
        'due_date',
        'position',
        'archived_at',
        'recipients',
        'closed_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'position' => 'integer',
        'archived_at' => 'datetime',
        'recipients' => 'array',
        'closed_at' => 'datetime',
    ];

    /**
     * The roles the CaPSA/TSA chose for this line's uploads, every one of whom
     * must approve; null keeps the usual people (SubmissionRecipients::rulesFor).
     * A signed CLF, a defense and the special lines keep their own rules.
     *
     * @return array<int, string>|null
     */
    public function sendsTo(): ?array
    {
        $special = in_array($this->template_key, [self::TITLE_RECOMMENDATION, self::ARCHIVE_ENTRY, self::BLUEBOOK_CLEARANCE], true);

        return $this->recipients && ! $special && in_array($this->kind, [self::FORM, self::DOCUMENT, self::CUSTOM], true)
            ? array_values($this->recipients)
            : null;
    }

    /**
     * The file type picked for this line, or null for the usual files. A link
     * is only taken by a Dean's check, the one place a link can be handed in.
     */
    public function fileType(): ?string
    {
        $type = $this->file_type;

        return isset(self::FILE_TYPES[$type]) && ($type !== 'link' || $this->kind === self::BLUEBOOK) ? $type : null;
    }

    /**
     * The file endings this line takes: the picked type's, or the usual ones.
     *
     * @param  array<int, string>  $usual
     * @return array<int, string>
     */
    public function accepts(array $usual): array
    {
        return ($type = $this->fileType()) ? self::FILE_TYPES[$type][1] : $usual;
    }

    /** The picked type in words ("ZIP", "Any file"), or null for the usual files. */
    public function fileWords(): ?string
    {
        return ($type = $this->fileType()) ? self::FILE_TYPES[$type][0] : null;
    }

    public function milestone()
    {
        return $this->belongsTo(ChecklistMilestone::class, 'checklist_milestone_id');
    }

    public function checks()
    {
        return $this->hasMany(DeliverableCheck::class);
    }

    /** Whether the CaPSA/TSA's title recommendation closes this line, not an upload. */
    public function closesOnTitleRecommendation(): bool
    {
        return $this->template_key === self::TITLE_RECOMMENDATION;
    }

    /** Whether publishing the team's archive entry closes this line, not an upload. */
    public function closesOnArchiveEntry(): bool
    {
        return $this->template_key === self::ARCHIVE_ENTRY;
    }

    public function closesOnBluebookClearance(): bool
    {
        return $this->template_key === self::BLUEBOOK_CLEARANCE;
    }

    /** What teams see: not archived, and not inside an archived milestone. */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at')
            ->whereHas('milestone', fn (Builder $milestone) => $milestone->whereNull('archived_at'));
    }

    /**
     * How a line is recognised across copies: by its department key, or by
     * its title for a line a CaPSA/TSA wrote themselves.
     */
    public static function keyFor(?string $templateKey, string $title): string
    {
        return $templateKey ?: 'own:'.mb_strtolower(trim($title));
    }
}
