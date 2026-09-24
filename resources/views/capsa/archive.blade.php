<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Archived Capstones - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
<style>
/* ── Abstract view: laid out like the abstract page of a manuscript ── */
#abstract-modal{padding:24px;overflow-y:auto;align-items:flex-start;z-index:1000;background:rgba(15,17,23,.55);backdrop-filter:blur(2px)}
.abs-sheet{position:relative;width:min(760px,100%);margin:auto;background:#fff;border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.28);border-top:5px solid var(--accent);overflow:hidden}
.abs-close{position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:#fff;color:var(--text3);cursor:pointer;display:flex;align-items:center;justify-content:center}
.abs-close:hover{color:var(--text);border-color:var(--accent)}
.abs-close svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.2}
.abs-body{padding:44px 56px 32px}
.abs-kicker{text-align:center;font-size:10.5px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--accent3,#8a6e10)}
.abs-title{margin:14px 0 0;text-align:center;font-family:Georgia,'Times New Roman',serif;font-size:23px;font-weight:700;line-height:1.35;color:var(--text);text-transform:uppercase;letter-spacing:.01em}
.abs-authors{margin-top:12px;text-align:center;font-family:Georgia,'Times New Roman',serif;font-size:14px;font-style:italic;color:var(--text2)}
.abs-meta{display:grid;grid-template-columns:repeat(3,1fr);margin:22px 0 0;border:1px solid var(--border);border-radius:10px;overflow:hidden}
.abs-meta div{padding:10px 14px;text-align:center;border-right:1px solid var(--border);background:var(--bg3,#f7f8fa)}
.abs-meta div:last-child{border-right:0}
.abs-meta dt{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text3)}
.abs-meta dd{margin:3px 0 0;font-size:13px;font-weight:600;color:var(--text)}
.abs-heading{display:flex;align-items:center;gap:14px;margin:30px 0 14px;font-family:Georgia,'Times New Roman',serif;font-size:15px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--text)}
.abs-heading::before,.abs-heading::after{content:"";flex:1;height:1px;background:var(--border)}
.abs-text{font-family:Georgia,'Times New Roman',serif;font-size:14.5px;line-height:1.85;color:var(--text);text-align:justify;hyphens:auto}
.abs-text p{margin:0 0 12px;text-indent:2.5em}
.abs-text p:last-child{margin-bottom:0}
.abs-empty{text-align:center;font-size:13px;font-style:italic;color:var(--text3);padding:12px 0}
.abs-keywords{margin-top:22px;font-family:Georgia,'Times New Roman',serif;font-size:13.5px;color:var(--text2)}
.abs-keywords b{font-style:italic;color:var(--text)}
.abs-foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 24px;border-top:1px solid var(--border);background:var(--bg3,#f7f8fa)}
.abs-foot .abs-pdf{display:inline-flex;align-items:center;gap:6px;text-decoration:none;background:#fff;color:var(--text);border:1.5px solid var(--border2,#d1d5db)}
.abs-foot .abs-pdf[hidden]{display:none}
.abs-foot .abs-pdf:hover{border-color:var(--accent);color:var(--accent3,#8a6e10)}
.archive-card .arch-view{font-size:10px;padding:3px 10px;background:#fff;color:var(--accent3,#8a6e10);border-color:rgba(201,168,44,.45)}
.archive-card .arch-view:hover{background:var(--accent-glow);border-color:var(--accent)}
.archive-title-text.is-link{cursor:pointer}
.archive-title-text.is-link:hover{color:var(--accent3,#8a6e10);text-decoration:underline}
@media (max-width:640px){
  #abstract-modal{padding:10px}
  .abs-body{padding:36px 20px 24px}
  .abs-title{font-size:18px}
  .abs-meta{grid-template-columns:1fr}
  .abs-meta div{border-right:0;border-bottom:1px solid var(--border)}
  .abs-meta div:last-child{border-bottom:0}
  .abs-text{text-align:left}
}
</style>
</head>
<body>
@php
    // academic_year is a YEAR column holding the school year's first year:
    // 2026 is S.Y. 2026-2027 (see Student\ArchiveController::academicYearFor).
    $schoolYearOf = function ($year): string {
        $year = (string) $year;

        if ($year === '') {
            return 'Not recorded';
        }

        return str_contains($year, '-') ? $year : $year.'–'.((int) $year + 1);
    };

    // Everything the abstract view shows, for published and pending alike.
    $abstractData = $archives->concat($pending)->mapWithKeys(fn ($doc) => [
        $doc->archived_id => [
            'title' => $doc->title,
            'abstract' => $doc->abstract,
            'authors' => $doc->authors,
            'program' => $doc->program ?: 'Not recorded',
            'schoolYear' => $schoolYearOf($doc->academic_year),
            'date' => $doc->submitted_at?->format('F j, Y') ?? 'Not recorded',
            'keywords' => $doc->relationLoaded('tags') ? $doc->tags->pluck('tag_name')->values() : [],
            // Only files this app stored itself have a path the public disk can
            // serve; staff rows can hold a Drive-style or absolute path (the
            // same rule deleteStoredFile() uses).
            'pdf' => $doc->file_path && ! str_contains($doc->file_path, ':')
                ? Storage::disk('public')->url($doc->file_path)
                : null,
            'pending' => $doc->submission_status === \App\Models\ArchivedDocument::PENDING,
        ],
    ]);
@endphp

@include('capsa._sidebar')
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Archived Capstone Management</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
      <button class="btn btn-ghost-nav" id="topbar-action" onclick="showAddArchive()">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span id="action-label">Add Capstone</span>
      </button>
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="row" style="margin-bottom:16px;gap:8px">
      <div class="sk" style="flex:1;min-width:180px;height:34px;border-radius:8px"></div>
      <div class="sk" style="width:120px;height:34px;border-radius:8px"></div>
      <div class="sk" style="width:150px;height:34px;border-radius:8px"></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
      <div class="sk" style="height:160px;border-radius:12px"></div>
      <div class="sk" style="height:160px;border-radius:12px"></div>
      <div class="sk" style="height:160px;border-radius:12px"></div>
      <div class="sk" style="height:160px;border-radius:12px"></div>
      <div class="sk" style="height:160px;border-radius:12px"></div>
      <div class="sk" style="height:160px;border-radius:12px"></div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    <div class="grid-2" style="margin-bottom:16px;align-items:start">
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <input class="input" id="arch-search" placeholder="Search title..." style="flex:1;min-width:180px" oninput="filterArch()"/>
        <select class="input" id="arch-year" style="width:120px" onchange="filterArch()">
          <option value="">All Years</option>
          @foreach($archives->pluck('academic_year')->filter()->unique()->sortDesc() as $year)
          <option value="{{ $year }}">S.Y. {{ $schoolYearOf($year) }}</option>
          @endforeach
        </select>
        <select class="input" id="arch-tag" style="width:150px" onchange="filterArch()">
          <option value="">All Categories</option>
          @foreach($tags as $tag)
          <option value="{{ $tag->tag_name }}">{{ $tag->tag_name }}</option>
          @endforeach
        </select>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;align-items:center">
        <span style="font-size:12px;color:var(--text3)" id="arch-count">Showing 8 studies</span>
      </div>
    </div>

    {{-- Manuscripts teams handed in themselves. They wait here until somebody
         decides: publishing puts it in the archive, declining removes it and
         frees the team to hand in a corrected copy. --}}
    @if ($pending->isNotEmpty())
    <div style="background:#fff;border:1.5px solid var(--accent);border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <span style="font-size:13px;font-weight:600;color:var(--text)">Capstone Manuscripts Waiting for Review</span>
        <span style="font-size:11px;font-weight:600;background:var(--accent-glow);color:var(--text);padding:3px 10px;border-radius:20px">{{ $pending->count() }}</span>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px">
        <div id="decision-error" hidden style="font-size:12px;color:var(--red)"></div>
        @foreach ($pending as $submission)
        <div style="border:1px solid var(--border);border-radius:10px;padding:12px" id="pending-{{ $submission->archived_id }}">
          <div style="font-size:13px;font-weight:600;color:var(--text)">{{ $submission->title }}</div>
          <div style="font-size:11.5px;color:var(--text3);margin-top:3px">
            {{ $submission->authors ?: 'No proponents recorded' }}
            &middot; {{ $submission->program ?: 'N/A' }} &middot; S.Y. {{ $schoolYearOf($submission->academic_year) }}
            @if ($submission->team)
              &middot; {{ $submission->team->team_name }}
            @endif
          </div>
          <div style="font-size:11.5px;color:var(--text3);margin-top:3px">
            Handed in by {{ $submission->submitter?->f_name }} {{ $submission->submitter?->l_name }}
            on {{ $submission->submitted_at?->format('M j, Y') }}
            @if ($submission->file_name)
              &middot; {{ $submission->file_name }}
            @endif
          </div>
          @if ($submission->abstract)
          <div style="font-size:12px;color:var(--text2);margin-top:6px">{{ Str::limit($submission->abstract, 220) }}</div>
          @endif
          <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
            <button class="btn btn-sm btn-ghost" style="padding:5px 14px"
              onclick="openAbstract({{ $submission->archived_id }})">View Abstract</button>
            <button class="btn btn-sm btn-primary" style="padding:5px 14px"
              onclick="decideSubmission({{ $submission->archived_id }}, 'published')">Publish to Archive</button>
            <button class="btn btn-sm btn-danger" style="padding:5px 14px"
              onclick="decideSubmission({{ $submission->archived_id }}, 'declined')">Decline</button>
          </div>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    <div id="add-archive-modal" style="display:none;background:#fff;border:1.5px solid var(--accent);border-radius:12px;padding:20px;margin-bottom:16px;box-shadow:0 4px 16px rgba(201,150,12,0.12)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <span style="font-size:13px;font-weight:600;color:var(--text)">Add Archived Capstone</span>
        <button onclick="hideAddArchive()" style="background:none;border:none;cursor:pointer;color:var(--text3);font-size:18px;line-height:1">&times;</button>
      </div>
      <div class="grid-2" style="gap:12px">
        <div>
          {{-- These fields had no ids and the Save button had no handler, so
               the whole form was drawn and did nothing. An archive nobody can
               add to is an archive that cannot name who wrote anything. --}}
          <div class="form-group"><label class="label">Capstone Title</label><input class="input" id="arch-title" placeholder="Full title of the capstone study"/></div>
          <div class="form-group"><label class="label">Proponents (comma-separated)</label><input class="input" id="arch-authors" placeholder="e.g. Dela Cruz, J., Santos, M."/></div>
          <div class="grid-2" style="gap:8px">
            {{-- Its own id: the page filter above is also a Year select, and
                 getElementById takes the first one, so saving read the filter
                 and threw away the year picked here. --}}
            <div class="form-group"><label class="label">School Year</label><select class="input" id="arch-new-year">
              @for ($y = (int) date('Y'); $y >= (int) date('Y') - 10; $y--)
                <option value="{{ $y }}">{{ $schoolYearOf($y) }}</option>
              @endfor
            </select></div>
            <div class="form-group"><label class="label">Program</label><select class="input" id="arch-program"><option>BSIT</option><option>BSCS</option><option>BSIS</option><option>BLIS</option></select></div>
          </div>
          {{-- Tags come from archived_doc_tags. The seven that used to be
               typed in here were checkboxes with no value and no tag behind
               them, so ticking one did nothing and could not. --}}
          @if ($tags->isNotEmpty())
            <div class="form-group"><label class="label">Category Tags</label>
              <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:2px">
                @foreach ($tags as $tag)
                  <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer">
                    <input type="checkbox" class="arch-tag" value="{{ $tag->tag_id }}" style="accent-color:var(--accent)"/>
                    {{ $tag->tag_name }}
                  </label>
                @endforeach
              </div>
            </div>
          @endif
        </div>
        <div>
          <div class="form-group"><label class="label">Abstract</label><textarea class="input" id="arch-abstract" style="min-height:120px" placeholder="Paste the capstone abstract here..."></textarea></div>
          <div class="form-group"><label class="label">Upload PDF (optional)</label>
            <div style="border:1.5px dashed var(--border2);border-radius:8px;padding:20px;text-align:center;cursor:pointer;transition:all 0.15s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border2)'">
              <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="var(--text3)" stroke-width="2" style="margin-bottom:6px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <div style="font-size:12px;color:var(--text2);font-weight:500">Click to upload PDF</div>
              <div style="font-size:10px;color:var(--text3)">PDF up to 50MB</div>
            </div>
          </div>
          <div id="arch-error" hidden style="font-size:12px;color:var(--red);margin-bottom:8px"></div>
          <button class="btn btn-primary" id="arch-save" style="width:100%;justify-content:center;padding:10px;font-size:13px" onclick="saveArchive()">Save to Archive</button>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px" id="arch-grid">
    @forelse($archives as $archive)
      <div class="archive-card"
        data-tags="{{ $archive->tags->pluck('tag_name')->implode(',') }}"
        data-year="{{ $archive->academic_year }}"
        data-title="{{ strtolower($archive->title) }}">
        <div class="archive-year">S.Y. {{ $schoolYearOf($archive->academic_year) }}</div>
        <div class="archive-title-text is-link" role="button" tabindex="0"
          onclick="openAbstract({{ $archive->archived_id }})"
          onkeydown="if(event.key==='Enter'){openAbstract({{ $archive->archived_id }})}">{{ $archive->title }}</div>
        <div class="archive-proponents">{{ $archive->authors ?: Str::limit($archive->abstract ?? 'No description', 60) }}</div>
        <div class="archive-tags">
          @foreach($archive->tags as $tag)
          <span class="atag" style="background:{{ $tag->tag_color ?? 'var(--accent-glow)' }}">{{ $tag->tag_name }}</span>
          @endforeach
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:8px;border-top:1px solid var(--border)">
          <span style="font-size:11px;color:var(--text3)">{{ $archive->program ?? 'N/A' }} — CCS</span>
          <span style="display:flex;gap:6px">
            <button class="btn btn-ghost arch-view" onclick="openAbstract({{ $archive->archived_id }})">View Abstract</button>
            <button class="btn btn-danger" style="font-size:10px;padding:3px 10px" onclick="deleteArchive({{ $archive->archived_id }})">Remove</button>
          </span>
        </div>
      </div>
    @empty
      <div style="grid-column:1/-1;text-align:center;padding:48px;color:var(--text3);font-size:13px">No archived capstones yet.</div>
    @endforelse
    </div>
    <div id="arch-empty" style="display:none;text-align:center;padding:48px;color:var(--text3);font-size:13px">No archived capstones match your search.</div>
  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')

{{-- The abstract, laid out like the abstract page of the manuscript itself. --}}
<div class="modal-overlay" id="abstract-modal" onclick="if (event.target === this) closeAbstract()">
  <article class="abs-sheet" role="dialog" aria-modal="true" aria-labelledby="abs-title">
    <button type="button" class="abs-close" aria-label="Close" onclick="closeAbstract()">
      <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
    <div class="abs-body">
      <div class="abs-kicker" id="abs-kicker">Capstone Project</div>
      <h2 class="abs-title" id="abs-title"></h2>
      <div class="abs-authors" id="abs-authors"></div>
      <dl class="abs-meta">
        <div><dt>Program</dt><dd id="abs-program"></dd></div>
        <div><dt>School Year</dt><dd id="abs-sy"></dd></div>
        <div><dt>Date</dt><dd id="abs-date"></dd></div>
      </dl>
      <div class="abs-heading">Abstract</div>
      <div class="abs-text" id="abs-text"></div>
      <div class="abs-keywords" id="abs-keywords" hidden><b>Keywords:</b> <span></span></div>
    </div>
    <div class="abs-foot">
      <a class="btn abs-pdf" id="abs-pdf" href="#" target="_blank" rel="noopener" hidden>
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
        Open PDF
      </a>
      <button type="button" class="btn btn-primary" onclick="closeAbstract()">Close</button>
    </div>
  </article>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const ABSTRACTS = @json($abstractData);

/* Text only (textContent, never innerHTML): an abstract is whatever a team typed. */
function openAbstract(id) {
  const doc = ABSTRACTS[id];
  if (!doc) return;

  document.getElementById('abs-kicker').textContent = doc.pending ? 'Capstone Project · Waiting for Review' : 'Capstone Project';
  document.getElementById('abs-title').textContent = doc.title;
  document.getElementById('abs-authors').textContent = doc.authors || 'No proponents recorded';
  document.getElementById('abs-program').textContent = doc.program;
  document.getElementById('abs-sy').textContent = doc.schoolYear === 'Not recorded' ? doc.schoolYear : 'S.Y. ' + doc.schoolYear;
  document.getElementById('abs-date').textContent = doc.date;

  // One paragraph per blank-line-separated block, the way it was written.
  const text = document.getElementById('abs-text');
  text.innerHTML = '';
  const paragraphs = (doc.abstract || '').split(/\n\s*\n/).map(p => p.replace(/\s*\n\s*/g, ' ').trim()).filter(Boolean);
  if (paragraphs.length) {
    paragraphs.forEach(function (p) {
      const el = document.createElement('p');
      el.textContent = p;
      text.appendChild(el);
    });
  } else {
    const empty = document.createElement('div');
    empty.className = 'abs-empty';
    empty.textContent = 'No abstract was recorded for this capstone.';
    text.appendChild(empty);
  }

  const keywords = document.getElementById('abs-keywords');
  keywords.hidden = !doc.keywords.length;
  keywords.querySelector('span').textContent = doc.keywords.join(', ');

  const pdf = document.getElementById('abs-pdf');
  pdf.hidden = !doc.pdf;
  pdf.href = doc.pdf || '#';

  const modal = document.getElementById('abstract-modal');
  modal.classList.add('show');
  modal.scrollTop = 0;
  document.body.style.overflow = 'hidden';
  modal.querySelector('.abs-close').focus();
}

function closeAbstract() {
  document.getElementById('abstract-modal').classList.remove('show');
  document.body.style.overflow = '';
}

function showAddArchive(){document.getElementById('add-archive-modal').style.display='block'}
function hideAddArchive(){document.getElementById('add-archive-modal').style.display='none'}
function filterArch(){
  const q=(document.getElementById('arch-search').value||'').toLowerCase();
  const yr=document.getElementById('arch-year').value;
  const tg=document.getElementById('arch-tag').value;
  const cards=document.querySelectorAll('#arch-grid .archive-card');
  let c=0;
  cards.forEach(card=>{
    const t=(card.dataset.title||'').toLowerCase();
    const y=card.dataset.year||'';
    const tags=card.dataset.tags||'';
    const show=(!q||t.includes(q))&&(!yr||y===yr)&&(!tg||tags.includes(tg));
    card.style.display=show?'':'none';
    if(show)c++;
  });
  document.getElementById('arch-count').textContent='Showing '+c+' studies';
  document.getElementById('arch-empty').style.display=c===0?'block':'none';
}
function saveArchive() {
  const title = document.getElementById('arch-title').value.trim();
  const err = document.getElementById('arch-error');
  const btn = document.getElementById('arch-save');

  err.hidden = true;

  if (!title) {
    err.textContent = 'A capstone needs a title.';
    err.hidden = false;

    return;
  }

  btn.disabled = true;
  btn.textContent = 'Saving...';

  fetch('/api/admin/archive', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': CSRF_TOKEN,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      title: title,
      authors: document.getElementById('arch-authors').value.trim() || null,
      academic_year: document.getElementById('arch-new-year').value,
      program: document.getElementById('arch-program').value,
      abstract: document.getElementById('arch-abstract').value.trim() || null,
      tag_ids: Array.prototype.slice.call(document.querySelectorAll('.arch-tag:checked'))
        .map(function (c) { return Number(c.value); }),
    }),
  })
    .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
    .then(function (res) {
      if (!res.ok) {
        const messages = res.body && res.body.errors ? Object.values(res.body.errors).flat() : [];
        err.textContent = messages[0] || (res.body && res.body.message) || 'Could not save.';
        err.hidden = false;
        btn.disabled = false;
        btn.textContent = 'Save to Archive';

        return;
      }

      location.reload();
    })
    .catch(function () {
      err.textContent = 'Could not reach the server. Please try again.';
      err.hidden = false;
      btn.disabled = false;
      btn.textContent = 'Save to Archive';
    });
}

async function deleteArchive(id) {
  const agreed = await confirmSheet({
    title: 'Delete this archived capstone?',
    body: 'It leaves the archive for good, and similarity checks will stop '
      + 'comparing new titles against it.',
    confirmLabel: 'Delete',
    danger: true,
  });

  if (!agreed) return;

  fetch('/api/admin/archive/' + id, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
  }).then(() => location.reload());
}

/* Publish a team's manuscript into the archive, or decline it. */
async function decideSubmission(id, decision) {
  const publishing = decision === 'published';
  const err = document.getElementById('decision-error');

  if (err) err.hidden = true;

  const agreed = await confirmSheet({
    title: publishing ? 'Publish this manuscript?' : 'Decline this manuscript?',
    body: publishing
      ? 'It goes into the archive, and similarity checks start comparing new '
        + 'titles against it.'
      : 'It is removed and the team is free to hand in a corrected copy.',
    confirmLabel: publishing ? 'Publish' : 'Decline',
    danger: ! publishing,
  });

  if (!agreed) return;

  function failed(message) {
    if (!err) return;
    err.textContent = message;
    err.hidden = false;
  }

  fetch('/api/admin/archive/' + id + '/submission', {
    method: 'PATCH',
    headers: {
      'X-CSRF-TOKEN': CSRF_TOKEN,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ decision: decision }),
  })
    .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
    .then(function (res) {
      if (!res.ok) {
        failed((res.body && res.body.message) || 'Could not save that decision.');

        return;
      }

      location.reload();
    })
    .catch(function () {
      failed('Could not reach the server. Please try again.');
    });
}


/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  if (!sidebarEl) return;
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
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
</script>
@include('partials.confirm-sheet')
@include('partials.escape-closes-modals')
</body>
</html>
