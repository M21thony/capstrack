{{--
  Moving between pages, made smooth - on a phone, a tablet and a computer.

  - No fade from page to page. A cross-fade (view transitions) was tried and
    read as the whole screen flashing on every click; the browser already
    keeps the old page up until the new one is drawn.
  - The pages the sidebar and the tab bar lead to start loading as a pointer
    rests on the link, or a finger lands on it, so they open almost at once.
  - A tap on a phone lands straight away: no wait to see whether it was the
    first half of a double-tap zoom.

  Included in <head> by partials/skeleton-head, and again by
  partials/mobile-tabbar for the few pages without it; @once keeps it single.
--}}
@once
<style>
  a, button, [role="button"], label, summary, input, select, textarea { touch-action: manipulation; }
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
