/* Report Grid design system: compact bordered tables, sticky header, right-aligned numbers, bold subtotal/total rows. Applies to accounting/purchase/report pages only (not the order list). */
(function(){
var P=/^\/admin\/(purchase|m\/|reports?|accounts?|inventory|stock|suppliers?|couriers?|payments?|entries)/;
if(!P.test(location.pathname))return;
var css='table.rg{border-collapse:separate;border-spacing:0;width:100%;font-size:12.5px}table.rg th,table.rg td{border:1px solid #DFE3E8;border-width:0 1px 1px 0;padding:6px 10px!important;height:34px;vertical-align:middle}table.rg tr>:first-child{border-left-width:1px}table.rg thead tr:first-child th{border-top-width:1px}'+
'table.rg thead th{position:sticky;top:0;z-index:4;background:#EEF1F5;color:#1F2A44;font-weight:700;text-align:left;height:36px}table.rg tbody tr{height:34px}table.rg tbody tr:hover td{background:#FFF7F0}'+
'table.rg td.rgn,table.rg th.rgn{text-align:right;font-variant-numeric:tabular-nums}table.rg tr.tf td,table.rg tfoot td,table.rg tr.tot td{background:#F1F3F6;font-weight:700}table.rg td.pos{color:#15803D}table.rg td.neg{color:#B91C1C}'+
'table.rg tr.grp td{background:#E8EEF7;font-weight:600;color:#1F2A44}.rgw{overflow:auto;max-height:calc(100vh - 230px)}'+
'@media print{aside,.btn,button,form,.chips,.hd+.chips,#ocb{display:none!important}main{padding:0!important}table.rg thead th{position:static}.rgw{max-height:none;overflow:visible}}';
var s=document.createElement('style');s.textContent=css;document.head.appendChild(s);
var NUM=/^[\s৳$+\-−(]*[\d,]+(\.\d+)?%?\)?\s*(TK|৳)?$/i;
function apply(t){
 if(t.classList.contains('ordt')||t.classList.contains('rg')||!t.tHead)return;
 t.classList.add('rg');
 var rows=t.tBodies[0]?[].slice.call(t.tBodies[0].rows):[];var cols=t.tHead.rows[0]?t.tHead.rows[0].cells.length:0;
 for(var c=0;c<cols;c++){var n=0,m=0;rows.slice(0,30).forEach(function(r){var x=r.cells[c];if(!x)return;var v=x.innerText.trim();if(!v)return;m++;if(NUM.test(v))n++});
  if(m&&n/m>=.8){t.tHead.rows[0].cells[c].classList.add('rgn');rows.forEach(function(r){var x=r.cells[c];if(x){x.classList.add('rgn');var v=x.innerText.trim();if(/^[\-−]/.test(v))x.classList.add('neg')}})}}
 var p=t.parentElement;if(p&&!p.classList.contains('rgw')&&p!==document.querySelector('main')){p.classList.add('rgw')}
}
function run(){[].forEach.call(document.querySelectorAll('main table'),apply)}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
var t;new MutationObserver(function(){clearTimeout(t);t=setTimeout(run,400)}).observe(document.body,{childList:true,subtree:true});
})();
