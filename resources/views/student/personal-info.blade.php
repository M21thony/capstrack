<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isEditing ? 'Edit' : 'Complete' }} Personal Information - CapsTRACK</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    @vite('resources/student-ui/Styles/personal-info.css')
</head>
<body class="pi-page-body">
    <div class="pi-page">
        <header class="pi-header">
            <div class="pi-brand">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                </div>
                <div>
                    <div class="pi-brand-name">CapsTRACK</div>
                    <div class="pi-brand-sub">Personal Information</div>
                </div>
            </div>
            @if ($isEditing)
                <a href="{{ route('student.dashboard') }}" class="pi-link-back">Back to Dashboard</a>
            @endif
        </header>

        <main class="pi-card">
            <div class="pi-hero">
                <h1 class="pi-title">{{ $isEditing ? 'Update your profile' : 'Complete your profile' }}</h1>
                @if (! $isEditing)
                    <p class="pi-meta">Takes about 2 minutes · required once</p>
                @endif
                <p class="pi-intro">
                    @if ($isEditing)
                        Update your details below. Team information is managed separately in Team Management.
                    @else
                        Before using CapsTrack, fill in your personal and academic details. You can set up your capstone team later from the sidebar.
                    @endif
                </p>
            </div>

            @if (session('status'))
                <div class="pi-flash">{{ session('status') }}</div>
            @endif

            @include('student.partials.personal-info-form', ['student' => $student, 'presets' => $presets, 'isEditing' => $isEditing, 'hasTeam' => $hasTeam ?? false])
        </main>
    </div>
</body>
</html>
