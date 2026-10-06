import { captionsOnPage, figureFor } from "./figures.js";
import { greyOf, pictureDifference } from "./revisionCheck.js";

/*
 * The diagrams of two versions, compared picture by picture. Diagrams pasted
 * into a chapter are pictures, so the words diff cannot see them change.
 * Every picture of the earlier version is paired with one in this version
 * (Hungarian algorithm, cost = how different the two look), and each pair is
 * laid over each other to find the spots that changed.
 *
 * Measured on real chapters: two unrelated diagrams still share about half
 * their ink, so a pair more than 45% different was redone, not edited. A
 * picture re-exported at a slightly different size looks far off until it is
 * scaled back, so a pair more than 20% different is lined up first.
 */

/** Above this share of differing ink, a diagram was redone rather than edited. */
export const OVERHAUL = 0.45;
/** Above this, a pair is scaled and moved onto each other before it is judged. */
const ALIGN_ABOVE = 0.2;
/** Pictures are compared this wide, and shown twice as wide. */
const W = 400;
const SHOW = 2;

/** Hungarian algorithm on a square cost matrix: match[row] = column, least total cost. */
export function hungarian(a) {
  const n = a.length, INF = 1e18, u = new Float64Array(n + 1), v = new Float64Array(n + 1), p = new Int32Array(n + 1), way = new Int32Array(n + 1);
  for (let i = 1; i <= n; i++) {
    p[0] = i;
    let j0 = 0;
    const minv = new Float64Array(n + 1).fill(INF), used = new Uint8Array(n + 1);
    do {
      used[j0] = 1;
      const i0 = p[j0], row = a[i0 - 1];
      let delta = INF, j1 = 0;
      for (let j = 1; j <= n; j++) {
        if (used[j]) continue;
        const cur = row[j - 1] - u[i0] - v[j];
        if (cur < minv[j]) { minv[j] = cur; way[j] = j0; }
        if (minv[j] < delta) { delta = minv[j]; j1 = j; }
      }
      for (let j = 0; j <= n; j++) {
        if (used[j]) { u[p[j]] += delta; v[j] -= delta; } else minv[j] -= delta;
      }
      j0 = j1;
    } while (p[j0] !== 0);
    do { const j1 = way[j0]; p[j0] = p[j1]; j0 = j1; } while (j0);
  }
  const match = new Int32Array(n);
  for (let j = 1; j <= n; j++) match[p[j] - 1] = j - 1;
  return match;
}

/**
 * Each earlier picture's partner in this version (its index, or null), from
 * how different every pair looks. When one version has more pictures, the
 * extra ones stay unpaired at the cost of a redone diagram.
 */
export function pairUp(cost, newCount) {
  const n = Math.max(cost.length, newCount);
  const square = Array.from({ length: n }, (_, i) => Array.from({ length: n }, (_, j) => (i < cost.length && j < newCount ? cost[i][j] : OVERHAUL)));
  const match = hungarian(square);
  return cost.map((_, i) => (match[i] < newCount ? match[i] : null));
}

/** What a pair amounts to: redone, edited in some spots, or the same. */
export const kindOf = (mismatch, spots) => (mismatch > OVERHAUL ? "big" : spots ? "small" : "same");

const mul = (a, b) => [a[0] * b[0] + a[2] * b[1], a[1] * b[0] + a[3] * b[1], a[0] * b[2] + a[2] * b[3], a[1] * b[2] + a[3] * b[3], a[0] * b[4] + a[2] * b[5] + a[4], a[1] * b[4] + a[3] * b[5] + a[5]];

// A one-column flowchart is narrow but tall; a logo is neither.
const bigEnough = (b) => (b.width > 0.15 && b.height > 0.08) || (b.width > 0.08 && b.height > 0.3);
const shapeLike = (b) => b.width > 0.02 && b.height > 0.02;

/**
 * Where diagrams made of drawn lines and shapes sit (Canva, Word shapes), from
 * the boxes of every drawn path, as page fractions. Paths a small gap apart
 * are one drawing; a drawing counts when it is diagram-sized and holds at
 * least two real shapes, so underlines and table rules are left out. One
 * mostly covered by a pasted picture is that picture's frame, and one with
 * several long lines of text in it is a panel behind the page's text (Word's
 * comment pane), since a diagram's labels are short or sit in their own box.
 * ponytail: a table with shaded cells can still pass as a diagram; tell them
 * apart by the text grid inside if that shows up in real chapters.
 */
export function drawingAreas(paths, pictures = [], lines = [], gap = 0.015) {
  let groups = paths.filter((p) => !(p.width > 0.8 && p.height > 0.8)).map((p) => ({ ...p, shapes: shapeLike(p) ? 1 : 0, parts: shapeLike(p) ? [p] : [] }));
  const near = (a, b) => a.left - gap < b.left + b.width && b.left - gap < a.left + a.width && a.top - gap < b.top + b.height && b.top - gap < a.top + a.height;
  for (let merged = true; merged;) {
    merged = false;
    for (let i = 0; i < groups.length; i++) {
      for (let j = i + 1; j < groups.length; j++) {
        const a = groups[i], b = groups[j];
        if (!near(a, b)) continue;
        const left = Math.min(a.left, b.left), top = Math.min(a.top, b.top);
        groups[i] = { left, top, width: Math.max(a.left + a.width, b.left + b.width) - left, height: Math.max(a.top + a.height, b.top + b.height) - top, shapes: a.shapes + b.shapes, parts: [...a.parts, ...b.parts] };
        groups.splice(j, 1);
        merged = true;
        j = i;
      }
    }
  }
  // A label just under or beside a shape (an icon's name) is part of the drawing; a caption is not.
  groups = groups.map((g) => {
    const labels = lines.filter((l) => l.width < 0.5 * g.width && near(g, l) && !/^\s*fig(ure)?\b/i.test(l.str ?? ""));
    const all = [g, ...labels], left = Math.min(...all.map((b) => b.left)), top = Math.min(...all.map((b) => b.top));
    return { ...g, left, top, width: Math.max(...all.map((b) => b.left + b.width)) - left, height: Math.max(...all.map((b) => b.top + b.height)) - top };
  });
  const overlap = (a, b) => Math.max(0, Math.min(a.left + a.width, b.left + b.width) - Math.max(a.left, b.left)) * Math.max(0, Math.min(a.top + a.height, b.top + b.height) - Math.max(a.top, b.top));
  const inside = (g, l) => l.left + l.width / 2 > g.left && l.left + l.width / 2 < g.left + g.width && l.top + l.height / 2 > g.top && l.top + l.height / 2 < g.top + g.height;
  const boxed = (g, l) => g.parts.some((p) => p.width * p.height < 0.5 * g.width * g.height && inside(p, l));
  const prose = (g) => lines.filter((l) => l.width > 0.4 * g.width && inside(g, l) && !boxed(g, l)).length >= 3;
  groups = groups.filter((g) => bigEnough(g) && g.shapes >= 2 && !pictures.some((p) => overlap(g, p) > 0.5 * g.width * g.height) && !prose(g));
  // A little margin so a label just outside a shape comes along.
  return groups.map(({ left, top, width, height }) => {
    const l = Math.max(0, left - 0.01), t = Math.max(0, top - 0.01);
    return { left: l, top: t, width: Math.min(1, left + width + 0.01) - l, height: Math.min(1, top + height + 0.01) - t };
  });
}

/**
 * Every diagram on a page, as fractions of the page: pictures big enough to
 * be one (so a logo or an icon is left out), and drawings made of lines and
 * shapes. Read from what the page draws.
 */
