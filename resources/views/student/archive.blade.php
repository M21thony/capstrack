<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Archived Capstones - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
@vite('resources/student-ui/styles.css')
<x-dashboard.styles />
<style>
  @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
  .sk-overlay { position: absolute; inset: 0; background: var(--bg); z-index: 10; }
  .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }

  /* ── Manuscript modal. Built on .modal-overlay so Escape closes it, with
     the image-zoom look that class carries turned off. ── */
  #manuscript-modal { background: rgba(24,25,31,.55); cursor: default; padding: 20px; overflow-y: auto; align-items: flex-start; }
  .ms-card { width: min(620px, 100%); margin: auto; background: #fff; border-radius: 16px; border-top: 5px solid var(--accent); box-shadow: 0 24px 70px rgba(0,0,0,.28); overflow: hidden; }
  .ms-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 20px 24px 14px; border-bottom: 1px solid var(--border); }
  .ms-kicker { font-size: 10.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--accent3); }
  .ms-head h2 { margin: 4px 0 0; font-size: 17px; font-weight: 700; color: var(--text); }
  .ms-x { width: 32px; height: 32px; flex: none; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border); border-radius: 8px; background: #fff; color: var(--text3); cursor: pointer; }
  .ms-x:hover { border-color: var(--accent); color: var(--text); }
  .ms-body { padding: 18px 24px 6px; display: flex; flex-direction: column; }
  .ms-auto { display: grid; grid-template-columns: repeat(3, 1fr); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; margin-bottom: 4px; }
  .ms-auto div { padding: 9px 12px; background: var(--bg3); border-right: 1px solid var(--border); display: grid; gap: 2px; }
  .ms-auto div:last-child { border-right: 0; }
  .ms-auto span { font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text3); }
  .ms-auto b { font-size: 13px; color: var(--text); }
  .ms-label { margin: 14px 0 5px; font-size: 12px; font-weight: 600; color: var(--text); }
  .ms-label em { font-style: normal; font-weight: 400; color: var(--text3); margin-left: 4px; }
  .ms-hint { margin-top: 5px; font-size: 11px; color: var(--text3); }
  .ms-body textarea { resize: vertical; line-height: 1.55; }
  .ms-tags { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; min-height: 42px; padding: 6px 8px; border: 1px solid var(--border); border-radius: 8px; background: #fff; cursor: text; }
  .ms-tags:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
  .ms-tags input { flex: 1; min-width: 120px; border: 0; outline: 0; font: inherit; font-size: 13px; padding: 4px; background: transparent; color: var(--text); }
  .ms-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 4px 3px 10px; border-radius: 999px; background: var(--accent-glow); border: 1px solid rgba(201,168,44,.35); font-size: 12px; font-weight: 600; color: var(--text); }
  .ms-chip button { width: 18px; height: 18px; border: 0; border-radius: 50%; background: transparent; color: var(--text3); cursor: pointer; font-size: 14px; line-height: 1; }
  .ms-chip button:hover { background: rgba(0,0,0,.08); color: var(--red); }
  .ms-error { margin-bottom: 10px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-size: 12px; }
  .ms-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 24px; margin-top: 14px; border-top: 1px solid var(--border); background: var(--bg3); }
  @media (max-width: 560px) {
    #manuscript-modal { padding: 8px; }
    .ms-head, .ms-body, .ms-foot { padding-left: 16px; padding-right: 16px; }
    .ms-auto { grid-template-columns: 1fr; }
    .ms-auto div { border-right: 0; border-bottom: 1px solid var(--border); }
    .ms-auto div:last-child { border-bottom: 0; }
  }
  /* Flash messages from the manuscript form - the page redirects back, so the
     only place a refusal can be read is here. */
  #archive-toast { position: fixed; bottom: 30px; right: 30px; background: var(--navy-dark); color: #fff; padding: 14px 22px; border-radius: 12px; font-size: 13px; font-weight: 500; z-index: 2000; box-shadow: 0 8px 30px rgba(0,0,0,0.3); opacity: 0; transform: translateY(12px); pointer-events: none; transition: opacity 0.25s ease, transform 0.25s ease; border-left: 4px solid transparent; display: flex; align-items: center; gap: 10px; max-width: 380px; }
  #archive-toast.show { opacity: 1; transform: translateY(0); }
  #archive-toast.success { border-left-color: var(--green); }
  #archive-toast.error { border-left-color: var(--red); }
</style>
@include('partials.skeleton-head', ['skReveal' => 'opacity'])
</head>
<body>
@include('student.partials.sidebar')
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Archived Capstones</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'archive'])
    <div id="contentWrapper" style="position: relative;">
