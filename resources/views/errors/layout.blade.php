{{--
  The shell every error page shares.

  These pages are reached when something has already gone wrong, so they lean on
  nothing: no Vite bundle, no layout inheritance beyond this file, no database and
  no authenticated user. Only public/css/admin.css, which is a static asset, and
  the same DM Sans link the rest of the app uses. A 500 caused by a failing
  container must not turn into a second 500 rendering its own error page.

  Sections: code, title, heading, body, tone (red|amber|blue|neutral), icon,
  extra (optional block under the body), actions.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>@yield('title') - CapsTRACK</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
  .err-page{min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;background:var(--bg)}
  .err-bar{height:54px;flex-shrink:0;background:var(--navy-dark);display:flex;align-items:center;gap:12px;padding:0 24px}
  .err-bar-mark{width:22px;height:22px;border-radius:7px;background:var(--accent)}
  .err-bar-name{font-size:14px;font-weight:700;color:var(--navy-text);letter-spacing:-0.2px}
  .err-bar-code{margin-left:auto;font-size:12px;font-weight:700;color:var(--navy-text2);font-variant-numeric:tabular-nums;letter-spacing:.5px}
  .err-main{flex:1;display:flex;align-items:center;justify-content:center;padding:32px 20px}
  .err-card{width:100%;max-width:520px;background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:32px;display:flex;flex-direction:column;gap:14px}
  .err-icon{width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .err-icon svg{width:22px;height:22px;fill:none;stroke-width:2;stroke-linecap:round}
  .err-icon-red{background:#fdeaea}.err-icon-red svg{stroke:#b91c1c}
  .err-icon-amber{background:#fdf5ec}.err-icon-amber svg{stroke:#92400e}
  .err-icon-blue{background:#eef2fd}.err-icon-blue svg{stroke:#1d4ed8}
  .err-icon-neutral{background:var(--bg4)}.err-icon-neutral svg{stroke:var(--text2)}
  .err-title{font-size:21px;font-weight:700;color:var(--text);line-height:1.3;margin:0}
  .err-body{font-size:14px;color:var(--text2);line-height:1.6;margin:0}
  .err-note{font-size:13px;color:var(--text2);line-height:1.55;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:12px 14px}
  .err-ref{font-size:13px;font-weight:700;color:var(--text);background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:10px 12px;font-variant-numeric:tabular-nums;letter-spacing:.5px}
  .err-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px}
  .err-actions .btn{min-height:44px;justify-content:center}
  .err-btn-2{background:var(--bg3);color:var(--text);border:1px solid var(--border);font-weight:600}
  .err-btn-2:hover{background:var(--bg4)}
  @media (max-width:520px){
    .err-card{padding:24px}
    .err-title{font-size:19px}
    .err-actions{flex-direction:column}
    .err-actions .btn,.err-actions .err-btn-2{width:100%}
  }
</style>
</head>
<body>
<div class="err-page">
  <div class="err-bar">
    <div class="err-bar-mark"></div>
    <div class="err-bar-name">CapsTRACK</div>
    <div class="err-bar-code">@yield('code')</div>
  </div>
  <main class="err-main">
    <div class="err-card" role="alert">
      <div class="err-icon err-icon-@yield('tone', 'neutral')" aria-hidden="true">@yield('icon')</div>
      <h1 class="err-title">@yield('heading')</h1>
      <p class="err-body">@yield('body')</p>
      @hasSection('extra')
        @yield('extra')
      @endif
      <div class="err-actions">@yield('actions')</div>
    </div>
  </main>
</div>
</body>
</html>
