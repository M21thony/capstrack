<style nonce="{{ Vite::cspNonce() }}">
.crumbs{display:flex;align-items:center;gap:8px;font-size:14px;min-width:0;flex:1}
.crumbs > *{white-space:nowrap}
.crumbs > span:first-child, .crumbs a{color:var(--navy-text2);text-decoration:none}
.crumbs a:hover{color:var(--navy-text);text-decoration:underline}
.crumbs .sep{color:var(--navy-text3)}
.crumbs .here{font-weight:600;color:var(--navy-text);overflow:hidden;text-overflow:ellipsis;min-width:0}

.rp{--line:#eceef2;--head:#f7f8fa;font-size:14px;line-height:1.5;color:var(--text);display:flex;flex-direction:column;gap:16px;min-height:0}
.rp [hidden]{display:none!important}
.rp > *{flex-shrink:0}
.rp a{color:inherit}
.rp svg.i{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
.rp :focus-visible{outline:2px solid var(--accent3);outline-offset:2px}

.rp-head h1{margin:0;font-size:26px;line-height:1.2;letter-spacing:-.01em}
.rp-head p{margin:4px 0 0;color:var(--text2);max-width:780px}

.rp-card{background:var(--bg2);border:1px solid var(--border);border-radius:10px}

.rp-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:36px;padding:0 14px;border-radius:7px;border:1px solid var(--border2);background:var(--bg2);color:var(--text);font:500 13.5px/1 'DM Sans','DM Sans Fallback',sans-serif;white-space:nowrap;cursor:pointer;text-decoration:none;box-sizing:border-box}
.rp-btn:hover{border-color:var(--text3)}
.rp-btn.gold{background:var(--accent);border-color:var(--accent);color:#18191f;font-weight:600}
.rp-btn.gold:hover{background:var(--accent2);border-color:var(--accent2)}
.rp-btn.dark{background:var(--text);border-color:var(--text);color:#fff;font-weight:600}
.rp-btn.dark:hover{background:#000}
.rp-btn.ghost{border-color:transparent;background:none;color:var(--text2)}
.rp-btn.ghost:hover{background:var(--bg3);color:var(--text)}
.rp-btn.on{border-color:var(--accent);background:var(--accent-light)}
.rp-btn:disabled,.rp-btn[aria-disabled="true"]{opacity:.45;cursor:not-allowed}
.rp-btn.icon{width:36px;padding:0}

.rp-field{display:flex;flex-direction:column;gap:5px;font-size:11.5px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em;min-width:0}
.rp-field select,.rp-select{height:38px;padding:0 10px;border:1px solid var(--border2);border-radius:7px;background:var(--bg2);color:var(--text);font:400 14px 'DM Sans','DM Sans Fallback',sans-serif;text-transform:none;letter-spacing:0;width:100%;min-width:0}
.rp-field select:focus,.rp-select:focus,.rp-search input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,168,44,.2)}

.chip{display:inline-flex;align-items:center;height:24px;padding:0 10px;border-radius:999px;font-size:12.5px;font-weight:600;white-space:nowrap}
.chip.ok{background:#e7f5ec;color:#17663a}
.chip.warn{background:var(--accent-light);color:var(--accent3)}
.chip.bad{background:#fdecec;color:#b42318}
.chip.none{background:#eef0f3;color:var(--text2)}
.chip.info{background:#e8f0fb;color:#1d4f91}
.chip.gold{background:var(--accent-light);color:var(--accent3)}
/* Coloured words, not pills: a verdict or a suggestion inside a sentence or a cell. */
.tone{font-weight:600;white-space:nowrap}
.tone.ok{color:#17663a}
.tone.bad{color:#b42318}
.tone.none{color:var(--text2)}

/* Expert Load's "Enough experts?" box, above the table. */
.rp-enough{flex:none;font-size:13px}
.rp-enough p{margin:0;padding:10px 14px;display:flex;flex-wrap:wrap;gap:4px 16px}
.rp-enough .sum{display:block;line-height:1.6;border-bottom:1px solid var(--line)}
.rp-enough .sum > b:first-child{margin-right:6px}
.rp-enough .box{overflow-x:auto}
.rp-enough table{border-collapse:collapse;min-width:100%;font-variant-numeric:tabular-nums}
.rp-enough th,.rp-enough td{padding:6px 14px;text-align:left;white-space:nowrap;border-bottom:1px solid var(--line)}
.rp-enough th{font-size:12px;font-weight:600;color:var(--text2);background:var(--head)}
.rp-enough .r{text-align:right}
.rp-enough tfoot td{font-weight:600;background:var(--head);border-bottom:0}
.rp-enough .line{border-top:1px solid var(--line)}
.rp-enough .key{font-size:12px;color:var(--text3)}
.rp-enough .dash{color:var(--text3)}

.rp-note{margin:0;font-size:13px;color:var(--text3)}

@media (max-width:640px){.rp-head h1{font-size:22px}}
</style>
