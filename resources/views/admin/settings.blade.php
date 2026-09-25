<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>System Settings - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
  .set-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr);gap:16px;align-items:start}
  @media (max-width:900px){ .set-grid{grid-template-columns:1fr} }
  .set-label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;
             letter-spacing:.6px;color:var(--text3);margin-bottom:7px}
  .set-hint{font-size:11.5px;color:var(--text3);line-height:1.5;margin:7px 0 0}
  /* Ticking a program is a click on the whole box, not on a 14px square. */
  .opt-row{display:flex;flex-wrap:wrap;gap:6px}
  .opt{display:inline-flex;align-items:center;gap:6px;padding:6px 11px;border:1.5px solid var(--border);
       border-radius:8px;cursor:pointer;font-size:12.5px;font-weight:600;color:var(--text2);
       background:var(--bg2);transition:border-color .12s,background .12s,color .12s}
  .opt:hover{border-color:var(--border2)}
  .opt input{width:14px;height:14px;accent-color:var(--accent);cursor:pointer;margin:0}
  .opt:has(input:checked){border-color:var(--accent);background:var(--accent-light);color:var(--accent3)}
  /* A to Z as one strip. Click either end of the range; the letters between
     fill in, which is the whole answer to "which sections do you run". */
  .letters{display:flex;flex-wrap:wrap;gap:3px}
  .letter{width:27px;height:29px;padding:0;border:1.5px solid var(--border);border-radius:7px;
          background:var(--bg2);cursor:pointer;font-family:inherit;font-size:12px;font-weight:600;
          color:var(--text3);transition:border-color .1s,background .1s,color .1s}
  .letter:hover{border-color:var(--border2);color:var(--text2)}
  /* Inside the range: the letters this will actually make. */
  .letter.on{background:var(--accent-light);border-color:rgba(201,168,44,0.4);color:var(--accent3)}
  /* The two you chose, so it is clear which ends you can move. */
  .letter.edge{background:var(--accent);border-color:var(--accent);color:#18191f}
  .letter:focus-visible{outline:2px solid var(--accent);outline-offset:1px}
  .letter-said{font-size:12px;color:var(--text2);margin:9px 0 0}
  .letter-said b{color:var(--text)}
  /* What the range will actually produce, before it is saved. */
  .preview{margin-top:14px;padding:11px 13px;border:1px dashed var(--border2);border-radius:9px;
           background:var(--bg3);font-size:12px;color:var(--text2);min-height:42px}
  .preview b{color:var(--text)}
  .sec-tag{display:inline-block;padding:2px 8px;margin:2px 3px 2px 0;border-radius:6px;
           background:var(--bg4);color:var(--text2);font-size:11.5px;font-weight:600;
           font-variant-numeric:tabular-nums}
  .sec-list{display:flex;flex-wrap:wrap;gap:5px}
  .sec-item{display:inline-flex;align-items:center;gap:6px;padding:4px 6px 4px 10px;border-radius:7px;
            background:var(--bg4);font-size:12px;font-weight:600;color:var(--text2);
            font-variant-numeric:tabular-nums}
  .sec-item button{border:none;background:none;padding:0;cursor:pointer;color:var(--text3);
                   line-height:0;border-radius:4px;display:flex}
  .sec-item button:hover{color:var(--red)}
  .sec-item svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.5}
  .sec-empty{font-size:12.5px;color:var(--text3);padding:14px 12px;text-align:center;
             border:1px dashed var(--border);border-radius:9px}
  .sec-table{width:100%;border-collapse:collapse}
  .sec-table th{text-align:left;padding:8px 12px;font-size:10.5px;font-weight:600;letter-spacing:.7px;
                text-transform:uppercase;color:var(--text3);border-bottom:1px solid var(--border)}
  .sec-table td{padding:9px 12px;border-bottom:1px solid var(--bg4);vertical-align:middle}
  .sec-table tr:last-child td{border-bottom:none}
  .sec-prog{display:inline-block;padding:2px 8px;border-radius:6px;background:var(--blue-bg);
            color:var(--blue);font-size:11.5px;font-weight:700}
  .sec-year{font-size:12.5px;color:var(--text2);font-variant-numeric:tabular-nums;white-space:nowrap}
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

    <div class="set-grid">

    <div class="card">
      <div class="card-header"><span class="card-title">Current School Year</span></div>
      <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')
        <div style="margin-bottom:14px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text3);margin-bottom:6px">School Year</label>
          <input class="input" type="text" name="current_school_year" value="{{ old('current_school_year', $currentSchoolYear) }}" placeholder="2026-2027">
          @error('current_school_year')
            <div style="font-size:11px;color:var(--red);margin-top:4px">{{ $message }}</div>
          @enderror
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
      </form>
    </div>

    {{-- Sections had to be worked out from the students enrolled in them,
         which is true once a term is running and useless before it: a fresh
         system has no students, so it has no sections, so nobody can be made
         a CaPSA over one - and a CaPSA is who uploads the students. --}}
    <div class="card">
      <div class="card-header"><span class="card-title">Sections</span></div>

      <form method="POST" action="{{ route('admin.settings.sections.store') }}" id="sectionForm">
        @csrf

        <div style="margin-bottom:15px">
          <label class="set-label">Programs</label>
          <div class="opt-row">
            @foreach ($programs as $program)
              <label class="opt"><input type="checkbox" name="programs[]" value="{{ $program }}" onchange="previewSections()">{{ $program }}</label>
            @endforeach
          </div>
          @error('programs')<div style="font-size:11px;color:var(--red);margin-top:5px">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="set-label">Section letters</label>
          {{-- Two dropdowns beside the programs, which are
               both click-to-pick, read as a different kind of control for no
               reason - and a range is a thing you point at either end of, not
               a pair of lists you read twice. --}}
          <div class="letters" id="letterStrip">
            @foreach ($letters as $l)
              <button type="button" class="letter" data-letter="{{ $l }}" onclick="pickLetter('{{ $l }}')">{{ $l }}</button>
            @endforeach
          </div>
          <input type="hidden" name="from" id="fromLetter" value="A">
          <input type="hidden" name="to" id="toLetter" value="D">
          <p class="letter-said" id="letterSaid"></p>
          @error('from')<div style="font-size:11px;color:var(--red);margin-top:5px">{{ $message }}</div>@enderror
        </div>

        <div class="preview" id="sectionPreview"></div>

        <button type="submit" class="btn btn-primary" style="margin-top:13px">Add sections</button>
        <p class="set-hint">Sections are added for 3rd year, where capstone starts. A 4th-year section comes from the student list: it appears once a list or Year Promotion puts students in it, and a CaPSA is assigned to it then. A section a student is already enrolled in is listed whether or not it is added here &mdash; this is for the ones nobody has arrived in yet.</p>
      </form>
    </div>

    </div>

    <div class="card" style="margin-top:16px">
      <div class="card-header"><span class="card-title">{{ count($sections) }} section{{ count($sections) === 1 ? '' : 's' }} listed</span></div>
      @if ($sections === [])
        <div class="sec-empty">None yet. Add a range above &mdash; without at least one, Add CaPSA has nothing to assign.</div>
      @else
        {{-- Course, year and section read as three things, not as "BSIT-4A"
             two dozen times over: the course and the year are said once for
             the row, and the letters sit under them. --}}
        @php
          $grouped = [];
          foreach ($sections as $label) {
              preg_match('/^([A-Z]+)-(\d+)([A-Z]*)$/', $label, $m);
              $grouped[$m[1] ?? $label][$m[2] ?? ''][] = ['letter' => $m[3] ?? '', 'label' => $label];
          }
        @endphp
        <table class="sec-table">
          <thead><tr><th>Course</th><th>Year</th><th>Sections</th></tr></thead>
          <tbody>
            @foreach ($grouped as $program => $byYear)
              @foreach ($byYear as $year => $items)
                <tr>
                  <td><span class="sec-prog">{{ $program }}</span></td>
                  <td class="sec-year">{{ $year }}{{ [1=>'st',2=>'nd',3=>'rd',4=>'th'][(int) $year] ?? 'th' }}</td>
                  <td>
                    <div class="sec-list">
                      @foreach ($items as $item)
                        <span class="sec-item">{{ $item['letter'] ?: '&mdash;' }}
                          <form method="POST" action="{{ route('admin.settings.sections.destroy') }}" style="display:inline">
                            @csrf @method('DELETE')
                            <input type="hidden" name="section" value="{{ $item['label'] }}">
                            <button type="submit" title="Remove {{ $item['label'] }}" aria-label="Remove {{ $item['label'] }}">
                              <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                          </form>
                        </span>
                      @endforeach
                    </div>
                  </td>
                </tr>
              @endforeach
            @endforeach
          </tbody>
        </table>
      @endif
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

  if (!programs.length) {
    box.innerHTML = 'Pick at least one program.';
    return;
  }

  var made = [];
  programs.forEach(function (p) {
    years.forEach(function (y) {
      for (var i = from; i <= to; i++) made.push(p + '-' + y + SECTION_LETTERS[i]);
    });
  });

  // Named, not counted, so the Admin sees exactly which labels will be added.
  var shown = made.slice(0, 12).map(function (s) { return '<span class="sec-tag">' + s + '</span>'; }).join('');
  box.innerHTML = '<b>' + made.length + '</b> section' + (made.length === 1 ? '' : 's') + '<div style="margin-top:7px">'
    + shown + (made.length > 12 ? '<span class="sec-tag">+' + (made.length - 12) + ' more</span>' : '') + '</div>';
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
