/*
 * The submit-document form's behaviour, lifted out of the old submission
 * page so the drawer can run it on any student page. Every lookup is made
 * inside `root` (the fetched fragment), never on the whole document, so a
 * second copy of the form could never fight with the first.
 */
export function initSubmissionForm(root) {
  const SUBMIT_URL = root.dataset.submitUrl;
  const NEXT_VERSION_URL = root.dataset.nextVersionUrl;
  const FOLDERS_URL = root.dataset.foldersUrl;
  const CSRF_TOKEN = root.dataset.csrf;

  const $ = (id) => root.querySelector('#' + id);

  const dropZone = $('dropZone');
  const fileInput = $('fileInput');
  const dropText = $('dropText');
  const dropSub = $('dropSub');
  const fileNameLabel = $('fileNameLabel');
  const versionLabel = $('versionLabel');
  const chapterLabel = $('chapterLabel');

  dropZone.addEventListener('click', () => fileInput.click());
  dropZone.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
  });
  dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
  dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
  dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    if (e.dataTransfer.files.length) addFiles(e.dataTransfer.files);
  });
  fileInput.addEventListener('change', (e) => {
    if (e.target.files.length) addFiles(e.target.files);
    fileInput.value = '';
  });

  // -- Checklist requirement --
  // A signed paper - a form, or a CLF your adviser or consultant signed by
  // hand - goes to the CaPSA to check, so it needs no reviewer and a phone
  // photo will do.
  const checklistSelect = $('checklistItem');
  const checklistHint = $('checklistHint');

  // Several files can go at once, each to its own checklist line.
  let files = []; // [{ file, itemId }]
  const fileList = $('fileList');

  function optionFor(id) {
    return checklistSelect && id ? checklistSelect.querySelector('option[value="' + id + '"]') : null;
  }
  function paperItem(id) {
    const option = optionFor(id);
    return !!option && ['form', 'approval', 'custom'].includes(option.dataset.kind);
  }
  // Signed papers only: no reviewer needed. With files queued, every one must be.
  function isPaper() {
    return files.length ? files.every(f => paperItem(f.itemId)) : paperItem(checklistSelect && checklistSelect.value);
  }

  // "09 Signed CLF Approving Chapter 1 (CaPA-Adviser).pdf" -> that checklist
  // line. Letters and digits only, so "-" for "/" and a leading number match.
  const nameKey = s => (s || '').toLowerCase().replace(/\.[^.]+$/, '').replace(/^\s*\d+[\s._-]*/, '').replace(/[^a-z0-9]/g, '');

  function matchItem(fileName) {
    if (!checklistSelect) return '';
    const key = nameKey(fileName);
    let best = '';
    let strength = 0;
    checklistSelect.querySelectorAll('option:not(:disabled)').forEach(option => {
      const title = option.value ? nameKey(option.textContent.split(' — ')[0]) : '';
      if (!title) return;
      if (key === title) {
        best = option.value;
        strength = 2;
      } else if (strength < 1 && Math.min(key.length, title.length) >= 8 && (key.includes(title) || title.includes(key))) {
        best = option.value;
        strength = 1;
      }
    });
    return best;
  }

  function applyChecklistItem() {
    if (!checklistSelect) return;
    const boxes = root.querySelectorAll('#recipientGroup input[type="checkbox"]:not(:disabled)');

    if (isPaper()) {
      boxes.forEach(cb => { cb.checked = false; });
      checklistHint.textContent = 'Upload a scan or a clear phone photo of the signed paper. It goes to your CaPSA to check — no reviewer needed.';
    } else {
      checklistHint.textContent = 'Pick the requirement and your Progress Tracker updates on its own.';
    }

    if (!files.length) dropSub.textContent = isPaper() ? 'PDF, JPG or PNG up to 25MB each' : 'PDF up to 25MB each - several at once is fine';
  }

  if (checklistSelect) {
    checklistSelect.addEventListener('change', () => {
      // Files that matched no line take the one picked here.
      files.forEach(f => { if (!f.itemId) f.itemId = checklistSelect.value; });
      renderFiles();
      applyChecklistItem();
    });
    applyChecklistItem();
  }

  function addFiles(list) {
    for (const file of list) {
      const ext = file.name.split('.').pop().toLowerCase();
      if (!['pdf', 'jpg', 'jpeg', 'png'].includes(ext)) {
        showToast(file.name + ': only PDF, JPG or PNG files are accepted.', 'error');
        continue;
      }
      if (file.size > 25 * 1024 * 1024) {
        showToast(file.name + ' is too large. Max 25MB.', 'error');
        continue;
      }
      files.push({ file: file, itemId: matchItem(file.name) || (checklistSelect ? checklistSelect.value : '') });
    }
    renderFiles();
    applyChecklistItem();
  }

  function renderFiles() {
    dropZone.classList.toggle('has-file', files.length > 0);
    dropText.textContent = files.length ? files.length + (files.length === 1 ? ' file' : ' files') + ' ready' : 'Click or drag files to upload';
    if (files.length) dropSub.textContent = 'Click or drop to add more';
    fileList.innerHTML = '';

    files.forEach((entry, i) => {
      const row = document.createElement('div');
      row.className = 'file-row';

      const name = document.createElement('div');
      name.className = 'file-row-name';
      name.title = entry.file.name;
      name.textContent = entry.file.name;
      const size = document.createElement('small');
      size.textContent = (entry.file.size / (1024 * 1024)).toFixed(2) + ' MB';
      name.appendChild(size);
      row.appendChild(name);

      if (checklistSelect) {
        const select = checklistSelect.cloneNode(true);
        select.removeAttribute('id');
        select.value = entry.itemId;
        select.setAttribute('aria-label', 'Requirement for ' + entry.file.name);
        select.addEventListener('change', () => { entry.itemId = select.value; applyChecklistItem(); });
        row.appendChild(select);
      }

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'file-row-remove';
      remove.textContent = 'Remove';
      remove.addEventListener('click', () => { files.splice(i, 1); renderFiles(); applyChecklistItem(); });

      // Drawn from the file itself - pages on canvases, no iframe or viewer menus.
      const preview = document.createElement('button');
      preview.type = 'button';
      preview.className = 'file-row-preview';
      preview.textContent = 'Preview';
      preview.addEventListener('click', () => window.previewSubmissionFile && window.previewSubmissionFile(entry.file));

      const acts = document.createElement('div');
      acts.className = 'file-row-acts';
      acts.append(preview, remove);
      row.appendChild(acts);

      fileList.appendChild(row);
    });

    if (files.length === 1) {
      fileNameLabel.textContent = files[0].file.name;
      updateVersionLabel(files[0].file.name);
    } else {
      fileNameLabel.textContent = files.length ? files.length + ' files' : 'No file selected yet';
      versionLabel.textContent = files.length ? 'Each file is versioned on its own' : 'Version 1';
      if (chapterLabel) chapterLabel.textContent = '—';
    }
  }

  // Version only carries forward for a resubmission of the *same* document.
  // The server (SubmissionController::detectChapter) looks for a "Chapter N"
  // reference in the name and groups by that when present, so a changing
  // date/suffix around it doesn't reset the version count; otherwise it
  // falls back to matching the exact file name.
  function updateVersionLabel(fileName) {
    if (!versionLabel) return;
    const title = fileName.replace(/\.[^/.]+$/, '');
    versionLabel.textContent = 'Checking…';
    if (chapterLabel) chapterLabel.textContent = '—';
    fetch(NEXT_VERSION_URL + '?title=' + encodeURIComponent(title))
      .then(r => r.json())
      .then(data => {
        versionLabel.textContent = 'Version ' + (data.version || 1);
        if (chapterLabel) {
          chapterLabel.textContent = data.chapter || 'Not detected (using exact file name)';
        }
      })
      .catch(() => {
        versionLabel.textContent = 'Version 1';
      });
  }

  // -- Subject Adviser folder picker --
  // Only present when Subject Adviser is one of the assigned recipients (see
  // the condition in the blade fragment). Fetches the adviser's named folders
  // once and shows the picker only while the Subject Adviser checkbox is
  // actually checked and they've set folders up - otherwise the submission
  // just goes to the default per-team folder, same as any other recipient.
  const folderWrap = $('folderSelectWrap');
  const folderSelect = $('subjectAdviserFolder');
  const subjectAdviserCheckbox = root.querySelector('#recipientGroup input[data-role="subject_adviser"]');
  let subjectAdviserFolders = [];

  function refreshFolderPickerVisibility() {
    if (!folderWrap || !subjectAdviserCheckbox) return;
    folderWrap.style.display = (subjectAdviserCheckbox.checked && subjectAdviserFolders.length) ? '' : 'none';
  }

  if (folderWrap && folderSelect) {
    fetch(FOLDERS_URL)
      .then(r => r.json())
      .then(data => {
        subjectAdviserFolders = data.folders || [];
        if (!subjectAdviserFolders.length) return;
        folderSelect.innerHTML = '<option value="" disabled selected>Select a folder</option>' +
          subjectAdviserFolders.map(f => `<option value="${f.drive_folder_id}">${f.name}</option>`).join('');
        refreshFolderPickerVisibility();
      })
      .catch(() => {});

    if (subjectAdviserCheckbox) {
      subjectAdviserCheckbox.addEventListener('change', refreshFolderPickerVisibility);
    }
  }

  // Each file is its own submission, sent one after another, so every file is
  // stored, versioned and ticked on the checklist line it was given.
  $('submitBtn').addEventListener('click', async function () {
    const checked = Array.from(root.querySelectorAll('#recipientGroup input[type="checkbox"]:checked')).map(cb => cb.value);

    if (!files.length) {
      showToast('Please attach a file before submitting.', 'error');
      return;
    }
    if (checked.length === 0 && !isPaper()) {
      showToast('Please select at least one recipient for the documents being reviewed.', 'error');
      return;
    }
    const notPdf = files.find(f => !paperItem(f.itemId) && !f.file.name.toLowerCase().endsWith('.pdf'));
    if (notPdf) {
      showToast(notPdf.file.name + ': a document sent for review must be a PDF. Only signed papers can be photos.', 'error');
      return;
    }
    if (folderWrap && folderWrap.style.display !== 'none' && !folderSelect.value) {
      showToast('Please select which folder to submit to for your Subject Adviser.', 'error');
      return;
    }

    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;

    const total = files.length;
    let sent = 0;
    const driveProblems = [];
    let failure = null;

    for (const entry of files) {
      btn.innerHTML = 'Submitting ' + (sent + 1) + ' of ' + total + '…';

      const fd = new FormData();
      fd.append('file', entry.file);
      // A signed paper goes to the CaPSA, not to a reviewer.
      if (!paperItem(entry.itemId)) checked.forEach(role => fd.append('recipients[]', role));
      if (entry.itemId) fd.append('deliverable_id', entry.itemId);
      if (!paperItem(entry.itemId) && folderWrap && folderWrap.style.display !== 'none' && folderSelect.value) {
        fd.append('subject_adviser_folder_id', folderSelect.value);
      }

      try {
        const data = await fetch(SUBMIT_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }, body: fd }).then(r => r.json());
        if (!data.success) {
          failure = entry.file.name + ': ' + (data.message || 'Submission failed.');
          break;
        }
        (data.recipients || [])
          .filter(r => ['not_connected', 'failed', 'unresolved'].includes(r.status))
          .forEach(r => driveProblems.push(entry.file.name + ' - ' + r.role + ' (' + r.status + ')'));
        sent++;
      } catch (e) {
        failure = entry.file.name + ': submission failed. Please try again.';
        break;
      }
    }

    btn.disabled = false;
    btn.innerHTML = originalText;

    if (failure) {
      // What went through is off the list, so trying again never sends it twice.
      files = files.slice(sent);
      renderFiles();
      applyChecklistItem();
      showToast((sent ? sent + ' of ' + total + ' sent. Stopped at ' : '') + failure, 'error');
      return;
    }

    let msg = total === 1 ? 'Document submitted successfully.' : 'All ' + total + ' files submitted.';
    if (driveProblems.length) msg += ' Some recipients could not receive a Drive copy: ' + driveProblems.join(', ');
    showToast(msg, driveProblems.length ? 'error' : 'success');
    // The page behind the drawer shows this submission's progress, so it is
    // refreshed once the message has been read.
    setTimeout(() => window.location.reload(), 1800);
  });
}

// The message lives outside the drawer, so it survives the drawer closing.
export function showToast(message, type) {
  type = type || 'success';
  const toast = document.getElementById('submission-toast');
  if (!toast) return;
  toast.textContent = (type === 'error' ? '✕ ' : '✓ ') + message;
  toast.classList.remove('success', 'error');
  toast.classList.add('show', type);
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toast.classList.remove('show'), 4000);
}
