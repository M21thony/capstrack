@php
    $team = $schedule->team;
    $start = $schedule->schedule_date;
    $end = $schedule->end_date ?? $start?->copy()->addHours(2);
    $panel = $schedule->panels->sortBy(fn ($p) => $p->panel_role === 'Panel Chair' ? 0 : 1);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $rescheduled ? 'Defense Rescheduled' : 'Defense Scheduled' }}</title>
    <style>
        body { font-family: 'Roboto', Arial, sans-serif; color: #3c4043; line-height: 1.5; padding: 20px; }
        .email-container { max-width: 600px; margin: auto; }
        .header { margin-bottom: 30px; border-bottom: 1px solid #dadce0; padding-bottom: 20px; }
        .logo { font-weight: bold; font-size: 20px; color: #1a73e8; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 20px; background: {{ $rescheduled ? '#fef3c7' : '#dcfce7' }}; color: {{ $rescheduled ? '#92400e' : '#166534' }}; }
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
        <div class="header"><div class="logo">CapsTrack</div></div>

        <div class="badge">{{ $rescheduled ? 'Defense Rescheduled' : 'Defense Scheduled' }}</div>
        <h1>
            @if ($forPanelist)
                You are on the panel for {{ $team?->team_name }}'s {{ $schedule->defense_type }}
            @else
                Your {{ $schedule->defense_type }} {{ $rescheduled ? 'has a new date' : 'is scheduled' }}
            @endif
        </h1>

        <div class="details">
            <div><strong>Team:</strong> {{ $team?->team_name }}</div>
            @if ($team?->project_title)
                <div><strong>Title:</strong> {{ $team->project_title }}</div>
            @endif
            <div><strong>Defense:</strong> {{ $schedule->defense_type }}@if ((int) $schedule->attempt > 1) (redefense) @endif</div>
            <div><strong>Date:</strong> {{ $start?->format('F j, Y (l)') }}</div>
            <div><strong>Time:</strong> {{ $start?->format('g:i A') }} – {{ $end?->format('g:i A') }}</div>
            <div><strong>Venue:</strong> {{ $schedule->venue }}</div>
            @foreach ($panel as $member)
                <div><strong>{{ $member->panel_role }}:</strong> {{ $member->expert?->name }}</div>
            @endforeach
        </div>

        <a href="{{ url($forPanelist ? '/instructor/grading' : '/student/dashboard') }}" class="btn">
            {{ $forPanelist ? 'Open Team Grading' : 'View on CapsTrack' }}
        </a>

        <div class="footer">
            <div class="logo" style="margin-bottom:10px;">CapsTrack</div>
            © 2026 CSPC - College of Computer Studies <br>
            You're receiving this because you're {{ $forPanelist ? 'on this defense panel' : 'a member of '.$team?->team_name }}.
        </div>
    </div>
</body>
</html>
