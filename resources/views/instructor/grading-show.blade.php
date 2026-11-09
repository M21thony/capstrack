@php
    $categories = $rubric->criteria->groupBy(fn ($c) => $c->category ?? 'General');
    $existingScores = $grade?->scores ?? [];
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');

    // The rubric as the sheet draws it: sections of criteria, each criterion
    // with its own levels. Built here so the page script only lays it out.
    $sections = $categories->map(fn ($criteria, $name) => [
        'name' => $name,
        'max' => (float) ($criteria->first()->category_max_points ?? $criteria->sum('max_points')),
        'items' => $criteria->map(fn ($c) => [
            'id' => (string) $c->criterion_id,
            'n' => $c->criterion_name,
            'd' => $c->description,
            'levels' => collect($c->decodedScoreLevels())->map(fn ($l) => [
                's' => (float) ($l['score'] ?? 0),
                'label' => $l['label'] ?? '',
                'desc' => $l['desc'] ?? '',
            ])->values(),
        ])->values(),
    ])->values();

    $savedScores = collect($existingScores)->mapWithKeys(fn ($v, $k) => [(string) $k => (float) $v]);

    $isAbstained = (bool) $grade?->isAbstained();
    $savedAt = $grade?->updated_at?->format('g:i A');
    $saveText = match (true) {
        $isAbstained => 'Abstained',
        $grade?->status === 'submitted' => 'Submitted'.($savedAt ? ' '.$savedAt : ''),
        $grade !== null => 'Saved'.($savedAt ? ' '.$savedAt : ''),
        default => 'Not saved yet',
    };
    $outcomeTone = \App\Support\DefenseOutcome::tone($defense->outcome);
    // Grading for the panel: this grade counts for the panelists joined to it too.
    $sharedCount = count($gradingFor) + 1;
    $isGrader = count($gradingFor) > 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>Grading {{ $defense->team?->team_name }} — CapsTRACK</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/expert.css') }}" />
