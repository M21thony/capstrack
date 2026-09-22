# Audit: the four items the CaPSA already ticked (09/17/26)

She wrote `[/]` beside four items. This checks each one against the code as it
stands, so nothing is reported as done on trust. Verdicts are one of:

- **Works** — traced end to end, from the control that sets it to the code that
  enforces it.
- **Works, with a gap** — the main path works; something adjacent does not.
- **Not verifiable from code** — a judgement call only she can make.

---

## 1. Compliance to previous milestone before moving on the next — **Works**

Traced the whole chain:

| Step | Where |
| --- | --- |
| The CaPSA's checkbox, "Opens only after the milestone before it is complete" | `resources/js/milestones/editor.js:921` |
| Saved by `PATCH /capsa/checklist-milestones/{id}` | `resources/js/milestones/editor.js:927` |
| Accepted and written | `app/Http/Controllers/Capsa/ChecklistMilestoneController.php:40,51` |
| Lock worked out per team | `app/Support/MilestoneProgress.php:158` |
| Shown to the team as locked | `app/Http/Controllers/SubmissionController.php:321,323` |
| **Upload actually refused** (HTTP 422) | `app/Http/Controllers/SubmissionController.php:78-86` |

Two things worth knowing:

- The checkbox is deliberately hidden on the **first** milestone of a checklist
  (`editor.js:545`, `idx === 0`) — there is nothing before it to require.
- It is **off by default**. The column defaults to false, so on a checklist
  nobody has touched, every milestone is open. Her "(for those applicable only)"
  matches that exactly, but it does mean somebody has to go and tick the boxes.
  Worth asking her whether she has.

## 2. Milestone, CaPSA side: to see the progress of teams handled — **Works**

Two separate screens, both live:

- **CaPSA** — Team Management → Milestones tab. `capsa/team_management.blade.php:54`
  pulls in `capsa/partials/team-management/milestones.blade.php`, which hands the
  whole section to `resources/js/milestones/tracker.js`. Teams down the side,
  milestones across, one section at a time. Data from
  `app/Support/ChecklistTracker.php:29`.
- **Adviser / consultant** — the same tracker, read-only, at
  `app/Http/Controllers/Instructor/ProgressController.php`. It shows only the
  teams that expert advises or consults for; teams they merely grade as a
  panelist are excluded.

The Team Management list itself also carries one column per milestone
(`capsa/team_management.blade.php:87-96`), so she can see all her teams' progress
without opening the tab at all.

## 3. Fix the team-management — **Not verifiable from code**

The page has since been rebuilt: a breadcrumb trail on every level
(`capsa/partials/team-management/_crumb.blade.php`), seven tabs
(`TeamManagementController.php:43`), a milestone column per checklist milestone,
a "waiting on you" line that counts titles to review, teams to verify, papers to
check and leave requests (`team_management.blade.php:99-107`), and detail pages
instead of rows that unfold.

Whether that is the fix she meant is her call, not something the code can answer.
**Ask her to open Team Management and say what is still wrong.** This is the one
ticked item that could quietly be wrong.

## 4. Notifications for important matters + a settings page — **Works, with a gap**

Working:

- Bell with unread count — `resources/views/partials/notification-bell.blade.php`.
- Real browser notifications (the Facebook-style kind she described) —
  `public/sw.js` and `public/js/capstrack-push.js`.
- Per-person on/off switches — `resources/views/partials/notification-settings.blade.php`,
  included on the student, CaPSA and adviser profile pages. Saved by
  `NotificationController::preferences` (`routes/web.php:477`).
- The list of things you can be told about — `app/Support/NotificationTypes.php`.

**The gap is her own example.** She said "example: changes in consultation".
`NotificationTypes::STUDENT` has three entries: a review finished, a milestone
deadline coming up, and a consultation happening today. There is **no** "your
expert moved or cancelled your consultation" notification. The expert is told
when a team cancels (`consultation_cancelled`); the team is not told when the
expert does. So the one example she gave is the one case not covered.

Two smaller gaps:

- Browser notifications need **https or localhost**. On a plain `http://` address
  on the school network the bell still works but the popup never appears.
- After changing anything about notifications, the background worker
  (`php artisan queue:work`) has to be restarted or it keeps running the old code.
