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
        #pageSkeleton { position: fixed; inset: 0; z-index: 9999; background: var(--bg2, #fff); transition: opacity 0.6s ease, visibility 0.6s ease; }
        #pageSkeleton.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
        #pi-realContent { opacity: 1; transition: opacity 0.6s ease; }
        #pi-realContent.loading { opacity: 0; }
    </style>
</head>
<body class="pi-page-body">
    <div class="pi-page">

        <main class="pi-card">

            {{-- Skeleton loader --}}
            <div id="pageSkeleton" style="padding:40px">
                <div class="skeleton-box" style="width:60%;height:22px;border-radius:6px;margin-bottom:10px"></div>
                <div class="skeleton-box" style="width:40%;height:12px;border-radius:4px;margin-bottom:24px"></div>
                @for ($s = 0; $s < 4; $s++)
                <div class="skeleton-box" style="width:180px;height:14px;border-radius:4px;margin-bottom:14px"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:26px">
                    @for ($i = 0; $i < 4; $i++)
                    <div>
                        <div class="skeleton-box" style="width:90px;height:9px;border-radius:4px;margin-bottom:6px"></div>
                        <div class="skeleton-box" style="width:100%;height:38px;border-radius:8px"></div>
                    </div>
                    @endfor
                </div>
                @endfor
                <div class="skeleton-box" style="width:160px;height:40px;border-radius:8px"></div>
            </div>

            {{-- Content --}}
            <div id="pi-realContent" class="loading">
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
    var __skeleton = document.getElementById('pageSkeleton');
    var __content = document.getElementById('pi-realContent');
    if (__skeleton && __content) {
      (function() {
        function hideSkeleton() {
          __skeleton.classList.add('hidden');
          __content.classList.remove('loading');
        }
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', hideSkeleton);
        } else {
          hideSkeleton();
        }
      })();
    }
    </script>
</body>
</html>
