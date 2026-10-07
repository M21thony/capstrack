import { createRoot } from 'react-dom/client';
import StudentPdfViewer from './component/pages/StudentPdfViewer';

const el = document.getElementById('student-pdf-viewer-root');

const json = (value, fallback) => {
    try {
        return value ? JSON.parse(value) : fallback;
    } catch {
        return fallback;
    }
};

if (el) {
    createRoot(el).render(
        <StudentPdfViewer
            pdfUrl={el.dataset.pdfUrl}
            remarksUrl={el.dataset.remarksUrl}
            diagrams={el.dataset.diagramsUrl || null}
            reviewer={el.dataset.reviewer}
            general={json(el.dataset.general, [])}
            decision={json(el.dataset.decision, null)}
            others={json(el.dataset.others, [])}
        />
    );
}
