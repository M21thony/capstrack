import React, { useState, useEffect, useRef } from "react";
import { Document, Page, pdfjs } from "react-pdf";
import "react-pdf/dist/Page/AnnotationLayer.css";
import "react-pdf/dist/Page/TextLayer.css";
import Sidebar from "../Sidebar/Sidebar";

pdfjs.GlobalWorkerOptions.workerSrc = new URL(
  "pdfjs-dist/build/pdf.worker.min.mjs",
  import.meta.url
).toString();

// Maps highlight color names to rgba strings used for the highlight overlay.
const HIGHLIGHT_COLORS = {
  yellow: "rgba(255, 255, 0, 0.25)",
  green: "rgba(0, 255, 0, 0.25)",
  blue: "rgba(0, 191, 255, 0.25)",
  pink: "rgba(255, 0, 0, 0.25)",
  orange: "rgba(255, 165, 0, 0.25)",
};

// Merge selection fragments into one continuous rectangle per text line so the
// highlight looks like a real highlighter instead of separate "correction tape" strips.
const mergeRectsByLine = (rects) => {
  if (!rects.length) return [];
  const sorted = [...rects].sort((a, b) => a.top_position - b.top_position);
  const merged = [];
  let current = { ...sorted[0] };

  for (let i = 1; i < sorted.length; i++) {
    const r = sorted[i];
    const sameLine = Math.abs(r.top_position - current.top_position) < 0.01;
    if (sameLine) {
      const left = Math.min(current.left_position, r.left_position);
      const right = Math.max(
        current.left_position + current.highlight_width,
        r.left_position + r.highlight_width
      );
      const bottom = Math.max(
        current.top_position + current.highlight_height,
        r.top_position + r.highlight_height
      );
      current.left_position = left;
      current.highlight_width = right - left;
      current.highlight_height = bottom - current.top_position;
    } else {
      merged.push(current);
      current = { ...r };
    }
  }
  merged.push(current);
  return merged;
};

