import { useEffect, useState } from "react";
import { pdfjs } from "react-pdf";
import { compareVersions, dropRunningLines, remarksNearChanges, sharedPhrases, MIN_SHARED } from "./versionDiff";
import { checkRevision } from "./revisionCheck";
import { remarkFromServer } from "./figures";
import { layoutPiece, naturalScale } from "./rects";

const normalise = (s) => s.toLowerCase().normalize("NFKD").replace(/[^\p{L}\p{N}]+/gu, "");

/**
 * The width of some text at a font size of 1, in a font like the PDF's
 * (the one PDF.js names for it), so a word's place in its line follows its
 * letters and not how many there are.
 */
const measurer = () => {
  const ctx = typeof document === "undefined" ? null : document.createElement("canvas").getContext("2d");
  const cache = new Map();
  return (family) => {
    if (!ctx) {
      return (text) => text.length / 2;
    }
    return (text) => {
      const key = `${family}\u0000${text}`;
      if (!cache.has(key)) {
        ctx.font = `100px ${family}`;
        cache.set(key, ctx.measureText(text).width / 100);
      }
      return cache.get(key);
    };
  };
};

/**
 * Every word of a PDF, with its page, line and where it sits as fractions of
 * the page. A PDF stores text in pieces that can split a word ("C" + "APSTRACK"
 * in small caps) differently from one save to the next. PDF.js already puts a
 * space wherever the text really has one, so pieces are joined unless it did.
 */
export async function readWords(url) {
  const doc = await pdfjs.getDocument(url).promise;
  const words = [];
  const fontLike = measurer();
  try {
    for (let p = 1; p <= doc.numPages; p++) {
      const page = await doc.getPage(p);
      const vp = page.getViewport({ scale: 1 });
      const { items, styles } = await page.getTextContent();
      const placed = items.filter((item) => item.str).map((item) => {
        const tx = pdfjs.Util.transform(vp.transform, item.transform);
        return { item, tx, h: Math.hypot(tx[2], tx[3]) || 10 };
      });
      const natural = {};
      Object.keys(styles).forEach((font) => {
        const ofFont = placed.filter((p) => p.item.fontName === font);
        const measure = fontLike(styles[font].fontFamily || "sans-serif");
        natural[font] = naturalScale(ofFont.map((p) => ({ str: p.item.str, width: p.item.width / p.h })), measure);
      });
      let line = 0;
      let word = null;
      let prev = null; // {base, h, eol}

      const endWord = () => {
        if (word) {
          const t = normalise(word.raw);
          if (t) words.push({ ...word, t });
        }
        word = null;
      };

      items.forEach((item) => {
        if (!item.str) { if (item.hasEOL && prev) prev.eol = true; return; }
        const tx = pdfjs.Util.transform(vp.transform, item.transform);
        const h = Math.hypot(tx[2], tx[3]) || 10;
        // From the top of the font's tallest letters to the bottom of its
        // lowest, the way PDF.js lays the text out for selecting.
        const style = styles[item.fontName] || {};
        const ascent = style.ascent || (style.descent ? 1 + style.descent : 0.8);
        const descent = style.descent || ascent - 1;
        const top = tx[5] - h * ascent;
        const x = tx[4];

        // Lines are told apart by baseline, so a bigger first letter stays on its line.
        if (prev && (prev.eol || Math.abs(tx[5] - prev.base) > Math.min(h, prev.h) / 2)) {
          line++;
          // A word hyphenated across the line break is still one word.
          if (!(word && /\p{L}-$/u.test(word.raw))) endWord();
        }

        const measure = fontLike(style.fontFamily || "sans-serif");
        for (const run of layoutPiece(item.str, item.width / h, measure, natural[item.fontName] ?? 1)) {
          if (run.space) { endWord(); continue; }
          word ||= { raw: "", page: p, line, rects: [] };
          word.raw += run.text;
          word.rects.push({
            top_position: top / vp.height,
            left_position: (x + run.left * h) / vp.width,
            highlight_width: (run.width * h) / vp.width,
            highlight_height: (h * (ascent - descent)) / vp.height,
          });
        }
        prev = { base: tx[5], h, eol: item.hasEOL };
      });
      endWord();
      page.cleanup();
    }
  } finally {
    doc.destroy();
  }
  return dropRunningLines(words);
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
