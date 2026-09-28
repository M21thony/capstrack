<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Consultation - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite('resources/student-ui/Styles/consultation.css')
<x-dashboard.styles />
<style>
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

/* ── Booking card ──────────────────────────────────────────────────────
   Laid out as three numbered steps, because booking is a sequence and the
   old page presented the expert, the calendar and the slot list as three
   unrelated panels. Colours are the app's own gold accent, not the mock's
   indigo - every other student page uses this one. */
.visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}

.book-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:22px 22px 18px}
.book-card-head{margin-bottom:20px}
.book-title{font-size:17px;font-weight:700;color:var(--text);letter-spacing:-0.2px}
.book-sub{font-size:12.5px;color:var(--text3);margin-top:3px}

.book-step{margin-bottom:22px}
.step-head{display:flex;gap:8px;align-items:flex-start;margin-bottom:12px}
.step-n{font-variant-numeric:tabular-nums;font-size:13px;font-weight:700;color:var(--accent3);line-height:1.35}
.step-title{font-size:13.5px;font-weight:600;color:var(--text)}
.step-sub{font-size:11.5px;color:var(--text3);margin-top:1px}

/* ── Who to consult ── */
.role-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}
.role-card{display:flex;flex-direction:column;gap:10px;text-align:left;padding:13px 14px;border:1.5px solid var(--border);
           border-radius:12px;background:var(--bg2);cursor:pointer;font-family:inherit;transition:border-color .15s,background .15s}
.role-card:hover:not(:disabled){border-color:var(--accent);background:var(--accent-light)}
.role-card.selected{border-color:var(--accent);background:var(--accent-light)}
.role-card.empty,.role-card:disabled{opacity:.55;cursor:not-allowed}
/* The person, large, on top; the kind of consultation, smaller, under them. */
.role-who{display:flex;align-items:center;gap:10px}
.role-who-avatar{width:40px;height:40px;border-radius:50%;background:var(--accent);color:#18191f;font-size:14px;font-weight:700;
                 display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative;overflow:hidden}
/* Their own photo over the initials; one that fails to load removes itself. */
.role-who-avatar img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
/* Nobody in this role yet: an empty circle the size of a photo, so the three
   cards still line up. */
.role-who-avatar.is-empty{background:none;border:1.5px dashed var(--border2)}
/* A long name wraps onto a second line rather than being cut off. */
.role-who-name{font-size:14px;font-weight:700;color:var(--text);line-height:1.3;overflow-wrap:anywhere}
.role-who-none{font-size:12px;color:var(--text3);font-style:italic}
/* The dot is the only thing saying which one is chosen, so it is not
   decoration - it carries the selection. */
.role-radio{width:15px;height:15px;border-radius:50%;border:1.5px solid var(--border2);flex-shrink:0;margin-left:auto}
.role-card.selected .role-radio{border-color:var(--accent);border-width:4.5px}
.role-kind{display:flex;align-items:center;gap:9px;padding-top:10px;border-top:1px solid var(--border)}
.role-ico{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.role-ico svg{width:15px;height:15px;fill:none;stroke-width:2;stroke:currentColor}
.role-ico-consultant{background:var(--blue-bg);color:var(--blue)}
.role-ico-adviser{background:var(--green-bg);color:var(--green)}
.role-ico-grammarian{background:var(--amber-bg);color:var(--amber)}
.role-kind-text{display:flex;flex-direction:column;gap:1px;min-width:0}
.role-name{font-size:12px;font-weight:600;color:var(--text2);line-height:1.3}
.role-blurb{font-size:11px;color:var(--text3);line-height:1.4}

/* ── Schedule ── */
.sched-note{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:9px;background:var(--blue-bg);
            border:1px solid rgba(29,78,216,0.18);color:var(--blue);font-size:11.5px;margin-bottom:14px}
.sched-note svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2;flex-shrink:0}
.sched-cols{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;align-items:start}
.sched-cal .calendar-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.sched-cal .calendar-header h3{font-size:13.5px;font-weight:600;color:var(--text)}
.cal-nav-group{display:flex;align-items:center;gap:4px}

.slots-head{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:10px;gap:8px}
.slots-title{font-size:13.5px;font-weight:600;color:var(--text)}
.slots-hint{font-size:11px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.slot-grid{display:block}
.slot-empty{grid-column:1/-1;font-size:11.5px;color:var(--text3);padding:18px 12px;text-align:center;
            border:1px dashed var(--border);border-radius:10px}

/* The times on offer, as a list. What a team is choosing between is the times
   themselves, so that is all this shows. */
.sd-list{display:flex;flex-direction:column;gap:8px;max-height:320px;overflow-y:auto;padding-right:2px}
.sd-slot{display:flex;flex-direction:column;gap:2px;align-items:flex-start;text-align:left;width:100%;
         padding:10px 12px;border:1.5px solid var(--border);border-radius:10px;background:var(--bg2);
         font-family:inherit;cursor:pointer;transition:border-color .15s,background-color .15s}
.sd-slot:hover{border-color:var(--accent);background:var(--accent-light)}
.sd-slot.picked{border-color:var(--accent);background:var(--accent-light)}
.sd-slot b{font-size:13px;font-weight:600;color:var(--text)}
.sd-slot span{font-size:11px;color:var(--text3)}

.book-actions{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
              padding-top:14px;border-top:1px solid var(--border)}
.book-note{display:flex;align-items:center;gap:6px;font-size:11.5px;color:var(--text3)}
.book-note svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;flex-shrink:0}
.book-confirm{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;font-size:12.5px;font-weight:600}
.book-confirm:disabled{opacity:.5;cursor:not-allowed}

.page-title-wrap{display:flex;flex-direction:column;min-width:0}
.page-subtitle{font-size:11.5px;color:var(--text3);margin-top:1px}

/* Booking rows read date-first, the way the mock does - the date is what a
   team scans for. */
.appt-row{display:flex;align-items:stretch;gap:12px}
.appt-date{display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;
           width:46px;padding:7px 0;border-radius:9px;background:var(--bg3);border:1px solid var(--border)}
.appt-date-m{font-size:9.5px;font-weight:700;letter-spacing:.5px;color:var(--text3);text-transform:uppercase}
.appt-date-d{font-size:17px;font-weight:700;color:var(--text);line-height:1.1}
.appt-date-w{font-size:9px;font-weight:600;color:var(--text3);text-transform:uppercase}
.appt-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:3px;justify-content:center}
.appt-time{font-size:12px;color:var(--text2);font-weight:500}
.appt-kind{font-size:13px;font-weight:600;color:var(--text)}
.appt-with{display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--text3)}
.appt-with svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2;flex-shrink:0}
.view-all{display:inline-flex;align-items:center;gap:4px;margin-top:10px;font-size:12px;font-weight:600;
          color:var(--accent3);background:none;border:none;cursor:pointer;font-family:inherit;padding:0}
.view-all svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2.5}

/* Calendar header reads  <  Month Year  >  the way the picker in the brief
   does: the month centred, the arrows either side of it. */
.sched-cal .calendar-header{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:4px 0 10px}
.sched-cal .calendar-header{max-width:300px}
.sched-cal .calendar-header h3{font-size:13.5px;font-weight:600;color:var(--text);text-align:center;flex:1}
.book-actions{justify-content:flex-end;border-top:none;padding-top:4px}
.book-note{justify-content:center;margin-top:10px;padding-top:12px;border-top:1px solid var(--border)}
.sched-cols{gap:26px}

