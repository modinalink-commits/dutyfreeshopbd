@if(in_array(optional(auth()->user())->role, ['super','admin'], true))
<style>
#dfBell{position:fixed;top:10px;right:14px;z-index:9990;font:13px/1.4 system-ui,sans-serif}
#dfBell .bt{width:34px;height:34px;border:1px solid #E5E7EB;background:#fff;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 3px rgba(15,23,42,.08);position:relative;padding:0}
#dfBell .bt svg{width:17px;height:17px;stroke:#0F172A;fill:none;stroke-width:1.8}
#dfBell .bd{position:absolute;top:-5px;right:-5px;min-width:17px;height:17px;border-radius:9px;background:#E11D48;color:#fff;font-size:10px;font-weight:700;display:none;align-items:center;justify-content:center;padding:0 4px}
#dfBell .pn{display:none;position:absolute;top:40px;right:0;width:340px;max-width:calc(100vw - 24px);max-height:70vh;overflow:auto;background:#fff;border:1px solid #E5E7EB;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.14)}
#dfBell.on .pn{display:block}
#dfBell .hd{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-bottom:1px solid #EEF0F3;font-weight:700;color:#0F172A}
#dfBell .hd a{font-weight:600;font-size:12px;color:#F97316;text-decoration:none}
#dfBell .it{padding:10px 12px;border-bottom:1px solid #F1F3F5;color:#0F172A}
#dfBell .it:last-child{border:0}
#dfBell .it b{display:block;font-size:13px}
#dfBell .it small{display:block;color:#64748B;margin-top:2px}
#dfBell .it .sv{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:6px;background:#F59E0B}
#dfBell .it .sv.critical,#dfBell .it .sv.high{background:#E11D48}
#dfBell .it.seen{opacity:.6}
#dfBell .it button{margin-top:6px;border:1px solid #E5E7EB;background:#fff;border-radius:7px;font-size:11px;padding:3px 8px;cursor:pointer;color:#0F172A}
#dfBell .em{padding:18px 12px;color:#64748B;text-align:center}
@media(max-width:900px){#dfBell{top:8px;right:8px}}
</style>
<div id="dfBell"><button class="bt" type="button" aria-label="Alerts"><svg viewBox="0 0 24 24"><path d="M6 9a6 6 0 0 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg><span class="bd"></span></button>
<div class="pn"><div class="hd"><span>Alerts</span><a href="/admin/deploy/issues">All issues</a></div><div class="ls"><div class="em">Loading…</div></div></div></div>
<script>(function(){
var r=document.getElementById('dfBell');if(!r||r.dataset.on)return;r.dataset.on=1;
var bt=r.querySelector('.bt'),bd=r.querySelector('.bd'),ls=r.querySelector('.ls');
function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML}
function draw(j){
 bd.style.display=j.count>0?'flex':'none';bd.textContent=j.count>9?'9+':j.count;
 if(!j.items.length){ls.innerHTML='<div class="em">No active alerts</div>';return}
 ls.innerHTML=j.items.map(function(i){return '<div class="it'+(i.status==='open'?'':' seen')+'"><b><span class="sv '+esc(i.severity)+'"></span>'+esc(i.title)+'</b><small>'+esc(i.service)+' · '+esc(i.detected)+'</small><small>'+esc(i.description)+'</small>'+(i.action?'<small>→ '+esc(i.action)+'</small>':'')+(i.status==='open'?'<button data-id="'+i.id+'">Mark seen</button>':'')+'</div>'}).join('');
}
function load(){fetch('/admin/alerts',{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(x){return x.ok?x.json():null}).then(function(j){if(j)draw(j)}).catch(function(){})}
bt.addEventListener('click',function(e){e.stopPropagation();r.classList.toggle('on');if(r.classList.contains('on'))load()});
document.addEventListener('click',function(e){if(!r.contains(e.target))r.classList.remove('on')});
ls.addEventListener('click',function(e){var b=e.target.closest('button[data-id]');if(!b)return;var t=document.querySelector('meta[name=csrf-token]');
 fetch('/admin/deploy/issue/'+b.dataset.id,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':t?t.content:''},body:JSON.stringify({status:'investigating'})}).then(load)});
setTimeout(load,1500);setInterval(function(){if(!document.hidden)load()},60000);
})();</script>
@endif
