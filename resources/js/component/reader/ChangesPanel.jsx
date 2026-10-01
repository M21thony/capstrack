import React, { useEffect, useRef, useState } from "react";
import { pdfjs } from "react-pdf";
import { Icon, KIND_LABEL, PagedList, figureTitle, kindOf } from "./Reader";
import FigureCrop, { regionPixels } from "./FigureCrop";
import { boundsOf, isFigureRemark, marksOf } from "./figures";
import { PICTURE_CHANGED, findPicture, greyOf, labelsAt, pictureDifference } from "./revisionCheck";

/** A remark from the earlier version, placed where it is now. */
const atTarget = (r) => ({
  ...r,
  page_number: r.target.page,
  highlightRects: r.target.rects,
  x_position: r.target.x ?? r.x_position,
  y_position: r.target.y ?? r.y_position,
});

const clamp = (n) => Math.min(1, Math.max(0, n));

/**
 * A remark moved to where its diagram was found, or to the page the reviewer
 * chose. The labels inside a figure are compared again at the new place,
 * when the versions have words to compare.
 */
const relocate = (r, m, words) => {
  if (m.keep) return r;
  const move = (x) => ({ ...x, left_position: clamp(x.left_position + m.dx), top_position: clamp(x.top_position + m.dy) });
  if (r.type === "text") {
    return { ...r, foundBy: m.how, changed: null, target: { page: m.page, rects: (r.highlightRects || []).map(move) } };
  }
  const target = r.type === "pin"
    ? { page: m.page, x: clamp(r.x_position + m.dx), y: clamp(r.y_position + m.dy), rects: [] }
    : { page: m.page, rects: r.highlightRects.map(move) };
  const labels = words?.oldWords?.length || words?.newWords?.length
    ? labelsAt(r, target, words.oldWords, words.newWords)
    : { changed: null, removedWords: "", addedWords: "" };
  return { ...r, foundBy: m.how, target, ...labels };
};

/**
 * Looks for a remark's diagram on some pages of this version by its picture,
 * and says where it is - wherever it sits on the page. Null when it is not
 * clearly on any of them.
 */
async function searchPages(r, oldPdf, newPdf, pages) {
  const area = compared(r);
  const tplW = 100;
  const t = await regionPixels(oldPdf, r.page_number, area, tplW);
  const tpl = greyOf(t.data, t.width, t.height);
  const { width: oldWidth } = (await oldPdf.getPage(Number(r.page_number))).getViewport({ scale: 1 });
  const scale = tplW / (area.width * oldWidth);
  let best = null;
  for (const n of pages) {
    if (n < 1 || n > newPdf.numPages) continue;
    const { width } = (await newPdf.getPage(n)).getViewport({ scale: 1 });
    const p = await regionPixels(newPdf, n, { left: 0, top: 0, width: 1, height: 1 }, Math.round(width * scale));
    const hit = findPicture(greyOf(p.data, p.width, p.height), p.width, p.height, tpl, t.width, t.height);
    if (hit.found && (!best || hit.score > best.score)) {
      best = { page: n, dx: hit.x / p.width - area.left, dy: hit.y / p.height - area.top, score: hit.score };
    }
  }
  return best && (await lineUp(r, area, oldPdf, newPdf, best)) || best;
}

/**
 * True when the picture was found where the caption already put the figure,
 * to within a couple of pixels. The caption's place is then kept: text moves
 * by exact amounts, so it lays the two pictures over each other better than
 * a search can.
 */
const agrees = (r, hit) => {
  if (!hit.pixel || hit.page !== r.target.page) return false;
  const was = marksOf(r)[0];
  const now = r.type === "pin" ? { left_position: r.target.x, top_position: r.target.y } : r.target.rects[0];
  return Math.abs(now.left_position - was.left_position - hit.dx) <= hit.pixel.x * 2
    && Math.abs(now.top_position - was.top_position - hit.dy) <= hit.pixel.y * 2;
};

/**
 * A picture found on a whole page is only placed to within a few points,
 * since the page is searched small. That is enough to find it but not to lay
 * the two over each other, so it is looked for again, drawn at the size the
 * pictures are compared at, in a small window around where it was found.
 */
