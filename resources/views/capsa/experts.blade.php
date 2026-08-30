<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Expert Management - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
  /* Expert Management (CaPSA's copy - view + Manage Roles only). Admin has
     its own separate, fuller copy at resources/views/admin/experts.blade.php
     (this plus Edit/Remove/Make CAPSA/Add Expert) - these two used to be
     one file switching on viewer role, which made neither version easy to
     read on its own. */
  .expert-role-badges{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    align-items:center;
  }
  .expert-role-stack{
    display:inline-flex;
    flex-direction:column;
    gap:5px;
    width:fit-content;
    max-width:100%;
  }
  .expert-role-badge{
    font-size:10px;
    font-weight:600;
    padding:5px 10px;
    letter-spacing:0;
    text-transform:none;
    border-radius:999px;
    box-shadow:none;
  }
  .expert-role-meta{
    display:inline-flex;
    align-items:center;
    gap:6px;
    width:fit-content;
    font-size:10px;
    font-weight:600;
    color:var(--text3);
    text-transform:none;
    letter-spacing:0;
  }
  .expert-role-meta::before{
    content:'';
    width:4px;
    height:4px;
    border-radius:999px;
    background:var(--accent);
  }

  .expert-actions{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:8px;
  }

  /* Capacity column - merges what used to be separate Teams / Slots Left
     columns. One row per program when an expert's slots are actually split
     that way (see CapstoneExpert::parsedCapacity()), one "ALL" row when
     they're not. Read-only here - editing capacity is Admin's job. */
  .capacity-stack{display:flex;flex-direction:column;gap:5px}
  .capacity-row{display:grid;grid-template-columns:38px 1fr 34px;align-items:center;gap:8px}
  .capacity-prog{font-size:10px;font-weight:700;color:var(--text3);letter-spacing:0.3px}
  .capacity-count{font-family:'Space Mono',monospace;font-size:11px;color:var(--text3);text-align:right}

  /* Manage Roles - the one action this page has. */
  .roles-btn{
    display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;
    border:1.5px solid var(--blue-bg);background:var(--blue-bg);color:var(--blue);
    font-size:11px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;
  }
  .roles-btn:hover{background:var(--blue);color:#fff;border-color:var(--blue)}
  .roles-btn svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.2}
  .role-options{display:flex;flex-direction:column;gap:8px;text-align:left}
  .role-opt{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer;background:#f8fafc}
  .role-opt:hover{border-color:#bfdbfe;background:#eff6ff}
  .role-opt input{width:15px;height:15px;accent-color:#2563eb;margin-top:2px;flex-shrink:0}
  .role-opt-name{font-size:13px;font-weight:700;color:#0f172a}
  .role-opt-desc{font-size:11.5px;color:#64748b;margin-top:2px;line-height:1.4}

  /* Personal info modal - opens on clicking an expert's name/avatar, and
     shows what the table row can't fit (full expertise list, credentials,
     department, sections). Rides on the page's existing .ct-modal-overlay /
     .ct-modal-card shell so it looks and behaves like every other dialog
     here. Shared markup/behavior with the other copy of this page. */
  .expert-name-trigger{cursor:pointer}
  .expert-detail-head{
    display:flex;align-items:flex-start;gap:12px;text-align:left;
    padding-bottom:16px;border-bottom:1px solid #f1f5f9;margin-bottom:18px;
  }
  .expert-detail-avatar{width:48px;height:48px;border-radius:10px;object-fit:cover;flex-shrink:0}
  .expert-detail-name{font-size:16px;font-weight:700;color:#0f172a;line-height:1.3}
  .expert-detail-email{font-size:12.5px;color:#64748b;margin-top:2px;word-break:break-all}
  .expert-detail-close{
    margin-left:auto;background:none;border:none;cursor:pointer;color:#94a3b8;
    padding:4px;border-radius:6px;flex-shrink:0;line-height:0;
  }
  .expert-detail-close:hover{color:#475569;background:#f1f5f9}
  /* Caps the body rather than the whole card, so the header and Close
     button stay put while a long expertise list scrolls. */
  .expert-detail-body{
    display:flex;flex-direction:column;gap:16px;text-align:left;
    max-height:52vh;overflow-y:auto;scrollbar-width:none;
  }
  .expert-detail-body::-webkit-scrollbar{display:none}
  .expert-detail-field-label{
    font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;
  }
  .expert-detail-field-value{font-size:13.5px;color:#1e293b;line-height:1.5}
  .expert-detail-badges{display:flex;flex-wrap:wrap;gap:6px}
  .expert-detail-chip{
    font-size:11px;font-weight:600;padding:4px 10px;border-radius:999px;background:#f1f5f9;color:#475569;
  }

  /* ── Modal overlay ── */
  .ct-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(5px);
    display: flex; align-items: safe center; justify-content: center;
    z-index: 9999;
    animation: ctFadeIn 0.18s ease;
    /* safe center keeps a normal modal centred but falls back to
       top-aligned once it is taller than the viewport, so a long expert
       detail list can still be scrolled to instead of being clipped. */
    overflow-y: auto; padding: 40px 16px; scrollbar-width: none;
  }
  .ct-modal-overlay::-webkit-scrollbar { display: none; }

  /* ── Modal card ── */
  .ct-modal-card {
    background: #ffffff;
    width: 100%; max-width: 400px;
    border-radius: 16px;
    box-shadow: 0 24px 48px -8px rgba(0,0,0,0.18), 0 0 0 1px rgba(0,0,0,0.06);
    padding: 32px 28px 24px;
    text-align: center;
    animation: ctPopUp 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  }

  .ct-modal-title {
    margin: 0 0 8px;
    font-size: 18px; font-weight: 700; color: #0f172a;
  }
  .ct-modal-desc {
    font-size: 13.5px; color: #64748b; line-height: 1.5;
    margin: 0 0 20px;
  }

  .ct-modal-label {
    display: block;
    font-size: 11px; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: 0.05em;
    text-align: left; margin-bottom: 6px;
  }
  .ct-modal-input {
    width: 100%; box-sizing: border-box;
    padding: 10px 13px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #f8fafc; color: #1e293b;
    font-size: 13px; outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    margin-bottom: 20px;
  }
  .ct-modal-input:focus {
    border-color: #94a3b8;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2);
  }

  .ct-modal-actions { display: flex; gap: 10px; }
  .ct-btn {
    flex: 1;
    padding: 10px 16px;
    font-size: 13px; font-weight: 600;
    border-radius: 8px; border: none;
    cursor: pointer; transition: all 0.12s ease;
    display: inline-flex; align-items: center; justify-content: center;
  }
  .ct-btn-secondary { background: #f1f5f9; color: #475569; }
  .ct-btn-secondary:hover { background: #e2e8f0; color: #1e293b; }
  .ct-btn-primary { background: #2563eb; color: #fff; }
  .ct-btn-primary:hover:not(:disabled) { background: #1d4ed8; }
  .ct-btn-primary:disabled { background: #f1f5f9; color: #cbd5e1; cursor: not-allowed; }

  @keyframes ctFadeIn { from { opacity: 0; } to { opacity: 1; } }
  @keyframes ctPopUp  { from { transform: scale(0.94) translateY(8px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }

  /* ── Toast Notifications ── */
  #ct-toast-container {
    position: fixed;
    bottom: 24px; right: 24px;
    z-index: 99999;
    display: flex; flex-direction: column; gap: 10px;
    align-items: flex-end;
  }
  .ct-toast {
    display: flex; align-items: flex-start; gap: 12px;
    min-width: 280px; max-width: 360px;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.06);
    padding: 14px 16px;
    animation: toastIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    transition: opacity 0.2s, transform 0.2s;
  }
  .ct-toast.hiding { opacity: 0; transform: translateX(20px); }
  .ct-toast-icon {
    width: 36px; height: 36px; flex-shrink: 0;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
  }
  .ct-toast-icon.success { background: #dcfce7; color: #16a34a; }
  .ct-toast-icon.error   { background: #fee2e2; color: #dc2626; }
  .ct-toast-icon.info    { background: #dbeafe; color: #2563eb; }
  .ct-toast-body { flex: 1; }
  .ct-toast-title { font-size: 13px; font-weight: 700; color: #0f172a; margin: 0 0 2px; }
  .ct-toast-msg   { font-size: 12.5px; color: #64748b; margin: 0; line-height: 1.4; }
  .ct-toast-close {
    background: none; border: none; cursor: pointer;
    color: #94a3b8; font-size: 16px; line-height: 1;
    padding: 2px 4px; border-radius: 4px;
    transition: color 0.1s;
  }
  .ct-toast-close:hover { color: #475569; }
  @keyframes toastIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
</style>
</head>
<body>

@include('capsa._sidebar')

<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Expert Management</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="content">
      <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
        <div class="sk" style="height:38px;width:260px;border-radius:8px;"></div>
        <div class="sk" style="height:38px;width:140px;border-radius:8px;"></div>
        <div class="sk" style="height:38px;width:140px;border-radius:8px;"></div>
      </div>
      <div class="card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
          <thead><tr><th style="padding:12px 20px;"><div class="sk" style="height:14px;width:40px;"></div></th><th style="padding:12px"><div class="sk" style="height:14px;width:50px;"></div></th><th style="padding:12px"><div class="sk" style="height:14px;width:60px;"></div></th><th style="padding:12px"><div class="sk" style="height:14px;width:50px;"></div></th><th style="padding:12px"><div class="sk" style="height:14px;width:70px;"></div></th><th style="padding:12px 20px;"><div class="sk" style="height:14px;width:50px;"></div></th></tr></thead>
          <tbody>
            @for($i = 0; $i < 4; $i++)
            <tr>
              <td style="padding:14px 20px;"><div style="display:flex;align-items:center;gap:10px;"><div class="sk" style="width:32px;height:32px;border-radius:8px;"></div><div><div class="sk" style="height:13px;width:110px;margin-bottom:5px;"></div><div class="sk" style="height:11px;width:80px;"></div></div></div></td>
              <td style="padding:14px 12px;"><div class="sk" style="height:20px;width:60px;border-radius:10px;"></div></td>
              <td style="padding:14px 12px;"><div class="sk" style="height:12px;width:100px;"></div></td>
              <td style="padding:14px 12px;"><div class="sk" style="height:12px;width:50px;"></div></td>
              <td style="padding:14px 12px;"><div class="sk" style="height:20px;width:50px;border-radius:10px;"></div></td>
              <td style="padding:14px 20px;"><div class="sk" style="height:28px;width:120px;border-radius:6px;"></div></td>
            </tr>
            @endfor
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    <div class="experts-toolbar">
      <div class="experts-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="input" id="experts-search-input" placeholder="Search experts by name or expertise..."/>
      </div>
      <div class="filter-row">
        <select class="input" id="experts-role-filter" style="width:160px">
          <option>All Roles</option>
          <option>Adviser</option>
          <option>Consultant</option>
          <option>Grammarian</option>
        </select>
        <select class="input" style="width:160px" id="statusFilter">
          <option value="all">All Status</option>
          <option value="active">Active</option>
          <option value="full">Full</option>
          <option value="offline">Offline</option>
        </select>
      </div>
      <span class="experts-count">{{ $experts->count() }} registered experts</span>
      <button type="button" class="btn" onclick="openExpertRosterModal()">Upload List</button>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
      <table class="table">
        <thead>
          <tr>
            <th style="padding-left:20px">Expert</th>
            <th>Role(s)</th>
            <th>Expertise</th>
            <th>Capacity</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach($experts as $expert)
          <tr>
            @php
              $expertiseList = $expert->expertise->pluck('expertise_field');
              $expertiseFull = $expertiseList->join(', ') ?: 'None specified';
              $expertiseShown = $expertiseList->take(2)->join(', ');
              $expertiseExtra = $expertiseList->count() - 2;

              $maxTeams = $expert->max_teams ?? 4;
              $teamCount = $expert->team_assignments_count ?? 0;
              $slotsLeft = max(0, $maxTeams - $teamCount);
              $isFull = $teamCount >= $maxTeams;
              $isOnline = $expert->is_online;
              $computedStatus = $isFull ? 'full' : ($isOnline ? 'active' : 'offline');
              $capacityRows = $expert->parsedCapacity();

              // "Adviser" and "capstone_adviser" read as near-duplicates
              // once both show up together (the latter is really "CaPSA"'s
              // own role - Capstone [Project] Subject Adviser) - spelling
              // both out fully keeps them visibly distinct instead of both
              // collapsing to "Adviser".
              $roleLabel = fn (string $roleName) => match ($roleName) {
                  'adviser' => 'Capstone Adviser',
                  'capstone_adviser' => 'Capstone Subject Adviser',
                  default => (string) \Illuminate\Support\Str::of($roleName)->replace('_', ' ')->title(),
              };

              $detailPayload = [
                'avatar' => $expert->avatarUrl(),
                'name' => $expert->name,
                'email' => $expert->email,
                'credentials' => $expert->credentials,
                'program' => $expert->program,
                'is_capsa' => (bool) $expert->is_capsa,
                'sections' => $expert->sections ?? [],
                'roles' => $expert->roles->pluck('role_name')->map($roleLabel)->values(),
                'expertise' => $expertiseList->values(),
                'capacity' => collect($capacityRows)->map(fn ($row) => $row[0].': max '.$row[1])->values(),
              ];
            @endphp
            <td style="padding-left:20px" data-label="Expert">
              <div class="expert-name-trigger" style="display:flex;align-items:center;gap:10px" onclick="openExpertDetail({{ Illuminate\Support\Js::from($detailPayload) }})">
                <img src="{{ $expert->avatarUrl() }}" alt="" loading="lazy"
                     style="width:32px;height:32px;border-radius:8px;object-fit:cover;flex-shrink:0"
                     onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.jpg') }}';">
                <div>
                  <div class="td-main">{{ $expert->name }}</div>
                  <div style="font-size:11px;color:var(--text3)">{{ $expert->email }}</div>
                </div>
              </div>
            </td>
            <td data-label="Role">
              <div class="expert-role-stack">
                <div class="expert-role-badges">
                  {{-- No separate "CAPSA" badge here - toggleCapsa() keeps the
                       capstone_adviser role in sync with is_capsa, so the
                       "Capstone Subject Adviser" badge below already says it;
                       a second blue badge just repeated the same fact. --}}
                  @forelse ($expert->roles as $role)
                    <span class="badge badge-gold expert-role-badge">{{ $roleLabel($role->role_name) }}</span>
                  @empty
                    @if (! $expert->is_capsa)
                      <span class="badge badge-gray expert-role-badge">No Role</span>
                    @endif
                  @endforelse
                </div>
                @if ($expert->is_capsa)
                  <span class="expert-role-meta">Sections: {{ !empty($expert->sections) ? implode(', ', $expert->sections) : 'none set' }}</span>
                @endif
              </div>
            </td>

            <td style="font-size:12px;max-width:220px" data-label="Expertise" title="{{ $expertiseFull }}">
              @if ($expertiseList->isEmpty())
                None specified
              @else
                <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $expertiseShown }}</div>
                @if ($expertiseExtra > 0)
                  <span style="color:var(--text3)">+{{ $expertiseExtra }} more</span>
                @endif
              @endif
            </td>
            <td data-label="Capacity">
              @if (count($capacityRows) === 1)
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="pbar" style="width:60px">
                    <div class="pbar-fill pf-green" style="width:{{ $maxTeams > 0 ? round(($teamCount / $maxTeams) * 100) : 0 }}%"></div>
                  </div>
                  <span style="font-size:11px;color:var(--text3)">{{ $teamCount }}/{{ $maxTeams }} · {{ $slotsLeft }} left</span>
                </div>
              @else
                <div class="capacity-stack">
                  @foreach ($capacityRows as [$program, $max])
                    <div class="capacity-row">
                      <span class="capacity-prog">{{ $program }}</span>
                      <span></span>
                      <span class="capacity-count">max {{ $max }}</span>
                    </div>
                  @endforeach
                </div>
                <div style="font-size:10px;color:var(--text3);margin-top:4px">{{ $teamCount }} total assigned</div>
              @endif
            </td>
            <td data-label="Status">
              @if($computedStatus === 'full')
                <span class="badge badge-amber">Full</span>
              @elseif($computedStatus === 'offline')
                <span class="badge badge-gray">Offline</span>
              @else
                <span class="badge badge-green">Active</span>
              @endif
            </td>
            <td data-label="Action">
              <div class="expert-actions">
                <button type="button" class="roles-btn"
                        onclick="openRoles({{ $expert->expert_id }}, {{ Illuminate\Support\Js::from($expert->name) }}, {{ Illuminate\Support\Js::from($expert->roles->pluck('role_name')) }})">
                  <svg viewBox="0 0 24 24"><path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"/><circle cx="7" cy="7" r="1"/></svg>
                  Manage Roles
                </button>
              </div>
            </td>
          </tr>
          @endforeach

<script>
  // Client-side filtering: search, role, and status.
  (function() {
    var searchInput = document.getElementById('experts-search-input');
    var roleFilter = document.getElementById('experts-role-filter');
    var statusFilter = document.getElementById('statusFilter');
    var rows = document.querySelectorAll('table.table tbody tr');

    function filterRows() {
      var q = searchInput ? searchInput.value.toLowerCase().trim() : '';
      var role = roleFilter ? roleFilter.value : 'All Roles';
      var status = statusFilter ? statusFilter.value : 'all';

      rows.forEach(function(row) {
        var name = (row.querySelector('.td-main')?.textContent || '').toLowerCase();
        var email = (row.querySelector('td:first-child div[style*="font-size:11px"]')?.textContent || '').toLowerCase();
        var expertise = (row.querySelector('td[data-label="Expertise"]')?.textContent || '').toLowerCase();
        var roleTd = row.querySelector('td[data-label="Role"]');
        var roleBadges = roleTd ? roleTd.querySelectorAll('.badge-gold') : [];
        var roleText = '';
        roleBadges.forEach(function(b) { roleText += b.textContent.trim().toLowerCase() + ' '; });

        var badge = row.querySelector('td[data-label="Status"] span');
        var statusText = badge ? badge.textContent.trim().toLowerCase() : '';

        var matchSearch = !q || name.includes(q) || email.includes(q) || expertise.includes(q);
        var matchRole = role === 'All Roles' || roleText.includes(role.toLowerCase());
        var matchStatus = true;
        if (status === 'active') matchStatus = statusText === 'active';
        else if (status === 'full') matchStatus = statusText === 'full';
        else if (status === 'offline') matchStatus = statusText === 'offline';

        row.style.display = (matchSearch && matchRole && matchStatus) ? '' : 'none';
      });
    }

    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (roleFilter) roleFilter.addEventListener('change', filterRows);
    if (statusFilter) statusFilter.addEventListener('change', filterRows);
  })();
</script>
        </tbody>
      </table>
    </div>
  </div>
  </div><!-- /real-content -->
</div>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

{{-- Toast container --}}
<div id="ct-toast-container"></div>

{{-- ══════════════════════════════════════════
     EXPERT DETAILS — Modal
     Opens on clicking an expert's name/avatar in the table. Shows the
     full, untruncated personal info the row itself can't fit.
══════════════════════════════════════════ --}}
<div id="expertDetailModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card" style="max-width:460px;text-align:left;" role="dialog" aria-label="Expert details">
    <div class="expert-detail-head">
      <img id="detailAvatar" src="" alt="" class="expert-detail-avatar">
      <div>
        <div class="expert-detail-name" id="detailName"></div>
        <div class="expert-detail-email" id="detailEmail"></div>
      </div>
      <button type="button" class="expert-detail-close" onclick="closeExpertDetail()" aria-label="Close">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="expert-detail-body">
      <div id="detailBadges" class="expert-detail-badges"></div>
      <div>
        <div class="expert-detail-field-label">Credentials</div>
        <div class="expert-detail-field-value" id="detailCredentials">—</div>
      </div>
      <div>
        <div class="expert-detail-field-label">Program</div>
        <div class="expert-detail-field-value" id="detailProgram">—</div>
      </div>
      <div id="detailSectionsWrap" style="display:none">
        <div class="expert-detail-field-label">Sections</div>
        <div class="expert-detail-field-value" id="detailSections">—</div>
      </div>
      <div>
        <div class="expert-detail-field-label">Expertise</div>
        <div class="expert-detail-field-value" id="detailExpertise">—</div>
      </div>
      <div>
        <div class="expert-detail-field-label">Capacity</div>
        <div class="expert-detail-field-value" id="detailCapacity">—</div>
      </div>
    </div>
    <div class="ct-modal-actions" style="margin-top:20px;">
      <button type="button" class="ct-btn ct-btn-secondary" onclick="closeExpertDetail()">Close</button>
    </div>
  </div>
</div>

{{-- ══════════════════════════════════════════
     MANAGE ROLES — Modal
     The one action this page has. Role assignment used to only happen
     once, at account approval - this is the same set of roles, just
     reachable any time afterward. Panel Member isn't offered here since
     it's assigned per-defense, not to the account itself.
══════════════════════════════════════════ --}}
<div id="rolesModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card" style="max-width:420px;">
    <h3 class="ct-modal-title">Manage Roles for <span id="rolesExpertName" style="color:#2563eb;"></span></h3>
    <p class="ct-modal-desc">What this expert does for a team - they can hold more than one at once.</p>
    <div class="role-options" id="roleOptionsList"></div>
    <p id="rolesModalError" style="display:none;color:#dc2626;font-size:12px;margin:14px 0 0;text-align:left;"></p>
    <div class="ct-modal-actions" style="margin-top:20px;">
      <button type="button" class="ct-btn ct-btn-secondary" onclick="closeRolesModal()">Cancel</button>
      <button type="button" id="rolesSaveBtn" class="ct-btn ct-btn-primary" onclick="saveRoles()">Save Roles</button>
    </div>
  </div>
</div>

<script>
const ROLE_OPTIONS = [
  { id: 'adviser', label: 'Capstone Adviser', desc: "Primary subject adviser for a team's capstone project." },
  { id: 'consultant', label: 'Consultant', desc: 'Secondary technical/content guidance alongside the adviser.' },
  { id: 'grammarian', label: 'Grammarian', desc: 'Reviews document language and formatting before submission.' },
];

const rolesModal = document.getElementById('rolesModal');
let rolesTargetExpertId = null;

function openRoles(expertId, expertName, currentRoles) {
  rolesTargetExpertId = expertId;
  document.getElementById('rolesExpertName').textContent = expertName;
  document.getElementById('rolesModalError').style.display = 'none';

  document.getElementById('roleOptionsList').innerHTML = ROLE_OPTIONS.map(r => `
    <label class="role-opt">
      <input type="checkbox" value="${r.id}" ${currentRoles.includes(r.id) ? 'checked' : ''}>
      <div>
        <div class="role-opt-name">${r.label}</div>
        <div class="role-opt-desc">${r.desc}</div>
      </div>
    </label>
  `).join('');

  rolesModal.style.display = 'flex';
}

function closeRolesModal() {
  rolesModal.style.display = 'none';
}

function saveRoles() {
  const checked = Array.from(document.querySelectorAll('#roleOptionsList input:checked')).map(c => c.value);
  const errorEl = document.getElementById('rolesModalError');
  const saveBtn = document.getElementById('rolesSaveBtn');

  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving...';

  fetch(`{{ route('admin.experts.update-roles', ['expert' => 'EXPERT_ID']) }}`.replace('EXPERT_ID', rolesTargetExpertId), {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    body: JSON.stringify({ role_ids: checked.map(id => ROLE_NAME_TO_ID[id]).filter(Boolean) }),
  })
    .then(res => res.json().then(data => ({ status: res.status, data })))
    .then(({ status, data }) => {
      if (status === 200 && data.success) {
        closeRolesModal();
        showToast(data.message, 'success');
        setTimeout(() => window.location.reload(), 700);
      } else {
        errorEl.textContent = data.message || 'Could not save roles.';
        errorEl.style.display = 'block';
      }
    })
    .catch(() => {
      errorEl.textContent = 'Network error while saving.';
      errorEl.style.display = 'block';
    })
    .finally(() => {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save Roles';
    });
}

@php
  $roleNameToId = DB::table('roles')->whereIn('role_name', ['adviser', 'consultant', 'grammarian'])->pluck('role_id', 'role_name');
@endphp
const ROLE_NAME_TO_ID = {{ Illuminate\Support\Js::from($roleNameToId) }};
</script>

{{-- ══════════════════════════════════════════
     UPLOAD EXPERT ROSTER — Modal
══════════════════════════════════════════ --}}
<div id="expertRosterModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card" style="max-width:480px;">
    <h3 class="ct-modal-title">Upload Expert List</h3>
    <p class="ct-modal-desc" style="text-align:left;">
      One .xlsx file with 3 sheets named <strong>Adviser</strong>, <strong>Consultant</strong>, and <strong>Grammarian</strong>. Each sheet needs a <strong>Name</strong> and <strong>Email Address</strong> column (any order).
      Everyone on the list is approved right away and can sign in with that exact CSPC email - they'll fill in their own accepted program(s), areas of expertise, and team capacity from their profile afterward.
      This only creates the account - it doesn't assign a role. Use <strong>Manage Roles</strong> on each new person afterward to set what they'll do.
    </p>
    <input type="file" id="expertRosterFile" class="ct-modal-input" accept=".xlsx">
    <div id="expertRosterResult" style="display:none;font-size:12px;text-align:left;margin:-6px 0 16px;"></div>
    <div class="ct-modal-actions">
      <button type="button" class="ct-btn ct-btn-secondary" onclick="closeExpertRosterModal()">Cancel</button>
      <button type="button" id="expertRosterUploadBtn" class="ct-btn ct-btn-primary" onclick="uploadExpertRoster()">Upload</button>
    </div>
  </div>
</div>

<script>
const expertRosterModal = document.getElementById('expertRosterModal');

function openExpertRosterModal() {
  document.getElementById('expertRosterFile').value = '';
  document.getElementById('expertRosterResult').style.display = 'none';
  expertRosterModal.style.display = 'flex';
}

function closeExpertRosterModal() {
  expertRosterModal.style.display = 'none';
}

function uploadExpertRoster() {
  const fileInput = document.getElementById('expertRosterFile');
  const resultBox = document.getElementById('expertRosterResult');

  if (!fileInput.files.length) {
    resultBox.style.display = 'block';
    resultBox.innerHTML = '<span style="color:#dc2626">Choose a file first.</span>';
    return;
  }

  const btn = document.getElementById('expertRosterUploadBtn');
  btn.disabled = true;
  btn.textContent = 'Uploading...';

  const formData = new FormData();
  formData.append('file', fileInput.files[0]);

  fetch('{{ route('admin.rosters.experts.store') }}', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    body: formData,
  })
    .then(r => r.json())
    .then(data => {
      resultBox.style.display = 'block';

      if (!data.success) {
        resultBox.innerHTML = `<span style="color:#dc2626">${data.message || 'Upload failed.'}</span>`;
        return;
      }

      let html = `<strong>${data.inserted}</strong> created, <strong>${data.updated}</strong> updated, <strong>${data.skipped}</strong> skipped.`;
      if (data.errors && data.errors.length) {
        html += '<div style="max-height:160px;overflow-y:auto;color:var(--text3);margin-top:6px">' +
          data.errors.map(e => `<div>${e}</div>`).join('') + '</div>';
      }
      resultBox.innerHTML = html;
      showToast('Expert list uploaded.', 'success');
      setTimeout(() => window.location.reload(), 1200);
    })
    .catch(() => {
      resultBox.style.display = 'block';
      resultBox.innerHTML = '<span style="color:#dc2626">Network error while uploading.</span>';
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = 'Upload';
    });
}
</script>

<script>
// ── TOAST NOTIFICATION ───────────────────────────────────────
function showToast(message, type = 'info', title = null) {
  const icons = {
    success: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`,
    error:   `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
    info:    `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
  };
  const defaultTitles = { success: 'Success', error: 'Error', info: 'Info' };

  const toast = document.createElement('div');
  toast.className = 'ct-toast';
  toast.innerHTML = `
    <div class="ct-toast-icon ${type}">${icons[type] || icons.info}</div>
    <div class="ct-toast-body">
      <p class="ct-toast-title">${title || defaultTitles[type]}</p>
      <p class="ct-toast-msg">${message}</p>
    </div>
    <button class="ct-toast-close" onclick="this.closest('.ct-toast').remove()">&times;</button>
  `;

  document.getElementById('ct-toast-container').appendChild(toast);

  setTimeout(() => {
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 200);
  }, 4000);
}

// ── EXPERT DETAILS DETAILS ────────────────────────────────────
const expertDetailModal = document.getElementById('expertDetailModal');

function openExpertDetail(data) {
  document.getElementById('detailAvatar').src = data.avatar;
  document.getElementById('detailName').textContent = data.name;
  document.getElementById('detailEmail').textContent = data.email;
  document.getElementById('detailCredentials').textContent = data.credentials || 'None on file';
  document.getElementById('detailProgram').textContent = data.program || 'Not set';
  document.getElementById('detailExpertise').textContent = data.expertise.length ? data.expertise.join(', ') : 'None specified';
  document.getElementById('detailCapacity').textContent = data.capacity.length ? data.capacity.join(' · ') : 'Not set';

  const sectionsWrap = document.getElementById('detailSectionsWrap');
  if (data.is_capsa) {
    sectionsWrap.style.display = '';
    document.getElementById('detailSections').textContent = data.sections.length ? data.sections.join(', ') : 'None set';
  } else {
    sectionsWrap.style.display = 'none';
  }

  // No separate "CAPSA" chip - is_capsa stays synced with the
  // capstone_adviser role, so data.roles already says "Capstone Subject
  // Adviser" for a CaPSA account. (Admin accounts never appear in this
  // list at all, so there's no is_admin case to handle here either.)
  const badges = [];
  data.roles.forEach(r => badges.push(r));
  if (!badges.length) badges.push('No Role');
  document.getElementById('detailBadges').innerHTML = badges.map(label => `<span class="expert-detail-chip">${label}</span>`).join('');

  expertDetailModal.style.display = 'flex';
}

function closeExpertDetail() {
  expertDetailModal.style.display = 'none';
}

// Click the dimmed backdrop to dismiss, matching the other modals here.
expertDetailModal.addEventListener('click', function (e) {
  if (e.target === expertDetailModal) closeExpertDetail();
});

// ── SKELETON SWAP ──────────────────────────────────────────────
// Fake-load only the FIRST time this page is opened in a tab. Every CRUD
// action here ends in window.location.reload(), and replaying a 400ms
// skeleton on each of those just puts a stutter between clicking Save and
// seeing the result. Same guard the shared skeleton-toggle partial uses -
// sessionStorage can throw (private mode / blocked cookies), so fall back
// to revealing immediately rather than leaving the skeleton stuck up.
(function() {
  var sk = document.getElementById('skeleton-content');
  var rc = document.getElementById('real-content');
  if (!sk || !rc) return;

  var reveal = function () {
    sk.style.display = 'none';
    rc.style.display = 'flex';
  };

  // Scoped to THIS login: session()->getId() changes when the session is
  // invalidated at logout, so signing back in shows the skeleton again on
  // first visit - without needing every logout button to remember to clear
  // sessionStorage (several of them didn't).
  var seenKey = 'sk-seen:{{ substr(sha1(session()->getId()), 0, 12) }}:' + window.location.pathname;
  var alreadySeen = false;
  try { alreadySeen = !!sessionStorage.getItem(seenKey); } catch (e) {}

  if (alreadySeen) {
    reveal();
  } else {
    setTimeout(function () {
      reveal();
      try { sessionStorage.setItem(seenKey, '1'); } catch (e) {}
    }, 400);
  }
})();

/* ── Mobile sidebar toggle ── */
var sidebarEl = document.querySelector('.sidebar');
var sidebarOverlay = document.getElementById('sidebar-overlay');
function toggleSidebar() {
  if (!sidebarEl) return;
  sidebarEl.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
}
function openSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.add('mobile-open');
  sidebarOverlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  if (!sidebarEl || !sidebarOverlay) return;
  sidebarEl.classList.remove('mobile-open');
  sidebarOverlay.classList.remove('open');
  document.body.style.overflow = '';
}
</script>

</body>
</html>
