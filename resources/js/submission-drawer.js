/*
 * The upload pop-up. Every upload a student makes opens this one small
 * window over the page they are on - an Upload on a Progress line, Submit a
 * file or Submit revision on Documents, the phone's Submit.
 *
 * It is one screen, a panel from the right (the whole screen on a phone):
 * the file (page one, quick checks, a full preview on demand), its diagram
 * files, who it goes to - nobody ticked for a file for no line - and Send,
 * which says who gets it. Once sent, the page says so with an Undo that
 * takes it back while nobody has opened it.
 *
 * Who it goes to comes from the server (SubmissionRecipients): a first
 * upload starts from its line's rule and the team may add or drop an
 * optional reviewer; a revision goes back to the same people as the last
 * version, with nothing to pick.
 *
 * It replaced a form that dropped open under the button, pushed the page
 * apart and asked for everything at once.
 */
import * as pdfjsLib from 'pdfjs-dist';

pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url).toString();

const CONFIG = (() => {
  try {
    return JSON.parse(document.getElementById('upload-config')?.textContent || '{}');
  } catch {
    return {};
  }
})();

const MAX_MB = 25;
const TOAST_KEY = 'capstrack-upload-toast';

const ICON = {
  x: '<path d="M18 6 6 18M6 6l12 12"/>',
  upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
  send: '<path d="M22 2 11 13M22 2 15 22l-4-9-9-4 20-7Z"/>',
  check: '<polyline points="20 6 9 17 4 12"/>',
  alert: '<circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
  lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
  minus: '<line x1="5" y1="12" x2="19" y2="12"/>',
  eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
  left: '<polyline points="15 18 9 12 15 6"/>',
  right: '<polyline points="9 18 15 12 9 6"/>',
  retry: '<polyline points="1 4 1 10 7 10"/><path d="M3.5 15a9 9 0 1 0 2.1-9.4L1 10"/>',
  file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
};

const svg = (name, size = 16, stroke = 2) =>
  `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="currentColor" stroke-width="${stroke}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICON[name]}</svg>`;

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const mb = (bytes) => bytes < 1024 * 1024
  ? Math.max(1, Math.round(bytes / 1024)) + ' KB'
  : (bytes / (1024 * 1024)).toFixed(1) + ' MB';
const ext = (name) => (name.split('.').pop() || '').toLowerCase();
const ordinal = (n) => n + (([11, 12, 13].includes(n % 100)) ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] || 'th'));

/** "Chapter1_Rev1.pdf" -> 1, or null when the name names no chapter. */
function chapterInName(name) {
  const m = /(?:^|[^a-z])(?:chapter|chap|ch)[\s_-]*([0-9]{1,2})(?!\d)/i.exec(name);
  return m ? parseInt(m[1], 10) : null;
}

/** A draw.io or Visio file, sent as itself and read as its diagram pages. */
const isDiagram = (name) => ['drawio', 'vsdx'].includes(ext(name));

/** What is wrong with this file for this line, in words that say how to fix it; null when nothing is. */
function problemWith(file, accept) {
  const e = ext(file.name);
  const name = `<b>${esc(file.name)}`;
  if (!accept.includes(e)) {
    if (['doc', 'docx'].includes(e)) return `${name} is a Word file.</b> Save it as PDF (File → Save As → PDF), then add it here.`;
    if (['ppt', 'pptx'].includes(e)) return `${name} is a PowerPoint file.</b> Export it as PDF (File → Export → PDF), then add it here.`;
    if (['jpg', 'jpeg', 'png', 'heic', 'webp'].includes(e)) return `${name} is a photo.</b> This goes up as a PDF. Only signed papers can be photos.`;
    return `${name} can’t be sent here.</b> Use ${accept.includes('png') ? 'a PDF, JPG or PNG' : 'a PDF, or a draw.io (.drawio) or Visio (.vsdx) diagram'}.`;
  }
  if (file.size > MAX_MB * 1024 * 1024) {
    return `${name} is ${mb(file.size)}</b>; the limit is ${MAX_MB} MB. Compress the pictures in it, or save it with “Reduce file size”, then add it again.`;
  }
  return null;
}

// ── One upload at a time ─────────────────────────────────────────────
let S = null; // the open upload's state
let dialog = null;

function freshState(itemId, from, revises) {
  return {
    itemId: itemId || '',
    // The upload a "Submit revision" is the next version of.
    revises: revises || null,
    from,
    fixedLine: !!itemId,
    plan: null,
    loadError: null,
    step: 'pick', // pick | check | sending | sent | failed
    file: null,
    problem: null,
    pages: null,
    pdf: null,
    broken: false,
    thumbUrl: null,
    rotation: 0,
    checking: false,
    same: null,
    warnings: [],
    diagrams: [],
    diagramProblem: null,
    picked: new Set(),
    progress: 0,
    failure: null,
    result: null,
    xhr: null,
    confirmingClose: false,
  };
}