/* The calendar is a date picker now, not an agenda: the times moved out to
   step 3, so the cells carry a number and nothing else. Scoped to .sched-cal
   so the shared .calendar-* rules keep serving whoever else uses them. */
.sched-cal .calendar-grid{background:transparent;border:none;border-radius:0;gap:1px;overflow:visible}
.sched-cal .day-label{background:transparent;color:var(--text3);font-size:10.5px;font-weight:600;
                      letter-spacing:.6px;padding:6px 0 8px}
/* Capped rather than stretched to the column: a date picker is a fixed,
   compact thing, and letting seven cells share a 380px column blew each one
   up to the size of an agenda cell. */
.sched-cal .calendar-grid{max-width:300px}
.sched-cal .calendar-day{height:36px;width:36px;margin:0 auto;padding:0;background:transparent;border-radius:50%;
                         display:flex;align-items:center;justify-content:center;font-size:12px;
                         font-weight:500;color:var(--text);overflow:visible}
.sched-cal .calendar-day .cal-date-num{line-height:1}
/* Times live in step 3, so the pills inside a cell would only repeat them. */
.sched-cal .cal-slots,.sched-cal .cal-slot-pill,.sched-cal .cal-more,.sched-cal .event-dot{display:none}

.sched-cal .calendar-day.other-month{color:var(--text3);opacity:.45}
.sched-cal .calendar-day.past{color:var(--text3);opacity:.4;cursor:default}
/* Available is the only state a plain picker needs to signal - the legend
   under the times says what the dot means. */
.sched-cal .calendar-day.today{box-shadow:inset 0 0 0 1.5px var(--border2)}
/* The colour that says a date can be booked: a gold ring on a pale gold
   disc. It used to come from the agenda calendar's 3px left border, which
   also bent the circle into a half-moon; taking that border off took the
   colour with it, and a bookable date was only bold. */
.sched-cal .calendar-day.available{color:var(--text);font-weight:700;cursor:pointer;
                                   background:var(--accent-light);box-shadow:inset 0 0 0 1.5px var(--accent)}
.sched-cal .calendar-day.available:hover{background:rgba(201,168,44,.28)}
/* A date the team already has a session on: blue, as in the legend. */
.sched-cal .calendar-day.my-booking{color:var(--blue);font-weight:700;background:var(--blue-bg);box-shadow:inset 0 0 0 2px var(--blue)}
.sched-cal .calendar-day.selected{background:var(--accent);color:#18191f;font-weight:700;box-shadow:none}
.sched-cal .calendar-day.selected:hover{background:var(--accent)}
.sched-cal .calendar-day.not-available{color:var(--text3);opacity:.5;cursor:not-allowed}

.col-head{font-size:12.5px;font-weight:600;color:var(--text);margin-bottom:10px}
.sched-cal .calendar-grid{max-width:340px}
.sched-cal .calendar-header{max-width:340px}
.sched-cal .calendar-day{height:44px;width:44px;font-size:13px}
/* The date picker's days stay round on a phone: the older agenda calendar's
   rules gave .available and .my-booking a 3px left border, and its phone
   heights (56px, then 34px) are marked !important, which squeezed the
   circles into half-moons and ovals. */
.sched-cal .calendar-day{border:0!important}
@media (max-width:768px){
  .sched-cal .calendar-day{height:44px!important;padding:0!important;font-size:13px!important}
  .sched-cal .cal-date-num{font-size:13px!important}
}

.book-body[hidden],.cal-notes[hidden]{display:none!important}
.book-empty{display:flex;gap:14px;align-items:flex-start;margin-top:18px;padding:16px;border:1px dashed var(--border2);border-radius:12px;background:var(--bg3)}
.book-empty-ic{width:40px;height:40px;flex:none;border-radius:10px;display:grid;place-items:center;background:var(--accent-light);color:var(--accent3)}
.book-empty-ic svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.book-empty-title{font-size:14px;font-weight:700;color:var(--text)}
.book-empty-text{margin:4px 0 10px;font-size:12.5px;line-height:1.5;color:var(--text2)}
.book-empty-link{display:inline-flex;align-items:center;min-height:36px;padding:0 14px;border-radius:9px;background:var(--accent);color:#18191f;font-size:13px;font-weight:600;text-decoration:none}
.book-empty-link:hover{background:var(--accent2)}

/* On a small phone the seven days share the card's width instead of being
   44px each - 314px of calendar in a 250px card ran off its right edge. */
@media (max-width:480px){
  .book-card{padding:16px 14px 14px}
  .sched-cal .calendar-grid{grid-template-columns:repeat(7,minmax(0,1fr))}
  .sched-cal .calendar-day{width:100%;max-width:40px;height:auto!important;aspect-ratio:1/1}
}

.appt-chev{width:15px;height:15px;fill:none;stroke:var(--text3);stroke-width:2.5;flex-shrink:0;align-self:center}
.appt-row{align-items:center}
/* Three at a time keeps the column readable; the link below opens the rest. */
.session-list:not(.show-all) > .session-list-item:nth-child(n+4){display:none}

/* This panel is its own thing now: a plain white card with the title in
   normal text, the way the brief has it - the shared .sessions-card gives it
   a navy bar and an uppercase gold-underlined heading, which belongs to the
   older pages still using it. */
.appt-sidebar .sessions-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;
                             padding:18px 18px 16px;box-shadow:none;overflow:visible}
.appt-sidebar .sessions-card-header{background:transparent;padding:0;border-bottom:none;margin-bottom:14px}
.appt-sidebar .sessions-card-title{font-size:15px;font-weight:700;color:var(--text);text-transform:none;letter-spacing:-0.2px}
.appt-sidebar .session-list{display:flex;flex-direction:column;gap:10px}
/* Each booking is a card in its own right, as the brief draws them. */
.appt-sidebar .session-list-item{background:var(--bg2);border:1px solid var(--border);border-radius:11px;
                                 padding:12px 13px;gap:8px}
.appt-sidebar .session-list-item-upcoming{border-color:var(--border)}
.appt-status-badge{align-self:flex-start;font-size:10px;font-weight:600;padding:3px 8px;border-radius:6px;
                   background:var(--bg4);color:var(--text3);white-space:nowrap}
.appt-status-badge-upcoming{background:var(--blue-bg);color:var(--blue)}
.session-list-item-past .appt-status-badge{background:var(--green-bg);color:var(--green)}
.appt-cancel-btn{margin-top:2px}

/* ── My Consultations ── */
.consult-tabs{display:flex;gap:18px;border-bottom:1px solid var(--border);margin-bottom:12px}
.consult-tab{position:relative;padding:0 0 9px;border:none;background:none;cursor:pointer;font-family:inherit;
             font-size:12.5px;font-weight:600;color:var(--text3);display:inline-flex;align-items:center;gap:6px}
.consult-tab.active{color:var(--accent3)}
.consult-tab.active::after{content:'';position:absolute;left:0;right:0;bottom:-1px;height:2px;background:var(--accent)}
.consult-tab-n{font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;background:var(--bg4);color:var(--text3)}
.consult-tab.active .consult-tab-n{background:var(--accent-light);color:var(--accent3)}

@media (max-width: 900px){
  .sched-cols{grid-template-columns:1fr}
}
  #consult-toast { position: fixed; bottom: 30px; right: 30px; background: var(--navy-dark); color: #fff; padding: 14px 22px; border-radius: 12px; font-size: 13px; font-weight: 500; z-index: 2000; box-shadow: 0 8px 30px rgba(0,0,0,0.3); opacity: 0; transform: translateY(12px); pointer-events: none; transition: opacity 0.25s ease, transform 0.25s ease; border-left: 4px solid transparent; display: flex; align-items: center; gap: 10px; }
  #consult-toast.show { opacity: 1; transform: translateY(0); }
  #consult-toast.success { border-left-color: var(--green); }
  #consult-toast.error { border-left-color: var(--red); }
</style>
@include('partials.skeleton-head', ['skReveal' => 'opacity'])
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Consultation</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'consultation'])
    <div id="contentWrapper" style="position: relative;">
