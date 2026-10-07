/*
 * Remarks on figures: what a box or a pin is about. Captions are read from the
 * PDF's own text, so a mark can be matched to "Figure 3.2" without the
 * reviewer typing it, and the CLF can say which figure a remark means.
 * Positions are fractions of the page, like every remark's.
 */

// The diagram types a figure remark can name. The ids are what pdf_remarks stores.
export const DIAGRAM_TYPES = [
  { id: "", label: "General" },
  { id: "ERD", label: "ERD" },
  { id: "DFD", label: "DFD / context" },
  { id: "UseCase", label: "Use case" },
  { id: "Flowchart", label: "Flowchart" },
  { id: "Architecture", label: "Architecture" },
  { id: "UI", label: "UI / screen" },
];
export const DIAGRAM_LABEL = Object.fromEntries(DIAGRAM_TYPES.filter((d) => d.id).map((d) => [d.id, d.label]));

// Ready-made remarks a reviewer taps instead of typing, by diagram type.
// The general ones show for every figure.
export const QUICK_REMARKS = {
  "": ["Label is unclear.", "Does not match the text.", "Caption is missing or wrong.", "Image is blurry; export it at a higher resolution."],
  ERD: ["Missing cardinality.", "Missing primary key.", "Foreign key is not shown.", "Relationship is not labelled.", "Mixed notation (Chen and Crow's Foot).", "Entity is not discussed in the text."],
  DFD: ["Not balanced with the context diagram.", "Data flow is not labelled.", "Data store has no inflow.", "Process is not numbered.", "An external entity is missing."],
  UseCase: ["Actor is not connected.", "Name use cases with a verb.", "System boundary is missing.", "Include/extend is used wrongly."],
  Flowchart: ["Decision has only one exit.", "Missing start or end.", "Wrong symbol for this step.", "Flow lines cross; reroute them."],
  Architecture: ["Component is not explained in the text.", "Connections are not labelled.", "Hosting or deployment is not shown."],
  UI: ["Screen does not match the system.", "Fields have no labels."],
};

/** The quick remarks for a diagram type: its own first, then the general ones. */
export const quickRemarksFor = (type) => (type ? [...(QUICK_REMARKS[type] || []), ...QUICK_REMARKS[""].slice(0, 3)] : QUICK_REMARKS[""]);

/**
 * "Figure 3.1. Title", "Fig. 2: Title", "Figure 4 Title" - but not a sentence
 * that mentions one ("Figure 3.1 shows ...") or a List of Figures line (dot
 * leaders, or a page number at the end).
 */
export function parseCaption(text) {
  const s = String(text || "").replace(/\s+/g, " ").trim();
  // Case matters after the number: a title starts with a capital, a sentence does not.
  const m = s.match(/^([Ff]ig(?:ure)?|FIG(?:URE)?)\.?\s*(\d+(?:[.-]\d+)*)(?:\s*[.:–—-](?!\d)\s*|\s+(?=[A-Z])|$)(.*)$/);
  if (!m) return null;
  const title = m[3].trim();
  // A List of Figures line: dot leaders, or a title ending in its page number.
  if (/\.{4,}/.test(s) || /\s\d{1,3}$/.test(title)) return null;
  return { label: `Figure ${m[2]}`, title };
}

/** A diagram type guessed from a caption's words, or "" when it says nothing. */
export function guessDiagramType(title) {
  const t = String(title || "").toLowerCase();
  if (/entity|\berd\b|relationship diagram|database schema/.test(t)) return "ERD";
  if (/context diagram|data flow|\bdfd\b/.test(t)) return "DFD";
  if (/use[- ]case/.test(t)) return "UseCase";
  if (/flow ?chart|program flow|process flow/.test(t)) return "Flowchart";
  if (/architecture|deployment|network|component|infrastructure/.test(t)) return "Architecture";
  if (/interface|screen|wireframe|mock-?up|layout|\bui\b/.test(t)) return "UI";
  return "";
}

/**
 * The captions on one page, from pdf.js text items ({str, transform, height}).
 * `pageHeight` is the page's height at scale 1; `y` comes back as the fraction
 * down the page where the caption's line starts.
 */
export function captionsOnPage(items, pageHeight, pageNumber) {
  const lines = (items || []).filter((i) => typeof i.str === "string" && i.str.trim());
  const found = [];
  lines.forEach((item, i) => {
    if (!/^\s*fig/i.test(item.str)) return;
    const baseline = item.transform[5];
    // The rest of the caption is often split over several items on the same line.
    const line = lines.slice(i, i + 12).filter((o) => Math.abs(o.transform[5] - baseline) < 2).map((o) => o.str).join(" ");
    const caption = parseCaption(line);
    if (!caption) return;
    const size = Math.hypot(item.transform[2], item.transform[3]) || item.height || 10;
    found.push({ ...caption, page: pageNumber, y: Math.min(1, Math.max(0, (pageHeight - baseline - size) / pageHeight)) });
  });
  return found;
}