async function lineUp(r, area, oldPdf, newPdf, hit) {
  const fine = 400;
  const margin = 0.015;
  const t = await regionPixels(oldPdf, r.page_number, area, fine);
  const { width: oldWidth } = (await oldPdf.getPage(Number(r.page_number))).getViewport({ scale: 1 });
  const { width } = (await newPdf.getPage(hit.page)).getViewport({ scale: 1 });
  const scale = fine / (area.width * oldWidth);
  const win = { left: area.left + hit.dx - margin, top: area.top + hit.dy - margin, width: area.width + margin * 2, height: area.height + margin * 2 };
  const p = await regionPixels(newPdf, hit.page, win, Math.round(win.width * width * scale));
  const found = findPicture(greyOf(p.data, p.width, p.height), p.width, p.height, greyOf(t.data, t.width, t.height), t.width, t.height);
  if (!found.found) return null;
  return {
    ...hit,
    dx: win.left + (found.x / p.width) * win.width - area.left,
    dy: win.top + (found.y / p.height) * win.height - area.top,
    pixel: { x: win.width / p.width, y: win.height / p.height },
  };
}

const FOUND_BY = {
  picture: (r) => `Found by its picture · p.${r.target.page}`,
  picked: (r) => `You chose p.${r.target.page} · found the diagram there`,
  "picked, not matched": (r) => `You chose p.${r.target.page} · the diagram was not recognised there, so this is the same place on that page`,
  "not found": (r) => `Not found by its text or its picture · showing the same place, p.${r.target.page}`,
  "picked place": (r) => `You chose p.${r.target.page} · showing the same place on that page`,
  caption: (r) => `Found by its caption${r.figureNow && r.figureNow !== r.figure_label ? `, now ${r.figureNow}` : ""} · p.${r.target.page}`,
  labels: (r) => `Found by the labels inside it · p.${r.target.page}`,
  "nearby text": (r) => `Found by the text around it · p.${r.target.page}`,
  "same place": (r) => `Not found by its text · showing the same place, p.${r.target.page}`,
  words: (r) => `p.${r.target.page}`,
};

/** The part of the page a picture check compares: the box itself, or a small area around a pin. */
const compared = (r) => {
  if (r.type === "pin") {
    const x = Number(r.x_position) || 0;
    const y = Number(r.y_position) || 0;
    const left = Math.min(Math.max(0, x - 0.1), 0.8);
    const top = Math.min(Math.max(0, y - 0.06), 0.88);
    return { left, top, width: 0.2, height: 0.12 };
  }
  const b = boundsOf(r.highlightRects);
  return { left: b.left_position, top: b.top_position, width: Math.max(0.02, b.highlight_width), height: Math.max(0.02, b.highlight_height) };
};

/** The earlier version, open for snapshots and picture checks while this panel is. */
function useEarlierPdf(url) {
  const [doc, setDoc] = useState(null);
  useEffect(() => {
    if (!url) return undefined;
    const task = pdfjs.getDocument(url);
    let loaded = null;
    task.promise.then((d) => { loaded = d; setDoc(d); }).catch(() => setDoc(null));
    return () => { setDoc(null); (loaded ? loaded.destroy() : task.destroy()).catch?.(() => {}); };
  }, [url]);
  return doc;
}

/**
 * For each remark on a figure: whether the picture itself changed, by laying
 * the same region of both versions over each other. Words cannot tell when a
 * diagram is a picture, so this is what decides then.
 */
