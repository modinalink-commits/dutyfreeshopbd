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
</style></head><body>
<div class="ph">
<div class="top"><b>☎ Softphone</b><span class="badge" id="bdg">Disconnected</span></div>
<div class="box">
<div class="stat"><b id="cst">Ready</b><span id="tmr">00:00:00</span></div>
<div class="num"><input id="num" inputmode="tel" autocomplete="off" placeholder="Enter Phone Number" value="{{ $n }}"><button id="bk" title="Backspace">⌫</button></div>
<div class="keys">@foreach([['1',''],['2','ABC'],['3','DEF'],['4','GHI'],['5','JKL'],['6','MNO'],['7','PQRS'],['8','TUV'],['9','WXYZ'],['*',''],['0','+'],['#','']] as $k)<button type="button" data-k="{{ $k[0] }}">{{ $k[0] }}<small>{{ $k[1] ?: ' ' }}</small></button>@endforeach</div>
<div class="row"><button id="bMute" disabled>🎙 Mute</button><button id="bHold" disabled>⏸ Hold</button><button id="bSpk">🔊 Speaker</button><button id="bDtmf" disabled>⌨ DTMF</button></div>
<div class="go"><button class="call" id="bCall">📞 Call</button><button class="hang" id="bHang" disabled>⛔ Hang Up</button></div>
<div class="msg" id="msg">@if(!$has)SIP account is not set up. Ask an admin to open Call Center → SIP Account.@endif</div>
<div class="mut"><a href="#" id="bCn" style="color:#4F46E5">Connect</a> · <a href="#" id="bDc" style="color:#4F46E5">Disconnect</a> · <a href="/admin/call-center/history" target="_blank" style="color:#4F46E5">Call history</a></div>
</div></div>
<div class="ov" id="ovInc"><div class="card inc"><div style="font-size:12px;color:#6B7280;font-weight:700">Incoming Call</div><b class="nm" id="icN">Unknown caller</b><div class="ln" id="icP"></div><div class="ln" id="icI"></div><div style="display:flex;gap:8px;margin-top:14px"><button class="pri" id="icA" style="background:#16A34A;flex:1">Answer</button><button class="pri" id="icR" style="background:#DC2626;flex:1">Reject</button></div></div></div>
<div class="ov" id="ovNote"><div class="card"><b>Add Call Note</b><div style="margin-top:6px">@foreach(['Customer confirmed','No answer','Call later','Order cancelled','WhatsApp follow-up required'] as $c)<span class="chip" data-c="{{ $c }}">{{ $c }}</span>@endforeach</div><textarea id="ntx" rows="3" maxlength="1000" placeholder="Write a note…"></textarea><div style="display:flex;gap:8px;margin-top:10px"><button class="pri" id="nSave">Save note</button><button class="sec" id="nSkip">Skip</button></div></div></div>
<audio id="aud" autoplay></audio>
@verbatim
<script src="https://cdnjs.cloudflare.com/ajax/libs/jssip/3.10.0/jssip.min.js"></script>
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
 $('icN').textContent=(s.remote_identity&&s.remote_identity.display_name)||'Unknown caller';$('icP').textContent=num;$('icI').textContent='';$('ovInc').classList.add('on');
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
</body></html>
