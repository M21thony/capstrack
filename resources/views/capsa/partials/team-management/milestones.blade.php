{{-- Milestones: the tracker.

     One section at a time. Each team is a row with a strip of blocks, one
     per milestone, filled as its requirements get done; its name opens a
     page of its own (breadcrumb back, no dropdowns). Signed papers waiting
     for the CaPSA's check open straight from the row. Manage milestones goes
     to the milestone pages. Drawn by resources/js/milestones/tracker.js from
     the JSON below - only the list inside it scrolls, never the page. --}}
<div class="ms-app" id="ms-tracker"
     data-mode="capsa"
     data-state="{{ json_encode($tracker, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
     data-urls="{{ json_encode([
         'section' => route('capsa.sections.progress', '__ID__'),
         'manage' => route('capsa.team_management', ['tab' => 'milestone_list']),
         'setup' => route('capsa.team_management.milestones.setup', '__ID__'),
     ], JSON_UNESCAPED_SLASHES) }}">
  <noscript><div class="tm-empty">The milestone tracker needs JavaScript turned on.</div></noscript>
</div>
@vite('resources/js/milestones/tracker.js')
