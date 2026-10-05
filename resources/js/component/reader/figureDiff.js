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

/**
 * Every picture big enough to be a diagram on a page, as fractions of the
 * page. Read from what the page draws, so a logo or an icon is left out.
 */
export async function picturesOn(page, OPS) {
  const vp = page.getViewport({ scale: 1 });
  const ops = await page.getOperatorList();
  let ctm = [1, 0, 0, 1, 0, 0];
  const stack = [];
  const out = [];
  ops.fnArray.forEach((fn, i) => {
    const a = ops.argsArray[i];
    if (fn === OPS.save) stack.push(ctm);
    else if (fn === OPS.restore) ctm = stack.pop() || ctm;
    else if (fn === OPS.transform) ctm = mul(ctm, a);
    else if (fn === OPS.paintFormXObjectBegin && a?.[0]) { stack.push(ctm); ctm = mul(ctm, a[0]); }
    else if (fn === OPS.paintFormXObjectEnd) ctm = stack.pop() || ctm;
    else if (fn === OPS.paintImageXObject || fn === OPS.paintInlineImageXObject || fn === OPS.paintImageMaskXObject) {
      const xs = [ctm[4], ctm[4] + ctm[0], ctm[4] + ctm[2], ctm[4] + ctm[0] + ctm[2]];
      const ys = [ctm[5], ctm[5] + ctm[1], ctm[5] + ctm[3], ctm[5] + ctm[1] + ctm[3]];
      const box = { left: Math.min(...xs) / vp.width, top: 1 - Math.max(...ys) / vp.height, width: (Math.max(...xs) - Math.min(...xs)) / vp.width, height: (Math.max(...ys) - Math.min(...ys)) / vp.height };
      if (box.width > 0.15 && box.height > 0.08) out.push(box);
    }
  });
  return out;
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
  const grow = new Uint8Array(diff.length);
  for (let r = 0; r < rows; r++) {
    for (let q = 0; q < cols; q++) {
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
  for (let i = 0; i < ink.length; i++) if (ink[i]) { inked++; if (diff[i]) differing++; }
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
    const captions = captionsOnPage(text.items, page.getViewport({ scale: 1 }).height, n);
    for (const box of boxes) {
      const label = figureFor(captions, n, [{ top_position: box.top, left_position: box.left, highlight_width: box.width, highlight_height: box.height }]);
      const drawn = await drawBox(page, box, W * SHOW * 1.5);
      const t = trimBox(drawn);
      // Where the inked part sits on the page, to mark changed spots there.
      const ink = { left: box.left + (t.x / drawn.width) * box.width, top: box.top + (t.y / drawn.height) * box.height, width: (t.w / drawn.width) * box.width, height: (t.h / drawn.height) * box.height };
      const sharp = normalised(drawn, W * SHOW);
      out.push({ page: n, label, title: captions.find((c) => c.label === label)?.title || "", sharp, small: normalised(sharp, W), ink });
    }
  }
  return out;
}

const pause = () => new Promise((ok) => setTimeout(ok));

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
    cost.push(news.map((n) => difference(o.small, n.small).mismatch));
    await pause();
  }
  const partner = pairUp(cost, news.length);
  const figures = [];
  for (const [i, o] of olds.entries()) {
    const j = partner[i];
    if (j === null) {
      figures.push({ kind: "removed", label: o.label, title: o.title, oldPage: o.page, newPage: null, old: o.sharp, oldInk: o.ink });
      continue;
    }
    const n = news[j];
    let near = n.small;
    let fit = null;
    if (cost[i][j] > ALIGN_ABOVE) {
      if (!alive()) return [];
      fit = alignment(o.small, n.small);
      near = onWhite(n.small, W, o.small.height, fit.x, fit.y, fit.s);
      await pause();
    }
    const d = difference(o.small, near);
    const h = Math.max(o.sharp.height, n.sharp.height);
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
      fig.changes = inkChanges(fig);
      fig.tones = fig.changes.spots.map((sp) => sp.tone);
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
 * The changed spots of every figure as boxes on the pages of both versions,
 * for the side-by-side view: { marks, oldMarks }, each by page number. A part
 * removed is boxed on the earlier version only, a part added on this one
 * only, a part rewritten on both. A redone, new or removed diagram is boxed whole.
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
  figures.forEach((f, i) => {
    const id = `f${i}`;
    if (f.kind === "small") {
      // Spots are found in the earlier picture's frame; this version's was moved onto it first.
      const s = f.fit?.s ?? 1, dx = f.fit?.x ?? 0, dy = f.fit?.y ?? 0;
      f.spots.forEach((r, k) => {
        const tone = f.tones?.[k] ?? null;
        if (tone !== "added") add(oldMarks, f.oldPage, `${id}-${k}`, tone, tone === "removed" ? "− removed" : tone === "rewritten" ? "changed" : null, onPage(f.oldInk, f.oldH, r));
        if (tone !== "removed") add(marks, f.newPage, `${id}-${k}`, tone, tone === "added" ? "+ added" : tone === "rewritten" ? "changed" : null, onPage(f.newInk, f.newH, { x: (r.x - dx) / s, y: (r.y - dy) / s, w: r.w / s, h: r.h / s }));
      });
    } else if (f.kind === "big") {
      add(oldMarks, f.oldPage, id, "rewritten", "redone", whole(f.oldInk));
      add(marks, f.newPage, id, "rewritten", "redone", whole(f.newInk));
    } else if (f.kind === "removed") add(oldMarks, f.oldPage, id, "removed", "− removed figure", whole(f.oldInk));
    else if (f.kind === "new") add(marks, f.newPage, id, "added", "+ new figure", whole(f.newInk));
  });
  return { marks, oldMarks };
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
 * is left unmarked: it is boxed and shown as before/after instead.
 */
function inkChanges(f) {
  const w = f.old.width, h = f.old.height, a = rgba(f.old), b = rgba(f.neu);
  const inkA = new Uint8Array(w * h), inkB = new Uint8Array(w * h), mark = new Uint8Array(w * h);
  for (let k = 0; k < w * h; k++) {
    inkA[k] = (a[k * 4] + a[k * 4 + 1] + a[k * 4 + 2]) / 3 < 170;
    inkB[k] = (b[k * 4] + b[k * 4 + 1] + b[k * 4 + 2]) / 3 < 170;
  }
  // A line one or two pixels off in the other picture is the same line.
  const near = (ink, x, y) => {
    for (let dy = -2; dy <= 2; dy++) for (let dx = -2; dx <= 2; dx++) {
      const xx = x + dx, yy = y + dy;
      if (xx >= 0 && yy >= 0 && xx < w && yy < h && ink[yy * w + xx]) return true;
    }
    return false;
  };
  const spots = f.spots.map((r) => {
    const box = [Math.max(0, r.x * SHOW - 4), Math.max(0, r.y * SHOW - 4), Math.min(w, (r.x + r.w) * SHOW + 4), Math.min(h, (r.y + r.h) * SHOW + 4)];
    let removed = 0, added = 0;
    for (let y = box[1]; y < box[3]; y++) {
      for (let x = box[0]; x < box[2]; x++) {
        const k = y * w + x;
        if (inkA[k] && !near(inkB, x, y)) { mark[k] = 1; removed++; }
        else if (inkB[k] && !near(inkA, x, y)) { mark[k] = 2; added++; }
      }
    }
    const tone = spotTone(removed, added);
    if (tone === "rewritten") for (let y = box[1]; y < box[3]; y++) mark.fill(0, y * w + box[0], y * w + box[2]);
    return { box, tone };
  });
  return { a, b, w, h, mark, spots };
}

/**
 * The pair as one picture: faded where both agree, red = only in the earlier
 * one, green = only in this one. A spot that was rewritten in place is boxed
 * and numbered instead, and given as a before/after close-up.
 */
export function highlighted(f) {
  const { a, b, w, h, mark, spots } = (f.changes ??= inkChanges(f));
  const boxes = spots.filter((s) => s.tone === "rewritten").map((s) => s.box);
  const plain = spots.filter((s) => s.tone === "removed" || s.tone === "added");
  const c = canvasOf(w, h), ctx = c.getContext("2d"), img = ctx.createImageData(w, h), o = img.data;
  for (let k = 0; k < w * h; k++) {
    const faded = 255 - (255 - Math.min(a[k * 4] + a[k * 4 + 1] + a[k * 4 + 2], b[k * 4] + b[k * 4 + 1] + b[k * 4 + 2]) / 3) * 0.3;
    o.set(mark[k] === 1 ? [220, 38, 38, 255] : mark[k] === 2 ? [22, 163, 74, 255] : [faded, faded, faded, 255], k * 4);
  }
  ctx.putImageData(img, 0, 0);
  // A part removed or added is boxed too, so a small change is not missed: red dashed with −, green with +.
  ctx.font = "bold 13px sans-serif";
  for (const { box: [x0, y0, x1, y1], tone } of plain) {
    const colour = tone === "removed" ? "#dc2626" : "#16a34a", text = tone === "removed" ? "− removed" : "+ added";
    ctx.lineWidth = 2;
    ctx.strokeStyle = ctx.fillStyle = colour;
    ctx.setLineDash(tone === "removed" ? [6, 4] : []);
    ctx.strokeRect(x0, y0, x1 - x0, y1 - y0);
    ctx.setLineDash([]);
    const tw = ctx.measureText(text).width + 8, ty = y0 >= 18 ? y0 - 18 : y0;
    ctx.fillRect(Math.min(x0, w - tw), ty, tw, 18);
    ctx.fillStyle = "#fff";
    ctx.fillText(text, Math.min(x0, w - tw) + 4, ty + 13);
  }
  ctx.strokeStyle = ctx.fillStyle = "#d97706";
  ctx.lineWidth = 2;
  ctx.font = "bold 16px sans-serif";
  boxes.forEach(([x0, y0, x1, y1], i) => {
    ctx.strokeRect(x0, y0, x1 - x0, y1 - y0);
    ctx.fillText(String(i + 1), Math.min(x1 + 4, w - 12), y0 + 14);
  });
  const pad = (box) => [Math.max(0, box[0] - 12), Math.max(0, box[1] - 12), Math.min(w, box[2] + 12), Math.min(h, box[3] + 12)];
  return { picture: c, closeUps: boxes.map((box) => ({ old: crop(f.old, pad(box)), neu: crop(f.neu, pad(box)) })) };
}

/** A picture with the changed spots outlined. */
export function outlined(c, spots, colour) {
  const d = onWhite(c, c.width, c.height), ctx = d.getContext("2d");
  ctx.lineWidth = 3;
  ctx.strokeStyle = colour;
  for (const r of spots) ctx.strokeRect(r.x * SHOW, r.y * SHOW, r.w * SHOW, r.h * SHOW);
  return d;
}
