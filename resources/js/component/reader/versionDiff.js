import { mergeRectsByLine } from "./Reader";

/*
 * What changed between two versions of a document, word by word. Lines are
 * matched first so a long document stays quick, then the words inside each
 * changed stretch are matched, so a reflowed paragraph only marks the words
 * that really differ.
 */

const MAX_EDITS = 2000;
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

const linesOf = (words) => {
  const lines = [];
  words.forEach((w, i) => {
    const last = lines[lines.length - 1];
    if (last && last.page === w.page && last.line === w.line) { last.end = i + 1; last.text += ` ${w.t}`; }
    else lines.push({ page: w.page, line: w.line, start: i, end: i + 1, text: w.t });
  });
  return lines;
};

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

  stretches.forEach(([a0, a1, b0, b1]) => {
    // ponytail: a stretch past MAX_EDITS is marked whole; it was rewritten anyway.
    const edit = editScript(oldWords.slice(a0, a1).map((w) => w.t), newWords.slice(b0, b1).map((w) => w.t));
    for (let i = a0; i < a1; i++) removed[i] = edit ? edit.removed[i - a0] : 1;
    for (let j = b0; j < b1; j++) added[j] = edit ? edit.added[j - b0] : 1;
  });

  const marks = {};
  const changes = hunks(removed, added).map(([a0, a1, b0, b1], id) => {
    const byPage = {};
    for (let j = b0; j < b1; j++) (byPage[newWords[j].page] ||= []).push(...newWords[j].rects);
    Object.entries(byPage).forEach(([page, rects]) => (marks[page] ||= []).push({ id, rects: mergeRectsByLine(rects) }));
    // A removal has no words of its own on the new version: a thin mark just
    // before the words that now follow it.
    const anchor = newWords[Math.min(b0, newWords.length - 1)];
    const first = anchor?.rects[0];
    if (b0 === b1 && first) {
      (marks[anchor.page] ||= []).push({ id, removed: true, rects: [{ ...first, left_position: Math.max(0, first.left_position - 0.004), highlight_width: 0.003 }] });
    }
    return {
      id,
      kind: a0 === a1 ? "added" : b0 === b1 ? "removed" : "reworded",
      page: anchor?.page ?? 1,
      rect: first ?? null,
      // A few words either side, the way a code review shows a changed line.
      before: snippet(newWords, Math.max(0, b0 - 6), b0),
      oldText: snippet(oldWords, a0, a1),
      newText: snippet(newWords, b0, b1),
      after: snippet(newWords, b1, b1 + 6),
      a0,
      a1,
    };
  });

  return { removed, added, changes, marks };
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
