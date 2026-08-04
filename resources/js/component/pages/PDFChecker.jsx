import React, { useState, useEffect, useRef } from "react";
import { Document, Page, pdfjs } from "react-pdf";
import "react-pdf/dist/Page/AnnotationLayer.css";
import "react-pdf/dist/Page/TextLayer.css";
import Sidebar from "../Sidebar/Sidebar";

pdfjs.GlobalWorkerOptions.workerSrc = new URL(
  "pdfjs-dist/build/pdf.worker.min.mjs",
  import.meta.url
).toString();

const HIGHLIGHT_COLORS = {
  yellow: "rgba(255, 255, 0, 0.25)",
  green: "rgba(0, 255, 0, 0.25)",
  blue: "rgba(0, 191, 255, 0.25)",
  pink: "rgba(255, 0, 0, 0.25)",
  orange: "rgba(255, 165, 0, 0.25)",
};

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
  const [draft, setDraft] = useState(null);
  const [draftText, setDraftText] = useState("");
  const [draftSection, setDraftSection] = useState("");

  // ── NEW: general (paper-level) comments, not tied to a page/paragraph ──
  const [generalComments, setGeneralComments] = useState([]);
  const [reviewId, setReviewId] = useState(null);

  const pageDimensions = { width: 816, height: 1056 };
  const containerRef = useRef(null);
  const isProcessingComment = useRef(false);
  const pdfAreaRef = useRef(null);
  const scrollTicking = useRef(false);
  const [visiblePages, setVisiblePages] = useState(() => new Set([1, 2, 3, 4, 5]));

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const fileID = params.get("fileID") || params.get("reviewId");
    if (fileID) {
      setPdfUrl({ url: `/reviews/${fileID}/pdf` });
    } else {
      setPdfUrl("/test.pdf");
    }
  }, []);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const id = params.get("reviewId") || params.get("fileID");
    if (!id) return;
    setReviewId(id);

    fetch(`/reviews/${id}/remarks`)
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
          selectedText: r.selected_text,
          section: r.section,
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

    // NEW: load general (paper-level) comments separately
    fetch(`/reviews/${id}/general-remarks`)
      .then((r) => r.json())
      .then((data) => {
        const mapped = data.map((r) => ({
          id: r.id,
          text: r.comment,
          section: r.section,
          author: "Reviewer",
          timestamp: r.created_at,
        }));
        setGeneralComments(mapped);
      })
      .catch((e) => console.error("Failed to load general remarks:", e));
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
        const endPage = Math.min(
          numPages,
          Math.ceil((scrollTop + viewportHeight) / pageHeight) + 2
        );

        const newVisible = new Set();
        for (let i = startPage; i <= endPage; i++) {
          newVisible.add(i);
        }

        setVisiblePages((prev) => {
          if (
            prev.size === newVisible.size &&
            [...prev].every((p) => newVisible.has(p))
          ) {
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

  // ── Get global comment number (1-based, across all pages) ──────────
  const getCommentNumber = (commentId) => {
    const index = comments.findIndex((c) => c.id === commentId);
    return index === -1 ? "?" : index + 1;
  };

  const addRemark = ({ pageNumber, x, y, text, selectedText, highlightRects, section }) => {
    const newComment = {
      id: Date.now(),
      page_number: pageNumber,
      x_position: x,
      y_position: y,
      text,
      author: "Reviewer",
      timestamp: new Date().toISOString(),
      selectedText: selectedText,
      section: section || null,
      highlightRects: highlightRects,
      color: selectedHighlightColor,
      resolved: false,
      replies: [],
    };

    setComments((prev) => [...prev, newComment]);

    if (reviewId) {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
      fetch(`/reviews/${reviewId}/remarks`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
          Accept: "application/json",
        },
        body: JSON.stringify({
          page_number: pageNumber,
          top_position: y,
          left_position: x,
          comment: text,
          selected_text: selectedText || null,
          section: section || null,
          highlight_width: highlightRects?.[0]?.highlight_width ?? null,
          highlight_height: highlightRects?.[0]?.highlight_height ?? null,
          color: selectedHighlightColor,
        }),
      }).catch((e) => console.error("Failed to save remark:", e));
    }
  };

  // ── NEW: add a general, paper-level comment (no page/paragraph anchor) ──
  const addGeneralComment = (text, section) => {
    const newComment = {
      id: Date.now(),
      text,
      section: section || null,
      author: "Reviewer",
      timestamp: new Date().toISOString(),
    };

    setGeneralComments((prev) => [...prev, newComment]);

    if (reviewId) {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
      fetch(`/reviews/${reviewId}/general-remarks`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
          Accept: "application/json",
        },
        body: JSON.stringify({ comment: text, section: section || null }),
      }).catch((e) => console.error("Failed to save general remark:", e));
    }
  };

  const deleteGeneralComment = (id) => {
    setGeneralComments((prev) => prev.filter((c) => c.id !== id));
    if (reviewId) {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
      fetch(`/reviews/${reviewId}/general-remarks/${id}`, {
        method: "DELETE",
        headers: { "X-CSRF-TOKEN": csrfToken, Accept: "application/json" },
      }).catch((e) => console.error("Failed to delete general remark:", e));
    }
  };

  const deleteComment = (id) => {
    setComments((prev) => prev.filter((c) => c.id !== id));
    if (reviewId) {
      fetch(`/reviews/${reviewId}/remarks/${id}`, {
        method: "DELETE",
      }).catch((e) => console.error("Failed to delete remark:", e));
    }
  };

  const handlePageClick = (e, pageNumber) => {
    // Existing anchored-comment tool: requires a text selection
    if (activeTool === "comment") {
      const pageEl = e.currentTarget;
      const rect = pageEl.getBoundingClientRect();
      const x = (e.clientX - rect.left) / rect.width;
      const y = (e.clientY - rect.top) / rect.height;

      const selection = window.getSelection();
      const selectedText = selection?.toString().trim();

      if (!selectedText) return;

      const range = selection.getRangeAt(0);
      const rects = Array.from(range.getClientRects());
      const pageRect = pageEl.getBoundingClientRect();

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
      return;
    }

    if (openCommentId) setOpenCommentId(null);
  };

  const zoomIn = () => setScale((prev) => Math.min(prev + 0.2, 2.0));
  const zoomOut = () => setScale((prev) => Math.max(prev - 0.2, 0.5));
  const resetZoom = () => setScale(1.0);

  useEffect(() => {
    const handleSelection = () => {
      if (isProcessingComment.current || activeTool === "comment") return;

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

      const lastRect = rects[rects.length - 1];
      const pageRect = pageEl.getBoundingClientRect();

      setSelectionCommentIcon({
        pageNumber,
        x: lastRect.right - pageRect.left,
        y: lastRect.top - pageRect.top,
        selectedText: selection.toString(),
      });
    };

    const handleMouseUp = (e) => {
      if (isProcessingComment.current) return;
      setTimeout(handleSelection, 10);
    };

    document.addEventListener("mouseup", handleMouseUp);
    return () => document.removeEventListener("mouseup", handleMouseUp);
  }, [activeTool]);

  const handleSelectionComment = (e) => {
    e.preventDefault();
    e.stopPropagation();

    if (!selectionCommentIcon || isProcessingComment.current) return;

    isProcessingComment.current = true;
    const savedSelection = { ...selectionCommentIcon };

    setSelectionCommentIcon(null);

    let highlightRects = [];
    const pageEl = document.querySelector(
      `.react-pdf__Page[data-page-number="${savedSelection.pageNumber}"]`
    );
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
    setTimeout(() => {
      isProcessingComment.current = false;
    }, 500);
  };

  const submitDraft = () => {
    if (!draft || !draftText.trim()) return;
    addRemark({
      pageNumber: draft.pageNumber,
      x: draft.x,
      y: draft.y,
      text: draftText.trim(),
      selectedText: draft.selectedText,
      highlightRects: draft.highlightRects,
      section: draftSection.trim(),
    });
    setDraft(null);
    setDraftText("");
    setDraftSection("");
  };

  const cancelDraft = () => {
    setDraft(null);
    setDraftText("");
    setDraftSection("");
  };

  return (
    <div style={styles.container}>
      <Sidebar
        isOpen={sidebarOpen}
        toggleSidebar={() => setSidebarOpen(!sidebarOpen)}
        comments={comments}
        setComments={setComments}
        onDeleteComment={deleteComment}
        numPages={numPages}
        currentPage={currentPage}
        setCurrentPage={setCurrentPage}
        selectedCommentId={openCommentId}
        onSelectComment={setOpenCommentId}
        // NEW: general (paper-level) comments
        generalComments={generalComments}
        onAddGeneralComment={addGeneralComment}
        onDeleteGeneralComment={deleteGeneralComment}
      />

      <div
        ref={containerRef}
        style={{
          ...styles.mainContent,
          marginRight: sidebarOpen ? "280px" : "0",
        }}
      >
        <div style={styles.toolbar}>
          <div style={styles.zoomControls}>
            <button onClick={zoomOut} style={styles.zoomButton}>−</button>
            <span style={styles.zoomText}>{Math.round(scale * 100)}%</span>
            <button onClick={zoomIn} style={styles.zoomButton}>+</button>
            <button onClick={resetZoom} style={styles.resetButton}>Reset</button>
          </div>

          <button
            onClick={() => setSidebarOpen(!sidebarOpen)}
            style={styles.toolbarButton}
          >
            {sidebarOpen ? "Hide Sidebar ▶" : "◀ Show Sidebar"}
          </button>
        </div>

        <div style={{ display: "flex", flex: 1, overflow: "hidden" }}>
          <div style={styles.pdfArea} ref={pdfAreaRef}>
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
                          style={{
                            display: "flex",
                            flexDirection: "row",
                            gap: "20px",
                            marginBottom: "20px",
                          }}
                        >
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

                              {/* Remarks Layer */}
                              {comments
                                .filter((c) => Number(c.page_number) === pageNum)
                                .map((comment) => {
                                  const bgColor =
                                    HIGHLIGHT_COLORS[comment.color] ||
                                    HIGHLIGHT_COLORS.yellow;
                                  const commentNumber = getCommentNumber(comment.id);

                                  return (
                                    <div key={comment.id}>
                                      {/* Highlight */}
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

                                      {/* Comment marker — shows #number */}
                                      <div
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          setOpenCommentId(
                                            openCommentId === comment.id
                                              ? null
                                              : comment.id
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
                                          <span
                                            style={{
                                              color:
                                                openCommentId === comment.id
                                                  ? "#ffffff"
                                                  : "#854d0e",
                                              fontWeight: "bold",
                                              fontSize: "11px",
                                            }}
                                          >
                                            #{commentNumber}
                                          </span>
                                        </div>
                                      </div>
                                    </div>
                                  );
                                })}

                              {/* Selection Comment Icon */}
                              {selectionCommentIcon &&
                                selectionCommentIcon.pageNumber === pageNum && (
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

                              {/* Open comment card */}
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
                                        commentNumber={getCommentNumber(comment.id)}
                                        onDelete={(id) => {
                                          deleteComment(id);
                                          setOpenCommentId(null);
                                        }}
                                      />
                                    </div>
                                  </div>
                                ))}

                              {/* Draft highlight */}
                              {draft &&
                                draft.pageNumber === pageNum &&
                                draft.highlightRects &&
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

                              {/* Draft card */}
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
                                      section={draftSection}
                                      setSection={setDraftSection}
                                      onSubmit={submitDraft}
                                      onCancel={cancelDraft}
                                      commentNumber={comments.length + 1}
                                    />
                                  </div>
                                </div>
                              )}
                            </div>

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

