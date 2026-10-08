<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Profile &mdash; CapsTRACK</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/expert.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'block'])
  @include('partials.render-whole')
<style nonce="{{ Vite::cspNonce() }}">
  /* The profile is a page of its own now. It used to be a 300px drawer
     beside the sidebar, too narrow for a list per role and a slot count
     per program, and on a phone it covered most of the screen anyway.
     Left: what the expert may change. Right: how students see them. */
  /* #real-content is the scroller here; .content scrolling too would pin
     the sticky aside and save bar to a box that never moves. */
  .pp.content{padding-bottom:0;overflow:visible}
  .pp-layout{max-width:1400px;display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:18px;align-items:start}
  .pp-aside{grid-column:2;grid-row:1;position:sticky;top:0}
  .pp-main{grid-column:1;grid-row:1;min-width:0;display:flex;flex-direction:column;gap:18px}

  /* One sheet, each row a label column and a control column. */
  .pp-sheet{background:var(--bg2);border:1px solid var(--border);border-radius:14px}
  .pp-row{display:grid;grid-template-columns:170px minmax(0,1fr);gap:22px;padding:16px 20px}
  .pp-row + .pp-row{border-top:1px solid var(--bg4)}
  .pp-row h2{margin:0 0 3px;font-size:13.5px;font-weight:700;color:var(--text)}
  .pp-row-label p{margin:0;font-size:12px;line-height:1.45;color:var(--text2)}
  .pp-control{min-width:0}
  .pp-staff-note{margin:0;font-size:12.5px;color:var(--text2)}
  .pp-staff-note a{color:var(--accent3);font-weight:600}

  .pp-degree{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px}
  .pp-select{height:36px;width:100%;max-width:240px;border:1px solid var(--border2);border-radius:8px;padding:0 10px;
             font:inherit;font-size:13px;color:var(--text);background:var(--bg2)}
  .pp-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
  .pp-chair{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600}
  .pp-chair svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
  .pp-chair.yes{color:var(--green)}
  .pp-chair.no{color:var(--amber)}

  .pp-progs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
  .pp-prog{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:2px 5px 2px 10px;
           border:1px solid var(--border);border-radius:9px;background:var(--bg2);color:var(--text3)}
  .pp-prog.on{border-color:rgba(201,168,44,.55);background:var(--accent-light);color:var(--text)}
  .pp-prog label{display:flex;align-items:center;gap:8px;flex:1;min-height:34px;cursor:pointer;font-size:13px;font-weight:700}
  .pp-prog input{width:15px;height:15px;margin:0;accent-color:var(--accent)}
  .pp-stepper{display:flex;align-items:center;gap:4px}
  .pp-step{width:26px;height:26px;border-radius:7px;border:1px solid var(--border);background:var(--bg2);color:var(--text);
           font:inherit;font-size:14px;line-height:1;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;padding:0}
  .pp-step:hover:not(:disabled){background:var(--bg4)}
  .pp-step:disabled{opacity:.4;cursor:not-allowed}
  .pp-count{min-width:24px;text-align:center;font-size:13px;font-weight:700;font-variant-numeric:tabular-nums}
  .pp-total{font-size:12px;color:var(--text2);margin-top:8px}
  .pp-total b{color:var(--text)}

  .pp-sides{display:flex;gap:2px;padding:3px;border-radius:9px;background:var(--bg4);margin-bottom:10px}
  .pp-side{flex:1 1 0;min-height:30px;border:0;border-radius:7px;background:transparent;color:var(--text2);
           font:inherit;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;
           justify-content:center;gap:6px;padding:0 8px;white-space:nowrap}
  .pp-side:hover{color:var(--text)}
  .pp-side[aria-pressed="true"]{background:var(--bg2);color:var(--text);box-shadow:0 1px 2px rgba(24,25,31,.12)}
  .pp-side-n{color:var(--text3);font-weight:500;font-variant-numeric:tabular-nums}
  .pp-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;background:var(--text3)}
  .pp-dot.adviser{background:var(--accent)}
  .pp-dot.consultant{background:var(--cyan)}
  .pp-dot.grammarian{background:var(--green)}
  .pp-dot.panel{background:var(--purple)}

  .pp-chips{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:8px}
  .pp-chip{display:inline-flex;align-items:center;gap:3px;min-height:26px;padding:0 4px 0 10px;border-radius:7px;
           font-size:12px;font-weight:600;background:var(--accent-light);color:var(--accent3);border:1px solid rgba(201,168,44,.4)}
  .pp-chip.consultant{background:var(--cyan-bg);color:#0e6a84;border-color:rgba(8,145,178,.28)}
  .pp-chip.grammarian{background:var(--green-bg);color:#15803d;border-color:rgba(22,163,74,.28)}
  .pp-chip.panel{background:var(--purple-bg);color:#5b21b6;border-color:rgba(124,58,237,.25)}
  .pp-chip button{width:18px;height:18px;border:0;border-radius:50%;background:transparent;color:inherit;cursor:pointer;
                  display:inline-flex;align-items:center;justify-content:center;padding:0}
  .pp-chip button:hover{background:rgba(0,0,0,.08)}
  .pp-chip svg{width:10px;height:10px;fill:none;stroke:currentColor;stroke-width:2.6;stroke-linecap:round}
  .pp-none{font-size:12px;color:var(--amber);padding:4px 0}

  .pp-search{display:flex;align-items:center;gap:7px;height:34px;border:1px solid var(--border2);border-radius:8px;padding:0 10px;background:var(--bg2)}
  .pp-search:focus-within{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
  .pp-search svg{width:13px;height:13px;fill:none;stroke:var(--text3);stroke-width:2;flex-shrink:0}
  .pp-search input{flex:1;min-width:0;border:0;outline:none;background:transparent;font:inherit;font-size:12.5px;color:var(--text)}
  .pp-search input::placeholder{color:var(--text3)}
  .pp-groups{display:flex;flex-wrap:wrap;gap:4px;margin:10px 0 8px}
  .pp-group{font:inherit;font-size:11.5px;font-weight:600;padding:3px 9px;border-radius:7px;border:1px solid transparent;
            background:transparent;color:var(--text3);cursor:pointer}
  .pp-group:hover{background:var(--bg4);color:var(--text2)}
  .pp-group[aria-pressed="true"]{background:var(--bg4);color:var(--text);border-color:var(--border2)}
  .pp-pick{display:flex;flex-wrap:wrap;gap:5px}
  .pp-opt{font:inherit;font-size:11.5px;min-height:24px;padding:0 9px;border-radius:6px;border:1px dashed var(--border2);
          background:var(--bg2);color:var(--text2);cursor:pointer;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .pp-opt:hover{border-color:var(--accent);background:var(--accent-light);color:var(--text)}
  .pp-opt.own{border-color:var(--accent);color:var(--text);font-weight:600}
  .pp-hint{font-size:11px;color:var(--text3);margin-top:8px;line-height:1.45}

  /* The notification switches are a shared card; here it takes the full width. */
  /* Notifications: one switch per channel, grouped by what they are about. */
  .pp-switch{appearance:none;-webkit-appearance:none;width:32px;height:18px;border-radius:9px;background:var(--border2);position:relative;cursor:pointer;margin:0;flex:0 0 auto;transition:background .15s}
  .pp-switch::before{content:"";position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .15s}
  .pp-switch:checked{background:var(--accent)}
  .pp-switch:checked::before{transform:translateX(14px)}
  .pp-switch:disabled{opacity:.4;cursor:not-allowed}
  @media (prefers-reduced-motion: reduce){.pp-switch,.pp-switch::before{transition:none}}
  .pp-master{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border-radius:10px;background:var(--bg3);border:1px solid var(--border)}
  .pp-master label{display:block;font-size:13px;font-weight:700;color:var(--text);cursor:pointer}
  .pp-master small{display:block;font-size:11.5px;color:var(--text3);margin-top:1px}
  .pp-allow{display:inline-flex;align-items:center;min-height:22px;padding:0 8px;margin-left:6px;border:1px solid var(--accent);border-radius:6px;background:var(--accent-light);color:var(--accent3);font:inherit;font-size:11px;font-weight:700;cursor:pointer}
  .pp-allow:hover{background:var(--accent);color:#18191f}
  .pp-allow[hidden]{display:none}
  .pp-ngrid{margin-top:8px}
  .pp-ncols,.pp-nitem{display:grid;grid-template-columns:minmax(0,1fr) 56px 56px;align-items:center;gap:8px}
  .pp-ncols{font-size:11px;font-weight:600;color:var(--text3);text-align:center;padding-bottom:2px}
  .pp-ngroup{font-size:11.5px;font-weight:700;color:var(--text2);padding:9px 0 3px;border-top:1px solid var(--bg4)}
  .pp-ncols + .pp-ngroup{border-top:0}
  .pp-nitem{min-height:32px;padding:3px 0;font-size:12.5px;line-height:1.35;color:var(--text)}
  .pp-ncell{display:flex;justify-content:center}
  .pp-nnone{color:var(--text3);opacity:.6}
  .pp-ngrid.off .pp-nitem,.pp-ngrid.off .pp-ngroup,.pp-ngrid.off .pp-ncols{opacity:.45}

  /* What students see. */
  .pp-cap{display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin:0 2px 6px;font-size:12px;font-weight:600;color:var(--text2)}
  .pp-cap span{font-size:11px;font-weight:400;color:var(--text3)}
  .pp-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;overflow:hidden}
  .pp-head{background:var(--navy-dark);padding:14px 16px;display:flex;align-items:center;gap:12px}
  .pp-avatar{width:42px;height:42px;flex-shrink:0;border-radius:50%;object-fit:cover;display:block;
             box-shadow:0 0 0 2px var(--navy-dark),0 0 0 4px rgba(201,168,44,.6)}
  .pp-head h2{margin:0;font-size:15px;font-weight:700;color:#fff;line-height:1.25;overflow-wrap:anywhere}
  .pp-email{font-size:11.5px;color:rgba(255,255,255,.62);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .pp-body{padding:12px 16px 14px;display:flex;flex-direction:column;gap:12px}
  .pp-roles{display:flex;flex-wrap:wrap;gap:5px}
  .pp-role{padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;background:var(--bg4);color:var(--text2);border:1px solid var(--border)}
  .pp-role.adviser{background:var(--accent-light);color:var(--accent3);border-color:rgba(201,168,44,.4)}
  .pp-role.consultant{background:var(--cyan-bg);color:#0e6a84;border-color:rgba(8,145,178,.28)}
  .pp-role.grammarian{background:var(--green-bg);color:#15803d;border-color:rgba(22,163,74,.28)}
  .pp-role.capstone_adviser{background:var(--blue-bg);color:var(--blue);border-color:rgba(29,78,216,.2)}
  .pp-lock{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text3);margin-top:6px}
  .pp-lock svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2;flex-shrink:0}
  .pp-slot-top{display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:6px}
  .pp-slot-top b{font-size:12.5px;color:var(--text)}
  .pp-slot-top span{font-size:11px;color:var(--text3);font-variant-numeric:tabular-nums}
  .pp-meter{display:flex;gap:4px}
  .pp-meter span{flex:1 1 0;height:8px;border-radius:2px;background:var(--accent-light);box-shadow:inset 0 0 0 1.5px var(--accent)}
  .pp-meter span.taken{background:var(--navy-dark);box-shadow:none}
  .pp-meter span.over{background:var(--amber);box-shadow:none}
  .pp-warn{font-size:11px;color:var(--amber);margin-top:6px;line-height:1.4}
  .pp-suggest{font-size:11px;color:var(--text3);margin-top:6px;line-height:1.45}
  .pp-suggest b{color:var(--text2)}
  .pp-sub{font-size:11.5px;font-weight:600;color:var(--text3);margin-bottom:5px}
  .pp-tags{display:flex;flex-wrap:wrap;gap:4px}
  .pp-tag{padding:2px 8px;border-radius:5px;background:var(--bg4);font-size:11px;color:var(--text2)}
  .pp-rating{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text2);padding-top:10px;border-top:1px solid var(--bg4)}
  .pp-rating svg{width:13px;height:13px;fill:var(--accent);flex-shrink:0}
  .pp-rating b{color:var(--text)}

  /* Only there while something is unsaved, or just saved. */
  .pp-save{position:sticky;bottom:0;padding:14px 0 16px;display:none;max-width:1400px}
  /* As wide as what it says, so the notification toasts in the corner never cover Save. */
  .pp-save.on{display:block}
  .pp-savebar{display:flex;width:fit-content;max-width:100%;align-items:center;gap:16px;padding:8px 10px 8px 16px;border-radius:12px;background:var(--navy-dark);
              color:#fff;box-shadow:0 8px 28px rgba(15,17,23,.25)}
  .pp-save-msg{display:flex;align-items:center;gap:9px;min-width:0;font-size:12.5px;font-weight:600}
  .pp-save-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;background:var(--accent)}
  .pp-save.saved .pp-save-dot{background:#22c55e}
  .pp-save.failed .pp-save-dot{background:#f87171}
  .pp-save-btns{display:flex;gap:8px}
  .pp-save.saved .pp-save-btns{display:none}
  .pp-btn{min-height:34px;padding:0 14px;border-radius:8px;font:inherit;font-size:12.5px;font-weight:600;cursor:pointer}
  .pp-btn-ghost{border:1px solid rgba(255,255,255,.22);background:transparent;color:#fff}
  .pp-btn-ghost:hover{background:rgba(255,255,255,.08)}
  .pp-btn-save{border:0;background:var(--accent);color:#18191f;font-weight:700;padding:0 16px}
  .pp-btn-save:hover{background:var(--accent2)}
  .pp-btn:disabled{opacity:.6;cursor:default}

  .pp :focus-visible{outline:2px solid var(--accent3);outline-offset:2px}

  @media (max-width:1100px){
    .pp-layout{grid-template-columns:minmax(0,1fr)}
    .pp-aside,.pp-main{grid-column:auto;grid-row:auto;position:static}
  }
  /* Clear of the floating tab bar along the bottom of a phone. */
  @media (max-width:768px){
    .pp-save{bottom:calc(76px + env(safe-area-inset-bottom, 0px))}
  }
  @media (max-width:640px){
    .pp-layout,.pp-main{gap:10px}
    .pp-row{grid-template-columns:minmax(0,1fr);gap:8px;padding:14px}
    .pp-row-label p{font-size:11.5px}
    .pp-select{max-width:none}
    .pp-progs{gap:6px}
    .pp-cap,.pp-aside .pp-lock,.pp-aside-areas{display:none}
    .pp-head{padding:12px 14px;gap:10px}
    .pp-avatar{width:36px;height:36px}
    .pp-head h2{font-size:14px}
    .pp-body{padding:10px 14px 12px;gap:10px}
    .pp-side{font-size:11.5px;gap:4px;padding:0 4px}
    .pp-ncols,.pp-nitem{grid-template-columns:minmax(0,1fr) 46px 46px;gap:6px}
    .pp-savebar{width:auto;flex-wrap:wrap;gap:10px}
    .pp-save-msg{flex:1 1 100%}
    .pp-save-btns{width:100%}
    .pp-save-btns .pp-btn{flex:1}
  }
</style>
</head>
<body>

@include('instructor.partials.sidebar', ['currentPage' => $currentPage ?? ''])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

@php
  $degreeLevels = collect(\App\Models\CapstoneExpert::DEGREE_TITLES)
      ->flatMap(fn ($titles, $level) => collect($titles)->mapWithKeys(fn ($title) => [$title => $level]));
  $firstSide = array_key_first($researchSides) ?? 'panel';
@endphp

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Profile</div>
    <div class="topbar-actions">
      @include('instructor.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div style="display:grid;grid-template-columns:minmax(0,1fr);gap:18px;max-width:1160px">
      <div class="card">
        <div class="sk" style="width:140px;height:14px;border-radius:4px;margin-bottom:12px"></div>
        <div class="sk" style="width:100%;height:36px;border-radius:8px;margin-bottom:12px"></div>
        <div class="sk" style="width:100%;height:36px;border-radius:8px;margin-bottom:12px"></div>
        <div class="sk" style="width:70%;height:36px;border-radius:8px"></div>
      </div>
    </div>
  </div>

  <div id="real-content" style="display:none">
  <div class="content pp">
    <div class="pp-layout">

      {{-- What students see. It follows the form as it is edited, so the
           effect of a change is visible before it is saved. --}}
      <aside class="pp-aside" aria-labelledby="pp-who">
        <div class="pp-cap">What students see <span>Updates as you edit</span></div>
        <div class="pp-card">
          <div class="pp-head">
            <img class="pp-avatar" src="{{ $expert->avatarUrl() }}" alt="" onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.jpg') }}'">
            <div style="min-width:0">
              <h2 id="pp-who">{{ $expert->first_name }} {{ $expert->last_name }}<span id="pp-who-degree">{{ $expert->degreeText() ? ', '.$expert->degreeText() : '' }}</span></h2>
              <div class="pp-email">{{ $expert->email }}</div>
            </div>
          </div>
          <div class="pp-body">
            <div>
              <div class="pp-roles">
                @forelse ($expert->roles as $role)
                  <span class="pp-role {{ $role->role_name }}">{{ $role->label }}</span>
                @empty
                  <span class="pp-role">No role yet</span>
                @endforelse
              </div>
              <div class="pp-lock">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                <span>Roles are set by the administrator</span>
              </div>
              @if ($expert->program)
                <div class="pp-lock">Program: {{ $expert->program }}</div>
              @endif
            </div>

            <div>
              <div class="pp-slot-top"><b id="pp-slot-head"></b><span id="pp-slot-sub"></span></div>
              <div class="pp-meter" id="pp-meter" role="img"></div>
              <div class="pp-warn" id="pp-slot-warn" hidden></div>
              @if ($slotSuggestion)
                <div class="pp-suggest">Suggestion: <b>{{ $slotSuggestion }}</b>. From how quickly you review your teams' documents. It's up to you.</div>
              @endif
            </div>

            <div class="pp-aside-areas">
              <div class="pp-sub" id="pp-preview-label"></div>
              <div class="pp-tags" id="pp-preview-areas"></div>
            </div>

            <div class="pp-rating">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
              @if ($ratingAverage !== null)
                <b>{{ number_format($ratingAverage, 1) }}</b> from {{ $ratingCount }} {{ $ratingCount === 1 ? 'team leader rating' : 'team leader ratings' }}
              @else
                No ratings from team leaders yet
              @endif
            </div>
          </div>
        </div>
      </aside>

      <div class="pp-main">
        @if ($staffProfileRoute)
          <p class="pp-staff-note">Your name, photo and assigned sections are on your <a href="{{ route($staffProfileRoute) }}">{{ $expert->is_admin ? 'Admin' : 'CaPSA' }} profile</a>.</p>
        @endif
        <div class="pp-sheet">

          <section class="pp-row" aria-labelledby="pp-degree-h">
            <div class="pp-row-label">
              <h2 id="pp-degree-h">Degree</h2>
              <p>Shown after your name. A Panel Chair needs a master's degree or higher.</p>
            </div>
            <div class="pp-control pp-degree">
              <label for="pp-degree" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Highest degree</label>
              @include('partials.degree-select', [
                  'id' => 'pp-degree',
                  'selected' => $expert->degree_title,
                  'attrs' => 'class="pp-select"',
              ])
              <div class="pp-chair" id="pp-chair" aria-live="polite"></div>
            </div>
          </section>

          <section class="pp-row" aria-labelledby="pp-prog-h">
            <div class="pp-row-label">
              <h2 id="pp-prog-h">Programs and team slots</h2>
              <p>Tick each program you take teams from, then set how many.</p>
            </div>
            <div class="pp-control">
              <div class="pp-progs">
                @foreach (\App\Models\CapstoneExpert::PROGRAMS as $program)
                  <div class="pp-prog" data-program="{{ $program }}">
                    <label>
                      <input type="checkbox" id="pp-prog-{{ $program }}">
                      {{ $program }}
                    </label>
                    <div class="pp-stepper">
                      <button type="button" class="pp-step" data-step="-1" aria-label="Fewer {{ $program }} teams">&minus;</button>
                      <output class="pp-count" aria-live="polite"></output>
                      <button type="button" class="pp-step" data-step="1" aria-label="More {{ $program }} teams">+</button>
                    </div>
                  </div>
                @endforeach
              </div>
              <div class="pp-total" id="pp-total"></div>
            </div>
          </section>

          <section class="pp-row" aria-labelledby="pp-areas-h">
            <div class="pp-row-label">
              <h2 id="pp-areas-h">Research areas</h2>
              <p>Kept for each role you hold and for defense panels. Students see the list for the role they are looking for.</p>
            </div>
            <div class="pp-control">
              <div class="pp-sides" role="group" aria-label="Research areas as">
                @foreach ($researchSides as $side => $label)
                  <button type="button" class="pp-side" data-side="{{ $side }}" aria-pressed="{{ $side === $firstSide ? 'true' : 'false' }}">
                    <span class="pp-dot {{ $side }}"></span>{{ $label }}<span class="pp-side-n" data-side-count="{{ $side }}">{{ count($researchAreas[$side] ?? []) }}</span>
                  </button>
                @endforeach
              </div>
              <div class="pp-chips" id="pp-chips"></div>
              <div class="pp-search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <label for="pp-area-input" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Search research areas</label>
                <input id="pp-area-input" type="text" autocomplete="off" placeholder="Search, or type your own research area">
              </div>
              <div class="pp-groups" id="pp-groups" role="group" aria-label="Research area groups"></div>
              <div class="pp-pick" id="pp-pick"></div>
              <div class="pp-hint">Click an area to add it, and remove one with its &times; above. Not on the list? Type it and press Enter.</div>
            </div>
          </section>

          <section class="pp-row" id="notifications" aria-labelledby="pp-notif-h">
            <div class="pp-row-label">
              <h2 id="pp-notif-h">Notifications</h2>
              <p>How CapsTrack tells you when a team needs you. Desktop pops up in the corner of your screen; email goes to your Google address.</p>
            </div>
            <div class="pp-control">
              <div class="pp-master">
                <span>
                  <label for="pp-n-all">Notify me at all</label>
                  <small><span id="pp-perm-text"></span><button type="button" class="pp-allow" id="pp-allow" hidden>Allow</button></small>
                </span>
                <input class="pp-switch" type="checkbox" id="pp-n-all" data-key="all">
              </div>
              <script nonce="{{ Vite::cspNonce() }}">
              // Said as the box is drawn, so it never grows in afterwards.
              (function () {
                var wrap = document.getElementById('notif-wrap');
                var supported = !!(wrap && wrap.dataset.vapid && window.isSecureContext
                  && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window);
                var state = supported ? Notification.permission : 'unsupported';
                var text = document.getElementById('pp-perm-text');
                text.textContent = {
                  granted: 'Desktop pop-ups are on in this browser.',
                  denied: 'This browser blocks pop-ups from CapsTrack. Allow them in its site settings.',
                  'default': 'Desktop pop-ups are not set up in this browser yet.'
                }[state] || 'Desktop pop-ups need CapsTrack opened over https://.';
                document.getElementById('pp-allow').hidden = state !== 'default';
              })();
              </script>
              <div class="pp-ngrid" id="pp-ngrid">
                <div class="pp-ncols" aria-hidden="true"><span></span><span>Desktop</span><span>Email</span></div>
                @foreach ($notificationGroups as $group => $switches)
                  <div class="pp-ngroup">{{ $group }}</div>
                  @foreach ($switches as $switch)
                    <div class="pp-nitem">
                      <span>{{ $switch['label'] }}</span>
                      @foreach (['web' => 'Desktop', 'email' => 'Email'] as $channel => $channelName)
                        <span class="pp-ncell">
                          @if ($switch[$channel])
                            <input class="pp-switch" type="checkbox" id="pp-n-{{ $channel }}-{{ $switch['type'] }}" data-key="{{ $channel }}.{{ $switch['type'] }}" aria-label="{{ $channelName }}: {{ $switch['label'] }}">
                          @else
                            <span class="pp-nnone" title="{{ $channel === 'web' ? 'Shows in the bell only' : 'No email is sent for this' }}">&mdash;</span>
                          @endif
                        </span>
                      @endforeach
                    </div>
                  @endforeach
                @endforeach
              </div>
            </div>
          </section>

        </div>
      </div>
    </div>

    <div class="pp-save" id="pp-save">
      <div class="pp-savebar">
        <div class="pp-save-msg" role="status"><span class="pp-save-dot"></span><span id="pp-save-text">You have unsaved changes</span></div>
        <div class="pp-save-btns">
          <button type="button" class="pp-btn pp-btn-ghost" id="pp-discard">Discard</button>
          <button type="button" class="pp-btn pp-btn-save" id="pp-save-btn">Save changes</button>
        </div>
      </div>
    </div>
  </div>
  </div>
</div>

@include('instructor.partials.skeleton-toggle')
<script src="/js/capstrack-push.js" defer></script>

<script nonce="{{ Vite::cspNonce() }}">
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

(function () {
  var PROGRAMS = @json(\App\Models\CapstoneExpert::PROGRAMS);
  var SIDES = @json((object) $researchSides);
  var DEGREE_LEVELS = @json($degreeLevels);
  var TEAMS_HANDLED = {{ (int) $teamsHandled }};
  // A limit saved before slots were set per program: one number, no programs ticked.
  var FLAT_LIMIT = {{ $programSlots ? 0 : (int) ($expert->max_teams ?? 0) }};
  // Sourced from App\Support\SkillCatalog, the same groups the student
  // expert list filters by. A starting point, not a whitelist.
  var GROUPS = Object.entries(@json(\App\Support\SkillCatalog::groups())).map(function (g) { return { name: g[0], items: g[1] }; });
  var KNOWN = GROUPS.reduce(function (all, g) { return all.concat(g.items); }, []);

  var saved = {
    degree: @json($expert->degree_title ?? ''),
    programs: PROGRAMS.reduce(function (o, p) { o[p] = 0; return o; }, {}),
    areas: @json((object) $researchAreas),
    notifs: @json((object) $notificationPrefs)
  };
  Object.assign(saved.programs, @json((object) $programSlots));
  Object.keys(SIDES).forEach(function (side) { if (!saved.areas[side]) saved.areas[side] = []; });

  function copy(x) { return JSON.parse(JSON.stringify(x)); }
  function norm(v) { return String(v).trim().toLowerCase(); }

  var state = copy(saved);
  // What a program's stepper goes back to when it is ticked again.
  var lastCount = PROGRAMS.reduce(function (o, p) { o[p] = state.programs[p] || 1; return o; }, {});
  var side = @json($firstSide);
  var group = null;
  var justSaved = false;

  var el = function (id) { return document.getElementById(id); };
  var input = el('pp-area-input');

  function total() { return PROGRAMS.reduce(function (t, p) { return t + (state.programs[p] || 0); }, 0); }
  function dirty() { return JSON.stringify(state) !== JSON.stringify(saved); }
  function areas() { return state.areas[side] || (state.areas[side] = []); }
  function has(name) { return areas().some(function (a) { return norm(a) === norm(name); }); }

  function button(cls, text, onClick, pressed) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = cls;
    b.textContent = text;
    if (pressed !== undefined) b.setAttribute('aria-pressed', pressed ? 'true' : 'false');
    b.addEventListener('click', onClick);
    return b;
  }

  function renderDegree() {
    var level = DEGREE_LEVELS[state.degree] || null;
    var chair = el('pp-chair');
    var canChair = level === 'master' || level === 'doctorate';
    chair.className = 'pp-chair ' + (canChair ? 'yes' : 'no');
    chair.innerHTML = canChair
      ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>You can chair a defense panel'
      : '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>You can sit on a panel, but not chair it';
    el('pp-who-degree').textContent = state.degree ? ', ' + state.degree : '';
  }

  function renderPrograms() {
    document.querySelectorAll('.pp-prog').forEach(function (row) {
      var p = row.dataset.program, n = state.programs[p] || 0, on = n > 0;
      row.classList.toggle('on', on);
      row.querySelector('input').checked = on;
      row.querySelector('.pp-count').textContent = n;
      row.querySelector('[data-step="-1"]').disabled = !on || n <= 1;
      row.querySelector('[data-step="1"]').disabled = !on || n >= 20;
    });
    var t = total();
    el('pp-total').innerHTML = t ? 'Up to <b>' + t + (t === 1 ? ' team' : ' teams') + '</b> in all' : 'Tick at least one program to take teams.';

    if (!t && FLAT_LIMIT && !dirty()) t = FLAT_LIMIT;
    var open = Math.max(t - TEAMS_HANDLED, 0), meter = el('pp-meter');
    el('pp-slot-head').textContent = open === 0 ? 'No team slots open' : (open === 1 ? '1 team slot open' : open + ' team slots open');
    el('pp-slot-sub').textContent = t < TEAMS_HANDLED ? 'Limit ' + t + ', handling ' + TEAMS_HANDLED : TEAMS_HANDLED + ' of ' + t + ' taken';
    meter.setAttribute('aria-label', TEAMS_HANDLED + ' of ' + t + ' team slots taken');
    meter.innerHTML = '';
    for (var i = 0; i < Math.max(t, TEAMS_HANDLED); i++) {
      var s = document.createElement('span');
      if (i < TEAMS_HANDLED) s.className = i < t ? 'taken' : 'over';
      meter.appendChild(s);
    }
    var warn = el('pp-slot-warn');
    warn.hidden = t >= TEAMS_HANDLED;
    warn.textContent = 'You already handle ' + TEAMS_HANDLED + (TEAMS_HANDLED === 1 ? ' team' : ' teams') + '. No new team is assigned to you until you are below your limit.';
  }

  function renderAreas() {
    document.querySelectorAll('.pp-side').forEach(function (b) {
      b.setAttribute('aria-pressed', b.dataset.side === side ? 'true' : 'false');
      b.querySelector('[data-side-count]').textContent = (state.areas[b.dataset.side] || []).length;
    });

    var list = areas(), label = (SIDES[side] || '').toString();
    var chips = el('pp-chips');
    chips.innerHTML = '';
    if (!list.length) {
      var none = document.createElement('div');
      none.className = 'pp-none';
      none.textContent = 'None yet, so no team is matched to you as ' + label + '.';
      chips.appendChild(none);
    }
    list.forEach(function (name) {
      var chip = document.createElement('span');
      chip.className = 'pp-chip ' + side;
      chip.appendChild(document.createTextNode(name));
      var x = document.createElement('button');
      x.type = 'button';
      x.setAttribute('aria-label', 'Remove ' + name);
      x.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>';
      x.addEventListener('click', function () { toggle(name); });
      chip.appendChild(x);
      chips.appendChild(chip);
    });

    el('pp-preview-label').textContent = 'Research areas as ' + label;
    var preview = el('pp-preview-areas');
    preview.innerHTML = '';
    if (!list.length) {
      preview.innerHTML = '<span class="pp-warn" style="margin:0">None yet</span>';
    }
    list.forEach(function (name) {
      var tag = document.createElement('span');
      tag.className = 'pp-tag';
      tag.textContent = name;
      preview.appendChild(tag);
    });

    renderPicker();
  }

  function renderPicker() {
    var q = norm(input.value), groupsEl = el('pp-groups'), pick = el('pp-pick');
    groupsEl.innerHTML = '';
    pick.innerHTML = '';

    var typed = input.value.trim();
    if (typed && !has(typed) && !KNOWN.some(function (k) { return norm(k) === norm(typed); })) {
      pick.appendChild(button('pp-opt own', '+ Add “' + typed + '”', function () { addTyped(); }));
    }

    // What is taken already shows as chips above, so the list only offers the rest.
    function opt(name) {
      if (has(name)) return;
      pick.appendChild(button('pp-opt', '+ ' + name, function () { toggle(name); }));
    }
    function none(text) {
      var note = document.createElement('span');
      note.className = 'pp-hint';
      note.style.margin = '0';
      note.textContent = text;
      pick.appendChild(note);
    }

    // A search crosses every group, so the matches show together.
    if (q) {
      KNOWN.filter(function (k) { return norm(k).indexOf(q) >= 0; }).forEach(opt);
      if (!pick.children.length) none('Nothing else matches. Press Enter to add it as your own.');
      groupsEl.hidden = true;
      return;
    }

    groupsEl.hidden = false;
    if (!GROUPS.some(function (g) { return g.name === group; })) group = GROUPS.length ? GROUPS[0].name : null;
    GROUPS.forEach(function (g) {
      var n = g.items.filter(has).length;
      groupsEl.appendChild(button('pp-group', g.name + (n ? ' (' + n + ')' : ''), function () { group = g.name; renderPicker(); }, g.name === group));
    });
    var active = GROUPS.find(function (g) { return g.name === group; });
    (active ? active.items : []).forEach(opt);
    if (active && !pick.children.length) none('You have every area in this group.');
  }

  function toggle(name) {
    var list = areas(), at = list.findIndex(function (a) { return norm(a) === norm(name); });
    if (at >= 0) { list.splice(at, 1); } else { list.push(name); }
    changed();
    renderAreas();
  }

  function addTyped() {
    var val = input.value.trim();
    if (!val) return;
    if (!has(val)) {
      // A known area typed in other casing is stored as the list spells it.
      var known = KNOWN.find(function (k) { return norm(k) === norm(val); });
      areas().push(known || val);
    }
    input.value = '';
    changed();
    renderAreas();
    input.focus();
  }

  function renderSaveBar(text, cls) {
    var bar = el('pp-save');
    var show = dirty() || justSaved || cls === 'failed';
    bar.classList.toggle('on', show);
    bar.classList.toggle('saved', !dirty() && justSaved && cls !== 'failed');
    bar.classList.toggle('failed', cls === 'failed');
    el('pp-save-text').textContent = text || (dirty() ? 'You have unsaved changes' : 'Changes saved');
  }

  function changed() { justSaved = false; renderSaveBar(); }

  function renderNotifs() {
    el('pp-ngrid').classList.toggle('off', !state.notifs.all);
    document.querySelectorAll('.pp-switch[data-key]').forEach(function (sw) {
      sw.checked = !!state.notifs[sw.dataset.key];
      if (sw.dataset.key !== 'all') sw.disabled = !state.notifs.all;
    });
  }

  function renderAll() { renderDegree(); renderPrograms(); renderAreas(); renderNotifs(); renderSaveBar(); }

  document.querySelectorAll('.pp-switch[data-key]').forEach(function (sw) {
    sw.addEventListener('change', function () { state.notifs[sw.dataset.key] = sw.checked; changed(); renderNotifs(); });
  });

  el('pp-allow').addEventListener('click', function () {
    var allow = this;
    if (!window.CapsTrackPush) return;
    allow.disabled = true;
    window.CapsTrackPush.enable().then(function (permission) {
      allow.hidden = true;
      el('pp-perm-text').textContent = permission === 'granted'
        ? 'Desktop pop-ups are on in this browser.'
        : 'This browser blocks pop-ups from CapsTrack. Allow them in its site settings.';
    });
  });

  el('pp-degree').addEventListener('change', function (e) { state.degree = e.target.value; changed(); renderDegree(); });

  document.querySelectorAll('.pp-prog').forEach(function (row) {
    var p = row.dataset.program;
    row.querySelector('input').addEventListener('change', function (e) {
      if (state.programs[p] > 0) lastCount[p] = state.programs[p];
      state.programs[p] = e.target.checked ? (lastCount[p] || 1) : 0;
      changed();
      renderPrograms();
    });
    row.querySelectorAll('.pp-step').forEach(function (b) {
      b.addEventListener('click', function () {
        state.programs[p] = Math.min(20, Math.max(1, (state.programs[p] || 0) + Number(b.dataset.step)));
        lastCount[p] = state.programs[p];
        changed();
        renderPrograms();
      });
    });
  });

  document.querySelectorAll('.pp-side').forEach(function (b) {
    b.addEventListener('click', function () { side = b.dataset.side; renderAreas(); });
  });

  input.addEventListener('input', renderPicker);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addTyped(); } });

  el('pp-discard').addEventListener('click', function () {
    state = copy(saved);
    el('pp-degree').value = state.degree;
    justSaved = false;
    renderAll();
  });

  function part(obj, keys) { return JSON.stringify(keys.map(function (k) { return obj[k]; })); }

  async function put(url, body) {
    var res = await fetch(url, {
      method: 'PUT',
      headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    var json = await res.json().catch(function () { return {}; });
    if (!res.ok || json.success === false) throw new Error(json.message || 'Could not save. Try again.');
    return json;
  }

  // The profile and the notification switches are saved where each always
  // has been; only the part that changed is sent.
  el('pp-save-btn').addEventListener('click', async function () {
    var btn = this;
    var profileChanged = part(state, ['degree', 'programs', 'areas']) !== part(saved, ['degree', 'programs', 'areas']);
    var notifsChanged = JSON.stringify(state.notifs) !== JSON.stringify(saved.notifs);
    if (profileChanged && total() < 1) {
      renderSaveBar('Tick at least one program and how many teams you take from it.', 'failed');
      return;
    }
    btn.disabled = true;
    try {
      if (profileChanged) {
        var json = await put(@json(route('instructor.profile.update')), { program_slots: state.programs, expertise_by_side: state.areas, degree: state.degree || null });
        if (json.expertise_by_side) {
          Object.keys(SIDES).forEach(function (s) { state.areas[s] = json.expertise_by_side[s] || []; });
        }
        saved.degree = state.degree;
        saved.programs = copy(state.programs);
        saved.areas = copy(state.areas);
      }
      if (notifsChanged) {
        var on = {};
        Object.keys(state.notifs).forEach(function (k) { if (k !== 'all') on[k] = state.notifs[k]; });
        await put(@json(route('instructor.notifications.preferences')), { all: state.notifs.all, on: on });
        saved.notifs = copy(state.notifs);
      }
      justSaved = true;
      renderAll();
    } catch (e) {
      renderSaveBar(e.message && e.message.indexOf('Failed to fetch') < 0 ? e.message : 'Could not reach CapsTrack. Check your connection and try again.', 'failed');
    } finally {
      btn.disabled = false;
    }
  });

  window.addEventListener('beforeunload', function (e) { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });

  renderAll();
})();
</script>

@include('partials.escape-closes-modals')
</body>
</html>
