@extends('layouts.guest')

@section('title', 'Mock Google Account Selector')

@section('content')
<style>
    body {
        margin: 0;
        padding: 0;
        background-color: #0f1115;
        font-family: 'DM Sans', sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;min-height: 100dvh;
        color: #fff;
    }
    .mock-card {
        background-color: #17191e;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        width: 100%;
        max-width: 440px;
        padding: 40px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        text-align: center;
    }
    .google-logo {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-bottom: 24px;
        font-size: 22px;
        font-weight: 600;
    }
    .google-logo svg {
        width: 32px;
        height: 32px;
    }
    .mock-badge {
        display: inline-block;
        background-color: rgba(181, 148, 31, 0.1);
        border: 1px dashed #b5941f;
        color: #b5941f;
        font-size: 11px;
        font-variant-numeric:tabular-nums;
        padding: 4px 12px;
        border-radius: 20px;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .mock-title {
        font-size: 20px;
        font-weight: 500;
        margin-bottom: 8px;
        color: #fff;
    }
    .mock-sub {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 30px;
        line-height: 1.5;
    }
    .form-group {
        text-align: left;
        margin-bottom: 20px;
    }
    label {
        font-size: 12px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.7);
        display: block;
        margin-bottom: 6px;
    }
    input {
        width: 100%;
        background-color: #21242d;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 12px 14px;
        font-size: 14px;
        color: #fff;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    input:focus {
        border-color: #b5941f;
        box-shadow: 0 0 0 3px rgba(181, 148, 31, 0.15);
    }
    .btn-submit {
        background-color: #b5941f;
        color: #0f1115;
        border: none;
        border-radius: 8px;
        padding: 12px;
        width: 100%;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
        margin-top: 10px;
    }
    .btn-submit:hover {
        background-color: #9e811a;
    }
    .error-msg {
        background-color: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.2);
        color: #ef4444;
        font-size: 12.5px;
        padding: 10px 14px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: left;
        line-height: 1.4;
    }
    .test-credentials {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        font-size: 11px;
        color: rgba(255, 255, 255, 0.4);
        text-align: left;
    }
    .test-credentials code {
        background-color: rgba(255, 255, 255, 0.05);
        padding: 2px 5px;
        border-radius: 4px;
        color: #b5941f;
        cursor: pointer;
    }
</style>

<div class="mock-card">
    <div class="google-logo">
        <svg width="24" height="24" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        <span>Google Account Simulator</span>
    </div>

    <div class="mock-badge">Local Sandbox Environment</div>

    <div class="mock-title">Choose a Mock Account</div>
    <div class="mock-sub">Enter account details to simulate a Google authentication response. Only institutional emails (@my.cspc.edu.ph) will be allowed.</div>

    @if ($errors->any())
        <div class="error-msg">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login.google.mock.post') }}">
        @csrf
        <input type="hidden" name="state" value="{{ $state }}">

        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" placeholder="e.g. Juan Dela Cruz" value="{{ old('name', 'Juan Dela Cruz') }}" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="username@my.cspc.edu.ph" value="{{ old('email') }}" required>
        </div>

        <button type="submit" class="btn-submit">Continue to CapsTrack</button>
    </form>

    <div class="test-credentials">
        <strong>Quick Test Presets:</strong><br>
        <span style="display:block; margin-top:8px;">
            Valid domain: <code onclick="fillPreset('Juan Dela Cruz', 'juan.delacruz@my.cspc.edu.ph')">juan.delacruz@my.cspc.edu.ph</code>
        </span>
        <span style="display:block; margin-top:4px;">
            Invalid domain: <code onclick="fillPreset('Guest User', 'guest@gmail.com')">guest@gmail.com</code>
        </span>
    </div>
</div>

<script>
    function fillPreset(name, email) {
        document.getElementById('name').value = name;
        document.getElementById('email').value = email;
    }
</script>
@endsection
