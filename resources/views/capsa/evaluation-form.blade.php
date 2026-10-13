<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>{{ $template ? 'Edit evaluation' : 'New evaluation' }} - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
@include('partials.evaluation-styles')
  @include('partials.render-whole')
</head>
<body>

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
@php
  $oldQuestions = old('questions', $questions);
  $oldQuestions = $oldQuestions ?: [''];
  $pickedRoles = old('roles', $roles);
  $pickedStage = old('defense_type', $stage);
  $max = \App\Http\Controllers\Capsa\EvaluationTemplateController::MAX_QUESTIONS;
@endphp
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Expert Evaluations</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <main class="content">
    <div class="ev-page">
      <a class="ev-back" href="{{ route('capsa.evaluations') }}" data-leave><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>Expert Evaluations</a>
      <div class="ev-head">
        <div>
          <h1>{{ $template ? 'Edit evaluation' : 'Create new evaluation' }}</h1>
          <p>Team leaders answer every question with <b>1 to 5 stars</b> after their team passes this defense. The expert's rating is the average of the answers.</p>
        </div>
      </div>

      @if($errors->any())
        <div class="ev-flash is-bad" role="alert" data-flash>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
          <span>{{ $errors->first() }}</span>
          <button type="button" class="ev-flash-x" data-flash-close aria-label="Dismiss"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
      @endif

      <div class="ev-compose">
        <form class="ev-form" id="ev-form" method="POST" action="{{ $template ? route('capsa.evaluations.update', $template) : route('capsa.evaluations.store') }}" novalidate>
          @csrf
          @if($template) @method('PUT') @endif

          <section class="ev-step-sec" aria-labelledby="ev-s1">
            <h2 class="ev-step-h" id="ev-s1"><span>1</span>The defense</h2>
            <div class="ev-field">
              <label for="ev-stage">After which defense</label>
              @if($template)
                <input class="ev-input" id="ev-stage" value="{{ $template->defense_type }}" readonly aria-describedby="ev-stage-hint">
                <span class="ev-hint" id="ev-stage-hint">The defense cannot change once the evaluation exists. Create a new one for another defense.</span>
              @else
                <select class="ev-input" id="ev-stage" name="defense_type" required>
                  @foreach(\App\Models\EvaluationTemplate::STAGES as $s)
                    <option value="{{ $s }}" @selected($pickedStage === $s)>{{ $s }}</option>
                  @endforeach
                </select>
              @endif
              @error('defense_type')<span class="ev-err">{{ $message }}</span>@enderror
            </div>
            <div class="ev-field">
              <label for="ev-title">Title</label>
              <input class="ev-input" id="ev-title" name="title" maxlength="150" required value="{{ old('title', $title) }}" placeholder="e.g. Title Defense expert evaluation" aria-describedby="ev-title-err">
              <span class="ev-err" id="ev-title-err" data-err="title">@error('title'){{ $message }}@enderror</span>
            </div>
          </section>

          <fieldset class="ev-step-sec" aria-describedby="ev-roles-hint">
            <legend class="ev-step-h"><span>2</span>Who it is about</legend>
            <div class="ev-roles">
              @foreach(\App\Models\ExpertEvaluation::ROLES as $key => $label)
                <label class="r-{{ $key }}"><input type="checkbox" name="roles[]" value="{{ $key }}" @checked(in_array($key, (array) $pickedRoles, true))><span class="ev-role-dot" aria-hidden="true"></span>{{ $label }}</label>
              @endforeach
            </div>
            <span class="ev-hint" id="ev-roles-hint">The leader answers these questions once for each expert in the roles you pick.</span>
            <span class="ev-err" data-err="roles">@error('roles'){{ $message }}@enderror</span>
          </fieldset>

          <section class="ev-step-sec" aria-labelledby="ev-s3">
            <div class="ev-step-row">
              <h2 class="ev-step-h" id="ev-s3"><span>3</span>Questions</h2>
              <span class="ev-hint" id="ev-count" aria-live="polite"></span>
            </div>
            <div class="ev-scale" aria-label="The scale">
              @foreach(\App\Models\EvaluationTemplate::SCALE as $n => $word)<span><b>{{ $n }}★</b>{{ $word }}</span>@endforeach
            </div>
            <ol class="ev-qlist" id="ev-qlist" aria-labelledby="ev-s3">
              @foreach($oldQuestions as $i => $q)
                <li class="ev-qrow">
                  <span class="ev-qnum" aria-hidden="true">{{ $i + 1 }}</span>
                  <textarea class="ev-input" name="questions[]" rows="1" maxlength="300" aria-label="Question {{ $i + 1 }}" placeholder="Write a statement the leader rates from 1 to 5">{{ $q }}</textarea>
                  <span class="ev-qacts">
                    <button type="button" class="ev-ibtn" data-move="-1" aria-label="Move up" title="Move up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg></button>
                    <button type="button" class="ev-ibtn" data-move="1" aria-label="Move down" title="Move down"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
                    <button type="button" class="ev-ibtn is-danger" data-remove aria-label="Remove" title="Remove"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                  </span>
                </li>
              @endforeach
            </ol>
            <span class="ev-err" data-err="questions">@error('questions'){{ $message }}@enderror @error('questions.*'){{ $message }}@enderror</span>
            <div class="ev-qtools">
              <button type="button" class="ev-btn sm" id="ev-add"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add question</button>
              <button type="button" class="ev-btn sm" id="ev-standard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>Use the 5 standard questions</button>
              <span class="ev-hint ev-kbd">Press <kbd>Enter</kbd> for the next question</span>
            </div>
          </section>

          <div class="ev-form-foot">
            <a class="ev-btn" href="{{ route('capsa.evaluations') }}" data-leave>Cancel</a>
            <button type="submit" class="ev-btn gold">{{ $template ? 'Save changes' : 'Create evaluation' }}</button>
          </div>
        </form>

        {{-- What the leader will see, kept in step with the form. --}}
        <aside class="ev-preview" aria-label="What team leaders see">
          <div class="ev-sub">What team leaders see</div>
          <div class="ev-pv-card">
            <div class="ev-pv-head">
              {{-- Stands in for whichever expert the leader is evaluating. --}}
              <span class="ev-pv-av" aria-hidden="true">
                <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="20" class="ev-pv-av-bg"/><circle cx="20" cy="15.2" r="6.6" class="ev-pv-av-fg"/><path d="M6.6 34.8C8.5 28.4 13.7 24.6 20 24.6s11.5 3.8 13.4 10.2A19.94 19.94 0 0 1 20 40a19.94 19.94 0 0 1-13.4-5.2z" class="ev-pv-av-fg"/></svg>
              </span>
              <div class="ev-pv-who"><b id="ev-pv-role">Your adviser</b><span id="ev-pv-stage">{{ $template?->defense_type ?? $pickedStage }} passed</span></div>
            </div>
            <ol class="ev-pv-qs" id="ev-pv-qs"></ol>
            <p class="ev-pv-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Anonymous to you and the expert.</p>
          </div>
        </aside>
      </div>
    </div>
  </main>
