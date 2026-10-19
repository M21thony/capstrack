# 01: One TeamPage module answers what a student's Team page shows

**What to build:**
- The Team page gets everything it shows from one TeamPage module.
- TeamPage reports which screen the student is on (no team / member / leader with open seats / leader of a full team) and that screen's data:
  - hero stats;
  - members with roles and badges;
  - pending invites;
  - open roles from TeamSeating;
  - Team selection's first page and counts;
  - Section overview;
  - Team Rules flags and member limit;
  - defense panel.
- The controller's inline assembly and the queries in the page's inline PHP blocks move behind it.
- This is a prefactor: the page looks and behaves exactly as before.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] TeamPage picks the right screen for a student with no team, a member, a leader with free seats, and a leader whose team is full.
- [ ] Each screen's data is asserted through TeamPage's interface. The member limit comes from the section's Team Rules.
- [ ] The Team page view no longer runs its own database queries.
- [ ] All existing Team page, invitation, recruitment and role tests still pass unchanged.