<div id="skeleton-content" class="sk-overlay">
      <div class="consultation-container">
        <div class="left-panel">
          <div class="expert-filter-area">
            <div class="sk" style="width:110px;height:14px;border-radius:4px"></div>
            <div class="sk" style="flex:1;height:36px;border-radius:8px"></div>
          </div>
          <div class="calendar-card">
            <div class="sk" style="width:140px;height:16px;border-radius:4px;margin-bottom:14px"></div>
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px">
              @for ($i = 0; $i < 35; $i++)
              <div class="sk" style="height:60px;border-radius:6px"></div>
              @endfor
            </div>
          </div>
        </div>
        <div class="appt-sidebar">
          <div class="sessions-card">
            <div class="sessions-card-header">
              <div class="sk" style="width:130px;height:12px;border-radius:4px"></div>
            </div>
            <div style="padding:12px">
              <div class="sk" style="width:100%;height:60px;border-radius:8px"></div>
            </div>
          </div>
          <div class="sessions-card">
            <div class="sessions-card-header">
              <div class="sk" style="width:100px;height:12px;border-radius:4px"></div>
            </div>
            <div style="padding:12px">
              <div class="sk" style="width:100%;height:60px;border-radius:8px"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="real-content" style="opacity:0">
  <div class="consultation-container">

    <div class="left-panel">

      @php
        // One expert can hold more than one of these roles, so the picker is
        // built per role rather than per person - a team choosing "who do I
        // need" thinks in roles, not in names.
        $expertForRole = [];

        foreach ($experts as $e) {
            foreach (explode(',', mb_strtolower($e->role_name)) as $role) {
                $expertForRole[trim($role)] = $e;
            }
        }

        // Adviser, consultant, grammarian - the order the department names
        // them in, and the order a team engages them. The cards are drawn in
        // the order of this array, and it had the consultant first.
        $roleCards = [
            'adviser' => ['title' => 'Adviser Consultation', 'blurb' => 'Academic guidance and concerns'],
            'consultant' => ['title' => 'Consultant Consultation', 'blurb' => 'Capstone project review'],
            'grammarian' => ['title' => 'Grammarian Consultation', 'blurb' => 'Grammar & language review'],
        ];
      @endphp

      <div class="book-card">
        <div class="book-card-head">
          <h2 class="book-title">Book a Consultation</h2>
          <p class="book-sub">Fill in the details below to schedule your consultation.</p>
        </div>

        {{-- Nobody to book with yet: three empty expert cards, a calendar and a
             Confirm button offered a booking that could not be made. The steps
             stay in the page, hidden - its script reads them - and this says
             what to do instead. --}}
        @if ($experts->isEmpty())
          <div class="book-empty">
            <span class="book-empty-ic" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>
            <div>
              <div class="book-empty-title">No expert on your team yet</div>
              <p class="book-empty-text">Consultations are booked with your team's adviser, consultant or grammarian. Once one of them accepts your team, their free dates show here.</p>
              <a href="{{ route('student.experts') }}" class="book-empty-link">Find an expert</a>
            </div>
          </div>
        @endif

        <div class="book-body" @if ($experts->isEmpty()) hidden @endif>

        <div class="book-step">
          <div class="step-head">
            <span class="step-n">1.</span>
            <div>
              <div class="step-title">Who do you want to consult?</div>
              <div class="step-sub">Pick the type of consultation you need.</div>
            </div>
          </div>

          {{-- The select is what every piece of this page's JS already reads,
               so it stays as the source of truth and the cards drive it. --}}
          <select id="expertSelect" onchange="onExpertChange()" class="visually-hidden" aria-label="Consultation type">
            @if($experts->isEmpty())
              <option value="" disabled selected>No adviser/expert assigned to your team yet</option>
            @endif
            @foreach($experts as $expert)
              <option value="{{ $expert->expert_id }}" @selected($expert->expert_id === $defaultExpertId)>
                {{ $expert->first_name }} {{ $expert->last_name }} ({{ $expert->role_name }})
              </option>
            @endforeach
          </select>

          <div class="role-cards">
            @foreach($roleCards as $role => $card)
              @php $who = $expertForRole[$role] ?? null; @endphp
              <button type="button"
                      class="role-card {{ $who && $who->expert_id === $defaultExpertId ? 'selected' : '' }} {{ $who ? '' : 'empty' }}"
                      data-role="{{ $role }}"
                      @disabled(! $who)
                      onclick="pickRole(this, {{ $who?->expert_id ?? 'null' }})">
                {{-- The person first: a team is choosing who they will meet.
                     What kind of consultation that is sits under them. --}}
                <span class="role-who">
                  @if($who)
                    <span class="role-who-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($who->first_name, 0, 1).mb_substr($who->last_name, 0, 1)) }}@if($who->avatar)<img src="{{ $who->avatar }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">@endif</span>
                    <span class="role-who-name">{{ $who->first_name }} {{ $who->last_name }}</span>
                  @else
                    <span class="role-who-avatar is-empty" aria-hidden="true"></span>
                    <span class="role-who-none">No {{ $role }} assigned yet</span>
                  @endif
                  <span class="role-radio" aria-hidden="true"></span>
                </span>
                <span class="role-kind">
                  <span class="role-ico role-ico-{{ $role }}">
                    @if($role === 'consultant')
                      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    @elseif($role === 'adviser')
                      <svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>
                    @else
                      <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    @endif
                  </span>
                  <span class="role-kind-text">
                    <span class="role-name">{{ $card['title'] }}</span>
                    <span class="role-blurb">{{ $card['blurb'] }}</span>
                  </span>
                </span>
              </button>
            @endforeach
          </div>
        </div>

        <div class="book-step">
          <div class="step-head">
            <span class="step-n">2.</span>
            <div>
              <div class="step-title">Select an available schedule</div>
              <div class="step-sub">Choose from the available dates and times set by your expert.</div>
            </div>
          </div>

          <div class="sched-note">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <span>You can only book on the dates and times made available by your expert.</span>
          </div>

          <div class="sched-cols">
            <div class="sched-cal">
              <div class="col-head">Select Available Date</div>

              <div class="calendar-header">
                <button class="cal-btn" onclick="changeMonth(-1)" aria-label="Previous month"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg></button>
                <h3 id="cal-month">Select an Expert</h3>
                <div class="cal-nav-group">
                  <button class="cal-btn" id="consultRefreshBtn" title="Refresh availability and bookings">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><polyline points="21 3 21 9 15 9"/></svg>
                  </button>
                  <button class="cal-btn" onclick="changeMonth(1)" aria-label="Next month"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg></button>
                </div>
              </div>

              <div class="calendar-grid" id="calendar-grid">
                <div class="day-label">SUN</div><div class="day-label">MON</div><div class="day-label">TUE</div>
                <div class="day-label">WED</div><div class="day-label">THU</div><div class="day-label">FRI</div><div class="day-label">SAT</div>
              </div>

              <div class="step-sub" id="cal-hint" style="margin-top:8px">Choose an expert above to see which dates they are free.</div>
            </div>

            <div class="sched-slots">
              <div class="col-head">Available Time Slots</div>
              <div class="slot-grid" id="slot-grid">
                <div class="slot-empty">Choose a highlighted date to see its times.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="book-actions">
          <button type="button" class="btn-primary book-confirm" id="confirmBookingBtn" onclick="confirmBooking()" disabled>
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Confirm Booking
          </button>
        </div>

        <div class="book-note">
          <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
          A booking can't be cancelled once the session is within {{ \App\Http\Controllers\StudentConsultationController::CANCEL_CUTOFF_DAYS }} days.
        </div>
        </div>
      </div>

      <div class="cal-notes" @if ($experts->isEmpty()) hidden @endif>
        <h4 style="font-size: 11px; color: var(--text); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;">Consultation Legend & Guide</h4>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
          <div style="display: flex; flex-direction: column; gap: 4px;">
            <div class="note-item">
              <div style="width: 12px; height: 12px; background: var(--bg2); border: 1px solid var(--border); position: relative; overflow: hidden; border-radius: 3px; flex-shrink: 0;">
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top right, transparent 45%, var(--text3) 50%, transparent 55%); opacity: 0.5;"></div>
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top left, transparent 45%, var(--text3) 50%, transparent 55%); opacity: 0.5;"></div>
              </div>
              <span><strong>Unavailable:</strong> Expert is blocked or fully booked.</span>
            </div>
            <div class="note-item">
              <div style="width: 12px; height: 12px; background: #526D82; border-radius: 3px; opacity: 0.5; flex-shrink: 0;"></div>
              <span><strong>Past Dates:</strong> Previous dates (Dark Gray) are disabled.</span>
            </div>
          </div>

          <div style="display: flex; flex-direction: column; gap: 4px;">
            <div class="note-item">
              <span style="color: var(--accent2); font-weight: 700; font-size: 13px;">✔</span>
              <span><strong>Available:</strong> Click a date to book a slot.</span>
            </div>
            <div class="note-item">
              <div style="width: 8px; height: 8px; background: var(--blue-bg); box-shadow: inset 0 0 0 1.5px var(--blue); border-radius: 50%; flex-shrink: 0; margin-top: 3px;"></div>
              <span><strong>Your Booking:</strong> Pending appointments.</span>
            </div>
          </div>
        </div>

        <div style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--border); font-size: 10px; color: var(--text3); font-style: italic;">
          CapsTip: Select an expert, then click an available date to book a specific time slot.
        </div>
      </div>
    </div>

    @php
      // Same type→color mapping as instructor/schedule's renderUpcoming() typeColors,
      // so a slot's type badge looks identical on both pages.
      // What a booking is, read from its slot type. "consult" is a plain
      // consultation - put in front of "Consultation" it read "Consult
      // Consultation".
      $slotTypeNames = ['consult' => 'Consultation', 'docreview' => 'Document Review', 'defense' => 'Defense Consultation'];
      $slotTypeColors = [
        'consult'   => ['bg' => 'rgba(29,78,216,0.1)',  'color' => 'var(--blue)'],
        'docreview' => ['bg' => 'rgba(124,58,237,0.1)', 'color' => 'var(--purple)'],
        'defense'   => ['bg' => 'rgba(180,83,9,0.1)',   'color' => 'var(--amber)'],
      ];
    @endphp

    <div class="appt-sidebar">
      <div class="sessions-card">
        <div class="sessions-card-header">
          <h3 class="sessions-card-title">My Consultations</h3>
        </div>

        {{-- One card, two views. Upcoming is what a team acts on; past is
             what they look back at, and stacking both open pushed the
             second one off the screen. --}}
        <div class="consult-tabs" role="tablist">
          <button type="button" class="consult-tab active" data-tab="upcoming" role="tab" onclick="switchConsultTab('upcoming')">
            Upcoming <span class="consult-tab-n">{{ $upcomingBookings->count() }}</span>
          </button>
          <button type="button" class="consult-tab" data-tab="past" role="tab" onclick="switchConsultTab('past')">
            Past <span class="consult-tab-n">{{ $recentBookings->count() }}</span>
          </button>
        </div>

        <div class="consult-pane" data-pane="upcoming">
        <div id="upcoming-bookings-list" class="session-list">
          @forelse($upcomingBookings as $booking)
            @php $tc = $slotTypeColors[$booking->schedule->slot_type ?? 'consult'] ?? $slotTypeColors['consult']; @endphp
            <div class="session-list-item session-list-item-upcoming" data-booking-id="{{ $booking->booking_id }}">
              <div class="appt-row">
                <div class="appt-date">
                  <span class="appt-date-m">{{ $booking->session_date?->format('M') ?? '--' }}</span>
                  <span class="appt-date-d">{{ $booking->session_date?->format('j') ?? '--' }}</span>
                  <span class="appt-date-w">{{ $booking->session_date?->format('D') ?? '' }}</span>
                </div>
                <div class="appt-body">
                  <div class="appt-time">
                    {{ $booking->schedule?->start_time ? \Carbon\Carbon::parse($booking->schedule->start_time)->format('g:i A') : '—' }}
                    &ndash;
                    {{ $booking->schedule?->end_time ? \Carbon\Carbon::parse($booking->schedule->end_time)->format('g:i A') : '—' }}
                  </div>
                  <div class="appt-kind">{{ $slotTypeNames[$booking->schedule->slot_type ?? 'consult'] ?? 'Consultation' }}</div>
                  <div class="appt-with">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    {{ $booking->schedule->expert->first_name ?? 'Expert' }} {{ $booking->schedule->expert->last_name ?? '' }}
                  </div>
                </div>
                <span class="appt-status-badge appt-status-badge-upcoming">
                  {{ $booking->cancellation_requested_at ? 'Cancel requested' : ($booking->session_date && $booking->session_date->isToday() ? 'Today' : 'Scheduled') }}
                </span>
                <svg class="appt-chev" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
              </div>
              @unless ($booking->cancellation_requested_at)
              <button type="button" class="appt-cancel-btn" data-cancel-booking-id="{{ $booking->booking_id }}"
                      data-session-date="{{ $booking->session_date?->format('Y-m-d') }}">Cancel Booking</button>
              @endunless
            </div>
          @empty
            <div class="session-list-empty">No upcoming consultations.</div>
          @endforelse
        </div>
          @if($upcomingBookings->count() > 3)
            <button type="button" class="view-all" onclick="showAll(this, 'upcoming-bookings-list')">
              View all upcoming <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
            </button>
          @endif
        </div>

        <div class="consult-pane" data-pane="past" style="display:none">
        <div id="bookings-list" class="session-list">
          @forelse($recentBookings as $booking)
            @php $tc = $slotTypeColors[$booking->schedule->slot_type ?? 'consult'] ?? $slotTypeColors['consult']; @endphp
            <div class="session-list-item session-list-item-past">
              <div class="appt-row">
                <div class="appt-date">
                  <span class="appt-date-m">{{ $booking->session_date?->format('M') ?? '--' }}</span>
                  <span class="appt-date-d">{{ $booking->session_date?->format('j') ?? '--' }}</span>
                  <span class="appt-date-w">{{ $booking->session_date?->format('D') ?? '' }}</span>
                </div>
                <div class="appt-body">
                  <div class="appt-time">
                    {{ $booking->schedule?->start_time ? \Carbon\Carbon::parse($booking->schedule->start_time)->format('g:i A') : '—' }}
                    &ndash;
                    {{ $booking->schedule?->end_time ? \Carbon\Carbon::parse($booking->schedule->end_time)->format('g:i A') : '—' }}
                  </div>
                  <div class="appt-kind">{{ $slotTypeNames[$booking->schedule->slot_type ?? 'consult'] ?? 'Consultation' }}</div>
                  <div class="appt-with">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    {{ $booking->schedule->expert->first_name ?? 'Expert' }} {{ $booking->schedule->expert->last_name ?? '' }}
                  </div>
                </div>
                <span class="appt-status-badge">Ended</span>
                <svg class="appt-chev" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
              </div>
            </div>
          @empty
            <div class="session-list-empty">No past consultations.</div>
          @endforelse
        </div>

        </div>
      </div>
    </div>
  </div>

  <div class="legal-credits">
    <div class="credits-title">System Developers</div>
    <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
  </div>
    </div>
