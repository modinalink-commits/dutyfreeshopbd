{{-- Mother Commerce — Ultra-Light Tint Grid colour system (ONE central theme).
     Included once by layout.blade.php right after admin_grid. Colours live ONLY in the :root tokens below — change a value there and every module follows.
     Visual layer only: no route, query, status or calculation logic is touched. Tints stay at 2–6 % so text contrast is unchanged. --}}
<style id="admin-theme">
/* ===== 1. tokens (spec §4, §62) ===== */
:root{
  --page-bg:#F8FAFC;--surface-main:#FFFFFF;--surface-soft:#FAFBFC;
  --tint-blue:#F4F8FF;--tint-green:#F3FBF6;--tint-orange:#FFF8F1;--tint-yellow:#FFFBF0;--tint-amber:#FFF9EC;
  --tint-purple:#F8F5FF;--tint-cyan:#F2FBFC;--tint-red:#FFF5F5;--tint-pink:#FFF5F7;--tint-gray:#F7F8FA;
  --tint-slate:#F5F7FA;--tint-greengray:#F4F8F6;--tint-redgray:#FAF5F5;
  --acc-blue:#A9C4F5;--acc-green:#9BD6AE;--acc-orange:#F6C39A;--acc-yellow:#EFD98A;--acc-purple:#C9B8F2;
  --acc-cyan:#94D8E3;--acc-red:#F2B3B3;--acc-gray:#CBD2DB;--acc-greengray:#A9C7B9;
  /* 5-level ladder: 1 normal row < 2 group row < 3 working bar < 4 module/section bar < 5 table header */
  --grid-bg-level-1:#FAFBFC;--grid-bg-level-2:#F5F7F9;--toolbar-bg-level-3:#F0F3F6;--module-bg-level-4:#E8ECF1;--header-bg-level-5:#E3E8EE;
  /* 3-level border ladder (thin 1px lines, darker not thicker): soft inner < grid (main table/grid line) < strong (header bottom, group, totals, module edges) */
  --grid-border-soft:#D1D5DB;--grid-border-main:#B8BFC9;--border-grid:var(--grid-border-main);--border-strong:#9CA3AF;
  --toolbar-border:var(--border-grid);--module-border:var(--border-grid);
  --border-main:var(--grid-border-main);--border-soft:var(--grid-border-soft);
  --head-bg:var(--header-bg-level-5);--group-bg:var(--grid-bg-level-2);--total-bg:var(--toolbar-bg-level-3);
  --accent-tint:#FFF4EC;
  /* text must read clearly stronger than any border: primary near-black, secondary dark slate, muted still dark enough */
  --text-primary:#0B1220;--text-secondary:#374151;--text-muted:#4B5563;
  /* module / section / working bars: one step darker than normal grid rows (6-10 % tint) */
  --module-bar-bg:var(--module-bg-level-4);--module-bar-border:var(--module-border);--module-bar-text:#111827;
  /* map the tokens onto the variables admin_grid already reads */
  --bg:var(--page-bg);--surface:var(--surface-main);--surface-2:var(--surface-soft);
  --border-color:var(--border-main);--row-hover:#F6F8FA;--row-selected:#FFF3EA;--primary-soft:var(--accent-tint);
}
body{background:var(--page-bg)}

/* ===== 2. semantic classes (spec §63-64): put on a <tr>, a card or any block ===== */
.grid-neutral{--rt:var(--tint-gray);--ra:var(--acc-gray)}
.grid-info{--rt:var(--tint-blue);--ra:var(--acc-blue)}
.grid-success{--rt:var(--tint-green);--ra:var(--acc-green)}
.grid-warning{--rt:var(--tint-yellow);--ra:var(--acc-yellow)}
.grid-orange{--rt:var(--tint-orange);--ra:var(--acc-orange)}
.grid-danger{--rt:var(--tint-red);--ra:var(--acc-red)}
.grid-purple{--rt:var(--tint-purple);--ra:var(--acc-purple)}
.grid-cyan{--rt:var(--tint-cyan);--ra:var(--acc-cyan)}
.grid-slate{--rt:var(--tint-slate);--ra:var(--acc-gray)}
:is(.grid-neutral,.grid-info,.grid-success,.grid-warning,.grid-orange,.grid-danger,.grid-purple,.grid-cyan,.grid-slate):not(tr){background:var(--rt)}
main table tbody tr:is(.grid-neutral,.grid-info,.grid-success,.grid-warning,.grid-orange,.grid-danger,.grid-purple,.grid-cyan,.grid-slate)>td{background:var(--rt)}
main table:not(.ordt):has(th) tbody tr:is(.grid-neutral,.grid-info,.grid-success,.grid-warning,.grid-orange,.grid-danger,.grid-purple,.grid-cyan,.grid-slate):hover>td{background:var(--rt);filter:brightness(.98)}
main table:not(.ordt):has(th) tbody tr:is(.grid-neutral,.grid-info,.grid-success,.grid-warning,.grid-orange,.grid-danger,.grid-purple,.grid-cyan,.grid-slate)>td:first-child{box-shadow:inset 2px 0 0 var(--ra)}

