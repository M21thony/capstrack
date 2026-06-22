<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CapsTRACK Account Status</title>
    <style>
        body {
            font-family: 'DM Sans', Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        .header {
            background-color: #0f1117;
            padding: 32px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 24px;
            margin: 0;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .content {
            padding: 40px 32px;
            color: #374151;
        }
        .content h2 {
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 16px;
            font-weight: 600;
            color: #111827;
        }
        .content p {
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
        }
        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .reason-box {
            background-color: #f9fafb;
            border-left: 4px solid #ef4444;
            padding: 16px;
            margin-bottom: 24px;
            border-radius: 0 8px 8px 0;
        }
        .reason-box strong {
            color: #991b1b;
            font-size: 13px;
            text-transform: uppercase;
            display: block;
            margin-bottom: 4px;
        }
        .reason-box p {
            margin: 0;
            font-size: 14.5px;
            color: #4b5563;
        }
        .btn {
            display: inline-block;
            background-color: #3b82f6;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            margin-bottom: 24px;
        }
        .footer {
            background-color: #f9fafb;
            padding: 24px 32px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CapsTRACK</h1>
        </div>
        <div class="content">
            <h2>Hello, {{ $expert->first_name }} {{ $expert->last_name }}</h2>

            @if($expert->approved_status)
                <div class="status-badge status-approved">Approved</div>
                <p>We are pleased to inform you that your request for a CapsTRACK instructor account has been <strong>approved</strong> by the administrator.</p>
                <p>You now have full access to the instructor features, where you can oversee capstone projects, manage team assignments, and evaluate submissions.</p>
                
                <div style="text-align: center;">
                    <a href="{{ route('login') }}" class="btn" style="color: #ffffff;">Access Your Account</a>
                </div>
            @else
                <div class="status-badge status-rejected">Rejected</div>
                <p>Your account request has been rejected.</p>
                @if(isset($reason) && $reason)
                    <div class="reason-box">
                        <strong>Reason:</strong>
                        <p>{{ $reason }}</p>
                    </div>
                @endif
                <p>Please review the feedback and consider reapplying if appropriate.</p>
            @endif

            <p>Best regards,<br>The CapsTRACK Team</p>
        </div>
        <div class="footer">
            <p>This is an automated notification. Please do not reply directly to this email.</p>
            <p>&copy; {{ date('Y') }} CCS Department, Camarines Sur Polytechnic Colleges</p>
        </div>
    </div>
</body>
</html>
