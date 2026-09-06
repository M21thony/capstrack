import React, { useState, useRef, useEffect } from "react";

/**
 * CheckerNavbar - Navigation bar with PDF checking tools
 */
function CheckerNavbar({
  commentMode,
  setCommentMode,
  selectedHighlightColor,
  setSelectedHighlightColor,
  status = "To Review",
  verdict = null,
  saveState = "idle",
  driveSyncState = "idle",
  driveSyncError = null,
  onTurnIn,
  onSave,
  onExport,
}) {
  const [showHighlightDropdown, setShowHighlightDropdown] = useState(false);
  const highlightDropdownRef = useRef(null);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (highlightDropdownRef.current && !highlightDropdownRef.current.contains(event.target)) {
        setShowHighlightDropdown(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  // Toggle the comment tool
  const toggleTool = () => {
    setCommentMode(!commentMode);
  };

  const highlightColors = [
    { id: "yellow", label: "Yellow", color: "#ffff00", rgba: "rgba(255, 255, 0, 0.4)" },
    { id: "green", label: "Green", color: "#00ff00", rgba: "rgba(0, 255, 0, 0.4)" },
    { id: "blue", label: "Blue", color: "#00bfff", rgba: "rgba(0, 191, 255, 0.4)" },
    { id: "pink", label: "Red", color: "#ff0000", rgba: "rgba(255, 0, 0, 0.4)" },
    { id: "orange", label: "Orange", color: "#ffa500", rgba: "rgba(255, 165, 0, 0.4)" },
  ];

  const getCurrentHighlightColor = () => {
    const hl = highlightColors.find((c) => c.id === selectedHighlightColor);
    return hl || highlightColors[0];
  };

  return (
    <nav style={styles.navbar}>
      {/* Left Section - Navigation */}
      <div style={styles.left}>
        <a
          href="/instructor/docreview"
          style={styles.backLink}
          onClick={(e) => {
            if (
              driveSyncState === "syncing" &&
              !window.confirm(
                "The annotated PDF is still uploading to Google Drive. Leave anyway? The upload will be canceled."
              )
            ) {
              e.preventDefault();
            }
          }}
        >
          ← Back
        </a>
      </div>

      {/* Center Section - Tools + Actions */}
      <div style={styles.center}>
        {/* Comment tool with highlight color dropdown.
            A comment always carries its highlight, so there is no separate
            highlight tool (no highlight without a comment). */}
        <div style={styles.dropdownContainer} ref={highlightDropdownRef}>
          <div style={{ display: "flex" }}>
            <button
              style={{
                ...styles.toolButton,
                backgroundColor: commentMode ? "#dbeafe" : "white",
                borderColor: commentMode ? "#3b82f6" : "#d1d5db",
                borderRadius: "8px 0 0 8px",
                borderRight: "none",
              }}
              onClick={toggleTool}
              title="Add comment (with highlight)"
            >
              <span style={{ ...styles.toolIcon }}>💬</span>
              <span style={styles.toolLabel}>Comment</span>
            </button>
            <button
              style={{
                ...styles.dropdownToggleButton,
                backgroundColor: commentMode ? "#dbeafe" : "white",
                borderColor: commentMode ? "#3b82f6" : "#d1d5db",
              }}
              onClick={() => setShowHighlightDropdown(!showHighlightDropdown)}
              title="Select highlight color"
            >
              <span
                style={{
                  ...styles.colorDot,
                  backgroundColor: getCurrentHighlightColor().color,
                }}
              ></span>
            </button>
          </div>

          {/* Highlight Color Dropdown */}
          {showHighlightDropdown && (
            <div style={styles.dropdown}>
              {highlightColors.map((hlColor) => (
                <button
                  key={hlColor.id}
                  style={{
                    ...styles.dropdownItem,
                    backgroundColor:
                      selectedHighlightColor === hlColor.id ? hlColor.rgba : "transparent",
                    color: "#374151",
                    border: `2px solid ${hlColor.color}`,
                  }}
                  onClick={() => {
                    setSelectedHighlightColor(hlColor.id);
                    setShowHighlightDropdown(false);
                  }}
                >
                  <span
                    style={{
                      display: "inline-block",
                      width: "16px",
                      height: "16px",
                      backgroundColor: hlColor.color,
                      borderRadius: "3px",
                      marginRight: "8px",
                      border: "1px solid rgba(0,0,0,0.2)",
                    }}
                  ></span>
                  {hlColor.label}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Active Tool Indicator */}
        {commentMode && (
          <div style={styles.activeIndicator}>
            <span style={styles.activeDot}>●</span>
            <span style={styles.activeText}>Comment Mode</span>
            <button onClick={() => setCommentMode(false)} style={styles.deactivateButton}>
              ✕
            </button>
          </div>
        )}

        {/* Divider between tools and actions */}
        <div style={styles.divider} />

        {/* Review status indicator */}
        <div style={getStatusPillStyle(status)}>
          {getStatusLabel(status)}
        </div>

        {/* Background Google Drive sync indicator */}
        {driveSyncState === "syncing" && (
          <div style={styles.syncPill} title="Uploading the annotated PDF to Google Drive — stay on this page until this finishes.">
            ☁ Syncing to Drive…
          </div>
        )}
        {driveSyncState === "error" && (
          <div
            style={{ ...styles.syncPill, ...styles.syncPillError }}
            title={
              driveSyncError
                ? `Drive sync failed: ${driveSyncError}`
                : "Could not upload the annotated PDF to Google Drive. Click Save or Turn In again to retry."
            }
          >
            ⚠ Drive sync failed{driveSyncError ? ` — ${driveSyncError}` : ""}
          </div>
        )}
        {driveSyncState === "error" && needsDriveConnect(driveSyncError) && (
          <a href="/admin/gdrive/connect" style={styles.connectDriveLink}>
            Connect Google Drive →
          </a>
        )}

        {/* Action Buttons */}
        <button
          onClick={onSave}
          style={
            saveState === "saved"
              ? { ...styles.saveButton, ...styles.saveButtonSaved }
              : styles.saveButton
          }
          title={status === "To Review" ? "Save annotations" : "Continue reviewing"}
        >
          💾 {saveState === "saved" ? "Saved" : "Save"}
        </button>

        <button onClick={onExport} style={styles.exportButton} title="Export PDF with annotations">
          📤 Export
        </button>

        {/* Turning in IS the decision — there is no way to finish without
            making one. It used to be a single button, and whether the adviser
            was happy went into a typed remark that nothing could read. */}
        <button
          onClick={() => onTurnIn("approved")}
          style={verdict === "approved" ? { ...styles.turnInButton, ...styles.verdictActive } : styles.turnInButton}
          title="Finish this review and approve the document"
        >
          ✓ Approve
        </button>

        <button
          onClick={() => onTurnIn("needs_revision")}
          style={verdict === "needs_revision" ? { ...styles.reviseButton, ...styles.verdictActive } : styles.reviseButton}
          title="Finish this review and send it back for changes"
        >
          ↻ Needs revision
        </button>
      </div>

      {/* Right Section - spacer for balance */}
      <div style={styles.right}></div>
    </nav>
  );
}

// True when the Drive error is something a "Connect Google Drive" link can
// actually fix (no account connected, or the connection went stale) — as
// opposed to e.g. "this review has no Drive file to update", which connecting
// again wouldn't help.
const needsDriveConnect = (message) => {
  if (!message) return false;
  const m = message.toLowerCase();
  return m.includes("not connected") || m.includes("reconnect") || m.includes("session expired");
};

const getStatusLabel = (status) => {
  if (status === "Reviewed") return "Reviewed";
  if (status === "Reviewing") return "In Review";
  return "To Review";
};

const getStatusPillStyle = (status) => {
  const base = {
    display: "inline-flex",
    alignItems: "center",
    gap: "6px",
    padding: "6px 12px",
    borderRadius: "20px",
    fontSize: "12px",
    fontWeight: "600",
    whiteSpace: "nowrap",
  };

  if (status === "Reviewed") {
    return { ...base, backgroundColor: "#dcfce7", color: "#166534", border: "1px solid #bbf7d0" };
  }
  if (status === "Reviewing") {
    return { ...base, backgroundColor: "#fef3c7", color: "#92400e", border: "1px solid #fde68a" };
  }
  return { ...base, backgroundColor: "#fee2e2", color: "#991b1b", border: "1px solid #fecaca" };
};

const styles = {
  navbar: {
    display: "flex",
    alignItems: "center",
    justifyContent: "space-between",
    backgroundColor: "#ffffff",
    padding: "12px 24px",
    boxShadow: "0 2px 8px rgba(0, 0, 0, 0.1)",
    borderBottom: "1px solid #e5e7eb",
    position: "sticky",
    top: 0,
    zIndex: 1000,
  },
  left: {
    flex: 1,
    display: "flex",
    alignItems: "center",
  },
  backLink: {
    textDecoration: "none",
    color: "#6b7280",
    fontWeight: "500",
    fontSize: "14px",
    padding: "8px 12px",
    borderRadius: "6px",
    transition: "all 0.2s",
  },
  center: {
    flex: 3,
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
    gap: "16px",
  },
  right: {
    flex: 1,
    display: "flex",
    alignItems: "center",
    justifyContent: "flex-end",
    gap: "8px",
  },
  toolButton: {
    display: "flex",
    alignItems: "center",
    gap: "4px",
    padding: "8px 12px",
    borderRadius: "8px",
    border: "2px solid",
    cursor: "pointer",
    fontSize: "13px",
    fontWeight: "500",
    transition: "all 0.2s",
    backgroundColor: "white",
  },
  dropdownToggleButton: {
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
    padding: "8px 8px",
    borderRadius: "0 8px 8px 0",
    border: "2px solid",
    cursor: "pointer",
    transition: "all 0.2s",
  },
  toolIcon: {
    fontSize: "16px",
  },
  toolLabel: {
    color: "#374151",
  },
  dropdownArrow: {
    fontSize: "10px",
    color: "#6b7280",
  },
  divider: {
    width: "1px",
    height: "32px",
    backgroundColor: "#e5e7eb",
    margin: "0 8px",
  },
  dropdownContainer: {
    position: "relative",
  },
  dropdown: {
    position: "absolute",
    top: "100%",
    left: 0,
    marginTop: "4px",
    backgroundColor: "white",
    borderRadius: "8px",
    boxShadow: "0 4px 12px rgba(0, 0, 0, 0.15)",
    border: "1px solid #e5e7eb",
    overflow: "hidden",
    zIndex: 100,
    minWidth: "160px",
  },
  dropdownItem: {
    display: "flex",
    alignItems: "center",
    gap: "8px",
    width: "100%",
    padding: "10px 14px",
    border: "none",
    cursor: "pointer",
    fontSize: "13px",
    fontWeight: "500",
    transition: "all 0.2s",
    textAlign: "left",
  },
  colorDot: {
    width: "14px",
    height: "14px",
    borderRadius: "3px",
    border: "1px solid rgba(0,0,0,0.2)",
  },
  activeIndicator: {
    display: "flex",
    alignItems: "center",
    gap: "6px",
    padding: "6px 12px",
    backgroundColor: "#eff6ff",
    borderRadius: "20px",
    border: "1px solid #bfdbfe",
  },
  activeDot: {
    color: "#3b82f6",
    fontSize: "10px",
  },
  activeText: {
    fontSize: "12px",
    fontWeight: "500",
    color: "#1d4ed8",
  },
  deactivateButton: {
    width: "18px",
    height: "18px",
    borderRadius: "50%",
    border: "none",
    backgroundColor: "#fee2e2",
    color: "#dc2626",
    cursor: "pointer",
    fontSize: "10px",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
  },
  syncPill: {
    display: "inline-flex",
    alignItems: "center",
    gap: "4px",
    padding: "6px 12px",
    borderRadius: "20px",
    fontSize: "12px",
    fontWeight: "600",
    whiteSpace: "nowrap",
    backgroundColor: "#eff6ff",
    color: "#1d4ed8",
    border: "1px solid #bfdbfe",
  },
  syncPillError: {
    backgroundColor: "#fef2f2",
    color: "#991b1b",
    border: "1px solid #fecaca",
  },
  connectDriveLink: {
    fontSize: "12px",
    fontWeight: "600",
    color: "#2563eb",
    textDecoration: "none",
    whiteSpace: "nowrap",
  },
  saveButton: {
    display: "flex",
    alignItems: "center",
    gap: "6px",
    padding: "8px 16px",
    borderRadius: "8px",
    border: "1px solid #d1d5db",
    backgroundColor: "#ffffff",
    cursor: "pointer",
    fontSize: "13px",
    fontWeight: "500",
    color: "#374151",
  },
  saveButtonSaved: {
    backgroundColor: "#dcfce7",
    borderColor: "#86efac",
    color: "#166534",
  },
  exportButton: {
    display: "flex",
    alignItems: "center",
    gap: "6px",
    padding: "8px 16px",
    borderRadius: "8px",
    border: "1px solid #d1d5db",
    backgroundColor: "#ffffff",
    cursor: "pointer",
    fontSize: "13px",
    fontWeight: "500",
    color: "#374151",
  },
  turnInButton: {
    display: "flex",
    alignItems: "center",
    gap: "6px",
    padding: "10px 20px",
    borderRadius: "8px",
    border: "none",
    backgroundColor: "#2563eb",
    color: "white",
    cursor: "pointer",
    fontSize: "14px",
    fontWeight: "600",
    boxShadow: "0 2px 4px rgba(37, 99, 235, 0.3)",
  },

  // Sending it back is a different act from accepting it, so it does not
  // borrow the primary colour. Amber, not red: needing another draft is
  // ordinary, not a failure.
  reviseButton: {
    display: "flex",
    alignItems: "center",
    gap: "6px",
    padding: "10px 18px",
    borderRadius: "8px",
    border: "1px solid #d97706",
    backgroundColor: "transparent",
    color: "#b45309",
    cursor: "pointer",
    fontSize: "14px",
    fontWeight: "600",
  },

  // Marks the decision already recorded, so reopening the annotator shows
  // what was decided rather than offering two fresh-looking choices.
  verdictActive: {
    outline: "2px solid currentColor",
    outlineOffset: "2px",
  },
};

export default CheckerNavbar;
