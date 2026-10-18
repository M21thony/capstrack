# 02: Team page frame, tokens, footer and the shared modal pattern

**What to build:**
- Set up the Team tab's frame inside the app's existing sidebar, topbar and scroll area:
  - the 1280px main column with the mockup padding;
  - DM Sans, 14px base text and the mockup color tokens;
  - the footer pinned to the bottom when content is short;
  - visible focus styles.
- Build one shared modal component that matches the system's modals:
  - blurred dark overlay;
  - white 14px card;
  - round close button (optional);
  - optional icon circle;
  - label/value detail box;
  - gold primary and transparent Cancel.
- Move the page's existing modals onto it without changing what they do.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] On a page with little content the footer sits at the bottom of the scroll area. On a long page it follows the content.
- [ ] Every modal on the Team page renders through the shared component. Choose your role still has no close button.
- [ ] The CapsTrack scrollbar is the only vertical scrollbar on the page and its lists, with one handle at a time.
- [ ] No horizontal scroll at 390px. Existing Team page tests pass.
