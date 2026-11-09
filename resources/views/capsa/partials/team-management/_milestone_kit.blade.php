{{-- What the milestone list and the milestone pages share: their look, one
     way to save through the JSON endpoints, and drag to reorder. --}}
<style nonce="{{ Vite::cspNonce() }}">
  /* The add/edit forms: a dialog-style card. */
  .tmx .mdg{width:min(560px,100%);margin:8px auto 0;background:var(--card);border:1px solid var(--line);border-radius:12px;box-shadow:0 10px 30px rgba(15,23,42,.08);box-sizing:border-box;color:var(--ink)}
  .tmx .mdg-h{display:flex;align-items:center;gap:10px;padding:16px 20px;border-bottom:1px solid var(--line)}
  .tmx .mdg-h h1{flex:1;margin:0;font-size:17px;font-weight:700;color:var(--ink)}
  .tmx .mdg-h small{display:block;font-size:12.5px;font-weight:500;color:var(--mut)}
  .tmx a.mdg-x{width:30px;height:30px;border-radius:7px;display:grid;place-items:center;color:var(--mut);text-decoration:none}
  .tmx a.mdg-x:hover{background:var(--tint);color:var(--ink)}
  .tmx .mdg-b{padding:18px 20px;display:flex;flex-direction:column;gap:14px}
  .tmx .mdg-f{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:var(--ink);margin:0;min-width:0}
  .tmx .mdg-f[hidden]{display:none}
  .tmx .mdg-f i{font-style:normal;font-weight:500;color:var(--mut)}
  .tmx .mdg-in{min-height:36px;border:1px solid var(--line2);border-radius:7px;padding:0 11px;font-family:inherit;font-size:14px;font-weight:400;color:var(--ink);background:var(--card);width:100%;box-sizing:border-box}
  .tmx textarea.mdg-in{padding:9px 11px;line-height:1.5;resize:vertical;min-height:84px}
  .tmx .mdg-in:focus-visible,.tmx .mdg-chk input:focus-visible,.tmx .mdg-btn:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
  .tmx .mdg-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .tmx .mdg-chk{display:flex;gap:10px;align-items:center;font-size:13.5px;font-weight:500;color:var(--ink);cursor:pointer;margin:0}
  .tmx .mdg-chk input{width:17px;height:17px;margin:0;accent-color:var(--accent);flex:none}
  .tmx .mdg-err{color:var(--red);font-size:13px;margin:0}
  .tmx .mdg-err:empty{display:none}
  .tmx .mdg-foot{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:14px 20px;border-top:1px solid var(--line)}
  .tmx .mdg-foot .grow{flex:1}
  .tmx .mdg-btn{min-height:36px;padding:0 14px;border-radius:7px;border:1px solid var(--line2);background:var(--card);color:var(--ink);font-family:inherit;font-size:13.5px;font-weight:600;display:inline-flex;gap:6px;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;white-space:nowrap;box-sizing:border-box}
  .tmx .mdg-btn:hover:not([disabled]){border-color:var(--ink2);color:var(--ink)}
  .tmx .mdg-btn[disabled]{opacity:.6;cursor:default}
  .tmx .mdg-btn.p{background:var(--accent);border-color:var(--accent);color:var(--on-accent)}
  .tmx .mdg-btn.p:hover:not([disabled]){filter:brightness(1.06);color:var(--on-accent)}
  .tmx .mdg-btn.q{border-color:transparent;background:transparent;color:var(--ink2);padding:0 6px}
  .tmx .mdg-btn.danger{border-color:transparent;background:transparent;color:var(--red);padding:0 6px}
  @media (max-width:640px){
    .tmx .mdg{margin-top:0}
    .tmx .mdg-h,.tmx .mdg-b,.tmx .mdg-foot{padding-left:16px;padding-right:16px}
    .tmx .mdg-row{grid-template-columns:minmax(0,1fr)}
  }
  .ml-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}
  .ml-title{font-size:22px;font-weight:700;color:var(--text);letter-spacing:-.01em;margin:0}
  .ml-sub{font-size:13px;color:var(--text3);margin-top:3px;max-width:62ch;line-height:1.5}
  .ml-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
  .ml-tabs{display:inline-flex;gap:4px;padding:4px;border:1px solid var(--border);border-radius:10px;background:var(--bg2)}
  .ml-tab{font-size:13px;font-weight:600;padding:7px 14px;border-radius:7px;color:var(--text3);text-decoration:none;display:inline-flex;align-items:center;gap:6px}
  .ml-tab[aria-current="page"]{background:var(--bg3);color:var(--text)}
  .ml-count{font-variant-numeric:tabular-nums;padding:1px 7px;border-radius:6px;background:var(--bg4);font-size:11px;color:var(--text2)}
  .ml-sort{display:inline-flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text3)}
  .ml-sort select,.ml-f input,.ml-f select,.ml-choice select{font:inherit;font-size:13px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg2);color:var(--text)}
  .ml-board{margin-bottom:22px}
  .ml-board-h{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:0 0 8px}
  .ml-board-h h2{font-size:14px;font-weight:700;color:var(--text2);margin:0}
  .ml-board-h small{font-weight:500;color:var(--text3);margin-left:6px}
  .ml-list{border:1px solid var(--border);border-radius:12px;background:var(--bg2);overflow:hidden}
  .ml-row{display:flex;gap:12px;align-items:flex-start;padding:14px 18px;border-top:1px solid var(--border);background:var(--bg2)}
  .ml-list > :first-child{border-top:0}
  .ml-row.is-drag{opacity:.45}
  .ml-grip{flex:none;border:0;background:none;padding:2px;margin-top:1px;color:var(--text3);cursor:grab;border-radius:6px;line-height:0}
  .ml-grip:hover,.ml-grip:focus-visible{color:var(--text);background:var(--bg3)}
  .ml-main{flex:1;min-width:0;display:grid;gap:5px}
  .ml-name{font-size:15.5px;font-weight:700;color:var(--text);line-height:1.3;text-decoration:none}
  a.ml-name:hover{text-decoration:underline}
  .ml-req{font-size:14px;font-weight:600;color:var(--text)}
  .ml-meta{display:flex;flex-wrap:wrap;align-items:center;gap:4px 10px;font-size:12.5px;color:var(--text3)}
  .ml-late{color:var(--red);font-weight:600}
  .ml-prog{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:3px;font-size:12.5px;color:var(--text3)}
  .ml-prog b{color:var(--text);font-variant-numeric:tabular-nums}
  .ml-meter{width:200px;max-width:100%;height:8px;border-radius:999px;background:var(--bg4);overflow:hidden}
  .ml-meter i{display:block;height:100%;border-radius:999px;background:var(--green)}
  .ml-acts{display:flex;gap:8px;flex:none;flex-wrap:wrap}
  .ml-btn{font:inherit;font-size:12.5px;font-weight:600;padding:7px 13px;border-radius:8px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;
          border:1px solid var(--border);background:var(--bg2);color:var(--text2)}
  .ml-btn:hover:not([disabled]){color:var(--text);border-color:var(--text3)}
  .ml-btn[disabled]{opacity:.6;cursor:default}
  .ml-gold{background:var(--accent);border-color:var(--accent);color:var(--on-accent,#18191f)}
  .ml-gold:hover:not([disabled]){color:var(--on-accent,#18191f);border-color:var(--accent);filter:brightness(1.05)}
  .ml-red{color:var(--red)}
  .ml-red:hover:not([disabled]){color:var(--red);border-color:var(--red)}
  .ml-cap{font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--text3);padding:8px 18px;background:var(--bg3);border-top:1px solid var(--border)}
  .ml-empty{padding:28px 18px;text-align:center;font-size:13px;color:var(--text3)}
  .ml-setup{border:1px dashed var(--border);border-radius:12px;padding:14px 18px;margin-bottom:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .ml-setup > div{flex:1;min-width:200px;font-size:13px;color:var(--text3);line-height:1.5}
  .ml-setup b{display:block;color:var(--text);font-weight:600}
  .ml-f{display:flex;flex-direction:column;gap:5px;font-size:13px;font-weight:600;color:var(--text);margin-bottom:16px}
  .ml-f small{font-weight:400;color:var(--text3);font-size:12px}
  .ml-f input[type=date]{max-width:200px}
  .ml-check{display:flex;gap:8px;align-items:flex-start;font-size:13px;color:var(--text);margin-bottom:12px;cursor:pointer}
  .ml-check input{margin-top:3px}
  .ml-note{font-size:12.5px;color:var(--text3);margin-bottom:12px}
  .ml-foot{display:flex;gap:8px;justify-content:flex-end;border-top:1px solid var(--border);padding-top:14px;margin-top:8px}
  .ml-danger{border:1px solid color-mix(in srgb,var(--red) 40%,var(--border));border-radius:10px;padding:14px 16px;margin-top:24px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .ml-danger > div{flex:1;min-width:200px;font-size:12.5px;color:var(--text3);line-height:1.5}
  .ml-danger b{display:block;color:var(--text);font-size:13px;font-weight:600}
  .ml-choice{display:flex;gap:10px;align-items:flex-start;border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:8px;cursor:pointer;background:var(--bg2);font-size:13px;color:var(--text3);line-height:1.5}
  .ml-choice:has(input:checked){border-color:var(--accent);box-shadow:0 0 0 1px var(--accent)}
  .ml-choice input[type=radio]{margin-top:3px}
  .ml-choice b{display:block;color:var(--text);font-weight:600}
  .ml-choice .ml-more{display:none;margin-top:8px}
  .ml-choice:has(input:checked) .ml-more{display:block}
  .ml-err{color:var(--red);font-size:12.5px;margin:-4px 0 12px}
  .ml-err:empty{display:none}
  @media (max-width:640px){
    .ml-row{flex-wrap:wrap}
    .ml-acts{width:100%}
    .ml-acts .ml-btn{flex:1;justify-content:center}
  }
</style>
<script nonce="{{ Vite::cspNonce() }}">
  /** Sends JSON to one of the checklist endpoints; throws with the server's own words when it refuses. */
  window.mlSend = async function (url, method, body) {
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const first = data.errors ? Object.values(data.errors)[0] : null;
      throw new Error((first && first[0]) || data.message || 'That did not save. Please try again.');
    }
    return data;
  };

  window.mlFail = function (error) {
    outcomeDialog({ tone: 'error', title: 'That did not save', reason: error.message });
  };

  /**
   * Drag rows by their grip, or move them with the arrow keys on it. Once a
   * row lands somewhere new, save() gets every row's id in the new order.
   */
  window.mlSortable = function (list, save) {
    let dragged = null;
    let before = '';
    const ids = () => [...list.querySelectorAll('[data-id]')].map(row => Number(row.dataset.id));
    const commit = () => {
      if (ids().join() !== before) {
        save(ids()).catch(error => { mlFail(error); setTimeout(() => location.reload(), 1500); });
      }
    };
    const step = (row, up) => {
      let sibling = up ? row.previousElementSibling : row.nextElementSibling;
      while (sibling && !sibling.dataset.id) {
        sibling = up ? sibling.previousElementSibling : sibling.nextElementSibling;
      }
      return sibling;
    };

    list.addEventListener('mousedown', event => {
      const row = event.target.closest('.ml-grip') && event.target.closest('[data-id]');
      if (row) { row.draggable = true; }
    });
    list.addEventListener('dragstart', event => {
      dragged = event.target.closest('[data-id]');
      before = ids().join();
      dragged.classList.add('is-drag');
      event.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragover', event => {
      if (!dragged) { return; }
      event.preventDefault();
      const over = event.target.closest('[data-id]');
      if (!over || over === dragged) { return; }
      const box = over.getBoundingClientRect();
      list.insertBefore(dragged, event.clientY < box.top + box.height / 2 ? over : over.nextSibling);
    });
    list.addEventListener('dragend', () => {
      if (!dragged) { return; }
      dragged.classList.remove('is-drag');
      dragged.draggable = false;
      dragged = null;
      commit();
    });
    list.addEventListener('keydown', event => {
      const grip = event.target.closest('.ml-grip');
      if (!grip || !['ArrowUp', 'ArrowDown'].includes(event.key)) { return; }
      event.preventDefault();
      const row = grip.closest('[data-id]');
      const sibling = step(row, event.key === 'ArrowUp');
      if (!sibling) { return; }
      before = ids().join();
      list.insertBefore(row, event.key === 'ArrowUp' ? sibling : sibling.nextSibling);
      grip.focus();
      commit();
    });
  };
</script>