export async function picturesOn(page, OPS) {
  const vp = page.getViewport({ scale: 1 });
  const ops = await page.getOperatorList();
  let ctm = [1, 0, 0, 1, 0, 0];
  const stack = [];
  const out = [];
  const paths = [];
  const onPage = (xs, ys) => ({ left: Math.min(...xs) / vp.width, top: 1 - Math.max(...ys) / vp.height, width: (Math.max(...xs) - Math.min(...xs)) / vp.width, height: (Math.max(...ys) - Math.min(...ys)) / vp.height });
  ops.fnArray.forEach((fn, i) => {
    const a = ops.argsArray[i];
    if (fn === OPS.save) stack.push(ctm);
    else if (fn === OPS.restore) ctm = stack.pop() || ctm;
    else if (fn === OPS.transform) ctm = mul(ctm, a);
    else if (fn === OPS.paintFormXObjectBegin && a?.[0]) { stack.push(ctm); ctm = mul(ctm, a[0]); }
    else if (fn === OPS.paintFormXObjectEnd) ctm = stack.pop() || ctm;
    else if (fn === OPS.paintImageXObject || fn === OPS.paintInlineImageXObject || fn === OPS.paintImageMaskXObject) {
      const box = onPage([ctm[4], ctm[4] + ctm[0], ctm[4] + ctm[2], ctm[4] + ctm[0] + ctm[2]], [ctm[5], ctm[5] + ctm[1], ctm[5] + ctm[3], ctm[5] + ctm[1] + ctm[3]]);
      if (bigEnough(box)) out.push(box);
    } else if (fn === OPS.constructPath && a?.[0] !== OPS.endPath && a?.[2] && Number.isFinite(a[2][0])) {
      // A drawn line or shape (a clipping path draws nothing and is skipped).
      const [x0, y0, x1, y1] = a[2], c = [[x0, y0], [x1, y0], [x0, y1], [x1, y1]].map(([x, y]) => [ctm[0] * x + ctm[2] * y + ctm[4], ctm[1] * x + ctm[3] * y + ctm[5]]);
      paths.push(onPage(c.map((p) => p[0]), c.map((p) => p[1])));
    }
  });
  if (!paths.length) return out;
  const text = await page.getTextContent().catch(() => ({ items: [] }));
  const lines = text.items.filter((t) => t.str?.trim()).map((t) => ({ str: t.str, left: t.transform[4] / vp.width, top: 1 - (t.transform[5] + t.height) / vp.height, width: t.width / vp.width, height: t.height / vp.height }));
  return [...out, ...drawingAreas(paths, out, lines)];
}

const canvasOf = (w, h) => Object.assign(document.createElement("canvas"), { width: Math.max(1, Math.round(w)), height: Math.max(1, Math.round(h)) });
const rgba = (c) => c.getContext("2d", { willReadFrequently: true }).getImageData(0, 0, c.width, c.height).data;
const plainGrey = (c) => {
  const d = rgba(c), g = new Float32Array(c.width * c.height);
  for (let i = 0; i < g.length; i++) g[i] = (d[i * 4] + d[i * 4 + 1] + d[i * 4 + 2]) / 3;
  return g;
};

/** A picture on a white canvas of a given size, its corner at (x, y). */
function onWhite(src, w, h, x = 0, y = 0, scale = 1) {
  const d = canvasOf(w, h), ctx = d.getContext("2d");
  ctx.fillStyle = "#fff";
  ctx.fillRect(0, 0, d.width, d.height);
  ctx.imageSmoothingQuality = "high";
  ctx.drawImage(src, x, y, src.width * scale, src.height * scale);
  return d;
}

/** One part of a page, drawn `width` pixels wide. */
async function drawBox(page, box, width) {
  const base = page.getViewport({ scale: 1 });
  const scale = width / (box.width * base.width);
  const c = canvasOf(width, box.height * base.height * scale);
  const ctx = c.getContext("2d");
  ctx.fillStyle = "#fff";
  ctx.fillRect(0, 0, c.width, c.height);
  const viewport = page.getViewport({ scale, offsetX: -box.left * base.width * scale, offsetY: -box.top * base.height * scale });
  await page.render({ canvasContext: ctx, viewport }).promise;
  return c;
}

/** The inked part of a picture: the white margin around it is not part of the diagram. */
function trimBox(c) {
  const d = rgba(c), w = c.width, h = c.height;
  let x0 = w, y0 = h, x1 = -1, y1 = -1;
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const i = (y * w + x) * 4;
      if (Math.min(d[i], d[i + 1], d[i + 2]) < 235) { x0 = Math.min(x0, x); x1 = Math.max(x1, x); y0 = Math.min(y0, y); y1 = Math.max(y1, y); }
    }
  }
  return x1 < 0 ? { x: 0, y: 0, w, h } : { x: x0, y: y0, w: x1 - x0 + 1, h: y1 - y0 + 1 };
}

/** Trimmed and drawn `width` wide, softened a little the way a re-export softens it. */
function normalised(c, width) {
  const t = trimBox(c), d = canvasOf(width, (t.h * width) / t.w), ctx = d.getContext("2d");
  ctx.imageSmoothingQuality = "high";
  ctx.filter = "blur(1px)";
  ctx.fillStyle = "#fff";
  ctx.fillRect(0, 0, d.width, d.height);
  ctx.drawImage(c, t.x, t.y, t.w, t.h, 0, 0, d.width, d.height);
  return d;
}

/**
 * How two pictures (both W wide) differ: the share of inked 4px cells that
 * differ, and the spots where they do - differing cells close together,
 * with specks dropped.
 */
function difference(a, b) {
  const h = Math.max(a.height, b.height), A = onWhite(a, W, h), B = onWhite(b, W, h), ga = greyOf(rgba(A), W, h), gb = greyOf(rgba(B), W, h);
  const { dx, dy } = pictureDifference(rgba(A), rgba(B), W, h);
  const cell = 4, cols = Math.floor(W / cell), rows = Math.floor(h / cell), diff = new Uint8Array(cols * rows), ink = new Uint8Array(cols * rows);
  for (let r = 0; r < rows; r++) {
    for (let q = 0; q < cols; q++) {
      let sa = 0, sb = 0, n = 0, mina = 255, minb = 255;
      for (let y = 0; y < cell; y++) {
        for (let x = 0; x < cell; x++) {
          const ya = r * cell + y, xa = q * cell + x, yb = ya + dy, xb = xa + dx;
          if (yb < 0 || xb < 0 || yb >= h || xb >= W) continue;
          const va = ga[ya * W + xa], vb = gb[yb * W + xb];
          sa += va; sb += vb; n++; mina = Math.min(mina, va); minb = Math.min(minb, vb);
        }
      }
      if (!n) continue;
      if (Math.abs(sa - sb) / n > 28) diff[r * cols + q] = 1;
      if (mina < 200 || minb < 200 || sa / n < 235 || sb / n < 235) ink[r * cols + q] = 1;
    }
  }
  // A thin grey line too faint to move a cell's average: grey where the other picture is blank all around.
  const faint = new Uint8Array(diff.length);
  const blankNear = (g, x, y) => {
    for (let yy = Math.max(0, y - 2); yy <= Math.min(h - 1, y + 2); yy++) {
      for (let xx = Math.max(0, x - 2); xx <= Math.min(W - 1, x + 2); xx++) if (g[yy * W + xx] < 248) return false;
    }
    return true;
  };
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < W; x++) {
      const yb = y + dy, xb = x + dx;
      if (yb < 0 || xb < 0 || yb >= h || xb >= W) continue;
      const k = Math.floor(y / cell) * cols + Math.floor(x / cell);
      if (k >= faint.length || diff[k]) continue;
      if ((ga[y * W + x] <= 240 && blankNear(gb, xb, yb)) || (gb[yb * W + xb] <= 240 && blankNear(ga, x, y))) faint[k]++;
    }
  }
  const grow = new Uint8Array(diff.length);
  for (let r = 0; r < rows; r++) {
    for (let q = 0; q < cols; q++) {
      if (faint[r * cols + q] >= 2) diff[r * cols + q] = 2;
      if (!diff[r * cols + q]) continue;
      for (let a2 = -2; a2 <= 2; a2++) for (let b2 = -2; b2 <= 2; b2++) {
        const rr = r + a2, qq = q + b2;
        if (rr >= 0 && qq >= 0 && rr < rows && qq < cols) grow[rr * cols + qq] = 1;
      }
    }
  }
  const seen = new Uint8Array(diff.length), spots = [];
  for (let i = 0; i < grow.length; i++) {
    if (!grow[i] || seen[i]) continue;
    let x0 = 1e9, y0 = 1e9, x1 = -1, y1 = -1, real = 0;
    const st = [i];
    seen[i] = 1;
    while (st.length) {
      const k = st.pop(), q = k % cols, r = (k / cols) | 0;
      if (diff[k]) real++;
      x0 = Math.min(x0, q); x1 = Math.max(x1, q); y0 = Math.min(y0, r); y1 = Math.max(y1, r);
      for (const [a2, b2] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
        const qq = q + a2, rr = r + b2, m = rr * cols + qq;
        if (qq >= 0 && rr >= 0 && qq < cols && rr < rows && grow[m] && !seen[m]) { seen[m] = 1; st.push(m); }
      }
    }
    if (real >= 6) spots.push({ x: x0 * cell, y: y0 * cell, w: (x1 - x0 + 1) * cell, h: (y1 - y0 + 1) * cell });
  }
  let inked = 0, differing = 0;
  for (let i = 0; i < ink.length; i++) if (ink[i]) { inked++; if (diff[i] === 1) differing++; }
  return { mismatch: inked ? differing / inked : 0, spots };
}

