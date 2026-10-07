import { editScript } from "./versionDiff.js";

/*
 * Exact compare of a diagram from the draw.io files the team sent with the
 * PDF. The shapes and arrows are read from the files themselves, so what
 * changed is known for certain (diagramPages.jsx colours it on the drawing).
 *
 * Cells are matched by what they are (shape, words, what they connect), the
 * id only breaks ties: a redrawn diagram can reuse an id for something else.
 */

const words = (t) => t.split(" ").filter(Boolean);
/** A label's lines as written (a line break, or a new paragraph, starts one). */
const labelLines = (v, html) => {
  if (!v) return [];
  const t = html ? new DOMParser().parseFromString(`<body>${v.replace(/<br\s*\/?>|<\/?(div|p)\b[^>]*>/gi, "\n")}</body>`, "text/html").body.textContent : v;
  return t.split("\n").map((s) => s.replace(/\s+/g, " ").trim()).filter(Boolean);
};
const parseStyle = (s) => {
  const out = {};
  (s || "").split(";").filter(Boolean).forEach((kv, i) => {
    const at = kv.indexOf("=");
    if (at < 0) { if (i === 0) out.bare = kv; } else out[kv.slice(0, at)] = kv.slice(at + 1);
  });
  return out;
};
const colour = (c) => (c || "default").toLowerCase();

/** The pages of a .drawio file, each a list of its <mxCell>s (a page saved compressed is inflated). */
async function pages(xml) {
  const doc = new DOMParser().parseFromString(xml, "text/xml");
  if (doc.querySelector("parsererror")) throw new Error("Not a draw.io file");
  const diagrams = [...doc.getElementsByTagName("diagram")];
  const roots = diagrams.length ? diagrams : [doc.documentElement];
  return Promise.all(roots.map(async (d) => {
    let model = d.getElementsByTagName("mxGraphModel")[0];
    if (!model && d.textContent.trim()) {
      const bin = Uint8Array.from(atob(d.textContent.trim()), (c) => c.charCodeAt(0));
      const raw = await new Response(new Blob([bin]).stream().pipeThrough(new DecompressionStream("deflate-raw"))).text();
      model = new DOMParser().parseFromString(decodeURIComponent(raw), "text/xml").documentElement;
    }
    return { name: d.getAttribute("name") || "", model, els: model ? [...model.getElementsByTagName("mxCell")] : [] };
  }));
}

/** A cell with data of its own is wrapped in an <object>, which then holds its id and words. */
export const holderOf = (el) => (/^(object|UserObject)$/.test(el.parentNode?.nodeName) ? el.parentNode : el);

