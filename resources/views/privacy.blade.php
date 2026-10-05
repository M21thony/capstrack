@extends('layouts.guest')

@section('title', 'Privacy Policy - CapsTRACK')

@section('content')
<style nonce="{{ Vite::cspNonce() }}">
  body { font-family: 'DM Sans','DM Sans Fallback', sans-serif; background: #0f1115; color: #f0f2f7; margin: 0; padding: 40px 16px; }
  .privacy { max-width: 720px; margin: 0 auto; line-height: 1.6; }
  .privacy h1 { font-size: 28px; margin-bottom: 4px; }
  .privacy h2 { font-size: 18px; margin: 28px 0 8px; color: #b5941f; }
  .privacy p, .privacy li { color: #9ba3b5; }
  .privacy ul { padding-left: 20px; }
  .privacy a { color: #b5941f; }
</style>

<main class="privacy">
  <h1>Privacy Policy</h1>
  <p>Last updated: September 23, 2026</p>

  <p>CapsTRACK is the capstone project tracking system used by students, advisers, and panel members. This page explains what information CapsTRACK collects when you sign in with Google and how it is used.</p>

  <h2>Information we collect</h2>
  <ul>
    <li><strong>Google account details:</strong> your name, email address, and profile picture, used to identify you and sign you in.</li>
    <li><strong>Google Calendar:</strong> used to add and update consultation and defense schedules on your calendar.</li>
    <li><strong>Google Drive:</strong> access is limited to files CapsTRACK itself creates or that you choose to open with it, used to store capstone documents. CapsTRACK cannot see any other files in your Drive.</li>
    <li><strong>Information you enter:</strong> team details, titles, submissions, comments, and schedules you add inside CapsTRACK.</li>
  </ul>

  <h2>How we use it</h2>
  <p>This information is used only to run CapsTRACK: signing you in, tracking capstone progress, scheduling consultations and defenses, and sending notifications about your project. It is not sold, not used for advertising, and not shared with anyone outside the school's capstone program.</p>

  <h2>Google user data</h2>
  <p>CapsTRACK's use of information received from Google APIs follows the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>

  <h2>Removing your data</h2>
  <p>You can remove CapsTRACK's access to your Google account at any time from <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">your Google account permissions</a>. To have your CapsTRACK records deleted, contact your capstone coordinator.</p>

  <p style="margin-top: 32px;"><a href="{{ route('login') }}">Back to sign in</a></p>
</main>
@endsection
