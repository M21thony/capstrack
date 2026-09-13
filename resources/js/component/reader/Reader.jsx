import React, { useEffect, useLayoutEffect, useRef, useState } from "react";

/*
 * The pieces the reviewer's annotator and the student's Review Viewer share:
 * the labelled remark kinds, the highlights drawn over a page, the remarks
 * panel, and a list that pages itself to fit instead of scrolling.
 */

// Each highlight colour is a kind of remark, so colour is never the only way
// to tell them apart. The ids are the colours already stored on pdf_remarks.
export const KINDS = [
  { id: "pink", label: "Must fix" },
  { id: "orange", label: "Format" },
  { id: "yellow", label: "Suggestion" },
  { id: "blue", label: "Question" },
  { id: "green", label: "Good" },
];
export const KIND_LABEL = Object.fromEntries(KINDS.map((k) => [k.id, k.label]));
export const kindOf = (color) => (KIND_LABEL[color] ? color : "yellow");

/** Numbered per page, in the order they were made - the numbers the exported PDF prints. */
export function numberRemarks(remarks) {
  const perPage = {};
  const numbers = {};
  remarks.forEach((r) => {
    perPage[r.page_number] = (perPage[r.page_number] || 0) + 1;
    numbers[r.id] = perPage[r.page_number];
  });
  return numbers;
}

export const mergeRectsByLine = (rects) => {
  if (!rects.length) return [];
  const sorted = [...rects].sort((a, b) => a.top_position - b.top_position);
  const merged = [];
  let current = { ...sorted[0] };
  for (let i = 1; i < sorted.length; i++) {
    const r = sorted[i];
    if (Math.abs(r.top_position - current.top_position) < 0.01) {
      const left = Math.min(current.left_position, r.left_position);
      const right = Math.max(current.left_position + current.highlight_width, r.left_position + r.highlight_width);
      const bottom = Math.max(current.top_position + current.highlight_height, r.top_position + r.highlight_height);
      current.left_position = left;
      current.highlight_width = right - left;
      current.highlight_height = bottom - current.top_position;
    } else {
      merged.push(current);
      current = { ...r };
    }
  }
  merged.push(current);
  return merged;
};

const pct = (n) => `${(Number(n) || 0) * 100}%`;
export const rectStyle = (r) => ({
  top: pct(r.top_position),
  left: pct(r.left_position),
  width: pct(r.highlight_width),
  height: pct(r.highlight_height),
});

const PATHS = {
  left: <polyline points="15 18 9 12 15 6" />,
  right: <polyline points="9 18 15 12 9 6" />,
  up: <polyline points="18 15 12 9 6 15" />,
  down: <polyline points="6 9 12 15 18 9" />,
  minus: <line x1="5" y1="12" x2="19" y2="12" />,
  plus: (<><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></>),
  fit: (<><polyline points="15 3 21 3 21 9" /><polyline points="9 21 3 21 3 15" /><line x1="21" y1="3" x2="14" y2="10" /><line x1="3" y1="21" x2="10" y2="14" /></>),
  x: (<><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></>),
  edit: (<><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></>),
  download: (<><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></>),
};
export function Icon({ name }) {
  return (
    <svg className="rr-icon" viewBox="0 0 24 24" aria-hidden="true">{PATHS[name]}</svg>
  );
}

/** A page's remarks: the highlighted words, and their number in the margin beside them. */
export function Highlights({ remarks, numbers, selectedId, onSelect, clickable = false, override = null }) {
  return remarks.map((r) => {
    const rects = r.highlightRects || [];
    if (!rects.length) return null;
    const first = rects[0];
    const kind = override && override.id === r.id ? override.kind : kindOf(r.color);
    const on = selectedId === r.id;
    return (
      <React.Fragment key={r.id}>
        {rects.map((rect, i) => (
          <div
            key={i}
            className={`rr-hl c-${kind}${on ? " on" : ""}${clickable ? " click" : ""}`}
            style={rectStyle(rect)}
            onClick={clickable ? (e) => { e.stopPropagation(); onSelect(r.id); } : undefined}
          />
        ))}
        <button
          type="button"
          className={`rr-num${on ? " on" : ""}`}
          style={{ top: pct(Number(first.top_position) + Number(first.highlight_height) / 2) }}
          onClick={(e) => { e.stopPropagation(); onSelect(r.id); }}
          aria-label={`Remark ${numbers[r.id]}: ${r.text}`}
          title={`${KIND_LABEL[kind]}: ${r.text}`}
        >
          {numbers[r.id]}
        </button>
      </React.Fragment>
    );
  });
}

