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
    // The page's first-time skeleton (student/partials/content-skeleton)
    // waits for the document and the remarks, so it gives way to the reader
    // as it will be, not to an empty one.
    const ready = window.contentSkeleton ? window.contentSkeleton.hold() : null;

    createRoot(el).render(
        <StudentPdfViewer
            pdfUrl={el.dataset.pdfUrl || null}
            remarksUrl={el.dataset.remarksUrl}
            diagrams={el.dataset.diagramsUrl || null}
            reviewer={el.dataset.reviewer}
            general={json(el.dataset.general, [])}
            decision={json(el.dataset.decision, null)}
            others={json(el.dataset.others, [])}
            onReady={ready}
            canReply
        />
    );
}
