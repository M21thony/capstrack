# 04: Team selection scrolls with the page, filters and loads more

**What to build:**
- Team selection becomes the mockup's section:
  - it scrolls with the page, with no inner scroll box;
  - a sticky header with All / Find members / Find team tabs (with counts) and the existing skill filter;
  - compact post cards in a responsive grid;
  - a sticky Section overview beside the cards;
  - "Showing X of Y · Load more", adding eight at a time from a JSON endpoint that reuses the feed query;
  - a post details modal;
  - Ask to join → Send request → "Requested".
- Changing a filter resets to the first eight.

**Blocked by:** 01, 02

**Status:** ready-for-agent

- [ ] The page shows the first eight posts. Load more appends the next eight until none remain, then hides.
- [ ] Each tab shows only its recruitment goal, and its count matches. The skill filter still works together with it.
- [ ] Full teams stay off the list and reopened teams appear (TeamSelectionFeedTest keeps passing).
- [ ] A wheel over the posts scrolls the page, and the filter header stays at the top while scrolling.
- [ ] Asking to join from the details modal sends the request and marks the card Requested.
