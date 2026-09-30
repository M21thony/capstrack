<x-dashboard.charts />
<style>
    @keyframes shimmer { 0% { background-position: -400px 0; } 100% { background-position: calc(400px + 100%) 0; } }
    .skeleton-box { background: linear-gradient(90deg, var(--bg2) 25%, var(--bg) 50%, var(--bg2) 75%); background-size: 400px 100%; animation: shimmer 1.6s ease-in-out infinite; border-radius: 6px; }

    .stat-sub { font-size:11px;color:var(--text3);margin-top:2px; }
    a.stat-chip { text-decoration:none; transition:transform 0.15s,box-shadow 0.15s; cursor:pointer; }
    a.stat-chip:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,0.08); }
    a.stat-chip:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }

    .dash-grid-v3 { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:20px; margin-top:20px; }

    @media (max-width:768px) {
        .dash-grid-v3 { gap:12px; margin-top:12px; }
    }
</style>
