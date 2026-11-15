// The section list the annotator reads out of a PDF's bookmarks.
// Run with `npm run test:js` - Node's own test runner, nothing to install.
import { test } from "node:test";
import assert from "node:assert/strict";
import { readOutline, sectionAt } from "../../resources/js/component/reader/outline.js";

test("a page's section is its chapter and the sub-heading it sits under", () => {
  const flat = [
    { id: 0, title: "Abstract", page: 2, path: [] },
    { id: 1, title: "CHAPTER II - REVIEW", page: 10, path: [] },
    { id: 2, title: "2.1 Related Literature", page: 11, path: [1] },
    { id: 3, title: "2.1.1 Local", page: 12, path: [1, 2] },
    { id: 4, title: "Chapter 3", page: 20, path: [] },
  ];
  assert.equal(sectionAt(flat, 1), "");
  assert.equal(sectionAt(flat, 5), "Abstract");
  assert.equal(sectionAt(flat, 10), "Chapter 2");
  assert.equal(sectionAt(flat, 15), "Chapter 2 › 2.1 Related Literature › 2.1.1 Local");
  assert.equal(sectionAt(flat, 25), "Chapter 3");
  assert.equal(sectionAt([], 3), "");
});

// Stands in for the PDF reader: page references are objects, named places are strings.
const pdf = (outline) => ({
  getOutline: async () => outline,
  getDestination: async (name) => (name === "ch2" ? [{ num: 40 }] : null),
  getPageIndex: async (ref) => { if (ref.num === 99) throw new Error("no such page"); return ref.num / 10; },
});

test("bookmarks come out flat, in reading order, each with its page and what it sits under", async () => {
  const flat = await readOutline(pdf([
    { title: "Chapter 1", dest: [{ num: 20 }], items: [{ title: "Background", dest: [{ num: 30 }], items: [] }] },
    { title: "Chapter 2", dest: "ch2", items: [] },
    { title: "Appendix", dest: [6], items: [] },
  ]));
  assert.deepEqual(flat, [
    { id: 0, title: "Chapter 1", page: 3, path: [], kids: true },
    { id: 1, title: "Background", page: 4, path: [0], kids: false },
    { id: 2, title: "Chapter 2", page: 5, path: [], kids: false },
    { id: 3, title: "Appendix", page: 7, path: [], kids: false },
  ]);
});

test("a bookmark that points nowhere is kept without a page; a file with no bookmarks gives nothing", async () => {
  const flat = await readOutline(pdf([{ title: "Lost", dest: [{ num: 99 }] }, { title: "Unnamed", dest: "gone" }]));
  assert.deepEqual(flat.map((it) => it.page), [null, null]);
  assert.deepEqual(await readOutline(pdf(null)), []);
});
