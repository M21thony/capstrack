import { useEffect, useRef, useState } from "react";
import { comparePages, holderOf, pairPages, readDrawio, withStyle } from "./drawioDiff.js";
import { textLayer } from "./textLayer.js";

/*
 * The team's draw.io files shown as extra pages after the PDF, drawn by
 * draw.io's own viewer (served from this site, nothing is sent to draw.io).
 * Against an earlier version, what was put in is coloured green and what was
 * taken out red: on the drawing itself, from the files, not from a picture.
 */

const VIEWER = "/vendor/drawio";
let viewer = null;
let visio = null;

const script = (src) => new Promise((resolve, reject) => {
  const s = Object.assign(document.createElement("script"), { src: `${VIEWER}/${src}`, onload: resolve, onerror: reject });
  document.head.append(s);
});

/**
 * draw.io's viewer and its shape library (AWS, Cisco, flowchart…), loaded
 * once. Its paths point here first, so it never reaches out to diagrams.net.
 */
export function loadViewer() {
  ["PROXY_URL", "DRAWIO_LIGHTBOX_URL", "VSS_CONVERT_URL", "DRAWIO_LOG_URL"].forEach((k) => { window[k] = ""; });
  ["STYLE_PATH", "SHAPES_PATH", "STENCIL_PATH", "DRAW_MATH_URL", "GRAPH_IMAGE_PATH", "mxImageBasePath", "mxBasePath"].forEach((k) => { window[k] = VIEWER; });
  viewer ??= script("viewer-static.min.js").then(() => script("stencils.min.js"))
    .catch(() => { viewer = null; throw new Error("The diagram viewer did not load."); });
  return viewer;
}

/** draw.io's Visio reader, loaded once, only when a team attached a .vsdx. */
function loadVisio() {
  // The reader ends by adding a menu action to draw.io's editor, which the viewer doesn't have.
  window.EditorUi ??= function EditorUi() {};
  visio ??= loadViewer().then(() => script("vsdx/jszip.min.js")).then(() => script("vsdx/bmpDecoder.js")).then(() => script("vsdx/importer.js"))
    .catch(() => { visio = null; throw new Error("The Visio reader did not load."); });
  return visio;
}

/** A .vsdx (sent as base64) turned into draw.io's own file, by draw.io's reader, here in the browser. */
async function visioToDrawio(base64) {
  await loadVisio();
  const bytes = Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
  return new Promise((resolve, reject) => {
    new window.com.mxgraph.io.mxVsdxCodec().decodeVsdx(new Blob([bytes]), (xml) => (xml ? resolve(xml) : reject(new Error("Not a Visio file"))), null, reject);
  });
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

/** The page drawn by draw.io, as a picture (its labels can never run anything on this page), and its words. */
function picture(model) {
  const { Graph, mxCodec, mxUtils } = window;
  const graph = new Graph(document.createElement("div"));
  try {
    // Reading a model unwraps its data-carrying shapes in place, so it reads a copy: the compare still needs the original.
    new mxCodec(model.ownerDocument).decode(model.cloneNode(true), graph.getModel());
    const svg = graph.getSvg("#ffffff", 1, 12, null, null, true);
    return {
      src: `data:image/svg+xml;charset=utf-8,${encodeURIComponent(mxUtils.getXml(svg))}`,
      width: parseFloat(svg.getAttribute("width")) || 800,
      height: parseFloat(svg.getAttribute("height")) || 600,
      text: textLayer(svg),
    };
  } finally {
    graph.destroy();
  }
}

const decoded = (src) => { const img = new Image(); img.src = src; return img.decode().then(() => img); };

/** A picture as PNG bytes, drawn at this scale, for a PDF to carry. */
export async function pngOf({ src, width, height }, scale = 2) {
  const img = await decoded(src);
  const canvas = Object.assign(document.createElement("canvas"), { width: Math.round(width * scale), height: Math.round(height * scale) });
  const ctx = canvas.getContext("2d");
  ctx.fillStyle = "#fff";
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
  const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/png"));
  return new Uint8Array(await blob.arrayBuffer());
}

/** A picture that answers like a pdf.js page, for code that draws pages by number. */
function pictureAsPage({ src, width, height }) {
  let img;
  return {
    getViewport: ({ scale = 1, offsetX = 0, offsetY = 0 } = {}) => ({ width: width * scale, height: height * scale, offsetX, offsetY }),
    render: ({ canvasContext, viewport }) => ({
      promise: (img ??= decoded(src)).then((i) => canvasContext.drawImage(i, viewport.offsetX, viewport.offsetY, viewport.width, viewport.height)),
    }),
  };
}

/**
 * The PDF with the diagram pages after its own, numbered as the reader shows
 * them, so a remark's snapshot on a diagram page is drawn like any other.
 * numPages stays the PDF's own.
 */
export function withDiagramPages(pdf, pages) {
  if (!pages.length) return pdf;
  const extra = pages.map((p) => pictureAsPage(p.plain));
  // A diagram sent as itself: its pages are the whole document.
  if (!pdf) return { numPages: extra.length, getPage: (n) => Promise.resolve(extra[Number(n) - 1]) };
  return new Proxy(pdf, {
    get(target, key) {
      if (key === "getPage") {
        return (n) => extra[Number(n) - target.numPages - 1] ? Promise.resolve(extra[Number(n) - target.numPages - 1]) : target.getPage(n);
      }
      const value = Reflect.get(target, key);
      return typeof value === "function" ? value.bind(target) : value;
    },
  });
}

/** Every page of the .drawio and .vsdx files at this address. */
async function pagesAt(url) {
  const r = await fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } });
  if (!r.ok) throw new Error(`Could not load the diagrams (${r.status}).`);
  const files = await r.json();
  const read = await Promise.all(files.map((f) => (f.vsdx ? visioToDrawio(f.vsdx) : Promise.resolve(f.xml)).then(readDrawio).catch(() => [])));
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

/** Stands where react-pdf's Document would for a diagram sent as itself, saying when its pages are not ready. */
export function DiagramsOnly({ drawings, loading, children }) {
  if (drawings.status === "error") return <div className="rr-error">Could not load the document: {drawings.note}</div>;
  if (drawings.status !== "ready") return loading;
  if (!drawings.pages.length) return <div className="rr-error">This diagram file has no pages to show.</div>;
  return children;
}

/** One diagram page's body: its name, then the drawing filling the page's width, its words selectable over it. */
export function DiagramPage({ page, showChanges = true }) {
  const pic = (showChanges && page.marked) || page.plain;
  const words = useRef(null);
  useEffect(() => { words.current?.replaceChildren(pic.text.cloneNode(true)); }, [pic]);
  return (
    <div className="rr-diagram">
      <div className="rr-diagram-cap">{page.label}</div>
      <img src={pic.src} width={pic.width} height={pic.height} alt={`Diagram: ${page.label}`} draggable={false} />
      <div ref={words} className="rr-diagram-text" />
    </div>
  );
}
