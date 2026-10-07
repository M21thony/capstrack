import { useEffect, useState } from "react";
import { pdfjs } from "react-pdf";
import { compareVersions, remarksNearChanges, sharedPhrases, MIN_SHARED } from "./versionDiff";
import { checkRevision } from "./revisionCheck";
import { remarkFromServer } from "./figures";

const normalise = (s) => s.toLowerCase().normalize("NFKD").replace(/[^\p{L}\p{N}]+/gu, "");
// A page number in the header or footer would count as changed whenever pages
// shift. Only short numbers near the page edge: "2026" on a title page is text.
const PAGE_NUMBER = /^(page\s*)?(\d{1,3}|[ivxlc]{1,6})(\s*of\s*\d{1,3})?$/i;
const nearEdge = (top) => top < 0.12 || top > 0.88;

/**
 * Every word of a PDF, with its page, line and where it sits as fractions of
 * the page. A PDF stores text in pieces that can split a word ("C" + "APSTRACK"
 * in small caps) differently from one save to the next. PDF.js already puts a
 * space wherever the text really has one, so pieces are joined unless it did.
 */
export async function readWords(url) {
  const doc = await pdfjs.getDocument(url).promise;
  const words = [];
  try {
    for (let p = 1; p <= doc.numPages; p++) {
      const page = await doc.getPage(p);
      const vp = page.getViewport({ scale: 1 });
      const { items } = await page.getTextContent();
      const pageWords = [];
      let line = 0;
      let word = null;
      let prev = null; // {base, h, eol}

      const endWord = () => {
        if (word) {
          const t = normalise(word.raw);
          if (t) pageWords.push({ ...word, t });
        }
        word = null;
      };

      items.forEach((item) => {
        if (!item.str) { if (item.hasEOL && prev) prev.eol = true; return; }
        const tx = pdfjs.Util.transform(vp.transform, item.transform);
        const h = Math.hypot(tx[2], tx[3]) || 10;
        const top = tx[5] - h;
        const x = tx[4];

        // Lines are told apart by baseline, so a bigger first letter stays on its line.
        if (prev && (prev.eol || Math.abs(tx[5] - prev.base) > Math.min(h, prev.h) / 2)) {
          line++;
          // A word hyphenated across the line break is still one word.
          if (!(word && /\p{L}-$/u.test(word.raw))) endWord();
        }

        const len = item.str.length;
        for (const m of item.str.matchAll(/\s+|\S+/g)) {
          if (!m[0].trim()) { endWord(); continue; }
          word ||= { raw: "", page: p, line, rects: [] };
          word.raw += m[0];
          word.rects.push({
            top_position: top / vp.height,
            left_position: (x + (item.width * m.index) / len) / vp.width,
            highlight_width: (item.width * m[0].length) / len / vp.width,
            highlight_height: (h * 1.2) / vp.height,
          });
        }
        prev = { base: tx[5], h, eol: item.hasEOL };
      });
      endWord();

      // Drop header and footer lines that are only a page number.
      const byLine = {};
      pageWords.forEach((w) => (byLine[w.line] ||= []).push(w.raw));
      pageWords.forEach((w) => {
        if (!(nearEdge(w.rects[0].top_position) && PAGE_NUMBER.test(byLine[w.line].join(" ")))) words.push(w);
      });
      page.cleanup();
    }
  } finally {
    doc.destroy();
  }
  return words;
}

/**
 * Compares the document with an earlier version ({file, remarks} URLs).
 * Status: idle, loading, ready, notext (a scanned PDF has no words),
 * unrelated (almost nothing in common - likely the wrong file) or error.
 */
export function useVersionDiff(currentUrl, base) {
  const [state, setState] = useState({ status: "idle" });

  useEffect(() => {
    // A diagram sent as itself has no words to line up; its diagram compare shows what changed.
    if (!currentUrl || !base?.file) { setState({ status: "idle" }); return undefined; }
    let live = true;
    setState({ status: "loading" });
    Promise.all([
      readWords(base.file),
      readWords(currentUrl),
      fetch(base.remarks, { headers: { Accept: "application/json" } }).then((r) => (r.ok ? r.json() : [])),
    ])
      .then(([oldWords, newWords, remarks]) => {
        if (!live) return;
        // Shape remarks are followed on the drawing instead (ChangesPanel).
        const earlier = remarks.map(remarkFromServer).filter((r) => r.type !== "shape");
        if (!oldWords.length || !newWords.length) {
          // No text to line up - a scanned or diagrams-only PDF. Figure remarks
          // are still checked, in the same place, by comparing the pictures.
          const none = { removed: new Uint8Array(oldWords.length), added: new Uint8Array(newWords.length) };
          setState({ status: "notext", revision: checkRevision(earlier, oldWords, newWords, none), oldWords, newWords });
          return;
        }
        // Every word would show as changed, which tells the reviewer nothing.
        const shared = sharedPhrases(oldWords, newWords);
        if (shared !== null && shared < MIN_SHARED) { setState({ status: "unrelated", shared }); return; }
        // ponytail: runs on the main thread; move to a worker if long documents freeze the page.
        const diff = compareVersions(oldWords, newWords);
        setState({
          status: "ready", changes: diff.changes, marks: diff.marks, oldMarks: diff.oldMarks,
          remarks: remarksNearChanges(remarks, oldWords, newWords, diff),
          revision: checkRevision(earlier, oldWords, newWords, diff),
          oldWords, newWords,
        });
      })
      .catch(() => { if (live) setState({ status: "error" }); });
    return () => { live = false; };
  }, [currentUrl, base?.file]); // eslint-disable-line react-hooks/exhaustive-deps

  return state;
}