@include('partials.skeleton-head', ['skReveal' => 'flex'])
<style nonce="{{ Vite::cspNonce() }}">
  /* The grading sheet fills the screen and never scrolls: a section that is
     too long for the window is split into pages that fit it. */
  .main{min-width:0}
  #real-content{flex:1;min-height:0;flex-direction:column;overflow:hidden}
  .gs{flex:1;min-height:0;display:flex;flex-direction:column;background:var(--bg);font-variant-numeric:tabular-nums;color:var(--text)}
  .gs :focus-visible{outline:2px solid var(--accent3);outline-offset:2px}
  .gs button{font-family:inherit}
  .gs kbd{font:inherit;font-size:10.5px;padding:0 5px;border:1px solid var(--border2);border-bottom-width:2px;border-radius:4px;background:var(--bg3);color:var(--text2)}

  /* ── Header ── */
  .gs-top{flex:0 0 auto;display:flex;align-items:center;gap:16px;padding:10px 24px;background:var(--bg2);border-bottom:1px solid var(--border)}
  .gs-iconbtn{flex:0 0 40px;width:40px;height:40px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);display:flex;align-items:center;justify-content:center;color:var(--text2);text-decoration:none;cursor:pointer}
  .gs-iconbtn:hover{border-color:var(--border2);color:var(--text)}
  .gs-iconbtn svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .gs-burger{display:none}
  .gs-who{flex:1;min-width:0}
  .gs-who h1{margin:0;font-size:17px;line-height:1.3;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .gs-who h1 span{font-weight:500;color:var(--text2)}
  .gs-meta{display:flex;align-items:center;gap:8px;margin-top:3px;font-size:12.5px;color:var(--text3);white-space:nowrap;overflow:hidden}
  .gs-chip{font-weight:700;padding:1px 8px;border-radius:999px;background:var(--bg4);color:var(--text2)}
  .gs-chip.gold{background:#faf3d9;color:#6b5409}
  .gs-progress{flex:0 0 210px;display:flex;flex-direction:column;gap:5px}
  .gs-progress-top{display:flex;align-items:baseline;justify-content:space-between;gap:8px}
  .gs-progress-top b{font-size:22px;line-height:1}
  .gs-progress-top .of{font-size:13px;color:var(--text3)}
  .gs-progress-top .cnt{font-size:12px;font-weight:600;color:var(--text2);white-space:nowrap}
  .gs-segs{display:flex;gap:3px}
  .gs-segs span{flex:1;height:6px;border-radius:2px;background:var(--border)}
  .gs-segs span.on{background:var(--accent)}
  .gs-divider{width:1px;height:36px;background:var(--border)}
  .gs-saved{display:inline-flex;align-items:center;gap:7px;font-size:12.5px;color:var(--text2);white-space:nowrap}
  .gs-saved i{width:8px;height:8px;border-radius:50%;background:var(--green)}
  .gs-saved.dirty i{background:var(--amber)}

  /* ── Buttons ── */
  .gs-btn{font:inherit;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:7px;padding:0 14px;min-height:40px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);color:var(--text2);cursor:pointer;white-space:nowrap;text-decoration:none}
  .gs-btn:hover:not(:disabled){border-color:var(--border2);color:var(--text)}
  .gs-btn:disabled{color:#a2a6b2;cursor:default}
  .gs-btn[hidden]{display:none}
  .gs-btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .gs-btn-dark{background:#18191f;border-color:#18191f;color:#fff;font-weight:700}
  .gs-btn-dark:hover:not(:disabled){color:#fff;border-color:#18191f;filter:brightness(1.15)}
  .gs-btn-dark:disabled{background:var(--border2);border-color:var(--border2);color:var(--text2);cursor:not-allowed}
  .gs-btn-outline{border-color:#18191f;color:#18191f}
  .gs-btn-dashed{border-style:dashed;border-color:var(--border2);color:var(--text3)}
  .gs-btn-dashed:hover:not(:disabled){color:var(--red);border-color:var(--red)}
  .gs-btn-sm{min-height:36px;padding:0 12px;font-size:12.5px}
  .gs-btn-danger{background:var(--red);border-color:var(--red);color:#fff}
  .gs-btn-danger:hover:not(:disabled){color:#fff;filter:brightness(.95)}

  /* ── Alerts ── */
  .gs-alerts{flex:0 0 auto;display:flex;flex-direction:column;gap:8px;padding:10px 24px 0}
  .gs-alerts:empty{display:none}
  .gs-alert{margin:0;display:flex;align-items:center;gap:12px;padding:8px 14px;border-radius:10px;font-size:13px}
  .gs-alert[hidden]{display:none}
  .gs-alert span{flex:1}
  .gs-alert.err{border:1px solid var(--red);background:var(--red-bg);color:var(--red)}
  .gs-alert.ok{border:1px solid #86efac;background:rgba(22,163,74,.08);color:#14532d}
  .gs-alert.warn{border:1px solid #fed7aa;background:#fff7ed;color:#7c2d12}
  .gs-alert.muted{border:1px solid var(--border2);background:var(--bg3);color:var(--text2)}
  .gs-alert.muted .why{display:block;margin-top:2px;font-style:italic;color:var(--text3)}
  .gs-alert button.link{font:inherit;font-size:13px;font-weight:700;color:inherit;background:none;border:none;text-decoration:underline;cursor:pointer;min-height:32px;padding:0}

  /* ── Section steps ── */
  .gs-steps{flex:0 0 auto;display:flex;gap:8px;padding:10px 24px 0}
  .gs-step{font:inherit;flex:1 1 0;min-width:0;display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:10px;cursor:pointer;text-align:left;color:var(--text);min-height:48px;background:var(--bg2);border:1px solid var(--border)}
  .gs-step.on{border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,168,44,.14)}
  .gs-step .dot{width:24px;height:24px;border-radius:50%;flex:0 0 24px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;background:var(--bg4);color:var(--text2)}
  .gs-step.on .dot{background:#18191f;color:#fff}
  .gs-step.done .dot{background:var(--green);color:#fff}
  .gs-step .dot svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
  .gs-step .txt{display:flex;flex-direction:column;gap:4px;min-width:0;flex:1}
  .gs-step .nm{font-size:13px;font-weight:700;line-height:1.25}
  .gs-step .st{font-size:12px;color:var(--text3)}
  .gs-step .bar{height:4px;border-radius:2px;background:var(--bg4);overflow:hidden;display:block}
  .gs-step .bar i{display:block;height:100%;border-radius:2px;background:var(--accent)}
  .gs-step.done .bar i{background:var(--green)}
  .gs-step.review{flex:0 0 210px;border-style:dashed;border-color:var(--border2);background:transparent;color:var(--text2)}
  .gs-step.review.on{border-style:solid;border-color:var(--accent);background:var(--bg2)}
  .gs-step.review .dot{background:none;border:1.5px solid var(--border2);color:var(--text2)}
  .gs-step.review.on .dot{background:#18191f;border-color:#18191f;color:#fff}
  .gs-step.review .dot svg{width:12px;height:12px;stroke-width:2.2}
  .gs-step.review .nm{color:var(--text)}

  /* ── Body ── */
  .gs-body{flex:1;min-height:0;display:flex;gap:16px;padding:10px 24px 12px}
  .gs-body[hidden]{display:none}
  .gs-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px}
  .gs-main{flex:1;min-width:0;min-height:0;display:flex;flex-direction:column;overflow:hidden;align-self:flex-start;max-height:100%}
  .gs-main-head{flex:0 0 auto;display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--border)}
  .gs-main-head h2{margin:0;font-size:16px;font-weight:700}
  .gs-main-head .meta{font-size:12.5px;color:var(--text3)}
  .gs-main-head .grow{flex:1}
  .gs-rows{flex:1 1 auto;min-height:0;overflow:hidden}
  .gs-table{width:100%;border-collapse:collapse;table-layout:fixed}
  .gs-table thead tr{background:var(--bg3);height:34px}
  .gs-table th[scope=col]{text-align:left;padding:0 12px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--text3);border-bottom:1px solid var(--border)}
  .gs-table th[scope=col] + th[scope=col]{border-left:1px solid var(--border)}
  .gs-table th.crit-col{width:19%;padding-left:14px}
  .gs-table th.score-col{width:72px;text-align:right;padding-right:14px}
  .gs-table th[scope=col] kbd{margin-right:7px;background:var(--bg2);letter-spacing:0}
  .gs-table tbody tr{border-bottom:1px solid var(--border);background:var(--bg2)}
  .gs-table tbody tr.cur{background:#fffdf6}
  .gs-table tbody th{text-align:left;vertical-align:top;padding:12px 14px;font-weight:400}
  .gs-table tbody tr.cur th{box-shadow:inset 4px 0 0 var(--accent)}
  .gs-crit{font:inherit;text-align:left;background:none;border:none;padding:0;cursor:pointer;color:inherit;display:block;width:100%}
  .gs-crit b{display:block;font-size:13.5px;line-height:1.3}
  .gs-crit b .no{font-weight:600;color:var(--text3)}
  .gs-crit:hover b .nm{text-decoration:underline}
  .gs-crit span.d{display:block;font-size:12px;line-height:1.4;color:var(--text3);margin-top:3px}
  .gs-table td{padding:0;border-left:1px solid var(--border);vertical-align:top;height:1px}
  .gs-cell{font:inherit;text-align:left;width:100%;height:100%;display:flex;flex-direction:column;gap:4px;padding:8px 10px;border:none;cursor:pointer;color:inherit;background:transparent;box-sizing:border-box}
  .gs-cell:hover{background:#fcf8ea}
  .gs-cell .top{display:flex;align-items:center;justify-content:space-between;gap:8px}
  .gs-cell .pts{font-size:12px;font-weight:700;padding:1px 7px;border-radius:6px;background:var(--bg3);color:#6b5409;box-shadow:inset 0 0 0 1px var(--border)}
  .gs-cell .lb{font-size:11px;font-weight:600;color:var(--text3)}
  .gs-cell .ds{font-size:12px;line-height:1.4;color:var(--text2)}
  .gs-cell .tick{display:none;width:18px;height:18px;border-radius:50%;background:var(--accent3);color:#fff;align-items:center;justify-content:center}
  .gs-cell .tick svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:3.2;stroke-linecap:round;stroke-linejoin:round}
  .gs-table tr.scored .gs-cell .ds{color:#8a8f9c}
  .gs-cell[aria-pressed="true"]{background:#faf3d9;box-shadow:inset 0 0 0 2px var(--accent)}
  .gs-cell[aria-pressed="true"] .pts{background:#18191f;color:#fff;box-shadow:none}
  .gs-cell[aria-pressed="true"] .ds{color:var(--text2) !important}
  .gs-cell[aria-pressed="true"] .tick{display:flex}
  .gs-score{padding:12px 14px !important;text-align:right}
  .gs-score b{display:block;font-size:16px}
  .gs-score small{display:block;font-size:11.5px;color:var(--text3);margin-top:2px}
  .gs-score .empty{display:inline-block;width:38px;height:24px;border:1.5px dashed var(--border2);border-radius:6px}
  .gs-main-foot{flex:0 0 auto;display:flex;align-items:center;gap:10px;padding:8px 16px;background:var(--bg3);border-top:1px solid var(--border)}
  .gs-main-foot .sum{flex:1;font-size:13px;color:var(--text2)}
  .gs-main-foot .sum b{color:var(--text)}
  .gs-main-foot .gs-btn{min-height:38px}

  /* ── Side column ── */
  .gs-aside{flex:0 0 272px;min-height:0;display:flex;flex-direction:column;gap:12px;overflow-y:auto}
  .gs-aside .gs-card{padding:12px 16px}
  .gs-aside h3{margin:0 0 10px;font-size:13.5px;font-weight:700}
  .gs-aside h3 span{font-weight:600;color:var(--text3)}
  .gs-sum-rows{display:flex;flex-direction:column;gap:9px}
  .gs-sum-row .line{display:flex;justify-content:space-between;gap:10px;font-size:12.5px}
  .gs-sum-row .line span{color:var(--text2);min-width:0}
  .gs-sum-row .line b{white-space:nowrap}
  .gs-sum-row .bar{margin-top:4px;height:5px;border-radius:3px;background:var(--bg4);overflow:hidden;display:block}
  .gs-sum-row .bar i{display:block;height:100%;border-radius:3px;background:var(--accent)}
  .gs-sum-row.done .bar i{background:var(--green)}
  .gs-sum-total{display:flex;justify-content:space-between;align-items:baseline;margin-top:10px;padding-top:9px;border-top:1px solid var(--border);font-size:12.5px}
  .gs-sum-total span{color:var(--text2)}
  .gs-sum-total span b{color:var(--text)}
  .gs-sum-total strong{font-size:15px}
  .gs-sum-total em{font-style:normal;color:var(--text3)}
  .gs-shared{background:#faf3d9 !important;border-color:#ecd98f !important}
  .gs-shared-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .gs-shared-head h3{margin:0}
  .gs-link{font:inherit;font-size:12.5px;font-weight:700;color:#4a3a06;background:none;border:none;padding:0;min-height:28px;cursor:pointer;text-decoration:underline}
  .gs-shared-chips{display:flex;flex-wrap:wrap;gap:6px}
  .gs-shared-chip{font:inherit;font-size:12.5px;font-weight:700;display:inline-flex;align-items:center;gap:5px;padding:0 10px;min-height:30px;border-radius:999px;cursor:pointer;background:#fff;color:var(--text2);border:1px dashed #b5941f}
  .gs-shared-chip.on{background:#18191f;color:#fff;border:1px solid #18191f}
  span.gs-shared-chip{cursor:default}
  .gs-shared-chip:disabled{cursor:not-allowed;opacity:.6}
  .gs-shared-chip svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
  .gs-todo{flex:1 1 auto;min-height:0;overflow:hidden;display:flex;flex-direction:column}
  .gs-todo h3{margin-bottom:4px}
  .gs-todo ul{list-style:none;margin:0 -8px;padding:0}
  .gs-todo li button{font:inherit;width:100%;display:flex;align-items:center;justify-content:space-between;gap:10px;text-align:left;padding:6px 8px;min-height:44px;border:none;border-radius:8px;background:none;cursor:pointer;color:var(--text)}
  .gs-todo li button:hover{background:var(--bg3)}
  .gs-todo li .t{display:flex;flex-direction:column;min-width:0}
  .gs-todo li .t b{font-size:13px;font-weight:600;line-height:1.3}
  .gs-todo li .t small{font-size:11.5px;color:var(--text3)}
  .gs-todo li svg{width:14px;height:14px;flex-shrink:0;stroke:var(--text3);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .gs-todo .more{margin:2px 0 4px;font-size:12px;color:var(--text3)}
  .gs-todo .all{margin:0 0 6px;font-size:13px;color:#15803d;font-weight:600}

  /* ── Review step ── */
  .gs-review-main{flex:1;min-width:0;min-height:0;display:flex;flex-direction:column;overflow:hidden}
  .gs-review-main .gs-main-head{justify-content:space-between}
  .gs-review-list{flex:1;min-height:0;overflow:auto}
  .gs-review-sec{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:7px 16px;background:var(--bg3);border-bottom:1px solid var(--bg4)}
  .gs-review-sec h3{margin:0;font-size:13px;font-weight:700}
  .gs-review-sec span{font-size:12.5px;color:var(--text2)}
  .gs-review-sec span b{color:var(--text)}
  .gs-review-row{font:inherit;display:grid;grid-template-columns:minmax(0,1fr) 132px 56px;align-items:center;gap:12px;padding:0 16px;height:34px;width:100%;border:none;border-bottom:1px solid var(--bg4);background:none;text-align:left;cursor:pointer;color:var(--text)}
  .gs-review-row:hover{background:var(--bg3)}
  .gs-review-row .n{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .gs-review-row .lv{justify-self:start;font-size:11.5px;font-weight:700;padding:2px 9px;border-radius:999px;background:var(--bg4);color:var(--text2);white-space:nowrap}
  .gs-review-row .sc{text-align:right;font-size:13.5px;font-weight:700}
  .gs-review-row.missing .lv{background:#fff7ed;color:#9a3412;box-shadow:inset 0 0 0 1px #fed7aa}
  .gs-review-row.missing .sc{color:#9a3412}
  .gs-review-aside{flex:0 0 400px;min-height:0;display:flex;flex-direction:column;gap:12px}
  .gs-review-aside .gs-card{padding:12px 16px}
  .gs-remarks{flex:1 1 auto;min-height:0;display:flex;flex-direction:column}
  .gs-remarks label{display:block;font-size:13.5px;font-weight:700;margin-bottom:8px}
  .gs-remarks textarea{flex:1;min-height:60px;width:100%;box-sizing:border-box;font:inherit;font-size:13px;line-height:1.45;padding:8px 10px;border:1px solid var(--border2);border-radius:10px;resize:none;color:var(--text)}
  .gs-remarks textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
  .gs-decision{flex:0 0 auto}
  .gs-decision-head{display:flex;justify-content:space-between;align-items:center;gap:8px}
  .gs-decision-head h3{margin:0;font-size:13.5px;font-weight:700}
  .gs-decision-head span{font-size:12px;color:var(--text2)}
  .gs-decision-head span b{color:var(--text)}
  .gs-panel-segs{display:flex;gap:4px;margin:8px 0 6px}
  .gs-panel-segs i{flex:1;height:5px;border-radius:3px;background:var(--border)}
  .gs-panel-segs i.on{background:var(--green)}
  .gs-decision .note{margin:0 0 10px;font-size:12px;color:var(--text3)}
  .gs-outcomes{border:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px}
  .gs-outcome{display:flex;gap:10px;align-items:flex-start;padding:7px 12px;border-radius:10px;min-height:44px;box-sizing:border-box;cursor:pointer;border:1px solid var(--border);background:var(--bg2)}
  .gs-outcome input{margin:2px 0 0;accent-color:#18191f;width:16px;height:16px;flex-shrink:0}
  .gs-outcome .t{display:flex;flex-direction:column;gap:1px}
  .gs-outcome .t b{font-size:13px}
  .gs-outcome .t small{font-size:12px;color:var(--text3);line-height:1.35}
  .gs-outcome:focus-within{outline:2px solid var(--accent3);outline-offset:2px}
  .gs-outcomes:disabled .gs-outcome{opacity:.55;cursor:not-allowed}
  .gs-outcome.is-pass:has(input:checked){border-color:var(--green);background:rgba(22,163,74,.07)}
  .gs-outcome.is-pass:has(input:checked) b{color:#15803d}
  .gs-outcome.is-major:has(input:checked){border-color:#ea580c;background:#fff7ed}
  .gs-outcome.is-major:has(input:checked) b{color:#c2410c}
  .gs-outcome.is-fail:has(input:checked){border-color:var(--red);background:rgba(220,38,38,.06)}
  .gs-outcome.is-fail:has(input:checked) b{color:#b91c1c}
  .gs-record{width:100%;justify-content:center;margin-top:8px}
  .gs-recorded{padding:12px;border-radius:10px;border:1px solid #86efac;background:rgba(22,163,74,.07)}
  .gs-recorded b{display:block;font-size:14px;color:#15803d}
  .gs-recorded.is-major{border-color:#fed7aa;background:#fff7ed}
  .gs-recorded.is-major b{color:#c2410c}
  .gs-recorded.is-fail{border-color:#fecaca;background:rgba(220,38,38,.06)}
  .gs-recorded.is-fail b{color:#b91c1c}
  .gs-recorded p{margin:2px 0 0;font-size:12.5px;color:var(--text2)}
  .gs-recorded small{display:block;font-size:12px;color:var(--text3);margin-top:8px}

  /* ── Footer ── */
  .gs-foot{flex:0 0 auto;display:flex;align-items:center;gap:12px;padding:10px 24px;background:var(--bg2);border-top:1px solid var(--border)}
  .gs-keys{flex:1;display:flex;gap:14px;font-size:12px;color:var(--text3);white-space:nowrap;overflow:hidden}
  .gs-foot .hint{font-size:13px;color:var(--text2);white-space:nowrap}

  /* ── Abstain dialog ── */
  .abstain-box{width:min(460px,calc(100vw - 32px))}
  .abstain-label{display:block;font-size:12px;font-weight:600;color:var(--text);margin-bottom:6px}
  #abstain-reason{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font:inherit;font-size:13px;line-height:1.5;resize:vertical}
  #abstain-reason:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
  .abstain-count{margin-top:4px;font-size:11.5px;color:var(--text3)}

  /* ── A phone: one criterion at a time ── */
  @media (min-width: 769px) and (max-width: 1024px){
    .gs-burger{display:flex}
  }
  @media (max-width: 1100px){
    .gs-keys{display:none}
    .gs-progress{flex-basis:170px}
    .gs-aside{flex-basis:240px}
  }
  @media (max-width: 900px){
    .gs-top{flex-wrap:wrap;gap:10px 12px;padding:10px 16px;background:#18191f;border-bottom:none;color:#fff}
    .gs-top .gs-iconbtn{background:transparent;border-color:rgba(255,255,255,.18);color:#fff}
    .gs-who h1,.gs-who h1 span{color:#fff;white-space:normal}
    .gs-meta{color:rgba(255,255,255,.7);flex-wrap:wrap;white-space:normal}
    .gs-meta .gs-chip{background:rgba(255,255,255,.12);color:#fff}
    .gs-divider,.gs-saved{display:none}
    .gs-progress{order:5;flex:1 1 100%}
    .gs-progress-top b{color:#e3c75a}
    .gs-progress-top .of,.gs-progress-top .cnt{color:rgba(255,255,255,.7)}
    .gs-segs span{background:rgba(255,255,255,.18)}
    .gs-alerts{padding:10px 16px 0}
    .gs-meta .rubric{display:none}
    .gs-steps,.gs-aside,.gs-main-head,.gs-table-wrap,.gs-main-foot .sum{display:none}
    .gs-body{flex-direction:column;padding:12px 16px;overflow:auto}
    .gs-review-aside{flex:0 0 auto}
    .gs-review-main{flex:0 0 auto;overflow:visible}
    .gs-review-list{overflow:visible}
    .gs-one{display:block;padding:16px}
    .gs-main{flex:0 0 auto;align-self:stretch;max-height:none}
    .gs-main-foot{background:var(--bg2)}
    .gs-main-foot .gs-btn{min-height:48px}
    .gs-main-foot .gs-btn-outline{flex:1;justify-content:center;background:#18191f;border-color:#18191f;color:#fff}
    .gs-main-foot .gs-btn:first-of-type{width:48px;padding:0;justify-content:center}
    .gs-review-row{grid-template-columns:minmax(0,1fr) auto 48px}
    .gs-foot{padding:10px 16px calc(10px + env(safe-area-inset-bottom,0px));flex-wrap:wrap}
    .gs-foot .hint{display:none}
  }
  .gs-one-eyebrow{display:flex;justify-content:space-between;align-items:baseline;gap:8px;margin-bottom:8px}
  .gs-one-eyebrow b{font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--accent3)}
  .gs-one-eyebrow span{font-size:12.5px;color:var(--text3);white-space:nowrap}
  .gs-one h2{margin:0;font-size:18px;line-height:1.3}
  .gs-one > p{margin:4px 0 14px;font-size:13px;line-height:1.45;color:var(--text3)}
  .gs-one-levels{display:flex;flex-direction:column;gap:8px}
  .gs-one-level{font:inherit;text-align:left;display:flex;flex-direction:column;gap:6px;padding:12px 14px;min-height:56px;border-radius:12px;cursor:pointer;color:var(--text);background:var(--bg2);border:1px solid var(--border)}
  .gs-one-level .top{display:flex;justify-content:space-between;align-items:center;gap:8px}
  .gs-one-level .top b{font-size:14px}
  .gs-one-level .pts{font-size:12.5px;font-weight:700;padding:2px 8px;border-radius:6px;background:var(--bg3);color:#6b5409;box-shadow:inset 0 0 0 1px var(--border)}
  .gs-one-level .ds{font-size:13px;line-height:1.45;color:var(--text2)}
  .gs-one-level[aria-checked="true"]{background:#faf3d9;border:2px solid var(--accent)}
  .gs-one-level[aria-checked="true"] .pts{background:#18191f;color:#fff;box-shadow:none}
  @media (prefers-reduced-motion: reduce){.gs *{transition:none !important}}
</style>
  @include('partials.render-whole')
</head>
<body>

@include('instructor.partials.sidebar', ['currentPage' => 'grading', 'sidebarRail' => true])
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="main">
  <div id="skeleton-content" class="sk-overlay">
    <div class="sk" style="height:60px;border-radius:12px;margin-bottom:12px"></div>
    <div class="sk" style="height:48px;border-radius:12px;margin-bottom:12px"></div>
    <div class="sk" style="height:320px;border-radius:12px"></div>
  </div>

  <div id="real-content" style="display:none">
  <div class="gs">

    <header class="gs-top">
      <button type="button" class="gs-iconbtn gs-burger" onclick="toggleSidebar()" aria-label="Open menu">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
      <a href="{{ route('instructor.grading') }}" class="gs-iconbtn" aria-label="Back to Team Grading">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      </a>
      <div class="gs-who">
        <h1>{{ $defense->team?->team_name ?? 'Unknown Team' }} <span>— {{ $defense->team?->project_title ?? 'No title' }}</span></h1>
        <div class="gs-meta">
          <span>{{ $defense->defense_type }}</span>
          <span aria-hidden="true">·</span>
          <span class="gs-chip {{ $isChair ? 'gold' : '' }}">{{ $panelRole }}</span>
          <span class="rubric" aria-hidden="true">·</span>
          <span class="rubric">{{ $rubric->rubric_name }}</span>
        </div>
      </div>
      <div class="gs-progress">
        <div class="gs-progress-top">
          <span><b id="gs-total">0</b><span class="of"> / {{ $fmt($rubric->total_points) }}</span></span>
          <span class="cnt"><span id="gs-done">0</span> of {{ $rubric->criteria->count() }} criteria</span>
        </div>
        <div class="gs-segs" id="gs-segs" role="progressbar" aria-label="Criteria scored" aria-valuemin="0" aria-valuemax="{{ $rubric->criteria->count() }}"></div>
      </div>
      <span class="gs-divider" aria-hidden="true"></span>
      <span class="gs-saved" id="saveStatus" role="status"><i aria-hidden="true"></i><span>{{ $saveText }}</span></span>
      @include('partials.page-guide')
    </header>

    <div class="gs-alerts">
      <p class="gs-alert err" id="formError" role="alert" hidden></p>
      @if ($isAbstained)
        <div class="gs-alert muted">
          <span><b>You abstained from grading this team.</b> Your scores are left out of the team's average.
            <span class="why">Your reason: &ldquo;{{ $grade->remarks }}&rdquo;</span></span>
        </div>
      @elseif ($grade?->isFromPaper() && ! $defense->hasOutcome())
        <div class="gs-alert warn"><span><b>{{ $grade->remarks }}</b> Total {{ $fmt($grade->total_score) }} / {{ $fmt($rubric->total_points) }}. You can still change it — scoring the rubric and submitting replaces it.</span></div>
      @elseif ($grade?->status === 'submitted' && ! $defense->hasOutcome())
        <div class="gs-alert ok"><span>Submitted. You can still change it — submitting again replaces it.</span></div>
      @endif
    </div>

    <nav class="gs-steps" id="gs-steps" aria-label="Rubric sections"></nav>

    {{-- The rubric, one section at a time. --}}
    <div class="gs-body" id="gs-sheet-body">
      <section class="gs-card gs-main" id="gs-sheet-area" aria-live="polite"></section>
      <aside class="gs-aside" aria-label="Summary">
        @if ($isGrader)
          <section class="gs-card gs-shared">
            <div class="gs-shared-head">
              <h3>Grading for the panel</h3>
              @unless ($defense->hasOutcome() || ! $isChair)
                <button type="button" class="gs-link" id="gs-who-change">Change</button>
              @endunless
            </div>
            <div class="gs-shared-chips" id="gs-shared-chips">
              <span class="gs-shared-chip on">You</span>
              @foreach ($who['panel'] as $p)
                @continue($p['id'] === $who['me'])
                @php $on = in_array($p['id'], $who['joined'], true); @endphp
                <button type="button" class="gs-shared-chip {{ $on ? 'on' : '' }}" data-id="{{ $p['id'] }}" aria-pressed="{{ $on ? 'true' : 'false' }}" @disabled($p['turnedIn'] || $defense->hasOutcome() || ! $isChair) title="{{ $p['turnedIn'] ? 'Already turned in a grade of their own' : ($on ? 'Take '.$p['name'].' out' : 'Grade for '.$p['name'].' too') }}">
                  @if ($on)<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>@endif
                  {{ $p['name'] }}
                </button>
              @endforeach
            </div>
          </section>
        @endif
        <section class="gs-card">
          <h3>Score summary</h3>
          <div class="gs-sum-rows" id="gs-sum-rows"></div>
          <div class="gs-sum-total">
            <span>Panel: <b>{{ $gradesIn }} of {{ $panelSize }}</b> in</span>
            <span><strong id="gs-sum-total">0</strong><em> / {{ $fmt($rubric->total_points) }}</em></span>
          </div>
        </section>
        <section class="gs-card gs-todo" id="gs-todo"></section>
      </aside>
    </div>

    {{-- Review and submit: every score, the remarks and the panel's decision. --}}
    <div class="gs-body" id="gs-review-body" hidden>
      <section class="gs-card gs-review-main">
        <div class="gs-main-head">
          <h2>{{ $isGrader ? 'Shared grade' : 'Your scores' }}</h2>
          <span class="meta">{{ $isGrader ? 'For '.$sharedCount.' panelists' : 'Select a criterion to change it' }}</span>
        </div>
        <div class="gs-review-list" id="gs-review-list"></div>
      </section>
      <aside class="gs-review-aside">
        <form id="gradingForm" class="gs-card gs-remarks" method="post" onsubmit="return false">
          @csrf
          {{-- Remarks are this panelist's own. The verdict is not: the panel
               confers and gives the team one result, recorded once by the
               chair beside this. An abstention's remarks are its reason, not
               comments for the team. --}}
          <label for="gs-remarks-field">{{ $isGrader ? 'Panel remarks' : 'Your remarks' }}</label>
          <textarea id="gs-remarks-field" name="remarks" placeholder="What should the team fix, and what worked?">{{ $isAbstained || $grade?->isFromPaper() ? '' : $grade?->remarks }}</textarea>
        </form>

        <section class="gs-card gs-decision">
          <div class="gs-decision-head">
            <h3>Panel decision</h3>
            <span>Panelists in <b>{{ $gradesIn }} of {{ $panelSize }}</b></span>
          </div>
          <div class="gs-panel-segs" aria-hidden="true">
            @for ($i = 0; $i < $panelSize; $i++)<i class="{{ $i < $gradesIn ? 'on' : '' }}"></i>@endfor
          </div>

          @if ($defense->hasOutcome())
            <div class="gs-recorded is-{{ $outcomeTone }}">
              <b>{{ $defense->outcomeLabel() }}</b>
              <p>{{ \App\Support\DefenseOutcome::description($defense->outcome, $defense->defense_type) }}</p>
              @if ($defense->outcome_remarks)
                <p>{{ $defense->outcome_remarks }}</p>
              @endif
              <small>Recorded by {{ $defense->outcomeRecordedBy?->name ?? 'the Panel Chair' }}@if ($defense->outcome_recorded_at) · {{ $defense->outcome_recorded_at->format('M j, g:i A') }}@endif</small>
            </div>
          @elseif (! $canRecord)
            <p class="note">Your Panel Chair records the decision.</p>
          @else
            @if ($gradesIn < $panelSize)
              @php
                // Those joined to this grade come in with it: they are not waited on.
                $waiting = collect($waitingOn)->diff(collect($gradingFor)->pluck('name'))->values();
              @endphp
              <p class="note">{{ $isGrader && $waiting->all() === ['you'] ? 'Opens when you submit' : 'Waiting on '.$waiting->join(', ', ' and ') }}</p>
            @endif
            <fieldset class="gs-outcomes" @disabled($gradesIn < $panelSize)>
              <legend class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">What the panel agreed</legend>
              @foreach ($outcomeOptions as $option)
                <label class="gs-outcome is-{{ \App\Support\DefenseOutcome::tone($option) }}">
                  <input type="radio" name="panel_outcome" value="{{ $option }}"/>
                  <span class="t">
                    <b>{{ \App\Support\DefenseOutcome::label($option, $defense->defense_type) }}</b>
                    <small>{{ \App\Support\DefenseOutcome::description($option, $defense->defense_type) }}</small>
                  </span>
                </label>
              @endforeach
            </fieldset>
            <button type="button" class="gs-btn gs-btn-dark gs-record" id="record-outcome-btn" onclick="recordOutcome()" @disabled($gradesIn < $panelSize)>Record panel decision</button>
          @endif
        </section>
      </aside>
    </div>

    <footer class="gs-foot">
      <div class="gs-keys">
        <span><kbd>1</kbd>–<kbd>4</kbd> score</span>
        <span><kbd>↑</kbd><kbd>↓</kbd> criterion</span>
        <span><kbd>←</kbd><kbd>→</kbd> section</span>
        <span><kbd>N</kbd> next unscored</span>
      </div>
      <span class="hint" id="gs-hint"></span>
      @if ($isAbstained)
        @unless ($defense->hasOutcome())
          <button type="button" class="gs-btn gs-btn-dark" id="gs-withdraw" onclick="withdrawAbstention()">Withdraw abstention and grade</button>
        @endunless
      @else
        @unless ($defense->hasOutcome())
          <button type="button" class="gs-btn gs-btn-dashed" onclick="openAbstain()" title="Decline to score this team, with a reason">Abstain</button>
        @endunless
        <button type="button" class="gs-btn" onclick="saveDraft()">Save draft</button>
        <button type="button" class="gs-btn gs-btn-dark" id="gs-to-review">Review and submit</button>
        <button type="button" class="gs-btn gs-btn-dark" id="gs-submit" onclick="submitGrade()" hidden disabled>{{ $isGrader ? 'Submit for all '.$sharedCount : 'Submit grade' }}</button>
      @endif
    </footer>

  </div>
  </div>
</div>

{{-- Abstaining: declining to score this team, with the reason. --}}
<div class="modal-overlay" id="abstain-modal" onclick="if (event.target === this) closeAbstain()">
  <div class="modal-box abstain-box" role="dialog" aria-modal="true" aria-labelledby="abstain-title">
    <div class="modal-title" id="abstain-title">Abstain from grading {{ $defense->team?->team_name ?? 'this team' }}?</div>
    <div class="modal-sub">
      You will not score this team. It still counts as turned in, and the team's average comes from the panelists who scored.
      The team sees that you abstained, not your reason.
    </div>
    <label for="abstain-reason" class="abstain-label">Reason</label>
    <textarea id="abstain-reason" rows="4" maxlength="1000" placeholder="e.g. I advised this team, so I should not grade it."></textarea>
    <div class="abstain-count" id="abstain-count">At least 10 characters.</div>
    <p class="gs-alert err" id="abstain-error" role="alert" hidden style="margin:10px 0 0"></p>
    <div class="modal-actions" style="margin-top:16px">
      <button type="button" class="gs-btn" aria-label="Close" onclick="closeAbstain()">Cancel</button>
      <button type="button" class="gs-btn gs-btn-danger" id="abstain-confirm" onclick="confirmAbstain()">Abstain</button>
    </div>
  </div>
</div>

@include('instructor.partials.skeleton-toggle', ['revealDisplay' => 'flex'])
@include('instructor.partials.who-grades-dialog')

<script nonce="{{ Vite::cspNonce() }}">
const SECTIONS = {{ Js::from($sections) }};
const scores = {{ Js::from((object) $savedScores->all()) }};
const ROUTES = {
  save: '{{ route('instructor.grading.save', $defense->defense_id) }}',
  submit: '{{ route('instructor.grading.submit', $defense->defense_id) }}',
  outcome: '{{ route('instructor.grading.outcome', $defense->defense_id) }}',
  abstain: '{{ route('instructor.grading.abstain', $defense->defense_id) }}',
  list: '{{ route('instructor.grading') }}',
};

const ALL = SECTIONS.flatMap((s, si) => s.items.map((c, ci) => Object.assign(c, { sec: si, no: (si + 1) + '.' + (ci + 1) })));
const REVIEW = SECTIONS.length;
const $ = id => document.getElementById(id);
const el = (t, c, x) => { const e = document.createElement(t); if (c) e.className = c; if (x != null) e.textContent = x; return e; };
const svg = d => '<svg viewBox="0 0 24 24" aria-hidden="true">' + d + '</svg>';
const ICON = {
  check: svg('<path d="M20 6 9 17l-5-5"/>'),
  next: svg('<path d="m9 18 6-6-6-6"/>'),
  prev: svg('<path d="m15 18-6-6 6-6"/>'),
  pen: svg('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>'),
};
const fmt = n => +(+n).toFixed(2);
const same = (a, b) => a !== undefined && Math.abs(a - b) < 0.001;
const has = c => c.id in scores;
const secScore = s => fmt(s.items.reduce((a, c) => a + (scores[c.id] ?? 0), 0));
const secDone = s => s.items.filter(has).length;
const total = () => fmt(ALL.reduce((a, c) => a + (scores[c.id] ?? 0), 0));
const unscored = () => ALL.filter(c => !has(c));
const narrow = () => window.matchMedia('(max-width: 900px)').matches;
const levelName = (c, k) => c.levels[k]?.label || 'Level ' + (k + 1);
const pickedLevel = c => c.levels.findIndex(l => same(scores[c.id], l.s));

let sec = 0, page = 0, pages = [[]], cur = 0;
let dirty = false;

// Open on the first criterion still to score - or on the review, when asked
// for or when there is nothing left to score.
(function start() {
  const first = ALL.find(c => !has(c));
  if (first) { cur = ALL.indexOf(first); sec = first.sec; } else { sec = REVIEW; }
  if (new URLSearchParams(location.search).get('step') === 'review') sec = REVIEW;
})();

/* ── Header ──────────────────────────────────────────────────── */
function drawHeader() {
  const done = ALL.length - unscored().length;
  $('gs-total').textContent = total();
  $('gs-done').textContent = done;
  const segs = $('gs-segs'); segs.innerHTML = '';
  segs.setAttribute('aria-valuenow', done);
  ALL.forEach((c, i) => segs.append(el('span', i < done ? 'on' : '')));
}

/* ── Section steps ───────────────────────────────────────────── */
function drawSteps() {
  const nav = $('gs-steps'); nav.innerHTML = '';
  SECTIONS.forEach((s, i) => {
    const done = secDone(s), n = s.items.length;
    const b = el('button', 'gs-step' + (i === sec ? ' on' : '') + (done === n ? ' done' : ''));
    b.type = 'button';
    b.setAttribute('aria-current', i === sec ? 'step' : 'false');
    const dot = el('span', 'dot');
    if (done === n) dot.innerHTML = ICON.check; else dot.textContent = i + 1;
    const txt = el('span', 'txt');
    const bar = el('span', 'bar'); const fill = el('i'); fill.style.width = Math.round(done / n * 100) + '%'; bar.append(fill);
    txt.append(el('span', 'nm', s.name), el('span', 'st', fmt(secScore(s)) + ' / ' + fmt(s.max) + ' pts · ' + done + ' of ' + n + ' scored'), bar);
    b.append(dot, txt);
    b.onclick = () => go(i, 0);
    nav.append(b);
  });
  const r = el('button', 'gs-step review' + (sec === REVIEW ? ' on' : ''));
  r.type = 'button';
  r.setAttribute('aria-current', sec === REVIEW ? 'step' : 'false');
  const dot = el('span', 'dot'); dot.innerHTML = ICON.pen;
  const txt = el('span', 'txt'); txt.append(el('span', 'nm', 'Review and submit'), el('span', 'st', 'Scores · decision'));
  r.append(dot, txt);
  r.onclick = () => go(REVIEW, 0);
  nav.append(r);
}

/* ── The sheet ───────────────────────────────────────────────── */
// Column headers are shared only when every criterion in the section has the
// same levels; otherwise each cell names its own level.
function sharedLabels(s) {
  const first = s.items[0]?.levels.map(l => l.label).join('|');
  return s.items.every(c => c.levels.map(l => l.label).join('|') === first) ? s.items[0].levels.map(l => l.label) : null;
}

function shell(box, s) {
  const labels = sharedLabels(s);
  const cols = Math.max(...s.items.map(c => c.levels.length));
  const t = el('table', 'gs-table'); const h = el('tr');
  const th0 = el('th', 'crit-col', 'Criterion'); th0.scope = 'col'; h.append(th0);
  for (let k = 0; k < cols; k++) {
    const th = el('th'); th.scope = 'col';
    if (k < 9) th.append(el('kbd', '', k + 1));
    th.append(labels ? (labels[k] || 'Level ' + (k + 1)) : 'Level ' + (k + 1));
    h.append(th);
  }
  const ths = el('th', 'score-col', 'Score'); ths.scope = 'col'; h.append(ths);
  const thead = el('thead'); thead.append(h);
  const tb = el('tbody'); t.append(thead, tb); box.append(t);
  return { tb, labels, cols };
}

function row(c, shared, cols) {
  const tr = el('tr', (has(c) ? 'scored' : '') + (ALL.indexOf(c) === cur ? ' cur' : ''));
  const th = el('th'); th.scope = 'row';
  const btn = el('button', 'gs-crit'); btn.type = 'button';
  const name = el('b'); name.append(el('span', 'no', c.no), ' ', el('span', 'nm', c.n));
  btn.append(name); if (c.d) btn.append(el('span', 'd', c.d));
  btn.onclick = () => { cur = ALL.indexOf(c); draw(); };
  th.append(btn); tr.append(th);
  for (let k = 0; k < cols; k++) {
    const td = el('td'); const l = c.levels[k];
    if (l) {
      const b = el('button', 'gs-cell'); b.type = 'button';
      b.setAttribute('aria-pressed', same(scores[c.id], l.s));
      b.setAttribute('aria-label', c.n + ': ' + levelName(c, k) + ', ' + fmt(l.s) + ' points. ' + (l.desc || ''));
      const top = el('span', 'top');
      top.append(el('span', 'pts', fmt(l.s) + ' pts'));
      const tick = el('span', 'tick'); tick.innerHTML = ICON.check; top.append(tick);
      b.append(top);
      if (!shared && l.label) b.append(el('span', 'lb', l.label));
      if (l.desc) b.append(el('span', 'ds', l.desc));
      b.onclick = () => score(c, l.s);
      td.append(b);
    }
    tr.append(td);
  }
  const sc = el('td', 'gs-score');
  if (has(c)) {
    const k = pickedLevel(c);
    sc.append(el('b', '', fmt(scores[c.id])), el('small', '', k >= 0 ? levelName(c, k) : ''));
  } else {
    sc.append(el('span', 'empty'), el('small', '', 'Not scored'));
  }
  tr.append(sc);
  return tr;
}

// A phone shows one criterion at a time, its levels stacked.
function drawOne(box, s) {
  const items = s.items;
  let c = ALL[cur];
  if (!c || c.sec !== sec) { c = items.find(x => !has(x)) || items[0]; cur = ALL.indexOf(c); }
  const i = items.indexOf(c);
  const eyebrow = el('div', 'gs-one-eyebrow');
  eyebrow.append(el('b', '', s.name), el('span', '', 'Criterion ' + (i + 1) + ' of ' + items.length + ' · ' + fmt(secScore(s)) + ' / ' + fmt(s.max)));
  box.append(eyebrow, el('h2', '', c.n));
  if (c.d) box.append(el('p', '', c.d));
  const group = el('div', 'gs-one-levels');
  group.setAttribute('role', 'radiogroup'); group.setAttribute('aria-label', c.n);
  c.levels.forEach((l, k) => {
    const b = el('button', 'gs-one-level'); b.type = 'button';
    b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', same(scores[c.id], l.s));
    const top = el('span', 'top'); top.append(el('b', '', levelName(c, k)), el('span', 'pts', fmt(l.s) + ' pts'));
    b.append(top); if (l.desc) b.append(el('span', 'ds', l.desc));
    b.onclick = () => score(c, l.s);
    group.append(b);
  });
  box.append(group);
}

function drawSheet() {
  const s = SECTIONS[sec];
  const area = $('gs-sheet-area'); area.innerHTML = '';

  const head = el('div', 'gs-main-head');
  const pageLabel = el('span', 'meta');
  const jump = el('button', 'gs-btn gs-btn-sm'); jump.type = 'button'; jump.id = 'gs-jump';
  jump.append('Next unscored ', el('kbd', '', 'N'));
  jump.disabled = !unscored().length;
  jump.onclick = jumpNext;
  head.append(el('h2', '', s.name), el('span', 'meta', 'Max ' + fmt(s.max) + ' pts'), el('span', 'grow'), pageLabel, jump);

  const rows = el('div', 'gs-rows gs-table-wrap');
  const one = el('div', 'gs-one');
  const foot = el('div', 'gs-main-foot');
  area.append(head, rows, one, foot);

  // Fill a page until the next row would overflow, then start another.
  pages = [[]];
  if (narrow()) {
    pages = [s.items.slice()];
    drawOne(one, s);
  } else {
    let sh = shell(rows, s);
    s.items.forEach(c => {
      sh.tb.append(row(c, sh.labels, sh.cols));
      if (rows.scrollHeight > rows.clientHeight + 1 && pages[pages.length - 1].length) {
        rows.innerHTML = ''; sh = shell(rows, s); sh.tb.append(row(c, sh.labels, sh.cols)); pages.push([]);
      }
      pages[pages.length - 1].push(c);
    });
    page = Math.max(0, Math.min(page, pages.length - 1));
    rows.innerHTML = '';
    const sh2 = shell(rows, s);
    pages[page].forEach(c => sh2.tb.append(row(c, sh2.labels, sh2.cols)));
    if (pages.length > 1) {
      const first = s.items.indexOf(pages[page][0]) + 1, last = first + pages[page].length - 1;
      pageLabel.textContent = 'Criteria ' + first + '–' + last + ' of ' + s.items.length;
    }
  }

  const sum = el('div', 'sum');
  const doneB = el('b', '', secDone(s) + ' of ' + s.items.length);
  const subB = el('b', '', fmt(secScore(s)) + ' / ' + fmt(s.max));
  sum.append(doneB, ' scored · subtotal ', subB);

  const prev = el('button', 'gs-btn'); prev.type = 'button';
  const next = el('button', 'gs-btn gs-btn-outline'); next.type = 'button';
  if (narrow()) {
    const i = s.items.indexOf(ALL[cur]);
    prev.innerHTML = ICON.prev; prev.setAttribute('aria-label', 'Previous criterion');
    prev.disabled = sec === 0 && i <= 0;
    prev.onclick = () => step(-1);
    next.append('Next criterion '); next.insertAdjacentHTML('beforeend', ICON.next);
    next.onclick = () => step(1);
  } else {
    prev.textContent = page > 0 ? 'Previous page' : 'Previous section';
    prev.disabled = sec === 0 && page === 0;
    prev.onclick = () => page > 0 ? go(sec, page - 1) : go(sec - 1, 99);
    const label = page < pages.length - 1 ? 'Next page' : (sec < SECTIONS.length - 1 ? 'Next section' : 'Review and submit');
    next.append(label + ' '); next.insertAdjacentHTML('beforeend', ICON.next);
    next.onclick = () => page < pages.length - 1 ? go(sec, page + 1) : go(sec + 1, 0);
  }
  foot.append(sum, prev, next);
}

// The phone's previous and next: criterion by criterion, across sections.
function step(d) {
  const j = ALL.indexOf(ALL[cur]) + d;
  if (j < 0) return;
  if (j >= ALL.length) { go(REVIEW, 0); return; }
  cur = j; sec = ALL[j].sec; draw();
}

/* ── Side column ─────────────────────────────────────────────── */
function drawAside() {
  const box = $('gs-sum-rows'); box.innerHTML = '';
  SECTIONS.forEach(s => {
    const done = secDone(s), n = s.items.length;
    const r = el('div', 'gs-sum-row' + (done === n ? ' done' : ''));
    const line = el('div', 'line'); line.append(el('span', '', s.name), el('b', '', fmt(secScore(s)) + ' / ' + fmt(s.max)));
    const bar = el('span', 'bar'); const fill = el('i'); fill.style.width = Math.round(done / n * 100) + '%'; bar.append(fill);
    r.append(line, bar);
    box.append(r);
  });
  $('gs-sum-total').textContent = total();

  const todo = $('gs-todo'); todo.innerHTML = '';
  const left = unscored();
  const h = el('h3', '', 'Still to score '); h.append(el('span', '', '(' + left.length + ')'));
  todo.append(h);
  if (!left.length) { todo.append(el('p', 'all', 'Every criterion is scored.')); return; }
  const ul = el('ul'); todo.append(ul);
  const more = el('p', 'more');
  left.forEach(c => {
    const li = el('li'); const b = el('button'); b.type = 'button';
    const t = el('span', 't'); t.append(el('b', '', c.n), el('small', '', SECTIONS[c.sec].name));
    b.append(t); b.insertAdjacentHTML('beforeend', ICON.next);
    b.onclick = () => jumpTo(c);
    li.append(b); ul.append(li);
  });
  // As many as the card has room for, then a count of the rest.
  todo.append(more);
  let hidden = 0;
  while (ul.children.length > 1 && todo.scrollHeight > todo.clientHeight + 1) {
    ul.lastElementChild.remove(); hidden++;
    more.textContent = 'and ' + hidden + ' more';
  }
  if (!hidden) more.remove();
}

/* ── Review and submit ───────────────────────────────────────── */
function drawReview() {
  const list = $('gs-review-list'); list.innerHTML = '';
  SECTIONS.forEach(s => {
    const head = el('div', 'gs-review-sec');
    const sub = el('span'); sub.append(el('b', '', fmt(secScore(s))), ' / ' + fmt(s.max));
    head.append(el('h3', '', s.name), sub);
    list.append(head);
    s.items.forEach(c => {
      const b = el('button', 'gs-review-row' + (has(c) ? '' : ' missing')); b.type = 'button';
      const k = pickedLevel(c);
      b.append(el('span', 'n', c.n), el('span', 'lv', has(c) ? (k >= 0 ? levelName(c, k) : 'Scored') : 'Not scored'), el('span', 'sc', has(c) ? fmt(scores[c.id]) : '—'));
      b.setAttribute('aria-label', c.n + ': ' + (has(c) ? fmt(scores[c.id]) + ' points. Change it' : 'not scored. Score it'));
      b.onclick = () => jumpTo(c);
      list.append(b);
    });
  });
}

function drawMissing() {
  let box = $('gs-missing');
  const left = unscored();
  if (sec !== REVIEW || !left.length || {{ $isAbstained ? 'true' : 'false' }}) { if (box) box.remove(); return; }
  if (!box) { box = el('div', 'gs-alert warn'); box.id = 'gs-missing'; box.setAttribute('role', 'alert'); $('formError').after(box); }
  box.innerHTML = '';
  const names = left.slice(0, 2).map(c => c.n).join(', ') + (left.length > 2 ? ' and ' + (left.length - 2) + ' more' : '');
  const t = el('span'); t.append(el('b', '', 'Not scored: '), names);
  const go = el('button', 'link', 'Score it'); go.type = 'button'; go.onclick = () => jumpTo(left[0]);
  box.append(t, go);
}

/* ── Drawing it all ──────────────────────────────────────────── */
function go(s, p) {
  if (s < 0 || s > REVIEW) return;
  sec = s; page = p;
  draw();
  if (sec < REVIEW && !narrow() && !pages[page].some(c => ALL.indexOf(c) === cur)) { cur = ALL.indexOf(pages[page][0]); draw(); }
}

function jumpTo(c) {
  cur = ALL.indexOf(c); sec = c.sec; page = 0; draw();
  const p = pages.findIndex(pg => pg.includes(c)); if (p > 0) { page = p; draw(); }
}

function jumpNext() {
  const left = unscored(); if (!left.length) return;
  const after = left.find(c => ALL.indexOf(c) > cur) || left[0];
  jumpTo(after);
}

function score(c, s) {
  scores[c.id] = s;
  dirty = true;
  setStatus('Unsaved changes', true);
  // Each pick is kept as a draft, so a refresh does not lose it. Not once
  // submitted: that would quietly take the grade back out of the panel's.
  clearTimeout(autoSave);
  if (!submitted) autoSave = setTimeout(saveDraft, 600);
  const next = SECTIONS[c.sec].items.find(x => !has(x));
  if (next) {
    cur = ALL.indexOf(next);
    const p = pages.findIndex(pg => pg.includes(next));
    if (p >= 0) page = p;
  }
  draw();
}

function draw() {
  const onReview = sec === REVIEW;
  $('gs-sheet-body').hidden = onReview;
  $('gs-review-body').hidden = !onReview;
  drawHeader();
  drawSteps();
  if (onReview) drawReview(); else { drawSheet(); drawAside(); }
  drawMissing();

  const left = unscored().length;
  $('gs-hint').textContent = left ? left + ' left to score' : '';
  // The server refuses a submission with any criterion unscored. There is no
  // Submit button while abstaining.
  const submit = $('gs-submit'), toReview = $('gs-to-review');
  if (submit) {
    submit.hidden = !onReview;
    submit.disabled = left > 0;
    submit.title = left ? 'Score every criterion first' : '';
  }
  if (toReview) {
    toReview.hidden = onReview;
    toReview.onclick = () => go(REVIEW, 0);
  }
}

document.addEventListener('keydown', e => {
  if (e.target.closest && e.target.closest('textarea, input, select, [contenteditable]')) return;
  if (e.ctrlKey || e.metaKey || e.altKey) return;
  if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
    e.preventDefault();
    const forward = e.key === 'ArrowRight';
    if (sec < REVIEW && forward && page < pages.length - 1) go(sec, page + 1);
    else if (sec < REVIEW && !forward && page > 0) go(sec, page - 1);
    else go(sec + (forward ? 1 : -1), forward ? 0 : 99);
    return;
  }
  if (e.key === 'n' || e.key === 'N') { e.preventDefault(); jumpNext(); return; }
  if (sec === REVIEW) return;
  const items = SECTIONS[sec].items, i = items.indexOf(ALL[cur]);
  if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
    e.preventDefault();
    const j = Math.max(0, Math.min(items.length - 1, (i < 0 ? 0 : i) + (e.key === 'ArrowDown' ? 1 : -1)));
    cur = ALL.indexOf(items[j]);
    const p = pages.findIndex(pg => pg.includes(items[j])); if (p >= 0 && p !== page) page = p;
    draw();
  } else if (/^[1-9]$/.test(e.key) && i >= 0) {
    const level = items[i].levels[+e.key - 1];
    if (level) score(items[i], level.s);
  }
});

// The sheet fills a page until the next row would overflow, so it can only be
// drawn against a height that is real. Being visible is not enough: the moment
// the skeleton hands over, the area is on screen with no height yet, and
// measuring then puts a single criterion on every page.
let bodyHeight = 0;
let waited = 0;

(function whenVisible() {
  const page = document.querySelector('.gs');
  const ready = page.offsetParent !== null && page.clientHeight > 0;

  // Waiting for a height is worth a couple of seconds, never the whole page:
  // if one never arrives, a badly paginated sheet still beats a blank one.
  if (!ready && waited++ < 180) {
    requestAnimationFrame(whenVisible);

    return;
  }

  draw();
  // Rows are measured to fill the page; measure again once the font has loaded.
  document.fonts.ready.then(draw);

  // The height can still change under it - the font swapping, the sidebar
  // folding, a zoom. Only the page around the sheet can change it.
  if (window.ResizeObserver) {
    const sheetBody = $('gs-sheet-body');
    bodyHeight = sheetBody.clientHeight;
    new ResizeObserver(() => {
      const h = sheetBody.clientHeight;

      if (h > 0 && Math.abs(h - bodyHeight) > 4) {
        bodyHeight = h;
        draw();
      }
    }).observe(sheetBody);
  }
})();
let resizeTimer;
window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(draw, 120); });
window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
$('gs-remarks-field').addEventListener('input', () => { dirty = true; setStatus('Unsaved changes', true); });

/* ── Saving ──────────────────────────────────────────────────── */
function setStatus(text, unsaved) {
  const box = $('saveStatus');
  box.classList.toggle('dirty', !!unsaved);
  box.querySelector('span').textContent = text;
}

function clock() {
  return new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function showFormError(msg) {
  const box = $('formError');
  box.hidden = !msg;
  box.textContent = msg || '';
}

function collectPayload() {
  return { scores: scores, remarks: $('gs-remarks-field').value };
}

// The panel's one decision, recorded by the chair after the briefing.
function recordOutcome() {
  const picked = document.querySelector('input[name="panel_outcome"]:checked');
  if (!picked) { showFormError('Choose what the panel decided.'); return; }

  const btn = $('record-outcome-btn');
  btn.disabled = true;
  btn.textContent = 'Recording…';

  fetch(ROUTES.outcome, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ outcome: picked.value }),
  })
    .then(r => r.json())
    .then(res => {
      if (!res.success) {
        showFormError(res.message || 'Could not record the outcome.');
        btn.disabled = false; btn.textContent = 'Record panel decision';
        return;
      }
      dirty = false;
      reloadOnReview();
    })
    .catch(() => {
      showFormError('Could not reach the server.');
      btn.disabled = false; btn.textContent = 'Record panel decision';
    });
}

function reloadOnReview() {
  const url = new URL(location.href);
  url.searchParams.set('step', 'review');
  location.href = url.toString();
}

let autoSave = null;
let submitted = {{ Js::from($grade?->status === 'submitted') }};
// One save at a time, in order: a draft still on its way never lands after a submit.
let saving = Promise.resolve();

function postGrade(url, method) {
  clearTimeout(autoSave);
  showFormError('');
  setStatus('Saving…', true);

  saving = saving.then(() => fetch(url, {
    method: method,
    credentials: 'same-origin',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(collectPayload()),
  })
    .then(res => res.json().then(data => ({ ok: res.ok, data })))
    .then(({ ok, data }) => {
      if (!ok || !data.success) {
        setStatus('Not saved', true);
        showFormError(data.message || 'Something went wrong. Please try again.');
        return;
      }
      dirty = false;
      if (data.data && data.data.status === 'submitted') {
        submitted = true;
        setStatus('Submitted ' + clock());
        // The chair whose submission completes the panel stays to record
        // the decision; everyone else goes back to their teams.
        if (data.data.can_record_outcome) { reloadOnReview(); return; }
        setTimeout(() => { window.location.href = ROUTES.list; }, 900);
        return;
      }
      setStatus('Saved ' + clock());
    })
    .catch(() => {
      setStatus('Not saved', true);
      showFormError('Network error. Nothing was saved.');
    }));
}

function saveDraft() { postGrade(ROUTES.save, 'PUT'); }
function submitGrade() { postGrade(ROUTES.submit, 'PATCH'); }

/* ── Abstaining ──────────────────────────────────────────────── */
const ABSTAIN_MIN = 10;

function abstainFetch(method, body) {
  return fetch(ROUTES.abstain, {
    method: method,
    credentials: 'same-origin',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: body ? JSON.stringify(body) : null,
  }).then(res => res.json());
}

function updateAbstainCount() {
  const n = $('abstain-reason').value.trim().length;
  $('abstain-count').textContent = n < ABSTAIN_MIN
    ? (n ? (ABSTAIN_MIN - n) + ' more character' + (ABSTAIN_MIN - n === 1 ? '' : 's') + ' needed' : 'At least ' + ABSTAIN_MIN + ' characters.')
    : n + ' / 1000';
}

function openAbstain() {
  $('abstain-error').hidden = true;
  updateAbstainCount();
  $('abstain-modal').classList.add('show');
  $('abstain-reason').focus();
}

function closeAbstain() { $('abstain-modal').classList.remove('show'); }

function confirmAbstain() {
  const reason = $('abstain-reason').value.trim();
  const error = $('abstain-error');
  const btn = $('abstain-confirm');

  if (reason.length < ABSTAIN_MIN) {
    error.textContent = 'Give the reason you are abstaining - at least ' + ABSTAIN_MIN + ' characters.';
    error.hidden = false;
    $('abstain-reason').focus();
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Abstaining…';

  abstainFetch('POST', { reason: reason })
    .then(res => {
      if (!res.success) {
        error.textContent = res.message || 'Could not record your abstention.';
        error.hidden = false;
        btn.disabled = false;
        btn.textContent = 'Abstain';
        return;
      }
      dirty = false;
      window.location.reload();
    })
    .catch(() => {
      error.textContent = 'Could not reach the server.';
      error.hidden = false;
      btn.disabled = false;
      btn.textContent = 'Abstain';
    });
}

function withdrawAbstention() {
  const btn = $('gs-withdraw');
  btn.disabled = true;

  abstainFetch('DELETE')
    .then(res => {
      if (!res.success) { showFormError(res.message || 'Could not withdraw.'); btn.disabled = false; return; }
      window.location.reload();
    })
    .catch(() => { showFormError('Could not reach the server.'); btn.disabled = false; });
}

if ($('abstain-reason')) $('abstain-reason').addEventListener('input', updateAbstainCount);

/* ── Grading for the panel ───────────────────────────────────── */
const WHO = {{ Js::from($who) }};

if ($('gs-who-change')) $('gs-who-change').onclick = () => openWhoGrades(WHO);

// A chip takes a panelist out of the shared grade, or puts them back.
document.querySelectorAll('#gs-shared-chips button[data-id]').forEach(chip => {
  chip.addEventListener('click', () => {
    const id = +chip.dataset.id;
    const joined = WHO.joined.filter(x => x !== id);
    if (chip.getAttribute('aria-pressed') !== 'true') joined.push(id);
    if (dirty) { showFormError('Save your draft first, then change who you grade for.'); return; }
    chip.disabled = true;
    fetch(WHO.url, {
      method: 'PUT',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ grader_id: joined.length ? WHO.me : null, joined: joined }),
    })
      .then(r => r.json())
      .then(res => {
        if (!res.success) { showFormError(res.message || 'Could not change who you grade for.'); chip.disabled = false; return; }
        dirty = false;
        window.location.reload();
      })
      .catch(() => { showFormError('Could not reach the server.'); chip.disabled = false; });
  });
});

/* Mobile sidebar toggle */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  if (!sidebarEl) return;
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open'); sidebarOverlay.classList.add('open');
  document.body.classList.add('sidebar-open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open'); sidebarOverlay.classList.remove('open');
  document.body.classList.remove('sidebar-open');
  document.body.style.overflow = '';
}
</script>
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
