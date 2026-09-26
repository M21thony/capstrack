{{--
  Moving between pages, made smooth - on a phone, a tablet and a computer.

  - Page to page, the screen cross-fades instead of blanking and redrawing:
    the browser's own cross-document view transitions. The sidebar, the top
    bar and the tab bar are the same on both pages, so they read as holding
    still while the page under them changes. Nothing is given a
    view-transition-name: that would lift it onto a layer of its own, and
    the notification list hanging from the top bar would then pass under the
    page's sticky table headings. A browser without view transitions ignores
    all of it and navigates as before.
  - The pages the sidebar and the tab bar lead to start loading as a pointer
    rests on the link, or a finger lands on it, so they open almost at once.
  - A tap on a phone lands straight away: no wait to see whether it was the
    first half of a double-tap zoom.

  Included in <head> by partials/skeleton-head, and again by
  partials/mobile-tabbar for the few pages without it; @once keeps it single.
--}}
@once
<style>
  @view-transition { navigation: auto; }

  ::view-transition-old(root),
  ::view-transition-new(root) { animation-duration: .18s; animation-timing-function: ease-out; }

  a, button, [role="button"], label, summary, input, select, textarea { touch-action: manipulation; }

  @media (prefers-reduced-motion: reduce) {
    ::view-transition-group(*),
    ::view-transition-old(*),
    ::view-transition-new(*) { animation: none !important; }
  }
</style>
<script type="speculationrules">
{
  "prefetch": [{
    "where": { "and": [
      { "selector_matches": ".sidebar a[href], .mtab a[href]" },
      { "not": { "selector_matches": "a[href^='#'], a[href^='javascript:'], a[target], a[download], [data-no-prefetch]" } },
      { "not": { "href_matches": "/*logout*" } }
    ] },
    "eagerness": "moderate"
  }]
}
</script>
@endonce
