@php
  $rejectAttempts = (int) ($accountRequest->reject_attempts ?? 0);
  $prefillEmail = request('email', old('email', $accountRequest->email ?? ''));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Request an expert account — CapsTrack</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
  /* ══════════════════════════════════════════════════════════════
     Request an expert account.

     A rail names the three things being asked and how far along you
     are; the questions themselves sit beside it. Two of the three
     are skippable, and the Skip is a button of its own in the footer
     rather than a word inside a sentence - a skip nobody sees is not
     a skip.

     Self-contained, like login.blade.php: this is a public page, and
     admin.css is written for the signed-in shell.
     ══════════════════════════════════════════════════════════════ */
  *{box-sizing:border-box;margin:0;padding:0}
  :root{
    --bg:#f2f3f5;--bg2:#ffffff;--bg3:#f7f8fa;--bg4:#eceef2;
    --border:#e0e2e8;--border2:#c8cad4;
    --text:#1e2128;--text2:#4a5060;--text3:#8a909e;
    --navy:#18191f;--navy-mid:#1e2028;
    --accent:#c9a82c;--accent2:#b5941f;--accent3:#8a6e10;--accent-light:#faf7ed;
    --green:#16a34a;--green-bg:rgba(22,163,74,.07);
    --amber:#b45309;--amber-bg:rgba(180,83,9,.07);
    --red:#dc2626;--red-bg:rgba(220,38,38,.07);
  }
  body{font-family:'DM Sans',system-ui,sans-serif;background:var(--bg);color:var(--text);
    font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased;
    min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 20px}

  .ra-shell{width:100%;max-width:760px}
  .ra-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;
    box-shadow:0 1px 4px rgba(0,0,0,.05);overflow:hidden}

  /* The wordmark the sign-in panel uses. No badge, no monogram. */
  .ra-head{display:flex;align-items:center;gap:12px;padding:15px 22px;background:var(--navy)}
  .ra-wordmark{font-size:15px;font-weight:700;letter-spacing:.02em;color:#fff}
  .ra-wordmark span{color:var(--accent)}
  .ra-head-right{margin-left:auto;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;
    color:rgba(255,255,255,.5)}

  .ra-grid{display:grid;grid-template-columns:206px 1fr}

  .ra-rail{border-right:1px solid var(--border);background:var(--bg3);padding:22px 0 20px}
  .ra-railitem{display:flex;gap:11px;padding:10px 20px;font-size:13px;color:var(--text3);
    border-left:2px solid transparent;width:100%;text-align:left;background:none;border-top:0;
    border-right:0;border-bottom:0;font-family:inherit;cursor:pointer}
  .ra-railitem .rn{font-size:11px;font-weight:600;padding-top:2px;width:14px;flex-shrink:0}
  .ra-railitem .rl{display:block}
  .ra-railitem .ro{display:block;font-size:11px;font-weight:400;color:var(--text3)}
  .ra-railitem.on{color:var(--text);font-weight:600;border-left-color:var(--accent);background:var(--bg2)}
  .ra-railitem.done{color:var(--text2)}
  .ra-railitem.done .rn{color:var(--green)}
  .ra-railitem:disabled{cursor:default}
  .ra-railfoot{margin:18px 20px 0;padding-top:15px;border-top:1px solid var(--border);
    font-size:11.5px;color:var(--text3);line-height:1.55}

  .ra-body{padding:22px}
  .ra-title{font-size:17px;font-weight:700;letter-spacing:-.01em;margin-bottom:4px}
  .ra-sub{font-size:12.5px;color:var(--text2);margin-bottom:18px;max-width:54ch}

  .ra-grid2{display:grid;grid-template-columns:1fr 1fr;gap:11px}
  .ra-grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:11px}
  .ra-field{margin-bottom:11px}
  .ra-field:last-child{margin-bottom:0}
  .ra-field label{display:block;font-size:11px;font-weight:600;color:var(--text2);margin-bottom:5px}
  .ra-field label i{font-style:normal;color:var(--text3);font-weight:500}
  .ra-input{width:100%;height:38px;border:1.5px solid var(--border);border-radius:8px;
    background:var(--bg2);padding:0 11px;font-size:13px;font-family:inherit;color:var(--text);outline:none}
  .ra-input::placeholder{color:var(--text3)}
  .ra-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,168,44,.12)}
  .ra-input.bad{border-color:var(--red)}
  .ra-hint{font-size:11px;color:var(--text3);margin-top:5px}
  .ra-err{font-size:11.5px;color:var(--red);margin-top:5px;display:none}
  .ra-err.show{display:block}

  /* programs and how many teams of each */
  .ra-progs{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .ra-prog{border:1.5px solid var(--border);border-radius:10px;padding:12px;cursor:pointer;
    background:var(--bg2);text-align:left;font-family:inherit;
    transition:border-color .12s,background .12s}
  .ra-prog:hover{border-color:var(--border2)}
  .ra-prog:focus-visible{outline:2px solid var(--accent);outline-offset:1px}
  .ra-prog-top{display:flex;align-items:center;justify-content:space-between;gap:6px}
  .ra-prog-code{font-size:13.5px;font-weight:700}
  .ra-prog-full{font-size:10.5px;color:var(--text3);line-height:1.35;margin-top:3px}
  .ra-check{width:16px;height:16px;border-radius:4px;border:1.5px solid var(--border2);flex-shrink:0;
    display:flex;align-items:center;justify-content:center}
  .ra-prog.on{border-color:var(--accent);background:var(--accent-light)}
  .ra-prog.on .ra-check{background:var(--accent);border-color:var(--accent2)}
  .ra-prog.on .ra-check::after{content:"";width:7px;height:4px;border-left:2px solid #18191f;
    border-bottom:2px solid #18191f;transform:rotate(-45deg) translate(1px,-1px)}
  .ra-step{display:none;align-items:center;margin-top:10px;border:1px solid var(--border);
    border-radius:7px;overflow:hidden;background:var(--bg2)}
  .ra-prog.on .ra-step{display:flex}
  .ra-step span[data-d]{flex:0 0 28px;height:28px;display:flex;align-items:center;justify-content:center;
    background:var(--bg3);color:var(--text2);font-size:15px;font-weight:600;line-height:1;user-select:none}
  .ra-step span[data-d]:hover{background:var(--bg4);color:var(--text)}
  .ra-step .n{flex:1;text-align:center;font-size:12.5px;font-weight:700;font-variant-numeric:tabular-nums}
  .ra-step .u{font-size:10px;font-weight:500;color:var(--text3);padding-right:8px}

  .ra-total{margin-top:12px;font-size:12px;color:var(--text2)}
  .ra-total b{color:var(--text);font-weight:700;font-variant-numeric:tabular-nums}
  .ra-total .none{color:var(--text3)}

  /* expertise */
  .ra-tags{display:flex;flex-wrap:wrap;gap:6px}
  .ra-tag{border:1.5px solid var(--border);border-radius:999px;padding:6px 13px;font-size:12px;
    color:var(--text2);background:var(--bg2);cursor:pointer;font-family:inherit}
  .ra-tag:hover{border-color:var(--border2)}
  .ra-tag:focus-visible{outline:2px solid var(--accent);outline-offset:1px}
  .ra-tag.on{border-color:var(--accent);background:var(--accent-light);color:var(--accent3);font-weight:600}
  .ra-addown{display:flex;gap:8px;margin-top:12px}
  .ra-addown .ra-input{height:34px;font-size:12.5px}
  .ra-addown button{flex-shrink:0}

  /* footer */
  .ra-foot{display:flex;align-items:center;gap:9px;padding:14px 22px;border-top:1px solid var(--border);
    background:var(--bg3);flex-wrap:wrap}
  .ra-footnote{font-size:11.5px;color:var(--text3);margin-right:auto}
  .ra-btn{height:38px;padding:0 18px;border-radius:8px;border:1.5px solid var(--border2);
    background:var(--bg2);color:var(--text);font-family:inherit;font-size:13px;font-weight:600;
    display:inline-flex;align-items:center;justify-content:center;gap:7px;cursor:pointer;white-space:nowrap}
  .ra-btn:hover{border-color:var(--text3)}
  .ra-btn:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
  .ra-btn.primary{background:var(--accent);border-color:var(--accent);color:#18191f;font-weight:700}
  .ra-btn.primary:hover{background:var(--accent2);border-color:var(--accent2)}
  .ra-btn.primary:disabled{opacity:1;background:var(--bg4);border-color:var(--border);
    color:var(--text3);cursor:not-allowed}
  .ra-btn.ghost{border-color:transparent;background:none;color:var(--text2)}
  .ra-btn.ghost:hover{background:var(--bg4);border-color:transparent}
  .ra-btn[hidden]{display:none}

  /* notices */
  .ra-note{display:flex;align-items:flex-start;gap:9px;padding:11px 13px;border-radius:9px;
    font-size:12px;line-height:1.5;margin-bottom:16px}
  .ra-note.warn{background:var(--amber-bg);border:1px solid #e8d3b4;color:var(--amber)}
  .ra-note.bad{background:var(--red-bg);border:1px solid #e6c9c7;color:var(--red)}
  .ra-note.info{background:var(--accent-light);border:1px solid #ecdfae;color:var(--accent3)}
  .ra-dots{display:flex;gap:4px;margin-left:auto;padding-top:4px;flex-shrink:0}
  .ra-dot{width:6px;height:6px;border-radius:50%;background:rgba(180,83,9,.25)}
  .ra-dot.used{background:var(--amber)}

  .ra-alt{text-align:center;font-size:12.5px;color:var(--text3);margin-top:16px}
  .ra-alt a{color:var(--accent3);font-weight:600}

  /* done */
  .ra-done{padding:44px 24px;text-align:center}
  .ra-done-mark{width:46px;height:46px;border-radius:50%;background:var(--green-bg);
    border:1.5px solid rgba(22,163,74,.3);display:flex;align-items:center;justify-content:center;
    margin:0 auto 16px}
  .ra-done-mark svg{width:22px;height:22px;stroke:var(--green);fill:none;stroke-width:2.6}
  .ra-done h2{font-size:18px;font-weight:700;margin-bottom:7px}
  .ra-done p{font-size:13px;color:var(--text2);max-width:44ch;margin:0 auto 18px}

  @media (max-width:720px){
    body{padding:0;align-items:stretch}
    .ra-shell{max-width:none}
    .ra-card{border-radius:0;border-left:0;border-right:0;min-height:100vh;
      display:flex;flex-direction:column}
    .ra-grid{grid-template-columns:1fr;flex:1}
    .ra-rail{border-right:0;border-bottom:1px solid var(--border);display:flex;
      overflow-x:auto;padding:0}
    .ra-railitem{border-left:0;border-bottom:2px solid transparent;white-space:nowrap;padding:12px 16px}
    .ra-railitem.on{border-left-color:transparent;border-bottom-color:var(--accent)}
    .ra-railitem .ro{display:none}
    .ra-railfoot{display:none}
    .ra-grid2,.ra-grid3,.ra-progs{grid-template-columns:1fr}
    .ra-foot{position:sticky;bottom:0}
    .ra-footnote{width:100%;margin-bottom:4px}
  }
  @media (prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}}
</style>
</head>
<body>

<div class="ra-shell">
  <div class="ra-card" id="ra-card">
    <div class="ra-head">
      <span class="ra-wordmark">Caps<span>TRACK</span></span>
      <span class="ra-head-right">CCS · CSPC</span>
    </div>

    <div class="ra-grid" id="ra-main">
      <nav class="ra-rail" aria-label="Steps">
        <button type="button" class="ra-railitem on" data-go="1">
          <span class="rn">01</span>
          <span><span class="rl">Who you are</span></span>
        </button>
        <button type="button" class="ra-railitem" data-go="2">
          <span class="rn">02</span>
          <span><span class="rl">What you will take on</span><span class="ro">optional</span></span>
        </button>
        <button type="button" class="ra-railitem" data-go="3">
          <span class="rn">03</span>
          <span><span class="rl">What you know</span><span class="ro">optional</span></span>
        </button>
        {{-- The one rule people ask about, kept off the fields. --}}
        <p class="ra-railfoot">Your role is a CaPSA's to give when they approve this.
          Adviser, Consultant and Grammarian are not picked here.</p>
      </nav>

      <div class="ra-body">
        @if ($errors->any())
          <div class="ra-note bad">
            <span>{{ $errors->first() }}</span>
          </div>
        @endif

        @if ($cooldownSeconds)
          <div class="ra-note bad" id="ra-cooldown"
               data-seconds="{{ $cooldownSeconds }}">
            <span>Three requests from this address have been turned down.
              You can try again in <b id="ra-cooldown-left">a moment</b>.</span>
          </div>
        @elseif ($rejectAttempts > 0)
          <div class="ra-note warn">
            <span>{{ $rejectAttempts }} of 3 attempts used.
              @if ($accountRequest?->rejection_reason)
                Last reason given: &ldquo;{{ $accountRequest->rejection_reason }}&rdquo;
              @endif
              A third rejection pauses new requests for 3 days.</span>
            <span class="ra-dots" aria-hidden="true">
              @for ($i = 1; $i <= 3; $i++)
                <span class="ra-dot {{ $i <= $rejectAttempts ? 'used' : '' }}"></span>
              @endfor
            </span>
          </div>
        @endif

        {{-- ── 01 ── the only step that is required ── --}}
        <section data-step="1">
          <h1 class="ra-title">Who you are</h1>
          <p class="ra-sub">A CaPSA reads this and decides whether to approve the account.</p>

          <div class="ra-grid2">
            <div class="ra-field">
              <label for="ra-first">First name</label>
              <input class="ra-input" id="ra-first" autocomplete="given-name"
                     value="{{ request('first_name', old('first_name', $accountRequest->first_name ?? '')) }}"
                     placeholder="Neil">
            </div>
            <div class="ra-field">
              <label for="ra-last">Last name</label>
              <input class="ra-input" id="ra-last" autocomplete="family-name"
                     value="{{ request('last_name', old('last_name', $accountRequest->last_name ?? '')) }}"
                     placeholder="Inocencio">
            </div>
          </div>

          <div class="ra-grid3">
            <div class="ra-field">
              <label for="ra-middle">Middle name <i>optional</i></label>
              <input class="ra-input" id="ra-middle" value="{{ old('middle_name', $accountRequest->middle_name ?? '') }}" placeholder="Sigabo">
            </div>
            <div class="ra-field">
              <label for="ra-suffix">Suffix <i>optional</i></label>
              <input class="ra-input" id="ra-suffix" value="{{ old('suffix', $accountRequest->suffix ?? '') }}" placeholder="Jr.">
            </div>
            <div class="ra-field">
              <label for="ra-credentials">Credentials <i>optional</i></label>
              <input class="ra-input" id="ra-credentials" value="{{ old('credentials', $accountRequest->credentials ?? '') }}" placeholder="MIT">
            </div>
          </div>

          <div class="ra-field">
            <label for="ra-email">Email address</label>
            <input class="ra-input" id="ra-email" type="email" autocomplete="email"
                   value="{{ $prefillEmail }}" placeholder="you@cspc.edu.ph">
            <p class="ra-hint">Sign-in goes through Google, so this has to be the address
              you will sign in with — your CSPC Mail or a Gmail address.</p>
          </div>

          <p class="ra-err" id="ra-err-1"></p>
        </section>

        {{-- ── 02 ── --}}
        <section data-step="2" hidden>
          <h1 class="ra-title">What you will take on</h1>
          <p class="ra-sub">Tick every program you are willing to take teams from, and say
            how many of each. Most advisers take two.</p>

          <div class="ra-progs" id="ra-progs">
            @foreach (['BSCS' => 'Computer Science', 'BSIT' => 'Information Technology', 'BSIS' => 'Information Systems', 'BLIS' => 'Library & Information Science'] as $code => $name)
              <button type="button" class="ra-prog" data-prog="{{ $code }}" data-n="1" aria-pressed="false">
                <span class="ra-prog-top">
                  <span class="ra-prog-code">{{ $code }}</span>
                  <span class="ra-check"></span>
                </span>
                <span class="ra-prog-full">{{ $name }}</span>
                <span class="ra-step">
                  <span data-d="-1" role="button" aria-label="One fewer team">&minus;</span>
                  <span class="n">1</span><span class="u">teams</span>
                  <span data-d="1" role="button" aria-label="One more team">+</span>
                </span>
              </button>
            @endforeach
          </div>

          <p class="ra-total" id="ra-total"></p>
        </section>

        {{-- ── 03 ── --}}
        <section data-step="3" hidden>
          <h1 class="ra-title">What you know</h1>
          <p class="ra-sub">Helps a CaPSA match you to a team whose topic you can actually
            supervise. Nothing here is required.</p>

          <div class="ra-tags" id="ra-tags">
            @foreach (['Systems Analysis', 'Machine Learning', 'Web Development', 'Mobile Apps', 'Data Analytics', 'Cybersecurity', 'Network Systems', 'Database Systems', 'UI/UX Design', 'Technical Writing', 'Project Management', 'Research Methodology'] as $field)
              <button type="button" class="ra-tag" aria-pressed="false">{{ $field }}</button>
            @endforeach
          </div>

          <div class="ra-addown">
            <input class="ra-input" id="ra-own" maxlength="100" placeholder="Something else you supervise">
            <button type="button" class="ra-btn" id="ra-add">Add</button>
          </div>
        </section>
      </div>
    </div>

    <div class="ra-foot" id="ra-footer">
      <span class="ra-footnote" id="ra-footnote">You will get an email when a CaPSA decides.</span>
      <button type="button" class="ra-btn ghost" id="ra-back" hidden>Back</button>
      {{-- Its own button, in the row where the decisions are. A skip written
           into a sentence above the fields is a skip nobody finds. --}}
      <button type="button" class="ra-btn" id="ra-skip" hidden>Skip for now</button>
      <button type="button" class="ra-btn primary" id="ra-next">Continue</button>
    </div>
  </div>

  <p class="ra-alt">Already approved? <a href="{{ url('/login') }}">Sign in instead</a></p>
</div>

<script>
(function () {
  var PROGRAMS = ['BSCS', 'BSIT', 'BSIS', 'BLIS'];
  var STORE = @json(route('account.request.store'));
  var TOKEN = document.querySelector('meta[name="csrf-token"]').content;

  var step = 1;
  var main = document.getElementById('ra-main');
  var footer = document.getElementById('ra-footer');
  var card = document.getElementById('ra-card');
  var backBtn = document.getElementById('ra-back');
  var skipBtn = document.getElementById('ra-skip');
  var nextBtn = document.getElementById('ra-next');
  var note = document.getElementById('ra-footnote');

  function val(id) { return document.getElementById(id).value.trim(); }

  // ── which step is showing ──
  function show(n) {
    step = n;

    document.querySelectorAll('[data-step]').forEach(function (s) {
      s.hidden = Number(s.dataset.step) !== n;
    });

    document.querySelectorAll('.ra-railitem').forEach(function (item) {
      var i = Number(item.dataset.go);
      item.classList.toggle('on', i === n);
      item.classList.toggle('done', i < n);
      item.querySelector('.rn').textContent = i < n ? '✓' : '0' + i;
    });

    backBtn.hidden = n === 1;
    // Step one is the only thing actually needed to review a request, so it
    // is the only one without a way past it.
    skipBtn.hidden = n === 1;
    nextBtn.textContent = n === 3 ? 'Send request' : 'Continue';
    note.textContent = n === 3
      ? 'You will get an email when a CaPSA decides.'
      : (n === 2 ? 'You can set this later from your profile.' : 'Takes about a minute.');
  }

  // ── step one has to be filled in ──
  function checkOne() {
    var err = document.getElementById('ra-err-1');
    var email = val('ra-email');
    var problems = [];

    [['ra-first', 'first name'], ['ra-last', 'last name']].forEach(function (pair) {
      var el = document.getElementById(pair[0]);
      var empty = el.value.trim() === '';
      el.classList.toggle('bad', empty);
      if (empty) problems.push('your ' + pair[1]);
    });

    var emailEl = document.getElementById('ra-email');
    var okEmail = /^[^@\s]+@(gmail\.com|cspc\.edu\.ph)$/i.test(email);
    emailEl.classList.toggle('bad', !okEmail);
    if (!okEmail) problems.push('a CSPC Mail or Gmail address');

    if (problems.length) {
      err.textContent = 'Still needed: ' + problems.join(', ') + '.';
      err.classList.add('show');
      return false;
    }

    err.classList.remove('show');
    return true;
  }

  // ── programs and slots ──
  var progs = document.getElementById('ra-progs');
  var total = document.getElementById('ra-total');

  function chosen() {
    return Array.prototype.slice.call(progs.querySelectorAll('.ra-prog.on'));
  }

  function renderTotal() {
    var on = chosen();
    total.textContent = '';

    if (!on.length) {
      var none = document.createElement('span');
      none.className = 'none';
      none.textContent = 'No programs picked. You can be approved without this, but nobody '
        + 'can be assigned to you until you set it on your profile.';
      total.appendChild(none);
      return;
    }

    var teams = on.reduce(function (sum, el) { return sum + Number(el.dataset.n); }, 0);
    var b = document.createElement('b');
    b.textContent = teams + (teams === 1 ? ' team' : ' teams');
    total.appendChild(b);
    total.appendChild(document.createTextNode(' across ' + on.length
      + (on.length === 1 ? ' program' : ' programs') + ': '));
    var which = document.createElement('b');
    which.textContent = on.map(function (el) { return el.dataset.n + ' - ' + el.dataset.prog; }).join(', ');
    total.appendChild(which);
  }

  progs.addEventListener('click', function (e) {
    var card = e.target.closest('.ra-prog');
    if (!card) return;

    var stepper = e.target.closest('[data-d]');
    if (stepper) {
      var n = Math.min(10, Math.max(1, Number(card.dataset.n) + Number(stepper.dataset.d)));
      card.dataset.n = n;
      card.querySelector('.ra-step .n').textContent = n;
    } else {
      var on = !card.classList.contains('on');
      card.classList.toggle('on', on);
      card.setAttribute('aria-pressed', String(on));
    }

    renderTotal();
  });

  function slots() {
    var out = {};
    PROGRAMS.forEach(function (p) { out[p] = 0; });
    chosen().forEach(function (el) { out[el.dataset.prog] = Number(el.dataset.n); });
    return out;
  }

  // ── expertise ──
  var tags = document.getElementById('ra-tags');

  tags.addEventListener('click', function (e) {
    var tag = e.target.closest('.ra-tag');
    if (!tag) return;
    var on = !tag.classList.contains('on');
    tag.classList.toggle('on', on);
    tag.setAttribute('aria-pressed', String(on));
  });

  function addOwn() {
    var input = document.getElementById('ra-own');
    var text = input.value.trim();
    if (!text) return;

    var tag = document.createElement('button');
    tag.type = 'button';
    tag.className = 'ra-tag on';
    tag.setAttribute('aria-pressed', 'true');
    tag.textContent = text;
    tags.appendChild(tag);
    input.value = '';
  }

  document.getElementById('ra-add').addEventListener('click', addOwn);
  document.getElementById('ra-own').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); addOwn(); }
  });

  function expertise() {
    return Array.prototype.slice.call(tags.querySelectorAll('.ra-tag.on'))
      .map(function (t) { return t.textContent.trim(); });
  }

  // ── moving about ──
  document.querySelectorAll('.ra-railitem').forEach(function (item) {
    item.addEventListener('click', function () {
      var to = Number(item.dataset.go);
      // Forward only over step one, so nobody lands on step three having
      // never given a name.
      if (to > 1 && !checkOne()) { show(1); return; }
      show(to);
    });
  });

  backBtn.addEventListener('click', function () { show(step - 1); });

  // Skipping a step is the same move as finishing it, minus the answers -
  // which on the last step means sending the request with none.
  skipBtn.addEventListener('click', function () {
    if (step === 2) {
      chosen().forEach(function (el) {
        el.classList.remove('on');
        el.setAttribute('aria-pressed', 'false');
      });
      renderTotal();
      show(3);
      return;
    }

    tags.querySelectorAll('.ra-tag.on').forEach(function (t) {
      t.classList.remove('on');
      t.setAttribute('aria-pressed', 'false');
    });
    submit();
  });

  nextBtn.addEventListener('click', function () {
    if (step === 1) { if (checkOne()) show(2); return; }
    if (step === 2) { show(3); return; }
    submit();
  });

  // ── sending it ──
  function submit() {
    nextBtn.disabled = true;
    skipBtn.disabled = true;
    nextBtn.textContent = 'Sending…';

    fetch(STORE, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': TOKEN,
      },
      body: JSON.stringify({
        first_name: val('ra-first'),
        last_name: val('ra-last'),
        middle_name: val('ra-middle') || null,
        suffix: val('ra-suffix') || null,
        credentials: val('ra-credentials') || null,
        email: val('ra-email'),
        program_slots: slots(),
        expertise: expertise(),
      }),
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .catch(function () { return { ok: false, data: {} }; })
      .then(function (r) {
        nextBtn.disabled = false;
        skipBtn.disabled = false;
        nextBtn.textContent = 'Send request';

        if (r.ok && r.data.success) { done(r.data.message); return; }

        // The reason comes back on the step it belongs to, rather than as a
        // banner over a form the applicant then has to search.
        var msg = r.data.message || 'Could not send the request. Try again in a moment.';
        show(1);
        var err = document.getElementById('ra-err-1');
        err.textContent = msg;
        err.classList.add('show');
      });
  }

  function done(message) {
    main.remove();
    footer.remove();

    var panel = document.createElement('div');
    panel.className = 'ra-done';
    panel.innerHTML = '<div class="ra-done-mark"><svg viewBox="0 0 24 24">'
      + '<polyline points="20 6 9 17 4 12"/></svg></div>'
      + '<h2>Request sent</h2>'
      + '<p></p>'
      + '<a class="ra-btn" href="' + @json(url('/login')) + '">Back to sign in</a>';
    panel.querySelector('p').textContent = message
      || 'A CaPSA will review it. You will get an email either way, usually within 3 days.';
    card.appendChild(panel);
  }

  // ── the three-day wait, counted down rather than stated once ──
  var cooldown = document.getElementById('ra-cooldown');
  if (cooldown) {
    var left = Number(cooldown.dataset.seconds);
    var out = document.getElementById('ra-cooldown-left');
    nextBtn.disabled = true;

    var tick = setInterval(function () {
      left -= 1;

      if (left <= 0) {
        clearInterval(tick);
        cooldown.remove();
        nextBtn.disabled = false;
        return;
      }

      var h = Math.floor(left / 3600);
      var m = Math.floor((left % 3600) / 60);
      out.textContent = h > 0 ? h + 'h ' + m + 'm' : m + 'm ' + (left % 60) + 's';
    }, 1000);
  }

  renderTotal();
  show(1);
})();
</script>
</body>
</html>
