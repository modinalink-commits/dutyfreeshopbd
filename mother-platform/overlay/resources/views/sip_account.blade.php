@extends('layout')@section('title','SIP Account')
@section('body')
<h1 style="margin:0 0 4px">SIP Account</h1>
<span style="font-size:13px;color:#6B7280">Connect the admin web softphone to your PBX. The password is stored encrypted on the server and is never written into the page.</span>
@if(session('ok'))<div class="card" style="margin-top:12px;background:#DCFCE7;border-color:#A7F3D0">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card err" style="margin-top:12px">@foreach($errors->all() as $e){{ $e }}<br>@endforeach</div>@endif
<div class="card" style="margin-top:12px;background:#FEF3C7;border-color:#FDE68A;font-size:13px"><b>Before you start:</b> a browser cannot use the UDP connection MicroSIP uses. Your PBX must support <b>WebRTC</b> and <b>SIP over WSS</b> (secure WebSocket), for example <code>wss://pbx.example.com:8089/ws</code>. Ask your PBX provider: <i>"Does this SIP account/PBX support WebRTC and SIP over WSS for a browser softphone?"</i> and request the WSS address.</div>
<div class="card" style="margin-top:12px"><div style="display:flex;align-items:center;gap:10px;margin-bottom:8px"><b>Connection status</b><span id="bdg" class="chip" style="background:#6B7280;color:#fff;border:0;font-weight:800">Disconnected</span></div>
<form method="post" action="/admin/call-center/account" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">@csrf
@php($f=fn($k)=>old($k, $a->$k ?? ''))
<details style="grid-column:1/-1"><summary style="cursor:pointer;font-size:13px;color:#6B7280">Advanced — অটো সেট করা আছে (PBX: childcity.pbx.com.bd · WSS port 443), সাধারণত বদলাতে হবে না</summary><div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));margin-top:8px"><label>Account Name<input name="account_name" value="{{ $f('account_name') }}" placeholder="Child City PBX"></label>
<label>SIP Server<input name="sip_server" value="{{ $f('sip_server') }}" placeholder="childcity.pbx.com.bd"></label>
<label>SIP WebSocket / WSS URL<input name="wss_url" id="wss" value="{{ $f('wss_url') }}" placeholder="wss://pbx.example.com:8089/ws"></label>
</div></details>
<label>Username / Extension<input name="username" id="usr" value="{{ $f('username') }}" required></label>
<label>Password <small style="color:#6B7280">{{ $hasPw ? 'saved — shown as stored' : 'not set' }}</small><input type="text" name="password" id="pwd" value="{{ $pwPlain ?? '' }}" autocomplete="off" autocapitalize="off" spellcheck="false" data-lpignore="true" style="font-family:ui-monospace,Menlo,monospace"></label>
<details style="grid-column:1/-1"><summary style="cursor:pointer;font-size:13px;color:#6B7280">Advanced 2 — Domain, Auth, Caller ID, STUN</summary><div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));margin-top:8px"><label>Auth Username<input name="auth_username" id="aus" value="{{ $f('auth_username') }}" placeholder="same as username if empty"></label>
<label>Domain<input name="domain" id="dom" value="{{ $f('domain') }}" placeholder="childcity.pbx.com.bd" required></label>
<label>Display Name<input name="display_name" value="{{ $f('display_name') }}" placeholder="Forhad"></label>
<label>Outbound Proxy (optional)<input name="outbound_proxy" value="{{ $f('outbound_proxy') }}"></label>
<label>Dialing Prefix (optional)<input name="dial_prefix" value="{{ $f('dial_prefix') }}"></label>
<label>Caller ID<input name="caller_id" value="{{ $f('caller_id') }}"></label>
<label>STUN/TURN Server (optional)<input name="stun_server" value="{{ $f('stun_server') }}" placeholder="stun.l.google.com:19302"></label>
<label style="grid-column:1/-1;display:flex;gap:8px;align-items:center"><input type="checkbox" name="recording_on" value="1" style="width:auto" @checked(\Illuminate\Support\Facades\DB::table('settings')->where('key','cc_recording_on')->value('value')==='1')> আমাদের PBX কল রেকর্ড করে (ডায়ালারে "Recording" সূচক দেখাবে)। রেকর্ডিং লিংক PBX থেকে না এলে "Processing" দেখাবে — লিংক ছাড়া "Available" দেখানো হয় না।</label></div></details>
<div style="grid-column:1/-1;display:flex;gap:8px;flex-wrap:wrap"><button class="btn" type="submit">Save Account</button><button class="btn" type="button" id="bTest" style="background:#fff;color:#0B1220;border:1px solid #D1D5DB">Test Connection</button><button class="btn" type="button" id="bCon" style="background:#16A34A">Connect</button><button class="btn" type="button" id="bDis" style="background:#6B7280">Disconnect</button></div>
</form><div id="tres" style="margin-top:10px;font-size:13px"></div></div>
@verbatim
<script src="/js/jssip.min.js"></script>
<script>
(function(){
var $=function(i){return document.getElementById(i)},ch=window.BroadcastChannel?new BroadcastChannel('dfphone'):null;
function badge(t,c){var b=$('bdg');b.textContent=t;b.style.background=({'b-connected':'#16A34A','b-connecting':'#2563EB','b-failed':'#DC2626','b-perm':'#F59E0B'})[c]||'#6B7280'}
if(ch){ch.onmessage=function(m){var d=m.data||{};if(d.type==='status')badge(d.text,d.cls);if(d.type==='here')$('tres').textContent='Softphone window is open.'};ch.postMessage({type:'getstatus'})}
$('bCon').onclick=function(){if(ch)ch.postMessage({type:'connect'});var w=window.open('/admin/call-center/phone','dfphone','width=400,height=720');if(!w)$('tres').textContent='Allow pop-ups for this site to open the softphone window.'};
$('bDis').onclick=function(){if(ch)ch.postMessage({type:'disconnect'});badge('Disconnected','')};
$('bTest').onclick=function(){
 var res=$('tres'),w=$('wss').value.trim();
 if(!/^wss:\/\//i.test(w)){res.innerHTML='<span style="color:#B91C1C">Enter a WSS URL that starts with wss:// first.</span>';return}
 badge('Connecting','b-connecting');res.textContent='Testing… (up to 10 seconds)';
 var done=false,ws;function end(ok,msg){if(done)return;done=true;try{ws&&ws.close()}catch(e){}badge(ok?'Connected':'Registration Failed',ok?'b-connected':'b-failed');res.innerHTML='<span style="color:'+(ok?'#15803D':'#B91C1C')+'">'+msg+'</span>'}
 try{ws=new WebSocket(w,'sip')}catch(e){end(false,'Invalid WebSocket address.');return}
 var timer=setTimeout(function(){end(false,'No answer from the PBX within 10 seconds. The WSS port is probably closed or not a SIP WebSocket. Ask the PBX provider for the correct WSS address.')},10000);
 ws.onopen=function(){
  clearTimeout(timer);
  var pw=$('pwd').value;
  if(!pw){end(true,'The PBX accepts WebSocket connections on this address. Type the password above to also test registration, or press Save then Connect.');return}
  try{
   var ua=new JsSIP.UA({sockets:[new JsSIP.WebSocketInterface(w)],uri:'sip:'+$('usr').value+'@'+$('dom').value,password:pw,authorization_user:$('aus').value||$('usr').value,register:true});
   ua.on('registered',function(){end(true,'Registration succeeded. This account works with the browser softphone.');ua.stop()});
   ua.on('registrationFailed',function(e){end(false,'WebSocket is reachable but registration failed ('+((e&&e.cause)||'rejected')+'). Check username, auth username, password and domain.');ua.stop()});
   ua.start();setTimeout(function(){end(false,'Registration timed out.');try{ua.stop()}catch(x){}},10000)
  }catch(e){end(false,'Could not start registration: '+e.message)}
 };
 ws.onerror=function(){clearTimeout(timer);end(false,'Cannot open this WSS address. The PBX may not support WebRTC/WSS, the port may be blocked, or the certificate is not valid. Ask your PBX provider for a WSS endpoint.')};
};
})();
</script>
@endverbatim
@endsection
