/**
 * The PDF's own bookmarks as one flat list, in reading order. Each entry knows
 * the page it opens and the entries it sits under. Empty when the file was
 * saved without bookmarks.
 */
export async function readOutline(pdf) {
  const flat = [];
  const walk = async (nodes, path) => {
    for (const node of nodes || []) {
      let page = null;
      try {
        const dest = typeof node.dest === "string" ? await pdf.getDestination(node.dest) : node.dest;
        const ref = dest?.[0];
        if (ref != null) page = (typeof ref === "object" ? await pdf.getPageIndex(ref) : ref) + 1;
      } catch { /* a bookmark that points nowhere stays in the list, unclickable */ }
      const id = flat.length;
      flat.push({ id, title: node.title, page, path, kids: Boolean(node.items?.length) });
      await walk(node.items, [...path, id]);
    }
  };
  await walk(await pdf.getOutline(), []);
  return flat;
}

const ROMAN = { i: 1, ii: 2, iii: 3, iv: 4, v: 5 };

/** Where a page sits in the bookmarks, as "Chapter 2 › 2.1 Related Literature". Empty when there are none. */
export function sectionAt(outline, page) {
  const it = outline.findLast((o) => o.page && o.page <= page);
  if (!it) return "";
  const names = [...it.path, it.id].map((id) => (outline[id].title || "").trim());
  // "CHAPTER II - REVIEW…" becomes the list's own "Chapter 2"
  const ch = names[0].match(/^chapter\s+(\d+|[iv]+)\b/i);
  if (ch) names[0] = `Chapter ${ROMAN[ch[1].toLowerCase()] || ch[1]}`;
  return names.filter(Boolean).join(" › ").slice(0, 150); // the server keeps 150 characters
}
