import { editScript, hunks } from "./versionDiff.js";
import { mergeRectsByLine } from "./rects.js";
import { boundsOf, marksOf, parseCaption } from "./figures.js";

/*
 * Checking a revision: for each remark left on the earlier version, where the
 * same place is in this one, and whether what it pointed at changed.
 *
 * Words are the ones readWords() gives ({t, raw, page, line, rects}), and the
 * two versions are lined up by the same Myers diff the What changed tab uses,
 * so a remark follows its words even when a page was added before it.
 */

const normalise = (s) => s.toLowerCase().normalize("NFKD").replace(/[^\p{L}\p{N}]+/gu, "");
const clamp = (n) => Math.min(1, Math.max(0, n));
const median = (xs) => {
  const s = [...xs].sort((a, b) => a - b);
  return s.length ? s[Math.floor((s.length - 1) / 2)] : 0;
};

/** For each word of the old version, the same word in the new one (-1 when it was removed or reworded). */
export function wordMap(removed, added) {
  const oldToNew = new Int32Array(removed.length).fill(-1);
  for (let i = 0, j = 0; i < removed.length && j < added.length;) {
    if (removed[i]) i++; else if (added[j]) j++; else oldToNew[i++] = j++;
  }
  return oldToNew;
}

const centre = (w) => {
  const r = w.rects[0];
  return { x: r.left_position + r.highlight_width / 2, y: r.top_position + r.highlight_height / 2 };
};

/** The words whose middle is inside a box on a page, in reading order. */
export function wordsInside(words, page, box, pad = 0) {
  const out = [];
  words.forEach((w, i) => {
    if (w.page !== page) return;
    const c = centre(w);
    if (c.x >= box.left_position - pad && c.x <= box.left_position + box.highlight_width + pad
      && c.y >= box.top_position - pad && c.y <= box.top_position + box.highlight_height + pad) out.push(i);
  });
  return out;
}

/** Every caption in a version, from its words, with the words it is made of. */
export function captionsFromWords(words) {
  const lines = new Map();
  words.forEach((w, i) => {
    const key = `${w.page}:${w.line}`;
    if (!lines.has(key)) lines.set(key, []);
    lines.get(key).push(i);
  });
  const found = [];
  lines.forEach((idx) => {
    const caption = parseCaption(idx.map((i) => words[i].raw).join(" "));
    if (!caption || found.some((f) => f.label === caption.label)) return;
    const first = words[idx[0]];
    found.push({ ...caption, page: first.page, y: first.rects[0].top_position, words: idx });
  });
  return found;
}

/**
 * The quoted words of a text remark on the old version: the run of words
 * inside its box that spells what was selected. The saved box spans whole
 * lines, so it can hold words either side of the selection; the quote says
 * exactly which.
 */
export function quotedSpan(remark, words) {
  const box = boundsOf(marksOf(remark));
  const inside = wordsInside(words, remark.page_number, box, 0.004);
  if (!inside.length) return null;
  const want = String(remark.selectedText || "").split(/\s+/).map(normalise).filter(Boolean);
  const lo = inside[0];
  const hi = inside[inside.length - 1];
  if (want.length) {
    const n = want.length;
    const fits = (i) => {
      if (i + n > words.length) return false;
      if (n === 1) return words[i].t.includes(want[0]);
      // A selection can start or end part-way through a word.
      if (!words[i].t.endsWith(want[0]) || !words[i + n - 1].t.startsWith(want[n - 1])) return false;
      for (let k = 1; k < n - 1; k++) if (words[i + k].t !== want[k]) return false;
      return true;
    };
    for (let i = lo; i <= hi; i++) if (fits(i)) return [i, i + n];
  }
  return [lo, hi + 1];
}

const rectsOf = (words, s, e) => {
  if (s >= e) return { page: null, rects: [] };
  const page = words[s].page;
  return { page, rects: mergeRectsByLine(words.slice(s, e).filter((w) => w.page === page).flatMap((w) => w.rects)) };
};

/** Where a run of old words is in the new version, and which hunks of the diff touch it. */
function follow(s, e, ctx) {
  const { oldToNew, changes, newWords } = ctx;
  // A change touches the words when it replaces some of them, or puts new
  // words between them. Words added just before or after them do not count.
  const touching = changes.filter(([a0, a1]) => (a0 < a1 ? a0 < e && a1 > s : a0 > s && a0 < e));
  const mapped = [];
  for (let k = s; k < e; k++) if (oldToNew[k] >= 0) mapped.push(oldToNew[k]);
  touching.forEach(([, , b0, b1]) => { for (let j = b0; j < b1; j++) mapped.push(j); });
  let ns;
  let ne;
  if (mapped.length) {
    ns = Math.min(...mapped);
    ne = Math.max(...mapped) + 1;
  } else {
    // Every word was removed: the place is where the next surviving word is now.
    let k = e;
    while (k < oldToNew.length && oldToNew[k] < 0) k++;
    ns = k < oldToNew.length ? oldToNew[k] : newWords.length - 1;
    ne = ns;
  }
  return { ns, ne, touching };
}

