{{-- The CaPSA/TSA, Admin or Expert guided tour: that portal's steps from
     config/staff-tour.php, for partials/portal-tour. Included by the CaPSA/TSA
     sidebar (CaPSA/TSA and Admin) and the Expert sidebar.
       $tourKey   'capsa', 'admin' or 'expert'
       $expert    the signed-in account --}}
@php
    $staffTourSteps = collect(config('staff-tour.'.$tourKey, []))->map(function (array $step) use ($expert) {
        // A route name is that page's link in the sidebar; anything else is
        // already a selector.
        $selectors = collect($step['target'] ?? [])->map(function (string $target) {
            if (str_starts_with($target, '#') || str_starts_with($target, '.') || str_starts_with($target, '[')) {
                return $target;
            }

            // Matched on the path, not the whole address, so a link written
            // against another host (or behind a proxy) is still found.
            return \Illuminate\Support\Facades\Route::has($target)
                ? '.sidebar a.nav-item[href$="'.parse_url(route($target), PHP_URL_PATH).'"]'
                : null;
        })->filter()->values()->all();

        return [
            'key' => $step['key'],
            'label' => str_replace(':name', trim((string) $expert?->first_name) ?: 'there', $step['label']),
            'body' => $step['body'],
            'target' => $selectors,
        ];
    })
        // A step that points at something that is not a route here is dropped,
        // rather than shown as a card with nothing to point at.
        ->reject(fn (array $step) => $step['target'] === [] && ! in_array($step['key'], ['welcome', 'done'], true))
        ->values();
@endphp

@if ($expert)
  @include('partials.portal-tour', [
      'tourKey' => $tourKey,
      'tourSteps' => $staffTourSteps,
      'tourAutoOpen' => $expert->needsPortalTour(),
      'tourSaveUrl' => route('instructor.tour.update'),
  ])
@endif
