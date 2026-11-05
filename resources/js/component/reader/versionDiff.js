import { mergeRectsByLine } from "./rects.js";

/*
 * What changed between two versions of a document, word by word. Lines are
 * matched first so a long document stays quick, then the words inside each
 * changed stretch are matched, so a reflowed paragraph only marks the words
 * that really differ.
 */

const MAX_EDITS = 2000;
// Below this share of three-word phrases in common, the new file is not a
// revision of the old one - most likely the wrong file (Chapter 3 sent as the
// next Chapter 1). Same measure the upload's own check uses (RevisionCheck).
export const MIN_SHARED = 0.25;
const MIN_PHRASES = 20;

/**
 * The share of three-word phrases two word lists have in common, out of the
 * shorter one's; null when either is too short to tell. A revision keeps most
 * of its phrases; another chapter of the same study shares only a handful.
 */
export function sharedPhrases(oldWords, newWords) {
  const phrases = (words) => {
    const out = new Set();
    for (let i = 0; i + 2 < words.length; i++) out.add(words[i].t + " " + words[i + 1].t + " " + words[i + 2].t);
    return out;
  };
  const a = phrases(oldWords);
  const b = phrases(newWords);
  if (a.size < MIN_PHRASES || b.size < MIN_PHRASES) return null;
  let common = 0;
  a.forEach((p) => { if (b.has(p)) common++; });
  return common / Math.min(a.size, b.size);
}
// How many words either side of a remark still count as "near" it.
const NEAR = 25;

/**
 * The shortest set of edits turning list `a` into list `b` (Myers). Returns
 * which items of `a` were removed and which of `b` were added, or null when
 * the lists differ by more than `maxD` edits.
 */
export function editScript(a, b, maxD = MAX_EDITS) {
  const n = a.length;
  const m = b.length;
  const removed = new Uint8Array(n);
  const added = new Uint8Array(m);
  const max = n + m;
  const off = max + 1;
  const v = new Int32Array(2 * max + 3);
  const trace = [];
  for (let d = 0; d <= Math.min(max, maxD); d++) {
    trace.push(v.slice(off - d, off + d + 1));
    for (let k = -d; k <= d; k += 2) {
      let x = k === -d || (k !== d && v[off + k - 1] < v[off + k + 1]) ? v[off + k + 1] : v[off + k - 1] + 1;
      let y = x - k;
      while (x < n && y < m && a[x] === b[y]) { x++; y++; }
      v[off + k] = x;
      if (x >= n && y >= m) {
        for (let e = d, cx = n, cy = m; e > 0; e--) {
          const w = trace[e];
          const ck = cx - cy;
          const down = ck === -e || (ck !== e && w[ck - 1 + e] < w[ck + 1 + e]);
          const pk = down ? ck + 1 : ck - 1;
          const px = w[pk + e];
          const py = px - pk;
          if (down) added[py] = 1; else removed[px] = 1;
          cx = px;
          cy = py;
        }
        return { removed, added };
      }
    }
  }
  return null;
}

const ANCHOR = 3;

/**
 * Three-word phrases found exactly once in a[a0, a1) and once in b[b0, b1),
 * as [i, j] pairs rising on both sides. Where the two sides disagree on the
 * order, the longest agreeing run wins; the rest were moved or matched by
 * chance (patience diff).
 */
