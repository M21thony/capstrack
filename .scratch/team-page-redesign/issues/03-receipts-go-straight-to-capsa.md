# 03: Receipts go straight to the CaPSA

**What to build:**
- A member's uploaded receipt goes to their CaPSA immediately as Waiting (pending), with no leader hold-and-send step.
- Remove the "held" status and the leader's send action.
- Release any receipts already held to pending.
- Replacing a sent-back receipt sends it again. A verified receipt can't be replaced.
- OR number and photo are both required.

**Blocked by:** None (can start immediately)

**Status:** done

- [x] After a member uploads, their CaPSA sees the receipt right away, on the web and the API.
- [x] There is no leader "send to CaPSA" action, and no receipt is left in a held state after the data migration.
- [x] A sent-back receipt that is replaced is Waiting again. A verified receipt can't be replaced.
- [x] FinancialReceiptsTest is updated to the new flow (leader-hold expectations replaced, not silently deleted), and the receipt status tests pass.

## Comments

Done.

- **Uploads:** `FinancialRecord::submitFor()` now saves an upload as pending, and the CaPSA sees it at once.
- **Removed:**
  - the "held" status;
  - `submitTeam()` and the `api/student/financial/team-submit` route;
  - the CaPSA-side held checks;
  - the "Receipts still with team leaders" dashboard fact;
  - the leader's Send to CaPSA row and copy in the Financial tab.
- **Migration:** `release_held_receipts_to_the_capsa` moves any held receipt to pending.
- **Waiting receipts:** a receipt waiting on the CaPSA can't be replaced until she sends it back. This matches the mockup, which offers no Replace while waiting.
- **Tests:** FinancialReceiptsTest now asserts the direct flow. The leader-hold tests were replaced, not dropped. TeamReachesTitleDefenseTest no longer calls the send step.