const said = (words, list) => list.map((i) => words[i].raw).join(" ");

/** The label words of a figure that are new or gone, by the same diff, inside the box only. */
function labelChanges(oldWords, oldIdx, newWords, newIdx) {
  const a = oldIdx.map((i) => oldWords[i].t);
  const b = newIdx.map((j) => newWords[j].t);
  const edit = editScript(a, b);
  if (!edit) return { removed: said(oldWords, oldIdx), added: said(newWords, newIdx) };
  return {
    removed: said(oldWords, oldIdx.filter((_, k) => edit.removed[k])),
    added: said(newWords, newIdx.filter((_, k) => edit.added[k])),
  };
}

/**
 * Where a figure is now. In order of how sure it is: its caption (followed
 * word by word, so a renumbered figure is still found), the words inside the
 * box, then the text just above and below it. Failing all of those, the same
 * place on the same page.
 */
function locateFigure(remark, ctx) {
  const { oldWords, newWords, oldToNew, oldCaptions, newCaptions } = ctx;
  const page = remark.page_number;
  const box = boundsOf(marksOf(remark));
  const moved = (pairs, how, extra = {}) => {
    const byPage = {};
    pairs.forEach(([o, n]) => (byPage[newWords[n].page] ||= []).push(newWords[n].rects[0].top_position - oldWords[o].rects[0].top_position));
    const [toPage, shifts] = Object.entries(byPage).sort((x, y) => y[1].length - x[1].length)[0];
    return { page: Number(toPage), dy: median(shifts), how, ...extra };
  };
  const pairsOf = (idx) => idx.filter((i) => oldToNew[i] >= 0).map((i) => [i, oldToNew[i]]);

  const caption = remark.figure_label && oldCaptions.find((c) => c.label === remark.figure_label);
  if (caption) {
    const pairs = pairsOf(caption.words);
    if (pairs.length) {
      const at = moved(pairs, "caption");
      const now = newCaptions.find((c) => c.page === at.page && pairs.some(([, n]) => c.words.includes(n)));
      return { ...at, label: now?.label || remark.figure_label };
    }
    const same = newCaptions.find((c) => c.label === remark.figure_label);
    if (same) return { page: same.page, dy: same.y - caption.y, how: "caption", label: same.label };
  }

  const labels = pairsOf(wordsInside(oldWords, page, box, 0.005));
  if (labels.length) return moved(labels, "labels");

  const onPage = oldWords.map((w, i) => i).filter((i) => oldWords[i].page === page);
  const above = onPage.filter((i) => centre(oldWords[i]).y < box.top_position).slice(-6);
  const below = onPage.filter((i) => centre(oldWords[i]).y > box.top_position + box.highlight_height).slice(0, 6);
  const nearby = pairsOf([...below, ...above]);
  if (nearby.length) return moved(nearby, "nearby text");

  return { page, dy: 0, how: "same place" };
}

/**
 * What the words inside a figure say about it at a place in this version:
 * the labels that are new or gone, inside the marked area only. `changed`
 * is null when there are no words there at all (a diagram that is a picture).
 */
export function labelsAt(remark, target, oldWords, newWords) {
  const area = remark.type === "pin"
    ? { top_position: remark.y_position - 0.06, left_position: remark.x_position - 0.1, highlight_width: 0.2, highlight_height: 0.12 }
    : boundsOf(remark.highlightRects);
  const moved = remark.type === "pin"
    ? { top_position: target.y - 0.06, left_position: target.x - 0.1, highlight_width: 0.2, highlight_height: 0.12 }
    : boundsOf(target.rects);
  const oldIdx = wordsInside(oldWords, remark.page_number, area, 0.003);
  const newIdx = wordsInside(newWords, target.page, moved, 0.003);
  if (!oldIdx.length && !newIdx.length) return { changed: null, removedWords: "", addedWords: "" };
  const words = labelChanges(oldWords, oldIdx, newWords, newIdx);
  return { changed: Boolean(words.removed || words.added), removedWords: words.removed, addedWords: words.added };
}

/**
 * Every remark from the earlier version ({id, type, page_number,
 * highlightRects, x/y_position, selectedText, figure_label, text, ...}),
 * with where it is in this version and what changed there.
 *
 * - target: {page, rects} for words and boxes, {page, x, y} for a pin
 * - changed: true, false, or null when the words cannot tell (a figure
 *   that is only a picture - the screen compares the pictures then)
 * - removed / added: the words that went and came, for the reviewer to read
 */
