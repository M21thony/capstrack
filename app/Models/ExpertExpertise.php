<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'expert_id',
    'expertise_field',
])]
class ExpertExpertise extends Model
{
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
