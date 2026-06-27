<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CapsTRACK Account Status</title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8fafc; margin: 0; padding: 40px 20px; }
        .container { max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 20px; padding: 40px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); border: 1px solid #f1f5f9; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #0f172a; font-size: 28px; margin: 0; letter-spacing: -0.5px; }
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 20px; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        h2 { font-size: 18px; color: #1e293b; margin-bottom: 12px; }
        p { font-size: 14px; line-height: 1.6; color: #64748b; margin-bottom: 20px; }
        .reason-box { background: #fdf2f2; border-radius: 12px; padding: 16px; color: #991b1b; font-size: 13px; margin-bottom: 25px; }
        .btn { background: #0f172a; color: #ffffff; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block; }
        .footer { text-align: center; margin-top: 30px; font-size: 11px; color: #94a3b8; }
        .dev-sig { margin-top: 40px; text-align: center; font-size: 9px; color: #cbd5e1; letter-spacing: 1px; text-transform: uppercase; opacity: 0.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header"><h1>CapsTrack</h1></div>

        @if($status === 'approved')
            <div class="status-badge status-approved">Account Approved</div>
            <h2>Welcome aboard, {{ $expert['first_name'] }}!</h2>
            <p>Your expert account has been verified. You may now start guiding capstone teams.</p>
            <div style="text-align: center;">
                <a href="{{ url('/instructor/dashboard') }}" class="btn">Go to Dashboard</a>
            </div>
        @else
            <div class="status-badge status-rejected">Application Update</div>
            <h2>Hello, {{ $expert['first_name'] }}</h2>
            <p>We've reviewed your account request. Unfortunately, we cannot approve it at this time.</p>
            @if(isset($reason) && $reason)
                <div class="reason-box">
                    <strong>Reason:</strong> {{ $reason }}
                </div>
            @endif
        @endif

        <div class="footer">
            &copy; {{ date('Y') }} CapsTrack. All rights reserved.<br>
            CSPC - CCS Department
        </div>

        <div class="dev-sig">
            Crafted by balilla • aurillas • rabusa • tumambing • llorin
        </div>
    </div>
</body>
</html>