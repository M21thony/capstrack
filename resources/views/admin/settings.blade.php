<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>System Settings - CapsTRACK Admin</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<style>
  /* One panel, three rows. Each row says what it is on the left and holds its
     controls on the right, so the page reads down one column. */
  .set-panel{background:var(--bg2);border:1px solid var(--border);border-radius:14px}
  .set-row{display:grid;grid-template-columns:300px minmax(0,1fr);gap:48px;padding:28px 32px}
  .set-row + .set-row{border-top:1px solid var(--bg4)}
  .set-row h2{margin:0 0 6px;font-size:16px;font-weight:700;color:var(--text)}
  .set-row .set-degit sc{margin:0;font-size:13.5px;line-height:1.5;color:var(--text3)}
  .set-label{display:block;font-size:12.5px;font-weight:600;color:var(--text2);margin-bottom:8px}
  .set-label span{font-weight:400;color:var(--text3)}
  .set-field + .set-field{margin-top:22px}
  .set-err{font-size:12px;color:var(--red);margin-top:6px}
  .set-btn{height:44px;padding:0 20px;border-radius:9px;border:none;background:var(--accent);color:#18191f;
           font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:background .12s}
  .set-btn:hover{background:var(--accent2)}
  .set-btn:disabled{background:var(--bg4);color:var(--text3);cursor:default}
  .set-btn:focus-visible{outline:2px solid var(--accent);outline-offset:2px}

  .sy-line{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .sy-input{width:220px;height:44px;box-sizing:border-box;padding:0 14px;border-radius:9px;
            border:1.5px solid var(--border);background:var(--bg2);color:var(--text);
            font-family:inherit;font-size:15px;font-weight:600}
  .sy-input:focus{outline:none;border-color:var(--accent)}
  .sy-hint{display:block;font-size:12.5px;color:var(--text3);margin-top:6px}

  /* Ticking a program is a click on the whole box, not on a 16px square. */
  .opt-row{display:flex;flex-wrap:wrap;gap:8px}
  .opt{display:inline-flex;align-items:center;gap:9px;height:44px;padding:0 16px;box-sizing:border-box;
       border:1.5px solid var(--border);border-radius:9px;cursor:pointer;font-size:14px;font-weight:600;
       color:var(--text2);background:var(--bg2);transition:border-color .12s,background .12s,color .12s}
  .opt:hover{border-color:var(--border2)}
  .opt input{width:16px;height:16px;accent-color:var(--accent);cursor:pointer;margin:0}
  .opt:has(input:checked){border-color:var(--accent);background:var(--accent-light);color:var(--accent3)}
  .opt:has(input:focus-visible){outline:2px solid var(--accent);outline-offset:2px}

  /* A to Z as one joined strip. Click either end of the range; the letters
     between fill in, so the range reads as one band. */
  .letters{display:flex;width:100%;max-width:936px;border:1.5px solid var(--border);border-radius:10px;overflow:hidden;box-sizing:border-box}
  .letter{flex:1 1 0;min-width:0;height:40px;padding:0;border:none;border-right:1px solid var(--bg4);background:var(--bg2);
          cursor:pointer;font-family:inherit;font-size:13.5px;font-weight:700;color:var(--text3);
          transition:background .1s,color .1s}
  .letter:last-child{border-right:none}
  .letter:hover{background:var(--bg3);color:var(--text2)}
  .letter.on{background:#f3e7b8;color:var(--accent3)}
  .letter.edge{background:var(--accent);color:#18191f}
  .letter:focus-visible{outline:2px solid var(--text);outline-offset:-3px}
  .letter-said{font-size:12.5px;color:var(--text2);margin:9px 0 0}
  .letter-said b{color:var(--text)}

  /* What the range will actually produce, before it is saved. */
  .preview{padding:16px;border-radius:10px;background:var(--bg3);font-size:13px;color:var(--text3)}
  .preview-title{display:block;font-size:12.5px;font-weight:600;color:var(--text2);margin-bottom:10px}
  .preview-list{display:flex;flex-wrap:wrap;gap:6px}
  .sec-tag{display:inline-flex;align-items:center;height:30px;padding:0 11px;border-radius:7px;
           border:1.5px dashed var(--accent);background:var(--bg2);color:var(--accent3);
           font-size:13px;font-weight:700;font-variant-numeric:tabular-nums}
  .sec-tag.more{border-style:solid;border-color:var(--border);color:var(--text2)}

  /* The list: a program and year once on the left, its sections beside it. */
  .sec-group{display:grid;grid-template-columns:110px minmax(0,1fr);gap:16px;align-items:start;
             padding:14px 0;border-bottom:1px solid var(--bg4)}
  .sec-group:first-child{padding-top:0}
  .sec-group:last-child{border-bottom:none;padding-bottom:0}
  .sec-prog{display:block;font-size:14.5px;font-weight:700;color:var(--text);padding-top:7px}
  .sec-year{display:block;font-size:12.5px;color:var(--text3);margin-top:2px}
  .sec-list{display:flex;flex-wrap:wrap;gap:6px}
  .sec-item{display:inline-flex;align-items:center;gap:2px;height:36px;padding:0 2px 0 12px;box-sizing:border-box;
            border-radius:8px;background:var(--bg4);font-size:14px;font-weight:600;color:var(--text);
            font-variant-numeric:tabular-nums}
  .sec-item form{display:flex}
  .sec-item button{width:32px;height:32px;border:none;background:none;padding:0;cursor:pointer;color:var(--text3);
                   border-radius:6px;display:flex;align-items:center;justify-content:center}
  .sec-item button:hover{color:var(--red);background:var(--red-bg)}
  .sec-item button:focus-visible{outline:2px solid var(--accent);outline-offset:0}
  .sec-item svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.4;stroke-linecap:round}
  .sec-empty{font-size:13px;color:var(--text3);padding:16px;text-align:center;
             border:1px dashed var(--border);border-radius:10px}

  @media (max-width:900px){
    .set-row{grid-template-columns:1fr;gap:16px;padding:22px 18px}
    /* Too narrow for one strip: the letters wrap as separate keys. */
    .letters{flex-wrap:wrap;gap:4px;border:none;border-radius:0;overflow:visible}
    .letter{flex:0 0 36px;border:1.5px solid var(--border);border-radius:8px}
    .letter.on{border-color:rgba(201,168,44,0.45)}
    .letter.edge{border-color:var(--accent)}
    .sec-group{grid-template-columns:1fr;gap:8px}
    .sec-prog{padding-top:0}
  }
</style>
</head>
<body>

@include('capsa._sidebar')
@include('partials.sidebar-toggle')

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">System Settings</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
    @if (session('success'))
      <div class="card" style="border-color:var(--green);background:var(--green-bg);margin-bottom:16px;padding:12px 16px;color:var(--green);font-size:13px">{{ session('success') }}</div>
    @endif

    <div class="set-panel">

      <section class="set-row" aria-labelledby="sy-h">
        <div>
          <h2 id="sy-h">School year</h2>
          <p class="set-desc">The year the whole system is working in.</p>
        </div>
        <form method="POST" action="{{ route('admin.settings.update') }}">
          @csrf
          @method('PUT')
          <label class="set-label" for="current_school_year">School year</label>
          <div class="sy-line">
            <input class="sy-input" type="text" id="current_school_year" name="current_school_year" value="{{ old('current_school_year', $currentSchoolYear) }}" placeholder="2026-2027">
            <button type="submit" class="set-btn">Save</button>
          </div>
          @error('current_school_year')
            <div class="set-err">{{ $message }}</div>
          @else
            <span class="sy-hint">Written as 2026-2027.</span>
          @enderror
        </form>
      </section>

      {{-- Sections had to be worked out from the students enrolled in them,
           which is true once a term is running and useless before it: a fresh
           system has no students, so it has no sections, so nobody can be made
           a CaPSA over one - and a CaPSA is who uploads the students. --}}
      <section class="set-row" aria-labelledby="add-h">
        <div>
          <h2 id="add-h">Add sections</h2>
          <p class="set-desc">Sections are added for 3rd year, where capstone starts. 4th-year sections come from the student list, once a list or Year Promotion puts students in them.</p>
        </div>
        <form method="POST" action="{{ route('admin.settings.sections.store') }}" id="sectionForm">
          @csrf

          <fieldset class="set-field" style="border:none;margin:0;padding:0">
            <legend class="set-label" style="padding:0">Programs</legend>
            <div class="opt-row">
              @foreach ($programs as $program)
                <label class="opt"><input type="checkbox" name="programs[]" value="{{ $program }}" onchange="previewSections()">{{ $program }}</label>
              @endforeach
            </div>
            @error('programs')<div class="set-err">{{ $message }}</div>@enderror
          </fieldset>

          <fieldset class="set-field" style="border:none;margin-top:22px;padding:0">
            <legend class="set-label" style="padding:0">Letters <span>&middot; click the first and the last</span></legend>
            {{-- A range is a thing you point at either end of, not a pair of
                 lists you read twice. --}}
            <div class="letters" id="letterStrip">
              @foreach ($letters as $l)
                <button type="button" class="letter" data-letter="{{ $l }}" onclick="pickLetter('{{ $l }}')">{{ $l }}</button>
              @endforeach
            </div>
            <input type="hidden" name="from" id="fromLetter" value="A">
            <input type="hidden" name="to" id="toLetter" value="D">
            <p class="letter-said" id="letterSaid" aria-live="polite"></p>
            @error('from')<div class="set-err">{{ $message }}</div>@enderror
          </fieldset>

          <div class="set-field preview" id="sectionPreview" aria-live="polite"></div>

          <div class="set-field">
            <button type="submit" class="set-btn" id="addSections">Add sections</button>
          </div>
        </form>
      </section>

      <section class="set-row" aria-labelledby="list-h">
        <div>
          <h2 id="list-h">Sections</h2>
          <p class="set-desc">{{ count($sections) }} listed.</p>
        </div>
        <div>
          @if ($sections === [])
            <div class="sec-empty">None yet. Add a range above &mdash; without at least one, Add CaPSA has nothing to assign.</div>
          @else
            {{-- Course, year and section read as three things, not as "BSIT-4A"
                 two dozen times over: the course and the year are said once for
                 the group, and its sections sit beside them. --}}
            @php
              $grouped = [];
              foreach ($sections as $label) {
                  preg_match('/^([A-Z]+)-(\d+)([A-Z]*)$/', $label, $m);
                  $grouped[$m[1] ?? $label][$m[2] ?? ''][] = ['letter' => $m[3] ?? '', 'year' => $m[2] ?? '', 'label' => $label];
              }
            @endphp
            @foreach ($grouped as $program => $byYear)
              @foreach ($byYear as $year => $items)
                <div class="sec-group">
                  <div>
                    <span class="sec-prog">{{ $program }}</span>
                    <span class="sec-year">{{ $year }}{{ [1=>'st',2=>'nd',3=>'rd',4=>'th'][(int) $year] ?? 'th' }} year</span>
                  </div>
                  <div class="sec-list">
                    @foreach ($items as $item)
                      <span class="sec-item">{{ $item['year'] }}{{ $item['letter'] ?: '' }}
                        <form method="POST" action="{{ route('admin.settings.sections.destroy') }}">
                          @csrf @method('DELETE')
                          <input type="hidden" name="section" value="{{ $item['label'] }}">
                          <button type="submit" title="Remove {{ $item['label'] }}" aria-label="Remove {{ $item['label'] }}">
                            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                          </button>
                        </form>
                      </span>
                    @endforeach
                  </div>
                </div>
              @endforeach
            @endforeach
          @endif
        </div>
      </section>

    </div>
  </div>
</div>

<script>
var SECTION_LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

/** What the range would produce, shown before it is saved rather than after. */
function previewSections(){
  var form = document.getElementById('sectionForm');
  var box = document.getElementById('sectionPreview');

  var programs = [].slice.call(form.querySelectorAll('input[name="programs[]"]:checked')).map(function (i) { return i.value; });
  // 3rd year, where capstone starts; 4th-year sections come from the student list.
  var years = @json($years);
  var from = SECTION_LETTERS.indexOf(document.getElementById('fromLetter').value);
  var to = SECTION_LETTERS.indexOf(document.getElementById('toLetter').value);

  if (from > to) { var swap = from; from = to; to = swap; }

  var button = document.getElementById('addSections');

  if (!programs.length) {
    box.innerHTML = 'Pick at least one program.';
    button.disabled = true;
    button.textContent = 'Add sections';
    return;
  }

  var made = [];
  programs.forEach(function (p) {
    years.forEach(function (y) {
      for (var i = from; i <= to; i++) made.push(p + '-' + y + SECTION_LETTERS[i]);
    });
  });

  // Named, not counted, so the Admin sees exactly which labels will be added.
  var shown = made.slice(0, 16).map(function (s) { return '<span class="sec-tag">' + s + '</span>'; }).join('');
  box.innerHTML = '<span class="preview-title">Will add</span><div class="preview-list">'
    + shown + (made.length > 16 ? '<span class="sec-tag more">+' + (made.length - 16) + ' more</span>' : '') + '</div>';
  button.disabled = false;
  button.textContent = 'Add ' + made.length + ' section' + (made.length === 1 ? '' : 's');
}

/**
 * Click one letter, then another. The first sets one end and the second the
 * other; a third click starts again, so there is no mode to be in and
 * nothing to reset.
 */
var letterPending = null;

function pickLetter(letter){
  var fromEl = document.getElementById('fromLetter');
  var toEl = document.getElementById('toLetter');

  if (letterPending === null) {
    letterPending = letter;
    fromEl.value = letter;
    toEl.value = letter;
  } else {
    var a = SECTION_LETTERS.indexOf(letterPending);
    var b = SECTION_LETTERS.indexOf(letter);

    fromEl.value = SECTION_LETTERS[Math.min(a, b)];
    toEl.value = SECTION_LETTERS[Math.max(a, b)];
    letterPending = null;
  }

  paintLetters();
  previewSections();
}

function paintLetters(){
  var from = SECTION_LETTERS.indexOf(document.getElementById('fromLetter').value);
  var to = SECTION_LETTERS.indexOf(document.getElementById('toLetter').value);
  var said = document.getElementById('letterSaid');

  document.querySelectorAll('.letter').forEach(function (b) {
    var i = SECTION_LETTERS.indexOf(b.dataset.letter);
    b.classList.toggle('on', i >= from && i <= to);
    b.classList.toggle('edge', i === from || i === to);
    b.setAttribute('aria-pressed', i >= from && i <= to ? 'true' : 'false');
  });

  var n = to - from + 1;
  said.innerHTML = from === to
    ? 'Section <b>' + SECTION_LETTERS[from] + '</b> only.'
      + (letterPending ? ' Click another letter for a range.' : '')
    : '<b>' + SECTION_LETTERS[from] + '</b> through <b>' + SECTION_LETTERS[to] + '</b> \u2014 ' + n + ' sections each.';
}

paintLetters();
previewSections();
</script>
@include('partials.escape-closes-modals')
</body>
</html>