function usePictureChecks(items, oldPdf, newPdf) {
  const [checks, setChecks] = useState({});
  // A figure still being placed is checked once it is placed, even where it stays.
  const key = items.map((r) => `${r.id}:${r.unsettled ? "?" : ""}${r.target?.page}:${r.target?.rects?.[0]?.top_position ?? r.target?.y}`).join("|");
  useEffect(() => {
    if (!oldPdf || !newPdf) return undefined;
    let alive = true;
    setChecks({});
    (async () => {
      for (const r of items) {
        if (!alive) return;
        if (!isFigureRemark(r) || !r.target || LOST.includes(r.foundBy) || r.unsettled) continue;
        try {
          // Only what the reviewer marked: the box itself, or a little around a
          // pin - not the margin a snapshot shows, which can catch the text
          // around the figure and call an unchanged figure changed.
          const before = compared(r);
          const after = compared(atTarget(r));
          const width = 400;
          const a = await regionPixels(oldPdf, r.page_number, before, width);
          const differs = async (x, y) => {
            const at = { left: after.left + (x * before.width) / width, top: after.top + (y * before.height) / a.height, width: before.width, height: before.height };
            const b = await regionPixels(newPdf, r.target.page, at, width, a.height);
            return pictureDifference(a.data, b.data, a.width, a.height).share;
          };
          // A figure found by its picture is placed to a fraction of a pixel,
          // and half a pixel off is enough to make every thin line differ. So
          // a picture that looks changed is laid over again half a pixel each
          // way: a real change stays, a misplacement does not.
          let share = await differs(0, 0);
          for (const [x, y] of [[0, -0.5], [0, 0.5], [-0.5, 0], [0.5, 0], [-0.5, -0.5], [0.5, -0.5], [-0.5, 0.5], [0.5, 0.5]]) {
            if (share <= PICTURE_CHANGED || !alive) break;
            share = Math.min(share, await differs(x, y));
          }
          if (alive) setChecks((c) => ({ ...c, [r.id]: { share, changed: share > PICTURE_CHANGED } }));
        } catch { /* a page that would not draw: the words still speak */ }
      }
    })();
    return () => { alive = false; };
  }, [oldPdf, newPdf, key]); // eslint-disable-line react-hooks/exhaustive-deps
  return checks;
}

/** Places the check could not confirm: it shows them, but says nothing about what changed there. */
const LOST = ["not found", "picked, not matched", "picked place"];

/** What was found at a remark's place, in words. */
function finding(r, picture, pending = false) {
  if (pending) return { tone: "", text: "Looking for this diagram by its picture…" };
  if (!r.target) return { tone: "", text: "Could not find these words in this version. Use the page arrows in Compare to look for them, then decide." };
  if (r.foundBy === "picked place") return { tone: "", text: "The words could not be checked here. Look, then decide." };
  if (LOST.includes(r.foundBy)) return { tone: "", text: "Could not find this diagram in this version. Use the page arrows in Compare to look for it, then decide." };
  if (!isFigureRemark(r)) {
    if (!r.changed) return { tone: "", text: "The quoted words did not change." };
    return { tone: "mod", text: "The quoted words changed.", removed: r.removedWords, added: r.addedWords };
  }
  if (r.changed) return { tone: "mod", text: "Labels in the figure changed.", removed: r.removedWords, added: r.addedWords };
  if (picture?.changed) return { tone: "mod", text: "The picture changed." };
  if (picture) return { tone: "", text: r.changed === false ? "No change found in its labels or its picture." : "The picture looks the same." };
  return { tone: "", text: r.changed === false ? "No change found in its labels. Comparing the picture…" : "Comparing the picture…" };
}

function Finding({ f }) {
  return (
    <div className="rr-finding">
      <span>{f.text}</span>
      {(f.removed || f.added) && (
        <div className="rr-diffline">
          {f.removed && <del>{f.removed}</del>}
          {f.removed && f.added && " "}
          {f.added && <ins>{f.added}</ins>}
        </div>
      )}
    </div>
  );
}

function Verdict({ status, onSet, round }) {
  return (
    <div className="rr-row rr-verdict" role="group" aria-label={`Was this fixed in ${round || "this version"}?`}>
      <button type="button" className="rr-btn sm rr-fix" aria-pressed={status === "fixed"} onClick={(e) => { e.stopPropagation(); onSet("fixed"); }}>Fixed</button>
      <button type="button" className="rr-btn sm rr-notyet" aria-pressed={status === "not_fixed"} onClick={(e) => { e.stopPropagation(); onSet("not_fixed"); }}>Not yet</button>
    </div>
  );
}

