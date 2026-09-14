import React, { useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import { Document, Page, pdfjs } from "react-pdf";
import "react-pdf/dist/Page/TextLayer.css";
import {
  Highlights, KINDS, PageStrip, ReaderTools, RemarksPanel,
  kindOf, mergeRectsByLine, numberRemarks, rectStyle, usePageColumn,
} from "../reader/Reader";
import ChangesPanel from "../reader/ChangesPanel";
import { useVersionDiff } from "../reader/useVersionDiff";

pdfjs.GlobalWorkerOptions.workerSrc = new URL(
  "pdfjs-dist/build/pdf.worker.min.mjs",
  import.meta.url
).toString();

// Keys that move the caret or extend a selection. Everything else that would
// type into the page is blocked - the page is editable only so the browser
// gives keyboard users a caret to select words with.
const NAV_KEYS = new Set([
  "ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown", "Home", "End",
  "PageUp", "PageDown", "Shift", "Control", "Meta", "Alt", "Tab", "Escape",
]);
const block = (e) => e.preventDefault();

/**
 * The document being reviewed, with the remarks on it.
 *
 * Select words - with the mouse, or Shift and the arrow keys then Enter - and
 * they stay highlighted while a box beside the page takes the remark. The
 * page itself never moves to make room.
 */
function PDFChecker({
  pdfUrl, comments, setComments, generalComments, onAddGeneral, onDeleteGeneral,
  onDeleteComment, selectedId, setSelectedId, notify, base = null, showChanges = true,
}) {
  const [numPages, setNumPages] = useState(null);
  const [error, setError] = useState(null);
  const [rendered, setRendered] = useState(() => new Set());
  const col = usePageColumn(numPages);

  // Changes since an earlier version are only drawn here, never kept as remarks.
  const diff = useVersionDiff(pdfUrl, base);
  const [tab, setTab] = useState("remarks");
  const [changeId, setChangeId] = useState(null);
  const marks = showChanges && diff.status === "ready" ? diff.marks : {};
  const openChange = (c) => { setChangeId(c.id); if (c.rect) col.goToRect(c.page, c.rect); };
  const openOldRemark = (r) => {
    setChangeId(r.changeIds[0] ?? null);
    if (r.target) col.goToRect(r.target.page, r.target.rect);
  };
  const tabs = base && (
    <div className="rr-tabs" role="tablist" aria-label="Side panel">
      {[["remarks", "Remarks"], ["changes", "What changed"]].map(([k, label]) => (
        <button key={k} type="button" role="tab" className="rr-tab" aria-selected={tab === k} onClick={() => setTab(k)}>{label}</button>
      ))}
    </div>
  );

  const [draft, setDraft] = useState(null);
  const [editingId, setEditingId] = useState(null);
  const [kind, setKind] = useState("pink");
  const [text, setText] = useState("");
  const [section, setSection] = useState("");
  const [popPos, setPopPos] = useState(null);
  const [selecting, setSelecting] = useState(false);
  const popRef = useRef(null);
  const textRef = useRef(null);

  const numbers = useMemo(() => numberRemarks(comments), [comments]);
  const counts = useMemo(() => {
    const c = {};
    comments.forEach((r) => { c[r.page_number] = (c[r.page_number] || 0) + 1; });
    return c;
  }, [comments]);
  const editing = editingId != null ? comments.find((c) => c.id === editingId) : null;
  const composing = Boolean(draft || editing);
  const composingRef = useRef(false);
  composingRef.current = composing;

  // Tell keyboard users how to finish once they have words selected.
  useEffect(() => {
    const onChange = () => {
      const sel = window.getSelection();
      const node = sel?.anchorNode;
      const el = node && (node.nodeType === 1 ? node : node.parentElement);
      setSelecting(Boolean(sel && !sel.isCollapsed && el?.closest?.(".rr-page") && !composingRef.current));
    };
    document.addEventListener("selectionchange", onChange);
    return () => document.removeEventListener("selectionchange", onChange);
  }, []);

  const closeBox = () => {
    setDraft(null);
    setEditingId(null);
    setPopPos(null);
  };

  const startFromSelection = () => {
    if (composingRef.current) return;
    const sel = window.getSelection();
    if (!sel || sel.isCollapsed || !sel.rangeCount) return;
    const range = sel.getRangeAt(0);
    const elOf = (n) => (n.nodeType === 1 ? n : n.parentElement);
    const pageEl = elOf(range.startContainer)?.closest(".rr-page");
    if (!pageEl) return;
    if (!pageEl.contains(elOf(range.endContainer))) {
      notify("Select words on one page at a time.", { type: "error" });
      return;
    }
    const selectedText = sel.toString().replace(/\s+/g, " ").trim();
    if (selectedText.length < 2) return;

    const box = (pageEl.querySelector(".react-pdf__Page") || pageEl).getBoundingClientRect();
    const rects = mergeRectsByLine(
      [...range.getClientRects()]
        .filter((r) => r.width > 1 && r.height > 1)
        .map((r) => ({
          top_position: (r.top - box.top) / box.height,
          left_position: (r.left - box.left) / box.width,
          highlight_width: r.width / box.width,
          highlight_height: r.height / box.height,
        }))
    );
    if (!rects.length) return;

    sel.removeAllRanges();
    setSelectedId(null);
    setDraft({ pageNumber: Number(pageEl.dataset.page), selectedText, highlightRects: rects });
    setText("");
    setSection("");
    setPopPos(null);
  };

  const startEdit = (id) => {
    const c = comments.find((x) => x.id === id);
    if (!c) return;
    setDraft(null);
    setSelectedId(id);
    setEditingId(id);
    setKind(kindOf(c.color));
    setText(c.text || "");
    setSection(c.section || "");
    setPopPos(null);
    col.goToRect(c.page_number, c.highlightRects?.[0] || { top_position: c.y_position }, false);
  };

  const openRemark = (id) => {
    const c = comments.find((x) => x.id === id);
    setSelectedId(id);
    if (c) col.goToRect(c.page_number, c.highlightRects?.[0] || { top_position: c.y_position });
  };

  const saveBox = () => {
    const body = text.trim();
    if (!body) { textRef.current?.focus(); return; }
    const sectionValue = section.trim() || null;
    if (editing) {
      setComments((prev) => prev.map((c) => (c.id === editing.id ? { ...c, text: body, color: kind, section: sectionValue } : c)));
      setSelectedId(editing.id);
    } else {
      const id = `new-${Date.now()}`;
      const first = draft.highlightRects[0];
      setComments((prev) => [...prev, {
        id,
        page_number: draft.pageNumber,
        x_position: first.left_position,
        y_position: first.top_position,
        text: body,
        author: "Reviewer",
        timestamp: new Date().toISOString(),
        selectedText: draft.selectedText,
        section: sectionValue,
        highlightRects: draft.highlightRects,
        color: kind,
      }]);
      setSelectedId(id);
    }
    closeBox();
  };

  // Place the box beside the words: in the right margin when it fits, and
  // otherwise over the edge of the page on the side away from the words.
  useLayoutEffect(() => {
    if (!composing || popPos) return;
    const canvas = col.canvasRef.current;
    const pageNumber = draft ? draft.pageNumber : editing.page_number;
    const wrapper = col.pageRefs.current[pageNumber];
    const pop = popRef.current;
    if (!canvas || !wrapper || !pop) return;
    const anchor = (draft ? draft.highlightRects : editing.highlightRects)?.[0]
      || { top_position: Number(editing?.y_position) || 0, left_position: 0.5, highlight_width: 0, highlight_height: 0 };
    const c = canvas.getBoundingClientRect();
    const p = (wrapper.querySelector(".react-pdf__Page") || wrapper).getBoundingClientRect();
    const w = pop.offsetWidth;
    const h = pop.offsetHeight;
    const gap = 16;
    const ay = p.top - c.top + canvas.scrollTop + (anchor.top_position + anchor.highlight_height / 2) * p.height;
    const aLeft = p.left - c.left + canvas.scrollLeft + anchor.left_position * p.width;
    const aMid = aLeft + (anchor.highlight_width * p.width) / 2;
    const pageMid = p.left - c.left + canvas.scrollLeft + p.width / 2;
    let left;
    let flip = false;
    if (c.right - p.right - gap >= w + 8) {
      left = p.right - c.left + canvas.scrollLeft + gap;
    } else if (aMid < pageMid) {
      left = canvas.scrollLeft + canvas.clientWidth - w - 12;
    } else {
      left = Math.max(canvas.scrollLeft + 12, aLeft - w - gap);
      flip = true;
    }
    const top = Math.max(canvas.scrollTop + 8, ay - 28);
    setPopPos({ left, top, ay: Math.min(Math.max(14, ay - top), h - 14), flip });
  });

  useEffect(() => { if (popPos) textRef.current?.focus({ preventScroll: true }); }, [popPos]);
  useEffect(() => { if (composingRef.current) setPopPos(null); }, [col.pageWidth]);

  const onPageKey = (e) => {
    if (NAV_KEYS.has(e.key)) return;
    if ((e.ctrlKey || e.metaKey) && ["c", "a"].includes(e.key.toLowerCase())) return;
    e.preventDefault();
    if (e.key === "Enter") startFromSelection();
  };

  const quote = draft ? draft.selectedText : editing?.selectedText;

  return (
    <div className="rr-work">
      <PageStrip numPages={numPages} currentPage={col.currentPage} counts={counts} onGo={col.goToPage} />

      <section className="rr-viewer" aria-label="Document">
        <div className="rr-hint">
          {selecting ? "Press Enter to add a remark on the selected words" : "Select text to add a remark"}
        </div>
        <div
          className="rr-canvas"
          ref={col.canvasRef}
          onScroll={col.onScroll}
          onMouseDown={(e) => {
            if (composingRef.current && !e.target.closest(".rr-pop") && !e.target.closest(".rr-page")) closeBox();
          }}
        >
          {error && <div className="rr-error">Could not load the document: {error}</div>}
          {pdfUrl && (
            <Document
              file={pdfUrl}
              onLoadSuccess={({ numPages: n }) => { setNumPages(n); setError(null); }}
              onLoadError={(err) => setError(err.message)}
              loading={<div className="rr-loading">Loading document…</div>}
            >
              {numPages && Array.from({ length: numPages }, (_, i) => {
                const n = i + 1;
                const pageRemarks = comments.filter((c) => Number(c.page_number) === n);
                return (
                  <div
                    key={n}
                    ref={(el) => { col.pageRefs.current[n] = el; }}
                    data-page={n}
                    className="rr-page"
                    style={{ width: col.pageWidth, minHeight: rendered.has(n) ? undefined : Math.round((col.pageWidth * 11) / 8.5) }}
                    contentEditable
                    suppressContentEditableWarning
                    spellCheck={false}
                    aria-readonly="true"
                    aria-label={`Page ${n}`}
                    onKeyDown={onPageKey}
                    onBeforeInput={block}
                    onPaste={block}
                    onCut={block}
                    onDrop={block}
                    onMouseUp={() => setTimeout(startFromSelection, 0)}
                  >
                    {col.visible.has(n) && (
                      <Page
                        pageNumber={n}
                        width={col.pageWidth}
                        renderTextLayer
                        renderAnnotationLayer={false}
                        loading=""
                        onRenderSuccess={() => setRendered((prev) => (prev.has(n) ? prev : new Set(prev).add(n)))}
                      />
                    )}
                    <div className="rr-overlay" contentEditable={false}>
                      {(marks[n] || []).map((m) => m.rects.map((r, idx) => (
                        <div key={`d${m.id}-${idx}`} className={`rr-hl rr-diff${changeId === m.id ? " on" : ""}`} style={rectStyle(r)} />
                      )))}
                      <Highlights
                        remarks={pageRemarks}
                        numbers={numbers}
                        selectedId={selectedId}
                        onSelect={openRemark}
                        override={editing ? { id: editing.id, kind } : null}
                      />
                      {draft && draft.pageNumber === n && draft.highlightRects.map((r, idx) => (
                        <div key={idx} className={`rr-hl pending c-${kind}`} style={rectStyle(r)} />
                      ))}
                    </div>
                  </div>
                );
              })}
            </Document>
          )}

          {composing && (
            <div
              ref={popRef}
              className={`rr-pop${popPos?.flip ? " flip" : ""}`}
              role="dialog"
              aria-label={editing ? "Edit remark" : "Add a remark"}
              style={{ left: popPos?.left ?? 0, top: popPos?.top ?? 0, visibility: popPos ? "visible" : "hidden", "--ay": `${popPos?.ay ?? 24}px` }}
              onKeyDown={(e) => { if (e.key === "Escape") { e.stopPropagation(); closeBox(); } }}
            >
              {quote && <div className="rr-pop-quote">“{quote}”</div>}
              <div className="rr-kinds" role="group" aria-label="Kind of remark">
                {KINDS.map((k) => (
                  <button key={k.id} type="button" className="rr-kind" aria-pressed={kind === k.id} onClick={() => setKind(k.id)}>
                    <span className={`rr-sw c-${k.id}`} />{k.label}
                  </button>
                ))}
              </div>
              <textarea
                ref={textRef}
                className="rr-input"
                placeholder="What should the team change?"
                aria-label="Remark"
                value={text}
                onChange={(e) => setText(e.target.value)}
                onKeyDown={(e) => { if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) { e.preventDefault(); saveBox(); } }}
              />
              <input
                className="rr-input"
                placeholder="Section, e.g. 2.3 Related Studies (optional)"
                aria-label="Section"
                value={section}
                onChange={(e) => setSection(e.target.value)}
              />
              <div className="rr-row">
                <span className="rr-muted">Ctrl+Enter to add</span>
                <span className="rr-spacer" />
                <button type="button" className="rr-btn sm" onClick={closeBox}>Cancel</button>
                <button type="button" className="rr-btn sm tint" onClick={saveBox}>{editing ? "Save remark" : "Add remark"}</button>
              </div>
            </div>
          )}
        </div>
        <ReaderTools currentPage={col.currentPage} numPages={numPages} zoom={col.zoom} onGo={col.goToPage} onZoom={col.setZoom} />
      </section>

      {tabs && tab === "changes" ? (
        <ChangesPanel top={tabs} base={base} diff={diff} selectedChange={changeId} onOpenChange={openChange} onOpenRemark={openOldRemark} />
      ) : (
      <RemarksPanel
        top={tabs}
        title="Remarks"
        remarks={comments}
        general={generalComments}
        numbers={numbers}
        selectedId={selectedId}
        onOpen={openRemark}
        onEdit={startEdit}
        onDelete={onDeleteComment}
        onAddGeneral={onAddGeneral}
        onDeleteGeneral={onDeleteGeneral}
      />
      )}
    </div>
  );
}

export default PDFChecker;
