@once
<style>
/* Order Management header system: one panel, equal-height controls (34px), content-based width, 6px rhythm, status cards 58px.
   Reusable classes: .toolbar-control(-xs|-sm|-md|-lg) and .status-card(-sm|-md). Loaded last so it wins over older layered rules. */
#ocb#ocb{background:#F8FAFC!important;border:1px solid #D8DEE6!important;border-radius:10px!important;padding:8px!important;box-shadow:none!important;margin:0 0 8px!important}
#ocb#ocb form{margin:0}
#ocb#ocb .r1{display:flex!important;flex-wrap:wrap!important;align-items:center!important;justify-content:flex-start!important;gap:6px!important;min-height:0!important;position:relative}
#ocb#ocb .r1>.owt,#ocb#ocb .r1 .owt .obk,#ocb#ocb .r1 .owt .owf,#ocb#ocb .r1 .owt .q{display:contents!important}
#ocb#ocb .r1>span[style*="flex:1"]{display:none!important}
#ocb#ocb .r1 #osel{height:34px!important;min-width:28px}
/* toolbar controls */
#ocb#ocb .toolbar-control{box-sizing:border-box!important;display:inline-flex!important;align-items:center;justify-content:center;gap:6px;flex:0 0 auto!important;height:34px!important;min-height:34px!important;width:auto!important;min-width:max-content;max-width:none!important;margin:0!important;padding:0 12px!important;border:1px solid var(--border-grid,#CED4DA)!important;border-radius:8px!important;font-size:12px!important;font-weight:600!important;line-height:1!important;white-space:nowrap;box-shadow:none!important;transition:border-color .15s ease,background-color .15s ease}
#ocb#ocb .toolbar-control:hover{border-color:#94A3B8!important}
#ocb#ocb select.toolbar-control{display:inline-block!important;padding:0 26px 0 10px!important;max-width:170px!important;text-align:left;text-overflow:ellipsis}
#ocb#ocb .toolbar-control-xs{width:34px!important;min-width:34px!important;padding:0!important}
#ocb#ocb .toolbar-control-sm{min-width:64px}
#ocb#ocb .toolbar-control-md{min-width:96px}
#ocb#ocb #qsearch{flex:1 1 210px!important;min-width:210px!important;max-width:320px!important;margin:0!important;display:flex}
#ocb#ocb input.toolbar-control-lg{display:block!important;width:100%!important;min-width:0!important;justify-content:flex-start;text-align:left;font-weight:500!important;background:#fff!important;padding:0 12px!important}
#ocb#ocb input.toolbar-control-lg:focus{border-color:#FF5A00!important;outline:0}
#ocb#ocb #oexs{height:34px!important;min-width:0!important;flex:0 0 auto!important}
#ocb#ocb #oexs .dfx-btn.toolbar-control{height:34px!important;min-height:34px!important;padding:0 12px!important;font-size:12px!important;font-weight:600!important;line-height:1!important;border:1px solid var(--border-grid,#CED4DA)!important;border-radius:8px!important}
#ocb#ocb select.toolbar-control{max-width:130px!important}
#ocb#ocb .toolbar-control-sm[name=per]{min-width:0!important;padding-right:22px!important}
#ocb#ocb .pre{flex:0 0 auto!important;margin:0!important}
/* Create Order: strongest primary action, same height */
#ocb#ocb a.btn.toolbar-control{margin-left:auto!important;background:var(--o,#FF5A00)!important;border-color:var(--o,#FF5A00)!important;color:#fff!important;font-weight:700!important;text-decoration:none}
#ocb#ocb a.btn.toolbar-control:hover{filter:brightness(.95)!important;border-color:#E04E00!important}
/* status row: one line, controlled scroll, equal-height cards */
.ocb .r2{display:block!important;margin-top:8px!important}
.ocb .strip{display:flex!important;flex-wrap:nowrap!important;align-items:stretch!important;gap:6px!important;height:auto!important;padding:0!important;overflow-x:auto!important;overflow-y:hidden!important;white-space:nowrap;cursor:grab;scrollbar-width:none;-ms-overflow-style:none;-webkit-user-select:none;user-select:none;-webkit-overflow-scrolling:touch}
.ocb .strip::-webkit-scrollbar{display:none;height:0}
.ocb .strip.dragging{cursor:grabbing}
.ocb .strip a.status-card{box-sizing:border-box!important;flex:0 0 auto!important;display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;gap:1px!important;height:58px!important;min-width:74px;max-width:112px;padding:6px 8px!important;border-radius:8px!important;border:1px solid color-mix(in srgb,var(--c,#64748B) 45%,#fff)!important;line-height:1.15!important;text-decoration:none;box-shadow:none!important}
.ocb .strip a.status-card-sm{min-width:72px;max-width:96px}
.ocb .strip a.status-card:hover{border-color:var(--c,#475569)!important}
.ocb .strip a.status-card.on{border-color:var(--c,#0B1220)!important;box-shadow:0 0 0 2px color-mix(in srgb,var(--c,#0B1220) 28%,transparent)!important}
.ocb .strip a.status-card .cl{display:flex;flex-direction:column;align-items:center;gap:1px;max-width:100%}
.ocb .strip a.status-card .sl{font-size:11px;font-weight:600;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
.ocb .strip a.status-card .cl b{order:2;margin:0!important;font-size:16px!important;font-weight:700!important;line-height:1.15}
.ocb .strip a.status-card .cv{font-size:10.5px!important;font-weight:500!important;line-height:1.2;opacity:.85;white-space:nowrap}
@media(max-width:800px){#ocb#ocb a.btn.toolbar-control{margin-left:0!important}}
</style>
@endonce
