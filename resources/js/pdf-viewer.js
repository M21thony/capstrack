import '../css/pdf-viewer.css';
import * as pdfjsLib from 'pdfjs-dist';

pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url).toString();

/*
 * The one window every "View PDF" in the app opens (the Document Annotator
 * keeps its own). The pages are drawn in the page with pdf.js: no iframe, so
 * no browser viewer toolbar, and no phone offering a download instead of the
 * paper.
 *
 *   PdfViewer.open({
 *     url,            // a PDF on the server, or
 *     file,           // a File/Blob on this device (a PDF or a picture)
 *     title,          // the window's heading
 *     meta,           // the line under it: team, section, date…
 *     fileName,       // for the footer and the downloaded file's name
 *     download,       // where Download points; defaults to url, false for none
 *     side,           // an element shown beside the pages (what to decide)
 *     note, actions,  // a sentence and buttons for the footer
 *     inPage,         // open in the page rather than over everything, so the
 *                     // page's own dialogs can open on top of it
 *     onClose,
 *   })
 *
 * A link or button with data-pdf-view="<url>" opens it too, with
 * data-pdf-title, data-pdf-meta and data-pdf-download.
 */

const PDF_TO_CSS = 96 / 72;
const ZOOM_MIN = 0.25;
const ZOOM_MAX = 4;

const ICONS = {
  file: '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
  prev: '<path d="m15 18-6-6 6-6"/>',
  next: '<path d="m9 18 6-6-6-6"/>',
  minus: '<path d="M5 12h14"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  fit: '<path d="M21 3 14 10"/><path d="M3 21l7-7"/><path d="M15 3h6v6"/><path d="M9 21H3v-6"/>',
  down: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
  close: '<path d="M18 6 6 18M6 6l12 12"/>',
};
const svg = (name) => `<svg viewBox="0 0 24 24" aria-hidden="true">${ICONS[name]}</svg>`;

let ui = null;
let session = null;

