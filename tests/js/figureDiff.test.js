// Comparing the diagrams of two versions: which picture is which, and what
// a pair amounts to. Run with `npm run test:js`.
import { test } from "node:test";
import assert from "node:assert/strict";
import { OVERHAUL, figureMarks, hungarian, kindOf, overlapping, pairUp, spotTone } from "../../resources/js/component/reader/figureDiff.js";

test("hungarian finds the cheapest pairing, not the greedy one", () => {
  // Greedy takes 0->0 (1), leaving 1->1 (100): 101. Best is 0->1, 1->0: 4.
  assert.deepEqual([...hungarian([[1, 2], [2, 100]])], [1, 0]);
  assert.deepEqual([...hungarian([[0.9, 0.1, 0.5], [0.1, 0.8, 0.6], [0.5, 0.6, 0.05]])], [1, 0, 2]);
});

test("each earlier figure is paired with the one that looks most like it", () => {
  assert.deepEqual(pairUp([[0.4, 0.03], [0.02, 0.5]], 2), [1, 0]);
});

test("a figure that was dropped stays unpaired", () => {
  // Three before, two now: the one unlike everything now is the removed one.
  assert.deepEqual(pairUp([[0.05, 0.6], [0.7, 0.8], [0.6, 0.04]], 2), [0, null, 1]);
});

test("with the same number of figures, even a redone one keeps its partner", () => {
  assert.deepEqual(pairUp([[0.9]], 1), [0]);
  assert.deepEqual(pairUp([], 2), []);
});

test("a pair is redone past the cut-off, edited when spots differ, else the same", () => {
  assert.equal(kindOf(OVERHAUL + 0.01, 3), "big");
  assert.equal(kindOf(0.08, 2), "small");
  assert.equal(kindOf(0.01, 0), "same");
});

test("a spot rewritten in place is told apart from a plain removal or addition", () => {
  assert.equal(overlapping(400, 380), true); // a retyped title
  assert.equal(overlapping(500, 0), false); // handwriting taken out
  assert.equal(overlapping(0, 300), false); // a new line
  assert.equal(overlapping(800, 60), false); // mostly removed, a sliver added
  assert.equal(overlapping(20, 20), false); // too small to matter
});

test("each spot is boxed as rewritten, removed or added, and specks are left alone", () => {
  assert.equal(spotTone(400, 380), "rewritten");
  assert.equal(spotTone(500, 0), "removed");
  assert.equal(spotTone(0, 300), "added");
  assert.equal(spotTone(800, 60), "removed");
  assert.equal(spotTone(10, 20), null);
});

test("changed spots are boxed on the pages of both versions, where the diagram sits", () => {
  // A diagram filling the right half of the page, 400 wide and 200 tall when compared.
  const ink = { left: 0.5, top: 0.2, width: 0.4, height: 0.3 };
  const spot = { x: 0, y: 100, w: 200, h: 100 }; // its bottom-left quarter
  const { marks, oldMarks } = figureMarks([
    { kind: "small", spots: [spot], oldPage: 2, newPage: 3, oldInk: ink, newInk: ink, oldH: 200, newH: 200, fit: null },
    { kind: "new", newPage: 5, newInk: ink },
    { kind: "same", oldPage: 1, newPage: 1, oldInk: ink, newInk: ink },
  ]);
  const near = (a, b) => assert.ok(Math.abs(a - b) < 0.01, `${a} vs ${b}`);
  const [r] = oldMarks[2][0].rects;
  near(r.left_position, 0.5);
  near(r.top_position, 0.35);
  near(r.highlight_width, 0.2);
  near(r.highlight_height, 0.15);
  assert.equal(marks[3][0].rects.length, 1);
  near(marks[5][0].rects[0].highlight_width, 0.4); // a new diagram is boxed whole
  assert.equal(marks[1], undefined); // an unchanged one is not boxed
  assert.equal(oldMarks[5], undefined);
});

test("on this version, a spot follows the diagram when it was rescaled to line up", () => {
  const ink = { left: 0, top: 0, width: 1, height: 1 };
  const { marks } = figureMarks([{ kind: "small", spots: [{ x: 120, y: 20, w: 40, h: 40 }], oldPage: 1, newPage: 1, oldInk: ink, newInk: ink, oldH: 400, newH: 400, fit: { s: 2, x: 0, y: 0 } }]);
  assert.ok(Math.abs(marks[1][0].rects[0].left_position - (60 / 400 - 0.004)) < 0.001);
});

test("a spot is highlighted like a changed word, with a thin bar on the version it is missing from", () => {
  const ink = { left: 0, top: 0, width: 1, height: 1 };
  const spot = { x: 10, y: 10, w: 20, h: 20 };
  const { marks, oldMarks } = figureMarks([{ kind: "small", spots: [spot, spot, spot], tones: ["removed", "added", "rewritten"], oldPage: 1, newPage: 1, oldInk: ink, newInk: ink, oldH: 400, newH: 400, fit: null }]);
  assert.deepEqual(oldMarks[1].map((m) => [m.diff, !!m.inserted, m.rects[0].highlight_width]), [[true, false, oldMarks[1][0].rects[0].highlight_width], [true, true, 0.003], [true, false, oldMarks[1][2].rects[0].highlight_width]]);
  assert.deepEqual(marks[1].map((m) => [m.diff, !!m.removed]), [[true, true], [true, false], [true, false]]);
  assert.equal(marks[1][0].rects[0].highlight_width, 0.003);
  assert.ok(marks[1][1].rects[0].highlight_width > 0.05);
  assert.equal(marks[1][0].label, undefined); // no "changed" / "+ added" tags
});
