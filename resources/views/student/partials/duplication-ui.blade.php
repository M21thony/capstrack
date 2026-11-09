{{--
  How the duplication check looks: the verdict band across the top, the flat
  match rows, and the two pages a row or a saved title opens into.

  It is here rather than in the stylesheet because the stylesheet only
  reaches the browser after a build, and these three pages are read as one.
--}}
<style nonce="{{ Vite::cspNonce() }}">
/* ── the trail back, on both of the new pages ───────────────────── */
.dup-crumb{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--text3);flex-wrap:wrap;margin-bottom:10px}
.dup-crumb a{color:var(--text2);text-decoration:none;border-bottom:1px solid transparent}
.dup-crumb a:hover{color:var(--accent3);border-bottom-color:var(--accent)}
.dup-crumb a:focus-visible{outline:2px solid var(--accent3);outline-offset:2px;border-radius:3px}
.dup-crumb svg{width:12px;height:12px;stroke:var(--text3);fill:none;stroke-width:2.2;flex-shrink:0}
.dup-crumb .here{color:var(--text);font-weight:600}

/* ── the verdict band ───────────────────────────────────────────── */
.card.verdict{display:flex;align-items:center;gap:18px;flex-wrap:wrap;padding:16px 18px;margin-bottom:14px}
.bigscore{font-variant-numeric:tabular-nums;font-size:42px;font-weight:700;letter-spacing:-1.5px;line-height:1}
.verdict .doc{flex:1;min-width:230px}
.verdict .doc .t{font-size:15px;font-weight:600;letter-spacing:-.2px;line-height:1.35;color:var(--text)}
.verdict .doc .m{font-size:11.5px;color:var(--text3);margin-top:3px}

.scalebar{display:flex;height:7px;border-radius:4px;overflow:hidden;background:var(--bg4);min-width:200px;flex:1}
.scalebar i{display:block;height:100%}
.scalekeys{display:flex;gap:14px;font-size:10.5px;color:var(--text3);margin-top:6px;flex-wrap:wrap}
.scalekeys b{font-weight:600;color:var(--text2)}
.key-dot{display:inline-block;width:8px;height:8px;border-radius:2px;margin-right:5px;vertical-align:-1px}

/* ── one match per row, nothing folded away ─────────────────────── */
/* Full bleed: the card pads itself by 24px and a row's hover should reach
   the card's own edge. */
.dup-rows{margin-left:-24px;margin-right:-24px}
.mrow{display:grid;grid-template-columns:58px minmax(0,1fr);gap:11px;padding:11px 20px;border-top:1px solid var(--border);cursor:pointer;transition:background .15s;text-decoration:none;color:inherit}
.mrow:first-child{border-top:none}
.mrow:hover{background:var(--bg3)}
.mrow:focus-visible{outline:2px solid var(--accent3);outline-offset:-2px}
.mrow .pc{font-variant-numeric:tabular-nums;font-weight:700;font-size:15.5px;line-height:1.1;letter-spacing:-.4px}
.mrow .bar{display:block;height:4px;border-radius:3px;background:var(--bg4);overflow:hidden;margin-top:5px}
.mrow .bar i{display:block;height:100%}
.mrow .rk{font-size:10px;color:var(--text3);margin-top:5px;font-variant-numeric:tabular-nums}
.mrow .t{font-size:12.5px;font-weight:600;line-height:1.4;letter-spacing:-.1px;color:var(--text)}
.mrow .mt{font-size:11px;color:var(--text3);margin-top:3px;line-height:1.45}
.mrow .wy{font-size:11.5px;color:var(--text2);line-height:1.5;margin-top:5px}

/* ── Back / Previous / Next ─────────────────────────────────────── */
.pager{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.pbtn{display:inline-flex;align-items:center;gap:7px;font:inherit;font-size:12.5px;font-weight:600;color:var(--text2);background:var(--bg2);border:1px solid var(--border);border-radius:9px;padding:8px 13px;cursor:pointer;text-decoration:none}
.pbtn:hover{border-color:var(--border2);color:var(--text)}
.pbtn:focus-visible{outline:2px solid var(--accent3);outline-offset:2px}
.pbtn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.4;flex-shrink:0}
.pbtn.off{opacity:.45;cursor:not-allowed;pointer-events:none}