function build() {
  // A page may already hold the empty window (the student portal names it
  // #previewDialog); otherwise it is made here.
  const dialog = document.getElementById('previewDialog') || document.body.appendChild(document.createElement('dialog'));
  dialog.id = 'previewDialog';
  dialog.className = 'pv';
  dialog.setAttribute('aria-labelledby', 'pvTitle');
  dialog.innerHTML = `
    <div class="pv-card">
      <header class="pv-head">
        <div class="pv-id">
          <span class="pv-ic">${svg('file')}</span>
          <div class="pv-idtext"><h2 class="pv-title" id="pvTitle"></h2><div class="pv-meta"></div></div>
        </div>
        <nav class="pv-grp pv-pager" aria-label="Pages">
          <button type="button" class="pv-b" data-pv="prev" aria-label="Previous page">${svg('prev')}</button>
          <label class="pv-pg">Page <input class="pv-pgin" type="number" min="1" value="1" aria-label="Page number"> of <span class="pv-total">1</span></label>
          <button type="button" class="pv-b" data-pv="next" aria-label="Next page">${svg('next')}</button>
        </nav>
        <div class="pv-tools">
          <div class="pv-grp pv-zoom" role="group" aria-label="Zoom">
            <button type="button" class="pv-b" data-pv="out" aria-label="Zoom out">${svg('minus')}</button>
            <span class="pv-zl" aria-live="polite">100%</span>
            <button type="button" class="pv-b" data-pv="in" aria-label="Zoom in">${svg('plus')}</button>
          </div>
          <button type="button" class="pv-b pv-line pv-fit" data-pv="fit">${svg('fit')}Fit width</button>
          <span class="pv-sep"></span>
          <a class="pv-b pv-line pv-dl" download>${svg('down')}<span class="pv-lbl">Download</span></a>
          <button type="button" class="pv-b pv-x" data-pv="close" aria-label="Close">${svg('close')}</button>
        </div>
      </header>
      <div class="pv-main">
        <aside class="pv-thumbs" aria-label="Pages"></aside>
        <div class="pv-stage" tabindex="0"></div>
        <aside class="pv-side" hidden></aside>
      </div>
      <footer class="pv-foot">
        <span class="pv-file"></span>
        <span class="pv-note"></span>
        <span class="pv-keys">← → turn pages · + − zoom · Esc closes</span>
        <div class="pv-acts"></div>
      </footer>
      <nav class="pv-pill" aria-label="Pages">
        <button type="button" class="pv-b" data-pv="prev" aria-label="Previous page">${svg('prev')}</button>
        <span class="pv-pill-n"><b class="pv-cur">1</b> <span>/ <span class="pv-total">1</span></span></span>
        <button type="button" class="pv-b" data-pv="next" aria-label="Next page">${svg('next')}</button>
        <span class="pv-sep"></span>
        <button type="button" class="pv-b" data-pv="out" aria-label="Zoom out">${svg('minus')}</button>
        <button type="button" class="pv-b" data-pv="in" aria-label="Zoom in">${svg('plus')}</button>
      </nav>
    </div>`;

  const q = (s) => dialog.querySelector(s);
  ui = {
    dialog,
    card: q('.pv-card'),
    title: q('.pv-title'),
    meta: q('.pv-meta'),
    pager: q('.pv-pager'),
    pgin: q('.pv-pgin'),
    totals: dialog.querySelectorAll('.pv-total'),
    cur: q('.pv-cur'),
    zoom: q('.pv-zoom'),
    zoomLabel: q('.pv-zl'),
    fit: q('.pv-fit'),
    download: q('.pv-dl'),
    thumbs: q('.pv-thumbs'),
    stage: q('.pv-stage'),
    side: q('.pv-side'),
    foot: q('.pv-foot'),
    file: q('.pv-file'),
    note: q('.pv-note'),
    acts: q('.pv-acts'),
    pill: q('.pv-pill'),
  };

  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) { close(); return; }
    const b = e.target.closest('[data-pv]');
    if (!b || !session) return;
    ({
      prev: () => goTo(session.current - 1),
      next: () => goTo(session.current + 1),
      in: () => zoomBy(1.25),
      out: () => zoomBy(0.8),
      fit: () => setFit(),
      close: () => close(),
    })[b.dataset.pv]?.();
  });

  ui.pgin.addEventListener('change', () => goTo(parseInt(ui.pgin.value, 10) || 1));
  ui.pgin.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); ui.pgin.blur(); } });

  dialog.addEventListener('keydown', (e) => {
    // Esc closes this window and nothing under it: handled here and stopped,
    // so a page's own Esc handler does not close the panel it was opened from.
    if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
      close();
      return;
    }
    if (!session || e.target.closest('.pv-side, input, textarea, select')) return;
    const keys = {
      ArrowLeft: () => goTo(session.current - 1),
      ArrowRight: () => goTo(session.current + 1),
      PageUp: () => goTo(session.current - 1),
      PageDown: () => goTo(session.current + 1),
      Home: () => goTo(1),
      End: () => goTo(session.pages.length),
      '+': () => zoomBy(1.25),
      '=': () => zoomBy(1.25),
      '-': () => zoomBy(0.8),
      0: () => setFit(),
    };
    if (keys[e.key]) { e.preventDefault(); keys[e.key](); }
  });

  // A top-layer dialog closes itself on Esc; tidy up after it.
  dialog.addEventListener('close', () => { if (session) teardown(); });

  ui.stage.addEventListener('scroll', () => {
    if (session && !session.scrollQueued) {
      session.scrollQueued = true;
      requestAnimationFrame(() => { if (session) { session.scrollQueued = false; trackCurrent(); } });
    }
  }, { passive: true });

  window.addEventListener('resize', () => { if (session?.fit) setFit(); });
}

function message(text, busy) {
  ui.stage.replaceChildren();
  const box = document.createElement('div');
  box.className = 'pv-msg';
  if (busy) {
    const spin = document.createElement('div');
    spin.className = 'pv-spin';
    box.append(spin);
  }
  box.append(document.createTextNode(text));
  ui.stage.append(box);
}

