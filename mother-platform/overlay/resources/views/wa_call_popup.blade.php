@extends('layout')@section('title','WhatsApp Call')
@section('body')
@include('wa_rec_ui')
<style>
.wp{max-width:460px;margin:0 auto;font-size:13px;color:#0B1B33}
.wp-top{border:1px solid #E5E7EB;border-radius:14px;background:#fff;overflow:hidden;margin-bottom:10px}
.wp-bar{height:4px;background:#25D366}
.wp-id{display:flex;gap:12px;align-items:center;padding:14px}
.wp-av{width:46px;height:46px;border-radius:50%;background:#ECFDF3;color:#067647;font-weight:700;font-size:18px;display:flex;align-items:center;justify-content:center;flex:none}
.wp-id b{display:block;font-size:16px;line-height:1.25}.wp-id small{color:#475467}
.wp-meta{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:#F2F4F7;border-top:1px solid #F2F4F7}
.wp-meta div{background:#fff;padding:8px 14px}.wp-meta span{display:block;font-size:11px;color:#475467;text-transform:uppercase;letter-spacing:.04em}.wp-meta b{font-size:13px}
.wp-act{display:flex;gap:8px;padding:12px 14px;border-top:1px solid #F2F4F7}
main button.wp-b,a.wp-b{flex:1;height:42px;border-radius:10px;border:1px solid #D0D5DD;background:#fff;color:#0B1B33;font-weight:700;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}
main button.wp-b.go{background:#15803D;border-color:#15803D;color:#fff}main button.wp-b.no{background:#D92D20;border-color:#D92D20;color:#fff}main button.wp-b[disabled]{opacity:.55;cursor:default}
.wp-pill{display:inline-block;font-size:11px;font-weight:700;border-radius:6px;padding:2px 7px;background:#F2F4F7;color:#344054}.wp-pill.ok{background:#ECFDF3;color:#067647}.wp-pill.bad{background:#FEF3F2;color:#B42318}.wp-pill.warn{background:#FFFAEB;color:#B54708}
.wp-box{border:1px solid #E5E7EB;border-radius:14px;background:#fff;margin-bottom:10px;overflow:hidden}.wp-box>b.t{display:block;padding:10px 14px 4px;font-size:13px}
.wp-row{display:flex;justify-content:space-between;gap:10px;padding:6px 14px;border-top:1px solid #F9FAFB}.wp-row span{color:#475467}.wp-row b{text-align:right}
.wp-al{margin:6px 14px;padding:7px 10px;border-radius:8px;background:#FFFAEB;color:#93370D;font-size:12.5px}
.wp-links{display:flex;gap:8px;padding:10px 14px;flex-wrap:wrap}.wp-links a.wp-b{height:36px;font-size:13px;flex:1 1 30%}
.wp-links a.wp-b.pri{background:#F95505;border-color:#F95505;color:#fff}
.wp-note{padding:8px 14px 12px}.wp-note textarea{width:100%;min-height:54px;border:1px solid #D0D5DD;border-radius:8px;padding:8px;font:inherit;resize:vertical}
#wpMsg{color:#B42318;font-size:12.5px;padding:0 14px 10px;min-height:0}
</style>
<div class="wp">
<div class="wp-top"><div class="wp-bar"></div>
<div class="wp-id"><div class="wp-av">{{ mb_strtoupper(mb_substr($c->from_name ?: 'W', 0, 1)) }}</div><div style="min-width:0"><b id="wpName">{{ $c->from_name ?: 'WhatsApp caller' }}</b><small>{{ $c->from_wa ? '+'.$c->from_wa : 'Number hidden' }} · WhatsApp call</small></div><span class="wp-pill" id="wpSt" style="margin-left:auto">—</span></div>
<div class="wp-meta"><div><span>Called number</span><b>{{ $to }}</b></div><div><span>Answered by</span><b id="wpBy">{{ $c->user_name ?: '—' }}</b></div><div><span>Started</span><b>{{ \Carbon\Carbon::parse($c->created_at)->timezone('Asia/Dhaka')->format('d M h:i A') }}</b></div><div><span>Talk time</span><b id="wpT">{{ $c->duration !== null ? gmdate('i:s', (int)$c->duration) : '00:00' }}</b></div></div>
<div class="wp-act" id="wpAct"></div><div id="wpRec" hidden style="margin:0 14px 10px;padding:8px 10px;border-radius:8px;background:#FEF3F2;color:#912018;font-size:12.5px"></div><div id="wpMsg"></div></div>
<audio id="wpAudio" autoplay></audio>

<div class="wp-box"><b class="t">Customer</b><div id="wpCust"><div class="wp-row"><span>Checking this number…</span></div></div>
<div class="wp-links" id="wpLinks"></div></div>

<div class="wp-box"><b class="t">Calls &amp; recordings of this caller</b><div id="wpRecs"><div class="wr-i" style="color:#475467;padding:8px 14px;font-size:12.5px">Loading…</div></div></div>

<div class="wp-box"><b class="t">Call note</b><div class="wp-note"><textarea id="wpNote" maxlength="240" placeholder="What the customer wanted, what was promised…">{{ preg_replace('/ — [^—]*$/u', '', (string)$c->note) }}</textarea>
<div style="display:flex;gap:8px;align-items:center;margin-top:6px"><button class="wp-b" style="flex:none;height:34px;padding:0 14px;font-size:13px" onclick="WP.note(this)">Save note</button><small id="wpNoteS" style="color:#475467"></small></div></div></div>
</div>
@endsection
@section('js')
<script>
window.WC_PAGE=1;
var WP=(function(){
  var ID={{ (int)$c->id }},state=@json($c->status),LOCAL=@json($local),pc=null,stream=null,remote=null,t0=0,tick=null,busy=false,mine=false;
  var REC={on:@json(\App\Support\WaRec::on()),mr:null,ac:null,seq:0,q:Promise.resolve(),err:null,started:false,done:false};
  function $(i){return document.getElementById(i)}
  function tk(){var e=document.querySelector('meta[name=csrf-token]');return e?e.content:''}
  function post(u,b){return fetch(u,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':tk()},body:JSON.stringify(b||{})}).then(function(r){return r.json().catch(function(){return{ok:false,message:'Server error '+r.status}})})}
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML}
  function msg(m){$('wpMsg').textContent=m||''}
  var LAB={ringing:['Ringing','warn'],answering:['Connecting','warn'],active:['On call','ok'],ended:['Ended','ok'],missed:['Missed','bad'],rejected:['Rejected','bad'],failed:['Failed','bad']};
  function tell(a){try{if(window.parent&&window.parent!==window)window.parent.postMessage({wadock:a},location.origin)}catch(e){}}
  function draw(){tell(mine&&(state==='active'||state==='answering')?'busy':'idle');var l=LAB[state]||[state,''];$('wpSt').textContent=l[0];$('wpSt').className='wp-pill '+l[1];var h='';
    if(state==='ringing')h='<button class="wp-b go" onclick="WP.answer(this)">Answer</button><button class="wp-b no" onclick="WP.reject()">Reject</button>';
    else if(mine&&(state==='active'||state==='answering'))h='<button class="wp-b" id="wpMute" onclick="WP.mute()">Mute</button><button class="wp-b no" onclick="WP.hang()">Hang up</button>';
    else if(state==='active'||state==='answering')h='<button class="wp-b" disabled>Another representative is on this call</button>';
    else h='<button class="wp-b" onclick="WP.close()">Close</button>';
    $('wpAct').innerHTML=h}
  // ---- recording (never allowed to disturb the call: every step is wrapped, a failure only marks the recording) ----
  function recBadge(t,ok){var e=$('wpRec');if(!t){e.hidden=true;return}e.hidden=false;e.style.background=ok?'#FEF3F2':'#FFFAEB';e.style.color=ok?'#912018':'#93370D';e.innerHTML=t}
  function recFail(why){if(REC.err)return;REC.err=String(why||'Recording error').slice(0,190);try{if(REC.mr&&REC.mr.state!=='inactive')REC.mr.stop()}catch(e){}recBadge('Recording failed: '+esc(REC.err)+'. The call is not affected.',false);if(!REC.mr)recFinish()}
  function recSend(blob){var n=REC.seq++;REC.q=REC.q.then(function(){if(REC.err)return;var tries=0;
    function go(){return fetch('/admin/call-center/whatsapp/'+ID+'/rec/chunk?seq='+n,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/octet-stream','Accept':'application/json','X-CSRF-TOKEN':tk()},body:blob}).then(function(r){return r.json()}).then(function(d){if(!d.ok)throw new Error('!'+(d.message||'Server refused the audio'))}).catch(function(e){if(String(e.message).charAt(0)==='!'||++tries>=4)throw e;return new Promise(function(res){setTimeout(res,1500*tries)}).then(go)})}
    return go().catch(function(e){recFail(String(e.message||'Upload failed').replace(/^!/,''))})})}
  function recFinish(){if(REC.done)return;REC.done=true;REC.q.then(function(){return post('/admin/call-center/whatsapp/'+ID+'/rec/finish',{duration:t0?Math.floor((Date.now()-t0)/1000):null,error:REC.err})}).then(function(d){if(!REC.err)recBadge(d&&d.status==='complete'?'Recording saved.':'Recording '+esc((d&&d.status)||'not confirmed')+'.',d&&d.status==='complete');try{if(REC.ac)REC.ac.close()}catch(e){}recs()}).catch(function(){})}
  function recStart(){
    if(!REC.on||REC.started||state!=='active'||!remote||!stream)return;REC.started=true;
    try{var AC=window.AudioContext||window.webkitAudioContext,mime=window.MediaRecorder?['audio/webm;codecs=opus','audio/webm'].filter(function(m){return MediaRecorder.isTypeSupported(m)})[0]:null;
      if(!AC||!mime)throw new Error('This browser cannot record calls (use Chrome)');
      REC.ac=new AC();var dest=REC.ac.createMediaStreamDestination();REC.ac.createMediaStreamSource(stream).connect(dest);REC.ac.createMediaStreamSource(remote).connect(dest);if(REC.ac.state!=='running')REC.ac.resume();
      post('/admin/call-center/whatsapp/'+ID+'/rec/start',{mime:mime}).then(function(d){
        if(!d.ok){if(/switched off/.test(d.message||'')){REC.done=true;return}throw new Error(d.message||'Recording could not start')}
        if(state!=='active'){REC.err='Call ended before recording started';recFinish();return}
        REC.mr=new MediaRecorder(dest.stream,{mimeType:mime,audioBitsPerSecond:32000});
        REC.mr.ondataavailable=function(e){if(e.data&&e.data.size)recSend(e.data)};REC.mr.onerror=function(){recFail('Recorder error')};REC.mr.onstop=recFinish;REC.mr.start(4000);
        recBadge('<b>● Recording.</b> Tell the customer: «'+esc(d.notice||'')+'» <button type="button" id="wpNt" style="margin-left:6px;height:24px;border:1px solid #FDA29B;border-radius:6px;background:#fff;color:#912018;font-weight:700;font-size:12px;cursor:pointer">I told the customer</button>',true);
        var nb=$('wpNt');if(nb)nb.onclick=function(){nb.disabled=true;post('/admin/call-center/whatsapp/'+ID+'/rec/notice').then(function(){nb.outerHTML='<span style="font-weight:700">· Notice given ✓</span>'})};
        setTimeout(function(){try{if(REC.ac&&REC.ac.state!=='running'&&!REC.done)REC.ac.resume().catch(function(){recFail('Audio engine is blocked by the browser')})}catch(e){}},3000);
      }).catch(function(e){recFail(e.message)});
    }catch(e){recFail(e.message)}
  }
  function recStop(){try{if(REC.mr&&REC.mr.state!=='inactive')REC.mr.stop();else if(REC.started&&!REC.done&&!REC.mr){REC.err=REC.err||'Call ended before recording started';recFinish()}}catch(e){recFinish()}}
  function recs(){var h=$('wpRecs');if(h&&window.WaRecUI)WaRecUI.load(h,'call='+ID,{current:ID,empty:'No recording of this caller yet.'})}
  function stop(){recStop();if(tick)clearInterval(tick);tick=null;try{if(pc)pc.close()}catch(e){}pc=null;if(stream)stream.getTracks().forEach(function(t){t.stop()});stream=null;busy=false;mine=false}
  function waitIce(p){return new Promise(function(res){if(p.iceGatheringState==='complete')return res();var d=false,f=function(){if(d)return;d=true;res()};p.addEventListener('icegatheringstatechange',function(){if(p.iceGatheringState==='complete')f()});setTimeout(f,2500)})}
  function answer(btn){
    if(busy)return;busy=true;msg('');if(btn){btn.disabled=true;btn.textContent='Connecting…'}var sent=false;
    if(!navigator.mediaDevices||!window.RTCPeerConnection){busy=false;msg('This browser cannot take calls. Use Chrome.');draw();return}
    navigator.mediaDevices.getUserMedia({audio:true}).then(function(s){stream=s;return post('/admin/call-center/whatsapp/'+ID+'/claim')}).then(function(d){
      if(!d.ok)throw new Error(d.message||'Could not take the call');mine=true;state='answering';draw();
      pc=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});
      stream.getTracks().forEach(function(t){pc.addTrack(t,stream)});
      pc.ontrack=function(e){$('wpAudio').srcObject=e.streams[0];remote=e.streams[0];recStart()};
      pc.onconnectionstatechange=function(){if(!pc)return;if(pc.connectionState==='disconnected')msg('Connection is weak. Trying to reconnect…');if(pc.connectionState==='connected')msg('');if(pc.connectionState==='failed'){msg('The connection was lost.');hang()}};
      return pc.setRemoteDescription({type:'offer',sdp:d.sdp}).then(function(){return pc.createAnswer()}).then(function(a){return pc.setLocalDescription(a)}).then(function(){return waitIce(pc)}).then(function(){sent=true;return post('/admin/call-center/whatsapp/'+ID+'/accept',{sdp:pc.localDescription.sdp})});
    }).then(function(d){
      if(!d.ok)throw new Error(d.message||'WhatsApp refused the call');
      state='active';t0=Date.now();draw();recStart();
      tick=setInterval(function(){var s=Math.floor((Date.now()-t0)/1000);$('wpT').textContent=('0'+Math.floor(s/60)).slice(-2)+':'+('0'+s%60).slice(-2)},1000);
    }).catch(function(e){var m=(e&&e.name==='NotAllowedError')?'Microphone is blocked. Allow the microphone for this site and press Answer again.':(e&&e.message)||'Could not answer';
      var wasMine=mine;stop();
      // nothing was sent to WhatsApp yet: give the call back so it keeps ringing for everyone
      if(wasMine&&!sent)post('/admin/call-center/whatsapp/'+ID+'/release').then(function(){state='ringing';draw();msg(m)});
      else{if(wasMine)post('/admin/call-center/whatsapp/'+ID+'/end');msg(m);sync()}});
  }
  function reject(){post('/admin/call-center/whatsapp/'+ID+'/end').then(sync)}
  function hang(){post('/admin/call-center/whatsapp/'+ID+'/end').then(sync);stop();state='ended';draw()}
  function mute(){if(!stream)return;var t=stream.getAudioTracks()[0];if(!t)return;t.enabled=!t.enabled;$('wpMute').textContent=t.enabled?'Mute':'Unmute'}
  function sync(){fetch('/admin/call-center/whatsapp/poll?id='+ID,{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(d){
    if(d.by)$('wpBy').textContent=d.by;if(!d.state||d.state===state)return;
    if(mine&&(state==='active'||state==='answering')&&d.state!=='active'&&d.state!=='answering'){stop();msg('The caller ended the call.')}
    if(!busy||d.state!=='ringing'){state=d.state;draw()}}).catch(function(){})}
  function cust(){
    if(!LOCAL){$('wpCust').innerHTML='<div class="wp-row"><span>WhatsApp did not share a phone number for this caller.</span></div>';links(null);return}
    fetch('/admin/call-center/lookup?phone='+encodeURIComponent(LOCAL),{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(c){
      var h='';
      if(!c.found){h='<div class="wp-row"><span>No customer or order found for '+esc(LOCAL)+'. Treat as a new caller.</span></div>'}
      else{if(c.name){$('wpName').textContent=c.name}
        (c.alerts||[]).forEach(function(a){h+='<div class="wp-al">'+esc(a)+'</div>'});
        h+='<div class="wp-row"><span>Customer type</span><b>'+esc(c.category||'—')+(c.membership?' · membership '+esc(typeof c.membership==='string'?c.membership:(c.membership.name||c.membership.tier||'')):'')+'</b></div>';
        h+='<div class="wp-row"><span>Orders</span><b>'+(c.orders||0)+' total · '+(c.delivered||0)+' delivered</b></div>';
        if(c.last)h+='<div class="wp-row"><span>Last order</span><b>'+esc(c.last.invoice||('#'+c.last.id))+' · '+esc(c.last.status||'')+' · ৳'+Math.round(c.last.total||0)+'</b></div>';
        if(c.fraud)h+='<div class="wp-row"><span>Courier record</span><b>'+esc(c.fraud.label||'')+'</b></div>';
        h+='<div class="wp-row"><span>Earlier phone calls</span><b>'+(c.calls||0)+'</b></div>'}
      $('wpCust').innerHTML=h;links(c);extras()}).catch(function(){$('wpCust').innerHTML='<div class="wp-row"><span>Customer details could not be loaded.</span></div>';links(null)})}
  function extras(){fetch('/admin/call-center/whatsapp/'+ID+'/extras',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(x){var h='';
      if(x.returns||x.cancelled)h+='<div class="wp-row"><span>Returned / cancelled</span><b>'+(x.returns||0)+' / '+(x.cancelled||0)+'</b></div>';
      if(x.address)h+='<div class="wp-row"><span>Last address</span><b>'+esc(x.address)+'</b></div>';
      if(x.owner)h+='<div class="wp-row"><span>Chat is handled by</span><b>'+esc(x.owner)+'</b></div>';
      (x.notes||[]).forEach(function(n,i){h+='<div class="wp-row" style="display:block"><span>'+(i?'':'Earlier notes<br>')+esc(n.at)+' · '+esc(n.by)+'</span><div style="margin-top:2px">'+esc(n.body)+'</div></div>'});
      if(h)$('wpCust').insertAdjacentHTML('beforeend',h)}).catch(function(){})}
  function links(c){var h='<a class="wp-b pri" target="_blank" href="/admin/orders/new'+(LOCAL&&/^01\d{9}$/.test(LOCAL)?'?phone='+LOCAL:'')+'">New order</a>';
    if(c&&c.last)h+='<a class="wp-b" target="_blank" href="/admin/orders/'+c.last.id+'">Last order</a>';
    if(c&&c.thread_id)h+='<a class="wp-b" target="_blank" href="/admin/chat?open='+c.thread_id+'">Chat</a>';
    $('wpLinks').innerHTML=h}
  function note(btn){var v=$('wpNote').value.trim();if(!v)return;btn.disabled=true;post('/admin/call-center/whatsapp/'+ID+'/note',{note:v}).then(function(d){btn.disabled=false;$('wpNoteS').textContent=d.ok?'Saved':'Not saved'})}
  draw();cust();recs();setInterval(sync,3000);
  window.addEventListener('pagehide',function(){if(mine&&(state==='active'||state==='answering'))fetch('/admin/call-center/whatsapp/'+ID+'/end',{method:'POST',credentials:'same-origin',keepalive:true,headers:{'X-CSRF-TOKEN':tk(),'Accept':'application/json'}})});
  if(state==='ringing'&&/[?&]go=1/.test(location.search))setTimeout(function(){answer(document.querySelector('.wp-b.go'))},200);
  function close(){if(window.parent&&window.parent!==window)tell('close');else window.close()}
  return{answer:answer,reject:reject,hang:hang,mute:mute,note:note,close:close};
})();
</script>
@endsection
