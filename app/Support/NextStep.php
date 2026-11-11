<?php

namespace App\Support;

use App\Models\Student;

/**
 * The one thing a student's team has to do next to get set up: a team, then
 * titles turned in to the CaPSA/TSA, then an adviser. Read by the dashboard's
 * "Your next step" card and by the sidebar, which puts a dot on that page.
 *
 * Null once the team has an adviser - from there the section's checklist
 * (the "Where your team is" card) says what is next.
 *
 * The order is the app's own: an adviser can only be applied to once the
 * CaPSA/TSA has recommended a title and verified the team
 * (Student\ExpertController, $canApply).
 */
class NextStep
{
    public static function for(Student $student): ?object
    {
        $team = StudentTeam::for($student);

        if (! $team) {
            return self::step('student.team', false, 'Create or join a team',
                'Most of CapsTrack opens once you are in a team.',
                ['Build a Team, or send a request under Find a Team.', 'Invite classmates by name.', 'They join when they accept.'],
                'Go to Team Management',
                [['Team', 'todo', 'Not in a team yet'], ['Titles', 'lock', 'Needs a team'], ['Adviser', 'lock', 'Needs a team']]);
        }

        $assignments = $team->expertAssignments()->with('expert')->get()
            ->filter(fn ($a) => strtolower($a->engaged_role_name ?? '') === 'adviser');

        if ($assignments->firstWhere('status', 'approved')) {
            return null;
        }

        $pending = $assignments->firstWhere('status', 'pending');
        $isLeader = (int) $team->leader_id === (int) $student->student_id;
        $teamRow = ['Team', 'ok', $team->team_name];

        // The same reading as PreProposalController::isTurnedIn(): a set the
        // CaPSA/TSA turned down in full is no longer turned in.
        $submitted = $team->preProposals()->whereNotNull('submitted_at');
        $turnedIn = (clone $submitted)
            ->where(fn ($q) => $q->where('approved_status', true)->orWhereNull('rejection_reason'))
            ->exists();

        if (! $turnedIn) {
            $rejected = $submitted->exists();

            return self::step('student.duplication', false,
                $rejected ? 'Write new titles' : 'Check and turn in your titles',
                $rejected
                    ? 'Your CaPSA/TSA turned down the titles you sent. Check new ones and turn them in again.'
                    : 'Each title is compared with past capstones, then your CaPSA/TSA picks one to recommend.',
                ['Type a title and press Check similarity.', 'If a past capstone is too close, change it and check again.',
                    $isLeader ? 'Turn in the titles you want your CaPSA/TSA to see.' : 'Your leader turns them in to your CaPSA/TSA.'],
                'Go to Pre-Proposal Submission',
                [$teamRow, ['Titles', 'todo', $rejected ? 'Turned down, write new ones' : 'Not turned in yet'], ['Adviser', 'lock', 'After your CaPSA/TSA recommends a title']]);
        }

        if (! $team->selected_pre_proposal_id || ! $team->is_confirmed) {
            return self::step(null, true, 'Wait for your CaPSA/TSA',
                'Your CaPSA/TSA is checking your titles and your team. The bell tells you when they recommend a title.',
                [], 'See your titles',
                [$teamRow, ['Titles', 'wait', 'With your CaPSA/TSA'], ['Adviser', 'lock', 'After your CaPSA/TSA recommends a title']], 'student.duplication');
        }

        $titleRow = ['Titles', 'ok', 'Recommended: '.($team->selectedPreProposal?->title ?? 'yes')];

        if ($pending) {
            $name = $pending->expert?->name ?? 'the adviser';

            return self::step(null, true, 'Wait for '.$name,
                'You asked '.$name.' to be your adviser. The bell tells you when they answer.',
                [], 'See your experts',
                [$teamRow, $titleRow, ['Adviser', 'wait', 'Asked '.$name]], 'student.experts');
        }

        return self::step('student.experts', false, 'Get an adviser',
            'Your CaPSA/TSA recommended your title, so you can now ask an adviser to take your team.',
            ['Under Find experts, pick an adviser who fits your topic.',
                $isLeader ? 'Press Apply.' : 'Your leader presses Apply.', 'The bell tells you when they accept.'],
            'Find an adviser',
            [$teamRow, $titleRow, ['Adviser', 'todo', 'None yet']]);
    }

    private static function step(?string $route, bool $waiting, string $title, string $why, array $how, string $button, array $rows, ?string $buttonRoute = null): object
    {
        return (object) [
            // The page the sidebar marks; null while the team is waiting on someone else.
            'route' => $route,
            'waiting' => $waiting,
            'title' => $title,
            'why' => $why,
            'how' => $how,
            'button' => $button,
            'url' => route($route ?? $buttonRoute),
            'rows' => $rows,
        ];
    }
}
