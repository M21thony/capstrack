import React, { useCallback, useEffect, useRef, useState } from "react";
import { PDFDocument, rgb, StandardFonts } from "pdf-lib";
import CheckerNavbar from "./component/CheckerNavbar";
import PDFChecker from "./component/pages/PDFChecker";
import StudentPdfViewer from "./component/pages/StudentPdfViewer";
import FinishReview from "./component/FinishReview";
import { useVersionDiff } from "./component/reader/useVersionDiff";
import { Toast } from "./component/reader/Reader";
import "./component/reader/reader.css";
import "./App.css";

// Maps highlight color names to pdf-lib rgb for export
const HIGHLIGHT_COLOR_MAP = {
  yellow: rgb(1, 1, 0),
  green: rgb(0, 1, 0),
  blue: rgb(0, 0.749, 1),
  pink: rgb(1, 0, 0),
  orange: rgb(1, 0.647, 0),
};

// Width of the blank sidebar strip added to the right of each page for
// comment cards, so they never sit on top of the document's own content.
const SIDEBAR_WIDTH = 180;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

// What the page knows about the document: its team, requirement and round.
const readContext = () => {
  try {
    return JSON.parse(document.getElementById("pdf-annotator-root")?.dataset.context || "{}");
  } catch {
    return {};
  }
};

