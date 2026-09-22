# PRD: CaPSA feedback, 09/17/26

What the adviser asked for on 17 September 2026, what each item means in terms
of this system, and how we will know it is done.

The four items she ticked are checked separately in
[capsa-feedback-2026-09-17-audit.md](capsa-feedback-2026-09-17-audit.md) — three
hold up, one needs her to confirm. This document covers the nine that are open.

Two items she wrote down are not specified here because she herself was unsure
what she meant, or we are: they are in **Questions for the CaPSA** at the end,
and should be asked before anything is built for them.

---

## 1. Notifications as dialog boxes, not toasts

> "Use Modal or Dialog Boxes for Notification the Toast Notification is just hard to notice."

**What exists.** A toast is the small strip that slides in at the corner and
fades out on its own. Fourteen pages have one, and each page wrote its own copy
with its own arguments — `showToast(message, type, title)` at
`resources/views/admin/experts.blade.php:995`, `showToast(type, msg)` at
`resources/views/capsa/approval.blade.php:646`, `showToast(msg)` at
`resources/views/capsa/resources.blade.php:1283`, and eleven more. There is no
shared one.

A shared dialog box already exists and already looks right:
`resources/views/partials/confirm-sheet.blade.php` — a centred card over a dimmed
page, with a heading, a body, an optional list of names, and buttons. Six pages
use it for "are you sure". It is the obvious thing to reuse.

**What "done" means.**

- One shared message dialog, built from the same styling as the confirm sheet,
  that stays on screen until the person closes it.
- All fourteen pages use it. None keeps a private copy.
- The three different argument orders still work, so no page has to be rewritten
  call by call.
- It closes on Escape and on clicking the dimmed area, like the confirm sheet
  does.
- Routine "saved" confirmations are not turned into dialogs the person has to
  dismiss on every save — see the open question below.

**Open question.** "Notification" may mean all messages, or only the ones that
matter (an error, something rejected, something that needs her). A dialog on
every autosave would be worse than a toast. Default assumption: **anything that
is a warning, an error, or a rejection becomes a dialog; plain confirmations stay
as they are.** Confirm with her.

## 2. CLF: include the deadline, and say Approved when the paper is approved

> "Fix CLF Template Include Deadline, and if the Paper is Approved then Comment Approve."

**What exists.** The CLF is generated as a PDF from
`resources/views/instructor/pdf/consultation-log.blade.php` by
`app/Http/Controllers/Instructor/ConsultationLogController.php`. Line 141 of that
template prints the word **"Deadline:"** and then an empty 40-pixel box. No
deadline value is passed to the template at all, so it prints blank every time —
exactly what she is pointing at.

The approval itself is recorded: `capstone_document_review` has a `verdict` column
whose value is `approved` or `needs_revision`
(`app/Models/CapstoneDocumentReview.php:41,70,76`), set when the expert finishes a
review. The CLF never reads it.

**What "done" means.**

- The generated CLF prints an actual deadline date beside "Deadline:".
- When the review's verdict is `approved`, the CLF says so in words in the remarks
  area, rather than leaving the reader to infer it from an empty remark list.
- A CLF for a review with no verdict yet prints as it does now — no false
  "Approved".

**Open question.** Where does the deadline come from? Three candidates exist in
the system: the milestone's due date on the checklist, a date the expert types
when finishing the review, or the defense window for that stage. The milestone due
date is the only one that needs no new input from anyone, so that is the default
assumption. Confirm with her.

## 3. Summary of defense schedule and venue, exportable to Excel with colour legends

> "Add Summary of Defense Sched and Venue, can be exported via excel file with color legends"

**What exists.** Defense scheduling lives at
`resources/views/capsa/defense_scheduling.blade.php`, backed by
`app/Support/DefenseBoard.php`. Schedules are rows in `defense_schedule` (team,
stage, date and time, venue, outcome); panels are in `defense_panels`. Eight
venues are listed at `DefenseBoard.php:36`.

There is **no summary view and no export**. The pieces for one are already here:
the project ships `phpoffice/phpspreadsheet`, and
`app/Http/Controllers/Capsa/StudentRosterController.php:256-275` is a working
example of building a sheet, bolding a header row and returning it as a download.

**What "done" means.**

- A summary the CaPSA can read on screen: every scheduled defense, by date, with
  team, stage, time, venue and panel.
- An **Export to Excel** button producing an `.xlsx` file.
- Cells are colour-filled, and the sheet carries a **legend** block saying what
  each colour means. Colour by venue is the reading that matches "Sched and
  Venue"; that is the default assumption.
