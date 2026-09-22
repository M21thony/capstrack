{{-- Milestones: the tracker.

     One section at a time. Teams down the table, each opening a page of its
     own (breadcrumb back, no dropdowns) with every milestone and
     deliverable on show; the signed papers waiting for the
     CaPSA's check are what she acts on here. Edit milestones opens the
     checklist editor. Drawn by resources/js/milestones/tracker.js from the
     JSON below - only the lists inside it scroll, never the page. --}}
<div class="ms-app" id="ms-tracker"
     data-mode="capsa"
     data-state="{{ json_encode($tracker, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
     data-urls="{{ json_encode([
         'section' => route('capsa.sections.progress', '__ID__'),
         'checklists' => route('capsa.checklists.index'),
         'checklist' => route('capsa.checklists.show', '__ID__'),
         'order' => route('capsa.checklists.order.update', '__ID__'),
         'milestonesStore' => route('capsa.checklists.milestones.store', '__ID__'),
         'milestone' => route('capsa.checklist_milestones.update', '__ID__'),
         'deliverablesStore' => route('capsa.checklist_milestones.deliverables.store', '__ID__'),
         'deliverables' => route('capsa.deliverables.update_many'),
         'deliverable' => route('capsa.deliverables.update', '__ID__'),
         'removals' => route('capsa.removals.update'),
     ], JSON_UNESCAPED_SLASHES) }}">
  <noscript><div class="tm-empty">The milestone tracker needs JavaScript turned on.</div></noscript>
</div>
@vite('resources/js/milestones/tracker.js')
