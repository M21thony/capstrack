{{-- The team review modal: pre-proposal approve/reject with the original
     PDF and duplication report, plus the Verify action.

     Extracted from capsa/verify_teams.blade.php so Team Management can open
     the same modal rather than grow a second, drifting copy of a review
     flow this involved. Callers pass the payload openVerifyModal() expects
     (see TeamManagementController / AdminTeamVerificationController). --}}
<div class="modal-overlay" id="verify-modal" onclick="if(event.target===this)closeVerifyModal()">
  <div class="modal-box" style="max-width:720px;max-height:85vh;overflow-y:auto">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:12px">
        <div id="vm-avatar" style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0">--</div>
        <div>
          <div style="font-size:16px;font-weight:700;color:var(--text)" id="vm-name"></div>
          <div style="font-size:11px;color:var(--text3)" id="vm-section"></div>
        </div>
      </div>
      <button onclick="closeVerifyModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text3);padding:0;line-height:1">&times;</button>
    </div>

    <div style="font-size:12px;color:var(--text2);line-height:1.5;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border)">
      <strong style="color:var(--text)">Capstone Title:</strong> <span id="vm-title"></span>
    </div>

    <div style="margin-bottom:16px">
      <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px">Tags</div>
      <div id="vm-tags" style="display:flex;flex-wrap:wrap;gap:6px"></div>
    </div>

    <div style="margin-bottom:16px">
      <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px">Team Leader</div>
      <div style="font-size:13px;color:var(--text)" id="vm-leader"></div>
    </div>

    <div style="margin-bottom:16px">
      <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px">
        Members <span id="vm-member-count" style="color:var(--accent3)"></span>
      </div>
      <div id="vm-members" style="display:flex;flex-direction:column;gap:6px"></div>
    </div>

    <div style="margin-bottom:16px">
      <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px">Submitted Pre-Proposals</div>
      <div id="vm-preproposals" style="display:flex;flex-direction:column;gap:10px"></div>
    </div>

    {{-- Verify Team is the switch that makes a team official for the
         semester, and nothing said so - it read as a second, mystery button
         next to the approvals. Say what it does, and what is still in the
         way. --}}
    <div id="vm-verify-note" style="display:none;font-size:12px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;margin-bottom:12px">
      Finish reviewing every title first. This team has a final title once you have
      decided on all of them &mdash; approve or reject each one &mdash; and if you
      approve more than one, once the team has picked between them.
    </div>

    <form id="vm-form" method="POST" style="border-top:1px solid var(--border);padding-top:16px">
      @csrf
      <div style="font-size:12px;color:var(--text2);line-height:1.6;margin-bottom:12px">
        <strong style="color:var(--text)">Verifying</strong> confirms this team for the semester.
        Its members and title are settled from then on, and it becomes eligible for
        a panel, its checklist, and announcements sent to confirmed teams.
      </div>
      <button type="submit" class="btn btn-primary" id="vm-verify-btn" style="width:100%;padding:10px;font-size:13px">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:6px"><polyline points="20 6 9 17 4 12"/></svg>
        Verify Team
      </button>
    </form>
  </div>
</div>

<div class="modal-overlay" id="pp-reject-modal" onclick="if(event.target===this)closePpRejectModal()">
  <div class="modal-box" style="max-width:420px">
    <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:12px">Reject this proposal</div>
    <textarea id="pp-reject-reason" placeholder="Reason (required)" style="width:100%;min-height:80px;padding:10px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);color:var(--text);font-size:13px;box-sizing:border-box;font-family:inherit"></textarea>
    <div id="pp-reject-error" style="display:none;font-size:11px;color:#991b1b;margin-top:4px">A reason is required.</div>
    <div style="display:flex;gap:8px;margin-top:14px">
      <button type="button" class="btn" style="flex:1;background:transparent;border:1px solid var(--border);color:var(--text2)" onclick="closePpRejectModal()">Cancel</button>
      <button type="button" class="btn" style="flex:1;background:#dc3545;color:#fff;border:none" onclick="confirmPpReject()">Reject</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="pdf-preview-modal" onclick="if(event.target===this)closePdfPreview()">
  <div class="modal-box" style="max-width:900px;width:95%;height:85vh;display:flex;flex-direction:column;padding:16px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-shrink:0">
      <span style="font-size:14px;font-weight:700;color:var(--text)">Original Pre-Proposal PDF</span>
      <button type="button" onclick="closePdfPreview()" style="border:none;background:none;font-size:20px;line-height:1;cursor:pointer;color:var(--text3)" aria-label="Close">&times;</button>
    </div>
    <iframe id="pdf-preview-frame" src="" style="flex:1;width:100%;border:1px solid var(--border);border-radius:8px"></iframe>
  </div>
