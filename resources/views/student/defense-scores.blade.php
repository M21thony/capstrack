<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Panel Scores - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
<style>
/* Each panelist's copy of the rubric, side by side: one column per panelist,
   so the team sees where the three agreed and where they did not. */
.ds-tabs{display:flex;gap:2px;border-bottom:1px solid var(--border);margin-bottom:12px;overflow-x:auto}
.ds-tab{font:inherit;border:0;background:none;padding:9px 14px 8px;font-size:13px;font-weight:600;color:var(--text3);border-bottom:2px solid transparent;cursor:pointer;white-space:nowrap}
.ds-tab[aria-selected="true"]{color:var(--text);border-bottom-color:var(--accent)}
.ds-band{display:flex;align-items:center;gap:12px 26px;flex-wrap:wrap;background:var(--accent-light);border:1px solid #e3d08f;border-radius:11px;padding:10px 16px;margin-bottom:12px}
.ds-band b{font-size:20px;color:var(--text)}
.ds-band small{display:block;font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:var(--accent3);font-weight:700}
.ds-band p{margin:0;font-size:12.5px;color:var(--text2);max-width:60ch}
/* The decision's colour says how serious it is. */
.ds-band.is-pass{background:var(--green-bg);border-color:rgba(22,163,74,.25)}
.ds-band.is-pass .ds-decision b,.ds-band.is-pass .ds-decision small{color:var(--green)}
.ds-band.is-major{background:#fff7ed;border-color:#fed7aa}
.ds-band.is-major .ds-decision b,.ds-band.is-major .ds-decision small{color:#c2410c}
.ds-band.is-fail{background:var(--red-bg);border-color:#fecaca}
.ds-band.is-fail .ds-decision b,.ds-band.is-fail .ds-decision small{color:var(--red)}
.ds-avg{margin-left:auto;text-align:right}
.ds-avg b{font-variant-numeric:tabular-nums}
.ds-grid{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:12px;align-items:start}
.ds-sheet{background:var(--bg2);border:1px solid var(--border);border-radius:11px;overflow:auto;max-height:calc(100vh - 290px)}
.ds-sheet table{width:100%;border-collapse:collapse;font-size:13px}
.ds-sheet th{position:sticky;top:0;background:var(--bg3);text-align:right;padding:8px 12px;border-bottom:1px solid var(--border);font-size:12px;color:var(--text2);white-space:nowrap}
.ds-sheet th:first-child{text-align:left}
.ds-sheet th small{display:block;font-weight:500;color:var(--text3);font-size:11px}
.ds-sheet td{padding:8px 12px;border-bottom:1px solid var(--border);text-align:right;font-variant-numeric:tabular-nums}
.ds-sheet td:first-child{text-align:left;font-variant-numeric:normal}
.ds-cat td{background:var(--bg3);font-weight:700;color:var(--text)}
.ds-total td{font-weight:700;background:var(--accent-light);border-bottom:0}
.ds-max{color:var(--text3)}
.ds-remarks{display:grid;gap:10px;max-height:calc(100vh - 290px);overflow:auto}
.ds-abstained{font-style:italic;color:var(--text3);font-weight:500}
.ds-card{background:var(--bg2);border:1px solid var(--border);border-radius:11px;padding:12px 14px;font-size:13px;line-height:1.5}
.ds-card b{display:block;font-size:13px}
.ds-card small{color:var(--text3);font-size:11.5px}
.ds-card p{margin:6px 0 0;color:var(--text2);white-space:pre-line}
@media (max-width:900px){.ds-grid{grid-template-columns:1fr}.ds-sheet,.ds-remarks{max-height:none}}
</style>
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title"><a href="{{ route('student.progress') }}" style="color:var(--text3);text-decoration:none">Our milestones</a> <span style="color:var(--text3)">›</span> Panel scores</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
@if ($defenses->isEmpty())
  <div class="empty-state" style="text-align:center;padding:60px 20px">
    <div style="font-size:15px;font-weight:600;color:var(--text2);margin-bottom:6px">No results yet</div>
    <div style="font-size:12px;color:var(--text3)">Once your panel records its decision for a defense, each panelist's scores show here.</div>
  </div>
@else
  @php $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp

  <div class="ds-tabs" role="tablist" aria-label="Defenses">
    @foreach ($defenses as $i => $d)
      <button type="button" class="ds-tab" role="tab" aria-selected="{{ $loop->last ? 'true' : 'false' }}" aria-controls="ds-{{ $d->defense_id }}"
              onclick="dsShow({{ $d->defense_id }}, this)">{{ $d->defense_type }}{{ $d->attempt > 1 ? ' · Redefense' : '' }}</button>
    @endforeach
  </div>

  @foreach ($defenses as $d)
    @php
      $rubric = ($d->grades->firstWhere('status', 'submitted') ?? $d->grades->first())?->rubric;
      $criteria = $rubric ? $rubric->criteria->sortBy('sort_order')->groupBy(fn ($c) => $c->category ?? 'General') : collect();
      $seats = $d->panels->sortBy(fn ($p) => $p->panel_role === 'Panel Chair' ? 0 : 1)->values();
      $gradeOf = $d->grades->keyBy('expert_id');
      $abstained = fn ($seat) => (bool) $gradeOf->get($seat->expert_id)?->isAbstained();
      $scoreOf = fn ($seat, $c) => $gradeOf->get($seat->expert_id)?->scores[(string) $c->criterion_id] ?? null;
    @endphp
    <section id="ds-{{ $d->defense_id }}" class="ds-panel" role="tabpanel" @unless ($loop->last) hidden @endunless>
      <div class="ds-band is-{{ \App\Support\DefenseOutcome::tone($d->outcome) }}">
        <div class="ds-decision">
          <small>Panel decision</small>
          <b>{{ $d->outcomeLabel() }}</b>
          <p>{{ $d->outcome_remarks ?: \App\Support\DefenseOutcome::description($d->outcome, $d->defense_type) }}</p>
        </div>
        <div class="ds-avg">
          <small>Panel average</small>
          <b>{{ $fmt($d->panelAverage()) }}</b>@if ($rubric)<span style="color:var(--text3)"> / {{ $fmt($rubric->total_points) }}</span>@endif
        </div>
      </div>

      <div class="ds-grid">
        <div class="ds-sheet">
          @if (! $rubric)
            <div style="padding:24px;text-align:center;color:var(--text3)">The panel's scores are not in yet.</div>
          @else
            <table>
              <thead>
                <tr>
                  <th scope="col">Criterion</th>
                  <th scope="col">Max</th>
                  @foreach ($seats as $seat)
                    <th scope="col">{{ $seat->expert?->first_name }} {{ $seat->expert?->last_name }}<small>{{ $seat->panel_role }}</small></th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @foreach ($criteria as $category => $items)
                  <tr class="ds-cat">
                    <td>{{ $category }}</td>
                    <td class="ds-max">{{ $fmt($items->first()->category_max_points ?? $items->sum('max_points')) }}</td>
                    @foreach ($seats as $seat)
                      <td>{{ $abstained($seat) ? '—' : $fmt($items->sum(fn ($c) => (float) $scoreOf($seat, $c))) }}</td>
                    @endforeach
                  </tr>
                  @foreach ($items as $c)
                    <tr>
                      <td>{{ $c->criterion_name }}</td>
                      <td class="ds-max">{{ $fmt($c->max_points) }}</td>
                      @foreach ($seats as $seat)
                        <td>{{ $fmt($scoreOf($seat, $c)) }}</td>
                      @endforeach
                    </tr>
                  @endforeach
                @endforeach
                <tr class="ds-total">
                  <td>Total</td>
                  <td>{{ $fmt($rubric->total_points) }}</td>
                  @foreach ($seats as $seat)
                    <td>@if ($abstained($seat))<span class="ds-abstained">Abstained</span>@else{{ $fmt($gradeOf->get($seat->expert_id)?->total_score) }}@endif</td>
                  @endforeach
                </tr>
              </tbody>
            </table>
          @endif
        </div>

        <div class="ds-remarks" aria-label="Panelists' remarks">
          @foreach ($seats as $seat)
            <div class="ds-card">
              <b>{{ $seat->expert?->first_name }} {{ $seat->expert?->last_name }}</b>
              <small>{{ $seat->panel_role }}</small>
              {{-- An abstention's reason stays with the panel. --}}
              @if ($abstained($seat))
                <p class="ds-abstained">Abstained from grading this defense. Not counted in the panel average.</p>
              @else
                <p>{{ $gradeOf->get($seat->expert_id)?->remarks ?: 'No remarks.' }}</p>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </section>
  @endforeach
@endif
  </div>
</div>

<script>
function dsShow(id, tab) {
  document.querySelectorAll('.ds-tab').forEach(function (t) { t.setAttribute('aria-selected', t === tab); });
  document.querySelectorAll('.ds-panel').forEach(function (p) { p.hidden = p.id !== 'ds-' + id; });
}
</script>
@include('partials.escape-closes-modals')
<script>
{{-- The hamburger button and the overlay both called these and nothing
     defined them, so the menu would not open on a phone. Same two lines as
     every other student page. --}}
function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
function closeSidebar() { document.body.classList.remove('sidebar-open'); }
</script>
</body>
</html>
