<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ExpertAwayDateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Days a panelist is away - travel, a seminar, sick. They keep their seat on
 * every panel they sit on; nobody is scheduled in front of them on those days.
 */
class ExpertAwayDate extends Model
{
    /** @use HasFactory<ExpertAwayDateFactory> */
    use HasFactory;

    public const REASONS = ['Travel', 'Seminar', 'Sick', 'Other'];

    protected $fillable = [
        'expert_id',
        'starts_on',
        'ends_on',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(CapstoneExpert::class, 'expert_id', 'expert_id');
    }

    public function covers(CarbonInterface $day): bool
    {
        return $day->toDateString() >= $this->starts_on->toDateString() && $day->toDateString() <= $this->ends_on->toDateString();
    }

    /**
     * Away dates of these panelists that touch the given days.
     *
     * @param  array<int, int>  $expertIds
     * @return Collection<int, ExpertAwayDate>
     */
    public static function forExperts(array $expertIds, CarbonInterface $from, CarbonInterface $until): Collection
    {
        if ($expertIds === []) {
            return collect();
        }

        return self::whereIn('expert_id', $expertIds)
            ->whereDate('starts_on', '<=', $until->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString())
            ->get();
    }
}
