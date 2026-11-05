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
  <p>Last updated: November 5, 2026</p>

  <p>CapsTRACK is the capstone project tracking system of the CSPC College of Computer Studies, used by students, advisers, panel members and Capstone Subject Advisers (CaPSA). This page explains what personal information CapsTRACK collects, why, who can see it, and your rights under the Data Privacy Act of 2012 (Republic Act No. 10173).</p>

  <h2>Information we collect</h2>
  <ul>
    <li><strong>Google account details:</strong> your name, email address, and profile picture, used to identify you and sign you in.</li>
    <li><strong>Google Calendar (advisers, panel members and CaPSA only):</strong> access is limited to calendar events. CapsTRACK adds consultation bookings and defense schedules to your calendar, updates them when they are rescheduled, and removes them when they are cancelled. Students are not asked for Calendar access.</li>
    <li><strong>Google Drive (advisers, panel members and CaPSA only):</strong> access is limited to files CapsTRACK itself creates or that you choose to open with it, used to store capstone documents. CapsTRACK cannot see any other files in your Drive. Students are not asked for Drive access.</li>
    <li><strong>School records:</strong> student number, name, course, section, year level and school year, from the section list your CaPSA uploads, plus your field of expertise if you are an adviser or panel member.</li>
    <li><strong>Capstone work:</strong> team details, titles, submitted documents and diagrams, comments, defense schedules, grades, evaluations and team ratings.</li>
    <li><strong>Consultations:</strong> the bookings you make or accept, their topics and notes.</li>
    <li><strong>Payments:</strong> receipt images, receipt numbers and amounts you submit for capstone fees.</li>
    <li><strong>Usage records:</strong> an activity log of actions taken in CapsTRACK, and your sign-in session (IP address and browser), kept for security. If you turn on notifications, CapsTRACK also stores your browser's notification subscription.</li>
  </ul>

  <h2>How we use it</h2>
  <p>This information is used only to run CapsTRACK: signing you in, tracking capstone progress, checking proposed titles for duplicates, scheduling consultations and defenses, recording grades and payments, and sending notifications by email or browser about your project. It is not sold, not used for advertising, and not shared with anyone outside the college's capstone program.</p>

  <h2>Who can see it</h2>
  <p>Students see their own team's records. Advisers and panel members see the teams assigned to them. The CaPSA and the college's capstone administrators see the records of the sections they handle.</p>

  <h2>Where it is kept</h2>
  <p>Records and uploaded files are stored on servers run for the college. Title duplication checks are done by an AI model hosted by the school itself, so your documents are not sent to an outside AI service. Google keeps the Calendar events and Drive files described above in your own Google account.</p>

  <h2>How long it is kept</h2>
  <p>Records are kept while your capstone is in progress and afterwards as part of the college's capstone archive, so finished projects can be checked against new titles. You can ask for your records to be removed as described below.</p>

  <h2>Security</h2>
  <p>CapsTRACK only lets CSPC accounts approved by the college sign in, ends idle sessions automatically, and limits each page to the roles that need it.</p>

  <h2>Your rights</h2>
  <p>Under the Data Privacy Act you have the right to be informed, to access your personal information, to have errors corrected, to object to its processing, to have it erased or blocked, and to file a complaint with the National Privacy Commission.</p>

  <h2>Google user data</h2>
  <p>CapsTRACK's use of information received from Google APIs follows the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>

  <h2>Removing your data</h2>
  <p>You can remove CapsTRACK's access to your Google account at any time from <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">your Google account permissions</a>. To see, correct or delete your CapsTRACK records, contact your CaPSA or the CSPC Data Protection Officer.</p>

  <p style="margin-top: 32px;"><a href="{{ route('login') }}">Back to sign in</a></p>
</main>
@endsection
