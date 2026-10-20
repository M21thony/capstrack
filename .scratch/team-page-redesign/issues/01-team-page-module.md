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

**Status:** done

- [x] TeamPage picks the right screen for a student with no team, a member, a leader with free seats, and a leader whose team is full.
- [x] Each screen's data is asserted through TeamPage's interface. The member limit comes from the section's Team Rules.
- [x] The Team page view no longer runs its own database queries.
- [x] All existing Team page, invitation, recruitment and role tests still pass unchanged.

## Comments

Done. The module is `TeamManagementPage` rather than "TeamPage": a `TeamPageTest` already covers the public "Meet the Team" page. It returns the page's data plus `screen` (`no_team`, `member`, `leader_open`, `leader_full`) and `openRoles`. It also returns what the view used to query for itself: school-year row, team size, stage, seats free, expert/pre-proposal flags, title data and previous leader.

Tests are in `TeamManagementPageTest`. They cover screen choice, open roles, the section limit, and zero queries from the page's own markup; the shared shell's composer and partials are excluded.
