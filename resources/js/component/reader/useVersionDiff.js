import { useEffect, useState } from "react";
import { pdfjs } from "react-pdf";
import { compareVersions, remarksNearChanges } from "./versionDiff";

const normalise = (s) => s.toLowerCase().normalize("NFKD").replace(/[^\p{L}\p{N}]+/gu, "");
// A line that is only a page number would count as changed whenever pages shift.
const PAGE_NUMBER = /^(page\s*)?(\d+|[ivxlc]{1,6})(\s*of\s*\d+)?$/i;

/**
 * Every word of a PDF, with its page, line and where it sits as fractions of
 * the page. A PDF stores text in pieces that can split a word ("sys" + "tem")
 * differently from one save to the next, so pieces are joined back into words
 * by where they sit, not by how they were stored.
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
      let prev = null; // {top, end, h, eol}

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

        if (prev) {
          const newLine = prev.eol || Math.abs(top - prev.top) > h / 2;
          if (newLine) {
            line++;
            // A word hyphenated across the line break is still one word.
            if (!(word && /\p{L}-$/u.test(word.raw))) endWord();
          } else if (x - prev.end > h * 0.2) {
            endWord();
          }
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
        prev = { top, end: x + item.width, h, eol: item.hasEOL };
      });
      endWord();

      // Drop lines that are only a page number.
      const byLine = {};
      pageWords.forEach((w) => (byLine[w.line] ||= []).push(w.raw));
      pageWords.forEach((w) => { if (!PAGE_NUMBER.test(byLine[w.line].join(" "))) words.push(w); });
      page.cleanup();
    }
  } finally {
    doc.destroy();
  }
  return words;
}

/**
 * Compares the document with an earlier version ({file, remarks} URLs).
 * Status: idle, loading, ready, notext (a scanned PDF has no words) or error.
 */
export function useVersionDiff(currentUrl, base) {
  const [state, setState] = useState({ status: "idle" });

  useEffect(() => {
    if (!currentUrl || !base) { setState({ status: "idle" }); return undefined; }
    let live = true;
    setState({ status: "loading" });
    Promise.all([
      readWords(base.file),
      readWords(currentUrl),
      fetch(base.remarks, { headers: { Accept: "application/json" } }).then((r) => (r.ok ? r.json() : [])),
    ])
      .then(([oldWords, newWords, remarks]) => {
        if (!live) return;
        if (!oldWords.length || !newWords.length) { setState({ status: "notext" }); return; }
        // ponytail: runs on the main thread; move to a worker if long documents freeze the page.
        const diff = compareVersions(oldWords, newWords);
        setState({ status: "ready", changes: diff.changes, marks: diff.marks, remarks: remarksNearChanges(remarks, oldWords, newWords, diff) });
      })
      .catch(() => { if (live) setState({ status: "error" }); });
    return () => { live = false; };
  }, [currentUrl, base?.file]); // eslint-disable-line react-hooks/exhaustive-deps

  return state;
}
