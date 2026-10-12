{{--
  The PDF window (resources/js/pdf-viewer.js): every "View PDF" opens the
  pages drawn in the page by pdf.js rather than the browser's viewer in an
  iframe. Include once per page that shows a PDF, then call
  PdfViewer.open({ url, title, meta }) or give a link data-pdf-view.

  The empty window is written here so it is there before the script arrives;
  the script fills it.
--}}
<dialog class="pv" id="previewDialog" aria-label="Document preview"></dialog>
@vite('resources/js/pdf-viewer.js')
