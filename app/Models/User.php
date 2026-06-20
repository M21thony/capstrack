<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'email',
    'password',
    'login_success',
    'last_login_at',
    'first_name',
    'middle_name',
    'last_name',
    'course',
    'profile_completed_at',
    'team_name',
    'year_section',
    'role',
    'avatar',
    'student_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profile_completed_at' => 'datetime',
        ];
    }

    public function hasCompletedProfile(): bool
    {
        return $this->profile_completed_at !== null
            && filled($this->first_name)
            && filled($this->last_name)
            && filled($this->course)
            && filled($this->year_section)
            && filled($this->student_id)
            && $this->hasValidAvatarPreset();
    }

    public function hasValidAvatarPreset(): bool
    {
        return filled($this->avatar) && array_key_exists($this->avatar, config('avatars.presets', []));
    }

    public function avatarUrl(): string
    {
        if ($this->hasValidAvatarPreset()) {
            $path = public_path('images/avatars/'.$this->avatar.'.svg');

            if (file_exists($path)) {
                return asset('images/avatars/'.$this->avatar.'.svg');
            }
        }

        return '';
    }

    public function avatarInitials(): string
    {
        $first = Str::substr($this->first_name ?? '', 0, 1);
        $last = Str::substr($this->last_name ?? '', 0, 1);

        if ($first !== '' || $last !== '') {
            return strtoupper($first.$last);
        }

        return Str::substr($this->name ?? 'U', 0, 2);
    }

    public function displayFirstName(): string
    {
        return $this->first_name ?? Str::before($this->name ?? 'Student', ' ');
    }

    public function displayYearSection(): string
    {
        return $this->year_section ?? 'Complete your profile';
    }

    /**
     * @return array<int, string>
     */
    public static function allowedAvatarKeys(): array
    {
        return array_keys(config('avatars.presets', []));
    }
}
