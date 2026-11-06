# CaPSA, Expert and Notification Redesign: Plan

Status: mockups ready, waiting on the open questions below. No Blade has changed.

The sidebar and topbar stay. Only what sits inside `.content` changes, the same as the student handoff (`handoff.md`).

## Files

| File | What it is |
| --- | --- |
| `capsa-dashboard-content.html` / `.png` | CaPSA dashboard, content area only, sample data. |
| `expert-dashboard-content.html` / `.png` | Expert dashboard, content area only, sample data. |
| `notification-panel.html` / `.png` | The bell's panel, with a mock topbar so it can be seen hanging from the bell. |
| `staff-dashboard.css` | Shared by all three. Tokens and `db-` classes from the student mockup, plus tiles, rings, tabs and quick actions. |

No AI-generated images. The only picture is the existing `public/images/bg-ccs.jpg` banner photo; rings and icons are drawn in code.

## Layout

```
CaPSA                                     Expert
┌──────────── Welcome banner ───────────┐ ┌──────────── Welcome banner ───────────┐
├ Teams ┬ Students ┬ Coverage ┬ Issues ─┤ ├ Teams ┬ Reviews ┬ Graded ┬ Consults ──┤
├─ Team & Expert ──┬─ Pending Issues ───┤ ├─ My Workload ┬─ Distribution ┬ Consults ┤
│  Coverage        │  (6 rows)          │ │              │               ├ Tasks ───┤
├─ Deadlines ┬─ Activity ─┬─ Quick ─────┤ ├─ Activity ───┼─ Quick Access ┼ Announce ┤
└────────────┴────────────┴─ Actions ───┘ └──────────────┴───────────────┴──────────┘
```

- Same rules as the student page: one screen, no page scroll, no scroll bar inside a card, a fixed number of rows, **View all** for the rest.
- One-screen mode needs width 1100px and up, and height 700px and up. In the app the height is `calc(100vh - 58px)`. Shorter than that, the page scrolls instead of cutting off a card.
- Under 1100px the cards stack. Under 760px the banner photo and the Pending Issues detail text are hidden.
- Checked at 1440×900, 1220×800, 1116×710 and 390×844: no card clips. The bell panel's list scrolls at 710px high, as it does today.

## CaPSA: what each card reads

| Card | Shows | Source | Link |
| --- | --- | --- | --- |
| Total Teams, Total Students | counts | `DashboardStats::capsa()` teams, students | `capsa.team_management` |
| Coverage Rate | teams with all three roles ÷ teams | **new** per-team check from `CapsaReports::master()` | `capsa.reports.show` |
| Pending Issues tile | sum of the six rows below | **new** sum in `forCapsa()` | the card |
| Team & Expert Coverage | ring, Adviser / Consultant / Grammarian have, Overall Status | `DashboardStats::capsa()` coverage, capacity; `ExpertCapacity::forPrograms()` | `capsa.reports.show` |
| Pending Issues rows | Insufficient Experts, Unverified Teams, Titles, Unscheduled Defenses, Receipts, Member Requests | `ExpertCapacity::shortages()`, then `forCapsa()` toVerify, titles, receipts, leaveRequests; Unscheduled is `not_scheduled` from `defenseSummary` | the page for each |
| Upcoming Deadlines: Defenses | next 3 defenses | `forCapsa()` defenses (already 14 days) | `capsa.defenses` |
| Upcoming Deadlines: Requirements | next 3 due dates, teams left | **new** query on `Deliverable.due_date`, falling back to `MilestoneItem.due_date` | `capsa.team_management.milestones.*` |
| Recent Activity | latest 4 | `ActivityLog` | `capsa.activitylog` |
| Quick Actions | four links | none | reports, milestones, teams, reports |

## Expert: what each card reads

| Card | Shows | Source | Link |
| --- | --- | --- | --- |
| Assigned Teams | count | `DashboardStats::expert()` teams | `instructor.teams` |
| Pending Reviews | count | reviews.waiting / `docsForReview` | `instructor.docreview` |
| Defenses Graded | count | panels.graded | `instructor.grading` |
| Upcoming Consultations | count | consultations.upcoming | `instructor.schedule` |
| My Workload | ring slots used, four numbers | slots, reviews, panels.toGrade, consultations | none |
| Team Distribution | ring by section | bySection | `instructor.teams` |
| Consultations | next 3 | `$upcoming` / upcomingConsults | `instructor.schedule` |
| Upcoming Tasks | next review, grade to submit, team request | docsForReview + oldestDocReview, gradesToSubmit, pendingApplications | `instructor.assignments` |
| Announcements | latest 1 | announcements | list page |
| Recent Activity | latest 3 | **new** query, from their notifications or the team's `ActivityLog` | none, no expert activity page exists |
| Quick Access | six links | none | teams, docreview, schedule, grading, evaluations, profile |

Keep the existing **+New** applications signal: it now lives in Upcoming Tasks, as the team request row.

## Notification panel

- One panel for expert, CaPSA and admin: `resources/views/partials/notification-bell.blade.php`. Same 380px width, same latest 15, same Mark all read, same "Get these on your desktop" row.
- New: tabs All / Unread / Documents / Consultations, a coloured icon per type (`NotificationTypes`), and a Review or View button that goes to the notification's link.
- Footer gets **See all notifications**. This needs one new simple page, because the bell only holds 15.

## What differs from the images, on purpose

| In the image | In the mockup | Why |
| --- | --- | --- |
| Trend numbers (+2, +5), small graphs behind tiles | Removed | The app keeps no history to compare against. |
| Expertise Mismatch | Removed | The app has no such concept. |
| Overall Status looks fine while Pending Issues says critical | Red "Short" | The two would contradict each other. |
| Completed Evaluations | Defenses Graded | "Evaluations" already means something else (`ExpertEvaluations`). |
| Task due dates | "Waiting 3 days" | Document reviews have no due date. |
| Grouped notifications ("3 teams…") | One line per event | The app stores one notification per event. |
| Blue | Gold | The app's accent. |
| Filled pill tabs | Underlined tabs | No pills, per earlier feedback. Time labels and Critical keep the student mockup's pills. |
| Topbar search | Removed | The topbar stays as it is. |

## Overlap with what exists

- The CaPSA view is shared with **Admin**. The `@if ($admin && $stats['admin'])` block must survive; only the CaPSA block changes.
- Cards leaving the CaPSA page: Team Progress, Defense Results, Fee Collection, Experts and Sections, Teams vs Experts. Their numbers stay in Reports and Analytics.
- The student bell is a separate file (`student/partials/notification-dropdown.blade.php`) and this plan does not touch it.
- All numbers stay in `DashboardStats`, so a dashboard never disagrees with Reports.

## Open questions

1. Are you happy dropping the five CaPSA cards listed above from the dashboard?
2. Does Admin keep today's view, or get its own redesign later?
3. Keep the Requirements tab? The student page dropped its deadlines card, and this tab is empty unless due dates are set.
4. Build the See all notifications page now?
5. Should the student bell get the same design?
6. CaPSA rarely gets Documents or Consultations notifications. Keep the same tabs for simplicity, or give CaPSA its own?

## Build order

1. `DashboardStats` additions: fully covered count, pending total, deadlines, expert activity.
2. `capsa/dashboard.blade.php`, keeping the Admin block.
3. `instructor/dashboard.blade.php` and its summary partial.
4. Notification bell, then the See all page.
5. Page tours: the Help Center tour steps point at today's cards, so they need updating.
6. `npm run build`, `graphify update .`, PHPUnit directly (`php artisan test` stops early).
