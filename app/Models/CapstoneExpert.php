<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'email',
    'first_name',
    'last_name',
    'middle_name',
    'credentials',
    'profile_picture',
    'is_admin',
    'approved_status',
    'max_teams',
    'department',
    'rejection_reason',
])]
class CapstoneExpert extends Authenticatable
{
    use Notifiable;

    protected $primaryKey = 'expert_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $casts = [
        'is_admin' => 'boolean',
        'approved_status' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'expert_roles', 'expert_id', 'role_id');
    }

    public function expertise()
    {
        return $this->hasMany(ExpertExpertise::class, 'expert_id', 'expert_id');
    }

    public function getStatusAttribute(): string
    {
        return $this->approved_status ? 'approved' : 'pending';
    }

    public function getSpecializationAttribute(): string
    {
        $roles = $this->roles->pluck('role_name')->toArray();

        return implode(', ', array_map('ucfirst', $roles));
    }

    public function getMaxTeamsAttribute($value): int
    {
        return $value ?? 4;
    }

    public function getExpertiseAttribute(): array
    {
        if ($this->relationLoaded('expertise')) {
            return $this->getRelationValue('expertise')->pluck('expertise_field')->toArray();
        }

        return [];
    }
}