function sizeLabel(bytes) {
  if (!bytes) return '';
  return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

function fileNameOf(opts) {
  if (opts.fileName) return opts.fileName;
  if (opts.file?.name) return opts.file.name;
  try {
    const last = new URL(opts.url, location.href).pathname.split('/').pop();
    return last && /\.[a-z0-9]{2,5}$/i.test(last) ? decodeURIComponent(last) : '';
  } catch { return ''; }
}

function open(opts) {
  if (!ui) build();
  if (session) teardown(true);

  session = {
    opts,
    inPage: !!opts.inPage,
    pages: [],
    current: 1,
    fit: true,
    scale: 1,
    pdf: null,
    objectUrl: null,
    observer: null,
    thumbObserver: null,
    scrollQueued: false,
  };

  ui.title.textContent = opts.title || 'Document';
  ui.meta.textContent = opts.meta || '';
  ui.meta.hidden = !opts.meta;
  ui.dialog.setAttribute('aria-label', opts.title || 'Document');

  const download = opts.download === false ? null : (opts.download || opts.url || null);
  ui.download.hidden = !download;
  if (download) {
    ui.download.href = download;
    ui.download.setAttribute('download', fileNameOf(opts) || '');
  } else {
    ui.download.removeAttribute('href');
  }

  ui.side.hidden = !opts.side;
  ui.side.replaceChildren(...(opts.side ? [opts.side] : []));
  ui.note.textContent = opts.note || '';
  ui.acts.replaceChildren(...(opts.actions || []));
  ui.foot.classList.toggle('pv-has-acts', !!(opts.actions && opts.actions.length));
  ui.file.textContent = fileNameOf(opts);

  ui.thumbs.replaceChildren();
  setPaging(false);
  setTotal(1);

  ui.dialog.classList.toggle('pv-inpage', session.inPage);
  if (!ui.dialog.open) {
    if (session.inPage) { ui.dialog.show(); } else { ui.dialog.showModal(); }
  }
  session.lockedOverflow = document.documentElement.style.overflow;
  document.documentElement.style.overflow = 'hidden';
  ui.stage.focus({ preventScroll: true });

  load(session);
}

async function load(s) {
  const opts = s.opts;
  message('Loading…', true);
  try {
    let data;
    let type = opts.file?.type || '';
    if (opts.file) {
      data = await opts.file.arrayBuffer();
    } else {
      const res = await fetch(opts.url, { credentials: 'same-origin', headers: { Accept: 'application/pdf,image/*' } });
      if (!res.ok) throw new Error(String(res.status));
      type = res.headers.get('Content-Type') || '';
      data = await res.arrayBuffer();
    }
    if (s !== session) return;

    const size = sizeLabel(data.byteLength);
    if (size) ui.file.textContent = [fileNameOf(opts), size].filter(Boolean).join(' · ');

    const isImage = /^image\//i.test(type) || (opts.file && !/\.pdf$/i.test(opts.file.name || '') && /^image\//.test(opts.file.type));
    if (isImage) {
      showImage(s, data, type);
      return;
    }

    const pdf = await pdfjsLib.getDocument({ data }).promise;
    if (s !== session) { pdf.destroy(); return; }
    s.pdf = pdf;
    await layout(s);
  } catch {
    if (s !== session) return;
    message(opts.file
      ? 'This file cannot be shown. It may be damaged or password-protected.'
      : 'The PDF could not be loaded. Try again in a moment, or download it instead.');
  }
}

function showImage(s, data, type) {
  s.objectUrl = URL.createObjectURL(new Blob([data], { type: type || 'image/*' }));
  const img = document.createElement('img');
  img.src = s.objectUrl;
  img.alt = s.opts.title || 'Picture';
  ui.stage.replaceChildren(img);
  setPaging(false);
}

function setPaging(on) {
  ui.pager.hidden = !on;
  ui.zoom.hidden = !on;
  ui.fit.hidden = !on;
  ui.pill.hidden = !on;
  ui.thumbs.hidden = !on;
}

function setTotal(n) {
  ui.totals.forEach((t) => { t.textContent = n; });
  ui.pgin.max = n;
  // setCurrent() skips the first page as "unchanged", so set the arrows here too.
  ui.dialog.querySelectorAll('[data-pv="prev"]').forEach((b) => { b.disabled = session.current <= 1; });
  ui.dialog.querySelectorAll('[data-pv="next"]').forEach((b) => { b.disabled = session.current >= n; });
}

async function layout(s) {
  const pdf = s.pdf;
  const first = await pdf.getPage(1);
  if (s !== session) return;
  const base = first.getViewport({ scale: 1 });

  s.pages = [];
  ui.stage.replaceChildren();
  for (let n = 1; n <= pdf.numPages; n++) {
    const box = document.createElement('div');
    box.className = 'pv-page';
    box.dataset.n = n;
    box.setAttribute('role', 'img');
    box.setAttribute('aria-label', `Page ${n} of ${pdf.numPages}`);
    ui.stage.append(box);
    s.pages.push({ n, box, w: base.width, h: base.height, page: n === 1 ? first : null, task: null, drawnAt: 0 });
  }

  setTotal(pdf.numPages);
  setPaging(true);
  buildThumbs(s);

  s.observer = new IntersectionObserver((entries) => {
    entries.forEach((e) => { if (e.isIntersecting) draw(s, s.pages[e.target.dataset.n - 1]); });
  }, { root: ui.stage, rootMargin: '800px 0px' });
  s.pages.forEach((p) => s.observer.observe(p.box));

  setFit();
}

function fitScale(s) {
  const room = Math.max(240, ui.stage.clientWidth - (window.innerWidth <= 640 ? 24 : 48));
  // Wide enough to read, never so wide a line runs the width of a monitor.
  return Math.min(room, 860) / s.pages[0].w;
}

function setFit() {
  if (!session?.pages.length) return;
  session.fit = true;
  applyScale(fitScale(session));
}

function zoomBy(f) {
  if (!session?.pages.length) return;
  session.fit = false;
  const next = Math.min(ZOOM_MAX * PDF_TO_CSS, Math.max(ZOOM_MIN * PDF_TO_CSS, session.scale * f));
  applyScale(next);
}

function applyScale(scale) {
  const s = session;
  const keep = s.current;
  s.scale = scale;
  ui.zoomLabel.textContent = Math.round((scale / PDF_TO_CSS) * 100) + '%';
  s.pages.forEach((p) => {
    p.box.style.width = Math.round(p.w * scale) + 'px';
    p.box.style.height = Math.round(p.h * scale) + 'px';
    if (p.drawnAt && Math.abs(p.drawnAt - scale) > 0.001) {
      p.task?.cancel();
      p.task = null;
      p.drawnAt = 0;
    }
  });
  // Pages near the view are drawn again at the new size; the rest when they
  // come into view.
  s.pages.forEach((p) => {
    const r = p.box.getBoundingClientRect();
    const v = ui.stage.getBoundingClientRect();
    if (r.bottom > v.top - 800 && r.top < v.bottom + 800) draw(s, p);
  });
  goTo(keep, true);
}

async function draw(s, p) {
  if (s !== session || p.drawnAt === s.scale || p.task) return;
  const scale = s.scale;
  try {
    p.page = p.page || await s.pdf.getPage(p.n);
    if (s !== session || scale !== s.scale) return;
    const own = p.page.getViewport({ scale: 1 });
    if (own.width !== p.w || own.height !== p.h) {
      p.w = own.width;
      p.h = own.height;
      p.box.style.width = Math.round(p.w * scale) + 'px';
      p.box.style.height = Math.round(p.h * scale) + 'px';
    }
    const ratio = window.devicePixelRatio || 1;
    const viewport = p.page.getViewport({ scale: scale * ratio });
    const canvas = document.createElement('canvas');
    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    p.task = p.page.render({ canvasContext: canvas.getContext('2d'), viewport });
    await p.task.promise;
    p.task = null;
    if (s !== session || scale !== s.scale) return;
    p.box.replaceChildren(canvas);
    p.drawnAt = scale;
  } catch {
    p.task = null;
  }
}

function buildThumbs(s) {
  ui.thumbs.replaceChildren();
  s.thumbObserver = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (!e.isIntersecting) return;
      s.thumbObserver.unobserve(e.target);
      drawThumb(s, e.target);
    });
  }, { root: ui.thumbs, rootMargin: '300px 0px' });

  s.pages.forEach((p) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'pv-thumb';
    b.dataset.n = p.n;
    b.setAttribute('aria-label', `Page ${p.n}`);
    const blank = document.createElement('span');
    blank.className = 'pv-blank';
    blank.style.height = Math.round(92 * (p.h / p.w)) + 'px';
    b.append(blank, document.createTextNode(String(p.n)));
    b.addEventListener('click', () => goTo(p.n));
    ui.thumbs.append(b);
    s.thumbObserver.observe(b);
  });
}

