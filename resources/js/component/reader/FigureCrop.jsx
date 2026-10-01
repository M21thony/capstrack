import React, { useEffect, useRef } from "react";
import { cropOf, marksOf } from "./figures";

// The kinds' colours, solid, for outlines drawn on a snapshot.
const KIND_STROKE = { pink: "#dc2626", orange: "#ea580c", yellow: "#ca8a04", blue: "#0284c7", green: "#16a34a" };

// Pages drawn once per size and reused, since a list of cards asks for the
// same page over and over. A few sizes per page at most.
const drawn = new WeakMap();
function pageCanvas(page, scale) {
  let sizes = drawn.get(page);
  if (!sizes) { sizes = new Map(); drawn.set(page, sizes); }
  if (!sizes.has(scale)) {
    if (sizes.size >= 3) sizes.delete(sizes.keys().next().value);
    sizes.set(scale, (async () => {
      const viewport = page.getViewport({ scale });
      const canvas = document.createElement("canvas");
      canvas.width = Math.round(viewport.width);
      canvas.height = Math.round(viewport.height);
      await page.render({ canvasContext: canvas.getContext("2d"), viewport }).promise;
      return canvas;
    })());
  }
  return sizes.get(scale);
}

/**
 * A snapshot of the part of the page a remark is on, with the remark drawn
 * over it: the box, the pin, or the highlighted words. Cut from the PDF itself,
 * so it shows exactly what the reviewer marked.
 */
export default function FigureCrop({ pdf, remark, maxWidth, maxHeight, pad, pending = false }) {
  const ref = useRef(null);
  const rects = marksOf(remark);
  const key = JSON.stringify([remark.page_number, remark.type, remark.color, rects, pad, pending]);

  useEffect(() => {
    if (!pdf || !remark.page_number) return undefined;
    let alive = true;
    (async () => {
      const page = await pdf.getPage(Number(remark.page_number));
      const base = page.getViewport({ scale: 1 });
      const crop = cropOf(remark, pad);
      const ratio = (crop.height * base.height) / (crop.width * base.width);
      let w = maxWidth;
      let h = w * ratio;
      if (h > maxHeight) { h = maxHeight; w = h / ratio; }
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const scale = Math.min(6000 / base.width, Math.max(0.25, Math.ceil(((w * dpr) / (crop.width * base.width)) * 4) / 4));
      const source = await pageCanvas(page, scale);
      const canvas = ref.current;
      if (!alive || !canvas) return;
      canvas.width = Math.max(1, Math.round(w * dpr));
      canvas.height = Math.max(1, Math.round(h * dpr));
      canvas.style.width = `${w}px`;
      canvas.style.height = `${h}px`;
      const ctx = canvas.getContext("2d");
      ctx.fillStyle = "#fff";
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(source, crop.left * source.width, crop.top * source.height, crop.width * source.width, crop.height * source.height, 0, 0, canvas.width, canvas.height);

      const x = (f) => ((f - crop.left) / crop.width) * canvas.width;
      const y = (f) => ((f - crop.top) / crop.height) * canvas.height;
      const colour = pending ? "#18191f" : (KIND_STROKE[remark.color] || KIND_STROKE.yellow);
      ctx.lineWidth = 2 * dpr;
      ctx.strokeStyle = colour;
      if (remark.type === "pin") {
        const r = rects[0];
        ctx.fillStyle = pending ? "#c9a82c" : colour;
        ctx.beginPath();
        ctx.arc(x(r.left_position), y(r.top_position), 6 * dpr, 0, Math.PI * 2);
        ctx.fill();
        ctx.lineWidth = 2 * dpr;
        ctx.strokeStyle = "#fff";
        ctx.stroke();
      } else if (remark.type === "box") {
        if (pending) ctx.setLineDash([6 * dpr, 4 * dpr]);
        rects.forEach((r) => ctx.strokeRect(x(r.left_position), y(r.top_position), (r.highlight_width / crop.width) * canvas.width, (r.highlight_height / crop.height) * canvas.height));
      } else {
        ctx.globalAlpha = 0.3;
        ctx.fillStyle = colour;
        rects.forEach((r) => ctx.fillRect(x(r.left_position), y(r.top_position), (r.highlight_width / crop.width) * canvas.width, (r.highlight_height / crop.height) * canvas.height));
        ctx.globalAlpha = 1;
      }
    })().catch(() => {});
    return () => { alive = false; };
  }, [pdf, key, maxWidth, maxHeight]); // eslint-disable-line react-hooks/exhaustive-deps

  return <canvas ref={ref} className="rr-crop" aria-hidden="true" />;
}
