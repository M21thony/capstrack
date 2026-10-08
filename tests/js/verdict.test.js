// The decision a review is turned in with comes from its remarks.
// Run with `npm run test:js`.
import { test } from "node:test";
import assert from "node:assert/strict";
import { verdictFor } from "../../resources/js/component/reader/verdict.js";

test("no remarks approves", () => {
  assert.equal(verdictFor([], []), "approved");
});

test("only Good remarks still approves", () => {
  assert.equal(verdictFor([{ color: "green" }, { color: "green" }], []), "approved");
});

test("any other remark sends it back", () => {
  for (const color of ["pink", "orange", "yellow", "blue", null]) {
    assert.equal(verdictFor([{ color: "green" }, { color }], []), "needs_revision", String(color));
  }
});

test("a general comment sends it back", () => {
  assert.equal(verdictFor([], [{ text: "Fix the references." }]), "needs_revision");
});
