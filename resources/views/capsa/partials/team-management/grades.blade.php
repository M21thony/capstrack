{{-- Grades: CaPSA/TSA types each paper sheet's total and attaches the signed
     sheets as one PDF (Encode scores), then sees every team's panel average
     (Grade summary). The Panel Chair still records the decision. --}}
@include('capsa.partials.team-management._milestone_kit')

@php
  $methods = ['individual' => 'Individual', 'joint' => 'Joint', 'mixed' => 'Mixed'];
  $trim = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
  $round = fn ($d) => $d->attempt > 1 ? ' · Redefense '.($d->attempt - 1) : '';
  $state = function ($d) {
      $open = collect($d->records)->where('own', false);
      if ($open->isNotEmpty() && $open->every(fn ($r) => $r['status'] === \App\Models\PanelGrade::SUBMITTED)) return ['Submitted', 'ok'];
      if ($open->isEmpty() && $d->panelSize && $d->in >= $d->panelSize) return ['Submitted', 'ok'];
      if ($open->contains(fn ($r) => $r['total'] !== null)) return ['Draft', 'gold'];
      return ['Not started', 'mut'];
  };
  $js = $defenses->mapWithKeys(fn ($d) => [$d->id => [
      'team' => $d->team,
      'encode' => route('capsa.grades.encode', $d->id),
      'upload' => route('capsa.grades.sheet.upload', $d->id),
      'sheet' => (bool) $d->sheet,
      'open' => collect($d->records)->where('own', false)->pluck('id')->values(),
      'submitted' => $state($d)[0] === 'Submitted',
      'cats' => collect($d->cats)->map(fn ($max, $name) => [$name, $max])->values(),
      'saved' => collect($d->records)->filter(fn ($r) => $r['cats'])
          ->mapWithKeys(fn ($r) => [$r['id'] => array_map(fn ($n) => $r['cats'][$n] ?? null, array_keys($d->cats))]),
  ]]);
@endphp

