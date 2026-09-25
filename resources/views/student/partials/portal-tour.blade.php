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

    // Where the last step sends them. Always the team first: experts, titles
    // and documents all hang off it, and a team already formed is still
    // where its members, invites and settings live.
@endphp

@include('partials.portal-tour', [
    'tourKey' => 'student',
    'tourSteps' => $studentTourSteps,
    'tourAutoOpen' => $student->needsPortalTour(),
    'tourSaveUrl' => route('student.tour.update'),
    'tourNextUrl' => route('student.team'),
    'tourNextLabel' => 'Create your team in Team Management',
])