/** Best whole-pixel shift of b over a within ±R of (cx, cy); outside b counts as white. */
function bestShift(ga, w, h, gb, bw, bh, R, cx = 0, cy = 0) {
  let best = { v: Infinity };
  for (let oy = cy - R; oy <= cy + R; oy++) {
    for (let ox = cx - R; ox <= cx + R; ox++) {
      let v = 0;
      for (let y = 0; y < h; y++) {
        const yb = y - oy, inY = yb >= 0 && yb < bh;
        for (let x = 0; x < w; x++) { const xb = x - ox; v += Math.abs(ga[y * w + x] - (inY && xb >= 0 && xb < bw ? gb[yb * bw + xb] : 255)); }
        if (v >= best.v) break;
      }
      if (v < best.v) best = { v, ox, oy };
    }
  }
  return best;
}

/**
 * The scale (0.8-1.2) and place that lay b best over a, both W wide: tried
 * small first, then refined at full size. A changed frame or a note in the
 * margin moves the inked edge, so the edge alone cannot line them up.
 */
function alignment(a, b) {
  const k = 0.25, w4 = Math.round(W * k), h4 = Math.round(a.height * k), ga4 = plainGrey(onWhite(a, w4, h4, 0, 0, k));
  let coarse = { v: Infinity };
  for (let s = 0.8; s <= 1.2001; s += 0.02) {
    const bw = Math.round(b.width * s * k), bh = Math.round(b.height * s * k);
    const r = bestShift(ga4, w4, h4, plainGrey(onWhite(b, bw, bh, 0, 0, s * k)), bw, bh, 12);
    if (r.v < coarse.v) coarse = { ...r, s };
  }
  const gA = plainGrey(a);
  let fine = { v: Infinity };
  for (let s = coarse.s - 0.02; s <= coarse.s + 0.0201; s += 0.005) {
    const bw = Math.round(b.width * s), bh = Math.round(b.height * s);
    const r = bestShift(gA, W, a.height, plainGrey(onWhite(b, bw, bh, 0, 0, s)), bw, bh, 4, coarse.ox / k, coarse.oy / k);
    if (r.v < fine.v) fine = { ...r, s };
  }
  return { s: fine.s, x: fine.ox, y: fine.oy };
}

/** Every diagram of a document: where it is, its caption when it has one, and its picture. */
async function diagramsOf(pdf, OPS, alive) {
  const out = [];
  for (let n = 1; n <= pdf.numPages; n++) {
    if (!alive()) return out;
    const page = await pdf.getPage(n);
    const boxes = await picturesOn(page, OPS);
    if (!boxes.length) continue;
    const text = await page.getTextContent().catch(() => ({ items: [] }));
    const vp = page.getViewport({ scale: 1 });
    const captions = captionsOnPage(text.items, vp.height, n);
    for (const box of boxes) {
      const label = figureFor(captions, n, [{ top_position: box.top, left_position: box.left, highlight_width: box.width, highlight_height: box.height }]);
      const drawn = await drawBox(page, box, W * SHOW * 1.5);
      const t = trimBox(drawn);
      // Where the inked part sits on the page, to mark changed spots there.
      const ink = { left: box.left + (t.x / drawn.width) * box.width, top: box.top + (t.y / drawn.height) * box.height, width: (t.w / drawn.width) * box.width, height: (t.h / drawn.height) * box.height };
      const sharp = normalised(drawn, W * SHOW);
      // The words in it, boxed in the sharp picture: compared as words, and told apart from the lines.
      const all = [];
      for (const it of text.items) {
        if (!it.str.trim()) continue;
        const x0 = ((it.transform[4] / vp.width - ink.left) / ink.width) * sharp.width, base = ((1 - it.transform[5] / vp.height - ink.top) / ink.height) * sharp.height;
        const size = ((Math.hypot(it.transform[2], it.transform[3]) || it.height) / vp.height / ink.height) * sharp.height, per = (it.width / it.str.length / vp.width / ink.width) * sharp.width;
        for (const m of it.str.matchAll(/\S+/g)) all.push({ str: m[0], x: x0 + m.index * per, y: base - size, w: m[0].length * per, h: size * 1.25, lx: x0, lw: it.str.length * per });
      }
      const words = all.filter((it) => it.x + it.w > 0 && it.y + it.h > 0 && it.x < sharp.width && it.y < sharp.height);
      out.push({ page: n, label, title: captions.find((c) => c.label === label)?.title || "", sharp, small: normalised(sharp, W), ink, words });
    }
  }
  return out;
}

const pause = () => new Promise((ok) => setTimeout(ok));

/**
 * Two versions of a diagram drawn to the same scale. Each is stretched to W,
 * so one that gained a branch at its side would shrink and nothing would line
 * up; both are widened with white to the strip of the page they cover together.
 */
function sameScale(o, n) {
  const left = Math.min(o.ink.left, n.ink.left), width = Math.max(o.ink.left + o.ink.width, n.ink.left + n.ink.width) - left;
  if (Math.max(o.ink.width, n.ink.width) / Math.min(o.ink.width, n.ink.width) < 1.02) return [o, n];
  const widen = (d) => {
    const k = d.ink.width / width, SW = d.sharp.width, off = ((d.ink.left - left) / width) * SW;
    const sharp = onWhite(d.sharp, SW, d.sharp.height * k, off, 0, k);
    const small = canvasOf(W, (sharp.height * W) / SW), ctx = small.getContext("2d");
    ctx.imageSmoothingQuality = "high";
    ctx.filter = "blur(1px)";
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, small.width, small.height);
    ctx.drawImage(sharp, 0, 0, small.width, small.height);
    const words = d.words.map((it) => ({ ...it, x: off + it.x * k, y: it.y * k, w: it.w * k, h: it.h * k, lx: off + it.lx * k, lw: it.lw * k }));
    return { ...d, sharp, small, words, ink: { ...d.ink, left, width } };
  };
  return [widen(o), widen(n)];
}

/**
 * How far boxes of a diagram moved, in sharp pixels of the earlier frame: the
 * shift of each piece of writing found once in each version but somewhere
 * else, `fit` putting this version's onto the earlier frame. Most common first.
 */
export function movesOf(olds = [], news = [], fit = null) {
  const once = (ws) => {
    const m = new Map();
    for (const it of ws) m.set(it.str, m.has(it.str) ? null : it);
    return m;
  };
  const b = once(news), s = fit?.s ?? 1, fx = (fit?.x ?? 0) * SHOW, fy = (fit?.y ?? 0) * SHOW, count = new Map();
  for (const [str, o] of once(olds)) {
    const n = b.get(str);
    if (!o || !n) continue;
    const dx = Math.round(fx + n.x * s - o.x), dy = Math.round(fy + n.y * s - o.y);
    if (Math.abs(dx) > 2 || Math.abs(dy) > 2) count.set(`${dx},${dy}`, (count.get(`${dx},${dy}`) || 0) + 1);
  }
  return [...count].sort((p, q) => q[1] - p[1]).slice(0, 20).map(([k]) => k.split(",").map(Number));
}

/** Changed words next to each other on a line as one box, so one highlight covers them. */
export function joinWords(words) {
  const out = [];
  for (const it of [...words].sort((p, q) => p.y - q.y || p.x - q.x)) {
    const last = out[out.length - 1];
    if (last && Math.abs(last.y - it.y) <= 2 && it.x - (last.x + last.w) <= it.h) last.w = Math.max(last.w, it.x + it.w - last.x);
    else out.push({ ...it });
  }
  return out;
}