export function checkRevision(remarks, oldWords, newWords, { removed, added }) {
  const oldToNew = wordMap(removed, added);
  const joined = (words, s, e) => words.slice(s, e).map((w) => w.t).join("");
  // Words split differently ("C APSTRACK" and "CAPSTRACK") are not a change.
  const changes = hunks(removed, added).filter(([a0, a1, b0, b1]) => joined(oldWords, a0, a1) !== joined(newWords, b0, b1));
  const ctx = {
    oldWords, newWords, oldToNew, changes,
    oldCaptions: captionsFromWords(oldWords),
    newCaptions: captionsFromWords(newWords),
  };

  return remarks.map((remark) => {
    if (remark.type === "box" || remark.type === "pin") {
      const at = locateFigure(remark, ctx);
      const shift = (r) => ({ ...r, top_position: clamp(r.top_position + at.dy) });
      const target = remark.type === "pin"
        ? { page: at.page, x: remark.x_position, y: clamp(remark.y_position + at.dy), rects: [] }
        : { page: at.page, rects: remark.highlightRects.map(shift) };
      const words = labelsAt(remark, target, oldWords, newWords);
      return {
        ...remark,
        target,
        foundBy: at.how,
        figureNow: at.label || remark.figure_label || null,
        ...words,
      };
    }

    const span = quotedSpan(remark, oldWords);
    if (!span) return { ...remark, target: null, foundBy: "not found", changed: null, removedWords: "", addedWords: "" };
    const [s, e] = span;
    const { ns, ne, touching } = follow(s, e, ctx);
    const placed = rectsOf(newWords, ns, Math.max(ne, ns + 1));
    return {
      ...remark,
      target: { page: placed.page ?? remark.page_number, rects: placed.rects },
      foundBy: "words",
      changed: touching.length > 0,
      removedWords: touching.map(([a0, a1]) => said(oldWords, Array.from({ length: a1 - a0 }, (_, k) => a0 + k))).filter(Boolean).join(" … "),
      addedWords: touching.map(([, , b0, b1]) => said(newWords, Array.from({ length: b1 - b0 }, (_, k) => b0 + k))).filter(Boolean).join(" … "),
      quoteNow: ne > ns ? said(newWords, Array.from({ length: ne - ns }, (_, k) => ns + k)) : "",
    };
  });
}

/**
 * How much of a picture changed, as the share of small blocks that clearly
 * differ between two same-size RGBA images.
 *
 * First the two are lined up: the shift of up to a few pixels that makes them
 * most alike. Then they are compared a block at a time (the average of each
 * cell x cell block), so a letter drawn one pixel off in one version - which
 * the browser does when it snaps text to whole pixels - does not count, while
 * a new label, line or box changes many blocks a lot.
 */
export function pictureDifference(a, b, width, height, { maxShift = 3, cell = 4, threshold = 40 } = {}) {
  const grey = (d) => {
    const g = new Float32Array(width * height);
    for (let i = 0; i < g.length; i++) g[i] = 0.299 * d[i * 4] + 0.587 * d[i * 4 + 1] + 0.114 * d[i * 4 + 2];
    return g;
  };
  const ga = grey(a);
  const gb = grey(b);

  let best = Infinity;
  let at = { dx: 0, dy: 0 };
  for (let dy = -maxShift; dy <= maxShift; dy++) {
    for (let dx = -maxShift; dx <= maxShift; dx++) {
      let total = 0;
      let seen = 0;
      for (let y = Math.max(0, -dy); y < Math.min(height, height - dy); y++) {
        for (let x = Math.max(0, -dx); x < Math.min(width, width - dx); x++) {
          total += Math.abs(ga[y * width + x] - gb[(y + dy) * width + x + dx]);
          seen++;
        }
      }
      const mean = seen ? total / seen : Infinity;
      if (mean < best) { best = mean; at = { dx, dy }; }
    }
  }

  const { dx, dy } = at;
  const x0 = Math.max(0, -dx);
  const y0 = Math.max(0, -dy);
  const cols = Math.floor((width - Math.abs(dx)) / cell);
  const rows = Math.floor((height - Math.abs(dy)) / cell);
  let differ = 0;
  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < cols; c++) {
      let sa = 0;
      let sb = 0;
      for (let y = 0; y < cell; y++) {
        for (let x = 0; x < cell; x++) {
          const ya = y0 + r * cell + y;
          const xa = x0 + c * cell + x;
          sa += ga[ya * width + xa];
          sb += gb[(ya + dy) * width + xa + dx];
        }
      }
      if (Math.abs(sa - sb) / (cell * cell) > threshold) differ++;
    }
  }
  return { share: rows * cols ? differ / (rows * cols) : 0, dx, dy };
}

