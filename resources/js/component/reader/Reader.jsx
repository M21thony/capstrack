import React, { useEffect, useLayoutEffect, useRef, useState } from "react";
import FigureCrop from "./FigureCrop";
import { DIAGRAM_LABEL, isFigureRemark, shapeLabel } from "./figures";
import { readOutline } from "./outline.js";

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

export { mergeRectsByLine } from "./rects.js";

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
  text: (<><polyline points="4 7 4 4 20 4 20 7" /><line x1="9" y1="20" x2="15" y2="20" /><line x1="12" y1="4" x2="12" y2="20" /></>),
  box: <rect x="4" y="5" width="16" height="14" rx="1" strokeDasharray="3 2.5" />,
  pin: (<><path d="M12 21s-6-5.6-6-11a6 6 0 0 1 12 0c0 5.4-6 11-6 11z" /><circle cx="12" cy="10" r="2" /></>),
  shape: (<><rect x="3" y="4" width="8" height="6" rx="1" /><rect x="13" y="14" width="8" height="6" rx="1" /><path d="M7 10v7h6" /></>),
  hand: (<><path d="M12 3v18" /><path d="M3 12h18" /><polyline points="9 6 12 3 15 6" /><polyline points="9 18 12 21 15 18" /><polyline points="6 9 3 12 6 15" /><polyline points="18 9 21 12 18 15" /></>),
  pages: (<><rect x="5" y="3" width="14" height="18" rx="1.5" /><line x1="9" y1="8" x2="15" y2="8" /><line x1="9" y1="12" x2="15" y2="12" /></>),
  list: (<><line x1="9" y1="6" x2="20" y2="6" /><line x1="9" y1="12" x2="20" y2="12" /><line x1="9" y1="18" x2="20" y2="18" /><line x1="4" y1="6" x2="4.1" y2="6" /><line x1="4" y1="12" x2="4.1" y2="12" /><line x1="4" y1="18" x2="4.1" y2="18" /></>),
};
export function Icon({ name }) {
  return (
    <svg className="rr-icon" viewBox="0 0 24 24" aria-hidden="true">{PATHS[name]}</svg>
  );
}

/**
 * What a remark on a figure wears instead of a number: an icon for its kind,
 * so the kinds differ by shape as well as by colour.
 */
const KIND_ICON = {
  pink: (<><line x1="12" y1="6" x2="12" y2="13.5" /><line x1="12" y1="17.5" x2="12" y2="17.6" /></>),
  yellow: (<><path d="M12 4.5 20.5 19h-17Z" /><line x1="12" y1="10" x2="12" y2="13.5" /><line x1="12" y1="16.3" x2="12" y2="16.4" /></>),
  blue: <path d="M5 6h14v9h-8l-4 3.5V15H5Z" />,
  orange: (<><polyline points="6 18 10.5 6 15 18" /><line x1="7.6" y1="14" x2="13.4" y2="14" /><line x1="16.5" y1="18" x2="19" y2="18" /></>),
  green: <polyline points="5.5 12.5 10 17 18.5 7.5" />,
};
export function KindMark({ kind, inline = false }) {
  return (
    <span className={`rr-mark${inline ? " inline" : ""} c-${kind}`} aria-hidden="true">
      <svg viewBox="0 0 24 24">{KIND_ICON[kind] || KIND_ICON.yellow}</svg>
    </span>
  );
}

/**
 * How much of the drawing sits under each area, read from the page as drawn.
 * An area is a rectangle (x, y, w, h in pixels) off a point on the page (top,
 * left as fractions of it). One running off the page counts as full.
 */
function inkAt(surface, overlay, areas) {
  const { shown, canvas } = surface;
  const c = shown.getBoundingClientRect();
  const o = overlay.getBoundingClientRect();
  if (!c.width || !canvas.width) return null;
  let ctx;
  try { ctx = canvas.getContext("2d", { willReadFrequently: true }); } catch { return null; }
  if (!ctx) return null;
  const scale = canvas.width / c.width;
  return areas.map(({ top, left, x, y, w, h }) => {
    const x0 = o.left + left * o.width + x - c.left;
    const y0 = o.top + top * o.height + y - c.top;
    if (x0 < 0 || y0 < 0 || x0 + w > c.width || y0 + h > c.height) return Infinity;
    let data;
    try { data = ctx.getImageData(Math.round(x0 * scale), Math.round(y0 * scale), Math.max(1, Math.round(w * scale)), Math.max(1, Math.round(h * scale))).data; } catch { return 0; }
    let ink = 0;
    for (let i = 0; i < data.length; i += 4) if (data[i] + data[i + 1] + data[i + 2] < 690) ink++;
    return ink;
  });
}