</div>

<div class="ev-snack" id="ev-snack" role="status" hidden><span>Question removed.</span><button type="button" id="ev-undo">Undo</button></div>

<template id="ev-qrow">
  <li class="ev-qrow">
    <span class="ev-qnum" aria-hidden="true"></span>
    <textarea class="ev-input" name="questions[]" rows="1" maxlength="300" placeholder="Write a statement the leader rates from 1 to 5"></textarea>
    <span class="ev-qacts">
      <button type="button" class="ev-ibtn" data-move="-1" aria-label="Move up" title="Move up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg></button>
      <button type="button" class="ev-ibtn" data-move="1" aria-label="Move down" title="Move down"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
      <button type="button" class="ev-ibtn is-danger" data-remove aria-label="Remove" title="Remove"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </span>
  </li>
</template>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var MAX = {{ $max }};
  var STANDARD = @json(\App\Models\EvaluationTemplate::STANDARD_QUESTIONS);
  var form = document.getElementById('ev-form');
  var list = document.getElementById('ev-qlist');
  var tpl = document.getElementById('ev-qrow');
  var add = document.getElementById('ev-add');
  var count = document.getElementById('ev-count');
  var pv = document.getElementById('ev-pv-qs');
  var star = '<svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
  var initial = null;
  var removed = null;
  var snackTimer = null;

  function rows() { return list.querySelectorAll('.ev-qrow'); }
  function grow(t) { t.style.height = 'auto'; t.style.height = t.scrollHeight + 2 + 'px'; }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function snapshot() { return new URLSearchParams(new FormData(form)).toString(); }
  function dirty() { return initial !== null && snapshot() !== initial; }

  // Numbers, labels, the limit and the preview follow every change.
  function sync() {
    var all = rows();
    all.forEach(function (row, i) {
      row.querySelector('.ev-qnum').textContent = i + 1;
      row.querySelector('textarea').setAttribute('aria-label', 'Question ' + (i + 1));
      row.querySelector('[data-move="-1"]').disabled = i === 0;
      row.querySelector('[data-move="1"]').disabled = i === all.length - 1;
      row.querySelector('[data-remove]').disabled = all.length === 1;
    });
    add.disabled = all.length >= MAX;
    count.textContent = all.length + ' of ' + MAX + ' questions';
    var texts = Array.prototype.map.call(list.querySelectorAll('textarea'), function (t) { return t.value.trim(); }).filter(Boolean);
    pv.innerHTML = texts.length
      ? texts.map(function (q) { return '<li><span>' + esc(q) + '</span><span class="ev-pv-stars">' + star + star + star + star + star + '</span></li>'; }).join('')
      : '<li class="ev-pv-empty">Your questions appear here.</li>';
  }
  function addRow(text, before) {
    var row = tpl.content.firstElementChild.cloneNode(true);
    var t = row.querySelector('textarea');
    t.value = text || '';
    list.insertBefore(row, before || null);
    grow(t);
    return t;
  }
  function clearErr(name) { var el = form.querySelector('[data-err="' + name + '"]'); if (el) { el.textContent = ''; } }
  function showErr(name, msg) { var el = form.querySelector('[data-err="' + name + '"]'); if (el) { el.textContent = msg; } }

  add.addEventListener('click', function () { if (rows().length < MAX) { addRow('').focus(); sync(); } });
  list.addEventListener('click', function (e) {
    var move = e.target.closest('[data-move]');
    var row = e.target.closest('.ev-qrow');
    if (move && row) {
      var other = move.dataset.move === '-1' ? row.previousElementSibling : row.nextElementSibling;
      if (other) { move.dataset.move === '-1' ? list.insertBefore(row, other) : list.insertBefore(other, row); }
      sync();
      move.disabled ? row.querySelector('textarea').focus() : move.focus();
      return;
    }
    var btn = e.target.closest('[data-remove]');
    if (!btn || rows().length === 1) { return; }
    var next = row.nextElementSibling || row.previousElementSibling;
    // Gone at once, with a moment to take it back.
    removed = { row: row, before: row.nextElementSibling };
    row.remove();
    if (next) { next.querySelector('textarea').focus(); }
    sync();
    var snack = document.getElementById('ev-snack');
    snack.hidden = false;
    clearTimeout(snackTimer);
    snackTimer = setTimeout(function () { snack.hidden = true; removed = null; }, 5000);
  });
  document.getElementById('ev-undo').addEventListener('click', function () {
    if (!removed) { return; }
    list.insertBefore(removed.row, removed.before && removed.before.isConnected ? removed.before : null);
    removed.row.querySelector('textarea').focus();
    removed = null;
    document.getElementById('ev-snack').hidden = true;
    sync();
  });
  list.addEventListener('input', function (e) { if (e.target.tagName === 'TEXTAREA') { grow(e.target); clearErr('questions'); sync(); } });
  // Enter adds the next question instead of a line break.
  list.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey && e.target.tagName === 'TEXTAREA') {
      e.preventDefault();
      if (rows().length < MAX) { addRow('', e.target.closest('.ev-qrow').nextElementSibling).focus(); sync(); }
    }
  });
  document.getElementById('ev-standard').addEventListener('click', function () {
    var written = Array.prototype.some.call(list.querySelectorAll('textarea'), function (t) { return t.value.trim() !== ''; });
    var go = written
      ? window.confirmDialog({ title: 'Use the standard questions?', body: 'The questions you wrote here are replaced with the 5 standard ones.', ok: 'Replace them', tone: 'warn' })
      : Promise.resolve(true);
    go.then(function (yes) {
      if (!yes) { return; }
      list.innerHTML = '';
      STANDARD.forEach(function (q) { addRow(q); });
      clearErr('questions');
      sync();
    });
  });
  form.querySelector('#ev-title').addEventListener('input', function () { clearErr('title'); });
  form.addEventListener('change', function (e) { if (e.target.name === 'roles[]') { clearErr('roles'); } });
  var stageSelect = form.querySelector('select[name="defense_type"]');
  if (stageSelect) {
    stageSelect.addEventListener('change', function () { document.getElementById('ev-pv-stage').textContent = stageSelect.value + ' passed'; });
  }
  // The preview speaks of the first role picked: "Your consultant".
  function syncRole() {
    var picked = form.querySelector('input[name="roles[]"]:checked');
    document.getElementById('ev-pv-role').textContent = 'Your ' + (picked ? picked.value : 'expert');
  }
  form.addEventListener('change', function (e) { if (e.target.name === 'roles[]') { syncRole(); } });
  syncRole();

  // Said here, before the trip to the server.
  form.addEventListener('submit', function (e) {
    var first = null;
    var title = form.querySelector('#ev-title');
    if (!title.value.trim()) { showErr('title', 'Give the evaluation a title.'); first = first || title; }
    if (!form.querySelector('input[name="roles[]"]:checked')) { showErr('roles', 'Pick who this evaluation is about.'); first = first || form.querySelector('input[name="roles[]"]'); }
    var texts = Array.prototype.map.call(list.querySelectorAll('textarea'), function (t) { return t.value.trim().toLowerCase(); }).filter(Boolean);
    if (!texts.length) { showErr('questions', 'Write at least one question.'); first = first || list.querySelector('textarea'); }
    else if (new Set(texts).size !== texts.length) { showErr('questions', 'Two questions are the same.'); first = first || list.querySelector('textarea'); }
    if (first) {
      e.preventDefault();
      first.focus();
      first.scrollIntoView({ block: 'center', behavior: 'smooth' });
      return;
    }
    initial = null;
  });

  // Leaving with changes not saved asks first.
  document.querySelectorAll('[data-leave]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (!dirty()) { return; }
      e.preventDefault();
      window.confirmDialog({ title: 'Leave without saving?', body: 'Your changes to this evaluation are not saved yet.', ok: 'Leave', cancel: 'Keep editing', tone: 'warn' })
        .then(function (yes) { if (yes) { initial = null; window.location.href = a.href; } });
    });
  });

  list.querySelectorAll('textarea').forEach(grow);
  sync();
  initial = snapshot();
})();
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() { if (sidebarEl) { sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); } }
function openSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeSidebar() { if (!sidebarEl || !sidebarOverlay) return; sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open'); document.body.style.overflow = ''; }
</script>
@include('partials.evaluation-page-script')
@include('partials.confirm-dialog')
@include('partials.app-scrollbar')
</body>
</html>
