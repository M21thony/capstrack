import React, { useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import { Document, Page, pdfjs } from "react-pdf";
import "react-pdf/dist/Page/TextLayer.css";
import {
  FigureZoom, Highlights, KINDS, PageStrip, ReaderTools, RemarksPanel, SectionPicker,
  kindOf, mergeRectsByLine, numberRemarks, rectStyle, usePageColumn,
} from "../reader/Reader";
import ChangesPanel, { useEarlierPdf, useFigureDiff } from "../reader/ChangesPanel";
import { figureMarks, oldOverlayRect, overlayRect, tints } from "../reader/figureDiff";
import FigureCrop from "../reader/FigureCrop";
import { DiagramPage, DiagramsOnly, caretAt, shapeCell, shapeRects, shownPicture, useDiagramPages, withDiagramPages } from "../reader/diagramPages";
import {
  boundsOf, captionsOnPage, figureFor, guessDiagramType, marksOf, shapeLabel, uniqueFigures,
} from "../reader/figures";
import { cellAt } from "../reader/drawioDiff";

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

/** The kinds offered on the bar over selected words; the remark box still offers every kind. */
const SELECTION_KINDS = KINDS.filter((k) => ["pink", "yellow", "blue"].includes(k.id));
/** Half the bar's width, so it is kept inside the page column. */
const SELBAR_HALF = 175;

/**
 * The text spot a drag at a point means, as [node, offset]: the words under it, or, beside
 * the words, the end of the nearest words on that line to its left (the start if none are).
 */
function spotNear(layer, x, y) {
  const walker = document.createTreeWalker(layer, NodeFilter.SHOW_TEXT);
  const range = document.createRange();
  const words = [];
  while (walker.nextNode()) {
    const t = walker.currentNode;
    if (!t.data.trim()) continue;
    range.selectNodeContents(t);
    for (const b of range.getClientRects()) words.push({ t, b });
  }
  if (!words.length) return null;
  const dy = (b) => Math.max(b.top - y, 0, y - b.bottom);
  const nearest = Math.min(...words.map((w) => dy(w.b)));
  const line = words.filter((w) => dy(w.b) <= nearest + 1);
  const before = line.filter((w) => w.b.left <= x);
  const w = before.length ? before.reduce((a, c) => (c.b.left > a.b.left ? c : a)) : line.reduce((a, c) => (c.b.left < a.b.left ? c : a));
  if (x < w.b.left) return [w.t, 0];
  if (x >= w.b.right) return [w.t, w.t.length];
  const at = caretAt(x, Math.min(Math.max(y, w.b.top + 0.5), w.b.bottom - 0.5));
  return at && at[0] === w.t ? at : [w.t, w.t.length];
}
const clamp = (n) => Math.min(1, Math.max(0, n));

const HINTS = {
  box: "Drag over a figure to add a remark",
  pin: "Click one spot on a figure to add a remark",
  shape: "Click a box or arrow on a diagram page · Shift+click adds another",
};

/**
 * Every captioned figure in the document, and whether it has been looked at:
 * it has a remark, or the reviewer marked it checked. So a figure is never
 * missed in a long chapter.
 */
function FiguresPanel({ top, attached, figures, comments, checked, onToggle, onGo }) {
  const count = (label) => comments.filter((c) => c.figure_label === label).length;
  const done = figures.filter((f) => count(f.label) || checked.includes(f.label)).length;
  return (
    <aside className="rr-side" aria-label="Figures">
      {top}
      {attached}
      {figures.length ? (
        <>
          <p className="rr-help">
            <b>{done} of {figures.length}</b> figures reviewed. A figure counts once it has a remark, or once you mark it checked.
            <span className="rr-progress" aria-hidden="true"><i style={{ width: `${(done / figures.length) * 100}%` }} /></span>
          </p>
          <div className="rr-figlist">
            {figures.map((f) => {
              const n = count(f.label);
              const isChecked = checked.includes(f.label);
              return (
                <div
                  key={f.label}
                  className="rr-frow"
                  role="button"
                  tabIndex={0}
                  aria-label={`Go to ${f.label} on page ${f.page}`}
                  onClick={() => onGo(f)}
                  onKeyDown={(e) => {
                    if (e.target !== e.currentTarget) return;
                    if (e.key === "Enter" || e.key === " ") { e.preventDefault(); onGo(f); }
                  }}
                >
                  <div><b>{f.label}</b> <small>· p.{f.page}</small><div className="rr-ft">{f.title || "Untitled"}</div></div>
                  {!n && (
                    <button type="button" className="rr-btn sm" aria-pressed={isChecked} onClick={(e) => { e.stopPropagation(); onToggle(f.label); }}>
                      {isChecked ? "Undo check" : "Mark checked"}
                    </button>
                  )}
                  <div className="rr-fstat">
                    {n
                      ? <span className="rr-chip gold">{n} {n === 1 ? "remark" : "remarks"}</span>
                      : isChecked ? <span className="rr-chip ok">Checked, no remarks</span> : <span className="rr-chip">Not reviewed</span>}
                  </div>
                </div>
              );
            })}
          </div>
        </>
      ) : (
        <div className="rr-empty" style={{ margin: "24px auto" }}>
          <b>No figure captions found</b>
          <span>Captions that start with “Figure 3.1” or “Fig. 2:” are listed here so none is missed.</span>
        </div>
      )}
    </aside>
  );
}

/**
 * The team's diagram files, which sit after the PDF's last page. Listed at the
 * top of the side panel so a reviewer knows they are there, and can jump to one.
 */
function AttachedDiagrams({ pages, firstPage, onOpen }) {
  const files = new Set(pages.map((p) => p.file)).size;
  return (
    <section className="rr-attached" aria-label="Attached diagrams">
      <div className="rr-attached-head">Attached diagrams · {files} {files === 1 ? "file" : "files"}</div>
      <p className="rr-help rr-help-tight">The team sent these diagram files with the chapter. They come after the last page. Open one to remark on its boxes and arrows.</p>
      {pages.map((p, i) => (
        <div key={p.key} className="rr-drow">
          <div><b title={p.label}>{p.label}</b><small>Page {firstPage + i}</small></div>
          <button type="button" className="rr-btn sm tint" onClick={() => onOpen(firstPage + i)}>Open</button>
        </div>
      ))}
    </section>
  );
}

/**
 * The document being reviewed, with the remarks on it.
 *
 * Select words - with the mouse, or Shift and the arrow keys then Enter - and
 * they stay highlighted while a box beside the page takes the remark. The
 * page itself never moves to make room. For a figure, Box draws a box over
 * it (or several) and Pin marks one spot; the remark box then also takes
 * which figure it is and what kind of diagram.
 */
function PDFChecker({
  pdfUrl, comments, setComments, generalComments, onAddGeneral, onDeleteGeneral,
  onDeleteComment, selectedId, setSelectedId, notify, base = null, showChanges = true,
  diff = { status: "idle" }, onFocusChange,
  startMode = "text", startTab = "remarks", checkedFigures = [], onToggleFigure = () => {},
  revisionStatus = {}, onRevision = () => {}, compareOpen = false, onFigures = () => {}, diagrams = null,
}) {
  // A diagram sent as itself has no PDF: its diagram pages are all there is.
  const [numPages, setNumPages] = useState(pdfUrl ? null : 0);
  const [pdf, setPdf] = useState(null);
  const [figures, setFigures] = useState([]);
  const [mode, setMode] = useState(startMode);
  const [zoomId, setZoomId] = useState(null);
  const [error, setError] = useState(null);
  const [rendered, setRendered] = useState(() => new Set());
  // The team's draw.io files follow the PDF as pages of their own.
  const drawings = useDiagramPages(diagrams, base?.diagrams ?? null, "neu");
  const totalPages = numPages == null ? null : numPages + drawings.pages.length;
  // For snapshots of remarks, which may sit on a diagram page.
  const pagesPdf = useMemo(() => (pdfUrl && !pdf ? null : withDiagramPages(pdf, drawings.pages)), [pdfUrl, pdf, drawings.pages]);
  const col = usePageColumn(totalPages);
  const Holder = pdfUrl ? Document : DiagramsOnly;
  // Diagram pages after a PDF are easy to miss, so they are marked and listed.
  const firstDiagramPage = numPages && drawings.pages.length ? numPages + 1 : null;
  const [flashPage, setFlashPage] = useState(null);
  const openDiagram = (n) => {
    col.goToPage(n);
    setFlashPage(null);
    requestAnimationFrame(() => setFlashPage(n));
  };

  // Changes since an earlier version are only drawn, never kept as remarks.
  // Opening one also moves the earlier version, when it is open beside this.
  const [tab, setTab] = useState(startTab);
  const [changeId, setChangeId] = useState(null);
  const marks = showChanges && diff.status === "ready" ? diff.marks : {};
  // Diagrams are pictures, so the words diff cannot see them change: they are
  // compared picture by picture once the earlier version is opened beside
  // this one (or the Figures list is), and their changed spots boxed on both.
  const [figuresWanted, setFiguresWanted] = useState(false);
  useEffect(() => { if (compareOpen || (showChanges && base)) setFiguresWanted(true); }, [compareOpen, showChanges, base]);
  const earlierPdf = useEarlierPdf(base && figuresWanted ? base.file : null);
  const figureDiff = useFigureDiff(earlierPdf, pdf, figuresWanted);
  const figureBoxes = useMemo(() => (figureDiff.status === "ready" ? figureMarks(figureDiff.figures) : { marks: {}, oldMarks: {} }), [figureDiff]);
  // In an edited diagram, a shape or arrow put in is coloured green where it
  // sits on this page, and one taken out red on the earlier version's page.
  const figureTints = useMemo(() => {
    const now = {}, before = {};
    if (figureDiff.status !== "ready") return { now, before };
    figureDiff.figures.forEach((f, i) => {
      if (f.kind !== "small") return;
      const { addedWash, removedWash } = (f.tints ??= tints(f));
      if (addedWash) (now[f.newPage] ||= []).push({ id: `w${i}`, wash: true, src: addedWash.toDataURL(), rects: [overlayRect(f, addedWash.height)] });
      if (removedWash) (before[f.oldPage] ||= []).push({ id: `w${i}`, wash: true, src: removedWash.toDataURL(), rects: [oldOverlayRect(f, removedWash.height)] });
    });
    return { now, before };
  }, [figureDiff]);
  useEffect(() => {
    const oldMarks = { ...figureBoxes.oldMarks };
    Object.entries(figureTints.before).forEach(([p, ts]) => { oldMarks[p] = [...(oldMarks[p] || []), ...ts]; });
    onFigures({ status: figureDiff.status, note: figureDiff.note, oldMarks });
  }, [figureDiff, figureBoxes, figureTints]); // eslint-disable-line react-hooks/exhaustive-deps
  const openChange = (c) => {
    setChangeId(c.id);
    if (c.rect) col.goToRect(c.page, c.rect);
    onFocusChange?.(c);
  };
  const openOldRemark = (r) => {
    setChangeId(r.changeIds[0] ?? null);
    if (r.target) col.goToRect(r.target.page, r.target.rect);
    const c = diff.changes?.find((x) => x.id === r.changeIds[0]);
    if (c) onFocusChange?.(c);
  };
  // The captions, read once from the PDF's own text.
  useEffect(() => {
    if (!pdf) return undefined;
    let alive = true;
    (async () => {
      const found = [];
      for (let n = 1; n <= pdf.numPages && alive; n++) {
        try {
          const page = await pdf.getPage(n);
          const { height } = page.getViewport({ scale: 1 });
          found.push(...captionsOnPage((await page.getTextContent()).items, height, n));
        } catch { /* a page with no text */ }
      }
      if (alive) setFigures(uniqueFigures(found));
    })();
    return () => { alive = false; };
  }, [pdf]);

  const toCheck = (diff.revision || []).filter((r) => !((r.id in revisionStatus ? revisionStatus[r.id] : r.revision_status))).length;
  const figuresDone = figures.filter((f) => checkedFigures.includes(f.label) || comments.some((c) => c.figure_label === f.label)).length;
  const tabList = [
    ["remarks", "Remarks"],
    ["figures", figures.length ? `Figures ${figuresDone}/${figures.length}` : "Figures"],
    ...(base ? [["changes", toCheck ? `What changed · ${toCheck} to check` : "What changed"]] : []),
  ];
  const tabs = (
    <div className="rr-tabs" role="tablist" aria-label="Side panel">
      {tabList.map(([k, label]) => (
        <button key={k} type="button" role="tab" className="rr-tab" aria-selected={tab === k} onClick={() => setTab(k)}>{label}</button>
      ))}
    </div>
  );

  const [draft, setDraft] = useState(null);
  const [editingId, setEditingId] = useState(null);
  const [kind, setKind] = useState("pink");
  const [text, setText] = useState("");
  const [section, setSection] = useState("");
  const [figure, setFigure] = useState("");
  const [diagramType, setDiagramType] = useState("");
  // Drawing another box onto the same remark: the remark box steps aside meanwhile.
  const [adding, setAdding] = useState(false);
  // The cell under the pointer while picking shapes: { n, id, rect }.
  const [hoverShape, setHoverShape] = useState(null);
  // The earlier version's shape remark a What changed card is outlining: { id, page, rects, before }.
  const [shownShape, setShownShape] = useState(null);
  // Diagram pages show the earlier version's drawing instead (V).
  const [beforeView, setBeforeView] = useState(false);
  const hasBefore = drawings.pages.some((p) => p.oldMarked);
  const drawing = useRef(null);
  const [popPos, setPopPos] = useState(null);
  const [selecting, setSelecting] = useState(false);
  const [selBtn, setSelBtn] = useState(null);
  const popRef = useRef(null);
  const textRef = useRef(null);

  const numbers = useMemo(() => numberRemarks(comments), [comments]);
  const counts = useMemo(() => {
    const c = {};
    comments.forEach((r) => { c[r.page_number] = (c[r.page_number] || 0) + 1; });
    return c;
  }, [comments]);
  const editing = editingId != null ? comments.find((c) => c.id === editingId) : null;
  // A remark being written or edited always has a draft: where it is on the page.
  const composing = Boolean(draft) && !draft.drawing && !adding;
  const composingRef = useRef(false);
  composingRef.current = composing;

  // Tell keyboard users how to finish once they have words selected.
  useEffect(() => {
    const onChange = () => {
      const sel = window.getSelection();
      const node = sel?.anchorNode;
      const el = node && (node.nodeType === 1 ? node : node.parentElement);
      setSelecting(Boolean(sel && !sel.isCollapsed && el?.closest?.(".rr-page") && !composingRef.current));
      if (!sel || sel.isCollapsed) setSelBtn(null);
    };
    document.addEventListener("selectionchange", onChange);
    return () => document.removeEventListener("selectionchange", onChange);
  }, []);

  const closeBox = () => {
    setDraft(null);
    setEditingId(null);
    setAdding(false);
    setPopPos(null);
  };

  // A click away closes the box, but never drops words the reviewer typed: those need Add or Cancel.
  const clickAway = () => {
    if (text.trim() === (editing?.text || "").trim()) return closeBox();
    textRef.current?.focus({ preventScroll: true });
    notify("Your remark is not saved yet. Add it, or press Cancel to drop it.", { type: "info" });
  };

  // A new remark on a mark: empty fields, and the figure guessed from the caption under it.
  const beginRemark = (d) => {
    const onFigure = d.type !== "text";
    const label = onFigure ? figureFor(figures, d.pageNumber, marksOf(d)) || "" : "";
    setDraft(d);
    setText("");
    setSection("");
    setFigure(label);
    setDiagramType(onFigure ? guessDiagramType(figures.find((f) => f.label === label)?.title) : "");
    setPopPos(null);
  };

  const changeMode = (next) => {
    if (next === "shape" && !drawings.pages.length) return;
    if (next !== "box" && adding) { setAdding(false); setPopPos(null); }
    if (next !== "shape") setHoverShape(null);
    if (next !== "text") window.getSelection()?.removeAllRanges();
    setMode(next);
  };

  // T, B, P and S switch how the page is marked; V flips diagrams to the earlier version; Esc finishes adding boxes.
  useEffect(() => {
    const onKey = (e) => {
      if (document.querySelector("dialog[open]")) return;
      if (e.key === "Escape" && adding) { setAdding(false); setPopPos(null); return; }
      if (e.ctrlKey || e.metaKey || e.altKey || e.target.closest?.("input, textarea, select")) return;
      if (e.key.toLowerCase() === "v" && hasBefore) { setBeforeView((v) => !v); return; }
      const next = { t: "text", b: "box", p: "pin", s: "shape" }[e.key.toLowerCase()];
      if (next) changeMode(next);
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  });

  const pointOn = (e, pageEl) => {
    const b = (pageEl.querySelector(".react-pdf__Page") || pageEl).getBoundingClientRect();
    return { x: clamp((e.clientX - b.left) / b.width), y: clamp((e.clientY - b.top) / b.height), b };
  };

  // The cell under the pointer on a diagram page, in the drawing shown: { page, id, rects }.
  const shapeUnder = (e, n) => {
    const page = drawings.pages[n - numPages - 1];
    if (!page || (beforeView && page.oldMarked)) return null;
    const pic = shownPicture(page, showChanges);
    const { x, y } = pointOn(e, e.currentTarget);
    const t = cellAt(pic.targets || [], x * pic.width, y * pic.height);
    return t && { page, id: t.id, rects: shapeRects(t, pic) };
  };

  const pickShape = (e, n) => {
    if (!drawings.pages[n - numPages - 1]) return;
    if (beforeView && drawings.pages[n - numPages - 1].oldMarked) { notify("This is the earlier version. Press V to go back and pick a shape.", { type: "info" }); return; }
    const hit = shapeUnder(e, n);
    if (!hit) return;
    const cell = shapeCell(hit.page, hit.id);
    // Shift+click adds the cell to the remark being written (or takes it off again).
    if (e.shiftKey && draft?.type === "shape" && !draft.drawing) {
      if (n !== draft.pageNumber) { notify(`Shapes on one remark stay on one page. Pick on page ${draft.pageNumber}.`, { type: "error" }); return; }
      const at = draft.shape_ref.cells.findIndex((c) => c.id === cell.id);
      if (at >= 0 && draft.shape_ref.cells.length === 1) return;
      const cells = at >= 0 ? draft.shape_ref.cells.filter((_, i) => i !== at) : [...draft.shape_ref.cells, cell];
      const pic = shownPicture(hit.page, showChanges);
      const highlightRects = cells.flatMap((c) => { const t = pic.targets.find((x) => x.id === c.id); return t ? shapeRects(t, pic) : []; });
      setDraft((d) => ({ ...d, highlightRects, shape_ref: { ...d.shape_ref, cells } }));
      return;
    }
    if (composingRef.current) { clickAway(); return; }
    setSelectedId(null);
    beginRemark({ type: "shape", pageNumber: n, highlightRects: hit.rects, shape_ref: { file: hit.page.file, page: hit.page.name, cells: [cell] } });
  };

  const onMarkDown = (e, n) => {
    if (mode === "text" || e.button !== 0 || e.target.closest(".rr-num, .rr-pin")) return;
    if (mode === "shape") { e.preventDefault(); pickShape(e, n); return; }
    if (adding) {
      if (n !== draft.pageNumber) { notify(`Boxes on one remark stay on one page. Draw on page ${draft.pageNumber}.`, { type: "error" }); return; }
    } else if (composingRef.current) { clickAway(); return; }
    e.preventDefault();
    const { x, y, b } = pointOn(e, e.currentTarget);
    if (mode === "pin") {
      setSelectedId(null);
      beginRemark({ type: "pin", pageNumber: n, highlightRects: [], x_position: x, y_position: y });
      return;
    }
    drawing.current = { n, b, x, y, id: e.pointerId };
    e.currentTarget.setPointerCapture?.(e.pointerId);
    const start = { top_position: y, left_position: x, highlight_width: 0, highlight_height: 0 };
    if (adding) setDraft((d) => ({ ...d, highlightRects: [...d.highlightRects, start] }));
    else {
      setSelectedId(null);
      setDraft({ type: "box", pageNumber: n, highlightRects: [start], drawing: true });
    }
  };

  const onMarkMove = (e, n) => {
    if (mode === "shape") {
      const hit = shapeUnder(e, n);
      if ((hit?.id ?? null) !== (hoverShape?.n === n ? hoverShape.id : null)) setHoverShape(hit && { n, id: hit.id, rects: hit.rects });
      return;
    }
    const d0 = drawing.current;
    if (!d0 || e.pointerId !== d0.id) return;
    const x = clamp((e.clientX - d0.b.left) / d0.b.width);
    const y = clamp((e.clientY - d0.b.top) / d0.b.height);
    const rect = { top_position: Math.min(y, d0.y), left_position: Math.min(x, d0.x), highlight_width: Math.abs(x - d0.x), highlight_height: Math.abs(y - d0.y) };
    setDraft((d) => (d ? { ...d, highlightRects: [...d.highlightRects.slice(0, -1), rect] } : d));
  };

  const onMarkUp = (e) => {
    const d0 = drawing.current;
    if (!d0 || e.pointerId !== d0.id) return;
    drawing.current = null;
    const last = draft.highlightRects[draft.highlightRects.length - 1];
    const tooSmall = last.highlight_width < 0.02 || last.highlight_height < 0.015;
    if (adding) {
      if (tooSmall) { setDraft((d) => ({ ...d, highlightRects: d.highlightRects.slice(0, -1) })); notify("Drag across the area to draw the box.", { type: "info" }); return; }
      setAdding(false);
      setPopPos(null);
      return;
    }
    if (tooSmall) { setDraft(null); notify("Drag across the figure to draw a box around it.", { type: "info" }); return; }
    beginRemark({ ...draft, drawing: false });
  };

  const onMarkCancel = () => {
    if (!drawing.current) return;
    drawing.current = null;
    if (adding) setDraft((d) => ({ ...d, highlightRects: d.highlightRects.slice(0, -1) }));
    else setDraft(null);
  };

  const startFromSelection = () => {
    if (mode !== "text" || composingRef.current) return;
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
    beginRemark({ type: "text", pageNumber: Number(pageEl.dataset.page), selectedText, highlightRects: rects });
  };

  const startEdit = (id) => {
    const c = comments.find((x) => x.id === id);
    if (!c) return;
    setAdding(false);
    setDraft({
      type: c.type || "text",
      pageNumber: c.page_number,
      selectedText: c.selectedText,
      highlightRects: (c.highlightRects || []).map((r) => ({ ...r })),
      x_position: c.x_position,
      y_position: c.y_position,
      shape_ref: c.shape_ref || null,
    });
    setSelectedId(id);
    setEditingId(id);
    setKind(kindOf(c.color));
    setText(c.text || "");
    setSection(c.section || "");
    setFigure(c.figure_label || "");
    setDiagramType(c.diagram_type || "");
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
    const onFigure = draft.type !== "text";
    const figureFields = { figure_label: onFigure ? figure || null : null, diagram_type: onFigure ? diagramType || null : null };
    if (editing) {
      setComments((prev) => prev.map((c) => (c.id === editing.id
        ? { ...c, text: body, color: kind, section: sectionValue, ...figureFields, ...(draft.type === "box" || draft.type === "shape" ? { highlightRects: draft.highlightRects, shape_ref: draft.shape_ref ?? null } : {}) }
        : c)));
      setSelectedId(draft.type === "shape" ? null : editing.id);
    } else {
      const id = `new-${Date.now()}`;
      const first = draft.highlightRects[0];
      setComments((prev) => [...prev, {
        id,
        type: draft.type,
        page_number: draft.pageNumber,
        x_position: draft.type === "pin" ? draft.x_position : first.left_position,
        y_position: draft.type === "pin" ? draft.y_position : first.top_position,
        text: body,
        author: "Reviewer",
        timestamp: new Date().toISOString(),
        selectedText: onFigure ? null : draft.selectedText,
        section: sectionValue,
        highlightRects: draft.highlightRects,
        shape_ref: draft.type === "shape" ? draft.shape_ref : null,
        color: kind,
        ...figureFields,
      }]);
      // A shape remark lets go once added, so its outline is off the drawing.
      setSelectedId(draft.type === "shape" ? null : id);
    }
    closeBox();
  };

  // Place the box beside the words: in the right margin when it fits, and
  // otherwise over the edge of the page on the side away from the words.
  useLayoutEffect(() => {
    if (!composing || popPos) return;
    const canvas = col.canvasRef.current;
    const wrapper = col.pageRefs.current[draft.pageNumber];
    const pop = popRef.current;
    if (!canvas || !wrapper || !pop) return;
    // Beside the whole mark (every box, or every leg of an arrow), not just its first piece.
    const anchor = draft.highlightRects?.length ? boundsOf(draft.highlightRects)
      : { top_position: Number(draft.y_position) || 0, left_position: Number(draft.x_position) || 0.5, highlight_width: 0, highlight_height: 0 };
    const c = canvas.getBoundingClientRect();
    const p = (wrapper.querySelector(".react-pdf__Page") || wrapper).getBoundingClientRect();
    const w = pop.offsetWidth;
    const h = pop.offsetHeight;
    const gap = 16;
    const ay = p.top - c.top + canvas.scrollTop + (anchor.top_position + anchor.highlight_height / 2) * p.height;
    const aLeft = p.left - c.left + canvas.scrollLeft + anchor.left_position * p.width;
    const aMid = aLeft + (anchor.highlight_width * p.width) / 2;
    const pageMid = p.left - c.left + canvas.scrollLeft + p.width / 2;
    const aRight = aLeft + anchor.highlight_width * p.width;
    let left;
    let flip = false;
    // A picked shape gets the box right next to it, since a diagram fills the page and its margin is far away.
    if (draft.type === "shape" && aRight + gap + w + 8 <= canvas.scrollLeft + canvas.clientWidth) {
      left = aRight + gap;
    } else if (draft.type === "shape" && aLeft - gap - w >= canvas.scrollLeft + 8) {
      left = aLeft - gap - w;
      flip = true;
    } else if (c.right - p.right - gap >= w + 8) {
      left = p.right - c.left + canvas.scrollLeft + gap;
    } else if (aMid < pageMid) {
      left = canvas.scrollLeft + canvas.clientWidth - w - 12;
    } else {
      left = Math.max(canvas.scrollLeft + 12, aLeft - w - gap);
      flip = true;
    }
    // With no room beside them, the box goes under (or over) the words instead of on top of them.
    const aTop = p.top - c.top + canvas.scrollTop + anchor.top_position * p.height;
    const aBottom = aTop + anchor.highlight_height * p.height;
    const viewTop = canvas.scrollTop + 8;
    const viewBottom = canvas.scrollTop + canvas.clientHeight - 8;
    if (left < aRight + 4 && left + w > aLeft - 4) {
      const under = aBottom + gap + h <= viewBottom ? aBottom + gap : aTop - gap - h >= viewTop ? aTop - gap - h : null;
      if (under != null) {
        setPopPos({ left: Math.min(Math.max(aLeft, canvas.scrollLeft + 8), canvas.scrollLeft + canvas.clientWidth - w - 8), top: under, under: true });
        return;
      }
    }
    // Kept inside the visible part of the page, so it never opens below the screen.
    const top = Math.max(viewTop, Math.min(ay - 28, viewBottom - h));
    setPopPos({ left, top, ay: Math.min(Math.max(14, ay - top), h - 14), flip });
  });

  useEffect(() => { if (popPos) textRef.current?.focus({ preventScroll: true }); }, [popPos]);
  useEffect(() => { if (composingRef.current) setPopPos(null); setSelBtn(null); }, [col.pageWidth]);

  // The page is editable for keyboard users, and a drag left to the browser there jumps to
  // the end of the page once it passes the words. So a drag picks its two ends itself.
  const steerDrag = (e) => {
    const layer = e.currentTarget.querySelector(".textLayer");
    if (mode !== "text" || e.button !== 0 || e.detail > 1 || e.defaultPrevented || !layer?.contains(e.target)) return;
    const at = spotNear(layer, e.clientX, e.clientY);
    if (!at) return;
    e.preventDefault();
    const sel = window.getSelection();
    // Shift+click moves the far end of words already selected, so a selection can be fixed.
    const anchor = e.shiftKey && !sel.isCollapsed && layer.contains(sel.anchorNode) ? [sel.anchorNode, sel.anchorOffset] : at;
    sel.setBaseAndExtent(...anchor, ...at);
    setSelBtn(null);
    const follow = (m) => {
      const focus = spotNear(layer, m.clientX, m.clientY);
      if (focus) sel.setBaseAndExtent(...anchor, ...focus);
    };
    window.addEventListener("mousemove", follow);
    window.addEventListener("mouseup", () => { window.removeEventListener("mousemove", follow); placeSelBtn(); }, { once: true });
  };

  // Letting go of the mouse leaves the words selected; a bar over them starts
  // the remark with its kind already picked (or Enter, with the last kind used).
  const placeSelBtn = () => {
    const sel = window.getSelection();
    const canvas = col.canvasRef.current;
    const node = sel?.anchorNode;
    const el = node && (node.nodeType === 1 ? node : node.parentElement);
    const rects = sel && !sel.isCollapsed && sel.rangeCount ? [...sel.getRangeAt(0).getClientRects()].filter((r) => r.width > 1 && r.height > 1) : [];
    const last = rects[rects.length - 1];
    if (mode !== "text" || composingRef.current || !canvas || !last || !el?.closest(".rr-page")) return setSelBtn(null);
    const c = canvas.getBoundingClientRect();
    const middle = (Math.min(...rects.map((r) => r.left)) + Math.max(...rects.map((r) => r.right))) / 2 - c.left + canvas.scrollLeft;
    const left = Math.min(Math.max(middle, SELBAR_HALF + 8), Math.max(SELBAR_HALF + 8, canvas.scrollWidth - SELBAR_HALF - 8));
    // Above the words, unless that would put it off the top of the view: then under them.
    const above = rects[0].top - c.top + canvas.scrollTop - 10;
    const below = above - canvas.scrollTop < 52;
    setSelBtn({ left, top: below ? last.bottom - c.top + canvas.scrollTop + 10 : above, below });
  };

  const onPageKey = (e) => {
    if (NAV_KEYS.has(e.key)) return;
    if ((e.ctrlKey || e.metaKey) && ["c", "a"].includes(e.key.toLowerCase())) return;
    e.preventDefault();
    if (e.key === "Enter") startFromSelection();
  };

  const quote = draft?.type === "text" ? draft.selectedText : null;
  const onFigure = draft && draft.type !== "text";
  const draftRemark = draft && { ...draft, page_number: draft.pageNumber, color: kind };
  const where = draft ? (draft.type === "shape" ? `${shapeLabel(draft.shape_ref)} · page ${draft.pageNumber}` : draft.type === "pin" ? `Pin on page ${draft.pageNumber}` : `${draft.highlightRects.length > 1 ? `${draft.highlightRects.length} boxes` : "Box"} on page ${draft.pageNumber}`) : "";
  const zoomRemark = zoomId != null ? comments.find((c) => c.id === zoomId) : null;
  const hint = mode === "text"
    ? (selecting ? "Pick a kind above the words, or press Enter · Shift+click changes where it ends" : "Select text to add a remark")
    : adding ? `Draw another box on page ${draft?.pageNumber} · Esc when done` : HINTS[mode];

  return (
    <div className="rr-work">
      <PageStrip numPages={totalPages} currentPage={col.currentPage} counts={counts} onGo={col.goToPage} firstDiagramPage={firstDiagramPage} />

      <section className="rr-viewer" aria-label="Document">
        <div className="rr-hint">{showChanges && figureDiff.status === "loading" ? figureDiff.note || "Comparing the figures…" : hint}</div>
        <div
          className="rr-canvas"
          ref={col.canvasRef}
          onScroll={col.onScroll}
          onMouseDown={(e) => {
            if (composingRef.current && !e.target.closest(".rr-pop") && !e.target.closest(".rr-page")) clickAway();
          }}
        >
          {error && <div className="rr-error">Could not load the document: {error}</div>}
          {(pdfUrl || diagrams) && (
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
                // This version's remarks are not drawn on the earlier version's drawing.
                const before = Boolean(beforeView && drawing?.oldMarked);
                // A shape remark is outlined from its cells on the drawing, not from the outline it was saved with.
                const pic = drawing && shownPicture(drawing, showChanges);
                const outline = (c) => c.shape_ref.cells.flatMap((cell) => { const t = pic.targets.find((x) => x.id === cell.id); return t ? shapeRects(t, pic) : []; });
                const pageRemarks = before ? [] : comments.filter((c) => Number(c.page_number) === n)
                  .map((c) => (pic && c.type === "shape" && c.shape_ref && outline(c).length ? { ...c, highlightRects: outline(c) } : c));
                return (
                  <div
                    key={n}
                    ref={(el) => { col.pageRefs.current[n] = el; }}
                    data-page={n}
                    className={`rr-page${mode !== "text" ? " marking" : ""}${mode === "pin" ? " pinning" : ""}${mode === "shape" && drawing ? " shaping" : ""}${n === flashPage ? " flash" : ""}`}
                    onAnimationEnd={(e) => { if (e.animationName === "rr-flash") setFlashPage(null); }}
                    style={{ width: col.pageWidth, minHeight: drawing || rendered.has(n) ? undefined : Math.round((col.pageWidth * 11) / 8.5) }}
                    onPointerDown={(e) => onMarkDown(e, n)}
                    onPointerMove={(e) => onMarkMove(e, n)}
                    onPointerLeave={() => setHoverShape(null)}
                    onPointerUp={onMarkUp}
                    onPointerCancel={onMarkCancel}
                    contentEditable={mode === "text"}
                    suppressContentEditableWarning
                    spellCheck={false}
                    aria-readonly="true"
                    aria-label={`Page ${n}`}
                    onKeyDown={onPageKey}
                    onBeforeInput={block}
                    onPaste={block}
                    onCut={block}
                    onDrop={block}
                    onMouseDown={steerDrag}
                    onMouseUp={() => setTimeout(placeSelBtn, 0)}
                    onKeyUp={placeSelBtn}
                  >
                    {drawing && <DiagramPage page={drawing} showChanges={showChanges} before={beforeView} />}
                    {!drawing && col.visible.has(n) && (
                      <Page
                        pageNumber={n}
                        width={col.pageWidth}
                        renderTextLayer
                        renderAnnotationLayer={false}
                        loading=""
                        onRenderSuccess={() => setRendered((prev) => (prev.has(n) ? prev : new Set(prev).add(n)))}
                      />
                    )}
                    {/* On the page itself, not in the overlay, so it can recolour the page's own lines. */}
                    {showChanges && (figureTints.now[n] || []).map((t) => (
                      <img key={t.id} className={`rr-figover${t.wash ? " wash" : ""}`} src={t.src} alt="" contentEditable={false} style={rectStyle(t.rects[0])} />
                    ))}
                    <div className={`rr-overlay${before || (drawing && showChanges && drawing.marked) ? " ink" : ""}`} contentEditable={false}>
                      {shownShape?.page === n && Boolean(shownShape.before) === before && shownShape.rects.map((r, idx) => (
                        <div key={`s${idx}`} className={`rr-box shown c-${kindOf(shownShape.color)}`} style={rectStyle(r)} />
                      ))}
                      {(marks[n] || []).map((m) => m.rects.map((r, idx) => (
                        <div key={`d${m.id}-${idx}`} className={`rr-hl rr-diff${m.removed ? " del" : ""}${changeId === m.id ? " on" : ""}`} style={rectStyle(r)} />
                      )))}
                      {showChanges && (figureBoxes.marks[n] || []).map((m) => m.rects.map((r, idx) => (m.diff
                        ? <div key={`${m.id}-${idx}`} className={`rr-hl rr-diff${m.removed ? " del" : ""}`} style={rectStyle(r)} />
                        : <div key={`${m.id}-${idx}`} className={`rr-figdiff ${m.tone || ""}`} data-label={m.label || undefined} style={rectStyle(r)} />
                      )))}
                      <Highlights
                        remarks={pageRemarks}
                        numbers={numbers}
                        selectedId={selectedId}
                        onSelect={openRemark}
                        override={editing ? { id: editing.id, kind, rects: draft?.type === "box" || draft?.type === "shape" ? draft.highlightRects : null } : null}
                      />
                      {draft && !editing && draft.pageNumber === n && draft.type === "text" && draft.highlightRects.map((r, idx) => (
                        <div key={idx} className={`rr-hl pending c-${kind}`} style={rectStyle(r)} />
                      ))}
                      {mode === "shape" && hoverShape?.n === n && !(draft?.type === "shape" && draft.shape_ref.cells.some((c) => c.id === hoverShape.id)) && (
                        hoverShape.rects.map((r, idx) => <div key={`h${idx}`} className="rr-box hover" style={rectStyle(r)} />)
                      )}
                      {draft && !editing && draft.pageNumber === n && (draft.type === "box" || draft.type === "shape") && draft.highlightRects.map((r, idx) => (
                        <div key={idx} className="rr-box pending" style={rectStyle(r)} />
                      ))}
                      {draft && !editing && draft.pageNumber === n && draft.type === "pin" && (
                        <div className="rr-pin pending" style={{ top: `${draft.y_position * 100}%`, left: `${draft.x_position * 100}%` }}><span /></div>
                      )}
                      {draft?.drawing && draft.pageNumber === n && (() => {
                        const r = draft.highlightRects[draft.highlightRects.length - 1];
                        return <div className="rr-size" style={{ top: `${(r.top_position + r.highlight_height) * 100}%`, left: `${(r.left_position + r.highlight_width) * 100}%` }}>{Math.round(r.highlight_width * 100)}% × {Math.round(r.highlight_height * 100)}% of the page</div>;
                      })()}
                    </div>
                  </div>
                );
              })}
            </Holder>
          )}

          {selBtn && !composing && (
            <div
              className={`rr-selbar${selBtn.below ? " below" : ""}`}
              role="toolbar"
              aria-label="Add a remark"
              style={{ left: selBtn.left, top: selBtn.top }}
              onMouseDown={block}
            >
              <span className="rr-selbar-lbl">Add remark</span>
              {SELECTION_KINDS.map((k) => (
                <button key={k.id} type="button" className="rr-kind" onClick={() => { setKind(k.id); startFromSelection(); }}>
                  <span className={`rr-sw c-${k.id}`} />{k.label}
                </button>
              ))}
            </div>
          )}

          {composing && (
            <div
              ref={popRef}
              className={`rr-pop${popPos?.flip ? " flip" : ""}${popPos?.under ? " under" : ""}${onFigure ? " fig" : ""}`}
              role="dialog"
              aria-label={editing ? "Edit remark" : "Add a remark"}
              style={{ left: popPos?.left ?? 0, top: popPos?.top ?? 0, visibility: popPos ? "visible" : "hidden", "--ay": `${popPos?.ay ?? 24}px` }}
              onKeyDown={(e) => { if (e.key === "Escape") { e.stopPropagation(); closeBox(); } }}
            >
              {quote && <div className="rr-pop-quote">“{quote}”</div>}
              {onFigure && (
                <div className="rr-pop-fig">
                  <span className="rr-pop-thumb">{pagesPdf && <FigureCrop pdf={pagesPdf} remark={draftRemark} maxWidth={64} maxHeight={46} pending />}</span>
                  <div><b>{figure || where}</b>{figure && <small>{where}</small>}</div>
                </div>
              )}
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
              <SectionPicker value={section} onChange={setSection} />
              <div className="rr-row">
                {draft.type === "box"
                  ? <button type="button" className="rr-btn sm ghost" onClick={() => { setAdding(true); changeMode("box"); }}>+ Box</button>
                  : <span className="rr-muted">{draft.type === "shape" ? "Shift+click adds a shape" : "Ctrl+Enter to add"}</span>}
                <span className="rr-spacer" />
                <button type="button" className="rr-btn sm" onClick={closeBox}>Cancel</button>
                <button type="button" className="rr-btn sm tint" onClick={saveBox}>{editing ? "Save remark" : "Add remark"}</button>
              </div>
            </div>
          )}
        </div>
        <ReaderTools currentPage={col.currentPage} numPages={totalPages} zoom={col.zoom} onGo={col.goToPage} onZoom={col.setZoom} mode={mode} onMode={changeMode} shapes={drawings.pages.length > 0}
          before={hasBefore ? beforeView : null} beforeLabel={base?.round || "Earlier version"} onBefore={() => setBeforeView((v) => !v)} />
      </section>

      {base && tab === "changes" ? (
        <ChangesPanel
          top={tabs}
          base={base}
          diff={diff}
          selectedChange={changeId}
          onOpenChange={openChange}
          onOpenRemark={openOldRemark}
          pdf={pdf}
          figureDiff={figureDiff}
          onWantFigures={() => setFiguresWanted(true)}
          revisionStatus={revisionStatus}
          onRevision={onRevision}
          diagramPages={drawings.pages}
          firstDiagramPage={(numPages ?? 0) + 1}
          shownShapeId={shownShape?.id}
          onOpenRevision={(r) => {
            // A card outlines its remark where it is now, and goes there; clicking it again hides it.
            const hide = shownShape?.id === r.id;
            const spot = r.target.rects?.length ? r.target.rects : [{ top_position: r.target.y - 0.012, left_position: r.target.x - 0.012, highlight_width: 0.024, highlight_height: 0.024 }];
            setShownShape(hide ? null : { id: r.id, ...r.target, rects: spot, color: r.color });
            setBeforeView(!hide && Boolean(r.target.before));
            if (hide) return;
            col.goToRect(r.target.page, r.target.rects[0] || { top_position: r.target.y });
            onFocusChange?.({ id: `r${r.id}`, oldPage: r.page_number, oldRect: r.highlightRects[0] || { top_position: r.y_position } });
          }}
        />
      ) : tab === "figures" ? (
        <FiguresPanel
          top={tabs}
          attached={firstDiagramPage && <AttachedDiagrams pages={drawings.pages} firstPage={firstDiagramPage} onOpen={openDiagram} />}
          figures={figures}
          comments={comments}
          checked={checkedFigures}
          onToggle={onToggleFigure}
          onGo={(f) => col.goToRect(f.page, { top_position: Math.max(0, f.y - 0.25) })}
        />
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
        pdf={pagesPdf}
        onZoom={setZoomId}
      />
      )}
      <FigureZoom pdf={pagesPdf} remark={zoomRemark} number={zoomRemark ? numbers[zoomRemark.id] : null} onClose={() => setZoomId(null)} />
    </div>
  );
}

export default PDFChecker;
