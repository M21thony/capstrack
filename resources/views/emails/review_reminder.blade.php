<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Documents waiting for your review</title>
    <style>
        body { font-family: 'Roboto', Arial, sans-serif; color: #3c4043; line-height: 1.5; padding: 20px; }
        .email-container { max-width: 600px; margin: auto; }
        .header { margin-bottom: 30px; border-bottom: 1px solid #dadce0; padding-bottom: 20px; }
        .logo { font-weight: bold; font-size: 20px; color: #1a73e8; }
        h1 { font-size: 22px; color: #202124; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        th { text-align: left; font-size: 12px; color: #70757a; text-transform: uppercase; padding: 6px 8px; border-bottom: 1px solid #dadce0; }
        td { padding: 8px; border-bottom: 1px solid #f1f3f4; vertical-align: top; }
        .overdue { color: #b42318; font-weight: bold; }
        .btn { background: #1a73e8; color: #ffffff !important; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; display: inline-block; margin: 10px 0; }
        .footer { margin-top: 40px; border-top: 1px solid #dadce0; padding-top: 20px; font-size: 12px; color: #70757a; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header"><div class="logo">CapsTrack</div></div>

        <h1>{{ $expert->name }}, {{ $waiting->count() }} {{ $waiting->count() === 1 ? 'document is' : 'documents are' }} waiting for your review</h1>
        <p>Students need these reviewed to move forward on their checklist.</p>

        <table>
            <tr><th>Team</th><th>Requirement</th><th>Needed by</th></tr>
            @foreach ($waiting as $document)
                @php $due = $document->due_on ? \Carbon\Carbon::parse($document->due_on) : null; @endphp
                <tr>
                    <td>{{ $document->team_name }}</td>
                    <td>{{ $document->title }}</td>
                    <td class="{{ $due && $due->endOfDay()->isPast() ? 'overdue' : '' }}">
                        {{ $due ? $due->format('M j, Y') : 'No date set' }}{{ $due && $due->endOfDay()->isPast() ? ' (overdue)' : '' }}
                    </td>
                </tr>
            @endforeach
        </table>

        <a href="{{ route('instructor.teams', ['tab' => 'todo']) }}" class="btn">Open your to-do list</a>

        <div class="footer">
            <div class="logo" style="margin-bottom:10px;">CapsTrack</div>
            © 2026 CSPC - College of Computer Studies <br>
            You're receiving this once a day while documents are waiting for your review.
        </div>
    </div>
</body>
</html>
