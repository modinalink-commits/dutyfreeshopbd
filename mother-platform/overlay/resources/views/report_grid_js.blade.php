<script>
(function(){var f=document.querySelector('form.bar');if(!f)return;var t;function go(){var u=location.pathname+'?'+new URLSearchParams(new FormData(f)).toString();var rb=document.querySelector('.rp-body');if(rb)rb.classList.add('mc-loading');fetch(u,{headers:{'X-Requested-With':'fetch'}}).then(function(r){return r.text()}).then(function(h){var d=new DOMParser().parseFromString(h,'text/html');['.rp-body'].forEach(function(s){var a=document.querySelector(s),b=d.querySelector(s);if(a&&b)a.innerHTML=b.innerHTML;if(a)a.classList.remove('mc-loading');if(window.__pgInit)window.__pgInit()});history.replaceState(null,'',u)}).catch(function(){if(rb)rb.classList.remove('mc-loading')})}
f.addEventListener('submit',function(e){e.preventDefault();go()});f.querySelectorAll('input[type=date],select').forEach(function(i){i.addEventListener('change',go)});var q=f.querySelector('input[name=q]');if(q)q.addEventListener('input',function(){clearTimeout(t);t=setTimeout(go,350)})})();
</script>
<script>
(function(){
var pb=document.querySelector('.noprint button.g[onclick*="print"]');if(!pb)return;
function load(u){return new Promise(function(ok,no){var s=document.createElement('script');s.src=u;s.onload=ok;s.onerror=function(){no(new Error('load '+u))};document.head.appendChild(s)})}
function prep(){document.querySelectorAll('.det,.pgh').forEach(function(e){e.classList.remove('hide');e.classList.remove('pgh')});var t=document.querySelector('table.pl');return t}
var title=(document.querySelector('h1')||{}).textContent||'report';var nm=title.trim().toLowerCase().replace(/[^a-z0-9]+/g,'-')+'-'+new Date().toISOString().slice(0,10);
function mk(label,fn){var b=document.createElement('button');b.type='button';b.className='g';b.textContent=label;b.onclick=function(){var o=b.textContent;b.textContent='Please wait...';b.disabled=true;fn().catch(function(e){alert('Export failed: '+e.message)}).then(function(){b.textContent=o;b.disabled=false})};pb.parentNode.insertBefore(b,pb);return b}
mk('Export .xlsx',function(){return load('https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js').then(function(){var t=prep();var wb=XLSX.utils.table_to_book(t,{raw:false});XLSX.writeFile(wb,nm+'.xlsx')})});
mk('Export PDF',function(){return load('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js').then(function(){return load('https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js')}).then(function(){var t=prep();var d=new window.jspdf.jsPDF({orientation:'landscape',unit:'pt',format:'a4'});d.setFontSize(13);d.text(title.trim(),30,28);var fr=document.querySelector('form.bar');d.setFontSize(8);d.text(new Date().toLocaleString(),30,40);d.autoTable({html:t,startY:48,styles:{fontSize:7,cellPadding:2},headStyles:{fillColor:[55,65,81]},margin:{left:30,right:30},didParseCell:function(h){var r=h.row.raw&&h.row.raw.classList;if(r&&(r.contains('gh')||r.contains('st')||r.contains('gt'))){h.cell.styles.fontStyle='bold';h.cell.styles.fillColor=r.contains('gt')?[229,231,235]:[243,244,246]}}});d.save(nm+'.pdf')})});
})();
</script>
<style>.pgh{display:none!important}@media print{.pgh{display:table-row!important}.pgbar{display:none!important}}.pgbar{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:12px 0 0;font-size:13px;color:#6B7280}.pgbar button{border:1px solid #D1D5DB;background:#fff;color:#0F172A!important;border-radius:8px;padding:6px 11px;min-width:34px;cursor:pointer;font-weight:600}.pgbar button.on{background:#0F172A;color:#fff!important;border-color:#0F172A}.pgbar button:disabled{opacity:.4;cursor:default}.pgbar .tot{margin-left:auto;font-size:12px}</style>
<script>
(function(){
var PER=50,page=1;
function rowsOf(t){return Array.prototype.slice.call(t.querySelectorAll('tbody > tr'))}
function render(){
 var t=document.querySelector('table.pl');if(!t)return;
 var old=document.querySelector('.pgbar');if(old)old.remove();
 var rs=rowsOf(t),dets=rs.filter(function(r){return r.classList.contains('det')&&!r.classList.contains('st')});
 rs.forEach(function(r){r.classList.remove('pgh')});
 if(dets.length<=PER)return;
 var pages=Math.ceil(dets.length/PER);if(page>pages)page=pages;if(page<1)page=1;
 var from=(page-1)*PER,to=from+PER,idx=0,grp=[],vis=0;
 function flush(){if(grp.length&&vis===0)grp.forEach(function(r){r.classList.add('pgh')});grp=[];vis=0}
 rs.forEach(function(r){
  if(r.classList.contains('gh')){flush();grp.push(r);return}
  if(r.classList.contains('st')){grp.push(r);flush();return}
  if(r.classList.contains('det')){var show=idx>=from&&idx<to;idx++;if(!show)r.classList.add('pgh');else vis++;return}
 });
 flush();
 var bar=document.createElement('div');bar.className='pgbar';
 var h='<button type="button" data-g="'+(page-1)+'"'+(page<=1?' disabled':'')+'>\u2039 Prev</button>',lo=Math.max(1,page-2),hi=Math.min(pages,page+2);if(lo>1)h+='<button type="button" data-g="1">1</button>'+(lo>2?'\u2026':'');for(var i=lo;i<=hi;i++)h+='<button type="button" class="'+(i===page?'on':'')+'" data-g="'+i+'">'+i+'</button>';if(hi<pages)h+=(hi<pages-1?'\u2026':'')+'<button type="button" data-g="'+pages+'">'+pages+'</button>';h+='<button type="button" data-g="'+(page+1)+'"'+(page>=pages?' disabled':'')+'>Next \u203a</button><span class="tot">'+dets.length.toLocaleString('en-IN')+' rows</span>';bar.innerHTML=h;
 bar.addEventListener('click',function(e){var b=e.target.closest('button');if(!b||b.disabled)return;page=parseInt(b.getAttribute('data-g'),10);render();var h=t.getBoundingClientRect().top+window.pageYOffset-80;window.scrollTo({top:Math.max(h,0)})});
 var host=t.parentNode;host.parentNode.insertBefore(bar,host.nextSibling);
}
window.__pgInit=function(){page=1;render()};
window.__pgInit();
})();
</script>
