import React, { useState, useEffect, useRef } from "react";
import { Document, Page, pdfjs } from "react-pdf";
import "react-pdf/dist/Page/AnnotationLayer.css";
import "react-pdf/dist/Page/TextLayer.css";

pdfjs.GlobalWorkerOptions.workerSrc = new URL(
  "pdfjs-dist/build/pdf.worker.min.mjs",
  import.meta.url
).toString();

const HIGHLIGHT_COLORS = {
  yellow: "rgba(255, 255, 0, 0.35)",
  green: "rgba(0, 255, 0, 0.35)",
  blue: "rgba(0, 191, 255, 0.35)",
  pink: "rgba(255, 0, 0, 0.35)",
  orange: "rgba(255, 165, 0, 0.35)",
};

// Read-only PDF viewer for students: renders the clean document with the
// reviewer's highlights drawn as overlays positioned from the same
// top/left/width/height fractions the annotator stores — never a page with
// annotations baked into its pixels, so this stays in sync if a reviewer
// updates a comment later.
function StudentPdfViewer({ pdfUrl, remarksUrl }) {
  const [numPages, setNumPages] = useState(null);
  const [remarks, setRemarks] = useState([]);
  const [openId, setOpenId] = useState(null);
  const [error, setError] = useState(null);
  // Pages are sized to fit the container's own width, never a fixed pixel
  // value — otherwise a page wider than the card forces a horizontal
  // scrollbar on some screens.
  const containerRef = useRef(null);
  const [pageWidth, setPageWidth] = useState(700);
  // Keyed by page number, so the "Expert Annotations" list (plain Blade
  // HTML, not part of this React tree) can ask this viewer to jump to a
  // page via a plain window CustomEvent.
  const pageRefs = useRef({});

  useEffect(() => {
    if (!remarksUrl) return;
    fetch(remarksUrl)
      .then((r) => (r.ok ? r.json() : []))
      .then(setRemarks)
      .catch(() => setRemarks([]));
  }, [remarksUrl]);

  useEffect(() => {
    const handleScrollToRemark = (e) => {
      const { page, remarkId } = e.detail || {};
      pageRefs.current[page]?.scrollIntoView({ behavior: "smooth", block: "start" });
      if (remarkId != null) setOpenId(remarkId);
    };

    window.addEventListener("student-review:scroll-to-remark", handleScrollToRemark);
    return () => window.removeEventListener("student-review:scroll-to-remark", handleScrollToRemark);
  }, []);

  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;

    const updateWidth = () => setPageWidth(Math.max(el.clientWidth - 24, 200));
    updateWidth();

    const observer = new ResizeObserver(updateWidth);
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  if (!pdfUrl) return null;

  return (
    <div style={styles.container} ref={containerRef}>
      {error && <p style={styles.error}>Could not load the document: {error}</p>}
      <Document
        file={pdfUrl}
        onLoadSuccess={({ numPages }) => setNumPages(numPages)}
        onLoadError={(err) => setError(err.message)}
        loading={<p style={styles.loading}>Loading document…</p>}
      >
        {numPages &&
          Array.from({ length: numPages }, (_, i) => {
            const pageNum = i + 1;
            const pageRemarks = remarks.filter((r) => Number(r.page_number) === pageNum);

            return (
              <div key={pageNum} ref={(el) => (pageRefs.current[pageNum] = el)} style={styles.pageWrapper}>
                <Page pageNumber={pageNum} width={pageWidth} renderTextLayer={false} renderAnnotationLayer={false} />

                {pageRemarks.map((r) => (
                  <div
                    key={r.id}
                    onClick={() => setOpenId(openId === r.id ? null : r.id)}
                    title={r.comment}
                    style={{
                      position: "absolute",
                      top: `${r.top_position * 100}%`,
                      left: `${r.left_position * 100}%`,
                      width: `${r.highlight_width * 100}%`,
                      height: `${r.highlight_height * 100}%`,
                      backgroundColor: HIGHLIGHT_COLORS[r.color] || HIGHLIGHT_COLORS.yellow,
                      cursor: "pointer",
                      outline: openId === r.id ? "2px solid #3b82f6" : "none",
                    }}
                  />
                ))}

                <div style={styles.pageLabel}>Page {pageNum} of {numPages}</div>
              </div>
            );
          })}
      </Document>
    </div>
  );
}

const styles = {
  container: {
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
    padding: "12px",
  },
  loading: {
    padding: "24px",
    color: "#6b7280",
    fontSize: "13px",
  },
  error: {
    padding: "12px",
    color: "#b91c1c",
    fontSize: "13px",
  },
  pageWrapper: {
    position: "relative",
    marginBottom: "28px",
    boxShadow: "0 1px 4px rgba(0,0,0,0.15)",
  },
  pageLabel: {
    position: "absolute",
    bottom: "-18px",
    left: 0,
    fontSize: "11px",
    color: "#9ca3af",
  },
};

export default StudentPdfViewer;