<style nonce="{{ Vite::cspNonce() }}">
  .tm-hub .gr-page{display:flex;flex-direction:column;gap:12px;height:max(480px,calc(100dvh - 260px))}
  .tm-hub .gr-page .card{display:flex;flex-direction:column;flex:1;min-height:0}
  .tm-hub .gr-page .tscroll{flex:1;min-height:0;max-height:none}
  .tm-hub .gr-page [hidden]{display:none !important}
  .tm-hub .gr-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 14px;border-bottom:1px solid var(--line);flex:none}
  .tm-hub .gr-bar .grow{flex:1}
  .tm-hub .gr-seg{display:inline-flex;gap:3px;padding:3px;border:1px solid var(--line);border-radius:9px;background:var(--surface)}
  .tm-hub .gr-seg button{border:0;background:none;padding:6px 13px;border-radius:7px;font:inherit;font-size:13px;font-weight:600;color:var(--mut);cursor:pointer}
  .tm-hub .gr-seg button[aria-pressed="true"]{background:var(--gold-bg);color:var(--ink)}
  .tm-hub .gr-bar select,.tm-hub .gr-bar input{font:inherit;font-size:13px;padding:7px 10px;border:1px solid var(--line);border-radius:8px;background:var(--surface);color:var(--ink)}
  .tm-hub .gr-bar input{width:220px}
  .tm-hub .gr-win{font-size:12.5px;color:var(--mut)}
  .tm-hub .gr-page tbody tr{cursor:default}
  .tm-hub .gr-page tbody tr:hover td{background:none}
  .tm-hub .gr-page td{padding:7px 14px;vertical-align:middle}
  .tm-hub .gr-page .b{font-weight:700;color:var(--ink)}
  .tm-hub .gr-page .sub{font-size:11.5px;color:var(--mut);margin-top:1px}
  /* Short rows: the title keeps to one line, panelists sit side by side. */
  .tm-hub .gr-page td:first-child{width:26%;max-width:0}
  .tm-hub .gr-page td:nth-child(4){width:48%}
  .tm-hub .gr-page td:first-child .sub{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .tm-hub .gr-page .nw{white-space:nowrap}
  .tm-hub .gr-page .num{text-align:right;font-variant-numeric:tabular-nums}
  .tm-hub .gr-tag{font-size:12.5px;font-weight:700;color:var(--ink2)}
  .tm-hub .gr-recs{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:5px 18px;min-width:230px}
  .tm-hub .gr-rec{display:grid;grid-template-columns:minmax(0,1fr) 78px;gap:8px;align-items:center;margin:0;cursor:default}
  .tm-hub .gr-rec .who{font-size:12.5px;color:var(--ink2);line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .tm-hub .gr-rec .who i{font-style:normal;color:var(--mut);font-size:11px;display:block;overflow:hidden;text-overflow:ellipsis}
  .tm-hub .gr-rec input{width:100%;box-sizing:border-box;padding:4px 8px;border:1px solid var(--line2);border-radius:7px;font:inherit;font-size:13px;text-align:right;background:var(--surface);color:var(--ink);font-variant-numeric:tabular-nums}
  .tm-hub .gr-rec input:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,168,44,.22)}
  .tm-hub .gr-rec input.bad{border-color:var(--bad)}
  .tm-hub .gr-rec input:disabled{background:var(--tint);color:var(--ink2)}
  .tm-hub .gr-recs:has(.gr-cat){grid-template-columns:repeat(auto-fill,minmax(300px,1fr))}
  .tm-hub .gr-rec:has(.gr-cat){grid-template-columns:minmax(0,1fr) 78px auto}
  .tm-hub .gr-cat{font:inherit;font-size:12px;font-weight:600;color:var(--ink2);background:var(--surface);border:1px solid var(--line2);border-radius:7px;padding:4px 9px;cursor:pointer;white-space:nowrap}
  .tm-hub .gr-cat:hover{border-color:var(--gold)}
  .tm-hub .gr-rec .fixed{text-align:right;font-size:12.5px;color:var(--ink2);font-variant-numeric:tabular-nums}
  .tm-hub .gr-pdf{display:flex;gap:6px;align-items:center;white-space:nowrap}
  .tm-hub .gr-pdf .btn{padding:4px 10px;font-size:12px}
  .tm-hub .gr-pdf .none{font-size:12px;color:var(--bad);font-weight:600}
  .tm-hub .gr-st{font-weight:700;font-size:12.5px;white-space:nowrap}
  .tm-hub .gr-st.ok{color:var(--ok)}
  .tm-hub .gr-st.gold{color:var(--gold-ink)}
  .tm-hub .gr-st.mut{color:var(--mut)}
  .tm-hub .gr-page .foot{flex:none}
  .tm-hub .gr-page .foot .grow{flex:1;color:var(--bad);font-weight:600}
  .tm-hub .gr-page .foot .btn{padding:7px 14px}
  .tm-hub .gr-page .btn:disabled{opacity:.5;cursor:not-allowed}
  .tm-hub .gr-res{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:2px 22px;font-size:12.5px;color:var(--ink2);min-width:220px}
  .tm-hub .gr-res span{display:flex;justify-content:space-between;gap:14px}
  .tm-hub .gr-res b{font-variant-numeric:tabular-nums;color:var(--ink)}
  .tm-hub .gr-res b.p{color:var(--mut);font-weight:500}
  .tm-hub .gr-avg{font-size:15px;font-weight:700;color:var(--ink);font-variant-numeric:tabular-nums}
  dialog.gr-dlg{width:min(560px,calc(100% - 32px));max-width:none;margin:auto}
  .gr-dlg table{width:100%;border-collapse:collapse;font-size:13px}
  .gr-dlg th{text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#d4d6de;padding:8px 10px;background:#18191f}
  .gr-dlg td{padding:7px 10px;border-bottom:1px solid var(--border)}
  .gr-dlg td input{width:110px;box-sizing:border-box;padding:5px 8px;border:1px solid var(--border);border-radius:7px;font:inherit;font-size:13px;text-align:right;font-variant-numeric:tabular-nums}
  .gr-dlg td input:invalid{border-color:#dc2626}
  .gr-dlg .num{text-align:right;font-variant-numeric:tabular-nums}
  .gr-dlg .x{margin-left:auto;border:0;background:none;font-size:22px;line-height:1;color:var(--text3);cursor:pointer;width:32px;height:32px;border-radius:8px}
  .gr-dlg .dh{display:flex;gap:12px;align-items:flex-start;padding-bottom:12px;border-bottom:1px solid var(--border)}
  @media (max-width:900px){
    .tm-hub .gr-page{height:auto}
    .tm-hub .gr-page .card{flex:none}
    .tm-hub .gr-page .tscroll{max-height:65dvh}
    .tm-hub .gr-bar input{width:100%}
  }
</style>

<div class="gr-page">
  <div class="card">
    <div class="gr-bar">
      <div class="gr-seg" role="group" aria-label="Grades view">
        <button type="button" data-view="encode" aria-pressed="true">Encode scores</button>
        <button type="button" data-view="summary" aria-pressed="false">Grade summary</button>
      </div>
      <select id="gr-stage" aria-label="Stage">
        <option value="">All stages</option>
        @foreach ($stages as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
      </select>
      <span class="gr-win" id="gr-win"></span>
      <span class="grow"></span>
      <input type="search" id="gr-q" placeholder="Search team or title" aria-label="Search team or title">
    </div>

    @if ($defenses->isEmpty())
      <div class="empty"><b>No scheduled defenses yet</b>Teams show here once their defense has a date.</div>
    @else
      {{-- Encode scores --}}
      <div class="tscroll" data-pane="encode">
        <table>
          <thead><tr><th>Team &amp; defense</th><th>Schedule</th><th>Method</th><th>Totals from the sheets</th><th>Signed PDF</th><th>Status</th></tr></thead>
          <tbody>
            @foreach ($defenses as $d)
              @php [$st, $tone] = $state($d); @endphp
              <tr data-id="{{ $d->id }}" data-type="{{ $d->type }}" data-q="{{ strtolower($d->team.' '.$d->title) }}">
                <td>
                  <div class="b">{{ $d->team }}</div>
                  @if ($d->title)<div class="sub">{{ $d->title }}</div>@endif
                  <div class="sub">{{ $d->type }}{{ $round($d) }} · {{ $d->section }}</div>
                </td>
                <td class="nw">{{ $d->date->format('M j, Y') }}<div class="sub">{{ $d->date->format('g:i A') }}{{ $d->venue ? ' · '.$d->venue : '' }}</div></td>
                <td class="nw"><span class="gr-tag">{{ $methods[$d->method] }}</span>@if ($d->recorder)<div class="sub">Recorder: {{ $d->recorder }}</div>@endif</td>
                <td>
                  <div class="gr-recs">
                    @foreach ($d->records as $r)
                      <label class="gr-rec">
                        <span class="who">{{ implode(' + ', $r['names']) }}<i>{{ implode(', ', $r['roles']) }}</i></span>
                        @if ($r['own'])
                          <span class="fixed">{{ $r['abstained'] ? 'Abstained' : $trim($r['total']) }}<span class="sub">Turned in online</span></span>
                        @else
                          <input type="number" inputmode="decimal" step="0.01" min="0" @if ($d->max) max="{{ $trim($d->max) }}" @endif
                                 data-rec="{{ $r['id'] }}" value="{{ $r['total'] !== null ? $trim($r['total']) : '' }}"
                                 placeholder="{{ $d->max ? 'of '.$trim($d->max) : 'No rubric' }}" @disabled($d->decided || ! $d->max)
                                 aria-label="Total for {{ implode(' and ', $r['names']) }}">
                          @if (count($d->cats) > 1 && (! $d->decided || $r['cats']))
                            <button class="gr-cat" type="button" data-cat="{{ $r['id'] }}" data-who="{{ implode(' + ', $r['names']) }}">By category</button>
                          @endif
                        @endif
                      </label>
                    @endforeach
                  </div>
                </td>
                <td>
                  <div class="gr-pdf">
                    @if ($d->sheet)
                      <button class="btn" type="button" data-pdf-view="{{ $d->sheet }}" data-pdf-title="{{ $d->team }} · signed grade sheets">View PDF</button>
                    @else
                      <span class="none">Not attached</span>
                    @endif
                    @unless ($d->decided)
                      <button class="btn" type="button" data-attach="{{ $d->id }}">{{ $d->sheet ? 'Replace PDF' : 'Attach signed PDF' }}</button>
                    @endunless
                  </div>
                </td>
                <td><span class="gr-st {{ $d->decided ? 'ok' : $tone }}">{{ $d->decided ? $d->outcome : $st }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      {{-- Grade summary --}}
      <div class="tscroll" data-pane="summary" hidden>
        <table data-export="Grade summary">
          <thead><tr><th>Team &amp; course</th><th>Stage</th><th>Method</th><th>Panel results</th><th class="num">Final grade</th><th>Status</th><th>Decision</th></tr></thead>
          <tbody>
            @foreach ($defenses as $d)
              <tr data-type="{{ $d->type }}" data-q="{{ strtolower($d->team.' '.$d->title) }}">
                <td><div class="b">{{ $d->team }}</div><div class="sub">{{ $d->section }}</div></td>
                <td class="nw">{{ $d->type }}{{ $round($d) }}<div class="sub">{{ $d->date->format('M j, Y') }}</div></td>
                <td class="nw">{{ $methods[$d->method] }}</td>
                <td>
                  <div class="gr-res">
                    @foreach ($d->records as $r)
                      @php $in = $r['abstained'] || ($r['total'] !== null && $r['status'] === \App\Models\PanelGrade::SUBMITTED); @endphp
                      <span>{{ implode(' + ', $r['names']) }} <b @class(['p' => ! $in])>{{ $r['abstained'] ? 'Abstained' : ($in ? $trim($r['total']) : 'Pending') }}</b></span>
                    @endforeach
                  </div>
                </td>
                <td class="num">@if ($d->average !== null)<span class="gr-avg">{{ number_format($d->average, 2) }}</span><div class="sub">Panel average</div>@else<span class="sub">Pending</span>@endif</td>
                <td>
                  @if ($d->panelSize && $d->in >= $d->panelSize)<span class="gr-st ok">Complete</span>
                  @elseif ($d->in)<span class="gr-st gold">{{ $d->in }}/{{ $d->panelSize }} in</span>
                  @else<span class="gr-st mut">Not started</span>@endif
                </td>
                <td class="nw">{{ $d->outcome ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="foot" data-pane="encode">
        <span class="grow" id="gr-note"></span>
        <button class="btn" type="button" id="gr-save">Save drafts</button>
        <button class="btn p" type="button" id="gr-review">Review &amp; submit</button>
      </div>
    @endif
  </div>
</div>

<input type="file" id="gr-file" accept="application/pdf" hidden>
<dialog class="tm-dialog gr-dlg" id="gr-dlg" aria-labelledby="gr-dlg-h"></dialog>

<script nonce="{{ Vite::cspNonce() }}">
(() => {
  const D = @json($js);
  const WIN = @json($windows->map(fn ($w) => $w->start_date && $w->end_date ? $w->start_date->format('M j').' – '.$w->end_date->format('M j, Y') : null));
  const dlg = document.getElementById('gr-dlg');
  // 'defense:record' -> that record's category scores, in the rubric's order.
  const CAT = {};
  Object.entries(D).forEach(([id, d]) => Object.entries(d.saved).forEach(([rec, v]) => { CAT[id + ':' + rec] = v; }));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const plural = (n, one, many) => n + ' ' + (n === 1 ? one : many);

  try { const t = sessionStorage.getItem('grToast'); if (t) { sessionStorage.removeItem('grToast'); window.ctToast && ctToast(t, 'success'); } } catch (e) {}
  const done = msg => { try { sessionStorage.setItem('grToast', msg); } catch (e) {} location.reload(); };

  // Encode / Summary switch in place; the choice survives the reload after a save.
  const view = v => {
    document.querySelectorAll('.gr-seg [data-view]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.view === v)));
    document.querySelectorAll('.gr-page [data-pane]').forEach(p => { p.hidden = p.dataset.pane !== v; });
    try { sessionStorage.setItem('grView', v); } catch (e) {}
  };
  document.querySelectorAll('.gr-seg [data-view]').forEach(b => b.addEventListener('click', () => view(b.dataset.view)));
  try { const v = sessionStorage.getItem('grView'); if (v) view(v); } catch (e) {}

  const stage = document.getElementById('gr-stage'), q = document.getElementById('gr-q'), win = document.getElementById('gr-win');
  const filter = () => {
    const s = stage.value, t = q.value.trim().toLowerCase();
    document.querySelectorAll('.gr-page tbody tr').forEach(tr => {
      tr.hidden = Boolean((s && tr.dataset.type !== s) || (t && !tr.dataset.q.includes(t)));
    });
    win.textContent = s && WIN[s] ? 'Window: ' + WIN[s] : '';
    note();
  };
  stage.addEventListener('change', filter);
  q.addEventListener('input', filter);

  // What each visible row would send.
  const rows = () => [...document.querySelectorAll('[data-pane="encode"] tbody tr[data-id]')].filter(tr => !tr.hidden);
  const read = tr => {
    const totals = {}, categories = {}, vals = []; let typed = 0, bad = false, live = false, changed = false;
    tr.querySelectorAll('input[data-rec]').forEach(i => {
      if (i.disabled) return;
      live = true;
      const v = i.value.trim(), wrong = v !== '' && !i.checkValidity();
      i.classList.toggle('bad', wrong);
      bad = bad || wrong;
      totals[i.dataset.rec] = v === '' ? null : Number(v);
      if (v !== '') { typed++; vals.push(v); }
      changed = changed || i.value !== i.defaultValue || Boolean(i.dataset.dirty);
      const c = CAT[tr.dataset.id + ':' + i.dataset.rec];
      if (c && c.every(x => x !== null)) categories[i.dataset.rec] = c;
    });
    const d = D[tr.dataset.id];
    return { d, totals, categories, vals, typed, bad, live, changed, full: live && typed === d.open.length };
  };
  // A submitted row counts again only once a total is changed.
  const ready = () => rows().map(read).filter(r => r.full && !r.bad && (r.changed || !r.d.submitted));

  const review = document.getElementById('gr-review');
  function note() {
    if (!review) return;
    const n = ready().length;
    review.textContent = n ? 'Review ' + plural(n, 'result', 'results') : 'Review & submit';
    review.disabled = !n;
  }
  // A total typed by hand stands alone: the category scores behind the old one go.
  document.querySelectorAll('input[data-rec]').forEach(i => i.addEventListener('input', () => {
    delete CAT[i.closest('tr').dataset.id + ':' + i.dataset.rec];
    i.dataset.dirty = 1;
    note();
  }));

  // Optional: type one record's score per category and the total adds itself up.
  document.querySelector('[data-pane="encode"]')?.addEventListener('click', e => {
    const b = e.target.closest('.gr-cat');
    if (!b) return;
    const tr = b.closest('tr'), d = D[tr.dataset.id], key = tr.dataset.id + ':' + b.dataset.cat;
    const total = tr.querySelector(`input[data-rec="${b.dataset.cat}"]`), have = CAT[key] || [], locked = total.disabled;
    dlg.innerHTML = `<div class="dh"><div><h2 id="gr-dlg-h">Scores by category</h2><div class="sub">${esc(d.team)} · ${esc(b.dataset.who)}</div></div><button class="x" type="button" data-close aria-label="Close">×</button></div>
      <div class="db"><table><thead><tr><th>Category</th><th class="num">Score</th></tr></thead><tbody>
      ${d.cats.map(([name, max], i) => `<tr><td>${esc(name)}</td><td class="num"><input type="number" inputmode="decimal" step="0.01" min="0" max="${max}" value="${have[i] ?? ''}" placeholder="of ${max}" aria-label="${esc(name)}" ${locked ? 'disabled' : ''}></td></tr>`).join('')}
      <tr><td><b>Total</b></td><td class="num"><b id="gr-sum"></b></td></tr></tbody></table></div>
      <div class="df"><button class="btn" type="button" data-close>${locked ? 'Close' : 'Cancel'}</button>${locked ? '' : '<button class="btn btn-p" type="button" id="gr-use">Use this total</button>'}</div>`;
    const ins = [...dlg.querySelectorAll('td input')], use = dlg.querySelector('#gr-use');
    const sum = () => {
      const t = Math.round(ins.reduce((a, i) => a + (Number(i.value) || 0), 0) * 100) / 100;
      dlg.querySelector('#gr-sum').textContent = t + ' / ' + total.max;
      if (use) use.disabled = !ins.every(i => i.value.trim() !== '' && i.checkValidity());
      return t;
    };
    ins.forEach(i => i.addEventListener('input', sum));
    sum();
    dlg.querySelectorAll('[data-close]').forEach(x => x.addEventListener('click', () => dlg.close()));
    use?.addEventListener('click', () => {
      CAT[key] = ins.map(i => Number(i.value));
      total.value = sum();
      total.dataset.dirty = 1;
      note();
      dlg.close();
    });
    dlg.showModal();
  });

  async function post(list, submit, btn, label) {
    const was = btn.textContent;
    btn.disabled = true; btn.textContent = label;
    try {
      for (const r of list) await mlSend(r.d.encode, 'POST', { totals: r.totals, categories: r.categories, submit });
      done(submit ? plural(list.length, 'result', 'results') + ' submitted' : 'Drafts saved');
    } catch (error) { btn.disabled = false; btn.textContent = was; dlg.close(); mlFail(error); }
  }

  document.getElementById('gr-save')?.addEventListener('click', e => {
    const list = rows().map(read).filter(r => r.typed && r.changed);
    if (list.some(r => r.bad)) { mlFail(new Error('A total is over the rubric\'s maximum or not a number.')); return; }
    if (!list.length) { mlFail(new Error('Nothing new to save.')); return; }
    post(list, false, e.currentTarget, 'Saving…');
  });

  review?.addEventListener('click', () => {
    const list = ready();
    dlg.innerHTML = `<div class="dh"><div><h2 id="gr-dlg-h">Submit ${plural(list.length, 'result', 'results')}</h2></div><button class="x" type="button" data-close aria-label="Close">×</button></div>
      <div class="db"><table><thead><tr><th>Team</th><th class="num">Totals</th></tr></thead><tbody>
      ${list.map(r => `<tr><td>${esc(r.d.team)}</td><td class="num">${r.vals.map(esc).join(' · ')}</td></tr>`).join('')}
      </tbody></table></div>
      <div class="df"><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-p" type="button" id="gr-go">Submit ${plural(list.length, 'result', 'results')}</button></div>`;
    dlg.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => dlg.close()));
    dlg.querySelector('#gr-go').addEventListener('click', e => post(list, true, e.currentTarget, 'Submitting…'));
    dlg.showModal();
  });

  // Attach / replace the signed sheets: one PDF per defense.
  const file = document.getElementById('gr-file');
  let target = null;
  document.querySelectorAll('[data-attach]').forEach(b => b.addEventListener('click', () => { target = b; file.value = ''; file.click(); }));
  file.addEventListener('change', async () => {
    if (!file.files.length || !target) return;
    const d = D[target.dataset.attach], body = new FormData(), was = target.textContent;
    body.append('sheet', file.files[0]);
    target.disabled = true; target.textContent = 'Uploading…';
    try {
      const res = await fetch(d.upload, { method: 'POST', body, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error((data.errors && Object.values(data.errors)[0][0]) || data.message || 'The PDF did not upload.');
      done('Signed sheets attached for ' + d.team);
    } catch (error) { target.disabled = false; target.textContent = was; mlFail(error); }
  });

  note();
})();
</script>
