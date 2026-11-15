{{-- Team Ratings: every rating she made, and what each student's teammates
     gave them. Students rate on their own Team page; only she sees scores.
     The two lists share the screen, each scrolling in its own box. --}}
@include('capsa.partials.team-management._milestone_kit')

@php
  $max = \App\Support\PeerRatingRubric::MAX;
  $criteria = array_keys(\App\Support\PeerRatingRubric::CRITERIA);
  $name = fn ($s) => trim($s->f_name.' '.$s->l_name);

  // What the rows and the dialogs need about each rating, worked out once.
  $rows = $ratings->map(function (array $r) use ($name) {
      $form = $r['form'];
      [$opens, $due] = $form->window();
      $sync = $form->follows_checklist && ! $form->date_overridden;
      $last = $form->items->filter(fn ($i) => $i->dueOn())->sortBy(fn ($i) => $i->dueOn())->last();
      $pending = [];
      $total = 0;
      foreach ($r['teams'] as $t) {
          foreach ($t['members'] as $s) {
              $total++;
              if (! in_array((int) $s->student_id, $r['done'], true)) {
                  $pending[] = [$name($s), $t['team']->team_name];
              }
          }
      }

      return $r + [
          'opens' => $opens, 'due' => $due, 'sync' => $sync, 'pending' => $pending, 'total' => $total,
          'when' => $sync && $last ? 'opens when '.$last->label.' is due' : 'opens on a date you set',
      ];
  });

  $msDue = fn ($m) => $m->due_on ?? $m->deliverables->pluck('due_date')->filter()->max();
  $js = [
      'today' => today()->toDateString(),
      'sections' => $sections,
      'store' => route('capsa.team_ratings.store'),
      'checklists' => $checklists->map(fn ($c) => [
          'title' => $c->title ?: $c->program,
          'ms' => $c->milestones->map(fn ($m) => [
              'id' => $m->id, 'name' => $m->name, 'due' => $msDue($m)?->toDateString(), 'used' => $used['m'][$m->id] ?? null,
              'ds' => $m->deliverables->map(fn ($d) => [
                  'id' => $d->id, 'title' => $d->title, 'due' => $d->due_date?->toDateString(), 'used' => $used['d'][$d->id] ?? null,
              ])->values(),
          ])->values(),
      ])->values(),
      'ratings' => $rows->mapWithKeys(fn ($r) => [$r['form']->id => [
          'name' => $r['form']->name, 'sections' => $r['form']->sections, 'when' => $r['when'], 'days' => $r['form']->days_to_rate,
          'sync' => $r['sync'], 'opens' => $r['opens']->toDateString(), 'due' => $r['due']->toDateString(),
          'dues' => $r['form']->items->map(fn ($i) => ['label' => $i->label, 'due' => $i->dueOn()?->toDateString()])->values(),
          'pending' => $r['pending'],
          'items' => route('capsa.team_ratings.items', $r['form']),
          'update' => route('capsa.team_ratings.update', $r['form']),
          'remind' => route('capsa.team_ratings.remind', $r['form']),
      ]]),
  ];
@endphp

