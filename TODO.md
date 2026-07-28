# TODO: Fix Student Dashboard Milestone Progress

## Goal
Make the 5 milestones from admin's Milestones tab (Capstone Team, Pre-Proposal, Title Defense, Pre-Oral Defense, Final Defense) visible in the student dashboard's "Milestone Progress" card, and ensure overall progress updates when a student leader marks a milestone as done.

## Steps

- [x] Step 1: Analyze codebase (DashboardController, ProgressController, admin announcements view)
- [x] Step 2: Create plan
- [x] Step 3: Fix `getOverallProgress()` in DashboardController to deduplicate by milestone title
- [x] Step 4: Fix `getMilestones()` in DashboardController to fetch global milestones and check StudentMilestone completion status per team
- [x] Step 5: Test by marking a milestone as done and checking dashboard

