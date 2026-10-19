# 09: CaPSA Team Rules: closed states on every screen

**What to build:**
- When the section's Team Rules turn creating or joining off, show a red banner naming what's closed and what still works, with the section named, and lock every affected control with a reason on hover.

| Rules state | Locked | Still works |
|---|---|---|
| Creating closed | Build a team, and the post form's Build goal | Find-a-team posts, accepting invites |
| Joining closed | Accept on invites (Decline still works), invite details' Accept invite, a locked "Closed" on posts instead of Ask to join; for a leader, the open slot ("Joining is closed") and Invite to team | Building a team; for a leader, cancelling pending invites |
| Both closed | Both post buttons | Talking to the CaPSA |

**Blocked by:** 04, 05, 07

**Status:** ready-for-agent

- [ ] Each of the four states renders its banner and locks, asserted on the Team page response with the section's Team Rules set.
- [ ] Everything that should still work is enabled, and the server still refuses closed actions (TeamRulesTest passes).
- [ ] A section's own rules win over the school-wide settings.
