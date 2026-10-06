// Comparing the diagrams of two versions: which picture is which, and what
// a pair amounts to. Run with `npm run test:js`.
import { test } from "node:test";
import assert from "node:assert/strict";
import { OVERHAUL, changedLetters, changedWords, drawingAreas, enclosed, joinWords, movesOf, figureMarks, hungarian, kindOf, oldOverlayRect, overlapping, overlayRect, pairUp, spotTone, textLike, textLines } from "../../resources/js/component/reader/figureDiff.js";

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

test("the one-picture view sits over this version's diagram, undoing the move that lined it up", () => {
  const near = (a, b) => assert.ok(Math.abs(a - b) < 1e-9, `${a} vs ${b}`);
  const f = { newInk: { left: 0.1, top: 0.2, width: 0.8, height: 0.4 }, newH: 300 };
  const same = overlayRect(f, 600); // not moved: exactly over the diagram
  near(same.left_position, 0.1); near(same.top_position, 0.2); near(same.highlight_width, 0.8); near(same.highlight_height, 0.4);
  const r = overlayRect({ ...f, fit: { s: 2, x: 40, y: 30 } }, 600);
  near(r.left_position, 0.1 + (-20 / 400) * 0.8);
  near(r.top_position, 0.2 + (-15 / 300) * 0.4);
  near(r.highlight_width, 0.4);
  near(r.highlight_height, 0.2);
});

test("the inside of a closed shape is found, but not an open one or a letter's small hole", () => {
  const w = 40, ink = new Uint8Array(w * w);
  const line = (x0, y0, x1, y1) => { for (let y = y0; y <= y1; y++) for (let x = x0; x <= x1; x++) ink[y * w + x] = 1; };
  line(5, 5, 25, 5); line(5, 25, 25, 25); line(5, 5, 5, 25); line(25, 5, 25, 25); // a closed square, 19x19 inside
  assert.equal(enclosed(ink, w, [0, 0, w, w]).length, 19 * 19);
  assert.equal(enclosed(ink, w, [0, 0, w, w], 400).length, 0); // too small to count
  line(25, 5, 25, 25); ink.fill(0, 15 * w + 25, 15 * w + 26); // open one side
  for (let y = 6; y < 25; y++) ink[y * w + 25] = 0;
  assert.equal(enclosed(ink, w, [0, 0, w, w]).length, 0);
});

test("shapes joined by lines are one drawn diagram; underlines, a lone frame and a picture's border are not", () => {
  const box = (left, top, width, height) => ({ left, top, width, height });
  const near = (a, b) => assert.ok(Math.abs(a - b) < 1e-9, `${a} vs ${b}`);
  // Two boxes and the arrow between them.
  const flow = [box(0.2, 0.3, 0.1, 0.05), box(0.3, 0.32, 0.1, 0.001), box(0.4, 0.3, 0.1, 0.05), box(0.2, 0.36, 0.05, 0.05)];
  const [d] = drawingAreas(flow);
  near(d.left, 0.19); near(d.top, 0.29); near(d.width, 0.32); near(d.height, 0.13);
  assert.equal(drawingAreas(flow).length, 1);
  assert.equal(drawingAreas([box(0.1, 0.5, 0.6, 0.001), box(0.1, 0.52, 0.6, 0.001)]).length, 0); // underlines
  assert.equal(drawingAreas([box(0.1, 0.1, 0.5, 0.3)]).length, 0); // one frame alone
  assert.equal(drawingAreas([box(0, 0, 1, 1), box(0.1, 0.1, 0.05, 0.05)]).length, 0); // page background dropped
  assert.equal(drawingAreas(flow, [box(0.18, 0.28, 0.4, 0.2)]).length, 0); // a pasted picture's frame
  const prose = [0.31, 0.33, 0.35].map((t) => box(0.2, t, 0.28, 0.01));
  assert.equal(drawingAreas(flow, [], prose).length, 0); // a panel behind long lines of text
  assert.equal(drawingAreas(flow, [], prose.map((l) => ({ ...l, width: 0.06 }))).length, 1); // short labels are fine
  // A one-column flowchart: narrow, but tall.
  const column = [0.1, 0.2, 0.3, 0.4].flatMap((t) => [box(0.4, t, 0.12, 0.06), box(0.46, t + 0.06, 0.001, 0.04)]);
  assert.equal(drawingAreas(column).length, 1);
  // Its labels nearly fill the column, but each sits in its own box, so it is no text panel.
  assert.equal(drawingAreas(column, [], [0.1, 0.2, 0.3, 0.4].map((t) => box(0.41, t + 0.02, 0.1, 0.01))).length, 1);
  assert.equal(drawingAreas(column.slice(0, 4)).length, 0); // two boxes alone stay too small
});