/**
 * The words taken out of a diagram (old) and put in (neu), both on the
 * earlier frame. A word is the same when the other version has it on the same
 * line nearby, where it was or where its box moved to: a longer word before
 * it on the line pushes it along, which is not a change.
 */
export function changedWords(olds = [], news = [], moves = []) {
  const shifts = [[0, 0], ...moves];
  const near = (it, o, sign) => shifts.some(([dx, dy]) => Math.abs(o.y - it.y - sign * dy) <= 4 && Math.abs(o.x - it.x - sign * dx) <= Math.max(8, it.h * 2));
  const kept = (it, others, sign) => others.some((o) => o.str === it.str && near(it, o, sign));
  const old = olds.filter((it) => !kept(it, news, 1)), neu = news.filter((it) => !kept(it, olds, -1));
  // A word rewritten in place (team_name -> team_title) keeps only the parts that differ.
  const taken = new Set(), trimmed = new Map();
  for (const o of old) {
    const n = neu.find((it) => !taken.has(it) && near(o, it, 1));
    if (!n) continue;
    taken.add(n);
    const [a, b] = changedLetters(o.str, n.str);
    trimmed.set(o, a).set(n, b);
  }
  const cut = (list) => list.flatMap((it) => {
    if (!trimmed.has(it)) return [it];
    const [from, to] = trimmed.get(it), per = it.w / it.str.length;
    return to > from ? [{ ...it, x: it.x + from * per, w: (to - from) * per }] : [];
  });
  return { old: cut(old), neu: cut(neu) };
}

/**
 * Where two spellings of a word differ, as [from, to] letter ranges of each.
 * Compared by name parts (team, _, name; VARCHAR, (, 50, )) so a highlight
 * covers "title", not the stray letters "titl" that happen to differ.
 */
export function changedLetters(a, b) {
  const split = (s) => s.match(/[A-Z]?[a-z]+|[A-Z]+(?![a-z])|\d+|[^A-Za-z\d]/g) ?? [s];
  const p = split(a), q = split(b);
  let pre = 0, suf = 0;
  while (pre < p.length && pre < q.length && p[pre] === q[pre]) pre++;
  while (suf < p.length - pre && suf < q.length - pre && p[p.length - 1 - suf] === q[q.length - 1 - suf]) suf++;
  const len = (parts, from, to) => parts.slice(from, to).join("").length;
  return [[len(p, 0, pre), a.length - len(p, p.length - suf)], [len(q, 0, pre), b.length - len(q, q.length - suf)]];
}

/**
 * Every diagram of the earlier version and this one, paired and compared.
 * Each comes back with its kind (small, big, same, new, removed), how much
 * differs, the changed spots (in the W-wide frame) and the two pictures,
 * drawn SHOW times sharper and lined up on each other.
 */
export async function compareFigures(oldPdf, newPdf, OPS, { onProgress = () => {}, alive = () => true } = {}) {
  onProgress("Finding the figures…");
  const olds = await diagramsOf(oldPdf, OPS, alive);
  const news = await diagramsOf(newPdf, OPS, alive);
  const cost = [];
  for (const [i, o] of olds.entries()) {
    if (!alive()) return [];
    onProgress(`Comparing figure ${i + 1} of ${olds.length}…`);
    cost.push(news.map((n) => difference(...sameScale(o, n).map((d) => d.small)).mismatch));
    await pause();
  }
  const partner = pairUp(cost, news.length);
  const figures = [];
  for (const [i, j] of partner.entries()) {
    if (j === null) {
      const o = olds[i];
      figures.push({ kind: "removed", label: o.label, title: o.title, oldPage: o.page, newPage: null, old: o.sharp, oldInk: o.ink });
      continue;
    }
    const [o, n] = sameScale(olds[i], news[j]);
    let near = n.small;
    let fit = null;
    if (cost[i][j] > ALIGN_ABOVE) {
      if (!alive()) return [];
      fit = alignment(o.small, n.small);
      // Tall enough for whatever was added below the earlier diagram.
      near = onWhite(n.small, W, Math.max(o.small.height, Math.ceil(fit.y + n.small.height * fit.s)), fit.x, fit.y, fit.s);
      await pause();
    }
    const d = difference(o.small, near);
    const h = Math.max(o.sharp.height, Math.ceil(fit ? fit.y * SHOW + n.sharp.height * fit.s : n.sharp.height));
    const fig = {
      kind: kindOf(d.mismatch, d.spots.length),
      pct: Math.round(d.mismatch * 100),
      spots: d.spots,
      label: n.label || o.label,
      title: n.title || o.title,
      oldPage: o.page,
      newPage: n.page,
      oldInk: o.ink,
      newInk: n.ink,
      oldH: o.small.height,
      newH: n.small.height,
      fit,
      old: onWhite(o.sharp, o.sharp.width, h),
      neu: fit ? onWhite(n.sharp, o.sharp.width, h, fit.x * SHOW, fit.y * SHOW, fit.s) : onWhite(n.sharp, o.sharp.width, h),
    };
    if (fig.kind === "small") {
      fig.moves = movesOf(o.words, n.words, fit);
      // This version's words on the earlier frame, like its picture.
      const s = fit?.s ?? 1, fx = (fit?.x ?? 0) * SHOW, fy = (fit?.y ?? 0) * SHOW;
      fig.oldWords = o.words;
      fig.newWords = n.words.map((it) => ({ ...it, x: fx + it.x * s, y: fy + it.y * s, w: it.w * s, h: it.h * s, lx: fx + it.lx * s, lw: it.lw * s }));
      fig.words = changedWords(fig.oldWords, fig.newWords, fig.moves);
      fig.changes = inkChanges(fig);
      // A spot that only held a moved box is not a change.
      const kept = fig.changes.spots.map((sp) => !(sp.moved && !sp.tone));
      fig.spots = fig.spots.filter((_, k) => kept[k]);
      fig.changes.spots = fig.changes.spots.filter((_, k) => kept[k]);
      if (!fig.spots.length && !fig.words.old.length && !fig.words.neu.length) { fig.kind = "same"; fig.pct = 0; }
      fig.tones = fig.changes.spots.map((sp) => sp.tone);
      fig.texts = fig.changes.spots.map((sp) => sp.text);
      fig.lines = fig.changes.spots.map((sp) => sp.text && { old: sp.oldLines, neu: sp.newLines });
      await pause();
    }
    figures.push(fig);
  }
  news.forEach((n, j) => {
    if (!partner.includes(j)) figures.push({ kind: "new", label: n.label, title: n.title, oldPage: null, newPage: n.page, neu: n.sharp, newInk: n.ink });
  });
  return figures.sort((a, b) => (a.newPage ?? Infinity) - (b.newPage ?? Infinity) || (a.oldPage ?? 0) - (b.oldPage ?? 0));
}

/**
 * The changed spots of every figure on the pages of both versions:
 * { marks, oldMarks }, each by page number. A changed label in an edited
 * diagram is marked like a changed word: highlighted where it is, and a thin
 * bar on the other version where it was taken out or put in. A changed shape
 * or arrow is not marked here: its own ink is coloured instead (tints). A
 * redone, new or removed diagram is boxed whole.
 */