function PDFChecker({
  commentMode,
  setCommentMode,
  activeTool,
  comments,
  setComments,
  selectedHighlightColor = "yellow",
  openCommentId,
  setOpenCommentId,
}) {
  const [numPages, setNumPages] = useState(null);
  const [error, setError] = useState(null);
  const [pdfUrl, setPdfUrl] = useState(null);
  const [scale, setScale] = useState(1.0);
  const [currentPage, setCurrentPage] = useState(1);
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [selectionCommentIcon, setSelectionCommentIcon] = useState(null);
  // Draft remark being composed inline (replaces the browser prompt)
  const [draft, setDraft] = useState(null);
  const [draftText, setDraftText] = useState("");
  // Which comment's card is currently open (null = none). Only one shown at a time.
  // Provided by the parent (App) so the sidebar and page markers stay in sync.

  // Word-compatible page dimensions at 96 DPI (standard screen resolution)
  const pageDimensions = { width: 816, height: 1056 };
  const containerRef = useRef(null);
  const isProcessingComment = useRef(false);
  const pdfAreaRef = useRef(null);
  const scrollTicking = useRef(false);
  const [visiblePages, setVisiblePages] = useState(() => new Set([1, 2, 3, 4, 5]));

  useEffect(() => {
    // Load PDF from URL params or use default test.pdf
    const params = new URLSearchParams(window.location.search);
    const fileID = params.get("fileID") || params.get("reviewId");

    if (fileID) {
      setPdfUrl({ url: `/reviews/${fileID}/pdf` });
    } else {
      // Use default test.pdf for demonstration
      setPdfUrl("/test.pdf");
    }
  }, []);

  // Load previously saved remarks for this review, so refreshing the page
  // doesn't wipe out comments that were already saved to the database.
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const reviewId = params.get("reviewId") || params.get("fileID");
    if (!reviewId) return;

    fetch(`/reviews/${reviewId}/remarks`)
      .then((r) => r.json())
      .then((data) => {
        const mapped = data.map((r) => ({
          id: r.id,
          page_number: r.page_number,
          y_position: Number(r.top_position),
          x_position: Number(r.left_position),
          text: r.comment,
          author: "Reviewer",
          timestamp: r.created_at,
          selectedText: undefined,
          highlightRects: [
            {
              top_position: Number(r.top_position),
              left_position: Number(r.left_position),
              highlight_width: Number(r.highlight_width),
              highlight_height: Number(r.highlight_height),
            },
          ],
          color: r.color,
          resolved: false,
          replies: [],
        }));
        setComments(mapped);
      })
      .catch((e) => console.error("Failed to load remarks:", e));
  }, [setComments]);

  useEffect(() => {
    const pdfArea = pdfAreaRef.current;
    if (!pdfArea || !numPages) return;

    const updateVisiblePages = () => {
      if (scrollTicking.current) return;
      scrollTicking.current = true;

      requestAnimationFrame(() => {
        const scrollTop = pdfArea.scrollTop;
        const viewportHeight = pdfArea.clientHeight;
        const pageHeight = pageDimensions.height * scale + 40;

        const startPage = Math.max(1, Math.floor(scrollTop / pageHeight) - 2);
        const endPage = Math.min(numPages, Math.ceil((scrollTop + viewportHeight) / pageHeight) + 2);

        const newVisible = new Set();
        for (let i = startPage; i <= endPage; i++) {
          newVisible.add(i);
        }

        setVisiblePages((prev) => {
          if (prev.size === newVisible.size && [...prev].every((p) => newVisible.has(p))) {
            return prev;
          }
          return newVisible;
        });

        scrollTicking.current = false;
      });
    };

    updateVisiblePages();
    pdfArea.addEventListener("scroll", updateVisiblePages, { passive: true });
    return () => pdfArea.removeEventListener("scroll", updateVisiblePages);
  }, [numPages, scale]);

  function onDocumentLoadSuccess({ numPages }) {
    setNumPages(numPages);
    setError(null);
  }

  function onDocumentLoadError(err) {
    console.error("Error loading PDF:", err);
    setError(err.message);
  }

  // Add a remark (comment merged with its highlight) to the list
  const addRemark = ({ pageNumber, x, y, text, selectedText, highlightRects }) => {
    setComments((prev) => [
      ...prev,
      {
        id: Date.now(),
        page_number: pageNumber,
        x_position: x,
        y_position: y,
        text,
        author: "Reviewer",
        timestamp: new Date().toISOString(),
        selectedText: selectedText,
        highlightRects: highlightRects,
        color: selectedHighlightColor,
        resolved: false,
        replies: [],
      },
    ]);
  };

  // Delete a remark by id
  const deleteComment = (id) => {
    setComments((prev) => prev.filter((c) => c.id !== id));
  };

  // Handle page click for comments
  const handlePageClick = (e, pageNumber) => {
    if (activeTool !== "comment") {
      // Clicking elsewhere closes any open comment card
      if (openCommentId) setOpenCommentId(null);
      return;
    }

    const pageEl = e.currentTarget;
    const rect = pageEl.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;

    // Only prompt if there is selected text
    const selection = window.getSelection();
    const selectedText = selection?.toString().trim();

    if (!selectedText) {
      // No text selected, don't prompt
      return;
    }

    // Get selection range for highlighting
    const range = selection.getRangeAt(0);
    const rects = Array.from(range.getClientRects());
    const pageRect = pageEl.getBoundingClientRect();

    // Calculate highlight positions
    const highlightRects = mergeRectsByLine(
      rects
        .filter((r) => r.width > 5 && r.height > 5)
        .map((r) => ({
          top_position: (r.top - pageRect.top) / pageRect.height,
          left_position: (r.left - pageRect.left) / pageRect.width,
          highlight_width: r.width / pageRect.width,
          highlight_height: r.height / pageRect.height,
        }))
    );

    setDraft({ pageNumber, x, y, selectedText, highlightRects });
    setDraftText("");
    setOpenCommentId(null);
  };

  // Zoom controls
  const zoomIn = () => setScale((prev) => Math.min(prev + 0.2, 2.0));
  const zoomOut = () => setScale((prev) => Math.max(prev - 0.2, 0.5));
  const resetZoom = () => setScale(1.0);

  // Handle text selection for comment icon
  useEffect(() => {
    const handleSelection = () => {
      // Don't show selection icon if we're in the process of adding a comment
      if (isProcessingComment.current || activeTool === "comment") {
        return;
      }

      const selection = window.getSelection();
      if (!selection || selection.isCollapsed) {
        setSelectionCommentIcon(null);
        return;
      }

      const range = selection.getRangeAt(0);
      const pageEl = range.startContainer.parentElement?.closest(".react-pdf__Page");
      if (!pageEl) {
        setSelectionCommentIcon(null);
        return;
      }

      const pageNumber = Number(pageEl.getAttribute("data-page-number"));
      const rects = Array.from(range.getClientRects());
      if (rects.length === 0) {
        setSelectionCommentIcon(null);
        return;
      }

      // Get the last rect (end of selection)
      const lastRect = rects[rects.length - 1];
      const pageRect = pageEl.getBoundingClientRect();

      setSelectionCommentIcon({
        pageNumber,
        x: lastRect.right - pageRect.left,
        y: lastRect.top - pageRect.top,
        selectedText: selection.toString(),
      });
    };

    // Use mouseup instead of selectionchange to avoid triggering on deselect
    const handleMouseUp = (e) => {
      // Don't process if we're adding a comment
      if (isProcessingComment.current) {
        return;
      }
      // Small delay to ensure selection is complete
      setTimeout(handleSelection, 10);
    };

    document.addEventListener("mouseup", handleMouseUp);
    return () => document.removeEventListener("mouseup", handleMouseUp);
  }, [activeTool]);

  // Handle adding comment from selection
  const handleSelectionComment = (e) => {
    e.preventDefault();
    e.stopPropagation();

    if (!selectionCommentIcon || isProcessingComment.current) return;

    // Set processing flag to prevent re-triggering
    isProcessingComment.current = true;
    const savedSelection = { ...selectionCommentIcon };

    // Clear selection state first to prevent re-triggering
    setSelectionCommentIcon(null);

    // Build highlight rects from the selected text BEFORE clearing the selection
    let highlightRects = [];
    const pageEl = document.querySelector(`.react-pdf__Page[data-page-number="${savedSelection.pageNumber}"]`);
    const pageRect = pageEl?.getBoundingClientRect();
    const selection = window.getSelection();
    if (pageRect && selection && !selection.isCollapsed) {
      const range = selection.getRangeAt(0);
        highlightRects = mergeRectsByLine(
          Array.from(range.getClientRects())
            .filter((r) => r.width > 5 && r.height > 5)
            .map((r) => ({
              top_position: (r.top - pageRect.top) / pageRect.height,
              left_position: (r.left - pageRect.left) / pageRect.width,
              highlight_width: r.width / pageRect.width,
              highlight_height: r.height / pageRect.height,
            }))
        );
    }

    window.getSelection()?.removeAllRanges();

    if (pageRect) {
      const y = savedSelection.y / pageRect.height;
      setDraft({
        pageNumber: savedSelection.pageNumber,
        x: 1,
        y,
        selectedText: savedSelection.selectedText,
        highlightRects,
      });
      setDraftText("");
      setOpenCommentId(null);
    }
    // Reset processing flag after prompt is closed with longer delay
    setTimeout(() => {
      isProcessingComment.current = false;
    }, 500);
  };

  // Submit the inline draft comment (merged with its highlight)
  const submitDraft = () => {
    if (!draft || !draftText.trim()) return;
    addRemark({
      pageNumber: draft.pageNumber,
      x: draft.x,
      y: draft.y,
      text: draftText.trim(),
      selectedText: draft.selectedText,
      highlightRects: draft.highlightRects,
    });
    setDraft(null);
    setDraftText("");
  };

  // Cancel the inline draft comment
  const cancelDraft = () => {
    setDraft(null);
    setDraftText("");
  };

  return (
    <div style={styles.container}>
      {/* Sidebar */}
      <Sidebar
        isOpen={sidebarOpen}
        toggleSidebar={() => setSidebarOpen(!sidebarOpen)}
        comments={comments}
        setComments={setComments}
        numPages={numPages}
        currentPage={currentPage}
        setCurrentPage={setCurrentPage}
        selectedCommentId={openCommentId}
        onSelectComment={setOpenCommentId}
      />

      {/* Main Content Area */}
      <div
        ref={containerRef}
        style={{
          ...styles.mainContent,
          marginRight: sidebarOpen ? "280px" : "0",
        }}
      >
        {/* Toolbar */}
        <div style={styles.toolbar}>
          <div style={styles.zoomControls}>
            <button onClick={zoomOut} style={styles.zoomButton}>−</button>
            <span style={styles.zoomText}>{Math.round(scale * 100)}%</span>
            <button onClick={zoomIn} style={styles.zoomButton}>+</button>
            <button onClick={resetZoom} style={styles.resetButton}>Reset</button>
          </div>

          <button onClick={() => setSidebarOpen(!sidebarOpen)} style={styles.toolbarButton}>
            {sidebarOpen ? "Hide Sidebar ▶" : "◀ Show Sidebar"}
          </button>
        </div>

        {/* PDF and Comments Container */}
        <div style={{ display: "flex", flex: 1, overflow: "hidden" }}>
          {/* PDF Display Area */}
          <div style={styles.pdfArea}>
            {error && (
              <div style={styles.errorContainer}>
                <p style={styles.errorText}>Error: {error}</p>
                <p style={styles.errorHint}>Please ensure a valid PDF is available.</p>
              </div>
            )}

            {pdfUrl && (
              <div style={styles.documentContainer}>
                <Document
                  file={pdfUrl}
                  onLoadSuccess={onDocumentLoadSuccess}
                  onLoadError={onDocumentLoadError}
                  loading={
                    <div style={styles.loadingContainer}>
                      <div style={styles.spinner}></div>
                      <p>Loading PDF...</p>
                    </div>
                  }
                >
                  {numPages &&
                    Array.from({ length: numPages }, (_, index) => {
                      const pageNum = index + 1;
                      const isPageLoaded = visiblePages.has(pageNum);

                      return (
                        <div
                          key={`page_${pageNum}`}
                          style={{ display: "flex", flexDirection: "row", gap: "20px", marginBottom: "20px" }}
                        >
                          {/* PDF Page */}
                          <div style={styles.pageContainer}>
                            <div
                              style={{
                                ...styles.pageWrapper,
                                minHeight: pageDimensions.height * scale,
                              }}
                              onClick={(e) => handlePageClick(e, pageNum)}
                            >
                              {isPageLoaded && (
                                <Page
                                  pageNumber={pageNum}
                                  width={pageDimensions.width * scale}
                                  height={pageDimensions.height * scale}
                                  renderTextLayer={true}
                                  renderAnnotationLayer={true}
                                />
                              )}

                              {/* Remarks Layer (comment merged with its highlight) */}
                              {comments
                                .filter((c) => Number(c.page_number) === pageNum)
                                .map((comment) => {
                                  const bgColor =
                                    HIGHLIGHT_COLORS[comment.color] || HIGHLIGHT_COLORS.yellow;

                                  return (
                                    <div key={comment.id}>
                                      {/* Highlight for selected text, colored by the remark */}
                                      {comment.highlightRects &&
                                        comment.highlightRects.map((rect, idx) => (
                                          <div
                                            key={`${comment.id}-highlight-${idx}`}
                                            style={{
                                              position: "absolute",
                                              top: `${rect.top_position * 100}%`,
                                              left: `${rect.left_position * 100}%`,
                                              width: `${rect.highlight_width * 100}%`,
                                              height: `${rect.highlight_height * 100}%`,
                                              backgroundColor: bgColor,
                                              pointerEvents: "none",
                                              zIndex: 10,
                                            }}
                                          />
                                        ))}

                                      {/* Comment marker on the page at its true position */}
                                      <div
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          setOpenCommentId(
                                            openCommentId === comment.id ? null : comment.id
                                          );
                                        }}
                                        style={{
                                          position: "absolute",
                                          top: `${comment.y_position * 100}%`,
                                          right: "20px",
                                          transform: "translateY(-50%)",
                                          zIndex: 15,
                                          cursor: "pointer",
                                        }}
                                      >
                                        <div
                                          style={{
                                            ...styles.commentIcon,
                                            backgroundColor:
                                              openCommentId === comment.id
                                                ? "#3b82f6"
                                                : comment.resolved
                                                ? "#9ca3af"
                                                : "#fef08a",
                                            border:
                                              openCommentId === comment.id
                                                ? "2px solid #3b82f6"
                                                : "2px solid #eab308",
                                          }}
                                        >
                                          <span style={{
                                            color:
                                              openCommentId === comment.id
                                                ? "#ffffff"
                                                : "#854d0e",
                                            fontWeight: "bold",
                                            fontSize: "12px",
                                          }}>
                                            C
                                          </span>
                                        </div>
                                      </div>
                                    </div>
                                  );
                                })}

                              {/* Selection Comment Icon */}
                              {selectionCommentIcon && selectionCommentIcon.pageNumber === pageNum && (
                                <div
                                  style={{
                                    position: "absolute",
                                    left: selectionCommentIcon.x,
                                    top: selectionCommentIcon.y,
                                    transform: "translate(0, -50%)",
                                    zIndex: 100,
                                  }}
                                >
                                  <button
                                    onClick={handleSelectionComment}
                                    style={styles.selectionCommentButton}
                                    title="Add comment"
                                  >
                                    💬
                                  </button>
                                </div>
                              )}

                              {/* Open comment card - only the selected remark is shown,
                                  so cards can never overlap each other */}
                              {comments
                                .filter(
                                  (c) =>
                                    Number(c.page_number) === pageNum &&
                                    c.id === openCommentId
                                )
                                .map((comment) => (
                                  <div
                                    key={comment.id}
                                    style={{
                                      position: "absolute",
                                      top: `${comment.y_position * 100}%`,
                                      right: "20px",
                                      zIndex: 100,
                                    }}
                                  >
                                    <div
                                      style={{
                                        position: "absolute",
                                        top: 0,
                                        left: "100%",
                                        marginLeft: "35px",
                                        width: "250px",
                                      }}
                                    >
                                      <CommentCard
                                        comment={comment}
                                        onDelete={(id) => {
                                          deleteComment(id);
                                          setOpenCommentId(null);
                                        }}
                                      />
                                    </div>
                                  </div>
                                ))}

                              {/* Draft highlight - show the selected text highlighted
                                  while composing, since the native selection is cleared on click */}
                              {draft && draft.pageNumber === pageNum && draft.highlightRects &&
                                draft.highlightRects.map((rect, idx) => (
                                  <div
                                    key={`draft-hl-${idx}`}
                                    style={{
                                      position: "absolute",
                                      top: `${rect.top_position * 100}%`,
                                      left: `${rect.left_position * 100}%`,
                                      width: `${rect.highlight_width * 100}%`,
                                      height: `${rect.highlight_height * 100}%`,
                                      backgroundColor:
                                        HIGHLIGHT_COLORS[selectedHighlightColor] ||
                                        HIGHLIGHT_COLORS.yellow,
                                      pointerEvents: "none",
                                      zIndex: 10,
                                    }}
                                  />
                                ))}

                              {/* Draft card - shown while composing a comment */}
                              {draft && draft.pageNumber === pageNum && (
                                <div
                                  style={{
                                    position: "absolute",
                                    top: `${draft.y * 100}%`,
                                    right: "20px",
                                    zIndex: 120,
                                  }}
                                >
                                  <div
                                    style={{
                                      position: "absolute",
                                      top: 0,
                                      left: "100%",
                                      marginLeft: "35px",
                                      width: "260px",
                                    }}
                                  >
                                    <DraftCard
                                      draft={draft}
                                      text={draftText}
                                      setText={setDraftText}
                                      onSubmit={submitDraft}
                                      onCancel={cancelDraft}
                                    />
                                  </div>
                                </div>
                              )}
                            </div>

                            {/* Page Number Label */}
                            <div style={styles.pageLabel}>
                              Page {pageNum} of {numPages}
                            </div>
                          </div>
                        </div>
                      );
                    })}
                </Document>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function CommentCard({ comment, onDelete }) {
  return (
    <div style={styles.commentCard}>
      <div style={styles.cardHeader}>
        <span style={styles.pageBadge}>Page {comment.page_number}</span>
        <button onClick={() => onDelete(comment.id)} style={styles.deleteButton}>
          ×
        </button>
      </div>
      {comment.selectedText && (
        <p style={styles.selectedText}>"{comment.selectedText}"</p>
      )}
      <p style={styles.commentText}>{comment.text}</p>
      <div style={styles.cardFooter}>
        <span style={styles.author}>{comment.author}</span>
      </div>
    </div>
  );
}

function DraftCard({ draft, text, setText, onSubmit, onCancel }) {
  return (
    <div style={styles.draftCard}>
      {draft.selectedText && (
        <p style={styles.draftSelected}>"{draft.selectedText}"</p>
      )}
      <textarea
        autoFocus
        style={styles.draftTextarea}
        placeholder="Add a comment..."
        value={text}
        onChange={(e) => setText(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            onSubmit();
          }
          if (e.key === "Escape") onCancel();
        }}
      />
      <div style={styles.draftActions}>
        <button onClick={onCancel} style={styles.draftCancelButton}>
          Cancel
        </button>
        <button onClick={onSubmit} style={styles.draftSaveButton}>
          Comment
        </button>
      </div>
    </div>
  );
}

