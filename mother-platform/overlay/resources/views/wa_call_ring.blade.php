{{-- Owner 16 Nov 2026: a WhatsApp call rings on every admin page as its own small card, and is answered in a dock INSIDE the admin (like the PBX dialer dock), not in
     a separate window. A light poll (answered from cache when nothing rang) shows who is calling and on which business number. While a call is active the browser
     warns before the page is left, because leaving the page ends the call. --}}
<style>
#waDock{position:fixed;right:16px;bottom:16px;width:420px;max-width:calc(100vw - 32px);height:min(640px,calc(100vh - 32px));z-index:80;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden;box-shadow:0 12px 36px rgba(15,31,61,.28);transform:translate(calc(100% + 40px),0);opacity:0;pointer-events:none;transition:transform .28s cubic-bezier(.22,.8,.3,1),opacity .2s ease;display:flex;flex-direction:column}
#waDock.on{transform:translate(0,0);opacity:1;pointer-events:auto}
#waDock .h{display:flex;align-items:center;gap:8px;padding:9px 12px;background:#fff;border-bottom:1px solid #E5E7EB;font:700 13px/1 Inter,Bornomala,sans-serif;color:#0B1B33}
#waDock .h i{width:9px;height:9px;border-radius:50%;background:#25D366;flex:none}
#waDock .h button{margin-left:6px;border:1px solid #D0D5DD;background:#fff;border-radius:7px;height:28px;padding:0 10px;font:600 12px Inter,Bornomala,sans-serif;color:#344054;cursor:pointer}
#waDock .h button:first-of-type{margin-left:auto}
#waDock iframe{border:0;width:100%;flex:1;background:#F9FAFB}
/* 20 Nov 2026: minimise during a call so staff can work on other pages parts; the call keeps running (the frame stays loaded) */
#waDock.on.min{top:auto;bottom:16px;right:16px;left:auto;width:290px;height:46px;transform:none;border-color:#25D366}
#waDock.min iframe{flex:0 0 0;height:0;visibility:hidden}
#waDock.min .h{border-bottom:0;cursor:pointer}
#waDock.min .h i{animation:waMn 1.2s infinite}
@keyframes waMn{50%{opacity:.25}}
@media(max-width:800px){#waDock{right:0;left:0;top:auto;bottom:0;width:100%;max-width:100%;height:92vh;border-radius:14px 14px 0 0;transform:translateY(105%)}#waDock.on{transform:translateY(0)}#waDock.on.min{left:auto;right:8px;bottom:8px;width:270px;max-width:calc(100vw - 16px);height:46px;border-radius:14px;transform:none}}
</style>
<div id="waDock" aria-label="WhatsApp call"><div class="h" id="waDockH"><i></i><span id="waDockT">WhatsApp call</span><button type="button" id="waDockM" title="Minimise: the call keeps running">Minimise</button><button type="button" id="waDockX">Close</button></div></div>
<style>
#waBk{display:none!important}
#waRing{display:none;position:fixed;right:16px;bottom:16px;z-index:90;width:min(340px,calc(100vw - 24px));background:#fff;border-radius:20px;box-shadow:0 24px 60px rgba(0,0,0,.4);font:500 14px/1.4 Inter,Bornomala,sans-serif;color:#0B1B33;overflow:hidden;text-align:center}
#waRing .bar{height:6px;background:#25D366}
#waRing .av{width:84px;height:84px;border-radius:50%;margin:20px auto 8px;background:#E8F8EF;color:#15803D;display:grid;place-items:center;font-size:34px;font-weight:700;position:relative}
#waRing .av:before,#waRing .av:after{content:"";position:absolute;inset:-8px;border-radius:50%;border:2px solid #25D366;opacity:.5;animation:waP 1.6s infinite}
#waRing .av:after{animation-delay:.8s}
@keyframes waP{0%{transform:scale(.85);opacity:.6}100%{transform:scale(1.35);opacity:0}}
@media(prefers-reduced-motion:reduce){#waRing .av:before,#waRing .av:after{animation:none}}
#waRing .lb{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#15803D}
#waRing .nm{font-size:22px;font-weight:700;margin-top:2px}#waRing .nu{color:#667085}#waRing .cp{margin-left:6px;height:28px;padding:0 10px;border:1px solid #D0D5DD;border-radius:8px;background:#fff;color:#0B1B33;font:600 12px 'Hind Siliguri',Inter,system-ui,sans-serif;cursor:pointer;vertical-align:middle}#waRing .cp.ok{border-color:#067647;color:#067647;background:#ECFDF3}
#waRing .ch{display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin:10px 12px 0}
#waRing .ch span{background:#F7F8FB;border:1px solid #E4E7EE;border-radius:999px;padding:2px 10px;font-size:12px}
#waRing .to{font-size:12px;color:#667085;margin-top:10px}
#waRing .cn{height:4px;background:#E4E7EE;margin:14px 20px 0;border-radius:4px;overflow:hidden}#waRing .cn i{display:block;height:100%;width:100%;background:#25D366;transition:width 1s linear}
#waRing .ac{display:grid;grid-template-columns:1fr 1.4fr;gap:10px;padding:14px 20px 20px}
#waRing .b{height:48px;border-radius:12px;border:0;font:700 15px Inter,Bornomala,sans-serif;cursor:pointer}
#waRing .b.r{background:#fff;color:#D92D20;border:1px solid #F2C6C2}#waRing .b.a{background:#15803D;color:#fff}
#waMiss{position:fixed;right:16px;bottom:16px;z-index:88;width:min(440px,calc(100vw - 24px));display:grid;gap:8px}
#waMiss .m{background:#fff;border-radius:14px;box-shadow:0 14px 36px rgba(0,0,0,.3);border-left:6px solid #D92D20;padding:12px 14px;display:grid;gap:8px;font:500 13px/1.35 Inter,Bornomala,sans-serif;color:#0B1B33}
#waMiss .tg{font-size:11px;font-weight:700;color:#D92D20;letter-spacing:.06em;text-transform:uppercase}
#waMiss .rw{display:flex;gap:8px;flex-wrap:wrap}
#waMiss button{height:36px;border-radius:9px;padding:0 14px;border:1px solid #E4E7EE;background:#fff;color:#0B1B33;font:600 13px Inter,Bornomala,sans-serif;cursor:pointer}
#waMiss button.p{background:#F26B1D;border-color:#F26B1D;color:#fff}
#waMiss .x{margin-left:auto;border:0;background:none;color:#667085;height:auto;padding:0 4px;font-size:18px}
</style>
<div id="waBk"></div>
<div id="waRing" role="alertdialog" aria-label="Incoming WhatsApp call"><div class="bar"></div><div class="av" id="waAv">?</div><div class="lb" id="waLb">Incoming WhatsApp call</div>
<div class="nm" id="waRingN"></div><div class="nu"><span id="waRingF"></span> <button type="button" id="waRingC" class="cp" hidden>নম্বর কপি</button></div><div class="ch" id="waCh"></div><div class="to" id="waRingTo"></div><div class="cn"><i id="waCnI"></i></div>
<div class="ac"><button type="button" class="b r" id="waRingR">Reject</button><button type="button" class="b a" id="waRingA">Answer</button></div></div>
<div id="waMiss"></div>
<script>
(function(){if(window.DF_EMBED)return;var el=document.getElementById('waRing'),bk=document.getElementById('waBk'),ms=document.getElementById('waMiss'),dock=document.getElementById('waDock'),fr=null,busy=false,ctx=null,cur=null,seen=null,tickN=0;
function tk(){var e=document.querySelector('meta[name=csrf-token]');return e?e.content:''}
function post(u){return fetch(u,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':tk(),'Accept':'application/json'}})}
function beep(){try{ctx=ctx||new (window.AudioContext||window.webkitAudioContext)();var o=ctx.createOscillator(),g=ctx.createGain();o.frequency.value=880;g.gain.value=.06;o.connect(g);g.connect(ctx.destination);o.start();o.stop(ctx.currentTime+.35)}catch(e){}}
function setMin(m){dock.classList.toggle('min',!!m);document.getElementById('waDockM').textContent=m?'Open':'Minimise';document.getElementById('waDockT').textContent=m?'WhatsApp call running':'WhatsApp call'}
function kill(){setMin(false);dock.classList.remove('on');if(fr){fr.remove();fr=null}busy=false}
function hideRing(){el.style.display='none';bk.style.display='none';cur=null;seen=null}
/* open the call inside the admin; go=true answers at once (the click that opened it is the staff's Answer) */
window.waDock=function(id,go){setMin(false);if(busy){dock.classList.add('on');return false}if(fr){fr.remove();fr=null}
  fr=document.createElement('iframe');fr.allow='microphone; autoplay';fr.title='WhatsApp call';fr.src='/admin/call-center/whatsapp/call/'+id+'?embed=1'+(go?'&go=1':'');dock.appendChild(fr);dock.classList.add('on');return false};
document.getElementById('waDockM').onclick=function(e){e.stopPropagation();setMin(!dock.classList.contains('min'))};
document.getElementById('waDockH').onclick=function(e){if(dock.classList.contains('min')&&!e.target.closest('button'))setMin(false)};
document.getElementById('waDockX').onclick=function(e){e.stopPropagation();if(busy&&!confirm('A WhatsApp call is active. Close and hang up?'))return;kill()};
window.addEventListener('message',function(e){if(e.origin!==location.origin||!e.data||!e.data.wadock)return;var a=e.data.wadock;if(a==='busy')busy=true;else if(a==='idle')busy=false;else if(a==='close'){busy=false;kill()}});
window.addEventListener('beforeunload',function(e){if(busy){e.preventDefault();e.returnValue=''}});
/* Owner 27 Nov 2026: while a call is active, a link opens in a new tab so staff can check the order and the customer's profile without ending the call. */
document.addEventListener('click',function(e){if(!busy)return;var a=e.target&&e.target.closest?e.target.closest('a[href]'):null;if(!a||a.closest('#waDock')||e.defaultPrevented||e.ctrlKey||e.metaKey||e.shiftKey||a.target==='_blank'||a.hasAttribute('download'))return;var h=a.getAttribute('href')||'';if(!h||h.charAt(0)==='#'||/^(javascript|mailto|tel|sms):/i.test(h))return;var u;try{u=new URL(a.href,location.href)}catch(x){return}if(u.origin!==location.origin)return;e.preventDefault();window.open(u.href,'_blank','noopener')},true);

function chips(d){var c=document.getElementById('waCh');c.innerHTML='';var a=[];if(d&&d.found){if(d.category)a.push(d.category);if(d.membership)a.push(d.membership);if(d.orders)a.push(d.orders+' orders');if(d.last&&d.last.invoice)a.push('Last order '+d.last.invoice)}else a.push('New caller');a.forEach(function(t){var s=document.createElement('span');s.textContent=t;c.appendChild(s)})}
function show(c){var first=!cur||cur.id!==c.id;cur=c;var nm=c.name||c.from;document.getElementById('waRingN').textContent=nm;document.getElementById('waRingF').textContent=c.name?c.from:'';var cb=document.getElementById('waRingC');cb.hidden=!/\d{7,}/.test(String(c.from||''));cb._n=c.from;document.getElementById('waAv').textContent=(nm.replace(/[^A-Za-z\u0980-\u09FF]/g,'').charAt(0)||'?').toUpperCase();document.getElementById('waRingTo').textContent='To business number '+(c.to||'');
 document.getElementById('waLb').textContent=c.n>1?'Incoming WhatsApp call · 1 of '+c.n:'Incoming WhatsApp call';var w=Math.max(0,Math.min(100,100-(c.age||0)/(c.win||45)*100));document.getElementById('waCnI').style.width=w+'%';
 el.style.display='block';bk.style.display='block';beep();
 if(first){chips(null);fetch('/admin/call-center/lookup?phone='+encodeURIComponent(c.from),{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.ok?r.json():null}).then(function(d){if(cur&&cur.id===c.id)chips(d)}).catch(function(){})}}
function missed(l){var h='';l.forEach(function(m){h+='<div class="m" data-id="'+m.id+'"><div style="display:flex;gap:8px;align-items:flex-start"><div><span class="tg">Missed WhatsApp call</span><br><b></b> <span style="color:#667085" class="mt"></span><div class="mm" style="font-size:12px;margin-top:2px"></div></div><button class="x" data-a="hide" title="Hide for me">×</button></div><div class="rw"><button class="p" data-a="cb">📞 Call back</button><button data-a="chat">Open chat</button><button data-a="done">Done</button></div></div>'});ms.innerHTML=h;
 [].forEach.call(ms.querySelectorAll('.m'),function(n,i){var m=l[i];n.querySelector('b').textContent=m.name||m.from;n.querySelector('.mt').textContent=(m.name?m.from+' · ':'')+m.ago;var mm=n.querySelector('.mm');if(m.msg==='sent'){mm.style.color='#067647';mm.textContent='Automatic message sent to the customer'}else if(m.msg==='failed'){mm.style.color='#B42318';mm.style.fontWeight='700';mm.textContent='Automatic message FAILED: '+(m.msg_err||'not sent')+'. Contact the customer yourself.'}n._m=m})}
ms.addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;var n=b.closest('.m'),m=n&&n._m;if(!m)return;var a=b.getAttribute('data-a');
 if(a==='cb'){post('/admin/call-center/whatsapp/'+m.id+'/handled');if(window.dfCall)window.dfCall(m.from.replace(/\D/g,''),{name:m.name});n.remove()}
 else if(a==='wcb'){b.disabled=true;post('/admin/call-center/whatsapp/'+m.id+'/callback').then(function(r){return r.json()}).then(function(d){if(d&&d.ok&&d.id){post('/admin/call-center/whatsapp/'+m.id+'/handled');window.waDock(d.id,true);n.remove()}else{b.disabled=false;n.querySelector('.mt').textContent=(d&&d.message)||'Could not start the WhatsApp call back'}}).catch(function(){b.disabled=false})}
 else if(a==='done'){post('/admin/call-center/whatsapp/'+m.id+'/handled');n.remove()}
 else if(a==='hide'){post('/admin/call-center/whatsapp/'+m.id+'/hide');n.remove()}
 else if(a==='chat'){window.open('/admin/chat','_blank')}});
function go(){if(busy){hideRing();return}tickN++;
fetch('/admin/call-center/whatsapp/poll',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.ok?r.json():{ringing:[]}}).then(function(d){var l=d.ringing||[];
if(l.length&&!window.WC_PAGE){l[0].n=l.length;show(l[0])}else hideRing()}).catch(function(){});
if(!window.WC_PAGE&&tickN%2===0)fetch('/admin/call-center/whatsapp/missed',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.ok?r.json():{missed:[]}}).then(function(d){missed(d.missed||[])}).catch(function(){})}
function dfLocalNum(v){var d=String(v||'').replace(/\D/g,'');if(d.length===13&&d.indexOf('8801')===0)return '0'+d.slice(3);if(d.length===12&&d.indexOf('881')===0)return '0'+d.slice(2);if(d.length===10&&d.charAt(0)==='1')return '0'+d;if(d.length===11&&d.indexOf('01')===0)return d;return d?(String(v).trim().charAt(0)==='+'||d.length>11?'+'+d:d):''}
function dfCopyNum(btn,v){var n=dfLocalNum(v);if(!n)return;var ok=function(){var o=btn.getAttribute('data-l')||btn.textContent;btn.setAttribute('data-l',o);btn.textContent='নম্বর কপি হয়েছে';btn.classList.add('ok');clearTimeout(btn._t);btn._t=setTimeout(function(){btn.textContent=o;btn.classList.remove('ok')},2200)},fb=function(){try{var t=document.createElement('textarea');t.value=n;t.setAttribute('readonly','');t.style.cssText='position:fixed;left:-9999px;top:0;opacity:0';document.body.appendChild(t);t.select();var r=document.execCommand('copy');document.body.removeChild(t);if(r)ok();else btn.textContent='কপি হয়নি: '+n}catch(e){btn.textContent='কপি হয়নি: '+n}};
 if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(n).then(ok,fb);else fb()}
/* Copy number: never answers, rejects or closes the ring */
document.getElementById('waRingC').addEventListener('click',function(e){e.preventDefault();e.stopPropagation();dfCopyNum(this,this._n)});
document.getElementById('waRingA').onclick=function(){if(!cur)return;window.waDock(cur.id,true);hideRing()};
/* Reject hides the ring for THIS person only; everyone else keeps ringing until someone answers or the ring window ends */
document.getElementById('waRingR').onclick=function(){if(!cur)return;post('/admin/call-center/whatsapp/'+cur.id+'/end');hideRing()};
setInterval(go,8000);setTimeout(go,1500)})();
</script>
