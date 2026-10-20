# 05: The no-team screen: hero, invites and Choose your role

**What to build:**
- For a student without a team:
  - **Hero card:** "You're not in a team yet", Build a team / Post: looking for a team (each opening the post form on its goal), and the 1-2-3 steps.
  - **Team invites card:** a fixed-height list. A clickable row header opens invite details. Accept opens Choose your role. Decline asks for confirmation.
  - **Choose your role:** uses TeamSeating choices, shows "x of N free" per role and the note that everyone is also a Software Engineer/Programmer, and has no close button.
- The post form's info notes keep their content without the "CapsTip:" label. The member limit renders from Team Rules.

**Blocked by:** 01, 02

**Status:** done

- [x] Build a team and Post: looking for a team open the post form on the matching goal.
- [x] Invite details show the team, leader, members and roles needed. Declining needs confirmation.
- [x] Accepting an invite whose post names one role assigns it. Otherwise Choose your role offers only roles with seats.
- [x] The hero and post form copy show the section's member limit, not a literal 5.

## Comments

- Done. `student/team/_no-team` (hero, invites card, invite details in `x-team-modal`) and `student/team/_team-extras` (the leader's post and join requests until 06/07 replace them). Invite details list TeamSeating's open roles. Choose your role was already the TeamSeating modal with no close button. "CapsTip:" dropped from the post form notes. The no-team hero already shows ticket 09's banner and locks for its own controls. Tests: `NoTeamScreenTest`.