- The file opens in Excel and in Google Sheets with the colours intact.

## 4. Official Receipt entered by the CaPSA

> "Include inputting of Official Receipt by CaPSA."

**What exists.** Receipts are already in the system, but the CaPSA cannot touch
them. A student submits one (`routes/web.php:459`,
`StudentFinancialController::submitReceipt`); an **Admin** verifies it or sends it
back (`routes/web.php:253`, `AdminFinancialController::updateReceipt`). The table
`financial_records` already has `receipt_number`, `verified_at` and `verified_by`
(migration `2026_07_07_171019`). Every one of those routes sits behind the admin
prefix, so a CaPSA gets a 403.

**What "done" means.**

- A CaPSA can record an Official Receipt herself — receipt number, amount, what it
  was for, which team — without an Admin doing it.
- Her name is stored in `verified_by`, so the record shows who entered it.
- She sees only her own sections' teams, the same as everywhere else in the system.
- Admin keeps everything it can do today.

**Open question.** Is she *entering* receipts that students hand her on paper, or
*verifying* the ones students upload? Those are two different screens. Default
assumption: **entering**, since "inputting" is her word and verifying already
exists. Confirm with her.

## 5. Reports and Analytics need polishing

> "Modules Need Polishing Particularly in the Reports and Analytics."

**What exists, and why the numbers are wrong.**
`app/Http/Controllers/Admin/AnalyticsController.php` computes completed teams,
active teams, completion rate and overdue teams by querying a table called
**`milestones`** (lines 24, 29, 38). That is the *old* milestone table. The system
moved to `checklists` / `checklist_milestones` / `deliverables` / `milestone_items`
and nothing writes to `milestones` any more. Counted in this database:

| Table | Rows |
| --- | --- |
| `milestones` (what Analytics reads) | 1 |
| `checklist_milestones` (the live one) | 6 |
| `deliverables` | 19 |
| `milestone_items` | 19 |

So the completion rate and the overdue count on the Analytics page are not
"unpolished" — they are reading a dead table and reporting numbers that cannot be
right. This is the most serious single finding in her whole list.

There is also **no Reports module at all**. The only page is Analytics
(`routes/web.php:99`, `resources/views/capsa/analytics.blade.php`).

**What "done" means.**

- Analytics reads the live checklist tables. Completion rate, overdue count and
  active teams agree with what Team Management shows for the same teams.
- Anything the page cannot compute honestly says so, instead of printing a zero.
- Anything shipped as a "Reports module" is scoped with her first — see the
  questions below.

## 6. Pilot testing

> "Need to do pilot testing to see the full functionality."

Not a feature. It is a dependency: it should happen **after** items 1–5 land,
because every one of them changes a screen a pilot tester would be looking at.
Planned as the last phase.

---

## Already done since she wrote this

- **Duration of defense in Defense Scheduling.** She listed this as open; it
  shipped on 19 September. `defense_windows.duration_minutes` (migration
  `2026_09_19_100000`), the 1 hour / 1½ hours / 2 hours buttons at
  `resources/views/capsa/defense_scheduling.blade.php:100-102`, saved through
  `AdminPanelAssignmentController::updateWindow`, and used to lay out start times
  in `DefenseBoard.php:66,330`. **Show her this one rather than rebuilding it.**

---

## Questions for the CaPSA

These cannot be guessed at without a real risk of building the wrong thing.

1. **"Complete Cycle for every composition of milestone."** Her own note says she
   is unsure what was meant. Ask: does a milestone need a fixed sequence — submit,
   review, revise, approve, sign — that every one of them goes through, with none
   skippable? Or something else?
2. **"Fix flow of submission revision to the same expert."** She put "???" beside
   it herself. What the code shows: when a team sends a revision, the system routes
   it by **role**, not by person — `SubmissionController.php:167` asks the resolver
   who currently fills "adviser" for this team and sends it there. If the team's
   adviser changed between version 1 and version 2, the expert who asked for the
   revision never sees the answer. Also, the student re-picks the recipients on
   every upload; nothing pre-selects the expert who requested the change. Ask her
   whether that is the problem she hit.
3. **Item 1** — dialogs for everything, or only for warnings and errors?
4. **Item 2** — which deadline goes on the CLF?
5. **Item 3** — what should the Excel colours mean: venue, stage, or outcome?
6. **Item 4** — entering receipts, or verifying them?
7. **Item 5** — what is a "Report" to her, and who reads it?
8. **"Fix the team-management"** — she ticked it, but code cannot confirm a
   judgement. Ask her to open the page and say what is still wrong.