/* ── the link on a saved title ──────────────────────────────────── */
.golink{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--accent3);text-decoration:none;border-bottom:1px solid rgba(201,168,44,.5);padding-bottom:1px}
.golink:hover{color:var(--text);border-bottom-color:var(--text)}
.golink:focus-visible{outline:2px solid var(--accent3);outline-offset:3px;border-radius:2px}
.golink svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.4}

/* ── the match page: header, then the two documents side by side ── */
.sm-pos{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text3)}
.pbtn.sm-ic{padding:8px 9px}
.card.sm-head{display:flex;align-items:center;gap:18px;flex-wrap:wrap;padding:14px 20px;margin-bottom:14px}
.sm-score{text-align:center;min-width:72px}
.sm-score .bigscore{font-size:34px}
.sm-score small{display:block;font-size:11px;color:var(--text3);margin-top:4px}
.sm-doc{flex:1;min-width:230px}
.sm-title{margin:0;font-size:16px;font-weight:700;line-height:1.35;color:var(--text)}
.sm-meta{font-size:12px;color:var(--text3);margin-top:5px}
.card.sm-card{padding:0;display:flex;flex-direction:column;min-width:0}
.sm-card>.pc-tabs{padding-top:6px}
.sm-panel[hidden]{display:none}
.sm-why{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));border-bottom:1px solid var(--border)}
.sm-why>div{display:flex;gap:10px;padding:12px 18px}
.sm-why svg{width:16px;height:16px;flex-shrink:0;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;margin-top:1px}
.sm-why b{display:block;font-size:12.5px;font-weight:700;color:var(--text)}
.sm-why p{margin:3px 0 0;font-size:12.5px;line-height:1.5;color:var(--text2)}
.sm-vs{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);min-height:0}
.sm-col{display:flex;flex-direction:column;min-width:0;min-height:0}
.sm-col+.sm-col{border-left:1px solid var(--border)}
.sm-colh{padding:14px 18px 12px;border-bottom:1px solid var(--border)}
.sm-who{font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text3)}
.sm-colh h3{margin:5px 0 0;font-size:13.5px;font-weight:700;line-height:1.4;color:var(--text)}
.sm-by{font-size:11.5px;color:var(--text3);margin-top:4px}
.sm-colb{padding:0 18px 16px}
.sm-colb .field{margin-top:14px}
.sm-colb .field .v{font-size:13px;line-height:1.7}
.sm-how{padding:14px 18px}
@media (min-width:1101px) and (min-height:680px){
  .content:has(> .sm-card){display:flex;flex-direction:column;overflow:hidden}
  .content:has(> .sm-card)>*{flex-shrink:0}
  .content:has(> .sm-card) .sys-credits{display:none}
  .content>.sm-card{flex:1 1 0;min-height:0}
  .sm-panel{flex:1;min-height:0;display:flex;flex-direction:column}
  .sm-vs{flex:1}
  .sm-colb,.sm-how{flex:1;min-height:0;overflow-y:auto}
}
@media (max-width:1100px),(max-height:679px){
  .sm-colb{max-height:420px;overflow-y:auto}
}
@media (max-width:860px){
  .sm-vs{grid-template-columns:minmax(0,1fr)}
  .sm-col+.sm-col{border-left:0;border-top:1px solid var(--border)}
}

.field{margin-top:11px}
.field .k{font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--text3);margin-bottom:3px}
.field .v{font-size:12.5px;line-height:1.6;color:var(--text2);white-space:pre-line}
.teamfacts .field:first-child{margin-top:0}

/* Words that appear in both documents, matched literally rather than by
   the AI. */
