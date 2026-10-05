@php
    $expert = auth('expert')->user();
    $isAdmin = $expert && $expert->is_staff;
@endphp

@if (session('show_welcome'))
    <div class="modal-overlay show" id="welcome-modal">
        <div class="modal-box" style="width:min(420px,94%);text-align:center;padding:32px 28px 28px">
            <div style="width:56px;height:56px;border-radius:14px;background:{{ $isAdmin ? 'linear-gradient(135deg,#1d4ed8,#7c3aed)' : 'linear-gradient(135deg,#c9960c,#b45309)' }};display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <svg viewBox="0 0 24 24" style="width:26px;height:26px;stroke:#fff;fill:none;stroke-width:2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            </div>
            <div class="modal-title" style="font-size:17px;margin-bottom:4px">Welcome to CapsTRACK!</div>
            <div class="modal-sub" style="font-size:13px;margin-bottom:20px">
                Hello, <strong>{{ $expert?->first_name }}</strong>! Your account has been approved.
                Here's a quick overview to get you started.
            </div>

            <div style="display:flex;flex-direction:column;gap:10px;text-align:left;margin-bottom:22px">
                @if ($isAdmin)
                    <div style="display:flex;align-items:flex-start;gap:10px;background:var(--bg3);padding:11px 13px;border-radius:10px;font-size:12.5px;color:var(--text2);line-height:1.4">
                        <div style="width:28px;height:28px;border-radius:8px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:#1d4ed8;fill:none;stroke-width:2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        </div>
                        <span><strong>Dashboard</strong> — View key metrics, pending approvals, and recent activity at a glance.</span>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:10px;background:var(--bg3);padding:11px 13px;border-radius:10px;font-size:12.5px;color:var(--text2);line-height:1.4">
                        <div style="width:28px;height:28px;border-radius:8px;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:#b45309;fill:none;stroke-width:2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        </div>
                        <span><strong>Approvals</strong> — Review and approve pending instructor account requests.</span>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:10px;background:var(--bg3);padding:11px 13px;border-radius:10px;font-size:12.5px;color:var(--text2);line-height:1.4">
                        <div style="width:28px;height:28px;border-radius:8px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:#16a34a;fill:none;stroke-width:2.5"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>
                        </div>
                        <span><strong>Manage Experts</strong> — View, manage, and transfer admin rights for expert accounts.</span>
                    </div>
                @else
                    <div style="display:flex;align-items:flex-start;gap:10px;background:var(--bg3);padding:11px 13px;border-radius:10px;font-size:12.5px;color:var(--text2);line-height:1.4">
                        <div style="width:28px;height:28px;border-radius:8px;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:#b45309;fill:none;stroke-width:2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <span><strong>My Teams</strong> — Browse and manage the capstone teams assigned to you.</span>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:10px;background:var(--bg3);padding:11px 13px;border-radius:10px;font-size:12.5px;color:var(--text2);line-height:1.4">
                        <div style="width:28px;height:28px;border-radius:8px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:#1d4ed8;fill:none;stroke-width:2.5"><polyline points="16 11 18 13 22 9"/><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        </div>
                        <span><strong>Applications</strong> — Review and respond to team application requests.</span>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:10px;background:var(--bg3);padding:11px 13px;border-radius:10px;font-size:12.5px;color:var(--text2);line-height:1.4">
                        <div style="width:28px;height:28px;border-radius:8px;background:#f3e8ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:#9333ea;fill:none;stroke-width:2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <span><strong>Schedule</strong> — Manage your availability and consultation schedules.</span>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:10px;background:var(--bg3);padding:11px 13px;border-radius:10px;font-size:12.5px;color:var(--text2);line-height:1.4">
                        <div style="width:28px;height:28px;border-radius:8px;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:#dc2626;fill:none;stroke-width:2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </div>
                        <span><strong>Document Review</strong> — Review and provide feedback on team submissions.</span>
                    </div>
                @endif
            </div>

            <button type="button" class="btn btn-primary" id="welcome-dismiss" style="width:100%;justify-content:center;padding:10px;font-size:13px;font-weight:600">Got it, let's go!</button>
        </div>
    </div>

    <script nonce="{{ Vite::cspNonce() }}">
    (function () {
        var el = document.getElementById('welcome-modal');
        var btn = document.getElementById('welcome-dismiss');
        if (!el || !btn) return;
        btn.addEventListener('click', function () {
            el.classList.remove('show');
        });
    })();
    </script>
@endif
