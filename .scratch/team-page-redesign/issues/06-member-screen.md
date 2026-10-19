# 06: The member screen: hero stats, members table and folded Team selection

**What to build:**
- For a member of a team:
  - **Hero card:** project title, team name, status chips, and a stat row (Members n/N, your role, current stage, date formed).
  - **Team members:** a table (Member | Role | LEADER/YOU) with an "n / N · Full" chip when full and an **Ask CaPSA to leave** link that opens the leave modal (reason, minimum length, "Your team sees this too", counter).
  - **Defense panel** card.
  - **Team selection:** folded into one bar ("X open posts in {section} · N of M teamed up · invites") with Browse posts / Hide posts.
  - In a full team, post details say the member can't join other teams.

**Blocked by:** 01, 02, 04

**Status:** ready-for-agent

- [ ] Members are listed leader first, then the viewer, with correct roles and badges.
- [ ] Ask CaPSA to leave creates a leave request through the existing endpoint, and the modal enforces the reason rules.
- [ ] Team selection is folded by default for a member and opens and closes in place.
- [ ] Members n/N uses the section's limit.
