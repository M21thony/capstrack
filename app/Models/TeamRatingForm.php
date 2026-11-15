<?php

namespace App\Models;

use App\Services\Notifier;
use App\Support\PeerRatingRubric;
use App\Support\SectionScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One named team rating ("Midterm Team Participation Rating"): the
 * activities it covers, the sections that rate, and one rating window.
 *
 * A rating that follows the checklist works its window out when it is read,
 * from the latest due date among its activities - so moving a milestone on
 * the checklist moves the rating with no hook in the checklist code. Once the
 * CaPSA/TSA changes the date by hand (date_overridden) the stored dates win.
 */
class TeamRatingForm extends Model
{
    protected $fillable = [
        'capsa_id', 'name', 'sections', 'follows_checklist', 'days_to_rate', 'rubric',
        'opens_on', 'due_on', 'date_overridden', 'closed_at', 'opened_notified_at', 'reminded_at',
    ];

    protected $casts = [
        'sections' => 'array',
        'follows_checklist' => 'boolean',
        'days_to_rate' => 'integer',
        'rubric' => 'array',
        'opens_on' => 'date',
        'due_on' => 'date',
        'date_overridden' => 'boolean',
        'closed_at' => 'datetime',
        'opened_notified_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    /**
     * The rubric this rating was made with. A rating from before rubrics
     * could be written holds none, and was made with the department's.
     */
    public function rubric(): PeerRatingRubric
    {
        return PeerRatingRubric::fromArray($this->getAttribute('rubric'));
    }

    public function items()
    {
        return $this->hasMany(TeamRatingItem::class, 'form_id')->orderBy('id');
    }

    public function ratings()
    {
        return $this->hasMany(TeamRating::class, 'form_id');
    }

    /** @return array{0: Carbon, 1: Carbon} The day it opens and the last day to rate. */
    public function window(): array
    {
        if ($this->follows_checklist && ! $this->date_overridden) {
            $latest = $this->items->map->dueOn()->filter()->max();

            if ($latest) {
                $opens = $latest->copy()->max($this->created_at->copy()->startOfDay());

                return [$opens, $opens->copy()->addDays($this->days_to_rate)];
            }
        }

        return [$this->opens_on->copy(), $this->due_on->copy()];
    }

    public function opensOn(): Carbon
    {
        return $this->window()[0];
    }

    public function dueOn(): Carbon
    {
        return $this->window()[1];
    }

    /** 'open', 'scheduled' or 'closed'. */
    public function status(): string
    {
        [$opens, $due] = $this->window();

        return match (true) {
            $this->closed_at !== null || today()->gt($due) => 'closed',
            today()->lt($opens) => 'scheduled',
            default => 'open',
        };
    }

    public function isOpen(): bool
    {
        return $this->status() === 'open';
    }

    /**
     * The teams that rate in this form, each with its section and members.
     *
     * A team with no section is left out: it belongs to no CaPSA/TSA's list here.
     *
     * @return Collection<int, array{team: Team, section: string, members: Collection<int, Student>}>
     */
    public function teams(): Collection
    {
        $records = StudentSchoolYear::whereNotNull('team_id')->with('student')->get()->groupBy('team_id');
        $filtered = app(SectionScope::class)->filterTeams(
            Team::whereIn('team_id', $records->keys())->orderBy('team_id')->get(),
            $records,
            $this->sections,
        );

        return $filtered['teams']
            ->filter(fn (Team $team) => in_array($filtered['teamSections'][$team->team_id], $this->sections, true))
            ->mapWithKeys(fn (Team $team) => [$team->team_id => [
                'team' => $team,
                'section' => $filtered['teamSections'][$team->team_id],
                'members' => $filtered['teamMembers'][$team->team_id],
            ]]);
    }

    /** @return list<int> Students who submitted their ratings for this form. */
    public function submittedRaterIds(): array
    {
        return $this->ratings()->whereNotNull('submitted_at')->distinct()->pluck('rater_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return Collection<int, Student> Every student who rates in this form. */
    public function students(?Collection $teams = null): Collection
    {
        return ($teams ?? $this->teams())->flatMap(fn ($t) => $t['members'])->values();
    }

    /**
     * What each student's teammates gave them, from submitted ratings only:
     * [ratee_id][item_id] => ['avg' => out of the rubric's max, 'criteria' => an average each, 'n' => raters].
     */
    public function results(): array
    {
        $out = [];
        $criteria = $this->rubric()->count();
        $rows = $this->ratings()->whereNotNull('submitted_at')->get()->groupBy(['ratee_id', 'item_id']);

        foreach ($rows as $rateeId => $byItem) {
            foreach ($byItem as $itemId => $given) {
                $out[$rateeId][$itemId] = [
                    'avg' => round($given->avg('total'), 1),
                    'criteria' => collect(range(0, $criteria - 1))
                        ->map(fn ($i) => round($given->avg(fn ($r) => $r->scores[$i] ?? 0), 1))->all(),
                    'n' => $given->count(),
                ];
            }
        }

        return $out;
    }

    /** One student's rating as a percent, over the activities someone rated them on; null when nobody has. */
    public function percentFor(array $byItem): ?float
    {
        $max = $this->rubric()->max();

        return $byItem === [] ? null : round(collect($byItem)->avg(fn ($r) => $r['avg'] / $max * 100), 1);
    }

    /** Tells every student in its sections that it is open. Sent once. */
    public function announceOpen(): void
    {
        Notifier::toStudents(
            $this->students(),
            'team_rating_opened',
            'Rate your teammates',
            '"'.$this->name.'" is open until '.$this->dueOn()->format('M j').'.',
            route('student.team', ['tab' => 'rating']),
        );
        $this->update(['opened_notified_at' => now()]);
    }

    /** Reminds the students who have not submitted. @return int how many were reminded */
    public function remind(): int
    {
        $done = $this->submittedRaterIds();
        $waiting = $this->students()->reject(fn (Student $s) => in_array((int) $s->student_id, $done, true));

        Notifier::toStudents(
            $waiting,
            'team_rating_reminder',
            'Your team rating is not submitted',
            '"'.$this->name.'" closes on '.$this->dueOn()->format('M j').'. Rate your teammates before then.',
            route('student.team', ['tab' => 'rating']),
        );

        return $waiting->count();
    }
}
