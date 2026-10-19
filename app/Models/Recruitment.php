<?php

namespace App\Models;

use Database\Factories\RecruitmentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recruitment extends Model
{
    /** @use HasFactory<RecruitmentFactory> */
    use HasFactory;

    protected $table = 'recruitment';

    protected $primaryKey = 'recruitment_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'student_id',
        'recruitment_goal',
        'roles',
        'role_tags',
        'skills',
        'looking_for',
        'introduction',
        'member_count',
    ];

    protected function casts(): array
    {
        return [
            'member_count' => 'integer',
            'role_tags' => 'array',
            'skills' => 'array',
        ];
    }

    /**
     * The roles a post is looking for, always as a list.
     *
     * Some rows hold the list JSON-encoded twice, which the plain array cast
     * read back as a string - and the Team page's implode() on it took the
     * whole page down for everyone in that section.
     */
    protected function roles(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): array {
                $roles = json_decode((string) $value, true);

                if (is_string($roles)) {
                    $roles = json_decode($roles, true);
                }

                return is_array($roles) ? array_values($roles) : [];
            },
            set: fn (mixed $value): ?string => is_array($value) ? json_encode(array_values($value)) : $value,
        );
    }

    /**
     * What Create Post offers, in the order it offers it. 'role' is the team
     * role the choice stands for - what the join flow matches on - and null
     * for Leader and Member, which name no particular seat.
     *
     * @var array<string, array{label: string, role: ?string}>
     */
    public const ROLE_OPTIONS = [
        'leader' => ['label' => 'Leader', 'role' => null],
        'member' => ['label' => 'Member', 'role' => null],
        'nd_primary' => ['label' => 'Network Designer/UI Designer (Primary)', 'role' => 'Network Designer/UI Designer'],
        'nd_secondary' => ['label' => 'Network Designer/UI Designer (Secondary)', 'role' => 'Network Designer/UI Designer'],
        'qa_primary' => ['label' => 'QA Tester/Documentation (Primary)', 'role' => 'QA Tester/Documentation Writer'],
        'qa_secondary' => ['label' => 'QA Tester/Documentation (Secondary)', 'role' => 'QA Tester/Documentation Writer'],
    ];

    /**
     * What a form sent, as option keys - in the order offered, or with
     * $asPicked in the order the student picked them, which is the order the
     * team roles behind them are kept in. A key, a label or a bare team role
     * name (older forms, and the API) are all understood; a role name reads
     * as its Primary. Anything else is dropped.
     *
     * @param  array<int, mixed>  $picked
     * @return list<string>
     */
    public static function tagKeys(array $picked, bool $asPicked = false): array
    {
        $byLabel = collect(self::ROLE_OPTIONS)->mapWithKeys(fn ($o, $key) => [mb_strtolower($o['label']) => $key]);
        // A bare role name stands for that role's first choice - its Primary.
        $byRole = collect(self::ROLE_OPTIONS)->filter(fn ($o) => $o['role'] !== null)
            ->groupBy(fn ($o) => mb_strtolower($o['role']), preserveKeys: true)
            ->map(fn ($group) => $group->keys()->first())->all();

        $keys = collect($picked)->filter(fn ($v) => is_string($v))->map(function (string $v) use ($byLabel, $byRole) {
            $lower = mb_strtolower(trim($v));

            return array_key_exists($v, self::ROLE_OPTIONS) ? $v : ($byLabel[$lower] ?? $byRole[$lower] ?? null);
        })->filter()->unique()->values()->all();

        return $asPicked ? $keys : array_values(array_intersect(array_keys(self::ROLE_OPTIONS), $keys));
    }

    /**
     * The team roles those choices stand for, for `roles`.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function rolesForTags(array $keys): array
    {
        return collect($keys)->map(fn ($key) => self::ROLE_OPTIONS[$key]['role'] ?? null)->filter()->unique()->values()->all();
    }

    /**
     * The post's tags as option keys. A post from before tags existed has
     * only `roles`; each of those reads as its Primary.
     *
     * @return list<string>
     */
    public function tagKeyList(): array
    {
        $saved = is_array($this->role_tags) ? $this->role_tags : [];

        return self::tagKeys($saved !== [] ? $saved : $this->roles);
    }

    /**
     * The post's tags as they are shown.
     *
     * @return list<string>
     */
    public function tagLabels(): array
    {
        $saved = is_array($this->role_tags) ? $this->role_tags : [];

        if ($saved !== []) {
            return array_map(fn ($key) => self::ROLE_OPTIONS[$key]['label'], $this->tagKeyList());
        }

        // A post from before the six choices: each role it names shows as its
        // Primary when it is one of them, and as written when it is not - an
        // old post asking for a "Frontend Developer" still says so.
        return collect($this->roles)->filter(fn ($r) => is_string($r) && trim($r) !== '')
            ->map(fn (string $role) => ($key = self::tagKeys([$role])[0] ?? null) ? self::ROLE_OPTIONS[$key]['label'] : trim($role))
            ->unique()->values()->all();
    }

    /**
     * The leaders whose teams have reached the CaPSA's member limit, so
     * their build-a-team posts come off Team selection.
     *
     * One answer for the page and the API, which each kept their own copy:
     * one compared against member_count (a flat 5), and both also hid any
     * team marked Full, which stays Full after a member leaves.
     *
     * @return list<int>
     */
    public static function leadersOfFullTeams(): array
    {
        return static::where('recruitment_goal', 'build_a_team')
            ->with('student.team')
            ->get()
            ->filter(fn (self $recruit) => $recruit->student?->team?->isFull() ?? false)
            ->pluck('student_id')
            ->values()
            ->all();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
