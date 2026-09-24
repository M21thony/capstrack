{{--
  How the duplication check looks: the verdict band across the top, the flat
  match rows, and the two pages a row or a saved title opens into.

  It is here rather than in the stylesheet because the stylesheet only
  reaches the browser after a build, and these three pages are read as one.
--}}
<style>
/* ── the trail back, on both of the new pages ───────────────────── */
.dup-crumb{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--text3);flex-wrap:wrap;margin-bottom:14px}
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

/* One line only: 65% and above is highly similar. Below it the checker says
   nothing, because recommending or rejecting a title is the CaPSA's call. */
.stateline{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:700;padding:5px 11px;border-radius:7px}
/* Its display above would otherwise beat [hidden]: the red "Highly similar"
   showed before any document had been checked. */
.stateline[hidden]{display:none}
.s-red{background:var(--red-bg);color:var(--red)}
.s-grey{background:var(--bg4);color:var(--text2)}
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

/* ── the match page: the two documents side by side ─────────────── */
.vs{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media (max-width:860px){.vs{grid-template-columns:1fr}}
.vs .col{border:1px solid var(--border);border-radius:12px;background:var(--bg2);padding:14px 16px}
.vs .col.mine{border-color:rgba(201,168,44,.5);background:#fffdf6}
.vs .who{font-size:10px;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:var(--text3);margin-bottom:7px}
.vs .col.mine .who{color:var(--accent3)}
.vs h3{font-size:14px;font-weight:600;line-height:1.4;letter-spacing:-.15px;color:var(--text);margin:0}
.vs .sub{font-size:11.5px;color:var(--text2);margin-top:4px;padding-bottom:10px;border-bottom:1px solid var(--border)}

.field{margin-top:11px}
.field .k{font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--text3);margin-bottom:3px}
.field .v{font-size:12.5px;line-height:1.6;color:var(--text2);white-space:pre-line}
.teamfacts .field:first-child{margin-top:0}

/* Words that appear in both documents, matched literally rather than by
   the AI. */
.vs mark,.field mark{background:rgba(201,168,44,.34);color:var(--text);font-weight:600;border-radius:3px;padding:0 2px}

/* ── similarities · differences · what to do ────────────────────── */
.grid3{display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:12px;margin-top:14px}
.box{border:1px solid var(--border);border-radius:9px;padding:10px 12px;background:var(--bg2)}
.box h3{font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--text3);margin:0 0 5px;display:flex;align-items:center;gap:6px}
.box h3 svg{width:12px;height:12px;fill:none;stroke-width:2.4}
.box p{font-size:12.5px;color:var(--text2);line-height:1.55;margin:0}
.box-sim h3{color:var(--red)} .box-sim h3 svg{stroke:var(--red)}
.box-dif h3{color:var(--green)} .box-dif h3 svg{stroke:var(--green)}
.box-sug{background:var(--accent-light);border-color:rgba(201,168,44,.42)}
.box-sug h3{color:var(--accent3)} .box-sug h3 svg{stroke:var(--accent3)}
.box-sug p{color:var(--text)}

/* ── the report page: the team's words beside what came back ────── */
.capsa2{display:grid;grid-template-columns:480px minmax(0,1fr);gap:14px;align-items:stretch}
@media (max-width:900px){.capsa2{grid-template-columns:1fr}}
.capsa2>.card{display:flex;flex-direction:column;min-width:0;max-height:min(560px,max(300px,calc(100vh - 340px)));padding:0}
.capsa2>.card>.card-header{flex-shrink:0;margin:0;padding:14px 24px;border-bottom:1px solid var(--border)}
.capsa2 .scrolls{padding:16px 24px}
.capsa2 .scrolls.dup-rows{padding:0;margin:0}
/* The team's own words carry the gold edge, so the eye lands there first. */
.hilite{border-color:rgba(201,168,44,.6);box-shadow:0 0 0 3px rgba(201,168,44,.13)}
.hilite>.card-header{background:var(--accent-light);border-bottom-color:rgba(201,168,44,.45);border-radius:13px 13px 0 0}
.hilite>.card-header .card-title{color:var(--accent3)}

.scrolls{overflow-y:auto;flex:1;min-height:0;scrollbar-width:thin;scrollbar-color:var(--border2) transparent;overscroll-behavior:contain}
.scrolls::-webkit-scrollbar{width:11px}
.scrolls::-webkit-scrollbar-track{background:transparent}
.scrolls::-webkit-scrollbar-thumb{background:var(--border2);border-radius:99px;border:3px solid var(--bg2);background-clip:padding-box}
.scrolls::-webkit-scrollbar-thumb:hover{background:var(--text3);border-color:var(--bg2);background-clip:padding-box}

.dup-none{font-size:12.5px;color:var(--text3);text-align:center;padding:28px 16px}

/* ── suggested experts ──────────────────────────────────────────── */
/* Moved here out of the pre-proposal page: the report page shows the same
   list, and one copy beats two. */
.sx-role{font-size:9px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;
    color:var(--accent3,#8a6e10);margin-bottom:2px}
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
</style>