/** One page's cells as plain records: shapes (with arrow labels folded into their arrow) and arrows. */
function readPage(cellEls) {
  const raw = new Map();
  cellEls.forEach((el) => {
    const holder = holderOf(el);
    const id = holder.getAttribute("id");
    const st = parseStyle(el.getAttribute("style"));
    const g = el.getElementsByTagName("mxGeometry")[0];
    const num = (a) => parseFloat(g?.getAttribute(a)) || 0;
    const xy = (p) => [+p.getAttribute("x") || 0, +p.getAttribute("y") || 0];
    const pt = (as) => { const p = g && [...g.getElementsByTagName("mxPoint")].find((q) => q.getAttribute("as") === as); return p ? xy(p) : null; };
    const bends = g?.getElementsByTagName("Array")[0];
    const lines = labelLines(holder === el ? el.getAttribute("value") : holder.getAttribute("label"), st.html === "1");
    const port = (x, y) => (st[x] !== undefined && st[y] !== undefined ? [+st[x], +st[y]] : null);
    raw.set(id, {
      id, svgId: id,
      kind: el.getAttribute("edge") === "1" ? "edge" : el.getAttribute("vertex") === "1" ? "vertex" : "layer",
      parent: el.getAttribute("parent"), source: el.getAttribute("source"), target: el.getAttribute("target"),
      lines, text: lines.join(" "), fs: +st.fontSize || 12, off: pt("offset"),
      exit: port("exitX", "exitY"), entry: port("entryX", "entryY"),
      shape: st.shape || st.bare || (st.ellipse ? "ellipse" : "rect"),
      fill: colour(st.fillColor), stroke: colour(st.strokeColor), dashed: st.dashed === "1",
      heads: [st.startArrow || "none", st.endArrow || "classic"],
      rel: g ? [num("x"), num("y"), num("width"), num("height")] : [0, 0, 0, 0],
      ends: [pt("sourcePoint"), pt("targetPoint")],
      bends: bends ? [...bends.getElementsByTagName("mxPoint")].map(xy) : [],
    });
  });
  // Where a shape sits on the page: its place inside its container plus the container's place.
  const abs = (c, depth = 0) => {
    if (c.box) return c.box;
    const p = raw.get(c.parent);
    const [px, py] = p && p.kind === "vertex" && depth < 50 ? abs(p, depth + 1) : [0, 0];
    return (c.box = [px + c.rel[0], py + c.rel[1], px + c.rel[0] + c.rel[2], py + c.rel[1] + c.rel[3]]);
  };
  const out = [];
  raw.forEach((c) => {
    if (c.kind === "vertex" && raw.get(c.parent)?.kind === "edge") return; // an arrow's label: part of the arrow
    if (c.kind === "vertex") {
      abs(c);
      // Nothing to see (a store's wrapper, a spacer): still matched, since arrows end on it, but never reported.
      c.hidden = !c.text && ((c.fill === "none" && c.stroke === "none") || c.shape === "text");
    }
    if (c.kind === "edge") {
      // Its labels, each where draw.io puts it: a share of the way along (-1 to 1), pushed aside by dy and off.
      const part = (l) => ({ id: l.id, lines: l.lines, at: l.rel[0], dy: l.rel[1], off: l.off, fs: l.fs });
      c.parts = c.text ? [part(c)] : [];
      raw.forEach((l) => {
        if (l.kind === "vertex" && l.parent === c.svgId && l.text) {
          c.text = [c.text, l.text].filter(Boolean).join(" ");
          c.parts.push(part(l));
        }
      });
    }
    if (c.kind !== "layer") out.push(c);
  });
  const byId = new Map(out.map((c) => [c.svgId, c]));
  const centre = (b) => [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2];
  out.forEach((c) => {
    c.parentCell = byId.get(c.parent) || null;
    if (c.kind !== "edge") return;
    c.from = byId.get(c.source) || null;
    c.to = byId.get(c.target) || null;
    // An arrow's bends are kept relative to the container it sits in.
    const [ox, oy] = c.parentCell?.kind === "vertex" ? c.parentCell.box : [0, 0];
    c.bends = c.bends.map(([x, y]) => [x + ox, y + oy]);
    c.ends = c.ends.map((p) => p && [p[0] + ox, p[1] + oy]);
    // An arrow may end on another arrow (Visio files do this), which may not have its place yet.
    const ps = [c.from?.box ? centre(c.from.box) : c.ends[0], ...c.bends, c.to?.box ? centre(c.to.box) : c.ends[1]].filter(Boolean);
    c.box = ps.length ? [Math.min(...ps.map((p) => p[0])), Math.min(...ps.map((p) => p[1])), Math.max(...ps.map((p) => p[0])), Math.max(...ps.map((p) => p[1]))] : [0, 0, 0, 0];
  });
  // A container: something is drawn in it, as its child or only laid on top of it.
  const inside = (x, c) => x.box[0] >= c.box[0] - 1 && x.box[1] >= c.box[1] - 1 && x.box[2] <= c.box[2] + 1 && x.box[3] <= c.box[3] + 1;
  const vs = out.filter((c) => c.kind === "vertex");
  const area = (b) => (b[2] - b[0]) * (b[3] - b[1]);
  vs.forEach((c) => { c.inner = vs.some((x) => x !== c && !x.hidden && (x.parentCell === c || (inside(x, c) && area(x.box) < area(c.box) / 2))); });
  return out;
}

/** Every page of a .drawio file: its name, its model, its raw cells (read again for each compare) and its shapes. */
export async function readDrawio(xml) {
  return (await pages(xml)).map((p) => ({ name: p.name, model: p.model, els: p.els, cells: readPage(p.els) }));
}

