{{-- The Page Guide: the ? beside the bell, on every page of every portal.
     It opens a panel on the right with this page's quick guide, a tour that
     outlines the page's parts one at a time, links to related pages and a
     search across the pages of the same portal. The text is in
     config/page-guide.php, keyed by route name; a page with none there shows
     no ?. The tour is the portal tour engine (partials/portal-tour), given
     this page's steps. Included by both bells, and by the full-screen pages
     that have none: the grading sheet, and the annotator, whose React top
     bar draws the ? itself (pass 'pgOwnButton' => false). --}}
@php
    use Illuminate\Support\Facades\Route as PgRoute;

    $pgName = (string) PgRoute::currentRouteName();
    $pgAll = config('page-guide', []);
    $pg = $pgAll[$pgName] ?? null;
    // The portals this viewer can open: one staff account can be admin or
    // CaPSA and an adviser too, and its sidebar links both sets of pages.
    $pgViewer = str_starts_with($pgName, 'student.') ? null : auth('expert')->user();
    $pgPortals = $pgViewer ? array_keys(array_filter([
        'admin.' => (bool) $pgViewer->is_admin,
        'capsa.' => (bool) $pgViewer->is_capsa,
        'instructor.' => $pgViewer->roles->pluck('role_name')->intersect(['adviser', 'consultant', 'grammarian'])->isNotEmpty()
            || ! ($pgViewer->is_admin || $pgViewer->is_capsa),
    ])) : ['student.'];

    // A link only to a page the viewer can open that needs nothing filled in.
    $pgUrl = function (string $name) use ($pgPortals) {
        $route = PgRoute::getRoutes()->getByName($name);

        return $route && \Illuminate\Support\Str::startsWith($name, $pgPortals) && $route->parameterNames() === [] ? route($name) : null;
    };

    $pgTour = collect($pg['tour'] ?? [])->values()->map(fn ($s, $i) => [
        'key' => 'pg'.$i, 'label' => $s[0], 'body' => $s[1], 'target' => [$s[2]],
    ]);

    $pgPages = collect($pgAll)
        ->map(fn ($g, $name) => ['url' => ($g['search'] ?? true) ? $pgUrl($name) : null, 'title' => $g['title'], 'summary' => $g['summary'],
            'text' => mb_strtolower(implode(' ', [$g['title'], $g['summary'], ...collect($g['steps'] ?? [])->flatten()]))])
        ->filter(fn ($p) => $p['url'])
        // This page's own portal first; a page two portals share shows once.
        ->sortBy(fn ($p, $name) => str_starts_with($name, strtok($pgName, '.').'.') ? 0 : 1)
        ->unique('title')
        ->values();
@endphp

@if ($pg)
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/page-guide.css') }}">
@if ($pgOwnButton ?? true)
<button class="notif-btn pg-btn" id="pg-btn" type="button" aria-label="Page Guide for this page" aria-expanded="false" aria-controls="pg" aria-keyshortcuts="?">
  <span class="pg-q" aria-hidden="true">?</span>
</button>
@endif

<div class="pg-scrim" id="pg-scrim" hidden></div>
<aside class="pg" id="pg" role="dialog" aria-modal="true" aria-labelledby="pg-h" hidden>
  <div class="pg-head">
    <span class="pg-ico" aria-hidden="true">?</span>
    <div>
      <h2 class="pg-h" id="pg-h">Page Guide</h2>
      <p class="pg-sub">Guidance for the page you are on.</p>
    </div>
    <button type="button" class="pg-x" id="pg-x" aria-label="Close the Page Guide">
      <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
    </button>
  </div>

  <div class="pg-body">
    <label class="pg-search">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.6" y2="16.6"/></svg>
      <input type="search" id="pg-search" placeholder="Search the Page Guide…" aria-label="Search the Page Guide" autocomplete="off">
    </label>

    <div class="pg-here" id="pg-here">
      <section class="pg-card pg-cur">
        <div class="pg-eyebrow">Current page</div>
        <h3 class="pg-title" id="pg-title">{{ $pg['title'] }}</h3>
        <p>{{ $pg['summary'] }}</p>
      </section>

      @if (! empty($pg['steps']))
        <section class="pg-card">
          <h3 class="pg-sec">Quick guide</h3>
          <ol class="pg-steps">
            @foreach ($pg['steps'] as $i => $step)
              <li><span class="pg-n">{{ $i + 1 }}</span><span><b>{{ $step[0] }}</b> {{ $step[1] }}</span></li>
            @endforeach
          </ol>
        </section>
      @endif

      @if ($pgTour->isNotEmpty())
        <section class="pg-card pg-tourc" id="pg-tourc">
          <div class="pg-tour-row">
            <span class="pg-play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 4.5v15l12-7.5z"/></svg></span>
            <div>
              <h3 class="pg-tour-h">Interactive tour</h3>
              <p>A guided look at this page, one part at a time.</p>
            </div>
          </div>
          <button type="button" class="pg-go" id="pg-go"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5v15l12-7.5z"/></svg> Start tour</button>
        </section>
      @endif

      @php $pgRelated = collect($pg['related'] ?? [])->map(fn ($text, $name) => [$pgUrl($name), $text])->filter(fn ($l) => $l[0]); @endphp
      @if ($pgRelated->isNotEmpty())
        <section>
          <h3 class="pg-sec">Related topics</h3>
          <div class="pg-rel">
            @foreach ($pgRelated as [$url, $text])
              <a href="{{ $url }}">{{ $text }} <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
            @endforeach
          </div>
        </section>
      @endif

      @if (! empty($pg['tip']))
        <div class="pg-tip">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>
          <span><b>Tip:</b> {{ $pg['tip'] }}</span>
        </div>
      @endif
    </div>

    <div class="pg-hits" id="pg-hits" hidden>
      @foreach ($pgPages as $p)
        <a class="pg-hit" href="{{ $p['url'] }}" data-text="{{ $p['text'] }}"><b>{{ $p['title'] }}</b><span>{{ $p['summary'] }}</span></a>
      @endforeach
      <p class="pg-none" id="pg-none" hidden>No page matches that.</p>
    </div>
  </div>
