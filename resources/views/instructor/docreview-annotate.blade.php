<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>CapsTRACK — Annotate: {{ $review->document->file_name ?? 'Document' }}</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/main.jsx'])
</head>
<body>
    <div id="pdf-annotator-root" data-context="{{ json_encode($context, JSON_UNESCAPED_UNICODE) }}"></div>
@include('partials.app-dialog')
@include('partials.escape-closes-modals')
</body>
</html>
