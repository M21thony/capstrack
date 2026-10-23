<?php

namespace App\Support;

use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Team selection: the recruitment posts a student's section can see.
 *
 * Posts from the same course, section and school year, never the student's
 * own, and never a team that has reached the CaPSA's limit. They come eight
 * at a time, narrowed by what the post is for - building a team or finding
 * one - and by skill. The page shows the first eight; Load more asks for the
 * next through the same query, so the two can't disagree.
 */
class TeamSelectionFeed
{
    public const PER_PAGE = 8;

    /** @var array<string, string> what each tab shows, keyed by recruitment goal */
    public const GOALS = ['all' => 'All posts', 'build_a_team' => 'Find members', 'find_a_team' => 'Find team'];

    private function __construct(private Student $student, private ?string $schoolYear) {}

    public static function for(Student $student): self
    {
        $schoolYear = StudentSchoolYear::where('student_id', $student->student_id)->value('school_year');

        return new self($student, $schoolYear !== null ? (string) $schoolYear : null);
    }

    /**
     * One page of posts, newest first. A team's post carries open_slots:
     * how many members it can still take under its section's limit.
     */
    public function page(string $goal = 'all', ?string $skill = null, int $page = 1): LengthAwarePaginator
    {
        $posts = $this->posts($goal, $skill)
            ->with(['student.team'])
            ->latest()
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page));

        $teams = collect($posts->items())->map(fn (Recruitment $r) => $r->student?->team)->filter();
        $members = StudentSchoolYear::whereIn('team_id', $teams->pluck('team_id'))
            ->toBase()->selectRaw('team_id, count(*) as n')->groupBy('team_id')->pluck('n', 'team_id');

        foreach ($posts->items() as $post) {
            $team = $post->student?->team;
            $post->setAttribute('open_slots', $team ? max(0, $team->memberLimit() - (int) ($members[$team->team_id] ?? 0)) : null);
        }

        return $posts;
    }

    /**
     * How many posts each tab holds.
     *
     * @return array{all: int, build_a_team: int, find_a_team: int}
     */
    public function counts(): array
    {
        $byGoal = $this->posts()->toBase()->selectRaw('recruitment_goal, count(*) as n')->groupBy('recruitment_goal')->pluck('n', 'recruitment_goal');

        return [
            'all' => (int) $byGoal->sum(),
            'build_a_team' => (int) ($byGoal['build_a_team'] ?? 0),
            'find_a_team' => (int) ($byGoal['find_a_team'] ?? 0),
        ];
    }

    /**
     * The skills some post here names, in catalogue order: what the skill
     * filter offers.
     *
     * @return list<string>
     */
    public function skills(): array
    {
        $used = $this->posts()->pluck('skills')->flatten()->filter()->unique();

        return collect(SkillCatalog::allSkills())->intersect($used)->values()->all();
    }

    private function posts(string $goal = 'all', ?string $skill = null): Builder
    {
        $student = $this->student;

        return Recruitment::query()
            ->where('student_id', '!=', $student->student_id)
            // Off the list only once the team reaches the CaPSA's limit.
            ->whereNotIn('student_id', Recruitment::leadersOfFullTeams())
            ->whereIn('student_id', function ($query) use ($student) {
                $query->select('student_s_y.student_id')
                    ->from('student_s_y')
                    ->join('students', 'student_s_y.student_id', '=', 'students.student_id')
                    ->where('student_s_y.school_year', $this->schoolYear)
                    ->where('students.course', $student->course)
                    ->where('students.year_section', $student->year_section);
            })
            ->when(isset(self::GOALS[$goal]) && $goal !== 'all', fn ($q) => $q->where('recruitment_goal', $goal))
            ->when($skill, fn ($q) => $q->whereJsonContains('skills', $skill));
    }
}
