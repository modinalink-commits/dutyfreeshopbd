{{-- Owner 16 Nov 2026: a WhatsApp call rings on every admin page. A light poll (answered from cache when nothing rang) shows a banner that opens the WhatsApp Calls page. --}}
<div id="waRing" style="display:none;position:fixed;left:50%;top:14px;transform:translateX(-50%);z-index:90;background:#0F1F3D;color:#fff;border-radius:12px;padding:10px 14px;box-shadow:0 10px 30px rgba(15,31,61,.35);font:600 13px/1.3 Inter,Bornomala,sans-serif;align-items:center;gap:12px;max-width:calc(100vw - 24px)">
<span style="width:10px;height:10px;border-radius:50%;background:#22C55E;flex:none"></span><span id="waRingT">WhatsApp call</span>
<a href="/admin/call-center/whatsapp" style="background:#15803D;color:#fff;border-radius:8px;padding:7px 14px;text-decoration:none;font-weight:700;white-space:nowrap">Answer</a></div>
<script>
(function(){if(window.DF_EMBED||window.WC_PAGE)return;var el=document.getElementById('waRing'),ctx=null,last=0;
function beep(){try{ctx=ctx||new (window.AudioContext||window.webkitAudioContext)();var o=ctx.createOscillator(),g=ctx.createGain();o.frequency.value=880;g.gain.value=.06;o.connect(g);g.connect(ctx.destination);o.start();o.stop(ctx.currentTime+.35)}catch(e){}}
function go(){if(document.hidden&&Date.now()-last<15000)return;fetch('/admin/call-center/whatsapp/poll',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.ok?r.json():{ringing:[]}}).then(function(d){var l=d.ringing||[];
if(l.length){last=Date.now();document.getElementById('waRingT').textContent='WhatsApp call: '+(l[0].name||l[0].from);el.style.display='flex';beep()}else el.style.display='none'}).catch(function(){})}
setInterval(go,5000);setTimeout(go,1500)})();
</script>
