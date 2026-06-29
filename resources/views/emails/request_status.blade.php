<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CapsTRACK Account Status</title>
    <style>
        body { font-family: 'Roboto', Arial, sans-serif; color: #3c4043; line-height: 1.5; padding: 20px; }
        .email-container { max-width: 600px; margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #dadce0; padding-bottom: 20px; }
        .logo { font-weight: bold; font-size: 20px; color: #1a73e8; }
        .user-email { font-size: 14px; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 20px; }
        .approved { background: #dcfce7; color: #166534; }
        .rejected { background: #fee2e2; color: #991b1b; }
        h1 { font-size: 24px; color: #202124; margin-top: 20px; }
        .btn { background: #1a73e8; color: #ffffff !important; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; display: inline-block; margin: 20px 0; }
        .footer { margin-top: 40px; border-top: 1px solid #dadce0; padding-top: 20px; font-size: 12px; color: #70757a; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">CapsTrack</div>
            <div class="user-email">{{ $expert['email'] }}</div>
        </div>

        @if($status === 'approved')
            <div class="status-badge approved">Account Approved</div>
            <h1>Welcome aboard, {{ $expert['first_name'] }}!</h1>
            <p>Your expert account has been verified. You may now start guiding and evaluating capstone teams.</p>
            <a href="{{ url('/instructor/dashboard') }}" class="btn">Go to Dashboard</a>
        @else
            <div class="status-badge rejected">Application Update</div>
            <h1>Hello, {{ $expert['first_name'] }}</h1>
            <p>We've reviewed your account request. Unfortunately, we cannot approve it at this time.</p>
            @if(isset($reason) && $reason)
                <p><strong>Reason:</strong> {{ $reason }}</p>
            @endif
        @endif

        <p>Thank you for using CapsTrack services!</p>

        <div class="footer">
            <div class="logo" style="margin-bottom:10px;">CapsTrack</div>
            © 2026 CSPC - College of Computer Studies <br>
            You have received this email to update you about the status of your CapsTrack account request.
        </div>
    </div>
</body>
</html>