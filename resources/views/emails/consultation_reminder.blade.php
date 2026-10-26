<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Consultation Reminder</title>
    <style>
        body { font-family: 'Roboto', Arial, sans-serif; color: #3c4043; line-height: 1.5; padding: 20px; }
        .email-container { max-width: 600px; margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #dadce0; padding-bottom: 20px; }
        .logo { font-weight: bold; font-size: 20px; color: #1a73e8; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 20px; background: #fef3c7; color: #92400e; }
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

        <div class="badge">Today</div>
        <h1>You have a consultation today</h1>
        <p>This is a reminder that {{ $team->team_name }} has a consultation with {{ trim($expert->first_name.' '.$expert->last_name) }} today.</p>

        <div class="details">
            <div><strong>Type:</strong> {{ ucfirst($booking->schedule->slot_type ?? 'Consultation') }}</div>
            {{-- The slot row can be gone while the booking is still here: the tables
                 are MyISAM, so the ON DELETE CASCADE on schedule_id never runs. The
                 consultation page already prints a dash for that; the mail crashed. --}}
            <div><strong>Time:</strong> {{ $booking->schedule?->start_time ? \Carbon\Carbon::parse($booking->schedule->start_time)->format('h:i A') : '—' }} – {{ $booking->schedule?->end_time ? \Carbon\Carbon::parse($booking->schedule->end_time)->format('h:i A') : '—' }}</div>
            <div><strong>Team:</strong> {{ $team->team_name }}</div>
            @if ($booking->deliverables)
            <div><strong>Deliverables:</strong> {{ $booking->deliverablesLabel() }}</div>
            @endif
            @if ($booking->topics)
            <div><strong>About:</strong> {{ $booking->topicsLabel() }}</div>
            @endif
            @if ($booking->note)
            <div><strong>Their note:</strong> {{ $booking->note }}</div>
            @endif
        </div>

        <a href="{{ url('/student/consultation') }}" class="btn">View Details</a>

        <div class="footer">
            <div class="logo" style="margin-bottom:10px;">CapsTrack</div>
            © 2026 CSPC - College of Computer Studies <br>
            You're receiving this because you're a member of {{ $team->team_name }} or the assigned expert.
        </div>
    </div>
@include('partials.escape-closes-modals')
</body>
</html>