async function drawThumb(s, button) {
  try {
    const p = s.pages[button.dataset.n - 1];
    p.page = p.page || await s.pdf.getPage(p.n);
    if (s !== session) return;
    const ratio = window.devicePixelRatio || 1;
    const viewport = p.page.getViewport({ scale: (92 / p.page.getViewport({ scale: 1 }).width) * ratio });
    const canvas = document.createElement('canvas');
    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    await p.page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    if (s !== session) return;
    button.firstChild.replaceWith(canvas);
  } catch { /* the number still says which page it is */ }
}

function goTo(n, instant) {
  const s = session;
  if (!s?.pages.length) return;
  n = Math.min(s.pages.length, Math.max(1, n));
  const box = s.pages[n - 1].box;
  ui.stage.scrollTo({ top: box.offsetTop - 16, behavior: instant ? 'auto' : 'smooth' });
  setCurrent(n);
}

function trackCurrent() {
  const s = session;
  if (!s?.pages.length) return;
  const mark = ui.stage.scrollTop + ui.stage.clientHeight * 0.3;
  let n = 1;
  for (const p of s.pages) {
    if (p.box.offsetTop <= mark) n = p.n; else break;
  }
  setCurrent(n);
}

function setCurrent(n) {
  const s = session;
  if (s.current === n && ui.pgin.value === String(n)) return;
  s.current = n;
  if (document.activeElement !== ui.pgin) ui.pgin.value = n;
  ui.cur.textContent = n;
  ui.dialog.querySelectorAll('[data-pv="prev"]').forEach((b) => { b.disabled = n <= 1; });
  ui.dialog.querySelectorAll('[data-pv="next"]').forEach((b) => { b.disabled = n >= s.pages.length; });
  ui.thumbs.querySelectorAll('.pv-thumb').forEach((t) => {
    if (+t.dataset.n === n) {
      t.setAttribute('aria-current', 'page');
      const top = t.offsetTop;
      if (top < ui.thumbs.scrollTop || top + t.offsetHeight > ui.thumbs.scrollTop + ui.thumbs.clientHeight) {
        ui.thumbs.scrollTo({ top: top - 8 });
      }
    } else {
      t.removeAttribute('aria-current');
    }
  });
}

