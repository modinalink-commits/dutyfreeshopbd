{{-- "Customer kept waiting" popup for staff (14 Nov 2026). Shown to the agent the chat was with when a customer passed the reply limit; the owner sees the same case in the Issues Center. --}}
@auth
<style>#swn{position:fixed;inset:0;background:rgba(11,27,51,.55);z-index:99990;display:none;align-items:center;justify-content:center;padding:14px}#swn.on{display:flex}
#swn .bx{background:#fff;border-radius:14px;width:min(520px,100%);max-height:90vh;overflow:auto;box-shadow:0 20px 50px rgba(0,0,0,.25);font:14px Inter,'Hind Siliguri',system-ui,sans-serif;color:#0B1B33;position:relative}
#swn .hd{display:flex;align-items:center;gap:8px;padding:14px 48px 12px 16px;border-bottom:1px solid #EEF0F3;font-weight:700;font-size:15px}#swn .hd i{width:9px;height:9px;border-radius:50%;background:#D92D20;flex:none}
#swn .x{position:absolute;right:10px;top:10px;width:30px;height:30px;border-radius:8px;border:0;background:#FEE2E2;color:#B91C1C;font-size:20px;line-height:1;font-weight:700;cursor:pointer}#swn .x:hover{background:#FECACA}
#swn .it{padding:12px 16px;border-bottom:1px solid #F2F4F7}#swn .it b{display:block;font-size:15px;margin-bottom:3px}#swn .it p{margin:0 0 8px;color:#344054;line-height:1.5}
#swn .it a{display:inline-flex;align-items:center;height:32px;padding:0 12px;border-radius:8px;background:#F26B1D;color:#fff;font-weight:700;font-size:13px;text-decoration:none}
#swn .ft{padding:10px 16px;font-size:12.5px;color:#475467}#swn .ft a{color:#C2410C;font-weight:600}</style>
<div id="swn" role="dialog" aria-modal="true" aria-label="Customer kept waiting"><div class="bx"><button type="button" class="x" id="swnX" title="Close" aria-label="Close">×</button><div class="hd"><i></i>Customer kept waiting</div><div id="swnL"></div><div class="ft">This is recorded on your <a href="/admin/my-day">My Day</a> page. The admin has been informed.</div></div></div>
<script>(function(){var box=document.getElementById('swn'),list=document.getElementById('swnL'),ids=[],T=(document.querySelector('meta[name=csrf-token]')||{}).content||'';
function esc(t){return String(t==null?'':t).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function seen(){if(!ids.length)return;var s=ids;ids=[];fetch('/admin/my-day/notices/seen',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':T},body:JSON.stringify({ids:s})}).catch(function(){})}
function load(){if(box.classList.contains('on')||document.hidden)return;fetch('/admin/my-day/notices',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(r){return r.ok?r.json():null}).then(function(j){var a=j&&j.notices||[];if(!a.length)return;ids=a.map(function(n){return n.id});
 list.innerHTML=a.map(function(n){return '<div class="it"><b>'+esc(n.customer)+' has waited '+n.min+' minutes with no reply.</b><p>'+(n.mine?'This chat was with you. Why is this customer still waiting? A customer left without an answer goes to another shop.':'Nobody had taken this chat. Why is this customer still waiting? Whoever is free must answer now.')+'</p><a href="/admin/chat?open='+n.thread+'">Reply now</a></div>'}).join('');box.classList.add('on')}).catch(function(){})}
document.getElementById('swnX').onclick=function(){box.classList.remove('on');seen()};list.addEventListener('click',function(e){if(e.target.closest('a'))seen()});
setTimeout(load,4000);setInterval(load,60000)})();</script>
@endauth
