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

    <!-- Google reCAPTCHA -->
    <!-- Use this site key in the HTML code your site serves to users - 6LcsrwotAAAAAKKcqCrYmohVfKP-bVvID6LjXWTp (reCAPTCHA v3) -->
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}" />

    <style>
        :root { --primary: #b5941f; --primary-dark: #9e811a; --navy: #0f1115; --navy-light: #17191e; }
        
        /* ── GOOGLE SIGN IN BUTTON STYLES ── */
        #google-signin-btn:not([disabled]) {
            opacity: 1 !important;
            cursor: pointer !important;
            background: var(--primary) !important;
            color: #0f1115 !important;
            border-color: #fff !important;
        }
        #google-signin-btn:not([disabled]):hover {
            background: #fff !important;
            color: var(--navy) !important;
            border-color: var(--primary) !important;
            box-shadow: 0 8px 24px rgba(181, 148, 31, 0.3) !important;
            transform: translateY(-1px);
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--navy); color: #fff; margin: 0; overflow-x: hidden; }
        .hero-section { position: relative; height: 100vh; width: 100%; display: flex; align-items: center; justify-content: center; background-color: var(--navy); overflow: hidden; }
        .hero-bg { position: absolute; inset: 0; background-image: url('{{ asset('images/bg-ccs.jpg') }}'); background-size: cover; background-position: center; opacity: 0.3; z-index: 1; }
        .hero-overlay { position: absolute; inset: 0; background: radial-gradient(circle at center, transparent 20%, var(--navy) 100%); z-index: 2; }
        .hero-content { position: relative; z-index: 10; text-align: center; max-width: 900px; padding: 0 20px; animation: fadeInUp 1s ease-out; }
        .hero-logo { width: 180px; margin: 0 auto 30px; }
        .hero-title { font-family: 'Space Mono', monospace; font-size: clamp(2.5rem, 8vw, 4.5rem); font-weight: 700; margin-bottom: 20px; letter-spacing: -2px; line-height: 1; }
        .hero-title span { color: var(--primary); }
        .hero-description { font-size: clamp(1rem, 3vw, 1.25rem); color: rgba(255, 255, 255, 0.7); margin-bottom: 40px; line-height: 1.6; max-width: 700px; margin-left: auto; margin-right: auto; }
        .hero-actions { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
        .btn-ct { padding: 14px 32px; border-radius: 12px; font-weight: 600; text-decoration: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-size: 1rem; display: inline-flex; align-items: center; gap: 8px; }
        .btn-ct-primary { background: var(--primary); color: var(--navy); }
        .btn-ct-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(181, 148, 31, 0.2); }
        .btn-ct-outline { background: transparent; color: #fff; border: 1px solid rgba(255, 255, 255, 0.2); }
        .btn-ct-outline:hover { border-color: var(--primary); color: var(--primary); background: rgba(181, 148, 31, 0.05); transform: translateY(-2px); }
        .nav-ct { position: absolute; top: 0; left: 0; right: 0; padding: 30px; display: flex; justify-content: space-between; align-items: center; z-index: 100; }
        .nav-logo { font-family: 'Space Mono', monospace; font-weight: 700; font-size: 1.5rem; color: #fff; text-decoration: none; letter-spacing: -1px; }
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
.modal-close:hover { background: rgba(0,0,0,0.1); color: var(--primary); border-color: var(--primary); }
.modal-header { margin-bottom: 24px; text-align: left; }
.modal-title { font-family: 'Space Mono', monospace; font-size: 1.8rem; font-weight: 700; color: #333; }
.modal-footer { margin-top: 1.5rem; text-align: center; }
.modal-footer .btn-ct-primary { min-width: 120px; }

.modal-body p { margin-bottom: 0.8rem; font-size: 1rem; line-height: 1.5; color: #333; }
.modal-body ul { margin-left: 1.2rem; margin-bottom: 1rem; }
.modal-body li { margin-bottom: 0.5rem; }
.modal-footer { margin-top: 1.5rem; text-align: center; }
        
        /* ── DISCUSSION ACCORDION SYSTEM ── */
        .discussion-list { display: flex; flex-direction: column; gap: 16px; margin-top: 20px; text-align: left; }
        .disc-item { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; padding: 20px; transition: 0.3s ease; }
        .disc-item:hover { background: rgba(255,255,255,0.04); border-color: rgba(181, 148, 31, 0.2); }
        .disc-topic { font-size: 1.1rem; font-weight: 600; color: var(--primary); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .disc-text { font-size: 0.95rem; color: rgba(255, 255, 255, 0.65); line-height: 1.6; }
    </style>
</head>
<body>
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
                            <a href="{{ route('account.request') }}" class="btn-ct btn-ct-primary" style="padding: 8px 20px; font-size: 0.9rem;">Request Account</a>
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
            <p class="hero-description">
                A unified digital space for students and instructors at CSPC College of Computer Studies. 
                Streamlining document submissions, expert matching, and project evaluation.
            </p>
            
            <div class="hero-actions">
                @auth
                    <a href="{{ route('student.dashboard') }}" class="btn-ct btn-ct-primary">
                        Go to Dashboard
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                @else

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
    <p><strong>Welcome to CapsTrack!</strong> This platform helps you easily manage your capstone projects from start to finish.</p>
    <p><strong>CapsTrack</strong> is a cloud‑based solution for the <em>CSPC College of Computer Studies</em>, streamlining proposal submissions, mentor connections, and progress tracking.</p>
    <ul>
        <li>🗂️ Create and manage project proposals, milestones, and deliverables.</li>
        <li>🤝 Connect with faculty mentors and industry experts.</li>
        <li>📊 Real‑time progress tracking with automated reminders.</li>
        <li>📁 Secure document storage with version history.</li>
        <li>🔒 Sign in using your <code>@my.cspc.edu.ph</code> Google account for single‑sign‑on access.</li>
    </ul>
    <p>Ready to explore? Click the button below to get started or sign in with your university account.</p>

</div>
        </div>
    </div>
    </div>

    <!-- ── GOOGLE LOGIN MODAL ── -->
    <div class="modal-overlay" id="login-modal">
        <div class="modal-card" style="max-width: 440px; text-align: center; padding: 40px 30px;">
            <button class="modal-close" id="close-login-modal">&times;</button>
            
            <div style="width: 140px; height: 60px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                <img src="{{ asset('img/capstrack.png') }}" alt="CapsTRACK Logo" style="width: 100%; height: auto;">
            </div>

            <div class="modal-header" style="text-align: center; margin-bottom: 16px;">
                <h2 class="modal-title" style="font-size: 1.6rem; text-align: center;">Sign in to <span>CapsTrack</span></h2>
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
                <button type="submit" id="google-signin-btn" style="background: var(--primary); color: #fff; border: 1px solid var(--primary); width: 100%; padding: 12px; border-radius: 10px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.9rem;">
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
                                btn.innerHTML = '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg> CSPC Mail';
                            });
                        });
                    });
                });
                </script>

            <div style="margin-top: 24px; font-size: 0.8rem; color: #000; text-align: center;">
                Don't have an account yet? <a href="{{ route('account.request') }}" style="color: #004cffff; text-decoration: none; font-weight: 500; transition: color 0.2s;">Request Account</a>
            </div>
        </div>
    </div>

    <footer style="position: absolute; bottom: 0; width: 100%; padding: 20px; text-align: center; font-size: 0.8rem; color: rgba(255,255,255,0.3); z-index: 10;">
        &copy; {{ date('Y') }} CSPC College of Computer Studies. CapsTrack System. - created by BULBASAUR
    </footer>

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

    </script>
</body>
</html>