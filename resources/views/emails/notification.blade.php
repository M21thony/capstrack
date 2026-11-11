<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $headline }}</title>
</head>
{{-- Styles are inline: several mail apps drop a <style> block. --}}
<body style="margin:0;padding:24px 12px;background:#f2f3f5;font-family:'DM Sans','DM Sans Fallback',Arial,sans-serif;color:#1e2128;font-size:14px;line-height:1.55;">
    <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e0e2e8;border-radius:10px;">
        <div style="background:#18191f;color:#ffffff;padding:14px 24px;font-weight:700;font-size:16px;border-bottom:3px solid #c9a82c;border-radius:10px 10px 0 0;">CapsTrack</div>

        <div style="padding:22px 24px;">
            @if ($poster)
                <div style="font-size:12px;color:#656b7a;margin-bottom:6px;">From {{ $poster }}, CaPSA/TSA</div>
            @endif
            <h1 style="font-size:20px;line-height:1.3;margin:0 0 14px;color:#1e2128;">{{ $headline }}</h1>
            <p style="margin:0 0 12px;">Hi {{ $recipientName }},</p>
            @if ($body)
                <p style="margin:0 0 12px;white-space:pre-line;">{{ $body }}</p>
            @endif

            @if ($url)
                <a href="{{ $url }}" style="display:inline-block;background:#c9a82c;color:#18191f;font-weight:700;padding:10px 18px;border-radius:8px;margin:6px 0 4px;text-decoration:none;">Open in CapsTrack</a>
            @endif
        </div>

        <div style="border-top:1px solid #e0e2e8;padding:14px 24px;font-size:12px;color:#656b7a;background:#f7f8fa;border-radius:0 0 10px 10px;">
            @if ($offUrl)
                <a href="{{ $offUrl }}" style="color:#1e2128;">Turn off announcement emails</a><br>
            @else
                Choose which emails you get under Notifications in your profile.<br>
            @endif
            CSPC · College of Computer Studies
        </div>
    </div>
</body>
</html>
