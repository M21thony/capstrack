{{--
  One title: the decision, the form the team filled in, and the similarity
  report - all on the page, nothing folded away.

  The report used to show the single closest match inline. Here it is a
  ranked list of one-line rows, and each row's detail is a page of its own.
--}}
@extends('capsa.team-management._shell')

@section('title', $title['title'])

@section('tm-content')
@php
  $team = $row->team;
  $final = $team->selected_pre_proposal_id;
  $isFinal = $final && (int) $title['id'] === (int) $final;
  $pending = collect($titles)->where('status', 'pending')->count();
  $top = (int) round((float) ($title['duplication']['top_score'] ?? 0));
  $highlySimilar = collect($matches)->filter(fn ($m) => (float) ($m['score'] ?? 0) >= 65)->count();
  $answers = [
    'SDG' => is_array($title['sdg_goals']) ? implode(', ', $title['sdg_goals']) : $title['sdg_goals'],
    'Problem statement' => $title['problem_statement'],
    'Proposed solution' => $title['proposed_solution'],
    'Scope' => $title['scope'],
    'Limitations' => $title['limitations'],
    'Development method' => $title['sdm'],
    'Clients/Organization' => $title['clients'] ?? null,
    'Address of the organization' => $title['client_address'] ?? null,
    'Representative' => $title['client_representative'] ?? null,
    'Their position' => $title['client_position'] ?? null,
  ];

  // Tags are the one answer that goes somewhere. They come from the same fixed
  // list (App\Support\ProjectTags) that Analytics filters teams by, so a tag
  // here can show every other team working on that kind of project - which is
  // exactly the question this page raises while a title is being judged.
  $projectTags = is_array($title['tags'])
      ? $title['tags']
      : array_map('trim', explode(',', (string) $title['tags']));
  $projectTags = array_values(array_filter($projectTags, 'filled'));
@endphp

<div class="th tpg">
  <div class="tile"><x-tm-icon n="file" s="22" /></div>
  <div class="grow">
    <p>Title #{{ $index + 1 }} of {{ count($titles) }}</p>
    <h1>{{ $title['title'] }}</h1>
    <div class="ps">@include('capsa.team-management._marks')</div>
  </div>
</div>