export async function openSubmission(itemId, from, revises) {
  if (!CONFIG.plan) return;
  if (dialog?.open) closeNow();

  S = freshState(itemId, from, revises);
  dialog = document.createElement('dialog');
  dialog.className = 'up';
  dialog.setAttribute('aria-labelledby', 'up-title');
  document.body.append(dialog);

  dialog.addEventListener('cancel', (e) => {
    e.preventDefault();
    askToClose();
  });
  // Chrome closes a dialog on Escape without asking when there has been no
  // click since, whatever cancel says. A file not yet sent is not lost that
  // way: the pop-up comes straight back and asks.
  dialog.addEventListener('close', (e) => {
    // The event comes after close() returns, so it is matched to this pop-up.
    if (!S || e.target !== dialog) return;
    if (S.step === 'sending' || (S.file && ['pick', 'check', 'failed'].includes(S.step))) {
      dialog.showModal();
      if (S.step !== 'sending') {
        S.confirmingClose = true;
        render();
        dialog.querySelector('[data-act="keep"]')?.focus();
      }
      return;
    }
    closeNow();
  });
  dialog.addEventListener('click', onClick);
  dialog.addEventListener('change', onChange);
  dialog.addEventListener('keydown', onKeydown);
  dialog.addEventListener('dragover', (e) => {
    if (!['pick', 'check'].includes(S.step)) return;
    e.preventDefault();
    dialog.querySelector('.up-drop')?.classList.add('over');
  });
  dialog.addEventListener('dragleave', (e) => {
    if (e.target.closest('.up-drop') && !e.relatedTarget?.closest?.('.up-drop')) {
      dialog.querySelector('.up-drop')?.classList.remove('over');
    }
  });
  dialog.addEventListener('drop', (e) => {
    if (!['pick', 'check'].includes(S.step)) return;
    e.preventDefault();
    dialog.querySelector('.up-drop')?.classList.remove('over');
    if (e.dataTransfer.files.length) takeFile(e.dataTransfer.files[0]);
  });

  render();
  dialog.showModal();
  await loadPlan();
}

async function loadPlan() {
  S.plan = null;
  S.loadError = null;
  render();
  try {
    const url = CONFIG.plan + (S.itemId ? '?item=' + encodeURIComponent(S.itemId) : '');
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'This could not be opened.');
    S.plan = data;
    S.picked = new Set(data.people.filter((p) => p.on).map((p) => p.role));
  } catch (e) {
    S.loadError = e.message || 'This could not be opened. Reload the page and try again.';
  }
  render();
  focusFirst();
}

function closeNow() {
  S?.xhr?.abort();
  if (S?.thumbUrl) URL.revokeObjectURL(S.thumbUrl);
  const from = S?.from;
  if (dialog) {
    if (dialog.open) dialog.close();
    dialog.remove();
  }
  dialog = null;
  if (S?.step === 'sent') {
    window.location.reload();
    return;
  }
  S = null;
  from?.focus?.();
}

function askToClose() {
  if (!S) return;
  if (S.step === 'sending') return; // waits for the upload, or Stop sending
  if (S.file && ['pick', 'check', 'failed'].includes(S.step)) {
    S.confirmingClose = true;
    render();
    dialog.querySelector('[data-act="keep"]')?.focus();
    return;
  }
  closeNow();
}

// ── Taking a file ────────────────────────────────────────────────────
async function takeFile(file) {
  if (!S?.plan) return;
  S.problem = problemWith(file, S.plan.accept);
  if (S.problem) {
    S.step = 'pick';
    S.file = null;
    render();
    return;
  }
  if (S.thumbUrl) URL.revokeObjectURL(S.thumbUrl);
  Object.assign(S, { file, step: 'check', pages: null, pdf: null, broken: false, thumbUrl: null, rotation: 0, same: null, warnings: [], checking: true, failure: null });
  render();

  // A diagram has nothing to read here: the reviewer's screen draws it.
  const reading = ext(file.name) === 'pdf' ? readPdf(file) : isDiagram(file.name) ? null : readImage(file);
  await Promise.all([reading, serverCheck(file)]);
  if (S?.file === file) {
    S.checking = false;
    render();
  }
}

async function readPdf(file) {
  try {
    const pdf = await pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
    if (S?.file !== file) return;
    S.pdf = pdf;
    S.pages = pdf.numPages;
    const page = await pdf.getPage(1);
    const base = page.getViewport({ scale: 1 });
    const viewport = page.getViewport({ scale: (184 / base.width) });
    const canvas = document.createElement('canvas');
    canvas.width = viewport.width;
    canvas.height = viewport.height;
    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    if (S?.file === file) S.thumbUrl = canvas.toDataURL('image/png');
  } catch {
    if (S?.file === file) S.broken = true;
  }
}

async function readImage(file) {
  S.thumbUrl = URL.createObjectURL(file);
}

/** The server's read of the file: the same file as last time, another chapter's heading, unrelated text. */
async function serverCheck(file) {
  // With no line, a revision is still checked against the upload it revises.
  if (!CONFIG.check || !(S.plan?.item || S.revises)) return;
  const fd = new FormData();
  fd.append('file', file);
  if (S.plan.item) fd.append('deliverable_id', S.plan.item.id);
  else fd.append('revises', S.revises);
  try {
    const data = await fetch(CONFIG.check, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CONFIG.csrf, Accept: 'application/json' },
      credentials: 'same-origin',
      body: fd,
    }).then((r) => r.json());
    if (S?.file !== file) return;
    S.same = data.same || null;
    S.warnings = data.warnings || [];
  } catch {
    // A check that does not answer never stops a send.
  }
}