.sm-col mark,.field mark{background:rgba(201,168,44,.34);color:var(--text);font-weight:600;border-radius:3px;padding:0 2px}

/* Titles other sections had recommended this year. */
.ty-tag{display:inline-block;padding:1px 7px;border-radius:999px;background:var(--red,#c62828);color:#fff;font-size:10px;font-weight:600;margin-right:4px;letter-spacing:0;text-transform:none}
.sm-who .ty-tag{vertical-align:1px}
.ty-note{display:flex;gap:7px;align-items:flex-start;margin-top:5px;font-size:12px;line-height:1.45;color:var(--red,#c62828)}
.ty-note svg{width:14px;height:14px;flex-shrink:0;margin-top:2px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}
.ty-note b{font-weight:600}

/* ── how the score is worked out ─────────────────────────────────── */
.how ol.steps{list-style:none;margin:0;padding:0;display:grid;gap:8px}
.how .steps li{display:grid;grid-template-columns:22px minmax(0,1fr) auto;gap:10px;align-items:start}
.how .steps li.skip{opacity:.6}
.how .no{width:22px;height:22px;border-radius:50%;border:1px solid var(--border);background:var(--bg2);display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text2)}
.how .steps b{font-size:12.5px;font-weight:600;color:var(--text)}
.how .steps p{font-size:12.5px;color:var(--text2);line-height:1.55;margin:2px 0 0}
.how .val{font-variant-numeric:tabular-nums;font-weight:700;font-size:14px;color:var(--text)}
.how code{font-size:11.5px;background:var(--bg2);padding:1px 4px;border-radius:4px}
.how .refs{margin:10px 0 0;padding:8px 0 0 18px;border-top:1px solid var(--border);font-size:11.5px;color:var(--text3);line-height:1.5}

/* ── the report page: the team's words beside what came back ────── */
.capsa2{display:grid;grid-template-columns:480px minmax(0,1fr);gap:14px;align-items:stretch}
@media (max-width:900px){.capsa2{grid-template-columns:1fr}}
.capsa2>.card{display:flex;flex-direction:column;min-width:0;max-height:min(560px,max(300px,calc(100vh - 340px)));max-height:min(560px,max(300px,calc(100dvh - 340px)));padding:0}
.capsa2>.card>.card-header{flex-shrink:0;margin:0;padding:14px 24px;border-bottom:1px solid var(--border)}
.capsa2 .scrolls{padding:16px 24px}
.capsa2 .scrolls.dup-rows{padding:0;margin:0}
/* The team's own words carry the gold edge, so the eye lands there first. */
.hilite{border-color:rgba(201,168,44,.6);box-shadow:0 0 0 3px rgba(201,168,44,.13)}
.hilite>.card-header{background:var(--accent-light);border-bottom-color:rgba(201,168,44,.45);border-radius:13px 13px 0 0}
.hilite>.card-header .card-title{color:var(--accent3)}

.scrolls{overflow-y:auto;flex:1;min-height:0;overscroll-behavior:contain}

.dup-none{font-size:12.5px;color:var(--text3);text-align:center;padding:28px 16px}

/* ── suggested experts ──────────────────────────────────────────── */
/* Moved here out of the pre-proposal page: the report page shows the same
   list, and one copy beats two. */
.sx-role{font-size:9px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;
    color:var(--accent3,#8a6e10);margin-bottom:2px}
.sx-leave{display:inline-block;margin-left:7px;padding:1px 7px;border-radius:999px;font-size:10px;font-weight:700;
    vertical-align:1px;background:rgba(180,83,9,.09);color:#b45309;border:1px solid rgba(180,83,9,.18)}
  .instructor-item {
  display: flex; gap: 10px; align-items: flex-start;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
}
.instructor-item:last-child { border-bottom: none; }

/* Apply button on a suggested instructor. Locked until the pre-proposal
   this report belongs to has been approved. */
.dup-apply-btn { display:inline-flex; align-items:center; gap:5px; margin-left:auto; flex-shrink:0; padding:5px 11px; border-radius:6px; border:1.5px solid var(--accent,#c9a82c); background:var(--accent,#c9a82c); color:#18191f; font-family:inherit; font-size:11px; font-weight:700; text-decoration:none; cursor:pointer; transition:opacity .13s; }
.dup-apply-btn:hover { opacity:0.86; }
.dup-apply-btn.locked { background:transparent; border-color:var(--border); color:var(--text3); cursor:not-allowed; }
.dup-apply-btn.locked:hover { opacity:1; }
.dup-apply-note { font-size:10px; color:var(--text3); margin-top:8px; }

/* Tooltip explaining WHY Apply is locked. It sits on a wrapper because a
   disabled button emits no hover events of its own. */
.dup-apply-wrap { position:relative; margin-left:auto; flex-shrink:0; display:inline-flex; outline:none; }
.dup-apply-wrap .dup-apply-btn { margin-left:0; }
.dup-apply-wrap::after {
  content: attr(data-tip);
  position:absolute; bottom:calc(100% + 8px); right:0; z-index:40;
  width:max-content; max-width:230px; padding:7px 10px; border-radius:6px;
  background:var(--text, #1e2128); color:#fff; font-size:11px; font-weight:500;
  line-height:1.45; text-align:left; white-space:normal;
  box-shadow:0 6px 18px -4px rgba(0,0,0,0.35);
  opacity:0; visibility:hidden; transform:translateY(3px);
  transition:opacity .13s ease, transform .13s ease, visibility .13s;
  pointer-events:none;
}
.dup-apply-wrap::before {
  content:''; position:absolute; bottom:calc(100% + 3px); right:14px; z-index:41;
  border:5px solid transparent; border-top-color:var(--text, #1e2128);
  opacity:0; visibility:hidden; transition:opacity .13s ease, visibility .13s;
  pointer-events:none;
}
.dup-apply-wrap:hover::after, .dup-apply-wrap:focus-visible::after,
.dup-apply-wrap:hover::before, .dup-apply-wrap:focus-visible::before {
  opacity:1; visibility:visible; transform:translateY(0);
}
@media (prefers-reduced-motion: reduce) {
  .dup-apply-wrap::after, .dup-apply-wrap::before { transition:none; }
}

.avatar {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--primary-light, #e8e8fc);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 600;
  color: var(--primary, #5b5bd6); flex-shrink: 0;
}

/* ── the report page, fitted to one screen ──────────────────────── */
/* The team's words beside what came back and the suggested experts
   underneath, with nothing scrolling inside anything: a laptop screen shows
   it whole, a shorter one scrolls the page once. The percentage keeps its
   colour; the words beside it stay calm (App\Support\SimilarityBand). */
.sim-close{color:var(--red)}
.sim-some{color:#b45309}
.sim-little{color:var(--green)}
.sim-none{color:var(--text3)}
.band{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:11.5px;font-weight:600;white-space:nowrap}
.band-close{background:#fdf3e2;color:#8a5300}
.band-some{background:#eef1f6;color:#3d4a63}
.band-little,.band-none{background:var(--bg4);color:var(--text2)}

/* The check page: header, the document and submission on the left, the results table on the right. */
.pc-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:14px}
.pc-h1{margin:0;font-size:21px;font-weight:700;letter-spacing:-.3px;color:var(--text)}
.pc-sub{font-size:12.5px;color:var(--text3);margin-top:3px}
.pc-head-btns{display:flex;gap:8px;flex-wrap:wrap}
.pc-n{display:inline-grid;place-items:center;min-width:20px;height:18px;padding:0 6px;border-radius:999px;background:var(--bg4);color:var(--text2);font-size:11px;font-weight:700;font-variant-numeric:tabular-nums;box-sizing:border-box}
.pc-grid{display:grid;grid-template-columns:300px minmax(0,1fr);gap:14px;align-items:start}
.pc-left{display:flex;flex-direction:column;gap:14px;min-height:0}
.pc-card{padding:16px 18px}
.pc-card-h{display:flex;align-items:center;gap:8px;margin:0 0 12px;font-size:13.5px;font-weight:700;color:var(--text)}
.pc-card-h svg{width:15px;height:15px;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.pc-file{display:flex;align-items:center;gap:10px;padding:9px 11px;border:1px solid var(--border);border-radius:10px;background:var(--bg3);cursor:pointer;margin-bottom:8px}
.pc-file:hover{border-color:var(--border2)}
.pc-file.has-file{border-color:var(--accent,#c9a82c);background:#fffbeb}
.pc-file-ic{width:30px;height:30px;border-radius:8px;display:grid;place-items:center;background:#fdecec;flex-shrink:0}
.pc-file-ic svg{width:15px;height:15px;fill:none;stroke:#c62828;stroke-width:2.2}
.pc-file-t{min-width:0;display:flex;flex-direction:column}
.pc-file-t b{font-size:12.5px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pc-file-t small{font-size:10.5px;color:var(--text3)}
.pc-btn{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:7px;font:inherit;font-size:12.5px;font-weight:600;padding:8px 12px;border-radius:9px;border:1px solid var(--border);background:#fff;color:var(--text);cursor:pointer;margin-bottom:8px}
.pc-btn:hover{border-color:var(--border2)}
.pc-btn svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2.3}
.pc-btn.pc-gold{background:var(--accent,#c9a82c);border-color:var(--accent,#c9a82c);color:#18191f}
.pc-btn.pc-gold:hover{filter:brightness(.96)}
.pc-btn:disabled{opacity:.55;cursor:not-allowed}
.pc-btn:focus-visible{outline:2px solid var(--accent3);outline-offset:2px}
.pc-locked{font-size:11.5px;color:var(--text2);line-height:1.5;padding:8px 10px;border-radius:8px;background:var(--bg3);margin-bottom:8px}
.pc-latest{border-top:1px solid var(--border);padding-top:12px;margin-top:4px}
.pc-k{font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text3);margin-bottom:6px}
.pc-empty{font-size:12px;color:var(--text3)}
.pc-score{display:flex;align-items:center;gap:9px}
.pc-score .bigscore{font-size:30px}
.pc-x{margin-left:auto;width:26px;height:26px;display:grid;place-items:center;border:1px solid var(--border);border-radius:7px;background:#fff;color:var(--text3);cursor:pointer}
.pc-x:hover{color:var(--red);border-color:var(--red)}
.pc-x svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2.3}
.pc-doc{font-size:12.5px;font-weight:600;color:var(--text);margin-top:8px;line-height:1.4}
.pc-meta{font-size:11.5px;color:var(--text3);margin-top:2px;line-height:1.45}
#sc-result .golink{margin-top:9px}
.pc-linkbtn{background:none;border-top:0;border-left:0;border-right:0;font-family:inherit;cursor:pointer;margin-top:10px}

.pc-results{display:flex;flex-direction:column;padding:16px 0 0;min-height:420px}
.pc-results>.pc-card-h{padding:0 18px}
.pc-tabs{display:flex;gap:18px;padding:0 18px;border-bottom:1px solid var(--border)}
.pc-tabs button{display:inline-flex;align-items:center;gap:7px;font:inherit;font-size:12.5px;font-weight:600;color:var(--text3);background:none;border:0;border-bottom:2px solid transparent;padding:8px 0 9px;margin-bottom:-1px;cursor:pointer}
.pc-tabs button:hover{color:var(--text)}
.pc-tabs button[aria-selected="true"]{color:var(--text);border-bottom-color:var(--accent,#c9a82c)}
.pc-tabs button[aria-selected="true"] .pc-n{background:#fdf3e2;color:#8a5300}
.pc-tabs button:focus-visible{outline:2px solid var(--accent3);outline-offset:2px}
.pc-thead,.pc-row{display:grid;grid-template-columns:minmax(0,1fr) 92px 96px;gap:12px;align-items:center;padding:0 18px}
.pc-thead{font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text3);padding-top:9px;padding-bottom:9px;border-bottom:1px solid var(--border);background:var(--bg3)}
.pc-thead span:not(:first-child),.pc-row>:not(:first-child){justify-self:center}
.pc-rows{flex:1 1 0;min-height:0;overflow-y:auto}
.pc-row{padding-top:10px;padding-bottom:10px;border-top:1px solid var(--border);text-decoration:none;color:inherit;transition:background .15s}
.pc-row:first-child{border-top:0}
.pc-row:hover{background:var(--bg3)}
.pc-row:focus-visible{outline:2px solid var(--accent3);outline-offset:-2px}
.pc-what{min-width:0}
.pc-what b{display:block;font-size:12.5px;font-weight:600;line-height:1.4;color:var(--text)}
.pc-what small{display:block;font-size:11px;color:var(--text3);margin-top:2px}
.pc-what .wy{color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pc-cmp{display:inline-flex;align-items:center;font-size:12px;font-weight:600;color:var(--text);border:1px solid var(--border);border-radius:8px;padding:5px 12px;background:#fff}
.pc-row:hover .pc-cmp{border-color:var(--accent,#c9a82c)}
.pc-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 18px;border-top:1px solid var(--border);font-size:12px;color:var(--text3)}
.pc-dlg{margin:auto}
.pc-dlg .rp-dialog-b{padding:0}
.pc-dlg #instructor-list{padding:14px 18px}

/* One screen on a laptop: the results scroll inside their card, the page itself never does. */
@media (min-width:1101px) and (min-height:680px){
  .content:has(> .pc-grid){display:flex;flex-direction:column;overflow:hidden}
  .content:has(> .pc-grid)>*{flex-shrink:0}
  .content>.pc-grid{flex:1 1 0;min-height:0;align-items:stretch}
  .content>.pc-grid>*{min-height:0}
  .content>.pc-grid .pc-left{overflow-y:auto}
  .content:has(> .pc-grid) .sys-credits{display:none}
}
@media (max-width:1100px),(max-height:679px){
  .pc-results{max-height:640px}
}
@media (max-width:860px){
  .pc-grid{grid-template-columns:minmax(0,1fr)}
}
@media (max-width:560px){
  .pc-thead,.pc-row{grid-template-columns:minmax(0,1fr) 64px;padding-left:14px;padding-right:14px}
  .pc-thead span:last-child,.pc-row .pc-cmp{display:none}
}

/* ── the report page: the team's words beside the results table ─── */
.card.rp-head{display:flex;align-items:center;gap:16px;padding:14px 18px;margin-bottom:14px;flex-wrap:wrap}
.rp-doc{flex:1;min-width:240px}
.rp-title{margin:0;font-size:15.5px;font-weight:700;line-height:1.35;color:var(--text);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.rp-meta{display:flex;align-items:center;flex-wrap:wrap;gap:4px 10px;margin-top:6px;font-size:12px;color:var(--text3)}
.rp-doc .ty-note{margin-top:6px}
.rp-acts{display:flex;gap:8px;flex-shrink:0}
.rp-acts .pc-btn{width:auto;margin:0;padding:8px 16px}
.rp-grid{display:grid;grid-template-columns:330px minmax(0,1fr);gap:14px;align-items:start}
.rp-mine{padding:16px 0 0;display:flex;flex-direction:column;min-width:0}
.rp-mine>.pc-card-h{padding:0 18px 12px;margin:0;border-bottom:1px solid var(--border)}
.rp-body{padding:14px 18px;display:flex;flex-direction:column;gap:12px}
.rp-v-title{font-size:13.5px;font-weight:600;line-height:1.4;color:var(--text)}
.rp-field .k{font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text3);margin-bottom:4px}
.rp-v{font-size:12.5px;line-height:1.6;color:var(--text2);white-space:pre-line}
.rp-sdgs{display:flex;flex-wrap:wrap;gap:5px}
.rp-sdgs span{padding:2px 9px;border-radius:6px;font-size:11.5px;font-weight:500;background:#eef1fb;color:#33449a}
.rp-foot{padding:12px 18px;border-top:1px solid var(--border)}
.rp-foot .pc-btn{margin:0}
.pc-what small.rp-sec{color:var(--red,#c62828);font-weight:600}
.rp-sx{display:flex;flex-direction:column;gap:10px;padding:14px 18px}
.rp-sx .dup-apply-note{margin:0}
@media (min-width:1101px) and (min-height:680px){
  .content:has(> .rp-grid){display:flex;flex-direction:column;overflow:hidden}
  .content:has(> .rp-grid)>*{flex-shrink:0}
  .content:has(> .rp-grid) .sys-credits{display:none}
  .content>.rp-grid{flex:1 1 0;min-height:0;align-items:stretch}
  .content>.rp-grid>*{min-height:0}
  .rp-body{flex:1;min-height:0;overflow-y:auto}
}
@media (max-width:1100px),(max-height:679px){
  .rp-body{max-height:420px;overflow-y:auto}
}
@media (max-width:860px){
  .rp-grid{grid-template-columns:minmax(0,1fr)}
}
.dup-apply-btn.rp-lock{padding:6px 8px}
.rp-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.rp-expert{display:flex;gap:12px;align-items:flex-start;padding:10px 14px;border:1px solid var(--border);border-radius:12px;min-width:0}
.rp-expert-who{flex:1;min-width:0}
/* Shown whole: a name, field or reason cut off with "…" left the team
   guessing who the expert is and why they were suggested. */
.rp-expert-who .n{font-size:13.5px;font-weight:600;color:var(--text);line-height:1.35}
.rp-expert-who .x{font-size:11.5px;color:var(--primary,#3f55b5);margin-top:2px;line-height:1.45}
.rp-expert-who .r{font-size:11.5px;color:var(--text3);margin-top:3px;line-height:1.45}

.rp-dialog{margin:auto;border:0;border-radius:16px;padding:0;width:min(820px,calc(100vw - 32px));max-height:min(86vh,900px);max-height:min(86dvh,900px);box-shadow:0 24px 60px rgba(0,0,0,.28)}
.rp-dialog::backdrop{background:rgba(15,16,22,.55)}
.rp-dialog-h{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:18px 22px 14px;border-bottom:1px solid var(--border);position:sticky;top:0;background:#fff}
.rp-dialog-h .k{font-size:10.5px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--text3)}
.rp-dialog-h h2{margin:3px 0 0;font-size:16px;line-height:1.4;color:var(--text)}
.rp-x{width:34px;height:34px;flex-shrink:0;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:#fff;color:var(--text3);cursor:pointer}
.rp-x svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.2}
.rp-dialog-b{padding:16px 24px 24px}
.rf-facts{display:grid;gap:12px;padding:14px 16px;border-radius:12px;background:#f6f7fb;border:1px solid var(--border);margin-bottom:6px}
.rf-k{font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text3);margin-bottom:6px}
.rf-tags{display:flex;flex-wrap:wrap;gap:6px}
.rf-tags span{padding:4px 10px;border-radius:7px;font-size:12.5px;font-weight:500;line-height:1.35;background:#fff;border:1px solid #dfe3f0;color:#2d3a7a}
.rf-sec{padding:16px 0 0}
.rf-sec+.rf-sec{border-top:1px solid var(--border);margin-top:16px}
.rf-h{display:flex;align-items:center;gap:8px;margin:0 0 7px;font-size:13.5px;font-weight:700;color:var(--text)}
.rf-h::before{content:"";width:4px;height:15px;border-radius:2px;background:#c9a82c}
.rf-p{margin:0;font-size:14px;line-height:1.7;color:#2b2f3a;white-space:pre-line}
.rf-list{margin:0;padding-left:20px;font-size:14px;line-height:1.7;color:#2b2f3a}
.rf-list li+li{margin-top:4px}
.ty-note[hidden]{display:none}
</style>
