<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Consultation Slot Available</title>
    <style>
        body { font-family: 'Roboto', Arial, sans-serif; color: #3c4043; line-height: 1.5; padding: 20px; }
        .email-container { max-width: 600px; margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #dadce0; padding-bottom: 20px; }
        .logo { font-weight: bold; font-size: 20px; color: #1a73e8; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 20px; background: #dbeafe; color: #1d4ed8; }
        h1 { font-size: 22px; color: #202124; margin-top: 0; }
        .details { background: #f8f9fa; border-radius: 8px; padding: 16px 20px; margin: 20px 0; }
        .details div { padding: 4px 0; font-size: 14px; }
        .details strong { color: #202124; }
        .btn { background: #1a73e8; color: #ffffff !important; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; display: inline-block; margin: 10px 0; }
        .footer { margin-top: 40px; border-top: 1px solid #dadce0; padding-top: 20px; font-size: 12px; color: #70757a; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">CapsTrack</div>
        </div>

        <div class="badge">New Slot Available</div>
        <h1>A new consultation slot is open</h1>
        <p>{{ trim($expert->first_name.' '.$expert->last_name) }} opened up a new consultation slot for <strong>{{ $team->team_name }}</strong>.</p>

        <div class="details">
            <div><strong>Type:</strong> {{ ucfirst($slot->slot_type ?? 'Consultation') }}</div>
            <div><strong>When:</strong> {{ $slot->recurrenceDescription() }}</div>
            <div><strong>Time:</strong> {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }} – {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}</div>
        </div>

        <a href="{{ url('/student/consultation') }}" class="btn">Book This Slot</a>

        <div class="footer">
            <div class="logo" style="margin-bottom:10px;">CapsTrack</div>
            © 2026 CSPC - College of Computer Studies <br>
            You're receiving this because you're a member of {{ $team->team_name }}.
        </div>
    </div>
</body>
</html>