<style nonce="{{ Vite::cspNonce() }}">
  /* The two cards share the screen below the tabs; each list scrolls inside. */
  .tm-hub .tr-page{display:flex;flex-direction:column;gap:14px;height:max(480px,calc(100dvh - 260px))}
  .tm-hub .tr-page td.nw{white-space:nowrap}
  .tm-hub .tr-page .card{display:flex;flex-direction:column;min-height:0}
  .tm-hub .tr-page .tr-r{flex:0 1 auto;max-height:42%}
  .tm-hub .tr-page .tr-a{flex:1 1 0}
  .tm-hub .tr-page .tscroll{flex:1;min-height:0;max-height:none}
  .tm-hub .tr-ch{display:flex;align-items:center;gap:6px 14px;flex-wrap:wrap;padding:12px 16px;border-bottom:1px solid var(--line);flex:none}
  .tm-hub .tr-ch h2{margin:0;font-size:15px;font-weight:700;color:var(--ink)}
  .tm-hub .tr-ch .t2{flex:1;min-width:240px;font-size:12.5px;color:var(--mut)}
  .tm-hub .tr-ch .r{display:flex;gap:8px;flex-wrap:wrap}
  .tm-hub .tr-ch .btn{display:inline-flex;align-items:center;gap:6px;text-decoration:none}
  /* Nothing here opens a row, so rows don't look clickable. */
  .tm-hub .tr-page tbody tr{cursor:default}
  .tm-hub .tr-page tbody tr:hover td{background:none}
  .tm-hub .tr-page td{padding:9px 14px}
  .tm-hub .tr-page .num{text-align:right;font-variant-numeric:tabular-nums}
  .tm-hub .tr-page .b{font-weight:700}
  .tm-hub .tr-page .sub{font-size:11.5px;color:var(--mut);margin-top:2px}
  .tm-hub .tr-page .muted{color:var(--mut)}
  .tm-hub .tr-page .gold{color:var(--gold-ink);font-weight:700}
  .tm-hub .tr-page .green{color:var(--ok)}
  .tm-hub .tr-page .red{color:var(--bad)}
  .tm-hub .tr-page .cov{max-width:260px;color:var(--ink2)}
  .tm-hub .tr-page .acts{text-align:right;white-space:nowrap}
  .tm-hub .tr-page .acts .btn{padding:5px 10px;font-size:12px}
  .tm-hub .tr-page .btn.dg{color:var(--bad)}
  .tm-hub .tr-page .btn.dg:hover{border-color:var(--bad);color:var(--bad)}
  .tm-hub .tr-page .btn:disabled{opacity:.5;cursor:not-allowed}
  /* All students: two header rows that stay put together. */
  .tm-hub .tr-all thead{position:sticky;top:0;z-index:2}
  .tm-hub .tr-all th{position:static}
  .tm-hub .tr-all th.rg{text-align:center;color:#e2c14d;text-transform:none;letter-spacing:0;font-size:12px}
  .tm-hub .tr-all th.soon{color:#7d8190}
  .tm-hub .tr-all .gs{border-left:2px solid var(--line)}
  .tm-hub .tr-all th:last-child,.tm-hub .tr-all td:last-child{width:auto}
  .tm-hub .tr-all td:first-child{min-width:170px}
  .tm-hub .tr-all tr.grp td{background:var(--gold-bg);font-weight:700;font-size:13px;padding:7px 14px}
  .tm-hub .tr-all tr.grp:hover td{background:var(--gold-bg)}
  .tm-hub .tr-all tr.grp .muted{font-weight:500}
  .tm-hub .tr-page .foot{flex:none}

  /* The dialogs. */
  dialog.tr-dlg{width:min(520px,calc(100% - 32px));max-width:none;max-height:calc(100dvh - 32px)}
  .tr-dlg[open]{display:flex;flex-direction:column}
  .tr-dlg .db{overflow:auto}
  dialog.tr-dlg.wide{width:min(1100px,calc(100% - 32px))}
  .tr-dlg.tr-cm{height:min(720px,calc(100dvh - 32px))}
  .tr-dlg.tr-cm .db{flex:1;min-height:0;overflow:hidden;gap:14px}
  .tr-dlg .x{margin-left:auto;border:0;background:none;font-size:22px;line-height:1;color:var(--text3);cursor:pointer;width:32px;height:32px;border-radius:8px;flex:none}
  .tr-dlg .x:hover{background:var(--bg3);color:var(--text)}
  .tr-dlg .dh{display:flex;gap:12px;align-items:flex-start;padding-bottom:12px;border-bottom:1px solid var(--border)}
  .tr-dlg table{width:100%;border-collapse:collapse;font-size:13px}
  .tr-dlg th{text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#d4d6de;padding:8px 10px;background:#18191f;position:sticky;top:0;z-index:1}
  .tr-dlg td{padding:7px 10px;border-bottom:1px solid var(--border);vertical-align:top}
  .tr-dlg .t2{color:var(--text3)}
  .tr-dlg .b{font-weight:700}
  .tr-dlg .sub{font-size:11.5px;color:var(--text3);margin-top:2px}
  .tr-dlg .req{color:#dc2626;font-weight:700;margin-left:2px}
  .tr-dlg .fld{display:flex;flex-direction:column;gap:6px;margin:0;border:0;padding:0;min-width:0}
  .tr-dlg .fld>label,.tr-dlg .fld legend{font-weight:600;font-size:13px;padding:0}
  .tr-dlg input[type=text],.tr-dlg input[type=date],.tr-dlg input[type=number]{width:100%;box-sizing:border-box;padding:8px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg2);color:var(--text);font:inherit;font-size:13px}
  .tr-dlg input:focus{outline:none;border-color:#c9a82c;box-shadow:0 0 0 3px rgba(201,168,44,.25)}
  .tr-dlg input.bad{border-color:#dc2626}
  .tr-dlg .help{font-size:12px;color:var(--text3)}
  .tr-dlg .e{font-size:12px;color:#dc2626;font-weight:600;display:none}
  .tr-dlg .bad>.e,.tr-dlg .bad .e{display:block}
  .tr-dlg .fld.bad input{border-color:#dc2626}
  .tr-dlg .chk{display:flex;gap:8px;align-items:center;padding:5px 0;cursor:pointer;font-size:13px;font-weight:400}
  .tr-dlg .chk input{width:16px;height:16px;accent-color:#c9a82c;margin:0}
  .tr-dlg .chk:has(input:disabled){color:var(--text3);cursor:not-allowed}
  .tr-dlg .warn{background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.3);color:#b91c1c;border-radius:10px;padding:10px 12px;font-size:13px}
  .tr-dlg .df .l{margin-right:auto;font-size:12.5px;color:var(--text3);align-self:center}
  .tr-dlg .df .l b{color:var(--text)}
  .tr-dlg .df .btn-r.fill{background:#dc2626;color:#fff}
  /* Five even level columns; the hub's "last cell stays on one line" rule must not reach in here. */
  .tr-dlg .rub{table-layout:fixed}
  .tr-dlg .rub th{width:auto}
  .tr-dlg .rub th:first-child{width:160px}
  .tr-dlg .rub td{font-size:12.5px;line-height:1.45;color:var(--text2);white-space:normal}
  .tr-dlg .rub td:first-child{font-weight:600;color:var(--text);width:160px}
  .tr-dlg .cm-top{display:grid;grid-template-columns:minmax(0,1fr) auto 110px;gap:20px;align-items:start;flex:none}
  .tr-dlg .inl{display:flex;gap:18px;min-height:38px;align-items:center;flex-wrap:wrap}
  .tr-dlg .cm-when{display:flex;align-items:center;gap:12px;flex-wrap:wrap;flex:none}
  .tr-dlg .cm-when .lbl{font-weight:600;font-size:13px}
  .tr-dlg .cm-when input[type=date]{width:170px}
  .tr-dlg .seg{display:inline-flex;gap:2px;padding:3px;border-radius:9px;background:var(--bg3);border:1px solid var(--border)}
  .tr-dlg .seg button{border:0;background:none;padding:6px 12px;border-radius:7px;font:inherit;font-size:12.5px;font-weight:600;color:var(--text3);cursor:pointer;white-space:nowrap}
  .tr-dlg .seg button[aria-checked=true]{background:var(--bg2);color:var(--text);box-shadow:0 1px 2px rgba(0,0,0,.08)}
  .tr-dlg .cm-cols{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:16px;flex:1;min-height:0}
  .tr-dlg .cm-pane{border:1px solid var(--border);border-radius:12px;display:flex;flex-direction:column;min-height:0;overflow:hidden;gap:0}
  .tr-dlg .cm-pane.bad{border-color:#dc2626}
  .tr-dlg .cm-pane>.e{padding:8px 14px;border-top:1px solid rgba(220,38,38,.3);background:rgba(220,38,38,.08)}
  .tr-dlg .cm-ph{padding:10px 14px;border-bottom:1px solid var(--border);font-weight:700;font-size:13px;display:flex;gap:9px;align-items:center;background:var(--bg3);flex:none}
  .tr-dlg .cm-ph .no{width:20px;height:20px;border-radius:50%;background:#18191f;color:#c9a82c;font-size:11px;display:grid;place-items:center;flex:none}
  .tr-dlg .cm-ph .t2{font-weight:500}
  .tr-dlg .cm-scroll{flex:1;min-height:0;overflow:auto;padding:4px 14px}
  .tr-dlg .cm-scroll.flush{padding:0}
  .tr-dlg .cm-cl{font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--text3);margin:10px 0 2px}
  .tr-dlg .cm-ms{padding:8px 0}
  .tr-dlg .cm-ms+.cm-ms{border-top:1px solid var(--border)}
  .tr-dlg .cm-ms .due{margin-left:auto;font-size:12px;color:var(--text2);white-space:nowrap}
  .tr-dlg .cm-dl{display:flex;flex-wrap:wrap;column-gap:20px;padding-left:24px}
  .tr-dlg .cm-dl .chk{padding:3px 0}
  .tr-dlg .cm-note{font-size:12.5px;color:var(--text2);margin:10px 0;padding:9px 11px;background:var(--bg3);border-radius:8px;line-height:1.5}
  .tr-dlg .cm-own{border-top:1px solid var(--border);padding:12px 14px;background:var(--bg3);flex:none}
  .tr-dlg .cm-own .row2{display:flex;gap:8px}
  .tr-dlg .cm-empty{padding:40px 24px;text-align:center;color:var(--text3);font-size:13px;line-height:1.55}
  .tr-dlg .cm-empty b{display:block;color:var(--text2);font-size:14px;margin-bottom:4px}
  .tr-dlg .xs{border:0;background:none;color:var(--text3);font-size:18px;cursor:pointer;width:30px;height:30px;border-radius:6px}
  .tr-dlg .xs:hover{background:var(--bg3);color:#dc2626}
  @media (max-width:900px){
    .tm-hub .tr-page{height:auto}
    .tm-hub .tr-page .card,.tm-hub .tr-page .tr-r{flex:none;max-height:none}
    .tm-hub .tr-page .tscroll{max-height:60vh;max-height:60dvh}
    .tr-dlg .cm-cols,.tr-dlg .cm-top{grid-template-columns:minmax(0,1fr)}
    .tr-dlg.tr-cm{height:auto}
    .tr-dlg.tr-cm .db{overflow:auto}
    .tr-dlg .cm-pane{min-height:260px}
  }
  /* A phone: the tables keep their headers and scroll sideways in their box, and dialogs are full sheets. */
  @media (max-width:760px){
    .tm-hub .tr-page thead{display:table-header-group}
    dialog.tr-dlg,dialog.tr-dlg.wide,dialog.tr-dlg.tr-cm{width:100%;max-width:100%;height:100dvh;max-height:100dvh;border-radius:0;margin:0}
    .tr-dlg .cm-when .seg{width:100%}
    .tr-dlg .cm-when .seg button{flex:1;white-space:normal}
  }
</style>

<div class="tr-page">
  <div class="card tr-r">
    <div class="tr-ch">
      <h2>Team ratings</h2>
      <span class="t2">Students rate each teammate once per rating, with a score for every activity it covers. Only you see the results.</span>
      <div class="r">
        <button class="btn" type="button" id="tr-rub">View rubric</button>
        <a class="btn" href="{{ route('capsa.team_ratings.export') }}">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
          Export to Excel
        </a>
        <button class="btn p" type="button" id="tr-new">+ Create a rating</button>
      </div>
    </div>
    @if ($rows->isEmpty())
      <div class="empty"><b>No team ratings yet</b>Create one and the students in its sections rate their teammates when it opens.</div>
    @else
      <div class="tscroll">
        <table>
          <thead><tr><th>Rating</th><th>Covers</th><th>Opens</th><th>Due</th><th class="num">Submitted</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
          <tbody>
            @foreach ($rows as $r)
              @php $f = $r['form']; $p = count($r['pending']); @endphp
              <tr>
                <td><div class="b">{{ $f->name }}</div><div class="sub">{{ implode(', ', $f->sections) }} · {{ $r['when'] }} · {{ $f->days_to_rate }} days to rate</div></td>
                <td class="cov">{{ $f->items->pluck('label')->implode(', ') }}</td>
                <td class="nw">{{ $r['opens']->format('M j') }}</td>
                <td class="nw">{{ $r['due']->format('M j') }}</td>
                <td @class(['num', 'gold' => $r['status'] === 'open' && $p])>
                  @if ($r['status'] === 'scheduled')<span class="muted">—</span>@else{{ $r['total'] - $p }} of {{ $r['total'] }}@endif
                </td>
                <td @class(['b', 'green' => $r['status'] === 'open', 'muted' => $r['status'] !== 'open'])>{{ ['open' => 'Open', 'scheduled' => 'Scheduled', 'closed' => 'Closed'][$r['status']] }}</td>
                <td class="acts">
                  @if ($r['status'] === 'open')
                    <button class="btn" type="button" data-due="{{ $f->id }}">Change due date</button>
                    <button class="btn" type="button" data-remind="{{ $f->id }}" @disabled(! $p)>Remind {{ $p }}</button>
                    <button class="btn dg" type="button" data-shut="{{ $f->id }}">Close</button>
                  @elseif ($r['status'] === 'scheduled')
                    <button class="btn" type="button" data-add="{{ $f->id }}">+ Add activity</button>
                    <button class="btn" type="button" data-now="{{ $f->id }}">Open now</button>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="card tr-a">
    <div class="tr-ch">
      <h2>All students</h2>
      <span class="t2">Each score is the average of what teammates gave them, out of {{ $max }}. Point at a score to see its 7 criteria.</span>
    </div>
    @if ($rows->isEmpty() || $teams->isEmpty())
      <div class="empty">Scores show here once a rating opens and students submit.</div>
    @else
      @php $cols = 1 + $rows->sum(fn ($r) => $r['form']->items->count() + 1); @endphp
      <div class="tscroll tr-all">
        <table>
          <thead>
            <tr><th></th>@foreach ($rows as $r)<th class="rg gs" colspan="{{ $r['form']->items->count() + 1 }}">{{ $r['form']->name }}</th>@endforeach</tr>
            <tr>
              <th>Student</th>
              @foreach ($rows as $r)
                @foreach ($r['form']->items as $item)
                  <th @class(['num', 'gs' => $loop->first, 'soon' => $r['status'] === 'scheduled']) @if ($r['status'] === 'scheduled') title="Opens {{ $r['opens']->format('M j') }}" @endif>{{ $item->label }}</th>
                @endforeach
                <th class="num">Rating %</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @foreach ($teams as $teamId => $t)
              <tr class="grp"><td colspan="{{ $cols }}">{{ $t['team']->team_name }} <span class="muted">· {{ $t['section'] }}</span></td></tr>
              @foreach ($t['members'] as $s)
                @php
                  $sid = (int) $s->student_id;
                  $missing = $rows->contains(fn ($r) => $r['status'] === 'open' && isset($r['teams'][$teamId]) && ! in_array($sid, $r['done'], true));
                @endphp
                <tr>
                  <td><div class="b">{{ $name($s) }}</div>@if ($missing)<div class="sub"><span class="gold">hasn’t submitted yet</span></div>@endif</td>
                  @foreach ($rows as $r)
                    @php
                      $in = isset($r['teams'][$teamId]) && $r['status'] !== 'scheduled';
                      $mine = $in ? ($r['results'][$sid] ?? []) : [];
                      $pct = \App\Models\TeamRatingForm::percentFor($mine);
                    @endphp
                    @foreach ($r['form']->items as $item)
                      @php $c = $mine[$item->id] ?? null; @endphp
                      @if ($c)
                        <td @class(['num', 'gs' => $loop->first, 'red b' => $c['avg'] < $max / 2])
                            title="{{ $item->label }} · rated by {{ $c['n'] }}{{ collect($criteria)->map(fn ($n, $i) => "\n".$n.': '.number_format($c['criteria'][$i], 1))->implode('') }}">{{ number_format($c['avg'], 1) }}</td>
                      @else
                        <td @class(['num muted', 'gs' => $loop->first])>—</td>
                      @endif
                    @endforeach
                    <td @class(['num b', 'red' => $pct !== null && $pct < 50])>{{ $pct === null ? '—' : round($pct).'%' }}</td>
                  @endforeach
                </tr>
              @endforeach
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
    <div class="foot">Rating % averages the activities in that rating. Red means under 50%. Grey ratings haven’t opened yet. Nobody rates themselves, and anyone who hasn’t submitted is left out of the averages.</div>
  </div>
</div>

<dialog class="tm-dialog tr-dlg" id="tr-dlg" aria-labelledby="tr-dlg-h"></dialog>

<script nonce="{{ Vite::cspNonce() }}">
(() => {
  const TR = @json($js);
  const RUBRIC = @json(\App\Support\PeerRatingRubric::CRITERIA);
  const LEVELS = @json(\App\Support\PeerRatingRubric::LEVELS);
  const dlg = document.getElementById('tr-dlg');
  const $ = s => dlg.querySelector(s);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fmt = d => new Date(d + 'T12:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const addDays = (d, n) => { const x = new Date(d + 'T12:00'); x.setDate(x.getDate() + n); return x.toISOString().slice(0, 10); };
  const plural = (n, one, many) => n + ' ' + (n === 1 ? one : many);
  const head = (title, sub) => `<div class="dh"><div><h2 id="tr-dlg-h">${title}</h2>${sub ? `<p>${sub}</p>` : ''}</div><button class="x" type="button" data-close aria-label="Close">×</button></div>`;

  // A toast survives the reload that shows the change.
  try { const t = sessionStorage.getItem('trToast'); if (t) { sessionStorage.removeItem('trToast'); window.ctToast && ctToast(t, 'success'); } } catch (e) {}
  const done = msg => { try { sessionStorage.setItem('trToast', msg); } catch (e) {} location.reload(); };

  function open(html, cls) {
    dlg.className = 'tm-dialog tr-dlg ' + (cls || '');
    dlg.innerHTML = html;
    dlg.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => dlg.close()));
    dlg.showModal();
  }
  // One save at a time: the button says what it's doing until it lands.
  async function send(btn, label, url, method, body, ok) {
    const was = btn.textContent;
    btn.disabled = true; btn.textContent = label;
    try { done(ok(await mlSend(url, method, body))); }
    catch (error) { btn.disabled = false; btn.textContent = was; dlg.close(); mlFail(error); }
  }

  function rubric() {
    open(head("Member's Participation Rubric", 'Each criterion gets 10, 7, 5, 3 or 1 point, for every activity the rating covers.') +
      `<div class="db"><table class="rub"><thead><tr><th>Criteria</th>${Object.entries(LEVELS).sort((a, b) => b[0] - a[0]).map(([v, w]) => `<th>${esc(w)} (${v})</th>`).join('')}</tr></thead><tbody>
      ${Object.entries(RUBRIC).map(([c, ds]) => `<tr><td>${esc(c)}</td>${ds.map(d => `<td>${esc(d)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>
      <div class="df"><button class="btn btn-p" type="button" data-close>Close</button></div>`, 'wide');
  }

  function due(r) {
    open(head('Change the due date', `${esc(r.name)}. Students who haven't submitted get the new date in a notification.`) +
      `<div class="db"><div class="fld" id="f-due"><label for="nd">New due date</label><input type="date" id="nd" value="${r.due}" min="${TR.today}">
       ${r.sync ? '<div class="help">This rating follows the checklist date. Once you change it, it keeps your date instead.</div>' : ''}<div class="e">Pick a date from today on.</div></div></div>
      <div class="df"><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-p" type="button" id="ok">Save date</button></div>`);
    $('#ok').onclick = () => {
      const v = $('#nd').value;
      if (!v || v < TR.today) { $('#f-due').classList.add('bad'); $('#nd').focus(); return; }
      send($('#ok'), 'Saving…', r.update, 'PATCH', { action: 'due', due_on: v }, () => `Due date moved to ${fmt(v)}`);
    };
  }

  function remind(r) {
    const n = r.pending.length;
    open(head(`Remind ${plural(n, 'student', 'students')}?`, `They get a notification: "${esc(r.name)}" closes on ${fmt(r.due)}.`) +
      `<div class="db"><table><thead><tr><th>Student</th><th>Team</th></tr></thead><tbody>${r.pending.map(([s, t]) => `<tr><td>${esc(s)}</td><td class="t2">${esc(t)}</td></tr>`).join('')}</tbody></table>
       <p class="t2" style="margin:0">They also get an automatic reminder the day before it is due.</p></div>
      <div class="df"><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-p" type="button" id="ok">Send reminder</button></div>`);
    $('#ok').onclick = () => send($('#ok'), 'Sending…', r.remind, 'POST', {}, d => `Reminder sent to ${plural(d.reminded, 'student', 'students')}`);
  }

  function shut(r) {
    const n = r.pending.length;
    open(head(`Close ${esc(r.name)} now?`, "Students can't rate after this.") +
      `<div class="db">${n ? `<div class="warn">${plural(n, "student hasn't", "students haven't")} submitted. Their teammates' averages will leave them out.</div>` : '<p class="t2" style="margin:0">Everyone has submitted.</p>'}</div>
      <div class="df"><button class="btn" type="button" data-close>Keep it open</button><button class="btn btn-r fill" type="button" id="ok">Close rating</button></div>`);
    $('#ok').onclick = () => send($('#ok'), 'Closing…', r.update, 'PATCH', { action: 'close' }, () => `${r.name} closed`);
  }

  function now(r) {
    const until = addDays(TR.today, r.days);
    open(head(`Open ${esc(r.name)} now?`, `It was set to open ${fmt(r.opens)}. Students in ${esc(r.sections.join(', '))} can rate from today until ${fmt(until)}.`) +
      `<div class="db"><p class="t2" style="margin:0">They get a notification right away.</p></div>
      <div class="df"><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-p" type="button" id="ok">Open now</button></div>`);
    $('#ok').onclick = () => send($('#ok'), 'Opening…', r.update, 'PATCH', { action: 'open_now' }, () => `${r.name} is open · due ${fmt(until)}`);
  }

  // Create a rating, or (with base) add activities to one that hasn't opened.
  function create(base) {
    const M = { m: new Set(), d: new Set(), own: [], sync: base ? base.sync : true, date: '' };
    const ms = TR.checklists.flatMap(c => c.ms);
    const days = () => base ? base.days : Math.min(30, Math.max(1, +$('#days').value || 7));
    const items = () => [
      ...ms.flatMap(m => [
        ...(M.m.has(m.id) ? [{ k: 'm' + m.id, label: m.name, from: 'Checklist · whole milestone', due: m.due }] : []),
        ...m.ds.filter(d => M.d.has(d.id)).map(d => ({ k: 'd' + d.id, label: d.title, from: 'Checklist · ' + m.name, due: d.due })),
      ]),
      ...M.own.map((l, i) => ({ k: 'o' + i, label: l, from: 'Your own activity', due: null })),
    ];
    // The one window for the whole rating: [opens, due, the activity it hangs on], or null while it can't be worked out.
    const win = () => {
      const all = [...(base ? base.dues : []), ...items()], n = days();
      if (base && !base.sync) return [base.opens, base.due];
      if (M.sync) {
        const d = all.map(x => x.due).filter(Boolean).sort().pop();
        if (!d) return null;
        const o = d < TR.today ? TR.today : d;
        return [o, addDays(o, n), all.find(x => x.due === d).label];
      }
      return M.date ? [M.date, addDays(M.date, n)] : null;
    };

    open(head(base ? `Add activities to ${esc(base.name)}` : 'Create a team rating',
      base ? `${esc(base.sections.join(', '))} · ${esc(base.when)} · ${base.days} days to rate`
           : 'Pick everything this rating covers. Students rate each teammate once, with a score for every activity in it. <span class="req">*</span> Required.') +
      `<div class="db">
       ${base ? '' : `<div class="cm-top">
         <div class="fld" id="f-nm"><label for="nm">Name of the rating<span class="req" aria-hidden="true">*</span></label><input type="text" id="nm" maxlength="120" aria-required="true" placeholder="e.g. Midterm Team Participation Rating" autocomplete="off"><div class="e">Give this rating a name.</div></div>
         <fieldset class="fld" id="f-sec"><legend>Sections<span class="req" aria-hidden="true">*</span></legend><div class="inl">${TR.sections.map(s => `<label class="chk"><input type="checkbox" value="${esc(s)}" checked> ${esc(s)}</label>`).join('')}</div><div class="e">Pick at least one.</div></fieldset>
         <div class="fld" id="f-days"><label for="days">Days to rate<span class="req" aria-hidden="true">*</span></label><input type="number" id="days" aria-required="true" min="1" max="30" value="7"><div class="e">From 1 to 30.</div></div>
       </div>
       <div class="cm-when"><span class="lbl" id="when-l">It opens</span><div class="seg" role="radiogroup" aria-labelledby="when-l"><button type="button" role="radio" data-mode="sync">When the last milestone is due</button><button type="button" role="radio" data-mode="own">On a date I pick</button></div><input type="date" id="odate" min="${TR.today}" aria-label="Opening date"><span class="help" id="when-help"></span></div>`}
       <div class="cm-cols">
        <section class="cm-pane"><div class="cm-ph"><span class="no">1</span>Pick what it covers</div>
         <div class="cm-scroll" id="tree"></div>
         <div class="fld cm-own" id="f-own"><label for="own-i">Or add your own activity</label><div class="row2"><input type="text" id="own-i" maxlength="120" placeholder="e.g. Instruments" autocomplete="off"><button class="btn" id="own-add" type="button">Add</button></div><div class="e">Type a name first.</div></div></section>
        <section class="cm-pane" id="f-list"><div class="cm-ph"><span class="no">2</span>${base ? 'Adding' : 'Students score each teammate on'}<span class="req" aria-hidden="true">*</span><span class="t2" id="cnt"></span></div>
         <div class="cm-scroll flush" id="sumt"></div><div class="e">Pick at least one activity on the left.</div></section>
       </div></div>
      <div class="df"><span class="l" id="cm-sum"></span><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-p" type="button" id="ok">${base ? 'Add activities' : 'Create rating'}</button></div>`, 'wide tr-cm');

    const drawMode = () => {
      if (base) return;
      const w = win(), n = days();
      dlg.querySelectorAll('.cm-when [data-mode]').forEach(b => b.setAttribute('aria-checked', String((b.dataset.mode === 'sync') === M.sync)));
      $('#odate').hidden = M.sync;
      $('#when-help').innerHTML = M.sync
        ? (w ? `Opens <b>${fmt(w[0])}</b>, when ${esc(w[2])} is due, and closes <b>${fmt(w[1])}</b>. If that checklist date moves, the rating moves too.` : 'Pick at least one checklist milestone that has a due date.')
        : (w ? `Opens <b>${fmt(w[0])}</b> and closes <b>${fmt(w[1])}</b>, ${n} days later.` : 'Pick the day students can start rating.');
    };
    const drawTree = () => {
      const used = {}, many = TR.checklists.length > 1;
      let h = '';
      TR.checklists.forEach(c => {
        let part = '';
        c.ms.forEach(m => {
          if (m.used) { (used[m.used] = used[m.used] || []).push(m.name); return; }
          const some = m.ds.some(d => d.used), on = M.m.has(m.id);
          part += `<div class="cm-ms"><label class="chk"><input type="checkbox" data-m="${m.id}" ${on ? 'checked' : ''} ${some ? 'disabled' : ''}> <b>${esc(m.name)}</b><span class="due">${m.due ? 'Due ' + fmt(m.due) : 'No due date'}</span></label>
            ${m.ds.length ? `<div class="cm-dl">${m.ds.map(d => `<label class="chk"><input type="checkbox" data-d="${d.id}" ${on || M.d.has(d.id) ? 'checked' : ''} ${on || d.used ? 'disabled' : ''}> ${esc(d.title)}${d.used ? ` <span class="t2">· in ${esc(d.used)}</span>` : ''}</label>`).join('')}</div>` : ''}</div>`;
        });
        if (part) h += (many ? `<p class="cm-cl">${esc(c.title)}</p>` : '') + part;
      });
      h += Object.entries(used).map(([r, names]) => `<p class="cm-note"><b>${esc(names.join(', '))}</b> ${names.length > 1 ? 'are' : 'is'} already in ${esc(r)}.</p>`).join('');
      $('#tree').innerHTML = h || '<div class="cm-empty">Everything on your checklist is already in a rating.</div>';
      dlg.querySelectorAll('#tree input').forEach(i => i.onchange = () => {
        if (i.dataset.m) {
          const m = ms.find(x => x.id === +i.dataset.m);
          if (i.checked) { M.m.add(m.id); m.ds.forEach(d => M.d.delete(d.id)); } else M.m.delete(m.id);
        } else i.checked ? M.d.add(+i.dataset.d) : M.d.delete(+i.dataset.d);
        const sel = i.dataset.m ? `[data-m="${i.dataset.m}"]` : `[data-d="${i.dataset.d}"]`;
        drawAll();
        const again = $('#tree ' + sel); again && again.focus();
      });
    };
    const drawSum = () => {
      const its = items(), n = its.length, w = win();
      $('#cnt').textContent = n ? ` · ${n}` : '';
      $('#sumt').innerHTML = n
        ? `<table><thead><tr><th>Activity</th><th>Checklist due</th><th><span class="sr-only">Remove</span></th></tr></thead><tbody>${its.map(it => `<tr><td><div class="b">${esc(it.label)}</div><div class="sub">${esc(it.from)}</div></td>
            <td>${it.due ? fmt(it.due) : '<span class="t2">—</span>'}</td>
            <td style="text-align:right"><button class="xs" type="button" data-rm="${it.k}" aria-label="Remove ${esc(it.label)}">×</button></td></tr>`).join('')}</tbody></table>`
        : '<div class="cm-empty"><b>Nothing picked yet</b>Tick milestones, or only some of their parts, or add your own activity. They all go into this one rating.</div>';
      $('#cm-sum').innerHTML = n ? `<b>${plural(n, 'activity', 'activities')} in one rating</b>${w ? ` · opens ${fmt(w[0])}, due ${fmt(w[1])}` : ''}` : 'Students are notified when it opens and reminded the day before it is due.';
      dlg.querySelectorAll('[data-rm]').forEach(b => b.onclick = () => {
        const k = b.dataset.rm, id = +k.slice(1);
        if (k[0] === 'o') M.own.splice(id, 1); else (k[0] === 'm' ? M.m : M.d).delete(id);
        drawAll();
      });
      if (n) $('#f-list').classList.remove('bad');
    };
    const drawAll = () => { drawMode(); drawTree(); drawSum(); };
    const addOwn = () => {
      const v = $('#own-i').value.trim();
      $('#f-own').classList.toggle('bad', !v);
      if (!v) return;
      if (!M.own.includes(v)) M.own.push(v);
      $('#own-i').value = '';
      drawSum(); drawMode();
    };
    $('#own-add').onclick = addOwn;
    $('#own-i').onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); addOwn(); } };
    if (!base) {
      dlg.querySelectorAll('.cm-when [data-mode]').forEach(b => b.onclick = () => { M.sync = b.dataset.mode === 'sync'; drawMode(); drawSum(); if (!M.sync) $('#odate').focus(); });
      $('#odate').onchange = () => { M.date = $('#odate').value; $('#odate').classList.remove('bad'); drawMode(); drawSum(); };
      $('#days').oninput = () => { drawMode(); drawSum(); };
    }
    drawAll();

    $('#ok').onclick = () => {
      const its = items(), w = win();
      let bad = false, name, secs, dv;
      if (!base) {
        name = $('#nm').value.trim();
        secs = [...dlg.querySelectorAll('#f-sec input:checked')].map(i => i.value);
        dv = +$('#days').value;
        $('#f-nm').classList.toggle('bad', !name);
        $('#f-sec').classList.toggle('bad', !secs.length);
        $('#f-days').classList.toggle('bad', !(dv >= 1 && dv <= 30));
        bad = !name || !secs.length || !(dv >= 1 && dv <= 30);
      }
      $('#f-list').classList.toggle('bad', !its.length);
      if (!its.length) bad = true;
      if (its.length && !w) {
        bad = true;
        if (!M.sync) { $('#odate').classList.add('bad'); $('#when-help').textContent = 'Pick the day students can start rating.'; }
        else $('#when-help').textContent = 'Pick a checklist milestone with a due date, or open it on a date you pick.';
      }
      if (bad) { const f = dlg.querySelector('.bad input, input.bad'); f && f.focus(); return; }
      const picked = { milestones: [...M.m], deliverables: [...M.d], own: M.own };
      const what = plural(its.length, 'activity', 'activities');
      if (base) send($('#ok'), 'Adding…', base.items, 'POST', picked, () => `${base.name}: ${what} added`);
      else send($('#ok'), 'Creating…', TR.store, 'POST', { name, sections: secs, follows_checklist: M.sync, opens_on: M.sync ? null : M.date, days_to_rate: dv, ...picked },
        () => `${name}: one rating covering ${what}`);
    };
  }

  const R = id => TR.ratings[id];
  document.getElementById('tr-rub').onclick = rubric;
  document.getElementById('tr-new').onclick = () => create();
  document.querySelectorAll('[data-add]').forEach(b => b.onclick = () => create(R(b.dataset.add)));
  document.querySelectorAll('[data-due]').forEach(b => b.onclick = () => due(R(b.dataset.due)));
  document.querySelectorAll('[data-remind]').forEach(b => b.onclick = () => remind(R(b.dataset.remind)));
  document.querySelectorAll('[data-shut]').forEach(b => b.onclick = () => shut(R(b.dataset.shut)));
  document.querySelectorAll('[data-now]').forEach(b => b.onclick = () => now(R(b.dataset.now)));
})();
</script>