// ── Sending ──────────────────────────────────────────────────────────
async function fileToSend() {
  const file = S.file;
  if (!S.rotation || ext(file.name) === 'pdf') return file;
  // A photo turned the right way up before it goes.
  const img = await createImageBitmap(file);
  const turn = ((S.rotation % 360) + 360) % 360;
  const canvas = document.createElement('canvas');
  const sideways = turn === 90 || turn === 270;
  canvas.width = sideways ? img.height : img.width;
  canvas.height = sideways ? img.width : img.height;
  const ctx = canvas.getContext('2d');
  ctx.translate(canvas.width / 2, canvas.height / 2);
  ctx.rotate((turn * Math.PI) / 180);
  ctx.drawImage(img, -img.width / 2, -img.height / 2);
  const type = ext(file.name) === 'png' ? 'image/png' : 'image/jpeg';
  const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, 0.92));
  return new File([blob], file.name, { type });
}

async function send() {
  if (!canSend()) return;
  const body = new FormData();
  body.append('file', await fileToSend());
  if (S.plan.item) body.append('deliverable_id', S.plan.item.id);
  if (!S.plan.item?.paper) S.picked.forEach((role) => body.append('recipients[]', role));
  if (ext(S.file.name) === 'pdf') S.diagrams.forEach((d) => body.append('diagrams[]', d));

  S.step = 'sending';
  S.progress = 0;
  S.failure = null;
  render();

  const xhr = new XMLHttpRequest();
  S.xhr = xhr;
  xhr.open('POST', CONFIG.store);
  xhr.setRequestHeader('X-CSRF-TOKEN', CONFIG.csrf);
  xhr.setRequestHeader('Accept', 'application/json');
  xhr.upload.onprogress = (e) => {
    if (!e.lengthComputable || !S) return;
    S.progress = Math.min(99, Math.round((e.loaded / e.total) * 100));
    const bar = dialog?.querySelector('.up-bar i');
    const pct = dialog?.querySelector('[data-pct]');
    const of = dialog?.querySelector('[data-of]');
    if (bar) bar.style.width = S.progress + '%';
    if (pct) pct.textContent = S.progress + '%';
    if (of) of.textContent = `${mb(e.loaded)} of ${mb(e.total)} · keep this open`;
    dialog?.querySelector('.up-bar')?.setAttribute('aria-valuenow', S.progress);
  };
  xhr.onload = () => {
    if (!S) return;
    S.xhr = null;
    let data = {};
    try { data = JSON.parse(xhr.responseText); } catch { /* not JSON */ }
    if (xhr.status >= 200 && xhr.status < 300 && data.success) {
      S.step = 'sent';
      S.result = data;
      try {
        sessionStorage.setItem(TOAST_KEY, JSON.stringify({ message: sentLine(true), file: data.document?.file_id || null }));
      } catch { /* no storage: no notice after the reload */ }
      closeNow();
      return;
    } else {
      S.step = 'failed';
      S.failure = data.message
        || (data.errors ? Object.values(data.errors).flat()[0] : null)
        || (xhr.status === 419 ? 'Your session ran out. Reload the page and send it again.' : 'It didn’t send. Try again in a moment.');
    }
    render();
    focusFirst();
  };
  xhr.onerror = () => {
    if (!S) return;
    S.xhr = null;
    S.step = 'failed';
    S.failure = `<b>It didn’t send — the connection dropped at ${S.progress}%.</b> Check your internet and try again. Nobody got a half file.`;
    render();
    focusFirst();
  };
  xhr.onabort = () => {
    if (!S) return;
    S.xhr = null;
    S.step = 'check';
    render();
  };
  xhr.send(body);
}

function canSend() {
  if (!S?.plan || !S.file || S.checking || S.broken || S.same) return false;
  if (!S.plan.item?.paper && !S.plan.locked && S.picked.size === 0) return false;
  return true;
}

// ── Drawing ──────────────────────────────────────────────────────────
function peopleOn() {
  return (S.plan?.people || []).filter((p) => p.available && (S.plan.locked || S.plan.item?.paper || S.picked.has(p.role)));
}

function sendLabel() {
  if (S.plan?.item?.paper) return 'Hand in';
  const labels = [...new Set(peopleOn().map((p) => p.label))];
  return labels.length ? 'Send to ' + labels.join(', ') : 'Send';
}

/** "your CaPA and CaPSA" - for the notice back on the page. */
function sentLine(forToast) {
  const title = S.plan?.item?.title || S.file?.name || 'Your file';
  const labels = (S.result?.recipients || []).map((r) => r.label);
  const unique = [...new Set(labels)];
  if (S.plan?.item?.paper) return `${title} handed in to your CaPSA`;
  if (!unique.length) return `${title} sent`;
  const joined = unique.length === 1 ? unique[0] : unique.slice(0, -1).join(', ') + ' and ' + unique.at(-1);
  return forToast ? `${title} sent to your ${joined}` : joined;
}

function titleText() {
  if (!S.plan) return 'Submit a file';
  const item = S.plan.item;
  if (!item) return 'Submit a file';
  return S.plan.round ? `${item.title} · ${S.plan.round}` : item.title;
}

function subText() {
  const item = S.plan?.item;
  if (!S.plan) return '';
  if (!item) return S.fixedLine ? '' : 'Pick what it’s for, then the file';
  if (item.paper) return 'Signed paper · your CaPSA checks it';
  if (S.plan.round) return `Goes up as version ${S.plan.version}`;
  return [item.phase, item.due ? 'due ' + item.due : null].filter(Boolean).join(' · ');
}

function note(kind, html, actions = '') {
  return `<div class="up-note ${kind}" role="${kind === 'error' ? 'alert' : 'status'}">${svg('alert', 15)}<div>${html}${actions ? `<div class="up-note-acts">${actions}</div>` : ''}</div></div>`;
}