/** Before and after, side by side, for one remark. */
function Compare({ item, oldPdf, newPdf, picture, status, onSet, onClose, base, onPage, looking }) {
  const ref = useRef(null);
  useEffect(() => {
    const d = ref.current;
    if (item && d && !d.open) d.showModal();
    if (!item && d?.open) d.close();
  }, [item]);
  const width = Math.min(440, ((typeof window !== "undefined" ? window.innerWidth : 1000) - 120) / 2);
  return (
    <dialog ref={ref} className="rr-dialog rr-zoom" aria-labelledby="rr-compare-title" onClose={onClose} onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      {item && (
        <div className="rr-dlg">
          <div className="rr-zoom-head">
            <div>
              <h2 id="rr-compare-title">{isFigureRemark(item) ? figureTitle(item) : `“${item.selectedText || "Remark"}”`}</h2>
              <p className="rr-muted">{item.text}</p>
            </div>
            <button type="button" className="rr-btn icon sm ghost" aria-label="Close" onClick={onClose}><Icon name="x" /></button>
          </div>
          <div className="rr-compare">
            <div>
              <span className="rr-muted">{base?.round || "Earlier version"} · p.{item.page_number}</span>
              {oldPdf && <FigureCrop pdf={oldPdf} remark={item} maxWidth={width} maxHeight={300} pad={0.04} />}
            </div>
            <div>
              <span className="rr-muted rr-pagepick">
                This version ·
                <button type="button" className="rr-btn icon sm ghost" aria-label="Look on the page before" disabled={looking || (item.target?.page ?? item.page_number) <= 1} onClick={() => onPage(-1)}><Icon name="left" /></button>
                {looking ? "looking…" : item.target ? `p.${item.target.page}` : "not found"}
                <button type="button" className="rr-btn icon sm ghost" aria-label="Look on the page after" disabled={looking || (item.target?.page ?? item.page_number) >= (newPdf?.numPages || 1)} onClick={() => onPage(1)}><Icon name="right" /></button>
              </span>
              {newPdf && item.target && <FigureCrop pdf={newPdf} remark={atTarget(item)} maxWidth={width} maxHeight={300} pad={0.04} pending />}
            </div>
          </div>
          <Finding f={finding(item, picture)} />
          <Verdict status={status} onSet={onSet} round={base?.round} />
        </div>
      )}
    </dialog>
  );
}


const KIND = { added: ["Added", "add"], removed: ["Removed", "del"], reworded: ["Reworded", "mod"] };

/**
 * What changed since an earlier version: the passages that differ, and each
 * remark left on that version with whether the words near it changed.
 * Nothing here is saved - it is worked out again every time the page opens.
 */
