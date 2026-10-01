// Checking a revision: does each remark from the last version find its place
// in the new one, and does it say correctly whether that place changed?
// Run with `npm run test:js`.
import { test } from "node:test";
import assert from "node:assert/strict";
import { compareVersions } from "../../resources/js/component/reader/versionDiff.js";
import { PICTURE_CHANGED, checkRevision, pictureDifference } from "../../resources/js/component/reader/revisionCheck.js";

const normalise = (s) => s.toLowerCase().normalize("NFKD").replace(/[^\p{L}\p{N}]+/gu, "");

/**
 * A page laid out the way readWords() reports it: each entry is a line of
 * text at a height on a page, its words spread across from the left margin.
 */
function layout(lines) {
  const words = [];
  lines.forEach(({ page, top, text, left = 0.12 }, line) => {
    let x = left;
    text.split(/\s+/).filter(Boolean).forEach((raw) => {
      const w = raw.length * 0.012;
      words.push({ raw, t: normalise(raw), page, line, rects: [{ top_position: top, left_position: x, highlight_width: w, highlight_height: 0.018 }] });
      x += w + 0.01;
    });
  });
  return words.filter((w) => w.t);
}

const para = (page, top, n, seed) => Array.from({ length: n }, (_, i) => ({
  page, top: top + i * 0.025, text: `${seed} line ${i} says something about the barangay request system and its users here`,
}));

const check = (remarks, before, after) => {
  const oldWords = layout(before);
  const newWords = layout(after);
  return checkRevision(remarks, oldWords, newWords, compareVersions(oldWords, newWords));
};

// A text remark the way the screen keeps it, on the given words of a line.
const textRemark = (id, page, top, left, width, selectedText) => ({
  id, type: "text", page_number: page, selectedText, text: "Fix this.",
  highlightRects: [{ top_position: top, left_position: left, highlight_width: width, highlight_height: 0.018 }],
});

const v1 = [
  ...para(1, 0.1, 6, "intro"),
  { page: 1, top: 0.3, text: "Development followed the Agile model delivering the system in short iterations" },
  ...para(1, 0.35, 4, "after"),
];

test("an unchanged quote is found again after a page was added before it", () => {
  const v2 = [...para(1, 0.1, 30, "new chapter opening"), ...v1.map((l) => ({ ...l, page: 2 }))];
  // "Agile model" sits after "Development followed the " on its line.
  const [r] = check([textRemark(1, 1, 0.3, 0.12, 0.7, "Agile model")], v1, v2);
  assert.equal(r.changed, false);
  assert.equal(r.target.page, 2);
  assert.equal(r.quoteNow, "Agile model");
});

test("a changed quote is reported, with what it says now", () => {
  const v2 = v1.map((l) => (l.top === 0.3 ? { ...l, text: "Development followed the Scrum framework delivering the system in short iterations" } : l));
  const [r] = check([textRemark(1, 1, 0.3, 0.12, 0.7, "Agile model")], v1, v2);
  assert.equal(r.changed, true);
  assert.match(r.removedWords, /Agile model/);
  assert.match(r.addedWords, /Scrum framework/);
});

test("words added right after a quote are not a change to the quote", () => {
  const v2 = v1.map((l) => (l.top === 0.3 ? { ...l, text: "Development followed the Agile model with two week sprints delivering the system in short iterations" } : l));
  const [r] = check([textRemark(1, 1, 0.3, 0.12, 0.7, "Agile model")], v1, v2);
  assert.equal(r.changed, false);
});

// A figure: a picture between two paragraphs, with its caption under it.
const figurePage = (page, top, label, labels = []) => [
  ...labels.map((text, i) => ({ page, top: top + 0.04 + i * 0.03, text, left: 0.3 })),
  { page, top: top + 0.3, text: `${label}. Entity-relationship diagram of BarangayConnect`, left: 0.25 },
];
const boxRemark = (id, page, top, label) => ({
  id, type: "box", page_number: page, figure_label: label, text: "Foreign key is not shown.",
  highlightRects: [{ top_position: top, left_position: 0.2, highlight_width: 0.6, highlight_height: 0.27 }],
});

test("a figure that moved down the page is found by its caption", () => {
  const before = [...para(1, 0.05, 3, "top"), ...figurePage(1, 0.2, "Figure 3.2")];
  const after = [...para(1, 0.05, 7, "top"), ...figurePage(1, 0.3, "Figure 3.2")];
  const [r] = check([boxRemark(1, 1, 0.2, "Figure 3.2")], before, after);
  assert.equal(r.foundBy, "caption");
  assert.equal(r.target.page, 1);
  assert.ok(Math.abs(r.target.rects[0].top_position - 0.3) < 1e-9, `box should move to 0.3, got ${r.target.rects[0].top_position}`);
});

test("a figure moved to the next page and renumbered is still found", () => {
  const before = [...para(1, 0.05, 3, "top"), ...figurePage(1, 0.2, "Figure 3.2")];
  const after = [...para(1, 0.05, 30, "top"), ...figurePage(2, 0.1, "Figure 3.3")];
  const [r] = check([boxRemark(1, 1, 0.2, "Figure 3.2")], before, after);
  assert.equal(r.foundBy, "caption");
  assert.equal(r.target.page, 2);
  assert.equal(r.figureNow, "Figure 3.3");
  assert.ok(Math.abs(r.target.rects[0].top_position - 0.1) < 1e-9);
});

