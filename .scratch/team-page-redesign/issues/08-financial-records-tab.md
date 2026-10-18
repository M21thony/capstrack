# 08: Financial Records tab: inline receipt form that fits one screen

**What to build:**
- The Financial Records tab becomes the mockup:
  - **Top row:** the tab bar on the left, the fee tabs (Title / Pre Oral / Final Oral Defense with amounts) on the right.
  - **Your receipt card:**
    - a three-step progress (Upload receipt → Waiting for CaPSA → Verified, or Sent back in red);
    - the inline form when nothing is uploaded or it was sent back: OR number, photo with Replace, and Send to CaPSA disabled until a photo is added;
    - the CaPSA's reason and the old OR number prefilled after a send-back;
    - a receipt summary with a full-size viewer when Waiting or Verified.
  - **Team payments card:** a segmented bar, legend, the "All N receipts must be verified…" rule (N = team size), and each member's status.
- No page scroll at 1366×657.

**Blocked by:** 02, 03

**Status:** ready-for-agent

- [ ] Sending a receipt from the inline form shows it as Waiting at once, and the member's row in Team payments matches.
- [ ] A sent-back receipt shows the reason and the prefilled form with "Send again".
- [ ] The rule uses the team's size, and the fee tabs switch the whole tab's data.
- [ ] No vertical page scroll at 1366×657 in any receipt state. The layout reflows to one column at 390px.
