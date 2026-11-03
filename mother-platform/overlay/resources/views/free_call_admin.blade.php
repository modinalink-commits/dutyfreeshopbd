@if(\App\Support\FreeCall::ready())
<style>
.fca-p{position:fixed;left:256px;bottom:12px;z-index:9990;display:none;align-items:center;gap:6px;background:#0B1220;color:#fff;border-radius:22px;padding:4px 6px 4px 12px;font:600 12px system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;box-shadow:0 8px 20px rgba(11,18,32,.3)}
.fca-p.on{display:flex}.fca-p i{width:9px;height:9px;border-radius:50%;background:#64748B;display:inline-block}.fca-p.av i{background:#22C55E}.fca-p.bz i{background:#F59E0B}
.fca-p select{background:#1E293B;color:#fff;border:0;border-radius:14px;padding:5px 8px;font-weight:600;font-size:12px;font-family:inherit;cursor:pointer}.fca-p em{background:#FF5A00;border-radius:10px;padding:0 7px;font-style:normal;font-size:11px;display:none}.fca-p em.on{display:inline}
@media(max-width:900px){.fca-p{left:12px;bottom:70px}}
#fcabar{position:fixed;right:0;top:50%;transform:translateY(-50%);z-index:9990;display:flex;flex-direction:row;align-items:stretch;gap:4px;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif}
#fcabar>*{position:relative!important;transform:none!important;height:auto!important;margin:0!important;left:auto!important;top:auto!important;bottom:auto!important;right:auto!important;writing-mode:vertical-rl!important;align-items:center;gap:7px;padding:14px 7px!important;background:#0B1220!important;color:#fff;border:0;border-radius:10px 0 0 10px!important;font-size:12.5px;font-weight:700;line-height:1;text-decoration:none;cursor:pointer;box-shadow:-3px 3px 12px rgba(11,18,32,.25)!important;letter-spacing:.3px}
#fcabar>.fcam,#fcabar>#dfEdge{display:flex}#fcabar>*{justify-content:center}#fcabar>.fca-p{order:1}#fcabar>.fcam{order:2}#fcabar>#dfEdge{order:3}#fcabar>.fca-p.on{display:flex!important}#fcabar>*:hover{background:#FF5A00!important}
#fcabar i,#fcabar b,#fcabar em{writing-mode:horizontal-tb;font-style:normal}
.fcam b{background:#FF5A00;border-radius:10px;padding:0 6px;font-size:11px;line-height:18px}.fcam:hover b{background:#0B1220}
#fcabar #dfEdge::after{order:3}
#fcap select{position:absolute!important;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;writing-mode:horizontal-tb}
@media(max-width:900px){#fcabar .lbl,#fcabar #dfEdge span,#fcabar .fcam span,#fcast{display:none!important}#fcabar>*{padding:12px 6px!important}}
.fca-c{position:fixed;top:16px;right:16px;z-index:100003;width:320px;max-width:calc(100vw - 24px);background:#0B1220;color:#fff;border-radius:22px;box-shadow:0 24px 60px rgba(11,18,32,.5);padding:16px;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;display:none}.fca-c.on{display:block}
.fca-c .t{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#FDBA8C;font-weight:700;display:flex;justify-content:space-between}
.fca-c .n{font-size:19px;font-weight:800;margin:6px 0 1px;word-break:break-word}.fca-c .m{font-size:13px;color:#CBD5E1}
.fca-c .b{display:inline-block;font-size:10.5px;font-weight:700;border-radius:10px;padding:2px 8px;margin:6px 4px 0 0;background:#1E293B;color:#E2E8F0}.fca-c .b.o{background:#FF5A00;color:#fff}
.fca-c .x{background:#141C2E;border-radius:12px;padding:8px 10px;margin-top:8px;font-size:12px;line-height:1.5;color:#CBD5E1}.fca-c .x b{color:#fff}.fca-c .x a{color:#FDBA8C}
.fca-c .tm{font-size:30px;font-weight:800;text-align:center;margin:8px 0;letter-spacing:.03em}
.fca-r{display:flex;gap:10px;margin-top:12px}.fca-r button,.fca-k button{flex:1;border:0;border-radius:14px;padding:11px 6px;font-weight:700;font-size:13px;font-family:inherit;cursor:pointer;color:#fff}
.fca-ac{background:#16A34A}.fca-dc{background:#E11D48}.fca-gy{background:#334155}.fca-gy.on{background:#FF5A00}.fca-k{display:flex;gap:8px;margin-top:8px}
.fca-c textarea,.fca-c select.o{width:100%;background:#1E293B;color:#fff;border:1px solid #334155;border-radius:11px;padding:8px;font-size:13px;font-family:inherit;margin-top:8px}
</style>
<div class="fca-p" id="fcap"><i></i><span class="lbl">Free Call</span><span id="fcast">Offline</span><select id="fcas"><option value="available">Available</option><option value="busy">Busy</option><option value="offline">Offline</option></select><em id="fcaq"></em></div>
<div class="fca-c" id="fcac"></div>
<audio id="fcaa" autoplay playsinline></audio>
@verbatim
<script>(function(){
var T=(document.querySelector('meta[name="csrf-token"]')||{}).content||'',P=document.getElementById('fcap'),S=document.getElementById('fcas'),Q=document.getElementById('fcaq'),CD=document.getElementById('fcac'),AU=document.getElementById('fcaa');
var g={pc:null,id:0,stream:null,after:0,local:false,muted:false,t0:0,view:'',ringId:0,iceQ:[],rd:false,tick:null,busy:false,dead:false,skip:[],accepting:false,tone:null,actx:null,lastStatus:null};
try{g.skip=JSON.parse(sessionStorage.getItem('fcskip')||'[]')}catch(e){}
// Free Call popup sits beside (to the left of) the chat-message cards instead of on top of them
setInterval(function(){var m=document.getElementById('mcp'),n=m&&m.children.length>0&&innerWidth>=1000;CD.style.right=(n?376:16)+'px'},400);
var BAR=document.createElement('div');BAR.id='fcabar';document.body.appendChild(BAR);
(function(){var m=[].slice.call(document.querySelectorAll('aside a')).filter(function(a){return /^\s*Messages/.test(a.textContent)})[0];if(m){var l=document.createElement('a');l.className='fcam';l.href=m.getAttribute('href');l.innerHTML='💬 <span>Messages</span><b id="fcamn" style="display:none"></b>';BAR.appendChild(l);var upd=function(){var e=m.querySelector('em'),n=document.getElementById('fcamn');if(!n)return;var v=e?e.textContent.trim():'';n.textContent=v;n.style.display=v&&v!=='0'?'inline':'none'};upd();setInterval(upd,4000)}
var d=document.getElementById('dfEdge');if(d)BAR.appendChild(d);BAR.appendChild(P)})();

function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function fmt(n){n=Math.max(0,n|0);return ('0'+((n/60)|0)).slice(-2)+':'+('0'+(n%60)).slice(-2)}
function post(u,b){return fetch(u,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':T},body:JSON.stringify(b||{})}).then(function(r){return r.json().catch(function(){return {}}).then(function(j){j._st=r.status;return j})})}
function ac(){if(!g.actx){try{g.actx=new (window.AudioContext||window.webkitAudioContext)()}catch(e){}}if(g.actx&&g.actx.state==='suspended')g.actx.resume();return g.actx}
['click','keydown','touchstart'].forEach(function(e){document.addEventListener(e,function(){ac()},{once:true,passive:true})});
function ring(on){if(!on){if(g.tone){clearInterval(g.tone);g.tone=null}document.title=document.title.replace(/^📞 /,'');return}if(g.tone)return;var b=function(){var c=g.actx;if(!c||c.state!=='running')return;[0,.28].forEach(function(d){var o=c.createOscillator(),n=c.createGain();o.frequency.value=880;n.gain.value=.25;o.connect(n);n.connect(c.destination);o.start(c.currentTime+d);o.stop(c.currentTime+d+.18)})};b();g.tone=setInterval(function(){b();document.title=/^📞 /.test(document.title)?document.title.replace(/^📞 /,''):'📞 '+document.title},1000)}
function ctxHtml(c){var h='<div class="n">'+esc(c.name||'Guest customer')+'</div><div class="m">'+esc(c.mobile)+'</div><span class="b '+(c.guest?'':'o')+'">'+(c.guest?'Guest':'Customer')+'</span>'+(c.level?'<span class="b">'+esc(c.level)+'</span>':'');
 var x='';if(c.order)x+='<div>Order: <b>'+esc(c.order.invoice)+'</b> · '+esc(c.order.status)+' · ৳'+Math.round(c.order.total)+'</div>';else if(c.latest)x+='<div>Latest order: <b>'+esc(c.latest.invoice)+'</b> · '+esc(c.latest.status)+' · ৳'+Math.round(c.latest.total)+'</div>';
 if(c.counts&&c.counts.total)x+='<div>Orders: '+c.counts.total+' · Delivered '+c.counts.delivered+' · Returned '+c.counts.returned+' · Cancelled '+c.counts.cancelled+'</div>';
 if(c.product)x+='<div>Product: <b>'+esc(c.product)+'</b>'+(c.code?' ('+esc(c.code)+')':'')+'</div>';
 if(c.cart)x+='<div>Cart: '+esc(c.cart)+'</div>';
 if(c.ip)x+='<div>IP: '+esc(c.ip)+'</div>';if(c.page){var p=String(c.page);x+='<div>Page: <a href="'+esc(p)+'" target="_blank" rel="noopener">'+esc(c.page_title||p.replace(/^https?:\/\/[^\/]+/,'')||'/')+'</a></div>'}
 x+='<div><a href="/admin/customers?q='+encodeURIComponent(c.mobile)+'" target="_blank">Find in customers</a></div>';
 return h+(x?'<div class="x">'+x+'</div>':'')}
function showRing(r){if(g.view==='ring'&&g.ringId===r.id){var e=document.getElementById('fcal');if(e)e.textContent=r.ring_left+'s';return}
 g.view='ring';g.ringId=r.id;CD.className='fca-c on';CD.innerHTML='<div class="t"><span>📞 Incoming Free Call</span><span id="fcal">'+r.ring_left+'s</span></div>'+ctxHtml(r)+'<div class="x" id="fcasnd" style="display:none;color:#FDBA8C">🔔 Ringtone is blocked by the browser — click anywhere on this page once to enable sound.</div>'+'<div class="fca-r"><button class="fca-ac" data-f="acc" data-id="'+r.id+'">✓ Accept</button><button class="fca-dc" data-f="dec" data-id="'+r.id+'">✕ Decline</button></div>';ring(true);setTimeout(function(){var e=document.getElementById('fcasnd');if(e&&(!g.actx||g.actx.state!=='running'))e.style.display='block'},600)}
function showActive(a){if(g.view==='act'&&g.id===a.id){var e=document.getElementById('fcat');if(e)e.textContent=fmt(Date.now()/1000-(a.answered_ts||0));return}
 g.view='act';g.id=a.id;ring(false);CD.className='fca-c on';CD.innerHTML='<div class="t"><span>● On call</span><span>Free Call</span></div>'+ctxHtml(a)+'<div class="tm" id="fcat">'+fmt(Date.now()/1000-(a.answered_ts||0))+'</div><div class="fca-k"><button class="fca-gy" data-f="mute" id="fcam">🎤 Mute</button><button class="fca-gy on" data-f="spk" id="fcas2">🔊 Speaker</button><button class="fca-dc" data-f="hang">Hang Up</button></div><textarea id="fcan" rows="2" placeholder="Add note…" maxlength="2000"></textarea>'}
function passive(a){if(g.view==='pas'&&g.id===a.id)return;g.view='pas';g.id=a.id;ring(false);CD.className='fca-c on';CD.innerHTML='<div class="t"><span>● On call</span><span>Another window</span></div><div class="n">'+esc(a.name||'Customer')+'</div><div class="m">This call is being handled in another browser window or tab. If that window was closed, the call ends automatically in a few seconds.</div>'}
function hide(){CD.className='fca-c';CD.innerHTML='';g.view='';ring(false)}
function cleanup(){if(g.pc){try{g.pc.close()}catch(e){}g.pc=null}if(g.stream){g.stream.getTracks().forEach(function(t){t.stop()});g.stream=null}try{AU.srcObject=null}catch(e){}g.local=false;g.iceQ=[];g.rd=false;g.after=0;g.muted=false}
function after(l){if(g.skip.indexOf(l.id)>-1)return;if(g.view==='post'&&g.id===l.id)return;g.view='post';g.id=l.id;CD.className='fca-c on';
 CD.innerHTML='<div class="t"><span>Call ended</span><span>'+fmt(l.duration)+'</span></div><div class="n">'+esc(l.name)+'</div><select class="o" id="fcao"><option value="">Outcome…</option><option value="resolved">Resolved</option><option value="follow_up">Follow-up Required</option><option value="order">Order Related</option><option value="lead">Sales Lead</option><option value="technical">Not Reached / Technical Issue</option></select><textarea id="fcan" rows="3" placeholder="Note (optional)" maxlength="2000"></textarea><div class="fca-r"><button class="fca-ac" data-f="save" data-id="'+l.id+'">Save</button><button class="fca-gy" data-f="skip" data-id="'+l.id+'">Skip</button></div>'+(l.mobile?'<a href="/admin/call-center/phone?auto=1&n='+encodeURIComponent(l.mobile)+'" target="_blank" style="display:block;text-align:center;margin-top:8px;color:#FDBA8C;font-size:12.5px;font-weight:700">📞 Call back '+esc(l.mobile)+'</a>':'')}
function skip(id){g.skip.push(id);try{sessionStorage.setItem('fcskip',JSON.stringify(g.skip.slice(-30)))}catch(e){}}
function startPc(a){if(g.pc)return;g.pc=new RTCPeerConnection({iceServers:a.ice||[]});g.stream.getTracks().forEach(function(t){g.pc.addTrack(t,g.stream)});
 g.pc.onicecandidate=function(e){if(e.candidate)post('/admin/free-calls/'+a.id+'/signal',{type:'ice',payload:JSON.stringify(e.candidate)})};
 g.pc.ontrack=function(e){AU.srcObject=e.streams[0];var p=AU.play();if(p&&p.catch)p.catch(function(){})};
 g.pc.onconnectionstatechange=function(){var s=g.pc&&g.pc.connectionState;if(s==='connected'){post('/admin/free-calls/'+a.id+'/connected')}else if(s==='failed'){post('/admin/free-calls/'+a.id+'/hangup')}}}
function sigs(a,list){list.forEach(function(m){g.after=Math.max(g.after,m.id);if(!g.pc)return;
 if(m.type==='offer'){var o=JSON.parse(m.payload);g.pc.setRemoteDescription(o).then(function(){g.rd=true;g.iceQ.splice(0).forEach(function(c){g.pc.addIceCandidate(c).catch(function(){})});return g.pc.createAnswer()}).then(function(an){return g.pc.setLocalDescription(an)}).then(function(){post('/admin/free-calls/'+a.id+'/signal',{type:'answer',payload:JSON.stringify(g.pc.localDescription)})}).catch(function(){})}
 else if(m.type==='ice'){var c=JSON.parse(m.payload);if(g.rd)g.pc.addIceCandidate(c).catch(function(){});else g.iceQ.push(c)}})}
function poll(){if(g.busy||g.dead)return;g.busy=true;fetch('/admin/free-calls/poll?after='+g.after+(g.local&&g.id?'&own='+g.id:''),{headers:{'Accept':'application/json'},credentials:'same-origin'}).then(function(r){if(r.status===403||r.status===404||r.status===401){g.dead=true;P.classList.remove('on');return null}return r.json()}).then(function(j){g.busy=false;if(!j||!j.ok)return;
  P.classList.add('on');P.className='fca-p on '+(j.eff==='available'?'av':j.eff==='busy'?'bz':'');if(document.activeElement!==S)S.value=j.status;var fs=document.getElementById('fcast');if(fs)fs.textContent=({available:'Available',busy:'Busy',offline:'Offline'})[j.eff]||j.eff;
  Q.textContent=j.waiting+' waiting';Q.classList.toggle('on',j.waiting>0);P.title=j.on?'':'Free Call is OFF for customers';
  var nv=document.querySelectorAll('a[href="/admin/free-calls"]');
  if(j.active){var a=j.active;if(!g.local&&!g.accepting){passive(a);return}
   g.id=a.id;showActive(a);if(g.stream&&!g.pc)startPc(a);sigs(a,j.signals||[]);return}
  if(g.local&&!g.accepting){cleanup();hide()}
  if(j.ring&&!g.accepting){showRing(j.ring);return}
  if(g.view==='ring'||g.view==='act'||g.view==='pas'){hide()}
  if(j.last&&!g.accepting){after(j.last)}else if(g.view==='post'&&!j.last){hide()}
 }).catch(function(){g.busy=false})}
document.addEventListener('click',function(e){var b=e.target.closest('[data-f]');if(!b||!CD.contains(b))return;var f=b.getAttribute('data-f'),id=b.getAttribute('data-id');
 if(f==='acc'){g.accepting=true;g.id=+id;b.disabled=true;ring(false);navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true,autoGainControl:true}}).then(function(s){g.stream=s;g.local=true;return post('/admin/free-calls/'+id+'/accept')}).then(function(j){g.accepting=false;if(!j.ok){cleanup();hide();alert(j.error||'Could not accept this call');}else{g.after=0;g.busy=false;poll()}}).catch(function(){g.accepting=false;cleanup();hide();alert('Microphone permission is needed to answer Free Calls.')})}
 else if(f==='dec'){post('/admin/free-calls/'+id+'/decline');hide()}
 else if(f==='mute'){g.muted=!g.muted;if(g.stream)g.stream.getAudioTracks().forEach(function(t){t.enabled=!g.muted});b.classList.toggle('on',g.muted);b.textContent=g.muted?'🎤 Unmute':'🎤 Mute';post('/admin/free-calls/'+g.id+'/event',{e:g.muted?'muted':'unmuted'})}
 else if(f==='hang'){var n=(document.getElementById('fcan')||{}).value||'';var cid=g.id;post('/admin/free-calls/'+cid+'/hangup').then(function(){if(n.trim())return post('/admin/free-calls/'+cid+'/note',{note:n.trim()})}).then(function(){cleanup();hide();g.busy=false;poll()})}
 else if(f==='spk'){g.spk=g.spk===false;AU.muted=!g.spk;b.classList.toggle('on',g.spk);b.textContent=g.spk?'🔊 Speaker':'🔇 Speaker off'} else if(f==='save'){var o=(document.getElementById('fcao')||{}).value||'',n2=(document.getElementById('fcan')||{}).value||'';if(!o&&!n2.trim()){skip(+id);hide();return}post('/admin/free-calls/'+id+'/note',{note:n2.trim()||null,outcome:o||null}).then(function(){skip(+id);hide()})}
 else if(f==='skip'){skip(+id);hide()}});
S.addEventListener('change',function(){post('/admin/free-calls/status',{status:S.value}).then(function(){g.busy=false;poll()})});
window.addEventListener('beforeunload',function(){});
poll();setInterval(function(){poll()},2000);
})();</script>
@endverbatim
@endif
