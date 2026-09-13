import React, { useEffect, useRef, useState } from "react";
import { kindOf } from "./reader/Reader";

/**
 * Turning in is the moment the reviewer decides, so this is where the
 * decision is made - with a summary of what they are sending back and what
 * each choice does for the team.
 */
export default function FinishReview({ open, onClose, onConfirm, remarks, general, verdict, busy, ctx }) {
  const ref = useRef(null);
  const [choice, setChoice] = useState(verdict || null);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) {
      setChoice(verdict || null);
      dialog.showModal();
    }
    if (!open && dialog.open) dialog.close();
  }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

  const pages = new Set(remarks.map((r) => r.page_number)).size;
  const mustFix = remarks.filter((r) => kindOf(r.color) === "pink").length;

  return (
    <dialog ref={ref} className="rr-dialog" aria-labelledby="finish-title" onClose={onClose}>
      <form
        className="rr-dlg"
        onSubmit={(e) => {
          e.preventDefault();
          if (choice) onConfirm(choice);
        }}
      >
        <h2 id="finish-title">Finish your review{ctx.title ? ` of ${ctx.title}` : ""}</h2>
        <div className="rr-sum">
          <span><b>{remarks.length}</b> {remarks.length === 1 ? "remark" : "remarks"} on {pages} {pages === 1 ? "page" : "pages"}</span>
          <span><b>{mustFix}</b> must fix</span>
          <span><b>{general.length}</b> general</span>
        </div>
        <fieldset>
          <legend className="rr-sr">Your decision</legend>
          <label className="rr-choice">
            <input type="radio" name="verdict" value="approved" checked={choice === "approved"} onChange={() => setChoice("approved")} />
            <b>Approve</b>
            <small>
              {ctx.requirement
                ? <>The team's <b>{ctx.requirement}</b> goes to their CaPSA to verify.</>
                : "The team is told this document is approved."}
            </small>
          </label>
          <label className="rr-choice">
            <input type="radio" name="verdict" value="needs_revision" checked={choice === "needs_revision"} onChange={() => setChoice("needs_revision")} />
            <b>Needs revision</b>
            <small>The team is asked for their <b>{ctx.nextRound || "next revision"}</b>, with your remarks attached.</small>
          </label>
        </fieldset>
        <div className="rr-dlg-actions">
          <button type="button" className="rr-btn" onClick={onClose}>Keep reviewing</button>
          <button type="submit" className="rr-btn gold" disabled={!choice || busy}>
            {busy ? "Turning in…" : !choice ? "Choose a decision" : choice === "approved" ? "Approve and turn in" : "Send back for revision"}
          </button>
        </div>
      </form>
    </dialog>
  );
}