/**
 * The page as drawn, to read its pixels: a PDF page's own canvas, or a
 * diagram page's picture copied onto one (once per picture).
 */
const copies = new WeakMap();
function surfaceOf(page) {
  const canvas = page?.querySelector(".react-pdf__Page canvas, canvas");
  if (canvas?.width) return { shown: canvas, canvas };
  const img = page?.querySelector(".rr-diagram img");
  if (!img?.complete || !img.naturalWidth) return null;
  let copy = copies.get(img);
  if (!copy || copy.src !== img.src) {
    const c = document.createElement("canvas");
    c.width = img.naturalWidth;
    c.height = img.naturalHeight;
    c.getContext("2d").drawImage(img, 0, 0);
    copy = { src: img.src, canvas: c };
    copies.set(img, copy);
  }
  return { shown: img, canvas: copy.canvas };
}

/** The first choice (they come in the order preferred) that is about as clear as the clearest. */
const clearest = (ink, slack) => {
  const least = Math.min(...ink);
  return ink.findIndex((n) => n <= least + slack);
};

/** The four sides a comment can open to, off its mark. */
const SIDES = [" up right", " up left", " down right", " down left"];

/**
 * A remark's mark on a figure: its kind's icon, placed in whichever of the
 * spots around the box (or round the pinned point) has the least drawing
 * under it - the icon and the line out to it both - so neither sits on what
 * is being checked, and joined by that thin line to a dot on the box's edge
 * (or the point) so it is plain whose it is. None of it takes clicks but the icon. Its comment shows
 * beside it while it is pointed at or picked, opening to the side with the
 * least drawing, and lets clicks through.
 */
function FigureMark({ kind, spots, on, onSelect, title, text, pin = false }) {
  const ref = useRef(null);
  const [place, setPlace] = useState({ spot: 0, side: null });
  const key = JSON.stringify(spots) + text;
  useLayoutEffect(() => {
    let tries = 0;
    let timer;
    const look = () => {
      const el = ref.current;
      const surface = surfaceOf(el?.closest(".rr-page"));
      const overlay = el?.offsetParent;
      const icon = surface && overlay ? inkAt(surface, overlay, spots.map((s) => ({ ...s, x: s.dx - 14, y: s.dy - 14, w: 28, h: 28 }))) : null;
      if (!icon) {
        if (tries++ < 20) timer = setTimeout(look, 150);
        return;
      }
      // The line out to the icon counts too: the middle stretch of it, clear of the dot on the edge.
      const line = inkAt(surface, overlay, spots.map((s) => ({ ...s, x: s.dx * 0.6 - 4, y: s.dy * 0.6 - 4, w: 8, h: 8 })));
      const ink = icon.map((n, i) => n + (line?.[i] ?? 0));
      const spot = clearest(ink, 40);
      const at = spots[spot];
      const callout = el.querySelector(".rr-callout")?.getBoundingClientRect();
      let side = null;
      if (callout?.width) {
        const { width: w, height: h } = callout;
        const away = `${at.dy < 0 ? " up" : " down"}${at.dx < 0 ? " left" : " right"}`;
        const order = [away, ...SIDES.filter((x) => x !== away)];
        const room = inkAt(surface, overlay, order.map((x) => ({
          top: at.top, left: at.left, w, h,
          x: x.includes("left") ? at.dx - 4 - w : at.dx + 4,
          y: x.includes("up") ? at.dy - 14 - h : at.dy + 14,
        })));
        if (room) side = order[clearest(room, 60)];
      }
      setPlace({ spot, side });
    };
    look();
    return () => clearTimeout(timer);
  }, [key]); // eslint-disable-line react-hooks/exhaustive-deps
  const at = spots[place.spot] || spots[0];
  const side = place.side || `${at.dy < 0 ? " up" : " down"}${at.dx < 0 ? " left" : " right"}`;
  return (
    <span ref={ref} className={`rr-fmark c-${kind}`} style={{ top: pct(at.top), left: pct(at.left), "--dx": `${at.dx}px`, "--dy": `${at.dy}px` }}>
      {/* A dot where it belongs - the pinned point, or the box's edge - and a thin line out to the icon. */}
      <svg className="rr-flead" aria-hidden="true"><line x1="0" y1="0" x2={at.dx} y2={at.dy} /></svg>
      <span className={`rr-fdot${pin ? "" : " edge"}`} aria-hidden="true" />
      <button type="button" className={`rr-markbtn${on ? " on" : ""}`} onClick={onSelect} aria-label={title}>
        <KindMark kind={kind} />
      </button>
      {text && <span className={`rr-callout${on ? " on" : ""}${side}`} aria-hidden="true">{text}</span>}
    </span>
  );
}

