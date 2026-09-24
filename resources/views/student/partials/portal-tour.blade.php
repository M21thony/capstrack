{{-- The student portal's guided tour: its steps, for partials/portal-tour,
     which draws it the same way in every portal. Included from the sidebar,
     so every student page has it. Steps and copy: config/student-tour.php. --}}
@php
    $studentTourSteps = collect(config('student-tour.steps', []))->map(function (array $step) use ($student) {
        // Route names are sidebar links; anything else is already a selector.
        $selectors = collect($step['target'] ?? [])->map(
            fn (string $target) => str_starts_with($target, '#') || str_starts_with($target, '.') || str_starts_with($target, '[')
                ? $target
                : '[data-tour-target="'.$target.'"]'
        )->values()->all();

        return [
            'key' => $step['key'],
            'label' => str_replace(':name', $student->displayFirstName(), $step['label']),
            'body' => $step['body'],
            'target' => $selectors,
        ];
    })->values();

    // Where the last step sends them: the first thing they have not done.
    // Someone who joined a team through an invite before ever seeing this
    // does not need pointing at Team Management.
    $studentHasTeamForTour = $studentHasTeam ?? false;
@endphp

@include('partials.portal-tour', [
    'tourKey' => 'student',
    'tourSteps' => $studentTourSteps,
    'tourAutoOpen' => $student->needsPortalTour(),
    'tourSaveUrl' => route('student.tour.update'),
    'tourNextUrl' => route($studentHasTeamForTour ? 'student.experts' : 'student.team'),
    'tourNextLabel' => $studentHasTeamForTour ? 'Choose your experts' : 'Form your team',
])