/**
 * Shows as many items as fit, a page at a time. Items are measured as they
 * really render, so a long remark is never cut off at the bottom.
 */
export function PagedList({ items, keyOf, renderItem, noun, empty, focusKey }) {
  const holderRef = useRef(null);
  const measureRef = useRef(null);
  const [starts, setStarts] = useState([0]);
  const [page, setPage] = useState(0);
  const [size, setSize] = useState("");

  useLayoutEffect(() => {
    const el = holderRef.current;
    if (!el) return undefined;
    const observer = new ResizeObserver(() => setSize(`${el.clientWidth}x${el.clientHeight}`));
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  useLayoutEffect(() => {
    const holder = holderRef.current;
    const measure = measureRef.current;
    if (!holder || !measure) return;
    const max = holder.clientHeight;
    const gap = 6;
    const next = [0];
    let used = 0;
    [...measure.children].forEach((child, i) => {
      const h = child.getBoundingClientRect().height;
      if (used && used + gap + h > max) {
        next.push(i);
        used = h;
      } else {
        used += (used ? gap : 0) + h;
      }
    });
    setStarts((prev) => (prev.join() === next.join() ? prev : next));
  }, [items, size]);

  useEffect(() => {
    if (focusKey == null) return;
    const i = items.findIndex((x) => keyOf(x) === focusKey);
    if (i >= 0) setPage(starts.filter((s) => s <= i).length - 1);
  }, [focusKey, starts]); // eslint-disable-line react-hooks/exhaustive-deps

  const count = starts.length;
  const p = Math.min(page, count - 1);
  const shown = items.slice(starts[p], starts[p + 1] ?? items.length);

  return (
    <>
      <div className="rr-holder" ref={holderRef}>
        {items.length ? shown.map((x) => <React.Fragment key={keyOf(x)}>{renderItem(x)}</React.Fragment>) : <div className="rr-empty">{empty}</div>}
        <div className="rr-measure" ref={measureRef} aria-hidden="true">
          {items.map((x) => <React.Fragment key={keyOf(x)}>{renderItem(x, true)}</React.Fragment>)}
        </div>
      </div>
      <div className="rr-foot">
        <span>{items.length} {items.length === 1 ? noun : `${noun}s`}</span>
        {count > 1 && (
          <>
            <span className="rr-spacer" />
            <span>{p + 1} / {count}</span>
            <button type="button" className="rr-btn icon sm ghost" aria-label="Previous" disabled={p === 0} onClick={() => setPage(p - 1)}><Icon name="left" /></button>
            <button type="button" className="rr-btn icon sm ghost" aria-label="Next" disabled={p === count - 1} onClick={() => setPage(p + 1)}><Icon name="right" /></button>
          </>
        )}
      </div>
    </>
  );
}

function RemarkCard({ r, n, selected, readOnly, onOpen, onEdit, onDelete, measuring }) {
  const kind = kindOf(r.color);
  const open = () => onOpen(r.id);
  return (
    <div
      className="rr-card text"
      role={measuring ? undefined : "button"}
      tabIndex={measuring ? -1 : 0}
      aria-current={selected}
      aria-label={`Remark ${n} on page ${r.page_number}, ${KIND_LABEL[kind]}: ${r.text}`}
      onClick={open}
      onKeyDown={(e) => {
        if (e.target !== e.currentTarget) return;
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(); }
      }}
    >
      <div className="rr-card-r1">
        <span className="rr-no">{n}</span>
        <span className="rr-tag">Highlighted text</span>
        <span className="rr-where">p.{r.page_number}{r.section ? ` · ${r.section}` : ""}</span>
        {!readOnly && (
          <span className="rr-acts">
            <button type="button" className="rr-btn icon sm ghost" tabIndex={measuring ? -1 : 0} aria-label="Edit remark" onClick={(e) => { e.stopPropagation(); onEdit(r.id); }}><Icon name="edit" /></button>
            <button type="button" className="rr-btn icon sm ghost" tabIndex={measuring ? -1 : 0} aria-label="Delete remark" onClick={(e) => { e.stopPropagation(); onDelete(r.id); }}><Icon name="x" /></button>
          </span>
        )}
      </div>
      {r.selectedText && <div className="rr-q">“{r.selectedText}”</div>}
      <div className="rr-t">{r.text}</div>
      <span className="rr-lbl"><span className={`rr-sw c-${kind}`} />{KIND_LABEL[kind]}</span>
    </div>
  );
}

function GeneralCard({ g, readOnly, onDelete, measuring }) {
  return (
    <div className="rr-card gen">
      <div className="rr-card-r1">
        <span className="rr-tag gen">General</span>
        <span className="rr-where">{g.section || "Whole document"}</span>
        {!readOnly && (
          <span className="rr-acts">
            <button type="button" className="rr-btn icon sm ghost" tabIndex={measuring ? -1 : 0} aria-label="Delete general remark" onClick={() => onDelete(g.id)}><Icon name="x" /></button>
          </span>
        )}
      </div>
      <div className="rr-t">{g.text}</div>
    </div>
  );
}

/**
 * One list of every remark. Each card says what it is: on highlighted text
 * (with the words it points to) or general (about the whole document).
 */
export function RemarksPanel({
  title, remarks, general, numbers, selectedId, onOpen, readOnly = false,
  onEdit, onDelete, onAddGeneral, onDeleteGeneral, top = null, empty = null,
}) {
  const [filter, setFilter] = useState("all");
  const [writing, setWriting] = useState(false);
  const [gText, setGText] = useState("");
  const [gSection, setGSection] = useState("");
  const textRef = useRef(null);
  const total = remarks.length + general.length;

  useEffect(() => { if (writing) textRef.current?.focus(); }, [writing]);

  const items = [
    ...(filter !== "text" ? general.map((g) => ({ type: "general", key: `g${g.id}`, g })) : []),
    ...(filter !== "general"
      ? [...remarks]
          .sort((a, b) => Number(a.page_number) - Number(b.page_number) || numbers[a.id] - numbers[b.id])
          .map((r) => ({ type: "text", key: `r${r.id}`, r }))
      : []),
  ];

  const addGeneral = () => {
    if (!gText.trim()) { textRef.current?.focus(); return; }
    onAddGeneral(gText.trim(), gSection.trim());
    setGText(""); setGSection(""); setWriting(false);
  };

  const emptyText = {
    all: readOnly ? ["No remarks", "Your reviewer left no remarks on this document."] : ["No remarks yet", "Select text on a page to comment on it, or add a general remark about the whole document."],
    text: ["No highlighted text", readOnly ? "Nothing on this document was highlighted." : "Select words on a page — with the mouse, or Shift and the arrow keys then Enter."],
    general: ["No general remarks", readOnly ? "Your reviewer left no general remarks." : "Use + General remark for comments about the whole document."],
  }[filter];

  return (
    <aside className="rr-side" aria-label="Remarks">
      {top}
      <div className="rr-side-head">
        <h2>{title} <span className="rr-count">{total}</span></h2>
        <span className="rr-spacer" />
        {!readOnly && (
          <button type="button" className="rr-btn sm" onClick={() => { setWriting(true); if (filter === "text") setFilter("all"); }}>+ General remark</button>
        )}
      </div>
      {!readOnly && (
        <p className="rr-help">Select text on a page to comment on those words. Use <b>General remark</b> for comments about the whole document.</p>
      )}
      <div className="rr-side-body">
        <div className="rr-filter" role="group" aria-label="Show">
          {[["all", "All", total], ["text", "Highlighted text", remarks.length], ["general", "General", general.length]].map(([k, label, n]) => (
            <button key={k} type="button" className="rr-fbtn" aria-pressed={filter === k} onClick={() => setFilter(k)}>
              {label} <span className="rr-fc">{n}</span>
            </button>
          ))}
        </div>
        {writing && (
          <div className="rr-composer">
            <div className="rr-ctitle"><span className="rr-tag gen">General</span>About the whole document</div>
            <input className="rr-input" placeholder="Section (optional), e.g. Whole chapter" aria-label="Section" value={gSection} onChange={(e) => setGSection(e.target.value)} />
            <textarea
              ref={textRef}
              className="rr-input"
              placeholder="e.g. Add at least three local studies; the citation format is inconsistent throughout"
              aria-label="General remark"
              value={gText}
              onChange={(e) => setGText(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) { e.preventDefault(); addGeneral(); }
                if (e.key === "Escape") setWriting(false);
              }}
            />
            <div className="rr-row">
              <span className="rr-muted">Ctrl+Enter to add</span>
              <span className="rr-spacer" />
              <button type="button" className="rr-btn sm" onClick={() => setWriting(false)}>Cancel</button>
              <button type="button" className="rr-btn sm tint" onClick={addGeneral}>Add remark</button>
            </div>
          </div>
        )}
        <PagedList
          items={items}
          keyOf={(x) => x.key}
          noun="remark"
          focusKey={selectedId != null ? `r${selectedId}` : null}
          empty={empty || (<><b>{emptyText[0]}</b><span>{emptyText[1]}</span></>)}
          renderItem={(x, measuring) =>
            x.type === "general" ? (
              <GeneralCard g={x.g} readOnly={readOnly} onDelete={onDeleteGeneral} measuring={measuring} />
            ) : (
              <RemarkCard
                r={x.r}
                n={numbers[x.r.id]}
                selected={selectedId === x.r.id}
                readOnly={readOnly}
                onOpen={onOpen}
                onEdit={onEdit}
                onDelete={onDelete}
                measuring={measuring}
              />
            )
          }
        />
      </div>
    </aside>
  );
}