export function anchorsBetween(a, b, a0, a1, b0, b1) {
  const once = (w, s, e) => {
    const seen = new Map();
    for (let i = s; i + ANCHOR <= e; i++) {
      const key = w[i] + " " + w[i + 1] + " " + w[i + 2];
      seen.set(key, seen.has(key) ? -1 : i);
    }
    return seen;
  };
  const inB = once(b, b0, b1);
  const pairs = [];
  once(a, a0, a1).forEach((i, key) => {
    const j = inB.get(key);
    if (i >= 0 && j >= 0) pairs.push([i, j]);
  });
  pairs.sort((p, q) => p[0] - q[0]);

  const tails = [];
  const prev = new Int32Array(pairs.length).fill(-1);
  pairs.forEach(([, j], p) => {
    let lo = 0;
    let hi = tails.length;
    while (lo < hi) {
      const mid = (lo + hi) >> 1;
      if (pairs[tails[mid]][1] < j) lo = mid + 1; else hi = mid;
    }
    if (lo > 0) prev[p] = tails[lo - 1];
    tails[lo] = p;
  });
  const chain = [];
  for (let p = tails.length ? tails[tails.length - 1] : -1; p >= 0; p = prev[p]) chain.push(pairs[p]);
  return chain.reverse();
}

/**
 * Marks what differs between a[a0, a1) and b[b0, b1). A stretch with more
 * changes than one pass allows - a whole manuscript whose lines all moved
 * after a margin change, on top of its revisions - is cut at the phrases both
 * sides share exactly once, and each piece between them is compared on its
 * own. Only a piece with nothing left in common is marked whole.
 */
export function diffRange(a, b, a0, a1, b0, b1, removed, added) {
  if (a0 === a1 || b0 === b1) {
    removed.fill(1, a0, a1);
    added.fill(1, b0, b1);
    return;
  }
  const edit = editScript(a.slice(a0, a1), b.slice(b0, b1));
  if (edit) {
    removed.set(edit.removed, a0);
    added.set(edit.added, b0);
    return;
  }
  const chain = anchorsBetween(a, b, a0, a1, b0, b1);
  if (!chain.length) {
    removed.fill(1, a0, a1);
    added.fill(1, b0, b1);
    return;
  }
  let i = a0;
  let j = b0;
  for (const [ai, bj] of chain) {
    if (ai < i || bj < j) {
      // Overlaps the phrase before it: on the same diagonal it carries the match on.
      if (ai - bj === i - j && ai + ANCHOR > i) {
        j += ai + ANCHOR - i;
        i = ai + ANCHOR;
      }
      continue;
    }
    diffRange(a, b, i, ai, j, bj, removed, added);
    i = ai + ANCHOR;
    j = bj + ANCHOR;
  }
  diffRange(a, b, i, a1, j, b1, removed, added);
}

/** The stretches [a0, a1) of the old list and [b0, b1) of the new that differ. */
export function hunks(removed, added) {
  const out = [];
  let i = 0;
  let j = 0;
  while (i < removed.length || j < added.length) {
    if (i < removed.length && j < added.length && !removed[i] && !added[j]) { i++; j++; continue; }
    const a0 = i;
    const b0 = j;
    while ((i < removed.length && removed[i]) || (j < added.length && added[j])) {
      if (i < removed.length && removed[i]) i++; else j++;
    }
    if (a0 === i && b0 === j) break;
    out.push([a0, i, b0, j]);
  }
  return out;
}

// Myers returns the shortest edit script, not the most readable one: a rewritten
// paragraph comes back shredded into dozens of changes around the handful of
// words that happened to survive in place. A matching run shorter than the
// changes on either side of it was a coincidence, so fold it back in and let the
// passage read as one change. Google's own diff library calls this semantic
// cleanup. GAP is a ceiling, so two real rewrites a line or more apart stay apart.
const GAP = 8;
const spanOf = (h) => Math.max(h[1] - h[0], h[3] - h[2]);
const absorb = (list) => {
  const out = [];
  for (const h of list) {
    const p = out[out.length - 1];
    const gap = p ? Math.max(h[0] - p[1], h[2] - p[3]) : Infinity;
    if (p && gap <= Math.min(GAP, Math.max(spanOf(p), spanOf(h)))) { p[1] = h[1]; p[3] = h[3]; }
    else out.push([...h]);
  }
  return out;
};

