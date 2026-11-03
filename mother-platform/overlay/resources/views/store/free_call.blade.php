@php
  $__fcw = null;
  try { $__st = auth()->check(); $__fcw = \Illuminate\Support\Facades\Cache::remember('fc.cfg.' . ($__st ? 's' : 'c'), 30, fn() => \App\Support\FreeCall::widget($__st)); } catch (\Throwable $e) { $__fcw = null; }
  $__fcbn = ($s['mobile_bottom_nav'] ?? '') === '1';
@endphp
@if($__fcw && $__fcw[0])
<style>
.dffc-b{position:fixed;left:14px;bottom:18px;z-index:55;display:flex;align-items:center;gap:7px;background:#FF5A00;color:#fff;border:0;border-radius:24px;padding:11px 16px;font-size:13px;font-weight:700;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;box-shadow:0 4px 14px rgba(255,90,0,.38);cursor:pointer}
.dffc-b svg{width:16px;height:16px}.dffc-b.off{background:#64748B;box-shadow:0 4px 14px rgba(100,116,139,.35)}
@media(max-width:760px){.dffc-b{bottom:68px}}
.ai0,.aip{display:none!important}
.dffc-o{position:fixed;inset:0;z-index:100002;background:rgba(11,18,32,.5);display:none;align-items:center;justify-content:center;padding:14px;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif}.dffc-o.on{display:flex}
.dffc-x{background:#fff;border-radius:22px;width:min(350px,100%);box-shadow:0 24px 60px rgba(11,18,32,.4);overflow:hidden;color:#0B1220}
.dffc-h{background:#FF5A00;color:#fff;padding:12px 16px;display:flex;align-items:center;gap:9px;font-weight:800;font-size:14px}.dffc-h i{width:9px;height:9px;border-radius:50%;background:#fff;display:inline-block}.dffc-h button{margin-left:auto;background:rgba(255,255,255,.12);border:0;color:#fff;width:28px;height:28px;border-radius:8px;cursor:pointer;font-size:16px;padding:0}
.dffc-p{padding:20px 18px 18px;text-align:center}.dffc-p h3{margin:0 0 6px;font-size:19px}.dffc-p p{margin:6px 0;font-size:13.5px;line-height:1.5;color:#374151}.dffc-p .s{font-size:12px;color:#6B7280}
.dffc-p input{width:100%;border:1.25px solid #FDC2A5;border-radius:11px;padding:11px 12px;font:inherit;font-size:14px;margin:5px 0;background:#fff}
.dffc-bt{display:block;width:100%;border:0;border-radius:12px;padding:12px;font-weight:700;font-size:14px;font-family:inherit;cursor:pointer;margin-top:9px;background:#FF5A00;color:#fff}.dffc-bt.g{background:#fff;color:#0B1220;border:1.25px solid #E5E7EB}.dffc-bt.r{background:#E11D48}.dffc-bt.gr{background:#FF5A00}.dffc-bt:disabled{opacity:.6}
.dffc-big{font-size:34px;font-weight:800;letter-spacing:.02em;margin:8px 0}.dffc-av{width:68px;height:68px;border-radius:50%;background:#FF5A00;color:#fff;font-size:26px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:4px auto 8px;position:relative}
.dffc-av.pu::after{content:"";position:absolute;inset:-8px;border-radius:50%;border:2px solid rgba(255,90,0,.45);animation:dffcp 1.4s ease-out infinite}@keyframes dffcp{from{transform:scale(.85);opacity:1}to{transform:scale(1.3);opacity:0}}
.dffc-ct{display:flex;gap:10px;justify-content:center;margin-top:10px}.dffc-ct button{flex:1;border:1.25px solid #E5E7EB;background:#fff;border-radius:13px;padding:10px 4px;font-weight:600;font-size:12px;font-family:inherit;cursor:pointer}.dffc-ct button.on{background:#0B1220;color:#fff;border-color:#0B1220}
.dffc-e{color:#B91C1C;font-size:12.5px;min-height:16px}
</style>
<button type="button" class="dffc-b{{ $__fcw[1] ? '' : ' off' }}" id="dffcb" aria-label="Free Call"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>ফ্রি কল করুন</button>
<div class="dffc-o" id="dffco" role="dialog" aria-modal="true" aria-label="DutyFreeBD Free Call"><div class="dffc-x"><div class="dffc-h"><i></i>DutyFreeBD Free Call<button type="button" data-a="close" aria-label="Close">×</button></div><div class="dffc-p" id="dffcp"></div></div></div>
<audio id="dffca" autoplay playsinline></audio>
<script>window.DFFC={open:@json((bool)$__fcw[1]),msg:@json($__fcw[2]),wa:@json($wa ?? ''),tel:'09613828081'};</script>
@verbatim
<script>(function(){
var C=window.DFFC||{},$=function(i){return document.getElementById(i)},O=$('dffco'),P=$('dffcp'),AU=$('dffca'),KEY='dffc';
var st={};function reset(){st={c:null,s:null,after:0,pc:null,stream:null,poll:null,muted:false,spk:true,conn:0,iceQ:[],rd:false,t0:0,tick:null,tone:null,view:''}}reset();
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function show(h,v){st.view=v||'';P.innerHTML=h}
function J(u,b){var o={headers:{'Accept':'application/json'}};if(b){o.method='POST';o.headers['Content-Type']='application/json';o.body=JSON.stringify(b)}return fetch(u,o).then(function(r){return r.json().then(function(j){j._st=r.status;return j})})}
function ident(){try{var o=JSON.parse(localStorage.getItem('df_cust')||'null')||{},h=JSON.parse(localStorage.getItem('dfchat')||'null')||{};return {p:String(o.p||h.p||'').replace(/\D/g,'').slice(-11),n:o.n||h.n||''}}catch(e){return {p:'',n:''}}}
function sess(v){try{if(v===undefined)return JSON.parse(sessionStorage.getItem(KEY)||'null');if(v)sessionStorage.setItem(KEY,JSON.stringify(v));else sessionStorage.removeItem(KEY)}catch(e){}return null}
function fmt(n){n=Math.max(0,n|0);return ('0'+((n/60)|0)).slice(-2)+':'+('0'+(n%60)).slice(-2)}
function fallback(){return '<button class="dffc-bt g" data-a="chat">💬 Chat With Us</button><a class="dffc-bt g" style="text-decoration:none;box-sizing:border-box" href="https://wa.me/'+esc(C.wa)+'" target="_blank" rel="noopener">WhatsApp</a><a class="dffc-bt g" style="text-decoration:none;box-sizing:border-box" href="tel:'+C.tel+'">📞 Hotline '+C.tel+'</a>'}
function stopAll(){if(st.poll){clearInterval(st.poll);st.poll=null}if(st.tick){clearInterval(st.tick);st.tick=null}tone(0);if(st.pc){try{st.pc.close()}catch(e){}st.pc=null}if(st.stream){st.stream.getTracks().forEach(function(t){t.stop()});st.stream=null}try{AU.srcObject=null}catch(e){}}
// soft ringback tone (only after the customer's own click, so browsers allow it)
function tone(on){if(!on){if(st.tone){clearInterval(st.tone.i);try{st.tone.c.close()}catch(e){}st.tone=null}return}if(st.tone)return;try{var AC=window.AudioContext||window.webkitAudioContext,c=new AC(),b=function(){var o=c.createOscillator(),g=c.createGain();o.frequency.value=425;g.gain.value=.025;o.connect(g);g.connect(c.destination);o.start();setTimeout(function(){try{o.stop()}catch(e){}},900)};b();st.tone={c:c,i:setInterval(b,3500)}}catch(e){}}
function intro(){
 if(!C.open){show('<h3>Free Call Currently Offline</h3><p>'+esc(C.msg||'Free Call is offline right now.')+'</p>'+fallback(),'off');return}
 show('<h3>Talk to DutyFreeBD</h3><p>ইন্টারনেটের মাধ্যমে আমাদের Customer Care-এর সাথে বিনামূল্যে কথা বলুন।</p><p class="s">No mobile call charge. This call uses your internet connection and needs microphone access.</p><button class="dffc-bt gr" data-a="start">Start Free Call</button><button class="dffc-bt g" data-a="chat">💬 Continue with Chat</button>','intro')}
function form(){var i=ident();show('<h3>Talk to DutyFreeBD</h3><p class="s">Your details help our team help you.</p><input id="dffn" maxlength="60" placeholder="Your Name" value="'+esc(i.n)+'" autocomplete="name"><input id="dffp" inputmode="numeric" maxlength="11" placeholder="01XXXXXXXXX" value="'+esc(i.p)+'" autocomplete="tel"><div class="dffc-e" id="dffe"></div><button class="dffc-bt gr" data-a="go">Start Free Call</button>','form')}
function micDenied(msg){show('<h3>Microphone needed</h3><p>Microphone permission is required for Free Call.</p>'+(msg?'<p class="s">'+esc(msg)+'</p>':'')+'<button class="dffc-bt" data-a="go">Try Again</button><button class="dffc-bt g" data-a="chat">Continue with Chat</button>','mic')}
function start(){var i=ident();if(/^01\d{9}$/.test(i.p)){st.name=i.n;st.phone=i.p;go(true)}else form()}
function go(auto){
 var n=st.name,p=st.phone;if(!auto){n=($('dffn')||{}).value||'';p=String(($('dffp')||{}).value||'').replace(/\D/g,'').slice(-11);if(!/^01\d{9}$/.test(p)){$('dffe').textContent='সঠিক মোবাইল নম্বর দিন (১১ ডিজিট, 01 দিয়ে শুরু)';return}st.name=n;st.phone=p;try{var o=JSON.parse(localStorage.getItem('df_cust')||'{}')||{};o.p=p;if(n)o.n=n;localStorage.setItem('df_cust',JSON.stringify(o))}catch(e){}}
 if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia||!window.RTCPeerConnection){show('<h3>Not supported</h3><p>This browser cannot make internet calls. Please use Chat.</p>'+fallback(),'x');return}
 show('<h3>DutyFreeBD needs microphone access</h3><p>to start your free call.</p><p class="s">Please tap Allow in your browser.</p>','mic0');
 navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true,autoGainControl:true}}).then(function(s){st.stream=s;create()}).catch(function(e){micDenied(e&&e.name==='NotFoundError'?'No microphone was found on this device.':'')})}
function create(){
 show('<h3>Connecting you to DutyFreeBD Customer Care…</h3><div class="dffc-big">…</div>','w');
 var old=sess();J('/free-call/request',{name:st.name,phone:st.phone,url:location.href,title:document.title,product_id:window.DFPID||null,invoice:window.DFINV||null,session:old&&old.s||null}).then(function(j){
  if(!j.ok){stopAll();show('<h3>Free Call unavailable</h3><p>'+esc(j.error||'Please try again.')+'</p>'+fallback(),'x');return}
  st.c=j.c;st.s=j.s;sess({c:j.c,s:j.s});loop()}).catch(function(){stopAll();show('<h3>Connection problem</h3><p>Please check your internet and try again.</p>'+fallback(),'x')})}
function loop(){if(st.poll)clearInterval(st.poll);pollOnce();st.poll=setInterval(pollOnce,1500)}
var busy=false;
function pollOnce(){if(busy||!st.c)return;busy=true;fetch('/free-call/state?c='+st.c+'&s='+st.s+'&after='+st.after,{headers:{'Accept':'application/json'}}).then(function(r){return r.json().then(function(j){j._st=r.status;return j})}).then(function(j){busy=false;onState(j)}).catch(function(){busy=false})}
function waitScreen(j){
 var ring=j.status==='ringing';tone(ring);
 show('<div class="dffc-av pu">📞</div><h3>'+(j.pos>1||!ring?'Waiting for an available representative':'Connecting you…')+'</h3><p>'+esc(st.name||'')+'</p><p class="s">Queue position: <b>'+(j.pos||1)+'</b> · Waiting: <b id="dffw">'+fmt(j.wait)+'</b></p>'+(j.wait>45?'<p class="s">All representatives are currently busy. You are in the waiting queue.</p><button class="dffc-bt g" data-a="chat">💬 Send Message</button>':'')+'<button class="dffc-bt r" data-a="cancel">Cancel Call</button>','w')}
function onState(j){
 if(j._st===404||j.gone){stopAll();sess(false);reset();intro();return}
 if(!j.live){endScreen(j);return}
 if(j.status==='waiting'||j.status==='ringing'){waitScreen(j);return}
 if(j.status==='accepted'||j.status==='connected'){tone(0);
  if(!st.pc){if(!st.stream){navigator.mediaDevices.getUserMedia({audio:true}).then(function(s){st.stream=s;j._r=1;startPc(j)}).catch(function(){hang(true)});return}startPc(j)}
  (j.signals||[]).forEach(handle);if(j.status==='connected'&&!st.conn){st.conn=1;connScreen(j)}
  if(st.conn&&$('dffc-t')&&j.agent)$('dffc-ag').textContent=j.agent}}
function startPc(j){
 if(st.pc)return;var pc=st.pc=new RTCPeerConnection({iceServers:j.ice||[]});st.stream.getTracks().forEach(function(t){pc.addTrack(t,st.stream)});
 pc.onicecandidate=function(e){if(e.candidate)sig('ice',JSON.stringify(e.candidate))};
 pc.ontrack=function(e){AU.srcObject=e.streams[0];var p=AU.play();if(p&&p.catch)p.catch(function(){})};
 pc.onconnectionstatechange=function(){var s=pc.connectionState;if(s==='connected'&&!st.conn){st.conn=1;st.t0=Date.now();J('/free-call/connected',{c:st.c,s:st.s});connScreen(j)}else if(s==='failed'){fail()}};
 show('<div class="dffc-av pu">📞</div><h3>Connected to DutyFreeBD</h3><p class="s">Setting up your voice connection…</p>'+'<button class="dffc-bt r" data-a="cancel">End Call</button>','c0');
 pc.createOffer().then(function(o){return pc.setLocalDescription(o)}).then(function(){sig('offer',JSON.stringify(pc.localDescription))}).catch(function(){fail()})}
function sig(type,payload){J('/free-call/signal',{c:st.c,s:st.s,type:type,payload:payload})}
function handle(m){st.after=Math.max(st.after,m.id);if(!st.pc)return;
 if(m.type==='answer'){st.pc.setRemoteDescription(JSON.parse(m.payload)).then(function(){st.rd=true;st.iceQ.splice(0).forEach(function(c){st.pc.addIceCandidate(c).catch(function(){})})}).catch(function(){})}
 else if(m.type==='ice'){var c=JSON.parse(m.payload);if(st.rd)st.pc.addIceCandidate(c).catch(function(){});else st.iceQ.push(c)}
 else if(m.type==='hangup'){endScreen({status:'completed'})}}
function connScreen(j){
 if(!st.t0)st.t0=Date.now();var ag=(j&&j.agent)||'Customer Care';
 show('<div class="dffc-av">'+esc(ag.charAt(0).toUpperCase())+'</div><h3>Connected to DutyFreeBD</h3><p class="s">Representative: <b id="dffc-ag">'+esc(ag)+'</b></p><div class="dffc-big" id="dffc-t">00:00</div><div class="dffc-ct"><button data-a="mute" id="dffm">🎤 Mute</button><button data-a="spk" id="dffs" class="on">🔊 Speaker</button></div><button class="dffc-bt r" data-a="cancel">🔴 End Call</button>','c');
 if(st.tick)clearInterval(st.tick);st.tick=setInterval(function(){var e=$('dffc-t');if(e)e.textContent=fmt((Date.now()-st.t0)/1000)},500)}
function endScreen(j){
 var ok=j.status==='completed',cancelled=j.status==='cancelled';stopAll();sess(false);var d=j.duration;
 var t=ok?'Call Ended':cancelled?'Call Cancelled':j.status==='no_agent'||j.status==='missed'?'No representative available':'Connection problem';
 var m=ok?'Thank you for calling DutyFreeBD.'+(d?' Duration '+fmt(d)+'.':''):cancelled?'':'We could not connect your call. Please use Chat, WhatsApp or our hotline.';
 reset();show('<h3>'+t+'</h3><p>'+m+'</p>'+(ok||cancelled?'<button class="dffc-bt g" data-a="close">Close</button>':fallback()),'end')}
function fail(){var c=st.c,s=st.s;if(c)J('/free-call/cancel',{c:c,s:s});stopAll();sess(false);reset();show('<h3>Connection problem</h3><p>We could not connect your voice call. This can happen on some mobile networks.</p>'+fallback(),'end')}
function hang(silent){var c=st.c,s=st.s;if(c)J('/free-call/cancel',{c:c,s:s});stopAll();sess(false);reset();if(!silent)endScreen({status:'cancelled'});else intro()}
function openChat(){close();var b=document.getElementById('dfcb');if(b)b.click()}
function close(){O.classList.remove('on');if(st.view==='end'||st.view==='intro'||st.view==='form'||st.view==='off'||st.view==='x'||st.view==='mic'){}}
function resume(){var o=sess();if(!o)return false;st.c=o.c;st.s=o.s;fetch('/free-call/state?c='+o.c+'&s='+o.s,{headers:{'Accept':'application/json'}}).then(function(r){return r.json().then(function(j){j._st=r.status;return j})}).then(function(j){
  if(j._st===404||!j.live){sess(false);reset();return}
  if(j.status==='waiting'||j.status==='ringing'){O.classList.add('on');st.name='';navigator.mediaDevices.getUserMedia({audio:true}).then(function(s){st.stream=s;loop()}).catch(function(){hang(true)})}
  else{J('/free-call/cancel',{c:o.c,s:o.s});sess(false);reset()}}).catch(function(){});return false}
function mute(){st.muted=!st.muted;if(st.stream)st.stream.getAudioTracks().forEach(function(t){t.enabled=!st.muted});var b=$('dffm');if(b){b.classList.toggle('on',st.muted);b.textContent=st.muted?'🎤 Unmute':'🎤 Mute'}J('/free-call/event',{c:st.c,s:st.s,e:st.muted?'muted':'unmuted'})}
function spk(){st.spk=!st.spk;AU.muted=!st.spk;var b=$('dffs');if(b)b.classList.toggle('on',st.spk)}
document.addEventListener('click',function(e){var b=e.target.closest('[data-a]');
 if(e.target.closest('#dffcb')){O.classList.add('on');if(!st.c)intro();return}
 if(!b)return;var a=b.getAttribute('data-a');
 if(a==='close'){if(st.c&&st.poll){O.classList.remove('on');return}O.classList.remove('on');return}
 if(a==='start')start();else if(a==='go')go(false);else if(a==='cancel')hang();else if(a==='chat')openChat();else if(a==='mute')mute();else if(a==='spk')spk()});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&O.classList.contains('on')&&!st.c)O.classList.remove('on')});
if(sess())resume();
})();</script>
@endverbatim
@endif
