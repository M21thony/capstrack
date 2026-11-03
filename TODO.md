# TODO: Fix Student Dashboard Milestone Progress

## Goal
Make the 5 milestones from admin's Milestones tab (Capstone Team, Pre-Proposal, Title Defense, Pre-Oral Defense, Final Defense) visible in the student dashboard's "Milestone Progress" card, and ensure overall progress updates when a student leader marks a milestone as done.

## Steps

- [x] Step 1: Analyze codebase (DashboardController, ProgressController, admin announcements view)
- [x] Step 2: Create plan
- [x] Step 3: Fix `getOverallProgress()` in DashboardController to deduplicate by milestone title
- [x] Step 4: Fix `getMilestones()` in DashboardController to fetch global milestones and check StudentMilestone completion status per team
- [x] Step 5: Test by marking a milestone as done and checking dashboard


# TODO: Announcements (capsa/announcements), open decisions

Found while checking who announcements reach (Nov 3, 2026). The recipient,
@mention and failed-save bugs are fixed; these wait on a decision.

- [ ] Editing a post notifies nobody. Raising it to Urgent, or adding a section, sends no bell or email to the people it now reaches. Decide: notify only the people newly reached, or everyone again.
- [ ] Any CaPSA can edit or delete another CaPSA's post, and the page lists every CaPSA's posts. Decide: own posts only (Admin sees all), or keep it shared.
- [ ] "Panel" reaches every approved expert, not only experts sitting on a panel (InstructorDashboardAnnouncementTest expects this). Decide: real panelists only, or rename the option.
- [ ] Mentioning a team makes the post visible to that team only, even when Students or sections are picked. Decide: keep it, or let a mention tag the team while the post still reaches its audience (needs a way to tell the two apart, since defense-schedule posts rely on team-only).
- [ ] Redesign the page in the style of a class stream (mockups in the Announcements Redesign canvas).
