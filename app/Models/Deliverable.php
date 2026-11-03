<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One thing inside a milestone that a team hands in or passes.
 *
 * The kind decides what closes it. A signed paper - a form, or a CLF the
 * adviser or consultant signed by hand - closes when the CaPSA checks the
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

    public const KINDS = [self::FORM, self::DOCUMENT, self::APPROVAL, self::DEFENSE, self::CUSTOM];

    /** The kinds the CaPSA's check closes. */
    public const CHECKED_BY_CAPSA = [self::FORM, self::APPROVAL, self::CUSTOM];

    /**
     * The Recommended Pre-Proposal Title Form. The CaPSA recommends a title in
     * CapsTrack itself, so the line closes on that and nothing is uploaded for it.
     */
    public const TITLE_RECOMMENDATION = 'pre_proposal_title_form';

    /**
     * The Archive Entry: title, abstract and proponents, typed on the archive
     * page. The line closes when the CaPSA publishes it; nothing is uploaded.
     */
    public const ARCHIVE_ENTRY = 'archive_entry';

    protected $fillable = [
        'checklist_milestone_id',
        'template_key',
        'title',
        'kind',
        'role',
        'chapter',
        'defense_type',
        'due_date',
        'position',
        'archived_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'position' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function milestone()
    {
        return $this->belongsTo(ChecklistMilestone::class, 'checklist_milestone_id');
    }

    public function checks()
    {
        return $this->hasMany(DeliverableCheck::class);
    }

    /** Whether the CaPSA's title recommendation closes this line, not an upload. */
    public function closesOnTitleRecommendation(): bool
    {
        return $this->template_key === self::TITLE_RECOMMENDATION;
    }

    /** Whether publishing the team's archive entry closes this line, not an upload. */
    public function closesOnArchiveEntry(): bool
    {
        return $this->template_key === self::ARCHIVE_ENTRY;
    }

    /** What teams see: not archived, and not inside an archived milestone. */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at')
            ->whereHas('milestone', fn (Builder $milestone) => $milestone->whereNull('archived_at'));
    }

    /**
     * How a line is recognised across copies: by its department key, or by
     * its title for a line a CaPSA wrote themselves.
     */
    public static function keyFor(?string $templateKey, string $title): string
    {
        return $templateKey ?: 'own:'.mb_strtolower(trim($title));
    }
}
