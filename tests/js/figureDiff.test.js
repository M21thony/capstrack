// Comparing the diagrams of two versions: which picture is which, and what
// a pair amounts to. Run with `npm run test:js`.
import { test } from "node:test";
import assert from "node:assert/strict";
import { OVERHAUL, hungarian, kindOf, pairUp } from "../../resources/js/component/reader/figureDiff.js";

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
