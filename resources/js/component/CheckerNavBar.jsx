import React from "react";
import { Icon } from "./reader/Reader";

// True when the Drive error is something a "Connect Google Drive" link can
// actually fix (no account connected, or the connection went stale).
const needsDriveConnect = (message) => {
  if (!message) return false;
  const m = message.toLowerCase();
  return m.includes("not connected") || m.includes("reconnect") || m.includes("session expired");
};

const roleWord = (role) => (role ? role.replace(/_/g, " ") : null);

/**
 * The annotator's top bar: what this document is, whether the remarks are
 * saved, and the one thing to do at the end - finish the review.
 */
function CheckerNavbar({ ctx, status, verdict, saveState, driveSyncState, driveSyncError, onExport, onFinish, versions = [], comparing = false, onCompare, showChanges = true, onToggleChanges }) {
  const leave = (e) => {
    if ((saveState === "saving" || driveSyncState === "syncing")
      && !window.confirm("Your remarks are still saving. Leave anyway?")) {
      e.preventDefault();
    }
  };

  const saveText = saveState === "saving" ? "Saving…"
    : saveState === "error" ? "Not saved — check your connection"
    : driveSyncState === "syncing" ? "Saved · syncing to Drive"
    : driveSyncState === "error" ? "Saved · Drive sync failed"
    : driveSyncState === "synced" ? "Saved · synced to Drive"
    : "Saved";
  const busy = saveState === "saving" || driveSyncState === "syncing";
  const failed = saveState === "error" || driveSyncState === "error";

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
      <span
        className={`an-saved${busy ? " busy" : ""}${failed ? " err" : ""}`}
        role="status"
        title={driveSyncError ? `Drive sync failed: ${driveSyncError}` : undefined}
      >
        <span className="dot" aria-hidden="true" />
        {saveText}
      </span>
      {driveSyncState === "error" && needsDriveConnect(driveSyncError) && (
        <a className="an-link" href="/admin/gdrive/connect">Connect Google Drive</a>
      )}
      {versions.length > 0 && (
        <button type="button" className={`rr-btn ghost${showChanges ? " on" : ""}`} onClick={onToggleChanges} aria-pressed={showChanges}>
          Show changes
        </button>
      )}
      {versions.length > 0 && (
        <button type="button" className={`rr-btn ghost${comparing ? " on" : ""}`} onClick={onCompare} aria-pressed={comparing}>
          {comparing ? "Hide previous version" : "Compare with previous version"}
        </button>
      )}
      <button type="button" className="rr-btn ghost" onClick={onExport}>
        <Icon name="download" />Export PDF
      </button>
      <button type="button" className="rr-btn gold" onClick={onFinish}>
        {status === "Reviewed" ? "Change decision" : "Finish review"}
      </button>
    </header>
  );
}

export default CheckerNavbar;
