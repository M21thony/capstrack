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
    'student_number',
    'f_name',
    'm_name',
    'l_name',
    'suffix',
    'p_picture',
    'course',
    'year_section',
    'last_seen_at',
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
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function hasCompletedProfile(): bool
    {
        return filled($this->f_name)
            && filled($this->l_name)
            && filled($this->student_number);
    }

    public function hasValidAvatarPreset(): bool
    {
        return filled($this->p_picture) && array_key_exists($this->p_picture, config('avatars.presets', []));
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(5));
    }

    /**
     * Normally the Google account photo captured at login. Older accounts
     * may still carry a preset key from before avatars were tied to email
     * — keep serving those locally until their next login refreshes it.
     */
    public function avatarUrl(): string
    {
        if (filled($this->p_picture)) {
            if (filter_var($this->p_picture, FILTER_VALIDATE_URL)) {
                return $this->p_picture;
            }

            if ($this->hasValidAvatarPreset()) {
                $path = public_path('images/avatars/'.$this->p_picture.'.svg');

                if (file_exists($path)) {
                    return asset('images/avatars/'.$this->p_picture.'.svg');
                }
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

    /**
     * Program code => the full course name it is stored as.
     *
     * The course column holds whichever of the two a given record happened
     * to be created with - Google sign-up and the roster upload write the
     * full name, but some rows (and some hand-entered data) hold just the
     * code. Both are valid data, so both map.
     */
    /**
     * The only domain a student can sign in from.
     *
     * Sign-in has always enforced this; the student list upload now checks it
     * too, because an address outside it is one nobody will ever be able to
     * use - and a list is typed by hand, so "my.cpsc" for "my.cspc" is the
     * mistake it exists to catch.
     */
    public const EMAIL_DOMAIN = '@my.cspc.edu.ph';

    public const PROGRAM_COURSES = [
        'BSCS' => 'Bachelor of Science in Computer Science',
        'BSIT' => 'Bachelor of Science in Information Technology',
        'BSIS' => 'Bachelor of Science in Information Systems',
        'BLIS' => 'Bachelor of Library and Information Science',
    ];

    /**
     * Resolve a course to its program code, accepting either the full course
     * name ("Bachelor of Science in Information Systems") or the code itself
     * ("BSIS").
     *
     * This used to only match the full name, so any record storing the short
     * code resolved to null - which silently dropped those students out of
     * section labels and every section-scoped list built on them.
     */
    public static function mapCourseToProgram(string $course): ?string
    {
        $course = trim($course);

        if ($course === '') {
            return null;
        }

        foreach (self::PROGRAM_COURSES as $code => $fullName) {
            if (strcasecmp($course, $code) === 0 || strcasecmp($course, $fullName) === 0) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Compute a deterministic section label from a student's course and year_section.
     *
     * Handles both short formats ("4th · A") and long formats ("4th Year · BSCS-4A")
     * by extracting only the trailing section letter from the second segment.
     *
     * Returns null when the course cannot be mapped or inputs are empty.
     */
    /** Capstone is a 3rd and 4th year subject; no other year uses this system. */
    public const CAPSTONE_YEARS = [3, 4];

    public static function computeSectionLabel(?string $course, ?string $yearSection): ?string
    {
        if (! $course || ! $yearSection) {
            return null;
        }

        $program = self::mapCourseToProgram($course);
        if (! $program) {
            return null;
        }

        $parts = explode(' · ', $yearSection);
        $year = isset($parts[0]) ? preg_replace('/[^0-9]/', '', $parts[0]) : '';
        $secPart = $parts[1] ?? '';

        // Extract only trailing letters — this handles both "A" and "BSCS-4A" inputs
        preg_match('/[A-Za-z]+$/', $secPart, $matches);
        $secLetter = $matches[0] ?? '';

        return $program.'-'.$year.$secLetter;
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

    public function currentTeam()
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
