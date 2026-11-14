{{-- Mother Commerce — Ultra-Light Tint Grid colour system (ONE central theme).
     Included once by layout.blade.php right after admin_grid. Colours live ONLY in the :root tokens below — change a value there and every module follows.
     Visual layer only: no route, query, status or calculation logic is touched. Tints stay at 2–6 % so text contrast is unchanged. --}}
<link rel="stylesheet" href="/s/adm-theme-a.css?v=7288815d">
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
<link rel="stylesheet" href="/s/adm-theme-b.css?v=a747ab84">
