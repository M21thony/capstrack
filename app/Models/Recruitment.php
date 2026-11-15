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
     * The Primary choices on Create Post, in the order offered: the role the
     * student takes on the team. 'role' is the team role the choice stands
     * for, which the join flow matches on.
     *
     * Project Head is the leader's and Programmer is everybody's, so neither
     * is a choice.
     *
     * @var array<string, array{label: string, role: string}>
     */
    public const ROLE_OPTIONS = [
        'nd' => ['label' => 'Network Designer/UI Designer', 'role' => 'Network Designer/UI Designer'],
        'qa' => ['label' => 'QA Tester/Documentation', 'role' => 'QA Tester/Documentation Writer'],
    ];

    /**
     * What older posts were tagged with, when each role came as "(Primary)"
     * or "(Secondary)". Both read as the role itself now. Leader and Member
     * named no seat and are no longer shown.
     *
     * @var array<string, string>
     */
    private const OLDER_TAGS = [
        'nd_primary' => 'nd',
        'nd_secondary' => 'nd',
        'qa_primary' => 'qa',
        'qa_secondary' => 'qa',
    ];

    /**
     * The Secondary choices on Create Post: what else the student can give
     * the team, beside their role. Kept short, and none of them repeats a
     * Primary role (no "UI Design", "Testing" or "Documentation").
     *
     * @var list<string>
     */
    public const SECONDARY_SKILLS = [
        'Web Development',
        'Mobile Development',
        'Database',
        'IoT / Hardware',
        'Data Analysis',
        'AI / Machine Learning',
        'Cybersecurity',
        'Graphic Design',
        'Research',
        'Presentation',
    ];

    /**
     * What a form sent, as option keys - in the order offered, or with
     * $asPicked in the order the student picked them, which is the order the
     * team roles behind them are kept in. A key, a label, an older tag or a
     * bare team role name (older forms, and the API) are all understood.
     * Anything else is dropped.
     *
     * @param  array<int, mixed>  $picked
     * @return list<string>
     */
    public static function tagKeys(array $picked, bool $asPicked = false): array
    {
        $byName = [];
        foreach (self::ROLE_OPTIONS as $key => $option) {
            $byName[mb_strtolower($option['label'])] = $key;
            $byName[mb_strtolower($option['role'])] = $key;
            // The label an older post or client knew the choice by.
            $byName[mb_strtolower($option['label'].' (Primary)')] = $key;
            $byName[mb_strtolower($option['label'].' (Secondary)')] = $key;
        }

        $keys = collect($picked)->filter(fn ($v) => is_string($v))->map(function (string $v) use ($byName) {
            $v = trim($v);

            return array_key_exists($v, self::ROLE_OPTIONS) ? $v : (self::OLDER_TAGS[$v] ?? $byName[mb_strtolower($v)] ?? null);
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
     * What a form sent as Secondary, kept to the list and in its order.
     *
     * @param  array<int, mixed>  $picked
     * @return list<string>
     */
    public static function secondarySkills(array $picked): array
    {
        return array_values(array_intersect(self::SECONDARY_SKILLS, array_filter($picked, 'is_string')));
    }

    /**
     * The post's tags as option keys. A post from before tags existed has
     * only `roles`.
     *
     * @return list<string>
     */
    public function tagKeyList(): array
    {
        $saved = is_array($this->role_tags) ? $this->role_tags : [];

        return self::tagKeys($saved !== [] ? $saved : $this->roles);
    }

    /**
     * The post's roles as they are shown.
     *
     * @return list<string>
     */
    public function tagLabels(): array
    {
        $saved = is_array($this->role_tags) ? $this->role_tags : [];

        if ($saved !== []) {
            return array_map(fn ($key) => self::ROLE_OPTIONS[$key]['label'], $this->tagKeyList());
        }

        // A post from before the choices: a role it names shows as the choice
        // when it is one of them, and as written when it is not - an old post
        // asking for a "Frontend Developer" still says so.
        return collect($this->roles)->filter(fn ($r) => is_string($r) && trim($r) !== '')
            ->map(fn (string $role) => ($key = self::tagKeys([$role])[0] ?? null) ? self::ROLE_OPTIONS[$key]['label'] : trim($role))
            ->unique()->values()->all();
    }

    /**
     * The post's Secondary skills as they are shown. Skills saved before the
     * short list (from the experts' catalogue) are not on it and are left out.
     *
     * @return list<string>
     */
    public function secondaryLabels(): array
    {
        return self::secondarySkills(is_array($this->skills) ? $this->skills : []);
    }

    /**
     * The leaders whose teams have reached the CaPSA/TSA's member limit, so
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
