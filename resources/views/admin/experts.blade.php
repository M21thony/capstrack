<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Expert Management - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
  /* Expert Management (Admin's copy - full CRUD + Manage Roles). CaPSA has
     its own separate, leaner copy at resources/views/capsa/experts.blade.php
     (view + Manage Roles only) - these two used to be one file switching on
     viewer role, which made neither version easy to read on its own. */
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

  /* Direct icon buttons - one per action, no dropdown. Each is tooltipped
     via its title attribute since there's no room for a label. */
  .icon-btn{
    width:32px;height:32px;border-radius:8px;border:1.5px solid var(--border);background:#fff;
    color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;
  }
  .icon-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.3}
  .icon-btn:hover{border-color:var(--border2);background:var(--bg3)}
  .icon-btn.capsa{border-color:var(--blue-bg);background:var(--blue-bg);color:var(--blue)}
  .icon-btn.capsa:hover{border-color:var(--blue);background:var(--blue);color:#fff}
  .icon-btn.danger{border-color:var(--red-bg);background:var(--red-bg);color:var(--red)}
  .icon-btn.danger:hover{border-color:var(--red);background:var(--red);color:#fff}

  /* Capacity column - merges what used to be separate Teams / Slots Left
     columns. One row per program when an expert's slots are actually split
     that way (see CapstoneExpert::parsedCapacity()), one "ALL" row when
     they're not. */
  .capacity-stack{display:flex;flex-direction:column;gap:5px}
  .capacity-row{display:grid;grid-template-columns:38px 1fr 34px;align-items:center;gap:8px}
  .capacity-prog{font-size:10px;font-weight:700;color:var(--text3);letter-spacing:0.3px}
  .capacity-count{font-family:'Space Mono',monospace;font-size:11px;color:var(--text3);text-align:right}

  /* Manage Roles - shared with CaPSA's copy of this page. */
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

  .icon-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
  }


  /* ── Modal overlay ── */
  .ct-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(5px);
    display: flex; align-items: safe center; justify-content: center;
    z-index: 9999;
    animation: ctFadeIn 0.18s ease;
    /* "safe center" centers a modal that fits, but falls back to
       top-aligned once it's taller than the viewport - the Make CaPSA
       modal can now genuinely grow past screen height with a lot of
       sections expanded, and plain "center" would clip its top out of
       reach with no way to scroll to it. */
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

  /* ── Modal icon circle ── */
  .ct-modal-icon {
    width: 52px; height: 52px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
  }
  .ct-modal-icon.danger  { background: #fee2e2; color: #ef4444; }
  .ct-modal-icon.warning { background: #fef9c3; color: #d97706; }

  /* ── Modal text ── */
  .ct-modal-title {
    margin: 0 0 8px;
    font-size: 18px; font-weight: 700; color: #0f172a;
  }
  .ct-modal-desc {
    font-size: 13.5px; color: #64748b; line-height: 1.5;
    margin: 0 0 20px;
  }

  /* ── Text input in modal ── */
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
  .ct-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 12px;text-align:left}
  .ct-modal-grid .full{grid-column:1 / -1}

  /* Section picker (Make CaPSA modal). Grouped by program with a
     collapsed-by-default body and a per-group "select all" - a CaPSA
     covering every section of a program is one click on that group, not
     checking each section one at a time. Deliberately its own top-level
     component, not nested under .ct-modal-label/.ct-modal-input - a
     compound selector on a wrapper class can silently outrank a
     single-class selector here and break the layout. */
  .section-field{margin-bottom:16px;text-align:left}
  .section-count{font-size:11px;font-weight:700;color:var(--accent3);background:var(--accent-light);padding:3px 9px;border-radius:999px}
  .section-search{position:relative}
  .section-search svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:13px;height:13px;stroke:#94a3b8;fill:none;stroke-width:2.2}
  .section-search input{width:100%;box-sizing:border-box;padding:8px 10px 8px 30px;border:1.5px solid #e2e8f0;border-radius:8px;background:#f8fafc;font-size:12.5px;font-family:inherit;color:#1e293b;outline:none}
  .section-search input:focus{border-color:#94a3b8;background:#fff}

  .section-bulk-row{display:flex;align-items:center;gap:8px;margin-top:8px}
  .section-bulk-row button{background:none;border:none;padding:0;font-size:11.5px;font-weight:700;color:#2563eb;cursor:pointer;font-family:'DM Sans',sans-serif}
  .section-bulk-row button:hover{text-decoration:underline}
  .section-bulk-row span{color:#cbd5e1;font-size:11px}

  .section-preview{display:flex;flex-wrap:wrap;align-content:flex-start;gap:7px;min-height:34px;max-height:96px;overflow-y:auto;padding:10px;border:1.5px dashed #e2e8f0;border-radius:10px;scrollbar-width:none}
  .section-preview::-webkit-scrollbar{display:none}
  .section-preview-empty{font-size:12px;color:#94a3b8;padding:3px 2px}
  .section-preview-tag{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;padding:5px 6px 5px 11px;border-radius:999px;background:var(--blue-bg);color:var(--blue)}
  .section-preview-tag button{background:none;border:none;color:inherit;opacity:0.6;cursor:pointer;display:flex;padding:2px}
  .section-preview-tag button:hover{opacity:1}
  .section-preview-tag button svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5}

  .section-list{max-height:260px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;margin-bottom:4px;padding-right:2px;scrollbar-width:none}
  .section-list::-webkit-scrollbar{display:none}
  .section-group{border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden;flex-shrink:0}
  .section-group-header{display:flex;align-items:center;gap:8px;padding:9px 11px;cursor:pointer;background:#f8fafc;user-select:none}
  .section-group-header:hover{background:#f1f5f9}
  .group-chevron{width:12px;height:12px;stroke:#94a3b8;fill:none;stroke-width:2.4;flex-shrink:0;transition:transform .15s}
  .section-group.open .group-chevron{transform:rotate(90deg)}
  .group-all{width:15px;height:15px;accent-color:var(--accent);flex-shrink:0}
  .group-name{font-size:12.5px;font-weight:700;color:#0f172a;letter-spacing:0.2px}
  .group-count{margin-left:auto;font-size:10px;font-weight:700;color:#94a3b8;font-family:'Space Mono',monospace;background:#eceef2;padding:2px 8px;border-radius:999px}
  .section-group.all-selected .group-count{background:var(--accent-light);color:var(--accent3)}
  .section-group-body{display:none;grid-template-columns:repeat(3, 1fr);gap:5px 8px;padding:6px 8px 8px 33px}
  .section-group.open .section-group-body{display:grid}

  .section-opt{display:flex;align-items:center;gap:8px;padding:5px 9px;border:1px solid transparent;border-radius:7px;cursor:pointer;transition:all .12s;text-align:left}
  .section-opt:hover{background:#f1f5f9}
  .section-opt:has(input:checked):hover{background:var(--accent-light)}
  .section-opt:has(input:checked){border-color:var(--accent);background:var(--accent-light)}
  .section-opt input{width:14px;height:14px;accent-color:var(--accent);flex-shrink:0}
  .section-opt span{font-size:12px;font-weight:600;color:#0f172a}

  /* ── Modal action buttons ── */
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
  .ct-btn-danger { background: #dc2626; color: #fff; }
  .ct-btn-danger:hover:not(:disabled) { background: #b91c1c; }
  .ct-btn-danger:disabled { background: #f1f5f9; color: #cbd5e1; cursor: not-allowed; }
  .ct-btn-warning { background: #d97706; color: #fff; }
  .ct-btn-warning:hover { background: #b45309; }
  .ct-btn-primary { background: #2563eb; color: #fff; }
  .ct-btn-primary:hover:not(:disabled) { background: #1d4ed8; }
  .ct-btn-primary:disabled { background: #f1f5f9; color: #cbd5e1; cursor: not-allowed; }

  /* ── Animations ── */
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
  /* Skeleton styles now live in admin.css, shared across every admin page */
</style>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
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
        <div class="sk" style="height:38px;width:160px;border-radius:20px;"></div>
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
      <button type="button" class="btn btn-primary" onclick="openExpertModal()">+ Add Expert</button>
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

                <button type="button" class="icon-btn capsa" title="{{ $expert->is_capsa ? 'Remove CaPSA' : 'Make CaPSA' }}"
                        onclick="toggleCapsa({{ $expert->expert_id }}, {{ $expert->is_capsa ? 'true' : 'false' }}, {{ Illuminate\Support\Js::from($expert->name) }})">
                  @if ($expert->is_capsa)
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
                  @else
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
                  @endif
                </button>

                <button type="button" class="icon-btn danger remove-trigger" title="Remove" data-id="{{ $expert->expert_id }}" data-name="{{ $expert->name }}">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M7 6l1 14h8l1-14"/><path d="M10 10v6"/><path d="M14 10v6"/></svg>
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

        var badge = row.querySelector('td:nth-child(6) span, td[data-label="Status"] span');
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
     REMOVE EXPERT — Confirmation Modal
══════════════════════════════════════════ --}}
<div id="removeModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card">
    <div class="ct-modal-icon danger">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <polyline points="3 6 5 6 21 6"/>
        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
        <line x1="10" y1="11" x2="10" y2="17"/>
        <line x1="14" y1="11" x2="14" y2="17"/>
      </svg>
    </div>
    <h3 class="ct-modal-title">Remove Expert</h3>
    <p class="ct-modal-desc">
      You are about to permanently remove <strong id="removeExpertName" style="color:#0f172a;"></strong> from the system.<br>
      This action <span style="color:#ef4444;font-weight:600;">cannot be undone</span>.
    </p>
    <div style="margin-bottom:16px;text-align:left;">
      <label class="ct-modal-label">
        Reason for removal <span style="color:#ef4444;font-weight:400;">(sent to affected teams)</span>
      </label>
      <textarea id="removeReasonInput" class="ct-modal-input" rows="3" placeholder="Why is this expert being removed? This message will be sent to all affected team members..." style="resize:vertical;min-height:60px;font-family:inherit;"></textarea>
    </div>
    <div style="margin-bottom:20px;text-align:left;">
      <label class="ct-modal-label">
        Type <code style="background:#fee2e2;color:#dc2626;padding:2px 6px;border-radius:4px;font-family:monospace;">remove</code> to confirm
      </label>
      <input type="text" id="removeConfirmInput" class="ct-modal-input" placeholder="type remove" autocomplete="off">
    </div>
    <div class="ct-modal-actions">
      <button type="button" id="removeCancelBtn" class="ct-btn ct-btn-secondary">Cancel</button>
      <button type="button" id="removeConfirmBtn" class="ct-btn ct-btn-danger" disabled>Delete</button>
    </div>
  </div>
</div>

{{-- ══════════════════════════════════════════
     MAKE CAPSA — Section Assignment Modal
══════════════════════════════════════════ --}}
<div id="capsaModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card" style="max-width:520px;">
    <div class="ct-modal-icon" style="background:#dbeafe;color:#2563eb;">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/>
      </svg>
    </div>
    <h3 class="ct-modal-title">Make <span id="capsaExpertName" style="color:#2563eb;"></span> a CaPSA</h3>
    <p class="ct-modal-desc">
      CaPSA duties (account approval, panel assignment, team verification, financial checks) are scoped to the section(s) assigned below.
    </p>

    <div class="section-field">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
        <label class="ct-modal-label" style="margin:0">Section(s) to assign <span style="color:#ef4444;font-weight:400;">(required)</span></label>
        <span class="section-count" id="sectionCount">0 / {{ $groupedSections->flatten()->count() }}</span>
      </div>
      <div class="section-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input id="sectionSearch" placeholder="Search sections…" oninput="filterSections(this.value)" autocomplete="off">
      </div>
      <div class="section-bulk-row">
        <button type="button" onclick="selectAllSections()">Select all</button>
        <span>·</span>
        <button type="button" onclick="clearAllSections()">Clear all</button>
      </div>
    </div>

    <div class="section-field">
      <label class="ct-modal-label" style="margin-bottom:6px">This CaPSA will cover</label>
      <div class="section-preview" id="selectedPreview"><span class="section-preview-empty">Nothing selected yet.</span></div>
    </div>

    <div class="section-list" id="sectionList">
      @forelse ($groupedSections as $program => $sections)
        <div class="section-group" data-program="{{ strtolower($program) }}">
          <div class="section-group-header" onclick="toggleGroup(this)">
            <svg class="group-chevron" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            <input type="checkbox" class="group-all" onclick="event.stopPropagation()" onchange="toggleGroupAll(this)">
            <span class="group-name">{{ $program }}</span>
            <span class="group-count">0/{{ $sections->count() }}</span>
          </div>
          <div class="section-group-body">
            @foreach ($sections as $section)
              <label class="section-opt" data-name="{{ strtolower($section) }}"><input type="checkbox" onchange="onSectionChange(this)"><span>{{ $section }}</span></label>
            @endforeach
          </div>
        </div>
      @empty
        <p style="font-size:12.5px;color:#94a3b8;text-align:left;padding:8px 2px;">No sections found yet - they're derived from enrolled students, so this fills in once the student roster has course/section data.</p>
      @endforelse
    </div>
    <p id="capsaSectionsError" style="display:none;color:#dc2626;font-size:12px;margin-top:6px;text-align:left;">Select at least one section.</p>

    <div class="ct-modal-actions">
      <button type="button" id="capsaCancelBtn" class="ct-btn ct-btn-secondary">Cancel</button>
      <button type="button" id="capsaConfirmBtn" class="ct-btn ct-btn-primary">Make CaPSA</button>
    </div>
  </div>
</div>

{{-- ══════════════════════════════════════════
     REMOVE CAPSA — Confirmation Modal
══════════════════════════════════════════ --}}
<div id="removeCapsaModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card">
    <div class="ct-modal-icon warning">
      <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="11" x2="23" y2="11"/>
      </svg>
    </div>
    <h3 class="ct-modal-title">Remove CaPSA from <span id="removeCapsaExpertName" style="color:#d97706;"></span>?</h3>
    <p class="ct-modal-desc">
      They'll lose access to account approval, panel assignment, team verification, and financial checks for their assigned section(s). This can be undone later by making them CaPSA again.
    </p>
    <div class="ct-modal-actions">
      <button type="button" id="removeCapsaCancelBtn" class="ct-btn ct-btn-secondary">Cancel</button>
      <button type="button" id="removeCapsaConfirmBtn" class="ct-btn ct-btn-warning">Remove CaPSA</button>
    </div>
  </div>
</div>

{{-- ══════════════════════════════════════════
     ADD / EDIT EXPERT — Form Modal
══════════════════════════════════════════ --}}
<div id="expertModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card" style="max-width:480px;">
    <h3 class="ct-modal-title" id="expertModalTitle">Add Expert</h3>
    <div class="ct-modal-grid">
      <div class="full">
        <label class="ct-modal-label">Email <span style="color:#ef4444;font-weight:400;">*</span></label>
        <input type="email" id="expertEmailInput" class="ct-modal-input" placeholder="juan.delacruz@cspc.edu.ph">
      </div>
      <div>
        <label class="ct-modal-label">First Name <span style="color:#ef4444;font-weight:400;">*</span></label>
        <input type="text" id="expertFirstNameInput" class="ct-modal-input" placeholder="Juan">
      </div>
      <div>
        <label class="ct-modal-label">Last Name <span style="color:#ef4444;font-weight:400;">*</span></label>
        <input type="text" id="expertLastNameInput" class="ct-modal-input" placeholder="Dela Cruz">
      </div>
      <div>
        <label class="ct-modal-label">Middle Name</label>
        <input type="text" id="expertMiddleNameInput" class="ct-modal-input" placeholder="Optional">
      </div>
      <div>
        <label class="ct-modal-label">Suffix</label>
        <input type="text" id="expertSuffixInput" class="ct-modal-input" placeholder="Jr., III...">
      </div>
      <div>
        <label class="ct-modal-label">Credentials</label>
        <input type="text" id="expertCredentialsInput" class="ct-modal-input" placeholder="e.g. MIT, LPT">
      </div>
      <div>
        <label class="ct-modal-label">Program</label>
        <input type="text" id="expertProgramInput" class="ct-modal-input" placeholder="e.g. BSIT">
      </div>
      <div class="full">
        <label class="ct-modal-label">Max Teams</label>
        <input type="number" id="expertMaxTeamsInput" class="ct-modal-input" placeholder="4" min="0">
      </div>
    </div>
    <p id="expertModalError" style="display:none;color:#dc2626;font-size:12px;margin:-10px 0 16px;text-align:left;"></p>
    <div class="ct-modal-actions">
      <button type="button" class="ct-btn ct-btn-secondary" onclick="closeExpertModal()">Cancel</button>
      <button type="button" id="expertSaveBtn" class="ct-btn ct-btn-primary" onclick="saveExpert()">Save</button>
    </div>
  </div>
</div>

<script>
// ── ADD / EDIT EXPERT MODAL ─────────────────────────────────
let editingExpertId = null;
const expertModal = document.getElementById('expertModal');

function openExpertModal(expert = null) {
  editingExpertId = expert ? expert.id : null;
  document.getElementById('expertModalTitle').textContent = expert ? 'Edit Expert' : 'Add Expert';
  document.getElementById('expertEmailInput').value = expert?.email || '';
  document.getElementById('expertFirstNameInput').value = expert?.first_name || '';
  document.getElementById('expertLastNameInput').value = expert?.last_name || '';
  document.getElementById('expertMiddleNameInput').value = expert?.middle_name || '';
  document.getElementById('expertSuffixInput').value = expert?.suffix || '';
  document.getElementById('expertCredentialsInput').value = expert?.credentials || '';
  document.getElementById('expertProgramInput').value = expert?.program || '';
  document.getElementById('expertMaxTeamsInput').value = expert?.max_teams ?? '';
  document.getElementById('expertModalError').style.display = 'none';
  expertModal.style.display = 'flex';
}

function closeExpertModal() {
  expertModal.style.display = 'none';
}

function saveExpert() {
  const errorEl = document.getElementById('expertModalError');
  const maxTeamsRaw = document.getElementById('expertMaxTeamsInput').value.trim();
  const payload = {
    email: document.getElementById('expertEmailInput').value.trim(),
    first_name: document.getElementById('expertFirstNameInput').value.trim(),
    last_name: document.getElementById('expertLastNameInput').value.trim(),
    middle_name: document.getElementById('expertMiddleNameInput').value.trim() || null,
    suffix: document.getElementById('expertSuffixInput').value.trim() || null,
    credentials: document.getElementById('expertCredentialsInput').value.trim() || null,
    program: document.getElementById('expertProgramInput').value.trim() || null,
    max_teams: maxTeamsRaw === '' ? null : parseInt(maxTeamsRaw, 10),
  };

  if (!payload.email || !payload.first_name || !payload.last_name) {
    errorEl.textContent = 'Email, first name, and last name are required.';
    errorEl.style.display = 'block';
    return;
  }

  const saveBtn = document.getElementById('expertSaveBtn');
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving...';

  const url = editingExpertId ? `/admin/experts/${editingExpertId}` : '/admin/experts';
  const method = editingExpertId ? 'PUT' : 'POST';

  fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then(res => res.json().then(data => ({ status: res.status, data })))
    .then(({ status, data }) => {
      if (status === 200 && data.success) {
        closeExpertModal();
        showToast(data.message, 'success');
        setTimeout(() => window.location.reload(), 700);
      } else {
        errorEl.textContent = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not save this expert.');
        errorEl.style.display = 'block';
      }
    })
    .catch(() => {
      errorEl.textContent = 'Network error while saving.';
      errorEl.style.display = 'block';
    })
    .finally(() => {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save';
    });
}
</script>

{{-- ══════════════════════════════════════════
     MANAGE ROLES — Modal
     Role assignment used to only happen once, at account approval - this is
     the same set of roles, just reachable any time afterward. Panel Member
     isn't offered here since it's assigned per-defense, not to the account
     itself.
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

  // Auto-dismiss after 4s
  setTimeout(() => {
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 200);
  }, 4000);
}

// ── REMOVE EXPERT ─────────────────────────────────────────────
const removeModal = document.getElementById('removeModal');
const removeInput = document.getElementById('removeConfirmInput');
const removeReason = document.getElementById('removeReasonInput');
const removeBtn   = document.getElementById('removeConfirmBtn');

document.querySelectorAll('.remove-trigger').forEach(btn => {
  btn.addEventListener('click', function () {
    removeModal.dataset.expertId = this.dataset.id;
    document.getElementById('removeExpertName').textContent = this.dataset.name;
    removeInput.value = '';
    removeReason.value = '';
    removeBtn.disabled = true;
    removeBtn.textContent = 'Delete';
    removeModal.style.display = 'flex';
    setTimeout(() => removeReason.focus(), 60);
  });
});

removeInput.addEventListener('input', function () {
  removeBtn.disabled = this.value.trim().toLowerCase() !== 'remove';
});

document.getElementById('removeCancelBtn').addEventListener('click', () => {
  removeModal.style.display = 'none';
});

removeBtn.addEventListener('click', function () {
  const expertId = removeModal.dataset.expertId;
  if (removeInput.value.trim().toLowerCase() !== 'remove') { return; }

  this.disabled = true;
  this.textContent = 'Deleting…';

  fetch(`{{ route('admin.experts.destroy', ['expert' => 'EXPERT_ID']) }}`.replace('EXPERT_ID', expertId), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify({
      _method: 'DELETE',
      reason: removeReason.value.trim(),
    }),
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast(data.message, 'success');
        setTimeout(() => window.location.reload(), 1500);
      } else {
        showToast(data.message || 'Failed to delete expert.', 'error');
        this.disabled = false;
        this.textContent = 'Delete';
      }
    })
    .catch(() => {
      showToast('Network error during deletion.', 'error');
      this.disabled = false;
      this.textContent = 'Delete';
    })
    .finally(() => { removeModal.style.display = 'none'; });
});

// Turning CAPSA on asks which section(s) this person covers (one CAPSA can
// cover several) via the #capsaModal below - a real modal instead of a
// native prompt(), since prompt() gives zero feedback on Cancel (the action
// just silently does nothing) and looks out of place next to the rest of
// this page's modals. Turning it off doesn't need section input, so it
// skips the modal and fires immediately.
const capsaModal = document.getElementById('capsaModal');
const capsaExpertName = document.getElementById('capsaExpertName');
const capsaSectionsError = document.getElementById('capsaSectionsError');
const capsaConfirmBtn = document.getElementById('capsaConfirmBtn');
let capsaTargetExpertId = null;

function toggleCapsa(expertId, isCurrentlyCapsa, expertName) {
  if (isCurrentlyCapsa) {
    removeCapsaTargetExpertId = expertId;
    document.getElementById('removeCapsaExpertName').textContent = expertName;
    document.getElementById('removeCapsaConfirmBtn').disabled = false;
    document.getElementById('removeCapsaConfirmBtn').textContent = 'Remove CaPSA';
    removeCapsaModal.style.display = 'flex';
    return;
  }

  capsaTargetExpertId = expertId;
  capsaExpertName.textContent = expertName;
  capsaSectionsError.style.display = 'none';
  resetSectionPicker();
  capsaModal.style.display = 'flex';
}

// Removing CaPSA isn't destructive (making them CaPSA again undoes it),
// but it does immediately cut off account approval/panel/verification/
// financial access, so it still deserves a confirmation step rather than
// firing the instant the icon is clicked.
const removeCapsaModal = document.getElementById('removeCapsaModal');
let removeCapsaTargetExpertId = null;

document.getElementById('removeCapsaCancelBtn').addEventListener('click', () => {
  removeCapsaModal.style.display = 'none';
  removeCapsaTargetExpertId = null;
});

document.getElementById('removeCapsaConfirmBtn').addEventListener('click', function () {
  const expertName = document.getElementById('removeCapsaExpertName').textContent;
  this.disabled = true;
  this.textContent = 'Removing...';
  sendToggleCapsa(removeCapsaTargetExpertId, null, 'Removed CaPSA from '.concat(expertName, '.'), () => {
    this.disabled = false;
    this.textContent = 'Remove CaPSA';
  });
});

document.getElementById('capsaCancelBtn').addEventListener('click', () => {
  capsaModal.style.display = 'none';
  capsaTargetExpertId = null;
});

capsaConfirmBtn.addEventListener('click', function () {
  const sections = [...document.querySelectorAll('#sectionList .section-opt input:checked')]
    .map(cb => cb.closest('.section-opt').querySelector('span').textContent);
  if (sections.length === 0) {
    capsaSectionsError.style.display = 'block';
    return;
  }
  capsaSectionsError.style.display = 'none';

  this.disabled = true;
  this.textContent = 'Saving...';
  sendToggleCapsa(capsaTargetExpertId, sections, 'CaPSA assigned successfully.', () => {
    this.disabled = false;
    this.textContent = 'Make CaPSA';
  });
});

// ── SECTION PICKER (Make CaPSA modal) ────────────────────────
// Grouped by program, collapsed by default, with a per-group "select
// all" - a CaPSA covering an entire program is one click, not checking
// every section in it one at a time.
function resetSectionPicker() {
  document.getElementById('sectionSearch').value = '';
  document.querySelectorAll('#sectionList .section-group').forEach(g => {
    g.style.display = '';
    g.classList.remove('open');
    g.querySelector('.group-all').checked = false;
    g.querySelector('.group-all').indeterminate = false;
  });
  document.querySelectorAll('#sectionList .section-opt').forEach(opt => {
    opt.style.display = '';
    opt.querySelector('input').checked = false;
  });
  updateAllSectionCounts();
}

function toggleGroup(header) {
  header.closest('.section-group').classList.toggle('open');
}

function toggleGroupAll(checkbox) {
  const group = checkbox.closest('.section-group');
  group.querySelectorAll('.section-opt input').forEach(cb => { cb.checked = checkbox.checked; });
  if (checkbox.checked) group.classList.add('open'); // see what you just picked, don't leave it collapsed
  checkbox.indeterminate = false;
  updateAllSectionCounts();
}

function onSectionChange(input) {
  const group = input.closest('.section-group');
  const boxes = group.querySelectorAll('.section-opt input');
  const checkedCount = group.querySelectorAll('.section-opt input:checked').length;
  const groupAll = group.querySelector('.group-all');
  groupAll.checked = checkedCount === boxes.length;
  groupAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
  updateAllSectionCounts();
}

function updateAllSectionCounts() {
  let grandTotal = 0, grandChecked = 0;
  document.querySelectorAll('#sectionList .section-group').forEach(group => {
    const boxes = group.querySelectorAll('.section-opt input');
    const checked = group.querySelectorAll('.section-opt input:checked').length;
    group.querySelector('.group-count').textContent = checked + '/' + boxes.length;
    group.classList.toggle('all-selected', checked > 0 && checked === boxes.length);
    grandTotal += boxes.length;
    grandChecked += checked;
  });
  document.getElementById('sectionCount').textContent = grandChecked + ' / ' + grandTotal;
  renderSelectedSectionPreview();
}

// The one place that shows the full pick at a glance without expanding
// every group - groups are collapsed by default specifically so this is
// what tells you what you're actually about to assign.
function renderSelectedSectionPreview() {
  const box = document.getElementById('selectedPreview');
  const checked = [...document.querySelectorAll('#sectionList .section-opt')].filter(opt => opt.querySelector('input').checked);
  if (!checked.length) {
    box.innerHTML = '<span class="section-preview-empty">Nothing selected yet.</span>';
    return;
  }
  box.innerHTML = checked.map(opt => {
    const label = opt.querySelector('span').textContent;
    return `<span class="section-preview-tag">${label}<button type="button" onclick="removeSelectedSection('${opt.dataset.name}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></span>`;
  }).join('');
}

function removeSelectedSection(name) {
  const opt = document.querySelector(`#sectionList .section-opt[data-name="${name}"]`);
  if (!opt) return;
  opt.querySelector('input').checked = false;
  onSectionChange(opt.querySelector('input'));
}

function selectAllSections() {
  document.querySelectorAll('#sectionList .section-opt input').forEach(cb => { cb.checked = true; });
  document.querySelectorAll('#sectionList .group-all').forEach(cb => { cb.checked = true; cb.indeterminate = false; });
  updateAllSectionCounts();
}

function clearAllSections() {
  document.querySelectorAll('#sectionList input[type=checkbox]').forEach(cb => { cb.checked = false; cb.indeterminate = false; });
  updateAllSectionCounts();
}

function filterSections(q) {
  q = q.trim().toLowerCase();
  document.querySelectorAll('#sectionList .section-group').forEach(group => {
    let anyMatch = false;
    group.querySelectorAll('.section-opt').forEach(opt => {
      const match = opt.dataset.name.includes(q);
      opt.style.display = match ? '' : 'none';
      if (match) anyMatch = true;
    });
    group.style.display = anyMatch ? '' : 'none';
    if (q && anyMatch) group.classList.add('open'); // surface matches instead of hiding them in a collapsed group
    else if (!q) group.classList.remove('open');
  });
}

function sendToggleCapsa(expertId, sections, successMessage, onDone) {
  fetch(`{{ route('admin.experts.toggle-capsa', ['expert' => 'EXPERT_ID']) }}`.replace('EXPERT_ID', expertId), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify(sections !== null ? { sections } : {}),
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        capsaModal.style.display = 'none';
        removeCapsaModal.style.display = 'none';
        showToast(successMessage, 'success');
        setTimeout(() => window.location.reload(), 700);
      } else {
        showToast(data.message || 'Could not update CAPSA status.', 'error');
      }
    })
    .catch(() => showToast('Network error updating CAPSA status.', 'error'))
    .finally(() => { if (onDone) onDone(); });
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
  // Adviser" for a CaPSA account.
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

{{-- ══════════════════════════════════════════
     UPLOAD EXPERT LIST — Modal
     Shared with CaPSA (resources/views/capsa/experts.blade.php). Admin has
     it too so a fresh system can be populated from the .xlsx without
     hand-typing anyone email - the upload creates approved accounts with
     NO roles, so it grants Admin no role-assignment power.
══════════════════════════════════════════ --}}
<div id="expertRosterModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card" style="max-width:480px;">
    <h3 class="ct-modal-title">Upload Expert List</h3>
    <p class="ct-modal-desc" style="text-align:left;">
      One .xlsx file with 3 sheets named <strong>Adviser</strong>, <strong>Consultant</strong>, and <strong>Grammarian</strong>. Each sheet needs a <strong>Name</strong> and <strong>Email Address</strong> column (any order).
      Everyone on the list is approved right away and can sign in with that exact CSPC email - they will fill in their own accepted program(s), areas of expertise, and team capacity from their profile afterward.
      This only creates the account - it does not assign a role. Use <strong>Manage Roles</strong> on each new person afterward to set what they will do.
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

</body>
</html>
