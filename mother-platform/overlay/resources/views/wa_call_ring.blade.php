{{-- Owner 16 Nov 2026: a WhatsApp call rings on every admin page as its own small card (separate from the PBX dialer). A light poll (answered from cache when
     nothing rang) shows who is calling and on which business number; Answer opens the call window, which keeps the call alive while staff move between pages. --}}
<div id="waRing" style="display:none;position:fixed;right:18px;top:18px;z-index:90;width:320px;max-width:calc(100vw - 24px);background:#fff;border:1px solid #E5E7EB;border-radius:14px;box-shadow:0 14px 40px rgba(15,31,61,.28);font:500 13px/1.35 Inter,Bornomala,sans-serif;color:#0B1B33;overflow:hidden">
<div style="height:4px;background:#25D366"></div>
<div style="padding:12px 14px"><div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#067647">WhatsApp call</div>
<b id="waRingN" style="display:block;font-size:15px;margin-top:2px"></b><span id="waRingF" style="color:#475467"></span><br><span id="waRingTo" style="color:#475467;font-size:12px"></span></div>
<div style="display:flex;gap:8px;padding:0 14px 12px"><button type="button" id="waRingA" style="flex:1;height:38px;border:0;border-radius:9px;background:#15803D;color:#fff;font-weight:700;cursor:pointer">Answer</button><button type="button" id="waRingR" style="flex:1;height:38px;border:1px solid #FECDCA;border-radius:9px;background:#fff;color:#B42318;font-weight:700;cursor:pointer">Reject</button></div></div>
<script>
(function(){if(window.DF_EMBED||window.WC_PAGE)return;var el=document.getElementById('waRing'),ctx=null,last=0,cur=null;
function tk(){var e=document.querySelector('meta[name=csrf-token]');return e?e.content:''}
function beep(){try{ctx=ctx||new (window.AudioContext||window.webkitAudioContext)();var o=ctx.createOscillator(),g=ctx.createGain();o.frequency.value=880;g.gain.value=.06;o.connect(g);g.connect(ctx.destination);o.start();o.stop(ctx.currentTime+.35)}catch(e){}}
function go(){if(document.hidden&&Date.now()-last>15000&&!cur){/* background tab: still check, calls are rare and the check is cached */}
fetch('/admin/call-center/whatsapp/poll',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.ok?r.json():{ringing:[]}}).then(function(d){var l=d.ringing||[];
if(l.length){last=Date.now();cur=l[0];document.getElementById('waRingN').textContent=cur.name||cur.from;document.getElementById('waRingF').textContent=cur.name?cur.from:'';document.getElementById('waRingTo').textContent='To '+(cur.to||'');el.style.display='block';beep()}else{cur=null;el.style.display='none'}}).catch(function(){})}
document.getElementById('waRingA').onclick=function(){if(!cur)return;var w=window.open('/admin/call-center/whatsapp/call/'+cur.id+'?embed=1&go=1','wacall'+cur.id,'width=500,height=760');if(!w)location.href='/admin/call-center/whatsapp';el.style.display='none'};
document.getElementById('waRingR').onclick=function(){if(!cur)return;fetch('/admin/call-center/whatsapp/'+cur.id+'/end',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':tk(),'Accept':'application/json'}});el.style.display='none';cur=null};
setInterval(go,4000);setTimeout(go,1500)})();
</script>
