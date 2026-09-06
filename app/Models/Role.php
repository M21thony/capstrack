<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'role_name',
    'description',
])]
class Role extends Model
{
    protected $primaryKey = 'role_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    public function experts()
    {
        return $this->belongsToMany(CapstoneExpert::class, 'expert_roles', 'role_id', 'expert_id');
    }
}