/**
 * Where a box's mark may go, in the order preferred: just outside each
 * corner, then beside the middle of each side. A thin one (an arrow, a line)
 * tries beside its middle first, so the mark never sits by a shape it joins.
 */
const boxSpots = (b) => {
  const top = Number(b.top_position);
  const left = Number(b.left_position);
  const width = Number(b.highlight_width);
  const height = Number(b.highlight_height);
  const right = left + width;
  const bottom = top + height;
  const middle = (top + bottom) / 2;
  const centre = (left + right) / 2;
  const corners = [
    { top, left: right, dx: 20, dy: -20 },
    { top, left, dx: -20, dy: -20 },
    { top: bottom, left: right, dx: 20, dy: 20 },
    { top: bottom, left, dx: -20, dy: 20 },
  ];
  const besideSides = [{ top: middle, left: right, dx: 30, dy: 0 }, { top: middle, left, dx: -30, dy: 0 }];
  const besideEnds = [{ top, left: centre, dx: 0, dy: -30 }, { top: bottom, left: centre, dx: 0, dy: 30 }];
  if (width < 0.025) return [...besideSides, ...corners, ...besideEnds];
  if (height < 0.025) return [...besideEnds, ...corners, ...besideSides];
  return [...corners, ...besideSides, ...besideEnds];
};

/**
 * A page's remarks: the highlighted words with their number in the margin;
 * on a figure, a box (or several) or a pin, marked with its kind's icon on
 * the corner outside the box and its comment beside it.
 */
export function Highlights({ remarks, numbers, selectedId, onSelect, clickable = false, override = null }) {
  return remarks.map((r) => {
    const kind = override && override.id === r.id ? override.kind : kindOf(r.color);
    const rects = override && override.id === r.id && override.rects ? override.rects : (r.highlightRects || []);
    const on = selectedId === r.id;
    const select = (e) => { e.stopPropagation(); onSelect(r.id); };
    const label = `Remark ${numbers[r.id]}: ${r.text}`;
    const title = `${KIND_LABEL[kind]}: ${r.text}`;
    if (r.type === "pin") {
      const top = Number(r.y_position);
      const left = Number(r.x_position);
      const spots = [[22, -22], [-22, -22], [22, 22], [-22, 22]].map(([dx, dy]) => ({ top, left, dx, dy }));
      return <FigureMark key={r.id} kind={kind} spots={spots} on={on} onSelect={select} title={title} text={r.text} pin />;
    }
    if (!rects.length) return null;
    const first = rects[0];
    if (r.type === "box" || r.type === "shape") {
      return (
        <React.Fragment key={r.id}>
          {/* A shape remark is just its number until picked, so outlines never pile up over crossing arrows. */}
          {(r.type === "box" || on || override?.id === r.id) && rects.map((rect, i) => <div key={i} className={`rr-box c-${kind}${on ? " on" : ""}`} style={rectStyle(rect)} />)}
          <FigureMark kind={kind} spots={boxSpots(first)} on={on} onSelect={select} title={title} text={r.text} />
        </React.Fragment>
      );
    }
    return (
      <React.Fragment key={r.id}>
        {rects.map((rect, i) => (
          <div
            key={i}
            className={`rr-hl c-${kind}${on ? " on" : ""}${clickable ? " click" : ""}`}
            style={rectStyle(rect)}
            onClick={clickable ? select : undefined}
          />
        ))}
        <button
          type="button"
          className={`rr-num${on ? " on" : ""}`}
          style={{ top: pct(Number(first.top_position) + Number(first.highlight_height) / 2) }}
          onClick={select}
          aria-label={label}
          title={title}
        >
          {numbers[r.id]}
        </button>
      </React.Fragment>
    );
  });
}

