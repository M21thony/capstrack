/** Word boxes on the same line joined into one box, so a marked passage reads as one highlight a line. */
export const mergeRectsByLine = (rects) => {
  if (!rects.length) return [];
  const sorted = [...rects].sort((a, b) => a.top_position - b.top_position);
  const merged = [];
  let current = { ...sorted[0] };
  for (let i = 1; i < sorted.length; i++) {
    const r = sorted[i];
    if (Math.abs(r.top_position - current.top_position) < 0.01) {
      const left = Math.min(current.left_position, r.left_position);
      const right = Math.max(current.left_position + current.highlight_width, r.left_position + r.highlight_width);
      const bottom = Math.max(current.top_position + current.highlight_height, r.top_position + r.highlight_height);
      current.left_position = left;
      current.highlight_width = right - left;
      current.highlight_height = bottom - current.top_position;
    } else {
      merged.push(current);
      current = { ...r };
    }
  }
  merged.push(current);
  return merged;
};

/**
 * Where each word and space of a piece of PDF text sits, as offsets into the
 * piece's width. A piece is often a whole line with one width, so the words'
 * own widths come from `measure` (the letters' widths in a like font, scaled
 * by `natural` to the PDF's). A justified line is wider than its letters: the
 * room left over went into the spaces between its words, so it is shared out
 * there. A line set left, centred or right is as wide as its letters already.
 *
 * @param {string} str
 * @param {number} width
 * @param {(text: string) => number} measure
 * @param {number} [natural]
 * @returns {Array<{text: string, space: boolean, left: number, width: number}>}
 */
export const layoutPiece = (str, width, measure, natural = 1) => {
  const runs = [...str.matchAll(/\s+|\S+/g)].map((m) => ({
    text: m[0],
    space: !m[0].trim(),
    inner: m.index > 0 && m.index + m[0].length < str.length,
    own: measure(m[0]) * natural,
  }));
  const total = runs.reduce((sum, r) => sum + r.own, 0);
  const gaps = runs.filter((r) => r.space && r.inner).length;
  const spare = width - total;
  // Less than a hundredth over is the like font being a little narrow, not justification.
  const spread = gaps && spare > width * 0.01 ? spare / gaps : 0;
  const fit = spread || !total ? 1 : width / total;
  let left = 0;
  return runs.map((r) => {
    const w = r.own * fit + (r.space && r.inner ? spread : 0);
    const out = { text: r.text, space: r.space, left, width: w };
    left += w;
    return out;
  });
};

/**
 * How the PDF's letters compare with the like font's, for one font on one
 * page: the narrowest ratio of a piece's width to its letters' widths. Some
 * line is always set unjustified (a heading, a paragraph's last line), and a
 * justified one only ever comes out wider.
 *
 * @param {Array<{str: string, width: number}>} pieces
 * @param {(text: string) => number} measure
 * @returns {number}
 */
export const naturalScale = (pieces, measure) => {
  const ratios = pieces
    .filter((p) => p.str.trim().length >= 4 && p.width > 0)
    .map((p) => p.width / measure(p.str))
    .filter((r) => Number.isFinite(r) && r > 0);
  return ratios.length ? Math.min(...ratios) : 1;
};