/** Above this share of clearly different blocks, a picture counts as changed. */
export const PICTURE_CHANGED = 0.001;

/** Grey levels (0 black - 255 white) of an RGBA image. */
export function greyOf(rgba, width, height) {
  const g = new Float32Array(width * height);
  for (let i = 0; i < g.length; i++) g[i] = 0.299 * rgba[i * 4] + 0.587 * rgba[i * 4 + 1] + 0.114 * rgba[i * 4 + 2];
  return g;
}


/** A 3 x 3 average, so a line one pixel off still overlaps itself. */
function soften(g, width, height) {
  const out = new Float32Array(g.length);
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      let total = 0;
      let n = 0;
      for (let dy = -1; dy <= 1; dy++) {
        for (let dx = -1; dx <= 1; dx++) {
          const yy = y + dy;
          const xx = x + dx;
          if (yy >= 0 && yy < height && xx >= 0 && xx < width) { total += g[yy * width + xx]; n++; }
        }
      }
      out[y * width + x] = total / n;
    }
  }
  return out;
}

/**
 * How alike a picture is to the part of a page at (x, y): normalised
 * correlation, from 1 (the same pattern of dark and light) down. It compares
 * the shape of the drawing, not how dark it is, so a faint thin-lined diagram
 * scores as well as a bold one, and blank paper scores 0.
 */
function likeness(page, pageW, tpl, tplW, tplH, x, y, step) {
  let n = 0;
  let st = 0;
  let sp = 0;
  for (let ty = 0; ty < tplH; ty += step) {
    const row = (y + ty) * pageW + x;
    for (let tx = 0; tx < tplW; tx += step) { st += tpl[ty * tplW + tx]; sp += page[row + tx]; n++; }
  }
  const mt = st / n;
  const mp = sp / n;
  let cov = 0;
  let vt = 0;
  let vp = 0;
  for (let ty = 0; ty < tplH; ty += step) {
    const row = (y + ty) * pageW + x;
    for (let tx = 0; tx < tplW; tx += step) {
      const a = tpl[ty * tplW + tx] - mt;
      const b = page[row + tx] - mp;
      cov += a * b;
      vt += a * a;
      vp += b * b;
    }
  }
  return vt > 0 && vp > 0 ? cov / Math.sqrt(vt * vp) : 0;
}

/** At or above this likeness, the picture is taken to be there. */
export const PICTURE_FOUND = 0.6;

/**
 * Where a picture (tpl, grey) is on a page (grey, same scale): the top-left
 * corner where the two are most alike, to a fraction of a pixel. Every place
 * is tried on every other pixel, then the best is refined on every pixel. `found` is false when even
 * the best place is not clearly the picture.
 */
export function findPicture(pageGrey, pageW, pageH, tplGrey, tplW, tplH) {
  if (tplW > pageW || tplH > pageH) return { found: false, x: 0, y: 0, score: 0 };
  const page = soften(pageGrey, pageW, pageH);
  const tpl = soften(tplGrey, tplW, tplH);
  let best = { x: 0, y: 0, score: -Infinity };
  for (let y = 0; y + tplH <= pageH; y++) {
    for (let x = 0; x + tplW <= pageW; x++) {
      const score = likeness(page, pageW, tpl, tplW, tplH, x, y, 2);
      if (score > best.score) best = { x, y, score };
    }
  }
  const around = best;
  best = { x: around.x, y: around.y, score: likeness(page, pageW, tpl, tplW, tplH, around.x, around.y, 1) };
  for (let y = Math.max(0, around.y - 2); y <= Math.min(pageH - tplH, around.y + 2); y++) {
    for (let x = Math.max(0, around.x - 2); x <= Math.min(pageW - tplW, around.x + 2); x++) {
      const score = likeness(page, pageW, tpl, tplW, tplH, x, y, 1);
      if (score > best.score) best = { x, y, score };
    }
  }
  // Between pixels: a parabola through the scores either side of the best
  // place puts it to a fraction of a pixel, so the two pictures can be laid
  // over each other without every thin line looking moved by half a pixel.
  const at = (x, y) => likeness(page, pageW, tpl, tplW, tplH, x, y, 1);
  const between = (before, after) => {
    const bend = before - 2 * best.score + after;
    return bend < 0 ? Math.max(-0.5, Math.min(0.5, (before - after) / (2 * bend))) : 0;
  };
  const x = best.x > 0 && best.x < pageW - tplW ? best.x + between(at(best.x - 1, best.y), at(best.x + 1, best.y)) : best.x;
  const y = best.y > 0 && best.y < pageH - tplH ? best.y + between(at(best.x, best.y - 1), at(best.x, best.y + 1)) : best.y;
  return { x, y, score: best.score, found: best.score >= PICTURE_FOUND };
}