</div>

<style>
  /* Collapsed detail on each match in the duplication report. */
  .ppdup-toggle{display:block;margin-top:6px;padding:0;border:none;background:none;font-family:inherit;font-size:11px;font-weight:600;color:var(--primary,#5b5bd6);cursor:pointer;text-align:left}
  .ppdup-toggle:hover{text-decoration:underline}
  .ppdup-panel{display:none;margin-top:5px;padding:7px 9px;border-radius:6px;background:var(--bg3);border:1px solid var(--border)}
  .ppdup-panel.open{display:block}
  .ppdup-abstract{font-size:11px;color:var(--text2);line-height:1.55;max-height:200px;overflow-y:auto;white-space:pre-wrap}
  .ppdup-abstract mark{background:rgba(201,168,44,.35);color:inherit;padding:0 1px;border-radius:2px}

.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;pointer-events:none;transition:opacity 0.2s}
.modal-overlay.open{opacity:1;pointer-events:auto}
.modal-box{background:#fff;border:1px solid var(--border);border-radius:16px;padding:28px;max-width:560px;width:90%}
.vm-member-row{display:flex;align-items:center;gap:10px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:8px 12px}
.vm-member-row .vm-member-avatar{width:26px;height:26px;border-radius:6px;background:linear-gradient(135deg,#c9a82c,#8a6e10);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#fff;flex-shrink:0}
.vm-member-row .vm-member-name{font-size:13px;color:var(--text)}
.vm-member-row .vm-member-role{font-size:11px;color:var(--text3)}
</style>

<script>
var vmData = null;

// Show/hide one of a match's panels. Kept collapsed so a report with ten
// matches is still scannable.
function togglePpDup(id, btn, kind) {
  var el = document.getElementById(id);
  if (!el) return;

  var open = el.classList.toggle('open');
  var label = kind === 'abstract' ? 'abstract' : 'similarities &amp; differences';
  btn.innerHTML = (open ? 'Hide ' : 'Show ') + label;
}

// Mark the team's own significant words wherever they turn up in a matched
// abstract - the same literal, checkable signal the team was shown, rather
// than asking the CAPSA to spot the overlap by eye.
function highlightWords(text, words) {
  var safe = escapeHtml(text || '');

  if (!words || !words.length) return safe;

  var pattern = words
    .filter(Boolean)
    .map(function (w) { return String(w).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); })
    .join('|');

  if (!pattern) return safe;

  return safe.replace(new RegExp('\\b(' + pattern + ')\\b', 'gi'), '<mark>$1</mark>');
}

function openVerifyModal(data) {
  vmData = data;
  document.getElementById('vm-avatar').style.background = 'linear-gradient(135deg,' + data.gradient + ')';
  document.getElementById('vm-avatar').textContent = data.initials;
  document.getElementById('vm-name').textContent = data.team_name;
  document.getElementById('vm-section').textContent = 'Section: ' + data.section;
  document.getElementById('vm-title').textContent = data.project_title;

  var tagsEl = document.getElementById('vm-tags');
  tagsEl.innerHTML = '';
  if (data.tags && data.tags.length) {
    data.tags.forEach(function (tag) {
      var s = document.createElement('span');
      s.className = 'badge badge-gold';
      s.style.cssText = 'font-size:10px';
      s.textContent = tag;
      tagsEl.appendChild(s);
    });
  } else {
    tagsEl.innerHTML = '<span style="font-size:12px;color:var(--text3)">—</span>';
  }

  document.getElementById('vm-leader').textContent = data.leader;
  document.getElementById('vm-member-count').textContent = '(' + data.member_count + ' / 5)';

  var membersEl = document.getElementById('vm-members');
  membersEl.innerHTML = '';
  if (data.members && data.members.length) {
    data.members.forEach(function (name) {
      var row = document.createElement('div');
      row.className = 'vm-member-row';
      var av = document.createElement('div');
      av.className = 'vm-member-avatar';
      av.textContent = name.split(' ').filter(Boolean).map(function (w) { return w[0].toUpperCase(); }).slice(0, 2).join('');
      var nm = document.createElement('div');
      nm.className = 'vm-member-name';
      nm.textContent = name;
      row.appendChild(av);
      row.appendChild(nm);
      membersEl.appendChild(row);
    });
  } else {
    membersEl.innerHTML = '<span style="font-size:12px;color:var(--text3)">No members yet</span>';
  }

  renderPreProposals(data.pre_proposals || [], data.selected_pre_proposal_id);

  var form = document.getElementById('vm-form');
  form.style.display = data.verified ? 'none' : 'block';
  form.action = data.verify_url;

  var verifyBtn = document.getElementById('vm-verify-btn');
  var verifyNote = document.getElementById('vm-verify-note');
  var resolved = !!data.selected_pre_proposal_id;
  verifyBtn.disabled = !resolved;
  verifyBtn.style.opacity = resolved ? '1' : '0.5';
  verifyBtn.style.cursor = resolved ? 'pointer' : 'not-allowed';
  verifyNote.style.display = resolved ? 'none' : 'block';

  document.getElementById('verify-modal').classList.add('open');
}
function closeVerifyModal() {
  document.getElementById('verify-modal').classList.remove('open');

  // The table behind still shows the counts from before. Refresh it once, on
  // the way out, instead of after every decision - which is what used to shut
  // the modal and send you back to click Review again.
  if (ppDecided) {
    window.location.reload();
  }
}

// ── Per-proposal approve/reject (mirrors admin/approval.blade.php) ────────
var ppActiveId = null;
var ppDecided = false;

function renderPreProposals(proposals, selectedId) {
  var wrap = document.getElementById('vm-preproposals');
  wrap.innerHTML = '';

  if (!proposals.length) {
    wrap.innerHTML = '<span style="font-size:12px;color:var(--text3)">No pre-proposals submitted yet.</span>';
    return;
  }

  proposals.forEach(function (p) {
    var card = document.createElement('div');
    card.id = 'pp-card-' + p.id;
    card.style.cssText = 'border:1px solid var(--border);border-radius:10px;padding:12px 14px;background:var(--bg3)';

    var badgeClass = p.status === 'approved' ? 'badge-green' : (p.status === 'rejected' ? 'badge-red' : 'badge-amber');
    var isSelected = selectedId && p.id === selectedId;

    var dup = p.duplication;
    var dupBadgeClass = dup ? (dup.verdict === 'High Similarity' ? 'badge-red' : (dup.verdict === 'Moderate Similarity' ? 'badge-amber' : 'badge-green')) : '';
    // The actual duplication REPORT, not just a bare score - this is the
    // whole point of the adviser's requirement (a report that "travels
    // with the submission"), so the CAPSA can see what was flagged and why
    // instead of trusting a percentage blind.
    // The full report, the same one the team saw before submitting: every
    // match with its abstract, and what the check made of it. A title and a
    // percentage is not enough to judge whether a topic is too close to
    // something already done - the abstract is where that shows.
    var highlight = (dup && dup.highlight_words) || [];

    var matchesHtml = (dup && dup.matches && dup.matches.length)
      ? '<div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text3);letter-spacing:0.4px;margin-top:10px">'
          + dup.matches.length + ' matched capstone' + (dup.matches.length === 1 ? '' : 's') + '</div>'
        + dup.matches.map(function (m, i) {
          var mTone = m.score >= 65 ? 'red' : (m.score >= 55 ? 'amber' : 'green');
          var meta = [m.academic_year, m.program].filter(Boolean).join(' \u00b7 ');
          var notesId = 'ppdup-notes-' + p.id + '-' + i;
          var absId = 'ppdup-abs-' + p.id + '-' + i;

          var notes = ''
            + (m.similarities ? '<div><strong>Similarities:</strong> ' + escapeHtml(m.similarities) + '</div>' : '')
            + (m.differences ? '<div style="margin-top:3px"><strong>Differences:</strong> ' + escapeHtml(m.differences) + '</div>' : '')
            + (m.suggestion ? '<div style="margin-top:3px;color:var(--accent3)"><strong>Suggestion:</strong> ' + escapeHtml(m.suggestion) + '</div>' : '');

          return '<div style="border:1px solid var(--border);border-radius:8px;padding:8px 10px;margin-top:6px;background:#fff">'
            + '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">'
            + '<span style="font-size:11.5px;font-weight:600;color:var(--text)">' + escapeHtml(m.title || '') + '</span>'
            + '<span class="badge badge-' + mTone + '" style="font-family:\'Space Mono\',monospace;font-weight:700;flex-shrink:0">' + m.score + '%</span>'
            + '</div>'
            + (meta ? '<div style="font-size:10.5px;color:var(--text3);margin-top:2px">' + escapeHtml(meta) + '</div>' : '')
            + (m.reason ? '<div style="font-size:11px;color:var(--text3);margin-top:3px">' + escapeHtml(m.reason) + '</div>' : '')
            + (notes
                ? '<button type="button" class="ppdup-toggle" onclick="togglePpDup(\'' + notesId + '\', this, \'notes\')">Show similarities &amp; differences</button>'
                  + '<div id="' + notesId + '" class="ppdup-panel" style="font-size:11px;color:var(--text2);line-height:1.5">' + notes + '</div>'
                : '')
            + (m.abstract
                ? '<button type="button" class="ppdup-toggle" onclick="togglePpDup(\'' + absId + '\', this, \'abstract\')">Show abstract</button>'
                  + '<div id="' + absId + '" class="ppdup-panel ppdup-abstract">' + highlightWords(m.abstract, highlight) + '</div>'
                : '');
        }).join('')
      : (dup ? '<div style="font-size:11.5px;color:var(--green);margin-top:8px">No similar capstones were found.</div>' : '');

    var dupHtml = dup
      ? '<div style="margin-top:8px">'
        + '<div style="font-size:12px"><span class="badge ' + dupBadgeClass + '">' + dup.verdict + '</span> <span style="font-family:\'Space Mono\',monospace;font-weight:700;margin-left:6px">' + dup.top_score + '%</span></div>'
        + matchesHtml
        + '</div>'
      : '<div style="font-size:11.5px;color:var(--text3);margin-top:8px">No duplication check was run for this title.</div>';

    var contentHtml = '<div style="font-size:12px;color:var(--text2);line-height:1.5;margin-top:8px">'
      + '<div><strong style="color:var(--text)">SDG:</strong> ' + escapeHtml(p.sdg_goals || '—') + '</div>'
      + '<div><strong style="color:var(--text)">Research Agenda:</strong> ' + escapeHtml(joinStringList(p.research_agenda) || '—') + '</div>'
      + '<div><strong style="color:var(--text)">Problem Statement:</strong> ' + escapeHtml(p.problem_statement || '—') + '</div>'
      + '<div><strong style="color:var(--text)">Proposed Solution:</strong> ' + escapeHtml(p.proposed_solution || '—') + '</div>'
      + '<div><strong style="color:var(--text)">Scope:</strong> ' + escapeHtml(p.scope || '—') + '</div>'
      + '<div><strong style="color:var(--text)">Limitations:</strong> ' + escapeHtml(p.limitations || '—') + '</div>'
      + '<div><strong style="color:var(--text)">SDM:</strong> ' + escapeHtml(p.sdm || '—') + '</div>'
      + '</div>';

    var actionsHtml = p.status === 'pending'
      ? '<div style="display:flex;gap:8px;margin-top:10px" id="pp-actions-' + p.id + '">'
        + '<button type="button" class="btn btn-primary" style="font-size:11px;padding:5px 12px" onclick="ppApprove(' + p.id + ', \'' + p.approve_url + '\')">Approve</button>'
        + '<button type="button" class="btn" style="font-size:11px;padding:5px 12px;background:transparent;border:1px solid var(--border);color:var(--text2)" onclick="openPpRejectModal(' + p.id + ', \'' + p.reject_url + '\')">Reject</button>'
        + '</div>'
      : (p.status === 'rejected' ? '<div style="font-size:11px;color:#991b1b;margin-top:6px">Rejected: ' + escapeHtml(p.rejection_reason || '') + '</div>' : '');

    var documentLinkHtml = p.document_url
      ? '<button type="button" onclick="openPdfPreview(\'' + p.document_url + '\')" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:var(--primary,#5b5bd6);background:none;border:none;padding:0;cursor:pointer;margin-top:6px">📄 View Original PDF</button>'
      : '';

    card.innerHTML = '<div style="display:flex;align-items:center;justify-content:space-between;gap:10px">'
      + '<span style="font-size:13px;font-weight:600;color:var(--text)">Proposal ' + p.slot + (isSelected ? ' ⭐ Final Title' : '') + '</span>'
      + '<span class="badge ' + badgeClass + '">' + p.status.charAt(0).toUpperCase() + p.status.slice(1) + '</span>'
      + '</div>'
      + '<div style="font-size:13px;font-weight:500;color:var(--text);margin-top:4px">' + escapeHtml(p.title) + '</div>'
      + documentLinkHtml
      + dupHtml + contentHtml + actionsHtml;

    wrap.appendChild(card);
  });
}

function escapeHtml(str) {
  var div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
}

// Defends against a stray non-string entry (e.g. the AI extractor returning
// a nested object instead of plain text) rendering as the literal
// "[object Object]" instead of being silently dropped.
function joinStringList(list) {
  return (list || [])
    .map(function (item) { return typeof item === 'string' ? item : ''; })
    .filter(function (s) { return s.length > 0; })
    .join(', ');
}

function ppApprove(id, url) {
  fetch(url, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
  })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res.success) { alert(res.message || 'Failed.'); return; }
      ppApplyDecision(res);
    })
    .catch(function () { alert('Failed to process request.'); });
}

