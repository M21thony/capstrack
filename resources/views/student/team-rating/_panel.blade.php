{{--
  Team Management › Team Rating: the ratings open for this student's
  section, how far they are with each teammate, and Review & submit. The
  rating itself happens on the rate page (student.team_rating.rate).
--}}
@php
  use App\Http\Controllers\TeamRatingController as TR;
  use App\Support\PeerRatingRubric;
  $open = $rating['forms']->where('status', 'open');
  $others = $rating['forms']->where('status', '!=', 'open');
  $review = (int) request()->query('review');
  $student = auth()->user();
@endphp
<style nonce="{{ Vite::cspNonce() }}">
  .trs { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 16px; align-items: start; }
  .trs-card { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; }
  .trs-card + .trs-card { margin-top: 16px; }
  .trs-ch { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  .trs-ch h2 { margin: 0; font-size: 12px; letter-spacing: .1em; text-transform: uppercase; color: var(--text2); }
  .trs-ch .r { margin-left: auto; font-size: 13px; color: var(--text2); }
  .trs-name { font-weight: 700; font-size: 15px; margin-top: 2px; color: var(--text); }
  .trs-cov { font-size: 12.5px; color: var(--text2); }
  .trs-row { display: flex; align-items: center; gap: 14px; padding: 13px 20px; border-top: 1px solid var(--border); }
  .trs-row:first-child { border-top: none; }
  .trs-av { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 13px; color: #fff; background: var(--navy-light, #272a36); flex-shrink: 0; }
  .trs-row .nm { font-weight: 600; color: var(--text); }
  .trs-row .sub { font-size: 12.5px; color: var(--text2); margin-top: 2px; }
  .trs-row .r { margin-left: auto; display: flex; align-items: center; gap: 12px; white-space: nowrap; }
  .trs-row.self { opacity: .6; }
  .trs-foot { display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-top: 1px solid var(--border); flex-wrap: wrap; font-size: 13px; color: var(--text2); }
  .trs-foot .r { margin-left: auto; display: flex; gap: 10px; align-items: center; }
  .trs-bar { height: 6px; background: var(--bg4); border-radius: 3px; overflow: hidden; width: 120px; }
  .trs-bar span { display: block; height: 100%; background: var(--accent); }
  .trs-note { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; color: var(--text2); background: var(--bg3); border: 1px solid var(--border); border-radius: 10px; padding: 11px 14px; margin-top: 16px; }
  .trs-note svg { flex-shrink: 0; margin-top: 1px; }
  .trs-empty { padding: 28px 20px; color: var(--text2); font-size: 13.5px; }
  .trs-olist { max-height: calc(100vh - 290px); max-height: calc(100dvh - 290px); overflow: auto; }
  .trs .green { color: var(--green); } .trs .gold { color: var(--accent3); } .trs .muted { color: var(--text3); } .trs .red { color: var(--red); }
  .trs-btn { border: 1px solid var(--border); background: var(--bg2); color: var(--text); border-radius: 8px; padding: 8px 14px; font: 600 13px 'DM Sans', 'DM Sans Fallback', sans-serif; cursor: pointer; min-height: 36px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
  .trs-btn:hover { border-color: var(--text3); }
  .trs-btn.pri { background: var(--accent); border-color: var(--accent); color: #18191f; }
  .trs-btn.pri:hover { background: var(--accent2); border-color: var(--accent2); }
  .trs-btn:disabled { opacity: .45; cursor: not-allowed; }
  .trs-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
  .trs-dlg { border: none; border-radius: 14px; padding: 0; margin: auto; width: min(640px, calc(100% - 32px)); max-height: calc(100vh - 32px); max-height: calc(100dvh - 32px); box-shadow: 0 20px 50px rgba(0, 0, 0, .25); color: var(--text); background: var(--bg2); }
  .trs-dlg::backdrop { background: rgba(20, 20, 24, .5); }
  .trs-dlg[open] { display: flex; flex-direction: column; }
  .trs-dlg .mh { padding: 18px 22px 14px; display: flex; align-items: flex-start; gap: 12px; border-bottom: 1px solid var(--border); }
  .trs-dlg .mh h3 { margin: 0; font-size: 17px; } .trs-dlg .mh p { margin: 4px 0 0; color: var(--text2); font-size: 13px; }
  .trs-dlg .x { margin-left: auto; border: none; background: none; font-size: 20px; cursor: pointer; color: var(--text3); width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0; }
  .trs-dlg .x:hover { background: var(--bg4); }
  .trs-dlg .mb { padding: 18px 22px; overflow: auto; }
  .trs-dlg .mf { padding: 14px 22px; border-top: 1px solid var(--border); background: var(--bg3); display: flex; gap: 8px; justify-content: flex-end; }
  .trs-dlg table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  .trs-dlg th { background: var(--navy-mid, #1e2028); color: #e9e9ee; font-weight: 600; text-align: left; padding: 8px 10px; font-size: 12px; white-space: nowrap; }
  .trs-dlg td { padding: 7px 10px; border-top: 1px solid var(--border); }
  .trs-dlg .num { text-align: right; font-variant-numeric: tabular-nums; }
  .trs-dlg .warn { background: var(--red-bg); border: 1px solid #f3c4c4; color: #7f1d1d; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-top: 12px; }
  .trs-dlg .err { color: var(--red); font-size: 13px; font-weight: 600; margin-top: 10px; }
  body.tm-on-rating #contentWrapper,
  body.tm-on-rating .pagination-container,
  body.tm-on-rating .tutorial-note { display: none !important; }
  @media (max-width: 1100px) { .trs { grid-template-columns: minmax(0, 1fr); } .trs-olist { max-height: none; } }
  @media (max-width: 760px) {
    .trs-row { flex-wrap: wrap; } .trs-row .r { margin-left: 50px; }
    .trs-dlg { width: 100%; max-width: 100%; height: 100%; max-height: 100%; border-radius: 0; }
  }
</style>

<div class="trs">
  <div>
    @if (! $myTeam)
      <div class="trs-card"><div class="trs-empty">Join a team first. Team Rating is where you rate your teammates.</div></div>
    @elseif ($open->isEmpty())
      <div class="trs-card"><div class="trs-ch"><h2>Team Rating</h2></div><div class="trs-empty">No rating is open right now. Your CaPSA/TSA opens one when an activity is due, and you'll get a notice.</div></div>
    @endif

    @foreach ($open as $r)
      @php
        $f = $r->form;
        $items = $f->items;
        $all = $r->mates->every(fn ($m) => $m->done >= $r->need);
        $n = $r->mates->filter(fn ($m) => $m->done >= $r->need)->count();
      @endphp
      <div class="trs-card" id="trs-form-{{ $f->id }}">
        <div class="trs-ch">
          <div style="min-width:0">
            <h2>Open now</h2>
            <div class="trs-name">{{ $f->name }}</div>
            <div class="trs-cov">Covers {{ $items->pluck('label')->implode(', ') }}</div>
          </div>
          <div class="r">
            @if ($r->submittedAt)
              <b class="green">Submitted {{ \Illuminate\Support\Carbon::parse($r->submittedAt)->format('M j') }}</b>
            @else
              Due <b style="color:var(--text)">{{ $f->dueOn()->format('M j') }}, 11:59 PM</b>
            @endif
          </div>
        </div>
        @foreach ($r->mates as $m)
          @php $c = $m->done >= $r->need; @endphp
          <div class="trs-row">
            <span class="trs-av" aria-hidden="true">{{ $m->student->avatarInitials() }}</span>
            <div>
              <div class="nm">{{ TR::name($m->student) }}</div>
              <div class="sub">{{ $m->student->s_role_name ?: 'Member' }} ·
                @if ($c)<span class="green">Rated on all {{ $items->count() }} {{ \Illuminate\Support\Str::plural('activity', $items->count()) }}</span>
                @elseif ($m->done)<span class="gold">{{ $m->done }} of {{ $r->need }} scores</span>
                @else<span class="muted">Not rated yet</span>@endif
              </div>
            </div>
            <div class="r">
              @if ($r->submittedAt)
                <span class="muted">Sent to your CaPSA/TSA</span>
              @else
                <a class="trs-btn {{ $c ? '' : 'pri' }}" href="{{ route('student.team_rating.rate', [$f, $m->student->student_id]) }}">{{ $c ? 'Edit' : ($m->done ? 'Continue' : 'Rate') }}</a>
              @endif
            </div>
          </div>
        @endforeach
        <div class="trs-row self">
          <span class="trs-av" aria-hidden="true">{{ $student->avatarInitials() }}</span>
          <div><div class="nm">{{ TR::name($student) }} (you)</div><div class="sub">You don't rate yourself</div></div>
        </div>
        <div class="trs-foot">
          @if ($r->submittedAt)
            <span>Your ratings went to your CaPSA/TSA. They can't be changed now.</span>
          @elseif ($r->mates->isEmpty())
            <span>You have no teammates to rate yet.</span>
          @else
            <div class="trs-bar"><span style="width:{{ round($n / $r->mates->count() * 100) }}%"></span></div>
            <span><b style="color:var(--text)">{{ $n }} of {{ $r->mates->count() }}</b> teammates rated</span>
            <div class="r">
              @unless ($all)<span class="muted" style="font-size:12.5px">Rate all teammates to submit</span>@endunless
              <button type="button" class="trs-btn pri" data-trs-review="{{ $f->id }}" @disabled(! $all)>Review &amp; submit</button>
            </div>
          @endif
        </div>
      </div>

      @if (! $r->submittedAt && $all && $r->mates->isNotEmpty())
        <dialog class="trs-dlg" id="trs-dlg-{{ $f->id }}" aria-labelledby="trs-dlg-h-{{ $f->id }}">
          <div class="mh">
            <div><h3 id="trs-dlg-h-{{ $f->id }}">Submit your {{ $f->name }}?</h3><p>Your scores for {{ $items->pluck('label')->implode(', ') }} go to your CaPSA/TSA only. Check the totals before you send them.</p></div>
            <button class="x" type="button" data-trs-close aria-label="Close">×</button>
          </div>
          <div class="mb">
            <div style="overflow:auto">
              <table>
                <thead><tr><th>Teammate</th>@foreach ($items as $i)<th class="num">{{ $i->label }}</th>@endforeach</tr></thead>
                <tbody>
                  @foreach ($r->mates as $m)
                    <tr><td>{{ TR::name($m->student) }}</td>@foreach ($m->totals as $t)<td class="num"><b>{{ $t }}</b></td>@endforeach</tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <p style="font-size:12.5px;color:var(--text2);margin:8px 0 0">Each total is out of {{ PeerRatingRubric::MAX }}.</p>
            <div class="warn">You can't change ratings after you submit.</div>
            <div class="err" data-trs-err hidden></div>
          </div>
          <div class="mf">
            <button type="button" class="trs-btn" data-trs-close>Keep editing</button>
            <button type="button" class="trs-btn pri" data-trs-send="{{ route('student.team_rating.submit', $f) }}">Submit ratings</button>
          </div>
        </dialog>
      @endif
    @endforeach

    @if ($myTeam)
      <div class="trs-note">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        <div>Only your CaPSA/TSA sees these ratings. Your teammates never see what you gave them, and you never see what they gave you.</div>
      </div>
    @endif
  </div>

  <div class="trs-card">
    <div class="trs-ch"><h2>Other ratings</h2></div>
    <div class="trs-olist">
      @forelse ($others as $x)
        <div class="trs-row" style="align-items:flex-start">
          <div style="min-width:0">
            <div class="nm">{{ $x->form->name }}</div>
            <div class="sub">Covers {{ $x->form->items->pluck('label')->implode(', ') }}</div>
          </div>
          <div class="r">
            @if ($x->status === 'scheduled')
              <span class="muted">Opens {{ $x->form->opensOn()->format('M j') }}</span>
            @elseif ($x->submittedAt)
              <span class="green">Submitted</span>
            @else
              <span class="muted">Closed</span>
            @endif
          </div>
        </div>
      @empty
        <div class="trs-row muted">Nothing else yet.</div>
      @endforelse
    </div>
  </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  document.querySelectorAll('[data-trs-review]').forEach(function (b) {
    b.addEventListener('click', function () { var d = document.getElementById('trs-dlg-' + b.getAttribute('data-trs-review')); if (d) { d.showModal(); } });
  });
  document.querySelectorAll('.trs-dlg').forEach(function (d) {
    d.querySelectorAll('[data-trs-close]').forEach(function (x) { x.addEventListener('click', function () { d.close(); }); });
    d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
    var send = d.querySelector('[data-trs-send]');
    var err = d.querySelector('[data-trs-err]');
    send.addEventListener('click', function () {
      send.disabled = true; send.textContent = 'Sending…'; err.hidden = true;
      fetch(send.getAttribute('data-trs-send'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
        .then(function (res) { return res.json().then(function (j) { return { ok: res.ok, j: j }; }); })
        .then(function (r) {
          if (!r.ok) { throw new Error((r.j.errors && Object.values(r.j.errors)[0][0]) || r.j.message || 'Could not submit.'); }
          var url = new URL(location.href); url.searchParams.delete('review'); url.searchParams.set('tab', 'rating'); url.searchParams.set('sent', '1');
          location.replace(url.pathname + url.search);
        })
        .catch(function (e) { err.textContent = e.message; err.hidden = false; send.disabled = false; send.textContent = 'Submit ratings'; });
    });
  });
  // The rate page's last "Review & submit" lands here with ?review=<form>.
  @if ($review)
    var auto = document.getElementById('trs-dlg-{{ $review }}');
    if (auto) { auto.showModal(); }
  @endif
  @if (request()->query('sent'))
    var sentUrl = new URL(location.href); sentUrl.searchParams.delete('sent'); history.replaceState(null, '', sentUrl.pathname + sentUrl.search);
    window.addEventListener('load', function () { if (typeof showToast === 'function') { showToast('Ratings sent to your CaPSA/TSA', 'success'); } });
  @endif
})();
</script>
