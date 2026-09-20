{{--
  One title's marks: whether it is the team's final title, what she decided,
  and how close its nearest match is. The same three on the list row and on
  the title's own header.

  No pills here by the adviser's rule - inside .ppr and .tpg the stylesheet
  strips the background, leaving a coloured word with an icon.
--}}
@if ($final && (int) $title['id'] === (int) $final)
  <span class="pill p-gold"><x-tm-icon n="star" s="12" />Final title</span>
@endif
@if ($title['status'] === 'approved')
  <span class="pill p-green"><x-tm-icon n="check" s="12" />Recommended</span>
@elseif ($title['status'] === 'rejected')
  <span class="pill p-red"><x-tm-icon n="x" s="12" />Rejected</span>
@endif
<x-tm-sim :score="$title['duplication']['top_score'] ?? null" />