/**
 * Draw a decision into the open modal.
 *
 * The endpoints hand back the team's proposals in the same shape the modal
 * was opened with, so the list, the title and the Verify button can all be
 * redrawn where they stand. A CaPSA gets through all three titles in one
 * sitting instead of reopening the modal after each one.
 */
function ppApplyDecision(res) {
  ppDecided = true;

  if (res.proposals) {
    renderPreProposals(res.proposals, res.selected_pre_proposal_id || null);
  }

  var title = document.getElementById('vm-title');
  if (title) {
    // No title until the review is over and one of them has won it.
    title.textContent = res.project_title || '—';
  }

  var verifyBtn = document.getElementById('vm-verify-btn');
  var verifyNote = document.getElementById('vm-verify-note');
  if (verifyBtn) {
    var resolved = !!res.selected_pre_proposal_id;
    verifyBtn.disabled = !resolved;
    verifyBtn.style.opacity = resolved ? '1' : '0.5';
    verifyBtn.style.cursor = resolved ? 'pointer' : 'not-allowed';
    if (verifyNote) verifyNote.style.display = resolved ? 'none' : 'block';
  }
}

function openPpRejectModal(id, url) {
  ppActiveId = { id: id, url: url };
  document.getElementById('pp-reject-reason').value = '';
  document.getElementById('pp-reject-error').style.display = 'none';
  document.getElementById('pp-reject-modal').classList.add('open');
}
function closePpRejectModal() {
  document.getElementById('pp-reject-modal').classList.remove('open');
}

function openPdfPreview(url) {
  document.getElementById('pdf-preview-frame').src = url;
  document.getElementById('pdf-preview-modal').classList.add('open');
}
function closePdfPreview() {
  document.getElementById('pdf-preview-modal').classList.remove('open');
  document.getElementById('pdf-preview-frame').src = '';
}
function confirmPpReject() {
  var reason = document.getElementById('pp-reject-reason').value.trim();
  if (!reason) {
    document.getElementById('pp-reject-error').style.display = 'block';
    return;
  }

  fetch(ppActiveId.url, {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ reason: reason }),
  })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      closePpRejectModal();
      if (!res.success) { alert(res.message || 'Failed.'); return; }
      ppApplyDecision(res);
    })
    .catch(function () { alert('Failed to process request.'); });
}
</script>
