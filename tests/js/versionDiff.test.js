// What changed between two versions of a document (the reviewer's compare
// view). Run with `npm run test:js` - Node's own test runner, nothing to install.
import { test } from "node:test";
import assert from "node:assert/strict";
import { compareVersions, dropRunningLines, editScript } from "../../resources/js/component/reader/versionDiff.js";

// The same words the PDF reader hands over, laid out `perLine` words a line.
const normalise = (s) => s.toLowerCase().normalize("NFKD").replace(/[^\p{L}\p{N}]+/gu, "");
const layout = (raws, perLine = 12) => raws.map((raw, i) => ({
  raw,
  t: normalise(raw),
  page: 1 + Math.floor(i / 350),
  line: Math.floor(i / perLine),
  rects: [{ top_position: 0.5, left_position: 0.1, highlight_width: 0.01, highlight_height: 0.01 }],
}));
const wordsOf = (text) => text.split(/\s+/).filter(Boolean);
const count = (flags) => flags.reduce((sum, flag) => sum + flag, 0);

// Seeded, so a failure can be run again exactly.
const randomFrom = (seed) => () => {
  seed = (seed + 0x6d2b79f5) | 0;
  let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};
const chapterOf = (length, random) => {
  const common = ["the", "of", "and", "to", "a", "in", "is", "that", "for", "system"];
  return Array.from({ length }, () => (random() < 0.35 ? common[Math.floor(random() * 10)] : "word" + Math.floor(random() * 3000)));
};

test("the edit script is always valid and as short as it can be", () => {
  const random = randomFrom(1);
  const longestCommon = (a, b) => {
    const d = Array.from({ length: a.length + 1 }, () => new Int32Array(b.length + 1));
    for (let i = 1; i <= a.length; i++) {
      for (let j = 1; j <= b.length; j++) d[i][j] = a[i - 1] === b[j - 1] ? d[i - 1][j - 1] + 1 : Math.max(d[i - 1][j], d[i][j - 1]);
    }
    return d[a.length][b.length];
  };
  for (let n = 0; n < 2000; n++) {
    const a = Array.from({ length: Math.floor(random() * 30) }, () => "w" + Math.floor(random() * 5));
    const b = Array.from({ length: Math.floor(random() * 30) }, () => "w" + Math.floor(random() * 5));
    const { removed, added } = editScript(a, b);
    assert.deepEqual(a.filter((_, i) => !removed[i]), b.filter((_, j) => !added[j]));
    assert.equal(count(removed) + count(added), a.length + b.length - 2 * longestCommon(a, b));
  }
});

test("a shortened title shows the words taken out and the one reworded", () => {
  const before = layout(wordsOf("Project Title: Design and Development of an RFID-Integrated Student Attendance, School Compliance, and Parent Engagement System for Malawag National High School"), 11);
  const after = layout(wordsOf("Project Title: Development of an RFID-Integrated Student Attendance, School Compliance, and Parent Engagement System for MNHS"), 9);

  const { changes } = compareVersions(before, after);

  assert.deepEqual(changes.map((c) => [c.kind, c.oldText, c.newText]), [
    ["removed", "Design and", ""],
    ["reworded", "Malawag National High School", "MNHS"],
  ]);
});

test("the same paragraph with new line breaks and spacing has no changes", () => {
  const paragraph = chapterOf(300, randomFrom(2));

  const { changes } = compareVersions(layout(paragraph, 12), layout(paragraph, 7));

  assert.equal(changes.length, 0);
});

test("a rewritten paragraph is one change even where a few words survived", () => {
  const random = randomFrom(3);
  const around = chapterOf(200, random);
  const kept = ["which", "the", "study"];
  const before = [...around.slice(0, 100), ...chapterOf(40, random), ...kept, ...chapterOf(40, random), ...around.slice(100)];
  const after = [...around.slice(0, 100), ...wordsOf("x ".repeat(40)).map((w, i) => w + i), ...kept, ...wordsOf("y ".repeat(40)).map((w, i) => w + i), ...around.slice(100)];

  const { changes } = compareVersions(layout(before), layout(after));

  assert.equal(changes.length, 1);
  assert.equal(changes[0].kind, "reworded");
});

test("a whole manuscript whose lines all moved still lists each rewritten paragraph", () => {
  const before = chapterOf(25000, randomFrom(4));
  const after = before.slice();
  for (let p = 0; p < 30; p++) {
    const start = Math.floor((p + 0.5) * (25000 / 30));
    for (let i = 0; i < 80; i++) after[start + i] = `rewritten${p}_${i}`;
  }

  // A margin change: every line of the new version breaks in a different place.
  const { changes, added, removed } = compareVersions(layout(before, 12), layout(after, 11));

  assert.equal(changes.length, 30);
  assert.equal(count(added), 30 * 80);
  assert.ok(changes.every((c) => c.kind === "reworded"));
  assert.deepEqual(before.filter((_, i) => !removed[i]), after.filter((_, j) => !added[j]));
});