/** What a remark on a figure is about: its figure number, or where it is. */
const CHAPTERS = ["Chapter 1", "Chapter 2", "Chapter 3", "Chapter 4", "Chapter 5"];

/** Which chapter a remark is about, picked from a list. A section typed before the list existed stays choosable. */
export function SectionPicker({ value, onChange, none = "No section" }) {
  return (
    <select className="rr-input" aria-label="Section" value={value} onChange={(e) => onChange(e.target.value)}>
      <option value="">{none}</option>
      {value && !CHAPTERS.includes(value) && <option value={value}>{value}</option>}
      {CHAPTERS.map((c) => <option key={c} value={c}>{c}</option>)}
    </select>
  );
}

export const figureTitle = (r) => r.figure_label || shapeLabel(r.shape_ref) || (r.type === "pin" ? "Pin" : "Boxed area");
const count = (n, one) => (n > 1 ? `${n} ${one}s` : one[0].toUpperCase() + one.slice(1));
export const figureDetail = (r) => [
  r.type === "pin" ? "Pin" : r.type === "shape" ? count(r.shape_ref?.cells?.length || 1, "shape") : count(r.highlightRects?.length || 1, "box"),
  DIAGRAM_LABEL[r.diagram_type] || "",
].filter(Boolean).join(" · ");

/** A figure remark enlarged, so a small diagram can be read. */
export function FigureZoom({ pdf, remark, number, onClose }) {
  const ref = useRef(null);
  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (remark && !dialog.open) dialog.showModal();
    if (!remark && dialog.open) dialog.close();
  }, [remark]);
  const width = Math.min(940, (typeof window !== "undefined" ? window.innerWidth : 1000) - 80);
  const height = Math.max(220, (typeof window !== "undefined" ? window.innerHeight : 800) * 0.6);
  return (
    <dialog ref={ref} className="rr-dialog rr-zoom" aria-labelledby="rr-zoom-title" onClose={onClose} onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      {remark && (
        <div className="rr-dlg">
          <div className="rr-zoom-head">
            <div>
              <h2 id="rr-zoom-title">{figureTitle(remark)}</h2>
              <p className="rr-muted">Page {remark.page_number} · {figureDetail(remark)}</p>
            </div>
            <button type="button" className="rr-btn icon sm ghost" aria-label="Close" onClick={onClose}><Icon name="x" /></button>
          </div>
          <div className="rr-zoom-view"><FigureCrop pdf={pdf} remark={remark} maxWidth={width} maxHeight={height} pad={0.04} /></div>
          <div className="rr-card-r1"><span className="rr-no">{number}</span><span className="rr-lbl"><span className={`rr-sw c-${kindOf(remark.color)}`} />{KIND_LABEL[kindOf(remark.color)]}</span></div>
          <div className="rr-t rr-t-full">{remark.text}</div>
        </div>
      )}
    </dialog>
  );
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

