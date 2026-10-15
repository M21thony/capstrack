import React from "react";
import { Icon } from "./reader/Reader";

const roleWord = (role) => (role ? role.replace(/_/g, " ") : null);

/**
 * The annotator's top bar: what this document is, whether the remarks are
 * saved, and the one thing to do at the end - finish the review.
 */
function CheckerNavbar({ ctx, status, verdict, saveState, onExport, onFinish, versions = [], comparing = false, onCompare, showChanges = true, onToggleChanges }) {
  // Leaving mid-save loses remarks, so this asks in the app's own dialog rather
  // than the browser's. The navigation is always stopped first and re-issued
  // from the promise, because the answer cannot arrive synchronously.
  const leave = (e) => {
    if (saveState !== "saving") return;

    e.preventDefault();
    const href = e.currentTarget.href;

    window.confirmSheet({
      title: "Leave while your remarks are still saving?",
      body: "The remarks you have just written are still on their way to the server. Leaving now loses them, and the team sees the document without them.",
      confirmLabel: "Leave anyway",
      cancelLabel: "Stay on this page",
      danger: true,
    }).then((ok) => {
      if (ok) window.location.assign(href);
    });
  };

  const saveText = saveState === "saving" ? "Saving…"
    : saveState === "error" ? "Not saved — check your connection"
    : "Saved";
  const busy = saveState === "saving";
  const failed = saveState === "error";

  return (
    <header className="an-bar">
      <a className="an-back" href={ctx.backUrl || "/instructor/docreview"} onClick={leave}>
        <Icon name="left" />Document Review
      </a>
      <span className="an-divider" aria-hidden="true" />
      <div className="an-doc">
        <h1>{ctx.title || ctx.fileName || "Document"}</h1>
        <div className="an-meta">
          {ctx.team && (
            <span>
              <b>{ctx.team}</b>
              {ctx.role ? ` · you are the ${roleWord(ctx.role)}` : ""}
            </span>
          )}
          {ctx.round && <span className="an-pill">{ctx.round}</span>}
          {ctx.due && <span className="an-pill gold">Needed by {ctx.due}</span>}
          {status === "Reviewed" && verdict && (
            <span className={`an-pill ${verdict === "approved" ? "ok" : "revise"}`}>
              {verdict === "approved" ? "Turned in · approved" : "Turned in · needs revision"}
            </span>
          )}
        </div>
      </div>
      <span className="rr-spacer" />
      <span className={`an-saved${busy ? " busy" : ""}${failed ? " err" : ""}`} role="status">
        <span className="dot" aria-hidden="true" />
        {saveText}
      </span>
      {versions.length > 0 && (
        <label className="an-switch">
          <input type="checkbox" checked={showChanges} onChange={onToggleChanges} />
          <span className="an-switch-track" aria-hidden="true" />
          Show changes
        </label>
      )}
      {versions.length > 0 && (
        <button type="button" className={`rr-btn ghost${comparing ? " on" : ""}`} onClick={onCompare} aria-pressed={comparing}>
          {comparing ? "Close side by side" : "Side by side"}
        </button>
      )}
      <button type="button" className="rr-btn ghost" onClick={onExport}>
        <Icon name="download" />Export PDF
      </button>
      <button type="button" className="rr-btn gold" onClick={onFinish}>
        Apply Changes
      </button>
    </header>
  );
}

export default CheckerNavbar;
