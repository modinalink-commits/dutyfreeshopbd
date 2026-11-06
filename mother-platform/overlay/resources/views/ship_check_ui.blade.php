<style>#scm{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;display:none;align-items:center;justify-content:center;padding:14px}#scm.on{display:flex}#scm .bx{background:#fff;border-radius:14px;max-width:760px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,.35)}#scm .hd{padding:16px 20px;border-bottom:1px solid #E5E7EB}#scm .hd h3{margin:0;font-size:18px}#scm .hd p{margin:4px 0 0;color:#6B7280;font-size:13px}#scm .sm{display:flex;gap:8px;flex-wrap:wrap;padding:12px 20px}#scm .sm span{padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700}#scm .od{margin:0 20px 12px;border:1px solid #E5E7EB;border-radius:10px;overflow:hidden}#scm .oh{display:flex;justify-content:space-between;align-items:center;padding:9px 12px;background:#F9FAFB;font-weight:700;font-size:13px}#scm .ck{padding:7px 12px;font-size:12.5px;border-top:1px solid #F3F4F6;display:flex;gap:8px}#scm .ck b{min-width:104px}#scm .s-WARNING{color:#B45309}#scm .s-CRITICAL{color:#C2410C}#scm .s-BLOCKED{color:#B91C1C}#scm .s-INFO{color:#15803D}#scm .dt{font-size:11.5px;color:#6B7280;padding:0 12px 7px 124px}#scm .ov{padding:9px 12px;background:#FFF7ED;border-top:1px solid #FED7AA;font-size:12.5px}#scm .ov select,#scm .ov input{padding:5px 7px;border:1px solid #D1D5DB;border-radius:6px;font-size:12.5px;margin:3px 4px 0 0}#scm .ft{display:flex;gap:8px;justify-content:flex-end;padding:14px 20px;border-top:1px solid #E5E7EB;position:sticky;bottom:0;background:#fff}#scm .ft button{padding:9px 16px;border-radius:8px;border:1px solid #D1D5DB;background:#fff;color:#111827;font-size:13px;font-weight:700;cursor:pointer}#scm .ft button.p{background:#0F766E;color:#fff !important;border-color:#0F766E}</style>
<div id="scm"><div class="bx"><div class="hd"><h3>Shipment Validation</h3><p id="scs"></p></div><div class="sm" id="scsum"></div><div id="scb"></div><div class="ft"><button type="button" id="schold">Hold Shipment</button><button type="button" class="p" id="scgo">Send</button></div></div></div>
<script>(function(){
var M=document.getElementById('scm');if(!M)return;var D=null,tk=document.querySelector('meta[name=csrf-token]');
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
var IC={INFO:'✔',WARNING:'⚠',CRITICAL:'⛔',BLOCKED:'🔴'};
function draw(){var o=D.orders,ok=0,wr=0,cr=0,bl=0,h='';
o.forEach(function(x){if(x.worst==='INFO')ok++;else if(x.worst==='WARNING'){wr++}else if(x.worst==='CRITICAL')cr++;else bl++;
h+='<div class="od" data-id="'+x.id+'"><div class="oh"><span>Order #'+esc(x.order)+'</span><span class="s-'+x.worst+'">'+x.passed+' of '+x.total+' checks passed · '+x.worst+'</span></div>';
x.checks.forEach(function(c){if(c.sev==='INFO'&&x.worst!=='INFO'&&c.key!=='stock'&&c.key!=='margin')return;h+='<div class="ck"><b class="s-'+c.sev+'">'+IC[c.sev]+' '+esc(c.label)+'</b><span>'+esc(c.msg)+'</span></div>';
if(c.detail&&c.detail.length&&c.sev!=='INFO'){c.detail.forEach(function(d){var t=[];for(var k in d)t.push(k.replace('_',' ')+': '+d[k]);h+='<div class="dt">'+esc(t.join(' · '))+'</div>'})}});
if(x.worst==='CRITICAL'){h+='<div class="ov">'+(D.can_override?'<label><input type="checkbox" class="sa"> Send Anyway (I take responsibility)</label><br><select class="rs"><option value="">Override reason *</option><option>Customer-approved special rate</option><option>Management-approved discount</option><option>Price correction</option><option>Campaign order</option><option>Other</option></select><input class="rn" placeholder="Note (required for Other)" size="30">':'You do not have permission to override. Use Hold Shipment and ask an authorised person.')+'</div>'}
h+='</div>'});
document.getElementById('scb').innerHTML=h;
document.getElementById('scs').textContent=o.length+' order(s) selected';
document.getElementById('scsum').innerHTML='<span style="background:#DCFCE7;color:#15803D">Passed: '+ok+'</span><span style="background:#FEF3C7;color:#B45309">Warning: '+wr+'</span><span style="background:#FFEDD5;color:#C2410C">Critical: '+cr+'</span><span style="background:#FEE2E2;color:#B91C1C">Blocked: '+bl+'</span>';
var n=ok+wr;document.getElementById('scgo').textContent='Send '+n+' order(s) that can ship'+(cr&&D.can_override?' (+ checked overrides)':'')}
function go(){var f=document.createElement('form');f.method='post';f.action='/admin/courier/send-bulk';var n=0;
function add(k,v){var i=document.createElement('input');i.type='hidden';i.name=k;i.value=v;f.appendChild(i)}
add('_token',tk?tk.content:'');var err='';
D.orders.forEach(function(x){var el=M.querySelector('.od[data-id="'+x.id+'"]');
if(x.worst==='INFO'||x.worst==='WARNING'){add('ids[]',x.id);n++}
else if(x.worst==='CRITICAL'&&D.can_override&&el&&el.querySelector('.sa')&&el.querySelector('.sa').checked){var r=el.querySelector('.rs').value,t=el.querySelector('.rn').value.trim();
if(!r||(r==='Other'&&!t)){err='Choose an override reason (and write a note for Other) for #'+x.order;return}
add('ids[]',x.id);add('override['+x.id+']',r+(t?' — '+t:''));n++}});
if(err){alert(err);return}if(!n){alert('Nothing can be sent. Fix the issues or use Hold Shipment.');return}
document.body.appendChild(f);f.submit()}
document.getElementById('scgo').onclick=go;document.getElementById('schold').onclick=function(){M.classList.remove('on')};
M.addEventListener('click',function(e){if(e.target===M)M.classList.remove('on')});
window.dfShipCheck=function(ev){var ids=[].map.call(document.querySelectorAll('#bulk input[name="ids[]"]:checked'),function(i){return i.value});
if(!ids.length){alert('Select orders first');return false}
var fd=new FormData();ids.forEach(function(i){fd.append('ids[]',i)});fd.append('_token',tk?tk.content:'');
fetch('/admin/courier/ship-check',{method:'POST',body:fd,headers:{'X-CSRF-TOKEN':tk?tk.content:'','Accept':'application/json'}}).then(function(r){return r.json()}).then(function(j){D=j;draw();M.classList.add('on')}).catch(function(){alert('Could not run the shipment check. Please try again.')});
return false}})();</script>
