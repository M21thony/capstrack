import { useEffect, useState } from "react";
import { comparePages, holderOf, pairPages, readDrawio, withStyle } from "./drawioDiff.js";

/*
 * The team's draw.io files shown as extra pages after the PDF, drawn by
 * draw.io's own viewer (served from this site, nothing is sent to draw.io).
 * Against an earlier version, what was put in is coloured green and what was
 * taken out red: on the drawing itself, from the files, not from a picture.
 */

const VIEWER = "/vendor/drawio";
let viewer = null;

/** draw.io's viewer, loaded once. Its paths point here first, so it never reaches out to diagrams.net. */
export function loadViewer() {
  viewer ??= new Promise((resolve, reject) => {
    ["PROXY_URL", "DRAWIO_LIGHTBOX_URL", "VSS_CONVERT_URL", "DRAWIO_LOG_URL"].forEach((k) => { window[k] = ""; });
    ["STYLE_PATH", "SHAPES_PATH", "STENCIL_PATH", "DRAW_MATH_URL", "GRAPH_IMAGE_PATH", "mxImageBasePath", "mxBasePath"].forEach((k) => { window[k] = VIEWER; });
    const s = Object.assign(document.createElement("script"), { src: `${VIEWER}/viewer-static.min.js` });
    s.onload = resolve;
    s.onerror = () => { viewer = null; reject(new Error("The diagram viewer did not load.")); };
    document.head.append(s);
  });
  return viewer;
}

const GREEN = { fill: "#e0f7e8", line: "#1a7f37", word: "#abf2bc" };
const RED = { fill: "#f7dcde", line: "#cf222e", word: "#ffcecb" };

/** A label's words, each a run of letters in one of its text pieces, in reading order. */
function labelWords(value, html) {
  const escaped = (value || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, "<br>");
  const body = new DOMParser().parseFromString(`<body>${html ? value || "" : escaped}</body>`, "text/html").body;
  const walk = document.createTreeWalker(body, NodeFilter.SHOW_TEXT);
  const texts = [];
  while (walk.nextNode()) texts.push(walk.currentNode);
  return { body, texts, count: texts.reduce((n, t) => n + t.data.split(/\s+/).filter(Boolean).length, 0) };
}

/** Each word whose flag is set wrapped in a highlight; the label comes back as html. */
function highlighted({ body, texts }, flags, colour) {
  let k = 0;
  texts.forEach((t) => {
    const out = document.createDocumentFragment();
    t.data.split(/(\s+)/).forEach((piece) => {
      if (!piece || /^\s+$/.test(piece)) { out.append(piece); return; }
      if (flags[k++]) out.append(Object.assign(document.createElement("span"), { textContent: piece, style: `background-color:${colour}` }));
      else out.append(piece);
    });
    t.replaceWith(out);
  });
  return body.innerHTML;
}

/** A copy of the page's model with this side's changes coloured in. */
function paint(model, spots, side) {
  const copy = model.cloneNode(true);
  const tone = side === "old" ? RED : GREEN;
  const byId = new Map([...copy.getElementsByTagName("mxCell")].map((el) => [holderOf(el).getAttribute("id"), el]));
  const restyle = (id, set) => { const el = byId.get(id); if (el) el.setAttribute("style", withStyle(el.getAttribute("style"), set)); };
  // The words of these cells, read as one label, highlighted where flagged (all of them if the count is off).
  const mark = (ids, flags) => {
    const cells = ids.map((id) => byId.get(id)).filter(Boolean).map((el) => {
      const holder = holderOf(el), attr = holder === el ? "value" : "label";
      return { el, holder, attr, words: labelWords(holder.getAttribute(attr), /(^|;)html=1(;|$)/.test(el.getAttribute("style") || "")) };
    });
    const total = cells.reduce((n, c) => n + c.words.count, 0);
    const all = !flags || flags.length !== total;
    let at = 0;
    cells.forEach((c) => {
      const mine = all ? Array(c.words.count).fill(1) : flags.slice(at, at + c.words.count);
      at += c.words.count;
      if (!mine.some(Boolean)) return;
      c.holder.setAttribute(c.attr, highlighted(c.words, mine, tone.word));
      c.el.setAttribute("style", withStyle(c.el.getAttribute("style"), { html: 1 }));
    });
  };
  // An arrow's words: its own, then its labels, then any text box that was pulled off it.
  const labelIds = (c) => (c.kind === "edge" ? [c.id, ...c.parts.map((p) => p.id), ...(c.labels || []).map((l) => l.id)] : [c.id]);
  const isText = (c) => c.shape === "text" || (c.fill === "none" && c.stroke === "none");
  spots.flatMap((s) => s.items).forEach((it) => {
    const c = it[side];
    if (!c) return;
    if (it.what === "renamed") { mark([...new Set(labelIds(c))], side === "old" ? it.oldWords : it.newWords); return; }
    if (c.kind === "edge") {
      restyle(c.id, { strokeColor: tone.line, strokeWidth: 2 });
      if ((it.what === "added" || it.what === "removed") && c.text) mark([...new Set(labelIds(c))]);
      return;
    }
    if (isText(c)) mark([c.id]);
    else restyle(c.id, { fillColor: tone.fill, gradientColor: "none", ...(c.shape === "image" ? { imageBackground: tone.fill } : {}) });
  });
  return copy;
}

