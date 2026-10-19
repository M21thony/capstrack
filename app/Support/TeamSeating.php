<?php

namespace App\Support;

use App\Models\Recruitment;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Which member roles a team still has room for, and what a joining member
 * gets.
 *
 * The same question used to be answered in three places - the team page's
 * role dialog and both invitation controllers - each looking up the
 * leader's recruitment post and counting seats its own way. They are asked
 * of this one object now, so the rule for a seat cannot drift between the
 * page that offers a role and the request that hands one over.
 *
 * Only members who have joined fill a seat. An invitation carries no role:
 * the member picks theirs after joining, from what is left.
 */
class TeamSeating
{
    /**
     * @param  array<int, array{role: StudentRole, taken: int, seats: ?int, held_by: array<int, string>}>  $seating
     * @param  Collection<int, string>  $wanted  The roles the leader's recruitment post asked for.
     */
    private function __construct(private array $seating, private Collection $wanted) {}

    public static function for(Team $team): self
    {
        $size = StudentRole::teamSize($team->team_id);

        $holders = StudentSchoolYear::where('team_id', $team->team_id)
            ->whereNotNull('s_role_id')
            ->with('student')
            ->get()
            ->groupBy('s_role_id');

        $seating = StudentRole::memberRoles()->map(function ($role) use ($holders, $size) {
            $held = $holders->get($role->s_role_id, collect());

            return [
                'role' => $role,
                'taken' => $held->count(),
                'seats' => StudentRole::seatsFor($role->s_role_name, $size),
                'held_by' => $held
                    ->map(fn ($row) => trim(($row->student->f_name ?? '').' '.($row->student->l_name ?? '')))
                    ->filter()
                    ->values()
                    ->all(),
            ];
        })->all();

        $wanted = collect((array) Recruitment::where('student_id', $team->leader_id)
            ->where('recruitment_goal', 'build_a_team')
            ->value('roles'))->filter()->values();

        return new self($seating, $wanted);
    }

    /**
     * The member roles with a seat still free, by name - what the team still
     * needs.
     *
     * @return list<string>
     */
    public function openRoles(): array
    {
        return collect($this->seating)
            ->filter(fn ($row) => $this->hasRoom($row))
            ->map(fn ($row) => $row['role']->s_role_name)
            ->values()
            ->all();
    }

    /**
     * The roles a member who has just joined may pick from.
     *
     * Narrowed to what the team's recruitment post asked for, when it asked
     * for anything - a team advertising for a tester should not have somebody
     * arrive as a UI designer. With no post, or none of its roles left with a
     * seat, every member role is on the table, because the alternative is a
     * student who cannot answer the question.
     *
     * @return list<array{id: int, name: string, duties: ?string, taken: int, seats: ?int, held_by: array<int, string>, full: bool}>
     */
    public function choices(): array
    {
        $asked = collect($this->seating)
            ->filter(fn ($row) => $this->wanted->contains($row['role']->s_role_name))
            ->filter(fn ($row) => $this->hasRoom($row));

        $offer = $asked->isNotEmpty() ? $asked : collect($this->seating);

        return $offer->map(fn ($row) => [
            'id' => $row['role']->s_role_id,
            'name' => $row['role']->s_role_name,
            'duties' => $row['role']->s_role_description,
            'taken' => $row['taken'],
            'seats' => $row['seats'],
            'held_by' => $row['held_by'],
            'full' => ! $this->hasRoom($row),
        ])->values()->all();
    }

    /**
     * The role a joining member is given outright, or null when they choose.
     *
     * When the recruitment post names exactly one role that still has a
     * seat, that is the answer and the student is never asked. Two or more
     * is a genuine choice, and no post at all - every directly-invited
     * member - leaves nothing to go on; both answer null, and the team page
     * asks them once, on arrival.
     */
    public function roleOnJoin(): ?int
    {
        $open = collect($this->seating)
            ->filter(fn ($row) => $this->wanted->contains($row['role']->s_role_name))
            ->filter(fn ($row) => $this->hasRoom($row))
            ->values();

        return $open->count() === 1 ? $open->first()['role']->s_role_id : null;
    }

    /**
     * @param  array{seats: ?int, taken: int}  $row
     */
    private function hasRoom(array $row): bool
    {
        // A role with no seat limit never fills.
        return $row['seats'] === null || $row['taken'] < $row['seats'];
    }
}