<section class="card ppd">
  <div class="acts">
    @if ($title['document_url'])
      <button class="btn btn-o" type="button" id="pdf-open"><x-tm-icon n="file" s="14" />View original PDF</button>
    @endif
    <span class="sp"></span>
    @if ($title['status'] === 'pending')
      <button class="btn btn-p" type="button" id="rec-open">Recommend</button>
      <button class="btn btn-o" type="button" id="rej-open">Reject</button>
    @endif
  </div>

  @if ($title['status'] === 'rejected')
    <p class="msg why"><b>Reason sent to the team:</b> {{ $title['rejection_reason'] }}</p>
  @elseif ($title['status'] === 'approved')
    <p class="msg">{{ $isFinal
      ? "This is the team's final title."
      : ($pending
        ? 'The final title is settled once every title has a decision.'
        : 'You recommended more than one title, so the team picks between them.') }}</p>
    @if ($title['capsa_note'])
      <p class="msg"><b>Suggestion sent to the team:</b> {{ $title['capsa_note'] }}</p>
    @endif
  @endif

  <div>
    <h4>Pre-proposal form</h4>
    <dl class="ans">
      @foreach ($answers as $label => $value)
        <dt>{{ $label }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd>
      @endforeach
      <dt>Tags</dt>
      <dd class="ptags">
        @forelse ($projectTags as $tag)
          <a href="{{ route('capsa.analytics', ['tag' => $tag]) }}"
             title="See every team tagged {{ $tag }}">{{ $tag }}</a>
        @empty
          —
        @endforelse
      </dd>
    </dl>
  </div>
</section>

<section class="card ppd rep">
  <div>
    <h4>Similarity report &middot; {{ count($matches) }} similar {{ \Illuminate\Support\Str::plural('study', count($matches)) }}</h4>
    <div class="top">
      <b>{{ $top }}%</b>
      @if ($top >= 65)
        <span class="pill p-red"><x-tm-icon n="warn" s="12" />Highly similar</span>
      @endif
      <span class="k">closest study in the capstone archive</span>
    </div>
    {{-- Only the one band the adviser asked for. Nothing is said about the rest. --}}
    @if (count($matches))
      <div class="tly">
        <span class="pill {{ $highlySimilar ? 'p-red' : 'p-gray' }}">
          @if ($highlySimilar)<x-tm-icon n="warn" s="12" />@endif{{ $highlySimilar }} highly similar
        </span>
      </div>
    @endif
  </div>

  @if (count($matches))
    <ol class="ppr mtl">
      @foreach ($matches as $k => $match)
        <li>
          <a class="rw" href="{{ route('capsa.team_management.similar', [$team->team_id, $title['id'], $k + 1]) }}">
            <span class="no">#{{ $k + 1 }}</span>
            <span class="tx">
              <span class="tt">{{ $match['title'] }}</span>
              <span class="mm">{{ collect([$match['academic_year'], $match['program'], $match['reason']])->filter()->implode(' · ') }}</span>
            </span>
            <span class="ps"><x-tm-sim :score="$match['score']" /></span>
          </a>
        </li>
      @endforeach
    </ol>
  @else
    <p class="none">No similar capstones were found in the archive.</p>
  @endif
</section>

@if ($title['document_url'])
  <dialog class="pdfd" id="pdf-dlg" aria-label="Original pre-proposal PDF">
    <div class="doc-h">
      <span class="di"><x-tm-icon n="file" /></span>
      <span class="fn">{{ $title['title'] }}<small>The file the team uploaded</small></span>
      <a href="{{ $title['document_url'] }}" target="_blank" rel="noopener"><x-tm-icon n="ext" s="13" />Open in a new tab</a>
      <button class="x" type="button" data-close aria-label="Close">&times;</button>
    </div>
    <iframe title="Original pre-proposal PDF" style="flex:1;width:100%;border:0" data-src="{{ $title['document_url'] }}"></iframe>
  </dialog>
@endif

@if ($title['status'] === 'pending')
  <dialog id="rec-dlg" aria-labelledby="rec-h">
    <form method="dialog" id="rec-form">
      <div class="mh"><h4 id="rec-h">Recommend this title?</h4><div class="s">{{ $title['title'] }}</div></div>
      <div class="mb">
        <p class="rj-l" style="margin:0 0 14px">One recommended title becomes theirs. Recommend a second as well and the team picks between them.</p>
        <label class="rj-l" for="rec-note">Suggestion for the team <span style="color:var(--mut)">(optional)</span></label>
        <textarea class="rj" id="rec-note" maxlength="1000"
          placeholder="For example: narrow the scope to one building, or say what this adds that the closest match does not"></textarea>
        <div class="rj-e" id="rec-err" hidden></div>
      </div>
      <div class="mf">
        <button class="btn btn-o" type="button" data-close>Cancel</button>
        <button class="btn btn-p" type="submit">Recommend</button>
      </div>
    </form>
  </dialog>

  <dialog id="rej-dlg" aria-labelledby="rej-h">
    <form method="dialog" id="rej-form">
      <div class="mh"><h4 id="rej-h">Reject this title</h4><div class="s">Rejecting: {{ $title['title'] }}</div></div>
      <div class="mb">
        <label class="rj-l" for="rej-reason">Your reason is sent to the team with the rejection.</label>
        <textarea class="rj" id="rej-reason" maxlength="1000"
          placeholder="Tell the team why this title is being rejected (required)"></textarea>
        <div class="rj-e" id="rej-err" hidden>Give the team a reason they can act on.</div>
      </div>
      <div class="mf">
        <button class="btn btn-o" type="button" data-close>Cancel</button>
        <button class="btn btn-r" type="submit">Reject</button>
      </div>
    </form>
  </dialog>
@endif
@endsection

@push('scripts')
<script>
  (function () {
    /* One dialog at a time, and a click on the backdrop closes the open one. */
    const dialogs = [...document.querySelectorAll('dialog')];
    const open = d => { dialogs.forEach(o => o.open && o.close()); d.showModal(); };
    dialogs.forEach(d => {
      d.querySelectorAll('[data-close]').forEach(b => b.onclick = () => d.close());
      d.onclick = e => { if (e.target === d) d.close(); };
    });

    /* The PDF is only fetched once she asks to see it. */
    const pdf = document.getElementById('pdf-dlg'), pdfBtn = document.getElementById('pdf-open');
    if (pdfBtn) pdfBtn.onclick = () => {
      const f = pdf.querySelector('iframe');
      if (!f.src) f.src = f.dataset.src;
      open(pdf);
    };

    /* The decision endpoint answers in JSON, so the page asks it directly and
       reloads itself on success rather than landing her on a JSON page. */
    const url = @json($title['update_url'] ?? null), token = document.querySelector('meta[name=csrf-token]').content;
    const send = async (body, errBox, btn) => {
      btn.disabled = true;
      try {
        const r = await fetch(url, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
          body: JSON.stringify(body),
        });
        const d = await r.json().catch(() => ({}));
        if (r.ok) return location.reload();
        errBox.textContent = d.message || Object.values(d.errors || {}).flat()[0] || 'That could not be saved. Try again.';
        errBox.hidden = false;
      } catch (e) {
        errBox.textContent = 'That could not be saved. Check your connection and try again.';
        errBox.hidden = false;
      }
      btn.disabled = false;
    };

    const recBtn = document.getElementById('rec-open');
    if (recBtn) {
      const dlg = document.getElementById('rec-dlg'), err = document.getElementById('rec-err');
      recBtn.onclick = () => { err.hidden = true; open(dlg); };
      document.getElementById('rec-form').onsubmit = e => {
        e.preventDefault();
        err.hidden = true;
        send({ status: 'approved', note: document.getElementById('rec-note').value.trim() }, err, e.submitter);
      };
    }

    const rejBtn = document.getElementById('rej-open');
    if (rejBtn) {
      const dlg = document.getElementById('rej-dlg'), err = document.getElementById('rej-err'),
            box = document.getElementById('rej-reason');
      rejBtn.onclick = () => { err.hidden = true; open(dlg); };
      document.getElementById('rej-form').onsubmit = e => {
        e.preventDefault();
        const reason = box.value.trim();
        if (!reason) {
          err.textContent = 'Give the team a reason they can act on.';
          err.hidden = false;
          box.focus();
          return;
        }
        err.hidden = true;
        send({ status: 'rejected', reason }, err, e.submitter);
      };
    }
  })();
</script>
@endpush
