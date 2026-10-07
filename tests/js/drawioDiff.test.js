// Showing a team's draw.io files after the PDF: which earlier page each
// page is, and how a shape is recoloured. Run with `npm run test:js`.
import { test } from "node:test";
import assert from "node:assert/strict";
import { cellAt, comparePages, followShapes, pairPages, shapeRects, withStyle } from "../../resources/js/component/reader/drawioDiff.js";

const page = (file, name, index, words = "") => ({ file, name, index, words: new Set(words.split(" ").filter(Boolean)) });

test("a page is paired with the same page of the same file", () => {
  const before = [page("erd.drawio", "ERD", 0), page("dfd.drawio", "Level 1", 0), page("dfd.drawio", "Level 0", 1)];
  const now = [page("dfd.drawio", "Level 0", 0), page("dfd.drawio", "Level 1", 1), page("erd.drawio", "ERD", 0)];
  assert.deepEqual(pairPages(now, before), [2, 1, 0]);
});

test("a renamed page keeps its place in the same file", () => {
  assert.deepEqual(pairPages([page("dfd.drawio", "Level 1 (new)", 0)], [page("dfd.drawio", "Level 1", 0)]), [0]);
});

test("a renamed file is found by the words it shares", () => {
  const before = [page("a.drawio", "P", 0, "student upload document review"), page("b.drawio", "P", 0, "expert grade defense schedule")];
  const now = [page("c.drawio", "P", 0, "expert grade defense schedule panel"), page("d.drawio", "P", 0, "student upload document")];
  assert.deepEqual(pairPages(now, before), [1, 0]);
});

test("a new page that shares little with any earlier one is unpaired", () => {
  const before = [page("a.drawio", "P", 0, "student upload document review")];
  assert.deepEqual(pairPages([page("z.drawio", "Q", 0, "ollama server model review")], before), [-1]);
  assert.deepEqual(pairPages([page("z.drawio", "Q", 0)], []), [-1]);
});

test("each earlier page is paired at most once", () => {
  const before = [page("a.drawio", "P", 0, "one two three")];
  assert.deepEqual(pairPages([page("x.drawio", "P", 0, "one two three"), page("y.drawio", "P", 0, "one two three")], before), [0, -1]);
});

test("a style keeps its own keys and takes the new values", () => {
  assert.equal(withStyle("rounded=0;whiteSpace=wrap;fillColor=#fff;", { fillColor: "#e0f7e8", gradientColor: "none" }),
    "rounded=0;whiteSpace=wrap;fillColor=#e0f7e8;gradientColor=none;");
  assert.equal(withStyle("text;html=1", { html: 1 }), "text;html=1;");
  assert.equal(withStyle(null, { strokeWidth: 2 }), "strokeWidth=2;");
});

// Just enough of a draw.io cell for reading a page: its attributes and, for a shape, its place.
const cell = (attrs, box) => {
  const geometry = box && { getAttribute: (n) => box[n], getElementsByTagName: () => [] };
  return {
    parentNode: { nodeName: "root" },
    getAttribute: (n) => attrs[n] ?? null,
    getElementsByTagName: (t) => (t === "mxGeometry" && geometry ? [geometry] : []),
  };
};

test("an arrow that ends on another arrow is read, whichever comes first", () => {
  const els = [
    cell({ id: "1" }),
    cell({ id: "late", edge: "1", parent: "1", source: "early", target: "b", value: "Yes" }),
    cell({ id: "a", vertex: "1", parent: "1", value: "Start" }, { x: 0, y: 0, width: 80, height: 40 }),
    cell({ id: "b", vertex: "1", parent: "1", value: "End" }, { x: 0, y: 200, width: 80, height: 40 }),
    cell({ id: "early", edge: "1", parent: "1", source: "a", target: "b" }),
  ];
  assert.deepEqual(comparePages({ els }, { els }).spots, []);
});