const centreDist = (a, b) => Math.hypot((a[0] + a[2] - b[0] - b[2]) / 2, (a[1] + a[3] - b[1] - b[3]) / 2);
const overlap = (a, b) => {
  const w = Math.min(a[2], b[2]) - Math.max(a[0], b[0]), h = Math.min(a[3], b[3]) - Math.max(a[1], b[1]);
  if (w <= 0 || h <= 0) return 0;
  const area = (x) => (x[2] - x[0]) * (x[3] - x[1]);
  return (w * h) / (area(a) + area(b) - w * h);
};

// ponytail: greedy pairing (cheapest first), not an optimal assignment; fine while costs separate clearly.
function pair(olds, news, cost, map, back) {
  const pairs = [];
  olds.forEach((a) => { if (!map.has(a)) news.forEach((b) => { if (!back.has(b)) { const c = cost(a, b); if (c !== null) pairs.push([c, a, b]); } }); });
  pairs.sort((x, y) => x[0] - y[0]);
  pairs.forEach(([, a, b]) => { if (!map.has(a) && !back.has(b)) { map.set(a, b); back.set(b, a); } });
}

function matchPage(A, B) {
  const map = new Map(), back = new Map();
  const av = A.filter((c) => c.kind === "vertex"), bv = B.filter((c) => c.kind === "vertex");
  const ae = A.filter((c) => c.kind === "edge"), be = B.filter((c) => c.kind === "edge");
  const sameId = (a, b) => (a.id === b.id ? 0 : 1);
  // 1. Same shape, same words: the same thing, wherever it now sits.
  pair(av, bv, (a, b) => (a.text && a.shape === b.shape && a.text === b.text ? sameId(a, b) * 1e5 + centreDist(a.box, b.box) : null), map, back);
  // 2. Same words, other shape (a box turned into an oval).
  pair(av, bv, (a, b) => (a.text && a.text === b.text ? sameId(a, b) * 1e5 + centreDist(a.box, b.box) : null), map, back);
  // 3. Same shape, other words: renamed, if it sits where the old one sat, in the same container, or joins the same things.
  const near = (c, edges) => {
    const out = new Set();
    edges.forEach((e) => {
      if (e.from === c && e.to) out.add(e.to);
      if (e.to === c && e.from) out.add(e.from);
    });
    return out;
  };
  pair(av, bv, (a, b) => {
    if (a.shape !== b.shape) return null;
    const na = [...near(a, ae)].map((x) => map.get(x)).filter(Boolean), nb = near(b, be);
    const shared = na.filter((x) => nb.has(x)).length / Math.max(na.length, nb.size, 1);
    const sameParent = a.parentCell && map.get(a.parentCell) === b.parentCell;
    const s = overlap(a.box, b.box) + shared + (sameParent ? 0.3 : 0) + (a.id === b.id ? 0.2 : 0);
    // A shape without words is only the same one if it stayed about where it was, on the page or inside its moved container.
    const size = Math.max(a.rel[2], a.rel[3]);
    const r = (c) => [c.rel[0], c.rel[1], c.rel[0] + c.rel[2], c.rel[1] + c.rel[3]];
    if (!a.text || !b.text) return a.id === b.id || centreDist(a.box, b.box) < size || (sameParent && centreDist(r(a), r(b)) < size) ? -s : null;
    return s >= 0.5 ? -s : null;
  }, map, back);
  // 4. Arrows between the same two things (either way round); words, direction and id break ties.
  pair(ae, be, (a, b) => {
    if (!a.from || !a.to || !b.from || !b.to) return null;
    const s = map.get(a.from), t = map.get(a.to);
    const same = s === b.from && t === b.to, flipped = s === b.to && t === b.from;
    if (!same && !flipped) return null;
    return (a.text === b.text ? 0 : 10) + (same ? 0 : 1) + sameId(a, b) * 0.5;
  }, map, back);
  // 5. Loose arrows (an end tied to nothing): same words, close by.
  pair(ae, be, (a, b) => {
    if (a.text !== b.text) return null;
    const d = centreDist(a.box, b.box);
    return d < 80 || a.id === b.id ? d : null;
  }, map, back);
  return map;
}

