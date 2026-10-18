# 07: The leader screen: pending invites, open slots and inviting

**What to build:**
- For a leader whose team has free seats:
  - **Members table:** also lists each pending invite ("Picks a role after joining", PENDING, Cancel) and one clickable **Open slot · Invite a classmate** row per free seat.
  - **Invite a classmate modal:** search, section classmates without a team, an "x of y picked" counter, and Send invite disabled until someone is picked.
  - **Invite to team** (from a post) opens a modal with the person, a note that they pick their role after joining, and Send invite.
  - **Team selection:** opens on Find team, with "Your team still needs …" chips from TeamSeating's open roles.

**Blocked by:** 01, 02, 04

**Status:** ready-for-agent

- [ ] Pending invites show in the table and Cancel withdraws them. Free seats account for pending invites.
- [ ] The open slot row opens the classmate picker, and sending invites fills the slots as pending.
- [ ] Inviting from a post marks it Invited. No more invites can be sent than there are free seats (TeamCannotOverfillTest passes).
- [ ] "Your team still needs" lists only roles with free seats under the section's limit.
