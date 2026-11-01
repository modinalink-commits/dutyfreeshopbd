<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><meta name="csrf" content="{{ csrf_token() }}"><title>Softphone · DutyFreeBD</title>
<style>
:root{--n:#0B1F3A;--o:#FF5A00;--bd:#E5E7EB}*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:#F3F4F6;color:var(--n)}
.ph{max-width:380px;margin:0 auto;min-height:100vh;background:#fff;display:flex;flex-direction:column}
.top{background:var(--n);color:#fff;padding:12px 14px;display:flex;align-items:center;gap:10px}.top b{flex:1;font-size:15px}
.badge{font-size:11.5px;font-weight:800;padding:3px 10px;border-radius:12px;color:#fff;background:#6B7280}.b-connected{background:#16A34A}.b-connecting{background:#2563EB}.b-failed{background:#DC2626}.b-perm{background:#F59E0B}
.box{padding:14px;flex:1;display:flex;flex-direction:column;gap:10px}
.num{display:flex;gap:6px}.num input{flex:1;font-size:22px;letter-spacing:1px;text-align:center;padding:10px;border:1.5px solid var(--bd);border-radius:12px;color:var(--n);font-weight:700;min-width:0}.num button{border:1.5px solid var(--bd);background:#fff;border-radius:12px;width:48px;font-size:18px;cursor:pointer}
.stat{text-align:center;min-height:46px}.stat b{display:block;font-size:15px}.stat span{font-size:22px;font-weight:800;font-variant-numeric:tabular-nums}
.keys{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.keys button{height:54px;border:1px solid var(--bd);border-radius:14px;background:#F9FAFB;font-size:20px;font-weight:700;color:var(--n);cursor:pointer}.keys button:active{background:#FFF1E8}.keys small{display:block;font-size:9px;color:#9CA3AF;font-weight:600;letter-spacing:1px}
.row{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.row button{border:1px solid var(--bd);background:#fff;border-radius:12px;padding:9px 2px;font-size:12px;font-weight:700;color:var(--n);cursor:pointer}.row button.on{background:var(--n);color:#fff}
.go{display:flex;gap:8px}.go button{flex:1;border:0;border-radius:14px;height:56px;font-size:16px;font-weight:800;color:#fff;cursor:pointer}.call{background:#16A34A}.hang{background:#DC2626}button:disabled{opacity:.45;cursor:not-allowed}
.msg{font-size:12.5px;color:#B91C1C;text-align:center;min-height:16px}.mut{font-size:12px;color:#6B7280;text-align:center}
.ov{position:fixed;inset:0;background:rgba(11,31,58,.6);display:none;align-items:center;justify-content:center;z-index:9;padding:14px}.ov.on{display:flex}.card{background:#fff;border-radius:16px;padding:16px;width:min(360px,100%)}
.chip{display:inline-block;margin:3px 3px 0 0;padding:5px 10px;border:1px solid var(--bd);border-radius:14px;font-size:12px;font-weight:600;cursor:pointer;background:#fff}.chip:hover{border-color:var(--o);color:var(--o)}
textarea{width:100%;border:1.5px solid var(--bd);border-radius:10px;padding:8px;font:inherit;margin-top:8px}.pri{border:0;background:var(--o);color:#fff;border-radius:10px;padding:10px 16px;font-weight:800;cursor:pointer}.sec{border:1px solid var(--bd);background:#fff;border-radius:10px;padding:10px 16px;font-weight:700;cursor:pointer;color:var(--n)}
.inc b.nm{font-size:18px}.inc .ln{font-size:13px;color:#4B5563;margin-top:3px}
.top small{display:block;font-size:11.5px;color:#C9D1E0;margin-top:2px}.top .badge{font-size:10.5px;padding:1px 8px}.tb{border:0;background:rgba(255,255,255,.14);color:#fff;width:28px;height:28px;border-radius:8px;font-size:16px;cursor:pointer}
.tabs{display:flex;border-bottom:1px solid var(--bd);background:#fff}.tabs button{flex:1;border:0;background:none;padding:10px 2px;font-size:12px;font-weight:700;color:#6B7280;cursor:pointer;border-bottom:2px solid transparent}.tabs button.on{color:var(--o);border-bottom-color:var(--o)}
.fnd{min-height:0;font-size:12.5px;text-align:center}.fnd.on{background:#F0FDF4;border:1px solid #BBF7D0;color:#14532D;border-radius:10px;padding:6px 8px}
.srch{width:100%;border:1.5px solid var(--bd);border-radius:10px;padding:9px 11px;font:inherit;font-size:13px}.lst{display:flex;flex-direction:column}.li{display:flex;align-items:center;gap:10px;padding:9px 2px;border-top:1px solid #F1F3F6;cursor:pointer}.li:first-child{border-top:0}.li b{display:block;font-size:13px}.li small{color:#6B7280;font-size:11.5px}.li .go2{margin-left:auto;border:0;background:#E8F8EE;color:#15803D;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:15px}.li .av2{width:34px;height:34px;border-radius:50%;background:var(--n);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;flex:none}
.act{text-align:center;padding:10px 0}.act .av{width:62px;height:62px;border-radius:50%;background:var(--n);color:#fff;font-size:24px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 8px}.act b{font-size:17px;display:block}.act .as{font-weight:800;color:#16A34A;margin-top:6px}.act .at{font-size:26px;font-weight:800;font-variant-numeric:tabular-nums}
.kv{display:grid;grid-template-columns:1fr 1fr;gap:8px}.kv div{background:#F9FAFB;border:1px solid var(--bd);border-radius:10px;padding:8px 10px}.kv span{display:block;font-size:11px;color:#6B7280}.kv b{font-size:13px}.qa{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}.qa a{text-align:center;border:1px solid var(--bd);border-radius:10px;padding:9px;font-size:12.5px;font-weight:700;color:var(--n);text-decoration:none}.qa a:hover{border-color:var(--o);color:var(--o)}
.emb body,.emb{max-width:none}body:has(.emb){background:#fff}.emb.ph{max-width:none}
</style></head><body>
<div class="ph{{ $embed ? ' emb' : '' }}">
<div class="top"><div style="flex:1;min-width:0"><b>Soft Dialer</b><small>Call Center · <span class="badge" id="bdg">Disconnected</span></small></div>@if($embed)<button type="button" class="tb" id="pPop" title="Pop out (keeps the call alive while you browse)">⧉</button><button type="button" class="tb" id="pMin" title="Minimize">–</button><button type="button" class="tb" id="pX" title="Close">×</button>@endif</div>
<div class="tabs"><button type="button" data-t="pad" class="on">Dial Pad</button><button type="button" data-t="con">Contacts</button><button type="button" data-t="rec">Recent</button><button type="button" data-t="act">Active Call</button></div>
<div class="box tp" id="tp-pad">
<div class="stat"><b id="cst">Ready</b><span id="tmr">00:00:00</span></div>
<div class="num"><input id="num" inputmode="tel" autocomplete="off" placeholder="Enter Phone Number" value="{{ $n }}"><button id="bk" title="Backspace">⌫</button></div>
<div id="found" class="fnd"></div>
<div class="keys">@foreach([['1',''],['2','ABC'],['3','DEF'],['4','GHI'],['5','JKL'],['6','MNO'],['7','PQRS'],['8','TUV'],['9','WXYZ'],['*',''],['0','+'],['#','']] as $k)<button type="button" data-k="{{ $k[0] }}">{{ $k[0] }}<small>{{ $k[1] ?: ' ' }}</small></button>@endforeach</div>
<div class="row"><button id="bMute" disabled>🎙 Mute</button><button id="bHold" disabled>⏸ Hold</button><button id="bSpk">🔊 Speaker</button><button id="bDtmf" disabled>⌨ DTMF</button></div>
<div class="go"><button class="call" id="bCall">📞 Call</button><button class="hang" id="bHang" disabled>⛔ Hang Up</button></div>
<div class="msg" id="msg">@if(!$has)SIP account is not set up. Ask an admin to open Call Center → SIP Account.@endif</div>
<div class="mut"><a href="#" id="bCn" style="color:#4F46E5">Connect</a> · <a href="#" id="bDc" style="color:#4F46E5">Disconnect</a> · <a href="/admin/call-center/history" target="_blank" style="color:#4F46E5">Call history</a></div>
</div>
<div class="box tp" id="tp-con" style="display:none"><input id="cq" placeholder="Search name or phone" class="srch"><div id="cl" class="lst"></div></div>
<div class="box tp" id="tp-rec" style="display:none"><div id="rl" class="lst"></div></div>
<div class="box tp" id="tp-act" style="display:none"><div class="act"><div class="av" id="aAv">?</div><b id="aN">No active call</b><div id="aP" class="mut"></div><div id="aS" class="as">—</div><div id="aT" class="at">00:00:00</div></div>
<div class="kv"><div><span>Category</span><b id="aC">—</b></div><div><span>Total orders</span><b id="aO">—</b></div><div style="grid-column:1/-1"><span>Current / last order</span><b id="aL">—</b></div></div>
<div class="qa"><a id="aCu" target="_blank" href="#">Open Customer</a><a id="aOr" target="_blank" href="#">Open Order</a></div><div class="mut" style="margin-top:8px">When the call ends, a Call Note box opens: confirmed, no answer, call later, cancelled, WhatsApp follow-up or custom — saved to the customer and order history.</div></div>
</div>
<div class="ov" id="ovInc"><div class="card inc"><div style="font-size:12px;color:#6B7280;font-weight:700">Incoming Call</div><b class="nm" id="icN">Unknown caller</b><div class="ln" id="icP"></div><div class="ln" id="icI"></div><div style="display:flex;gap:8px;margin-top:14px"><button class="pri" id="icA" style="background:#16A34A;flex:1">Answer</button><button class="pri" id="icR" style="background:#DC2626;flex:1">Reject</button></div></div></div>
<div class="ov" id="ovNote"><div class="card"><b>Add Call Note</b><div style="margin-top:6px">@foreach(['Customer confirmed','No answer','Call later','Order cancelled','WhatsApp follow-up required'] as $c)<span class="chip" data-c="{{ $c }}">{{ $c }}</span>@endforeach</div><textarea id="ntx" rows="3" maxlength="1000" placeholder="Write a note…"></textarea><div style="display:flex;gap:8px;margin-top:10px"><button class="pri" id="nSave">Save note</button><button class="sec" id="nSkip">Skip</button></div></div></div>
<audio id="aud" autoplay></audio>
@verbatim
<script src="/js/jssip.min.js"></script>
<script>
(function(){
var $=function(i){return document.getElementById(i)},T=document.querySelector('meta[name=csrf]').content,ua=null,cfg=null,ses=null,logId=null,t0=0,tick=null,dir='out',held=false,muted=false,answered=false,cur='',ring=null,inCtx=null;
var ch=window.BroadcastChannel?new BroadcastChannel('dfphone'):null;
function post(u,o){var f=new FormData();f.append('_token',T);for(var k in o||{})f.append(k,o[k]);return fetch(u,{method:'POST',body:f,headers:{Accept:'application/json'}}).then(function(r){return r.json()})}
function badge(t,c){var b=$('bdg');b.textContent=t;b.className='badge '+(c||'');if(ch)ch.postMessage({type:'status',text:t,cls:c||''})}
function st(t){$('cst').textContent=t}function err(t){$('msg').textContent=t||''}
function fmt(s){var h=Math.floor(s/3600),m=Math.floor(s%3600/60),x=s%60;return [h,m,x].map(function(v){return (v<10?'0':'')+v}).join(':')}
function startTimer(){t0=Date.now();clearInterval(tick);tick=setInterval(function(){$('tmr').textContent=fmt(Math.floor((Date.now()-t0)/1000))},500)}
function secs(){return t0?Math.floor((Date.now()-t0)/1000):0}
function clean(n){return String(n||'').replace(/[^0-9+*#]/g,'')}
function ui(on){$('bCall').disabled=on||!ua||!ua.isRegistered();$('bHang').disabled=!on;['bMute','bHold','bDtmf'].forEach(function(i){$(i).disabled=!on||!answered})}
function mic(){if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia)return Promise.reject({name:'NoMedia'});return navigator.mediaDevices.getUserMedia({audio:true}).then(function(s){s.getTracks().forEach(function(t){t.stop()})})}
function micErr(e){badge('Permission Issue','b-perm');err('Microphone access is required to make calls.');st('Failed')}
function creds(){return fetch('/admin/call-center/creds',{headers:{Accept:'application/json'}}).then(function(r){return r.json()})}
function connect(){if(ua){return}badge('Connecting','b-connecting');creds().then(function(c){if(!c.ok){badge('Disconnected','');err(c.error);return}cfg=c;
 try{var sock=new JsSIP.WebSocketInterface(c.wss);ua=new JsSIP.UA({sockets:[sock],uri:'sip:'+c.user+'@'+c.domain,password:c.pw,authorization_user:c.auth,display_name:c.name,register:true,session_timers:false});}catch(e){badge('Registration Failed','b-failed');err('Could not start: '+e.message);ua=null;return}
 ua.on('registered',function(){badge('Connected','b-connected');err('');st('Ready');ui(false)});
 ua.on('unregistered',function(){badge('Disconnected','')});
 ua.on('registrationFailed',function(e){badge('Registration Failed','b-failed');err('Registration failed'+(e&&e.cause?': '+e.cause:'')+'. Check username, password and domain.')});
 ua.on('disconnected',function(){if(ua&&!ua.isRegistered()){badge('Disconnected','');err('Cannot reach the PBX over WSS. Check the WebSocket URL and that the PBX supports WebRTC.')}});
 ua.on('newRTCSession',function(e){var s=e.session;if(s.direction==='incoming')incoming(s)});
 ua.start()}).catch(function(){badge('Disconnected','');err('Could not load the SIP account.')})}
function disconnect(){if(ua){try{ua.stop()}catch(e){}ua=null}badge('Disconnected','');ui(false)}
function target(n){var p=cfg.prefix||'';n=clean(n);if(p&&n.indexOf(p)!==0&&n.charAt(0)!=='+')n=p+n;return 'sip:'+n+'@'+cfg.domain}
function wire(s){ses=s;answered=false;
 s.on('progress',function(){st('Ringing...')});
 s.on('accepted',function(){answered=true;st('Connected');startTimer();ui(true)});
 s.on('confirmed',function(){answered=true;if(!t0)startTimer();st('Connected');ui(true)});
 s.on('ended',function(){finish('Call Ended',answered?'answered':'missed')});
 s.on('failed',function(e){var c=(e&&e.cause)||'';finish('Failed',/busy/i.test(c)?'busy':(/cancel/i.test(c)&&dir==='in'?'missed':(/cancel|rejected/i.test(c)?'rejected':'failed')),c)});
 s.on('hold',function(){st('On Hold')});s.on('unhold',function(){st('Connected')});
 var pc=s.connection;if(pc)pc.addEventListener('track',function(ev){$('aud').srcObject=ev.streams[0]});
}
function finish(label,status,cause){var d=secs();clearInterval(tick);st(label);if(logId)post('/admin/call-center/log/'+logId+'/end',{status:status,duration:d});var id=logId;ses=null;answered=false;held=false;muted=false;t0=0;$('bMute').classList.remove('on');$('bHold').classList.remove('on');stopRing();ui(false);
 if(status==='failed'&&cause)err('Call failed: '+cause);if(id&&status!=='failed'||id&&d>0){note(id)}logId=null}
function call(n){n=clean(n||$('num').value);if(!n){err('Enter a phone number first.');return}if(!ua||!ua.isRegistered()){err('Not connected. Press Connect first.');return}err('');cur=n;dir='out';
 mic().then(function(){st('Dialing...');$('tmr').textContent='00:00:00';ui(true);return post('/admin/call-center/log',{direction:'out',phone:n,extension:cfg.ext})}).then(function(j){logId=j.id;
  var o={mediaConstraints:{audio:true,video:false},pcConfig:{iceServers:cfg.stun?[{urls:'stun:'+cfg.stun.replace(/^stun:/,'')}]:[{urls:'stun:stun.l.google.com:19302'}]},rtcOfferConstraints:{offerToReceiveAudio:true}};
  wire(ua.call(target(n),o))}).catch(function(e){ui(false);if(e&&(e.name==='NotAllowedError'||e.name==='NotFoundError'||e.name==='NoMedia'||e.name==='SecurityError'))micErr(e);else{st('Failed');err('Could not place the call.')}})}
function hang(){if(ses){try{ses.terminate()}catch(e){}}}
function ringStart(){try{inCtx=inCtx||new (window.AudioContext||window.webkitAudioContext)();var b=function(){var o=inCtx.createOscillator(),g=inCtx.createGain();o.frequency.value=480;g.gain.value=.08;o.connect(g);g.connect(inCtx.destination);o.start();setTimeout(function(){o.stop()},700)};b();ring=setInterval(b,1800)}catch(e){}}
function stopRing(){clearInterval(ring);ring=null}
function incoming(s){if(ses){try{s.terminate({status_code:486})}catch(e){}return}
 var num=clean((s.remote_identity&&s.remote_identity.uri&&s.remote_identity.uri.user)||'');dir='in';cur=num;ses=s;wire(s);st('Ringing...');ringStart();
 $('icN').textContent=(s.remote_identity&&s.remote_identity.display_name)||'Unknown caller';$('icP').textContent=num;$('icI').textContent='';fetch('/admin/call-center/lookup?phone='+encodeURIComponent(num),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(c){if(c.found){if(c.name)$('icN').textContent=c.name;$('icI').textContent=(c.category||'Customer')+' · '+c.orders+' orders'+(c.last?' · Last: '+c.last.invoice+' ('+c.last.status+')':'')}}).catch(function(){});$('ovInc').classList.add('on');
 post('/admin/call-center/log',{direction:'in',phone:num,extension:cfg&&cfg.ext}).then(function(j){logId=j.id});
 fetch('/admin/call-center/lookup?phone='+encodeURIComponent(num),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(c){if(c.found){$('icN').textContent=c.name||'Customer';$('icI').textContent=(c.category||'Customer')+(c.membership?' · Membership '+c.membership:'')+' · Previous orders: '+c.orders+(c.last?' · Last: '+c.last.invoice+' ('+c.last.status+')':'')}});
 if(window.Notification&&Notification.permission==='granted')try{new Notification('Incoming call',{body:num})}catch(e){}
 s.on('ended',function(){$('ovInc').classList.remove('on')});s.on('failed',function(){$('ovInc').classList.remove('on')});}
$('icA').onclick=function(){stopRing();$('ovInc').classList.remove('on');mic().then(function(){ses.answer({mediaConstraints:{audio:true,video:false},pcConfig:{iceServers:cfg.stun?[{urls:'stun:'+cfg.stun.replace(/^stun:/,'')}]:[{urls:'stun:stun.l.google.com:19302'}]}});$('num').value=cur;ui(true)}).catch(micErr)};
$('icR').onclick=function(){stopRing();$('ovInc').classList.remove('on');try{ses.terminate({status_code:486})}catch(e){}};
function note(id){$('ntx').value='';$('ovNote').classList.add('on');$('ovNote').dataset.id=id}
document.querySelectorAll('.chip').forEach(function(c){c.onclick=function(){$('ntx').value=c.dataset.c}});
$('nSkip').onclick=function(){$('ovNote').classList.remove('on')};
$('nSave').onclick=function(){var t=$('ntx').value.trim();if(!t)return;post('/admin/call-center/log/'+$('ovNote').dataset.id+'/note',{note:t}).then(function(){$('ovNote').classList.remove('on')})};
document.querySelectorAll('[data-k]').forEach(function(b){b.onclick=function(){var k=b.dataset.k;if(ses&&answered){try{ses.sendDTMF(k)}catch(e){}}$('num').value+=k}});
$('bk').onclick=function(){$('num').value=$('num').value.slice(0,-1)};
$('bCall').onclick=function(){call()};$('bHang').onclick=hang;
$('bMute').onclick=function(){if(!ses)return;muted=!muted;muted?ses.mute({audio:true}):ses.unmute({audio:true});this.classList.toggle('on',muted);this.textContent=muted?'🎙 Unmute':'🎙 Mute'};
$('bHold').onclick=function(){if(!ses)return;held=!held;held?ses.hold():ses.unhold();this.classList.toggle('on',held);this.textContent=held?'▶ Resume':'⏸ Hold'};
$('bSpk').onclick=function(){var a=$('aud');a.muted=!a.muted;this.classList.toggle('on',a.muted);this.textContent=a.muted?'🔇 Speaker off':'🔊 Speaker'};
$('bDtmf').onclick=function(){err('Use the keypad above to send DTMF tones during the call.')};
$('bCn').onclick=function(e){e.preventDefault();connect()};$('bDc').onclick=function(e){e.preventDefault();disconnect()};
if(ch)ch.onmessage=function(m){var d=m.data||{};if(d.type==='ping'){ch.postMessage({type:'here'})}if(d.type==='dial'){$('num').value=clean(d.n);window.focus();$('num').focus()}if(d.type==='call'){$('num').value=clean(d.n);window.focus();call(d.n)}if(d.type==='disconnect')disconnect();if(d.type==='connect')connect();if(d.type==='getstatus')ch.postMessage({type:'status',text:$('bdg').textContent,cls:$('bdg').className.replace('badge ','')})};
window.addEventListener('beforeunload',function(e){if(ses&&answered){e.preventDefault();e.returnValue=''}});
if(window.Notification&&Notification.permission==='default')try{Notification.requestPermission()}catch(e){}
ui(false);
if(document.querySelector('.msg').textContent==='')connect();
})();
</script>
@endverbatim
<script>
(function(){
var $=function(i){return document.getElementById(i)},emb=!!document.querySelector('.ph.emb');
function esc(t){return String(t==null?'':t).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function tab(t){document.querySelectorAll('.tabs button').forEach(function(b){b.classList.toggle('on',b.dataset.t===t)});document.querySelectorAll('.tp').forEach(function(p){p.style.display=p.id==='tp-'+t?'':'none'});if(t==='rec')recent();if(t==='con')contacts()}
document.querySelectorAll('.tabs button').forEach(function(b){b.onclick=function(){tab(b.dataset.t)}});
function fmtD(s){return s>=60?Math.floor(s/60)+'m '+(s%60)+'s':s+'s'}
function recent(){fetch('/admin/call-center/recent',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(a){$('rl').innerHTML=a.length?a.map(function(c){return '<div class="li"><span class="av2">'+esc((c.name||'?').charAt(0).toUpperCase())+'</span><div><b>'+esc(c.name||c.phone)+'</b><small>'+esc(c.phone)+'<br>'+(c.dir==='in'?'↙ Incoming':'↗ Outgoing')+' · '+esc(c.status)+' · '+fmtD(c.dur)+' · '+esc(c.at)+'</small></div><button type="button" class="go2" data-n="'+esc(c.phone)+'" title="Call again">📞</button></div>'}).join(''):'<div class="mut">No calls yet.</div>'})}
function contacts(){var q=$('cq').value;fetch('/admin/call-center/contacts?q='+encodeURIComponent(q),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(a){$('cl').innerHTML=a.length?a.map(function(c){return '<div class="li"><span class="av2">'+esc((c.name||'?').charAt(0).toUpperCase())+'</span><div><b>'+esc(c.name)+'</b><small>'+esc(c.phone)+' · '+c.orders+' orders</small></div><button type="button" class="go2" data-n="'+esc(c.phone)+'" title="Call">📞</button></div>'}).join(''):'<div class="mut">No customers found.</div>'})}
var t0;$('cq').oninput=function(){clearTimeout(t0);t0=setTimeout(contacts,250)};
document.addEventListener('click',function(e){var b=e.target.closest('.go2');if(!b)return;$('num').value=b.dataset.n;tab('pad');found();setTimeout(function(){$('bCall').click()},150)});
var lastN='';function found(){var n=($('num').value||'').replace(/\D/g,'');if(n===lastN)return;lastN=n;var f=$('found');if(n.length<6){f.className='fnd';f.textContent='';return}
 fetch('/admin/call-center/lookup?phone='+encodeURIComponent(n),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(c){if(c.found){f.className='fnd on';f.innerHTML='Customer Found: <b>'+esc(c.name||'Customer')+'</b><br>'+esc(c.category||'Customer')+' · '+c.orders+' Orders'}else{f.className='fnd';f.textContent=''}})}
setInterval(found,600);
var curNum='';function active(n){curNum=n;fetch('/admin/call-center/lookup?phone='+encodeURIComponent(n),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(c){$('aN').textContent=(c.found&&c.name)||'Unknown caller';$('aAv').textContent=((c.name||'?').charAt(0)).toUpperCase();$('aC').textContent=c.found?(c.category||'Customer'):'New caller';$('aO').textContent=c.found?c.orders:'0';$('aL').textContent=c.last?(c.last.invoice+' · '+c.last.status):'—';$('aCu').href='/admin/m/customers?q='+encodeURIComponent(n);$('aOr').href=c.last?('/admin/orders/'+c.last.id):'/admin/orders?q='+encodeURIComponent(n)})}
function sync(){var s=$('cst').textContent;$('aS').textContent=s;$('aT').textContent=$('tmr').textContent;var live=/Dialing|Ringing|Connected|Hold/i.test(s);if(live){var n=($('icP').textContent||$('num').value||'').replace(/[^0-9+]/g,'');$('aP').textContent=n;if(n&&n!==curNum)active(n);if(!sync.sw){sync.sw=1;tab('act')}}else{sync.sw=0}}
new MutationObserver(sync).observe($('cst'),{childList:true,characterData:true,subtree:true});new MutationObserver(function(){$('aT').textContent=$('tmr').textContent}).observe($('tmr'),{childList:true,characterData:true,subtree:true});
new MutationObserver(function(){if($('ovInc').classList.contains('on')){tab('act');if(emb)parent.postMessage({dfdial:'incoming'},location.origin)}}).observe($('ovInc'),{attributes:true,attributeFilter:['class']});
if(emb){var P=function(a){parent.postMessage({dfdial:a},location.origin)};$('pX').onclick=function(){P('close')};$('pMin').onclick=function(){P('min')};$('pPop').onclick=function(){P('pop')};
 new MutationObserver(function(){P($('bHang').disabled?'idle':'busy')}).observe($('bHang'),{attributes:true,attributeFilter:['disabled']})}
})();
</script>
</body></html>