/**
 * An arrow's words pulled off it into a text box of their own (or put back on) read the same on the page:
 * that text box becomes the arrow's label again. Returns the text boxes taken this way.
 */
function adoptLabels(A, B, map) {
  const taken = new Set();
  const matched = new Set([...map.keys(), ...map.values()]);
  const loose = (cells) => cells.filter((c) => c.kind === "vertex" && c.text && !matched.has(c) && (c.shape === "text" || c.fill === "none"));
  const gap = (c, e) => Math.max(e.box[0] - c.box[2], c.box[0] - e.box[2], e.box[1] - c.box[3], c.box[1] - e.box[3], 0);
  const take = (edge, text, cells) => {
    const best = loose(cells).filter((c) => !taken.has(c) && c.text === text).sort((x, y) => gap(x, edge) - gap(y, edge))[0];
    if (!best) return;
    taken.add(best);
    edge.text = [edge.text, best.text].filter(Boolean).join(" ");
    edge.labels = [...(edge.labels || []), best];
  };
  map.forEach((b, a) => {
    if (a.kind !== "edge" || a.text === b.text) return;
    if (a.text.startsWith(b.text)) take(b, a.text.slice(b.text.length).trim(), B);
    else if (b.text.startsWith(a.text)) take(a, b.text.slice(a.text.length).trim(), A);
  });
  // A new (or gone) arrow with no words of its own takes the loose text touching it as its label.
  // ponytail: boxes are end-to-end, so two arrows between the same boxes may swap labels; both land in one spot anyway.
  [A, B].forEach((cells) => cells.filter((e) => e.kind === "edge" && !e.text && !matched.has(e)).forEach((edge) => {
    const best = loose(cells).filter((c) => !taken.has(c) && gap(c, edge) <= 30).sort((x, y) => gap(x, edge) - gap(y, edge))[0];
    if (best) take(edge, best.text, cells);
  }));
  return taken;
}

/** Which words of each label were taken out and put in, one flag per word. */
function wordFlags(from, to) {
  const a = words(from).map((w) => w.toLowerCase()), b = words(to).map((w) => w.toLowerCase());
  const r = editScript(a, b);
  return r ? [[...r.removed], [...r.added]] : [a.map(() => 1), b.map(() => 1)];
}

/**
 * One page of the earlier file against one of this version's: { spots,
 * oldCells, newCells }. A spot is changes that belong together (a removed
 * box with its arrows), with its items and the words that say what changed.
 */
export function comparePages(oldPage, newPage) {
  const A = readPage(oldPage.els), B = readPage(newPage.els);
  const map = matchPage(A, B);
  const kept = new Set([...adoptLabels(A, B, map), ...map.values()]);
  const items = [];
  A.forEach((a) => {
    if (kept.has(a)) return;
    const b = map.get(a);
    if (!b) { items.push({ old: a, what: "removed", text: a.text, line: a.kind === "edge", hidden: a.hidden }); return; }
    const both = { old: a, neu: b, line: a.kind === "edge" };
    if (a.text !== b.text) {
      const [oldWords, newWords] = wordFlags(a.text, b.text);
      items.push({ ...both, what: "renamed", from: a.text, to: b.text, oldWords, newWords });
    }
    if (a.kind === "edge") {
      const flipped = map.get(a.from) === b.to && map.get(a.to) === b.from && b.from !== b.to;
      const hb = flipped ? [...b.heads].reverse() : b.heads;
      // Both ends on boxes, before and after, but not the same boxes: the arrow now joins other things.
      const rejoined = !flipped && [a.from, a.to, b.from, b.to].every(Boolean) && (map.get(a.from) !== b.from || map.get(a.to) !== b.to);
      if (rejoined) items.push({ ...both, what: "reconnected" });
      else if (hb[0] !== a.heads[0] || hb[1] !== a.heads[1]) items.push({ ...both, what: "turned" });
      else if (a.stroke !== b.stroke || a.dashed !== b.dashed) items.push({ ...both, what: "recoloured" });
    } else if (a.fill !== b.fill || a.stroke !== b.stroke || a.dashed !== b.dashed || a.shape !== b.shape) {
      items.push({ ...both, what: "recoloured" });
    }
  });
  B.forEach((b) => { if (!kept.has(b)) items.push({ neu: b, what: "added", text: b.text, line: b.kind === "edge", hidden: b.hidden }); });
  return { spots: group(items), oldCells: A, newCells: B };
}

