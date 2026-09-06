<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>CaPSA Management - CapsTRACK Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<style>
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

  /* Expert Management (Admin's copy: CaPSAs only, and naming them is the
     whole job). The CaPSA's own copy is resources/views/capsa/experts.blade.php
     - the faculty, their roles, and who advises whom. These two used to be one
     file switching on the viewer's role, which made neither version easy to
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

  {{-- .icon-btn lives in admin.css: both CaPSA pages use it too. --}}

  /* Capacity column - merges what used to be separate Teams / Slots Left
     columns. One row per program when an expert's slots are actually split
     that way (see CapstoneExpert::parsedCapacity()), one "ALL" row when
     they're not. */
  .capacity-stack{display:flex;flex-direction:column;gap:5px}
  .capacity-row{display:grid;grid-template-columns:38px 1fr 34px;align-items:center;gap:8px}
  .capacity-prog{font-size:10px;font-weight:700;color:var(--text3);letter-spacing:0.3px}
  .capacity-count{font-variant-numeric:tabular-nums;font-size:11px;color:var(--text3);text-align:right}


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
  .group-count{margin-left:auto;font-size:10px;font-weight:700;color:#94a3b8;font-variant-numeric:tabular-nums;background:#eceef2;padding:2px 8px;border-radius:999px}
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
    <div class="page-title">CaPSA Management</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="content">
      <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
        <div class="sk" style="height:38px;width:260px;border-radius:8px;"></div>
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
      {{-- Who owns which column. Without it a reader assumes every column on
           an editing page is editable, and two of these are not. --}}
      <p class="table-legend">
        <b>Sections</b> are yours to set.
        <b>Accepts</b> and <b>Expertise</b> belong to the expert &mdash; they choose which programs
        to take teams from and how many, on their own profile.
      </p>
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content list-page">
    @php
      // What the page is of, in a sentence. A bare count in a pill said less
      // and took the same room.
      $sectionsCovered = $experts->flatMap(fn ($e) => $e->sections ?? [])->unique()->count();
    @endphp

    {{-- The title is already in the bar above; this line says what the page
         holds, and the two things you can do to it sit opposite. --}}
    <div class="page-head">
      <div class="page-head-text">
        <span class="page-count">{{ $experts->count() }}</span>
        <span class="page-count-noun">{{ Str::plural('CaPSA', $experts->count()) }}</span>
        <span class="page-facts">
          @if ($sectionsCovered)
            <span class="fact">{{ $sectionsCovered }} {{ Str::plural('section', $sectionsCovered) }} covered</span>
          @else
            <span class="fact fact-warn">no sections covered yet</span>
          @endif
        </span>
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
      <select class="input page-filter" id="statusFilter">
        <option value="all">All Status</option>
        <option value="active">Active</option>
        <option value="full">Full</option>
        <option value="offline">Offline</option>
      </select>
      <span class="page-filters-actions">
        {{-- Admin's job here is naming CaPSAs. Getting the rest of the faculty
             into the system is the CaPSA's, from their own copy of this page,
             so the list upload and the one-off Add Expert form are not offered
             twice. --}}
        <button type="button" class="btn btn-primary" onclick="addCapsa()">Add CaPSA</button>
      </span>
    </div>

    @include('partials.row-select', [
      'table' => '.table',
      'noun' => 'expert',
      'actions' => [
        ['id' => 'bulkRemove', 'label' => 'Delete accounts', 'danger' => true],
      ],
    ])

    <div class="card list-card">
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
              {{-- You cannot put yourself in a batch: bulk-removing your own
                   account is not a thing to be one careless drag away from. --}}
              @if ($expert->expert_id === Auth::guard('expert')->id()) data-no-select="1" @endif
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
                {{-- Three actions, and two of them used to be a person with a
                     plus and a person with a minus - the shape of an add-friend
                     button, saying nothing about capstone. They carry their
                     names in here, and the colours keep them apart: amber takes
                     a role away, red deletes the account behind it. --}}
                <div class="rowmenu">
                  <button type="button" class="icon-btn rowmenu-btn" aria-expanded="false"
                          aria-label="Actions for {{ $expert->name }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.7" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.7" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.7" fill="currentColor" stroke="none"/></svg>
                  </button>
                  <div class="rowmenu-pop" hidden>
                    @if ($expert->is_capsa)
                      {{-- Only for someone who already holds the role: changing
                           which sections they cover is not a change of role, and
                           doing it through the toggle meant removing them as
                           CaPSA and making them one again. --}}
                      <button type="button" class="rowmenu-item"
                              onclick="editCapsaSections({{ $expert->expert_id }}, {{ Illuminate\Support\Js::from($expert->name) }}, {{ Illuminate\Support\Js::from($expert->sections ?? []) }})">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="6" rx="1"/><rect x="3" y="14" width="18" height="6" rx="1"/></svg> Which sections they cover
                      </button>
                    @endif
                    <button type="button" class="rowmenu-item warn"
                            onclick="toggleCapsa({{ $expert->expert_id }}, {{ $expert->is_capsa ? 'true' : 'false' }}, {{ Illuminate\Support\Js::from($expert->name) }})">
                      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="11" x2="23" y2="11"/></svg> {{ $expert->is_capsa ? 'Take away the CaPSA role' : 'Make them a CaPSA' }}
                    </button>
                    <div class="rowmenu-sep"></div>
                    <button type="button" class="rowmenu-item danger remove-trigger"
                            data-id="{{ $expert->expert_id }}" data-name="{{ $expert->name }}">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M7 6l1 14h8l1-14"/></svg> Delete this account
                    </button>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          @endforeach

<script>
  // Client-side filtering: search, role, and status.
  (function() {
    var searchInput = document.getElementById('experts-search-input');
    var statusFilter = document.getElementById('statusFilter');
    var rows = document.querySelectorAll('table.table tbody tr');

    function filterRows() {
      var q = searchInput ? searchInput.value.toLowerCase().trim() : '';
      var status = statusFilter ? statusFilter.value : 'all';

      rows.forEach(function(row) {
        // Read off the row rather than out of the cells. Counting columns to
        // find the status broke the moment one moved, and the expertise is
        // truncated on screen - the attribute holds all of it.
        var searchText = (row.dataset.search || '');
        var statusText = (row.dataset.status || '');

        var matchSearch = !q || searchText.includes(q);
        var matchStatus = true;
        if (status === 'active') matchStatus = statusText === 'active';
        else if (status === 'full') matchStatus = statusText === 'full';
        else if (status === 'offline') matchStatus = statusText === 'offline';

        row.style.display = (matchSearch && matchStatus) ? '' : 'none';
      });
    }

    if (searchInput) searchInput.addEventListener('input', filterRows);
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
    <h3 class="ct-modal-title">
      <span id="capsaModalLead">Make</span>
      <span id="capsaExpertName" style="color:#2563eb;"></span>
      <span id="capsaModalTail">a CaPSA</span>
    </h3>
    <p class="ct-modal-desc" id="capsaModalDesc">
      CaPSA duties (account approval, panel assignment, team verification, financial checks) are scoped to the section(s) assigned below.
    </p>

    {{-- Only for a person with no account yet. The same modal grants the role
         to somebody already listed, where the email is already known. --}}
    <div id="capsaNewFields" style="display:none">
      <div class="section-field">
        <label class="ct-modal-label" for="capsaEmail" style="margin-bottom:6px">
          Email address <span style="color:#ef4444;font-weight:400;">(required)</span>
        </label>
        <input type="email" id="capsaEmail" class="ct-modal-input" placeholder="name@cspc.edu.ph" autocomplete="off">
      </div>
      <div class="section-field">
        <label class="ct-modal-label" for="capsaName" style="margin-bottom:6px">Full name <span style="color:#ef4444;font-weight:400;">(required)</span></label>
        <input type="text" id="capsaName" class="ct-modal-input" placeholder="Rosa Villanueva" autocomplete="off">
      </div>
      <p id="capsaEmailError" style="display:none;color:#dc2626;font-size:12px;margin:-6px 0 12px;text-align:left;"></p>
    </div>

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
      {{-- Same picker for granting the role and for changing sections later;
           the label says which job it is doing. --}}
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

  fetch(`{{ route('capsa.experts.destroy', ['expert' => 'EXPERT_ID']) }}`.replace('EXPERT_ID', expertId), {
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
let capsaEditingSections = false;
let capsaAdding = false;

/**
 * Change the sections a CaPSA covers.
 *
 * Sections used to be settable only while granting the role, so changing
 * them meant removing the person as CaPSA and making them one again - which
 * cuts off their approval, panel, verification and financial access in
 * between, for a change that was never about their role.
 */
function editCapsaSections(expertId, expertName, current) {
  capsaTargetExpertId = expertId;
  capsaEditingSections = true;
  capsaAdding = false;
  document.getElementById('capsaNewFields').style.display = 'none';
  capsaExpertName.textContent = expertName;
  capsaSectionsError.style.display = 'none';
  resetSectionPicker();
  checkSections(current || []);
  capsaConfirmBtn.textContent = 'Save Sections';
  document.getElementById('capsaModalLead').textContent = 'Manage sections for';
  document.getElementById('capsaModalTail').textContent = '';
  document.getElementById('capsaModalDesc').textContent =
    'Tick every section they handle. Their CaPSA role is not affected - only what it covers.';
  capsaModal.style.display = 'flex';
}

/** Tick the ones they already have, and open the groups holding them. */
function checkSections(names) {
  const wanted = new Set(names.map(n => String(n).trim().toLowerCase()));

  document.querySelectorAll('#sectionList .section-opt').forEach(opt => {
    const label = opt.querySelector('span').textContent.trim().toLowerCase();
    if (wanted.has(label)) {
      opt.querySelector('input').checked = true;
      const group = opt.closest('.section-group');
      if (group) group.classList.add('open');
    }
  });

  updateAllSectionCounts();
}

/**
 * Make a CaPSA from an email address, with no account needed first.
 *
 * The job moves between faculty and there is no permanent CaPSA, so this
 * happens often enough that it should not need the expert list uploaded for
 * the sake of one person.
 */
function addCapsa() {
  capsaTargetExpertId = null;
  capsaEditingSections = false;
  capsaAdding = true;

  document.getElementById('capsaNewFields').style.display = '';
  document.getElementById('capsaEmail').value = '';
  document.getElementById('capsaName').value = '';
  document.getElementById('capsaEmailError').style.display = 'none';

  capsaExpertName.textContent = '';
  document.getElementById('capsaModalLead').textContent = 'Add a CaPSA';
  document.getElementById('capsaModalTail').textContent = '';
  document.getElementById('capsaModalDesc').textContent =
    'The account is made if it does not exist yet, and is approved straight away. CaPSA duties are scoped to the section(s) assigned below.';
  capsaConfirmBtn.textContent = 'Add CaPSA';

  capsaSectionsError.style.display = 'none';
  resetSectionPicker();
  capsaModal.style.display = 'flex';
}

function toggleCapsa(expertId, isCurrentlyCapsa, expertName) {
  capsaEditingSections = false;
  capsaAdding = false;
  document.getElementById('capsaNewFields').style.display = 'none';
  capsaConfirmBtn.textContent = 'Make CaPSA';
  document.getElementById('capsaModalLead').textContent = 'Make';
  document.getElementById('capsaModalTail').textContent = 'a CaPSA';
  document.getElementById('capsaModalDesc').textContent =
    'CaPSA duties (account approval, panel assignment, team verification, financial checks) are scoped to the section(s) assigned below.';

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

  // Said before the request goes, beside the field it is about.
  if (capsaAdding && !document.getElementById('capsaName').value.trim()) {
    const box = document.getElementById('capsaEmailError');
    box.textContent = 'Type their full name.';
    box.style.display = 'block';
    return;
  }
  capsaSectionsError.style.display = 'none';

  const wasEditing = capsaEditingSections;
  const wasAdding = capsaAdding;
  const restore = () => {
    this.disabled = false;
    this.textContent = wasAdding ? 'Add CaPSA' : (wasEditing ? 'Save Sections' : 'Make CaPSA');
  };

  this.disabled = true;
  this.textContent = 'Saving...';

  if (wasAdding) {
    addCapsaByEmail(sections, restore);

    return;
  }

  if (wasEditing) {
    saveCapsaSections(capsaTargetExpertId, sections, restore);

    return;
  }

  sendToggleCapsa(capsaTargetExpertId, sections, 'CaPSA assigned successfully.', restore);
});

/** Make the account if it is needed, grant the role, set the sections. */
function addCapsaByEmail(sections, onDone) {
  const errorBox = document.getElementById('capsaEmailError');
  errorBox.style.display = 'none';

  fetch('{{ route('admin.experts.capsa.store') }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify({
      email: document.getElementById('capsaEmail').value.trim(),
      name: document.getElementById('capsaName').value.trim(),
      sections,
    }),
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        capsaModal.style.display = 'none';
        showToast(data.message || 'CaPSA added.', 'success');
        setTimeout(() => window.location.reload(), 900);

        return;
      }

      // Beside the field it is about, rather than in a toast that has gone
      // by the time they look back at what they typed.
      errorBox.textContent = data.message || 'Could not add that CaPSA.';
      errorBox.style.display = 'block';
    })
    .catch(() => showToast('Network error adding the CaPSA.', 'error'))
    .finally(() => { if (onDone) onDone(); });
}

