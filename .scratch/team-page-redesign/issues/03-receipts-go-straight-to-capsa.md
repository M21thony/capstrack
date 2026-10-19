# 03: Receipts go straight to the CaPSA

**What to build:**
- A member's uploaded receipt goes to their CaPSA immediately as Waiting (pending), with no leader hold-and-send step.
- Remove the "held" status and the leader's send action.
- Release any receipts already held to pending.
- Replacing a sent-back receipt sends it again. A verified receipt can't be replaced.
- OR number and photo are both required.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] After a member uploads, their CaPSA sees the receipt right away, on the web and the API.
- [ ] There is no leader "send to CaPSA" action, and no receipt is left in a held state after the data migration.
- [ ] A sent-back receipt that is replaced is Waiting again. A verified receipt can't be replaced.
- [ ] FinancialReceiptsTest is updated to the new flow (leader-hold expectations replaced, not silently deleted), and the receipt status tests pass.
