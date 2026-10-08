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
  /* The settings an expert changes, most-changed first, with the email
     switches beside them. The research area suggestions open only while
     the box is in use, so the page is not a wall of options. */
  /* #real-content is the scroller here; .content scrolling too would pin
     the sticky card and save bar to a box that never moves. */
  .pp.content{padding-bottom:0;overflow:visible}
  .pp-page{max-width:1140px}
  .pp-cols{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;align-items:start}
  .pp-aside{position:sticky;top:0}

  /* Who this is, as a header: the photo, the name the degree follows, and the
     roles the administrator gave them. */
  .pp-id{display:flex;align-items:center;gap:14px 18px;flex-wrap:wrap;margin:0 0 16px;padding:16px 20px;background:var(--bg2);border:1px solid var(--border);border-radius:14px}
  .pp-id-photo{width:54px;height:54px;border-radius:50%;object-fit:cover;flex-shrink:0;box-shadow:0 0 0 2px var(--bg2),0 0 0 4px rgba(201,168,44,.65)}
  .pp-id-main{min-width:0;flex:1 1 220px}
  .pp-id-main h1{margin:0;font-size:19px;font-weight:700;letter-spacing:-.01em;color:var(--text);line-height:1.2;overflow-wrap:anywhere}
  .pp-id-mail{font-size:12.5px;color:var(--text3);margin-top:3px;overflow-wrap:anywhere}
  .pp-id-roles{display:flex;flex-direction:column;align-items:flex-end;gap:6px}
  .pp-id-chips{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px}
  .pp-role{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:var(--bg4);color:var(--text2);border:1px solid var(--border)}
  .pp-role.adviser{background:var(--accent-light);color:var(--accent3);border-color:rgba(201,168,44,.45)}
  .pp-role.consultant{background:var(--cyan-bg);color:#0e6a84;border-color:rgba(8,145,178,.3)}
  .pp-role.grammarian{background:var(--green-bg);color:#15803d;border-color:rgba(22,163,74,.3)}
  .pp-role.capstone_adviser{background:var(--blue-bg);color:var(--blue);border-color:rgba(29,78,216,.25)}
  .pp-id-lock{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;color:var(--text3)}
  .pp-id-lock svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .pp-id-edit{display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:0 13px;border:1px solid var(--border2);border-radius:9px;background:var(--bg2);color:var(--text);font-size:12.5px;font-weight:600;text-decoration:none;white-space:nowrap}
  .pp-id-edit:hover{border-color:var(--accent);background:var(--accent-light)}
  .pp-id-edit svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

  .pp-sheet,.pp-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px}
  .pp-sec{padding:18px 22px}
  .pp-sec + .pp-sec{border-top:1px solid var(--bg4)}
  .pp-sec-h{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
  .pp-sec-h h2{margin:0;font-size:14.5px;font-weight:700;color:var(--text)}
  .pp-help{margin:3px 0 12px;font-size:12.5px;line-height:1.45;color:var(--text3)}

  .pp-status{margin-left:auto;display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;font-variant-numeric:tabular-nums;background:rgba(22,163,74,.08);color:var(--green)}
  .pp-status.full{background:var(--amber-bg);color:var(--amber)}
  .pp-status i{width:7px;height:7px;border-radius:50%;background:currentColor}
  .pp-progs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
  /* A number typed per program; 0 or blank means no teams from it. */
  .pp-prog{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:4px 5px 4px 12px;border:1px solid var(--border);border-radius:10px;background:var(--bg2);color:var(--text3)}
  .pp-prog.on{border-color:rgba(201,168,44,.6);background:var(--accent-light);color:var(--text)}
  .pp-prog label{flex:1;font-size:13.5px;font-weight:700;cursor:pointer}
  .pp-prog input{width:64px;height:34px;border:1px solid var(--border2);border-radius:8px;padding:0 8px;background:var(--bg2);font:inherit;font-size:14px;font-weight:700;color:var(--text);text-align:center;font-variant-numeric:tabular-nums;-moz-appearance:textfield}
  .pp-prog input::-webkit-outer-spin-button,.pp-prog input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
  .pp-prog input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
  .pp-foot{display:flex;flex-wrap:wrap;gap:4px 14px;margin-top:10px;font-size:12px;color:var(--text3)}
  .pp-foot b{color:var(--text)}
  .pp-foot .pp-warn{color:var(--amber)}

  .pp-tabs{display:inline-flex;flex-wrap:wrap;gap:2px;padding:3px;border-radius:9px;background:var(--bg3);border:1px solid var(--border);margin-bottom:12px}
  .pp-tabs button{min-height:30px;padding:0 12px;border:0;border-radius:7px;background:transparent;color:var(--text2);font:inherit;font-size:12.5px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
  .pp-tabs button[aria-pressed="true"]{background:var(--bg2);color:var(--text);box-shadow:0 1px 2px rgba(24,25,31,.14)}
  .pp-tabs em{font-style:normal;color:var(--text3);font-weight:500;font-variant-numeric:tabular-nums}
  .pp-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
  .pp-chip{display:inline-flex;align-items:center;gap:2px;min-height:28px;padding:0 4px 0 11px;border-radius:8px;font-size:12.5px;font-weight:600;background:var(--accent-light);color:var(--accent3);border:1px solid rgba(201,168,44,.45)}
  .pp-chip button{width:20px;height:20px;border:0;border-radius:50%;background:transparent;color:inherit;cursor:pointer;padding:0;font-size:14px;line-height:1}
  .pp-chip button:hover{background:rgba(0,0,0,.08)}
  .pp-none{font-size:12.5px;color:var(--amber)}
  .pp-finder{position:relative}
  .pp-search{display:flex;align-items:center;gap:8px;height:38px;border:1px solid var(--border2);border-radius:9px;padding:0 12px;background:var(--bg2)}
  .pp-search:focus-within{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
  .pp-search svg{width:15px;height:15px;fill:none;stroke:var(--text3);stroke-width:2;flex-shrink:0}
  .pp-search input{flex:1;min-width:0;border:0;outline:none;background:transparent;font:inherit;font-size:13px;color:var(--text)}
  .pp-pop{position:absolute;left:0;right:0;top:44px;z-index:20;background:var(--bg2);border:1px solid var(--border);border-radius:12px;box-shadow:0 14px 34px rgba(24,25,31,.16);padding:10px}
  .pp-pop-groups{display:flex;flex-wrap:wrap;gap:3px;padding-bottom:8px;margin-bottom:8px;border-bottom:1px solid var(--bg4)}
  .pp-pop-groups button{font:inherit;font-size:12px;font-weight:600;padding:3px 9px;border-radius:7px;border:0;background:transparent;color:var(--text3);cursor:pointer}
  .pp-pop-groups button[aria-pressed="true"]{background:var(--bg3);color:var(--text)}
  .pp-pop-list{display:flex;flex-direction:column;max-height:208px;overflow:auto}
  .pp-pop-list button{display:flex;justify-content:space-between;align-items:center;gap:10px;text-align:left;padding:8px 10px;border:0;border-radius:8px;background:transparent;font:inherit;font-size:13px;color:var(--text);cursor:pointer}
  .pp-pop-list button:hover,.pp-pop-list button:focus-visible{background:var(--accent-light)}
  .pp-pop-list button span{font-size:11.5px;color:var(--text3)}
  .pp-pop-empty{padding:8px 10px;font-size:12.5px;color:var(--text3)}

  .pp-degree{display:flex;align-items:center;gap:10px 12px;flex-wrap:wrap}
  .pp-select{height:38px;width:220px;max-width:100%;border:1px solid var(--border2);border-radius:9px;padding:0 10px;font:inherit;font-size:13px;color:var(--text);background:var(--bg2)}
  .pp-select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
  .pp-chair{font-size:12.5px;font-weight:600}
  .pp-chair.yes{color:var(--green)}
  .pp-chair.no{color:var(--amber)}

  .pp-em{display:flex;align-items:center;gap:12px;padding:11px 0}
  .pp-em + .pp-em{border-top:1px solid var(--bg4)}
  .pp-em label{flex:1;min-width:0;cursor:pointer}
  .pp-em b{display:block;font-size:13.5px;color:var(--text)}
  .pp-em small{display:block;font-size:12px;color:var(--text3);margin-top:1px;line-height:1.4}
  .pp-switch{appearance:none;-webkit-appearance:none;width:34px;height:20px;border-radius:10px;background:var(--border2);position:relative;cursor:pointer;margin:0;flex:0 0 auto;transition:background .15s}
  .pp-switch::before{content:"";position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .15s}
  .pp-switch:checked{background:var(--accent)}
  .pp-switch:checked::before{transform:translateX(14px)}
  .pp-bell{display:flex;gap:9px;align-items:flex-start;margin-top:6px;padding:10px 12px;border-radius:10px;background:var(--bg3);font-size:12.5px;line-height:1.45;color:var(--text2)}
  .pp-bell svg{width:16px;height:16px;flex-shrink:0;margin-top:1px;fill:none;stroke:var(--accent3);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  @media (prefers-reduced-motion: reduce){.pp-switch,.pp-switch::before{transition:none}}

  /* Only there while something is unsaved, or just saved. */
  .pp-save{position:sticky;bottom:0;padding:14px 0 18px;display:none}
  .pp-save.on{display:block}
  /* As wide as what it says, so the notification toasts in the corner never cover Save. */
  .pp-savebar{display:flex;width:fit-content;max-width:100%;align-items:center;gap:16px;padding:8px 10px 8px 16px;border-radius:12px;background:var(--navy-dark);color:#fff;box-shadow:0 8px 28px rgba(15,17,23,.25)}
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
  .pp-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}

  @media (max-width:1180px){
    .pp-page{max-width:760px}
    .pp-cols{grid-template-columns:minmax(0,1fr)}
    .pp-aside{position:static}
  }
  /* Clear of the floating tab bar along the bottom of a phone. */
  @media (max-width:768px){
    .pp-save{bottom:calc(76px + env(safe-area-inset-bottom, 0px))}
  }
  @media (max-width:640px){
    .pp-cols{gap:10px}
    .pp-id{padding:14px 16px}
    .pp-id-photo{width:44px;height:44px}
    .pp-id-main h1{font-size:17px}
    .pp-id-roles{align-items:flex-start;width:100%}
    .pp-id-chips{justify-content:flex-start}
    .pp-sec{padding:16px}
    .pp-progs{gap:6px}
    .pp-prog{padding-left:9px}
    .pp-select{width:100%}
    .pp-status{margin-left:0}
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
    <div style="max-width:760px">
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
    <div class="pp-page">

      {{-- Who this is. Name, email and roles are set elsewhere; the degree follows the form below. --}}
      <header class="pp-id">
        <img class="pp-id-photo" src="{{ $expert->avatarUrl() }}" alt="" onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.jpg') }}'">
        <div class="pp-id-main">
          <h1 id="pp-who">{{ $expert->first_name }} {{ $expert->last_name }}<span id="pp-who-degree">{{ $expert->degreeText() ? ', '.$expert->degreeText() : '' }}</span></h1>
          <div class="pp-id-mail">{{ $expert->email }}</div>
        </div>
        <div class="pp-id-roles">
          <div class="pp-id-chips">
            @forelse ($expert->roles as $role)
              <span class="pp-role {{ $role->role_name }}">{{ $role->label }}</span>
            @empty
              <span class="pp-role">No role yet</span>
            @endforelse
          </div>
          <span class="pp-id-lock"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Roles are set by the administrator</span>
        </div>
        @if ($staffProfileRoute)
          <a class="pp-id-edit" href="{{ route($staffProfileRoute) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Name, photo and sections</a>
        @endif
      </header>

      <div class="pp-cols">
        <div class="pp-sheet">

          <section class="pp-sec" aria-labelledby="pp-slots-h">
            <div class="pp-sec-h">
              <h2 id="pp-slots-h">Team slots</h2>
              <span class="pp-status" id="pp-status" role="status"><i></i><span id="pp-status-text"></span></span>
            </div>
            <p class="pp-help">Type how many teams you take from each program. Leave it at 0 for a program you don't take.</p>
            <div class="pp-progs">
              @foreach (\App\Models\CapstoneExpert::PROGRAMS as $program)
                <div class="pp-prog" data-program="{{ $program }}">
                  <label for="pp-prog-{{ $program }}">{{ $program }}</label>
                  <input type="number" id="pp-prog-{{ $program }}" min="0" max="20" step="1" inputmode="numeric" placeholder="0" aria-label="{{ $program }} teams you take">
                </div>
              @endforeach
            </div>
            <div class="pp-foot">
              <span id="pp-taken"></span>
              @if ($slotSuggestion)
                <span>Suggestion: <b>{{ $slotSuggestion }}</b>, from how quickly you review your teams' documents.</span>
              @endif
            </div>
          </section>

          <section class="pp-sec" aria-labelledby="pp-areas-h">
            <div class="pp-sec-h"><h2 id="pp-areas-h">Research areas</h2></div>
            <p class="pp-help">Teams are matched to you by these. Keep a list for each role you hold and for defense panels.</p>
            <div class="pp-tabs" role="group" aria-label="Research areas as">
              @foreach ($researchSides as $side => $label)
                <button type="button" data-side="{{ $side }}" aria-pressed="{{ $side === $firstSide ? 'true' : 'false' }}">{{ $label }} <em data-side-count="{{ $side }}">{{ count($researchAreas[$side] ?? []) }}</em></button>
              @endforeach
            </div>
            <div class="pp-chips" id="pp-chips"></div>
            <div class="pp-finder">
              <div class="pp-search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <label for="pp-area-input" class="pp-sr">Add a research area</label>
                <input id="pp-area-input" type="text" autocomplete="off" placeholder="Search, or type your own research area" aria-controls="pp-pop">
              </div>
              <div class="pp-pop" id="pp-pop" hidden>
                <div class="pp-pop-groups" id="pp-pop-groups" role="group" aria-label="Research area groups"></div>
                <div class="pp-pop-list" id="pp-pop-list"></div>
              </div>
            </div>
          </section>

          <section class="pp-sec" aria-labelledby="pp-degree-h">
            <div class="pp-sec-h"><h2 id="pp-degree-h">Degree</h2></div>
            <p class="pp-help">Shown after your name. A Panel Chair needs a master's degree or higher.</p>
            <div class="pp-degree">
              <label for="pp-degree" class="pp-sr">Highest degree</label>
              @include('partials.degree-select', [
                  'id' => 'pp-degree',
                  'selected' => $expert->degree_title,
                  'attrs' => 'class="pp-select"',
              ])
              <span class="pp-chair" id="pp-chair" aria-live="polite"></span>
            </div>
          </section>

        </div>

        <aside class="pp-aside">
          <section class="pp-card pp-sec" id="notifications" aria-labelledby="pp-notif-h">
            <div class="pp-sec-h"><h2 id="pp-notif-h">Email notifications</h2></div>
            <p class="pp-help">Everything always shows in the bell. Choose what also goes to {{ $expert->email }}.</p>
            @foreach ($emailGroups as $key => $group)
              <div class="pp-em">
                <label for="pp-email-{{ $key }}"><b>{{ $group['label'] }}</b><small>{{ $group['help'] }}</small></label>
                <input class="pp-switch" type="checkbox" id="pp-email-{{ $key }}" data-group="{{ $key }}" data-types="{{ collect($group['types'])->map(fn ($type) => 'email.'.$type)->implode(' ') }}">
              </div>
            @endforeach
            <div class="pp-bell">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
              <span>For pop-ups on your desktop, open the bell and click <b>Turn on</b>.</span>
            </div>
          </section>
        </aside>
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
</div>

@include('instructor.partials.skeleton-toggle')

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
  var BELL_TYPES = @json($bellTypes);
  var EMAIL_GROUPS = @json(collect($emailGroups)->map(fn ($group) => $group['types']));
  // Sourced from App\Support\SkillCatalog, the same groups the student
  // expert list filters by. A starting point, not a whitelist.
  var GROUPS = Object.entries(@json(\App\Support\SkillCatalog::groups())).map(function (g) { return { name: g[0], items: g[1] }; });
  var KNOWN = GROUPS.reduce(function (all, g) { return all.concat(g.items.map(function (i) { return { name: i, group: g.name }; })); }, []);

  var saved = {
    degree: @json($expert->degree_title ?? ''),
    programs: PROGRAMS.reduce(function (o, p) { o[p] = 0; return o; }, {}),
    areas: @json((object) $researchAreas),
    email: @json(collect($emailGroups)->map(fn ($group) => $group['on']))
  };
  Object.assign(saved.programs, @json((object) $programSlots));
  Object.keys(SIDES).forEach(function (side) { if (!saved.areas[side]) saved.areas[side] = []; });

  function copy(x) { return JSON.parse(JSON.stringify(x)); }
  function norm(v) { return String(v).trim().toLowerCase(); }

  var state = copy(saved);
  var side = @json($firstSide);
  var group = GROUPS.length ? GROUPS[0].name : null;
  var justSaved = false;

  var el = function (id) { return document.getElementById(id); };
  var input = el('pp-area-input');

  function total() { return PROGRAMS.reduce(function (t, p) { return t + (state.programs[p] || 0); }, 0); }
  function dirty() { return JSON.stringify(state) !== JSON.stringify(saved); }
  function areas() { return state.areas[side] || (state.areas[side] = []); }
  function has(name) { return areas().some(function (a) { return norm(a) === norm(name); }); }
  function make(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }

  function renderPrograms() {
    document.querySelectorAll('.pp-prog').forEach(function (row) {
      var p = row.dataset.program, n = state.programs[p] || 0, box = row.querySelector('input');
      row.classList.toggle('on', n > 0);
      // Left alone while it is being typed in, so the caret does not jump.
      if (document.activeElement !== box) box.value = n ? n : '';
    });
    var t = total();
    if (!t && FLAT_LIMIT && !dirty()) t = FLAT_LIMIT;
    var open = Math.max(t - TEAMS_HANDLED, 0);
    el('pp-status').classList.toggle('full', open === 0);
    el('pp-status-text').textContent = open === 0 ? 'Full' : (open === 1 ? '1 slot open' : open + ' slots open');
    var taken = el('pp-taken');
    taken.className = t < TEAMS_HANDLED ? 'pp-warn' : '';
    taken.innerHTML = !t
      ? 'Type a number for at least one program to take teams.'
      : (t < TEAMS_HANDLED
        ? 'You handle <b>' + TEAMS_HANDLED + '</b> teams, more than your limit of <b>' + t + '</b>. No new team comes to you until you are below it.'
        : 'You handle <b>' + TEAMS_HANDLED + '</b> of <b>' + t + '</b> ' + (t === 1 ? 'team' : 'teams'));
  }

  function renderAreas() {
    document.querySelectorAll('.pp-tabs [data-side]').forEach(function (b) {
      b.setAttribute('aria-pressed', b.dataset.side === side ? 'true' : 'false');
      b.querySelector('[data-side-count]').textContent = (state.areas[b.dataset.side] || []).length;
    });
    var chips = el('pp-chips');
    chips.innerHTML = '';
    if (!areas().length) {
      chips.appendChild(make('span', 'pp-none', 'None yet, so no team is matched to you as ' + SIDES[side] + '.'));
    }
    areas().forEach(function (name) {
      var chip = make('span', 'pp-chip', name), x = make('button', null, '×');
      x.type = 'button';
      x.setAttribute('aria-label', 'Remove ' + name);
      x.addEventListener('click', function () { toggle(name); });
      chip.appendChild(x);
      chips.appendChild(chip);
    });
    if (!el('pp-pop').hidden) renderPicker();
  }

  // Open while the box is in use. A search crosses every group; otherwise
  // one group shows at a time. What is taken already is not offered again.
  function renderPicker() {
    var typed = input.value.trim(), q = norm(typed), groupsEl = el('pp-pop-groups'), list = el('pp-pop-list');
    groupsEl.innerHTML = '';
    list.innerHTML = '';
    groupsEl.hidden = !!q;
    GROUPS.forEach(function (g) {
      var b = make('button', null, g.name);
      b.type = 'button';
      b.setAttribute('aria-pressed', g.name === group ? 'true' : 'false');
      b.addEventListener('mousedown', function (e) { e.preventDefault(); group = g.name; renderPicker(); });
      groupsEl.appendChild(b);
    });
    var pool = q
      ? KNOWN.filter(function (k) { return norm(k.name).indexOf(q) >= 0; })
      : KNOWN.filter(function (k) { return k.group === group; });
    pool = pool.filter(function (k) { return !has(k.name); });
    if (typed && !has(typed) && !KNOWN.some(function (k) { return norm(k.name) === q; })) {
      pool.unshift({ name: typed, group: 'Add as your own' });
    }
    if (!pool.length) {
      list.appendChild(make('div', 'pp-pop-empty', q ? 'You have this one already.' : 'You have every area in this group.'));
    }
    pool.forEach(function (k) {
      var b = make('button');
      b.type = 'button';
      b.appendChild(document.createTextNode('+ ' + k.name));
      b.appendChild(make('span', null, q ? k.group : ''));
      b.addEventListener('mousedown', function (e) { e.preventDefault(); add(k.name); });
      list.appendChild(b);
    });
  }

  function toggle(name) {
    var list = areas(), at = list.findIndex(function (a) { return norm(a) === norm(name); });
    if (at >= 0) { list.splice(at, 1); } else { list.push(name); }
    changed();
    renderAreas();
  }

  function add(name) {
    // A known area typed in other casing is stored as the list spells it.
    var known = KNOWN.find(function (k) { return norm(k.name) === norm(name); });
    if (!has(name)) areas().push(known ? known.name : name.trim());
    input.value = '';
    changed();
    renderAreas();
    renderPicker();
  }

  function renderDegree() {
    var level = DEGREE_LEVELS[state.degree] || null, canChair = level === 'master' || level === 'doctorate', chair = el('pp-chair');
    chair.className = 'pp-chair ' + (canChair ? 'yes' : 'no');
    chair.textContent = canChair ? '✓ You can chair a defense panel' : 'You can sit on a panel, but not chair it';
    el('pp-who-degree').textContent = state.degree ? ', ' + state.degree : '';
  }

  function renderEmail() {
    document.querySelectorAll('.pp-switch[data-group]').forEach(function (sw) { sw.checked = !!state.email[sw.dataset.group]; });
  }

  function renderSaveBar(text, cls) {
    var bar = el('pp-save');
    bar.classList.toggle('on', dirty() || justSaved || cls === 'failed');
    bar.classList.toggle('saved', !dirty() && justSaved && cls !== 'failed');
    bar.classList.toggle('failed', cls === 'failed');
    el('pp-save-text').textContent = text || (dirty() ? 'You have unsaved changes' : 'Changes saved');
  }

  function changed() { justSaved = false; renderSaveBar(); }
  function renderAll() { renderPrograms(); renderAreas(); renderDegree(); renderEmail(); renderSaveBar(); }

  document.querySelectorAll('.pp-prog input').forEach(function (box) {
    var p = box.closest('.pp-prog').dataset.program;
    box.addEventListener('input', function () {
      var n = parseInt(box.value, 10);
      state.programs[p] = isNaN(n) ? 0 : Math.min(20, Math.max(0, n));
      changed();
      renderPrograms();
    });
    // Tidied once they move on: 25 becomes 20, -1 or blank becomes 0.
    box.addEventListener('blur', renderPrograms);
  });

  document.querySelectorAll('.pp-tabs [data-side]').forEach(function (b) {
    b.addEventListener('click', function () { side = b.dataset.side; renderAreas(); });
  });

  input.addEventListener('focus', function () { el('pp-pop').hidden = false; renderPicker(); });
  input.addEventListener('blur', function () { el('pp-pop').hidden = true; });
  input.addEventListener('input', renderPicker);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { input.blur(); return; }
    if (e.key === 'Enter') { e.preventDefault(); if (input.value.trim()) add(input.value); }
  });

  el('pp-degree').addEventListener('change', function (e) { state.degree = e.target.value; changed(); renderDegree(); });

  document.querySelectorAll('.pp-switch[data-group]').forEach(function (sw) {
    sw.addEventListener('change', function () { state.email[sw.dataset.group] = sw.checked; changed(); });
  });

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

  // The profile and the email switches are saved where each always has
  // been; only the part that changed is sent.
  el('pp-save-btn').addEventListener('click', async function () {
    var btn = this;
    var profileChanged = part(state, ['degree', 'programs', 'areas']) !== part(saved, ['degree', 'programs', 'areas']);
    var emailChanged = JSON.stringify(state.email) !== JSON.stringify(saved.email);
    if (profileChanged && total() < 1) {
      renderSaveBar('Type how many teams you take from at least one program.', 'failed');
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
      if (emailChanged) {
        // Every bell switch on, and each email as its group says.
        var on = {};
        BELL_TYPES.forEach(function (type) { on['web.' + type] = true; });
        Object.keys(EMAIL_GROUPS).forEach(function (g) {
          EMAIL_GROUPS[g].forEach(function (type) { on['email.' + type] = !!state.email[g]; });
        });
        await put(@json(route('instructor.notifications.preferences')), { all: true, on: on });
        saved.email = copy(state.email);
      }
      justSaved = true;
      renderAll();
    } catch (e) {
      renderSaveBar(e.message && e.message.indexOf('fetch') < 0 ? e.message : 'Could not reach CapsTrack. Check your connection and try again.', 'failed');
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
