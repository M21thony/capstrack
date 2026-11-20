import React, { useEffect, useMemo, useState } from "react";
import { Document, Page, pdfjs } from "react-pdf";
import {
  FigureZoom, Highlights, PageStrip, ReaderTools, RemarksPanel, numberRemarks, onPage, rectStyle, usePageColumn,
} from "../reader/Reader";
import { DiagramPage, DiagramsOnly, useDiagramPages, withDiagramPages } from "../reader/diagramPages";
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
            ? `Your “${d.requirement}” now goes to your CaPSA/TSA to verify.`
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
  // The team's draw.io files, shown after the PDF; beside the annotator, what
  // was taken out of them since is coloured red against the newer version's.
  diagrams = null, newerDiagrams = null,
  // Called once the document's first page is drawn and the remarks are in.
  onReady = null,
  // The team's own Documents page: each remark gets a Reply box.
  canReply = false,
}) {
  const [gen, setGen] = useState(general);
  const reply = async (id, body) => {
    const res = await fetch(`${remarksUrl}/${id}/replies`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
      },
      body: JSON.stringify({ body }),
    });
    if (!res.ok) {
      window.ctToast?.("Your reply was not sent. Try again.", "error");
      throw new Error(String(res.status));
    }
    const saved = await res.json();
    const add = (x) => (x.id === id ? { ...x, replies: [...(x.replies || []), saved] } : x);
    setRemarks((prev) => prev.map(add));
    setGen((prev) => prev.map(add));
  };
  // A diagram sent as itself has no PDF: its diagram pages are all there is.
  const [numPages, setNumPages] = useState(pdfUrl ? null : 0);
  const [error, setError] = useState(null);
  const [remarks, setRemarks] = useState([]);
  const [selectedId, setSelectedId] = useState(null);
  const [pdf, setPdf] = useState(null);
  const [zoomId, setZoomId] = useState(null);
  const drawings = useDiagramPages(diagrams, newerDiagrams, "old");
  const totalPages = numPages == null ? null : numPages + drawings.pages.length;
  // For snapshots of remarks, which may sit on a diagram page.
  const pagesPdf = useMemo(() => (pdfUrl && !pdf ? null : withDiagramPages(pdf, drawings.pages)), [pdfUrl, pdf, drawings.pages]);
  const col = usePageColumn(totalPages);

  useEffect(() => {
    if (focus?.rect) col.goToRect(focus.page, focus.rect);
  }, [focus]); // eslint-disable-line react-hooks/exhaustive-deps

  const [remarksIn, setRemarksIn] = useState(!remarksUrl);
  const [pageDrawn, setPageDrawn] = useState(!pdfUrl);

  useEffect(() => {
    if (!remarksUrl) return;
    fetch(remarksUrl, { headers: { Accept: "application/json" } })
      .then((r) => (r.ok ? r.json() : []))
      .then((data) => setRemarks(data.map(remarkFromServer)))
      .catch(() => setRemarks([]))
      .finally(() => setRemarksIn(true));
  }, [remarksUrl]);

  useEffect(() => {
    if (onReady && remarksIn && (pageDrawn || error)) onReady();
  }, [onReady, remarksIn, pageDrawn, error]);

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

  if (!pdfUrl && !diagrams) return null;
  const Holder = pdfUrl ? Document : DiagramsOnly;

  return (
    <div className="rr-work">
      <PageStrip numPages={totalPages} currentPage={col.currentPage} counts={counts} onGo={col.goToPage} pdf={pdf} />
      <section className="rr-viewer" aria-label="Your document with the reviewer's remarks">
        <div className="rr-hint">Click a highlight or its number to see the remark</div>
        <div className="rr-canvas" ref={col.canvasRef} onScroll={col.onScroll}>
          {error && <div className="rr-error">Could not load the document: {error}</div>}
          <Holder
            drawings={pdfUrl ? undefined : drawings}
            file={pdfUrl}
            onLoadSuccess={(doc) => { setNumPages(doc.numPages); setPdf(doc); setError(null); }}
            onLoadError={(err) => setError(err.message)}
            loading={<div className="rr-loading">Loading document…</div>}
          >
            {numPages != null && Array.from({ length: totalPages }, (_, i) => {
              const n = i + 1;
              const drawing = drawings.pages[n - numPages - 1];
              return (
                <div
                  key={n}
                  ref={(el) => { col.pageRefs.current[n] = el; }}
                  data-page={n}
                  className="rr-page"
                  style={{ width: col.pageWidth }}
                  aria-label={`Page ${n}`}
                >
                  {drawing && <DiagramPage page={drawing} />}
                  {!drawing && (col.visible.has(n)
                    ? <Page pageNumber={n} width={col.pageWidth} renderTextLayer={false} renderAnnotationLayer={false} loading="" onRenderSuccess={() => setPageDrawn(true)} onRenderError={() => setPageDrawn(true)} />
                    : <div style={{ height: Math.round((col.pageWidth * 11) / 8.5) }} />)}
                  {/* On the page itself, not in the overlay, so it can recolour the page's own lines. */}
                  {(figureMarks?.[n] || []).filter((m) => m.src).map((m) => m.rects.map((r, idx) => (
                    <img key={`${m.id}-${idx}`} className={`rr-figover${m.wash ? " wash" : ""}`} src={m.src} alt="" style={rectStyle(r)} />
                  )))}
                  <div className="rr-overlay">
                    {(marks?.[n] || []).map((m) => m.rects.map((r, idx) => (
                      <div key={`d${m.id}-${idx}`} className={`rr-hl rr-diff old${m.inserted ? " ins" : ""}${focus?.id === m.id ? " on" : ""}`} style={rectStyle(r)} />
                    )))}
                    {(figureMarks?.[n] || []).filter((m) => !m.src).map((m) => m.rects.map((r, idx) => (m.diff
                      ? <div key={`${m.id}-${idx}`} className={`rr-hl rr-diff old${m.inserted ? " ins" : ""}`} style={rectStyle(r)} />
                      : <div key={`${m.id}-${idx}`} className={`rr-figdiff old ${m.tone || ""}`} data-label={m.label || undefined} style={rectStyle(r)} />
                    )))}
                    <Highlights
                      remarks={remarks.filter((r) => onPage(r, n))}
                      page={n}
                      numbers={numbers}
                      selectedId={selectedId}
                      onSelect={open}
                      clickable
                    />
                  </div>
                </div>
              );
            })}
          </Holder>
        </div>
        <ReaderTools currentPage={col.currentPage} numPages={totalPages} zoom={col.zoom} onGo={col.goToPage} onZoom={col.setZoom} />
      </section>
      <RemarksPanel
        title={`Remarks from ${reviewer}`}
        remarks={remarks}
        general={gen}
        numbers={numbers}
        selectedId={selectedId}
        onOpen={open}
        readOnly
        onReply={canReply ? reply : null}
        top={top}
        pdf={pagesPdf}
        onZoom={setZoomId}
      />
      <FigureZoom
        pdf={pagesPdf}
        remark={zoomId != null ? remarks.find((r) => r.id === zoomId) : null}
        number={zoomId != null ? numbers[zoomId] : null}
        onClose={() => setZoomId(null)}
      />
    </div>
  );
}

export default StudentPdfViewer;
