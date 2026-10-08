<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'expert_id',
    'expertise_field',
    'side',
])]
class ExpertExpertise extends Model
{
    /**
     * The sides an expert keeps research areas for: each role a team engages
     * them as, and sitting on a defense panel. A null side is a list from
     * before sides existed, or one a CaPSA entered, and stands for all of them.
     */
    public const SIDES = ['adviser' => 'CaPA', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian', 'panel' => 'Panel'];

    /** Rows that speak for this side: its own, and the ones for every side. */
    public function scopeForSide($query, string $side)
    {
        return $query->where(fn ($q) => $q->where('side', $side)->orWhereNull('side'));
    }

    protected $primaryKey = 'expertise_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $table = 'expert_expertise';

    public function expert()
    {
        return $this->belongsTo(CapstoneExpert::class, 'expert_id', 'expert_id');
    }
}
