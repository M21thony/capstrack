// Where each word of a line of PDF text sits. Run with `npm run test:js`.
import { test } from "node:test";
import assert from "node:assert/strict";
import { layoutPiece, naturalScale } from "../../resources/js/component/reader/rects.js";

// A font where "i" is narrow, "m" wide and a space a quarter of an em.
const WIDTHS = { i: 0.25, m: 0.75, " ": 0.25 };
const measure = (text) => [...text].reduce((sum, c) => sum + (WIDTHS[c] ?? 0.5), 0);
const words = (runs) => runs.filter((r) => !r.space).map((r) => [r.text, +r.left.toFixed(4), +r.width.toFixed(4)]);

test("a line set left, centred or right puts each word where its letters end", () => {
  const line = "iii mmm";
  assert.deepEqual(words(layoutPiece(line, measure(line), measure)), [["iii", 0, 0.75], ["mmm", 1, 2.25]]);
});

test("a justified line shares its extra room out between its words, not inside them", () => {
  const line = "iii mmm iii";
  const runs = layoutPiece(line, measure(line) + 2, measure);
  assert.deepEqual(words(runs), [["iii", 0, 0.75], ["mmm", 2, 2.25], ["iii", 5.5, 0.75]]);
  assert.equal(runs.at(-1).left + runs.at(-1).width, measure(line) + 2);
});

test("a PDF font a little wider than the like font stretches every letter alike", () => {
  const line = "iii mmm";
  const runs = layoutPiece(line, measure(line) * 1.005, measure);
  assert.deepEqual(words(runs), [["iii", 0, 0.7537], ["mmm", 1.005, 2.2612]]);
});

test("the PDF font's own width is learnt from its narrowest line, so a justified one still spreads", () => {
  const natural = naturalScale([{ str: "mmmm iiii", width: measure("mmmm iiii") * 1.1 }, { str: "iiii mmmm iiii", width: measure("iiii mmmm iiii") * 1.1 + 3 }], measure);
  assert.ok(Math.abs(natural - 1.1) < 1e-9);
  const line = "iiii mmmm iiii";
  const runs = layoutPiece(line, measure(line) * 1.1 + 3, measure, natural);
  assert.deepEqual(words(runs), [["iiii", 0, 1.1], ["mmmm", 2.875, 3.3], ["iiii", 7.95, 1.1]]);
});

test("a piece of one word, or with spaces only at its ends, is fitted to its width", () => {
  assert.deepEqual(words(layoutPiece("mim", 3.5, measure)), [["mim", 0, 3.5]]);
  assert.deepEqual(words(layoutPiece(" mim ", 4, measure)), [["mim", 0.4444, 3.1111]]);
  assert.equal(naturalScale([{ str: "ab", width: 9 }], measure), 1);
});
