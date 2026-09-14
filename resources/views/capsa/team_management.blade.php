<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Team Management - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
  .tm-note{font-size:12px;color:var(--text2);line-height:1.6;margin-bottom:16px}

  /* State pills. Each colour means one thing: amber waits on someone,
     red is blocked, green is settled, grey is nothing yet. */
  .tm-pill{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;padding:3px 9px;border-radius:999px;text-transform:uppercase;letter-spacing:0.3px;white-space:nowrap}
  .tm-pill-green{background:var(--green-bg);color:var(--green)}
  .tm-pill-amber{background:var(--amber-bg);color:var(--amber)}
  .tm-pill-red{background:var(--red-bg);color:var(--red)}
  .tm-pill-grey{background:var(--bg4);color:var(--text3)}

  .tm-bar{width:96px;height:6px;border-radius:999px;background:var(--bg4);overflow:hidden}
  .tm-bar-fill{height:100%;border-radius:999px;background:var(--accent)}
  .tm-bar-label{font-size:11px;color:var(--text3);font-variant-numeric:tabular-nums;}

  .tm-roles{display:flex;gap:4px;flex-wrap:nowrap}
  .tm-score{display:flex;justify-content:space-between;gap:12px;font-size:11.5px;line-height:1.7;white-space:nowrap}
  .tm-score-stage{color:var(--text3)}
  .tm-score b{color:var(--text);font-variant-numeric:tabular-nums}
  .tm-score-wait{color:var(--amber);font-variant-numeric:tabular-nums}
  .tm-role{font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:5px;text-transform:uppercase;letter-spacing:0.3px}
  .tm-role-on{background:var(--accent-light);color:var(--accent3)}
  .tm-role-off{background:var(--bg4);color:var(--text3);opacity:.75}


  .tm-empty{text-align:center;padding:44px 20px;color:var(--text3);font-size:13px;line-height:1.6}
</style>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
</head>
<body>

@include('capsa._sidebar')
<div class="main">
  <div class="topbar">
    <div class="page-title">Team Management</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="card" style="padding:16px">
      <div class="sk" style="width:280px;height:34px;border-radius:8px;margin-bottom:16px"></div>
      @for ($i = 0; $i < 4; $i++)
        <div class="sk" style="height:44px;border-radius:8px;margin-bottom:10px"></div>
      @endfor
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">

    @if (session('status'))
      <div class="card" style="padding:12px 16px;margin-bottom:14px;border-left:3px solid var(--green);font-size:12.5px;color:var(--text2)">{!! session('status') !!}</div>
    @endif
    @if (session('error'))
      <div class="card" style="padding:12px 16px;margin-bottom:14px;border-left:3px solid var(--red);font-size:12.5px;color:var(--text2)">{!! session('error') !!}</div>
    @endif

    @php
      $tabs = [
        'teams' => 'All Teams',
        'pre_proposals' => 'Pre-Proposals',
        'verification' => 'Verification',
        'papers' => 'Document Review',
        'milestones' => 'Milestones',
        'leave_requests' => 'Leave Requests',
        'rules' => 'Team Rules',
      ];
      // Neither of these is a list of teams to filter by section.
      // Nor is Milestones, which has its own section tabs.
      $sectionFiltered = ! in_array($tab, ['leave_requests', 'rules', 'milestones'], true);
      $qs = fn (string $t) => ['tab' => $t] + ($selectedSection ? ['section' => $selectedSection] : []);
    @endphp

    <div class="tab-bar tab-bar-wide" style="margin-bottom:18px">
      @foreach ($tabs as $key => $label)
        <a href="{{ route('capsa.team_management', $qs($key)) }}" class="tab-btn {{ $tab === $key ? 'active' : '' }}">
          {{ $label }}
          @if (($counts[$key] ?? 0) > 0 && $key !== 'teams')
            <span class="tab-count">{{ $counts[$key] }}</span>
          @endif
        </a>
      @endforeach
    </div>

    @if ($sectionFiltered)
    <form method="get" action="{{ route('capsa.team_management') }}" style="display:flex;align-items:center;gap:8px;padding-bottom:16px">
      <input type="hidden" name="tab" value="{{ $tab }}"/>
      <label style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.4px">Section</label>
      <select name="section" onchange="this.form.submit()" style="padding:7px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);color:var(--text);font-size:12px;font-family:inherit">
        <option value="">All my sections</option>
        @foreach ($sectionOptions as $section)
          <option value="{{ $section }}" @selected($selectedSection === $section)>{{ $section }}</option>
        @endforeach
      </select>
      @if ($selectedSection)
        <a href="{{ route('capsa.team_management', ['tab' => $tab]) }}" style="font-size:12px;color:var(--text3);text-decoration:none">Clear</a>
      @endif

    </form>
    @endif

    @if ($allSections === [])
      <div class="card"><div class="tm-empty">
        You have no sections assigned yet, so there are no teams to manage.<br>
        An Admin assigns sections when they make someone a CaPSA.
      </div></div>
    @else

      @include('capsa.partials.team-management.' . $tab, $tabData)

    @endif

  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')
@include('capsa.partials.team-review-modal')
<div id="tm-toast" class="toast"></div>


@include('partials.escape-closes-modals')
</body>
</html>
