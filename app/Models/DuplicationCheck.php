<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicationCheck extends Model
{
    protected $fillable = [
        'student_s_y_id',
        'document_path',
        'concepts',
        'top_score',
        'verdict',
        'matches',
        'instructors_suggested',
    ];

    protected $casts = [
        'concepts' => 'array',
        'matches' => 'array',
        'instructors_suggested' => 'array',
        'top_score' => 'integer',
    ];

    /**
     * The suggestions, with a role on every one of them.
     *
     * instructors_suggested is written once and read forever, so a check run
     * before the panel knew about roles has entries carrying only a name, an
     * expertise string and a reason. Nothing displays a label it has not got,
     * which is why those cards looked bare while newer ones read correctly -
     * and no amount of changing the template fixes a field that was never
     * stored.
     *
     * The role is recovered from the expert instead. Only adviser and
     * consultant are recommended now, so anything else is dropped rather than
     * relabelled: a grammarian on an old check was picked on grounds this
     * panel no longer uses.
     *
     * @return array<int, array<string, mixed>>
     */
    public function suggestionsForDisplay(): array
    {
        $stored = collect((array) $this->instructors_suggested)->filter(fn ($s) => is_array($s));

        if ($stored->isEmpty()) {
            return [];
        }

        $missing = $stored->filter(fn ($s) => blank($s['role_label'] ?? null))->pluck('id')->filter();

        $roles = $missing->isEmpty()
            ? collect()
            : CapstoneExpert::with('roles')->whereIn('expert_id', $missing)->get()->keyBy('expert_id');

        return $stored->map(function (array $suggestion) use ($roles) {
            if (filled($suggestion['role_label'] ?? null)) {
                return $suggestion;
            }

            $held = $roles->get($suggestion['id'] ?? 0)?->roles->pluck('role_name') ?? collect();

            // Adviser first: somebody who holds both is far more often
            // suggested here for the study than for the build.
            $role = collect(['adviser', 'consultant'])->first(fn ($r) => $held->contains($r));

            if (! $role) {
                return null;
            }

            return $suggestion + ['role' => $role, 'role_label' => Role::labelFor($role)];
        })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The stored matches, with each study's authors read from the archive.
     *
     * matches is a copy taken when the check ran, so a check run before the
     * authors were recorded would say "not recorded" for ever.
     *
     * @return array<int, array<string, mixed>>
     */
    public function matchesWithAuthors(): array
    {
        $matches = array_values(array_filter((array) $this->matches, 'is_array'));
        $authors = ArchivedDocument::whereIn('archived_id', array_filter(array_column($matches, 'archived_id')))
            ->pluck('authors', 'archived_id');

        return array_map(
            fn (array $m) => ['authors' => $authors[$m['archived_id'] ?? 0] ?? ($m['authors'] ?? null)] + $m,
            $matches
        );
    }

    public function studentSchoolYear(): BelongsTo
    {
        return $this->belongsTo(StudentSchoolYear::class, 'student_s_y_id', 's_y_id');
    }

    public function team(): ?Team
    {
        return $this->studentSchoolYear->team;
    }

    public function student(): ?Student
    {
        return $this->studentSchoolYear->student;
    }
}
