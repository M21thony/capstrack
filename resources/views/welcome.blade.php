<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CapsTrack</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Audiowide&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Geist+Pixel&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">

    <!-- Google reCAPTCHA -->
    <!-- Use this site key in the HTML code your site serves to users - 6LcsrwotAAAAAKKcqCrYmohVfKP-bVvID6LjXWTp (reCAPTCHA v3) -->
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />

    <style>
        :root { --primary: #b5941f; --primary-dark: #9e811a; --navy: #0f1115; --navy-light: #17191e; --grey: #06202B; --grey-dark: #0a2936; }
        
        /* ── GOOGLE SIGN IN BUTTON STYLES ── */
        #google-signin-btn:not([disabled]) {
            opacity: 1 !important;
            cursor: pointer !important;
            background: #f3f4f6 !important;
            color: #111827 !important;
            border-color: #9ca3af !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
        }
        #google-signin-btn:not([disabled]):hover {
            background: transparent !important;
            color: #374151 !important;
            border-color: #d1d5db !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08) !important;
            transform: translateY(-1px);
        }
        body { font-family: 'Inter', sans-serif; font-optical-sizing: auto; background: var(--navy); color: #fff; margin: 0; overflow-x: hidden; }
        .hero-section { position: relative; height: 100vh; width: 100%; display: flex; align-items: center; justify-content: center; background-color: var(--navy); overflow: hidden; }
        .hero-bg { position: absolute; inset: 0; background-image: url('{{ asset('images/bg-ccs.jpg') }}'); background-size: cover; background-position: center; opacity: 0.3; z-index: 1; }
        .hero-overlay { position: absolute; inset: 0; background: radial-gradient(circle at center, transparent 20%, var(--navy) 100%); z-index: 2; }
        .hero-content { position: relative; z-index: 10; text-align: center; max-width: 900px; padding: 0 20px; animation: fadeInUp 1s ease-out; }
        .hero-logo { width: 180px; margin: 0 auto 30px; }
        .hero-title { font-family: 'Geist Pixel', sans-serif; font-size: clamp(3rem, 9vw, 4.8rem); font-weight: 600; font-optical-sizing: auto; font-variation-settings: 'ELSH' 5.7; margin-bottom: 20px; letter-spacing: -2px; line-height: 1; }
        .hero-title-img { display: none; width: min(320px, 80%); height: auto; margin: 0 auto 20px; }
        .hero-title span { color: var(--primary); }
        .hero-description { font-size: clamp(1rem, 3vw, 0.8rem); color: rgba(255, 255, 255, 0.7); margin-bottom: 40px; line-height: 1.6; max-width: 700px; margin-left: auto; margin-right: auto; }
        .hero-actions { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        .btn-ct { padding: 14px 32px; border-radius: 12px; font-weight: 600; text-decoration: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-size: 1rem; display: inline-flex; align-items: center; gap: 8px; }
        .btn-ct-primary { background: var(--grey); color: #fff; }
        .btn-ct-primary:hover { background: var(--grey-dark); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(107, 114, 128, 0.2); }
        .btn-ct-outline { background: transparent; color: #fff; border: 1px solid rgba(255, 255, 255, 0.2); }
        .btn-ct-outline:hover { border-color: var(--grey); color: #fff; background: rgba(107, 114, 128, 0.1); transform: translateY(-2px); }
        .nav-ct { position: absolute; top: 0; left: 0; right: 0; padding: 30px; display: flex; justify-content: space-between; align-items: center; z-index: 100; }
        .nav-logo { font-family: 'Inter', sans-serif; font-weight: 700; font-size: 1.5rem; color: #fff; text-decoration: none; letter-spacing: -1px; }
        .nav-logo span { color: var(--primary); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 60px; }
        .feature-card { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); padding: 24px; border-radius: 16px; text-align: left; transition: all 0.3s ease; }
        .feature-card:hover { background: rgba(255, 255, 255, 0.05); border-color: rgba(181, 148, 31, 0.3); }
        .feature-icon { color: var(--primary); margin-bottom: 16px; }
        .feature-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 8px; }
        .feature-desc { font-size: 0.9rem; color: rgba(255, 255, 255, 0.5); line-height: 1.5; }
        /* ── MODAL OVERLAY STYLES ── */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 17, 21, 0.85); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; pointer-events: none; transition: opacity 0.4s ease; }
        .modal-overlay.active { opacity: 1; pointer-events: auto; }
        .modal-card { background: #fff; border: 1px solid #ddd; width: 90%; max-width: 750px; border-radius: 24px; padding: 40px; box-shadow: 0 30px 60px rgba(0,0,0,0.1); transform: translateY(40px); transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); position: relative; max-height: 85vh; overflow-y: auto; color: #333; }
.modal-overlay.active .modal-card { transform: translateY(0); }
.modal-close { position: absolute; top: 24px; right: 24px; background: rgba(0,0,0,0.05); border: 1px solid #ccc; color: #333; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; font-size: 1.2rem; }
.modal-close:hover { background: rgba(0,0,0,0.1); color: #374151; border-color: #374151; }
.modal-header { margin-bottom: 24px; text-align: left; }
.modal-title { font-family: 'Space Mono', monospace; font-size: 1.8rem; font-weight: 700; color: #333; }
.modal-footer { margin-top: 1.5rem; text-align: center; }
.modal-footer .btn-ct-primary { min-width: 120px; }

.modal-body p { margin-bottom: 0.8rem; font-size: 1rem; line-height: 1.5; color: #333; }
.modal-body ul { margin-left: 1.2rem; margin-bottom: 1rem; }
.modal-body li { margin-bottom: 0.5rem; }
.modal-footer { margin-top: 1.5rem; text-align: center; }

        /* ── LOGIN MODAL STYLES ── */
        #login-modal .modal-card { text-align: center; position: relative; }
        .login-logo { width: 140px; height: 60px; margin: 0 auto 22px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .login-logo img { width: 100%; height: auto; }
        .login-heading { text-align: center; margin-bottom: 24px; }
        .login-heading .modal-title { font-family: 'Inter', sans-serif; font-size: 1.55rem; font-weight: 700; color: #111827; letter-spacing: -0.5px; }
        .login-note { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px 16px; text-align: left; margin-bottom: 20px; font-size: 0.82rem; color: #4b5563; line-height: 1.6; }
        .login-note strong { color: #111827; }
        .login-note ul { margin: 8px 0 0; padding-left: 16px; }
        .login-note li { margin-bottom: 4px; }
        .login-divider { display: flex; align-items: center; gap: 14px; margin: 22px 0; color: #9ca3af; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 1.5px; }
        .login-divider::before,
        .login-divider::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }
        .login-footer { margin-top: 4px; font-size: 0.82rem; color: #000; text-align: center; }
        .login-footer a { color: #111827; text-decoration: none; font-weight: 600; transition: color 0.2s; }
        .login-footer a:hover { color: #171f30; text-decoration: underline; }
        
        /* ── DISCUSSION ACCORDION SYSTEM ── */
        .discussion-list { display: flex; flex-direction: column; gap: 16px; margin-top: 20px; text-align: left; }
        .disc-item { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; padding: 20px; transition: 0.3s ease; }
        .disc-item:hover { background: rgba(255,255,255,0.04); border-color: rgba(181, 148, 31, 0.2); }
        .disc-topic { font-size: 1.1rem; font-weight: 600; color: var(--primary); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .disc-text { font-size: 0.95rem; color: rgba(255, 255, 255, 0.65); line-height: 1.6; }

        .hide-mobile { display: revert; }
        .show-mobile { display: none; }

        /* ── SKELETON LOADING SCREEN ── */
        .skeleton-loader {
            position: fixed; inset: 0; background: var(--navy); z-index: 9999;
            display: flex; flex-direction: column;
            transition: opacity 0.6s ease, visibility 0.6s ease;
        }
        .skeleton-loader.hidden { opacity: 0; visibility: hidden; pointer-events: none; }

        .sk-nav { display: flex; justify-content: space-between; align-items: center; padding: 30px; }
        .sk-block { position: relative; overflow: hidden; border-radius: 8px; background: rgba(255,255,255,0.06); }
        .sk-logo { width: 140px; height: 28px; }
        .sk-nav-group { display: flex; gap: 12px; }
        .sk-nav-btn { width: 100px; height: 36px; border-radius: 10px; }

        .sk-hero { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0 20px; gap: 18px; }
        .sk-title { width: min(500px, 80%); height: 52px; }
        .sk-desc { width: min(400px, 70%); height: 16px; }
        .sk-desc2 { width: min(300px, 50%); height: 16px; }
        .sk-hero-actions { display: flex; gap: 14px; margin-top: 18px; flex-wrap: wrap; justify-content: center; }
        .sk-btn { width: 150px; height: 48px; border-radius: 12px; }

        .sk-footer { padding: 20px; display: flex; justify-content: center; }
        .sk-footer-text { width: 200px; height: 12px; border-radius: 4px; background: rgba(255,255,255,0.04); }

        .sk-shimmer {
            position: absolute; inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.06), transparent);
            animation: skShimmer 1.5s ease-in-out infinite;
        }
        @keyframes skShimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

        .page-content { opacity: 1; transition: opacity 0.6s ease; }
        .page-content.loading { opacity: 0; }

        /* ── RESPONSIVE: Tablet (≤768px) ── */
        @media (max-width: 768px) {
            .nav-ct { padding: 22px 28px; }
            .nav-logo { font-size: 1.35rem; }
            .nav-auth .btn-ct { min-height: 44px; font-size: 0.85rem; padding: 8px 18px !important; }

            .hero-section { min-height: 100dvh; height: auto; padding: 90px 0 60px; }
            .hero-content { max-width: 620px; padding: 0 24px; }
            .hero-title { font-size: clamp(3.5rem, 7vw, 4.5rem); letter-spacing: -1px; }
            .hero-title-img { width: min(300px, 70%); margin: 0 auto 24px; }
            .hero-description { font-size: clamp(0.9rem, 2.5vw, 1.1rem); max-width: 520px; margin-bottom: 32px; }

            .btn-ct { padding: 12px 26px; min-height: 48px; font-size: 0.95rem; }
            .hero-actions { gap: 14px; }

            .modal-card { padding: 32px; max-width: 600px; }
            .modal-title { font-size: 1.6rem; }

            .disc-item { padding: 18px; }
            .disc-topic { font-size: 1.05rem; }
            .features-grid { gap: 18px; margin-top: 48px; grid-template-columns: repeat(2, 1fr); }
            .feature-card { padding: 22px; }

            #info-modal .modal-card,
            #login-modal .modal-card {
                max-width: 520px !important; width: 92% !important;
                position: relative !important; bottom: auto !important; left: auto !important; right: auto !important;
                top: auto !important; transform: translateY(40px) !important; border-radius: 24px !important;
                padding: 36px !important; border-bottom: 1px solid #ddd !important;
                transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
            }
            #info-modal .modal-card::before,
            #login-modal .modal-card::before { display: none !important; }
            #info-modal.modal-overlay,
            #login-modal.modal-overlay { align-items: center; }
            #info-modal.modal-overlay.active .modal-card,
            #login-modal.modal-overlay.active .modal-card { transform: translateY(0) !important; }

            .sk-nav { padding: 22px 28px; }
            .sk-logo { width: 120px; height: 26px; }
            .sk-nav-btn { width: 95px; height: 36px; }
            .sk-title { width: min(420px, 75%); height: 48px; }
            .sk-desc { width: min(360px, 65%); }
            .sk-btn { width: 140px; height: 46px; }

            footer { position: relative; padding: 16px; font-size: 0.65rem; }
        }

        /* ── RESPONSIVE: Phone (≤480px) ── */
        @media (max-width: 480px) {
            .nav-ct { padding: 14px 16px; flex-wrap: wrap; gap: 10px; }
            .nav-logo { font-size: 1.1rem; }
            .nav-auth > div { gap: 8px; flex-wrap: wrap; }
            .nav-auth .btn-ct { min-height: 44px; display: inline-flex; align-items: center; font-size: 0.8rem !important; padding: 8px 14px !important; }

            .hero-section { min-height: 100dvh; height: auto; padding: 100px 0 70px; }
            .hero-content { padding: 0 16px; }
            .hero-title { display: none; }
            .hero-title-img { display: block; width: min(280px, 65%); margin: 0 auto 3px; }
            .hero-description { font-size: 0.6rem; margin-bottom: 28px; }

            .hero-actions { flex-direction: row; flex-wrap: wrap; justify-content: center; gap: 8px; }
            .hero-actions .btn-ct { width: auto; padding: 7px 14px; font-size: 0.75rem; min-height: 36px; }
            .show-mobile { display: inline-flex; }
            .hide-mobile { display: none !important; }

            .modal-card { padding: 20px 14px; border-radius: 14px; width: 98%; max-height: 92vh; }
            #info-modal .modal-card {
                position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important;
                top: auto !important; transform: translateY(100%) !important; border-radius: 20px 20px 0 0 !important;
                width: 100% !important; max-width: 100% !important; max-height: 85vh !important; padding: 28px 20px 32px !important;
                border-bottom: none !important;
                transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }
            #info-modal .modal-card::before {
                content: ''; display: block; width: 40px; height: 5px;
                background: rgba(0,0,0,0.15); border-radius: 10px;
                margin: -4px auto 16px;
            }
            #info-modal.modal-overlay { align-items: flex-end; }
            #info-modal.modal-overlay.active .modal-card { transform: translateY(0) !important; }
            #info-modal .modal-close { top: 16px; right: 16px; width: 36px; height: 36px; font-size: 1.2rem; }
            #info-modal .modal-header { margin-bottom: 14px; padding-right: 36px; }
            #info-modal .modal-body p { font-size: 0.9rem; margin-bottom: 0.6rem; }
            #info-modal .modal-body li { font-size: 0.85rem; margin-bottom: 0.4rem; }
            #info-modal .modal-body ul { margin-left: 1rem; margin-bottom: 0.8rem; }
            #login-modal .modal-card {
                position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important;
                top: auto !important; transform: translateY(100%) !important; border-radius: 20px 20px 0 0 !important;
                width: 100% !important; max-width: 100% !important; max-height: 85vh !important; padding: 28px 20px 32px !important;
                border-bottom: none !important;
                transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
            }
            #login-modal .modal-card::before {
                content: ''; display: block; width: 40px; height: 5px;
                background: rgba(0,0,0,0.15); border-radius: 10px;
                margin: -4px auto 16px;
            }
            #login-modal.modal-overlay { align-items: flex-end; }
            #login-modal.modal-overlay.active .modal-card { transform: translateY(0) !important; }
            #login-modal .modal-card .modal-title { font-size: 1rem !important; }
            #login-modal .modal-close { top: 16px; right: 16px; }
            #login-modal .login-logo { width: 90px !important; height: 38px !important; margin-bottom: 12px !important; }
            #login-modal .login-footer { margin-top: 14px !important; font-size: 0.65rem !important; }
            .modal-title { font-size: 1.1rem; }
            .modal-header .modal-title { font-size: 1.1rem; }
            .modal-header { margin-bottom: 14px; }
            .modal-close { top: 12px; right: 12px; width: 36px; height: 36px; font-size: 1.3rem; }
            .modal-footer .btn-ct-primary { width: 100%; }
            #google-signin-btn { min-height: 44px; font-size: 0.8rem !important; padding: 10px !important; }
            #login-modal .modal-card a[href*="account.request"] { font-size: 0.65rem !important; }

            .disc-item { padding: 14px; }
            .disc-topic { font-size: 0.95rem; }
            .disc-text { font-size: 0.85rem; }

            .features-grid { grid-template-columns: 1fr; gap: 14px; margin-top: 32px; }
            .feature-card { padding: 18px; }

            footer { position: relative; padding: 6px; font-size: 0.4rem; }

            .sk-nav { padding: 14px 16px; }
            .sk-logo { width: 90px; height: 20px; }
            .sk-nav-btn { width: 70px; height: 32px; }
            .sk-title { width: min(320px, 85%); height: 36px; }
            .sk-desc { width: min(280px, 80%); height: 12px; }
            .sk-desc2 { width: min(200px, 60%); height: 12px; }
            .sk-btn { width: 120px; height: 38px; }
        }
    </style>
</head>
<body>

    <!-- ── SKELETON LOADER ── -->
    <div class="skeleton-loader" id="skeleton-loader">
        <div class="sk-nav">
            <div class="sk-block sk-logo"><div class="sk-shimmer"></div></div>
            <div class="sk-nav-group">
                <div class="sk-block sk-nav-btn"><div class="sk-shimmer"></div></div>
            </div>
        </div>
        <div class="sk-hero">
            <div class="sk-block sk-title"><div class="sk-shimmer"></div></div>
            <div class="sk-block sk-desc"><div class="sk-shimmer"></div></div>
            <div class="sk-block sk-desc2"><div class="sk-shimmer"></div></div>
            <div class="sk-hero-actions">
                <div class="sk-block sk-btn"><div class="sk-shimmer"></div></div>
                <div class="sk-block sk-btn"><div class="sk-shimmer"></div></div>
            </div>
        </div>
        <div class="sk-footer">
            <div class="sk-block sk-footer-text"><div class="sk-shimmer"></div></div>
        </div>
    </div>

    <div class="page-content loading" id="page-content">
    <nav class="nav-ct">
        <a href="/" class="nav-logo">Caps<span>Track
        </span></a>
        <div class="nav-auth">
            @if (Route::has('login'))
                <div class="flex gap-4" style="display: flex; gap: 16px; align-items: center;">
                    @auth
                        <a href="{{ route('student.dashboard') }}" class="btn-ct btn-ct-outline" style="padding: 8px 20px; font-size: 0.9rem;">Dashboard</a>
                    @else
                        <a href="#" class="btn-ct btn-ct-outline open-login-btn" style="cursor: pointer; border: none; padding: 8px 20px; font-size: 0.9rem;">Log in</a>
                        @if (Route::has('account.request'))
                            <a href="{{ route('account.request') }}" class="btn-ct btn-ct-outline hide-mobile" style="padding: 8px 20px; font-size: 0.9rem;">Request Account</a>
                        @endif
                    @endauth
                </div>
            @endif
        </div>
    </nav>

    <div class="hero-section">
        <div class="hero-bg"></div>
        <div class="hero-overlay"></div>
        
        <div class="hero-content">
            
            <h1 class="hero-title">Centralized <span>Capstone</span> Management</h1>
            <img src="{{ asset('img/capstrack1.png') }}" alt="CapsTrack" class="hero-title-img">
            <p class="hero-description">
                A unified digital space for students and instructors at CSPC College of Computer Studies. 
                Streamlining document submissions, expert matching, and project evaluation.
            </p>
            
            <div class="hero-actions">
                @auth
                    <a href="{{ route('student.dashboard') }}" class="btn-ct btn-ct-outline">
                        Go to Dashboard
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                @else
                    @if (Route::has('account.request'))
                        <a href="{{ route('account.request') }}" class="btn-ct btn-ct-outline show-mobile">Request Account</a>
                    @endif
                @endauth
                <button class="btn-ct btn-ct-outline" id="open-learn-more" style="cursor: pointer;">Learn More</button>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="info-modal">
        <div class="modal-card">
            <button class="modal-close" id="close-modal">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title">Learn More</h2>
            </div>
            <div class="modal-body">
    <p><strong>CapsTrack</strong> is the official capstone management system of the <em>CSPC College of Computer Studies</em>, designed to guide students from team formation to final defense.</p>
    <ul>
        <li>👥 <strong>Team Formation</strong> — Create or join teams, post recruitment ads, send invitations, and find members within your course.</li>
        <li>🎯 <strong>Expert Matching</strong> — Browse and apply to advisers, consultants, and grammarians. Track request status and manage assignments.</li>
        <li>📄 <strong>Document Submission & Review</strong> — Submit PDF manuscripts, receive annotated feedback, reply to comments, and track versions.</li>
        <li>📊 <strong>Progress Tracking</strong> — Visual milestone board with chapter-based progress, overdue alerts, and per-team reports for instructors.</li>
        <li>🤖 <strong>AI Duplication Checker</strong> — Upload your pre-proposal and get a similarity score with matched archived capstones.</li>
        <li>📅 <strong>Consultation Scheduling</strong> — Book slots with your adviser through an interactive calendar with availability management.</li>
        <li>📁 <strong>Google Drive Integration</strong> — Submissions are automatically stored in your adviser's Drive folder with version history.</li>
        <li>📋 <strong>Rubric-Based Grading</strong> — Multi-criteria evaluation (oral, content, methodology, output) with automated scoring.</li>
        <li>💳 <strong>Financial Tracking</strong> — Log expenses, upload receipts, and track verification status for approved budgets.</li>
        <li>📚 <strong>Resource Library</strong> — Access templates, lessons, and forms organized by capstone stage, hosted on Google Drive.</li>
        <li>🏛️ <strong>Archived Capstones</strong> — Browse past projects by year and category (Web, Mobile, IoT, AI/ML, and more).</li>
        <li>🔒 <strong>Google SSO</strong> — Secure sign-in using your <code>@my.cspc.edu.ph</code> account with reCAPTCHA protection.</li>
    </ul>
    <p style="margin-top: 16px;">Ready to get started? Sign in with your university account or request access as an instructor.</p>

</div>
        </div>
    </div>
    </div>

    <!-- ── GOOGLE LOGIN MODAL ── -->
    <div class="modal-overlay" id="login-modal">
        <div class="modal-card" style="max-width: 420px; padding: 40px 32px;">
            <button class="modal-close" id="close-login-modal">&times;</button>
            
            <div class="login-logo">
                <img src="{{ asset('img/capstrack.png') }}" alt="CapsTRACK Logo">
            </div>

            <div class="login-heading">
                <h2 class="modal-title">Sign in to CapsTrack</h2>
            </div>

            <div class="login-note">
                <strong>How to sign in:</strong>
                <ul>
                    <li><strong>Students:</strong> log in with your CSPC Mail to access your account.</li>
                    <li><strong>Instructors:</strong> log in with your CSPC Mail too, then you'll be directed to request an account.</li>
                    <li>Account approval may take up to <strong>3 days</strong>, depending on the admin.</li>
                    <li>You'll be notified through your Gmail when the admin accepts or rejects your request.</li>
                </ul>
            </div>

            @if ($errors->any())
                <div style="background: rgba(239, 68, 68, 0.08); border: 1px dashed rgba(239, 68, 68, 0.3); color: #f87171; font-size: 0.85rem; padding: 12px 14px; border-radius: 10px; margin-bottom: 20px; text-align: left; line-height: 1.4;">
                    @foreach ($errors->all() as $error)
                        <div style="display: flex; align-items: flex-start; gap: 6px;">
                            <span style="font-weight: bold; color: #ef4444;">•</span>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <form id="google-login-form" method="POST" action="{{ route('login.google.initiate') }}">
                @csrf
                <input type="hidden" name="g-recaptcha-response" id="recaptcha-token">
                <button type="submit" id="google-signin-btn" style="background: #f3f4f6; color: #111827; border: 1px solid #9ca3af; width: 100%; padding: 12px; border-radius: 10px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                    CSPC Mail
                </button>
            </form>
                <script>
                document.getElementById('google-login-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const btn = document.getElementById('google-signin-btn');
                    btn.disabled = true;
                    btn.textContent = 'Redirecting...';
                    grecaptcha.ready(function() {
                        grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', {action: 'login'}).then(function(token) {
                            document.getElementById('recaptcha-token').value = token;
                            fetch('{{ route('login.google.initiate') }}', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({'g-recaptcha-response': token}),
                            }).then(function(res) {
                                return res.json();
                            }).then(function(data) {
                                if (data.success && data.auth_url) {
                                    window.location.replace(data.auth_url);
                                } else {
                                    alert(data.message || 'Login failed. Please try again.');
                                    btn.disabled = false;
                                    btn.innerHTML = '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg> CSPC Mail';
                                }
                            }).catch(function(err) {
                                console.error(err);
                                alert('Network error: ' + (err.message || 'Please try again.') + '\nCheck the browser console for details.');
                                btn.disabled = false;
                                btn.innerHTML = '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg> CSPC Mail';
                           });
                        });
                    });
                });
                </script>

            <div class="login-divider">or</div>

            <div class="login-footer">
                Don't have an account yet? <a href="{{ route('account.request') }}">Request Account</a>
            </div>
        </div>
    </div>

    <footer style="position: absolute; bottom: 0; width: 100%; padding: 20px; text-align: center; font-size: 0.8rem; color: rgba(255,255,255,0.3); z-index: 10;">
        &copy; {{ date('Y') }} CSPC CCS CapsTrack - BULBASAUR
    </footer>
    </div>

    @if($removedExpert)
    <!-- ── EXPERT REMOVED MODAL ── -->
    <div class="modal-overlay active" id="expert-removed-modal">
        <div class="modal-card" style="max-width: 440px; text-align: center; padding: 40px 30px;">
            <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.08);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
            <h3 style="font-size:17px;font-weight:700;color:#1e2128;margin:0 0 6px;letter-spacing:-0.3px;">Account Removed</h3>
            <p style="font-size:13px;color:#6b7280;line-height:1.6;margin:0 0 12px;text-align:left;">
                Your expert account <strong>{{ $removedExpertName }}</strong> has been removed from the system.
                @if($removedExpertReason)
                    <br><br>Reason: {{ $removedExpertReason }}
                @endif
            </p>
            <button id="expert-removed-ok-btn" type="button" style="width:100%;padding:11px 16px;border-radius:10px;border:none;background:#1e2128;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background 0.15s;margin-top:4px;">OK</button>
        </div>
    </div>
    @endif

    <script>
        // Info Modal
        const openBtn = document.getElementById('open-learn-more');
        const closeBtn = document.getElementById('close-modal');
        const modal = document.getElementById('info-modal');

        openBtn.addEventListener('click', () => {
            modal.classList.add('active');
            document.body.classList.add('modal-open');
        });

        closeBtn.addEventListener('click', () => {
            modal.classList.remove('active');
            document.body.classList.remove('modal-open');
        });

        const closeFooterBtn = document.getElementById('close-learn-more');
        if (closeFooterBtn) {
            closeFooterBtn.addEventListener('click', () => {
                modal.classList.remove('active');
                document.body.classList.remove('modal-open');
            });
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
                document.body.classList.remove('modal-open');
            }
        });

        // Login Modal
        const loginModal = document.getElementById('login-modal');
        const closeLoginBtn = document.getElementById('close-login-modal');
        const openLoginBtns = document.querySelectorAll('.open-login-btn');

        openLoginBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                loginModal.classList.add('active');
                document.body.classList.add('modal-open');
            });
        });

        closeLoginBtn.addEventListener('click', () => {
            loginModal.classList.remove('active');
            document.body.classList.remove('modal-open');
            // Remove ?login=1 from URL so it doesn't reopen on reload
            const url = new URL(window.location);
            url.searchParams.delete('login');
            window.history.replaceState({}, '', url);
        });

        loginModal.addEventListener('click', (e) => {
            if (e.target === loginModal) {
                loginModal.classList.remove('active');
                document.body.classList.remove('modal-open');
                const url = new URL(window.location);
                url.searchParams.delete('login');
                window.history.replaceState({}, '', url);
            }
        });

        // Auto open login modal if ?login=1 query param is set, or if there are redirect errors
        const urlParams = new URLSearchParams(window.location.search);
        const hasErrors = @json($errors->any());
        if (urlParams.has('login') || hasErrors) {
            loginModal.classList.add('active');
            document.body.classList.add('modal-open');
        }

// reCAPTCHA v3 handling removed – v2 widget handles token automatically.

        // ── EXPERT REMOVED MODAL ──
        const expertRemovedModal = document.getElementById('expert-removed-modal');
        const expertRemovedOkBtn = document.getElementById('expert-removed-ok-btn');
        if (expertRemovedModal && expertRemovedOkBtn) {
            expertRemovedOkBtn.addEventListener('click', function() {
                expertRemovedModal.classList.remove('active');
                expertRemovedModal.style.display = 'none';
                const url = new URL(window.location);
                url.searchParams.delete('removed_expert');
                url.searchParams.delete('name');
                url.searchParams.delete('reason');
                window.history.replaceState({}, '', url);
            });
        }

        // ── SKELETON LOADING ──
        const skeleton = document.getElementById('skeleton-loader');
        const content = document.getElementById('page-content');

        function hideSkeleton() {
            skeleton.classList.add('hidden');
            content.classList.remove('loading');
        }

        if (document.readyState === 'complete') {
            hideSkeleton();
        } else {
            const minDisplay = new Promise(resolve => setTimeout(resolve, 800));
            Promise.all([minDisplay, new Promise(resolve => window.addEventListener('load', resolve))]).then(hideSkeleton);
        }
    </script>
</body>
</html>