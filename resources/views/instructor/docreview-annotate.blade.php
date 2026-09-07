<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>CapsTRACK — Annotate: {{ $review->document->file_name ?? 'Document' }}</title>
@vite(['resources/css/app.css', 'resources/js/main.jsx'])
</head>
<body>
    <div id="pdf-annotator-root"></div>
@include('partials.escape-closes-modals')
</body>
</html>