test("a changed label is told apart from a changed shape or arrow", () => {
  const w = 200, ink = new Uint8Array(w * w);
  const line = (x0, y0, x1, y1) => { for (let y = y0; y <= y1; y++) for (let x = x0; x <= x1; x++) ink[y * w + x] = 1; };
  for (let i = 0; i < 6; i++) line(10 + i * 12, 10, 17 + i * 12, 22); // six letters in a row
  assert.equal(textLike(ink, w, [0, 0, 100, 40]), true);
  ink.fill(0);
  line(10, 60, 150, 61); line(140, 55, 150, 66); // an arrow
  assert.equal(textLike(ink, w, [0, 40, w, 100]), false);
  ink.fill(0);
  line(20, 100, 180, 101); line(20, 160, 180, 161); line(20, 100, 21, 161); line(179, 100, 180, 161); // a box
  for (let i = 0; i < 3; i++) line(60 + i * 12, 125, 67 + i * 12, 137); // with a short label in it
  assert.equal(textLike(ink, w, [0, 95, w, 170]), false);
  ink.fill(0);
  for (const [x, len] of [[10, 62], [80, 49], [140, 40]]) line(x, 110, x + len, 119); // words whose letters ran together
  line(10, 130, 190, 131); // and the heading's dividing line, moved
  assert.equal(textLike(ink, w, [0, 100, w, 140]), true);
});

test("a word moved a few pixels is judged by the whole word, not its changed edges", () => {
  const w = 100, changed = new Uint8Array(w * w), ink = new Uint8Array(w * w);
  for (let y = 20; y <= 29; y++) for (let x = 10; x <= 60; x++) ink[y * w + x] = 1; // the word as drawn
  for (let x = 10; x <= 60; x++) changed[20 * w + x] = 1; // only its top edge has nothing near it in the other version
  assert.deepEqual(textLines(changed, w, [0, 0, w, w], 1), []); // the edge alone is a line
  assert.deepEqual(textLines(changed, w, [0, 0, w, w], 1, 30, ink), [[10, 20, 61, 30]]);
});

test("a changed shape is coloured on the diagram instead of highlighted like a word", () => {
  const ink = { left: 0, top: 0, width: 1, height: 1 };
  const spot = { x: 10, y: 10, w: 20, h: 20 };
  const { marks, oldMarks } = figureMarks([{ kind: "small", spots: [spot, spot], tones: ["added", "added"], texts: [false, true], oldPage: 1, newPage: 1, oldInk: ink, newInk: ink, oldH: 400, newH: 400, fit: null }]);
  assert.equal(marks[1].length, 1); // only the label
  assert.equal(oldMarks[1].length, 1);
});

test("a changed label is boxed tightly, one box per line of words", () => {
  const w = 200, ink = new Uint8Array(w * w);
  const line = (x0, y0, x1, y1, v) => { for (let y = y0; y <= y1; y++) for (let x = x0; x <= x1; x++) ink[y * w + x] = v; };
  for (let i = 0; i < 4; i++) line(20 + i * 10, 30, 26 + i * 10, 40, 2); // a word
  for (let i = 0; i < 3; i++) line(20 + i * 10, 50, 26 + i * 10, 60, 2); // the next line
  line(150, 10, 151, 190, 1); // a removed line, not writing
  assert.deepEqual(textLines(ink, w, [0, 0, w, w], 2), [[20, 30, 57, 41], [20, 50, 47, 61]]);
  assert.deepEqual(textLines(ink, w, [0, 0, w, w], 1), []);
});

