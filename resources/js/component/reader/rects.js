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
