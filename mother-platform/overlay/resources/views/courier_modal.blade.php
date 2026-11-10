@once
<style>
#cpov{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:100000;display:none;align-items:center;justify-content:center;padding:16px}
#cpov.on{display:flex}
#cpbx{background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.35);width:min(460px,100%);max-height:88vh;display:flex;flex-direction:column;overflow:hidden;font-family:inherit}
#cpbx .dh{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 16px;border-bottom:1px solid #E5E7EB;flex:none}
#cpbx .dh b{font-size:15px}#cpbx .dh small{color:#6B7280;font-weight:600;margin-left:6px}
#cpbx .cb{overflow:auto;flex:1 1 auto}
#cpbx .top{padding:12px 16px;background:#F8FAFC;border-bottom:1px solid #E5E7EB}
#cpbx .top .nm{font-size:13px;color:#4B5563;font-weight:700}
#cpbx .top .id{display:flex;align-items:center;gap:8px;margin-top:3px;font-size:16px;font-weight:800;color:#0B1220;word-break:break-all}
#cpbx .st{display:inline-block;margin-top:8px;padding:4px 12px;border-radius:999px;font-weight:800;font-size:13px;color:#0B1220;background:#E0F2FE;border:1px solid #BAE6FD}
#cpbx .st.s-delivered{background:#DCFCE7;border-color:#BBF7D0}#cpbx .st.s-returned,#cpbx .st.s-return{background:#F3E8FF;border-color:#E9D5FF}#cpbx .st.s-hold,#cpbx .st.s-pending{background:#FEF3C7;border-color:#FDE68A}#cpbx .st.s-cancelled{background:#FEE2E2;border-color:#FECACA}
#cpbx .dr{display:flex;gap:10px;padding:8px 16px;border-bottom:1px solid #F1F5F9;font-size:13px;line-height:1.35}
#cpbx .dr span{flex:0 0 110px;color:#6B7280;font-weight:600}#cpbx .dr div{flex:1;color:#0B1220;font-weight:600;word-break:break-word}
#cpbx .tl{padding:10px 16px 6px}#cpbx .tl h4{margin:0 0 8px;font-size:12px;color:#4B5563;text-transform:uppercase;letter-spacing:.04em}
#cpbx .ev{position:relative;padding:0 0 12px 18px;border-left:2px solid #E5E7EB;margin-left:5px;font-size:13px}
#cpbx .ev:before{content:"";position:absolute;left:-6px;top:3px;width:10px;height:10px;border-radius:50%;background:#9CA3AF;border:2px solid #fff;box-shadow:0 0 0 1px #D1D5DB}
#cpbx .ev:first-of-type:before{background:#16A34A;box-shadow:0 0 0 1px #86EFAC}
#cpbx .ev b{display:block;color:#0B1220}#cpbx .ev small{color:#6B7280;display:block;font-size:11.5px}
#cpbx .ft{display:flex;gap:8px;padding:12px 16px;border-top:1px solid #E5E7EB;flex:none;flex-wrap:wrap}
#cpbx .ft a,#cpbx .ft button{flex:1 1 auto;text-align:center;padding:8px 12px;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;text-decoration:none;color:#0B1220;border:1px solid #BFD6FA;background:#EAF2FF}
#cpbx .ft a{background:#E7F8EC;border-color:#B5E3C2}
#cpbx .ms{padding:22px 16px;text-align:center;color:#4B5563;font-size:13px}
#cpbx .cc{border:1px solid #D1D5DB;background:#fff;border-radius:7px;padding:2px 8px;font-size:11px;font-weight:700;cursor:pointer;color:#0B1220}
</style>
<div id="cpov" role="dialog" aria-modal="true" aria-label="Courier tracking"><div id="cpbx"></div></div>
<script>
(function(){
var ov=document.getElementById('cpov'),bx=document.getElementById('cpbx');if(!ov||!bx)return;
function el(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!==undefined&&x!==null)e.textContent=x;return e}
function row(k,v){if(v===null||v===undefined||v==='')return null;var r=el('div','dr');r.appendChild(el('span',null,k));r.appendChild(el('div',null,v));return r}
function money(n){return n===null||n===undefined?null:'৳'+Number(n).toLocaleString('en-US',{maximumFractionDigits:2})}
function copy(t,btn,done){t=String(t||'');if(!t)return;var o=btn.textContent;function ok(){btn.textContent=done||'✓ Copied';setTimeout(function(){btn.textContent=o},1400)}
function fb(){var x=document.createElement('textarea');x.value=t;x.style.cssText='position:fixed;opacity:0';document.body.appendChild(x);x.select();try{document.execCommand('copy')}catch(e){}document.body.removeChild(x);ok()}
try{if(navigator.clipboard&&window.isSecureContext)navigator.clipboard.writeText(t).then(ok,fb);else fb()}catch(e){fb()}}
function shell(inv){bx.textContent='';var h=el('div','dh');var t=el('b',null,'Courier tracking');if(inv)t.appendChild(el('small',null,inv));var c=el('button');c.type='button';c.textContent='×';c.setAttribute('aria-label','Close');c.onclick=close;h.appendChild(t);h.appendChild(c);bx.appendChild(h);var b=el('div','cb');bx.appendChild(b);return b}
function cls(s){s=String(s||'').toLowerCase();return /deliver/.test(s)&&!/fail|undeliver/.test(s)?'s-delivered':/return/.test(s)?'s-return':/cancel/.test(s)?'s-cancelled':/hold|pending|fail/.test(s)?'s-pending':''}
function show(d){
var b=shell(d.invoice);
if(!d.ok||!d.cid){b.appendChild(el('div','ms',d.error||'This order has not been sent to a courier yet.'));ov.className='on';return}
var top=el('div','top');top.appendChild(el('div','nm',d.courier||'Courier'));
var id=el('div','id');id.appendChild(el('span',null,d.cid));var cc=el('button','cc','Copy ID');cc.type='button';cc.onclick=function(){copy(d.cid,cc,'✓')};id.appendChild(cc);top.appendChild(id);
var st=el('span','st '+cls(d.status||d.stage),d.status||'Waiting for courier');top.appendChild(st);b.appendChild(top);
[row('Stage',d.stage),row('Location',d.location),row('Last synced',d.synced||'Not synced yet'),row('Delivered at',d.delivered),row('COD amount',money(d.cod)),row('Collected',money(d.collected)),row('Delivery fee',money(d.fee)),row('Settlement',d.settlement)].forEach(function(r){if(r)b.appendChild(r)});
var tl=el('div','tl');tl.appendChild(el('h4',null,'Tracking history'));
if(d.events&&d.events.length){d.events.forEach(function(e){var x=el('div','ev');x.appendChild(el('b',null,e.status));var m=[e.at,e.stage,e.loc].filter(Boolean).join(' · ');if(m)x.appendChild(el('small',null,m));tl.appendChild(x)})}
else tl.appendChild(el('div',null,'No status updates received yet.'));
tl.lastChild.style.paddingBottom='6px';b.appendChild(tl);
if(d.url){var ft=el('div','ft');var cp=el('button',null,'Copy tracking link');cp.type='button';cp.onclick=function(){copy(d.url,cp)};var op=el('a',null,'Open tracking ↗');op.href=d.url;op.target='_blank';op.rel='noopener';ft.appendChild(cp);ft.appendChild(op);bx.appendChild(ft)}
ov.className='on'}
function close(){ov.className=''}
window.dfCourier=function(id){
var b=shell(null);b.appendChild(el('div','ms','Loading courier status…'));ov.className='on';
fetch('/admin/orders/'+encodeURIComponent(id)+'/courier-info',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(show).catch(function(){var b2=shell(null);b2.appendChild(el('div','ms','Could not load courier status. Please try again.'))});
return false};
document.addEventListener('click',function(e){if(e.target===ov)close()});
document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});
})();
</script>
@endonce
