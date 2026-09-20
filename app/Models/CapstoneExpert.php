<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'email',
    'first_name',
    'last_name',
    'middle_name',
    'suffix',
    'credentials',
    'highest_degree',
    'profile_picture',
    'is_admin',
    'approved_status',
    'max_teams',
    'program_slots',
    'program',
    'sections',
    'degree_title',
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

    // The programs an expert can accept teams from. Must stay in step with
    // the keys of Student::PROGRAM_COURSES (a const can't call array_keys(),
    // so this is spelled out) - add a program there and it belongs here too,
    // or experts won't be able to take teams from it.
    // "CaPSA" is Capstone [Project] Subject Adviser - the same thing as the
    // capstone_adviser row in `roles`, not a separate concept. expert_roles
    // is the single source of truth for it; capstone_experts.is_capsa is a
    // leftover duplicate of the same fact, no longer read or written, and
    // due to be dropped in a migration.
    public const CAPSA_ROLE = 'capstone_adviser';

    /**
     * The domains an expert can sign in from.
     *
     * AuthController decides who may log in by these, and an account made on
     * anything else is one nobody can ever reach - so anywhere that creates
     * an expert account has to refuse an address outside them.
     */
    public const EMAIL_DOMAINS = ['@cspc.edu.ph', '@gmail.com'];

    public const PROGRAMS = ['BSCS', 'BSIT', 'BSIS', 'BLIS'];

    // The roles a team can actually engage an expert AS. capstone_adviser is
    // deliberately absent: it is a portal permission, not something a team
    // applies for.
    public const STUDENT_FACING_ROLES = ['adviser', 'consultant', 'grammarian'];

    /**
     * Deactivating rather than deleting.
     *
     * Removing someone used to be a real DELETE, so a lecturer on study leave
     * had to be re-added from nothing when they came back. SoftDeletes is
     * exactly this feature, and pointing it at deactivated_at rather than
     * deleted_at keeps the column honest about what it means.
     *
     * The point of doing it here rather than in a dozen queries: every place
     * that lists or picks an expert - the browse page a student applies from,
     * panel assignment, the API - filters on approved_status and none of them
     * had to change. A deactivated expert stops existing for all of them at
     * once. The two management pages that must still show them say so with
     * withTrashed().
     */
    use SoftDeletes;

    public const DELETED_AT = 'deactivated_at';

    protected $primaryKey = 'expert_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    /** Per-instance memo for getIsCapsaAttribute()'s fallback query. */
    protected ?bool $capsaRoleMemo = null;

    protected $casts = [
        'is_admin' => 'boolean',
        'approved_status' => 'boolean',
        'sections' => 'array',
        'created_at' => 'datetime',
        'reject_cooldown_until' => 'datetime',
        'last_seen_at' => 'datetime',
        'first_login_at' => 'datetime',
    ];

    // The pivot's own key comes along so a caller can point at one specific
    // role of this expert - what a team's application records when it says
    // "we engaged them as our consultant". Read it as $role->pivot->expert_role_id.
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'expert_roles', 'expert_id', 'role_id')
            ->withPivot('expert_role_id');
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

    /**
     * How many teams this expert takes, or null if they have never said.
     *
     * This used to hand back 4 for a null, which meant every account ever
     * made was credited with four teams' worth of slots nobody had agreed
     * to - and that number decided who could still be assigned.
     */
    public function getMaxTeamsAttribute($value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    // Removed getExpertiseAttribute accessor to allow direct relationship access

    public function getIsOnlineAttribute(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(5));
    }

    // "Staff" = either Admin or CAPSA - used everywhere the old, single
    // is_admin flag used to mean "this person runs the admin portal, not
    // just an instructor" (login redirect, approval bypass, sidebar merge),
    // now that those two jobs are split into independent flags.
    /**
     * Whether this expert is a CaPSA - resolved from expert_roles, not from
     * the old is_capsa column, so there is exactly one place the answer can
     * come from. Keeping both in step by hand is what let "Remove CaPSA"
     * leave the role behind.
     */
    public function getIsCapsaAttribute(): bool
    {
        // Expert Management eager-loads roles, so use that when it is there
        // and fall back to one existence query otherwise (the sidebar and
        // middleware read this for the logged-in user on every request).
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('role_name', self::CAPSA_ROLE);
        }

        if ($this->capsaRoleMemo === null) {
            $this->capsaRoleMemo = DB::table('expert_roles')
                ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
                ->where('expert_roles.expert_id', $this->expert_id)
                ->where('roles.role_name', self::CAPSA_ROLE)
                ->exists();
        }

        return $this->capsaRoleMemo;
    }

    /**
     * Set an expert's roles by adding and removing only what changed.
     *
     * The obvious version - delete every row, insert the new set - is what
     * this replaces. It looks equivalent, but expert_role_id is now the
     * handle capstone_expert_application points at, so wiping and reinserting
     * an unchanged role hands it a brand-new id and silently orphans every
     * assignment that referenced it. Diffing means a role the expert already
     * had keeps its row, and its id, untouched.
     *
     * $scope limits which role ids may be removed, so saving the student-
     * facing roles cannot revoke capstone_adviser as a side effect.
     */
    public static function syncRoles(int $expertId, iterable $roleIds, ?iterable $scope = null): void
    {
        $desired = collect($roleIds)->map(fn ($id) => (int) $id)->unique();
        $scopeIds = $scope === null ? null : collect($scope)->map(fn ($id) => (int) $id);

        if ($scopeIds !== null) {
            $desired = $desired->intersect($scopeIds);
        }

        $current = DB::table('expert_roles')
            ->where('expert_id', $expertId)
            ->when($scopeIds !== null, fn ($q) => $q->whereIn('role_id', $scopeIds))
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id);

        $remove = $current->diff($desired);
        if ($remove->isNotEmpty()) {
            DB::table('expert_roles')
                ->where('expert_id', $expertId)
                ->whereIn('role_id', $remove)
                ->delete();
        }

        foreach ($desired->diff($current) as $roleId) {
            DB::table('expert_roles')->insertOrIgnore([
                'expert_id' => $expertId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function getIsStaffAttribute(): bool
    {
        return (bool) ($this->is_admin || $this->is_capsa);
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

    /**
     * Whether there is a real picture to show, as opposed to the generic
     * placeholder avatarUrl() falls back to.
     *
     * Experts sign in with Google, so most have a photo from that account
     * already stored in profile_picture - a view that wants to show it needs
     * to know whether it is showing a face or a grey silhouette, since the
     * silhouette is worse than the person's initials.
     */
    public function hasAvatarImage(): bool
    {
        if (! filled($this->profile_picture)) {
            return false;
        }

        return filter_var($this->profile_picture, FILTER_VALIDATE_URL)
            || ($this->hasValidAvatarPreset() && file_exists(public_path('images/avatars/'.$this->profile_picture.'.svg')));
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

    // program_slots preserves whatever the roster upload's "Max Number of
    // Teams" cell actually said, which is sometimes a flat number and
    // sometimes a real per-program split like "1 - BSIT, 1 - BSIS" (see
    // Capsa\ExpertRosterController). This parses that split out for
    // display - a proper structured, editable per-program capacity is a
    // separate, larger change; this just stops the raw text some experts
    // already have from being silently thrown away as a flat number.
    // Returns [[program, max], ...] - one row labeled "ALL" when there's
    // no real split to show.
    public function parsedCapacity(): array
    {
        // >= 1, not > 1: an expert who accepts exactly one program (say 3
        // BSIT teams) is a real, deliberately-saved state now that this is
        // editable, and should read "BSIT 3" rather than collapsing to a
        // programless "ALL 3".
        if ($this->program_slots && preg_match_all('/(\d+)\s*-\s*([A-Za-z]+)/', $this->program_slots, $matches, PREG_SET_ORDER) && count($matches) >= 1) {
            return array_map(fn ($m) => [strtoupper($m[2]), (int) $m[1]], $matches);
        }

        // No capacity set is no slots, not four of them. An expert says
        // how many teams they take from their own profile; until they do,
        // there is no number here to assign against.
        return [['ALL', $this->max_teams ?? 0]];
    }

    /**
     * Turn a program => teams map into what the columns actually hold.
     *
     * Two places ask an expert which programs they take teams from and how
     * many of each - the request form on the way in, and their own profile
     * afterwards - and both have to write the same two columns the same way
     * or the Accepts column reads one of them wrong. The format is not
     * obvious enough to be retyped: program_slots is free text shaped
     * "2 - BSIT, 1 - BSIS", which is what the roster upload's own cells used
     * and what parsedCapacity() reads back.
     *
     * @param  array<string,int|string>  $slots
     * @return array{accepted: array<string,int>, raw: string, total: int}
     */
    public static function packProgramSlots(array $slots): array
    {
        // Walked in PROGRAMS order rather than the caller's, so the saved
        // text is the same however the form happened to send it.
        $accepted = [];
        foreach (self::PROGRAMS as $program) {
            $count = (int) ($slots[$program] ?? 0);
            if ($count > 0) {
                $accepted[$program] = $count;
            }
        }

        $raw = implode(', ', array_map(
            fn ($program, $count) => $count.' - '.$program,
            array_keys($accepted),
            $accepted
        ));

        return [
            'accepted' => $accepted,
            'raw' => $raw,
            // max_teams stays the overall total, so the existing full and
            // slots-left checks keep working untouched.
            'total' => array_sum($accepted),
        ];
    }

    /**
     * Limit to experts who take teams from $program (BSIT, BSIS, ...).
     *
     * Accepted programs live in program_slots as "1 - BSIT, 5 - BSIS" (see
     * parsedCapacity()). An expert who has never set a per-program split has
     * that column empty, and that means "no restriction" - they keep showing
     * up for every student rather than silently vanishing from everyone's
     * list. A null $program (student's course doesn't map to one yet) skips
     * the filter for the same reason.
     *
     * Filtering in SQL rather than after the fact keeps pagination honest.
     * The LIKE is safe here because no program code is a substring of
     * another - see PROGRAMS.
     */
    public function scopeAcceptsProgram($query, ?string $program)
    {
        if (! $program) {
            return $query;
        }

        return $query->where(function ($q) use ($program) {
            $q->whereNull('program_slots')
                ->orWhere('program_slots', '')
                ->orWhere('program_slots', 'like', '%'.$program.'%');
        });
    }

    /**
     * Same rule as scopeAcceptsProgram(), for one already-loaded expert -
     * used to enforce it on apply, not just hide them from the list.
     */
    /**
     * The programs this CaPSA covers, derived from the sections they handle -
     * "BSIT-4A" and "BSIT-4B" are one program, BSIT.
     *
     * Empty for an admin, who has no sections and is not scoped by program.
     *
     * @return array<int, string>
     */
    public function handledPrograms(): array
    {
        return collect($this->sections ?? [])
            ->map(fn ($section) => strtoupper(trim(explode('-', (string) $section)[0] ?? '')))
            ->filter(fn ($program) => in_array($program, self::PROGRAMS, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Has this expert said which programmes they take teams from?
     *
     * program_slots empty means "no restriction" everywhere else, which is
     * right for listing them - an expert who has not filled it in should
     * still be visible rather than silently vanishing from every student's
     * page. It is not right for applying: a team should not be able to
     * engage somebody who has not yet said they take teams like theirs, and
     * how many. So they stay browsable and the apply is refused.
     */
    public function hasSetPrograms(): bool
    {
        return filled($this->program_slots);
    }

    public function acceptsProgram(?string $program): bool
    {
        if (! $program) {
            return true;
        }

        $rows = $this->parsedCapacity();

        // No per-program split set: open to everyone.
        if (count($rows) === 1 && $rows[0][0] === 'ALL') {
            return true;
        }

        foreach ($rows as [$prog, $max]) {
            if ($prog === $program) {
                return $max > 0;
            }
        }

        return false;
    }

    /**
     * How many teams this expert already advises in $program.
     */
    public function approvedTeamCountForProgram(string $program): int
    {
        return $this->approvedTeamCountsByProgram()[$program] ?? 0;
    }

    /**
     * The per-program filled counts behind parsedCapacity(), in one query -
     * [program => teams]. The expert list reads it so a split cap (say
     * 2 - BSIT, 1 - BSIS) can show each program's own progress bar instead
     * of a bare "max n" per line, and the apply check uses the same numbers
     * to stop accepting once a cap is met.
     *
     * A team's program comes from student_s_y.program - the enrolment row
     * that records which program a student was in for a school year. The
     * writers that predate that column left it null though, and a team
     * counted for no program read as zero-filled no matter how many teams
     * had been approved - so a member's course stands in when the row does
     * not say, mapped the same way the profile save maps it.
     *
     * @return array<string, int>
     */
    public function approvedTeamCountsByProgram(): array
    {
        return DB::table('capstone_expert_application as a')
            ->join('student_s_y as sy', 'sy.team_id', '=', 'a.team_id')
            ->leftJoin('students as s', 's.student_id', '=', 'sy.student_id')
            ->where('a.expert_id', $this->expert_id)
            ->where('a.status', 'approved')
            ->select('a.team_id', 'sy.program', 's.course')
            ->get()
            // One program per team, from any enrolment row that can name
            // one; every member of a team is in the same program, and the
            // count is of teams, so extras only ever agree.
            ->groupBy('team_id')
            ->map(function ($rows) {
                foreach ($rows as $row) {
                    $program = $row->program ?: Student::mapCourseToProgram((string) ($row->course ?? ''));

                    if ($program) {
                        return $program;
                    }
                }

                return null;
            })
            ->filter()
            ->countBy()
            ->map(fn ($teams) => (int) $teams)
            ->all();
    }

    /**
     * The cap this expert set for $program, or null when they have no
     * per-program split (in which case only the overall max_teams applies).
     */
    public function slotsForProgram(?string $program): ?int
    {
        if (! $program) {
            return null;
        }

        $rows = $this->parsedCapacity();

        if (count($rows) === 1 && $rows[0][0] === 'ALL') {
            return null;
        }

        foreach ($rows as [$prog, $max]) {
            if ($prog === $program) {
                return $max;
            }
        }

        return 0;
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

    /** Highest degree an expert holds. A Panel Chair needs a Master's or higher. */
    public const DEGREES = [
        'bachelor' => "Bachelor's",
        'master' => "Master's",
        'doctorate' => 'Doctorate',
    ];

    public function degreeLabel(): ?string
    {
        return self::DEGREES[$this->highest_degree] ?? null;
    }

    /** The degrees offered, by level, as they are written after a name. */
    public const DEGREE_TITLES = [
        'bachelor' => ['BSIT', 'BSCS', 'BSIS', 'BLIS', 'BSEd', 'AB', 'BS'],
        'master' => ['MIT', 'MSIT', 'MSCS', 'MIS', 'MLIS', 'MAEd', 'MAT', 'MBA', 'MA', 'MS'],
        'doctorate' => ['PhD', 'DIT', 'EdD', 'DBA', 'DPA'],
    ];

    /** @return array<int, string> */
    public static function degreeTitles(): array
    {
        return array_merge(...array_values(self::DEGREE_TITLES));
    }

    /**
     * A degree title and the level it is: MIT is a master's. An unknown or
     * blank title clears both.
     *
     * @return array{degree_title: ?string, highest_degree: ?string}
     */
    public static function degreeFromTitle(?string $title): array
    {
        foreach (self::DEGREE_TITLES as $level => $titles) {
            if (in_array($title, $titles, true)) {
                return ['degree_title' => $title, 'highest_degree' => $level];
            }
        }

        return ['degree_title' => null, 'highest_degree' => null];
    }

    /**
     * A name as it is written, split from the credentials after the comma:
     * "Juan Dela Cruz, MIT" is Juan Dela Cruz, with MIT.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function splitName(string $name): array
    {
        [$name, $credentials] = array_pad(explode(',', $name, 2), 2, '');
        $credentials = trim($credentials);

        return [trim($name), $credentials !== '' ? mb_substr($credentials, 0, 50) : null];
    }

    /**
     * The highest degree named in someone's credentials: "MIT, LPT" is a
     * master's, "MIT, Ph.D." a doctorate. None named clears the degree.
     *
     * @return array{degree_title: ?string, highest_degree: ?string}
     */
    public static function degreeFromCredentials(?string $credentials): array
    {
        $words = preg_split('/[\s,;\/]+/', str_replace('.', '', (string) $credentials), -1, PREG_SPLIT_NO_EMPTY);

        foreach (array_reverse(self::DEGREE_TITLES, true) as $level => $titles) {
            foreach ($titles as $title) {
                foreach ($words as $word) {
                    if (strcasecmp($word, $title) === 0) {
                        return ['degree_title' => $title, 'highest_degree' => $level];
                    }
                }
            }
        }

        return self::degreeFromTitle(null);
    }

    protected static function booted(): void
    {
        // The degree is read from what is written after the name.
        static::saving(function (self $expert) {
            if ($expert->isDirty('credentials')) {
                $expert->fill(self::degreeFromCredentials($expert->credentials));
            }
        });
    }

    /** What a list shows after the name: MIT, PhD - or the level when only that is on record. */
    public function degreeText(): ?string
    {
        return $this->degree_title ?: $this->degreeLabel();
    }

    /**
     * Whether this expert may chair a defense panel.
     *
     * The department's rule: a Master's degree or higher. No degree on
     * record is not proof of one, so it does not qualify either.
     */
    public function canChairPanel(): bool
    {
        return in_array($this->highest_degree, ['master', 'doctorate'], true);
    }
}