export function figureMarks(figures) {
  const marks = {}, oldMarks = {};
  const clamp = (v) => Math.min(1, Math.max(0, v));
  const onPage = (ink, h, r) => {
    const left = clamp(ink.left + (r.x / W) * ink.width - 0.004), top = clamp(ink.top + (r.y / h) * ink.height - 0.004);
    return { left_position: left, top_position: top, highlight_width: clamp(ink.left + ((r.x + r.w) / W) * ink.width + 0.004) - left, highlight_height: clamp(ink.top + ((r.y + r.h) / h) * ink.height + 0.004) - top };
  };
  const whole = (ink) => onPage(ink, 1, { x: 0, y: 0, w: W, h: 1 });
  const add = (into, page, id, tone, label, rect) => (into[page] ||= []).push({ id, tone, label, rects: [rect] });
  const spot = (into, page, id, rects, bar) => rects.length && (into[page] ||= []).push({ id, diff: true, ...(bar && { [bar]: true }), rects: bar ? rects.map((r) => ({ ...r, highlight_width: 0.003 })) : rects });
  const shown = ([x0, y0, x1, y1]) => ({ x: x0 / SHOW, y: y0 / SHOW, w: (x1 - x0) / SHOW, h: (y1 - y0) / SHOW });
  figures.forEach((f, i) => {
    const id = `f${i}`;
    if (f.kind === "small") {
      // Spots are found in the earlier picture's frame; this version's was moved onto it first.
      const s = f.fit?.s ?? 1, dx = f.fit?.x ?? 0, dy = f.fit?.y ?? 0;
      const onOld = (r) => onPage(f.oldInk, f.oldH, r);
      const onNew = (r) => onPage(f.newInk, f.newH, { x: (r.x - dx) / s, y: (r.y - dy) / s, w: r.w / s, h: r.h / s });
      f.spots.forEach((r, k) => {
        const tone = f.tones?.[k] ?? null, lines = f.lines?.[k];
        if (f.texts?.[k] === false) return;
        // The words themselves when known, else the whole spot.
        const was = lines ? lines.old.map(shown) : tone === "added" ? [] : [r];
        const now = lines ? lines.neu.map(shown) : tone === "removed" ? [] : [r];
        spot(oldMarks, f.oldPage, `${id}-${k}`, (was.length ? was : now).map(onOld), was.length ? null : "inserted");
        spot(marks, f.newPage, `${id}-${k}`, (now.length ? now : was).map(onNew), now.length ? null : "removed");
      });
      // Words taken out red on the earlier version, words put in green on this one.
      const word = (it) => ({ x: it.x / SHOW, y: it.y / SHOW, w: it.w / SHOW, h: it.h / SHOW });
      spot(oldMarks, f.oldPage, `${id}-w`, joinWords(f.words?.old ?? []).map(word).map(onOld));
      spot(marks, f.newPage, `${id}-w`, joinWords(f.words?.neu ?? []).map(word).map(onNew));
    } else if (f.kind === "big") {
      add(oldMarks, f.oldPage, id, "rewritten", "redone", whole(f.oldInk));
      add(marks, f.newPage, id, "rewritten", "redone", whole(f.newInk));
    } else if (f.kind === "removed") add(oldMarks, f.oldPage, id, "removed", "− removed figure", whole(f.oldInk));
    else if (f.kind === "new") add(marks, f.newPage, id, "added", "+ new figure", whole(f.newInk));
  });
  return { marks, oldMarks };
}

/**
 * Where the one-picture view of an edited diagram goes on this version's
 * page, to lay over the diagram there. The picture is in the earlier
 * diagram's frame, `pictureHeight` tall at SHOW times, so it is moved back
 * the way this version's diagram was moved onto that frame.
 */
export function overlayRect(f, pictureHeight) {
  const s = f.fit?.s ?? 1, dx = f.fit?.x ?? 0, dy = f.fit?.y ?? 0, ink = f.newInk;
  return {
    left_position: ink.left + (-dx / s / W) * ink.width,
    top_position: ink.top + (-dy / s / f.newH) * ink.height,
    highlight_width: ink.width / s,
    highlight_height: (pictureHeight / SHOW / s / f.newH) * ink.height,
  };
}

/** Where a picture in the earlier diagram's frame, `pictureHeight` tall at SHOW times, goes on its page. */
export function oldOverlayRect(f, pictureHeight) {
  const ink = f.oldInk;
  return { left_position: ink.left, top_position: ink.top, highlight_width: ink.width, highlight_height: (pictureHeight / SHOW / f.oldH) * ink.height };
}

/**
 * Whether the changed ink in a box is writing: several small marks (letters)
 * making up most of it. A shape or an arrow is one or two big strokes, and a
 * new box with its label is mostly box. Straight rules and specks are left
 * out, so a heading whose dividing line moved is still writing.
 */
export function textLike(changed, w, box, letter = 30) {
  const parts = inkParts(changed, w, box, (v) => v !== 0).filter((p) => p.hy - p.ly >= 3 && p.hx - p.lx >= 3);
  const total = parts.reduce((t, p) => t + p.n, 0);
  const small = parts.filter((p) => isLetter(p, letter)).reduce((t, p) => t + p.n, 0);
  return parts.length >= 3 && small >= 0.7 * total;
}

/** The joined-up marks (3 pixels or more) in a box whose value passes `is`: { n, lx, hx, ly, hy }. */
function inkParts(changed, w, [x0, y0, x1, y1], is) {
  const seen = new Uint8Array(changed.length), parts = [];
  for (let y = y0; y < y1; y++) {
    for (let x = x0; x < x1; x++) {
      const k0 = y * w + x;
      if (!is(changed[k0]) || seen[k0]) continue;
      const stack = [k0];
      let n = 0, lx = x, hx = x, ly = y, hy = y;
      seen[k0] = 1;
      while (stack.length) {
        const k = stack.pop(), kx = k % w, ky = (k - kx) / w;
        n++; lx = Math.min(lx, kx); hx = Math.max(hx, kx); ly = Math.min(ly, ky); hy = Math.max(hy, ky);
        for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
          const xx = kx + dx, yy = ky + dy, m = yy * w + xx;
          if (xx >= x0 && yy >= y0 && xx < x1 && yy < y1 && is(changed[m]) && !seen[m]) { seen[m] = 1; stack.push(m); }
        }
      }
      if (n >= 3) parts.push({ n, lx, hx, ly, hy });
    }
  }
  return parts;
}

// A letter, or a word whose letters ran together: short, solid, and no longer than a long word.
const isLetter = (p, letter) => {
  const h = p.hy - p.ly + 1, w = p.hx - p.lx + 1;
  return h >= 4 && h <= letter && w <= 15 * h && p.n >= 0.3 * w * h;
};

/**
 * The lines of writing made of the changed ink of one kind (`value`) in a
 * box, as tight [x0, y0, x1, y1] boxes: letters side by side on the same
 * line are one box, so the highlight sits on the words and not the space
 * around them. Given the picture's `ink`, a mark is judged whole: a word
 * moved a few pixels changes only its top and bottom edges, which alone
 * would look like lines.
 */
export function textLines(changed, w, box, value, letter = 30, ink = null) {
  const touched = (p) => {
    for (let y = p.ly; y <= p.hy; y++) for (let x = p.lx; x <= p.hx; x++) if (changed[y * w + x] === value) return true;
    return false;
  };
  const parts = ink ? inkParts(ink, w, box, (v) => v !== 0).filter(touched) : inkParts(changed, w, box, (v) => v === value);
  const lines = [];
  for (const p of parts.filter((q) => isLetter(q, letter)).sort((a, b) => a.lx - b.lx)) {
    const line = lines.find((l) => Math.min(l[3], p.hy + 1) - Math.max(l[1], p.ly) > 0 && p.lx - l[2] < Math.max(8, l[3] - l[1]) * 1.5);
    if (line) {
      line[1] = Math.min(line[1], p.ly); line[2] = Math.max(line[2], p.hx + 1); line[3] = Math.max(line[3], p.hy + 1);
    } else lines.push([p.lx, p.ly, p.hx + 1, p.hy + 1]);
  }
  return lines;
}

/**
 * Whether a spot has about as much removed as added ink: something rewritten
 * or moved in place, where red over green would be unreadable.
 */
export function overlapping(removed, added) {
  const less = Math.min(removed, added);
  return less >= 30 && less >= 0.25 * Math.max(removed, added);
}

/** What happened at a spot: rewritten in place, removed, added, or nothing worth a box. */
export function spotTone(removed, added) {
  if (overlapping(removed, added)) return "rewritten";
  if (Math.max(removed, added) < 30) return null;
  return removed >= added ? "removed" : "added";
}

const crop = (src, [x0, y0, x1, y1]) => {
  const c = canvasOf(x1 - x0, y1 - y0);
  c.getContext("2d").drawImage(src, x0, y0, c.width, c.height, 0, 0, c.width, c.height);
  return c;
};

/**
 * Which ink in each changed spot is only in the earlier picture (1) or only
 * in this one (2), and what happened at the spot. A spot rewritten in place
 * is left unmarked: it is drawn as old above new instead.
 */
