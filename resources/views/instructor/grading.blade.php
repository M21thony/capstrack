<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CapsTRACK — Expert Portal</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/expert.css') }}" />
@include('partials.skeleton-head', ['skReveal' => 'block'])
<style>
  /* One row per defense you sit on: what is left to grade reads first. */
  .gl{background:var(--bg2);border:1px solid var(--border);border-radius:12px;display:flex;flex-direction:column;max-height:calc(100vh - 120px);overflow:hidden}
  .gl-head{display:flex;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid var(--border);flex-wrap:wrap}
  .gl-head b{font-size:14px}
  .gl-head .sub{font-size:12.5px;color:var(--text3)}
  .gl-filter{margin-left:auto;display:flex;gap:4px}
  .gl-filter button{font:inherit;font-size:12px;font-weight:600;border:1px solid var(--border);background:var(--bg2);color:var(--text2);border-radius:999px;padding:3px 11px;cursor:pointer;min-height:28px}
  .gl-filter button[aria-pressed="true"]{background:var(--accent-light);border-color:var(--accent);color:var(--accent3)}
  .gl-scroll{overflow:auto;min-height:0}
  .gl table{width:100%;border-collapse:collapse}
  .gl th{position:sticky;top:0;background:var(--bg3);font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text3);text-align:left;padding:8px 14px;border-bottom:1px solid var(--border);white-space:nowrap}
  .gl td{padding:10px 14px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle}
  .gl tr:last-child td{border-bottom:0}
  .gl tbody tr:hover td{background:var(--bg3)}
  .gl-team{display:flex;align-items:center;gap:10px;min-width:0}
  .gl-av{flex:none;width:32px;height:32px;border-radius:50%;background:var(--navy-dark,#1e2028);color:#fff;font-size:11px;font-weight:700;display:grid;place-items:center}
  .gl-team b{display:block;font-size:13.5px}
  .gl-team small{display:block;color:var(--text3);font-size:12px;max-width:44ch;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .gl-muted{color:var(--text3);font-size:12px}
  .gl-outcome{font-size:12px;font-weight:600}
  .gl-outcome.is-pass{color:var(--green)}
  .gl-outcome.is-major{color:#c2410c}
  .gl-outcome.is-fail{color:var(--red)}
  .gl-num{font-variant-numeric:tabular-nums}
  .gl-go{font:inherit;font-size:12px;font-weight:600;border-radius:6px;padding:6px 12px;border:1px solid var(--border2);background:var(--bg2);color:var(--text);text-decoration:none;white-space:nowrap}
  .gl-go.primary{background:var(--accent);border-color:var(--accent);color:#18191f}
  .gl-empty{padding:40px 16px;text-align:center;color:var(--text3);font-size:13px}
</style>
</head>
<body>

@include('instructor.partials.sidebar', ['currentPage' => 'grading'])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Team Grading</div>
    <div class="topbar-actions">
      @include('instructor.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="team-progress-grid">
      <div class="sk" style="height:180px;border-radius:14px"></div>
      <div class="sk" style="height:180px;border-radius:14px"></div>
      <div class="sk" style="height:180px;border-radius:14px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content">

    @if (session('error'))
      <div class="card" style="border-color:var(--red);background:var(--red-bg);color:var(--red);padding:14px 18px;margin-bottom:16px;font-size:13px">
        {{ session('error') }}
      </div>
    @endif

    @if($rows->isEmpty())
      <div style="text-align:center;padding:64px 24px">
        <div style="margin-bottom:16px;opacity:0.3">
          <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
        <div style="font-size:18px;font-weight:600;color:var(--text2);margin-bottom:8px">No Teams Assigned Yet</div>
        <div style="font-size:13px;color:var(--text3);max-width:400px;margin:0 auto">You haven't been placed on a defense panel yet. Once an admin assigns you to a team's panel, it will show up here.</div>
      </div>
    @else
      @php $toGrade = $rows->where('status', '!=', 'submitted')->count(); @endphp
      <section class="gl" aria-label="Defenses to grade">
        <div class="gl-head">
          <b>Your defenses</b>
          <span class="sub">{{ $toGrade }} to grade · {{ $rows->count() - $toGrade }} submitted</span>
          <div class="gl-filter" role="group" aria-label="Show">
            <button type="button" data-show="all" aria-pressed="true">All</button>
            <button type="button" data-show="todo" aria-pressed="false">To grade</button>
            <button type="button" data-show="done" aria-pressed="false">Submitted</button>
          </div>
        </div>
        <div class="gl-scroll">
          <table>
            <thead><tr><th>Team</th><th>Defense</th><th>Your seat</th><th>When</th><th>Your grade</th><th></th></tr></thead>
            <tbody id="grading-tbody">
              @foreach($rows as $row)
                @php
                  $url = route('instructor.grading.show', $row->defense_id);
                  $done = $row->status === 'submitted';
                  $isRedefense = ($row->attempt ?? 1) > 1;
                  $attempt = $row->attempt ?? 1;
                  $ordinal = $attempt === 2 ? '2nd' : ($attempt === 3 ? '3rd' : $attempt.'th');
                @endphp
                <tr data-state="{{ $done ? 'done' : 'todo' }}">
                  <td>
                    <div class="gl-team">
                      <span class="gl-av" aria-hidden="true">{{ strtoupper(substr($row->team_name, 0, 2)) }}</span>
                      <span style="min-width:0"><b>{{ $row->team_name }}</b><small title="{{ $row->project_title }}">{{ $row->project_title ?? 'No title set' }}</small></span>
                    </div>
                  </td>
                  <td>
                    {{ $row->defense_type }}
                    <div style="margin-top:2px">
                      @if($isRedefense)
                        <span class="badge badge-amber" style="font-size:10px">Redefense · {{ $ordinal }} attempt</span>
                      @else
                        <span class="badge badge-gray" style="font-size:10px">Defense</span>
                      @endif
                    </div>
                  </td>
                  <td>{{ $row->panel_role }}</td>
                  <td class="gl-num">
                    {{ $row->schedule_date->format('M j, Y') }}
                    <div class="gl-muted">
                      @if($row->schedule_date->format('H:i') !== '00:00')
                        {{ $row->schedule_date->format('g:i A') }}–{{ ($row->end_date ?? $row->schedule_date->copy()->addHours(2))->format('g:i A') }} ·
                      @endif
                      {{ $row->venue }}
                    </div>
                  </td>
                  <td>
                    @if($done && ($row->abstained ?? false))
                      <span class="badge badge-gray" style="font-size:10px">Submitted · Abstained</span>
                    @elseif($done)
                      <span class="badge badge-green" style="font-size:10px">Submitted{{ $row->total_score !== null ? ' · '.rtrim(rtrim(number_format((float) $row->total_score, 2), '0'), '.') : '' }}</span>
                    @elseif($row->status === 'draft')
                      <span class="badge badge-amber" style="font-size:10px">Draft saved</span>
                    @else
                      <span class="badge badge-gray" style="font-size:10px">Not started</span>
                    @endif
                  </td>
                  <td style="text-align:right"><a class="gl-go {{ $done ? '' : 'primary' }}" href="{{ $url }}">{{ $done ? 'Open' : ($row->status === 'draft' ? 'Continue' : 'Grade') }}</a></td>
                </tr>
              @endforeach
              <tr class="gl-none" hidden><td colspan="6" class="gl-empty">Nothing here.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    @endif

  </div>
  </div>
</div>

@include('instructor.partials.expert-profile')
@include('instructor.partials.skeleton-toggle')

<script>
document.querySelectorAll('.gl-filter button').forEach(function (b) {
  b.addEventListener('click', function () {
    document.querySelectorAll('.gl-filter button').forEach(function (x) { x.setAttribute('aria-pressed', x === b); });
    var shown = 0;
    document.querySelectorAll('.gl tbody tr[data-state]').forEach(function (tr) {
      tr.hidden = b.dataset.show !== 'all' && tr.dataset.state !== b.dataset.show;
      if (!tr.hidden) shown++;
    });
    document.querySelector('.gl-none').hidden = shown > 0;
  });
});

/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');

function toggleSidebar() {
  if (!sidebarEl) return;
  var isOpen = sidebarEl.classList.contains('mobile-open');
  if (isOpen) { closeSidebar(); } else { openSidebar(); }
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
  document.body.style.overflow = '';
}
</script>
@include('partials.escape-closes-modals')
</body>
</html>