function linkBtn(text, act, extra = '') {
  return `<button type="button" class="up-link" data-act="${act}"${extra}>${text}</button>`;
}

function lineSelect() {
  if (S.fixedLine || !S.plan) return '';
  const lines = S.plan.lines || [];
  if (!lines.length && !S.plan.item) {
    return `<div class="up-field"><label class="up-label" for="up-line">What is this for?</label>
      <select id="up-line" class="up-select"><option value="">Not a checklist requirement (feedback only)</option></select></div>`;
  }
  if (S.plan.item && !lines.length) {
    // Picked from the list: show the pick, and a way back to the list.
    return `<div class="up-field"><span class="up-label">What is this for?</span>
      <div class="up-picked-line"><b>${esc(S.plan.item.title)}</b>${linkBtn('Change', 'relist')}</div></div>`;
  }
  const groups = {};
  lines.forEach((l) => { (groups[l.phase || 'Other'] ||= []).push(l); });
  const options = Object.entries(groups).map(([phase, ls]) =>
    `<optgroup label="${esc(phase)}">` + ls.map((l) =>
      `<option value="${l.id}"${String(l.id) === String(S.itemId) ? ' selected' : ''}>${esc(l.title)}${l.note ? ' — ' + esc(l.note) : ''}</option>`).join('') + '</optgroup>').join('');
  return `<div class="up-field"><label class="up-label" for="up-line">What is this for?</label>
    <select id="up-line" class="up-select"><option value="">Choose…</option>${options}<option value="none"${S.itemId === 'none' ? ' selected' : ''}>Not a checklist requirement (feedback only)</option></select></div>`;
}

function remarkHtml() {
  const r = S.plan?.remark;
  if (!r) return '';
  const count = r.count ? ` · ${r.count} ${r.count === 1 ? 'remark' : 'remarks'}` : '';
  const first = r.first ? ` <span class="up-remark-q">“${esc(r.first)}”</span>` : '';
  return `<div class="up-remark"><b>${esc(r.who)} asked for changes</b>${count}${first} <a class="up-link" href="${esc(r.url)}">Read them</a></div>`;
}

function dropHtml() {
  const kinds = S.plan?.accept?.includes('png') ? 'PDF, JPG or PNG · up to 25 MB · a clear phone photo is fine' : `PDF, or a diagram (.drawio, .vsdx) · up to ${MAX_MB} MB`;
  const accept = (S.plan?.accept || ['pdf']).map((e) => '.' + e).join(',');
  return `<div class="up-drop${S.problem ? ' bad' : ''}" role="button" tabindex="0" data-act="browse" aria-describedby="up-drop-kinds">
      <span class="up-drop-ic">${svg('upload', 18)}</span>
      <b>Drop your file here or <span class="up-link">browse</span></b>
      <span id="up-drop-kinds">${kinds}</span>
      <input type="file" id="up-file" accept="${accept}" hidden>
    </div>` + (S.problem ? note('error', S.problem) : '');
}

const initials = (name) => String(name || '?').replace(/^(Dr|Prof|Engr|Mr|Ms|Mrs)\.?\s+/i, '').split(/\s+/).filter(Boolean).map((w) => w[0]).slice(0, 2).join('').toUpperCase() || '?';

function pill(p, { locked = false, toggle = false } = {}) {
  const on = locked || S.picked.has(p.role);
  const who = [p.name, p.note].filter(Boolean).join(' · ');
  const inner = `<i class="up-av" aria-hidden="true">${esc(initials(p.name || p.label))}</i><b>${esc(p.label)}</b>${who ? `<span>${esc(who)}</span>` : ''}`;
  if (toggle && p.available) {
    return `<button type="button" class="up-pill${on ? ' on' : ' off'}" data-act="toggle" data-role="${p.role}" aria-pressed="${on}">${inner}</button>`;
  }
  if (!p.available) return `<span class="up-pill na" title="${esc(p.label)}: ${esc(p.note || 'not assigned yet')}">${inner}</span>`;
  return `<span class="up-pill${on ? ' on' : ''}${locked ? ' locked' : ''}">${inner}</span>`;
}

function goesToHtml() {
  const plan = S.plan;
  if (!plan) return '';
  const people = plan.people || [];
  const paper = !!plan.item?.paper;
  const locked = plan.locked || paper;
  let right = '';
  if (plan.locked && !paper && plan.version > 1) right = `<span class="up-muted">same as version ${plan.version - 1}</span>`;

  // The names are there to tap.
  const pills = people.map((p) => pill(p, locked ? { locked: true } : { toggle: true })).join('');

  let hint = '';
  if (paper) hint = 'A signed paper always goes to your CaPSA to check.';
  else if (plan.locked) hint = 'A revision goes back to the people who read the last version.';
  else if (people.some((p) => p.role === 'subject_adviser' && p.note === 'review if asked')) hint = 'Your CaPSA only reviews this chapter when they ask for it.';
  if (!paper && !people.some((p) => p.available)) hint = 'Nobody is assigned to your team yet, so there is no one to send it to.';

  return `<div class="up-goes"><div class="up-goes-h"><span class="up-label">${plan.locked && !paper ? 'Goes back to' : 'Send to'}</span>${right}</div>
    <div class="up-pills">${pills}</div>${hint ? `<p class="up-hint">${hint}</p>` : ''}</div>`;
}