/** Sections only - the role is left exactly as it is. */
function saveCapsaSections(expertId, sections, onDone) {
  fetch(`{{ route('admin.experts.sections', ['expert' => 'EXPERT_ID']) }}`.replace('EXPERT_ID', expertId), {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ sections }),
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        capsaModal.style.display = 'none';
        showToast('Sections updated.', 'success');
        setTimeout(() => window.location.reload(), 700);
      } else {
        showToast(data.message || 'Could not update the sections.', 'error');
      }
    })
    .catch(() => showToast('Network error updating the sections.', 'error'))
    .finally(() => { if (onDone) onDone(); });
}

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
  fetch(`{{ route('admin.experts.capsa.update', ['expert' => 'EXPERT_ID']) }}`.replace('EXPERT_ID', expertId), {
    method: 'PUT',
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



<script>
// ── acting on several experts at once ──
// Both actions loop the endpoint that already changes one expert, so the rules
// about who may set what live in one place and cannot drift from the
// single-row path.

function bulkNames(rows) {
  return rows.map(function (tr) {
    const el = tr.querySelector('.td-main');
    return el ? el.textContent.trim() : '';
  }).filter(Boolean);
}

onBulk('bulkRemove', function (ids, rows) {
  confirmSheet({
    title: 'Delete ' + ids.length + (ids.length === 1 ? ' account' : ' accounts') + '?',
    names: bulkNames(rows),
    body: 'The account goes, not just the CaPSA role. To leave them in the system '
        + 'without their sections, use Remove CaPSA on the row instead.',
    confirmLabel: ids.length === 1 ? 'Delete account' : 'Delete ' + ids.length + ' accounts',
    danger: true,
  }).then(function (yes) {
    if (!yes) return;

    Promise.all(ids.map(function (id) {
      return fetch('{{ url('admin/experts') }}/' + id, {
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

@include('partials.confirm-sheet')
@include('partials.row-menu')
@include('partials.email-lookup', ['input' => 'capsaEmail', 'kind' => 'expert', 'nameInput' => 'capsaName'])

</body>
</html>
