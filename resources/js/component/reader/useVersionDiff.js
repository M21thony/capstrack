import { useEffect, useState } from "react";
import { pdfjs } from "react-pdf";
import { compareVersions, remarksNearChanges } from "./versionDiff";

const normalise = (s) => s.toLowerCase().normalize("NFKD").replace(/[^\p{L}\p{N}]+/gu, "");
// A line that is only a page number would count as changed whenever pages shift.
const PAGE_NUMBER = /^(page\s*)?(\d+|[ivxlc]{1,6})(\s*of\s*\d+)?$/i;

/** Every word of a PDF, with its page, line and position as fractions of the page. */
export async function readWords(url) {
  const doc = await pdfjs.getDocument(url).promise;
  const words = [];
  try {
    for (let p = 1; p <= doc.numPages; p++) {
      const page = await doc.getPage(p);
      const vp = page.getViewport({ scale: 1 });
      const { items } = await page.getTextContent();
      let line = 0;
      let lineTop = null;
      let current = [];
      const flush = () => {
        if (current.length && !PAGE_NUMBER.test(current.map((w) => w.raw).join(" "))) words.push(...current);
        current = [];
        line++;
      };
      items.forEach((item) => {
        if (item.str) {
          const tx = pdfjs.Util.transform(vp.transform, item.transform);
          const h = Math.hypot(tx[2], tx[3]) || 10;
          const top = tx[5] - h;
          if (lineTop != null && Math.abs(top - lineTop) > h / 2) flush();
          lineTop = top;
          for (const m of item.str.matchAll(/\S+/g)) {
            const t = normalise(m[0]);
            if (!t) continue;
            current.push({
              t,
              raw: m[0],
              page: p,
              line,
              rect: {
                top_position: top / vp.height,
                left_position: (tx[4] + (item.width * m.index) / item.str.length) / vp.width,
                highlight_width: (item.width * m[0].length) / item.str.length / vp.width,
                highlight_height: (h * 1.2) / vp.height,
              },
            });
          }
        }
        if (item.hasEOL) flush();
      });
      flush();
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
