<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One deliverable inside a milestone.
 *
 * The kind decides what closes it. Only FORM and CUSTOM are ticked by a
 * person and write completed_at here; the other three are answered by the
 * document, review or defense they point at, so nothing has to be kept in
 * step. See App\Support\MilestoneProgress, which resolves them in bulk.
 */
class MilestoneItem extends Model
{
    /** A signed paper the CaPSA sights. Ticked by hand. */
    public const FORM = 'form';

    /** A draft the team uploads. Closed by the upload existing. */
    public const DOCUMENT = 'document';

    /** A CLF. Closed by the adviser's or consultant's verdict. */
    public const APPROVAL = 'approval';

    /** Closed by the panel's recorded outcome. */
    public const DEFENSE = 'defense';

    /** Anything a CaPSA adds themselves. Ticked by hand, like a form. */
    public const CUSTOM = 'custom';

    public const KINDS = [self::FORM, self::DOCUMENT, self::APPROVAL, self::DEFENSE, self::CUSTOM];

    /** The kinds a person closes, rather than the system working it out. */
    public const TICKED_BY_HAND = [self::FORM, self::CUSTOM];

    protected $fillable = [
        'milestone_id',
        'template_key',
        'title',
        'short_label',
        'phase',
        'kind',
        'position',
        'due_date',
        'completed_at',
        'chapter',
        'role',
        'defense_type',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'position' => 'integer',
    ];

    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }

    /** Its own deadline if it has one, otherwise the milestone's. */
    public function dueOn(): ?\Illuminate\Support\Carbon
    {
        return $this->due_date ?? $this->milestone?->due_date;
    }

    /** What the grid puts in a column header, under its phase band. */
    public function columnLabel(): string
    {
        return $this->short_label ?: $this->title;
    }

    public function isTickedByHand(): bool
    {
        return in_array($this->kind, self::TICKED_BY_HAND, true);
    }
}