function inkChanges(f) {
  const w = f.old.width, h = f.old.height, a = rgba(f.old), b = rgba(f.neu);
  const inkA = new Uint8Array(w * h), inkB = new Uint8Array(w * h), mark = new Uint8Array(w * h);
  for (let k = 0; k < w * h; k++) {
    // Light enough to catch a thin grey line, which a re-export draws about 206.
    inkA[k] = (a[k * 4] + a[k * 4 + 1] + a[k * 4 + 2]) / 3 < 225;
    inkB[k] = (b[k * 4] + b[k * 4 + 1] + b[k * 4 + 2]) / 3 < 225;
  }
  // A line one or two pixels off in the other picture is the same line.
  const near = (ink, x, y) => {
    for (let dy = -2; dy <= 2; dy++) for (let dx = -2; dx <= 2; dx++) {
      const xx = x + dx, yy = y + dy;
      if (xx >= 0 && yy >= 0 && xx < w && yy < h && ink[yy * w + xx]) return true;
    }
    return false;
  };
  const boxes = f.spots.map((r) => [Math.max(0, r.x * SHOW - 4), Math.max(0, r.y * SHOW - 4), Math.min(w, (r.x + r.w) * SHOW + 4), Math.min(h, (r.y + r.h) * SHOW + 4)]);
  // The words are compared as words (changedWords), so only the lines and shapes are marked here.
  const words = new Uint8Array(w * h);
  for (const [list, v] of [[f.oldWords, 1], [f.newWords, 2]]) {
    for (const it of list ?? []) {
      // Its whole line of text: where one word ends is only guessed from its letters.
      for (let y = Math.max(0, Math.floor(it.y) - 1); y < Math.min(h, Math.ceil(it.y + it.h) + 1); y++) {
        for (let x = Math.max(0, Math.floor(it.lx) - 2); x < Math.min(w, Math.ceil(it.lx + it.lw) + 2); x++) words[y * w + x] |= v;
      }
    }
  }
  // Ink only in one picture, anywhere: 1 only in the earlier one, 2 only in this one.
  const only = new Uint8Array(w * h);
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const k = y * w + x;
      if (inkA[k] && !(words[k] & 1) && !near(inkB, x, y)) only[k] = 1;
      else if (inkB[k] && !(words[k] & 2) && !near(inkA, x, y)) only[k] = 2;
    }
  }
  for (const box of boxes) for (let y = box[1]; y < box[3]; y++) for (let x = box[0]; x < box[2]; x++) mark[y * w + x] = only[y * w + x];
  // A box that only moved: its ink gone from one place turns up, as new ink, where its writing moved to.
  const marked = (v, x, y) => {
    for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
      const xx = x + dx, yy = y + dy;
      if (xx >= 0 && yy >= 0 && xx < w && yy < h && only[yy * w + xx] === v) return true;
    }
    return false;
  };
  const moved = new Uint8Array(w * h);
  for (const [mx, my] of f.moves ?? []) {
    for (const box of boxes) {
      for (let y = box[1]; y < box[3]; y++) {
        for (let x = box[0]; x < box[2]; x++) {
          const k = y * w + x;
          if ((mark[k] === 1 && marked(2, x + mx, y + my)) || (mark[k] === 2 && marked(1, x - mx, y - my))) moved[k] = 1;
        }
      }
    }
  }
  // A box that only grew or shrank: a row put in or taken out slides its bottom edge (and the sides
  // along that row) straight down or up. Such a line is the other picture's, slid; the row's words
  // already show what changed.
  const inkAt = (ink, x, y) => {
    for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
      const xx = x + dx, yy = y + dy;
      if (xx >= 0 && yy >= 0 && xx < w && yy < h && ink[yy * w + xx]) return true;
    }
    return false;
  };
  const slides = [];
  for (let d = 3; d <= 80; d++) slides.push([0, d], [0, -d], [d, 0], [-d, 0]);
  for (const part of changedParts(mark, moved, w, h)) {
    // Only straight lines and filled areas slide like that; a slanted line (a new diamond's edge)
    // only happens to land on some other line. Most of a straight one lies in long level runs.
    const own = new Set(part), run = (k, step) => {
      let n = 1;
      for (let m = k + step; own.has(m) && n < 5; m += step) n++;
      for (let m = k - step; own.has(m) && n < 5; m -= step) n++;
      return n >= 5;
    };
    if (part.filter((k) => run(k, 1) || run(k, w)).length < 0.6 * part.length) continue;
    const other = mark[part[0]] === 1 ? inkB : inkA;
    const fits = ([sx, sy]) => {
      let misses = 0;
      for (const k of part) if (!inkAt(other, (k % w) + sx, Math.floor(k / w) + sy) && ++misses > 0.1 * part.length) return false;
      return true;
    };
    if (slides.some(fits)) for (const k of part) moved[k] = 1;
  }
  const spots = boxes.map((box) => {
    let removed = 0, added = 0, shifted = 0;
    for (let y = box[1]; y < box[3]; y++) {
      for (let x = box[0]; x < box[2]; x++) {
        const k = y * w + x;
        if (moved[k]) { mark[k] = 0; shifted++; }
        else if (mark[k] === 1) removed++;
        else if (mark[k] === 2) added++;
      }
    }
    const tone = spotTone(removed, added);
    // What is left of a moved box is specks: nothing to colour.
    if (shifted && !tone) for (let y = box[1]; y < box[3]; y++) mark.fill(0, y * w + box[0], y * w + box[2]);
    const text = textLike(mark, w, box);
    return { box, tone, text, moved: shifted > 0, ...(text && { oldLines: textLines(mark, w, box, 1, 30, inkA), newLines: textLines(mark, w, box, 2, 30, inkB) }) };
  });
  // The pop-up draws a rewritten spot as old above new, so its marks are kept only for colouring the pages.
  // Coloured on the pages: everything but the words of a label, which get the word-style highlight.
  const both = mark.slice();
  for (const s of spots) {
    if (!s.text) continue;
    for (const [lines, v] of [[s.oldLines, 1], [s.newLines, 2]]) {
      for (const [x0, y0, x1, y1] of lines) for (let y = y0; y < y1; y++) for (let x = x0; x < x1; x++) if (both[y * w + x] === v) both[y * w + x] = 0;
    }
  }
  for (const { box, tone } of spots) if (tone === "rewritten") for (let y = box[1]; y < box[3]; y++) mark.fill(0, y * w + box[0], y * w + box[2]);
  return { a, b, w, h, mark, both, spots, inkA, inkB, words };
}

/** The joined-up runs of changed ink (marked, and not already moved), each as its pixel indices, all of one kind. */
function changedParts(mark, moved, w, h) {
  const seen = new Uint8Array(mark.length), parts = [];
  for (let k0 = 0; k0 < mark.length; k0++) {
    if (!mark[k0] || moved[k0] || seen[k0]) continue;
    const v = mark[k0], stack = [k0], part = [];
    seen[k0] = 1;
    while (stack.length) {
      const k = stack.pop(), x = k % w, y = (k - x) / w;
      part.push(k);
      for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
        const xx = x + dx, yy = y + dy, m = yy * w + xx;
        if (xx >= 0 && yy >= 0 && xx < w && yy < h && mark[m] === v && !moved[m] && !seen[m]) { seen[m] = 1; stack.push(m); }
      }
    }
    parts.push(part);
  }
  return parts;
}

/**
 * The inside of every closed shape in a spot: blank pixels that cannot reach
 * the edge of the box without crossing ink, as pixel indices. Holes smaller
 * than `min` (the inside of an "o" or "a") are left out.
 */
export function enclosed(ink, w, box, min = 200) {
  return holes(ink, w, box, min).flat();
}

/** The same insides, one list of pixel indices per shape. */
function holes(ink, w, [x0, y0, x1, y1], min = 200) {
  const bw = x1 - x0, bh = y1 - y0, seen = new Uint8Array(bw * bh), out = [];
  const blank = (p) => !ink[(y0 + Math.floor(p / bw)) * w + x0 + (p % bw)];
  for (let p0 = 0; p0 < bw * bh; p0++) {
    if (seen[p0] || !blank(p0)) continue;
    const stack = [p0], got = [];
    let edge = false;
    seen[p0] = 1;
    while (stack.length) {
      const p = stack.pop(), x = p % bw, y = (p - x) / bw;
      got.push((y0 + y) * w + x0 + x);
      if (x === 0 || y === 0 || x === bw - 1 || y === bh - 1) edge = true;
      for (const q of [x > 0 ? p - 1 : -1, x < bw - 1 ? p + 1 : -1, y > 0 ? p - bw : -1, y < bh - 1 ? p + bw : -1]) {
        if (q >= 0 && !seen[q] && blank(q)) { seen[q] = 1; stack.push(q); }
      }
    }
    if (!edge && got.length >= min) out.push(got);
  }
  return out;
}