function App() {
  const [ctx] = useState(readContext);
  // Earlier rounds of this requirement, and the one open beside the document.
  const versions = ctx.versions || [];
  const [compareId, setCompareId] = useState(null);
  const compareVersion = versions.find((v) => v.id === compareId) || null;
  // Changes are measured against the version open beside it, else the latest earlier one.
  const baseVersion = compareVersion || versions[0] || null;
  const [showChanges, setShowChanges] = useState(true);
  const params = new URLSearchParams(window.location.search);
  const reviewId = ctx.reviewId || params.get("reviewId") || params.get("fileID");
  // Measured once: this document and the earlier version both draw from it.
  const diff = useVersionDiff(reviewId ? `/reviews/${reviewId}/pdf` : null, baseVersion);
  const [focus, setFocus] = useState(null);

  const [comments, setComments] = useState([]);
  const [generalComments, setGeneralComments] = useState([]);
  const [loaded, setLoaded] = useState(false);
  const [selectedId, setSelectedId] = useState(null);
  // Review workflow status: "To Review" -> "Reviewing" -> "Reviewed"
  const [reviewStatus, setReviewStatus] = useState("To Review");
  // "approved", "needs_revision", or null when not turned in yet.
  const [reviewVerdict, setReviewVerdict] = useState(null);
  const [saveState, setSaveState] = useState("saved");
  // Background Google Drive upload: "idle" | "syncing" | "synced" | "error".
  const [driveSyncState, setDriveSyncState] = useState("idle");
  const [driveSyncError, setDriveSyncError] = useState(null);
  const driveSyncRef = useRef({ inFlight: false, pending: false });
  const [toast, setToast] = useState(null);
  const toastTimerRef = useRef(null);
  const [finishing, setFinishing] = useState(false);
  const [busy, setBusy] = useState(false);

  const commentsRef = useRef(comments);
  commentsRef.current = comments;
  const statusRef = useRef(reviewStatus);
  statusRef.current = reviewStatus;

  const showToast = useCallback((message, opts = {}) => {
    clearTimeout(toastTimerRef.current);
    setToast({ message, ...opts });
    toastTimerRef.current = setTimeout(() => setToast(null), opts.undo ? 7000 : 4500);
  }, []);

  const request = (url, options = {}) => fetch(url, {
    ...options,
    headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf(), ...(options.headers || {}) },
  });

  // Load the saved remarks and where the review stands.
  useEffect(() => {
    if (!reviewId) { setLoaded(true); return; }
    const status = request(`/reviews/${reviewId}/status`)
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => {
        if (data?.review_status) setReviewStatus(data.review_status);
        if (data?.verdict) setReviewVerdict(data.verdict);
      });
    const remarks = request(`/reviews/${reviewId}/remarks`)
      .then((r) => r.json())
      .then((data) => setComments(data.map((r) => ({
        id: r.id,
        page_number: Number(r.page_number),
        y_position: Number(r.top_position),
        x_position: Number(r.left_position),
        text: r.comment,
        author: "Reviewer",
        timestamp: r.created_at,
        selectedText: r.selected_text,
        section: r.section,
        color: r.color,
        highlightRects: Number(r.highlight_width) > 0
          ? [{
              top_position: Number(r.top_position),
              left_position: Number(r.left_position),
              highlight_width: Number(r.highlight_width),
              highlight_height: Number(r.highlight_height),
            }]
          : [],
      }))));
    const general = request(`/reviews/${reviewId}/general-remarks`)
      .then((r) => r.json())
      .then((data) => setGeneralComments(data.map((r) => ({ id: r.id, text: r.comment, section: r.section }))));
    Promise.allSettled([status, remarks, general]).then((results) => {
      if (results.some((r) => r.status === "rejected")) showToast("Some of this review did not load. Reload the page.", { type: "error" });
      setLoaded(true);
    });
  }, [reviewId]); // eslint-disable-line react-hooks/exhaustive-deps

  const updateReviewStatus = async (status, verdict = null) => {
    const response = await request(`/reviews/${reviewId}/status`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ review_status: status, verdict }),
    });
    if (!response.ok) throw new Error(`Status update failed: ${response.status}`);
    return response.json();
  };

  // The endpoint replaces every page remark for this review in one request.
  const saveRemarks = async (list) => {
    const response = await request(`/reviews/${reviewId}/remarks`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ comments: list }),
    });
    if (!response.ok) throw new Error(`Save failed: ${response.status}`);
    return response.json();
  };

  // Remarks save themselves shortly after every change, so there is no Save
  // button to forget. The first change also marks the review as under way.
  const skipFirstSave = useRef(true);
  useEffect(() => {
    if (!loaded || !reviewId) return undefined;
    if (skipFirstSave.current) { skipFirstSave.current = false; return undefined; }
    setSaveState("saving");
    const timer = setTimeout(() => {
      saveRemarks(commentsRef.current)
        .then(async () => {
          if (statusRef.current === "To Review") {
            await updateReviewStatus("Reviewing");
            setReviewStatus("Reviewing");
          }
          setSaveState("saved");
        })
        .catch(() => setSaveState("error"));
    }, 900);
    return () => clearTimeout(timer);
  }, [comments, loaded]); // eslint-disable-line react-hooks/exhaustive-deps

  const deleteComment = (id) => {
    const list = commentsRef.current;
    const at = list.findIndex((c) => c.id === id);
    if (at < 0) return;
    const removed = list[at];
    setComments(list.filter((c) => c.id !== id));
    if (selectedId === id) setSelectedId(null);
    showToast("Remark deleted.", {
      undo: () => setComments((prev) => { const next = [...prev]; next.splice(at, 0, removed); return next; }),
    });
  };

  const addGeneral = async (text, section) => {
    try {
      const response = await request(`/reviews/${reviewId}/general-remarks`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ comment: text, section: section || null }),
      });
      if (!response.ok) throw new Error();
      const saved = await response.json();
      setGeneralComments((prev) => [{ id: saved.id, text: saved.comment, section: saved.section }, ...prev]);
    } catch {
      showToast("That general remark was not saved. Try again.", { type: "error" });
    }
  };

  const deleteGeneral = async (id) => {
    const removed = generalComments.find((g) => g.id === id);
    setGeneralComments((prev) => prev.filter((g) => g.id !== id));
    try {
      const response = await request(`/reviews/${reviewId}/general-remarks/${id}`, { method: "DELETE" });
      if (!response.ok) throw new Error();
      showToast("General remark deleted.", { undo: () => removed && addGeneral(removed.text, removed.section) });
    } catch {
      if (removed) setGeneralComments((prev) => [removed, ...prev]);
      showToast("That general remark could not be deleted.", { type: "error" });
    }
  };

  // Renders the current PDF with all remarks embedded and returns the bytes.
  // Each page that has comments is widened with a blank sidebar strip so
  // comment cards are drawn in guaranteed empty space, never on top of the
  // document's own text.
  const renderAnnotatedPdf = async () => {
    const response = await fetch(`/reviews/${reviewId}/pdf`);
    if (!response.ok) throw new Error(`Failed to load PDF: ${response.status} ${response.statusText}`);

    const pdfDoc = await PDFDocument.load(await response.arrayBuffer(), { ignoreEncryption: true, updateMetadata: false });
    const pages = pdfDoc.getPages();
    const helveticaFont = await pdfDoc.embedFont(StandardFonts.Helvetica);
    const helveticaBoldFont = await pdfDoc.embedFont(StandardFonts.HelveticaBold);

    const commentsByPage = {};
    commentsRef.current.forEach((comment) => {
      const pageIndex = Number(comment.page_number) - 1;
      (commentsByPage[pageIndex] ||= []).push(comment);
    });

    Object.entries(commentsByPage).forEach(([pageIndexStr, pageComments]) => {
      const pageIndex = Number(pageIndexStr);
      if (pageIndex < 0 || pageIndex >= pages.length) return;

      const page = pages[pageIndex];
      const { width, height } = page.getSize();
      const anchorYByIndex = [];

      pageComments.forEach((comment, cIdx) => {
        const highlightColor = HIGHLIGHT_COLOR_MAP[comment.color] || HIGHLIGHT_COLOR_MAP.yellow;
        (comment.highlightRects || []).forEach((rect) => {
          try {
            page.drawRectangle({
              x: (rect.left_position || 0) * width,
              y: height - (rect.top_position || 0) * height - (rect.highlight_height || 0.05) * height,
              width: Math.max((rect.highlight_width || 0.1) * width, 10),
              height: Math.max((rect.highlight_height || 0.05) * height, 5),
              color: highlightColor,
              opacity: 0.25,
            });
          } catch (e) {
            console.warn("Error drawing highlight:", e);
          }
        });

        const firstRect = (comment.highlightRects || [])[0];
        if (firstRect) {
          try {
            const badgeX = (firstRect.left_position || 0) * width;
            const badgeY = height - (firstRect.top_position || 0) * height;
            page.drawCircle({ x: badgeX, y: badgeY, size: 7, color: rgb(0.231, 0.51, 0.965) });
            page.drawText(String(cIdx + 1), {
              x: badgeX - (cIdx + 1 >= 10 ? 5 : 2.5), y: badgeY - 3, size: 7, font: helveticaBoldFont, color: rgb(1, 1, 1),
            });
            anchorYByIndex[cIdx] = badgeY;
          } catch (e) {
            console.warn("Error drawing badge:", e);
          }
        }
      });

      page.setSize(width + SIDEBAR_WIDTH, height);
      page.drawLine({ start: { x: width, y: 0 }, end: { x: width, y: height }, thickness: 1, color: rgb(0.85, 0.85, 0.85) });

      const commentBoxWidth = SIDEBAR_WIDTH - 20;
      const commentBoxHeight = 60;
      const margin = 10;
      const gap = 10;
      let cursorMaxTop = height;

      pageComments
        .map((comment, idx) => ({ comment, idx, anchorY: anchorYByIndex[idx] ?? height - margin - idx * (commentBoxHeight + gap) }))
        .sort((a, b) => b.anchorY - a.anchorY)
        .forEach(({ comment, idx, anchorY }) => {
          try {
            const boxX = width + margin;
            const boxTop = Math.min(anchorY + commentBoxHeight / 2, cursorMaxTop);
            const boxY = boxTop - commentBoxHeight;
            cursorMaxTop = boxY - gap;
            if (boxY < 0) return;

            page.drawLine({
              start: { x: width, y: anchorY }, end: { x: boxX, y: boxTop - 8 }, thickness: 1,
              color: HIGHLIGHT_COLOR_MAP[comment.color] || HIGHLIGHT_COLOR_MAP.yellow, opacity: 0.9,
            });
            page.drawRectangle({
              x: boxX, y: boxY, width: commentBoxWidth, height: commentBoxHeight,
              color: rgb(1, 1, 1), opacity: 1, borderWidth: 1, borderColor: rgb(0.231, 0.51, 0.965),
            });
            page.drawCircle({ x: boxX + 12, y: boxY + commentBoxHeight - 12, size: 8, color: rgb(0.231, 0.51, 0.965) });
            page.drawText((idx + 1).toString(), {
              x: boxX + 9, y: boxY + commentBoxHeight - 15, size: 7, font: helveticaBoldFont, color: rgb(1, 1, 1),
            });

            const words = (comment.text || "").split(" ");
            const lines = [];
            let line = "";
            words.forEach((word) => {
              const test = line ? `${line} ${word}` : word;
              if (test.length > 22) { lines.push(line); line = word; } else { line = test; }
            });
            if (line) lines.push(line);
            lines.slice(0, 3).forEach((textLine, lineIdx) => {
              page.drawText(textLine, {
                x: boxX + 25, y: boxY + commentBoxHeight - 15 - lineIdx * 10, size: 7, font: helveticaFont, color: rgb(0.2, 0.2, 0.2),
              });
            });
            page.drawText(`- ${comment.author || "Reviewer"}`, {
              x: boxX + 5, y: boxY + 5, size: 6, font: helveticaFont, color: rgb(0.5, 0.5, 0.5),
            });
          } catch (e) {
            console.warn("Error drawing comment:", e);
          }
        });
    });

    return pdfDoc.save();
  };

  // Uploads the annotated PDF bytes to the review's Google Drive file.
  const syncDrive = async (pdfBytes) => {
    const response = await request(`/reviews/${reviewId}/drive-file`, {
      method: "PUT",
      headers: { "Content-Type": "application/pdf" },
      body: pdfBytes,
    });
    if (!response.ok) {
      const body = await response.json().catch(() => null);
      throw new Error(body?.message || `Drive sync failed: ${response.status}`);
    }
    return response.json();
  };

  // Safe to call repeatedly: a sync already running just flags that another
  // is needed once it finishes, instead of running two heavy renders at once.
  const syncAnnotatedPdfToDrive = async () => {
    if (driveSyncRef.current.inFlight) { driveSyncRef.current.pending = true; return; }
    driveSyncRef.current.inFlight = true;
    setDriveSyncState("syncing");
    setDriveSyncError(null);
    try {
      await syncDrive(await renderAnnotatedPdf());
      setDriveSyncState("synced");
    } catch (e) {
      setDriveSyncState("error");
      setDriveSyncError(e.message);
    } finally {
      driveSyncRef.current.inFlight = false;
      if (driveSyncRef.current.pending) {
        driveSyncRef.current.pending = false;
        syncAnnotatedPdfToDrive();
      }
    }
  };

  // Leaving while remarks save or the Drive upload runs would drop them.
  useEffect(() => {
    const onBeforeUnload = (e) => {
      if (saveState !== "saving" && driveSyncState !== "syncing") return;
      e.preventDefault();
      e.returnValue = "";
    };
    window.addEventListener("beforeunload", onBeforeUnload);
    return () => window.removeEventListener("beforeunload", onBeforeUnload);
  }, [saveState, driveSyncState]);

  // Turning in is the moment the reviewer decides, so it carries the verdict.
  const handleTurnIn = async (verdict) => {
    setBusy(true);
    try {
      await saveRemarks(commentsRef.current);
      await updateReviewStatus("Reviewed", verdict);
      setReviewStatus("Reviewed");
      setReviewVerdict(verdict);
      setSaveState("saved");
      setFinishing(false);
      showToast(verdict === "approved" ? "Turned in — approved." : "Turned in — sent back for revision.");
      syncAnnotatedPdfToDrive();
    } catch (error) {
      console.error("Turn in error:", error);
      showToast("Could not turn this in. Please try again.", { type: "error" });
    } finally {
      setBusy(false);
    }
  };

  const handleExport = async () => {
    try {
      const blob = new Blob([await renderAnnotatedPdf()], { type: "application/pdf" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `${(ctx.fileName || "annotated").replace(/[^\w.-]+/g, "_")}-remarks.pdf`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      showToast("PDF with your remarks downloaded.");
    } catch (error) {
      console.error("Export error:", error);
      showToast(`Could not export the PDF: ${error.message}`, { type: "error" });
    }
  };

  return (
    <div className="an-app">
      <CheckerNavbar
        ctx={ctx}
        status={reviewStatus}
        verdict={reviewVerdict}
        saveState={saveState}
        driveSyncState={driveSyncState}
        driveSyncError={driveSyncError}
        onExport={handleExport}
        onFinish={() => setFinishing(true)}
        versions={versions}
        comparing={Boolean(compareVersion)}
        onCompare={() => setCompareId(compareVersion ? null : versions[0]?.id ?? null)}
        showChanges={showChanges}
        onToggleChanges={() => setShowChanges((on) => !on)}
      />
      <div className={compareVersion ? "an-split" : "an-single"}>
        <PDFChecker
          pdfUrl={reviewId ? `/reviews/${reviewId}/pdf` : null}
          comments={comments}
          setComments={setComments}
          generalComments={generalComments}
          onAddGeneral={addGeneral}
          onDeleteGeneral={deleteGeneral}
          onDeleteComment={deleteComment}
          selectedId={selectedId}
          setSelectedId={setSelectedId}
          notify={showToast}
          base={baseVersion}
          showChanges={showChanges}
          diff={diff}
          onFocusChange={(c) => setFocus({ id: c.id, page: c.oldPage, rect: c.oldRect, at: Date.now() })}
        />
        {compareVersion && (
          <div className="an-pane-head">
            <span className="an-dot now" aria-hidden="true" />
            <b>This version</b>
            {ctx.round && <span className="an-pane-sub">{ctx.round}</span>}
            <span className="rr-spacer" />
            {showChanges && <span className="an-key ins">New or reworded</span>}
          </div>
        )}
        {compareVersion && (
          <section className="an-compare" aria-label="Earlier version of this document">
            <div className="an-compare-bar">
              <span className="an-dot old" aria-hidden="true" />
              <label htmlFor="an-compare-pick">Earlier version</label>
              <select id="an-compare-pick" value={compareVersion.id} onChange={(e) => setCompareId(Number(e.target.value))}>
                {versions.map((v) => (
                  <option key={v.id} value={v.id}>{v.round}{v.uploaded ? ` · ${v.uploaded}` : ""}</option>
                ))}
              </select>
              <span className="rr-spacer" />
              {showChanges && <span className="an-key del">Removed</span>}
              <button type="button" className="rr-btn sm" onClick={() => setCompareId(null)}>Close</button>
            </div>
            <StudentPdfViewer
              key={compareVersion.id}
              pdfUrl={compareVersion.file}
              remarksUrl={compareVersion.remarks}
              reviewer="you"
              marks={showChanges && diff.status === "ready" ? diff.oldMarks : null}
              focus={focus}
            />
          </section>
        )}
      </div>
      <FinishReview
        open={finishing}
        onClose={() => setFinishing(false)}
        onConfirm={handleTurnIn}
        remarks={comments}
        general={generalComments}
        verdict={reviewVerdict}
        busy={busy}
        ctx={ctx}
      />
      <Toast toast={toast} onClose={() => setToast(null)} />
    </div>
  );
}

export default App;
