{{--
  The PDF window (resources/js/pdf-viewer.js): every "View PDF" opens the
  pages drawn in the page by pdf.js rather than the browser's viewer in an
  iframe. Include once per page that shows a PDF, then call
  PdfViewer.open({ url, title, meta }) or give a link data-pdf-view.

  The empty window is written here so it is there before the script arrives;
  the script fills it.

  Safe to include twice: the staff sidebars bring it in for Export's preview,
  and a page that shows PDFs of its own includes it too. Only the first one
  is written, so there is one window.
--}}
@once
<dialog class="pv" id="previewDialog" aria-label="Document preview"></dialog>
@vite('resources/js/pdf-viewer.js')
@endonce
