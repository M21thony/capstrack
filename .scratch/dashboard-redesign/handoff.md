# Student Dashboard Redesign: Handoff

Status: ready-for-human

The new content area for `resources/views/student/dashboard.blade.php`.

## Files

| File | What it is |
| --- | --- |
| `dashboard-content.html` | The approved mockup, content area only. It is a static page with sample data: open it from this folder in a browser, and the banner photo loads from `public/images/bg-ccs.jpg`. Its CSS uses the app's own tokens and `db-` class names, so it can be lifted into the Blade view. |
| `dashboard-content.png` | A screenshot of the HTML at 1220×800, the content area of a 1440×900 screen with the sidebar and topbar taken off. |

The sidebar (`student.partials.sidebar`) and the topbar (dark bar, page title, `?` Page Guide, notification bell) **stay as they are**. Only what sits inside `.content` changes.

## Layout

```
┌──────────────────────────── Welcome banner ────────────────────────────┐
├──────────── Capstone Progress (2 cols) ───────────┬── Quick Actions ───┤
├─── Announcements ───┬─── Upcoming Deadlines ──────┼── On-track card ───┤
├─── Your Team ───────┼─── Recent Activity ─────────┼── Resources ───────┤
└─────────────────────┴─────────────────────────────┴────────────────────┘
```

- **Width 1100px and up:** a 3-column grid (`repeat(3, minmax(0, 1fr))`), with a 12px gap everywhere. Capstone Progress spans 2 columns, so the edges of every card line up.
- **One screen, no page scroll (width 1100px and up, height 640px and up):** the content area takes the screen height minus the topbar, `calc(100vh - 58px)`. The banner and the top row keep their natural height, and the two lower rows split what is left equally.
- **No scroll bar inside a card.** The cards are `overflow: hidden`. Each list shows a fixed number of items and the **View all** link leads to the rest. Keep the item counts below, or the cards will clip.
- **Under 1100px:** the cards wrap and the page scrolls as usual. **Under 760px:** the banner photo is hidden and Quick Actions becomes 2×2.

## Tokens

All colours come from `resources/student-ui/styles.css`. Notes:

- Secondary text uses `--text2` (`#4a5060`), not `--text3`, which is too light to read at 12px.
- The gold for icons, the ring, the bar and the borders is `--accent2` (`#b5941f`). Gold link text is `--accent3` (`#8a6e10`). On the dark cards, the gold text is `#e6c75a`.
- The dark cards (banner, on-track card) use `--navy-dark`.
- The font is DM Sans, already loaded through `css/fonts.css`. The mockup links Google Fonts only so that it can stand alone.

## Sections and their data

Everything below except Upcoming Deadlines, Recent Activity and Resources already comes from `DashboardController::index`.

| # | Card | Shows | Source | Link |
| --- | --- | --- | --- | --- |
| 1 | Welcome banner | "Welcome back, {first name}!" and the campus photo | `auth()->user()`, `public/images/bg-ccs.jpg` | none |
| 2 | Capstone Progress | Ring and bar showing `$overallProgress`%; "{completed} of {total} requirements done"; a red **N Overdue** box (only when N > 0); **Next due** date and title | `$progress->completed`, `$progress->total`, `$overdueItems->count()`, `$nextItem->due_on`, `$nextItem->item->title` | View details → `student.progress` |
| 3 | Quick Actions | 4 tiles | none | Submit → `student.submission`; View Documents → `student.documents`; Check Schedule → **open question 3**; View Progress → `student.progress` |
| 4 | Announcements | The latest **3**, each with a title, a one-line excerpt (ellipsis) and a date. Gold dot = new, grey = older | `$announcements->take(3)` | View all → announcements list (**open question 4**) |
| 5 | Upcoming Deadlines | The next **3** items not yet done that have a due date, each with a date, a title and a pill: **Today** (gold), "N days left" | **New:** `$progress->items`, without complete or overdue items, those with `due_on`, sorted by `due_on`, `take(3)`. This is the same filter as `$nextItem` without `->first()`. | View calendar → `student.progress` |
| 6 | On-track card | A trophy icon and a message | `$overdueItems` (see open question 1) | none |
| 7 | Your Team | Team name, "Active team", up to 4 initials avatars, "{n} members · {section}"; **Manage team** button | `$myTeam`, `$teamMembers` | Manage team → `student.team` |
| 8 | Recent Activity | The latest **3**, each with a coloured icon by type, text and "Nov 4 · 3:45 PM" | **New:** `Notification::where('student_id', …)->latest('created_at')->take(3)`, read ones included (the controller loads only unread now) | View all → notifications |
| 9 | Resources | A gold notice "Your CaPSA uploaded **N** new resources · {date}", then the newest **2** resources, each with a category pill (Forms, Lessons, Rubrics, Presentation, Other) | **New:** `StudentResources::files($student)`; "new" = `uploaded_at` within the last week, the same rule as `isNew` in `Student\ResourceController`. The category labels are the ones `Student\ResourceController` already maps. | View all → `student.resources`; an item → `student.resources.download` |

### Empty states

- **No team:** keep today's `NextStep` card (`App\Support\NextStep`) in place of rows 2–4, or above them. It already explains the next setup step.
- **No checklist yet** (`$progress === null`): Capstone Progress shows the existing sentence "Your section's checklist is not set up yet…", and Upcoming Deadlines shows "No deadlines yet."
- **No overdue items:** hide the red box. The Next due box takes its place.
- **Nothing in a list:** show one muted line inside the card, such as "No announcements yet." Keep the card height.
- **No new resources:** hide the gold notice and show the 2 newest resources anyway.

## Open questions

1. **On-track card copy.** "You're on the right track!" is wrong for a team with 9 overdue items (the sample data). Suggestion: when `$overdueItems` is empty, show "You're on the right track!"; otherwise show "{N} requirements overdue. Catch up to stay on track." in the same card, with an amber icon in place of the trophy.
2. **Cards on today's dashboard that the mockup leaves out:** the adviser, consultant and grammarian card; Defense Results; the "print rating sheets" link for the next defense (`$sheetReady`). Decide whether they move elsewhere (Experts page, Progress page) or come back as cards. The rating-sheet link matters on defense day, so it could go inside the Next due box when the next item is a defense.
3. **Check Schedule** has no student route. Options: `student.progress`, or `student.consultation` if it means consultation booking.
4. **Announcements: View all.** Students have no announcements list route yet.
5. **Quick Actions label.** "Submit Pre-Proposal" is sample text. Make it follow the team's next deliverable, or use a fixed "Submit document".

## Tests

`tests/Feature/StudentDashboardTest.php` covers today's dashboard. Update it for the new sections, at least:

- Upcoming Deadlines lists at most 3 open items with due dates, in date order, and leaves out completed and overdue items.
- Recent Activity shows the student's own notifications only, read and unread.
- The Resources notice counts only resources uploaded in the last week, and only those the student's program and section can see.
- The Overdue box and the on-track message follow `$overdueItems`.

## Reference

The interactive mockup is on the design canvas at https://claude.ai/artifact/HnNNZCyCEQdrv3m5ZWC88r (artboard "Student Dashboard v2"). The canvas is private to its owner until shared.
