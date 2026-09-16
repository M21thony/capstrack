import * as pdfjsLib from 'pdfjs-dist';

pdfjsLib.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url).toString();

/*
 * A look at a file before it is sent: a PDF drawn page by page, a photo as
 * it is. Read from the file on the student's own device - nothing is
 * uploaded to show it. No iframe, so no browser viewer toolbar or download
 * prompt in the way.
 */
const dialog = document.getElementById('previewDialog');
const body = document.getElementById('previewBody');
const title = document.getElementById('previewTitle');
let objectUrl = null;

function note(text) {
  const p = document.createElement('p');
  p.className = 'preview-note';
  p.textContent = text;
  body.replaceChildren(p);
}

// `source` is anything with arrayBuffer(): a picked File, or a fetch Response.
async function drawPdf(source, name) {
  note('Loading…');
  try {
    const pdf = await pdfjsLib.getDocument({ data: await source.arrayBuffer() }).promise;
    body.replaceChildren();
    const width = Math.max(320, body.clientWidth - 24);
    const ratio = window.devicePixelRatio || 1;
    for (let n = 1; n <= pdf.numPages; n++) {
      const page = await pdf.getPage(n);
      const viewport = page.getViewport({ scale: (width / page.getViewport({ scale: 1 }).width) * ratio });
      const canvas = document.createElement('canvas');
      canvas.width = viewport.width;
      canvas.height = viewport.height;
      canvas.style.width = width + 'px';
      canvas.setAttribute('role', 'img');
      canvas.setAttribute('aria-label', `${name}, page ${n} of ${pdf.numPages}`);
      body.append(canvas);
      await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    }
  } catch {
    note('This PDF cannot be shown. It may be damaged or password-protected.');
  }
}

window.previewSubmissionFile = function (file) {
  if (!dialog) return;
  if (objectUrl) URL.revokeObjectURL(objectUrl);
  objectUrl = null;
  title.textContent = file.name;
  dialog.showModal();

  if (/\.pdf$/i.test(file.name)) {
    drawPdf(file, file.name);
  } else {
    objectUrl = URL.createObjectURL(file);
    const img = document.createElement('img');
    img.src = objectUrl;
    img.alt = file.name;
    body.replaceChildren(img);
  }
};

// A PDF already on the server (e.g. the team's recommended pre-proposal).
window.previewPdfUrl = async function (url, name) {
  if (!dialog) return;
  title.textContent = name;
  dialog.showModal();
  note('Loading…');
  try {
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/pdf' } });
    if (!res.ok) throw new Error(res.status);
    await drawPdf(res, name);
  } catch {
    note('The PDF could not be loaded. Try again, or ask your team leader to check the file.');
  }
};

dialog?.addEventListener('close', () => {
  body.replaceChildren();
  if (objectUrl) URL.revokeObjectURL(objectUrl);
  objectUrl = null;
});
dialog?.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
