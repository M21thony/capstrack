<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $headline }}</title>
    <style>
        body { font-family: 'Roboto', Arial, sans-serif; color: #3c4043; line-height: 1.5; padding: 20px; }
        .email-container { max-width: 600px; margin: auto; }
        .header { margin-bottom: 30px; border-bottom: 1px solid #dadce0; padding-bottom: 20px; }
        .logo { font-weight: bold; font-size: 20px; color: #1a73e8; }
        h1 { font-size: 22px; color: #202124; margin-top: 0; }
        .btn { background: #1a73e8; color: #ffffff !important; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; display: inline-block; margin: 10px 0; }
        .footer { margin-top: 40px; border-top: 1px solid #dadce0; padding-top: 20px; font-size: 12px; color: #70757a; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header"><div class="logo">CapsTrack</div></div>

        <h1>{{ $headline }}</h1>
        <p>Hi {{ $recipientName }},</p>
        @if ($body)
            <p>{{ $body }}</p>
        @endif

        @if ($url)
            <a href="{{ $url }}" class="btn">Open in CapsTrack</a>
        @endif

        <div class="footer">
            <div class="logo" style="margin-bottom:10px;">CapsTrack</div>
            © 2026 CSPC - College of Computer Studies <br>
            This is a copy of a notification in your CapsTrack bell. You can choose which of these
            emails you get under Notifications in your profile settings.
        </div>
    </div>
</body>
</html>
