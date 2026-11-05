@extends('layouts.guest')

@section('title', 'Privacy Policy - CapsTRACK')

@php
    $sections = [
        'collect' => 'Information We Collect',
        'use' => 'How We Use It',
        'see' => 'Who Can See It',
        'kept' => 'Where It Is Kept',
        'how-long' => 'How Long It Is Kept',
        'security' => 'Security',
        'rights' => 'Your Rights',
        'google' => 'Google User Data',
        'removing' => 'Removing Your Data',
    ];
@endphp

@section('content')
<div class="min-h-dvh bg-[#0f0f0f] px-4 py-8 text-[#b8b8b2] sm:px-6 sm:py-12">
    <div class="mx-auto flex max-w-[1080px] items-start gap-8">
        <aside class="sticky top-12 hidden w-[230px] flex-none flex-col rounded-2xl border border-white/10 bg-[#161616] px-3 py-6 md:flex">
            <img src="{{ asset('images/brand/capstrack-logo-ui.svg') }}" alt="CapsTrack" class="mx-3 mb-6 block h-7 w-auto self-start"/>
            <nav class="flex flex-col gap-0.5" aria-label="Sections">
                @foreach ($sections as $id => $title)
                    <a href="#{{ $id }}" class="flex min-h-9 items-center gap-2.5 rounded-lg px-3 text-[13px] font-medium text-[#b8b8b2] no-underline transition-colors duration-150 hover:bg-white/5 hover:text-white"><span class="w-4 text-[12px] font-bold text-gold">{{ $loop->iteration }}</span>{{ $title }}</a>
                @endforeach
            </nav>
            <a href="{{ route('login') }}" class="mx-3 mt-6 inline-flex min-h-11 items-center justify-center rounded-xl bg-gold px-4 text-[13px] font-bold text-navy no-underline transition-colors duration-150 hover:bg-gold-bright">Back to sign in</a>
        </aside>

        <main class="min-w-0 flex-1 overflow-hidden rounded-2xl border border-gold/25 bg-[#161616]">
            <div class="flex items-center gap-4 border-b border-gold/40 px-5 py-6 sm:px-8">
                <span class="flex size-12 flex-none items-center justify-center rounded-xl bg-gold/15 text-gold"><svg viewBox="0 0 24 24" class="size-7 fill-none stroke-current stroke-2 [stroke-linecap:round] [stroke-linejoin:round]" aria-hidden="true"><path d="M12 3l7 3v6c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6l7-3z"/><rect x="9" y="11" width="6" height="5" rx="1"/><path d="M10 11V9.5a2 2 0 0 1 4 0V11"/></svg></span>
                <div>
                    <h1 class="m-0 text-[24px] font-bold text-white sm:text-[28px]">Privacy Policy</h1>
                    <p class="m-0 mt-0.5 text-[13px]">Last updated: <span class="font-semibold text-gold">November 5, 2026</span></p>
                </div>
            </div>

            <div class="space-y-8 px-5 py-7 text-[14px] leading-relaxed sm:px-8 [&_a]:text-gold [&_strong]:text-white">
                <p class="mt-0">CapsTRACK is the capstone project tracking system of the CSPC College of Computer Studies, used by students, advisers, panel members and Capstone Subject Advisers (CaPSA). This page explains what personal information CapsTRACK collects, why, who can see it, and your rights under the Data Privacy Act of 2012 (Republic Act No. 10173).</p>

                @foreach ($sections as $id => $title)
                    <section id="{{ $id }}" class="flex scroll-mt-8 gap-4">
                        <span class="flex size-7 flex-none items-center justify-center rounded-full bg-gold text-[13px] font-bold text-navy">{{ $loop->iteration }}</span>
                        <div class="min-w-0">
                            <h2 class="m-0 mb-2 text-[17px] font-bold text-white">{{ $title }}</h2>
                            @switch($id)
                                @case('collect')
                                    <ul class="m-0 list-disc space-y-2 pl-5">
                                        <li><strong>Google account details:</strong> your name, email address, and profile picture, used to identify you and sign you in.</li>
                                        <li><strong>Google Calendar (advisers, panel members and CaPSA only):</strong> access is limited to calendar events. CapsTRACK adds consultation bookings and defense schedules to your calendar, updates them when they are rescheduled, and removes them when they are cancelled. Students are not asked for Calendar access.</li>
                                        <li><strong>Google Drive (advisers, panel members and CaPSA only):</strong> access is limited to files CapsTRACK itself creates or that you choose to open with it, used to store capstone documents. CapsTRACK cannot see any other files in your Drive. Students are not asked for Drive access.</li>
                                        <li><strong>School records:</strong> student number, name, course, section, year level and school year, from the section list your CaPSA uploads, plus your field of expertise if you are an adviser or panel member.</li>
                                        <li><strong>Capstone work:</strong> team details, titles, submitted documents and diagrams, comments, defense schedules, grades, evaluations and team ratings.</li>
                                        <li><strong>Consultations:</strong> the bookings you make or accept, their topics and notes.</li>
                                        <li><strong>Payments:</strong> receipt images, receipt numbers and amounts you submit for capstone fees.</li>
                                        <li><strong>Usage records:</strong> an activity log of actions taken in CapsTRACK, and your sign-in session (IP address and browser), kept for security. If you turn on notifications, CapsTRACK also stores your browser's notification subscription.</li>
                                    </ul>
                                    @break
                                @case('use')
                                    <p class="m-0">This information is used only to run CapsTRACK: signing you in, tracking capstone progress, checking proposed titles for duplicates, scheduling consultations and defenses, recording grades and payments, and sending notifications by email or browser about your project. It is not sold, not used for advertising, and not shared with anyone outside the college's capstone program.</p>
                                    @break
                                @case('see')
                                    <p class="m-0">Students see their own team's records. Advisers and panel members see the teams assigned to them. The CaPSA and the college's capstone administrators see the records of the sections they handle.</p>
                                    @break
                                @case('kept')
                                    <p class="m-0">Records and uploaded files are stored on servers run for the college. Title duplication checks are done by an AI model hosted by the school itself, so your documents are not sent to an outside AI service. Google keeps the Calendar events and Drive files described above in your own Google account.</p>
                                    @break
                                @case('how-long')
                                    <p class="m-0">Records are kept while your capstone is in progress and afterwards as part of the college's capstone archive, so finished projects can be checked against new titles. You can ask for your records to be removed as described below.</p>
                                    @break
                                @case('security')
                                    <p class="m-0">CapsTRACK only lets CSPC accounts approved by the college sign in, ends idle sessions automatically, and limits each page to the roles that need it.</p>
                                    @break
                                @case('rights')
                                    <p class="m-0 mb-2">Under the Data Privacy Act you have the right to:</p>
                                    <ul class="m-0 list-disc space-y-1 pl-5">
                                        <li>be informed, and access your personal information;</li>
                                        <li>have errors corrected;</li>
                                        <li>object to its processing;</li>
                                        <li>have it erased or blocked;</li>
                                        <li>file a complaint with the National Privacy Commission.</li>
                                    </ul>
                                    @break
                                @case('google')
                                    <p class="m-0">CapsTRACK's use of information received from Google APIs follows the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>
                                    @break
                                @case('removing')
                                    <p class="m-0">You can remove CapsTRACK's access to your Google account at any time from <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">your Google account permissions</a>. To see, correct or delete your CapsTRACK records, contact your CaPSA or the CSPC Data Protection Officer.</p>
                                    @break
                            @endswitch
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="border-t border-white/10 px-5 py-4 md:hidden">
                <a href="{{ route('login') }}" class="flex min-h-11 w-full items-center justify-center rounded-xl bg-gold px-4 text-[14px] font-bold text-navy no-underline">Back to sign in</a>
            </div>
        </main>
    </div>
</div>
@endsection