</div>
</div>

<div class="modal-overlay" id="slotSelectModal" onclick="if(event.target === this) closeSlotModal()">
  <div class="modal-content">
    <div style="margin-bottom: 20px;">
      <h2 style="font-size: 18px; font-weight: 600;">Select a Time Slot</h2>
      <p style="font-size: 11px; color: var(--text3);" id="slot-modal-date">Choose an available time for this date.</p>
    </div>
    <div id="slot-modal-list"></div>
    <div class="modal-footer">
      <button class="btn-ghost content" onclick="closeSlotModal()" style="margin-top:8px">Cancel</button>
    </div>
  </div>
</div>

<div id="cancelBookingModal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:1000;display:none;align-items:center;justify-content:center;box-sizing:border-box;">
  <div id="cancelBookingModalBox" style="background:var(--bg2);width:450px;max-width:90vw;border-radius:20px;padding:30px;box-shadow:0 20px 40px rgba(0,0,0,0.2);box-sizing:border-box;">
    <div style="margin-bottom:20px;">
      <h2 style="font-size:18px;font-weight:600;margin:0;">Cancel Booking</h2>
      <p style="font-size:11px;color:var(--text3);margin:6px 0 0;">Let the expert know why you're cancelling this session.</p>
    </div>
    <textarea id="cancelReasonInput" rows="4" placeholder="Reason for cancellation..." style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);color:var(--text);font-size:13px;box-sizing:border-box;resize:vertical;font-family:inherit;"></textarea>
    <div id="cancelReasonError" style="font-size:11px;color:var(--red);margin-top:6px;display:none;">Please enter a reason.</div>
    <div style="display:flex;gap:10px;margin-top:20px;">
      <button type="button" id="cancelBookingBackBtn" style="flex:1;padding:12px 16px;font-size:13px;font-weight:600;border-radius:10px;border:1.5px solid var(--border);background:transparent;color:var(--text2);box-sizing:border-box;">Back</button>
      <button type="button" id="cancelBookingConfirmBtn" style="flex:1;padding:12px 16px;font-size:13px;font-weight:700;border-radius:10px;border:none;background:var(--accent);color:#18191f;box-sizing:border-box;">Confirm Cancellation</button>
    </div>
  </div>