/** The page strip: one small page per page, with how many remarks are on it. */
export function PageStrip({ numPages, currentPage, counts, onGo }) {
  if (!numPages) return <nav className="rr-strip" aria-label="Pages" />;
  return (
    <nav className="rr-strip" aria-label="Pages">
      {Array.from({ length: numPages }, (_, i) => {
        const n = i + 1;
        const c = counts[n] || 0;
        return (
          <button
            key={n}
            type="button"
            className="rr-thumb"
            aria-current={n === currentPage}
            aria-label={`Page ${n}${c ? `, ${c} remark${c === 1 ? "" : "s"}` : ""}`}
            onClick={() => onGo(n)}
          >
            <span className="rr-lines" aria-hidden="true">
              {[62, 88, 80, 94, 70, 86, 76, 90].map((w, k) => <i key={k} style={{ width: `${w}%` }} />)}
            </span>
            <span className="rr-pn">{n}</span>
            {c > 0 && <span className="rr-rc">{c}</span>}
          </button>
        );
      })}
    </nav>
  );
}

/** Page and zoom controls, floating over the bottom of the document. */
export function ReaderTools({ currentPage, numPages, zoom, onGo, onZoom }) {
  return (
    <div className="rr-tools" role="toolbar" aria-label="Pages">
      <button type="button" className="rr-btn icon ghost" aria-label="Previous page" disabled={currentPage <= 1} onClick={() => onGo(currentPage - 1)}><Icon name="up" /></button>
      <span className="rr-pg">{numPages ? `${currentPage} / ${numPages}` : "–"}</span>
      <button type="button" className="rr-btn icon ghost" aria-label="Next page" disabled={!numPages || currentPage >= numPages} onClick={() => onGo(currentPage + 1)}><Icon name="down" /></button>
      <span className="rr-sep" aria-hidden="true" />
      <button type="button" className="rr-btn icon ghost" aria-label="Zoom out" disabled={zoom <= 0.5} onClick={() => onZoom(Math.max(0.5, +(zoom - 0.25).toFixed(2)))}><Icon name="minus" /></button>
      <span className="rr-pg">{Math.round(zoom * 100)}%</span>
      <button type="button" className="rr-btn icon ghost" aria-label="Zoom in" disabled={zoom >= 2} onClick={() => onZoom(Math.min(2, +(zoom + 0.25).toFixed(2)))}><Icon name="plus" /></button>
      <button type="button" className="rr-btn icon ghost" aria-label="Fit page width" onClick={() => onZoom(1)}><Icon name="fit" /></button>
    </div>
  );
}

