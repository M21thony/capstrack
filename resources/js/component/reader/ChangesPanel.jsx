import React, { useState } from "react";
import { PagedList } from "./Reader";

const KIND = { added: ["Added", "add"], removed: ["Removed", "del"], reworded: ["Reworded", "mod"] };

/**
 * What changed since an earlier version: the passages that differ, and each
 * remark left on that version with whether the words near it changed.
 * Nothing here is saved - it is worked out again every time the page opens.
 */
export default function ChangesPanel({ top, base, diff, selectedChange, onOpenChange, onOpenRemark }) {
  const [filter, setFilter] = useState("all");
  const ready = diff.status === "ready";
  const changes = ready ? diff.changes : [];
  const remarks = ready ? diff.remarks : [];

  const message = {
    idle: null,
    loading: [`Comparing with ${base?.round || "the earlier version"}…`, "This takes a moment on long documents."],
    notext: ["Nothing to compare", "One of the versions has no text to read. It may be a scanned image."],
    unrelated: [
      "This may be the wrong file",
      `It shares almost no text with ${base?.round || "the earlier version"} (${Math.round((diff.shared || 0) * 100)}% of its phrases). A revision keeps most of its words, so this looks like a different document - another chapter, perhaps. Open it to check, or send it back.`,
    ],
    error: ["Could not compare", "The earlier version did not load. Reload the page to try again."],
  }[diff.status];

  return (
    <aside className="rr-side" aria-label="What changed">
      {top}
      <div className="rr-side-head">
        <h2>What changed <span className="rr-count">{changes.length}</span></h2>
      </div>
      <p className="rr-help">
        Since <b>{base?.round}</b>. New and reworded words are underlined in green; a red mark shows where words were removed.
      </p>
      <div className="rr-side-body">
        {message ? (
          <div className="rr-holder"><div className="rr-empty"><b>{message[0]}</b><span>{message[1]}</span></div></div>
        ) : (
          <>
            <div className="rr-filter" role="group" aria-label="Show">
              {[["all", "All changes", changes.length], ["remarks", "Near your remarks", remarks.length]].map(([k, label, n]) => (
                <button key={k} type="button" className="rr-fbtn" aria-pressed={filter === k} onClick={() => setFilter(k)}>
                  {label} <span className="rr-fc">{n}</span>
                </button>
              ))}
            </div>
            {filter === "all" ? (
              <PagedList
                items={changes}
                keyOf={(c) => c.id}
                noun="change"
                focusKey={selectedChange}
                empty={<><b>No text changed</b><span>The words match {base?.round}. Formatting and pictures are not compared.</span></>}
                renderItem={(c, measuring) => (
                  <div
                    className="rr-card text"
                    role={measuring ? undefined : "button"}
                    tabIndex={measuring ? -1 : 0}
                    aria-current={selectedChange === c.id}
                    onClick={() => onOpenChange(c)}
                    onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); onOpenChange(c); } }}
                  >
                    <div className="rr-card-r1">
                      <span className={`rr-tag ${KIND[c.kind][1]}`}>{KIND[c.kind][0]}</span>
                      <span className="rr-where">p.{c.page}</span>
                    </div>
                    <div className="rr-diffline">
                      {c.before && <>…{c.before} </>}
                      {c.oldText && <del>{c.oldText}</del>}
                      {c.oldText && c.newText && " "}
                      {c.newText && <ins>{c.newText}</ins>}
                      {c.after && <> {c.after}…</>}
                    </div>
                  </div>
                )}
              />
            ) : (
              <PagedList
                items={remarks}
                keyOf={(r) => r.id}
                noun="remark"
                empty={<><b>No remarks on {base?.round}</b><span>You left no highlighted-text remarks on that version.</span></>}
                renderItem={(r, measuring) => {
                  const changed = r.changeIds.length > 0;
                  return (
                    <div
                      className="rr-card text"
                      role={measuring ? undefined : "button"}
                      tabIndex={measuring ? -1 : 0}
                      aria-disabled={!r.target}
                      onClick={() => onOpenRemark(r)}
                      onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); onOpenRemark(r); } }}
                    >
                      <div className="rr-card-r1">
                        <span className={`rr-tag ${changed ? "add" : ""}`}>{changed ? "Changed nearby" : "No change nearby"}</span>
                        <span className="rr-where">p.{r.page} on {base?.round}</span>
                      </div>
                      {r.quote && <div className="rr-q">“{r.quote}”</div>}
                      <div className="rr-t">{r.text}</div>
                    </div>
                  );
                }}
              />
            )}
          </>
        )}
      </div>
    </aside>
  );
}
