<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'email',
    'f_name',
    'l_name',
    'p_picture',
])]
class Student extends Authenticatable
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, Notifiable;

    protected $primaryKey = 'student_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected function casts(): array
    {
        return [];
    }

    public function hasCompletedProfile(): bool
    {
        return filled($this->f_name)
            && filled($this->l_name)
            && filled($this->p_picture)
            && $this->hasValidAvatarPreset();
    }

    public function hasValidAvatarPreset(): bool
    {
        return filled($this->p_picture) && array_key_exists($this->p_picture, config('avatars.presets', []));
    }

    public function avatarUrl(): string
    {
        if ($this->hasValidAvatarPreset()) {
            $path = public_path('images/avatars/'.$this->p_picture.'.svg');

            if (file_exists($path)) {
                return asset('images/avatars/'.$this->p_picture.'.svg');
            }
        }

        return '';
    }

    public function avatarInitials(): string
    {
        $first = Str::substr($this->f_name ?? '', 0, 1);
        $last = Str::substr($this->l_name ?? '', 0, 1);

        if ($first !== '' || $last !== '') {
            return strtoupper($first.$last);
        }

        return Str::substr($this->email ?? 'S', 0, 2);
    }

    public function displayFirstName(): string
    {
        return $this->f_name ?? Str::before($this->email ?? 'Student', '@');
    }

    public function getProgramAttribute(): ?string
    {
        $record = $this->studentSchoolYear()->first();

        return $record?->program;
    }

    /**
     * @return array<int, string>
     */
    public static function allowedAvatarKeys(): array
    {
        return array_keys(config('avatars.presets', []));
    }

    public static function mapCourseToProgram(string $course): ?string
    {
        return match ($course) {
            'Bachelor of Science in Computer Science' => 'BSCS',
            'Bachelor of Science in Information Technology' => 'BSIT',
            'Bachelor of Science in Information Systems' => 'BSIS',
            'Bachelor of Library and Information Science' => 'BLIS',
            default => null,
        };
    }

    /**
     * @return HasMany<StudentSchoolYear>
     */
    public function studentSchoolYear()
    {
        return $this->hasMany(StudentSchoolYear::class, 'student_id', 'student_id');
    }

    public function team()
    {
        return $this->hasOneThrough(
            Team::class,
            StudentSchoolYear::class,
            'student_id',
            'team_id',
            'student_id',
            'team_id'
        );
    }
}
