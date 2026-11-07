<?php

namespace App\Support;

use App\Models\CapstoneDocument;
use App\Models\CapstoneExpert;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bluebook Clearance: the milestone the Dean's Office checks. Each line takes
 * a PDF, a DOCX or a Google Drive video link; the dean accepts or sends back
 * each one (a DeliverableCheck), then clears the team, which closes the
 * clearance line and gives the slip its number.
 */
class Bluebook
{
    public const NOT_SUBMITTED = 'not_submitted';

    public const WAITING = 'waiting';

    public const SENT_BACK = 'sent_back';

    public const CLEARED = 'cleared';

    /** The team's Bluebook milestone, or null when its checklist has none. */
    public static function of(Team $team, ?object $progress = null): ?object
    {
        $progress ??= $team->checklistProgress();
        $summary = $progress?->milestones->first(fn ($m) => $m->items->contains(fn ($row) => $row->item->kind === Deliverable::BLUEBOOK));

        if (! $summary) {
            return null;
        }

        $rows = $summary->items->filter(fn ($row) => $row->item->kind === Deliverable::BLUEBOOK);
        $clearance = $rows->first(fn ($row) => $row->item->closesOnBluebookClearance());
        $lines = $rows->reject(fn ($row) => $row->item->closesOnBluebookClearance())->values();
        $files = CapstoneDocument::whereIn('file_id', $lines->pluck('file_id')->filter())->get()->keyBy('file_id');
        $lines->each(fn ($row) => $row->file = $files->get($row->file_id));

        $accepted = $lines->filter(fn ($row) => self::done($row))->count();
        $cleared = $clearance && self::done($clearance);
        $check = $cleared ? DeliverableCheck::where('deliverable_id', $clearance->item->id)->where('team_id', $team->team_id)->first() : null;

        return (object) [
            'milestone' => $summary->milestone,
            'locked' => $summary->locked,
            'requires' => $summary->requires,
            'lines' => $lines,
            'clearance' => $clearance,
            'accepted' => $accepted,
            'cleared' => $cleared,
            'cleared_on' => $check?->checked_at,
            'cleared_by' => $check ? CapstoneExpert::find($check->checked_by)?->name : null,
            'slip_no' => $check ? 'BC-'.$check->checked_at->year.'-'.str_pad((string) $check->id, 4, '0', STR_PAD_LEFT) : null,
            'status' => match (true) {
                $cleared => self::CLEARED,
                $lines->contains('waiting_on', MilestoneProgress::WAITING_ON_TEAM) => self::SENT_BACK,
                $lines->contains('waiting_on', MilestoneProgress::WAITING_ON_DEAN) || ($lines->isNotEmpty() && $accepted === $lines->count()) => self::WAITING,
                default => self::NOT_SUBMITTED,
            },
        ];
    }

    /**
     * Every team whose checklist has a Bluebook milestone, for the dean. One
     * progress read per checklist, not per team.
     *
     * @return Collection<int, object> {team, section, program, members, bluebook, submitted}
     */
    public static function forDean(?int $teamId = null): Collection
    {
        $scope = app(SectionScope::class);
        $checklists = app(TeamChecklist::class);
        $teams = Team::where('is_confirmed', true)->when($teamId, fn ($q) => $q->where('team_id', $teamId))->get();
        $records = StudentSchoolYear::whereIn('team_id', $teams->pluck('team_id'))->with('student')->get()->groupBy('team_id');
        $rows = collect();

        foreach ($teams->groupBy(fn (Team $t) => $scope->sectionFor($t, $records->get($t->team_id, collect()))) as $section => $group) {
            $checklist = $checklists->forSection((string) $section);
            $progress = $checklist ? MilestoneProgress::forTeams($checklist, $group->pluck('team_id')) : [];

            foreach ($group as $team) {
                $bluebook = isset($progress[$team->team_id]) ? self::of($team, $progress[$team->team_id]) : null;

                if (! $bluebook || $bluebook->locked) {
                    continue;
                }

                $rows->push((object) [
                    'team' => $team,
                    'section' => (string) $section,
                    'program' => TeamChecklist::programOf((string) $section),
                    'members' => $records->get($team->team_id, collect())->pluck('student')->filter()
                        ->map(fn ($s) => trim($s->f_name.' '.$s->l_name))->sort()->values(),
                    'bluebook' => $bluebook,
                    'submitted' => $bluebook->lines->pluck('file.created_at')->filter()->max(),
                ]);
            }
        }

        return $rows->sortBy(fn ($r) => mb_strtolower($r->team->team_name))->values();
    }

    public static function done(object $row): bool
    {
        return in_array($row->state, MilestoneProgress::COMPLETE_STATES, true);
    }

    /** The file id in a Google Drive share link, or null when it is not one. */
    public static function driveId(?string $url): ?string
    {
        return $url && preg_match('#drive\.google\.com/(?:file/d/|open\?id=)([\w-]{20,})#', $url, $m) ? $m[1] : null;
    }

    /** PDF, DOCX or Video; null when nothing was sent. */
    public static function kind(?CapstoneDocument $file): ?string
    {
        return ! $file ? null : (self::driveId($file->file_path) ? 'Video' : strtoupper(pathinfo($file->file_path, PATHINFO_EXTENSION)));
    }

    /** A PDF opens in the viewer; a DOCX downloads. A video is a link, not a file. */
    public static function fileResponse(CapstoneDocument $document): Response
    {
        abort_if(! $document->file_path || self::driveId($document->file_path), 404);

        $pdf = str_ends_with(strtolower($document->file_path), '.pdf');

        return Storage::disk('local')->response(
            $document->file_path,
            $document->file_name,
            $pdf ? ['Content-Type' => 'application/pdf'] : [],
            $pdf ? 'inline' : 'attachment',
        );
    }
}