</aside>

<script type="application/json" id="pg-tour">@json($pgTour)</script>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var panel = document.getElementById('pg');
  var scrim = document.getElementById('pg-scrim');
  if (!panel) return;
  // Looked up each time: on the annotator the ? is drawn later, by React.
  function btn() { return document.getElementById('pg-btn'); }
  // Out of the topbar: a page that slides in with a transform would
  // otherwise hold the fixed panel inside itself.
  document.body.appendChild(scrim);
  document.body.appendChild(panel);

  var search = document.getElementById('pg-search');
  var here = document.getElementById('pg-here');
  var hits = document.getElementById('pg-hits');
  var none = document.getElementById('pg-none');
  var steps = JSON.parse(document.getElementById('pg-tour').textContent);
  var tourCard = document.getElementById('pg-tourc');

  function isOpen() { return !panel.hidden; }

  function open() {
    // Only a page that carries the tour engine can run its tour, and only
    // while something it outlines is showing (Documents with a paper open has none).
    if (tourCard) tourCard.hidden = typeof window.openPortalTour !== 'function' || !steps.some(function (s) {
      return Array.prototype.some.call(document.querySelectorAll(s.target[0]), function (el) { return el.getClientRects().length; });
    });
    panel.hidden = false;
    scrim.hidden = false;
    if (btn()) btn().setAttribute('aria-expanded', 'true');
    document.getElementById('pg-x').focus({ preventScroll: true });
  }

  function close(keepFocus) {
    panel.hidden = true;
    scrim.hidden = true;
    if (btn()) btn().setAttribute('aria-expanded', 'false');
    if (!keepFocus && btn()) btn().focus({ preventScroll: true });
  }

  document.addEventListener('click', function (e) {
    if (!e.target.closest || !e.target.closest('#pg-btn')) return;
    e.stopPropagation();
    isOpen() ? close() : open();
  }, true);
  scrim.addEventListener('click', function () { close(); });
  document.getElementById('pg-x').addEventListener('click', function () { close(); });

  var go = document.getElementById('pg-go');
  if (go) go.addEventListener('click', function () {
    close(true);
    window.openPortalTour(steps);
  });

  search.addEventListener('input', function () {
    var words = search.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
    here.hidden = words.length > 0;
    hits.hidden = !words.length;
    var shown = 0;
    hits.querySelectorAll('.pg-hit').forEach(function (a) {
      var match = words.every(function (w) { return a.dataset.text.indexOf(w) !== -1; });
      a.hidden = !match;
      if (match) shown++;
    });
    none.hidden = shown > 0;
  });

  // On window, so a page's own keys go first: Defenses already had ? for its shortcuts.
  window.addEventListener('keydown', function (e) {
    if (isOpen() && e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (isOpen() && e.key === 'Tab') {
      var f = Array.prototype.filter.call(panel.querySelectorAll('button, a[href], input'), function (el) { return el.offsetParent !== null; });
      var first = f[0], end = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); end.focus(); }
      else if (!e.shiftKey && document.activeElement === end) { e.preventDefault(); first.focus(); }
      else if (!panel.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
      return;
    }
    // ? opens it, unless someone is typing, or the tour is running.
    if (e.key !== '?' || e.ctrlKey || e.metaKey || e.altKey || e.defaultPrevented) return;
    var t = e.target;
    if (t.closest && t.closest('input, textarea, select, [contenteditable=""], [contenteditable="true"]')) return;
    if (document.body.classList.contains('tour-open') || isOpen()) return;
    e.preventDefault();
    open();
  });
})();
</script>
@endif
