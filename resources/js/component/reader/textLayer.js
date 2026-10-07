const SVG_NS = "http://www.w3.org/2000/svg";
const HTML_NS = "http://www.w3.org/1999/xhtml";
// What a label is written with. Anything else (shapes, pictures, links, scripts) is left out.
const TEXT_TAGS = new Set(["svg", "g", "switch", "foreignObject", "text", "tspan", "div", "span", "br", "p", "b", "i", "u", "s", "strong", "em", "font",
  "strike", "sub", "sup", "ul", "ol", "li", "table", "thead", "tbody", "tr", "td", "th", "pre", "code", "blockquote", "h1", "h2", "h3", "h4", "h5", "h6"]);
const TEXT_ATTRS = new Set(["x", "y", "dx", "dy", "width", "height", "transform", "style", "font-family", "font-size", "font-weight", "font-style",
  "text-anchor", "dominant-baseline", "letter-spacing", "colspan", "rowspan", "align", "valign", "face", "size",
  // Every label's box is the size of the whole drawing and lets the mouse through: without this the last one covers the rest.
  "pointer-events"]);
const UNSAFE_STYLE = /url\s*\(|expression|javascript:|@import|behavior/i;

/**
 * The words of draw.io's picture, laid out exactly where it draws them, to lie
 * see-through over the picture so a reviewer can select them. Rebuilt from
 * nothing but text and its layout, so nothing in a label can run or load.
 */
export function textLayer(svg) {
  const copy = (n) => {
    if (n.nodeType === 3) return document.createTextNode(n.data);
    if (n.nodeType !== 1) return null;
    const name = n.localName, html = n.namespaceURI === HTML_NS, parent = n.parentNode?.localName;
    // A label is written twice, as html and as plain text for old viewers: the html one is the one drawn.
    if (name === "text" && parent === "switch" && n.parentNode.getElementsByTagNameNS(SVG_NS, "foreignObject").length) return null;
    const tag = name === "a" ? (html ? "span" : parent === "text" || parent === "tspan" ? "tspan" : "g") : name;
    if (!TEXT_TAGS.has(tag)) return null;
    const el = document.createElementNS(html ? HTML_NS : SVG_NS, tag);
    if (n !== svg) {
      [...n.attributes].filter((a) => TEXT_ATTRS.has(a.name)).forEach((a) => {
        el.setAttribute(a.name, a.name === "style" ? a.value.split(";").filter((d) => !UNSAFE_STYLE.test(d)).join(";") : a.value);
      });
    }
    n.childNodes.forEach((c) => { const k = copy(c); if (k) el.append(k); });
    return el;
  };
  const out = copy(svg);
  out.setAttribute("viewBox", svg.getAttribute("viewBox"));
  out.setAttribute("width", "100%");
  out.setAttribute("height", "100%");
  return out;
}
