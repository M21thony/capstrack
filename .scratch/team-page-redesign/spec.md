# Spec: Student Team page redesign

Status: ready-for-agent

Mockups: the "CapsTrack Team Page Redesign" design canvas (owner's claude.ai artifact). Boards:

- **Team page:**
  - no team yet;
  - in a team;
  - leader, inviting (3/5).
- **Financial Records:**
  - send your receipt;
  - waiting for CaPSA.
- **Bug:** which teams show in Team selection.
- **CaPSA Team Rules:**
  - creating closed;
  - joining closed;
  - both closed;
  - leader, joining closed.

## Problem Statement

The student Team page feels big and heavy on a 1366px laptop, and it scrolls more than it needs to.

**Team selection**
- It sits under everything else.
- It pages five posts at a time.
- It has no way to narrow to "teams that want members" or "students who want a team".

**Invites and roles**
- An invite expands inline and pushes the page around.
- A leader can't see at a glance which roles are still open.
- A leader can't see who would fit them.

**Financial Records**
- Receipts wait on the leader before the CaPSA sees them. The owner has decided receipts go straight to the CaPSA.
- The upload form hides behind a modal.
- The page overflows even when there is little on it.

**Team Rules**
- When the CaPSA turns creating or joining teams off, the page only says so in a thin strip. Buttons still look pressable until they fail.

**Modals and scrollbars**
- The page's own modals don't all match the system's modal pattern.
- Not every scroll box uses the CapsTrack scrollbar.

## Solution

The Team tab becomes one compact page with three shapes, chosen by where the student stands:

1. **No team yet.**
   - A dark hero card with **Build a team** and **Post: looking for a team**.
   - A **Team invites** card. Invite details, decline and **Choose your role** are modals.
   - **Team selection** below.
2. **In a team (member).**
   - A hero card with the project title, status chips and a stat row: Members n/N, your role, current stage, date formed.
   - A **Team members** table (Member | Role | LEADER/YOU badge) with **Ask CaPSA to leave**.
   - The defense panel.
   - **Team selection** collapsed into one bar that opens on demand.
3. **Leader, team not full.**
   - The same hero card.
   - The members table, which also lists pending invites ("Picks a role after joining", Cancel) and a clickable **Open slot · Invite a classmate** row.
   - **Team selection** filtered to students looking for a team.
   - "Your team still needs …" chips, computed from free role seats.

**Team selection** scrolls with the page:
- a sticky header with filter tabs (All / Find members / Find team) and the existing skill filter;
- compact cards in a responsive grid;
- **Load more**, eight at a time;
- a sticky Section overview beside it;
- the details of any post open in the system modal.

**Financial Records:**
- A **Your receipt** card with a three-step progress (Upload receipt → Waiting for CaPSA → Verified, or Sent back).
- The receipt form inline in the card: OR number, photo, Send to CaPSA.
- A **Team payments** card listing every member's status, with the "All N receipts must be verified…" rule inside it.
- It fits one laptop screen without scrolling.
- Receipts go to the CaPSA directly.

**When the CaPSA's Team Rules turn creating or joining off:**
- A red banner names what is closed and what is still possible.
- Every affected control is visibly locked, with a reason on hover.

**The whole page:**
- The CapsTrack scrollbar, with only the innermost box under the pointer showing a handle.
- Modals that match the system's.
- The footer sits at the bottom of the page when content is short.
- Every "5" is the section's limit from the CaPSA's Team Rules.

## User Stories

**Students without a team**

1. As a student without a team, I want to see straight away that I'm not in a team and what my two options are, so that I know what to do first.
2. As a student without a team, I want **Build a team** to open the post form already set to "Build a team", so that I don't pick the goal twice.
3. As a student without a team, I want **Post: looking for a team** to open the form set to "Find a team", so that I can advertise myself quickly.
4. As a student without a team, I want my pending team invites in their own card, so that I don't miss them.
5. As a student without a team, I want to open an invite's details (team, leader, members, roles needed) without the page jumping, so that I can judge it calmly.
6. As a student without a team, I want to decline an invite through a confirmation, so that a stray click doesn't throw an invite away.
7. As a student accepting an invite, I want to choose my role from the roles the team still has seats for, so that I don't pick a full one.
8. As a student accepting an invite, I want to see how many seats each role has left, so that I understand why a role is unavailable.
9. As a student accepting an invite to a team whose post names exactly one role, I want that role given to me without a question, so that joining is one step.
10. As a student, I want to be told that everyone on the team is also a Software Engineer/Programmer, so that I understand the role choice.
11. As a student, I want the role dialog to be impossible to close without choosing, so that I'm never left on a team with no role.

**Team selection**

12. As a student, I want Team selection to scroll with the page and not trap my mouse wheel, so that I can read the whole page with one scroll.
13. As a student, I want the Team selection filters to stay at the top while I scroll through posts, so that I can switch filters without scrolling back up.
14. As a student, I want to filter posts to teams looking for members or students looking for a team, so that I see only what's relevant.
15. As a student, I want to keep filtering posts by skill, so that I can find people or teams with what we need.
16. As a student, I want to see eight posts and load more on request, so that the page stays short until I want more.
17. As a student, I want each post as a compact card showing who posted, whether they're building or seeking, a one-line introduction, and the roles needed or skills offered, so that I can scan many at once.
18. As a student, I want to open a post's details in a modal, so that I can read the whole post without leaving the list.
19. As a student, I want to ask to join a team from its post, and see "Requested" afterwards, so that I know my request went out.
20. As a student, I want the Section overview (teamed up vs still individual) beside the posts, staying in view as I scroll, so that I know how far my section is.
21. As a student, I want teams that are full under the CaPSA's limit kept off Team selection, so that I don't ask to join a team that can't take me. (done)
22. As a student, I want a team that has a free seat again after someone left to show up in Team selection, so that I can still find it. (done)

**Members in a team**

23. As a member, I want a compact hero with the project title, team name, status, members count, my role, current stage and date formed, so that I see my team's state at a glance.
24. As a member, I want the team's members in a table with their roles and LEADER/YOU badges, so that it reads like a roster.
25. As a member, I want to ask my CaPSA to leave from the members card, in a modal that asks for a reason and says the team will see it, so that leaving goes through the proper channel.
26. As a member of a full team, I want Team selection folded into one bar with counts, which I can open if I'm curious, so that it doesn't crowd my page.
27. As a member of a full team, I want a post's details to tell me I can't join other teams, so that I'm not confused by a missing button.
28. As a member, I want to see my team's defense panel on the Team page, so that I know who my panelists are.

**Leaders**

29. As a leader, I want my pending invites listed in the members table as "Picks a role after joining" with a Cancel link, so that I can see who holds a seat and take an invite back.
30. As a leader, I want an empty seat to be a single clickable "Open slot · Invite a classmate" row, so that inviting is one click.
31. As a leader, I want to invite classmates from a searchable list of students in my section without a team, so that I can fill my team.
32. As a leader, I want the Send invite button disabled until I've picked someone, so that I can't send an empty invite.
33. As a leader, I want Team selection to open on students looking for a team, so that I see candidates first.
34. As a leader, I want to see which roles my team still needs, computed from free seats, so that I know what to look for.
35. As a leader, I want to be told that the member picks their own role after joining, so that I don't expect to assign one.
36. As a leader, I want "Members n / N" to use my section's limit from the CaPSA's Team Rules, so that the count is right when the CaPSA changes it.

**Financial Records**

37. As a member, I want to switch between the Title, Pre Oral and Final Oral Defense fees, each showing its amount, so that I can check every fee.
38. As a member, I want to see where my receipt is (uploaded, waiting for CaPSA, verified, or sent back), so that I know whether I have to act.
39. As a member who hasn't paid yet, I want the OR number and photo fields right in the card, so that I can send my receipt without opening a modal.
40. As a member, I want Send to CaPSA disabled until I've added the photo, so that I don't send an incomplete receipt.
41. As a member, I want to see the photo I picked with a Replace option, so that I can fix a wrong picture before sending.
42. As a member, I want my receipt to go straight to my CaPSA, so that I don't wait on my leader.
43. As a member whose receipt was sent back, I want the CaPSA's reason and the form with my OR number already filled, so that I can send it again quickly.
44. As a member, I want to open my uploaded receipt full size, so that I can check what I sent.
45. As a member, I want every teammate's receipt status and a verified count, so that I know what's holding up scheduling.
46. As a member, I want the rule "All N receipts must be verified before {stage} scheduling. Pre-proposal Defense has no fee." with N from my team's size, so that I know what's required.
47. As a member on a 1366×768 laptop, I want the Financial Records tab to fit without scrolling, so that the whole state is visible at once.

**CaPSA Team Rules**

48. As a student whose CaPSA closed team creation, I want a banner saying so, and **Build a team** locked (including the goal in the post form), so that I don't fill a form that will fail.
49. As a student whose CaPSA closed team creation, I want to still post that I'm looking for a team and accept invites, so that I can still get into a team.
50. As a student whose CaPSA closed joining, I want a banner, a locked Accept on invites and a locked "Closed" on posts instead of Ask to join, so that I know joining is on hold.
51. As a student whose CaPSA closed joining, I want to still decline invites and build my own team, so that what's allowed keeps working.
52. As a student whose CaPSA closed both, I want both post buttons locked and a banner telling me to talk to my CaPSA, so that I know who can help.
53. As a leader whose CaPSA closed joining, I want the open slot shown as "Joining is closed" and no Invite buttons, while I can still cancel pending invites, so that I see the rule without losing control of my invites.
54. As a student, I want every closed state to name my section, so that I know the rule is my CaPSA's choice for us.

**The whole page**

55. As a student, I want the page's modals to look and behave like the rest of CapsTrack (dark blurred overlay, white card, round close button, gold primary button), so that the system feels like one product.
56. As a student, I want the CapsTrack scrollbar on the page and its lists, with only one handle showing at a time, so that scrolling looks consistent.
57. As a student, I want the footer to stay at the bottom of the page when there is little content, so that it doesn't float mid-page.
58. As a student on a phone, I want every screen to reflow to one column without sideways scrolling, so that I can use it on mobile.
59. As a student, I want short, plain copy without filler, so that the page is quick to read.
60. As a keyboard user, I want visible focus on every control and real buttons for clickable rows, so that I can use the page without a mouse.

**CaPSA**

61. As a CaPSA, I want a team that lost a member to show as Active again, not Full, so that my Team Management view is accurate. (done)

## Implementation Decisions

**Already built on this branch** (behavior unchanged except where noted)

- **TeamSeating** answers which member roles a team still has room for, the role choices for a joining member, and the role given on join.
  - Interface: `for(team)`, `openRoles()`, `choices()`, `roleOnJoin()`.
  - Only joined members fill a role seat. Invitations carry no role, because the member picks after joining.
- **Team::isFull()** and **Team::reopenIfRoom()**:
  - Fullness is counted against the section's limit from Team Rules.
  - The status returns to Active when a member leaves.
- **Recruitment::leadersOfFullTeams()** is the single source for which posts come off Team selection, on both the web page and the API.

**New modules and changes**

- **TeamManagementPage** (done; named so to avoid the public "Meet the Team" page) is a new deep module. It answers "what does this student's Team page show".
  - **Input:** the student.
  - **Output:** the screen (no team / member / leader with open seats / leader of a full team) and that screen's data:
    - hero stats;
    - members with roles and badges;
    - pending invites;
    - open role chips (from TeamSeating);
    - Team selection's first page and counts;
    - Section overview;
    - Team Rules flags;
    - the member limit;
    - the defense panel.
  - **Replaces:** the Team page controller's inline assembly and the logic and queries in the page's inline PHP blocks.
  - **Scope:** the Financial Records and Team Rating tabs keep their own sources (FeeBoard, the rating panel).
- **Team selection paging:**
  - The first eight posts render with the page.
  - **Load more** fetches the next eight from a JSON endpoint that reuses the same feed query (filter and skill as parameters). Changing a filter resets to the first eight.
  - The existing five-per-page paginator goes.
- **Filters:**
  - All / Find members / Find team filter by recruitment goal.
  - The existing skill filter stays.
  - Counts per tab come from the same query.
- **Team Rules flags:**
  - Creating allowed, joining allowed and the member limit come from the section's Team Rules, which the page already receives.
  - The closed states are driven only by these flags.
  - The server keeps enforcing every rule. The UI lock is a courtesy, not the guard.
- **Receipts go to the CaPSA directly** (a behavior change the owner has decided):
  - An upload creates a receipt the CaPSA sees at once, status pending.
  - The leader's hold-and-send step and its "held" status are removed.
  - Existing held receipts are released to pending by a data migration.
  - Replacing a sent-back receipt sends it again.
  - A verified receipt can't be replaced.
- **Financial Records UI:**
  - The receipt form moves inline into the Your receipt card. OR number and photo are both required.
  - The upload modal goes. The full-size receipt viewer stays.
- **Modals:** every modal on the page uses one shared pattern:
  - overlay `rgba(24,25,31,.9)` with a 4px blur;
  - a white card, 14px radius, 28px padding;
  - a round 32px close button;
  - an optional icon circle;
  - a label/value detail box;
  - a gold primary button and a transparent Cancel.

  Choose your role has no close button.
- **Layout:**
  - **Keep:** the app's existing sidebar, topbar and `.content` scroll area. The redesign lives inside them.
  - **Main column:** max width 1280, padding `20px clamp(16px,3vw,28px) 32px`, `min-height: 100%` so the footer sits at the bottom.
  - **Type and tokens:** DM Sans, 14px base, 14px card radius, and the gold/ink/cream token set from the mockups.
- **Tabs:** Team, Financial Records and Team Rating stay as they are, including `?tab=`. There is no new tab for Team selection.
- **Copy:**
  - Every member limit renders from the Team Rules limit, never a literal 5.
  - Copy stays short and plain.
  - The info notes keep their content without a "CapsTip:" label.
- **Skills (blocked):**
  - Primary/Secondary skills on the Find-a-team post, the "★ Fits your open role" badge and sort, the clash warning in the invite modal, and the "Suggested" role in Choose your role all wait on the owner's other primary/secondary skills work.
  - When it lands, the skill-to-role mapping lives in TeamSeating, as a new `fits(skills)` and a suggestion inside `choices()`.

## Testing Decisions

**What a good test is:** a test drives the page or an endpoint the way a student or CaPSA does, and asserts what they would see or what was saved. It doesn't reach into private methods or markup structure beyond stable hooks (ids, roles, data attributes).

**Seams**

1. **The Team page response:** the student's Team page, read through its view data and rendered HTML. This is the main seam: screens, counts, Team Rules states and copy are asserted here.
2. **The existing action endpoints:** invitations, requests, role choice, recruitment posts, leave requests, receipt upload and CaPSA verify/send back. Each behavior change is asserted through them.
3. **TeamPage's interface,** for screen selection and the data each screen gets. TeamSeating and the feed helpers are already tested at their interfaces.

**Prior art**

- **Team page and roles:** MemberChoosesTheirRoleTest, TeamSelectionFeedTest, TeamStatusFollowsSeatsTest, TeamSeatingTest, TeamCannotOverfillTest, TeamRulesTest, RecruitmentTest, RecruitmentSkillsTest, AppScrollbarTest.
- **Receipts:** FinancialReceiptsTest and ReceiptSubmissionWithTeamStatusTest. These change with the direct-to-CaPSA decision: the leader-hold expectations are replaced, not deleted silently.

**Visual checks:** the mockup harness (render, click, scroll, measure) is the reference for layout. Each screen is also checked in the real app at 1366×657 and 390px wide.

**Formatting:** Pint runs on every PHP change.

## Out of Scope

- **The Team Rating tab:** its content and flows are unchanged. Only its tab button sits in the same bar.
- **CaPSA and admin screens:** Team Management and receipt verification UI are unchanged, except where the direct-to-CaPSA change removes "held" from what they show.
- **Team creation, transfer of leadership, removal rules, title revision and the team edit form:** they keep their current flows and only adopt the shared modal pattern where they appear on this page.
- **Primary/Secondary skills:** waits on the owner's other work (see Implementation Decisions).
- **The mobile app API's response shapes:** unchanged, except the feed already fixed and the receipt flow.

## Further Notes

- Section labels like "BSIT 3B" in copy come from the student's section.
- The defense panel card keeps its current data source; only its look changes.
- The CapsTrack scrollbar partial is already included on this page, with the innermost-box fix.