export default function ChangesPanel({
  top, base, diff, selectedChange, onOpenChange, onOpenRemark,
  pdf = null, revisionStatus = {}, onRevision = () => {}, onOpenRevision = () => {},
}) {
  const ready = diff.status === "ready";
  const changes = ready ? diff.changes : [];
  // Where the reviewer, or the picture search, put a remark instead.
  const [moved, setMoved] = useState({});
  const [looking, setLooking] = useState(null);
  const revision = (diff.revision || []).map((r) => (moved[r.id]
    ? relocate(r, moved[r.id], diff)
    : isFigureRemark(r) && ["same place", "nearby text", "labels", "caption"].includes(r.foundBy) ? { ...r, unsettled: true } : r));
  const [filter, setFilter] = useState("remarks");
  const [comparing, setComparing] = useState(null);
  const oldPdf = useEarlierPdf(revision.length ? base?.file : null);
  const pictures = usePictureChecks(revision, oldPdf, pdf);

  // Every figure is lined up by its picture. A caption is sure about the
  // page, so its figure is only looked for on that page, to place the box
  // exactly: a figure that grew taller moves its caption down further than
  // its top, and moving the box by the caption alone would catch the text
  // above it. Placed any other way - by the labels inside it, the text around
  // it, or nowhere - it is looked for on the pages around where the words put
  // it and where it was before, anywhere on each page. Text around a figure
  // can be reworded into the same phrases somewhere else, so it never decides
  // alone: if the picture is not found, the remark says so instead of
  // pointing at the wrong place. A caption, or labels inside a drawn diagram,
  // still stand when the picture changed too much to be recognised.
  const unsure = (r) => isFigureRemark(r) && ["same place", "nearby text", "labels", "caption"].includes(r.foundBy);
  const unplaced = (diff.revision || []).filter((r) => unsure(r) && !moved[r.id]).map((r) => r.id).join(",");
  useEffect(() => {
    if (!oldPdf || !pdf || !unplaced) return undefined;
    let alive = true;
    (async () => {
      for (const r of diff.revision) {
        if (!alive || !unsure(r) || moved[r.id]) continue;
        const at = r.target.page;
        const was = r.page_number;
        const pages = r.foundBy === "caption" ? [at] : [...new Set([at, was, at + 1, at - 1, was + 1, was + 2])];
        const hit = await searchPages(r, oldPdf, pdf, pages).catch(() => null);
        if (!alive) return;
        const stands = r.foundBy === "caption" || r.foundBy === "labels";
        const place = !hit
          ? stands ? { keep: true } : { page: was, dx: 0, dy: 0, how: "not found" }
          : r.foundBy === "caption" && agrees(r, hit) ? { keep: true }
            : { page: hit.page, dx: hit.dx, dy: hit.dy, how: r.foundBy === "caption" ? "caption" : "picture" };
        setMoved((m) => ({ ...m, [r.id]: place }));
      }
    })();
    return () => { alive = false; };
  }, [oldPdf, pdf, unplaced]); // eslint-disable-line react-hooks/exhaustive-deps

  // The reviewer points to another page: the diagram is looked for there.
  const pickPage = async (r, step) => {
    const page = (r.target?.page ?? r.page_number) + step;
    setLooking(r.id);
    const hit = isFigureRemark(r) && oldPdf && pdf ? await searchPages(r, oldPdf, pdf, [page]).catch(() => null) : null;
    const missed = isFigureRemark(r) ? "picked, not matched" : "picked place";
    setMoved((m) => ({ ...m, [r.id]: hit ? { page, dx: hit.dx, dy: hit.dy, how: "picked" } : { page, dx: 0, dy: 0, how: missed } }));
    setLooking(null);
  };
  const statusOf = (r) => (r.id in revisionStatus ? revisionStatus[r.id] : r.revision_status) || null;
  const fixed = revision.filter((r) => statusOf(r) === "fixed").length;
  const notFixed = revision.filter((r) => statusOf(r) === "not_fixed").length;
  const comparingItem = comparing != null ? revision.find((r) => r.id === comparing) : null;

  const message = {
    idle: null,
    loading: [`Comparing with ${base?.round || "the earlier version"}…`, "This takes a moment on long documents."],
    notext: ["No text to compare", "One of the versions has no text to read - it may be scanned, or only diagrams. Remarks on figures are still checked below by their pictures."],
    unrelated: [
      "This may be the wrong file",
      `It shares almost no text with ${base?.round || "the earlier version"} (${Math.round((diff.shared || 0) * 100)}% of its phrases). A revision keeps most of its words, so this looks like a different document - another chapter, perhaps. Open it to check, or send it back.`,
    ],
    error: ["Could not compare", "The earlier version did not load. Reload the page to try again."],
  }[diff.status];

  return (
    <aside className="rr-side" aria-label="What changed">
      {top}
      <div className="rr-side-head">
        <h2>What changed <span className="rr-count">{changes.length}</span></h2>
      </div>
      <p className="rr-help">
        Since <b>{base?.round}</b>. New and reworded words are underlined in green; a red mark shows where words were removed.
      </p>
      <div className="rr-side-body">
        {message && !(diff.status === "notext" && revision.length) ? (
          <div className="rr-holder"><div className="rr-empty"><b>{message[0]}</b><span>{message[1]}</span></div></div>
        ) : (
          <>
            <div className="rr-filter" role="group" aria-label="Show">
              {[["remarks", "Your remarks", revision.length], ["all", "All changes", changes.length]].map(([k, label, n]) => (
                <button key={k} type="button" className="rr-fbtn" aria-pressed={filter === k} onClick={() => setFilter(k)}>
                  {label} <span className="rr-fc">{n}</span>
                </button>
              ))}
            </div>
            {filter === "all" ? (
              <PagedList
                items={changes}
                keyOf={(c) => c.id}
                noun="change"
                focusKey={selectedChange}
                empty={<><b>No text changed</b><span>The words match {base?.round}. Formatting and pictures are not compared.</span></>}
                renderItem={(c, measuring) => (
                  <div
                    className="rr-card text"
                    role={measuring ? undefined : "button"}
                    tabIndex={measuring ? -1 : 0}
                    aria-current={selectedChange === c.id}
                    onClick={() => onOpenChange(c)}
                    onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); onOpenChange(c); } }}
                  >
                    <div className="rr-card-r1">
                      <span className={`rr-tag ${KIND[c.kind][1]}`}>{KIND[c.kind][0]}</span>
                      <span className="rr-where">p.{c.page}</span>
                    </div>
                    <div className="rr-diffline">
                      {c.before && <>…{c.before} </>}
                      {c.oldText && <del>{c.oldText}</del>}
                      {c.oldText && c.newText && " "}
                      {c.newText && <ins>{c.newText}</ins>}
                      {c.after && <> {c.after}…</>}
                    </div>
                  </div>
                )}
              />
            ) : (
              <>
                {diff.status === "notext" && <p className="rr-help rr-help-tight">{message?.[1]}</p>}
                {revision.length > 0 && (
                  <div className="rr-revsum" aria-label="Revision check so far">
                    <span className="rr-chip ok">{fixed} fixed</span>
                    <span className="rr-chip warn">{notFixed} not yet</span>
                    <span className="rr-chip">{revision.length - fixed - notFixed} to check</span>
                  </div>
                )}
                <PagedList
                  items={revision}
                  keyOf={(r) => r.id}
                  noun="remark"
                  empty={<><b>No remarks on {base?.round}</b><span>You left no remarks on that version.</span></>}
                  renderItem={(r, measuring) => {
                    const f = finding(r, pictures[r.id], unsure(r) && !moved[r.id]);
                    const status = statusOf(r);
                    return (
                      <div
                        className={`rr-card text${status === "fixed" ? " is-fixed" : status === "not_fixed" ? " is-notyet" : ""}`}
                        role={measuring ? undefined : "button"}
                        tabIndex={measuring ? -1 : 0}
                        aria-disabled={!r.target}
                        onClick={() => r.target && onOpenRevision(r)}
                        onKeyDown={(e) => { if (e.target === e.currentTarget && (e.key === "Enter" || e.key === " ")) { e.preventDefault(); if (r.target) onOpenRevision(r); } }}
                      >
                        <div className="rr-card-r1">
                          {isFigureRemark(r)
                            ? <span className="rr-tag fig">{r.type === "pin" ? "Pin" : "Figure"}</span>
                            : <span className="rr-tag">Highlighted text</span>}
                          <span className={`rr-tag ${f.tone || ""}`}>{f.tone ? "Changed" : r.target && !LOST.includes(r.foundBy) ? "No change" : "Not found"}</span>
                          <span className="rr-where">p.{r.page_number} on {base?.round}</span>
                        </div>
                        {isFigureRemark(r) ? <b className="rr-figname">{figureTitle(r)}</b> : r.selectedText && <div className="rr-q">“{r.selectedText}”</div>}
                        <div className="rr-t">{r.text}</div>
                        <span className="rr-lbl"><span className={`rr-sw c-${kindOf(r.color)}`} />{KIND_LABEL[kindOf(r.color)]}</span>
                        <Finding f={f} />
                        {r.target && <span className="rr-muted rr-foundby">{(FOUND_BY[r.foundBy] || FOUND_BY.words)(r)}</span>}
                        <div className="rr-row">
                          <button type="button" className="rr-btn sm" tabIndex={measuring ? -1 : 0} onClick={(e) => { e.stopPropagation(); setComparing(r.id); }}>Compare</button>
                          <span className="rr-spacer" />
                          <Verdict status={status} onSet={(v) => onRevision(r, v)} round={base?.round} />
                        </div>
                      </div>
                    );
                  }}
                />
              </>
            )}
          </>
        )}
      </div>
      <Compare
        item={comparingItem}
        oldPdf={oldPdf}
        newPdf={pdf}
        picture={comparingItem ? pictures[comparingItem.id] : null}
        status={comparingItem ? statusOf(comparingItem) : null}
        onSet={(v) => comparingItem && onRevision(comparingItem, v)}
        onClose={() => setComparing(null)}
        base={base}
        onPage={(step) => comparingItem && pickPage(comparingItem, step)}
        looking={comparingItem != null && looking === comparingItem.id}
      />
    </aside>
  );
}