test("a label added inside a diagram is a change, and says which", () => {
  const labels = ["REQUEST", "request_id (PK)", "resident_id (FK)", "document_type"];
  const before = figurePage(1, 0.2, "Figure 3.2", labels);
  const after = figurePage(1, 0.2, "Figure 3.2", ["REQUEST", "request_id (PK)", "resident_id (FK)", "official_id (FK)", "document_type"]);
  const [r] = check([boxRemark(1, 1, 0.2, "Figure 3.2")], before, after);
  assert.equal(r.changed, true);
  assert.equal(r.addedWords, "official_id (FK)");
  assert.equal(r.removedWords, "");
});

test("an unchanged diagram with labels is not a change", () => {
  const labels = ["REQUEST", "request_id (PK)", "resident_id (FK)"];
  const before = [...para(1, 0.05, 3, "top"), ...figurePage(1, 0.2, "Figure 3.2", labels)];
  const after = [...para(1, 0.05, 5, "top"), ...figurePage(1, 0.25, "Figure 3.2", labels)];
  const [r] = check([boxRemark(1, 1, 0.2, "Figure 3.2")], before, after);
  assert.equal(r.changed, false);
});

test("a diagram that is only a picture cannot be judged from its words", () => {
  const before = figurePage(1, 0.2, "Figure 3.2");
  const [r] = check([boxRemark(1, 1, 0.2, "Figure 3.2")], before, before);
  assert.equal(r.changed, null);
});

test("a pin follows its figure too", () => {
  const before = [...para(1, 0.05, 3, "top"), ...figurePage(1, 0.2, "Figure 3.1")];
  const after = [...para(1, 0.05, 7, "top"), ...figurePage(1, 0.3, "Figure 3.1")];
  const pin = { id: 2, type: "pin", page_number: 1, figure_label: "Figure 3.1", x_position: 0.4, y_position: 0.3, highlightRects: [], text: "Label this." };
  const [r] = check([pin], before, after);
  assert.equal(r.target.page, 1);
  assert.ok(Math.abs(r.target.y - 0.4) < 1e-9);
  assert.equal(r.target.x, 0.4);
});

// Pictures: a white square with a black bar, as RGBA.
const picture = (w, h, bars) => {
  const d = new Uint8ClampedArray(w * h * 4).fill(255);
  bars.forEach(([x0, y0, x1, y1]) => {
    for (let y = y0; y < y1; y++) for (let x = x0; x < x1; x++) { const i = (y * w + x) * 4; d[i] = d[i + 1] = d[i + 2] = 0; }
  });
  return d;
};

test("the same picture is not a change, even cut out a pixel apart", () => {
  const a = picture(120, 80, [[20, 20, 100, 30], [20, 50, 60, 70]]);
  assert.equal(pictureDifference(a, a, 120, 80).share, 0);
  const nudged = picture(120, 80, [[21, 21, 101, 31], [21, 51, 61, 71]]);
  assert.ok(pictureDifference(a, nudged, 120, 80).share < PICTURE_CHANGED);
});

test("a line added to a picture is a change", () => {
  const a = picture(120, 80, [[20, 20, 100, 30]]);
  const b = picture(120, 80, [[20, 20, 100, 30], [20, 40, 70, 44]]);
  assert.ok(pictureDifference(a, b, 120, 80).share > PICTURE_CHANGED);
});

import { findPicture, greyOf } from "../../resources/js/component/reader/revisionCheck.js";

// A white page with a diagram drawn on it at (x, y): two boxes joined by a line.
const pageWith = (w, h, at, extra = false) => {
  const d = new Uint8ClampedArray(w * h * 4).fill(255);
  const ink = (x0, y0, x1, y1) => { for (let y = y0; y < y1; y++) for (let x = x0; x < x1; x++) { const i = (y * w + x) * 4; d[i] = d[i + 1] = d[i + 2] = 0; } };
  if (at) {
    const [x, y] = at;
    ink(x, y, x + 30, y + 2); ink(x, y + 18, x + 30, y + 20); ink(x, y, x + 2, y + 20); ink(x + 28, y, x + 30, y + 20);
    ink(x + 50, y, x + 80, y + 2); ink(x + 50, y + 18, x + 80, y + 20); ink(x + 50, y, x + 52, y + 20); ink(x + 78, y, x + 80, y + 20);
    ink(x + 30, y + 9, x + 50, y + 11);
    if (extra) ink(x + 30, y + 15, x + 50, y + 16);
  }
  return greyOf(d, w, h);
};
const cut = (g, w, x, y, cw, ch) => {
  const out = new Float32Array(cw * ch);
  for (let r = 0; r < ch; r++) for (let c = 0; c < cw; c++) out[r * cw + c] = g[(y + r) * w + x + c];
  return out;
};

test("finds a diagram pushed to the top of the next page", () => {
  // On the old page it sat low down; on the new page it is near the top.
  const oldPage = pageWith(160, 200, [30, 140]);
  const tpl = cut(oldPage, 160, 25, 135, 90, 30);
  const next = pageWith(160, 200, [40, 20]);
  const hit = findPicture(next, 160, 200, tpl, 90, 30);
  assert.equal(hit.found, true);
  assert.deepEqual([hit.x, hit.y], [35, 15]);
});

test("finds a diagram that changed a little", () => {
  const oldPage = pageWith(160, 200, [30, 60]);
  const tpl = cut(oldPage, 160, 25, 55, 90, 30);
  const hit = findPicture(pageWith(160, 200, [30, 100], true), 160, 200, tpl, 90, 30);
  assert.equal(hit.found, true);
  assert.deepEqual([hit.x, hit.y], [25, 95]);
});

test("does not claim to find a diagram on a page without it", () => {
  const tpl = cut(pageWith(160, 200, [30, 60]), 160, 25, 55, 90, 30);
  assert.equal(findPicture(pageWith(160, 200, null), 160, 200, tpl, 90, 30).found, false);
});
