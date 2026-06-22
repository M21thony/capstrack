<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

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
    use HasFactory, Notifiable;

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
        if ($this->approved_status) {
            return 'approved';
        }
        if (! is_null($this->rejection_reason) && $this->rejection_reason !== '') {
            return 'rejected';
        }

        return 'pending';
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

    // Removed getExpertiseAttribute accessor to allow direct relationship access

    public function avatarInitials(): string
    {
        $first = Str::substr($this->first_name ?? '', 0, 1);
        $last = Str::substr($this->last_name ?? '', 0, 1);

        if ($first !== '' || $last !== '') {
            return strtoupper($first.$last);
        }

        return Str::substr($this->email ?? 'E', 0, 2);
    }

    public function getNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }
}