function checksHtml() {
  const f = S.file;
  const isPdf = ext(f.name) === 'pdf';
  const items = [];
  items.push(['ok', `${isPdf ? 'PDF' : isDiagram(f.name) ? 'Diagram' : 'Photo'}, ${mb(f.size)}`]);
  if (isPdf) {
    if (S.broken) items.push(['bad', 'This PDF won’t open']);
    else if (S.pages == null) items.push(['wait', 'Reading the pages…']);
    else items.push(['ok', `${S.pages} ${S.pages === 1 ? 'page' : 'pages'}, all open`]);
  }
  const want = parseInt(S.plan?.item?.chapter, 10);
  const got = chapterInName(f.name);
  if (isPdf && want && got) items.push([got === want ? 'ok' : 'warn', `Name says Chapter ${got}`]);
  if (S.plan?.item?.paper) items.push(['warn', 'Check every signature and date is readable']);
  if (S.checking && S.plan?.item) items.push(['wait', 'Comparing with what you sent before…']);
  else if (S.same) items.push(['bad', `Identical to version ${S.same}`]);
  else if (S.plan?.version > 1 && S.plan?.item) items.push(['ok', `Different from version ${S.plan.version - 1}`]);

  const mark = { ok: svg('check', 13, 2.6), warn: svg('alert', 13), bad: svg('x', 13, 2.6), wait: '<i class="up-spin" aria-hidden="true"></i>' };
  return '<ul class="up-checks">' + items.map(([k, t]) => `<li class="${k}">${mark[k]}<span>${t}</span></li>`).join('') + '</ul>';
}

function fileCardHtml() {
  const f = S.file;
  const isPdf = ext(f.name) === 'pdf';
  const diagram = isDiagram(f.name);
  const thumb = S.thumbUrl
    ? `<img src="${S.thumbUrl}" alt="" style="transform:rotate(${S.rotation}deg)">`
    : `<span class="up-thumb-blank">${S.broken || diagram ? svg(diagram ? 'file' : 'alert', 20) : '<i class="up-spin"></i>'}</span>`;
  const canPreview = !S.broken && !diagram && (S.thumbUrl || !isPdf);
  const tools = [
    canPreview ? linkBtn(isPdf ? 'Preview all pages' : 'Open full size', 'preview') : '',
    !isPdf && !diagram ? linkBtn('Rotate', 'rotate') : '',
    linkBtn('Change file', 'browse', ' data-muted'),
  ].filter(Boolean).join('<span class="up-dot" aria-hidden="true">·</span>');
  const versionLine = S.plan?.item ? `Goes up as version ${S.plan.version}` : 'Feedback only · moves nothing on your checklist';
  return `<div class="up-file">
      <button type="button" class="up-thumb" data-act="preview" aria-label="Preview ${esc(f.name)}"${canPreview ? '' : ' disabled'}>${thumb}${canPreview ? `<span class="up-thumb-eye">${svg('eye', 13)}</span>` : ''}</button>
      <div class="up-file-info">
        <b class="up-file-name" title="${esc(f.name)}">${esc(f.name)}</b>
        <span class="up-muted">${versionLine}</span>
        ${checksHtml()}
        <div class="up-tools">${tools}</div>
      </div>
      <input type="file" id="up-file" accept="${(S.plan?.accept || ['pdf']).map((e) => '.' + e).join(',')}" hidden>
    </div>`;
}

function checkNotes() {
  let out = '';
  if (S.broken) out += note('error', '<b>This PDF won’t open.</b> It may be damaged or password-protected. Save it again as PDF and add it here.', linkBtn('Choose another file', 'browse'));
  if (S.same) out += note('warn', `<b>This is the same file as version ${S.same}${S.plan?.remark ? ', the one sent back' : ''}.</b> Did you pick the old one? Open your edited copy instead.`, linkBtn('Choose another file', 'browse'));
  const want = parseInt(S.plan?.item?.chapter, 10);
  const got = S.file && chapterInName(S.file.name);
  const nameOff = !S.same && want && got && got !== want;
  if (nameOff) {
    out += note('warn', `<b>The file name says Chapter ${got}</b>, but this is ${esc(S.plan.item.title)}. Preview it to be sure.`, linkBtn('Preview', 'preview') + linkBtn('Choose another file', 'browse'));
  }
  // The first page naming another chapter says the same as the name did.
  if (!S.same) S.warnings.filter((w) => !(nameOff && w.type === 'heading')).forEach((w) => { out += note('warn', esc(w.message)); });
  return out;
}

/** The draw.io or Visio files of the document's diagrams, sent along so a reviewer sees exactly what changed in them. */
function diagramsHtml() {
  if (ext(S.file.name) !== 'pdf') return '';
  const list = S.diagrams.length
    ? '<ul class="up-checks">' + S.diagrams.map((d, i) => `<li class="ok">${svg('file', 13)}<span>${esc(d.name)} · ${mb(d.size)}</span>${linkBtn('Remove', 'undiagram', ` data-i="${i}" data-muted`)}</li>`).join('') + '</ul>'
    : '';
  return `<div class="up-goes"><div class="up-goes-h"><span class="up-label">Diagram files · optional</span>${linkBtn('Attach .drawio / .vsdx', 'diagrams')}</div>
    ${list}<p class="up-hint">Made your diagrams in draw.io or Visio? Attach their .drawio or .vsdx files so your reviewer sees exactly what changed in them.</p>
    ${S.diagramProblem ? note('error', S.diagramProblem) : ''}
    <input type="file" id="up-diagrams" accept=".drawio,.vsdx" multiple hidden></div>`;
}