/** The page drawn by draw.io, as a picture (its labels can never run anything on this page). */
function picture(model) {
  const { Graph, mxCodec, mxUtils } = window;
  const graph = new Graph(document.createElement("div"));
  try {
    new mxCodec(model.ownerDocument).decode(model, graph.getModel());
    const svg = graph.getSvg("#ffffff", 1, 12, null, null, true);
    return {
      src: `data:image/svg+xml;charset=utf-8,${encodeURIComponent(mxUtils.getXml(svg))}`,
      width: parseFloat(svg.getAttribute("width")) || 800,
      height: parseFloat(svg.getAttribute("height")) || 600,
    };
  } finally {
    graph.destroy();
  }
}

/** Every page of the .drawio files at this address. */
async function pagesAt(url) {
  const r = await fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } });
  if (!r.ok) throw new Error(`Could not load the diagrams (${r.status}).`);
  const files = await r.json();
  const read = await Promise.all(files.map((f) => readDrawio(f.xml).catch(() => [])));
  return files.flatMap((f, n) => read[n].filter((p) => p.model).map((p, index) => ({
    ...p, file: f.name, index,
    words: new Set(p.cells.flatMap((c) => c.text.toLowerCase().split(" ").filter(Boolean))),
  })));
}

/**
 * The diagram pages of one version: [{ key, label, plain, marked, said }].
 * With the other version's files, marked is this side's changes coloured in
 * (side "neu" for the version under review, "old" for the earlier one); a
 * page with no partner is all put in, or all taken out.
 */
export async function diagramPages(url, otherUrl = null, side = "neu") {
  const [mine, theirs] = await Promise.all([pagesAt(url), otherUrl ? pagesAt(otherUrl) : null, loadViewer()]);
  const partner = theirs ? pairPages(mine, theirs) : [];
  return mine.map((p, i) => {
    const page = { key: `${p.file}#${p.index}`, label: [p.file, p.name].filter(Boolean).join(" — "), plain: picture(p.model), marked: null, said: [] };
    if (!theirs) return page;
    const other = theirs[partner[i]] || { els: [] };
    const { spots } = side === "old" ? comparePages(p, other) : comparePages(other, p);
    page.said = spots.flatMap((s) => s.said);
    if (spots.length) page.marked = picture(paint(p.model, spots, side));
    return page;
  });
}

/** diagramPages as it loads: { status: idle | loading | ready | error, pages, note }. */
export function useDiagramPages(url, otherUrl = null, side = "neu") {
  const [state, setState] = useState({ status: "idle", pages: [] });
  useEffect(() => {
    if (!url) { setState({ status: "idle", pages: [] }); return undefined; }
    let alive = true;
    setState({ status: "loading", pages: [] });
    diagramPages(url, otherUrl, side)
      .then((pages) => { if (alive) setState({ status: "ready", pages }); })
      .catch((e) => { if (alive) setState({ status: "error", note: e.message, pages: [] }); });
    return () => { alive = false; };
  }, [url, otherUrl, side]);
  return state;
}

/** One diagram page's body: its name, then the drawing filling the page's width. */
export function DiagramPage({ page, showChanges = true }) {
  const pic = (showChanges && page.marked) || page.plain;
  return (
    <div className="rr-diagram">
      <div className="rr-diagram-cap">{page.label}</div>
      <img src={pic.src} width={pic.width} height={pic.height} alt={`Diagram: ${page.label}`} draggable={false} />
    </div>
  );
}