function RemarkCard({ r, n, selected, readOnly, onOpen, onEdit, onDelete, measuring, pdf, onZoom }) {
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
        {isFigureRemark(r) ? <KindMark kind={kind} inline /> : <span className="rr-no">{n}</span>}
        {isFigureRemark(r)
          ? <span className="rr-tag fig">{{ pin: "Pin", shape: "Shape" }[r.type] || "Figure"}</span>
          : <span className="rr-tag">Highlighted text</span>}
        <span className="rr-where">p.{r.page_number}{r.section ? ` · ${r.section}` : ""}</span>
        {!readOnly && (
          <span className="rr-acts">
            <button type="button" className="rr-btn icon sm ghost" tabIndex={measuring ? -1 : 0} aria-label="Edit remark" onClick={(e) => { e.stopPropagation(); onEdit(r.id); }}><Icon name="edit" /></button>
            <button type="button" className="rr-btn icon sm ghost" tabIndex={measuring ? -1 : 0} aria-label="Delete remark" onClick={(e) => { e.stopPropagation(); onDelete(r.id); }}><Icon name="x" /></button>
          </span>
        )}
      </div>
      {r.type === "shape" ? (
        <div className="rr-cshape">
          <span className={`rr-cshape-i${r.shape_ref?.cells?.[0]?.kind === "edge" ? " edge" : ""}`} aria-hidden="true" />
          <b>{figureTitle(r)}</b>
        </div>
      ) : isFigureRemark(r) ? (
        <div className="rr-cfig">
          <button
            type="button"
            className="rr-cthumb"
            tabIndex={measuring ? -1 : 0}
            aria-label={`Zoom into ${figureTitle(r)}`}
            onClick={(e) => { e.stopPropagation(); onZoom?.(r.id); }}
          >
            {!measuring && pdf && <FigureCrop pdf={pdf} remark={r} maxWidth={76} maxHeight={56} />}
          </button>
          <div className="rr-cfig-t"><b>{figureTitle(r)}</b><span>{figureDetail(r)}</span></div>
        </div>
      ) : null}
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
  onEdit, onDelete, onAddGeneral, onDeleteGeneral, top = null, empty = null, pdf = null, onZoom = null,
}) {
  const [filter, setFilter] = useState("all");
  const [writing, setWriting] = useState(false);
  const [gText, setGText] = useState("");
  const [gSection, setGSection] = useState("");
  const textRef = useRef(null);
  const total = remarks.length + general.length;

  useEffect(() => { if (writing) textRef.current?.focus(); }, [writing]);

  const figures = remarks.filter(isFigureRemark);
  const items = [
    ...(filter === "all" || filter === "general" ? general.map((g) => ({ type: "general", key: `g${g.id}`, g })) : []),
    ...(filter !== "general"
      ? [...remarks]
          .filter((r) => filter === "all" || (filter === "figures" ? isFigureRemark(r) : !isFigureRemark(r)))
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
    figures: ["No remarks on figures", readOnly ? "Nothing on a figure was marked." : "Use Box or Pin in the page toolbar to mark a figure."],
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
        <p className="rr-help">Select text on a page to comment on those words. Use <b>Box</b> or <b>Pin</b> in the page toolbar for a figure, or <b>Shape</b> to pick one box or arrow on a diagram page. Use <b>General remark</b> for comments about the whole document.</p>
      )}
      <div className="rr-side-body">
        <div className="rr-filter" role="group" aria-label="Show">
          {[["all", "All", total], ["text", "Highlighted text", remarks.length - figures.length], ["figures", "On figures", figures.length], ["general", "General", general.length]].map(([k, label, n]) => (
            <button key={k} type="button" className="rr-fbtn" aria-pressed={filter === k} onClick={() => setFilter(k)}>
              {label} <span className="rr-fc">{n}</span>
            </button>
          ))}
        </div>
        {writing && (
          <div className="rr-composer">
            <div className="rr-ctitle"><span className="rr-tag gen">General</span>About the whole document</div>
            <SectionPicker value={gSection} onChange={setGSection} none="Whole document" />
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
                pdf={pdf}
                onZoom={onZoom}
              />
            )
          }
        />
      </div>
    </aside>
  );
}

/**
 * The page strip: one small page per page, with how many remarks are on it.
 * Diagram pages, from firstDiagramPage on, sit under a "Diagrams" divider.
 */
function useOutline(pdf) {
  const [outline, setOutline] = useState([]);
  useEffect(() => {
    let alive = true;
    setOutline([]);
    if (pdf?.getOutline) readOutline(pdf).then((flat) => { if (alive) setOutline(flat); }).catch(() => {});
    return () => { alive = false; };
  }, [pdf]);
  return outline;
}