/* ===== 3. order status rows (spec §7-8): one very light tint per stage ===== */
.st-new,.st-scheduled{--rt:var(--tint-blue);--ra:var(--acc-blue)}
.st-pending,.st-pre-order,.st-wfp{--rt:var(--tint-amber);--ra:var(--acc-yellow)}
.st-assign{--rt:var(--tint-cyan);--ra:var(--acc-cyan)}
.st-ai-order,.st-packaging{--rt:var(--tint-purple);--ra:var(--acc-purple)}
.st-approved{--rt:var(--tint-green);--ra:var(--acc-green)}
.st-shipment{--rt:var(--tint-cyan);--ra:var(--acc-cyan)}
.st-delivered,.st-partial-delivered{--rt:var(--tint-greengray);--ra:var(--acc-greengray)}
.st-late-delivery,.st-partial-return-pending,.st-partial-return-received,.st-partial-returned,.st-exchange-pending,.st-exchange-received{--rt:var(--tint-orange);--ra:var(--acc-orange)}
.st-returned,.st-return-pending,.st-partial-return-pending,.st-partial-return-received,.st-partial-returned{--rt:var(--tint-pink);--ra:var(--acc-red)}
.st-cancel,.st-cancelled{--rt:var(--tint-redgray);--ra:var(--acc-gray)}
.st-incomplete{--rt:var(--tint-gray);--ra:var(--acc-gray)}
main table.ordt>tbody>tr:is(.st-new,.st-scheduled,.st-pending,.st-pre-order,.st-wfp,.st-assign,.st-ai-order,.st-packaging,.st-approved,.st-shipment,.st-delivered,.st-partial-delivered,.st-late-delivery,.st-partial-return-pending,.st-partial-return-received,.st-partial-returned,.st-exchange-received,.st-returned,.st-return-pending,.st-cancel,.st-cancelled,.st-incomplete)>td{background:var(--rt)}
main table.ordt>tbody>tr:is(.st-new,.st-scheduled,.st-pending,.st-pre-order,.st-wfp,.st-assign,.st-ai-order,.st-packaging,.st-approved,.st-shipment,.st-delivered,.st-partial-delivered,.st-late-delivery,.st-partial-return-pending,.st-partial-return-received,.st-partial-returned,.st-exchange-received,.st-returned,.st-return-pending,.st-cancel,.st-cancelled,.st-incomplete)>td:first-child{border-left-color:var(--ra)}
main table.ordt>tbody>tr:is(.st-new,.st-scheduled,.st-pending,.st-pre-order,.st-wfp,.st-assign,.st-ai-order,.st-packaging,.st-approved,.st-shipment,.st-delivered,.st-partial-delivered,.st-late-delivery,.st-partial-return-pending,.st-partial-return-received,.st-partial-returned,.st-exchange-received,.st-returned,.st-return-pending,.st-cancel,.st-cancelled,.st-incomplete):hover>td{background:var(--rt);filter:brightness(.985)}
main table.ordt>tbody>tr:has(input[type=checkbox]:checked)>td{background:var(--row-selected)!important}