function takeDiagrams(files) {
  const wrong = files.filter((f) => !['drawio', 'vsdx'].includes(ext(f.name)) || f.size > 5 * 1024 * 1024);
  const room = 20 - S.diagrams.length;
  const fine = files.filter((f) => !wrong.includes(f));
  S.diagrams.push(...fine.slice(0, room));
  S.diagramProblem = wrong.length
    ? `<b>${esc(wrong[0].name)} wasn’t added.</b> Only draw.io (.drawio) or Visio (.vsdx) files up to 5 MB.`
    : (fine.length > room ? '<b>Up to 20 diagram files.</b> The rest weren’t added.' : null);
  render();
}

function thumbRow(extra) {
  const f = S.file;
  const t = S.thumbUrl ? `<img src="${S.thumbUrl}" alt="" style="transform:rotate(${S.rotation}deg)">` : svg('file', 18);
  return `<div class="up-row"><span class="up-mini">${t}</span><div class="up-row-main">${extra(f)}</div></div>`;
}

function bodyAndFoot() {
  if (S.loadError) {
    return [note('error', esc(S.loadError)), btn('Close', 'close')];
  }
  if (!S.plan) {
    return ['<p class="up-loading"><i class="up-spin"></i> Opening…</p>', btn('Cancel', 'close')];
  }
  switch (S.step) {
    case 'pick':
      return [
        lineSelect() + remarkHtml() + (needsLine() ? '' : dropHtml()) + (needsLine() ? '' : goesToHtml()),
        btn('Cancel', 'close') + btn(sendLabel(), 'send', { primary: true, disabled: true, icon: 'send' }),
      ];
    case 'check':
      return [
        fileCardHtml() + checkNotes() + diagramsHtml() + goesToHtml(),
        whyNot() + btn('Cancel', 'close') + btn(sendLabel(), 'send', { primary: true, disabled: !canSend(), icon: 'send' }),
      ];
    case 'sending':
      return [
        thumbRow((f) => `<div class="up-row-top"><b title="${esc(f.name)}">Sending ${esc(f.name)}</b><span data-pct>${S.progress}%</span></div>
          <div class="up-bar" role="progressbar" aria-label="Sending" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${S.progress}"><i style="width:${S.progress}%"></i></div>
          <span class="up-muted" data-of>${mb(f.size)} · keep this open</span>`),
        btn('Stop sending', 'stop'),
      ];
    case 'failed':
      return [
        thumbRow((f) => `<b title="${esc(f.name)}">${esc(f.name)}</b><span class="up-muted">${mb(f.size)} · still here, nothing was lost</span>`)
          + note('error', S.failure.startsWith('<b>') ? S.failure : esc(S.failure)),
        btn('Cancel', 'close') + btn('Try again', 'send', { primary: true, icon: 'retry' }),
      ];
  }
  return ['', ''];
}

/** Why Send is greyed out, when the file itself is fine. */
function whyNot() {
  if (canSend() || !S.plan || S.checking || S.broken || S.same || S.plan.item?.paper || S.plan.locked) return '';
  return '<span class="up-why">Choose who it goes to.</span>';
}

function needsLine() {
  return !S.fixedLine && !S.plan?.item && S.itemId !== 'none' && (S.plan?.lines || []).length > 0;
}

function btn(text, act, { primary = false, disabled = false, icon = null } = {}) {
  const mark = icon ? svg(icon, 14, 2.4) : '';
  // An arrow onward reads after the word; everything else before it.
  const inner = icon === 'right' ? text + mark : mark + text;
  return `<button type="button" class="up-btn${primary ? ' primary' : ''}" data-act="${act}"${disabled ? ' disabled' : ''}>${inner}</button>`;
}

function render() {
  if (!dialog || !S) return;
  const [body, foot] = bodyAndFoot();
  const sending = S.step === 'sending';
  const confirm = S.confirmingClose
    ? `<div class="up-confirm"><div role="alertdialog" aria-labelledby="up-confirm-h" aria-describedby="up-confirm-p">
        <b id="up-confirm-h">Close without sending?</b>
        <p id="up-confirm-p">${esc(S.file?.name)} hasn’t been sent. Nobody will see it.</p>
        <div>${btn('Keep it', 'keep')}${btn('Close anyway', 'discard', { primary: true })}</div></div></div>`
    : '';
  dialog.classList.toggle('is-sent', S.step === 'sent');
  dialog.innerHTML = `
    <div class="up-head">
      <div class="up-titles"><h2 id="up-title">${esc(titleText())}</h2>${subText() ? `<p>${esc(subText())}</p>` : ''}</div>
      <button type="button" class="up-x" data-act="close" aria-label="${sending ? 'Wait for it to finish sending' : 'Close'}"${sending ? ' disabled' : ''}>${svg('x', 15, 2.2)}</button>
    </div>
    <div class="up-body">${body}</div>
    <div class="up-foot">${foot}</div>
    ${confirm}`;
}

function focusFirst() {
  if (!dialog) return;
  const target = dialog.querySelector('#up-line, .up-drop, .up-btn.primary:not([disabled]), .up-btn:not([disabled])');
  target?.focus();
}

