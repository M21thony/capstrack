<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Expert Management - CapsTRACK Admin</title>
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/admin.css') }}"/>
<link rel="stylesheet" href="{{ \App\Support\PublicAsset::url('css/table-kit.css') }}"/>
<style nonce="{{ Vite::cspNonce() }}">
  .ae-field{margin-bottom:14px}
  .ae-label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
            color:#64748b;margin-bottom:6px}
  .ae-req{color:#ef4444;font-weight:400;text-transform:none;letter-spacing:0}
  .ae-opt{color:#94a3b8;font-weight:400;text-transform:none;letter-spacing:0}
  .ae-roles{display:flex;flex-wrap:wrap;gap:6px}
  /* The whole box is the target, not a 14px square beside it. */
  .ae-role{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border:1.5px solid #e2e8f0;
           border-radius:8px;cursor:pointer;font-size:12.5px;font-weight:600;color:#475569;background:#fff;
           transition:border-color .12s,background .12s,color .12s}
  .ae-role:hover{border-color:#cbd5e1}
  .ae-role input{width:14px;height:14px;accent-color:#2563eb;cursor:pointer;margin:0}
  .ae-role:has(input:checked){border-color:#2563eb;background:rgba(29,78,216,0.06);color:#1d4ed8}
  .ae-note{font-size:11.5px;color:#94a3b8;line-height:1.5;margin:0 0 14px}
  /* Study leave: a quiet flag, not an alarm. */
  .ae-leave{display:inline-flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600;color:var(--text,#334155);cursor:pointer}
  .ae-leave input{width:15px;height:15px;accent-color:#b45309;margin:0;cursor:pointer}
  .ae-leave-hint{display:block;margin-top:3px;font-size:11.5px;color:#94a3b8;line-height:1.45}
  .cx-leave-tag{display:inline-block;margin-left:7px;padding:1px 7px;border-radius:999px;font-size:10px;font-weight:700;letter-spacing:.2px;vertical-align:1px;background:rgba(180,83,9,.09);color:#b45309;border:1px solid rgba(180,83,9,.18)}


  /* Both classes in the selector, so this beats .ct-modal-card wherever that
     is defined - on this page it is defined further down, and a single-class
     rule of equal weight coming later would otherwise win and squeeze the
     card back to the narrow dialog width. width as well as max-width,
     because the card is a flex item and a max-width alone leaves it free to
     shrink to fit its contents. */
  .ct-modal-card.ct-upload-card{width:100%;max-width:660px;text-align:left;padding:24px}
  .ct-up-head{display:flex;align-items:flex-start;gap:14px;margin-bottom:18px}
  .ct-up-head-icon{display:flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:50%;
                   background:#eff6ff;color:#2563eb;flex-shrink:0}
  .ct-up-head-text{display:flex;flex-direction:column;gap:4px;flex:1;min-width:0}
  .ct-up-head .ct-modal-title{margin-bottom:0;font-size:22px;letter-spacing:-0.4px}
  .ct-up-sub{font-size:13px;color:#64748b}
  .ct-up-close{background:none;border:none;padding:2px;cursor:pointer;color:#64748b;line-height:0;border-radius:6px}
  .ct-up-close:hover{color:#0f172a;background:#f1f5f9}

  .ct-up-hint{display:flex;align-items:center;gap:9px;padding:11px 14px;border-radius:10px;background:#f8fafc;
              font-size:13px;color:#475569;margin-bottom:18px;flex-wrap:wrap}
  .ct-up-hint > svg{color:#94a3b8;flex-shrink:0}
  .ct-template-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#2563eb;
                    text-decoration:none}
  .ct-template-link:hover{color:#1d4ed8;text-decoration:underline;text-underline-offset:3px}

  /* The drop zone is the body of the modal, so it is the thing the eye lands
     on and the thing a file can be thrown at without hunting for a button. */
  .ct-dropzone{display:flex;flex-direction:column;align-items:center;gap:10px;padding:32px 20px;
               border:1.5px dashed #cbd5e1;border-radius:12px;text-align:center;transition:border-color .15s,background .15s}
  .ct-dropzone.dragging{border-color:#2563eb;background:#eff6ff}
  .ct-drop-icon{display:flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;
                background:#eff6ff;color:#2563eb}
  .ct-drop-title{font-size:15px;font-weight:600;color:#0f172a}
  .ct-drop-or{font-size:13px;color:#94a3b8}
  .ct-drop-browse{padding:9px 22px;border:1.5px solid #2563eb;border-radius:9px;background:#fff;color:#2563eb;
                  font-family:inherit;font-size:13.5px;font-weight:600;cursor:pointer;transition:background .12s}
  .ct-drop-browse:hover{background:#eff6ff}
  .ct-drop-formats{font-size:12px;color:#94a3b8}
  .ct-drop-formats strong{color:#2563eb;font-weight:600}
  .ct-file-input{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);border:0}

  .ct-file-chip{display:flex;align-items:center;gap:12px;margin-top:16px;padding:12px 14px;border:1px solid #e2e8f0;
                border-radius:11px}
  .ct-file-icon{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;
                background:#dcfce7;color:#15803d;flex-shrink:0}
  .ct-file-text{display:flex;flex-direction:column;gap:2px;flex:1;min-width:0}
  .ct-file-name{font-size:13.5px;font-weight:600;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .ct-file-meta{font-size:11.5px;color:#94a3b8}
  .ct-file-ok{color:#16a34a;line-height:0;flex-shrink:0}
  .ct-file-remove{background:none;border:none;padding:2px;cursor:pointer;color:#64748b;line-height:0;border-radius:6px;flex-shrink:0}
  .ct-file-remove:hover{color:#0f172a;background:#f1f5f9}
  .ct-preview-summary{display:flex;gap:16px;flex-wrap:wrap;font-size:12.5px;color:#475569;margin-bottom:12px}
  .ct-preview-summary strong{color:#0f172a}
  .ct-preview-scroll{max-height:52vh;max-height:52dvh;overflow:auto;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:14px}
  /* Fixed layout so the columns are shared out rather than each one taking
     whatever its longest value asks for - an email is what would otherwise
     push the table past the modal and force a sideways scroll. */
  .ct-preview-table{width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed}
  .ct-preview-table th{position:sticky;top:0;background:#f8fafc;text-align:left;padding:8px 10px;font-weight:600;
                       color:#475569;border-bottom:1px solid #e2e8f0}
  .ct-preview-table td{padding:7px 10px;border-bottom:1px solid #f1f5f9;color:#0f172a;overflow-wrap:anywhere}
  .ct-preview-table tr:last-child td{border-bottom:none}
  .ct-preview-table th:nth-child(3),.ct-preview-table td:nth-child(3){width:180px}
  .ct-preview-table th:nth-child(5),.ct-preview-table td:nth-child(5){width:160px}
  /* The badge is what a CaPSA/TSA scans the list by, so the three outcomes have
     to be tellable apart at a glance and not only by their wording. */
  .ct-tag{display:inline-block;padding:2px 8px;border-radius:6px;font-size:10.5px;font-weight:600;white-space:nowrap}
  .ct-tag-create{background:rgba(22,163,74,0.1);color:#15803d}
  .ct-tag-update{background:rgba(217,119,6,0.12);color:#b45309}
  .ct-tag-same{background:rgba(107,114,128,0.1);color:#64748b}
  .ct-preview-changes{color:#64748b;font-size:11px}
  /* Preview sits between Cancel and Save: offered, not insisted on. */
  .ct-btn-outline{background:#fff;color:#2563eb;border:1.5px solid #cbd5e1}
  .ct-btn-outline:hover:not(:disabled){border-color:#2563eb;background:#eff6ff}
  /* Named alongside .ct-upload-card, because that rule carries two classes
     and would otherwise outrank this one and hold the preview at the width
     of the step before it. */
  .ct-modal-card.ct-upload-card.ct-modal-card-wide{max-width:min(1000px,96vw)}
  /* .ct-btn is flex:1, which shares a narrow dialog neatly and turns each
     button into half a wide one. On this step they sit at their own size. */
  .ct-modal-card-wide .ct-modal-actions{justify-content:flex-end}
  .ct-modal-card-wide .ct-modal-actions .ct-btn{flex:0 0 auto;min-width:160px}

  /* Expert Management (CaPSA/TSA's copy - view + Manage Roles only). Admin has
     its own separate, fuller copy at resources/views/admin/experts.blade.php
     (this plus Edit/Remove/Make CAPSA/Add Expert) - these two used to be
     one file switching on viewer role, which made neither version easy to
     read on its own. */
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

  /* One row, never two. The buttons carried their own labels when this
     wrapped; as icons they fit across, and a 2x2 block of them read as four
     separate things rather than one row of actions. */
  .expert-actions{
    display:flex;
    flex-wrap:nowrap;
    align-items:center;
    gap:5px;
  }


  /* Manage Roles - the one action this page has. */
  .roles-btn{
    display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;
    border:1.5px solid var(--blue-bg);background:var(--blue-bg);color:var(--blue);
    font-size:11px;font-weight:600;cursor:pointer;font-family:'DM Sans','DM Sans Fallback',sans-serif;
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
     degree, program). Rides on the page's existing .ct-modal-overlay /
     .ct-modal-card shell so it looks and behaves like every other dialog
     here. Shared markup/behavior with the other copy of this page. */
  .expert-name-trigger{cursor:pointer}

  /* Export: which columns go in the PDF and the Excel file. */
  .xp-cols{display:grid;grid-template-columns:1fr 1fr;gap:6px 14px;text-align:left;margin:4px 0 6px}
  .xp-col{display:flex;align-items:center;gap:8px;font-size:13px;color:#1e293b;padding:7px 4px;cursor:pointer}
  .xp-col input{width:15px;height:15px;accent-color:#2563eb;flex-shrink:0}
  .xp-always{font-size:10.5px;color:#94a3b8;margin-left:auto}
  .xp-quick{display:flex;gap:14px;margin:0 0 18px}
  .xp-quick button{background:none;border:0;padding:0;font-size:12px;font-weight:600;color:#2563eb;cursor:pointer}
  @media (max-width:520px){.xp-cols{grid-template-columns:1fr}}
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
    max-height:52vh;max-height:52dvh;overflow-y:auto;scrollbar-width:none;
  }
  .expert-detail-body::-webkit-scrollbar{display:none}
  .expert-detail-field-label{
    font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;
  }
  .expert-detail-field-value{font-size:13.5px;color:#1e293b;line-height:1.5}
  .expert-detail-side + .expert-detail-side{margin-top:4px}
  .expert-detail-side b{font-weight:700;color:#0f172a}
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
@include('partials.skeleton-head', ['skReveal' => 'flex'])
  @include('partials.render-whole')
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
      {{-- Roles are the only column a CaPSA/TSA can change, and a page that does
           not say so invites trying the others. --}}
      <p class="table-legend">
        <b>Roles</b> are yours to set.
        <b>Accepts</b> and <b>Research Areas</b> belong to the expert &mdash; they choose which programs
        to take teams from and how many, on their own profile.
      </p>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <nav class="page-tabs" aria-label="Expert Management views">
    <button type="button" data-tab="experts" aria-selected="{{ $tab === 'experts' ? 'true' : 'false' }}">Experts</button>
    <button type="button" data-tab="coverage" aria-selected="{{ $tab === 'coverage' ? 'true' : 'false' }}">Expert coverage</button>
  </nav>
  <div class="content list-page tk" id="experts-pane" @if ($tab === 'coverage') hidden @endif>
    @php
      $viewerId = (int) auth('expert')->id();
      // The mix, not just the total: how many of each role you have is the
      // thing a CaPSA/TSA is actually deciding about when they open this page.
      // A tab per role, each saying how many it holds.
      $roleCounts = $experts->flatMap(fn ($e) => $e->roles->pluck('role_name'))->countBy();
      $roleTabs = collect([\App\Models\CapstoneExpert::CAPSA_ROLE, ...\App\Models\CapstoneExpert::STUDENT_FACING_ROLES])
          ->mapWithKeys(fn ($role) => [$role => \App\Models\Role::labelFor($role)]);

      // Accounts nobody has signed in to yet. They cannot be matched to a
      // team until they do, so the page says how many there are.
      $awaitingCount = $experts->filter(fn ($e) => ! $e->deactivated_at && $e->first_login_at === null && $e->last_seen_at === null)->count();
    @endphp

    <div class="page-head">
      <div class="tk-head-text">
        <div class="page-head-text">
          <span class="page-count">{{ $experts->count() }}</span>
          <span class="page-count-noun">{{ Str::plural('expert', $experts->count()) }} on your panel</span>
        </div>
        <p class="tk-lede">Advisers, consultants and grammarians your teams can be matched with.</p>
      </div>
      {{-- The list is for a whole faculty at once. Add Expert is for the one
           it missed, so admitting a late arrival is not a spreadsheet. The same
           table as a report: View Report opens it with the column picker,
           Export asks which columns first, then shows the PDF with Download PDF
           and Download Excel. --}}
      <div class="tk-actions">
        <a class="tk-btn tk-btn-ghost" href="{{ route('capsa.reports.template', 'experts') }}" title="View Report"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg><span class="tk-lbl">View Report</span></a>
        <button type="button" class="tk-btn tk-btn-ghost" onclick="openExpertExportModal()" title="Export"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg><span class="tk-lbl">Export</span></button>
        <button type="button" class="tk-btn" onclick="openExpertRosterModal()" title="Upload List"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg><span class="tk-lbl">Upload List</span></button>
        <button type="button" class="tk-btn tk-btn-pri" onclick="openAddExpertModal()"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add Expert</button>
      </div>
    </div>

    @if ($awaitingCount > 0)
      <div class="tk-banner" role="status">
        <span class="tk-banner-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>
        <p><strong>{{ $awaitingCount }} {{ Str::plural('expert', $awaitingCount) }} {{ $awaitingCount === 1 ? "hasn't" : "haven't" }} signed in yet.</strong> Names, research areas and slots fill in on their first sign-in &mdash; until then they can't be matched to teams.</p>
        <button type="button" class="tk-btn tk-btn-sm" id="tkShowAwaiting">Show them</button>
      </div>
    @endif

    @include('partials.row-select', [
      'table' => '.table',
      'noun' => 'expert',
      'actions' => [
        ['id' => 'bulkRoles', 'label' => 'Set roles',
         'icon' => '<path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"/><circle cx="7" cy="7" r="1"/>'],
        ['id' => 'bulkRemove', 'label' => 'Deactivate', 'danger' => true,
         'icon' => '<path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><path d="M12 2v10"/>'],
      ],
    ])

    <div class="card list-card">
      <div class="tk-toolbar">
        <div class="tk-tabs" role="tablist" aria-label="Role" id="experts-role-filter" data-value="">
          <button type="button" class="tk-tab" role="tab" data-v="" aria-selected="true">All <span class="n">{{ $experts->count() }}</span></button>
          @foreach ($roleTabs as $roleName => $roleText)
            <button type="button" class="tk-tab" role="tab" data-v="{{ $roleName }}" aria-selected="false">{{ $roleText }} <span class="n">{{ $roleCounts[$roleName] ?? 0 }}</span></button>
          @endforeach
        </div>
        <label class="experts-search">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <span class="tk-vh">Search experts</span>
          <input class="input" id="experts-search-input" placeholder="Name, email or research area"/>
        </label>
        <div class="tk-toolbar-end">
          <label class="tk-vh" for="statusFilter">Status</label>
          <select class="input tk-select" id="statusFilter">
            <option value="all">Any status</option>
            <option value="active">Online</option>
            <option value="full">Full</option>
            <option value="offline">Offline</option>
            <option value="awaiting">Awaiting first sign-in</option>
            <option value="off">Deactivated</option>
          </select>
        </div>
      </div>

      <div class="tk-scroll">
      <table class="table">
        <thead>
          <tr>
            <th style="width:28%">Expert</th>
            <th style="width:22%">Role(s)</th>
            <th class="tk-opt" style="width:24%">Research Areas</th>
            <th class="tk-opt2" style="width:18%">Accepts <span class="th-owner">expert sets</span></th>
            <th style="width:64px"><span class="tk-vh">Action</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach($experts as $expert)
          @php
              // Every side together here; the detail window shows them apart.
              $expertiseList = $expert->allExpertise();
              $expertiseFull = $expertiseList->join(', ') ?: 'None specified';
              $expertiseExtra = $expertiseList->count() - 2;

              $maxTeams = $expert->max_teams ?? 0;
              $teamCount = $expert->team_assignments_count ?? 0;
              $slotsLeft = max(0, $maxTeams - $teamCount);

              // Team slots belong to the roles that take teams. A CaPSA/TSA is
              // not one of them - the job is running the subject, not
              // advising groups - so a plain CaPSA/TSA has no slots to show and
              // reading "0/0 - Full" against their name says they are
              // overloaded when they were never carrying anything.
              $takesTeams = $expert->roles
                  ->pluck('role_name')
                  ->intersect(\App\Models\CapstoneExpert::STUDENT_FACING_ROLES)
                  ->isNotEmpty();

              // Nobody has said how many they take yet, which is not the
              // same as having said none.
              $slotsSet = $expert->max_teams !== null || filled($expert->program_slots);

              $isFull = $takesTeams && $slotsSet && $teamCount >= $maxTeams;
              $isOnline = $expert->is_online;
              // A deactivated account has no capacity and no presence - saying
              // "offline" about it would be true and useless.
              $isOff = (bool) $expert->deactivated_at;
              $computedStatus = $isOff ? 'off' : ($isFull ? 'full' : ($isOnline ? 'active' : 'offline'));
              $awaiting = ! $isOff && $expert->first_login_at === null && $expert->last_seen_at === null;
              $isMe = (int) $expert->expert_id === $viewerId;
              $displayName = $expert->name !== '' ? $expert->name : $expert->email;
              $capacityRows = $expert->parsedCapacity();

              // Filled counts per program, for the split-cap rows in the
              // Accepts cell. One grouped query, and only when the cap is
              // actually split - a single "ALL" cap draws its bar from the
              // overall count instead.
              $programCounts = count($capacityRows) > 1
                  ? $expert->approvedTeamCountsByProgram()
                  : [];

              // One mapping, on the model, so the pills here and the ones on
              // the expert's own profile panel cannot drift apart.
              $roleLabel = fn (string $roleName) => \App\Models\Role::labelFor($roleName);

              $detailPayload = [
                'avatar' => $expert->avatarUrl(),
                'name' => $expert->name,
                'email' => $expert->email,
                'credentials' => $expert->credentials,
                'expert_id' => $expert->expert_id,
                'highest_degree' => $expert->highest_degree,
                'degree_title' => $expert->degree_title,
                'on_study_leave' => (bool) $expert->on_study_leave,
                'program' => $expert->program,
                'roles' => $expert->roles->pluck('role_name')->map($roleLabel)->values(),
                'expertise' => $expertiseList->values(),
                'expertise_by_side' => collect(\App\Models\ExpertExpertise::SIDES)
                    ->map(fn ($label, $side) => $expert->expertise->where('side', $side)->pluck('expertise_field')->values())
                    ->filter(fn ($fields) => $fields->isNotEmpty())
                    ->mapWithKeys(fn ($fields, $side) => [\App\Models\ExpertExpertise::SIDES[$side] => $fields])
                    ->all(),
                // Empty rather than "ALL: max 0" - the detail panel reads
                // that as "Not set", which is what it is.
                'capacity' => $takesTeams && $slotsSet
                    ? collect($capacityRows)->map(fn ($row) => $row[0].': max '.$row[1])->values()
                    : collect(),
              ];
            @endphp
          {{-- The filter reads these rather than picking through the cells:
               it used to find the status by counting columns, which is a
               thing that breaks the moment a column moves. --}}
          <tr data-status="{{ $computedStatus }}"
              @class(['cx-off' => $isOff, 'tk-me' => $isMe])
              data-id="{{ $expert->expert_id }}"
              {{-- Removing your own account, or stripping your own roles, is
                   not a thing to be one careless drag away from. --}}
              @if ($isMe) data-no-select="1" @endif
              data-awaiting="{{ $awaiting ? '1' : '0' }}"
              data-roles="{{ $expert->roles->pluck('role_name')->implode(' ') }}"
              data-search="{{ Str::lower($expert->name.' '.$expert->email.' '.$expertiseFull) }}">
            <td class="tk-lead" data-label="Expert">
              <div class="expert-name-trigger tk-who tk-click" onclick="openExpertDetail({{ Illuminate\Support\Js::from($detailPayload) }})">
                @include('partials.table-kit.avatar', [
                  'image' => $expert->hasAvatarImage() ? $expert->avatarUrl() : null,
                  'initials' => $expert->avatarInitials(),
                  'seed' => $expert->expert_id,
                  'awaiting' => $awaiting,
                  'presence' => $isOff ? null : ($isOnline ? 'on' : 'off'),
                ])
                {{-- The address under the name rather than hidden in a tooltip:
                     two people share a surname often enough, and the email is
                     what tells them apart. --}}
                <span class="tk-who-text">
                  <span class="tk-namerow">
                    <span @class(['tk-name', 'td-main', 'tk-name-plain' => $awaiting])>{{ $displayName }}</span>
                    @if ($isMe)<span class="tk-pill tk-p-you">YOU</span>@endif
                    @if ($isOff)<span class="tk-pill tk-p-off">Deactivated</span>@endif
                    @if ($expert->on_study_leave)<span class="cx-leave-tag" title="On study leave: may be slow to reply and hard to book.">Study leave</span>@endif
                  </span>
                  @if ($awaiting)
                    <span class="tk-sub tk-sub-warn">Awaiting first sign-in</span>
                  @elseif ($expert->name !== '')
                    <span class="tk-sub">{{ $expert->email }}</span>
                  @endif
                </span>
              </div>
            </td>
            <td class="tk-block" data-label="Roles">
              {{-- No separate "CAPSA" badge - toggleCapsa() keeps the
                   capstone_adviser role in sync with is_capsa, so the
                   "Capstone Subject Adviser" one already says it. --}}
              <span class="tk-chips">
                @forelse ($expert->roles as $role)
                  <span class="tk-role tk-role-{{ $role->role_name }}">{{ $roleLabel($role->role_name) }}</span>
                @empty
                  <span class="tk-role tk-role-none">No Role</span>
                @endforelse
              </span>
            </td>

            {{-- A chip each, so a long name truncates on its own instead of
                 being joined to the next one and cut mid-word. The counter
                 opens the detail panel, where the full list already is. --}}
            <td data-label="Research Areas" @class(['tk-opt', 'tk-block', 'tk-blank' => $expertiseList->isEmpty()]) title="{{ $expertiseFull }}">
              @if ($expertiseList->isEmpty())
                <span class="tk-empty">&mdash;</span>
              @else
                <span class="tk-chips tk-1line">
                  @foreach ($expertiseList->take(2) as $skill)
                    <span class="tk-chip">{{ $skill }}</span>
                  @endforeach
                  @if ($expertiseExtra > 0)
                    <button type="button" class="tk-more" onclick="openExpertDetail({{ Illuminate\Support\Js::from($detailPayload) }})" aria-label="{{ $expertiseExtra }} more research areas">+{{ $expertiseExtra }}</button>
                  @endif
                </span>
              @endif
            </td>
            <td data-label="Accepts" @class(['tk-opt2', 'tk-block', 'tk-blank' => ! ($takesTeams && $slotsSet)])>
              @if (! $takesTeams)
                <span class="tk-empty">&mdash;</span>
              @elseif (! $slotsSet)
                <span class="tk-empty">Not set</span>
              @elseif (count($capacityRows) === 1)
                <div class="tk-cap">
                  <span class="tk-cap-p">{{ $capacityRows[0][0] === 'ALL' ? 'All' : $capacityRows[0][0] }}</span>
                  <span class="tk-meter"><i @class(['full' => $isFull]) style="width:{{ $maxTeams > 0 ? round(($teamCount / $maxTeams) * 100) : 0 }}%"></i></span>
                  <span class="tk-cap-v">{{ $teamCount }}/{{ $maxTeams }} · {{ $slotsLeft }} left</span>
                </div>
              @else
                {{-- One row per program: bar plus n/max, so how full each
                     program already is reads at a glance. --}}
                <div class="tk-cap">
                  @foreach ($capacityRows as [$program, $max])
                    @php $filled = $programCounts[$program] ?? 0; @endphp
                    <span class="tk-cap-p">{{ $program }}</span>
                    <span class="tk-meter"><i @class(['full' => $max > 0 && $filled >= $max]) style="width:{{ $max > 0 ? round(($filled / $max) * 100) : 0 }}%"></i></span>
                    <span class="tk-cap-v"><span class="capacity-count">{{ $filled }}/{{ $max }}</span></span>
                  @endforeach
                </div>
              @endif
              @if ($takesTeams && $slotsSet && ($suggestion = $expert->slotSuggestion($teamCount)))
                <div class="tk-hint"><svg viewBox="0 0 24 24" aria-hidden="true" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V16h8v-1.3A7 7 0 0 0 12 2z"/></svg>Suggestion: {{ $suggestion }}</div>
              @endif
            </td>

            <td class="tk-ac" data-label="Action">
              <div class="tk-acts expert-actions">
                <div class="rowmenu">
                  <button type="button" class="icon-btn rowmenu-btn" aria-expanded="false"
                          aria-label="Actions for {{ $displayName }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.7" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.7" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.7" fill="currentColor" stroke="none"/></svg>
                  </button>
                  <div class="rowmenu-pop" hidden>
                    <button type="button" class="rowmenu-item"
                            onclick="openRoles({{ $expert->expert_id }}, {{ Illuminate\Support\Js::from($displayName) }}, {{ Illuminate\Support\Js::from($expert->roles->pluck('role_name')) }})">
                      <svg viewBox="0 0 24 24"><path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"/><circle cx="7" cy="7" r="1"/></svg> Set roles
                    </button>
                    {{-- On and off from here too: a leave ends, and the tag should go with it. --}}
                    <button type="button" class="rowmenu-item"
                            onclick="setStudyLeave({{ $expert->expert_id }}, {{ $expert->on_study_leave ? 'false' : 'true' }}, true)">
                      <svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg> {{ $expert->on_study_leave ? 'End study leave' : 'Mark study leave' }}
                    </button>
                    @if ($awaiting)
                      <button type="button" class="rowmenu-item" data-email="{{ $expert->email }}" onclick="copyExpertEmail(this)">
                        <svg viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg> Copy email
                      </button>
                    @endif
                    @if ($isOff || ! ($isMe || $expert->is_capsa))
                      <div class="rowmenu-sep"></div>
                    @endif
                    @if ($isOff)
                      <button type="button" class="rowmenu-item"
                              onclick="reactivateExpert({{ $expert->expert_id }}, {{ Illuminate\Support\Js::from($displayName) }})">
                        <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Reactivate
                      </button>
                    @elseif (! $isMe && ! $expert->is_capsa)
                    {{-- Never on your own row: nobody would be left to switch you back on. Never on another CaPSA/TSA: that is the admin's to do. --}}
                    <button type="button" class="rowmenu-item danger"
                            onclick="removeOneExpert({{ $expert->expert_id }}, {{ Illuminate\Support\Js::from($displayName) }})">
                      <svg viewBox="0 0 24 24"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg> Deactivate
                    </button>
                    @endif
                  </div>
                </div>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      </div>
      <div class="tk-foot">
        <span id="tkShowing">Showing all <strong>{{ $experts->count() }}</strong> {{ Str::plural('expert', $experts->count()) }}</span>
        <span class="tk-tip" style="color:var(--text3)">Tick rows to set roles or deactivate several at once</span>
      </div>
    </div>

<script nonce="{{ Vite::cspNonce() }}">
  // Client-side filtering: role tab, search and status.
  (function() {
    var searchInput = document.getElementById('experts-search-input');
    var roleTabs = document.getElementById('experts-role-filter');
    var statusFilter = document.getElementById('statusFilter');
    var showing = document.getElementById('tkShowing');
    var rows = document.querySelectorAll('table.table tbody tr');
    var total = rows.length;

    function filterRows() {
      var q = searchInput ? searchInput.value.toLowerCase().trim() : '';
      var role = roleTabs ? roleTabs.dataset.value : '';
      var status = statusFilter ? statusFilter.value : 'all';
      var visible = 0;

      rows.forEach(function(row) {
        // Read off the row rather than out of the cells. Counting columns to
        // find the status broke the moment one moved, and the expertise is
        // truncated on screen - the attribute holds all of it.
        var searchText = (row.dataset.search || '');
        var roles = ' ' + (row.dataset.roles || '') + ' ';
        var statusText = (row.dataset.status || '');

        var matchSearch = !q || searchText.includes(q);
        var matchRole = !role || roles.includes(' ' + role + ' ');
        var matchStatus = status === 'all'
          || (status === 'awaiting' ? row.dataset.awaiting === '1' : statusText === status);

        var shown = matchSearch && matchRole && matchStatus;
        row.style.display = shown ? '' : 'none';
        if (shown) visible++;
      });

      noMatch.hidden = visible > 0;
      showing.innerHTML = visible === total
        ? 'Showing all <strong>' + total + '</strong> ' + (total === 1 ? 'expert' : 'experts')
        : 'Showing <strong>' + visible + '</strong> of <strong>' + total + '</strong>';
    }

    // A table that just goes blank reads as broken, not as "nobody matched".
    var noMatch = document.createElement('tr');
    noMatch.className = 'tk-note';
    noMatch.hidden = true;
    noMatch.innerHTML = '<td colspan="6" style="padding:24px 20px;text-align:center;color:var(--text3);font-size:13px">No expert matches that search.</td>';
    rows[0]?.parentNode.appendChild(noMatch);

    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (statusFilter) statusFilter.addEventListener('change', filterRows);
    if (roleTabs) {
      roleTabs.querySelectorAll('button').forEach(function (button) {
        button.addEventListener('click', function () {
          roleTabs.dataset.value = button.dataset.v;
          roleTabs.querySelectorAll('button').forEach(function (b) {
            b.setAttribute('aria-selected', b === button ? 'true' : 'false');
          });
          filterRows();
        });
      });
    }

    var showAwaiting = document.getElementById('tkShowAwaiting');
    if (showAwaiting && statusFilter) {
      showAwaiting.addEventListener('click', function () {
        statusFilter.value = 'awaiting';
        filterRows();
      });
    }
  })();

  async function copyExpertEmail(button) {
    try {
      await navigator.clipboard.writeText(button.dataset.email);
      showToast(button.dataset.email + ' copied.', 'success');
    } catch (e) {
      showToast('Could not copy the address.', 'error');
    }
  }
</script>
  </div>
@php
  $covRoles = \App\Support\ExpertCapacity::ROLES;
  $teamCount = collect($coverageTeams)->countBy('program');
  $covPrograms = collect($coverage)->filter(fn ($rows, $p) => ($teamCount[$p] ?? 0) > 0);
  $roleRow = fn ($rows, $role) => collect($rows)->firstWhere('role', $role);
  // Chip for one need against its open slots: tone, mark, words.
  $chip = function (int $need, ?int $free) {
      if ($free === null) return ['gray', '✓', 'No limit'];
      $balance = $free - $need;
      if ($balance < 0) return ['red', '!', 'Short by '.(-$balance)];
      if ($balance > 0) return ['blue', 'ⓘ', $balance.' spare '.($balance === 1 ? 'slot' : 'slots')];
      return ['green', '✓', $need === 0 ? 'Covered' : 'Sufficient'];
  };
  $openText = fn (?int $free) => $free === null ? 'no limit' : $free.' open';
  $totalTeams = count($coverageTeams);
  $missingTotal = collect($coverageTeams)->sum(fn ($t) => count($t['missing']));
  $shorts = [];
  foreach ($covPrograms as $p => $rows) {
      foreach ($rows as $r) {
          if ($r['role'] !== 'all' && $r['short'] > 0) $shorts[] = ['program' => $p, 'role' => $r['role'], 'label' => $r['label'], 'count' => $r['short']];
      }
  }
  $allShort = $covPrograms->map(fn ($rows) => $roleRow($rows, 'all')['short'])->filter();
  $roleTotals = collect($covRoles)->map(function ($label, $role) use ($covPrograms, $roleRow, $teamCount, $shorts) {
      $rows = $covPrograms->map(fn ($rows) => $roleRow($rows, $role));
      $required = $covPrograms->keys()->sum(fn ($p) => $teamCount[$p]);
      $needed = $rows->sum('need');
      return [
          'programNeeded' => $rows->sum('programNeed'),
          'label' => $label,
          'required' => $required,
          'needed' => $needed,
          'assigned' => $required - $needed,
          'free' => $rows->contains(fn ($r) => $r['free'] === null) ? null : $rows->sum('free'),
          'short' => collect($shorts)->where('role', $role),
      ];
  });
@endphp
  <div class="content cov" id="coverage-pane" @if ($tab !== 'coverage') hidden @endif>
<style nonce="{{ Vite::cspNonce() }}">
  #real-content > [hidden],.cov [hidden]{display:none!important}
  .page-tabs{display:flex;gap:29px;border-bottom:1px solid var(--border,#dfe3eb);height:49px;flex-shrink:0;margin:0 26px}
  .page-tabs button{background:transparent;border:0;border-bottom:2px solid transparent;padding:0 8px;color:#647087;font-size:14px;cursor:pointer;font-family:inherit}
  .page-tabs button[aria-selected=true]{border-bottom-color:#c7a727;color:#151c28;font-weight:600}
  .cov{display:flex;flex-direction:column;gap:14px;overflow:hidden;min-height:0}
  .cov-top{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
  .cov .metrics{display:flex;align-items:center;gap:26px;font-size:14px;color:#151c28}
  .cov .metric{display:flex;align-items:center;gap:8px}
  .cov .metric:first-child{font-weight:600}
  .cov .metric svg{color:#25853d;width:23px;height:23px}
  .cov .metric+.metric{border-left:1px solid #c4cbd7;padding-left:26px}
  .cov .notice{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 15px;border:1px solid #ebc86a;border-radius:9px;background:#fff6e2;flex-shrink:0}
  .cov .notice-main{display:flex;align-items:center;gap:9px;font-weight:600;font-size:13.5px}
  .cov .notice-main svg{color:#bc8505;flex-shrink:0}
  .cov .notice.good{background:#edf7ef;border-color:#c8e1cd}
  .cov .notice.good svg{color:#25853d}
  .cov .panel{flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden;border:1px solid var(--border,#dfe3eb);border-radius:11px;background:var(--bg2,#fff)}
  .cov .panel-head{padding:13px 17px 10px;display:flex;align-items:center;justify-content:space-between;gap:12px}
  .cov .panel-head h2{font-size:18px;margin:0;font-weight:700}
  .cov .panel-head .tk-actions{display:flex;gap:8px}
  .cov .panel-toolbar{padding:0 16px 10px}
  .cov .segments{display:inline-flex;gap:2px;padding:3px;border-radius:8px;background:#eceef3}
  .cov .segments button{border:1px solid transparent;background:transparent;padding:5px 13px;font-size:12px;border-radius:6px;cursor:pointer;color:#4a5468;font-family:inherit}
  .cov .segments button[aria-pressed=true]{background:#fff;border-color:#e0e3e9;box-shadow:0 1px 3px #25304c10;font-weight:600;color:#151c28}
  .cov .table-scroll{flex:1;min-height:0;overflow:auto;padding:0 14px}
  .cov .table-scroll table{width:100%;border-collapse:separate;border-spacing:0;table-layout:fixed;font-size:13px}
  .cov .table-scroll thead th{position:sticky;top:0;z-index:1;background:#1d2028;color:#f1f3f7;padding:10px 15px;font-size:12px;font-weight:600;text-align:center}
  .cov .table-scroll thead th:first-child{border-top-left-radius:6px;text-align:left}
  .cov .table-scroll thead th:last-child{border-top-right-radius:6px}
  .cov .table-scroll td{border-bottom:1px solid #e5e9f0;padding:14px 15px;text-align:center;vertical-align:middle}
  .cov .table-scroll td:first-child{text-align:left}
  .cov .program-name{font-weight:700;font-size:15px}
  .cov .program-name small{display:none}
  .cov .role-name{font-weight:600}
  .cov .has-shortage td:first-child{border-left:4px solid #c63838;padding-left:11px}
  .cov .cell-status{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:7px;font-size:12px;font-weight:600;border:0;cursor:pointer;font-family:inherit}
  .cov .cell-status.green{background:#e7f5e8;color:#187b32}
  .cov .cell-status.red{background:#ffe8e9;color:#b6242c}
  .cov .cell-status.blue{background:#e5efff;color:#245bbc}
  .cov .cell-status.gray{background:#eef0f5;color:#657186}
  .cov .cell-detail{display:block;color:#657186;font-size:11px;margin-top:4px}
  .cov .empty{padding:30px;text-align:center;color:#657186}
  .cov .panel-foot{padding:10px 17px;border-top:1px solid var(--border,#dfe3eb);display:flex;justify-content:flex-end;align-items:center;background:#fcfcfd}
  #cov-dialog{margin:auto;width:min(780px,calc(100vw - 24px));max-height:calc(100vh - 40px);border:0;border-radius:12px;padding:0;box-shadow:0 20px 50px #0003}
  #cov-dialog[open]{display:flex;flex-direction:column}
  #cov-dialog::backdrop{background:#0f172a66}
  #cov-dialog .dialog-head{display:flex;align-items:center;justify-content:space-between;padding:15px 18px;border-bottom:1px solid #e5e9f0}
  #cov-dialog .dialog-head h2{font-size:17px;margin:0}
  #cov-dialog .dialog-x{border:0;background:transparent;font-size:22px;line-height:1;cursor:pointer;color:#657186}
  #cov-dialog .dialog-body{overflow:auto;padding:12px 18px;min-height:0}
  #cov-dialog table{width:100%;border-collapse:collapse;font-size:13px}
  #cov-dialog th{text-align:left;font-size:12px;color:#657186;padding:8px 10px;border-bottom:1px solid #e5e9f0;position:sticky;top:0;background:#fff}
  #cov-dialog td{padding:9px 10px;border-bottom:1px solid #eef1f5}
  #cov-dialog td:first-child{font-weight:600}
  #cov-dialog .dialog-foot{display:flex;justify-content:flex-end;padding:12px 18px;border-top:1px solid #e5e9f0}
  @media (max-width:1100px){.page-tabs{margin:0 16px}}
  @media (max-height:540px){.cov .metrics{display:none}}
  @media (max-width:650px){
    .page-tabs{margin:0 12px;height:43px;gap:18px}
    .page-tabs button{font-size:13px}
    .cov .metrics{flex-wrap:wrap;gap:10px}
    .cov .metric+.metric{border-left:0;padding-left:0}
    .cov .notice{flex-wrap:wrap}
    .cov .table-scroll colgroup,.cov .table-scroll thead{display:none}
    .cov .table-scroll table,.cov .table-scroll tbody{display:block}
    .cov .table-scroll tr{display:block;border:1px solid var(--border,#dfe3eb);border-radius:7px;margin-bottom:9px;overflow:hidden}
    .cov .table-scroll td{display:flex;justify-content:space-between;align-items:center;padding:9px 11px;text-align:left}
    .cov .table-scroll td:before{content:attr(data-label);font-size:11px;color:#657186}
    .cov .table-scroll td:first-child{display:block;background:#f7f8fa}
    .cov .table-scroll td:first-child:before{content:none}
    .cov .has-shortage td:first-child{padding-left:8px}
    .cov .matrix-team-count{display:none!important}
    .cov .program-name small{display:inline;font-weight:400;font-size:12px;color:#657186;margin-left:6px}
    .cov .coverage-value{display:flex;flex-direction:column;align-items:flex-end}
  }
</style>
    @if ($totalTeams)
      <div class="cov-top">
        <div class="metrics">
          <div class="metric"><x-tm-icon n="users" s="23" /><span>{{ $totalTeams }} {{ $totalTeams === 1 ? 'team' : 'teams' }} across {{ $covPrograms->keys()->join(', ', ' and ') }}</span></div>
          <div class="metric"><x-tm-icon n="check" s="23" /><span>{{ $totalTeams * 3 - $missingTotal }} of {{ $totalTeams * 3 }} assignments filled</span></div>
        </div>
      </div>
      @if ($shorts)
        @php
          $first = $shorts[0];
          $same = collect($shorts)->where('role', $first['role']);
          $msg = count($shorts) > $same->count()
              ? count($shorts).' program and role combinations need more slots'
              : $same->map(fn ($s) => "{$s['program']} needs {$s['count']} more {$s['label']} ".($s['count'] === 1 ? 'slot' : 'slots'))->join(' · ');
        @endphp
        <div class="notice"><div class="notice-main"><x-tm-icon n="warn" s="18" /><span>{{ $msg }}</span></div>
          <button type="button" class="tk-btn tk-btn-pri" data-cov-add="{{ $first['role'] }}"><x-tm-icon n="plus" s="15" /> Add {{ strtolower($first['label']) }}s</button></div>
      @elseif ($allShort->isNotEmpty())
        <div class="notice"><div class="notice-main"><x-tm-icon n="warn" s="18" /><span>{{ $allShort->map(fn ($n, $p) => "{$p} needs {$n} more expert ".($n === 1 ? 'slot' : 'slots').' in all')->join(' · ') }}</span></div>
          <button type="button" class="tk-btn tk-btn-pri" data-cov-add=""><x-tm-icon n="plus" s="15" /> Add experts</button></div>
      @else
        <div class="notice good"><div class="notice-main"><x-tm-icon n="check" s="18" /><span>Available expert capacity covers the remaining assignments in this scope.</span></div></div>
      @endif
    @endif

    <section class="panel" aria-label="Expert coverage results">
      <header class="panel-head">
        <h2 id="cov-title">Coverage by program</h2>
        <div class="tk-actions" id="cov-actions-program"></div>
        <div class="tk-actions" id="cov-actions-role" hidden></div>
      </header>
      <div class="panel-toolbar"><div class="segments" aria-label="Coverage view">
        <button type="button" data-cov-view="program" aria-pressed="true">By program</button>
        <button type="button" data-cov-view="role" aria-pressed="false">By role</button>
      </div></div>
      <div class="table-scroll" tabindex="0" aria-label="Scrollable expert coverage table">
        @if (! $totalTeams)
          <div class="empty">No teams this term.</div>
        @else
          <table data-cov="program" data-export="Expert coverage by program" data-export-into="#cov-actions-program">
            <colgroup><col style="width:16%"><col style="width:12%"><col style="width:24%"><col style="width:24%"><col style="width:24%"></colgroup>
            <thead><tr><th>Program</th><th>Teams</th>@foreach ($covRoles as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
            <tbody>
              @foreach ($covPrograms as $p => $rows)
                <tr class="{{ collect($rows)->contains(fn ($r) => $r['role'] !== 'all' && $r['short'] > 0) ? 'has-shortage' : '' }}">
                  <td><span class="program-name">{{ $p }}<small>{{ $teamCount[$p] }} {{ $teamCount[$p] === 1 ? 'team' : 'teams' }}</small></span></td>
                  <td class="matrix-team-count">{{ $teamCount[$p] }}</td>
                  @foreach ($covRoles as $role => $label)
                    @php $r = $roleRow($rows, $role); [$tone, $mark, $text] = $chip($r['programNeed'], $r['free']); $others = $r['programNeed'] - $r['need']; @endphp
                    <td data-label="{{ $label }}"><span class="coverage-value"><button type="button" class="cell-status {{ $tone }}" data-cov-teams data-program="{{ $p }}" data-role="{{ $role }}">{{ $mark }} {{ $text }}</button><span class="cell-detail">{{ $r['need'] }} needed{{ $others > 0 ? ' · '.$others.' in other sections' : '' }} · {{ $openText($r['free']) }}</span></span></td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
          <table data-cov="role" data-export="Expert coverage by role" data-export-into="#cov-actions-role" hidden>
            <colgroup><col style="width:25%"><col style="width:13%"><col style="width:13%"><col style="width:13%"><col style="width:13%"><col style="width:23%"></colgroup>
            <thead><tr><th>Expert role</th><th>Required</th><th>Assigned</th><th>Still needed</th><th>Open slots</th><th>Result</th></tr></thead>
            <tbody>
              @foreach ($roleTotals as $role => $t)
                @php
                  [$tone, $mark, $text] = $t['short']->isNotEmpty() ? ['red', '!', 'Short by '.$t['short']->sum('count')] : $chip($t['programNeeded'], $t['free']);
                @endphp
                <tr class="{{ $tone === 'red' ? 'has-shortage' : '' }}">
                  <td class="role-name">{{ $t['label'] }}</td>
                  <td data-label="Required">{{ $t['required'] }}</td>
                  <td data-label="Assigned">{{ $t['assigned'] }}</td>
                  <td data-label="Still needed">{{ $t['needed'] }}</td>
                  <td data-label="Open slots">{{ $t['free'] ?? 'No limit' }}</td>
                  <td data-label="Result"><span class="coverage-value"><button type="button" class="cell-status {{ $tone }}" data-cov-teams data-program="" data-role="{{ $role }}">{{ $mark }} {{ $text }}</button>@if ($t['short']->isNotEmpty())<span class="cell-detail">{{ $t['short']->map(fn ($s) => $s['program'].' '.$s['count'])->join(' · ') }}</span>@endif</span></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      </div>
      @if ($totalTeams)
        <footer class="panel-foot"><button type="button" class="tk-btn tk-btn-ghost" data-cov-teams data-program="" data-role="">View teams needing experts</button></footer>
      @endif
    </section>

    <dialog id="cov-dialog" aria-labelledby="cov-dialog-title">
      <header class="dialog-head"><h2 id="cov-dialog-title"></h2><button type="button" class="dialog-x" data-cov-close aria-label="Close dialog">×</button></header>
      <div class="dialog-body" id="cov-dialog-body"></div>
      <footer class="dialog-foot"><button type="button" class="tk-btn tk-btn-ghost" data-cov-close>Close</button></footer>
    </dialog>
  </div>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var teams = @json($coverageTeams);
  var labels = @json($covRoles);
  var panes = { experts: document.getElementById('experts-pane'), coverage: document.getElementById('coverage-pane') };

  document.querySelectorAll('.page-tabs [data-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tab = btn.dataset.tab;
      document.querySelectorAll('.page-tabs [data-tab]').forEach(function (b) { b.setAttribute('aria-selected', String(b === btn)); });
      Object.keys(panes).forEach(function (k) { panes[k].hidden = k !== tab; });
      var url = new URL(location.href);
      if (tab === 'coverage') url.searchParams.set('tab', 'coverage'); else url.searchParams.delete('tab');
      history.replaceState(null, '', url);
    });
  });

  document.querySelectorAll('[data-cov-view]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var view = btn.dataset.covView;
      document.querySelectorAll('[data-cov-view]').forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
      document.querySelectorAll('#coverage-pane table[data-cov]').forEach(function (t) { t.hidden = t.dataset.cov !== view; });
      document.getElementById('cov-actions-program').hidden = view !== 'program';
      document.getElementById('cov-actions-role').hidden = view !== 'role';
      document.getElementById('cov-title').textContent = 'Coverage by ' + view;
    });
  });

  var dialog = document.getElementById('cov-dialog');
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
  document.querySelectorAll('[data-cov-teams]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var program = btn.dataset.program, role = btn.dataset.role, label = labels[role];
      var rows = [];
      teams.forEach(function (t) {
        if (program && t.program !== program) return;
        var missing = t.missing.filter(function (m) { return !role || m === role; });
        if (missing.length) rows.push('<tr><td>' + esc(t.name) + '</td><td>' + esc(t.program) + '</td><td>' + esc(t.section) + '</td><td>' + esc(missing.map(function (m) { return labels[m]; }).join(', ')) + '</td></tr>');
      });
      document.getElementById('cov-dialog-title').textContent = program ? program + ' teams needing ' + (label || 'experts') : (label ? 'Teams needing ' + label : 'Teams needing experts');
      document.getElementById('cov-dialog-body').innerHTML = rows.length
        ? '<table><thead><tr><th>Team</th><th>Program</th><th>Section</th><th>Still needs</th></tr></thead><tbody>' + rows.join('') + '</tbody></table>'
        : '<div class="empty">All assignments in this scope are filled.</div>';
      dialog.showModal();
    });
  });
  document.querySelectorAll('[data-cov-close]').forEach(function (b) { b.addEventListener('click', function () { dialog.close(); }); });
  dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });

  document.querySelectorAll('[data-cov-add]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openAddExpertModal();
      var box = btn.dataset.covAdd && document.querySelector('.ae-role input[value="' + btn.dataset.covAdd + '"]');
      if (box) box.checked = true;
    });
  });
})();
</script>
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
        <label class="expert-detail-field-label" for="detailDegree">Degree</label>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:4px">
          @include('partials.degree-select', ['id' => 'detailDegree', 'attrs' => 'class="input" style="max-width:200px" onchange="saveExpertDegree(this)"'])
          <span id="detailDegreeNote" style="font-size:11.5px;font-weight:600"></span>
        </div>
      </div>
      <div>
        <div class="expert-detail-field-label">Study leave</div>
        <label class="ae-leave" style="margin-top:4px"><input type="checkbox" id="detailStudyLeave" onchange="saveStudyLeave(this)">On study leave</label>
        <span class="ae-leave-hint">Students see a small tag: may be slow to reply and hard to book. Untick it when the leave is over.</span>
      </div>
      <div>
        <div class="expert-detail-field-label">Program</div>
        <div class="expert-detail-field-value" id="detailProgram">—</div>
      </div>
      <div>
        <div class="expert-detail-field-label">Research areas</div>
        <div class="expert-detail-field-value" id="detailExpertise">—</div>
      </div>
      <div>
        <div class="expert-detail-field-label">Team Slot</div>
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

<script nonce="{{ Vite::cspNonce() }}">
const ROLE_OPTIONS = [
  { id: 'adviser', label: 'Adviser', desc: "Primary subject adviser for a team's capstone project." },
  { id: 'consultant', label: 'Consultant', desc: 'Secondary technical/content guidance alongside the adviser.' },
  { id: 'grammarian', label: 'Grammarian', desc: 'Reviews document language and formatting before submission.' },
];

const rolesModal = document.getElementById('rolesModal');
let rolesTargetExpertId = null;

// One id or a list of them. Bulk goes through the same picker and the same
// save, so what the endpoint expects is written once.
function rolesTargets() {
  return Array.isArray(rolesTargetExpertId) ? rolesTargetExpertId : [rolesTargetExpertId];
}

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

  const body = JSON.stringify({ role_ids: checked.map(id => ROLE_NAME_TO_ID[id]).filter(Boolean) });

  Promise.all(rolesTargets().map(function (id) {
    return fetch(`{{ route('capsa.experts.update-roles', ['expert' => 'EXPERT_ID']) }}`.replace('EXPERT_ID', id), {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
      body: body,
    })
      .then(res => res.json().then(data => ({ ok: res.status === 200 && data.success, data })))
      .catch(() => ({ ok: false, data: {} }));
  }))
    .then(function (results) {
      const ok = results.filter(r => r.ok).length;
      const failed = results.length - ok;

      if (!ok) {
        // Nothing changed, so the modal stays open with the reason showing
        // rather than closing on a silent failure.
        errorEl.textContent = (results[0] && results[0].data.message) || 'Could not save roles.';
        errorEl.style.display = 'block';
        return;
      }

      closeRolesModal();
      showToast(failed
        ? ok + ' updated, ' + failed + ' could not be'
        : (results.length > 1 ? ok + ' experts updated' : 'Roles saved.'), failed ? 'error' : 'success');
      setTimeout(() => window.location.reload(), 800);
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
<div id="addExpertModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card ct-upload-card" style="max-width:460px">
    <div class="ct-up-head">
      <span class="ct-up-head-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      </span>
      <span class="ct-up-head-text">
        <span class="ct-modal-title">Add Expert</span>
        <span class="ct-up-sub">For one person. Use Upload List for a whole faculty.</span>
      </span>
      <button type="button" class="ct-up-close" onclick="closeAddExpertModal()" aria-label="Close">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="ae-field">
      <label class="ae-label" for="addExpertEmail">Email address <span class="ae-req">(required)</span></label>
      <input type="email" id="addExpertEmail" class="ct-modal-input" placeholder="name@cspc.edu.ph" autocomplete="off" style="margin-bottom:0">
    </div>

    <div class="ae-field">
      <label class="ae-label" for="addExpertName">Full name <span class="ae-opt">(optional)</span></label>
      <input type="text" id="addExpertName" class="ct-modal-input" placeholder="Fills in when they first sign in" autocomplete="off" style="margin-bottom:0">
    </div>

    {{-- Neither name nor role is asked for. The address is the whole of
         what is needed to make the account; Google gives the real spelling
         of their name the first time they sign in, and the role is the
         CaPSA/TSA's to set, now or from the row afterwards. Add CaPSA/TSA has always
         worked this way. --}}
    <div class="ae-field">
      <label class="ae-label">Role <span class="ae-opt">(optional)</span></label>
      <div class="ae-roles">
        <label class="ae-role"><input type="checkbox" value="adviser">Adviser</label>
        <label class="ae-role"><input type="checkbox" value="consultant">Consultant</label>
        <label class="ae-role"><input type="checkbox" value="grammarian">Grammarian</label>
      </div>
    </div>

    <div class="ae-field">
      <label class="ae-leave"><input type="checkbox" id="addExpertStudyLeave">On study leave</label>
      <span class="ae-leave-hint">Students see a small tag: may be slow to reply and hard to book. You can change it any time.</span>
    </div>

    <p class="ae-note">They are approved straight away &mdash; naming them here is the vetting, the same as putting them on the list. Write a degree after the name, like <b>Juan Dela Cruz, MIT</b>, and it counts for Panel Chair. Their areas of expertise are theirs to fill in from their own profile.</p>

    <div id="addExpertResult" style="display:none;font-size:12px;margin-bottom:12px"></div>

    <div class="ct-modal-actions">
      <button type="button" class="ct-btn ct-btn-secondary" onclick="closeAddExpertModal()">Cancel</button>
      <button type="button" id="addExpertBtn" class="ct-btn ct-btn-primary" onclick="addExpert()">Add Expert</button>
    </div>
  </div>
</div>

{{-- ══════════════════════════════════════════
     DOWNLOAD EXCEL — pick the columns first
══════════════════════════════════════════ --}}
<div id="expertExportModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card ct-upload-card" style="max-width:460px">
    <div class="ct-up-head">
      <span class="ct-up-head-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
      </span>
      <span class="ct-up-head-text">
        <span class="ct-modal-title">Export</span>
        <span class="ct-up-sub">Tick the columns you want. You see the PDF first, then download it or the Excel file.</span>
      </span>
      <button type="button" class="ct-up-close" onclick="closeExpertExportModal()" aria-label="Close">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="xp-cols" id="expertExportCols">
      @foreach (\App\Support\CapsaReports::EXPERT_LIST_COLUMNS as $key => $label)
        {{-- Name is what names the row, so it is always in the file. --}}
        <label class="xp-col">
          <input type="checkbox" value="{{ $key }}" checked @disabled($loop->first) onchange="syncExpertExport()">
          {{ $label }}@if ($loop->first) <span class="xp-always">always included</span>@endif
        </label>
      @endforeach
    </div>
    <div class="xp-quick">
      <button type="button" onclick="pickExpertExportCols(true)">Select all</button>
      <button type="button" onclick="pickExpertExportCols(false)">Name only</button>
    </div>

    <div class="ct-modal-actions">
      <button type="button" class="ct-btn ct-btn-secondary" onclick="closeExpertExportModal()">Cancel</button>
      <button type="button" id="expertExportGo" class="ct-btn ct-btn-primary" onclick="previewExpertExport()">Export</button>
    </div>
  </div>
</div>

<div id="expertRosterModal" class="ct-modal-overlay" style="display:none;">
  <div class="ct-modal-card ct-upload-card">
    <div class="ct-up-head">
      <span class="ct-up-head-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      </span>
      <span class="ct-up-head-text">
        <span class="ct-modal-title">Upload Expert List</span>
        <span class="ct-up-sub">Upload a spreadsheet file containing your faculty.</span>
      </span>
      <button type="button" class="ct-up-close" onclick="closeExpertRosterModal()" aria-label="Close">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div id="expertRosterStep1">
      {{-- The template carries the exact columns this reads, the role included,
           so nobody has to type the headings by hand and get one subtly wrong. --}}
      <div class="ct-up-hint">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      <span>Need the correct format?</span>
      <a class="ct-template-link" href="{{ route('capsa.rosters.experts.template') }}">
        Download template
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      </a>
    </div>

    <div class="ct-dropzone" id="expertRosterDrop">
      <span class="ct-drop-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      </span>
      <span class="ct-drop-title">Drag &amp; drop your file here</span>
      <span class="ct-drop-or">or</span>
      <button type="button" class="ct-drop-browse" onclick="document.getElementById('expertRosterFile').click()">Browse files</button>
      <span class="ct-drop-formats">Supported format: Microsoft <strong>Excel</strong> (.xlsx)</span>
    </div>

    {{-- Driven by the drop zone and the Browse button above, but still the one
         place the file lives - the upload reads it from here. --}}
    <input type="file" id="expertRosterFile" accept=".xlsx" class="ct-file-input" onchange="showExpertRosterFile()">

    <div class="ct-file-chip" id="expertRosterChip" style="display:none">
      <span class="ct-file-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="19"/><line x1="15" y1="13" x2="9" y2="19"/></svg>
      </span>
      <span class="ct-file-text">
        <span class="ct-file-name" id="expertRosterFileName"></span>
        <span class="ct-file-meta" id="expertRosterFileMeta"></span>
      </span>
      <span class="ct-file-ok">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="8.5 12.5 11 15 15.5 9.5"/></svg>
      </span>
      <button type="button" class="ct-file-remove" onclick="clearExpertRosterFile()" aria-label="Remove file">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div id="expertRosterResult" style="display:none;font-size:12px;text-align:left;margin:4px 0 0;"></div>
      <div class="ct-modal-actions">
        <button type="button" class="ct-btn ct-btn-secondary" onclick="closeExpertRosterModal()">Cancel</button>
        <button type="button" id="expertRosterPreviewBtn" class="ct-btn ct-btn-outline" onclick="previewExpertRoster()">Preview</button>
        <button type="button" class="ct-btn ct-btn-primary" onclick="uploadExpertRoster(this)">Save to system</button>
      </div>
    </div>

    {{-- Nothing has been written at this point. An upload approves people and
         grants them work in one go, and nothing undoes it, so the list is
         shown as it will be applied before it is. --}}
    <div id="expertRosterStep2" style="display:none">
      <div class="ct-preview-summary" id="expertRosterSummary"></div>
      <div class="ct-preview-scroll"><table class="ct-preview-table" id="expertRosterPreviewTable"></table></div>
      <div id="expertRosterErrors" style="display:none"></div>
      <div id="expertRosterSaveResult" style="display:none;font-size:12px;margin-bottom:12px"></div>
      <div class="ct-modal-actions">
        <button type="button" class="ct-btn ct-btn-secondary" onclick="backToExpertRosterFile()">Choose another file</button>
        <button type="button" id="expertRosterUploadBtn" class="ct-btn ct-btn-primary" onclick="uploadExpertRoster()">Save to system</button>
      </div>
    </div>
  </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
const addExpertModal = document.getElementById('addExpertModal');

function openAddExpertModal() {
  document.getElementById('addExpertEmail').value = '';
  document.getElementById('addExpertName').value = '';
  document.querySelectorAll('.ae-role input').forEach(function (c) { c.checked = false; });
  document.getElementById('addExpertStudyLeave').checked = false;
  document.getElementById('addExpertResult').style.display = 'none';
  addExpertModal.style.display = 'flex';
  document.getElementById('addExpertEmail').focus();
}

function closeAddExpertModal() {
  addExpertModal.style.display = 'none';
}

// ── Export: the Expert List report, with only the ticked columns, as a PDF
// first; Download PDF and Download Excel are in the preview ──
const expertExportModal = document.getElementById('expertExportModal');
const expertExportUrl = @json(route('capsa.reports.show', 'expert_list'));

function openExpertExportModal() {
  syncExpertExport();
  expertExportModal.style.display = 'flex';
}

function closeExpertExportModal() {
  expertExportModal.style.display = 'none';
}

function pickExpertExportCols(all) {
  document.querySelectorAll('#expertExportCols input:not(:disabled)').forEach(function (box) { box.checked = all; });
  syncExpertExport();
}

// The report hides what is named in ?hide=, so the address lists the unticked ones.
function expertExportLink(format, inline) {
  const hidden = Array.from(document.querySelectorAll('#expertExportCols input:not(:disabled)'))
    .filter(function (box) { return !box.checked; })
    .map(function (box) { return box.value; });
  const query = new URLSearchParams({ download: format });
  if (hidden.length) { query.set('hide', hidden.join(',')); }
  if (inline) { query.set('inline', '1'); }
  return expertExportUrl + '?' + query.toString();
}

function syncExpertExport() {
  document.getElementById('expertExportGo').dataset.href = expertExportLink('pdf', true);
}

function previewExpertExport() {
  const excel = document.createElement('a');
  excel.className = 'tx-btn';
  excel.href = expertExportLink('xlsx');
  excel.setAttribute('download', '');
  excel.innerHTML = '<svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>Download Excel';
  closeExpertExportModal();
  window.PdfViewer.open({
    url: expertExportLink('pdf', true),
    download: expertExportLink('pdf'),
    downloadLabel: 'Download PDF',
    title: 'Expert List',
    actions: [excel],
  });
}

function addExpert() {
  const box = document.getElementById('addExpertResult');
  const btn = document.getElementById('addExpertBtn');
  const roles = [...document.querySelectorAll('.ae-role input:checked')].map(function (c) { return c.value; });

  const fail = function (message) {
    box.style.display = 'block';
    box.innerHTML = '<span style="color:#dc2626">' + message + '</span>';
  };

  // Said beside the fields rather than in a toast that has gone by the time
  // they look back at what they typed.
  if (!document.getElementById('addExpertEmail').value.trim()) return fail('Type an email address.');

  box.style.display = 'none';
  btn.disabled = true;
  btn.textContent = 'Adding...';

  fetch('{{ route('capsa.experts.store') }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify({
      email: document.getElementById('addExpertEmail').value.trim(),
      name: document.getElementById('addExpertName').value.trim(),
      roles: roles,
      on_study_leave: document.getElementById('addExpertStudyLeave').checked,
    }),
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.success) return fail(data.message || 'Could not add that expert.');

      closeAddExpertModal();
      showToast(data.message, 'success');
      setTimeout(function () { window.location.reload(); }, 900);
    })
    .catch(function () { fail('Network error adding the expert.'); })
    .finally(function () {
      btn.disabled = false;
      btn.textContent = 'Add Expert';
    });
}

const expertRosterModal = document.getElementById('expertRosterModal');

function openExpertRosterModal() {
  clearExpertRosterFile();
  backToExpertRosterFile();
  expertRosterModal.style.display = 'flex';
}

/** Back to choosing a file, and back to the narrow card that step needs. */
function backToExpertRosterFile() {
  document.getElementById('expertRosterStep1').style.display = '';
  document.getElementById('expertRosterStep2').style.display = 'none';
  document.querySelector('#expertRosterModal .ct-modal-card').classList.remove('ct-modal-card-wide');
}

/** Text from the file, put on the page as text and never as markup. */
function escExpertRoster(value) {
  const box = document.createElement('div');
  box.textContent = value == null ? '' : String(value);
  return box.innerHTML;
}

const EXPERT_ROSTER_ACTIONS = {
  create: ['ct-tag-create', 'New account'],
  update: ['ct-tag-update', 'Update'],
  unchanged: ['ct-tag-same', 'No change'],
};

/** What the file would do, read back and shown before anything is written. */
function previewExpertRoster() {
  const fileInput = document.getElementById('expertRosterFile');
  const resultBox = document.getElementById('expertRosterResult');

  if (!fileInput.files.length) {
    resultBox.style.display = 'block';
    resultBox.innerHTML = '<span style="color:#dc2626">Choose a file first.</span>';
    return;
  }

  const btn = document.getElementById('expertRosterPreviewBtn');
  btn.disabled = true;
  btn.textContent = 'Reading...';

  const formData = new FormData();
  formData.append('file', fileInput.files[0]);

  fetch('{{ route('capsa.rosters.experts.preview') }}', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    body: formData,
  })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        resultBox.style.display = 'block';
        resultBox.innerHTML = `<span style="color:#dc2626">${escExpertRoster(data.message || 'That file could not be read.')}</span>`;
        return;
      }

      renderExpertRosterPreview(data);

      document.getElementById('expertRosterStep1').style.display = 'none';
      document.getElementById('expertRosterStep2').style.display = '';
      document.querySelector('#expertRosterModal .ct-modal-card').classList.add('ct-modal-card-wide');
    })
    .catch(() => {
      resultBox.style.display = 'block';
      resultBox.innerHTML = '<span style="color:#dc2626">Could not read that file. Check the connection and try again.</span>';
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = 'Preview';
    });
}

function renderExpertRosterPreview(data) {
  const summary = [
    `<span><strong>${data.rows.length}</strong> ${data.rows.length === 1 ? 'person' : 'people'} read</span>`,
    `<span><strong>${data.create}</strong> new account${data.create === 1 ? '' : 's'}</span>`,
    `<span><strong>${data.update}</strong> to update</span>`,
    `<span><strong>${data.unchanged}</strong> unchanged</span>`,
  ];
  if (data.skipped) summary.push(`<span style="color:#b91c1c"><strong>${data.skipped}</strong> skipped</span>`);
  document.getElementById('expertRosterSummary').innerHTML = summary.join('');

  const head = '<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Research Areas</th><th>Action</th></tr></thead>';

  const body = data.rows.map(row => {
    const [cls, label] = EXPERT_ROSTER_ACTIONS[row.action] || EXPERT_ROSTER_ACTIONS.unchanged;
    // Naming what moves is the point of the preview - "Update" alone does not
    // say that an upload is about to change somebody's roles.
    const changes = row.changes && row.changes.length
      ? `<div class="ct-preview-changes">${escExpertRoster(row.changes.join(', '))}</div>`
      : '';
    const roles = row.roles && row.roles.length
      ? escExpertRoster(row.roles.map(r => r.charAt(0).toUpperCase() + r.slice(1)).join(', '))
      : '<span style="color:#b45309">None named</span>';

    return `<tr>
      <td>${escExpertRoster(row.name)}</td>
      <td>${escExpertRoster(row.email)}</td>
      <td>${roles}</td>
      <td>${escExpertRoster((row.expertise || []).join(', ')) || '&mdash;'}</td>
      <td><span class="ct-tag ${cls}">${label}</span>${changes}</td>
    </tr>`;
  }).join('');

  document.getElementById('expertRosterPreviewTable').innerHTML = head + '<tbody>' + body + '</tbody>';

  const errorBox = document.getElementById('expertRosterErrors');

  if (data.errors && data.errors.length) {
    errorBox.style.display = 'block';
    errorBox.innerHTML = '<div style="font-size:12px;color:#b91c1c;max-height:120px;overflow-y:auto;margin-bottom:12px">'
      + data.errors.map(e => `<div>${escExpertRoster(e)}</div>`).join('') + '</div>';
  } else {
    errorBox.style.display = 'none';
    errorBox.innerHTML = '';
  }
}

/** Nothing chosen: no file, no chip, nothing left over from last time. */
function clearExpertRosterFile() {
  document.getElementById('expertRosterFile').value = '';
  document.getElementById('expertRosterChip').style.display = 'none';
  document.getElementById('expertRosterResult').style.display = 'none';
}

/** Name the chosen file, so what is about to be read is never a guess. */
function showExpertRosterFile() {
  const file = document.getElementById('expertRosterFile').files[0];
  const chip = document.getElementById('expertRosterChip');

  if (!file) {
    chip.style.display = 'none';
    return;
  }

  const kb = file.size / 1024;
  const size = kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.max(1, Math.round(kb)) + ' KB';
  const type = (file.name.split('.').pop() || '').toUpperCase();

  document.getElementById('expertRosterFileName').textContent = file.name;
  document.getElementById('expertRosterFileMeta').textContent = size + ' \u2022 ' + type;
  document.getElementById('expertRosterResult').style.display = 'none';
  chip.style.display = 'flex';
}

// A dropped file has to end up in the file input itself, because the upload
// reads it from there - a file held anywhere else would be invisible to it.
(function () {
  const zone = document.getElementById('expertRosterDrop');
  const input = document.getElementById('expertRosterFile');

  if (!zone || !input) return;

  ['dragenter', 'dragover'].forEach(name => zone.addEventListener(name, e => {
    e.preventDefault();
    zone.classList.add('dragging');
  }));

  ['dragleave', 'drop'].forEach(name => zone.addEventListener(name, e => {
    e.preventDefault();
    zone.classList.remove('dragging');
  }));

  zone.addEventListener('drop', e => {
    const file = e.dataTransfer.files[0];

    if (!file) return;

    const box = new DataTransfer();
    box.items.add(file);
    input.files = box.files;

    showExpertRosterFile();
  });

  // Outside the zone a dropped file would otherwise replace the whole page
  // with itself, losing whatever was on screen.
  ['dragover', 'drop'].forEach(name => window.addEventListener(name, e => {
    if (!zone.contains(e.target)) e.preventDefault();
  }));
})();

function closeExpertRosterModal() {
  expertRosterModal.style.display = 'none';
}

function uploadExpertRoster(btn) {
  const fileInput = document.getElementById('expertRosterFile');
  // Saving is offered from both steps, so a failure has to be reported on
  // whichever one is on screen - the other one's box is hidden.
  const previewing = document.getElementById('expertRosterStep2').style.display !== 'none';
  const resultBox = document.getElementById(previewing ? 'expertRosterSaveResult' : 'expertRosterResult');

  if (!fileInput.files.length) {
    resultBox.style.display = 'block';
    resultBox.innerHTML = '<span style="color:#dc2626">Choose a file first.</span>';
    return;
  }

  btn = btn || document.getElementById('expertRosterUploadBtn');
  btn.disabled = true;
  btn.textContent = 'Uploading...';

  const formData = new FormData();
  formData.append('file', fileInput.files[0]);

  fetch('{{ route('capsa.rosters.experts.store') }}', {
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

<script nonce="{{ Vite::cspNonce() }}">
// ── TOAST NOTIFICATION ───────────────────────────────────────
function showToast(message, type = 'info', title = null) {
  if (window.ctToast) { return ctToast(message, type, { title }); }
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

// Highest degree, which decides who can chair a panel. Saved the moment it
// is picked; kept here too, so reopening the same expert shows the new value
// without reloading the page.
let currentDetailExpertId = null;
const degreeSaved = {};

function updateDegreeNote(level) {
  const canChair = level === 'master' || level === 'doctorate';
  const note = document.getElementById('detailDegreeNote');
  note.textContent = canChair ? 'Can be Panel Chair' : "Cannot be Panel Chair - needs a Master's or higher";
  note.style.color = canChair ? 'var(--green)' : 'var(--text3)';
}

function saveExpertDegree(select) {
  if (!currentDetailExpertId) return;
  const id = currentDetailExpertId;
  const value = select.value;
  select.disabled = true;

  fetch('{{ url('capsa/experts') }}/' + id + '/degree', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    // The title (MIT, PhD); the server works out the level from it.
    body: JSON.stringify({ degree: value || null }),
  })
    .then(r => r.json().then(d => ({ ok: r.ok && d.success, d })))
    .then(({ ok, d }) => {
      showToast(d.message || (ok ? 'Degree saved' : 'Could not save the degree'), ok ? 'success' : 'error');
      if (ok) { degreeSaved[id] = { title: value, level: d.highest_degree || '' }; updateDegreeNote(d.highest_degree || ''); }
    })
    .catch(() => showToast('Network error. Nothing was saved.', 'error'))
    .finally(() => { select.disabled = false; });
}

// Study leave: saved the moment it is ticked, like the degree.
const leaveSaved = {};

function setStudyLeave(id, value, reload) {
  return fetch('{{ url('capsa/experts') }}/' + id + '/study-leave', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    body: JSON.stringify({ on_study_leave: value }),
  })
    .then(r => r.json().then(d => ({ ok: r.ok && d.success, d })))
    .then(({ ok, d }) => {
      showToast(d.message || (ok ? 'Saved' : 'Could not save that'), ok ? 'success' : 'error');
      if (ok && reload) { setTimeout(function () { window.location.reload(); }, 700); }
      return { ok, d };
    });
}

function saveStudyLeave(box) {
  if (!currentDetailExpertId) return;
  const id = currentDetailExpertId;
  const value = box.checked;
  box.disabled = true;

  setStudyLeave(id, value, false)
    .then(({ ok, d }) => {
      if (!ok) { box.checked = !value; return; }
      leaveSaved[id] = !!d.on_study_leave;
      const row = document.querySelector('tr[data-id="' + id + '"] .td-main');
      if (row) {
        let tag = row.querySelector('.cx-leave-tag');
        if (d.on_study_leave && !tag) { tag = document.createElement('span'); tag.className = 'cx-leave-tag'; tag.textContent = 'Study leave'; row.appendChild(tag); }
        if (!d.on_study_leave && tag) { tag.remove(); }
      }
    })
    .catch(() => { box.checked = !value; showToast('Network error. Nothing was saved.', 'error'); })
    .finally(() => { box.disabled = false; });
}

function openExpertDetail(data) {
  document.getElementById('detailAvatar').src = data.avatar;
  document.getElementById('detailName').textContent = data.name;
  document.getElementById('detailEmail').textContent = data.email;
  document.getElementById('detailCredentials').textContent = data.credentials || 'None on file';
  currentDetailExpertId = data.expert_id;
  const savedDegree = degreeSaved[data.expert_id];
  document.getElementById('detailDegree').value = savedDegree ? savedDegree.title : (data.degree_title || '');
  updateDegreeNote(savedDegree ? savedDegree.level : (data.highest_degree || ''));
  document.getElementById('detailStudyLeave').checked = data.expert_id in leaveSaved ? leaveSaved[data.expert_id] : !!data.on_study_leave;
  document.getElementById('detailProgram').textContent = data.program || 'Not set';
  // Kept per side - adviser, consultant, grammarian, panel - once the expert
  // has saved them that way; one list until then.
  const detailExpertise = document.getElementById('detailExpertise');
  const bySide = data.expertise_by_side || {};
  detailExpertise.textContent = '';
  if (Object.keys(bySide).length) {
    Object.entries(bySide).forEach(([side, fields]) => {
      const line = document.createElement('div');
      line.className = 'expert-detail-side';
      const label = document.createElement('b');
      label.textContent = 'As ' + side + ': ';
      line.appendChild(label);
      line.appendChild(document.createTextNode(fields.join(', ')));
      detailExpertise.appendChild(line);
    });
  } else {
    detailExpertise.textContent = data.expertise.length ? data.expertise.join(', ') : 'None specified';
  }
  document.getElementById('detailCapacity').textContent = data.capacity.length ? data.capacity.join(' · ') : 'Not set';

  // No separate "CAPSA" chip - is_capsa stays synced with the
  // capstone_adviser role, so data.roles already says "Capstone Subject
  // Adviser" for a CaPSA/TSA account. (Admin accounts never appear in this
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

<script nonce="{{ Vite::cspNonce() }}">
// ── acting on several experts at once ──
// Both loop the endpoint that already changes one expert, so the rules about
// what a CaPSA/TSA may set stay in one place.

function bulkNames(rows) {
  return rows.map(function (tr) {
    const el = tr.querySelector('.td-main');
    return el ? el.textContent.trim() : '';
  }).filter(Boolean);
}

onBulk('bulkRoles', function (ids, rows) {
  const names = bulkNames(rows);
  // Nothing pre-ticked for a group: they hold different roles, and starting
  // from one person's would quietly hand those roles to everyone else.
  openRoles(ids, names.length > 2 ? names.length + ' experts' : names.join(', '), []);
});

// One expert, through the sheet the bar uses.
function removeOneExpert(id, name) {
  confirmSheet({
    title: 'Deactivate ' + name + "'s account?",
    names: [name],
    body: 'They can no longer sign in and cannot be assigned to a team. Nothing is '
        + 'deleted - their expertise, their teams and their history stay, and you can '
        + 'switch the account back on from this same menu. Every team they advise is told.',
    confirmLabel: 'Deactivate account',
    danger: true,
  }).then(function (yes) {
    if (!yes) return;
    fetch('{{ url('capsa/experts') }}/' + id, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    }).then(function (r) {
      showToast(r.ok ? name + ' deactivated' : 'Could not deactivate them', r.ok ? 'success' : 'error');
      if (r.ok) setTimeout(() => window.location.reload(), 900);
    });
  });
}

// Switching one back on asks nothing: it undoes something, and it is the
// deactivate above that was worth a confirmation.
function reactivateExpert(id, name) {
  fetch(`{{ route('capsa.experts.activation.update', ['expert' => 'EXPERT_ID']) }}`.replace('EXPERT_ID', id), {
    method: 'PUT',
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
  }).then(function (r) {
    showToast(r.ok ? name + ' is active again' : 'Could not switch it back on',
      r.ok ? 'success' : 'error');
    if (r.ok) setTimeout(() => window.location.reload(), 900);
  });
}

onBulk('bulkRemove', function (ids, rows) {
  confirmSheet({
    title: 'Remove ' + ids.length + (ids.length === 1 ? ' expert' : ' experts') + '?',
    names: bulkNames(rows),
    body: 'Every team they advise is told. This cannot be undone.',
    confirmLabel: ids.length === 1 ? 'Remove expert' : 'Remove ' + ids.length + ' experts',
    danger: true,
  }).then(function (yes) {
    if (!yes) return;

    Promise.all(ids.map(function (id) {
      return fetch('{{ url('capsa/experts') }}/' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
      }).then(r => r.ok).catch(() => false);
    })).then(function (results) {
      const ok = results.filter(Boolean).length;
      const failed = results.length - ok;
      showToast(failed ? ok + ' removed, ' + failed + ' could not be' : ok + ' removed',
        failed ? 'error' : 'success');
      setTimeout(() => window.location.reload(), 900);
    });
  });
});
</script>

@include('partials.app-dialog')
@include('partials.row-menu')
@include('partials.email-lookup', ['input' => 'addExpertEmail', 'kind' => 'expert', 'nameInput' => 'addExpertName'])

@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
