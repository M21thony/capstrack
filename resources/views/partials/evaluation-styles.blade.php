{{-- Shared by the CaPSA's Expert Evaluations pages and the expert's My
     Evaluations: the stage cards, the question editor and the anonymous
     results. Built on the tokens admin.css and expert.css both define. --}}
<style nonce="{{ Vite::cspNonce() }}">
.ev-page{display:flex;flex-direction:column;gap:18px;max-width:1180px}
.ev-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap}
.ev-head h1{font-size:22px;font-weight:700;letter-spacing:-.3px;color:var(--text)}
.ev-head p:first-child{margin-top:0}
.ev-head p{margin-top:6px;max-width:720px;font-size:13.5px;line-height:1.55;color:var(--text2)}
.ev-head p b{color:var(--text)}
.ev-flash{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:12px;font-size:13.5px;font-weight:600;background:var(--green-bg);color:var(--green);border:1px solid rgba(22,163,74,.2)}
.ev-flash svg{width:17px;height:17px;flex-shrink:0}
.ev-flash.is-bad{background:var(--red-bg);color:var(--red);border-color:rgba(220,38,38,.2)}
.ev-tabs{display:flex;gap:4px;padding:4px;width:fit-content;max-width:100%;border-radius:12px;background:var(--bg4);overflow-x:auto}
.ev-tabs a{display:inline-flex;align-items:center;gap:7px;min-height:36px;padding:0 14px;border-radius:9px;font-size:13px;font-weight:600;color:var(--text2);text-decoration:none;white-space:nowrap}
.ev-tabs a:hover{color:var(--text)}
.ev-tabs a[aria-current="page"]{background:var(--bg2);color:var(--text);box-shadow:0 1px 3px rgba(0,0,0,.08)}
.ev-tabs .ev-n{min-width:20px;height:20px;padding:0 6px;border-radius:999px;display:inline-grid;place-items:center;font-size:11px;background:var(--bg3);color:var(--text2)}
.ev-tabs .ev-n.is-due{background:var(--red);color:#fff}

/* stage cards */
.ev-stages{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.ev-stage{display:flex;flex-direction:column;background:var(--bg2);border:1px solid var(--border);border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,.04);overflow:hidden}
.ev-stage.is-due{border-color:rgba(220,38,38,.35);box-shadow:0 0 0 3px var(--red-bg)}
.ev-stage-head{display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border);background:var(--bg3)}
.ev-step{width:30px;height:30px;flex-shrink:0;border-radius:9px;display:grid;place-items:center;font-size:13px;font-weight:700;background:var(--bg2);border:1px solid var(--border);color:var(--text2)}
.ev-stage-head h2{flex:1;min-width:0;font-size:15px;font-weight:700;color:var(--text)}
.ev-chip{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;font-size:11.5px;font-weight:700;white-space:nowrap}
.ev-chip.ok{background:var(--green-bg);color:var(--green)}
.ev-chip.part{background:var(--amber-bg);color:var(--amber)}
.ev-chip.none{background:var(--bg4);color:var(--text2)}
.ev-chip.due{background:var(--red);color:#fff}
.ev-due{display:flex;align-items:flex-start;gap:9px;margin:12px 16px 0;padding:10px 12px;border-radius:10px;background:var(--red-bg);color:var(--red);font-size:12.5px;line-height:1.5}
.ev-due svg{width:16px;height:16px;flex-shrink:0;margin-top:1px}
.ev-due b{color:var(--red)}
.ev-stage-body{flex:1;display:flex;flex-direction:column;gap:10px;padding:14px 16px 16px}
.ev-tpl{padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--bg2)}
.ev-tpl-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.ev-tpl-top b{font-size:14px;color:var(--text);line-height:1.35}
.ev-tpl-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:6px;font-size:12px;color:var(--text3)}
.ev-tpl-qs{margin:10px 0 0;padding-left:20px;font-size:12.5px;line-height:1.5;color:var(--text2)}
.ev-tpl-qs li+li{margin-top:2px}
.ev-tpl-acts{display:flex;gap:6px;flex-shrink:0}
.ev-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:36px;padding:0 14px;border-radius:9px;font:inherit;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer;border:1px solid var(--border);background:var(--bg2);color:var(--text);transition:background .15s,border-color .15s}
.ev-btn:hover{background:var(--bg3);border-color:var(--border2)}
.ev-btn:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.ev-btn svg{width:15px;height:15px}
.ev-btn.gold{background:var(--accent);border-color:var(--accent);color:#18191f}
.ev-btn.gold:hover{background:var(--accent2);border-color:var(--accent2)}
.ev-btn.sm{min-height:30px;padding:0 10px;font-size:12px}
.ev-btn.danger{color:var(--red)}
.ev-btn.danger:hover{background:var(--red-bg);border-color:rgba(220,38,38,.3)}
.ev-btn.link{border:0;background:none;padding:0;min-height:0;color:var(--blue);font-weight:700}
.ev-btn.link:hover{text-decoration:underline;background:none}
.ev-empty{display:flex;flex-direction:column;gap:12px;padding:14px;border:1.5px dashed var(--border2);border-radius:12px;background:var(--bg3)}
.ev-empty p{font-size:13px;line-height:1.5;color:var(--text2)}
.ev-empty-acts{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.ev-or{font-size:12px;color:var(--text3)}
.ev-peek summary{cursor:pointer;width:max-content;font-size:12.5px;font-weight:700;color:var(--blue)}
.ev-peek ol{margin:8px 0 0;padding-left:20px;font-size:12.5px;line-height:1.5;color:var(--text2)}
.ev-gap{font-size:12.5px;color:var(--text2)}

/* results */
.ev-filter{display:flex;gap:6px;flex-wrap:wrap}
.ev-filter a{display:inline-flex;align-items:center;min-height:32px;padding:0 12px;border-radius:999px;border:1px solid var(--border);background:var(--bg2);font-size:12.5px;font-weight:600;color:var(--text2);text-decoration:none}
.ev-filter a:hover{border-color:var(--border2);color:var(--text)}
.ev-filter a[aria-current="page"]{background:var(--text);border-color:var(--text);color:#fff}
.ev-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.ev-stat{padding:14px 16px;border:1px solid var(--border);border-radius:14px;background:var(--bg2)}
.ev-stat span{display:block;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--text3)}
.ev-stat b{display:flex;align-items:baseline;gap:6px;margin-top:4px;font-size:24px;color:var(--text);font-variant-numeric:tabular-nums}
.ev-stat b small{font-size:13px;color:var(--text3);font-weight:600}
.ev-stars{display:inline-flex;gap:1px;flex-shrink:0;vertical-align:middle}
.ev-stars svg{width:15px;height:15px;fill:var(--border2)}
.ev-stars svg.on{fill:#e0a800}
.ev-stars.lg svg{width:20px;height:20px}
.ev-list{display:flex;flex-direction:column;gap:10px}
.ev-x{background:var(--bg2);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.ev-x > summary{list-style:none;display:flex;align-items:center;gap:14px;padding:14px 16px;cursor:pointer}
.ev-x > summary::-webkit-details-marker{display:none}
.ev-x > summary:hover{background:var(--bg3)}
.ev-x > summary:focus-visible{outline:2px solid var(--accent);outline-offset:-2px}
.ev-av{width:40px;height:40px;flex-shrink:0;border-radius:50%;display:grid;place-items:center;overflow:hidden;position:relative;font-size:13px;font-weight:700;background:var(--accent-light);color:var(--accent3);border:1px solid var(--border)}
.ev-av img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.ev-av.anon{background:var(--bg4);color:var(--text3)}
.ev-av.anon svg{width:20px;height:20px}
.ev-anon-av{position:relative;width:38px;height:38px;flex-shrink:0;display:inline-block}
.ev-anon-av.is-sm{width:30px;height:30px}
.ev-anon-av > svg{display:block;width:100%;height:100%;border-radius:50%;box-shadow:0 0 0 1px var(--border)}
.ev-anon-bg{fill:#e6e9ef}
.ev-anon-fg{fill:#a6adbb}
.ev-anon-lock{position:absolute;right:-3px;bottom:-3px;width:17px;height:17px;display:grid;place-items:center;border-radius:50%;background:#2b2f3a;color:#fff;box-shadow:0 0 0 2px var(--bg2)}
.ev-anon-lock svg{width:9px;height:9px}
.ev-anon-av.is-sm .ev-anon-lock{width:14px;height:14px;right:-2px;bottom:-2px}
.ev-anon-av.is-sm .ev-anon-lock svg{width:7px;height:7px}
.ev-x-main{flex:1;min-width:0}
.ev-x-main b{display:block;font-size:14.5px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ev-x-roles{display:block;margin-top:4px;font-size:12px;font-weight:600;color:var(--text2)}
.ev-score{display:flex;align-items:center;gap:8px;flex-shrink:0;font-size:13px;color:var(--text3)}
.ev-score b{font-size:16px;color:var(--text);font-variant-numeric:tabular-nums}
.ev-score b small{font-size:12px;color:var(--text3)}
.ev-chev{width:18px;height:18px;flex-shrink:0;color:var(--text3);transition:transform .2s}
.ev-x[open] .ev-chev{transform:rotate(180deg)}
.ev-x-body{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr);gap:18px;padding:16px;border-top:1px solid var(--border);background:var(--bg3)}
.ev-sub{font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--text3);margin-bottom:10px}
.ev-qbars{list-style:none;display:flex;flex-direction:column;gap:12px}
.ev-qbar-t{display:flex;justify-content:space-between;gap:12px;font-size:12.5px;line-height:1.45;color:var(--text)}
.ev-qbar-t b{flex-shrink:0;font-variant-numeric:tabular-nums}
.ev-bar{height:7px;margin-top:5px;border-radius:999px;background:var(--bg4);overflow:hidden}
.ev-bar i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#e0a800,var(--accent))}
.ev-resps{list-style:none;display:flex;flex-direction:column;gap:8px;max-height:460px;overflow:auto}
.ev-resp{padding:12px;border:1px solid var(--border);border-radius:12px;background:var(--bg2)}
.ev-resp-top{display:flex;align-items:center;gap:10px}
.ev-resp-top .ev-av{width:32px;height:32px}
.ev-resp-top{align-items:center}
.ev-resp-who{flex:1;min-width:0}
.ev-resp-who b{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text)}
.ev-resp-who > span{display:block;font-size:11.5px;color:var(--text3);margin-top:1px}
.ev-resp-score{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:var(--text);flex-shrink:0}
.ev-resp details{margin-top:8px}
.ev-resp summary{cursor:pointer;width:max-content;font-size:12px;font-weight:700;color:var(--blue)}
.ev-resp ul{list-style:none;margin-top:6px;display:flex;flex-direction:column;gap:5px}
.ev-resp li{display:flex;justify-content:space-between;gap:12px;font-size:12.5px;line-height:1.4;color:var(--text2)}
.ev-resp li b{flex-shrink:0;color:var(--text)}
.ev-resp p{margin-top:8px;font-size:12.5px;line-height:1.5;color:var(--text2)}
.ev-none{padding:36px 20px;text-align:center;border:1.5px dashed var(--border2);border-radius:14px;background:var(--bg2);color:var(--text2);font-size:13.5px;line-height:1.5}
.ev-none svg{width:32px;height:32px;color:var(--accent);margin-bottom:6px}
.ev-none b{display:block;color:var(--text);font-size:15px;margin-bottom:4px}
.ev-dist{list-style:none;display:flex;flex-direction:column;gap:6px}
.ev-dist li{display:grid;grid-template-columns:34px 1fr 28px;align-items:center;gap:8px;font-size:12px;color:var(--text2)}
.ev-dist li .ev-bar{margin:0}
.ev-dist li span:last-child{text-align:right;font-variant-numeric:tabular-nums}
.ev-overview{display:grid;grid-template-columns:minmax(0,260px) minmax(0,1fr);gap:16px;padding:18px;border:1px solid var(--border);border-radius:14px;background:var(--bg2)}
.ev-big{display:flex;flex-direction:column;align-items:flex-start;gap:6px}
.ev-big b{font-size:40px;line-height:1;color:var(--text);font-variant-numeric:tabular-nums}
.ev-big b small{font-size:16px;color:var(--text3)}
.ev-big span{font-size:12.5px;color:var(--text3)}
.ev-bystage{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
.ev-bystage div{padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:var(--bg3)}
.ev-bystage span{display:block;font-size:11.5px;color:var(--text3)}
.ev-bystage b{display:block;margin-top:2px;font-size:16px;color:var(--text)}
.ev-bystage small{font-size:11.5px;color:var(--text3);font-weight:500}
.ev-two{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr);gap:16px;align-items:start}
.ev-card{padding:16px;border:1px solid var(--border);border-radius:14px;background:var(--bg2)}
.ev-anon-note{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text2)}
.ev-anon-note svg{width:16px;height:16px;color:var(--green);flex-shrink:0}

/* the question editor */
.ev-form{display:flex;flex-direction:column;gap:0;min-width:0;padding:4px 22px 18px;border:1px solid var(--border);border-radius:14px;background:var(--bg2)}
.ev-field{display:flex;flex-direction:column;gap:7px}
.ev-field > label,.ev-field > .ev-lbl{font-size:12.5px;font-weight:700;color:var(--text)}
.ev-hint{font-size:12px;color:var(--text3);line-height:1.45}
.ev-input{width:100%;min-height:42px;padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;background:var(--bg2);color:var(--text);font:inherit;font-size:14px;outline:none}
.ev-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-glow)}
.ev-input[readonly]{background:var(--bg3);color:var(--text2)}
.ev-err{font-size:12px;font-weight:600;color:var(--red)}
.ev-roles{display:flex;gap:8px;flex-wrap:wrap}
.ev-roles label{position:relative;display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:0 14px 0 12px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-size:13.5px;font-weight:600;color:var(--text2);user-select:none}
.ev-roles input{width:16px;height:16px;accent-color:var(--accent)}
.ev-roles label:has(input:checked){border-color:var(--accent);background:var(--accent-light);color:var(--text)}
.ev-roles label:has(input:focus-visible){outline:2px solid var(--accent);outline-offset:2px}
.ev-qlist{list-style:none;display:flex;flex-direction:column;gap:2px;margin:4px -6px 0}
.ev-qrow{display:flex;align-items:flex-start;gap:8px;padding:6px;border:1px solid transparent;border-radius:12px;transition:background .15s,border-color .15s}
.ev-qrow:hover,.ev-qrow:focus-within{background:var(--bg3);border-color:var(--border)}
.ev-qnum{width:28px;height:28px;margin-top:7px;flex-shrink:0;display:grid;place-items:center;border-radius:8px;font-size:12.5px;font-weight:700;background:var(--accent-light);color:var(--accent3)}
.ev-qacts{display:flex;gap:2px;margin-top:3px;flex-shrink:0;opacity:.55;transition:opacity .15s}
.ev-qrow:hover .ev-qacts,.ev-qrow:focus-within .ev-qacts{opacity:1}
.ev-ibtn{width:34px;height:34px;display:grid;place-items:center;border:0;border-radius:9px;background:transparent;color:var(--text2);cursor:pointer}
.ev-ibtn:hover:not(:disabled){background:var(--bg4);color:var(--text)}
.ev-ibtn.is-danger:hover:not(:disabled){background:var(--red-bg);color:var(--red)}
.ev-ibtn:disabled{opacity:.3;cursor:default}
.ev-ibtn:focus-visible{outline:2px solid var(--accent);outline-offset:1px}
.ev-ibtn svg{width:16px;height:16px}
.ev-qrow textarea{min-height:42px;resize:vertical;line-height:1.45}
.ev-qtools{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.ev-scale{display:flex;gap:6px 16px;flex-wrap:wrap;font-size:12px;color:var(--text2)}
.ev-scale span{display:inline-flex;align-items:center;gap:4px}
.ev-scale b{color:#b8961c}
.ev-form-foot{position:sticky;bottom:-26px;display:flex;justify-content:flex-end;gap:8px;margin:0 -22px -18px;padding:14px 22px;border-top:1px solid var(--border);background:var(--bg2);border-radius:0 0 14px 14px;z-index:2}
.ev-compose{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;align-items:start}
.ev-step-sec{display:flex;flex-direction:column;gap:12px;margin:0;padding:18px 0;border:0;border-bottom:1px solid var(--border);min-width:0}
.ev-step-sec:last-of-type{border-bottom:0}
.ev-step-h{display:flex;align-items:center;gap:10px;padding:0;font-size:14.5px;font-weight:700;color:var(--text)}
.ev-step-h span{width:24px;height:24px;display:grid;place-items:center;border-radius:50%;font-size:12px;background:var(--text);color:#fff}
.ev-step-row{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.ev-err:empty{display:none}
.ev-role-dot{width:9px;height:9px;border-radius:50%;background:currentColor;opacity:.5}
.ev-roles label.r-adviser .ev-role-dot{color:var(--blue)}
.ev-roles label.r-consultant .ev-role-dot{color:var(--purple)}
.ev-roles label.r-grammarian .ev-role-dot{color:var(--cyan)}
.ev-roles label:has(input:checked) .ev-role-dot{opacity:1}
.ev-kbd kbd{display:inline-block;padding:1px 6px;border:1px solid var(--border2);border-bottom-width:2px;border-radius:5px;background:var(--bg2);font:inherit;font-size:11px;font-weight:700;color:var(--text2)}
.ev-preview{position:sticky;top:0;display:flex;flex-direction:column;gap:2px}
.ev-pv-card{padding:16px;border:1px solid var(--border);border-radius:14px;background:var(--bg2);box-shadow:0 1px 3px rgba(0,0,0,.04)}
.ev-pv-head{display:flex;align-items:center;gap:10px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.ev-pv-who{min-width:0}
.ev-pv-who b{display:block;font-size:13.5px;color:var(--text)}
.ev-pv-who span{display:block;margin-top:1px;font-size:11.5px;color:var(--green);font-weight:700}
.ev-pv-av{width:40px;height:40px;flex-shrink:0;display:block}
.ev-pv-av svg{display:block;width:100%;height:100%;border-radius:50%;box-shadow:0 0 0 1px rgba(201,168,44,.35)}
.ev-pv-av-bg{fill:#faf3dc}
.ev-pv-av-fg{fill:#d4b24a}
.ev-pv-qs{list-style:none;display:flex;flex-direction:column;gap:10px;margin:12px 0 0;max-height:52vh;max-height:52dvh;overflow:auto;counter-reset:pv}
.ev-pv-qs li{display:flex;flex-direction:column;gap:5px;font-size:12.5px;line-height:1.45;color:var(--text);counter-increment:pv}
.ev-pv-qs li > span:first-child::before{content:counter(pv) ". ";font-weight:700;color:var(--text3)}
.ev-pv-qs li.ev-pv-empty{color:var(--text3);font-style:italic}
.ev-pv-qs li.ev-pv-empty::before{content:none}
.ev-pv-stars{display:flex;gap:2px}
.ev-pv-stars svg{width:16px;height:16px;fill:var(--border2)}
.ev-pv-note{display:flex;align-items:center;gap:6px;margin-top:12px;padding-top:10px;border-top:1px solid var(--border);font-size:11.5px;color:var(--text3)}
.ev-pv-note svg{width:14px;height:14px;color:var(--green)}
.ev-snack{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:400;display:flex;align-items:center;gap:14px;padding:10px 12px 10px 16px;border-radius:12px;background:#1e2128;color:#fff;font-size:13px;box-shadow:0 12px 32px rgba(0,0,0,.25)}
.ev-snack[hidden]{display:none}
.ev-snack button{border:0;background:none;color:var(--accent);font:inherit;font-weight:700;cursor:pointer;padding:4px 6px;border-radius:6px}
.ev-snack button:hover{background:rgba(255,255,255,.08)}
.ev-back{display:inline-flex;align-items:center;gap:6px;width:max-content;font-size:13px;font-weight:600;color:var(--text2);text-decoration:none}
.ev-back:hover{color:var(--text)}
.ev-back svg{width:15px;height:15px}

/* the CaPSA's evaluations: only the ones they made */
.ev-tpls{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px;align-items:stretch}
.ev-stage-tag{font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--accent3);white-space:nowrap}
.ev-card2{display:flex;flex-direction:column;gap:10px;padding:16px 16px 0;border:1px solid var(--border);border-radius:14px;background:var(--bg2);box-shadow:0 1px 3px rgba(0,0,0,.04);transition:box-shadow .2s,border-color .2s,transform .2s}
.ev-card2:hover{border-color:var(--border2);box-shadow:0 8px 24px -12px rgba(15,18,30,.25)}
.ev-card2-head{display:flex;align-items:center;justify-content:space-between;gap:8px}
.ev-live{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;font-weight:700;color:var(--green)}
.ev-live i{width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 0 3px var(--green-bg)}
.ev-card2 h3{font-size:15.5px;font-weight:700;line-height:1.35;color:var(--text);overflow-wrap:anywhere}
.ev-card2-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));margin:2px 0 0;border:1px solid var(--border);border-radius:11px;overflow:hidden}
.ev-card2-stats div{padding:8px 12px;background:var(--bg3)}
.ev-card2-stats div+div{border-left:1px solid var(--border)}
.ev-card2-stats dt{font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--text3)}
.ev-card2-stats dd{margin:2px 0 0;font-size:16px;font-weight:700;color:var(--text);font-variant-numeric:tabular-nums}
.ev-mini-star{margin-left:3px;font-size:13px;color:#e0a800}
.ev-dim{color:var(--text3);font-weight:500}
.ev-card2 .ev-peek{margin-top:auto}
.ev-card2-foot{display:flex;justify-content:flex-end;gap:8px;margin:2px -16px 0;padding:12px 16px;border-top:1px solid var(--border);background:var(--bg3);border-radius:0 0 14px 14px}
.ev-btn.ghost-danger{color:var(--red);border-color:transparent;background:transparent}
.ev-btn.ghost-danger:hover{background:var(--red-bg);border-color:rgba(220,38,38,.25)}
.ev-btn.danger-solid{color:var(--red);border-color:rgba(220,38,38,.3);background:var(--bg2)}
.ev-btn.danger-solid:hover{background:var(--red);border-color:var(--red);color:#fff}
.ev-start{display:flex;flex-direction:column;align-items:center;gap:8px;padding:40px 20px;text-align:center;border:1.5px dashed var(--border2);border-radius:14px;background:var(--bg2)}
.ev-start-ic{width:56px;height:56px;display:grid;place-items:center;border-radius:16px;background:var(--accent-light);color:var(--accent3);margin-bottom:4px}
.ev-start-ic svg{width:28px;height:28px}
.ev-start h2{font-size:16px;font-weight:700;color:var(--text)}
.ev-start p{max-width:460px;font-size:13.5px;line-height:1.55;color:var(--text2)}
.ev-req{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:14px 16px;border-radius:14px;background:#fff5f5;border:1px solid rgba(220,38,38,.25);border-left:4px solid var(--red)}
.ev-req-ic{width:38px;height:38px;flex-shrink:0;display:grid;place-items:center;border-radius:11px;background:var(--red-bg);color:var(--red)}
.ev-req-ic svg{width:19px;height:19px}
.ev-req-text{flex:1;min-width:240px;display:flex;flex-direction:column;gap:3px;font-size:12.5px;line-height:1.5;color:var(--text2)}
.ev-req-text b{font-size:14px;color:var(--red)}
.ev-req-acts{display:flex;gap:8px;flex-wrap:wrap}
.ev-offs{display:flex;flex-direction:column;gap:10px;margin-top:6px}
.ev-offs-head{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
.ev-offs-head h2{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--text2)}
.ev-offs-head > span{font-size:12.5px;color:var(--text3)}
.ev-offs .ev-n{min-width:20px;height:20px;padding:0 6px;border-radius:999px;display:inline-grid;place-items:center;font-size:11px;background:var(--bg4);color:var(--text2);letter-spacing:0}
.ev-offs ul{list-style:none;display:flex;flex-direction:column;border:1px solid var(--border);border-radius:14px;background:var(--bg2);overflow:hidden}
.ev-off-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 16px}
.ev-off-row + .ev-off-row{border-top:1px solid var(--border)}
.ev-off-main{min-width:0;display:flex;flex-direction:column;gap:4px}
.ev-off-main b{font-size:13.5px;color:var(--text2)}
.ev-off-main > span{display:flex;align-items:center;flex-wrap:wrap;font-size:12px;color:var(--text3)}
.ev-off-acts{display:flex;gap:6px;flex-shrink:0}
.ev-flash{position:relative;transition:opacity .2s,transform .2s}
.ev-flash span{flex:1}
.ev-flash.is-leaving{opacity:0;transform:translateY(-4px)}
.ev-flash-x{width:28px;height:28px;flex-shrink:0;display:grid;place-items:center;border:0;border-radius:8px;background:transparent;color:inherit;cursor:pointer;opacity:.7}
.ev-flash-x:hover{opacity:1;background:rgba(0,0,0,.05)}
.ev-flash-x svg{width:15px;height:15px}

@media (max-width: 900px){
  .ev-compose{grid-template-columns:1fr}
  .ev-preview{position:static}
  .ev-tpls,.ev-stages,.ev-x-body,.ev-two,.ev-overview{grid-template-columns:1fr}
  .ev-bystage{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width: 560px){
  .ev-stats{grid-template-columns:1fr}
  .ev-x > summary{flex-wrap:wrap}
  .ev-score{width:100%;padding-left:54px}
  .ev-tpl-top{flex-direction:column}
  .ev-tpls{grid-template-columns:1fr}
  .ev-req-acts,.ev-req-acts form,.ev-req-acts .ev-btn{width:100%}
  .ev-off-acts{width:100%}
  .ev-off-acts > *{flex:1}
  .ev-off-acts .ev-btn{width:100%}
  .ev-form{padding:4px 16px 16px}
  .ev-form-foot{margin:0 -16px -16px;padding:12px 16px}
  .ev-qacts{opacity:1}
  .ev-kbd{display:none}
  .ev-form-foot .ev-btn{flex:1}
}
/* Results summary (CaPSA) and one expert's answer spread. */
.ev-sum-h{font-size:22px;font-weight:700;letter-spacing:-.3px;color:var(--text);margin:0}
.ev-sum-card{background:#fff;border:1px solid #e0e2e8;border-radius:14px}
.ev-sum-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin:0}
.ev-sum-tile{padding:16px 20px;border-left:1px solid #e0e2e8;min-width:0}
.ev-sum-tile:first-child{border-left:0}
.ev-sum-tile dt{font-size:13px;color:#4a5060;font-weight:600}
.ev-sum-tile dd{margin:6px 0 0;font-size:22px;font-weight:700;color:#18191f;font-variant-numeric:tabular-nums}
.ev-sum-tile dd.is-text{font-size:17px;line-height:1.35}
.ev-sum-tile dd.is-wait{color:#b45309}
.ev-sum-tile small{display:block;margin-top:4px;font-size:13px;color:#656b7a;font-weight:500}
.ev-sum-row{display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start}
.ev-sum-row > .ev-sum-card{padding:18px 20px}
.ev-sum-by{flex:1 1 560px;min-width:0}
.ev-sum-byq{flex:0 1 380px;min-width:0}
.ev-sum-card h2{font-size:16px;font-weight:700;color:#18191f;margin:0 0 8px}
.ev-sum-x{display:grid;grid-template-columns:200px minmax(80px,1fr) 56px 90px 20px;gap:14px;align-items:center;padding:12px 8px;border-top:1px solid #eef0f3;color:inherit;text-decoration:none;border-radius:8px}
.ev-sum-x:first-of-type{border-top:0}
a.ev-sum-x:hover{background:#f7f8fa}
a.ev-sum-x:focus-visible{outline:2px solid #c9a82c;outline-offset:2px}
.ev-sum-x b{display:block;font-size:15px;font-weight:600;color:#18191f;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ev-sum-x span.ev-sum-roles{display:block;font-size:13px;color:#656b7a}
.ev-sum-score{font-size:16px;font-weight:700;color:#18191f;text-align:right;font-variant-numeric:tabular-nums}
.ev-sum-score.is-none{color:#656b7a;font-weight:600}
.ev-sum-teams{font-size:13px;font-weight:700;color:#4a5060}
.ev-sum-teams.is-zero{color:#b45309}
.ev-sum-teams.is-all{color:#166534}
.ev-sum-go{color:#6b550c;font-size:18px;font-weight:700}
.ev-sum-track{display:block;height:8px;background:#eef0f3;border-radius:4px;overflow:hidden}
.ev-sum-fill{display:block;height:100%;border-radius:4px;background:#c9a82c}
.ev-sum-fill.is-dark{background:#18191f}
.ev-sum-q{padding:10px 0;border-top:1px solid #eef0f3}
.ev-sum-q:first-of-type{border-top:0}
.ev-sum-q div{display:flex;justify-content:space-between;gap:12px;font-size:14px;color:#18191f;margin-bottom:6px}
.ev-sum-q div b{font-variant-numeric:tabular-nums}
.ev-sum-foot{margin:10px 0 0;font-size:13px;color:#656b7a}
.ev-crumb{display:flex;gap:8px;align-items:center;font-size:13px;color:#656b7a}
.ev-crumb a{color:#6b550c;font-weight:600;text-decoration:none}
.ev-crumb a:hover{text-decoration:underline}
.ev-lk-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap}
.ev-lk-head h1{font-size:28px;font-weight:700;letter-spacing:-.4px;color:#18191f;margin:4px 0 6px}
.ev-lk-head p{margin:0;font-size:14px;color:#656b7a}
.ev-lk-nums{display:flex;gap:28px;margin:0}
.ev-lk-nums dt{font-size:13px;color:#4a5060;font-weight:600}
.ev-lk-nums dd{margin:4px 0 0;font-size:30px;font-weight:700;color:#18191f;font-variant-numeric:tabular-nums}
.ev-lk-nums dd small{font-size:15px;color:#656b7a;font-weight:600}
.ev-lk-card{padding:18px 20px}
.ev-lk-top{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:12px}
.ev-lk-top h2{margin:0}
.ev-lk-legend{display:flex;gap:14px;flex-wrap:wrap;font-size:13px;color:#4a5060}
.ev-lk-sw{width:16px;height:16px;border-radius:4px;display:inline-block;vertical-align:-3px;margin-right:6px}
.ev-lk-grid{display:grid;grid-template-columns:320px minmax(0,1fr) 60px;gap:10px 16px;align-items:center}
.ev-lk-axis{font-size:12px;color:#656b7a;display:flex;justify-content:space-between}
.ev-lk-q{font-size:15px;color:#18191f;line-height:1.35}
.ev-lk-bar{display:flex;align-items:stretch}
.ev-lk-half{flex:1;display:flex;height:34px;background:#f7f8fa}
.ev-lk-half.is-left{justify-content:flex-end;border-radius:8px 0 0 8px}
.ev-lk-half.is-right{border-radius:0 8px 8px 0}
.ev-lk-mid{width:2px;background:#18191f}
.ev-lk-seg{height:100%;padding:0 8px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;overflow:hidden}
.ev-lk-avg{font-size:18px;font-weight:700;color:#18191f;text-align:right;font-variant-numeric:tabular-nums}
.ev-lk-s1{background:#b45309;color:#fff}
.ev-lk-s2{background:#f0c79a;color:#18191f}
.ev-lk-s3{background:#c8cad4;color:#18191f}
.ev-lk-s4{background:#d9be5c;color:#18191f}
.ev-lk-s5{background:#8a6e10;color:#fff}
.ev-lk-roles{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
.ev-lk-role{background:#f7f8fa;border-radius:10px;padding:14px 16px;display:flex;justify-content:space-between;align-items:baseline;gap:10px;font-size:14px;color:#4a5060}
.ev-lk-role b{color:#18191f}
.ev-lk-role strong{font-size:22px;color:#18191f;font-variant-numeric:tabular-nums}
@media (max-width:900px){
  .ev-sum-strip{grid-template-columns:repeat(2,minmax(0,1fr))}
  .ev-sum-tile:nth-child(3){border-left:0}
  .ev-sum-tile:nth-child(n+3){border-top:1px solid #e0e2e8}
  .ev-sum-x{grid-template-columns:minmax(0,1fr) 48px 80px 16px}
  .ev-sum-x .ev-sum-track{display:none}
  .ev-lk-grid{grid-template-columns:minmax(0,1fr)}
  .ev-lk-axis-q,.ev-lk-axis-avg{display:none}
  .ev-lk-avg{text-align:left;margin-bottom:8px}
}
</style>
