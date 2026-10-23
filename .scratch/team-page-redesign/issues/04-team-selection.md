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

**Status:** done

- [x] The page shows the first eight posts. Load more appends the next eight until none remain, then hides.
- [x] Each tab shows only its recruitment goal, and its count matches. The skill filter still works together with it.
- [x] Full teams stay off the list and reopened teams appear (TeamSelectionFeedTest keeps passing).
- [x] A wheel over the posts scrolls the page, and the filter header stays at the top while scrolling.
- [x] Asking to join from the details modal sends the request and marks the card Requested.

## Comments

Done.

- **`TeamSelectionFeed`:** one module for the section's posts, eight at a time, with goal and skill filters, counts per tab and the skills in use. Open slots are worked out in the module, not the view.
- **`GET /student/team/posts`** (TeamController::posts) returns a page of cards rendered by the same `student/team/_post-cards` partial, with shown, total, hasMore and counts.
- **`student/team/_selection`:**
  - a sticky header with All / Find members / Find team (with counts) and the existing skill select;
  - the card grid;
  - Load more;
  - a sticky Section overview.
- **Leaders with open seats** start on Find team.
- **Card buttons:** View post and Ask to join / Invite to team open the existing post details modal (`viewPostDetails`). Requested and Invited show as states.
- **Removed:** the old 5-per-page paginator, the client-side `filterFeed()` and the Find Members/Find Team legend.
- **Fixed on the way:** a stray `</div>` in the team dashboard closed `.content` early for anyone on a team, so everything after it lost the page padding.
- **Tests:** TeamSelectionTest. RecruitmentTest's pagination test now expects eight per page.
