// The section list the annotator reads out of a PDF's bookmarks.
// Run with `npm run test:js` - Node's own test runner, nothing to install.
import { test } from "node:test";
import assert from "node:assert/strict";
import { readOutline } from "../../resources/js/component/reader/outline.js";

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