export function PageStrip({ numPages, currentPage, counts, onGo, firstDiagramPage = null, pdf = null }) {
  const outline = useOutline(pdf);
  const [tab, setTab] = useState("pages");
  const [open, setOpen] = useState(() => new Set());
  if (!numPages) return <nav className="rr-strip" aria-label="Pages" />;
  const sections = tab === "sections" && outline.length > 0;
  const shown = sections ? outline.filter((it) => it.path.every((p) => open.has(p))) : [];
  // The section being read; folded away, the one it sits under is marked instead.
  const reading = outline.findLast((it) => it.page && it.page <= currentPage);
  const hereId = reading ? reading.path.find((p) => !open.has(p)) ?? reading.id : null;
  const toggle = (id) => setOpen((prev) => { const next = new Set(prev); if (!next.delete(id)) next.add(id); return next; });
  // Remarks from a section's first page up to where the next one at its level (or above) starts.
  const remarksIn = (it, at) => {
    if (!it.page) return 0;
    const next = outline.slice(at + 1).find((o) => o.page && o.path.length <= it.path.length);
    const end = Math.max(it.page + 1, next?.page ?? firstDiagramPage ?? numPages + 1);
    let n = 0;
    for (let p = it.page; p < end; p++) n += counts[p] || 0;
    return n;
  };
  return (
    <nav className={`rr-strip${sections ? " wide" : ""}`} aria-label={sections ? "Sections" : "Pages"}>
      {outline.length > 0 && (
        <div className="rr-strip-tabs">
          <button type="button" aria-pressed={!sections} aria-label="Pages" title="Pages" onClick={() => setTab("pages")}><Icon name="pages" /><span>Pages</span></button>
          <button type="button" aria-pressed={sections} aria-label="Sections" title="Sections" onClick={() => setTab("sections")}><Icon name="list" /><span>Sections</span></button>
        </div>
      )}
      {sections && (
        <ul className="rr-outline">
          {shown.map((it) => {
            const c = remarksIn(it, it.id);
            return (
              <li key={it.id} className={it.path.length ? "sub" : ""} style={{ paddingLeft: Math.min(it.path.length, 3) * 12 }}>
                {it.kids
                  ? <button type="button" className="rr-ol-fold" aria-expanded={open.has(it.id)} aria-label={`${open.has(it.id) ? "Hide" : "Show"} what is under ${it.title}`} onClick={() => toggle(it.id)}><Icon name={open.has(it.id) ? "down" : "right"} /></button>
                  : <span className="rr-ol-fold" />}
                <button
                  type="button" className="rr-ol-go" aria-current={it.id === hereId} disabled={!it.page} onClick={() => onGo(it.page)}
                  ref={it.id === hereId ? (el) => el?.scrollIntoView({ block: "nearest" }) : undefined}
                >
                  <span>{it.title}</span>
                  {c > 0 && <span className="rr-ol-rc" aria-label={`${c} remark${c === 1 ? "" : "s"}`}>{c}</span>}
                  <span className="rr-ol-pn">{it.page}</span>
                </button>
              </li>
            );
          })}
        </ul>
      )}
      {!sections && Array.from({ length: numPages }, (_, i) => {
        const n = i + 1;
        const c = counts[n] || 0;
        const isDiagram = firstDiagramPage != null && n >= firstDiagramPage;
        return (
          <React.Fragment key={n}>
            {n === firstDiagramPage && <div className="rr-strip-divide">Diagrams</div>}
            <button
              type="button"
              className={`rr-thumb${isDiagram ? " diagram" : ""}`}
              aria-current={n === currentPage}
              aria-label={`${isDiagram ? "Diagram page" : "Page"} ${n}${c ? `, ${c} remark${c === 1 ? "" : "s"}` : ""}`}
              onClick={() => onGo(n)}
            >
              {isDiagram ? <span className="rr-dtag" aria-hidden="true">Diagram</span> : (
                <span className="rr-lines" aria-hidden="true">
                  {[62, 88, 80, 94, 70, 86, 76, 90].map((w, k) => <i key={k} style={{ width: `${w}%` }} />)}
                </span>
              )}
              <span className="rr-pn">{n}</span>
              {c > 0 && <span className="rr-rc">{c}</span>}
            </button>
          </React.Fragment>
        );
      })}
    </nav>
  );
}

/** Page and zoom controls, floating over the bottom of the document. */
const MODES = [
  ["text", "Select text", "T"], ["box", "Draw a box over a figure", "B"], ["pin", "Pin one spot", "P"],
  ["shape", "Pick a box or arrow on a diagram", "S"],
  ["hand", "Drag to move around", "H"],
];