const linesOf = (words) => {
  const lines = [];
  words.forEach((w, i) => {
    const last = lines[lines.length - 1];
    if (last && last.page === w.page && last.line === w.line) { last.end = i + 1; last.text += ` ${w.t}`; }
    else lines.push({ page: w.page, line: w.line, start: i, end: i + 1, text: w.t });
  });
  return lines;
};

// A page number in the header or footer would count as changed whenever pages
// shift. Only short numbers near the page edge: "2026" on a title page is text.
const PAGE_NUMBER = /^(page\s*)?(\d{1,3}|[ivxlc]{1,6})(\s*of\s*\d{1,3})?$/i;
const NUMBER = /^(\d+|[ivxlc]{1,6})$/;
const nearEdge = (top) => top < 0.12 || top > 0.88;

/**
 * The words without the header and footer lines that would count as changed
 * whenever the page breaks move: a line that is only a page number, and a line
 * near the edge of at least half the pages (a running header, the school's
 * letterhead), numbers in it aside. Half, so a "Table 3" at the top of a page
 * or two is still compared.
 */
export function dropRunningLines(words) {
  const lines = linesOf(words).map((l) => ({
    ...l,
    edge: nearEdge(words[l.start].rects[0].top_position),
    key: l.text.split(" ").filter((t) => !NUMBER.test(t)).join(" "),
  }));
  const pages = new Map(); // a line's text -> the pages it is near the edge of
  lines.forEach((l) => l.edge && l.key && pages.set(l.key, (pages.get(l.key) || new Set()).add(l.page)));
  const need = Math.max(2, Math.ceil(new Set(words.map((w) => w.page)).size / 2));
  const running = (l) => l.edge && (PAGE_NUMBER.test(words.slice(l.start, l.end).map((w) => w.raw).join(" ")) || pages.get(l.key)?.size >= need);
  return lines.filter((l) => !running(l)).flatMap((l) => words.slice(l.start, l.end));
}

const snippet = (words, s, e) =>
  words.slice(s, Math.min(e, s + 60)).map((w) => w.raw).join(" ") + (e - s > 60 ? " …" : "");

/**
 * Words are {t: normalised word, raw, page, line, rects}. Returns the changes
 * (added, removed or reworded passages, anchored on the new version) and the
 * rectangles to mark on each page of the new version.
 */
