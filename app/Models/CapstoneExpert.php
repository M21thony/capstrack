<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'email',
    'first_name',
    'last_name',
    'middle_name',
    'suffix',
    'credentials',
    'profile_picture',
    'is_admin',
    'approved_status',
    'max_teams',
    'department',
    'sections',
    'school_year',
    'rejection_reason',
    'reject_attempts',
    'reject_cooldown_until',
    'google_token',
    'google_token_expires_at',
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
        'sections' => 'array',
        'created_at' => 'datetime',
        'reject_cooldown_until' => 'datetime',
        'last_seen_at' => 'datetime',
        'first_login_at' => 'datetime',
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

    public function getIsOnlineAttribute(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(5));
    }

    public function hasValidAvatarPreset(): bool
    {
        return filled($this->profile_picture) && array_key_exists($this->profile_picture, config('avatars.presets', []));
    }

    public function avatarUrl(): string
    {
        if (filled($this->profile_picture)) {
            if (filter_var($this->profile_picture, FILTER_VALIDATE_URL)) {
                return $this->profile_picture;
            }

            if ($this->hasValidAvatarPreset()) {
                $path = public_path('images/avatars/'.$this->profile_picture.'.svg');

                if (file_exists($path)) {
                    return asset('images/avatars/'.$this->profile_picture.'.svg');
                }
            }
        }

        return asset('images/default-avatar.jpg');
    }

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
        $parts = array_filter([
            $this->first_name ?? '',
            $this->middle_name ? Str::substr($this->middle_name, 0, 1).'.' : '',
            $this->last_name ?? '',
            $this->suffix ?? '',
        ]);

        $name = implode(' ', $parts);

        if ($this->credentials) {
            $name .= ', '.$this->credentials;
        }

        return $name;
    }

    public function teamAssignments()
    {
        return $this->hasMany(TeamExpertAssignment::class, 'expert_id', 'expert_id');
    }

    public function markFirstLogin(): bool
    {
        if ($this->first_login_at === null) {
            $this->first_login_at = now();
            $this->save();

            session()->flash('show_welcome', true);

            return true;
        }

        return false;
    }

    public function getResolvedSchoolYearAttribute(): ?string
    {
        if (! $this->school_year) {
            return null;
        }

        $parts = explode('-', $this->school_year);
        if (count($parts) < 2) {
            return null;
        }

        $lastTwo = (int) $parts[0];
        $currentYear = (int) date('Y');
        $currentLastTwo = $currentYear % 100;

        if ($lastTwo === $currentLastTwo || $lastTwo === ($currentYear + 1) % 100) {
            return $currentYear.'-'.($currentYear + 1);
        }

        $prefix = '20';
        $startYear = (int) ($prefix.str_pad((string) $lastTwo, 2, '0', STR_PAD_LEFT));

        return $startYear.'-'.($startYear + 1);
    }
}