</div>

<div id="consult-toast"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (sk && rc) {
    // partials/skeleton-head already decided this in <head>, before the first
    // paint: the content is on screen and no skeleton was ever shown. Without
    // this, every save replayed the 600ms fade over content already there.
    if (document.documentElement.classList.contains('sk-seen')) {
      sk.remove();

      return;
    }

    requestAnimationFrame(function () {
      setTimeout(function () {
        sk.style.transition = 'opacity 0.3s ease';
        sk.style.opacity = '0';
        rc.style.transition = 'opacity 0.35s ease';
        rc.style.opacity = '1';
        setTimeout(function () {
          sk.remove();
          rc.style.transition = '';
        }, 350);
        try {
          sessionStorage.setItem('sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname, '1');
        } catch (e) {}
      }, 600);
    });
  }
});

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const DAY_NAMES = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

let expertSlots = @json($availableSlots);
// This student's own scheduled sessions, by date and which expert they were
// booked with — drives the "Your Booking" dot. Kept per-expert (not just a
// flat list of dates) so switching the Target Expert dropdown doesn't show
// another expert's booking history as if it belonged to the one selected.
let myBookedDates = @json($upcomingBookings->merge($recentBookings)->filter(fn ($b) => $b->session_date)->map(fn ($b) => [
    'date' => $b->session_date->format('Y-m-d'),
    'expert_id' => $b->schedule?->expert_id,
])->values());
// One active booking per team at a time — mirrors the server-side check in
// StudentConsultationController::book(), so clicking a date short-circuits
// with a clear toast instead of opening the modal and failing on submit.
// The experts this team already has a session waiting with. One live
// booking per expert, not one per team - a team due to see their adviser on
// Thursday can still book their consultant for Friday.
let bookedExpertIds = @json($bookedExpertIds).map(Number);
const slotTypeColors = @json($slotTypeColors);
const slotTypeNames = @json($slotTypeNames);

// Weekly/biweekly slots share a single schedule row across every future
// occurrence of that weekday, so "is this slot booked out" has to be tracked
// per (schedule_id, date) rather than on the row itself — otherwise booking
// one Monday would hide every other Monday too. "schedule_id|YYYY-MM-DD"
// entries here mean that specific occurrence is full.
let fullOccurrences = new Set(@json($fullOccurrences));

// Each expert's scheduling window: { earliest: 'YYYY-MM-DD HH:MM', latest: 'YYYY-MM-DD' | null }.
// An expert with no saved availability has none, and every slot stays offered.
let bookingWindows = @json($bookingWindows ?? (object) []);

function withinWindow(expertId, dateKey, startTime) {
  const w = bookingWindows[expertId];
  if (!w) return true;
  // Both sides are 'YYYY-MM-DD HH:MM', so they compare correctly as text.
  if (dateKey + ' ' + String(startTime || '00:00').slice(0, 5) < w.earliest) return false;
  return !w.latest || dateKey <= w.latest;
}

function isOccurrenceFull(scheduleId, dateKey) {
  return fullOccurrences.has(scheduleId + '|' + dateKey);
}

function getSlotsForExpert(expertId) {
  if (!expertId) return [];
  return expertSlots.filter(s => s.expert_id == expertId);
}

// A "once" slot is only ever valid on its specific calendar date — matching
// it by weekday name (like weekly/biweekly slots) would make it recur
// forever, showing as available on that weekday in every future month.
function matchesDate(s, dayName, dateKey) {
  if (s.recur === 'once') {
    return s.specific_date === dateKey;
  }
  return s.available_date === dayName;
}

function getSlotsForDate(expertId, dayName, dateKey) {
  return getSlotsForExpert(expertId).filter(s => matchesDate(s, dayName, dateKey) && s.is_available
    && !isOccurrenceFull(s.schedule_id, dateKey) && withinWindow(expertId, dateKey, s.start_time));
}

function formatTime(t) {
  if (!t) return '';
  const [h, m] = t.split(':').map(Number);
  const ampm = h < 12 ? 'AM' : 'PM';
  const h12 = h % 12 || 12;
  return h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;
}

// How long the expert set this slot to run, e.g. "45 mins · ". Read from
// the slot's own times - sessions are not all the same length.
function slotLength(slot) {
  if (!slot.start_time || !slot.end_time) return '';
  const toMin = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  const mins = toMin(slot.end_time) - toMin(slot.start_time);
  if (mins <= 0) return '';
  const text = mins % 60 === 0 ? (mins / 60) + (mins === 60 ? ' hr' : ' hrs') : mins + ' mins';
  return text + ' · ';
}