/**
 * The pair as one picture: faded where both agree, and what changed glowing
 * on top, red = only in the earlier one, green = only in this one, with a
 * closed shape that was added or removed filled in. A spot rewritten in place
 * shows its new ink green where it is and its old ink red just above it, and
 * is given as a before/after close-up too.
 */
export function highlighted(f) {
  const { a, b, w, h, mark, spots, inkA, inkB } = (f.changes ??= inkChanges(f));
  const boxes = spots.filter((s) => s.tone === "rewritten").map((s) => s.box);
  const grey = (p, k) => (p[k * 4] + p[k * 4 + 1] + p[k * 4 + 2]) / 3;
  const base = new ImageData(w, h), red = new ImageData(w, h), green = new ImageData(w, h);
  for (let k = 0; k < w * h; k++) {
    const faded = 255 - (255 - Math.min(grey(a, k), grey(b, k))) * 0.3;
    base.data.set([faded, faded, faded, 255], k * 4);
    if (mark[k] === 1) red.data.set([220, 38, 38, 255], k * 4);
    else if (mark[k] === 2) green.data.set([22, 163, 74, 255], k * 4);
  }
  for (const { box, tone } of spots) {
    if (tone === "added") for (const k of enclosed(inkB, w, box)) green.data.set([34, 197, 94, 70], k * 4);
    if (tone === "removed") for (const k of enclosed(inkA, w, box)) red.data.set([239, 68, 68, 70], k * 4);
  }
  // A rewritten spot: its new ink where it is, its old ink just above it (or below when there is no room).
  for (const [x0, y0, x1, y1] of boxes) {
    const bh = y1 - y0, top = y0 - bh - 2 >= 0 ? y0 - bh - 2 : Math.min(h - bh, y1 + 2);
    for (let y = 0; y < bh; y++) {
      for (let x = x0; x < x1; x++) {
        const k = (y0 + y) * w + x, t = (top + y) * w + x;
        base.data.set([255, 255, 255, 255], k * 4);
        base.data.set([255, 245, 245, 255], t * 4);
        if (inkB[k]) green.data.set([22, 163, 74, 255], k * 4);
        if (inkA[k]) red.data.set([220, 38, 38, 255], t * 4);
      }
    }
  }
  const c = canvasOf(w, h), ctx = c.getContext("2d");
  ctx.putImageData(base, 0, 0);
  for (const [layer, glow] of [[red, "rgba(239,68,68,.9)"], [green, "rgba(34,197,94,.9)"]]) {
    const l = canvasOf(w, h);
    l.getContext("2d").putImageData(layer, 0, 0);
    ctx.shadowColor = glow;
    ctx.shadowBlur = 12;
    ctx.drawImage(l, 0, 0);
    ctx.drawImage(l, 0, 0);
    ctx.shadowBlur = 0;
    ctx.drawImage(l, 0, 0);
  }
  for (const [list, colour] of [[f.words?.old, "rgba(207,34,46,.3)"], [f.words?.neu, "rgba(34,197,94,.3)"]]) {
    ctx.fillStyle = colour;
    for (const it of list ?? []) ctx.fillRect(it.x, it.y, it.w, it.h);
  }
  const pad = (box) => [Math.max(0, box[0] - 12), Math.max(0, box[1] - 12), Math.min(w, box[2] + 12), Math.min(h, box[3] + 12)];
  return { picture: c, closeUps: boxes.map((box) => ({ old: crop(f.old, pad(box)), neu: crop(f.neu, pad(box)) })) };
}

/**
 * The shapes and arrows that changed, coloured where they sit, on clear
 * pictures to lay over each version's diagram: { added } in green over this
 * version, { removed } in red over the earlier one, each null when nothing
 * is there. The picture is laid on the page with mix-blend-mode: lighten, so
 * the page's own dark lines turn the colour and its white stays white: the
 * shape itself is recoloured, at the page's own sharpness. The colour reaches
 * a little past each line, so being a pixel off still lands on it. The words
 * of a label are left to the word-style highlight. The inside of a changed
 * closed shape is filled in a light shade ({ addedWash, removedWash }), laid
 * with mix-blend-mode: multiply so the text in it stays as it is.
 */
