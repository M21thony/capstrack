// The Scheduled list keeps one room's defenses on a day together.
// Run with `npm run test:js`.
import { test } from "node:test";
import assert from "node:assert/strict";

// shared.js reads the CSRF meta tag when it loads.
globalThis.document = { querySelector: () => null };
const { byDayRoomTime } = await import("../../resources/js/defenses/shared.js");

test("earliest day first, then room, then time", () => {
    const rows = [
        { date: "2026-10-13", venue: "Mac Lab", time: "10:00" },
        { date: "2026-10-12", venue: "Lab 2", time: "13:00" },
        { date: "2026-10-13", venue: "Lab 2", time: "08:00" },
        { date: "2026-10-13", venue: "Mac Lab", time: "08:00" },
        { date: "2026-10-12", venue: "Lab 2", time: "08:00" },
    ];

    assert.deepEqual(
        rows.sort(byDayRoomTime).map((r) => `${r.date} ${r.venue} ${r.time}`),
        [
            "2026-10-12 Lab 2 08:00",
            "2026-10-12 Lab 2 13:00",
            "2026-10-13 Lab 2 08:00",
            "2026-10-13 Mac Lab 08:00",
            "2026-10-13 Mac Lab 10:00",
        ],
    );
});

test("a defense with no room yet does not break the sort", () => {
    const rows = [
        { date: "2026-10-12", venue: "Mac Lab", time: "08:00" },
        { date: "2026-10-12", venue: null, time: "10:00" },
    ];

    assert.equal(rows.sort(byDayRoomTime)[0].venue, null);
});
