import React, { useState, useEffect, useRef } from "react";
import { PDFDocument, rgb, StandardFonts } from "pdf-lib";
import CheckerNavbar from "./component/CheckerNavbar";
import PDFChecker from "./component/pages/PDFChecker";
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

function App() {
  // Single tool: a remark is a comment merged with its highlight
  const [commentMode, setCommentMode] = useState(false);
  const [selectedHighlightColor, setSelectedHighlightColor] = useState("yellow");
  const [comments, setComments] = useState([]);
  // The currently selected/open remark, shared by the page markers and the sidebar
  const [openCommentId, setOpenCommentId] = useState(null);
  // Review workflow status: "To Review" -> "Reviewing" -> "Reviewed"
  const [reviewStatus, setReviewStatus] = useState("To Review");
  // Transient "Saved" flash state for the save button.
  const [saveState, setSaveState] = useState("idle");
  // Background Google Drive upload state: "idle" | "syncing" | "synced" | "error".
  // Tracked separately from saveState because it finishes later — the DB
  // save is quick, but rendering the annotated PDF and uploading it to
  // Drive takes real time and shouldn't block the Save/Turn In button.
  const [driveSyncState, setDriveSyncState] = useState("idle");
  const [driveSyncError, setDriveSyncError] = useState(null);
  // Coalesces overlapping sync requests: if Save then Turn In are clicked in
  // quick succession, the second one no longer starts a second full render+
  // upload on top of the first (which was doubling main-thread work and
  // making everything else feel sluggish) — it just marks that another sync
  // is needed once the current one finishes.
  const driveSyncRef = useRef({ inFlight: false, pending: false });

  // Load the current review status on mount so the UI reflects saved state.
  useEffect(() => {
    const reviewId = getReviewId();
    if (!reviewId) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    fetch(`/reviews/${reviewId}/status`, {
      headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken },
    })
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => {
        if (data?.review_status) setReviewStatus(data.review_status);
      })
      .catch(() => {});
  }, []);

  // Updates the review workflow status on the server.
  const updateReviewStatus = async (status) => {
    const reviewId = getReviewId();
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const response = await fetch(`/reviews/${reviewId}/status`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken,
        Accept: "application/json",
      },
      body: JSON.stringify({ review_status: status }),
    });

    if (!response.ok) {
      throw new Error(`Status update failed: ${response.status}`);
    }

    return response.json();
  };

  const getActiveTool = () => (commentMode ? "comment" : null);

  // Reads the review id from the URL, e.g. ?reviewId=123 or ?fileID=123
  const getReviewId = () => {
    const params = new URLSearchParams(window.location.search);
    return params.get("reviewId") ?? params.get("fileID");
  };

  // Shared save call used by both "Save" and "Turn In" — the Laravel
  // endpoint replaces all remarks for this review in one request.
  const saveRemarks = async () => {
    const reviewId = getReviewId();
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const response = await fetch(`/reviews/${reviewId}/remarks`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken,
        Accept: "application/json",
      },
      body: JSON.stringify({ comments }),
    });

    if (!response.ok) {
      throw new Error(`Save failed: ${response.status}`);
    }

    return response.json();
  };

  // Handle turn in action — saves remarks and marks the review as Reviewed.
  const handleTurnIn = async () => {
    try {
      await persistAndFlash("Reviewed");
      alert("Turned in successfully!");
    } catch (error) {
      console.error("Turn in error:", error);
      alert("Error turning in. Please try again.");
    }
  };

  // Handle save action — saves remarks and marks the review as being Reviewed.
  const handleSave = async () => {
    try {
      await persistAndFlash("Reviewing");
    } catch (error) {
      console.error("Save error:", error);
      alert("Error saving annotations. Please try again.");
    }
  };

  // Renders the current PDF with all remarks embedded and returns the bytes.
  // Each page that has comments is widened with a blank sidebar strip so
  // comment cards are drawn in guaranteed empty space, never on top of the
  // document's own text.
  const renderAnnotatedPdf = async (fileID) => {
    const pdfUrl = fileID ? `/reviews/${fileID}/pdf` : "/test.pdf";

    const response = await fetch(pdfUrl);
    if (!response.ok) {
      throw new Error(`Failed to load PDF: ${response.status} ${response.statusText}`);
    }

    const existingPdfBytes = await response.arrayBuffer();
    const pdfDoc = await PDFDocument.load(existingPdfBytes, {
      ignoreEncryption: true,
      updateMetadata: false,
    });
    const pages = pdfDoc.getPages();

    const helveticaFont = await pdfDoc.embedFont(StandardFonts.Helvetica);
    const helveticaBoldFont = await pdfDoc.embedFont(StandardFonts.HelveticaBold);

    // Group comments by page so each page's boxes stack independently,
    // instead of using a global index that pushes later pages' boxes
    // off-screen based on how many comments earlier pages had.
    const commentsByPage = {};
    comments.forEach((comment) => {
      const pageIndex = Number(comment.page_number) - 1;
      if (!commentsByPage[pageIndex]) commentsByPage[pageIndex] = [];
      commentsByPage[pageIndex].push(comment);
    });

    Object.entries(commentsByPage).forEach(([pageIndexStr, pageComments]) => {
      const pageIndex = Number(pageIndexStr);
      if (pageIndex < 0 || pageIndex >= pages.length) return;

      const page = pages[pageIndex];
      // Capture the ORIGINAL dimensions before resizing — highlight rects
      // are stored as fractions of the original page size.
      const { width, height } = page.getSize();

      // Draw all highlights first, using the original content coordinates.
      // Each highlight also gets a small numbered badge matching the same
      // number shown on its comment card in the sidebar, so it's clear
      // which piece of text a given comment is pointing to.
      // Also records each highlight's vertical position so its comment
      // card can later be placed at (roughly) the same height in the
      // sidebar, instead of a plain top-down stack.
      const anchorYByIndex = [];
      pageComments.forEach((comment, cIdx) => {
        const highlightColor =
          HIGHLIGHT_COLOR_MAP[comment.color] || HIGHLIGHT_COLOR_MAP.yellow;

        (comment.highlightRects || []).forEach((rect) => {
          try {
            page.drawRectangle({
              x: (rect.left_position || 0) * width,
              y:
                height -
                ((rect.top_position || 0) * height) -
                ((rect.highlight_height || 0.05) * height),
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
            const badgeY =
              height - (firstRect.top_position || 0) * height;

            page.drawCircle({
              x: badgeX,
              y: badgeY,
              size: 7,
              color: rgb(0.231, 0.51, 0.965),
            });
            page.drawText(String(cIdx + 1), {
              x: badgeX - (cIdx + 1 >= 10 ? 5 : 2.5),
              y: badgeY - 3,
              size: 7,
              font: helveticaBoldFont,
              color: rgb(1, 1, 1),
            });

            anchorYByIndex[cIdx] = badgeY;
          } catch (e) {
            console.warn("Error drawing badge:", e);
          }
        }
      });

      // Widen the page to add a blank sidebar strip. This does NOT move or
      // rescale the existing content — it only extends the page boundary to
      // the right, so the new space is guaranteed empty.
      page.setSize(width + SIDEBAR_WIDTH, height);

      // Optional thin divider line between the document and the sidebar.
      page.drawLine({
        start: { x: width, y: 0 },
        end: { x: width, y: height },
        thickness: 1,
        color: rgb(0.85, 0.85, 0.85),
      });

      const commentBoxWidth = SIDEBAR_WIDTH - 20;
      const commentBoxHeight = 60;
      const margin = 10;
      const gap = 10;

      // Position each card at (roughly) the same height as its own
      // highlight, instead of a simple top-down stack. This means two
      // comments on nearby paragraphs get two cards that are also near
      // each other in the sidebar, and cards further apart in the document
      // stay visually far apart in the sidebar too — proximity in the
      // margin always reflects proximity in the text. A short connector
      // line is also drawn from each highlight to its card, so there's
      // never ambiguity even when several highlights sit close together.
      const anchored = pageComments
        .map((comment, idx) => ({
          comment,
          idx,
          anchorY: anchorYByIndex[idx] ?? height - margin - idx * (commentBoxHeight + gap),
        }))
        .sort((a, b) => b.anchorY - a.anchorY); // top of page first

      let cursorMaxTop = height;

      anchored.forEach(({ comment, idx, anchorY }) => {
        try {
          const boxX = width + margin; // inside the new sidebar, never over content
          const desiredTop = anchorY + commentBoxHeight / 2;
          const boxTop = Math.min(desiredTop, cursorMaxTop);
          const boxY = boxTop - commentBoxHeight;
          cursorMaxTop = boxY - gap;

          // If there are too many comments to fit vertically on this page,
          // stop drawing further ones rather than letting them run off the
          // bottom edge or wrap into the document area.
          if (boxY < 0) return;

          // Short connector line from the highlight to this card.
          const connectorColor =
            HIGHLIGHT_COLOR_MAP[comment.color] || HIGHLIGHT_COLOR_MAP.yellow;
          page.drawLine({
            start: { x: width, y: anchorY },
            end: { x: boxX, y: boxTop - 8 },
            thickness: 1,
            color: connectorColor,
            opacity: 0.9,
          });

          // Draw comment background
          page.drawRectangle({
            x: boxX,
            y: boxY,
            width: commentBoxWidth,
            height: commentBoxHeight,
            color: rgb(1, 1, 1),
            opacity: 1,
            borderWidth: 1,
            borderColor: rgb(0.231, 0.51, 0.965),
          });

          // Draw comment number badge
          page.drawCircle({
            x: boxX + 12,
            y: boxY + commentBoxHeight - 12,
            size: 8,
            color: rgb(0.231, 0.51, 0.965),
          });

          page.drawText((idx + 1).toString(), {
            x: boxX + 9,
            y: boxY + commentBoxHeight - 15,
            size: 7,
            font: helveticaBoldFont,
            color: rgb(1, 1, 1),
          });

          // Draw comment text (wrapped to fit the sidebar width)
          const displayText = comment.text || "";
          const maxCharsPerLine = 22;
          const words = displayText.split(" ");
          let line = "";
          let lineY = boxY + commentBoxHeight - 15;
          const lines = [];
          words.forEach((word) => {
            const testLine = line ? `${line} ${word}` : word;
            if (testLine.length > maxCharsPerLine) {
              lines.push(line);
              line = word;
            } else {
              line = testLine;
            }
          });
          if (line) lines.push(line);

          lines.slice(0, 3).forEach((textLine, lineIdx) => {
            page.drawText(textLine, {
              x: boxX + 25,
              y: lineY - lineIdx * 10,
              size: 7,
              font: helveticaFont,
              color: rgb(0.2, 0.2, 0.2),
            });
          });

          // Draw author
          page.drawText(`- ${comment.author || "Reviewer"}`, {
            x: boxX + 5,
            y: boxY + 5,
            size: 6,
            font: helveticaFont,
            color: rgb(0.5, 0.5, 0.5),
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
    const reviewId = getReviewId();
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const response = await fetch(`/reviews/${reviewId}/sync-drive`, {
      method: "POST",
      headers: {
        "Content-Type": "application/pdf",
        "X-CSRF-TOKEN": csrfToken,
        Accept: "application/json",
      },
      body: pdfBytes,
    });

    if (!response.ok) {
      // The backend sends a real reason ("Google Drive is not connected for
      // this account.", "Google Drive session expired...", etc.) — surface
      // it instead of just the HTTP status, or this fails silently with no
      // way to tell why.
      const body = await response.json().catch(() => null);
      throw new Error(body?.message || `Drive sync failed: ${response.status}`);
    }

    return response.json();
  };

  // Renders the annotated PDF and uploads it to Drive in the background.
  // Safe to call repeatedly in quick succession (Save then Turn In): if a
  // sync is already running, this just flags that another one is needed
  // once it's done, instead of running two heavy renders concurrently.
  const syncAnnotatedPdfToDrive = async () => {
    if (driveSyncRef.current.inFlight) {
      driveSyncRef.current.pending = true;
      return;
    }

    driveSyncRef.current.inFlight = true;
    setDriveSyncState("syncing");
    setDriveSyncError(null);

    try {
      const pdfBytes = await renderAnnotatedPdf(getReviewId());
      await syncDrive(pdfBytes);
      setDriveSyncState("synced");
      console.log("Annotations synced to Google Drive.");
    } catch (e) {
      setDriveSyncState("error");
      setDriveSyncError(e.message);
      console.warn("Could not sync annotations to Google Drive:", e);
    } finally {
      driveSyncRef.current.inFlight = false;
      if (driveSyncRef.current.pending) {
        driveSyncRef.current.pending = false;
        syncAnnotatedPdfToDrive();
      }
    }
  };

  // Warn before leaving the page while a Drive sync is still in flight —
  // navigating away (including the "← Back" link) aborts any pending fetch,
  // so without this the upload can silently never happen.
  useEffect(() => {
    const handleBeforeUnload = (e) => {
      if (driveSyncState !== "syncing") return;
      e.preventDefault();
      e.returnValue = "";
    };
    window.addEventListener("beforeunload", handleBeforeUnload);
    return () => window.removeEventListener("beforeunload", handleBeforeUnload);
  }, [driveSyncState]);

  // Saves remarks + status and flashes a "Saved" state on the save button.
  // The annotated-PDF render (client-side pdf-lib) and the Google Drive
  // upload are the slow part of this flow — a real network call to Google's
  // API plus redrawing every annotation onto the PDF — so they're kicked off
  // in the background afterward instead of blocking the button on them.
  const persistAndFlash = async (status) => {
    await Promise.all([saveRemarks(), updateReviewStatus(status)]);
    setReviewStatus(status);

    setSaveState("saved");
    if (window.__saveTimer) clearTimeout(window.__saveTimer);
    window.__saveTimer = setTimeout(() => setSaveState("idle"), 2000);

    syncAnnotatedPdfToDrive();
  };

  // Handle export action - downloads the annotated PDF locally.
  const handleExport = async () => {
    try {
      const fileID = getReviewId();
      const pdfBytes = await renderAnnotatedPdf(fileID);

      const blob = new Blob([pdfBytes], { type: "application/pdf" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `annotated-pdf-${Date.now()}.pdf`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);

      alert("PDF exported successfully!");
    } catch (error) {
      console.error("Export error:", error);
      alert(`Error exporting PDF: ${error.message}`);
    }
  };

  return (
    <div className="App">
      <CheckerNavbar
        commentMode={commentMode}
        setCommentMode={setCommentMode}
        selectedHighlightColor={selectedHighlightColor}
        setSelectedHighlightColor={setSelectedHighlightColor}
        status={reviewStatus}
        saveState={saveState}
        driveSyncState={driveSyncState}
        driveSyncError={driveSyncError}
        onTurnIn={handleTurnIn}
        onSave={handleSave}
        onExport={handleExport}
      />

      <PDFChecker
        commentMode={commentMode}
        setCommentMode={setCommentMode}
        activeTool={getActiveTool()}
        comments={comments}
        setComments={setComments}
        selectedHighlightColor={selectedHighlightColor}
        openCommentId={openCommentId}
        setOpenCommentId={setOpenCommentId}
      />
    </div>
  );
}

export default App;