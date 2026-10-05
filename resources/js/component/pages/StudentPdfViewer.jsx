import React, { useEffect, useMemo, useState } from "react";
import { Document, Page, pdfjs } from "react-pdf";
import {
  FigureZoom, Highlights, PageStrip, ReaderTools, RemarksPanel, numberRemarks, rectStyle, usePageColumn,
} from "../reader/Reader";
import { remarkFromServer } from "../reader/figures";
import "../reader/reader.css";

pdfjs.GlobalWorkerOptions.workerSrc = new URL(
  "pdfjs-dist/build/pdf.worker.min.mjs",
  import.meta.url
).toString();

function Decision({ d }) {
  if (d.verdict === "needs_revision") {
    return (
      <div className="rv-decision revise">
        <b>Needs revision</b>
        <p>
          Work through the remarks below, then submit your {d.next} to {d.reviewer}
          {d.due ? `. Needed by ${d.due}.` : "."}
        </p>
      </div>
    );
  }
  if (d.verdict === "approved") {
    return (
      <div className="rv-decision ok">
        <b>Approved{d.date ? ` on ${d.date}` : ""}</b>
        <p>
          {d.requirement
            ? `Your “${d.requirement}” now goes to your CaPSA to verify.`
            : `No changes needed from your ${d.role.toLowerCase()}.`}
        </p>
      </div>
    );
  }
  return (
    <div className="rv-decision plain">
      <b>Reviewed{d.date ? ` on ${d.date}` : ""}</b>
      <p>Read the remarks below from your {d.role.toLowerCase()}.</p>
    </div>
  );
}

/**
 * The student's read-only reader: their document, with the reviewer's
 * remarks on it and beside it. Pages are drawn from the clean file, never a
 * copy with the remarks baked into the pixels, so it always matches the
 * remarks as they are now.
 */
function StudentPdfViewer({
  pdfUrl, remarksUrl, reviewer = "your reviewer", general = [], decision = null, others = [],
  // Used only when it is the earlier version beside the annotator: what was
  // removed from it, and the change to scroll to.
  marks = null, focus = null, figureMarks = null,
}) {
  const [numPages, setNumPages] = useState(null);
  const [error, setError] = useState(null);
  const [remarks, setRemarks] = useState([]);
  const [selectedId, setSelectedId] = useState(null);
  const [pdf, setPdf] = useState(null);
  const [zoomId, setZoomId] = useState(null);
  const col = usePageColumn(numPages);

  useEffect(() => {
    if (focus?.rect) col.goToRect(focus.page, focus.rect);
  }, [focus]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!remarksUrl) return;
    fetch(remarksUrl, { headers: { Accept: "application/json" } })
      .then((r) => (r.ok ? r.json() : []))
      .then((data) => setRemarks(data.map(remarkFromServer)))
      .catch(() => setRemarks([]));
  }, [remarksUrl]);

  const numbers = useMemo(() => numberRemarks(remarks), [remarks]);
  const counts = useMemo(() => {
    const c = {};
    remarks.forEach((r) => { c[r.page_number] = (c[r.page_number] || 0) + 1; });
    return c;
  }, [remarks]);

  const open = (id) => {
    setSelectedId(id);
    const r = remarks.find((x) => x.id === id);
    if (r) col.goToRect(r.page_number, r.highlightRects[0] || { top_position: r.y_position });
  };

  const top = (
    <>
      {decision && <Decision d={decision} />}
      {others.length > 0 && (
        <div className="rv-others" aria-label="Other reviewers of this document">
          {others.map((o) => (o.url
            ? <a key={o.label} className="rr-chip" href={o.url}>{o.label}</a>
            : <span key={o.label} className="rr-chip off">{o.label}</span>))}
        </div>
      )}
    </>
  );

  if (!pdfUrl) return null;

  return (
    <div className="rr-work">
      <PageStrip numPages={numPages} currentPage={col.currentPage} counts={counts} onGo={col.goToPage} />
      <section className="rr-viewer" aria-label="Your document with the reviewer's remarks">
        <div className="rr-hint">Click a highlight or its number to see the remark</div>
        <div className="rr-canvas" ref={col.canvasRef} onScroll={col.onScroll}>
          {error && <div className="rr-error">Could not load the document: {error}</div>}
          <Document
            file={pdfUrl}
            onLoadSuccess={(doc) => { setNumPages(doc.numPages); setPdf(doc); setError(null); }}
            onLoadError={(err) => setError(err.message)}
            loading={<div className="rr-loading">Loading document…</div>}
          >
            {numPages && Array.from({ length: numPages }, (_, i) => {
              const n = i + 1;
              return (
                <div
                  key={n}
                  ref={(el) => { col.pageRefs.current[n] = el; }}
                  data-page={n}
                  className="rr-page"
                  style={{ width: col.pageWidth }}
                  aria-label={`Page ${n}`}
                >
                  {col.visible.has(n)
                    ? <Page pageNumber={n} width={col.pageWidth} renderTextLayer={false} renderAnnotationLayer={false} loading="" />
                    : <div style={{ height: Math.round((col.pageWidth * 11) / 8.5) }} />}
                  <div className="rr-overlay">
                    {(marks?.[n] || []).map((m) => m.rects.map((r, idx) => (
                      <div key={`d${m.id}-${idx}`} className={`rr-hl rr-diff old${m.inserted ? " ins" : ""}${focus?.id === m.id ? " on" : ""}`} style={rectStyle(r)} />
                    )))}
                    {(figureMarks?.[n] || []).map((m) => m.rects.map((r, idx) => (m.src
                      ? <img key={`${m.id}-${idx}`} className="rr-figover" src={m.src} alt="" style={rectStyle(r)} />
                      : m.diff
                      ? <div key={`${m.id}-${idx}`} className={`rr-hl rr-diff old${m.inserted ? " ins" : ""}`} style={rectStyle(r)} />
                      : <div key={`${m.id}-${idx}`} className={`rr-figdiff old ${m.tone || ""}`} data-label={m.label || undefined} style={rectStyle(r)} />
                    )))}
                    <Highlights
                      remarks={remarks.filter((r) => r.page_number === n)}
                      numbers={numbers}
                      selectedId={selectedId}
                      onSelect={open}
                      clickable
                    />
                  </div>
                </div>
              );
            })}
          </Document>
        </div>
        <ReaderTools currentPage={col.currentPage} numPages={numPages} zoom={col.zoom} onGo={col.goToPage} onZoom={col.setZoom} />
      </section>
      <RemarksPanel
        title={`Remarks from ${reviewer}`}
        remarks={remarks}
        general={general}
        numbers={numbers}
        selectedId={selectedId}
        onOpen={open}
        readOnly
        top={top}
        pdf={pdf}
        onZoom={setZoomId}
      />
      <FigureZoom
        pdf={pdf}
        remark={zoomId != null ? remarks.find((r) => r.id === zoomId) : null}
        number={zoomId != null ? numbers[zoomId] : null}
        onClose={() => setZoomId(null)}
      />
    </div>
  );
}

export default StudentPdfViewer;