function renderCalendar() {
  const grid = document.getElementById('calendar-grid');
  const monthLabel = document.getElementById('cal-month');
  const hint = document.getElementById('cal-hint');

  // The month is drawn whether or not an expert has been picked. It used to
  // stop here and render nothing but the seven weekday headings, which read
  // as a calendar that had failed to load rather than one waiting for a
  // choice. With no expert chosen every lookup below finds no slots, so the
  // dates come out plain - the month is there to look at, and picking an
  // expert fills in their availability.
  const expertId = document.getElementById('expertSelect').value;

  monthLabel.textContent = MONTHS[currentMonth] + ' ' + currentYear;
  hint.textContent = expertId
    ? 'Click an available date to book a slot.'
    : 'Choose an expert above to see which of these dates they are free.';

  const slots = getSlotsForExpert(expertId);

  const firstDay = new Date(currentYear, currentMonth, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
  const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();

  const today = new Date();
  const todayKey = today.getFullYear() + '-' + String(today.getMonth()+1).padStart(2,'0') + '-' + String(today.getDate()).padStart(2,'0');

  let html = '<div class="day-label">SUN</div><div class="day-label">MON</div><div class="day-label">TUE</div><div class="day-label">WED</div><div class="day-label">THU</div><div class="day-label">FRI</div><div class="day-label">SAT</div>';

  for (let i = firstDay - 1; i >= 0; i--) {
    html += '<div class="calendar-day other-month">' + (daysInPrevMonth - i) + '</div>';
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const dateObj = new Date(currentYear, currentMonth, d);
    const dayName = DAY_NAMES[dateObj.getDay()];
    const dateKey = currentYear + '-' + String(currentMonth+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
    const isToday = dateKey === todayKey;
    const isPast = dateObj < new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const daySlots = getSlotsForDate(expertId, dayName, dateKey);
    const hasAvailableSlot = daySlots.length > 0;
    const hasBookedSlot = slots.some(s => matchesDate(s, dayName, dateKey) && (!s.is_available || isOccurrenceFull(s.schedule_id, dateKey)));
    const isMyBooking = myBookedDates.some(b => b.date === dateKey && String(b.expert_id) === String(expertId));

    // Matches the legend exactly: not-available (X pattern) for fully
    // booked/blocked days, available for open ones, past for prior dates.
    // A date the student booked themselves takes priority over
    // not-available — that slot IS unavailable to other students, but to
    // the person who booked it, showing an X/blocked pattern read as if
    // something had gone wrong rather than "you're booked here."
    let cls = 'calendar-day';
    // Held here rather than on the element, because every refresh rebuilds
    // this grid from scratch and would drop a class set by hand.
    if (dateKey === selectedDateKey) cls += ' selected';
    if (isPast) cls += ' past';
    else if (isMyBooking) cls += ' my-booking';
    else if (hasBookedSlot && !hasAvailableSlot) cls += ' not-available';
    else if (hasAvailableSlot) cls += ' available';
    if (isToday) cls += ' today';

    let cellContent = '<div class="cal-date-num">' + d + '</div>';

    if (hasAvailableSlot && !isPast) {
      const slotsHtml = daySlots.slice(0, 2).map(s =>
        '<div class="cal-slot-pill">' + formatTime(s.start_time) + ' – ' + formatTime(s.end_time) + '</div>'
      ).join('');
      const moreHtml = daySlots.length > 2 ? '<div class="cal-more">+' + (daySlots.length - 2) + '</div>' : '';
      cellContent += '<div class="cal-slots">' + slotsHtml + moreHtml + '</div>';
    }

    if (isMyBooking) {
      cellContent += '<div class="event-dot" title="You have a booking on this date"></div>';
    }

    if (hasAvailableSlot && !isPast) {
      html += '<div class="' + cls + '" onclick="selectDate(' + d + ', \'' + dayName + '\', \'' + dateKey + '\')">' + cellContent + '</div>';
    } else if (hasBookedSlot && !isPast) {
      html += '<div class="' + cls + '" title="Fully booked">' + cellContent + '</div>';
    } else {
      html += '<div class="' + cls + '">' + cellContent + '</div>';
    }
  }

  const totalCells = firstDay + daysInMonth;
  const remainder = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
  for (let d = 1; d <= remainder; d++) {
    html += '<div class="calendar-day other-month">' + d + '</div>';
  }

  grid.innerHTML = html;
}

function changeMonth(delta) {
  currentMonth += delta;
  if (currentMonth < 0) { currentMonth = 11; currentYear--; }
  if (currentMonth > 11) { currentMonth = 0; currentYear++; }
  renderCalendar();
}

function onExpertChange() {
  selectedSlot = null;
  selectedDateKey = null;
  updateConfirmButton();

  const grid = document.getElementById('slot-grid');
  if (grid) grid.innerHTML = '<div class="slot-empty">Choose a highlighted date on the calendar to see its times.</div>';


  currentMonth = new Date().getMonth();
  currentYear = new Date().getFullYear();
  renderCalendar();
}

let currentSlotModalDateKey = null;
let selectedSlot = null;
let selectedDateKey = null;

function selectDate(d, dayName, dateKey) {
  const expertId = document.getElementById('expertSelect').value;

  // A date always opens, the booked one included - whether this team may
  // book is asked when they confirm, not by refusing to show the day.
  if (!expertId) {
    showToast('Choose who you want to consult first.', 'error');
    return;
  }

  const daySlots = getSlotsForDate(expertId, dayName, dateKey);
  if (daySlots.length === 0) {
    return;
  }

  currentSlotModalDateKey = dateKey;
  selectedDateKey = dateKey;
  selectedSlot = null;
  updateConfirmButton();
  renderCalendar();

  // The slot column is headed by the brief's own label, so the picked date
  // is not repeated there - the calendar already shows which one is chosen.
  const when = document.getElementById('slots-date');
  if (when) {
    when.textContent = DAY_NAMES[new Date(currentYear, currentMonth, d).getDay()] + ', ' + MONTHS[currentMonth] + ' ' + d;
  }

  const grid = document.getElementById('slot-grid');
  grid.innerHTML = '';

  renderDayGrid(daySlots, dateKey);
}

// The times on offer, earliest first. A timeline spent most of its height on
// hours with nothing in them.
function renderDayGrid(daySlots, dateKey) {
  const grid = document.getElementById('slot-grid');
  grid.innerHTML = '';

  if (!daySlots.length) {
    grid.innerHTML = '<div class="slot-empty">No open times on this date.</div>';
    return;
  }

  const list = document.createElement('div');
  list.className = 'sd-list';

  [...daySlots]
    .sort((a, b) => String(a.start_time).localeCompare(String(b.start_time)))
    .forEach(slot => {
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'sd-slot';
      row.innerHTML = '<b>' + formatTime(slot.start_time) + ' – ' + formatTime(slot.end_time) + '</b>'
        + '<span>' + slotLength(slot) + ucfirst(slot.slot_type || 'consultation') + '</span>';
      row.onclick = () => pickSlot(row, slot, dateKey);
      forceInteractive(row);
      list.appendChild(row);
    });

  grid.appendChild(list);
}

/** Choosing a time arms the Confirm button; it does not book on its own. */
function pickSlot(btn, slot, dateKey) {
  document.querySelectorAll('#slot-grid .sd-slot').forEach(b => b.classList.remove('picked'));
  btn.classList.add('picked');

  selectedSlot = { scheduleId: slot.schedule_id, dateKey: dateKey };
  updateConfirmButton();
}

function updateConfirmButton() {
  const btn = document.getElementById('confirmBookingBtn');
  if (btn) btn.disabled = selectedSlot === null;
}

function confirmBooking() {
  if (!selectedSlot) {
    showToast('Pick a time first.', 'error');
    return;
  }

  const expertId = document.getElementById('expertSelect').value;
  const who = document.querySelector('#expertSelect option:checked')?.textContent?.trim() || 'this expert';

  // The date the team already has with this expert: say that, not the
  // general "one session at a time" rule.
  if (myBookedDates.some(b => b.date === selectedSlot.dateKey && String(b.expert_id) === String(expertId))) {
    showToast('You already booked this date with ' + who + '. See it under Upcoming.', 'error');
    return;
  }

  if (bookedExpertIds.includes(Number(expertId))) {
    showToast('Your team already has a session booked with ' + who + '. You can book them again once that one is done.', 'error');
    return;
  }

  currentSlotModalDateKey = selectedSlot.dateKey;
  bookSlot(selectedSlot.scheduleId);
}

/** The role cards drive the select every other part of this page reads. */
function pickRole(card, expertId) {
  if (!expertId) return;

  document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
  card.classList.add('selected');

  document.getElementById('expertSelect').value = expertId;
  onExpertChange();
}

/** The list shows three; the link reveals the rest rather than paging away. */
function showAll(btn, listId) {
  document.getElementById(listId)?.classList.add('show-all');
  btn.remove();
}

function switchConsultTab(which) {
  document.querySelectorAll('.consult-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === which));
  document.querySelectorAll('.consult-pane').forEach(pane => {
    pane.style.display = pane.dataset.pane === which ? '' : 'none';
  });
}

function closeSlotModal() {
  document.getElementById('slotSelectModal').style.display = 'none';
}

function ucfirst(s) {
  return s.charAt(0).toUpperCase() + s.slice(1);
}

function formatDateLabel(dateKey) {
  const [y, m, d] = dateKey.split('-').map(Number);
  return MONTHS[m - 1].slice(0, 3) + ' ' + String(d).padStart(2, '0') + ', ' + y;
}

function showToast(message, type) {
  if (window.ctToast) { return ctToast(message, type); }
  type = type || 'success';
  const toast = document.getElementById('consult-toast');
  if (!toast) { return; }
  toast.textContent = (type === 'error' ? '✕ ' : '✓ ') + message;
  toast.classList.remove('success', 'error');
  toast.classList.add('show', type);
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toast.classList.remove('show'), 4000);
}

function todayKeyString() {
  const today = new Date();
  return today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
}

// Shared by prependUpcomingBooking (one new card, right after booking) and
// renderBookingLists (a full server-refreshed set) so both stay identical to
// each other and to the Blade-rendered markup on first page load.
function bookingCardHtml(booking, isUpcoming) {
  const tc = slotTypeColors[booking.slot_type] || slotTypeColors['consult'];
  const dateKey = booking.session_date;
  const dateLabel = dateKey ? formatDateLabel(dateKey) : (booking.available_date || '—');
  const statusBadge = isUpcoming
    ? '<span class="appt-status-badge appt-status-badge-upcoming">' + (booking.cancel_requested ? 'Cancel requested' : (dateKey === todayKeyString() ? 'Today' : 'Scheduled')) + '</span>'
    : '<span class="appt-status-badge">Ended</span>';
  const cancelBtn = isUpcoming && !booking.cancel_requested
    ? '<button type="button" class="appt-cancel-btn" data-cancel-booking-id="' + booking.booking_id
        + '" data-session-date="' + (booking.session_date || '') + '">Cancel Booking</button>'
    : '';

  // Same shape the page renders on the server, or a refresh would quietly
  // swap every row for a differently-built one.
  const d = dateKey ? new Date(dateKey + 'T00:00:00') : null;
  const month = d ? MONTHS[d.getMonth()].slice(0, 3).toUpperCase() : '--';
  const day = d ? d.getDate() : '--';
  const weekday = d ? DAY_NAMES[d.getDay()].slice(0, 3).toUpperCase() : '';
  const endTime = booking.end_time ? ' &ndash; ' + formatTime(booking.end_time) : '';

  return `
    <div class="appt-row">
      <div class="appt-date">
        <span class="appt-date-m">${month}</span>
        <span class="appt-date-d">${day}</span>
        <span class="appt-date-w">${weekday}</span>
      </div>
      <div class="appt-body">
        <div class="appt-time">${formatTime(booking.start_time)}${endTime}</div>
        <div class="appt-kind">${slotTypeNames[booking.slot_type || 'consult'] || 'Consultation'}</div>
        <div class="appt-with">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          ${booking.expert_name}
        </div>
      </div>
      ${statusBadge}
    </div>
    ${cancelBtn}
  `;
}

function prependUpcomingBooking(booking, dateKey) {
  const list = document.getElementById('upcoming-bookings-list');
  const empty = list.querySelector('.session-list-empty');
  if (empty) { empty.remove(); }

  const item = document.createElement('div');
  item.className = 'session-list-item session-list-item-upcoming';
  item.dataset.bookingId = booking.booking_id;
  item.innerHTML = bookingCardHtml(Object.assign({}, booking, {session_date: dateKey}), true);
  list.prepend(item);

  const count = document.querySelector('#upcoming-bookings-list').closest('.sessions-card').querySelector('.sessions-card-count');
  if (count) { count.textContent = String(Number(count.textContent || '0') + 1); }
}

// Fully rebuilds both booking lists from freshly fetched server data - used
// after a poll/manual refresh, so a booking someone else on the team made or
// cancelled (or that just finished) shows up without a page reload.
function renderBookingLists(upcoming, recent) {
  const upcomingList = document.getElementById('upcoming-bookings-list');
  const recentList = document.getElementById('bookings-list');

  upcomingList.innerHTML = upcoming.length
    ? upcoming.map(b => '<div class="session-list-item session-list-item-upcoming" data-booking-id="' + b.booking_id + '">' + bookingCardHtml(b, true) + '</div>').join('')
    : '<div class="session-list-empty">No upcoming consultations.</div>';

  recentList.innerHTML = recent.length
    ? recent.map(b => '<div class="session-list-item session-list-item-past" data-booking-id="' + b.booking_id + '">' + bookingCardHtml(b, false) + '</div>').join('')
    : '<div class="session-list-empty">No past consultations.</div>';

  const upcomingCount = upcomingList.closest('.sessions-card').querySelector('.sessions-card-count');
  if (upcomingCount) { upcomingCount.textContent = String(upcoming.length); }
  const recentCount = recentList.closest('.sessions-card').querySelector('.sessions-card-count');
  if (recentCount) { recentCount.textContent = String(recent.length); }
}

let consultRefreshInFlight = false;

async function refreshConsultationData(showFeedback) {
  if (consultRefreshInFlight) { return; }
  consultRefreshInFlight = true;

  const refreshBtn = document.getElementById('consultRefreshBtn');
  if (refreshBtn) { refreshBtn.style.opacity = '0.5'; }

  try {
    // The 30-second background poll is passive: it must not keep an idle
    // session alive. The Refresh button is someone at the page.
    const res = await fetch('{{ route("student.consultation.refresh") }}', {
      headers: showFeedback ? { 'Accept': 'application/json' } : { 'Accept': 'application/json', 'X-Idle-Passive': '1' },
    });
    if (!res.ok) { throw new Error('Refresh failed (' + res.status + ')'); }
    const data = await res.json();

    expertSlots = data.availableSlots;
    if (data.bookingWindows) bookingWindows = data.bookingWindows;
    fullOccurrences = new Set(data.fullOccurrences || []);
    bookedExpertIds = (data.bookedExpertIds || []).map(Number);
    myBookedDates = data.myBookedDates;

    renderCalendar();
    renderBookingLists(data.upcomingBookings, data.recentBookings);

    if (showFeedback) { showToast('Consultation data refreshed.', 'success'); }
  } catch (e) {
    if (showFeedback) { showToast('Could not refresh: ' + e.message, 'error'); }
  } finally {
    consultRefreshInFlight = false;
    if (refreshBtn) { refreshBtn.style.opacity = '1'; }
  }
}

document.getElementById('consultRefreshBtn')?.addEventListener('click', function () {
  refreshConsultationData(true);
});

// Polls in the background so a new slot the expert adds, or a booking
// someone else on the team makes/cancels, appears without a manual reload.
// Paused while the tab is hidden so it doesn't fire fetches into a
// backgrounded/inactive tab.
setInterval(function () {
  if (document.visibilityState === 'visible') { refreshConsultationData(false); }
}, 30000);

// Coming back to the tab (e.g. from the expert's window) shouldn't wait for
// the next 30-second tick to show what changed while it was hidden.
document.addEventListener('visibilitychange', function () {
  if (document.visibilityState === 'visible') { refreshConsultationData(false); }
});

async function bookSlot(scheduleId) {
  const dateKey = currentSlotModalDateKey;
  try {
    const res = await fetch('{{ route("student.consultation.book") }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        schedule_id: scheduleId,
        session_date: dateKey,
      }),
    });

    const data = await res.json();
    if (data.success) {
      closeSlotModal();

      fullOccurrences = new Set(data.fullOccurrences || []);
      const bookedExpertId = document.getElementById('expertSelect').value;

      // Only this expert is now spoken for; the others stay bookable.
      if (! bookedExpertIds.includes(Number(bookedExpertId))) {
        bookedExpertIds.push(Number(bookedExpertId));
      }

      if (dateKey && ! myBookedDates.some(b => b.date === dateKey && String(b.expert_id) === String(bookedExpertId))) {
        myBookedDates.push({ date: dateKey, expert_id: bookedExpertId });
      }
      if (data.newBooking && dateKey) {
        prependUpcomingBooking(Object.assign({}, data.newBooking, {booking_id: data.booking_id}), dateKey);
      }
      renderCalendar();

      showToast('Booking request submitted successfully!', 'success');
    } else {
      showToast(data.message || 'Failed to book.', 'error');
    }
  } catch (e) {
    showToast('Error: ' + e.message, 'error');
  }
}