function teardown(reopening) {
  const s = session;
  if (!s) return;
  session = null;
  s.observer?.disconnect();
  s.thumbObserver?.disconnect();
  s.pages.forEach((p) => p.task?.cancel());
  s.pdf?.destroy();
  if (s.objectUrl) URL.revokeObjectURL(s.objectUrl);
  ui.stage.replaceChildren();
  ui.thumbs.replaceChildren();
  ui.side.replaceChildren();
  ui.acts.replaceChildren();
  document.documentElement.style.overflow = s.lockedOverflow || '';
  if (!reopening) s.opts.onClose?.();
}

function close() {
  if (!ui) return;
  const had = session;
  teardown();
  if (ui.dialog.open) ui.dialog.close();
  return had;
}

window.PdfViewer = { open, close, isOpen: () => !!session };

// Links and buttons that only say where the PDF is.
document.addEventListener('click', (e) => {
  const trigger = e.target.closest('[data-pdf-view]');
  if (!trigger || e.defaultPrevented) return;
  // A new tab on purpose (Ctrl/Cmd/middle click) is left alone.
  if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;
  const url = trigger.dataset.pdfView || trigger.getAttribute('href');
  if (!url || url === '#') return;
  e.preventDefault();
  open({
    url,
    title: trigger.dataset.pdfTitle || trigger.textContent.trim(),
    meta: trigger.dataset.pdfMeta || '',
    download: trigger.dataset.pdfDownload === 'false' ? false : (trigger.dataset.pdfDownload || url),
  });
});