const styles = {
  container: {
    display: "flex",
    minHeight: "100vh",
    backgroundColor: "#e5e7eb",
  },
  mainContent: {
    flex: 1,
    display: "flex",
    flexDirection: "column",
    transition: "margin-right 0.3s ease",
  },
  toolbar: {
    display: "flex",
    alignItems: "center",
    justifyContent: "space-between",
    padding: "12px 20px",
    backgroundColor: "#ffffff",
    borderBottom: "1px solid #d1d5db",
    boxShadow: "0 2px 4px rgba(0, 0, 0, 0.05)",
    position: "sticky",
    top: 0,
    zIndex: 100,
  },
  toolbarButton: {
    padding: "8px 16px",
    borderRadius: "6px",
    border: "1px solid #d1d5db",
    backgroundColor: "#f9fafb",
    cursor: "pointer",
    fontSize: "14px",
    fontWeight: "500",
  },
  zoomControls: {
    display: "flex",
    alignItems: "center",
    gap: "8px",
  },
  zoomButton: {
    width: "32px",
    height: "32px",
    borderRadius: "6px",
    border: "1px solid #d1d5db",
    backgroundColor: "#ffffff",
    cursor: "pointer",
    fontSize: "18px",
    fontWeight: "bold",
  },
  zoomText: {
    fontSize: "14px",
    fontWeight: "500",
    minWidth: "50px",
    textAlign: "center",
  },
  resetButton: {
    padding: "6px 12px",
    borderRadius: "6px",
    border: "1px solid #d1d5db",
    backgroundColor: "#ffffff",
    cursor: "pointer",
    fontSize: "12px",
  },
  pageControls: {
    display: "flex",
    alignItems: "center",
    gap: "12px",
  },
  pageButton: {
    padding: "6px 12px",
    borderRadius: "6px",
    border: "1px solid #d1d5db",
    backgroundColor: "#ffffff",
    cursor: "pointer",
    fontSize: "13px",
  },
  pageText: {
    fontSize: "14px",
    fontWeight: "500",
  },
  pdfArea: {
    flex: 1,
    overflow: "auto",
    padding: "20px",
  },
  documentContainer: {
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
  },
  pageContainer: {
    marginBottom: "20px",
    backgroundColor: "#ffffff",
    boxShadow: "0 4px 12px rgba(0, 0, 0, 0.15)",
    borderRadius: "4px",
    overflow: "visible",
  },
  pageWrapper: {
    position: "relative",
  },
  pageLabel: {
    textAlign: "center",
    padding: "8px",
    backgroundColor: "#f3f4f6",
    fontSize: "12px",
    color: "#6b7280",
    borderTop: "1px solid #e5e7eb",
  },
  errorContainer: {
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
    justifyContent: "center",
    height: "400px",
    backgroundColor: "#fef2f2",
    borderRadius: "8px",
    padding: "40px",
  },
  errorText: {
    color: "#dc2626",
    fontSize: "18px",
    fontWeight: "600",
    marginBottom: "8px",
  },
  errorHint: {
    color: "#7f1d1d",
    fontSize: "14px",
  },
  loadingContainer: {
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
    justifyContent: "center",
    height: "400px",
  },
  spinner: {
    width: "40px",
    height: "40px",
    border: "4px solid #e5e7eb",
    borderTop: "4px solid #3b82f6",
    borderRadius: "50%",
    animation: "spin 1s linear infinite",
  },
  commentIcon: {
    width: "28px",
    height: "28px",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
    borderRadius: "4px",
    cursor: "pointer",
    boxShadow: "0 2px 4px rgba(0, 0, 0, 0.2)",
  },
  commentCard: {
    width: "250px",
    backgroundColor: "#ffffff",
    borderRadius: "8px",
    boxShadow: "0 4px 12px rgba(0, 0, 0, 0.15)",
    border: "1px solid #e5e7eb",
    padding: "12px",
    pointerEvents: "auto",
  },
  cardHeader: {
    display: "flex",
    justifyContent: "space-between",
    alignItems: "center",
    marginBottom: "8px",
  },
  pageBadge: {
    fontSize: "11px",
    backgroundColor: "#dbeafe",
    color: "#1d4ed8",
    padding: "2px 8px",
    borderRadius: "12px",
    fontWeight: "500",
  },
  deleteButton: {
    width: "20px",
    height: "20px",
    borderRadius: "4px",
    border: "none",
    backgroundColor: "#fee2e2",
    color: "#dc2626",
    cursor: "pointer",
    fontSize: "14px",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
  },
  commentText: {
    fontSize: "13px",
    color: "#374151",
    margin: "0 0 8px 0",
    lineHeight: "1.4",
  },
  selectedText: {
    fontSize: "12px",
    fontStyle: "italic",
    color: "#6b7280",
    margin: "0 0 8px 0",
    lineHeight: "1.4",
  },
  cardFooter: {
    display: "flex",
    justifyContent: "space-between",
    fontSize: "11px",
    color: "#9ca3af",
  },
  author: {
    fontWeight: "500",
  },
  selectionCommentButton: {
    width: "32px",
    height: "32px",
    borderRadius: "50%",
    border: "none",
    backgroundColor: "#3b82f6",
    cursor: "pointer",
    fontSize: "16px",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
    boxShadow: "0 2px 8px rgba(59, 130, 246, 0.5)",
    transition: "transform 0.2s, box-shadow 0.2s",
  },
  draftCard: {
    width: "260px",
    backgroundColor: "#ffffff",
    borderRadius: "8px",
    boxShadow: "0 4px 12px rgba(0, 0, 0, 0.2)",
    border: "1px solid #3b82f6",
    padding: "12px",
    zIndex: 120,
    pointerEvents: "auto",
  },
  draftSelected: {
    fontSize: "12px",
    fontStyle: "italic",
    color: "#6b7280",
    margin: "0 0 8px 0",
    lineHeight: "1.4",
    maxHeight: "60px",
    overflow: "auto",
  },
  draftTextarea: {
    width: "100%",
    padding: "8px",
    borderRadius: "4px",
    border: "1px solid #d1d5db",
    fontSize: "13px",
    resize: "vertical",
    minHeight: "70px",
    boxSizing: "border-box",
  },
  draftActions: {
    display: "flex",
    justifyContent: "flex-end",
    gap: "8px",
    marginTop: "8px",
  },
  draftCancelButton: {
    padding: "6px 12px",
    borderRadius: "4px",
    border: "1px solid #d1d5db",
    backgroundColor: "#ffffff",
    cursor: "pointer",
    fontSize: "12px",
    color: "#374151",
    fontWeight: "500",
  },
  draftSaveButton: {
    padding: "6px 14px",
    borderRadius: "4px",
    border: "none",
    backgroundColor: "#2563eb",
    color: "#ffffff",
    cursor: "pointer",
    fontSize: "12px",
    fontWeight: "600",
  },
};

export default PDFChecker;