let cancelBookingId = null;

// Forces an element to actually be interactive, overriding anything
// external that may have set pointer-events/cursor/disabled on it.
function forceInteractive(el) {
  if (!el) return;
  el.disabled = false;
  el.removeAttribute('disabled');
  el.removeAttribute('readonly');
  el.readOnly = false;
  el.style.setProperty('pointer-events', 'auto', 'important');
  el.style.setProperty('user-select', 'auto', 'important');
}

const CANCEL_CUTOFF_DAYS = {{ \App\Http\Controllers\StudentConsultationController::CANCEL_CUTOFF_DAYS }};

/**
 * Whole days between today and the session, counted the way the server
 * counts them.
 */
function daysUntil(sessionDate) {
  if (!sessionDate) return null;

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const when = new Date(sessionDate + 'T00:00:00');
  if (isNaN(when)) return null;

  return Math.round((when - today) / 86400000);
}

/**
 * Why this booking cannot be called off, or null when it can.
 *
 * Asked before the dialog opens. The server refuses a late cancellation
 * either way, but it used to be the only thing that knew - so the student
 * wrote out a reason, sent it, and was told then that they could not cancel
 * at all. The date is on the button; there is no reason to make them type
 * first to find out.
 */
function cancelBlockedReason(sessionDate) {
  const days = daysUntil(sessionDate);

  if (days === null || days >= CANCEL_CUTOFF_DAYS) return null;

  return 'This consultation is in ' + (days <= 0 ? 'less than a day' : days + ' day' + (days === 1 ? '' : 's'))
    + '. It can only be cancelled more than ' + CANCEL_CUTOFF_DAYS
    + ' days ahead - message your expert instead.';
}