test("a removed table is one change, its columns not taken for its lines' labels", () => {
  const table = (id, x, column, name) => [
    cell({ id, vertex: "1", parent: "1", value: id, style: "swimlane;" }, { x, y: 0, width: 200, height: 60 }),
    cell({ id: column, vertex: "1", parent: id, value: name, style: "text;fillColor=none;" }, { x: 0, y: 24, width: 200, height: 30 }),
    cell({ id: `${column}-key`, vertex: "1", parent: column, value: "", style: "rhombus;fillColor=#e05a4f;" }, { x: 4, y: 8, width: 12, height: 12 }),
  ];
  const teams = table("teams", 400, "team_id", "team_id");
  const before = [cell({ id: "1" }), ...table("students", 0, "student_id", "student_id"), ...teams,
    cell({ id: "fk", edge: "1", parent: "1", source: "student_id", target: "team_id", style: "endArrow=ERmany;" })];
  const { spots } = comparePages({ els: before }, { els: [cell({ id: "1" }), ...teams] });
  assert.equal(spots.length, 1);
  assert.deepEqual(spots[0].said, ["− “students”", "− “student_id”", "1 line removed"]);
});

test("a click picks the arrow near its line, else the smallest box under it", () => {
  const targets = [
    { id: "pool", kind: "vertex", box: [0, 0, 400, 300] },
    { id: "login", kind: "vertex", box: [20, 20, 120, 80] },
    { id: "e1", kind: "edge", box: [120, 50, 300, 50], pts: [[120, 50], [300, 50]], labels: [[190, 30, 230, 45]] },
  ];
  assert.equal(cellAt(targets, 60, 50).id, "login");
  assert.equal(cellAt(targets, 200, 200).id, "pool");
  assert.equal(cellAt(targets, 200, 54).id, "e1");
  assert.equal(cellAt(targets, 210, 35).id, "e1");
  assert.equal(cellAt(targets, 200, 70).id, "pool");
  assert.equal(cellAt(targets, 500, 500), null);
});

test("a shape remark follows its cell: unchanged, renamed, or removed", () => {
  const before = [
    cell({ id: "1" }),
    cell({ id: "a", vertex: "1", parent: "1", value: "Login" }, { x: 0, y: 0, width: 80, height: 40 }),
    cell({ id: "b", vertex: "1", parent: "1", value: "Dashboard" }, { x: 0, y: 200, width: 80, height: 40 }),
    cell({ id: "c", vertex: "1", parent: "1", value: "Logout" }, { x: 200, y: 0, width: 80, height: 40 }),
  ];
  const after = [
    cell({ id: "1" }),
    cell({ id: "a2", vertex: "1", parent: "1", value: "Login" }, { x: 40, y: 0, width: 80, height: 40 }),
    cell({ id: "b", vertex: "1", parent: "1", value: "Home" }, { x: 0, y: 200, width: 80, height: 40 }),
  ];
  const compare = comparePages({ els: before }, { els: after });
  const ref = (...ids) => ({ cells: ids.map((id) => ({ id, kind: "vertex" })) });
  const moved = followShapes(ref("a"), compare);
  assert.equal(moved.status, "unchanged");
  assert.equal(moved.cells[0].neu.id, "a2");
  assert.deepEqual(followShapes(ref("b"), compare).cells[0].what, ["renamed"]);
  assert.equal(followShapes(ref("b"), compare).status, "changed");
  assert.equal(followShapes(ref("c"), compare).status, "removed");
  assert.equal(followShapes(ref("a", "c"), compare).status, "changed");
  assert.equal(followShapes(ref("gone"), compare).status, "removed");
});

test("a bent arrow is outlined along its legs, not by one box over what it goes around", () => {
  const pic = { width: 1000, height: 1000 };
  const arrow = { id: "e", kind: "edge", box: [100, 100, 500, 500], pts: [[100, 100], [500, 100], [500, 500]], labels: [] };
  const rects = shapeRects(arrow, pic);
  assert.equal(rects.length, 2);
  // Nothing sits over the middle of the bend's box.
  const covers = (r, x, y) => x >= r.left_position && x <= r.left_position + r.highlight_width && y >= r.top_position && y <= r.top_position + r.highlight_height;
  assert.ok(!rects.some((r) => covers(r, 0.3, 0.3)));
  assert.ok(covers(rects[0], 0.3, 0.1) && covers(rects[1], 0.5, 0.3));
});

test("a slanted arrow goes in short steps, a box stays one box", () => {
  const pic = { width: 1000, height: 1000 };
  const slanted = shapeRects({ kind: "edge", box: [0, 0, 300, 300], pts: [[0, 0], [300, 300]], labels: [] }, pic);
  assert.ok(slanted.length > 10 && slanted.every((r) => r.highlight_width < 0.03));
  assert.equal(shapeRects({ kind: "vertex", box: [10, 10, 60, 40] }, pic).length, 1);
});
