<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Report expired - CapsTRACK</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<style nonce="{{ Vite::cspNonce() }}">
body{margin:0;min-height:100vh;min-height:100dvh;display:grid;place-items:center;background:#f4f5f8;font-family:'DM Sans','DM Sans Fallback',sans-serif;color:#18191f}
.box{max-width:420px;padding:32px;text-align:center;background:#fff;border:1px solid #e2e4ea;border-radius:14px}
h1{margin:0 0 8px;font-size:20px}
p{margin:0 0 20px;color:#5c5f6b;line-height:1.5}
a{display:inline-block;padding:10px 18px;border-radius:8px;background:#c9a82c;color:#18191f;font-weight:600;text-decoration:none}
</style>
</head>
<body>
<div class="box">
  <h1>This report has expired</h1>
  <p>A report is kept for a few hours after you open it from a page. Go back to the page and open View Report again.</p>
  <a href="{{ $back ?? url('/') }}">Go back to the page</a>
</div>
</body>
</html>
