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
  .pp-id-edit{white-space:nowrap}
  .pp-id-edit svg{stroke-linecap:round;stroke-linejoin:round}
  /* The degree is what is written after the name, so it is picked there. */
  .pp-name{display:flex;align-items:baseline;flex-wrap:wrap;gap:2px 0}
  .pp-name .pp-comma{font-size:19px;font-weight:700;color:var(--text);margin-right:6px}
  .pp-deg-pick{align-self:center;height:30px;max-width:100%;border:1px dashed var(--border2);border-radius:8px;padding:0 6px;background:var(--bg2);font:inherit;font-size:16px;font-weight:700;color:var(--text);cursor:pointer}
  .pp-deg-pick:hover,.pp-deg-pick:focus{border-style:solid;border-color:var(--accent);outline:none;box-shadow:0 0 0 3px var(--accent-glow)}
  .pp-id-mail .pp-chair{margin-left:10px;font-size:12px}
  .pp-name-form{flex:1 1 440px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr)) 90px;gap:8px}
  .pp-name-form label{display:flex;flex-direction:column;gap:4px;font-size:11.5px;font-weight:600;color:var(--text2)}
  .pp-name-form input{height:36px;border:1px solid var(--border2);border-radius:8px;padding:0 10px;font:inherit;font-size:13px;color:var(--text);background:var(--bg2)}
  .pp-name-form input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
  .pp-name-form[hidden],.pp-id-main[hidden]{display:none}
  .pp-name-form input[readonly]{color:var(--text3);cursor:not-allowed}
  .pp-id-secs{display:flex;flex-wrap:wrap;justify-content:flex-end;align-items:center;gap:5px;font-size:12px;color:var(--text3)}
  .pp-id-secs b{padding:2px 8px;border-radius:6px;background:var(--bg3);border:1px solid var(--border);color:var(--text2);font-size:11.5px}

  .pp-sheet,.pp-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px}
  .pp-sec{padding:18px 22px}
  .pp-sec + .pp-sec{border-top:1px solid var(--bg4)}
  .pp-sec-h{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
  .pp-sec-h{margin-bottom:12px}
  .pp-sec-h h2{margin:0;font-size:16px;font-weight:700;color:var(--text)}
  .pp-help{margin:3px 0 12px;font-size:12.5px;line-height:1.45;color:var(--text3)}

  .pp-status{margin-left:auto;display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;font-variant-numeric:tabular-nums;background:rgba(22,163,74,.08);color:var(--green)}
  .pp-status.full{background:var(--amber-bg);color:var(--amber)}
  .pp-status i{width:7px;height:7px;border-radius:50%;background:currentColor}
  /* One row per program taken, counted with - and +; the rest wait in Add program. */
  .pp-progs{display:grid;gap:8px}
  .pp-prog{display:flex;align-items:center;gap:10px;padding:5px 12px 5px 14px;border:1px solid var(--border);border-radius:10px;background:var(--bg3)}
  .pp-prog[hidden]{display:none}
  .pp-prog label{flex:1;font-size:14px;font-weight:700;color:var(--text)}
  .pp-prog > span{font-size:12.5px;color:var(--text3)}
  .pp-step{display:inline-flex;border:1px solid var(--border2);border-radius:8px;background:var(--bg2);overflow:hidden}
  .pp-step button{width:32px;height:32px;border:0;background:transparent;font:inherit;font-size:17px;color:var(--text2);cursor:pointer}
  .pp-step button:hover:not(:disabled){background:var(--bg3)}
  .pp-step button:disabled{color:var(--border2);cursor:default}
  .pp-step input{width:44px;height:32px;border:0;border-left:1px solid var(--border2);border-right:1px solid var(--border2);border-radius:0;padding:0;background:transparent;font:inherit;font-size:14px;font-weight:700;color:var(--text);text-align:center;font-variant-numeric:tabular-nums;-moz-appearance:textfield}
  .pp-step input::-webkit-outer-spin-button,.pp-step input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
  .pp-step input:focus{outline:none;box-shadow:inset 0 0 0 2px var(--accent)}
  .pp-add{appearance:none;-webkit-appearance:none;border:0;background:transparent;padding:0;font:inherit;font-size:13px;font-weight:700;color:var(--accent3);cursor:pointer}
  .pp-add[hidden]{display:none}
  #pp-taken{margin-left:auto}
  /* Panel preferences: a label over each line of choices. */
  .pp-field + .pp-field{margin-top:14px}
  .pp-label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;color:var(--text)}
  .pp-label em{font-style:normal;font-weight:500;color:var(--text3);margin-left:6px}
  .pp-row{display:flex;align-items:center;flex-wrap:wrap;gap:6px 12px}
  .pp-row .pp-label{flex:1;margin:0}
  .pp-row #pp-panels-taken{font-size:12px;color:var(--text3)}
  .pp-row #pp-panels-taken.pp-warn{color:var(--amber)}
  .pp-checks{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:4px 12px}
  .pp-strengths{grid-template-columns:repeat(3,minmax(0,1fr))}
  .pp-check{display:inline-flex;align-items:center;gap:8px;min-height:30px;font-size:13.5px;color:var(--text);cursor:pointer}
  .pp-check input{width:16px;height:16px;margin:0;accent-color:var(--accent)}
  .pp-foot{display:flex;flex-wrap:wrap;align-items:center;gap:4px 14px;margin-top:10px;font-size:12px;color:var(--text3)}
  .pp-foot b{color:var(--text)}
  .pp-foot .pp-warn{color:var(--amber)}

  .pp-tabs{display:inline-flex;flex-wrap:wrap;gap:2px;padding:3px;border-radius:9px;background:var(--bg3);border:1px solid var(--border);margin-bottom:12px}
  .pp-tabs button{min-height:32px;min-width:110px;padding:0 14px;border:0;border-radius:7px;background:transparent;color:var(--text2);font:inherit;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
  .pp-tabs button[aria-pressed="true"]{background:var(--accent);color:#18191f;font-weight:700}
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
  .pp-savebar{display:flex;width:fit-content;max-width:100%;align-items:center;gap:16px;padding:10px 12px 10px 18px;border-radius:14px;background:var(--bg2);border:1px solid var(--border);box-shadow:0 10px 30px rgba(15,17,23,.12)}
  .pp-save-msg{display:flex;align-items:center;gap:9px;min-width:0;font-size:13px;font-weight:600;color:var(--text2)}
  .pp-save-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;background:var(--accent)}
  .pp-save-btns{display:flex;gap:8px}
  .pp-save-btns .btn{justify-content:center}
  .pp .btn:disabled{opacity:.6;cursor:default}

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
    .pp-id-chips,.pp-id-secs{justify-content:flex-start}
    .pp-name-form{grid-template-columns:repeat(2,minmax(0,1fr))}
    .pp-sec{padding:16px}
    .pp-progs{gap:6px}
    .pp-prog{padding-left:10px}
    .pp-checks,.pp-strengths{grid-template-columns:repeat(2,minmax(0,1fr))}
    .pp-tabs button{min-width:0}
    .pp-select{width:100%}
    .pp-status{margin-left:0}
    .pp-savebar{width:auto;flex-wrap:wrap;gap:10px}
    .pp-save-msg{flex:1 1 100%}
    .pp-save-btns{width:100%}
    .pp-save-btns .btn{flex:1}
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
  $nameParts = ['first' => $expert->first_name ?? '', 'middle' => $expert->middle_name ?? '', 'last' => $expert->last_name ?? '', 'suffix' => $expert->suffix ?? ''];
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

      {{-- Who this is. The degree is picked beside the name it follows; a
           CaPSA/TSA or Admin edits their own name here too. Roles and sections
           are the Admin's, so they only show. --}}
      <header class="pp-id">
        <img class="pp-id-photo" src="{{ $expert->avatarUrl() }}" alt="" title="Your photo comes from your Google account" onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.jpg') }}'">
        <div class="pp-id-main" id="pp-id-view">
          <div class="pp-name">
            <h1 id="pp-who">{{ collect([$expert->first_name, $expert->middle_name ? mb_substr($expert->middle_name, 0, 1).'.' : null, $expert->last_name])->filter()->implode(' ') }}{{ $expert->suffix ? ' '.$expert->suffix : '' }}</h1><span class="pp-comma">,</span>
            <label for="pp-degree" class="pp-sr">Degree shown after your name</label>
            @include('partials.degree-select', [
                'id' => 'pp-degree',
                'selected' => $expert->degree_title,
                'blank' => 'No degree',
                'attrs' => 'class="pp-deg-pick"',
            ])
          </div>
          <div class="pp-id-mail">{{ $expert->email }} <span class="pp-chair" id="pp-chair" aria-live="polite"></span></div>
        </div>
        {{-- Only a CaPSA/TSA or Admin edits the whole name; everyone else's first
             and last name come from Google, which has no middle name. --}}
        <div class="pp-name-form" id="pp-name-form" hidden>
          <label>First name<input id="pp-first" autocomplete="given-name" maxlength="50" @readonly(! $canEditName) @if (! $canEditName) title="From your Google account" @endif></label>
          <label>Middle initial<input id="pp-middle" maxlength="1" placeholder="e.g. S"></label>
          <label>Last name<input id="pp-last" autocomplete="family-name" maxlength="50" @readonly(! $canEditName) @if (! $canEditName) title="From your Google account" @endif></label>
          <label>Suffix<input id="pp-suffix" maxlength="50" placeholder="e.g. Jr." @readonly(! $canEditName) @if (! $canEditName) title="Set by the administrator" @endif></label>
        </div>
        <div class="pp-id-roles">
          <div class="pp-id-chips">
            @forelse ($expert->roles as $role)
              <span class="pp-role {{ $role->role_name }}">{{ $role->label }}</span>
            @empty
              <span class="pp-role">No role yet</span>
            @endforelse
          </div>
          @if ($capsaSections !== null)
            <div class="pp-id-secs">
              <span>CaPSA/TSA of</span>
              @forelse ($capsaSections as $section)
                <b>{{ $section }}</b>
              @empty
                <span>no sections yet</span>
              @endforelse
              @if ($schoolYear)<span>{{ $schoolYear }}</span>@endif
            </div>
          @endif
          <span class="pp-id-lock"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>{{ $capsaSections !== null ? 'Roles and sections are set by the administrator' : 'Roles are set by the administrator' }}</span>
        </div>
        <button type="button" class="btn btn-ghost pp-id-edit" id="pp-name-btn" aria-pressed="false" aria-controls="pp-name-form"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg><span>Edit name</span></button>
      </header>

      <div class="pp-cols">
        <div class="pp-sheet">

          <section class="pp-sec" id="pp-slots" aria-labelledby="pp-slots-h">
            <div class="pp-sec-h">
              <h2 id="pp-slots-h">Team capacity</h2>
              <span class="pp-status" id="pp-status" role="status"><i></i><span id="pp-status-text"></span></span>
            </div>
            <div class="pp-progs">
              @foreach ([...\App\Models\CapstoneExpert::PROGRAMS, 'ALL'] as $program)
                @php($programLabel = $program === 'ALL' ? 'All programs' : $program)
                <div class="pp-prog" data-program="{{ $program }}" hidden>
                  <label for="pp-prog-{{ $program }}">{{ $programLabel }}</label>
                  <div class="pp-step">
                    <button type="button" data-step="-1" aria-label="One fewer team ({{ $programLabel }})">&minus;</button>
                    <input type="number" id="pp-prog-{{ $program }}" min="0" max="20" step="1" inputmode="numeric">
                    <button type="button" data-step="1" aria-label="One more team ({{ $programLabel }})">+</button>
                  </div>
                  <span>teams</span>
                </div>
              @endforeach
            </div>
            <div class="pp-foot">
              <label for="pp-add-prog" class="pp-sr">Add a program</label>
              <select id="pp-add-prog" class="pp-add"></select>
              <span id="pp-taken"></span>
            </div>
            @if ($slotSuggestion)
              <div class="pp-foot"><span>Suggestion: <b>{{ $slotSuggestion }}</b>, from how quickly you review your teams' documents.</span></div>
            @endif
          </section>

          <section class="pp-sec" aria-labelledby="pp-areas-h">
            <div class="pp-sec-h"><h2 id="pp-areas-h">Research areas</h2></div>
            <div class="pp-tabs" role="group" aria-label="Research areas as">
              @foreach ($researchSides as $side => $label)
                <button type="button" data-side="{{ $side }}" aria-pressed="{{ $side === $firstSide ? 'true' : 'false' }}">{{ $label }}</button>
              @endforeach
            </div>
            <div class="pp-chips" id="pp-chips"></div>
            <div class="pp-finder">
              <div class="pp-search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <label for="pp-area-input" class="pp-sr">Add a research area</label>
                <input id="pp-area-input" type="text" autocomplete="off" placeholder="Search or add a research area" aria-controls="pp-pop">
              </div>
              <div class="pp-pop" id="pp-pop" hidden>
                <div class="pp-pop-groups" id="pp-pop-groups" role="group" aria-label="Research area groups"></div>
                <div class="pp-pop-list" id="pp-pop-list"></div>
              </div>
            </div>
          </section>

          <section class="pp-sec" id="pp-panel" aria-labelledby="pp-panels-h">
            <div class="pp-sec-h"><h2 id="pp-panels-h">Panel preferences</h2></div>
            <div class="pp-field">
              <span class="pp-label" id="pp-pprog-l">Programs <em id="pp-pcourses-all">Every program</em></span>
              <div class="pp-checks" role="group" aria-labelledby="pp-pprog-l">
                @foreach (\App\Models\CapstoneExpert::PROGRAMS as $program)
                  <label class="pp-check"><input type="checkbox" data-panel-program="{{ $program }}">{{ $program }}</label>
                @endforeach
              </div>
            </div>
            <div class="pp-field pp-row">
              <label class="pp-label" for="pp-max-panels">Maximum teams</label>
              <span id="pp-panels-taken"></span>
              <select id="pp-max-panels" class="pp-select">
                <option value="">No limit</option>
                @for ($n = 1; $n <= 30; $n++)
                  <option value="{{ $n }}">{{ $n }}</option>
                @endfor
              </select>
            </div>
            @php($strengthHints = ['manuscript' => 'Chapters, research method, writing', 'design' => 'Diagrams, database, architecture', 'software' => 'The working system, code, testing'])
            <div class="pp-field">
              <span class="pp-label" id="pp-strong-l">Review strengths</span>
              <div class="pp-checks pp-strengths" role="group" aria-labelledby="pp-strong-l">
                @foreach (\App\Models\CapstoneExpert::STRENGTHS as $key => $label)
                  <label class="pp-check" title="{{ $strengthHints[$key] }}"><input type="checkbox" data-strength="{{ $key }}">{{ $label }}</label>
                @endforeach
              </div>
            </div>
          </section>

          <section class="pp-sec" aria-labelledby="pp-leave-h">
            <div class="pp-em">
              <label for="pp-leave"><b id="pp-leave-h">I'm on study leave</b><small>Students see that you may be slow to reply and hard to book. Your CaPSA/TSA can turn it on or off too.</small></label>
              <input class="pp-switch" type="checkbox" id="pp-leave">
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
          <div class="pp-save-msg"><span class="pp-save-dot"></span><span>You have unsaved changes</span></div>
          <div class="pp-save-btns">
            <button type="button" class="btn btn-ghost" id="pp-discard">Discard</button>
            <button type="button" class="btn btn-primary" id="pp-save-btn">Save changes</button>
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
  // "ALL" is one number for every program; it and the per-program rows replace each other.
  var SLOT_KEYS = PROGRAMS.concat('ALL');
  var SIDES = @json((object) $researchSides);
  var DEGREE_LEVELS = @json($degreeLevels);
  var TEAMS_HANDLED = {{ (int) $teamsHandled }};
  var PANELS_HELD = {{ (int) $panelsHeld }};
  // A limit saved before slots were set per program: one number, no programs ticked.
  var FLAT_LIMIT = {{ $programSlots ? 0 : (int) ($expert->max_teams ?? 0) }};
  // Over TEAMS_CAP in all, and over what they have now, waits for a CaPSA/TSA.
  var TEAMS_CAP = {{ \App\Models\CapstoneExpert::TEAMS_CAP }};
  var MAX_TEAMS = {{ (int) ($expert->max_teams ?? 0) }};
  var PENDING = @json($expert->teams_cap_request ? array_sum($expert->teams_cap_request) : null);
  var BELL_TYPES = @json($bellTypes);
  var EMAIL_GROUPS = @json(collect($emailGroups)->map(fn ($group) => $group['types']));
  // Sourced from App\Support\SkillCatalog, the same groups the student
  // expert list filters by. A starting point, not a whitelist.
  var GROUPS = Object.entries(@json(\App\Support\SkillCatalog::groups())).map(function (g) { return { name: g[0], items: g[1] }; });
  var KNOWN = GROUPS.reduce(function (all, g) { return all.concat(g.items.map(function (i) { return { name: i, group: g.name }; })); }, []);

  var CAN_EDIT_NAME = @json($canEditName);
  var saved = {
    name: @json($nameParts),
    degree: @json($expert->degree_title ?? ''),
    programs: SLOT_KEYS.reduce(function (o, p) { o[p] = 0; return o; }, {}),
    areas: @json((object) $researchAreas),
    strengths: @json(array_values($expert->panel_strengths ?? [])),
    maxPanels: @json($expert->max_panels),
    panelPrograms: @json(array_values($expert->panel_programs ?? [])),
    studyLeave: @json((bool) $expert->on_study_leave),
    email: @json(collect($emailGroups)->map(fn ($group) => $group['on']))
  };
  Object.assign(saved.programs, @json((object) $programSlots));
  Object.keys(SIDES).forEach(function (side) { if (!saved.areas[side]) saved.areas[side] = []; });

  function copy(x) { return JSON.parse(JSON.stringify(x)); }
  function norm(v) { return String(v).trim().toLowerCase(); }

  var state = copy(saved);
  var side = @json($firstSide);
  var group = GROUPS.length ? GROUPS[0].name : null;

  var el = function (id) { return document.getElementById(id); };
  var input = el('pp-area-input');

  function total() { return SLOT_KEYS.reduce(function (t, p) { return t + (state.programs[p] || 0); }, 0); }
  function dirty() { return JSON.stringify(state) !== JSON.stringify(saved); }
  function areas() { return state.areas[side] || (state.areas[side] = []); }
  function has(name) { return areas().some(function (a) { return norm(a) === norm(name); }); }
  function make(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }

  // Programs with a row on the page: the ones taken, plus any added since.
  var shown;
  function resetShown() { shown = SLOT_KEYS.filter(function (p) { return state.programs[p] > 0; }); }
  resetShown();

  function renderPrograms() {
    document.querySelectorAll('.pp-prog[data-program]').forEach(function (row) {
      var p = row.dataset.program, n = state.programs[p] || 0, box = row.querySelector('input');
      row.hidden = shown.indexOf(p) < 0;
      row.querySelector('[data-step="-1"]').disabled = n <= 0;
      row.querySelector('[data-step="1"]').disabled = n >= 20;
      // Left alone while it is being typed in, so the caret does not jump.
      if (document.activeElement !== box) box.value = n;
    });
    var add = el('pp-add-prog'), rest = SLOT_KEYS.filter(function (p) { return shown.indexOf(p) < 0; });
    add.innerHTML = '';
    add.appendChild(new Option('+ Add program', ''));
    rest.forEach(function (p) { add.appendChild(new Option(p === 'ALL' ? 'All programs' : p, p)); });
    add.hidden = !rest.length;
    var t = total();
    if (!t && FLAT_LIMIT && !dirty()) t = FLAT_LIMIT;
    var over = t > TEAMS_CAP && t > MAX_TEAMS;
    var open = Math.max((over ? MAX_TEAMS : t) - TEAMS_HANDLED, 0);
    el('pp-status').classList.toggle('full', open === 0);
    el('pp-status-text').textContent = open === 0 ? 'Full' : (open === 1 ? '1 slot available' : open + ' slots available');
    var taken = el('pp-taken');
    taken.className = over || t < TEAMS_HANDLED ? 'pp-warn' : '';
    taken.innerHTML = over
      ? (PENDING === t
        ? 'Waiting for your CaPSA/TSA to approve <b>' + t + '</b> teams. You take <b>' + MAX_TEAMS + '</b> until then.'
        : 'Over <b>' + TEAMS_CAP + '</b> teams needs your CaPSA/TSA\'s approval. Saving sends the request.')
      : !t
      ? 'Add a program to take teams.'
      : (t < TEAMS_HANDLED
        ? 'You handle <b>' + TEAMS_HANDLED + '</b> teams, more than your limit of <b>' + t + '</b>. No new team comes to you until you are below it.'
        : '<b>' + TEAMS_HANDLED + '</b> of <b>' + t + '</b> ' + (t === 1 ? 'slot' : 'slots') + ' filled');
  }

  function renderAreas() {
    document.querySelectorAll('.pp-tabs [data-side]').forEach(function (b) {
      b.setAttribute('aria-pressed', b.dataset.side === side ? 'true' : 'false');
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
    chair.textContent = canChair ? '\u2713 Can chair a defense panel' : 'Can sit on a panel, not chair it';
  }

  var editingName = false;
  function renderName() {
    var n = state.name;
    el('pp-who').textContent = [n.first, n.middle && n.middle.trim() ? n.middle.trim().charAt(0).toUpperCase() + '.' : '', n.last].filter(function (x) { return x && x.trim(); }).join(' ') + (n.suffix && n.suffix.trim() ? ' ' + n.suffix.trim() : '');
    el('pp-name-form').hidden = !editingName;
    el('pp-id-view').hidden = editingName;
    el('pp-name-btn').setAttribute('aria-pressed', editingName ? 'true' : 'false');
    el('pp-name-btn').querySelector('span').textContent = editingName ? 'Done' : 'Edit name';
    el('pp-name-btn').classList.toggle('btn-primary', editingName);
    el('pp-name-btn').classList.toggle('btn-ghost', !editingName);
    [['pp-first', 'first'], ['pp-middle', 'middle'], ['pp-last', 'last'], ['pp-suffix', 'suffix']].forEach(function (f) {
      if (document.activeElement !== el(f[0])) el(f[0]).value = n[f[1]] || '';
    });
  }

  function renderEmail() {
    document.querySelectorAll('.pp-switch[data-group]').forEach(function (sw) { sw.checked = !!state.email[sw.dataset.group]; });
  }

  function renderSaveBar() { el('pp-save').classList.toggle('on', dirty()); }

  // The same Success / Error card every other page shows.
  function toast(message, type) {
    if (window.ctToast) { window.ctToast(message, type); }
  }

  function changed() { renderSaveBar(); }
  function renderStrengths() {
    document.querySelectorAll('[data-strength]').forEach(function (box) { box.checked = state.strengths.indexOf(box.dataset.strength) >= 0; });
  }

  function renderPanels() {
    var max = state.maxPanels, taken = el('pp-panels-taken');
    el('pp-max-panels').value = max == null ? '' : String(max);
    taken.className = max != null && PANELS_HELD > max ? 'pp-warn' : '';
    taken.innerHTML = max == null
      ? 'Sitting on <b>' + PANELS_HELD + '</b>'
      : (PANELS_HELD > max
        ? 'You sit on <b>' + PANELS_HELD + '</b>, more than your limit of <b>' + max + '</b>. No new panel seat comes to you until you are below it.'
        : '<b>' + PANELS_HELD + '</b> of <b>' + max + '</b> filled');
    document.querySelectorAll('[data-panel-program]').forEach(function (b) { b.checked = state.panelPrograms.indexOf(b.dataset.panelProgram) >= 0; });
    el('pp-pcourses-all').hidden = state.panelPrograms.length > 0;
  }

  document.querySelectorAll('[data-panel-program]').forEach(function (box) {
    box.addEventListener('change', function () {
      state.panelPrograms = [].filter.call(document.querySelectorAll('[data-panel-program]'), function (b) { return b.checked; }).map(function (b) { return b.dataset.panelProgram; });
      changed();
      renderPanels();
    });
  });

  function renderAll() { renderPrograms(); renderPanels(); renderAreas(); renderDegree(); renderName(); renderStrengths(); renderEmail(); el('pp-leave').checked = state.studyLeave; renderSaveBar(); }

  el('pp-leave').addEventListener('change', function (e) { state.studyLeave = e.target.checked; changed(); });

  el('pp-max-panels').addEventListener('change', function (e) {
    state.maxPanels = e.target.value ? Number(e.target.value) : null;
    changed();
    renderPanels();
  });

  el('pp-name-btn').addEventListener('click', function () {
    editingName = !editingName;
    renderName();
    if (editingName) el(CAN_EDIT_NAME ? 'pp-first' : 'pp-middle').focus();
  });
  [['pp-first', 'first'], ['pp-middle', 'middle'], ['pp-last', 'last'], ['pp-suffix', 'suffix']].forEach(function (f) {
    el(f[0]).addEventListener('input', function (e) { state.name[f[1]] = e.target.value; changed(); renderName(); });
  });

  document.querySelectorAll('.pp-prog[data-program]').forEach(function (row) {
    var p = row.dataset.program, box = row.querySelector('input');
    function set(n) {
      state.programs[p] = isNaN(n) ? 0 : Math.min(20, Math.max(0, n));
      changed();
      renderPrograms();
    }
    box.addEventListener('input', function () { set(parseInt(box.value, 10)); });
    // Tidied once they move on: 25 becomes 20, -1 or blank becomes 0.
    box.addEventListener('blur', renderPrograms);
    row.querySelectorAll('[data-step]').forEach(function (b) {
      b.addEventListener('click', function () { set((state.programs[p] || 0) + Number(b.dataset.step)); });
    });
  });

  el('pp-add-prog').addEventListener('change', function (e) {
    var p = e.target.value;
    if (!p) return;
    if (p === 'ALL') {
      // Keeps the teams already counted, as one number.
      var n = total();
      PROGRAMS.forEach(function (q) { state.programs[q] = 0; });
      shown = [];
      state.programs.ALL = n;
    } else if (shown.indexOf('ALL') >= 0) {
      shown = [];
      state.programs.ALL = 0;
    }
    shown.push(p);
    state.programs[p] = Math.max(state.programs[p] || 0, 1);
    changed();
    renderPrograms();
    el('pp-prog-' + p).focus();
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

  document.querySelectorAll('[data-strength]').forEach(function (box) {
    box.addEventListener('change', function () {
      state.strengths = [].filter.call(document.querySelectorAll('[data-strength]'), function (b) { return b.checked; }).map(function (b) { return b.dataset.strength; });
      changed();
    });
  });

  document.querySelectorAll('.pp-switch[data-group]').forEach(function (sw) {
    sw.addEventListener('change', function () { state.email[sw.dataset.group] = sw.checked; changed(); });
  });

  el('pp-discard').addEventListener('click', function () {
    state = copy(saved);
    resetShown();
    el('pp-degree').value = state.degree;
    editingName = false;
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
    var keys = ['name', 'degree', 'programs', 'areas', 'strengths', 'maxPanels', 'panelPrograms', 'studyLeave'];
    var profileChanged = part(state, keys) !== part(saved, keys);
    var emailChanged = JSON.stringify(state.email) !== JSON.stringify(saved.email);
    if (CAN_EDIT_NAME && (!state.name.first.trim() || !state.name.last.trim())) {
      editingName = true;
      renderName();
      toast('Your first and last name cannot be blank.', 'error');
      return;
    }
    if (profileChanged && total() < 1) {
      toast('Take teams from at least one program.', 'error');
      return;
    }
    btn.disabled = true;
    try {
      if (profileChanged) {
        var body = { program_slots: state.programs, expertise_by_side: state.areas, degree: state.degree || null, panel_strengths: state.strengths, max_panels: state.maxPanels, panel_programs: state.panelPrograms, on_study_leave: state.studyLeave };
        if (CAN_EDIT_NAME) {
          body.name = { first_name: state.name.first, middle_name: state.name.middle, last_name: state.name.last, suffix: state.name.suffix };
        } else {
          body.middle_name = state.name.middle;
        }
        var json = await put(@json(route('instructor.profile.update')), body);
        if (json.expertise_by_side) {
          Object.keys(SIDES).forEach(function (s) { state.areas[s] = json.expertise_by_side[s] || []; });
        }
        saved.degree = state.degree;
        saved.name = copy(state.name);
        editingName = false;
        saved.programs = copy(state.programs);
        saved.areas = copy(state.areas);
        saved.strengths = copy(state.strengths);
        saved.maxPanels = state.maxPanels;
        saved.panelPrograms = copy(state.panelPrograms);
        saved.studyLeave = state.studyLeave;
        PENDING = json.pending || null;
        MAX_TEAMS = json.max_teams || 0;
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
      renderAll();
      toast(PENDING && profileChanged ? 'Saved. ' + PENDING + ' teams waits for your CaPSA/TSA to approve.' : 'Your profile was saved.', 'success');
    } catch (e) {
      toast(e.message && e.message.indexOf('fetch') < 0 ? e.message : 'Network error. Check your connection and try again.', 'error');
    } finally {
      btn.disabled = false;
    }
  });

  window.addEventListener('beforeunload', function (e) { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });

  renderAll();
})();
</script>

@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
