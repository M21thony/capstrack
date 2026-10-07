// Showing a team's draw.io files after the PDF: which earlier page each
// page is, and how a shape is recoloured. Run with `npm run test:js`.
import { test } from "node:test";
import assert from "node:assert/strict";
import { pairPages, withStyle } from "../../resources/js/component/reader/drawioDiff.js";

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
