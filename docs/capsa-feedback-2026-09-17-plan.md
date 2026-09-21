# Plan: CaPSA feedback, 09/17/26

How to build what
[capsa-feedback-2026-09-17-PRD.md](capsa-feedback-2026-09-17-PRD.md) describes,
in the order that costs least. The verification of her four ticks is in
[capsa-feedback-2026-09-17-audit.md](capsa-feedback-2026-09-17-audit.md).

## Ask her these before writing any code

Three of the five items below change shape depending on her answer, and one of
them is already built. Fifteen minutes with her saves rebuilding things twice.

- [ ] **Show her the defense duration.** It shipped on 19 September; she still has
      it on her list as missing. One screen, no work.
- [ ] **Dialogs: everything, or only warnings and errors?** Decides whether
      Phase 1 touches 14 files or 14 call sites inside them.
- [ ] **Which deadline goes on the CLF?** If it is the milestone due date, Phase 2
      is a one-line lookup. If the expert types it, Phase 2 needs a new field, a
      new column and a form change.
- [ ] **Excel colours: venue, stage, or outcome?** Decides the legend.
- [ ] **Receipts: entering, or verifying?** Entering is a new form. Verifying is a
      permission change on a screen that already exists.
- [ ] **"Fix the team-management" — what is still wrong?** She ticked it; the code
      cannot tell us whether she is satisfied.
- [ ] **The two she flagged herself** — "Complete Cycle" and "revision to the same
      expert". Do not guess at either.

---

## Phase 1 — Dialog boxes instead of toasts

Biggest visible win, and the one she led with.

1. **Add one shared partial**, `resources/views/partials/alert-sheet.blade.php`,
   modelled directly on `confirm-sheet.blade.php`: same scrim, same card, same
   Escape handling, but one **OK** button instead of two. It defines a single
   global `showToast` that opens the dialog.
2. **Accept all three existing signatures.** The pages call it three different
   ways. Sniff the arguments rather than editing every call site:
   - `showToast(msg)`
   - `showToast(msg, type)` and `showToast(msg, type, title)`
   - `showToast(type, msg)` — detectable because the first argument is one of
     `success` / `error` / `info` / `warning`.
3. **Delete the 14 local copies** and `@include` the partial instead. The list:
   `admin/experts:995`, `admin/students:241`, `capsa/approval:646`,
   `capsa/experts:1318`, `capsa/resources:1283`, `capsa/sections:490`,
   `instructor/assignments:952`, `instructor/schedule:2641`, `student/archive:249`,
   `student/consultation:966`, `student/duplication:819`, `student/experts:1693`,
   plus the two remaining files a `grep -rl showToast resources/views/` turns up.
4. **If she says "warnings and errors only"**, keep `success` on the existing
   toast and route only `error` / `warning` to the dialog — same shared file, one
   branch.

**Check it works:** on any one page, cause an error (submit a form with a field
missing). The message should sit there until clicked, dim the page behind it, and
close on Escape.

**Do not:** build this as a dropdown, a slide-down panel, or anything that unfolds
in place. The adviser has rejected those.

## Phase 2 — CLF deadline and Approved stamp

Small, self-contained, and she named it precisely.

1. In `ConsultationLogController::generate()`, the query already joins through to
   the team. Add the review's `verdict` to the `select`, and look up the deadline
   from whichever source she names (default: the checklist milestone's due date
   for this deliverable — `deliverables` already carries it and
   `MilestoneProgress` already reads it).
2. Pass both into the view.
3. In `consultation-log.blade.php:141`, print the date beside "Deadline:".
4. Where the remarks are printed, add one line when `verdict === 'approved'`:
   the paper is approved, dated. Print nothing when there is no verdict.

**Check it works:** generate a CLF for a review that is approved and one that is
not. The first says approved and shows a date; the second looks exactly as it does
today.

## Phase 3 — Reports and Analytics

This is a correctness fix disguised as polish, and it should not wait.

1. `AnalyticsController` lines 24, 29 and 38 query the dead `milestones` table.
   Replace them with the live path the rest of the system uses:
   `App\Support\MilestoneProgress::forTeams($checklist, $teamIds)`, the same call
   `ChecklistTracker` and `TeamManagementController:370` already make. Completed,
   overdue and active all come off `MilestoneProgress::COMPLETE_STATES` and
   `::OVERDUE`, which are already defined.
2. A team whose section has **no checklist** cannot have a completion rate. Say
   "no checklist yet" rather than counting it as 0% — a zero here is a lie that
   drags the whole rate down.
3. Leave the archive and program-distribution charts alone. They read real tables
   and are fine.

**Check it works:** pick one team. The completion it shows in Analytics must match
the count of ticked milestones on that team's row in Team Management. Today it
will not.

**Scope note:** this phase fixes the numbers. A *new* Reports module is a separate
piece of work and needs her answer to "what is a Report, and who reads it?" first.

## Phase 4 — Defense summary and Excel export

1. Add a **Summary** view to the defense scheduling page: every scheduled defense
   grouped by date, each row showing team, stage, time, venue, panel. The data is
   already assembled by `DefenseBoard`; this is a read-only presentation of it.
   Flat table, no rows that open.
2. Add `GET /capsa/defense-scheduling/export`. Copy the shape of
   `StudentRosterController.php:256-275` — build a `Spreadsheet`, write the rows,
   `(new Xlsx($book))->save($path)`, `return response()->download(...)
   ->deleteFileAfterSend()`.
3. Fill each row's cells with a colour (`getStyle(...)->getFill()`), and write a
   small legend block under the table saying which colour is which.

**Check it works:** download the file and open it. Colours present, legend present,
rows match what the screen shows.

## Phase 5 — Official Receipt for the CaPSA

Do not copy the admin screen. Reuse it.

1. `financial_records` already has every column needed — `receipt_number`,
   `amount`, `f_record_status`, `verified_at`, `verified_by`. Nothing new in the
   database.
2. Add CaPSA routes beside the admin ones, scoped to her sections through the
   existing `SectionScope`, and a form for the receipt number, amount, what it was
   for and the team.
3. Store her name in `verified_by`, the way `AdminFinancialController::updateReceipt`
   already does with `auth('expert')->user()->name`.

**Check it works:** as a CaPSA, add a receipt for one of your own teams, then
confirm it appears in Admin's list with your name against it — and that a team
from another CaPSA's section is not offered to you.

## Phase 6 — Pilot testing

Last, once the above is in. Every phase changes a screen a tester would look at,
so testing before them tests work that is about to change.

---

## Two things to fix while in the neighbourhood

Not on her list, but found while checking it, and cheap once the file is already
open:

- **No "your consultation was changed" notification.** The example she gave for
  the notifications item is the one case not covered —
  `NotificationTypes::STUDENT` has no entry for it. Adding one is an entry in that
  file plus a `Notifier::toStudent` call where the expert edits or cancels a booked
  slot.
- **Restart `php artisan queue:work`.** Notification code has changed since the
  running worker started; it will keep running the old copy until restarted.
