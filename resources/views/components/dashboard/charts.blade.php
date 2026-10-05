{{--
  The look of the dashboard charts (x-dashboard.stack, x-dashboard.bars,
  x-dashboard.ring). CSS only - no chart library - so a chart is a few lines of
  markup and reads the same in light and dark. Included once, from
  x-dashboard.styles.
--}}
<style nonce="{{ Vite::cspNonce() }}">
    .dc-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr)); gap:16px; margin-bottom:16px; }
    .dc-tiles { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:12px; margin-bottom:16px; }
    .dc-grid.wide { grid-template-columns:repeat(auto-fit,minmax(min(100%,460px),1fr)); }
    .dc-sub { font-size:12px; color:var(--text3); margin:-4px 0 12px; line-height:1.45; }
    .dc-lead { font-size:13.5px; color:var(--text2); margin:0 0 12px; }
    .dc-lead b { color:var(--text); }
    .dc-h { font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--text3); margin:0 0 10px; }
    .dc-more { display:inline-block; margin-top:12px; font-size:12.5px; font-weight:600; color:var(--accent3); text-decoration:none; }
    .dc-more:hover { text-decoration:underline; }

    /* One bar split into parts, with what each part is beneath it. */
    .dc-stack { display:flex; height:12px; border-radius:999px; overflow:hidden; background:var(--bg3); }
    .dc-stack i { display:block; height:100%; min-width:0; }
    .dc-legend { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:6px 14px; margin-top:12px; }
    .dc-legend div { display:flex; align-items:center; gap:7px; font-size:12.5px; color:var(--text2); min-width:0; }
    .dc-legend b { margin-left:auto; color:var(--text); font-variant-numeric:tabular-nums; }
    .dc-dot { width:9px; height:9px; border-radius:50%; flex:none; }

    /* A row per thing with a bar sized against the biggest. */
    .dc-bars { display:flex; flex-direction:column; gap:10px; }
    .dc-bar { display:grid; grid-template-columns:minmax(80px,38%) 1fr auto; align-items:center; gap:10px; font-size:13px; }
    .dc-bar span { color:var(--text2); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .dc-bar u { display:block; height:8px; border-radius:999px; background:var(--bg3); position:relative; overflow:hidden; text-decoration:none; }
    .dc-bar u i { position:absolute; inset:0 auto 0 0; border-radius:999px; }
    .dc-bar b { font-variant-numeric:tabular-nums; min-width:22px; text-align:right; }
    .dc-empty { padding:18px 4px; font-size:13px; color:var(--text3); text-align:center; }

    /* A ring with the figure in the middle. */
    .dc-ring-row { display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
    .dc-ring { --p:0; --c:var(--accent); position:relative; width:104px; height:104px; border-radius:50%; flex:none;
        background:conic-gradient(var(--c) calc(var(--p) * 1%), var(--bg3) 0); display:grid; place-items:center; }
    .dc-ring::after { content:''; position:absolute; inset:11px; border-radius:50%; background:var(--bg2); }
    .dc-ring b { position:relative; z-index:1; font-size:22px; font-variant-numeric:tabular-nums; }
    .dc-ring-text { min-width:0; flex:1; }
    .dc-ring-text strong { display:block; font-size:14px; }
    .dc-ring-text small { display:block; color:var(--text3); font-size:12.5px; margin-top:2px; line-height:1.45; }

    /* A stage or a fee: its name, a figure, then its bar. */
    .dc-rows { display:flex; flex-direction:column; gap:16px; }
    .dc-row-head { display:flex; justify-content:space-between; align-items:baseline; gap:10px; margin-bottom:6px; font-size:13.5px; }
    .dc-row-head b { font-weight:600; }
    .dc-row-head span { color:var(--text3); font-size:12.5px; font-variant-numeric:tabular-nums; }
    .dc-row .dc-legend { margin-top:8px; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); }
    .dc-row .dc-empty { padding:4px 0; text-align:left; }

    @media (max-width:480px) {
        .dc-grid, .dc-grid.wide { grid-template-columns:1fr; gap:12px; }
        .dc-tiles { grid-template-columns:1fr 1fr; gap:10px; }
        .dc-bar { grid-template-columns:minmax(70px,42%) 1fr auto; gap:8px; }
        .dc-ring { width:88px; height:88px; }
        .dc-ring b { font-size:19px; }
    }
</style>
