{{-- Owner 16 Nov 2026: a WhatsApp call rings on every admin page as its own small card, and is answered in a dock INSIDE the admin (like the PBX dialer dock), not in
     a separate window. A light poll (answered from cache when nothing rang) shows who is calling and on which business number. While a call is active the browser
     warns before the page is left, because leaving the page ends the call. --}}
<style>
#waDock{position:fixed;right:16px;top:50%;width:420px;max-width:calc(100vw - 32px);height:min(720px,calc(100vh - 32px));z-index:80;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden;box-shadow:0 12px 36px rgba(15,31,61,.28);transform:translate(calc(100% + 40px),-50%);opacity:0;pointer-events:none;transition:transform .28s cubic-bezier(.22,.8,.3,1),opacity .2s ease;display:flex;flex-direction:column}
#waDock.on{transform:translate(0,-50%);opacity:1;pointer-events:auto}
#waDock .h{display:flex;align-items:center;gap:8px;padding:9px 12px;background:#fff;border-bottom:1px solid #E5E7EB;font:700 13px/1 Inter,Bornomala,sans-serif;color:#0B1B33}
#waDock .h i{width:9px;height:9px;border-radius:50%;background:#25D366;flex:none}
#waDock .h button{margin-left:auto;border:1px solid #D0D5DD;background:#fff;border-radius:7px;height:28px;padding:0 10px;font:600 12px Inter,Bornomala,sans-serif;color:#344054;cursor:pointer}
#waDock iframe{border:0;width:100%;flex:1;background:#F9FAFB}
@media(max-width:800px){#waDock{right:0;left:0;top:auto;bottom:0;width:100%;max-width:100%;height:92vh;border-radius:14px 14px 0 0;transform:translateY(105%)}#waDock.on{transform:translateY(0)}}
</style>
<div id="waDock" aria-label="WhatsApp call"><div class="h"><i></i>WhatsApp call<button type="button" id="waDockX">Close</button></div></div>
<div id="waRing" style="display:none;position:fixed;right:18px;top:18px;z-index:90;width:320px;max-width:calc(100vw - 24px);background:#fff;border:1px solid #E5E7EB;border-radius:14px;box-shadow:0 14px 40px rgba(15,31,61,.28);font:500 13px/1.35 Inter,Bornomala,sans-serif;color:#0B1B33;overflow:hidden">
<div style="height:4px;background:#25D366"></div>
<div style="padding:12px 14px"><div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#067647">WhatsApp call</div>
<b id="waRingN" style="display:block;font-size:15px;margin-top:2px"></b><span id="waRingF" style="color:#475467"></span><br><span id="waRingTo" style="color:#475467;font-size:12px"></span></div>
<div style="display:flex;gap:8px;padding:0 14px 12px"><button type="button" id="waRingA" style="flex:1;height:38px;border:0;border-radius:9px;background:#15803D;color:#fff;font-weight:700;cursor:pointer">Answer</button><button type="button" id="waRingR" style="flex:1;height:38px;border:1px solid #FECDCA;border-radius:9px;background:#fff;color:#B42318;font-weight:700;cursor:pointer">Reject</button></div></div>
<script>
(function(){if(window.DF_EMBED)return;var el=document.getElementById('waRing'),dock=document.getElementById('waDock'),fr=null,busy=false,ctx=null,cur=null;
function tk(){var e=document.querySelector('meta[name=csrf-token]');return e?e.content:''}
function beep(){try{ctx=ctx||new (window.AudioContext||window.webkitAudioContext)();var o=ctx.createOscillator(),g=ctx.createGain();o.frequency.value=880;g.gain.value=.06;o.connect(g);g.connect(ctx.destination);o.start();o.stop(ctx.currentTime+.35)}catch(e){}}
function kill(){dock.classList.remove('on');if(fr){fr.remove();fr=null}busy=false}
/* open the call inside the admin; go=true answers at once (the click that opened it is the staff's Answer) */
window.waDock=function(id,go){if(busy){dock.classList.add('on');return false}if(fr){fr.remove();fr=null}
  fr=document.createElement('iframe');fr.allow='microphone; autoplay';fr.title='WhatsApp call';fr.src='/admin/call-center/whatsapp/call/'+id+'?embed=1'+(go?'&go=1':'');dock.appendChild(fr);dock.classList.add('on');return false};
document.getElementById('waDockX').onclick=function(){if(busy&&!confirm('A WhatsApp call is active. Close and hang up?'))return;kill()};
window.addEventListener('message',function(e){if(e.origin!==location.origin||!e.data||!e.data.wadock)return;var a=e.data.wadock;if(a==='busy')busy=true;else if(a==='idle')busy=false;else if(a==='close'){busy=false;kill()}});
window.addEventListener('beforeunload',function(e){if(busy){e.preventDefault();e.returnValue=''}});
function go(){if(busy){el.style.display='none';return}
fetch('/admin/call-center/whatsapp/poll',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.ok?r.json():{ringing:[]}}).then(function(d){var l=d.ringing||[];
if(l.length&&!window.WC_PAGE){cur=l[0];document.getElementById('waRingN').textContent=cur.name||cur.from;document.getElementById('waRingF').textContent=cur.name?cur.from:'';document.getElementById('waRingTo').textContent='To '+(cur.to||'');el.style.display='block';beep()}else{cur=null;el.style.display='none'}}).catch(function(){})}
document.getElementById('waRingA').onclick=function(){if(!cur)return;window.waDock(cur.id,true);el.style.display='none'};
document.getElementById('waRingR').onclick=function(){if(!cur)return;fetch('/admin/call-center/whatsapp/'+cur.id+'/end',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':tk(),'Accept':'application/json'}});el.style.display='none';cur=null};
setInterval(go,4000);setTimeout(go,1500)})();
</script>