// ── Events ───────────────────────────────────────────────────────────
function onClick(e) {
  if (e.target === dialog) {
    askToClose();
    return;
  }
  const el = e.target.closest('[data-act]');
  if (!el || el.disabled) return;
  const act = el.dataset.act;
  switch (act) {
    case 'close': askToClose(); break;
    case 'keep': S.confirmingClose = false; render(); focusFirst(); break;
    case 'discard': closeNow(); break;
    case 'browse': dialog.querySelector('#up-file')?.click(); break;
    case 'toggle': {
      const role = el.dataset.role;
      if (S.picked.has(role)) S.picked.delete(role); else S.picked.add(role);
      render();
      dialog.querySelector(`[data-act="toggle"][data-role="${role}"]`)?.focus();
      break;
    }
    case 'diagrams': dialog.querySelector('#up-diagrams')?.click(); break;
    case 'undiagram': S.diagrams.splice(parseInt(el.dataset.i, 10), 1); S.diagramProblem = null; render(); break;
    case 'rotate': S.rotation = (S.rotation + 90) % 360; render(); break;
    case 'preview': openPreview(); break;
    case 'send': send(); break;
    case 'stop': S.xhr?.abort(); break;
    case 'relist': S.itemId = ''; loadPlan(); break;
  }
}

function onChange(e) {
  if (e.target.id === 'up-file') {
    if (e.target.files.length) takeFile(e.target.files[0]);
    e.target.value = '';
  } else if (e.target.id === 'up-diagrams') {
    takeDiagrams([...e.target.files]);
  } else if (e.target.id === 'up-line') {
    S.itemId = e.target.value;
    if (S.itemId === 'none') {
      // Feedback only: no line, the plain rule for who reads it.
      S.plan.item = null;
      render();
      focusFirst();
      return;
    }
    loadPlan();
  }
}

function onKeydown(e) {
  const drop = e.target.closest?.('.up-drop');
  if (drop && (e.key === 'Enter' || e.key === ' ')) {
    e.preventDefault();
    dialog.querySelector('#up-file')?.click();
  }
}

// ── The full preview: every page, big, before it goes anywhere ─────
let pv = null;

async function openPreview() {
  if (!S?.file || S.broken || isDiagram(S.file.name)) return;
  const isPdf = ext(S.file.name) === 'pdf';
  pv = { page: 1, zoom: 1, total: isPdf ? (S.pages || 1) : 1 };
  const box = document.createElement('dialog');
  box.className = 'up-pv';
  box.setAttribute('aria-label', 'Preview ' + S.file.name);
  box.innerHTML = `
    <div class="up-pv-bar">
      <b class="up-pv-name" title="${esc(S.file.name)}">${esc(S.file.name)}</b>
      <span class="up-pv-meta">${isPdf ? `${pv.total} ${pv.total === 1 ? 'page' : 'pages'} · ` : ''}${mb(S.file.size)}</span>
      <span class="up-pv-grow"></span>
      ${isPdf ? `<span class="up-pv-nav">
        <button type="button" data-pv="prev" aria-label="Previous page">${svg('left', 14)}</button>
        <span>Page <input type="number" min="1" max="${pv.total}" value="1" aria-label="Page number" data-pv="goto"> of ${pv.total}</span>
        <button type="button" data-pv="next" aria-label="Next page">${svg('right', 14)}</button></span>` : ''}
      <span class="up-pv-nav">
        <button type="button" data-pv="out" aria-label="Zoom out">${svg('minus', 14)}</button>
        <span data-pv-zoom>100%</span>
        <button type="button" data-pv="in" aria-label="Zoom in">${svg('plus', 14)}</button></span>
      <button type="button" class="up-pv-x" data-pv="close" aria-label="Close preview">${svg('x', 15, 2.2)}</button>
    </div>
    <div class="up-pv-main">
      ${isPdf ? '<div class="up-pv-thumbs" aria-label="Pages"></div>' : ''}
      <div class="up-pv-stage" tabindex="0"></div>
    </div>
    <div class="up-pv-foot">
      <span>Look through every page. This is exactly what ${S.plan?.item?.paper ? 'your CaPSA' : 'your reviewers'} will open.</span>
      <button type="button" class="up-btn" data-pv="another">Choose another file</button>
      <button type="button" class="up-btn primary" data-pv="send"${canSend() ? '' : ' disabled'}>${svg('send', 14, 2.4)}Looks right, ${S.plan?.item?.paper ? 'hand it in' : 'send it'}</button>
    </div>`;
  document.body.append(box);
  box.addEventListener('close', () => { box.remove(); pv = null; dialog?.querySelector('[data-act="preview"]')?.focus(); });
  box.addEventListener('click', (e) => {
    const b = e.target.closest('[data-pv]');
    if (e.target === box) { box.close(); return; }
    if (!b) return;
    const k = b.dataset.pv;
    if (k === 'close') box.close();
    if (k === 'prev') showPage(box, pv.page - 1);
    if (k === 'next') showPage(box, pv.page + 1);
    if (k === 'in') zoom(box, 0.25);
    if (k === 'out') zoom(box, -0.25);
    if (k === 'thumb') showPage(box, +b.dataset.n);
    if (k === 'another') { box.close(); dialog?.querySelector('#up-file')?.click(); }
    if (k === 'send') { box.close(); send(); }
  });
  box.addEventListener('change', (e) => { if (e.target.dataset.pv === 'goto') showPage(box, parseInt(e.target.value, 10) || 1); });
  box.addEventListener('keydown', (e) => {
    if (e.target.tagName === 'INPUT') return;
    if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); showPage(box, pv.page + 1); }
    if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); showPage(box, pv.page - 1); }
  });
  box.showModal();

  if (isPdf) {
    drawThumbs(box);
    showPage(box, 1);
  } else {
    const img = document.createElement('img');
    img.src = S.thumbUrl;
    img.alt = S.file.name;
    img.style.transform = `rotate(${S.rotation}deg)`;
    box.querySelector('.up-pv-stage').append(img);
    pv.img = img;
  }
  box.querySelector('.up-pv-stage').focus();
}