export function compareVersions(oldWords, newWords) {
  const removed = new Uint8Array(oldWords.length);
  const added = new Uint8Array(newWords.length);
  const oldLines = linesOf(oldWords);
  const newLines = linesOf(newWords);
  const startOf = (lines, total, i) => (i < lines.length ? lines[i].start : total);

  const lineEdit = editScript(oldLines.map((l) => l.text), newLines.map((l) => l.text));
  const stretches = lineEdit
    ? hunks(lineEdit.removed, lineEdit.added).map(([a0, a1, b0, b1]) => [
        startOf(oldLines, oldWords.length, a0), startOf(oldLines, oldWords.length, a1),
        startOf(newLines, newWords.length, b0), startOf(newLines, newWords.length, b1),
      ])
    : [[0, oldWords.length, 0, newWords.length]];

  const oldText = oldWords.map((w) => w.t);
  const newText = newWords.map((w) => w.t);
  stretches.forEach(([a0, a1, b0, b1]) => diffRange(oldText, newText, a0, a1, b0, b1, removed, added));

  // The same letters split into different words ("C APSTRACK" and "CAPSTRACK")
  // is a difference in how the PDF was saved, not in what it says.
  const joined = (words, s, e) => words.slice(s, e).map((w) => w.t).join("");
  const marks = {};
  const oldMarks = {};
  const changes = absorb(
    hunks(removed, added).filter(([a0, a1, b0, b1]) => joined(oldWords, a0, a1) !== joined(newWords, b0, b1)),
  ).map(([a0, a1, b0, b1], id) => {
    const byPage = {};
    for (let j = b0; j < b1; j++) (byPage[newWords[j].page] ||= []).push(...newWords[j].rects);
    Object.entries(byPage).forEach(([page, rects]) => (marks[page] ||= []).push({ id, rects: mergeRectsByLine(rects) }));
    // A removal has no words of its own on the new version: the word that now
    // follows it is marked like a new word, in red. Its pieces are joined the
    // same way, so a small-caps word is as tall as a new one would be.
    const anchor = newWords[Math.min(b0, newWords.length - 1)];
    const first = anchor?.rects[0];
    if (b0 === b1 && first) {
      (marks[anchor.page] ||= []).push({ id, removed: true, rects: mergeRectsByLine(anchor.rects).slice(0, 1) });
    }
    // The same passage on the earlier version: its removed words, or a thin
    // mark where new words were put in.
    const byOld = {};
    for (let i = a0; i < a1; i++) (byOld[oldWords[i].page] ||= []).push(...oldWords[i].rects);
    Object.entries(byOld).forEach(([page, rects]) => (oldMarks[page] ||= []).push({ id, rects: mergeRectsByLine(rects) }));
    const oldAnchor = oldWords[Math.min(a0, oldWords.length - 1)];
    const oldFirst = oldAnchor?.rects[0];
    if (a0 === a1 && oldFirst) {
      (oldMarks[oldAnchor.page] ||= []).push({ id, inserted: true, rects: [{ ...oldFirst, left_position: Math.max(0, oldFirst.left_position - 0.004), highlight_width: 0.003 }] });
    }
    return {
      id,
      kind: a0 === a1 ? "added" : b0 === b1 ? "removed" : "reworded",
      page: anchor?.page ?? 1,
      rect: first ?? null,
      oldPage: oldAnchor?.page ?? 1,
      oldRect: oldFirst ?? null,
      // A few words either side, the way a code review shows a changed line.
      before: snippet(newWords, Math.max(0, b0 - 6), b0),
      oldText: snippet(oldWords, a0, a1),
      newText: snippet(newWords, b0, b1),
      after: snippet(newWords, b1, b1 + 6),
      a0,
      a1,
    };
  });

  return { removed, added, changes, marks, oldMarks };
}

/**
 * For each remark left on the earlier version: the changes within a few
 * words of what it pointed at, and where to look on the new version.
 */
export function remarksNearChanges(remarks, oldWords, newWords, { removed, added, changes }) {
  const oldToNew = new Int32Array(oldWords.length).fill(-1);
  for (let i = 0, j = 0; i < removed.length && j < added.length;) {
    if (removed[i]) i++; else if (added[j]) j++; else oldToNew[i++] = j++;
  }

  return remarks.map((r) => {
    const page = Number(r.page_number);
    const top = Number(r.top_position) || 0;
    const bottom = top + (Number(r.highlight_height) || 0.02);
    const left = Number(r.left_position) || 0;
    const base = { id: r.id, page, quote: r.selected_text, text: r.comment, changeIds: [], target: null };

    let s = oldWords.findIndex((w) => {
      const r = w.rects[0];
      const mid = r.top_position + r.highlight_height / 2;
      return w.page === page && mid >= top - 0.005 && mid <= bottom + 0.005 && r.left_position + r.highlight_width >= left;
    });
    if (s < 0) s = oldWords.findIndex((w) => w.page === page && w.rects[0].top_position >= top - 0.01);
    if (s < 0) return base;
    const e = s + Math.max(1, (r.selected_text || "").split(/\s+/).filter(Boolean).length);

    const near = changes.filter((c) => c.a0 <= e + NEAR && c.a1 >= s - NEAR);
    let target = near[0] ? { page: near[0].page, rect: near[0].rect } : null;
    if (!target) {
      let k = s;
      while (k < oldWords.length && oldToNew[k] < 0) k++;
      const w = newWords[oldToNew[k]];
      if (w) target = { page: w.page, rect: w.rects[0] };
    }
    return { ...base, changeIds: near.map((c) => c.id), target };
  });
}
