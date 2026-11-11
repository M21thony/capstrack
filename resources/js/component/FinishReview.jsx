import React, { useEffect, useRef } from "react";
import { kindOf } from "./reader/Reader";
import { verdictFor } from "./reader/verdict";

/**
 * Turning in sends the decision the remarks make: any remark asks for a
 * revision, none approves. The reviewer still confirms before it is sent.
 */
export default function FinishReview({ open, onClose, onConfirm, remarks, general, busy, ctx }) {
  const ref = useRef(null);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) dialog.showModal();
    if (!open && dialog.open) dialog.close();
  }, [open]);

  const pages = new Set(remarks.map((r) => r.page_number)).size;
  const mustFix = remarks.filter((r) => kindOf(r.color) === "pink").length;
  const verdict = verdictFor(remarks, general);
  const approved = verdict === "approved";

  return (
    <dialog ref={ref} className="rr-dialog" aria-labelledby="finish-title" onClose={onClose}>
      <form
        className="rr-dlg"
        onSubmit={(e) => {
          e.preventDefault();
          onConfirm(verdict);
        }}
      >
        <h2 id="finish-title">Apply changes{ctx.title ? ` to ${ctx.title}` : ""}</h2>
        <div className="rr-sum">
          <span><b>{remarks.length}</b> {remarks.length === 1 ? "remark" : "remarks"} on {pages} {pages === 1 ? "page" : "pages"}</span>
          <span><b>{mustFix}</b> must fix</span>
          <span><b>{general.length}</b> general</span>
        </div>
        <div className="rr-verdict">
          <b>{approved ? "Approved" : "Needs revision"}</b>
          <small>
            {approved
              ? ctx.requirement
                ? <>No remarks, so the team's <b>{ctx.requirement}</b> goes to their CaPSA/TSA to verify.</>
                : "No remarks, so the team is told this document is approved."
              : <>You left remarks, so the team is asked for their <b>{ctx.nextRound || "next revision"}</b> with your remarks attached.</>}
          </small>
        </div>
        <div className="rr-dlg-actions">
          <button type="button" className="rr-btn" onClick={onClose}>Keep reviewing</button>
          <button type="submit" className="rr-btn gold" disabled={busy}>
            {busy ? "Turning in…" : approved ? "Approve and turn in" : "Send back for revision"}
          </button>
        </div>
      </form>
    </dialog>
  );
}