export function tints(f) {
  const { a, b, w, h, both: mark, spots, inkA, inkB, words } = (f.changes ??= inkChanges(f));
  // The changed ink, carried a few pixels on along the version's own lines: where a new line
  // touches or crosses an old one, or runs past its spot, it would otherwise break off there.
  const GROW = 6;
  // Specks of a few pixels are a line drawn a hair differently, not a change: grown, they would
  // turn into a stray dash on an unchanged line.
  const real = new Uint8Array(w * h);
  for (const part of changedParts(mark, new Uint8Array(w * h), w, h)) if (part.length >= 10) for (const k of part) real[k] = 1;
  const changed = (tone, value) => {
    const ink = value === 2 ? inkB : inkA, dist = new Int8Array(w * h).fill(-1), queue = [];
    for (const s of spots) {
      if (s.tone !== tone && s.tone !== "rewritten") continue;
      const [x0, y0, x1, y1] = s.box;
      for (let y = y0; y < y1; y++) {
        for (let x = x0; x < x1; x++) {
          const k = y * w + x;
          if (mark[k] === value && real[k] && dist[k] < 0) { dist[k] = 0; queue.push(k); }
        }
      }
    }
    for (let i = 0; i < queue.length; i++) {
      const k = queue[i], x = k % w, y = (k - x) / w;
      if (dist[k] >= GROW) continue;
      for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
        const xx = x + dx, yy = y + dy, m = yy * w + xx;
        if (xx >= 0 && yy >= 0 && xx < w && yy < h && ink[m] && dist[m] < 0) { dist[m] = dist[k] + 1; queue.push(m); }
      }
    }
    return queue;
  };
  const layer = (...sets) => {
    if (!sets.some(([pixels]) => pixels.length)) return null;
    const img = new ImageData(w, h);
    for (const [pixels, colour, R, picture] of sets) {
      const px = [...colour, 255];
      for (const k of pixels) {
        const x = k % w, y = (k - x) / w;
        // Given the version's picture (for the multiply layer, where white changes nothing), mixed in
        // as strongly as the line is dark there, so it keeps its own thinness.
        if (picture) {
          const t = Math.min(1, (255 - (picture[k * 4] + picture[k * 4 + 1] + picture[k * 4 + 2]) / 3) / 64), at = k * 4;
          const under = img.data[at + 3] ? img.data.slice(at, at + 3) : [255, 255, 255];
          img.data.set([0, 1, 2].map((i) => under[i] * (1 - t) + colour[i] * t).concat(255), at);
          continue;
        }
        for (let yy = Math.max(0, y - R); yy <= Math.min(h - 1, y + R); yy++) {
          for (let xx = Math.max(0, x - R); xx <= Math.min(w - 1, x + R); xx++) {
            if ((xx - x) ** 2 + (yy - y) ** 2 <= R * R) img.data.set(px, (yy * w + xx) * 4);
          }
        }
      }
    }
    const c = canvasOf(w, h);
    c.getContext("2d").putImageData(img, 0, 0);
    return c;
  };
  // A closed shape (a box, diamond, cloud) whose outline is mostly changed is taken whole, like a
  // filled icon: all of its outline is coloured and its inside filled. Pieces of it that only look
  // like an old line shifted (the bottom of a cloud drawn bigger) are not left out, and a box that
  // merely moved, its outline unchanged, stays plain.
  // Its words are taken out first, so a label inside it is part of its inside, not of its outline.
  // Both are thickened a pixel: a thin pale outline (a yellow diamond's) comes out of the scan with
  // pinholes the inside would leak through. The outline is filled too, since a pale one cannot be
  // recoloured the way a dark line is.
  const thick = (on) => {
    const out = new Uint8Array(w * h);
    for (let k = 0; k < w * h; k++) {
      if (!on[k]) continue;
      const x = k % w;
      for (const m of [k, k - 1, k + 1, k - w, k + w, k - w - 1, k - w + 1, k + w - 1, k + w + 1]) {
        if (m >= 0 && m < w * h && Math.abs((m % w) - x) <= 1) out[m] = 1;
      }
    }
    return out;
  };
  // Each shape's inside, with the words written in it. With the letters taken out, small insides
  // (an actor's head) are shapes too, not the inside of an "o".
  const shapes = (version, bit, items) => {
    const ink = thick(version.map((v, k) => v && !(words[k] & bit))), list = holes(ink, w, [0, 0, w, h], 40);
    const id = new Int32Array(w * h).fill(-1), labels = list.map(() => new Set());
    list.forEach((hole, i) => { for (const k of hole) id[k] = i; });
    for (const it of items ?? []) {
      const k = Math.round(it.y + it.h / 2) * w + Math.round(it.x + it.w / 2);
      if (id[k] >= 0) labels[id[k]].add(it.str);
    }
    return { ink, list: list.map((hole, i) => ({ hole, labels: labels[i] })) };
  };
  const shapesA = shapes(inkA, 1, f.oldWords), shapesB = shapes(inkB, 2, f.newWords);
  // A shape the other version also has, the same size and with a word of the same label: it only moved.
  const movedShape = ({ hole, labels }, others) => others.some((o) => o.hole.length > 0.85 * hole.length && o.hole.length < 1.18 * hole.length && [...o.labels].some((s) => labels.has(s)));
  const whole = ({ ink, list }, others, grown) => {
    const isChanged = new Uint8Array(w * h), edges = [], fill = [], rim = new Uint8Array(w * h), still = new Uint8Array(w * h);
    for (const k of grown) isChanged[k] = 1;
    const changedNear = thick(isChanged);
    for (const shape of list) {
      const { hole } = shape, edge = new Set();
      for (const k of hole) {
        const x = k % w;
        for (const m of [k - 1, k + 1, k - w, k + w]) if (m >= 0 && m < w * h && Math.abs((m % w) - x) <= 1 && ink[m]) edge.add(m);
      }
      // A shape that only moved: none of it is coloured, its outline included.
      if (movedShape(shape, others)) {
        for (const k of edge) still[k] = 1;
        continue;
      }
      let hits = 0;
      for (const k of edge) hits += changedNear[k];
      if (hits < 0.6 * edge.size) continue;
      for (const k of edge) {
        if (!isChanged[k]) edges.push(k);
        rim[k] = 1;
      }
      for (const k of hole) fill.push(k);
    }
    // The fill reaches across the whole outline, so a pale one is covered too.
    const across = thick(thick(rim)), moved = thick(thick(thick(thick(still))));
    for (let k = 0; k < w * h; k++) if (across[k]) fill.push(k);
    return { lines: grown.filter((k) => !moved[k]).concat(edges), fill };
  };
  // A change that is neither a filled shape nor a plain line (a shape drawn bigger, one whose inside
  // could not be found) would be only a thin coloured outline, easy to miss: the area around it is
  // washed instead, so nothing changed goes unseen: the whole shape it is part of when there is one
  // about its size, else the box around it.
  const areas = ({ lines, fill }, { list }) => {
    const on = new Uint8Array(w * h), filled = new Uint8Array(w * h), id = new Int32Array(w * h).fill(-1), washed = new Uint8Array(w * h), boxes = [];
    for (const k of fill) filled[k] = 1;
    // What a filled shape already shows is left out: an arrow into it is then a line of its own.
    const nearFill = thick(thick(thick(filled)));
    for (const k of lines) if (!nearFill[k]) on[k] = 1;
    list.forEach(({ hole }, i) => { for (const k of hole) id[k] = i; });
    for (const part of changedParts(thick(on), new Uint8Array(w * h), w, h)) {
      if (part.length < 30) continue;
      // A straight line (an arrow): most of it lies along one axis.
      let mx = 0, my = 0, sxx = 0, syy = 0, sxy = 0;
      for (const k of part) { mx += k % w; my += Math.floor(k / w); }
      mx /= part.length; my /= part.length;
      for (const k of part) { const dx = (k % w) - mx, dy = Math.floor(k / w) - my; sxx += dx * dx; syy += dy * dy; sxy += dx * dy; }
      const t = Math.atan2(2 * sxy, sxx - syy) / 2, sin = Math.sin(t), cos = Math.cos(t);
      if (part.filter((k) => Math.abs(((Math.floor(k / w) - my) * cos) - (((k % w) - mx) * sin)) <= 4).length >= 0.85 * part.length) continue;
      let x0 = w, y0 = h, x1 = 0, y1 = 0;
      for (const k of part) { const x = k % w, y = Math.floor(k / w); x0 = Math.min(x0, x); y0 = Math.min(y0, y); x1 = Math.max(x1, x); y1 = Math.max(y1, y); }
      // Solid ink (an icon) is plain to see once it is coloured.
      if (part.length > 16 * (x1 - x0 + y1 - y0 + 2)) continue;
      // The shapes it borders, the smallest first: an outline borders its own inside and the one around it.
      const hits = new Map();
      for (const k of part) {
        const x = k % w, near = new Set();
        for (let dy = -3; dy <= 3; dy++) for (let dx = -3; dx <= 3; dx++) {
          const m = k + dy * w + dx;
          if (m >= 0 && m < w * h && Math.abs((m % w) - x) <= 3 && id[m] >= 0) near.add(id[m]);
        }
        for (const i of near) hits.set(i, (hits.get(i) ?? 0) + 1);
      }
      const [shape] = [...hits].filter(([i, n]) => n >= 0.3 * part.length && list[i].hole.length <= 2 * (x1 - x0 + 1) * (y1 - y0 + 1))
        .map(([i]) => list[i].hole).sort((p, q) => p.length - q.length);
      if (shape) {
        const inside = new Uint8Array(w * h);
        for (const k of shape) inside[k] = 1;
        const across = thick(thick(thick(inside)));
        for (let k = 0; k < w * h; k++) if (across[k]) washed[k] = 1;
      } else if (part.length >= 0.15 * (x1 - x0 + 1) * (y1 - y0 + 1)) {
        // A sparse part (a bent connector across the page) stays a coloured line: its box would be mostly empty.
        boxes.push([part, Math.max(0, x0 - 4), Math.max(0, y0 - 4), Math.min(w - 1, x1 + 4), Math.min(h - 1, y1 + 4)]);
      }
    }
    // A piece of a shape washed whole (a bump of a bigger cloud) needs no box of its own.
    const nearWashed = thick(thick(thick(washed)));
    for (const [part, x0, y0, x1, y1] of boxes) {
      if (part.some((k) => nearWashed[k])) continue;
      for (let y = y0; y <= y1; y++) washed.fill(1, y * w + x0, y * w + x1 + 1);
    }
    const out = [];
    for (let k = 0; k < w * h; k++) if (washed[k]) out.push(k);
    return out;
  };
  const GREEN = [22, 163, 74], RED = [220, 38, 38];
  const added = whole(shapesB, shapesA.list, changed("added", 2)), removed = whole(shapesA, shapesB.list, changed("removed", 1));
  return {
    added: layer([added.lines, GREEN, 3]), removed: layer([removed.lines, RED, 3]),
    // The lines again, right on them: a pale line (light grey, a yellow outline) is darkened to the
    // colour, where the one above could only colour a dark one.
    addedWash: layer([added.fill.concat(areas(added, shapesB)), [187, 247, 208], 0], [added.lines, GREEN, 0, b]),
    removedWash: layer([removed.fill.concat(areas(removed, shapesA)), [254, 202, 202], 0], [removed.lines, RED, 0, a]),
  };
}

/** A picture with the changed spots outlined. */
export function outlined(c, spots, colour) {
  const d = onWhite(c, c.width, c.height), ctx = d.getContext("2d");
  ctx.lineWidth = 3;
  ctx.strokeStyle = colour;
  for (const r of spots) ctx.strokeRect(r.x * SHOW, r.y * SHOW, r.w * SHOW, r.h * SHOW);
  return d;
}
