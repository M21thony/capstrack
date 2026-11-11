<?php

namespace App\Models;

use App\Models\Concerns\HasNotificationPrefs;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
    'is_returnee',
])]
class Student extends Authenticatable
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, HasNotificationPrefs, Notifiable;

    public function notificationAudience(): string
    {
        return 'student';
    }

    protected $primaryKey = 'student_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'tutorial_completed_at' => 'datetime',
            'page_tours_seen' => 'array',
            'is_returnee' => 'boolean',
        ];
    }

    /**
     * Year and section, always stored the one way: "4th · A".
     *
     * A class list with a combined "Year & Section" column wrote whatever it
     * held - "4th Year · BSCS-4A" - so one section was on file two ways.
     * Classmates are matched on this column, and the sidebar read the long
     * one as "BSCS - 4BSCS-4A".
     */
    protected function yearSection(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => self::normalizeYearSection($value));
    }

    /**
     * "4th Year · BSCS-4A", "4 · a" and "4th · A" all read "4th · A". Only a
     * value with the "·" between year and section is reworked; anything else
     * is kept as it came, trimmed, rather than guessed at.
     */
    public static function normalizeYearSection(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $parts = array_map('trim', explode('·', $value, 2));

        if (count($parts) !== 2
            || ! preg_match('/\d+/', $parts[0], $year)
            || ! preg_match('/[A-Za-z]+$/', $parts[1], $section)) {
            return $value;
        }

        $n = (int) $year[0];
        $ordinal = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => $n.'th',
            $n % 10 === 1 => $n.'st',
            $n % 10 === 2 => $n.'nd',
            $n % 10 === 3 => $n.'rd',
            default => $n.'th',
        };

        return $ordinal.' · '.strtoupper($section[0]);
    }

    public function hasCompletedProfile(): bool
    {
        return filled($this->f_name)
            && filled($this->l_name)
            && filled($this->student_number);
    }

    /**
     * A new account that has not yet been walked through the portal.
     *
     * Does not wait for a complete profile. The profile.complete gate is off,
     * so a new sign-in lands straight on the dashboard with only the name
     * Google gave it; waiting for a student number would mean most new
     * accounts never saw the tour at all.
     */
    public function needsPortalTour(): bool
    {
        return $this->tutorial_completed_at === null;
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
    /**
     * Deleting a student withdraws their invitation as well.
     *
     * student_rosters is what admits somebody at sign-in - not this table.
     * So deleting the account on its own achieves nothing: the next sign-in
     * reads the roster, finds them still listed, and builds the account
     * again. It looks exactly like the deletion never happened, and it has
     * been reported three times as "the gate is not working".
     *
     * This closes every path through the application. A row deleted by hand
     * in a database tool still leaves the roster entry behind, because
     * nothing in PHP runs for that - both tables have to go.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $student) {
            StudentRoster::whereRaw('LOWER(email) = ?', [Str::lower($student->email)])->delete();

            // Off their team too: an enrolment row left naming the team made it
            // look like it still had a member (Team Torchic, "0/5 members").
            $rows = StudentSchoolYear::withoutGlobalScope(StudentSchoolYear::ACTIVE_TERM_SCOPE)
                ->where('student_id', $student->student_id)
                ->whereNotNull('team_id');
            $teamIds = (clone $rows)->pluck('team_id')->unique()->all();

            $rows->update(['team_id' => null, 's_role_id' => null]);

            Team::whereIn('team_id', $teamIds)->get()->each->reopenIfRoom();
        });
    }

    /** Capstone is a 3rd and 4th year subject; no other year uses this system. */
    public const CAPSTONE_YEARS = [3, 4];

    /**
     * Their year as a number, read off year_section.
     *
     * Stored as "4th · B" - a label meant for reading, which is why nothing
     * could compare it.
     */
    public function yearNumber(): ?int
    {
        return preg_match('/\d+/', (string) $this->year_section, $m) ? (int) $m[0] : null;
    }

    /**
     * Which Capstone this student is in.
     *
     * 3rd year is Capstone 1, 4th is Capstone 2. The year is the signal
     * because of how the list works: a CaPSA/TSA uploads a section when it
     * starts capstone, and uploads it again as 4th year when it moves up -
     * so the year on the roster changes exactly when the stage does, with
     * nobody having to remember to declare it anywhere.
     */
    public function capstoneStage(): int
    {
        return ($this->yearNumber() ?? 3) >= 4 ? 2 : 1;
    }

    /** This student's section, e.g. "BSIT-3A" - whose Team Rules they are under before they have a team. */
    public function sectionLabel(): ?string
    {
        return self::computeSectionLabel($this->course, $this->year_section);
    }

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