export function ReaderTools({
  currentPage, numPages, zoom, onGo, onZoom, mode = null, onMode = null, shapes = false, before = null, beforeLabel = "", onBefore = null,
  real = null, onReal = null,
}) {
  return (
    <div className="rr-tools" role="toolbar" aria-label="Pages">
      {onMode && (
        <>
          {MODES.filter(([m]) => shapes || m !== "shape").map(([m, label, key]) => (
            <button key={m} type="button" className={`rr-btn icon ghost${mode === m ? " on" : ""}`} aria-pressed={mode === m} aria-label={label} title={`${label} (${key})`} onClick={() => onMode(m)}><Icon name={m} /></button>
          ))}
          <span className="rr-sep" aria-hidden="true" />
        </>
      )}
      {before !== null && (
        <>
          <button type="button" className={`rr-btn sm ghost${before ? " on" : ""}`} aria-pressed={before} title="Show the diagrams as they were in the earlier version (V)" onClick={onBefore}>{beforeLabel}</button>
          <span className="rr-sep" aria-hidden="true" />
        </>
      )}
      <button type="button" className="rr-btn icon ghost" aria-label="Previous page" disabled={currentPage <= 1} onClick={() => onGo(currentPage - 1)}><Icon name="up" /></button>
      <span className="rr-pg">{numPages ? `${currentPage} / ${numPages}` : "–"}</span>
      <button type="button" className="rr-btn icon ghost" aria-label="Next page" disabled={!numPages || currentPage >= numPages} onClick={() => onGo(currentPage + 1)}><Icon name="down" /></button>
      <span className="rr-sep" aria-hidden="true" />
      <button type="button" className="rr-btn icon ghost" aria-label="Zoom out" disabled={zoom <= 0.5} onClick={() => onZoom(Math.max(0.5, +(zoom - 0.25).toFixed(2)))}><Icon name="minus" /></button>
      <span className="rr-pg">{Math.round(zoom * 100)}%</span>
      <button type="button" className="rr-btn icon ghost" aria-label="Zoom in" disabled={zoom >= 2} onClick={() => onZoom(Math.min(2, +(zoom + 0.25).toFixed(2)))}><Icon name="plus" /></button>
      <button type="button" className="rr-btn icon ghost" aria-label="Fit page width" onClick={() => onZoom(1)}><Icon name="fit" /></button>
      {real !== null && (
        <button type="button" className={`rr-btn sm ghost${real ? " on" : ""}`} aria-pressed={real} title="Show big diagrams as large as they were drawn" onClick={onReal}>Real size</button>
      )}
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
  // How wide the window onto the pages is; a page is at most 900 of it.
  const [room, setRoom] = useState(808);
  const baseWidth = Math.min(900, Math.max(360, room - 48));
  const [zoom, setZoomState] = useState(1);
  const [visible, setVisible] = useState(() => new Set([1, 2, 3]));
  const [currentPage, setCurrentPage] = useState(1);
  const keepPage = useRef(null);

  useLayoutEffect(() => {
    const el = canvasRef.current;
    if (!el) return undefined;
    const observer = new ResizeObserver(() => setRoom(el.clientWidth));
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
    if (el) canvasRef.current?.scrollTo({ top: el.offsetTop - 56, behavior: smooth ? "smooth" : "auto" });
  };

  const goToRect = (n, rect, smooth = true) => {
    const el = pageRefs.current[n];
    const root = canvasRef.current;
    if (!el || !root) return;
    const top = el.offsetTop + Number(rect?.top_position || 0) * el.offsetHeight - root.clientHeight / 3;
    // Sideways too, on a page wider than the window (a diagram at its real size): the spot goes to the middle.
    const left = rect?.left_position != null && el.offsetWidth > root.clientWidth
      ? el.offsetLeft + (Number(rect.left_position) + Number(rect.highlight_width || 0) / 2) * el.offsetWidth - root.clientWidth / 2
      : root.scrollLeft;
    root.scrollTo({ top, left, behavior: smooth ? "smooth" : "auto" });
  };

  const setZoom = (z) => { keepPage.current = currentPage; setZoomState(z); };
  useLayoutEffect(() => {
    if (keepPage.current != null) { goToPage(keepPage.current, false); keepPage.current = null; }
  }, [zoom]); // eslint-disable-line react-hooks/exhaustive-deps

  return { canvasRef, pageRefs, room, baseWidth, pageWidth: Math.round(baseWidth * zoom), zoom, setZoom, visible, currentPage, onScroll, goToPage, goToRect };
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
