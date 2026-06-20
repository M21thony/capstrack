<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login – Capstrack</title>
    <!-- Tailwind CSS (already compiled in the app) -->
    @vite(['resources/css/app.css','resources/js/app.js'])
    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <style>
        body {font-family: 'Inter', sans-serif;}
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-100 via-purple-100 to-pink-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white bg-opacity-80 backdrop-blur-lg rounded-xl shadow-xl p-8 max-w-md w-full text-center">
        <h1 class="text-3xl font-semibold text-gray-800 mb-6">Welcome to Capstrack</h1>
        @if ($errors->any())
            <div class="mb-4 text-left text-red-600">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form id="google-login-form" method="POST" action="{{ route('login.google.initiate') }}">
            @csrf
            <input type="hidden" name="g-recaptcha-response" id="recaptcha-token">
            <button type="submit"
                class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition-colors duration-200">
                <svg class="w-5 h-5" viewBox="0 0 533.5 544.3" xmlns="http://www.w3.org/2000/svg">
                    <path d="M533.5 278.4c0-17.8-1.6-35-4.6-51.7H272v97.9h146.9c-6.4 34.5-25.5 63.8-54.4 83.4v68.9h87.9c51.5-47.5 81.1-117.5 81.1-198.5" fill="#4285F4"/>
                    <path d="M272 544.3c73.5 0 135.2-24.3 180.3-66.1l-87.9-68.9c-24.4 16.4-55.6 26-92.4 26-71 0-131.2-47.9-152.8-112.3H28.1v70.7C73.6 483.2 166.9 544.3 272 544.3" fill="#34A853"/>
                    <path d="M119.2 322.9c-10.5-31.3-10.5-65.1 0-96.4V155.8H28.1c-39.9 78.7-39.9 170.9 0 249.6l91.1-82.5" fill="#FBBC05"/>
                    <path d="M272 107.7c39.9-.6 78.3 15.3 107 44.2l79.9-79.9C410.1 21.8 341.5-.5 272 .1 166.9 .1 73.6 61.2 28.1 155.8l91.1 70.7C140.8 162 201 114.1 272 107.7" fill="#EA4335"/>
                </svg>
                Sign in with Google
            </button>
        </form>
        <script>
        document.getElementById('google-login-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Redirecting...';
            fetch('{{ route('login.google.initiate') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({'g-recaptcha-response': document.getElementById('recaptcha-token').value}),
            }).then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            }).then(function(data) {
                if (data.success && data.auth_url) {
                    window.location.replace(data.auth_url);
                } else {
                    alert(data.message || 'Login failed. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }).catch(function(err) {
                console.error(err);
                alert('Error: ' + (err.message || 'Network error. Please try again.'));
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
        </script>
    </div>
</body>
</html>
