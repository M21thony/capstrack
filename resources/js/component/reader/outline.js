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