test("a manuscript with a fifth of its words reworded is not marked as one change", () => {
  const random = randomFrom(5);
  const before = chapterOf(25000, random);
  const after = before.map((w) => (random() < 0.2 ? "new" + Math.floor(random() * 1e9) : w));
  const reworded = after.filter((w) => w.startsWith("new")).length;

  const { changes, added, removed } = compareVersions(layout(before, 12), layout(after, 11));

  assert.ok(changes.length > 1000);
  assert.ok(count(added) < reworded * 1.05);
  assert.deepEqual(before.filter((_, i) => !removed[i]), after.filter((_, j) => !added[j]));
});

test("text with nothing in common is marked whole", () => {
  const random = randomFrom(6);
  const before = chapterOf(5000, random);
  const after = Array.from({ length: 5000 }, (_, i) => "other" + i);

  const { changes, added } = compareVersions(layout(before), layout(after));

  assert.equal(changes.length, 1);
  assert.equal(count(added), 5000);
});

test("the words both versions keep always line up", () => {
  const random = randomFrom(7);
  for (let n = 0; n < 200; n++) {
    const before = chapterOf(200 + Math.floor(random() * 3000), random);
    const after = before.filter(() => random() > 0.05).map((w) => (random() < 0.1 ? "new" + Math.floor(random() * 50) : w));

    const { added, removed } = compareVersions(layout(before, 12), layout(after, 1 + Math.floor(random() * 15)));

    assert.deepEqual(before.filter((_, i) => !removed[i]), after.filter((_, j) => !added[j]));
  }
});

// Pages of `perPage` body words, each with `head` across the top and its page
// number at the foot, the way readWords hands them over.
const paged = (body, perPage, head) => {
  const words = [];
  const add = (raw, page, line, top) => words.push({ raw, t: normalise(raw), page, line, rects: [{ top_position: top, left_position: 0.1, highlight_width: 0.01, highlight_height: 0.01 }] });
  for (let p = 1; (p - 1) * perPage < body.length; p++) {
    [...head, String(p)].forEach((raw) => add(raw, p, 0, 0.04));
    body.slice((p - 1) * perPage, p * perPage).forEach((raw, i) => add(raw, p, 1 + Math.floor(i / 12), 0.2 + 0.6 * (i / perPage)));
    add(String(p), p, 99, 0.95);
  }
  return words;
};

test("a heading on every page is not a change when the page breaks move", () => {
  const body = chapterOf(1200, randomFrom(8));
  const head = wordsOf("CapsTrack: A Centralized Capstone System - Chapter 1");
  const before = paged(body, 300, head);
  const after = paged(body, 260, head);

  assert.ok(compareVersions(before, after).changes.length > 0);
  assert.equal(compareVersions(dropRunningLines(before), dropRunningLines(after)).changes.length, 0);
});

test("a line at the top of only a few pages is still compared", () => {
  const words = paged(chapterOf(3000, randomFrom(9)), 300, wordsOf("Chapter 4 Results"));
  [3, 7].forEach((page) => ["Table", String(page)].forEach((raw) => words.push({ raw, t: normalise(raw), page, line: 50, rects: [{ top_position: 0.08 }] })));

  const kept = dropRunningLines(words).map((w) => w.t);

  assert.equal(kept.filter((t) => t === "table").length, 2);
  assert.equal(kept.filter((t) => t === "results").length, 0);
  assert.equal(kept.length, 3000 + 4);
});

test("a removal is marked on the word that now follows it, as wide as that word", () => {
  const words = layout(wordsOf("the system checks every paper before the adviser signs"));
  words.forEach((w, i) => (w.rects = [{ top_position: 0.5, left_position: 0.05 * i, highlight_width: 0.04, highlight_height: 0.01 }]));
  const after = words.filter((w) => w.raw !== "every" && w.raw !== "paper");

  const { changes, marks } = compareVersions(words, after);

  assert.deepEqual(changes.map((c) => c.kind), ["removed"]);
  assert.deepEqual(marks[1], [{ id: 0, removed: true, rects: [words[5].rects[0]] }]);
});

test("a removal before a word stored in pieces is marked as tall and wide as the whole word", () => {
  const words = layout(wordsOf("submit the final CAPSTRACK report"));
  words.forEach((w, i) => (w.rects = [{ top_position: 0.5, left_position: 0.1 * i, highlight_width: 0.08, highlight_height: 0.02 }]));
  // Small caps: a big "C" and the smaller "APSTRACK" on the same baseline.
  words[3].rects = [
    { top_position: 0.5, left_position: 0.3, highlight_width: 0.01, highlight_height: 0.02 },
    { top_position: 0.504, left_position: 0.31, highlight_width: 0.06, highlight_height: 0.016 },
  ];
  const after = words.filter((w) => w.raw !== "final");

  const { marks } = compareVersions(words, after);

  assert.equal(marks[1].length, 1);
  assert.equal(marks[1][0].removed, true);
  assert.deepEqual(marks[1][0].rects.length, 1);
  const [r] = marks[1][0].rects;
  assert.equal(r.top_position, 0.5);
  assert.ok(Math.abs(r.highlight_height - 0.02) < 1e-9);
  assert.ok(Math.abs(r.highlight_width - 0.07) < 1e-9);
});