/** Changes that belong together (a removed box with its arrows and its inner words) as one spot. */
function group(items) {
  const up = items.map((_, i) => i);
  const root = (i) => (up[i] === i ? i : (up[i] = root(up[i])));
  const owner = new Map();
  items.forEach((it, i) => { if (it.what === "removed" || it.what === "added") owner.set(it.old || it.neu, i); });
  items.forEach((it, i) => {
    if (it.what !== "removed" && it.what !== "added" && it.what !== "reconnected") return;
    const c = it.old || it.neu;
    [c.from, c.to, c.parentCell, it.neu?.from, it.neu?.to].forEach((x) => { if (owner.has(x)) up[root(i)] = root(owner.get(x)); });
  });
  const groups = new Map();
  items.forEach((it, i) => groups.set(root(i), [...(groups.get(root(i)) || []), it]));
  // Unseen cells only tie their spot together (a store's wrapper and the parts drawn inside it).
  return [...groups.values()].map((g) => g.filter((x) => !x.hidden)).filter((g) => g.length).map((g) => {
    const said = [];
    g.forEach((x) => {
      if (x.what === "renamed") said.push(`“${x.from}” → “${x.to}”`);
      else if (x.text && (x.what === "added" || x.what === "removed")) said.push(`${x.what === "added" ? "+" : "−"} “${x.text}”`);
    });
    const lines = g.filter((x) => x.line && (x.what === "added" || x.what === "removed")).length;
    if (lines) said.push(`${lines} line${lines > 1 ? "s" : ""} ${g.every((x) => x.what === "added") ? "added" : g.every((x) => x.what === "removed") ? "removed" : "added or removed"}`);
    g.filter((x) => x.what === "reconnected").forEach((x) => said.push(`“${x.old.text || "line"}” now joins a different box`));
    g.filter((x) => x.what === "turned").forEach((x) => said.push(`“${x.old.text || "line"}” points another way`));
    if (g.some((x) => x.what === "recoloured")) said.push("colour or line changed");
    return { said, items: g };
  });
}

/** A style with these keys set (draw.io styles are "name;key=value;…"). */
export function withStyle(style, set) {
  const kept = (style || "").split(";").filter((kv) => kv && !(kv.split("=")[0] in set));
  return [...kept, ...Object.entries(set).map(([k, v]) => `${k}=${v}`)].join(";") + ";";
}

/**
 * Which earlier page each page is: the same page of the same file, else the
 * same place in the same file, else the page sharing the most words with it.
 * Each page is { file, name, index, words: Set }; returns an index or -1.
 */
export function pairPages(mine, theirs) {
  const out = mine.map(() => -1), used = new Set();
  const take = (test) => mine.forEach((p, i) => {
    if (out[i] >= 0) return;
    const j = theirs.findIndex((q, k) => !used.has(k) && test(p, q));
    if (j >= 0) { out[i] = j; used.add(j); }
  });
  take((p, q) => p.file === q.file && p.name === q.name);
  take((p, q) => p.file === q.file && p.index === q.index);
  const shared = (a, b) => { const both = [...a].filter((w) => b.has(w)).length; return both / (a.size + b.size - both || 1); };
  const pairs = [];
  mine.forEach((p, i) => { if (out[i] < 0) theirs.forEach((q, j) => { if (!used.has(j)) pairs.push([shared(p.words, q.words), i, j]); }); });
  pairs.sort((x, y) => y[0] - x[0]).forEach(([s, i, j]) => { if (s >= 0.3 && out[i] < 0 && !used.has(j)) { out[i] = j; used.add(j); } });
  return out;
}
