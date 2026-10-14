import './pdf-viewer.js';

/*
 * A look at a file before it is sent, and at a PDF already on the server -
 * both in the one PDF window the rest of the app uses (pdf-viewer.js). Read
 * from the file on the student's own device when it is not sent yet, so
 * nothing is uploaded to show it.
 */

window.previewSubmissionFile = function (file) {
  window.PdfViewer.open({
    file,
    title: file.name,
    meta: 'Not sent yet · this is the file as it will be sent',
    download: false,
  });
};

// A PDF already on the server (e.g. the team's recommended pre-proposal).
window.previewPdfUrl = function (url, name) {
  window.PdfViewer.open({ url, title: name });
};
