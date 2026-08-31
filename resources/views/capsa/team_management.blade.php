<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Team Management - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
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
  .tm-bar-label{font-size:11px;color:var(--text3);font-family:'Space Mono',monospace}

  .tm-roles{display:flex;gap:4px;flex-wrap:nowrap}
  .tm-role{font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:5px;text-transform:uppercase;letter-spacing:0.3px}
  .tm-role-on{background:var(--accent-light);color:var(--accent3)}
  .tm-role-off{background:var(--bg4);color:var(--text3);opacity:.75}


  /* ── Milestones: the section checklist grid ────────────────────────
     Deliverables across, teams down. The nineteen are a fixed list and the
     teams are not, so the growing side is the one that scrolls. */
  .ms-intro{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px;flex-wrap:wrap}
  .ms-intro-title{font-size:15px;font-weight:600;color:var(--text);margin-bottom:3px}
  .ms-intro-note{font-size:12px;color:var(--text3);line-height:1.6;max-width:82ch}

  /* Shown only when the grid falls back to one phase at a time. */
  .ms-phases{display:none;padding:0 12px;border-bottom:1px solid var(--border);overflow-x:auto}
  .ms-ptab{border:none;background:none;font-family:inherit;font-size:12px;font-weight:600;color:var(--text2);
           padding:9px 12px;border-bottom:2px solid transparent;cursor:pointer;white-space:nowrap}
  .ms-ptab:hover{color:var(--text)}
  .ms-ptab.on{color:var(--accent3);border-bottom-color:var(--accent)}
  .ms-pc{font-family:'Space Mono',monospace;font-size:9.5px;color:var(--text3);margin-left:5px}
  .ms-ptab.on .ms-pc{color:var(--accent3)}
  .ms-hide{display:none}

  /* The bar is hidden; scrolling still works. */
  .ms-scroll{overflow:auto;max-height:620px;scrollbar-width:none;-ms-overflow-style:none}
  .ms-scroll::-webkit-scrollbar{width:0;height:0;display:none}

  .ms-grid{border-collapse:separate;border-spacing:0;font-size:12px;table-layout:fixed;width:100%}
  .ms-grid th,.ms-grid td{padding:0}

  .ms-grid thead th{position:sticky;background:var(--bg3);z-index:3;text-align:center}
  .ms-phaserow th{top:0;height:26px;padding:0 6px;font-size:11px;font-weight:600;color:var(--text);
                  border-bottom:1px solid var(--border);white-space:nowrap}
  .ms-namerow th{top:26px;height:56px;vertical-align:bottom;padding:0 3px 7px;border-bottom:1px solid var(--border2)}
  /* Short, because the band above already says which chapter it is - which is
     what lets nineteen labels sit flat rather than on their side. */
  .ms-lab{font-size:11px;color:var(--text);font-weight:500;line-height:1.3;text-align:center;
          word-break:normal;overflow-wrap:break-word;hyphens:auto}
  /* Just enough to separate one phase from the next; at --bg4 whole groups
     read as grey blocks. */
  .ms-band{background:rgba(125,130,145,0.045)}
  .ms-grid thead th.ms-band{background:rgba(125,130,145,0.055)}
  .ms-grp{border-left:1px solid var(--border2)}

  .ms-kdot{display:inline-block;width:5px;height:5px;border-radius:50%;margin-top:5px}
  .ms-namerow .ms-kdot{display:block;margin:5px auto 0}
  .ms-k-form,.ms-k-custom{background:var(--text3)}
  .ms-k-document{background:#3b82f6}
  .ms-k-approval{background:#9333ea}
  .ms-k-defense{background:var(--accent)}

  .ms-grid th.ms-team,.ms-grid td.ms-team{position:sticky;left:0;z-index:2;background:var(--bg2);
    text-align:left;width:158px;border-right:1px solid var(--border2);padding:9px 12px}
  .ms-grid thead th.ms-team{z-index:5;background:var(--bg3);vertical-align:bottom}
  .ms-phaserow th.ms-team{top:0;height:26px}
  .ms-namerow th.ms-team{top:26px}
  .ms-th{font-size:11px;font-weight:600;color:var(--text3);letter-spacing:.3px;text-transform:uppercase}
  .ms-count{font-family:'Space Mono',monospace;font-size:10.5px;color:var(--text3);white-space:nowrap}

  .ms-tname{font-size:13.5px;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .ms-tprog{display:flex;align-items:center;gap:6px;margin-top:4px}
  .ms-tbar{flex:1;height:4px;border-radius:2px;background:var(--bg4);overflow:hidden}
  .ms-tbar i{display:block;height:100%;background:var(--green)}

  .ms-grid tbody tr td{border-bottom:1px solid var(--border)}
  .ms-sec td{background:var(--bg3);padding:7px 12px;position:sticky;top:82px;z-index:1;
             border-bottom:1px solid var(--border2)}
  .ms-sec td.ms-team{background:var(--bg3);z-index:3;font-size:12px;font-weight:600;color:var(--text2)}
  .ms-secmeta{font-size:11.5px;color:var(--text3);font-weight:400;margin-left:7px}

  .ms-cell{text-align:center;padding:5px 3px;width:70px}
  .ms-grid tbody tr:hover td{background:var(--accent-light)}
  .ms-na{color:var(--text3);opacity:.45}
  .ms-none{padding:9px 14px;font-size:12px;color:var(--text3)}

  .ms-mk{width:21px;height:21px;border-radius:5px;display:inline-flex;align-items:center;justify-content:center;
         border:1.5px solid var(--text3);background:var(--bg2);padding:0;cursor:pointer}
  .ms-mk svg{width:11px;height:11px}
  .ms-mk.on{background:var(--green);border-color:var(--green);color:#fff}
  .ms-mk.wait{border-color:var(--amber);color:var(--amber);background:var(--amber-bg)}
  .ms-mk.over{border-color:var(--red);color:var(--red);background:var(--red-bg)}
  /* Closes itself, so it is not a button and does not invite a press. */
  .ms-mk.auto{border-style:dashed;cursor:default}
  button.ms-mk:hover{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light)}

  .ms-key{display:flex;gap:14px;flex-wrap:wrap;align-items:center;padding:11px 15px;
          border-top:1px solid var(--border);background:var(--bg3);font-size:11.5px;color:var(--text3)}
  .ms-key > span{display:flex;align-items:center;gap:6px}
  .ms-key .ms-mk{width:15px;height:15px;cursor:default}
  .ms-key .ms-mk svg{width:9px;height:9px}
  .ms-kinds{margin-left:auto;gap:5px}
  .ms-kinds .ms-kdot{margin:0 2px 0 8px}

  .ms-label{display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;
            letter-spacing:.4px;margin:14px 0 5px}
  .ms-hint{font-size:11px;color:var(--text3);margin-top:5px}

  @media (max-width:1279px){
    .ms-phases{display:flex}
    /* The tab already names the phase, so the band would say it twice. Safe
       because each header row carries its own Team cell. */
    .ms-phaserow{display:none}
    .ms-namerow th{top:0}
    .ms-sec td{top:56px}
    .ms-grid th.ms-team,.ms-grid td.ms-team{width:142px;padding:8px 10px}
    .ms-tname{font-size:12.5px}
    .ms-secmeta{display:block;margin-left:0;margin-top:1px}
    .ms-cell{width:auto}
  }

  .tm-empty{text-align:center;padding:44px 20px;color:var(--text3);font-size:13px;line-height:1.6}
</style>
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
        'papers' => 'Paper Format Check',
        'milestones' => 'Milestones',
      ];
      $qs = fn (string $t) => ['tab' => $t] + ($selectedSection ? ['section' => $selectedSection] : []);
    @endphp

    <div class="tab-bar tab-bar-wide" style="margin-bottom:18px">
      @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.team_management', $qs($key)) }}" class="tab-btn {{ $tab === $key ? 'active' : '' }}">
          {{ $label }}
          @if (($counts[$key] ?? 0) > 0 && $key !== 'teams')
            <span class="tab-count">{{ $counts[$key] }}</span>
          @endif
        </a>
      @endforeach
    </div>

    <form method="get" action="{{ route('admin.team_management') }}" style="display:flex;align-items:center;gap:8px;padding-bottom:16px">
      <input type="hidden" name="tab" value="{{ $tab }}"/>
      <label style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.4px">Section</label>
      <select name="section" onchange="this.form.submit()" style="padding:7px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);color:var(--text);font-size:12px;font-family:inherit">
        <option value="">All my sections</option>
        @foreach ($sectionOptions as $section)
          <option value="{{ $section }}" @selected($selectedSection === $section)>{{ $section }}</option>
        @endforeach
      </select>
      @if ($selectedSection)
        <a href="{{ route('admin.team_management', ['tab' => $tab]) }}" style="font-size:12px;color:var(--text3);text-decoration:none">Clear</a>
      @endif
    </form>

    @if ($allSections === [])
      <div class="card"><div class="tm-empty">
        You have no sections assigned yet, so there are no teams to manage.<br>
        An Admin assigns sections when they make someone a CaPSA.
      </div></div>
    @else

      @include('capsa.partials.team-management.' . $tab)

    @endif

  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')
@include('capsa.partials.team-review-modal')
</body>
</html>