function openCancelModal(bookingId) {
  cancelBookingId = bookingId;
  const modal = document.getElementById('cancelBookingModal');
  const box = document.getElementById('cancelBookingModalBox');
  const reasonInput = document.getElementById('cancelReasonInput');
  const reasonError = document.getElementById('cancelReasonError');
  const backBtn = document.getElementById('cancelBookingBackBtn');
  const confirmBtn = document.getElementById('cancelBookingConfirmBtn');

  reasonInput.value = '';
  reasonError.style.display = 'none';

  [modal, box, reasonInput, backBtn, confirmBtn].forEach(forceInteractive);

  modal.style.setProperty('display', 'flex', 'important');
  modal.style.setProperty('opacity', '1', 'important');
  modal.style.setProperty('visibility', 'visible', 'important');
  reasonInput.style.setProperty('cursor', 'text', 'important');
  backBtn.style.setProperty('cursor', 'pointer', 'important');
  confirmBtn.style.setProperty('cursor', 'pointer', 'important');

  reasonInput.focus();
}

function closeCancelModal() {
  document.getElementById('cancelBookingModal').style.display = 'none';
  cancelBookingId = null;
}

// Bound via addEventListener (not inline onclick attributes) since inline
// handlers can be silently stripped by some browsers/content filters with
// no console error at all - this delegated listener is indistinguishable
// from any other page script to that kind of filtering.
document.addEventListener('click', function (event) {
  const cancelBtn = event.target.closest('.appt-cancel-btn');
  if (cancelBtn) {
    const blocked = cancelBlockedReason(cancelBtn.dataset.sessionDate);

    if (blocked) {
      showToast(blocked, 'error');

      return;
    }

    openCancelModal(cancelBtn.dataset.cancelBookingId);
    return;
  }

  if (event.target.id === 'cancelBookingBackBtn') {
    closeCancelModal();
    return;
  }

  if (event.target.id === 'cancelBookingConfirmBtn') {
    submitCancelBooking();
    return;
  }

  if (event.target.id === 'cancelBookingModal') {
    closeCancelModal();
  }
});

async function submitCancelBooking() {
  const reasonInput = document.getElementById('cancelReasonInput');
  const reason = reasonInput.value.trim();
  if (!reason) {
    document.getElementById('cancelReasonError').style.display = 'block';
    return;
  }

  const btn = document.getElementById('cancelBookingConfirmBtn');
  btn.disabled = true;

  try {
    const res = await fetch('{{ url("/student/consultation/cancel") }}/' + cancelBookingId, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
      },
      body: JSON.stringify({ reason: reason }),
    });

    const data = await res.json();
    if (data.success) {
      closeCancelModal();
      showToast('Cancellation requested. Your expert will approve or decline it.', 'success');
      refreshConsultationData(false);
    } else {
      showToast(data.message || 'Failed to cancel booking.', 'error');
    }
  } catch (e) {
    showToast('Error: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
  }
}

renderCalendar();

// -- Mobile Sidebar --
function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
    document.body.style.overflow = document.body.classList.contains('sidebar-open') ? 'hidden' : '';
}
function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.nav-item').forEach(function(el) {
    el.addEventListener('click', function() {
        if (window.innerWidth <= 768) closeSidebar();
    });
});
</script>
@include('partials.escape-closes-modals')
</body>
</html>
