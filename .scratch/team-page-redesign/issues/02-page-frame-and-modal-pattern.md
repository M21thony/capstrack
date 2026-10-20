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

**Status:** done

- [ ] On a page with little content the footer sits at the bottom of the scroll area. On a long page it follows the content.
- [ ] Every modal on the Team page renders through the shared component. Choose your role still has no close button.
- [ ] The CapsTrack scrollbar is the only vertical scrollbar on the page and its lists, with one handle at a time.
- [ ] No horizontal scroll at 390px. Existing Team page tests pass.

## Comments

Findings from the code, 2026-10-20. These change the scope.

- **Footer:** already handled app-wide. `partials/mobile-tabbar` appends the "System Developers" credit as the last thing in `.content` and grows the gap above it so it sits at the bottom of a short page. The Team page needs no footer of its own; only check it in the browser once the screens change.
- **Modals:** the page's modals already share the system pattern from `student-ui/Styles/team.css`: `.modal-overlay` (rgba(24,25,31,.9), 4px blur), `.card.modal-card` and `.pm-close-btn`. Two exceptions draw their own overlay inline: the confirm dialog and the "expert removed" notice.
- **Revised scope:**
  - A Blade component for that pattern (overlay, card width, optional close, slot).
  - Move the confirm dialog and the expert-removed notice onto the shared classes.
  - The other modals adopt the component when tickets 04–07 rewrite their screens. Their acceptance criteria cover it.
- **Assets:** Vite can't fetch fonts.bunny.net in the cloud sandbox. Building with a temporary config that drops the `fonts` option works for local checks; don't commit that config.

Done (revised scope).

- **`x-team-modal`:** renders the system pattern: `.modal-overlay` > `.card.modal-card.team-modal`, with the close button in the corner. It takes `:closable="false"` for modals that must be answered.
- **Overlays:** the confirm dialog and the expert-removed notice now use the system overlay (rgba(24,25,31,.9), 4px blur).
- **Tests:** TeamModalTest.
