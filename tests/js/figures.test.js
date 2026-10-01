// Remarks on figures: reading captions, and matching a box or pin to its
// figure. Run with `npm run test:js`.
import { test } from "node:test";
import assert from "node:assert/strict";
import {
  captionsOnPage, cropOf, figureFor, guessDiagramType, parseCaption, quickRemarksFor, uniqueFigures,
} from "../../resources/js/component/reader/figures.js";

// A pdf.js text item at `y` points from the bottom of a 792-point page.
const item = (str, y, x = 72, size = 12) => ({ str, transform: [size, 0, 0, size, x, y], height: size });

test("reads the usual ways a caption is written", () => {
  assert.deepEqual(parseCaption("Figure 3.1. Context diagram of BarangayConnect"), { label: "Figure 3.1", title: "Context diagram of BarangayConnect" });
  assert.deepEqual(parseCaption("Fig. 2: Use case diagram"), { label: "Figure 2", title: "Use case diagram" });
  assert.deepEqual(parseCaption("Figure 4 System Architecture"), { label: "Figure 4", title: "System Architecture" });
  assert.deepEqual(parseCaption("Figure 5"), { label: "Figure 5", title: "" });
});

test("does not take a sentence about a figure, or a List of Figures line, for a caption", () => {
  assert.equal(parseCaption("Figure 3.1 shows how the system exchanges data"), null);
  assert.equal(parseCaption("Figure 3.1 Context diagram ........................ 23"), null);
  assert.equal(parseCaption("Figure 3.2 Entity-relationship diagram 24"), null);
  assert.equal(parseCaption("The figures below"), null);
});

test("finds a caption split over several text items on one line", () => {
  const items = [item("Figure 3.2.", 300), item(" Entity-relationship", 300, 140), item("diagram", 300, 260), item("Some body text", 280)];
  const [caption] = captionsOnPage(items, 792, 2);
  assert.equal(caption.label, "Figure 3.2");
  assert.equal(caption.title, "Entity-relationship diagram");
  assert.equal(caption.page, 2);
  assert.ok(Math.abs(caption.y - (792 - 300 - 12) / 792) < 1e-9);
});

test("keeps the first caption of each figure number", () => {
  const figs = uniqueFigures([{ label: "Figure 1", page: 1 }, { label: "Figure 1", page: 3 }, { label: "Figure 2", page: 4 }]);
  assert.deepEqual(figs.map((f) => `${f.label}@${f.page}`), ["Figure 1@1", "Figure 2@4"]);
});

test("matches a mark to the caption under it on the same page", () => {
  const figures = [{ label: "Figure 3.1", page: 1, y: 0.62 }, { label: "Figure 3.2", page: 2, y: 0.45 }];
  const box = [{ top_position: 0.3, left_position: 0.1, highlight_width: 0.5, highlight_height: 0.28 }];
  assert.equal(figureFor(figures, 1, box), "Figure 3.1");
  assert.equal(figureFor(figures, 2, [{ top_position: 0.2, left_position: 0.2, highlight_width: 0, highlight_height: 0 }]), "Figure 3.2");
  // Far from any caption, and on a page with none.
  assert.equal(figureFor(figures, 1, [{ top_position: 0.02, left_position: 0.1, highlight_width: 0.1, highlight_height: 0.01 }]), null);
  assert.equal(figureFor(figures, 3, box), null);
});

test("guesses the diagram type from the caption", () => {
  assert.equal(guessDiagramType("Entity-relationship diagram of the system"), "ERD");
  assert.equal(guessDiagramType("Context diagram of BarangayConnect"), "DFD");
  assert.equal(guessDiagramType("Use case diagram"), "UseCase");
  assert.equal(guessDiagramType("System flowchart"), "Flowchart");
  assert.equal(guessDiagramType("Network architecture"), "Architecture");
  assert.equal(guessDiagramType("Login screen"), "UI");
  assert.equal(guessDiagramType("Respondents by year level"), "");
});

test("quick remarks for a type come before the general ones", () => {
  const erd = quickRemarksFor("ERD");
  assert.equal(erd[0], "Missing cardinality.");
  assert.ok(erd.includes("Label is unclear."));
  assert.ok(!quickRemarksFor("").some((q) => /too small/i.test(q)));
});

test("a snapshot shows a box with a margin and stays on the page", () => {
  const crop = cropOf({ type: "box", highlightRects: [{ top_position: 0.9, left_position: 0.9, highlight_width: 0.2, highlight_height: 0.2 }] });
  assert.ok(crop.left + crop.width <= 1 + 1e-9 && crop.top + crop.height <= 1 + 1e-9);
  const pin = cropOf({ type: "pin", highlightRects: [], x_position: 0.5, y_position: 0.5 });
  assert.ok(Math.abs(pin.width - 0.34) < 1e-9);
});

test("a saved figure remark comes back with every box, and a pin as a point", async () => {
  const { remarkFromServer } = await import("../../resources/js/component/reader/figures.js");
  const box = remarkFromServer({
    id: 1, page_number: "2", mark_type: "box", top_position: "0.1", left_position: "0.1", highlight_width: "0.6", highlight_height: "0.3",
    rects: [
      { top_position: 0.1, left_position: 0.1, highlight_width: 0.2, highlight_height: 0.1 },
      { top_position: 0.3, left_position: 0.5, highlight_width: 0.2, highlight_height: 0.1 },
    ],
    figure_label: "Figure 3.2", diagram_type: "ERD", comment: "Foreign key is not shown.", color: "pink",
  });
  assert.equal(box.type, "box");
  assert.equal(box.highlightRects.length, 2);
  assert.equal(box.figure_label, "Figure 3.2");
  assert.equal(box.page_number, 2);

  const pin = remarkFromServer({ id: 2, page_number: 1, mark_type: "pin", top_position: 0.4, left_position: 0.6, highlight_width: 0, highlight_height: 0, comment: "Label this.", color: "blue" });
  assert.equal(pin.type, "pin");
  assert.deepEqual(pin.highlightRects, []);
  assert.equal(pin.x_position, 0.6);
  assert.equal(pin.y_position, 0.4);

  // A remark saved before figure remarks existed is a text remark, as it was.
  const old = remarkFromServer({ id: 3, page_number: 1, top_position: 0.2, left_position: 0.1, highlight_width: 0.5, highlight_height: 0.02, comment: "Fix this.", selected_text: "words" });
  assert.equal(old.type, "text");
  assert.equal(old.highlightRects.length, 1);
  assert.equal(old.selectedText, "words");
});
