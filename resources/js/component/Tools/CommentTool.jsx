import { useEffect, useCallback } from "react";

/**
 * useComment - Custom hook for adding comments to PDF pages
 * 
 * @param {Function} setComments - State setter for comments array
 * @param {boolean} commentMode - Whether comment mode is active
 * @param {string} author - Author name for comments (default: "Reviewer")
 * 
 * Usage:
 *   useComment(setComments, commentMode, "John Doe");
 */
export default function useComment(setComments, commentMode, author = "Reviewer") {
  const handlePageClick = useCallback((e) => {
    if (!commentMode) return;

    // Find the PDF page element
    const pageEl = e.target.closest(".react-pdf__Page");
    if (!pageEl) return;

    // Get page number
    const pageNumber = Number(pageEl.getAttribute("data-page-number"));
    if (!pageNumber) return;

    // Calculate relative position
    const rect = pageEl.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;

    // Prompt for comment text
    const commentText = window.prompt("Enter your comment:");
    if (!commentText || commentText.trim() === "") return;

    // Create new comment
    const newComment = {
      id: Date.now() + Math.random(),
      page_number: pageNumber,
      x_position: x,
      y_position: y,
      text: commentText.trim(),
      author: author,
      timestamp: new Date().toISOString(),
      resolved: false,
    };

    setComments((prev) => [...prev, newComment]);
  }, [commentMode, author, setComments]);

  useEffect(() => {
    if (!commentMode) return;

    // Add click listener to document
    document.addEventListener("click", handlePageClick);

    // Change cursor to indicate comment mode
    document.body.style.cursor = "crosshair";

    return () => {
      document.removeEventListener("click", handlePageClick);
      document.body.style.cursor = "default";
    };
  }, [commentMode, handlePageClick]);
}

/**
 * Helper function to toggle comment resolved status
 * @param {Array} comments - Current comments array
 * @param {Function} setComments - State setter
 * @param {number} commentId - ID of comment to toggle
 */
export function toggleCommentResolved(comments, setComments, commentId) {
  setComments((prev) =>
    prev.map((c) =>
      c.id === commentId ? { ...c, resolved: !c.resolved } : c
    )
  );
}

/**
 * Helper function to edit comment text
 * @param {Array} comments - Current comments array
 * @param {Function} setComments - State setter
 * @param {number} commentId - ID of comment to edit
 * @param {string} newText - New comment text
 */
export function editComment(comments, setComments, commentId, newText) {
  setComments((prev) =>
    prev.map((c) =>
      c.id === commentId ? { ...c, text: newText, editedAt: new Date().toISOString() } : c
    )
  );
}

/**
 * Helper function to delete a comment
 * @param {Function} setComments - State setter
 * @param {number} commentId - ID of comment to delete
 */
export function deleteComment(setComments, commentId) {
  setComments((prev) => prev.filter((c) => c.id !== commentId));
}