/** Every caption in a document, the first of each figure number kept. */
export function uniqueFigures(captions) {
  const seen = new Set();
  return captions.filter((c) => (seen.has(c.label) ? false : seen.add(c.label)));
}

/** The box around a list of rects. */
export function boundsOf(rects) {
  const top = Math.min(...rects.map((r) => r.top_position));
  const left = Math.min(...rects.map((r) => r.left_position));
  const bottom = Math.max(...rects.map((r) => r.top_position + r.highlight_height));
  const right = Math.max(...rects.map((r) => r.left_position + r.highlight_width));
  return { top_position: top, left_position: left, highlight_width: right - left, highlight_height: bottom - top };
}

/** Where a remark sits: its rects, or the point of a pin. */
export const marksOf = (remark) => (remark.highlightRects?.length
  ? remark.highlightRects
  : [{ top_position: Number(remark.y_position) || 0, left_position: Number(remark.x_position) || 0, highlight_width: 0, highlight_height: 0 }]);

/**
 * The figure a mark is most likely on: the nearest caption below it on the
 * same page, since a figure's caption sits under it. A caption above a mark
 * is the caption of some other figure, so it is never taken; with nothing
 * below, the mark is not on a numbered figure.
 */
export function figureFor(figures, page, rects) {
  const captions = figures.filter((f) => f.page === page);
  if (!captions.length || !rects.length) return null;
  const b = boundsOf(rects);
  const middle = b.top_position + b.highlight_height / 2;
  const below = captions.filter((c) => c.y >= middle - 0.04).sort((a, z) => a.y - z.y);
  return below.length && below[0].y - middle < 0.55 ? below[0].label : null;
}

const clamp = (n) => Math.min(1, Math.max(0, n));
/**
 * The part of the page a snapshot of a remark shows: the boxes with a margin,
 * the area around a pin, the lines around quoted words.
 */
export function cropOf(remark, pad = 0.025) {
  const fit = (r) => {
    const left = clamp(r.left), top = clamp(r.top);
    return { left, top, width: Math.max(0.02, Math.min(r.width, 1 - left)), height: Math.max(0.02, Math.min(r.height, 1 - top)) };
  };
  const b = boundsOf(marksOf(remark));
  if (remark.type === "pin") return fit({ left: b.left_position - 0.17, top: b.top_position - 0.15, width: 0.34, height: 0.24 });
  if (remark.type !== "box" && remark.type !== "shape") return fit({ left: 0.06, top: b.top_position - 0.04, width: 0.88, height: b.highlight_height + 0.08 });
  return fit({ left: b.left_position - pad, top: b.top_position - pad, width: b.highlight_width + pad * 2, height: b.highlight_height + pad * 2 });
}

/** True for a remark drawn on a figure rather than on selected words. */
export const isFigureRemark = (remark) => ["box", "pin", "shape"].includes(remark.type);

/**
 * What a shape remark is about, from the cells it was tied to:
 * "Box: Validate login", "Arrow: User → Login", or a count for several.
 */
export function shapeLabel(ref) {
  const cells = ref?.cells || [];
  const one = (c) => (c.kind === "edge"
    ? `Arrow: ${c.from || "?"} → ${c.to || "?"}${c.text ? ` (${c.text})` : ""}`
    : `Box: ${c.text || "unnamed"}`);
  if (!cells.length) return "";
  if (cells.length === 1) return one(cells[0]);
  return cells.length === 2 ? `${one(cells[0])}; ${one(cells[1])}` : `${one(cells[0])} and ${cells.length - 1} more`;
}

/** A saved remark as the screen keeps it: every box of a figure remark, or the point of a pin. */
export const remarkFromServer = (r) => {
  const type = r.mark_type || "text";
  const box = {
    top_position: Number(r.top_position),
    left_position: Number(r.left_position),
    highlight_width: Number(r.highlight_width),
    highlight_height: Number(r.highlight_height),
  };
  const rects = (type === "box" || type === "shape") && Array.isArray(r.rects) && r.rects.length
    ? r.rects.map((x) => ({
        top_position: Number(x.top_position),
        left_position: Number(x.left_position),
        highlight_width: Number(x.highlight_width),
        highlight_height: Number(x.highlight_height),
      }))
    : type !== "pin" && box.highlight_width > 0 ? [box] : [];
  return {
    id: r.id,
    type,
    page_number: Number(r.page_number),
    y_position: box.top_position,
    x_position: box.left_position,
    text: r.comment,
    author: "Reviewer",
    timestamp: r.created_at,
    selectedText: r.selected_text,
    section: r.section,
    color: r.color,
    figure_label: r.figure_label || null,
    diagram_type: r.diagram_type || null,
    revision_status: r.revision_status || null,
    carried_from_id: r.carried_from_id ?? null,
    highlightRects: rects,
    shape_ref: r.shape_ref || null,
  };
};