test("a label's highlight sits on its words, and a side without words gets the thin bar", () => {
  const ink = { left: 0, top: 0, width: 1, height: 1 };
  const spot = { x: 0, y: 0, w: 200, h: 200 };
  const words = [40, 60, 120, 80]; // in the sharper picture: x 20..60, y 30..40 of the spot
  const { marks, oldMarks } = figureMarks([{ kind: "small", spots: [spot], tones: ["rewritten"], texts: [true], lines: [{ old: [], neu: [words] }], oldPage: 1, newPage: 1, oldInk: ink, newInk: ink, oldH: 400, newH: 400, fit: null }]);
  const [r] = marks[1][0].rects;
  assert.ok(Math.abs(r.left_position - (20 / 400 - 0.004)) < 1e-9 && Math.abs(r.highlight_width - (40 / 400 + 0.008)) < 1e-9);
  assert.equal(oldMarks[1][0].inserted, true);
  assert.equal(oldMarks[1][0].rects[0].highlight_width, 0.003);
});

test("the earlier side's colouring sits over the earlier diagram", () => {
  const r = oldOverlayRect({ oldInk: { left: 0.1, top: 0.2, width: 0.4, height: 0.2 }, oldH: 300 }, 800);
  assert.deepEqual([r.left_position, r.top_position, r.highlight_width], [0.1, 0.2, 0.4]);
  assert.ok(Math.abs(r.highlight_height - (800 / 2 / 300) * 0.2) < 1e-9);
});

const w = (str, x, y, extra = {}) => ({ str, x, y, w: str.length * 8, h: 12, ...extra });

test("a box that moved is found from the words that appear once in both versions", () => {
  const olds = [w("team", 10, 10), w("id", 10, 30), w("id", 10, 50)];
  const news = [w("team", 10, 340), w("id", 10, 360), w("id", 90, 90)];
  assert.deepEqual(movesOf(olds, news), [[0, 330]]);
});

test("a renamed or removed word is changed, a word that moved with its box or along its line is not", () => {
  const olds = [w("team", 10, 10), w("team_name", 10, 30), w("course", 10, 50), w("ENUM", 100, 70)];
  const news = [w("team", 10, 340), w("team_title", 10, 360), w("ENUM", 106, 70)];
  const { old, neu } = changedWords(olds, news, [[0, 330]]);
  assert.deepEqual(old.map((it) => it.str), ["team_name", "course"]);
  assert.deepEqual(neu.map((it) => it.str), ["team_title"]);
});

test("the same word a little way off in a diagram drawn bigger moved, it did not change; far off it did", () => {
  const { old, neu } = changedWords([w("CaPSA", 25, 753), w("Student", 25, 400)], [w("CaPSA", 38, 735), w("Student", 25, 600)]);
  assert.deepEqual(old.map((it) => it.str), ["Student"]);
  assert.deepEqual(neu.map((it) => it.str), ["Student"]);
});

test("changed words side by side on a line share one highlight", () => {
  const boxes = joinWords([w("VARCHAR(50)", 60, 50), w("course", 10, 50), w("status", 10, 90)]);
  assert.equal(boxes.length, 2);
  assert.deepEqual([boxes[0].x, boxes[0].w], [10, 60 + 88 - 10]);
});

test("a word rewritten in place is marked only where its name parts differ", () => {
  assert.deepEqual(changedLetters("team_name", "team_title"), [[5, 9], [5, 10]]);
  assert.deepEqual(changedLetters("VARCHAR(50)", "VARCHAR(100)"), [[8, 10], [8, 11]]);
  assert.deepEqual(changedLetters("id", "id_2"), [[2, 2], [2, 4]]);
  assert.deepEqual(changedLetters("studnet", "student"), [[0, 7], [0, 7]]);
});

test("the highlight of a rewritten word covers only its changed letters, and none when nothing was taken out", () => {
  const { old, neu } = changedWords([w("team_name", 10, 30), w("id", 10, 50)], [w("team_title", 10, 30), w("id_2", 10, 50)]);
  assert.deepEqual(old.map((it) => [it.str, it.x, it.w]), [["team_name", 10 + 5 * 8, 4 * 8]]);
  assert.deepEqual(neu.map((it) => [it.str, it.x, it.w]), [["team_title", 10 + 5 * 8, 5 * 8], ["id_2", 10 + 2 * 8, 2 * 8]]);
});
