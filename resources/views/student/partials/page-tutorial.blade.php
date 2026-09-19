@php
    $tutorial = config('student-tutorials.'.$page);
@endphp

@if ($tutorial)
    <div class="page-tutorial" id="tutorial-{{ $page }}" data-tutorial-key="{{ $page }}" hidden>
        <div class="page-tutorial-inner">
            <div class="page-tutorial-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            </div>
            <div class="page-tutorial-content">
                <div class="page-tutorial-title">{{ $tutorial['title'] }}</div>
                <p class="page-tutorial-summary">{{ $tutorial['summary'] }}</p>
                <ol class="page-tutorial-steps">
                    @foreach ($tutorial['steps'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            </div>
            <button type="button" class="page-tutorial-dismiss" data-dismiss-tutorial="{{ $page }}" aria-label="Dismiss tutorial">Got it</button>
        </div>
    </div>
    <script>
    (function () {
        var key = 'capstrack_tutorial_{{ $page }}';
        var el = document.getElementById('tutorial-{{ $page }}');
        if (!el) return;
        if (!localStorage.getItem(key)) {
            el.hidden = false;
        }
        el.querySelector('[data-dismiss-tutorial]').addEventListener('click', function () {
            localStorage.setItem(key, '1');
            el.hidden = true;
        });
    })();
    </script>
@endif
