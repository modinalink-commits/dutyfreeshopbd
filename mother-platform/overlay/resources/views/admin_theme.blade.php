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
  --border-main:#E3E7ED;--border-soft:#EDF0F4;
  --head-bg:#F4F6F8;--group-bg:#F0F3F7;--total-bg:#EDF1F5;
  --accent-tint:#FFF4EC;
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
.st-late-delivery,.st-exchange-received{--rt:var(--tint-orange);--ra:var(--acc-orange)}
.st-returned,.st-return-pending{--rt:var(--tint-pink);--ra:var(--acc-red)}
.st-cancel,.st-cancelled{--rt:var(--tint-redgray);--ra:var(--acc-gray)}
.st-incomplete{--rt:var(--tint-gray);--ra:var(--acc-gray)}
main table.ordt>tbody>tr:is(.st-new,.st-scheduled,.st-pending,.st-pre-order,.st-wfp,.st-assign,.st-ai-order,.st-packaging,.st-approved,.st-shipment,.st-delivered,.st-partial-delivered,.st-late-delivery,.st-exchange-received,.st-returned,.st-return-pending,.st-cancel,.st-cancelled,.st-incomplete)>td{background:var(--rt)}
main table.ordt>tbody>tr:is(.st-new,.st-scheduled,.st-pending,.st-pre-order,.st-wfp,.st-assign,.st-ai-order,.st-packaging,.st-approved,.st-shipment,.st-delivered,.st-partial-delivered,.st-late-delivery,.st-exchange-received,.st-returned,.st-return-pending,.st-cancel,.st-cancelled,.st-incomplete)>td:first-child{border-left-color:var(--ra)}
main table.ordt>tbody>tr:is(.st-new,.st-scheduled,.st-pending,.st-pre-order,.st-wfp,.st-assign,.st-ai-order,.st-packaging,.st-approved,.st-shipment,.st-delivered,.st-partial-delivered,.st-late-delivery,.st-exchange-received,.st-returned,.st-return-pending,.st-cancel,.st-cancelled,.st-incomplete):hover>td{background:var(--rt);filter:brightness(.985)}
main table.ordt>tbody>tr:has(input[type=checkbox]:checked)>td{background:var(--row-selected)!important}

/* ===== 4. table furniture: header, group, total (spec §35-37) ===== */
main table:not(.ordt):has(th) th,main table.ordt>thead>tr>th{background:var(--head-bg);color:#374151}
main table:not(.ordt):has(th),main table.ordt{border-color:var(--border-main)}
main table:not(.ordt):has(th) td,main table.ordt>tbody>tr>td{border-bottom-color:var(--border-soft);border-right-color:var(--border-soft)}
main table:not(.ordt):has(th) tr.g-group>td{background:var(--group-bg)}
main table:not(.ordt):has(th) tfoot td,main table:not(.ordt):has(th) tr.g-total>td{background:var(--total-bg);border-top:1px solid var(--border-main);font-weight:700}
/* soft tint for the first row-group zebra: only on tables that opt in with .g-zebra (never random) */
main table.g-zebra:not(.ordt) tbody tr:nth-child(even)>td{background:var(--surface-soft)}

/* ===== 5. surfaces: cards, forms, toolbar, filters (spec §29-32, §50-51) ===== */
main .card{background:var(--surface-main);border-color:var(--border-main)}
main .card:has(> form input,> form select,> form textarea),main form.card{background:var(--surface-soft)}
main .card input:not([type=checkbox]):not([type=radio]),main .card select,main .card textarea{background:var(--surface-main)}
.g-toolbar{background:var(--tint-slate);border-color:var(--border-main)}
.ocb{background:var(--tint-slate)!important;border-color:var(--border-main)!important}
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
aside nav details.mg[open]>summary{background:var(--tint-slate)}
aside nav,aside nav a.nv,aside nav details.mg>summary{border-color:var(--border-soft)}
.pgx{background:var(--tint-slate);border-color:var(--border-main)}
.pgx .b{background:var(--surface-main);border-color:var(--border-main)}
.pgx .b.on{background:var(--accent-tint)!important;color:#C2410C!important;border-color:#FDBA8C!important;font-weight:700}
dialog,[role=dialog],.modal,.modal-box{border:1px solid var(--border-main);background:var(--surface-main)}
dialog h3,[role=dialog] h3,.modal h3,.modal-box h3{background:var(--tint-slate)}
/* ===== 8. compact action icons (desktop only; mobile keeps big touch targets) ===== */
@media(min-width:761px){.ibar{gap:4px;margin-top:3px}.ibar .ib{width:24px;height:24px;border-radius:7px;box-shadow:none}.ibar .ib svg{width:12px;height:12px}.ibar .ib-call{height:24px;padding:0 9px;font-size:11px;border-radius:7px;gap:4px;box-shadow:none}.ibar .ib-call svg{width:11px;height:11px}}
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