/* ===== 4. table furniture: header, group, total (spec §35-37) ===== */
main table:not(.ordt):has(th) th,main table.ordt>thead>tr>th{background:var(--head-bg);color:#111827}
main table:not(.ordt):has(th),main table.ordt{border-color:var(--border-main)}
main table:not(.ordt):has(th) td,main table.ordt>tbody>tr>td{border-bottom-color:var(--border-grid);border-right-color:var(--border-grid)}
main table:not(.ordt):has(th) th,main table.ordt>thead>tr>th{border-color:var(--border-grid);border-bottom-color:var(--border-strong)}
main table:not(.ordt):has(th),main table.ordt{border-color:var(--border-grid)}
main table:not(.ordt):has(th) tr.g-group>td{border-top:1px solid var(--border-strong);border-bottom-color:var(--border-grid)}
main table:not(.ordt):has(th) td{background:var(--grid-bg-level-1)}
main table:not(.ordt):has(th) tr.g-group>td{background:var(--group-bg)}
main table:not(.ordt):has(th) tr.g-group.imp>td{background:var(--header-bg-level-5)}
main table:not(.ordt):has(th) tfoot td,main table:not(.ordt):has(th) tr.g-total>td{background:var(--total-bg);border-top:1px solid var(--border-strong);font-weight:700}
/* soft tint for the first row-group zebra: only on tables that opt in with .g-zebra (never random) */
main table.g-zebra:not(.ordt) tbody tr:nth-child(even)>td{background:var(--surface-soft)}

/* ===== 5. surfaces: cards, forms, toolbar, filters (spec §29-32, §50-51) ===== */
main .card{background:var(--surface-main);border-color:var(--border-main)}
main .card:has(> form input,> form select,> form textarea),main form.card{background:var(--surface-soft)}
main .card input:not([type=checkbox]):not([type=radio]),main .card select,main .card textarea{background:var(--surface-main)}
.ocb input,.ocb select{background:var(--surface-main)}
main input:focus-visible,main select:focus-visible,main textarea:focus-visible{border-color:var(--primary);box-shadow:0 0 0 3px rgba(255,90,0,.10)}
main .btn.g,main button.g{background:var(--tint-slate);color:var(--text-primary);border:1px solid var(--border-main)}
main .btn.g:hover,main button.g:hover{background:var(--accent-tint)}

/* ===== 6. KPI blocks: gentle tint rotation (spec §6, §26) ===== */
main .grid>.card.k:nth-child(5n+1){background:var(--tint-orange)}
main .grid>.card.k:nth-child(5n+2){background:var(--tint-blue)}
main .grid>.card.k:nth-child(5n+3){background:var(--tint-green)}
main .grid>.card.k:nth-child(5n+4){background:var(--tint-purple)}
main .grid>.card.k:nth-child(5n+5){background:var(--tint-cyan)}

/* ===== 7. sidebar, pagination, tabs, modals, dropdowns (spec §27-28, §52-55) ===== */
aside nav a.nv:hover,aside nav details>summary:hover,aside nav a.sub:hover{background:var(--tint-gray)}
aside nav a.nv.on,aside nav a.sub.on{background:var(--accent-tint)}
aside nav details.mg>summary{background:var(--toolbar-bg-level-3)}
aside nav details.mg>summary{border-bottom-color:var(--border-grid)}
aside nav details.mg[open]>summary{background:var(--module-bg-level-4);border-bottom-color:var(--border-grid)}
aside nav details.mg>a.sub,aside nav details.rpg>summary{background:var(--grid-bg-level-1)}
aside nav,aside nav a.nv,aside nav details.mg>summary{border-color:var(--border-soft)}
.pgx .b{background:var(--surface-main);border-color:var(--border-main)}
.pgx .b.on{background:var(--accent-tint)!important;color:#C2410C!important;border-color:#FDBA8C!important;font-weight:700}
dialog,[role=dialog],.modal,.modal-box{border:1px solid var(--border-main);background:var(--surface-main)}
dialog h3,[role=dialog] h3,.modal h3,.modal-box h3{background:var(--module-bg-level-4);border-bottom:1px solid var(--module-border);padding:8px 12px;margin:0;font-size:13px}
/* ===== 7b. bars: Level 4 module/section vs Level 3 working bars (spec: module bar = 3 steps lighter-tint stronger than a normal row) ===== */
.ui-grid-row{background:var(--grid-bg-level-1)}.ui-group-row{background:var(--grid-bg-level-2)}
.ui-table-header,.ui-important-group-header{background:var(--header-bg-level-5)}
.ui-module-bar,.ui-section-bar,.ui-submodule-bar,main>h1,main .card>:is(h2,h3):first-child{
  background:var(--module-bg-level-4);border:1px solid var(--module-border);border-radius:6px;box-sizing:border-box;color:var(--module-bar-text)}
.ui-toolbar-row,.ui-filter-row,.ui-search-row,.ui-export-row,.ui-summary-bar,main .g-toolbar{
  background:var(--toolbar-bg-level-3);border:1px solid var(--toolbar-border);border-radius:6px;box-sizing:border-box;color:var(--module-bar-text)}
main>h1,.ui-module-bar{display:flex;align-items:center;gap:8px;padding:6px 12px;min-height:38px;font-size:14px;font-weight:650;margin:0 0 10px}
main .card>:is(h2,h3):first-child,.ui-section-bar,.ui-submodule-bar{display:flex;align-items:center;gap:8px;padding:6px 12px;font-size:13px;font-weight:600;min-height:36px;margin-bottom:8px}
.ui-submodule-bar{min-height:34px}
.ui-toolbar-row,.ui-filter-row,.ui-search-row,.ui-export-row,.ui-summary-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:6px 10px;min-height:34px}
.ui-module-bar-title,.ui-bar-title{font-size:13px;font-weight:650;color:var(--module-bar-text)}.ui-bar-sub{color:var(--text-secondary)}
/* filter / search / date / show / bulk-action / pagination bars = Level 3 */
.ocb,.pgx,.g-toolbar{background:var(--toolbar-bg-level-3)!important;border-color:var(--toolbar-border)!important}
main form#bulk>.card:first-child,main .bulk-bar,main .filter-bar,main .search-bar,main .card:has(> form.row:first-child){background:var(--toolbar-bg-level-3);border-color:var(--toolbar-border)}
/* ===== 7c. inputs, modal & detail sections use the same grid border ===== */
main input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]),main select,main textarea{border-color:var(--border-grid)}
main input:focus-visible,main select:focus-visible,main textarea:focus-visible{border-color:var(--primary)}
main .card{border-color:var(--border-grid)}
.ui-module-bar,.ui-section-bar,main>h1,main .card>:is(h2,h3):first-child{border-bottom-color:var(--border-strong)}
dialog,[role=dialog],.modal,.modal-box{border-color:var(--border-grid)}
/* ===== 9. stronger text & icons (spec: text darker than borders) ===== */
main{color:var(--text-primary)}
main td{color:#111827}
main label,main small,main .muted,main .k span,main .sub-t{color:#374151}
main label{font-weight:600}
main h1,main h2,main h3,main h4{color:#0B1220}
/* inline light-gray text colours (#6B7280 / #9CA3AF / #9CA3AF / #64748B) -> dark slate; #4B5563 -> darker */
main [style*="color:#6B7280" i],main [style*="color:#9CA3AF" i],main [style*="color:#9CA3AF" i],main [style*="color:#64748B" i],main [style*="color: #6B7280" i],main [style*="color: #9CA3AF" i]{color:#4B5563!important}
main [style*="color:#4B5563" i],main [style*="color:#4B5563" i]{color:#334155!important}
main .pill,main .chip{font-weight:700}
/* icons follow text colour; make the grey ones clearly darker */
main svg{stroke-opacity:1}
main .ib{color:#111827}
main td svg,main .ocb svg{opacity:1}
aside a.sub,aside details.rpg>summary{color:#111827!important}
aside a.nv svg,aside details summary svg{color:#374151!important}
aside details.mg>summary::after,aside details.rpg>summary::after{color:#374151!important}
aside .brand small,aside .viewing small{color:#4B5563!important}
aside .sbs input::placeholder{color:#4B5563}
::placeholder{color:#6B7280;opacity:1}
/* ===== 8. compact action icons (desktop only; mobile keeps big touch targets) ===== */
@media(min-width:761px){.ibar{gap:4px;margin-top:3px}.ibar .ib{width:24px;height:24px;border-radius:7px;box-shadow:none}.ibar .ib svg{width:12px;height:12px}.ibar .ib-call{height:24px;padding:0 9px;font-size:11px;border-radius:7px;gap:4px;box-shadow:none}.ibar .ib-call svg{width:11px;height:11px}}
/* ===== 9. softer customer-intel cell + dark-blue outlined action buttons ===== */
main .ibar a.ib,main .ibar a.ib-call{border:1.5px solid #0B1220!important;box-shadow:none!important}
main .ibar a.ib{background:#F8FAFC;color:#334155}
main .ibar a.ib.ch{background:#EFF6FF;color:#3B6FD8}
main .ibar a.ib.wa{background:#F0FDF4;color:#22A06B}
main .ibar a.ib.pf{background:#F5F3FF;color:#7C6FDB}
main .ibar a.ib.hs{background:#FFF7ED;color:#E07B39}
main .ibar a.ib.cp{background:#F8FAFC;color:#64748B}
main .ibar a.ib-call{background:#ECFDF5;color:#166534!important}
main .ibar a.ib:hover,main .ibar a.ib-call:hover{box-shadow:0 3px 8px rgba(11,18,32,.12)!important}
/* ===== 10. compact customer card: stat chips + quiet action buttons ===== */
.ci .cs-row{display:flex;gap:5px;align-items:center;margin-top:5px;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;max-width:100%;scrollbar-width:none;-webkit-overflow-scrolling:touch}.ci .cs-row::-webkit-scrollbar{display:none}
.ci .cs-row+.cs-row{margin-top:5px}
.ci .cs{display:inline-flex;align-items:center;gap:4px;height:24px;padding:0 8px;border-radius:8px;border:1px solid #CED4DA;background:#F8FAFC;color:#334155;font-size:11.5px;font-weight:600;line-height:1;white-space:nowrap;flex:0 0 auto;cursor:default}
.ci .cs b{font-weight:700;color:inherit}
.ci .cs svg{flex:0 0 auto}
.ci .cs{justify-content:center;min-width:44px;font-size:12.5px;font-weight:700}
.ci .cs.t-n{background:#F6F7F9;border-color:#D5DAE0;color:#374151}
.ci .cs.t-g{background:#F1FBF4;border-color:#BFE8CA;color:#138A3D}
.ci .cs.t-r{background:#FFF3F3;border-color:#F3B6B6;color:#C62828}
.ci .cs.t-a{background:#FFF8E8;border-color:#F0D48C;color:#A96A00}
.ci .cs.t-b{background:#F2F7FF;border-color:#BCD4F6;color:#245DA8}
.ci .cs-sep{width:1px;height:14px;background:#CED4DA;flex:0 0 auto;margin:0 1px}
.ci .t-n{background:#F3F4F6;border-color:#D5D9DE;color:#374151}
.ci .t-g{background:#F0FBF4;border-color:#BFE6CE;color:#166534}
.ci .t-r{background:#FEF3F2;border-color:#F6C4C0;color:#B42318}
.ci .t-a{background:#FFF8E8;border-color:#F4D79B;color:#9A5B0B}
.ci .t-b{background:#EEF3FB;border-color:#C5D3EC;color:#1E3A6E}
.ci .ibar{flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;max-width:100%;gap:6px;margin-top:6px;padding:1px;scrollbar-width:none}.ci .ibar::-webkit-scrollbar{display:none}
main .ci .ibar a.ib,main .ci .ibar a.ib-call{border:1px solid #CED4DA!important;box-shadow:none!important;transform:none!important;transition:border-color .15s,background-color .15s,box-shadow .15s;flex:0 0 auto}
main .ci .ibar a.ib{width:30px!important;height:30px!important;min-width:30px;border-radius:8px}
main .ci .ibar a.ib svg{width:14px;height:14px}
main .ci .ibar a.ib-call{height:30px!important;padding:0 11px;border-radius:8px;font-size:12px;gap:5px;background:#ECFDF5}
main .ci .ibar a.ib-call svg{width:13px;height:13px}
main .ci .ibar a.ib:hover,main .ci .ibar a.ib-call:hover{border-color:#9CA3AF!important;box-shadow:0 2px 6px rgba(11,18,32,.10)!important;transform:none!important}
main .ci .ibar a.ib.on,main .ci .ibar a.ib-call.on{border-color:#FF5A00!important;background:#FFF3EA!important}
.dfx-menu{border-color:var(--border-main)}
.dfx-menu .dfx-f button:hover{background:var(--accent-tint)!important}
</style>
<script>
/* Status words -> the same soft semantic tints, on any admin table with a Status/State column (no per-page markup).
   Only rows whose status cell is exactly one of the known words are touched; everything else stays neutral. */
(function(){
  var M=[
    ['success',/^(verified|active|approved|paid|published|delivered|completed|success|successful|in stock|available|live|sent|received|matched|settled|enabled|running|ok|yes)$/i],
    ['warning',/^(pending|processing|draft|due|unpaid|partial|partially paid|low stock|low|waiting|review|hold|on hold|open|unverified)$/i],
    ['orange',/^(mismatch|partial mismatch|late|delayed)$/i],
    ['danger',/^(rejected|failed|cancel|cancelled|canceled|returned|suspended|out of stock|overdue|blocked|error|void|voided|declined|deleted)$/i],
    ['neutral',/^(inactive|offline|disabled|expired|archived|closed|off|no|stopped)$/i],
    ['info',/^(assigned|new|in transit|courier assigned|shipped|scheduled|busy)$/i]
  ];
  function kind(t){t=(t||'').replace(/\s+/g,' ').trim().replace(/[.!]+$/,'');if(!t||t.length>24)return null;for(var i=0;i<M.length;i++)if(M[i][1].test(t))return M[i][0];return null}
  function run(){
    document.querySelectorAll('main table:not(.ordt)').forEach(function(tb){
      if(tb.dataset.gt||tb.closest('td'))return;tb.dataset.gt='1';
      var head=tb.querySelector('thead tr')||tb.querySelector('tr');if(!head)return;
      var cols=-1;[].forEach.call(head.children,function(c,i){if(cols<0&&/^(status|state|payment status|verification|stock status|order status|delivery status)$/i.test((c.textContent||'').replace(/\s+/g,' ').trim()))cols=i});
      if(cols<0)return;var n=head.children.length;
      [].forEach.call(tb.querySelectorAll('tbody>tr'),function(r){
        if(r.children.length!==n||/\bgrid-/.test(r.className))return;
        var c=r.children[cols],k=c&&kind(c.textContent);if(k)r.classList.add('grid-'+k)
      });
    });
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
  window.addEventListener('load',run);
})();
</script>
<style>
/* 10. fewer lines: no coloured left stripe on order rows, hairline borders only, quiet sidebar scrollbar */
main table.ordt>tbody>tr>td:first-child,main table.ordt>tbody>tr:is(.st-new,.st-scheduled,.st-pending,.st-pre-order,.st-wfp,.st-assign,.st-ai-order,.st-packaging,.st-approved,.st-shipment,.st-delivered,.st-partial-delivered,.st-late-delivery,.st-partial-return-pending,.st-partial-return-received,.st-partial-returned,.st-exchange-received,.st-returned,.st-return-pending,.st-cancel,.st-cancelled,.st-incomplete)>td:first-child{border-left:0!important;padding-left:12px}
main table.ordt>tbody>tr>td{border-top:1px solid rgba(15,23,42,.045)!important;border-bottom:1px solid rgba(15,23,42,.045)!important}
main table.ordt>tbody>tr>td:last-child{border-right:1px solid rgba(15,23,42,.045)!important}
aside nav{scrollbar-color:transparent transparent}aside nav:hover{scrollbar-color:#D5DBE3 transparent}
</style>
<style>
/* 11. note + comment live under the status (one column) */
table.ordt td.ost-cell,table.ordt td:has(>.stn){min-width:180px;max-width:230px;position:relative}
.stn{margin-top:6px;position:relative;font-size:11.5px;line-height:1.35;color:#4B5563}
.stn-r{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:210px}
.stn-r b{font-weight:700;color:#334155;margin-right:3px}.stn-r.c b{color:#B45309}
.stn-e{display:inline-flex;align-items:center;margin-top:3px;color:#9CA3AF}.stn-e:hover{color:#1D4ED8}
.stn-p{display:none;position:absolute;left:0;top:100%;z-index:30;width:260px;max-width:70vw;padding:9px 11px;background:#fff;border:1px solid #D1D5DB;border-radius:9px;box-shadow:0 8px 24px rgba(15,23,42,.14);color:#0F172A;font-size:12px;line-height:1.5;white-space:pre-wrap;word-break:break-word}
.stn-p b{font-size:10.5px;letter-spacing:.04em;text-transform:uppercase;color:#64748B}
.stn:hover .stn-p{display:block}
</style>
<style>table.ordt td:has(>.stn:hover){z-index:50}.stn-p{background:#fff!important}</style>
<style>
/* 12. status: larger and centred in its column */
table.ordt td.ost-cell,table.ordt td:has(>.stn){text-align:center;vertical-align:middle}
table.ordt td.ost-cell .pill,table.ordt td:has(>.stn)>.pill{display:inline-block;font-size:13px;font-weight:700;line-height:1;padding:7px 18px;border-radius:999px;min-width:86px;text-align:center}
.stn{text-align:center}.stn-r{margin-left:auto;margin-right:auto}
.stn-p{text-align:left;left:50%;transform:translateX(-50%)}
</style>
<style>
/* 13. order page: compact, line-by-line detail cards */
.card.os{padding:12px 14px;font-size:13px;line-height:1.5}
.os-h{font-size:11.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#64748B;margin:0 0 8px}
.mu{color:#64748B;font-weight:500;font-size:12px}.neg{color:#B91C1C}.big{font-size:15px}
dl.kv{display:grid;grid-template-columns:128px minmax(0,1fr);gap:5px 14px;margin:0}
dl.kv dt{color:#64748B;font-weight:500}
dl.kv dd{margin:0;color:#0F172A;font-weight:600;min-width:0;overflow-wrap:anywhere}
dl.kv dd.nt{background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:3px 9px;font-weight:600;color:#92400E}
dl.kv dd.mu2{font-weight:500;color:#334155}
dl.kv-top{margin-top:10px;padding-top:10px;border-top:1px solid #EEF2F7}
.os-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:12px;align-items:start}
.os-2>.card{margin-bottom:0}
.os-tiles{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-bottom:10px}
.os-tiles>div{background:#F8FAFC;border:1px solid #E8EDF3;border-radius:9px;padding:6px 10px;min-width:0}
.os-tiles small{display:block;font-size:11px;color:#64748B;font-weight:500}
.os-tiles b{font-size:15px;font-weight:700;color:#0F172A}
.os-tiles .tot{background:#F0FDF4;border-color:#BBF7D0}.os-tiles .tot b{color:#166534}
.os-chips{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin:0 0 10px}
.os-chips .ddp{margin:0!important}
.fc-t{display:grid;grid-template-columns:repeat(auto-fit,minmax(104px,1fr));gap:8px;margin-top:8px}
.fc-t>div{background:#F8FAFC;border:1px solid #E8EDF3;border-radius:9px;padding:5px 10px}
.fc-t small{display:block;font-size:11px;color:#64748B;font-weight:500}.fc-t b{font-size:14px;font-weight:700}
.fc-c{display:flex;flex-wrap:wrap;gap:6px 16px;margin-top:8px;color:#374151}
.fc-o{margin-top:6px;color:#64748B}
details.os-sms{border-top:1px solid #EEF2F7;padding:6px 0}
details.os-sms:first-of-type{border-top:0}
details.os-sms summary{display:flex;flex-wrap:wrap;align-items:center;gap:4px 12px;cursor:pointer;list-style:none}
details.os-sms summary::-webkit-details-marker{display:none}
details.os-sms summary:before{content:"▸";color:#9CA3AF;font-size:11px}
details.os-sms[open] summary:before{content:"▾"}
details.os-sms .ev{font-weight:600;color:#0F172A}
details.os-sms .chip{background:#EEF2FF;color:#4338CA;border-radius:999px;padding:0 8px;font-size:11.5px;font-weight:600}
details.os-sms .tm{margin-left:auto}
details.os-sms pre{white-space:pre-wrap;margin:6px 0 2px 18px;font:inherit;font-size:12.5px;line-height:1.6;color:#374151;background:#F8FAFC;border:1px solid #E8EDF3;border-radius:8px;padding:8px 10px}
details.os-sms small{display:block;margin:4px 0 0 18px}
@media(max-width:640px){dl.kv{grid-template-columns:104px minmax(0,1fr)}.os-tiles{grid-template-columns:repeat(2,minmax(0,1fr))}.os-2{grid-template-columns:minmax(0,1fr)}}
</style>
