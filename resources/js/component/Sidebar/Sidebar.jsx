import React, { useRef, useEffect } from "react";

function Sidebar({
  isOpen,
  toggleSidebar,
  comments,
  setComments,
  numPages,
  currentPage,
  setCurrentPage,
  selectedCommentId,
  onSelectComment,
}) {
  const deleteComment = (id) => {
    setComments((prev) => prev.filter((c) => c.id !== id));
  };

  const clearAllAnnotations = () => {
    if (window.confirm("Are you sure you want to clear all annotations?")) {
      setComments([]);
    }
  };

  // Scroll the selected comment into view when it changes
  const cardRefs = useRef({});
  useEffect(() => {
    if (selectedCommentId && cardRefs.current[selectedCommentId]) {
      cardRefs.current[selectedCommentId].scrollIntoView({
        behavior: "smooth",
        block: "nearest",
      });
    }
  }, [selectedCommentId]);

  if (!isOpen) return null;

  return (
    <div style={styles.sidebar}>
      {/* Header */}
      <div style={styles.header}>
        <h2 style={styles.title}>Remarks</h2>
        <button onClick={toggleSidebar} style={styles.closeButton}>×</button>
      </div>

      {/* Summary */}
      <div style={styles.summary}>
        <div style={styles.summaryItem}>
          <span style={styles.summaryNumber}>{comments.length}</span>
          <span style={styles.summaryLabel}>Comments</span>
        </div>
      </div>

      {/* Content */}
      <div style={styles.content}>
        <div style={styles.tabContent}>
          {comments.length === 0 ? (
            <p style={styles.emptyText}>
              No comments yet. Select text on the PDF and add a comment.
            </p>
          ) : (
            comments.map((comment) => (
              <div
                key={comment.id}
                ref={(el) => (cardRefs.current[comment.id] = el)}
                onClick={() => onSelectComment(comment.id)}
                style={{
                  ...styles.annotationCard,
                  borderLeft: `3px solid ${comment.color || "#eab308"}`,
                  cursor: "pointer",
                  outline:
                    selectedCommentId === comment.id
                      ? "2px solid #3b82f6"
                      : "none",
                  boxShadow:
                    selectedCommentId === comment.id
                      ? "0 0 0 2px rgba(59, 130, 246, 0.25)"
                      : "none",
                }}
              >
                <div style={styles.cardHeader}>
                  <div>
                    <span style={styles.pageBadge}>Page {comment.page_number}</span>
                  </div>
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      deleteComment(comment.id);
                    }}
                    style={styles.deleteButton}
                  >
                    ×
                  </button>
                </div>

                {comment.selectedText && (
                  <p style={styles.selectedText}>"{comment.selectedText}"</p>
                )}

                <p style={styles.commentText}>{comment.text}</p>
                <div style={styles.cardFooter}>
                  <span style={styles.author}>{comment.author}</span>
                  <span style={styles.timestamp}>
                    {new Date(comment.timestamp).toLocaleString()}
                  </span>
                </div>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Footer Actions */}
      <div style={styles.footer}>
        <button onClick={clearAllAnnotations} style={styles.clearAllButton}>
          Clear All Annotations
        </button>
      </div>
    </div>
  );
}

const styles = {
  sidebar: {
    width: "280px",
    backgroundColor: "#ffffff",
    borderLeft: "1px solid #e5e7eb",
    display: "flex",
    flexDirection: "column",
    height: "100vh",
    position: "fixed",
    right: 0,
    top: 0,
    zIndex: 200,
    boxShadow: "-2px 0 8px rgba(0, 0, 0, 0.1)",
  },
  header: {
    display: "flex",
    justifyContent: "space-between",
    alignItems: "center",
    padding: "16px",
    borderBottom: "1px solid #e5e7eb",
    backgroundColor: "#f9fafb",
  },
  title: {
    fontSize: "18px",
    fontWeight: "600",
    color: "#111827",
    margin: 0,
  },
  closeButton: {
    width: "28px",
    height: "28px",
    borderRadius: "6px",
    border: "none",
    backgroundColor: "#e5e7eb",
    cursor: "pointer",
    fontSize: "18px",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
  },
  summary: {
    display: "grid",
    gridTemplateColumns: "repeat(1, 1fr)",
    padding: "12px",
    borderBottom: "1px solid #e5e7eb",
    backgroundColor: "#f9fafb",
  },
  summaryItem: {
    display: "flex",
    flexDirection: "column",
    alignItems: "center",
  },
  summaryNumber: {
    fontSize: "18px",
    fontWeight: "600",
    color: "#3b82f6",
  },
  summaryLabel: {
    fontSize: "10px",
    color: "#6b7280",
    textTransform: "uppercase",
  },
  content: {
    flex: 1,
    overflow: "auto",
    padding: "12px",
  },
  tabContent: {
    display: "flex",
    flexDirection: "column",
    gap: "12px",
  },
  emptyText: {
    textAlign: "center",
    color: "#9ca3af",
    fontSize: "13px",
    padding: "20px",
  },
  annotationCard: {
    backgroundColor: "#f9fafb",
    borderRadius: "8px",
    padding: "12px",
    border: "1px solid #e5e7eb",
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
    lineHeight: "1.5",
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
    marginBottom: "8px",
  },
  author: {
    fontWeight: "500",
  },
  timestamp: {
    fontStyle: "italic",
  },
  goToButton: {
    width: "100%",
    padding: "6px",
    borderRadius: "4px",
    border: "1px solid #d1d5db",
    backgroundColor: "#ffffff",
    cursor: "pointer",
    fontSize: "12px",
    color: "#6b7280",
  },
  footer: {
    padding: "12px",
    borderTop: "1px solid #e5e7eb",
    backgroundColor: "#f9fafb",
  },
  clearAllButton: {
    width: "100%",
    padding: "10px",
    borderRadius: "6px",
    border: "1px solid #fca5a5",
    backgroundColor: "#fef2f2",
    color: "#dc2626",
    cursor: "pointer",
    fontSize: "13px",
    fontWeight: "500",
  },
};

export default Sidebar;