<div id="skeleton-content" class="sk-overlay">
      <div class="row" style="margin-bottom:16px;gap:8px;flex-wrap:wrap">
        <div class="sk" style="flex:1;min-width:200px;max-width:380px;height:36px;border-radius:8px"></div>
        <div class="sk" style="width:120px;height:36px;border-radius:8px"></div>
        <div class="sk" style="width:160px;height:36px;border-radius:8px"></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
        @for ($i = 0; $i < 6; $i++)
        <div class="archive-card">
          <div class="sk" style="width:35%;height:10px;border-radius:4px;margin-bottom:8px"></div>
          <div class="sk" style="width:90%;height:14px;border-radius:4px;margin-bottom:10px"></div>
          <div class="sk" style="width:100%;height:11px;border-radius:4px;margin-bottom:4px"></div>
          <div class="sk" style="width:75%;height:11px;border-radius:4px;margin-bottom:12px"></div>
          <div class="sk" style="width:55%;height:18px;border-radius:10px"></div>
        </div>
        @endfor
      </div>
    </div>

    <div id="real-content" style="opacity:0">
{{-- The finished team's manuscript. Shown only once the journey is over, so
     it never competes with the archive itself for a team still working. --}}
@php $sub = $submission; @endphp
@if ($sub['team'] && ($sub['isFinished'] || $sub['existing']))
<div style="background:#fff;border:1.5px solid var(--accent);border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04)">
  <div class="row" style="justify-content:space-between;align-items:flex-start;gap:12px">
    <div>
      <div style="font-size:13px;font-weight:700;color:var(--text)">Submit your Capstone Manuscript</div>
      <div style="font-size:12px;color:var(--text3);margin-top:2px">
        Your team has completed its capstone journey. Hand in the final manuscript and it goes into the archive once it has been reviewed.
      </div>
    </div>
    @if ($sub['existing'])
      <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;white-space:nowrap;
        {{ $sub['existing']->submission_status === \App\Models\ArchivedDocument::PENDING ? 'background:var(--accent-glow);color:var(--text)' : 'background:rgba(34,153,84,0.12);color:var(--green)' }}">
        {{ $sub['existing']->submission_status === \App\Models\ArchivedDocument::PENDING ? 'Pending review' : 'In the archive' }}
      </span>
    @endif
  </div>

  @if ($sub['existing'])
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);font-size:12px;color:var(--text2);line-height:1.6">
      <div><strong style="color:var(--text)">{{ $sub['existing']->title }}</strong></div>
      <div>
        Handed in {{ $sub['existing']->submitted_at?->format('M j, Y') }}
        @if ($sub['existing']->file_name)
          &middot; {{ $sub['existing']->file_name }}
        @endif
      </div>
      @if ($sub['existing']->submission_status === \App\Models\ArchivedDocument::PENDING)
        <div style="color:var(--text3);margin-top:4px">Your CaPSA or the admin has to approve it before it appears in the archive. Only one manuscript per team.</div>
      @endif
    </div>
  @elseif (! $sub['isLeader'])
    {{-- Only the leader hands it in, so the rest of the team is told who to
         ask rather than shown a form that would refuse them. --}}
    <div style="margin-top:10px;font-size:12px;color:var(--text3)">
      Your team leader submits the manuscript for the team.
    </div>
  @else
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div style="font-size:12px;color:var(--text3);line-height:1.6">
        Title, tags, school year and date are filled in for you. Just add the abstract.
      </div>
      <button type="button" class="btn btn-primary" id="open-manuscript" onclick="openManuscriptModal()" style="padding:9px 16px;white-space:nowrap">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Submit to Archive
      </button>
    </div>
  @endif
</div>
@endif