/**
 * The scrolling column of pages both screens use: page width follows the
 * space available, only pages near the view are drawn, and the page in view
 * is reported as the reader scrolls.
 */
export function usePageColumn(numPages) {
  const canvasRef = useRef(null);
  const pageRefs = useRef({});
  const [baseWidth, setBaseWidth] = useState(760);
  const [zoom, setZoomState] = useState(1);
  const [visible, setVisible] = useState(() => new Set([1, 2, 3]));
  const [currentPage, setCurrentPage] = useState(1);
  const keepPage = useRef(null);

  useLayoutEffect(() => {
    const el = canvasRef.current;
    if (!el) return undefined;
    const observer = new ResizeObserver(() => setBaseWidth(Math.min(900, Math.max(360, el.clientWidth - 48))));
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    const root = canvasRef.current;
    if (!root || !numPages) return undefined;
    const observer = new IntersectionObserver((entries) => {
      setVisible((prev) => {
        const next = new Set(prev);
        entries.forEach((e) => {
          const n = Number(e.target.dataset.page);
          if (e.isIntersecting) next.add(n); else next.delete(n);
        });
        return next;
      });
    }, { root, rootMargin: "1600px 0px" });
    Object.values(pageRefs.current).forEach((el) => el && observer.observe(el));
    return () => observer.disconnect();
  }, [numPages, baseWidth, zoom]);

  const onScroll = () => {
    const root = canvasRef.current;
    if (!root) return;
    const line = root.scrollTop + root.clientHeight / 3;
    let current = 1;
    Object.entries(pageRefs.current).forEach(([n, el]) => { if (el && el.offsetTop <= line) current = Math.max(current, Number(n)); });
    setCurrentPage(current);
  };

  const goToPage = (n, smooth = true) => {
    const el = pageRefs.current[n];
    if (el) canvasRef.current?.scrollTo({ top: el.offsetTop - 20, behavior: smooth ? "smooth" : "auto" });
  };

  const goToRect = (n, rect, smooth = true) => {
    const el = pageRefs.current[n];
    const root = canvasRef.current;
    if (!el || !root) return;
    const top = el.offsetTop + Number(rect?.top_position || 0) * el.offsetHeight - root.clientHeight / 3;
    root.scrollTo({ top, behavior: smooth ? "smooth" : "auto" });
  };

  const setZoom = (z) => { keepPage.current = currentPage; setZoomState(z); };
  useLayoutEffect(() => {
    if (keepPage.current != null) { goToPage(keepPage.current, false); keepPage.current = null; }
  }, [zoom]); // eslint-disable-line react-hooks/exhaustive-deps

  return { canvasRef, pageRefs, pageWidth: Math.round(baseWidth * zoom), zoom, setZoom, visible, currentPage, onScroll, goToPage, goToRect };
}

export function Toast({ toast, onClose }) {
  if (!toast) return null;
  return (
    <div className={`rr-toast${toast.type === "error" ? " error" : ""}`} role="status">
      <span>{toast.message}</span>
      {toast.undo && (
        <button type="button" className="rr-btn sm" onClick={() => { toast.undo(); onClose(); }}>Undo</button>
      )}
    </div>
  );
}
