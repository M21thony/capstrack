import React, { useCallback, useEffect, useRef, useState } from "react";
import { PDFDocument, rgb, StandardFonts } from "pdf-lib";
import CheckerNavbar from "./component/CheckerNavBar";
import PDFChecker from "./component/pages/PDFChecker";
import StudentPdfViewer from "./component/pages/StudentPdfViewer";
import FinishReview from "./component/FinishReview";
import { useVersionDiff } from "./component/reader/useVersionDiff";
import { Toast } from "./component/reader/Reader";
import { remarkFromServer } from "./component/reader/figures";
import { diagramPages, pngOf } from "./component/reader/diagramPages";
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
const CARD_MARGIN = 10;
const CARD_GAP = 8;

// The PDF's built-in font knows only Western letters; these are its nearest stand-ins.
const SWAPS = { "→": "->", "←": "<-", "↔": "<->", "⇒": "=>", "≤": "<=", "≥": ">=", "≠": "!=", "✓": "OK", "✔": "OK", "✗": "x", "✘": "x", "−": "-" };

// Splits text into lines no wider than maxWidth, breaking a word too long for a line of its own.
const wrapText = (text, font, size, maxWidth) => {
  const fits = (s) => font.widthOfTextAtSize(s, size) <= maxWidth;
  const lines = [];
  let line = "";
  text.split(" ").forEach((word) => {
    while (word && !fits(word)) {
      let cut = word.length - 1;
      while (cut > 1 && !fits(word.slice(0, cut))) cut--;
      if (line) { lines.push(line); line = ""; }
      lines.push(word.slice(0, cut));
      word = word.slice(cut);
    }
    const test = line ? `${line} ${word}` : word;
    if (fits(test)) line = test; else { lines.push(line); line = word; }
  });
  if (line) lines.push(line);
  return lines;
};

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
  // None when the team sent the diagram file itself: its diagram pages are the document.
  const pdfUrl = reviewId && !ctx.diagramOnly ? `/reviews/${reviewId}/pdf` : null;
  // Measured once: this document and the earlier version both draw from it.
  const diff = useVersionDiff(pdfUrl, baseVersion);
  const [focus, setFocus] = useState(null);
  // The diagram compare's progress and its boxes for the earlier version.
  const [figureView, setFigureView] = useState({ status: "idle", note: "", oldMarks: {} });

  const [comments, setComments] = useState([]);
  const [generalComments, setGeneralComments] = useState([]);
  const [loaded, setLoaded] = useState(false);
  const [selectedId, setSelectedId] = useState(null);
  // Review workflow status: "To Review" -> "Reviewing" -> "Reviewed"
  const [reviewStatus, setReviewStatus] = useState("To Review");
  // "approved", "needs_revision", or null when not turned in yet.
  const [reviewVerdict, setReviewVerdict] = useState(null);
  const [saveState, setSaveState] = useState("saved");
  // The consultant is the one who checks the diagrams, so their review opens ready for figures.
  const checksDiagrams = ctx.role === "consultant";
  // Figures the reviewer looked at and had nothing to say about.
  const [checkedFigures, setCheckedFigures] = useState([]);
  const [toast, setToast] = useState(null);
  const toastTimerRef = useRef(null);
  const [finishing, setFinishing] = useState(false);
  const [busy, setBusy] = useState(false);

  const commentsRef = useRef(comments);
  commentsRef.current = comments;
  const statusRef = useRef(reviewStatus);
  statusRef.current = reviewStatus;

  // Checking a revision: what the reviewer found for each remark on the
  // earlier version. Kept on that remark; a remark still not fixed is carried
  // into this review, at its place in this version, so the team sees it again.
  const [revisionStatus, setRevisionStatus] = useState({});
  const setRevision = async (item, chosen) => {
    const before = item.id in revisionStatus ? revisionStatus[item.id] : item.revision_status || null;
    const next = before === chosen ? null : chosen;
    setRevisionStatus((m) => ({ ...m, [item.id]: next }));
    try {
      const response = await request(`/reviews/${reviewId}/versions/${baseVersion.id}/remarks/${item.id}/revision`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status: next }),
      });
      if (!response.ok) throw new Error();
    } catch {
      setRevisionStatus((m) => ({ ...m, [item.id]: before }));
      showToast("That was not saved. Try again.", { type: "error" });
      return;
    }
    const carried = (c) => String(c.carried_from_id) === String(item.id);
    // A removed shape points at the earlier drawing (target.before), so nothing is carried onto this one.
    if (next === "not_fixed" && item.target && !item.target.before && !commentsRef.current.some(carried)) {
      const t = item.target;
      setComments((prev) => [...prev, {
        id: `new-${Date.now()}`,
        type: item.type,
        page_number: t.page,
        x_position: item.type === "pin" ? t.x : t.rects[0]?.left_position ?? 0,
        y_position: item.type === "pin" ? t.y : t.rects[0]?.top_position ?? 0,
        highlightRects: item.type === "pin" ? [] : t.rects,
        text: `Still not fixed (from ${baseVersion.round || "the last version"}): ${item.text}`,
        author: "Reviewer",
        timestamp: new Date().toISOString(),
        selectedText: item.type === "text" ? item.quoteNow || item.selectedText : null,
        section: item.section || null,
        color: item.color,
        figure_label: item.type === "text" ? null : item.figureNow || item.figure_label,
        diagram_type: item.type === "text" ? null : item.diagram_type,
        shape_ref: item.type === "shape" ? item.shapeNow || item.shape_ref : null,
        carried_from_id: item.id,
      }]);
      showToast("Added to this review as still not fixed.");
    } else if (next !== "not_fixed" && commentsRef.current.some(carried)) {
      setComments((prev) => prev.filter((c) => !carried(c)));
    }
  };

  const toggleFigure = (label) => {
    const next = checkedFigures.includes(label) ? checkedFigures.filter((f) => f !== label) : [...checkedFigures, label];
    setCheckedFigures(next);
    request(`/reviews/${reviewId}/figures-checked`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ figures: next }),
    }).then((r) => { if (!r.ok) throw new Error(); })
      .catch(() => { setCheckedFigures(checkedFigures); showToast("That was not saved. Try again.", { type: "error" }); });
  };

  const showToast = useCallback((message, opts = {}) => {
    if (window.ctToast) {
      window.ctToast(message, opts.type, { undo: opts.undo });
      return;
    }
    clearTimeout(toastTimerRef.current);
    setToast({ message, ...opts });
    toastTimerRef.current = setTimeout(() => setToast(null), 4000);
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
        if (Array.isArray(data?.figures_checked)) setCheckedFigures(data.figures_checked);
      });
    const remarks = request(`/reviews/${reviewId}/remarks`)
      .then((r) => r.json())
      .then((data) => setComments(data.map(remarkFromServer)));
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

  // The endpoint takes every page remark for this review in one request and
  // keeps each remark's id, so a remark is the same remark from one save to
  // the next. A new remark is told its id in the reply; until the screen has
  // caught up, idMap carries it, so a remark is never created twice. Saves
  // run one after another for the same reason.
  const idMap = useRef({});
  const saveChain = useRef(Promise.resolve());
  const saveRemarks = (list) => {
    const run = saveChain.current.catch(() => {}).then(async () => {
      const comments = list.map((c) => ({ ...c, id: idMap.current[c.id] ?? c.id }));
      const response = await request(`/reviews/${reviewId}/remarks`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ comments }),
      });
      if (!response.ok) throw new Error(`Save failed: ${response.status}`);
      const data = await response.json();
      const ids = data.ids || {};
      if (Object.keys(ids).length) {
        Object.assign(idMap.current, ids);
        setComments((prev) => prev.map((c) => (ids[c.id] != null ? { ...c, id: ids[c.id] } : c)));
        setSelectedId((id) => (ids[id] != null ? ids[id] : id));
      }
      return data;
    });
    saveChain.current = run;
    return run;
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
    let pdfDoc;
    if (pdfUrl) {
      const response = await fetch(pdfUrl);
      if (!response.ok) throw new Error(`Failed to load PDF: ${response.status} ${response.statusText}`);
      pdfDoc = await PDFDocument.load(await response.arrayBuffer(), { ignoreEncryption: true, updateMetadata: false });
    } else {
      pdfDoc = await PDFDocument.create();
    }
    // The team's draw.io diagrams follow as pages of their own, as in the reader, so their remarks land on them.
    if (ctx.diagrams) {
      try {
        // A diagram sent as itself has no page to match, so it takes a letter page's width.
        const pageWidth = pdfDoc.getPageCount() ? pdfDoc.getPage(0).getWidth() : 612;
        for (const { plain } of await diagramPages(ctx.diagrams)) {
          const picture = await pdfDoc.embedPng(await pngOf(plain));
          const pageHeight = (pageWidth * plain.height) / plain.width;
          pdfDoc.addPage([pageWidth, pageHeight]).drawImage(picture, { x: 0, y: 0, width: pageWidth, height: pageHeight });
        }
      } catch (e) {
        console.warn("Could not add the diagram pages:", e);
      }
    }
    const pages = pdfDoc.getPages();
    const helveticaFont = await pdfDoc.embedFont(StandardFonts.Helvetica);
    const helveticaBoldFont = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
    // A character the font cannot draw would throw and leave the card empty, so each one is swapped first.
    const drawable = new Set(helveticaFont.getCharacterSet());
    const write = (s) => [...String(s).replace(/\s+/g, " ").trim()]
      .map((ch) => (drawable.has(ch.codePointAt(0)) ? ch : SWAPS[ch] ?? "?")).join("");
    const reviewerName = write(ctx.reviewer || "Reviewer");

    const commentsByPage = {};
    commentsRef.current.forEach((comment) => {
      const pageIndex = Number(comment.page_number) - 1;
      (commentsByPage[pageIndex] ||= []).push(comment);
    });

    // Words that ran onto the next page are highlighted there too; the card stays on the page they start on.
    commentsRef.current.forEach((comment) => (comment.highlightRects || []).forEach((rect) => {
      const on = rect.page && Number(rect.page) !== Number(comment.page_number) ? pages[Number(rect.page) - 1] : null;
      if (!on) return;
      const { width, height } = on.getSize();
      on.drawRectangle({
        x: rect.left_position * width,
        y: height - (rect.top_position + rect.highlight_height) * height,
        width: rect.highlight_width * width,
        height: rect.highlight_height * height,
        color: HIGHLIGHT_COLOR_MAP[comment.color] || HIGHLIGHT_COLOR_MAP.yellow,
        opacity: 0.25,
      });
    }));

    Object.entries(commentsByPage).forEach(([pageIndexStr, pageComments]) => {
      const pageIndex = Number(pageIndexStr);
      if (pageIndex < 0 || pageIndex >= pages.length) return;

      const page = pages[pageIndex];
      const { width, height } = page.getSize();
      const anchorYByIndex = [];

      pageComments.forEach((comment, cIdx) => {
        const highlightColor = HIGHLIGHT_COLOR_MAP[comment.color] || HIGHLIGHT_COLOR_MAP.yellow;
        if (comment.type === "pin") {
          // A pin is a spot, not an area: a dot where it was dropped.
          try {
            page.drawCircle({
              x: (comment.x_position || 0) * width, y: height - (comment.y_position || 0) * height,
              size: 4, color: highlightColor, borderColor: rgb(1, 1, 1), borderWidth: 1,
            });
          } catch (e) {
            console.warn("Error drawing pin:", e);
          }
        }
        (comment.highlightRects || []).filter((rect) => !rect.page || Number(rect.page) === Number(comment.page_number)).forEach((rect) => {
          try {
            if (comment.type === "box" || comment.type === "shape") {
              // An outline, so the diagram under it stays readable.
              page.drawRectangle({
                x: (rect.left_position || 0) * width,
                y: height - (rect.top_position || 0) * height - (rect.highlight_height || 0) * height,
                width: (rect.highlight_width || 0) * width,
                height: (rect.highlight_height || 0) * height,
                borderColor: highlightColor,
                borderWidth: 1.5,
              });
              return;
            }
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

        const firstRect = (comment.highlightRects || [])[0]
          || (comment.type === "pin" ? { left_position: comment.x_position, top_position: comment.y_position } : null);
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

      const boxX = width + CARD_MARGIN;
      const boxWidth = SIDEBAR_WIDTH - 2 * CARD_MARGIN;
      const cardHeight = (lineCount) => Math.max(40, 22 + lineCount * 9 + 12);
      const cards = pageComments.map((comment, idx) => {
        const lines = wrapText(write(`${comment.figure_label ? `${comment.figure_label}: ` : ""}${comment.text || ""}`), helveticaFont, 7, boxWidth - 30);
        return { comment, idx, lines, height: cardHeight(lines.length) };
      });
      const drawCard = (onPage, { idx, lines, height: h }, top) => {
        try {
          onPage.drawRectangle({
            x: boxX, y: top - h, width: boxWidth, height: h,
            color: rgb(1, 1, 1), borderWidth: 1, borderColor: rgb(0.231, 0.51, 0.965),
          });
          onPage.drawCircle({ x: boxX + 12, y: top - 12, size: 8, color: rgb(0.231, 0.51, 0.965) });
          onPage.drawText(String(idx + 1), { x: boxX + (idx + 1 >= 10 ? 7.5 : 9.5), y: top - 15, size: 7, font: helveticaBoldFont, color: rgb(1, 1, 1) });
          lines.forEach((textLine, i) => onPage.drawText(textLine, {
            x: boxX + 25, y: top - 15 - i * 9, size: 7, font: helveticaFont, color: rgb(0.2, 0.2, 0.2),
          }));
          onPage.drawText(`- ${reviewerName}`, { x: boxX + 5, y: top - h + 5, size: 6, font: helveticaFont, color: rgb(0.5, 0.5, 0.5) });
        } catch (e) {
          console.warn("Error drawing comment:", e);
        }
      };

      // Each card sits beside its mark, pushed down when the one above takes the room.
      // What no longer fits goes on a page of its own right after this one.
      const overflow = [];
      let cursorMaxTop = height;
      cards
        .map((card) => ({ ...card, anchorY: anchorYByIndex[card.idx] ?? height - CARD_MARGIN - card.idx * 50 }))
        .sort((a, b) => b.anchorY - a.anchorY)
        .forEach((card) => {
          const top = Math.min(card.anchorY + 20, cursorMaxTop);
          if (overflow.length || top - card.height < CARD_MARGIN) { overflow.push(card); return; }
          cursorMaxTop = top - card.height - CARD_GAP;
          page.drawLine({
            start: { x: width, y: card.anchorY }, end: { x: boxX, y: top - 8 }, thickness: 1,
            color: HIGHLIGHT_COLOR_MAP[card.comment.color] || HIGHLIGHT_COLOR_MAP.yellow, opacity: 0.9,
          });
          drawCard(page, card, top);
        });

      // A remark taller than a whole page carries on over as many cards as it needs.
      const maxLines = Math.floor((height - 45 - CARD_MARGIN - 34) / 9);
      const pieces = overflow.flatMap((card) => {
        const parts = [];
        for (let i = 0; i === 0 || i < card.lines.length; i += maxLines) {
          const lines = card.lines.slice(i, i + maxLines);
          parts.push({ ...card, lines, height: cardHeight(lines.length) });
        }
        return parts;
      });
      let at = pdfDoc.getPages().indexOf(page);
      let more = null;
      let top = 0;
      pieces.forEach((card) => {
        if (!more || top - card.height < CARD_MARGIN) {
          more = pdfDoc.insertPage(++at, [width + SIDEBAR_WIDTH, height]);
          more.drawText(`Remarks on page ${pageIndex + 1}, continued`, { x: boxX, y: height - 30, size: 10, font: helveticaBoldFont, color: rgb(0.2, 0.2, 0.2) });
          top = height - 45;
        }
        drawCard(more, card, top);
        top -= card.height + CARD_GAP;
      });
    });

    // General remarks are about the whole document, so they close it on pages of their own.
    if (generalComments.length) {
      const [w, h] = pages.length ? [pages[0].getWidth(), pages[0].getHeight()] : [612, 792];
      const textWidth = w - 2 * 50;
      let page = null;
      let y = 0;
      const newPage = () => {
        page = pdfDoc.addPage([w, h]);
        page.drawText("General remarks", { x: 50, y: h - 60, size: 16, font: helveticaBoldFont, color: rgb(0.1, 0.1, 0.1) });
        page.drawText(`- ${reviewerName}`, { x: 50, y: h - 78, size: 9, font: helveticaFont, color: rgb(0.45, 0.45, 0.45) });
        y = h - 108;
      };
      generalComments.forEach((g, i) => {
        const head = write(`${i + 1}.${g.section ? ` ${g.section}` : ""}`);
        const lines = wrapText(write(g.text || ""), helveticaFont, 10, textWidth);
        const need = 16 + lines.length * 13 + 12;
        // A remark that fits on a page is kept whole; one longer than a page runs on to the next.
        if (!page || (y - need < 50 && need <= h - 158) || y - 29 < 50) newPage();
        page.drawText(head, { x: 50, y, size: 10, font: helveticaBoldFont, color: rgb(0.231, 0.51, 0.965) });
        y -= 16;
        lines.forEach((line) => {
          if (y < 50) newPage();
          page.drawText(line, { x: 50, y, size: 10, font: helveticaFont, color: rgb(0.2, 0.2, 0.2) });
          y -= 13;
        });
        y -= 12;
      });
    }

    return pdfDoc.save();
  };

  // Leaving while remarks save would drop them.
  useEffect(() => {
    const onBeforeUnload = (e) => {
      if (saveState !== "saving") return;
      e.preventDefault();
      e.returnValue = "";
    };
    window.addEventListener("beforeunload", onBeforeUnload);
    return () => window.removeEventListener("beforeunload", onBeforeUnload);
  }, [saveState]);

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
      a.download = `${(ctx.fileName || "annotated").replace(/\.(pdf|drawio|vsdx)$/i, "").replace(/[^\w.-]+/g, "_")}-remarks.pdf`;
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
          pdfUrl={pdfUrl}
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
          startMode={checksDiagrams ? "box" : "text"}
          startTab={checksDiagrams ? "figures" : "remarks"}
          checkedFigures={checkedFigures}
          onToggleFigure={toggleFigure}
          revisionStatus={revisionStatus}
          onRevision={setRevision}
          compareOpen={Boolean(compareVersion)}
          onFigures={setFigureView}
          diagrams={ctx.diagrams}
        />
        {compareVersion && (
          <div className="an-pane-head">
            <span className="an-dot now" aria-hidden="true" />
            <b>This version</b>
            {ctx.round && <span className="an-pane-sub">{ctx.round}</span>}
            <span className="rr-spacer" />
            {showChanges && figureView.status === "loading" && <span className="an-pane-sub">{figureView.note || "Comparing the figures…"}</span>}
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
              diagrams={compareVersion.diagrams}
              newerDiagrams={showChanges ? ctx.diagrams : null}
              reviewer="you"
              marks={showChanges && diff.status === "ready" ? diff.oldMarks : null}
              figureMarks={showChanges ? figureView.oldMarks : null}
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
        busy={busy}
        ctx={ctx}
      />
      <Toast toast={toast} onClose={() => setToast(null)} />
    </div>
  );
}

export default App;