function CommentCard({ comment, commentNumber, onDelete }) {
  return (
    <div style={styles.commentCard}>
      <div style={styles.cardHeader}>
        <span style={styles.pageBadge}>
          #{commentNumber} — Page {comment.page_number}
        </span>
        <button onClick={() => onDelete(comment.id)} style={styles.deleteButton}>
          ×
        </button>
      </div>
      {comment.section && (
        <p style={styles.sectionBadge}>{comment.section}</p>
      )}
      {comment.selectedText && (
        <p style={styles.selectedText}>
          &ldquo;
          {comment.selectedText.length > 60
            ? comment.selectedText.substring(0, 60) + "..."
            : comment.selectedText}
          &rdquo;
        </p>
      )}
      <p style={styles.commentText}>{comment.text}</p>
      <div style={styles.cardFooter}>
        <span style={styles.author}>{comment.author}</span>
      </div>
    </div>
  );
}

function DraftCard({ draft, text, setText, section, setSection, onSubmit, onCancel, commentNumber }) {
  return (
    <div style={styles.draftCard}>
      <div style={{ fontSize: "11px", color: "#6b7280", marginBottom: "4px", fontWeight: "600" }}>
        Comment #{commentNumber} — Page {draft.pageNumber}
      </div>
      {draft.selectedText && (
        <p style={styles.draftSelected}>
          &ldquo;
          {draft.selectedText.length > 60
            ? draft.selectedText.substring(0, 60) + "..."
            : draft.selectedText}
          &rdquo;
        </p>
      )}
      <input
        type="text"
        style={styles.draftSectionInput}
        placeholder="Section (e.g. 3.1.2 Research Instruments)"
        value={section}
        onChange={(e) => setSection(e.target.value)}
      />
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
    height: "100vh",
    width: "100%",
    overflow: "hidden",
    background: "#f3f4f6",
  },
  mainContent: {
    display: "flex",
    flexDirection: "column",
    flex: 1,
    height: "100%",
    transition: "margin-right 0.2s ease",
  },
  toolbar: {
    display: "flex",
    alignItems: "center",
    justifyContent: "space-between",
    padding: "8px 16px",
    background: "#ffffff",
    borderBottom: "1px solid #e5e7eb",
  },
  zoomControls: {
    display: "flex",
    alignItems: "center",
    gap: "8px",
  },
  zoomButton: {
    width: "28px",
    height: "28px",
    borderRadius: "6px",
    border: "1px solid #d1d5db",
    background: "#fff",
    cursor: "pointer",
    fontSize: "14px",
  },
  zoomText: {
    fontSize: "13px",
    color: "#374151",
    minWidth: "40px",
    textAlign: "center",
  },
  resetButton: {
    padding: "4px 10px",
    borderRadius: "6px",
    border: "1px solid #d1d5db",
    background: "#fff",
    cursor: "pointer",
    fontSize: "12px",
  },
  toolbarButton: {
    padding: "6px 12px",
    borderRadius: "6px",
    border: "1px solid #d1d5db",
    background: "#fff",
    cursor: "pointer",
    fontSize: "12px",
  },
  pdfArea: {
    flex: 1,
    overflowY: "auto",
    padding: "20px",
    display: "flex",
    justifyContent: "center",
  },
  documentContainer: {
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
  },
  pageContainer: {
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
  },
  pageWrapper: {
    position: "relative",
    background: "#fff",
    boxShadow: "0 1px 4px rgba(0,0,0,0.15)",
  },
  pageLabel: {
    fontSize: "11px",
    color: "#6b7280",
    marginTop: "6px",
  },
  loadingContainer: {
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
    gap: "8px",
    padding: "40px",
    color: "#6b7280",
  },
  spinner: {
    width: "24px",
    height: "24px",
    border: "3px solid #e5e7eb",
    borderTopColor: "#3b82f6",
    borderRadius: "50%",
    animation: "spin 0.8s linear infinite",
  },
  errorContainer: {
    padding: "20px",
    textAlign: "center",
    color: "#b91c1c",
  },
  errorText: { fontWeight: "600", margin: 0 },
  errorHint: { fontSize: "12px", color: "#6b7280", margin: "4px 0 0" },
  commentIcon: {
    width: "22px",
    height: "22px",
    borderRadius: "50%",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
    boxShadow: "0 1px 3px rgba(0,0,0,0.3)",
  },
  selectionCommentButton: {
    width: "28px",
    height: "28px",
    borderRadius: "50%",
    border: "none",
    background: "#fff",
    boxShadow: "0 1px 4px rgba(0,0,0,0.3)",
    cursor: "pointer",
    fontSize: "14px",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
  },
  commentCard: {
    background: "#fff",
    borderRadius: "8px",
    boxShadow: "0 2px 10px rgba(0,0,0,0.2)",
    padding: "10px",
    fontSize: "13px",
  },
  cardHeader: {
    display: "flex",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: "6px",
  },
  pageBadge: {
    fontSize: "11px",
    fontWeight: "600",
    color: "#374151",
  },
  deleteButton: {
    border: "none",
    background: "none",
    cursor: "pointer",
    fontSize: "16px",
    color: "#9ca3af",
    lineHeight: 1,
  },
  selectedText: {
    fontSize: "11px",
    fontStyle: "italic",
    color: "#6b7280",
    margin: "0 0 6px",
  },
  sectionBadge: {
    display: "inline-block",
    fontSize: "10px",
    fontWeight: "700",
    color: "#1d4ed8",
    background: "#dbeafe",
    borderRadius: "4px",
    padding: "2px 6px",
    margin: "0 0 6px",
  },
  commentText: {
    margin: 0,
    color: "#111827",
  },
  cardFooter: {
    marginTop: "6px",
  },
  author: {
    fontSize: "10px",
    color: "#9ca3af",
  },
  draftCard: {
    background: "#fff",
    borderRadius: "8px",
    boxShadow: "0 2px 10px rgba(0,0,0,0.25)",
    padding: "10px",
    width: "100%",
    boxSizing: "border-box",
  },
  draftSelected: {
    fontSize: "11px",
    fontStyle: "italic",
    color: "#6b7280",
    margin: "0 0 6px",
  },
  draftSectionInput: {
    width: "100%",
    fontSize: "12px",
    border: "1px solid #d1d5db",
    borderRadius: "6px",
    padding: "6px",
    boxSizing: "border-box",
    fontFamily: "inherit",
    marginBottom: "6px",
  },
  draftTextarea: {
    width: "100%",
    minHeight: "60px",
    fontSize: "13px",
    border: "1px solid #d1d5db",
    borderRadius: "6px",
    padding: "6px",
    boxSizing: "border-box",
    resize: "vertical",
    fontFamily: "inherit",
  },
  draftActions: {
    display: "flex",
    justifyContent: "flex-end",
    gap: "6px",
    marginTop: "6px",
  },
  draftCancelButton: {
    padding: "5px 10px",
    borderRadius: "6px",
    border: "1px solid #d1d5db",
    background: "#fff",
    cursor: "pointer",
    fontSize: "12px",
  },
  draftSaveButton: {
    padding: "5px 10px",
    borderRadius: "6px",
    border: "none",
    background: "#3b82f6",
    color: "#fff",
    cursor: "pointer",
    fontSize: "12px",
  },
};

export default PDFChecker;