async function drawThumbs(box) {
  const holder = box.querySelector('.up-pv-thumbs');
  for (let n = 1; n <= pv.total && pv; n++) {
    const b = document.createElement('button');
    b.type = 'button';
    b.dataset.pv = 'thumb';
    b.dataset.n = n;
    b.setAttribute('aria-label', 'Page ' + n);
    b.innerHTML = `<canvas></canvas><span>${n}</span>`;
    holder.append(b);
  }
  for (let n = 1; n <= pv?.total; n++) {
    if (!pv || !S?.pdf) return;
    const page = await S.pdf.getPage(n);
    const vp = page.getViewport({ scale: 132 / page.getViewport({ scale: 1 }).width });
    const canvas = holder.querySelector(`[data-n="${n}"] canvas`);
    if (!canvas) return;
    canvas.width = vp.width;
    canvas.height = vp.height;
    await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
  }
}

let drawing = 0;
async function showPage(box, n) {
  if (!pv || !S?.pdf) return;
  pv.page = Math.max(1, Math.min(pv.total, n));
  const input = box.querySelector('[data-pv="goto"]');
  if (input) input.value = pv.page;
  box.querySelectorAll('[data-pv="thumb"]').forEach((t) => {
    const on = +t.dataset.n === pv.page;
    t.classList.toggle('on', on);
    if (on) { t.setAttribute('aria-current', 'page'); t.scrollIntoView({ block: 'nearest' }); } else t.removeAttribute('aria-current');
  });
  box.querySelector('[data-pv="prev"]').disabled = pv.page === 1;
  box.querySelector('[data-pv="next"]').disabled = pv.page === pv.total;
  const ticket = ++drawing;
  const stage = box.querySelector('.up-pv-stage');
  const page = await S.pdf.getPage(pv.page);
  if (ticket !== drawing) return;
  const fit = Math.min(760, stage.clientWidth - 32) / page.getViewport({ scale: 1 }).width;
  const ratio = window.devicePixelRatio || 1;
  const vp = page.getViewport({ scale: fit * pv.zoom * ratio });
  const canvas = document.createElement('canvas');
  canvas.width = vp.width;
  canvas.height = vp.height;
  canvas.style.width = vp.width / ratio + 'px';
  canvas.setAttribute('role', 'img');
  canvas.setAttribute('aria-label', `Page ${pv.page} of ${pv.total}`);
  await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
  if (ticket !== drawing) return;
  stage.replaceChildren(canvas);
  stage.scrollTop = 0;
}

function zoom(box, step) {
  pv.zoom = Math.max(0.5, Math.min(2.5, pv.zoom + step));
  box.querySelector('[data-pv-zoom]').textContent = Math.round(pv.zoom * 100) + '%';
  if (pv.img) pv.img.style.width = (pv.zoom * 100) + '%';
  else showPage(box, pv.page);
}

// ── The notice after a send, and the page's own toast ───────────────
export function showToast(message, type) {
  if (window.ctToast) { return window.ctToast(message, type); }
  type = type || 'success';
  const toast = document.getElementById('submission-toast');
  if (!toast) return;
  toast.textContent = (type === 'error' ? '✕ ' : '✓ ') + message;
  toast.classList.remove('success', 'error');
  toast.classList.add('show', type);
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => toast.classList.remove('show'), 4000);
}

/**
 * "Sent ... · Undo": Undo takes the file back - the same Unsubmit as on
 * Documents, which only works while nobody has opened it.
 */
function showSentToast({ message, file }) {
  if (!file || !CONFIG.unsubmit) { showToast(message, 'success'); return; }
  const toast = document.createElement('div');
  toast.className = 'up-sent-toast';
  toast.setAttribute('role', 'status');
  toast.innerHTML = `${svg('check', 15, 2.6)}<span>${esc(message)}</span><button type="button">Undo</button>`;
  document.body.append(toast);
  const gone = setTimeout(() => toast.remove(), 4000);
  toast.querySelector('button').addEventListener('click', async (e) => {
    clearTimeout(gone);
    e.target.disabled = true;
    try {
      await fetch(CONFIG.unsubmit.replace('__ID__', encodeURIComponent(file)), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CONFIG.csrf, Accept: 'text/html' },
        credentials: 'same-origin',
      });
    } finally {
      window.location.reload();
    }
  });
}

try {
  const pending = sessionStorage.getItem(TOAST_KEY);
  if (pending) {
    sessionStorage.removeItem(TOAST_KEY);
    let notice;
    try { notice = JSON.parse(pending); } catch { notice = { message: pending }; }
    showSentToast(typeof notice === 'object' && notice ? notice : { message: pending });
  }
} catch { /* no storage */ }

window.openSubmission = openSubmission;
// The phone's Submit still calls it by its old name: a file for no line, as on Documents.
window.openSubmissionDrawer = (itemId, from) => openSubmission(itemId || 'none', from);
window.submissionToast = showToast;

// The Document Submission page opened for one line (?item=) opens straight onto it.
// Only for a line named on that page: any other page's button that happened
// to carry the attribute (Financial Records' receipt upload once did) opened
// this pop-up over it on load.
const autoOpen = document.querySelector('.sub-card [data-open-upload]:not([data-open-upload=""])');
if (autoOpen) openSubmission(autoOpen.dataset.openUpload, autoOpen);
