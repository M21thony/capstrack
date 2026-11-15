<?php

namespace App\Models;

use App\Support\PeerRatingRubric;
use Database\Factories\TeamRatingRubricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Team Rating rubric a CaPSA/TSA wrote: its criteria, each with the five
 * descriptors a teammate reads. The points per level are the same on every
 * rubric (PeerRatingRubric::LEVELS).
 */
class TeamRatingRubric extends Model
{
    /** @use HasFactory<TeamRatingRubricFactory> */
    use HasFactory;

    protected $fillable = ['name', 'criteria', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** The rubric new ratings are made with, or null for the department's. */
    public static function inUse(): ?self
    {
        return self::where('is_active', true)->latest('id')->first();
    }

    /** Makes this the rubric new ratings use; only one is in use at a time. */
    public function putInUse(): void
    {
        self::whereKeyNot($this->getKey())->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    public function asRubric(): PeerRatingRubric
    {
        return PeerRatingRubric::fromArray(['name' => $this->name, 'criteria' => $this->criteria]);
    }
}
