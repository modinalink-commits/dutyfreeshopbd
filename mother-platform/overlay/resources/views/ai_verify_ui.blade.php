{{-- AI Verify: drawer + button colouring. Included once on the orders list and on the order page. Buttons: .ai-btn[data-id]. --}}
<style>
.ai-btn{grid-column:1/-1}
td.action-column .order-action-grid .ai-btn{background:#F1EBFF!important;border:1px solid #CDB9FF!important;color:#3B1D8A!important;font-weight:700}
td.action-column .order-action-grid .ai-btn:hover{background:#E6DAFF!important}
td.action-column .order-action-grid .ai-btn.s-verified{background:#E8F8EF!important;border-color:#86D9A9!important;color:#047857!important}
td.action-column .order-action-grid .ai-btn.s-review{background:#FFF6E0!important;border-color:#F2C46B!important;color:#92400E!important}
td.action-column .order-action-grid .ai-btn.s-high{background:#FEECEC!important;border-color:#F3A0A0!important;color:#B91C1C!important}
td.action-column .order-action-grid .ai-btn.s-failed{background:#FBE0E0!important;border-color:#D96A6A!important;color:#7F1D1D!important}
td.action-column .order-action-grid .ai-btn.s-pending{background:#EEF2F7!important;border-color:#C5CFDC!important;color:#475569!important}
.aiv-ov{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9998;display:none}.aiv-ov.on{display:block}
.aiv{position:fixed;top:0;right:0;bottom:0;width:480px;max-width:100%;background:#fff;z-index:9999;box-shadow:-12px 0 40px rgba(15,23,42,.22);display:flex;flex-direction:column;transform:translateX(102%);transition:transform .25s}.aiv.on{transform:none}
.aiv-h{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #E5E9F0}.aiv-h b{font-size:15px;color:#1E1B4B}.aiv-h small{display:block;color:#64748B;font-size:11.5px;font-weight:500}
.aiv-x{border:0;background:#F1F5F9;border-radius:8px;width:30px;height:30px;font-size:18px;cursor:pointer}
.aiv-b{overflow:auto;padding:14px 16px 20px;flex:1}
.aiv-top{border-radius:12px;padding:14px;display:flex;align-items:center;gap:14px;border:1px solid}
.aiv-top.verified{background:#ECFDF5;border-color:#A7F3D0;color:#047857}.aiv-top.review{background:#FFFBEB;border-color:#FDE68A;color:#92400E}.aiv-top.high{background:#FEF2F2;border-color:#FECACA;color:#B91C1C}.aiv-top.failed{background:#FDECEC;border-color:#E8A0A0;color:#7F1D1D}.aiv-top.pending{background:#F1F5F9;border-color:#CBD5E1;color:#475569}
.aiv-sc{font-size:30px;font-weight:800;line-height:1}.aiv-sc small{font-size:12px;font-weight:600;opacity:.7}.aiv-st{font-size:16px;font-weight:800;letter-spacing:.02em;text-transform:uppercase}.aiv-top p{margin:2px 0 0;font-size:12px;opacity:.85}
.aiv-g{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin:12px 0}.aiv-g div{background:#F8FAFC;border:1px solid #E5E9F0;border-radius:10px;padding:8px 10px}.aiv-g span{display:block;font-size:10.5px;color:#64748B;font-weight:600;text-transform:uppercase;letter-spacing:.04em}.aiv-g b{font-size:14px;color:#0F172A}
.aiv h4{margin:16px 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#475569}
.aiv-why{background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:8px 12px;font-size:12.5px}.aiv-why.ok{background:#ECFDF5;border-color:#A7F3D0}.aiv-why div{padding:3px 0}
.aiv-ck{display:flex;gap:9px;padding:7px 0;border-bottom:1px solid #F1F5F9;font-size:12.5px}.aiv-ck i{font-style:normal;font-weight:800;width:18px;flex:none;text-align:center}.aiv-ck.pass i{color:#059669}.aiv-ck.review i{color:#D97706}.aiv-ck.fail i{color:#DC2626}.aiv-ck.info i{color:#64748B}
.aiv-ck b{display:block;color:#0F172A;font-size:12.5px}.aiv-ck span{color:#475569}.aiv .cd{display:block;background:none!important;border:0!important;padding:0!important;margin:1px 0 2px!important;color:#7C8AA0!important;font-size:10px!important;font-weight:600;font-family:ui-monospace,Menlo,monospace;letter-spacing:.03em;box-shadow:none!important}
.aiv-f{display:flex;gap:8px;padding:12px 16px;border-top:1px solid #E5E9F0}.aiv-f button,.aiv-ovf button{height:34px;border-radius:8px;border:1px solid #CBD5E1;background:#fff;padding:0 14px;font-weight:700;cursor:pointer;font-size:12.5px}.aiv-f button,.aiv-ovf button{color:#0F172A!important}.aiv-f .p{background:#4C1D95!important;color:#fff!important;border-color:#4C1D95!important}.aiv-x{color:#0F172A!important}
.aiv-ovf{border:1px dashed #CBD5E1;border-radius:10px;padding:10px;margin-top:8px}.aiv-ovf select,.aiv-ovf input{width:100%;height:32px;border:1px solid #CBD5E1;border-radius:8px;padding:0 8px;margin:4px 0;font-size:12.5px;box-sizing:border-box}
.aiv-h2{width:100%;border-collapse:collapse;font-size:11.5px}.aiv-h2 td{padding:4px 6px;border-bottom:1px solid #F1F5F9;color:#475569}
.aiv-ld{padding:40px 10px;text-align:center;color:#64748B}
</style>
<div class="aiv-ov" id="aivOv"></div>
<aside class="aiv" id="aiv" aria-hidden="true"><div class="aiv-h"><div><b>AI Verification</b><small id="aivSub">Order</small></div><button type="button" class="aiv-x" id="aivX" aria-label="Close">×</button></div><div class="aiv-b" id="aivB"></div><div class="aiv-f"><button type="button" class="p" id="aivRe">Re-Verify</button><button type="button" id="aivC">Close</button></div></aside>
<script>(function(){
var D={verified:'Verified',review:'Review',high:'High Risk',failed:'Failed',pending:'Pending'},IC={verified:'✓',review:'⚠',high:'✕',failed:'✕',pending:'●'},cur=0,tok=function(){var m=document.querySelector('meta[name=csrf-token]');return m?m.content:''};
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
var ov=document.getElementById('aivOv'),dr=document.getElementById('aiv'),B=document.getElementById('aivB');
function open(){ov.classList.add('on');dr.classList.add('on');dr.setAttribute('aria-hidden','false')}
function close(){ov.classList.remove('on');dr.classList.remove('on');dr.setAttribute('aria-hidden','true')}
ov.onclick=close;document.getElementById('aivX').onclick=close;document.getElementById('aivC').onclick=close;document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});
function paint(btn,st,sc){if(!btn)return;btn.className=btn.className.replace(/\bs-\w+/g,'').trim()+' s-'+st;var t=btn.querySelector('.ai-t');if(t)t.textContent=(IC[st]||'')+' '+(D[st]||'AI Verify')+(sc!=null?' '+sc+'%':'')}
function render(r){var st=r.status,s=r.summary||{};document.getElementById('aivSub').textContent='Order #'+r.order_id+' · '+r.at+' · '+(r.version||'')+' · run '+r.runs;
var h='<div class="aiv-top '+st+'"><div class="aiv-sc">'+r.score+'<small>/100</small></div><div><div class="aiv-st">'+esc(r.label)+'</div><p>Risk: '+esc(r.risk||'-')+(r.override?' · manual override by '+esc(r.override.by):'')+'</p></div></div>';
if(r.override)h+='<div class="aiv-why" style="margin-top:8px">AI result was <b>'+esc(r.ai_label)+'</b>. Staff override to <b>'+esc(r.override.label)+'</b> ('+esc(r.override.by)+', '+esc(r.override.at)+'): '+esc(r.override.reason)+'</div>';
function cell(a,b){return'<div><span>'+a+'</span><b>'+esc(b)+'</b></div>'}
h+='<div class="aiv-g">'+cell('Customer reliability',r.reliability==null?'New':r.reliability+'%')+cell('Fraud risk',(s.fraud_risk||'-')+(r.fraud!=null?' ('+r.fraud+'/100)':''))+cell('Phone match',s.phone_match||'-')+cell('Previous orders',s.prev_orders!=null?s.prev_orders:'-')+cell('Address match',s.address||'-')+cell('Customer tier',s.tier||'-')+cell('Payment',s.payment||'-')+cell('Stock',s.stock||'-')+cell('Margin',r.can_cost||true?(s.margin||'-'):'-')+cell('Courier success',s.courier_rate!=null?s.courier_rate+'%':'-')+'</div>';
h+='<h4>Why?</h4>';var rs=r.reasons||[];
if(rs.length){h+='<div class="aiv-why">';rs.forEach(function(c){h+='<div>'+(c.r==='fail'?'✕':'⚠')+' '+esc(c.d)+' <span class="cd" style="display:inline">'+esc(c.c)+'</span></div>'});h+='</div>'}else h+='<div class="aiv-why ok">✓ Nothing unusual found. Every check passed.</div>';
if(s.critical&&s.critical.length)h+='<div class="aiv-why" style="margin-top:6px;background:#FEF2F2;border-color:#FECACA">Critical rule(s) forced human review: <b>'+s.critical.map(esc).join(', ')+'</b></div>';
var G={};(r.checks||[]).forEach(function(c){(G[c.g]=G[c.g]||[]).push(c)});
['Customer','Fraud','Order','Payment','Courier','Stock','Pricing','History','Communication'].forEach(function(g){if(!G[g])return;h+='<h4>'+g+'</h4>';G[g].forEach(function(c){var ic=c.r==='pass'?'✓':c.r==='review'?'⚠':c.r==='fail'?'✕':'i';h+='<div class="aiv-ck '+c.r+'"><i>'+ic+'</i><div><b>'+esc(c.t)+'</b><span class="cd">'+esc(c.c)+'</span><span>'+esc(c.d)+'</span></div></div>'})});
h+='<h4>Verification history</h4><table class="aiv-h2">';(r.history||[]).forEach(function(x){h+='<tr><td>#'+x.no+'</td><td>'+esc(x.prev)+' → <b>'+esc(x.status)+'</b> ('+x.score+')</td><td>'+esc(x.by)+'</td><td>'+esc(x.at)+(x.note?'<br>'+esc(x.note):'')+'</td></tr>'});h+='</table>';
h+='<p style="font-size:11px;color:#64748B;margin-top:10px">AI Verify supports staff decisions. It never cancels, blocks, refunds or blacklists. Verified ≥ '+r.threshold.verified+', Review ≥ '+r.threshold.review+'.</p>';
if(r.can_override)h+='<h4>Staff override</h4><div class="aiv-ovf"><select id="aivOs"><option value="verified">AI Verified</option><option value="review">Review Required</option><option value="high">High Risk</option><option value="pending">Pending Verification</option></select><input id="aivOr" maxlength="300" placeholder="Override reason (required)"><button type="button" id="aivOk">Save override</button></div>';
B.innerHTML=h;var ok=document.getElementById('aivOk');if(ok)ok.onclick=function(){var rs=document.getElementById('aivOr').value.trim();if(rs.length<5){document.getElementById('aivOr').focus();return}ok.disabled=true;fetch('/admin/orders/'+cur+'/ai-verify/override',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':tok()},body:JSON.stringify({status:document.getElementById('aivOs').value,reason:rs})}).then(function(x){return x.json()}).then(function(j){render(j);upd(j)}).catch(function(){ok.disabled=false})};
}
function upd(r){document.querySelectorAll('.ai-btn[data-id="'+r.order_id+'"]').forEach(function(b){paint(b,r.status,r.score)})}
function load(id,run){cur=id;open();B.innerHTML='<div class="aiv-ld">Verifying order #'+id+' …<br>Checking customer, fraud, payment, stock, pricing and history</div>';document.getElementById('aivSub').textContent='Order #'+id;
fetch('/admin/orders/'+id+'/ai-verify'+(run?'?run=1':''),{headers:{Accept:'application/json'}}).then(function(x){if(!x.ok)throw 0;return x.json()}).then(function(j){render(j);upd(j)}).catch(function(){B.innerHTML='<div class="aiv-ld">Could not run AI Verify. Please try again.</div>'})}
document.addEventListener('click',function(e){var b=e.target.closest('.ai-btn');if(b){e.preventDefault();load(parseInt(b.getAttribute('data-id'),10),false)}});
document.getElementById('aivRe').onclick=function(){if(cur)load(cur,true)};
var ids=[].map.call(document.querySelectorAll('.ai-btn[data-id]'),function(b){return b.getAttribute('data-id')});
function stat(n){if(!ids.length)return;fetch('/admin/orders/ai-status?ids='+ids.join(','),{headers:{Accept:'application/json'}}).then(function(x){return x.json()}).then(function(j){var m=j.m||{};document.querySelectorAll('.ai-btn[data-id]').forEach(function(b){var v=m[b.getAttribute('data-id')];if(v)paint(b,v.s,v.n)});if(j.left>0&&n<8)setTimeout(function(){stat(n+1)},800)}).catch(function(){})}
stat(0);
})();</script>
