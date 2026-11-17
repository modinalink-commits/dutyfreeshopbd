@extends('layout')@section('title','WhatsApp Calls')
@section('body')
@include('cc_ui')
@include('wa_rec_ui')
<style>
.cc-t tr.wc-on td{background:#FFF4ED!important}.cc-t tr.wc-on td:first-child{box-shadow:inset 3px 0 0 #F95505}.cc-t tr.wc-prow td{background:#FFFAF5;padding:10px 12px}.cc-t tr.wc-prow.wc-on td{background:#FFF4ED}
.wc-pinfo{display:flex;gap:6px 14px;flex-wrap:wrap;font-size:12.5px;color:#475467}.wc-pinfo b{color:#0B1B33}
.wc-f{display:flex;gap:8px;flex-wrap:wrap;padding:10px 12px;border-top:1px solid #F2F4F7}.wc-f input,.wc-f select{height:34px;border:1px solid #D0D5DD;border-radius:8px;padding:0 10px;font-size:13px}.wc-f input{flex:1;min-width:180px}
.wc-pg{display:flex;gap:10px;align-items:center;justify-content:flex-end;padding:10px 12px;border-top:1px solid #F2F4F7;font-size:13px;color:#475467}.wc-pg a{font-weight:700;color:#0B1B33;text-decoration:none;border:1px solid #D0D5DD;border-radius:8px;padding:5px 12px}
.wc-ring{border:1px solid #ABEFC6;background:#ECFDF3;border-radius:12px;padding:12px 14px;margin-bottom:10px;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.wc-ring b{font-size:15px;color:#0B1B33}.wc-ring small{color:#475467}
main button.wc-b{height:36px;padding:0 16px;border-radius:8px;border:1px solid #D0D5DD;background:#fff;color:#0B1B33;font-weight:700;font-size:13px;cursor:pointer}
main button.wc-b.go{background:#15803D;border-color:#15803D;color:#fff}main button.wc-b.no{background:#fff;border-color:#FECDCA;color:#B42318}main button.wc-b.pri{background:#F95505;border-color:#F95505;color:#fff}
.wc-live{border:1px solid #0F1F3D;background:#0F1F3D;color:#fff;border-radius:12px;padding:14px;margin-bottom:10px;display:none;gap:14px;align-items:center;flex-wrap:wrap}.wc-live.on{display:flex}
.wc-live b{font-size:16px}.wc-live span{font-variant-numeric:tabular-nums;opacity:.85}
.wc-n{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:10px 14px;border-top:1px solid #F2F4F7;font-size:13px}.wc-n:first-of-type{border-top:0}
#wcMsg{font-size:13px;margin:0 0 10px;color:#B42318;min-height:0}
</style>
<div class="cc-h"><h1>WhatsApp Calls</h1><span>Customers call from WhatsApp; answer here with your headset. This page checks for calls by itself.</span></div>
@if(!$ready)<div class="cc-box"><div class="cc-note">The call table is not installed yet. It is created by the next deploy.</div></div>@endif
<div class="cc-k"><div><span>Calls today</span><b>{{ $k['today'] }}</b></div><div class="ok"><span>Answered today</span><b>{{ $k['answered'] }}</b></div><div class="{{ $k['missed'] ? 'bad' : '' }}"><span>Missed today</span><b>{{ $k['missed'] }}</b></div><div class="{{ $k['open'] ? 'bad' : '' }}"><span>Waiting for callback</span><b>{{ $k['open'] }}</b></div></div>
<p id="wcMsg"></p>
<div class="wc-live" id="wcLive"><b id="wcWho">On call</b><span id="wcTime">00:00</span><span id="wcState"></span><button class="wc-b no" style="margin-left:auto" onclick="WC.hang()">Hang up</button><button class="wc-b" id="wcMute" onclick="WC.mute()">Mute</button></div>
<div id="wcRing"></div>
<div class="cc-box" id="wcEmpty"><div class="cc-note" style="border-top:0">No call is ringing right now. Keep this page (or any admin page) open: a banner appears when a customer calls.</div></div>
<audio id="wcAudio" autoplay></audio>

<div class="cc-box"><b class="t">Numbers</b>
@foreach($nums as $n)<div class="wc-n" data-pid="{{ $n['phone_id'] }}"><b>{{ $n['display'] ?: $n['name'] }}</b><small style="color:#475467">{{ $n['name'] }}</small><span class="cc-p st">@if($admin)Checking…@else — @endif</span>
@if($admin)<button class="wc-b pri on" style="display:none;margin-left:auto" onclick="WC.set('{{ $n['phone_id'] }}',1,this)">Turn calling on</button><button class="wc-b off" style="display:none;margin-left:auto" onclick="WC.set('{{ $n['phone_id'] }}',0,this)">Turn calling off</button>@endif
<small class="er" style="color:#B42318;flex-basis:100%"></small></div>@endforeach
@if(!count($nums))<div class="cc-note" style="border-top:0">No WhatsApp number is connected (Chat Settings).</div>@endif
<div class="cc-note">Only an API-only number can take calls here. A number that also runs in the WhatsApp Business app on a phone is refused by WhatsApp; its calls stay in that app.</div></div>

<div class="cc-box" id="wcHist"><b class="t">Call history</b>
<form method="get" class="wc-f"><input name="q" value="{{ $f['q'] }}" placeholder="Customer name, number or representative"><select name="res"><option value="">All results</option>@foreach(['ended'=>'Answered','missed'=>'Missed','rejected'=>'Rejected','recorded'=>'With recording'] as $k1=>$v1)<option value="{{ $k1 }}" @selected($f['res']===$k1)>{{ $v1 }}</option>@endforeach</select><select name="sort">@foreach(['new'=>'Newest first','old'=>'Oldest first','long'=>'Longest talk first'] as $k1=>$v1)<option value="{{ $k1 }}" @selected($f['sort']===$k1)>{{ $v1 }}</option>@endforeach</select><button class="wc-b">Apply</button>@if($f['q']!=='' || $f['res']!=='' || $f['sort']!=='new')<a href="/admin/call-center/whatsapp" class="wc-b" style="display:inline-flex;align-items:center;text-decoration:none">Clear</a>@endif</form>
<table class="cc-t"><thead><tr><th style="width:14%">Time</th><th style="width:21%">Caller</th><th style="width:11%">Result</th><th style="width:9%">Talk time</th><th style="width:13%">Answered by</th><th style="width:15%">Recording</th><th class="hm">Note</th></tr></thead><tbody>
@forelse($rows as $c)@php $lab=['ringing'=>['Ringing','warn'],'answering'=>['Answering','warn'],'active'=>['On call','ok'],'ended'=>['Answered','ok'],'missed'=>['Missed','bad'],'rejected'=>['Rejected','bad'],'failed'=>['Failed','bad']][$c->status] ?? [$c->status,'']; @endphp
<tr data-call="{{ $c->id }}"><td>{{ \Carbon\Carbon::parse($c->created_at)->timezone('Asia/Dhaka')->format('d M h:i A') }}</td><td><a href="/admin/call-center/whatsapp/call/{{ $c->id }}?embed=1" onclick="if(window.waDock)return window.waDock({{ $c->id }},false)" style="font-weight:700;color:#0B1B33">{{ $c->from_wa ? '+'.$c->from_wa : 'WhatsApp user' }}</a>@if($c->from_name)<br><small>{{ $c->from_name }}</small>@endif</td><td><span class="cc-p {{ $lab[1] }}">{{ $lab[0] }}</span></td><td>{{ $c->duration !== null ? gmdate('i:s', (int)$c->duration) : '—' }}</td><td>{{ $c->user_name ?: '—' }}</td><td>@php $rc = $recs[$c->id] ?? null; @endphp
@if($rc && $rc['play'])<button type="button" class="wc-b wc-play" data-rec="{{ $rc['id'] }}" data-call="{{ $c->id }}" data-dur="{{ (int)($rc['duration'] ?? $c->duration ?? 0) }}" data-who="{{ $c->from_name ?: 'WhatsApp caller' }}" data-num="{{ $c->from_wa ? '+'.$c->from_wa : 'Number hidden' }}" data-at="{{ \Carbon\Carbon::parse($c->created_at)->timezone('Asia/Dhaka')->format('d M Y h:i A') }}" data-by="{{ $c->user_name ?: '—' }}" style="height:30px;padding:0 12px;font-size:12px">Play</button>@if($rc['dl']) <button type="button" class="wc-b" style="height:30px;padding:0 10px;font-size:12px" onclick="RecDL.get('/admin/call-center/whatsapp/rec/{{ $rc['id'] }}/audio?dl=1&call={{ $c->id }}',{{ $c->id }},this)">Download</button>@endif @if($rc['status']==='incomplete')<br><small style="color:#B54708">Incomplete recording</small>@endif
@elseif($rc && $rc['status']==='recording')<small style="color:#B54708">Recording in progress…</small>
@elseif($rc && $rc['status']==='failed')<small style="color:#B42318">Recording failed</small>
@elseif($rc && $rc['nofile'])<small style="color:#B42318">Recording file not found</small>
@elseif($rc && $rc['status']==='deleted')<small style="color:#475467">Recording deleted</small>
@elseif($rc && $rc['status']==='expired')<small style="color:#475467">Recording expired</small>
@elseif($rc)<small style="color:#475467">No permission to listen</small>
@elseif($c->status==='ended')<small style="color:#475467">No recording</small>
@else<small style="color:#98A2B3">—</small>@endif</td><td class="hm">@if(!empty($c->outcome))<span class="cc-p ok">{{ \App\Support\WaCalls::OUTCOMES[$c->outcome] ?? $c->outcome }}</span> @endif<small>{{ $c->note }}</small>@if(!empty($c->followup_at))<div style="margin-top:4px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">@if(!empty($c->followup_done_at))<span class="cc-p ok">Follow-up done</span>@else<span class="cc-p {{ \Carbon\Carbon::parse($c->followup_at,'UTC')->isPast() ? 'bad' : 'warn' }}">Follow-up {{ \Carbon\Carbon::parse($c->followup_at,'UTC')->timezone('Asia/Dhaka')->format('d M h:i A') }}</span><button class="wc-b" style="height:26px;padding:0 10px;font-size:12px" onclick="var b=this;fetch('/admin/call-center/whatsapp/{{ $c->id }}/followup-done',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?document.querySelector('meta[name=csrf-token]').content:'','Accept':'application/json'}}).then(function(r){return r.json()}).then(function(d){if(d.ok)location.reload()})">Done</button>@endif</div>@endif@if($c->status==='missed' && $hasHandled)@php $lp=\App\Support\Chat::phone((string)$c->from_wa); @endphp<div style="margin-top:4px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">@if($c->handled_at)<span class="cc-p ok">Handled · {{ $c->handled_by }}</span>@else @if($lp && preg_match('/^01\d{9}$/', $lp))<a href="tel:{{ $lp }}" data-dfcall="{{ $lp }}" data-name="{{ $c->from_name }}" class="cc-p warn" style="text-decoration:none">Call back</a>@endif<button class="wc-b" style="height:26px;padding:0 10px;font-size:12px" onclick="WC.done({{ $c->id }},this)">Done</button>@endif</div>@endif</td></tr>
@empty<tr><td colspan="8" style="color:#475467">{{ ($f['q']!=='' || $f['res']!=='') ? 'No call matches this filter.' : 'No WhatsApp call yet.' }}</td></tr>@endforelse
</tbody></table>
@if($pg && $pg->lastPage() > 1)<div class="wc-pg">@if($pg->currentPage() > 1)<a href="{{ $pg->previousPageUrl() }}#wcHist">Previous</a>@endif<span>Page {{ $pg->currentPage() }} of {{ $pg->lastPage() }} · {{ number_format($pg->total()) }} calls</span>@if($pg->hasMorePages())<a href="{{ $pg->nextPageUrl() }}#wcHist">Next</a>@endif</div>@endif
</div>
<div class="cc-box"><b class="t">Representatives, last 7 days</b><table class="cc-t"><thead><tr><th>Representative</th><th>Calls answered</th><th>Total talk time</th><th>Average</th></tr></thead><tbody>
@forelse($rep as $r)<tr><td><b>{{ $r->user_name }}</b></td><td>{{ $r->n }}</td><td>{{ gmdate('H:i:s', (int)$r->secs) }}</td><td>{{ gmdate('i:s', (int)round($r->secs / max(1, $r->n))) }}</td></tr>
@empty<tr><td colspan="4" style="color:#475467">No answered WhatsApp call in the last 7 days.</td></tr>@endforelse
</tbody></table></div>
@endsection
@section('js')
<script>
/* Play a call's recording in its own row. The button carries the call id and that call's recording id; the server also answers with the call id and a mismatch is refused. */
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('.wc-play');if(!b)return;var tr=b.closest('tr'),id=b.dataset.call,row=document.querySelector('tr.wc-prow[data-for="'+id+'"]');
  document.querySelectorAll('tr.wc-prow').forEach(function(x){if(x!==row)x.hidden=true});
  if(!row||row.hidden)WaRecUI.pauseAll();
  if(!row){row=document.createElement('tr');row.className='wc-prow';row.dataset.for=id;function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML}
    row.innerHTML='<td colspan="'+tr.children.length+'"><div class="wc-pinfo"><span>Recording of <b>'+esc(b.dataset.who)+'</b> · '+esc(b.dataset.num)+'</span><span>Call: <b>'+esc(b.dataset.at)+'</b></span><span>Representative: <b>'+esc(b.dataset.by)+'</b></span><span>Length: <b>'+WaRecUI.mmss(+b.dataset.dur)+'</b></span><span>Call #'+esc(id)+'</span></div><div class="wr-pl"></div><div class="wr-e"></div></td>';
    tr.parentNode.insertBefore(row,tr.nextSibling);
    row.addEventListener('wrstate',function(ev){document.querySelectorAll('tr.wc-on').forEach(function(x){x.classList.remove('wc-on')});if(ev.detail){tr.classList.add('wc-on');row.classList.add('wc-on')}})}
  row.hidden=false;WaRecUI.player({id:+b.dataset.rec,call_id:+id,duration:+b.dataset.dur},row.querySelector('.wr-pl'),b)});
window.WC_PAGE=1;
var WC=(function(){
  var pc=null,stream=null,cur=null,t0=0,tick=null,busy=false;
  function tk(){var e=document.querySelector('meta[name=csrf-token]');return e?e.content:''}
  function post(u,b){return fetch(u,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':tk()},body:JSON.stringify(b||{})}).then(function(r){return r.json().catch(function(){return{ok:false,message:'Server error '+r.status}})})}
  function msg(m){document.getElementById('wcMsg').textContent=m||''}
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML}
  function draw(list){
    var h='';list.forEach(function(c){h+='<div class="wc-ring"><div><b>'+esc(c.name||c.from)+'</b><br><small>'+esc(c.from)+' · to '+esc(c.to||'')+' · ringing '+c.age+'s</small></div><button class="wc-b go" style="margin-left:auto" onclick="WC.open('+c.id+')">Answer</button><button class="wc-b no" onclick="WC.reject('+c.id+')">Reject</button></div>'});
    document.getElementById('wcRing').innerHTML=h;document.getElementById('wcEmpty').style.display=(list.length||cur)?'none':'';
  }
  function poll(){if(busy)return;fetch('/admin/call-center/whatsapp/poll?full=1',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(d){draw(d.ringing||[])}).catch(function(){})}
  function clean(){if(tick)clearInterval(tick);tick=null;try{if(pc)pc.close()}catch(e){}pc=null;if(stream)stream.getTracks().forEach(function(t){t.stop()});stream=null;cur=null;busy=false;document.getElementById('wcLive').classList.remove('on');poll()}
  function waitIce(p){return new Promise(function(res){if(p.iceGatheringState==='complete')return res();var done=false,f=function(){if(done)return;done=true;res()};p.addEventListener('icegatheringstatechange',function(){if(p.iceGatheringState==='complete')f()});setTimeout(f,2500)})}
  function answer(id,btn){
    if(busy)return;busy=true;msg('');if(btn){btn.disabled=true;btn.textContent='Connecting…'}
    if(!navigator.mediaDevices||!window.RTCPeerConnection){busy=false;msg('This browser cannot take calls. Use Chrome.');return}
    navigator.mediaDevices.getUserMedia({audio:true}).then(function(s){stream=s;return post('/admin/call-center/whatsapp/'+id+'/claim')}).then(function(d){
      if(!d.ok)throw new Error(d.message||'Could not take the call');
      pc=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});
      stream.getTracks().forEach(function(t){pc.addTrack(t,stream)});
      pc.ontrack=function(e){document.getElementById('wcAudio').srcObject=e.streams[0]};
      pc.onconnectionstatechange=function(){document.getElementById('wcState').textContent=pc?pc.connectionState:'';if(pc&&(pc.connectionState==='failed'||pc.connectionState==='closed'))hang()};
      return pc.setRemoteDescription({type:'offer',sdp:d.sdp}).then(function(){return pc.createAnswer()}).then(function(a){return pc.setLocalDescription(a)}).then(function(){return waitIce(pc)}).then(function(){return post('/admin/call-center/whatsapp/'+id+'/accept',{sdp:pc.localDescription.sdp})});
    }).then(function(d){
      if(!d.ok)throw new Error(d.message||'WhatsApp refused the call');
      cur=id;t0=Date.now();document.getElementById('wcLive').classList.add('on');document.getElementById('wcWho').textContent='On WhatsApp call';document.getElementById('wcRing').innerHTML='';document.getElementById('wcEmpty').style.display='none';
      tick=setInterval(function(){var s=Math.floor((Date.now()-t0)/1000);document.getElementById('wcTime').textContent=('0'+Math.floor(s/60)).slice(-2)+':'+('0'+s%60).slice(-2);
        if(s%3===0)fetch('/admin/call-center/whatsapp/poll?id='+id,{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(d){if(cur===id&&d.state&&d.state!=='active'&&d.state!=='answering'){clean();msg('The caller ended the call.')}}).catch(function(){})},1000);
    }).catch(function(e){var m=(e&&e.name==='NotAllowedError')?'Microphone is blocked. Allow the microphone for this site and try again.':(e&&e.message)||'Could not answer';post('/admin/call-center/whatsapp/'+id+'/end');clean();msg(m)});
  }
  function reject(id){post('/admin/call-center/whatsapp/'+id+'/end').then(poll)}
  function hang(){var id=cur;if(id)post('/admin/call-center/whatsapp/'+id+'/end');clean()}
  function mute(){if(!stream)return;var t=stream.getAudioTracks()[0];if(!t)return;t.enabled=!t.enabled;document.getElementById('wcMute').textContent=t.enabled?'Mute':'Unmute'}
  function st(){document.querySelectorAll('.wc-n[data-pid]').forEach(function(el){var p=el.getAttribute('data-pid');if(!el.querySelector('button'))return;
    fetch('/admin/call-center/whatsapp/status?phone_id='+encodeURIComponent(p),{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(d){
      var s=el.querySelector('.st'),on=el.querySelector('button.on'),off=el.querySelector('button.off'),er=el.querySelector('.er');
      if(d.display){el.querySelector('b').textContent=d.display}
      if(!d.ok){s.textContent='Cannot read';s.className='cc-p st bad';er.textContent=d.error||'';on.style.display='';off.style.display='none';return}
      var en=d.status==='ENABLED';s.textContent=en?'Calling ON':'Calling OFF';s.className='cc-p st '+(en?'ok':'');on.style.display=en?'none':'';off.style.display=en?'':'none';er.textContent=''}).catch(function(){})})}
  function set(p,on,btn){btn.disabled=true;post('/admin/call-center/whatsapp/calling',{phone_id:p,on:!!on}).then(function(d){btn.disabled=false;var er=btn.parentNode.querySelector('.er');er.textContent=d.ok?'':(d.message||'Failed');st()})}
  setInterval(poll,2500);poll();st();
  window.addEventListener('pagehide',function(){if(cur)fetch('/admin/call-center/whatsapp/'+cur+'/end',{method:'POST',credentials:'same-origin',keepalive:true,headers:{'X-CSRF-TOKEN':tk(),'Accept':'application/json'}})});
  function done(id,btn){btn.disabled=true;post('/admin/call-center/whatsapp/'+id+'/handled').then(function(d){if(d.ok)location.reload();else{btn.disabled=false;msg('Could not mark it. Reload the page.')}})}
  function open(id){if(window.waDock)window.waDock(id,true);else answer(id,null)}
  return{answer:answer,open:open,done:done,reject:reject,hang:hang,mute:mute,set:set};
})();
</script>
@endsection