@if ($sub['team'] && $sub['isFinished'] && $sub['isLeader'] && ! $sub['existing'])
{{-- The manuscript form. Title and tags come from the team's approved
     proposal and can be edited; the school year and date are the system's. --}}
<div class="modal-overlay" id="manuscript-modal" onclick="if (event.target === this) closeManuscriptModal()">
  <div class="ms-card" role="dialog" aria-modal="true" aria-labelledby="ms-heading">
    <div class="ms-head">
      <div>
        <div class="ms-kicker">Capstone Archive</div>
        <h2 id="ms-heading">Submit your Capstone Manuscript</h2>
      </div>
      <button type="button" class="ms-x" aria-label="Close" onclick="closeManuscriptModal()">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <form method="POST" action="{{ route('student.archive.manuscript.submit') }}" id="manuscript-form">
      @csrf
      <div class="ms-body">
        @if ($errors->any())
          <div class="ms-error">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
          </div>
        @endif

        {{-- Set by the system from the team's enrolment and today's date. --}}
        <div class="ms-auto">
          <div><span>School Year</span><b>S.Y. {{ $sub['schoolYear'] }}</b></div>
          <div><span>Date</span><b>{{ $sub['date'] }}</b></div>
          <div><span>Program</span><b>{{ $sub['program'] ?: 'CCS' }}</b></div>
        </div>

        <label class="ms-label" for="ms-title">Capstone Title <em>filled in from your approved title</em></label>
        <input class="input" id="ms-title" name="title" value="{{ old('title', $sub['defaultTitle']) }}" maxlength="255" required />

        <label class="ms-label" for="ms-tag-input">Tags <em>from your proposal &middot; press Enter to add</em></label>
        <div class="ms-tags" id="ms-tags" onclick="document.getElementById('ms-tag-input').focus()">
          <input id="ms-tag-input" list="ms-tag-options" maxlength="50" placeholder="Add a tag..." autocomplete="off" />
        </div>
        <datalist id="ms-tag-options">
          @foreach ($tags as $tag)<option value="{{ $tag->tag_name }}">@endforeach
        </datalist>
        <div class="ms-hint">New tags become archive categories for the capstones after yours. Up to 10.</div>

        <label class="ms-label" for="ms-abstract">Abstract</label>
        <textarea class="input" id="ms-abstract" name="abstract" required rows="8" placeholder="Paste the abstract of your final manuscript...">{{ old('abstract') }}</textarea>
        <div class="ms-hint">Recorded as {{ $sub['authors'] }}.</div>
      </div>

      <div class="ms-foot">
        <button type="button" class="btn" onclick="closeManuscriptModal()" style="background:#fff;border:1px solid var(--border);color:var(--text2)">Cancel</button>
        <button type="submit" class="btn btn-primary" id="ms-submit">Submit Manuscript</button>
      </div>
    </form>
  </div>
</div>
@endif

<div class="row" style="margin-bottom:16px;gap:8px;flex-wrap:wrap">
  <input class="input" id="archive-search" placeholder="Search by title or keyword..." style="flex:1;min-width:200px;max-width:380px" oninput="filterArchive()" />
  <select class="input" id="archive-year" style="width:120px" onchange="filterArchive()">
    <option value="">All Years</option>
    @foreach($archives->pluck('academic_year')->unique()->sort() as $year)
    <option>{{ $year }}</option>
    @endforeach
  </select>
  <select class="input" id="archive-tag" style="width:160px" onchange="filterArchive()">
    <option value="">All Categories</option>
    @foreach($tags as $tag)
    <option value="{{ $tag->tag_name }}">{{ $tag->tag_name }}</option>
    @endforeach
  </select>
  <span style="margin-left:auto;font-size:12px;color:var(--text3)" id="archive-count">Showing {{ $archives->count() }} studies</span>
</div>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px" id="archive-grid">
  @foreach($archives as $archive)
  <div class="archive-card"
    data-tags="{{ $archive->tags->pluck('tag_name')->implode(',') }}"
    data-year="{{ $archive->academic_year }}"
    data-title="{{ strtolower($archive->title) }}">
    <div class="archive-year">{{ $archive->academic_year ?? 'N/A' }}</div>
    <div class="archive-title">{{ $archive->title }}</div>
    <div class="archive-proponents">{{ $archive->abstract ? Str::limit($archive->abstract, 80) : 'No description' }}</div>
    <div class="archive-tags">
      @foreach($archive->tags as $tag)
      <span class="archive-tag" style="background:{{ $tag->tag_color ?? 'var(--accent-glow)' }};color:var(--text1)">{{ $tag->tag_name }}</span>
      @endforeach
    </div>
    <div class="archive-footer">
      <span style="font-size:11px;color:var(--text3)">{{ $archive->program ?? 'N/A' }} &middot; CCS</span>
    </div>
  </div>
  @endforeach
</div>
<div id="archive-empty" style="display:none;text-align:center;padding:48px;color:var(--text3);font-size:13px">No archived capstones match your search.</div>
    </div>
  </div>
</div>
</div>

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

// ── Search / year / tag ───────────────────────────────────────────────────
// The three controls called this and it was never written, so every keystroke
// in the search box threw and nothing was ever filtered.
function filterArchive() {
  var q = (document.getElementById('archive-search').value || '').toLowerCase();
  var year = document.getElementById('archive-year').value;
  var tag = document.getElementById('archive-tag').value;
  var cards = document.querySelectorAll('#archive-grid .archive-card');
  var shown = 0;

  cards.forEach(function (card) {
    var title = (card.dataset.title || '').toLowerCase();
    var tags = card.dataset.tags || '';
    var match = (! q || title.indexOf(q) !== -1)
      && (! year || card.dataset.year === year)
      && (! tag || tags.indexOf(tag) !== -1);

    card.style.display = match ? '' : 'none';
    if (match) shown++;
  });

  var count = document.getElementById('archive-count');
  if (count) count.textContent = 'Showing ' + shown + ' studies';

  var empty = document.getElementById('archive-empty');
  if (empty) empty.style.display = shown === 0 ? 'block' : 'none';
}

// ── Manuscript modal ──────────────────────────────────────────────────────
@if ($sub['team'] && $sub['isFinished'] && $sub['isLeader'] && ! $sub['existing'])
var MAX_TAGS = 10;
var manuscriptTags = [];

function openManuscriptModal() {
  document.getElementById('manuscript-modal').classList.add('active');
  document.body.style.overflow = 'hidden';
  document.getElementById('ms-abstract').focus();
}

function closeManuscriptModal() {
  document.getElementById('manuscript-modal').classList.remove('active');
  document.body.style.overflow = '';
}

// One chip, and one hidden tags[] field, per tag. Text only: a tag is typed.
function renderManuscriptTags() {
  var box = document.getElementById('ms-tags');
  var input = document.getElementById('ms-tag-input');
  box.querySelectorAll('.ms-chip').forEach(function (el) { el.remove(); });

  manuscriptTags.forEach(function (tag, i) {
    var chip = document.createElement('span');
    chip.className = 'ms-chip';
    chip.appendChild(document.createTextNode(tag));

    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'tags[]';
    hidden.value = tag;
    chip.appendChild(hidden);

    var remove = document.createElement('button');
    remove.type = 'button';
    remove.setAttribute('aria-label', 'Remove ' + tag);
    remove.textContent = '×';
    remove.onclick = function (e) { e.stopPropagation(); manuscriptTags.splice(i, 1); renderManuscriptTags(); };
    chip.appendChild(remove);

    box.insertBefore(chip, input);
  });

  input.disabled = manuscriptTags.length >= MAX_TAGS;
  input.placeholder = input.disabled ? 'Up to 10 tags' : 'Add a tag...';
}

function addManuscriptTag(raw) {
  var tag = (raw || '').replace(/\s+/g, ' ').trim();
  if (!tag || manuscriptTags.length >= MAX_TAGS) return;
  var exists = manuscriptTags.some(function (t) { return t.toLowerCase() === tag.toLowerCase(); });
  if (!exists) manuscriptTags.push(tag.slice(0, 50));
  renderManuscriptTags();
}

(function () {
  var input = document.getElementById('ms-tag-input');

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      addManuscriptTag(input.value);
      input.value = '';
    } else if (e.key === 'Backspace' && !input.value && manuscriptTags.length) {
      manuscriptTags.pop();
      renderManuscriptTags();
    }
  });
  // Picking a suggestion from the list adds it straight away.
  input.addEventListener('change', function () {
    if (input.value) { addManuscriptTag(input.value); input.value = ''; }
  });

  // A tag still being typed when Submit is pressed is kept, not dropped.
  document.getElementById('manuscript-form').addEventListener('submit', function () {
    if (input.value.trim()) { addManuscriptTag(input.value); input.value = ''; }
    var btn = document.getElementById('ms-submit');
    btn.disabled = true;
    btn.textContent = 'Submitting…';
  });

  var initialTags = @json(old('tags', $sub['defaultTags']));
  initialTags.forEach(addManuscriptTag);
  renderManuscriptTags();

  // A refused submission comes back to this page: reopen the form on it.
  @if ($errors->any())
    openManuscriptModal();
  @endif
})();
@endif

// ── Flash messages ────────────────────────────────────────────────────────
function showToast(message, type) {
  var toast = document.getElementById('archive-toast');
  if (! toast) return;

  toast.textContent = (type === 'error' ? '✕ ' : '✓ ') + message;
  toast.classList.remove('success', 'error');
  toast.classList.add('show', type);
  clearTimeout(showToast._t);
  showToast._t = setTimeout(function () { toast.classList.remove('show'); }, 4000);
}

@if (session('success'))
  document.addEventListener('DOMContentLoaded', function () { showToast(@json(session('success')), 'success'); });
@endif
@if (session('error'))
  document.addEventListener('DOMContentLoaded', function () { showToast(@json(session('error')), 'error'); });
@endif
@if ($errors->any())
  document.addEventListener('DOMContentLoaded', function () { showToast(@json($errors->first()), 'error'); });
@endif
</script>

@include('partials.escape-closes-modals')
<div id="archive-toast"></div>
</body>
</html>
