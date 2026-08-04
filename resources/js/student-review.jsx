import { createRoot } from 'react-dom/client';
import StudentPdfViewer from './component/pages/StudentPdfViewer';

const el = document.getElementById('student-pdf-viewer-root');
if (el) {
    createRoot(el).render(
        <StudentPdfViewer pdfUrl={el.dataset.pdfUrl} remarksUrl={el.dataset.remarksUrl} />
    );
}
