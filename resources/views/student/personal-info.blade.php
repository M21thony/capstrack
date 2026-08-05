<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isEditing ? 'Edit' : 'Complete' }} Personal Information - CapsTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    @vite('resources/student-ui/Styles/personal-info.css')
    <x-dashboard.styles />
    <style>
        @keyframes sk-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        .sk-overlay { position: absolute; inset: 0; background: var(--bg2, #fff); padding: 40px; z-index: 10; }
        .sk { background: linear-gradient(90deg, var(--bg4) 25%, var(--bg2) 50%, var(--bg4) 75%); background-size: 200% 100%; animation: sk-shimmer 1.5s ease-in-out infinite; border-radius: 8px; }
    </style>
</head>
<body class="pi-page-body">
    <div class="pi-page">

        <main class="pi-card" style="position: relative;">

            {{-- Skeleton loader --}}
            <div id="skeleton-content" class="sk-overlay">
                <div class="sk" style="width:60%;height:22px;border-radius:6px;margin-bottom:10px"></div>
                <div class="sk" style="width:40%;height:12px;border-radius:4px;margin-bottom:24px"></div>
                @for ($s = 0; $s < 4; $s++)
                <div class="sk" style="width:180px;height:14px;border-radius:4px;margin-bottom:14px"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:26px">
                    @for ($i = 0; $i < 4; $i++)
                    <div>
                        <div class="sk" style="width:90px;height:9px;border-radius:4px;margin-bottom:6px"></div>
                        <div class="sk" style="width:100%;height:38px;border-radius:8px"></div>
                    </div>
                    @endfor
                </div>
                @endfor
                <div class="sk" style="width:160px;height:40px;border-radius:8px"></div>
            </div>

            {{-- Content --}}
            <div id="real-content" style="opacity:0">
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

            @include('student.partials.personal-info-form', ['student' => $student, 'presets' => $presets, 'isEditing' => $isEditing, 'hasTeam' => $hasTeam ?? false, 'studentSy' => $studentSy ?? null])
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
      var sk = document.getElementById('skeleton-content');
      var rc = document.getElementById('real-content');
      if (sk && rc) {
        requestAnimationFrame(function () {
          setTimeout(function () {
            sk.style.transition = 'opacity 0.3s ease';
            sk.style.opacity = '0';
            rc.style.transition = 'opacity 0.35s ease';
            rc.style.opacity = '1';
            setTimeout(function () {
              sk.remove();
              rc.style.transition = '';
            }, 350);
          }, 600);
        });
      }
    });
    </script>
</body>
</html>
