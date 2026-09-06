<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Expert Management - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
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

  /* ── Compact table ───────────────────────────────────────────────────
     One line per person. Status is a dot beside the name, which buys the
     width back for the sections and the expertise - the two columns that
     were being squeezed. */
  .table td{padding-top:9px;padding-bottom:9px;vertical-align:middle}
  .table thead th{position:sticky;top:0;z-index:2;background:var(--bg2)}
  .cx-who{display:flex;align-items:center;gap:9px;cursor:pointer;min-width:0}
  .cx-av{width:26px;height:26px;border-radius:7px;object-fit:cover;flex-shrink:0}
  .cx-who .td-main{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  /* The dot is the only thing saying whether they are around, so it is not
     decoration - it carries the status the column used to. */
  .cx-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;background:var(--text3)}
  .cx-dot-active{background:var(--green)}
  .cx-dot-full{background:var(--amber)}
  .cx-dot-offline{background:var(--border2)}
  .cx-sections{font-size:11.5px;color:var(--text2);white-space:nowrap}
  .cx-skills{max-width:240px}
  .cx-chip{display:inline-block;max-width:150px;padding:2px 8px;margin:1px 3px 1px 0;border-radius:6px;
           background:var(--bg4);color:var(--text2);font-size:11px;font-weight:500;
           white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
  .cx-chip-n{background:transparent;border:1px dashed var(--border2);color:var(--text3);cursor:pointer;max-width:none}
  .cx-chip-n:hover{border-color:var(--accent);color:var(--accent3);background:var(--accent-light)}
  .table td[data-label="Action"]{text-align:right;padding-right:20px}
  .expert-actions{justify-content:flex-end}

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
  .ct-preview-scroll{max-height:52vh;overflow:auto;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:14px}
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
  /* The badge is what a CaPSA scans the list by, so the three outcomes have
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

  /* One row, never two. The buttons carried their own labels when this
     wrapped; as icons they fit across, and a 2x2 block of them read as four
     separate things rather than one row of actions. */
  .expert-actions{
    display:flex;
    flex-wrap:nowrap;
    align-items:center;
    gap:5px;
  }

  /* Capacity column - merges what used to be separate Teams / Slots Left
     columns. One row per program when an expert's slots are actually split
     that way (see CapstoneExpert::parsedCapacity()), one "ALL" row when
     they're not. Read-only here - editing capacity is Admin's job. */
  .capacity-stack{display:flex;flex-direction:column;gap:5px}
  .capacity-row{display:grid;grid-template-columns:38px 1fr 34px;align-items:center;gap:8px}
  .capacity-prog{font-size:10px;font-weight:700;color:var(--text3);letter-spacing:0.3px}
  .capacity-count{font-variant-numeric:tabular-nums;font-size:11px;color:var(--text3);text-align:right}

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
      {{-- Roles are the only column a CaPSA can change, and a page that does
           not say so invites trying the others. --}}
      <p class="table-legend">
        <b>Roles</b> are yours to set. <b>Sections</b> are the admin's.
        <b>Accepts</b> and <b>Expertise</b> belong to the expert &mdash; they choose which programs
        to take teams from and how many, on their own profile.
      </p>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">
    @php
      // The mix, not just the total: how many of each role you have is the
      // thing a CaPSA is actually deciding about when they open this page.
      $byRole = $experts->flatMap(fn ($e) => $e->roles->pluck('role_name'))
        ->countBy()
        ->map(fn ($n, $role) => $n.' '.Str::plural(Str::lower(\App\Models\Role::labelFor($role)), $n))
        ->values();
    @endphp

    <div class="page-head">
      <div class="page-head-text">
        {{ $experts->count() }} {{ Str::plural('expert', $experts->count()) }}
        @if ($byRole->isNotEmpty()) · {{ $byRole->join(', ') }} @endif
      </div>
      <div class="page-head-actions">
        {{-- The list is for a whole faculty at once. This is for the one it
             missed, so admitting a late arrival is not a spreadsheet. --}}
        <button type="button" class="btn" onclick="openExpertRosterModal()">Upload List</button>
        <button type="button" class="btn btn-primary" onclick="openAddExpertModal()">Add Expert</button>
      </div>
    </div>

    {{-- Filters on their own line. They were sharing a strip with the count
         and the two buttons, which made "look for something" and "do
         something" compete for the same row. --}}
    <div class="page-filters">
      <div class="experts-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="input" id="experts-search-input" placeholder="Search experts by name or expertise..."/>
      </div>
      <select class="input page-filter" id="experts-role-filter">
        <option>All Roles</option>
        <option>Adviser</option>
        <option>Consultant</option>
        <option>Grammarian</option>
      </select>
      <select class="input page-filter" id="statusFilter">
        <option value="all">All Status</option>
        <option value="active">Active</option>
        <option value="full">Full</option>
        <option value="offline">Offline</option>
      </select>
    </div>


    @include('partials.row-select', [
      'table' => '.table',
      'noun' => 'expert',
      'actions' => [
        ['id' => 'bulkRoles', 'label' => 'Set roles'],
        ['id' => 'bulkRemove', 'label' => 'Remove', 'danger' => true],
      ],
    ])

    <div class="card" style="padding:0;overflow:hidden">
      <table class="table">
        <thead>
          <tr>
            {{-- Status is a dot beside the name rather than a column of its
                 own: it carries one bit, and a whole column to say "Offline"
                 is width the sections and the expertise need more. --}}
            <th style="padding-left:20px">Expert</th>
            <th>Role(s)</th>
            <th>Sections</th>
            <th>Expertise</th>
            <th>Accepts <span class="th-owner">expert sets</span></th>
            <th style="text-align:right;padding-right:20px">Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach($experts as $expert)
          @php
              $expertiseList = $expert->expertise->pluck('expertise_field');
              $expertiseFull = $expertiseList->join(', ') ?: 'None specified';
              $expertiseShown = $expertiseList->take(2)->join(', ');
              $expertiseExtra = $expertiseList->count() - 2;

              $maxTeams = $expert->max_teams ?? 0;
              $teamCount = $expert->team_assignments_count ?? 0;
              $slotsLeft = max(0, $maxTeams - $teamCount);

              // Team slots belong to the roles that take teams. A CaPSA is
              // not one of them - the job is running the subject, not
              // advising groups - so a plain CaPSA has no slots to show and
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
              $computedStatus = $isFull ? 'full' : ($isOnline ? 'active' : 'offline');
              $capacityRows = $expert->parsedCapacity();

              // One mapping, on the model, so the pills here and the ones on
              // the expert's own profile panel cannot drift apart.
              $roleLabel = fn (string $roleName) => \App\Models\Role::labelFor($roleName);

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
              data-id="{{ $expert->expert_id }}"
              {{-- Removing your own account, or stripping your own roles, is
                   not a thing to be one careless drag away from. --}}
              @if ($expert->expert_id === Auth::guard('expert')->id()) data-no-select="1" @endif
              data-roles="{{ $expert->roles->pluck('role_name')->map($roleLabel)->implode(' ') }}"
              data-search="{{ Str::lower($expert->name.' '.$expert->email.' '.$expertiseFull) }}">
            <td style="padding-left:20px" data-label="Expert">
              <div class="expert-name-trigger cx-who" onclick="openExpertDetail({{ Illuminate\Support\Js::from($detailPayload) }})">
                <span class="cx-dot cx-dot-{{ $computedStatus }}" title="{{ ucfirst($computedStatus) }}"></span>
                <img src="{{ $expert->avatarUrl() }}" alt="" loading="lazy" class="cx-av"
                     onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.jpg') }}';">
                {{-- The address under the name rather than hidden in a tooltip:
                     two people share a surname often enough, and the email is
                     what tells them apart. --}}
                <span class="cx-text">
                  <span class="td-main">{{ $expert->name }}</span>
                  <span class="cx-mail">{{ $expert->email }}</span>
                </span>
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
              </div>
            </td>
            <td data-label="Sections" class="cx-sections">
              @if ($expert->is_capsa)
                {{ !empty($expert->sections) ? implode(', ', $expert->sections) : 'none set' }}
              @else
                <span style="color:var(--text3)">&mdash;</span>
              @endif
            </td>

            {{-- A chip each, so a long name truncates on its own instead of
                 being joined to the next one and cut mid-word. The counter
                 opens the detail panel, where the full list already is. --}}
            <td data-label="Expertise" class="cx-skills" title="{{ $expertiseFull }}">
              @if ($expertiseList->isEmpty())
                <span style="color:var(--text3)">None specified</span>
              @else
                @foreach ($expertiseList->take(2) as $skill)
                  <span class="cx-chip">{{ $skill }}</span>
                @endforeach
                @if ($expertiseExtra > 0)
                  <span class="cx-chip cx-chip-n" onclick="openExpertDetail({{ Illuminate\Support\Js::from($detailPayload) }})">+{{ $expertiseExtra }}</span>
                @endif
              @endif
            </td>
            <td data-label="Team Slot">
              @if (! $takesTeams)
                <span style="font-size:11px;color:var(--text3)">&mdash;</span>
              @elseif (! $slotsSet)
                <span style="font-size:11px;color:var(--text3)">Not set</span>
              @elseif (count($capacityRows) === 1)
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

            <td data-label="Action">
              <div class="expert-actions">
                <button type="button" class="icon-btn" title="Manage roles"
                        onclick="openRoles({{ $expert->expert_id }}, {{ Illuminate\Support\Js::from($expert->name) }}, {{ Illuminate\Support\Js::from($expert->roles->pluck('role_name')) }})">
                  <svg viewBox="0 0 24 24"><path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"/><circle cx="7" cy="7" r="1"/></svg>
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
        // Read off the row rather than out of the cells. Counting columns to
        // find the status broke the moment one moved, and the expertise is
        // truncated on screen - the attribute holds all of it.
        var searchText = (row.dataset.search || '');
        var roleText = (row.dataset.roles || '').toLowerCase();
        var statusText = (row.dataset.status || '');

        var matchSearch = !q || searchText.includes(q);
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

<script>
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
      <label class="ae-label" for="addExpertName">Full name <span class="ae-req">(required)</span></label>
      <input type="text" id="addExpertName" class="ct-modal-input" placeholder="Mark Anthony Balilla" autocomplete="off" style="margin-bottom:0">
    </div>

    {{-- Required here, unlike the bulk upload: adding one person is a
         deliberate act, and one with no role in it leaves an account that can
         sign in and do nothing. --}}
    <div class="ae-field">
      <label class="ae-label">Role <span class="ae-req">(required)</span></label>
      <div class="ae-roles">
        <label class="ae-role"><input type="checkbox" value="adviser">Adviser</label>
        <label class="ae-role"><input type="checkbox" value="consultant">Consultant</label>
        <label class="ae-role"><input type="checkbox" value="grammarian">Grammarian</label>
      </div>
    </div>

    <p class="ae-note">They are approved straight away &mdash; naming them here is the vetting, the same as putting them on the list. Their areas of expertise are theirs to fill in from their own profile.</p>

    <div id="addExpertResult" style="display:none;font-size:12px;margin-bottom:12px"></div>

    <div class="ct-modal-actions">
      <button type="button" class="ct-btn ct-btn-secondary" onclick="closeAddExpertModal()">Cancel</button>
      <button type="button" id="addExpertBtn" class="ct-btn ct-btn-primary" onclick="addExpert()">Add Expert</button>
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

<script>
const addExpertModal = document.getElementById('addExpertModal');

function openAddExpertModal() {
  document.getElementById('addExpertEmail').value = '';
  document.getElementById('addExpertName').value = '';
  document.querySelectorAll('.ae-role input').forEach(function (c) { c.checked = false; });
  document.getElementById('addExpertResult').style.display = 'none';
  addExpertModal.style.display = 'flex';
  document.getElementById('addExpertEmail').focus();
}

function closeAddExpertModal() {
  addExpertModal.style.display = 'none';
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
  if (!document.getElementById('addExpertName').value.trim()) return fail('Type their full name.');
  if (!roles.length) return fail('Choose at least one role.');

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

  const head = '<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Expertise</th><th>Action</th></tr></thead>';

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

<script>
// ── acting on several experts at once ──
// Both loop the endpoint that already changes one expert, so the rules about
// what a CaPSA may set stay in one place.

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

onBulk('bulkRemove', function (ids, rows) {
  const names = bulkNames(rows);
  if (!confirm('Remove ' + ids.length + (ids.length === 1 ? ' expert' : ' experts') + '?\n\n'
      + names.join('\n') + '\n\nTeams they advise are told. This cannot be undone.')) return;

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
</script>

@include('partials.email-lookup', ['input' => 'addExpertEmail', 'kind' => 'expert', 'nameInput' => 'addExpertName'])

</body>
</html>
