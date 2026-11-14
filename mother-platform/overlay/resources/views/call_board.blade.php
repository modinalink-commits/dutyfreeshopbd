@extends('layout')@section('title','Call Board')
@section('body')
@include('cc_ui')
<div class="cc-h"><h1>Call Board</h1><span>Live, today. Refreshes by itself every 15 seconds.</span><span id="cbT" style="margin-left:auto"></span></div>
<div class="cc-k" id="cbK"></div>
<div class="cc-box" data-nogrid><table class="cc-t"><thead><tr><th style="width:20%">Staff</th><th style="width:26%">Now</th><th>Calls today</th><th>Answered</th><th>Confirmed</th><th class="hm">Avg talk</th></tr></thead><tbody id="cbA"><tr><td colspan="6" style="color:#475467">Loading…</td></tr></tbody></table></div>
<div class="cc-note" style="border:1px solid #E5E7EB;border-radius:12px;background:#fff;margin-bottom:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap"><b style="color:#0B1B33">Daily Call Report</b><span id="cdS">Checking…</span><a href="/admin/call-center/daily" target="_blank" style="font-weight:600;margin-left:auto">See today's report</a></div>
<div class="cc-two">
<div class="cc-box"><b class="t">Needs attention</b><div id="cbN" style="padding:0 14px 10px"></div></div>
<div class="cc-box"><b class="t">Call results today</b><div id="cbR" style="padding:0 14px 12px"></div></div>
</div>
@verbatim
<script>
(function(){
function esc(t){return String(t==null?'':t).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function dur(s){s=Math.max(0,Math.floor(s));var m=Math.floor(s/60);return (m>=60?Math.floor(m/60)+'h '+(m%60)+'m':m+'m '+(s%60)+'s')}
function ago(m){return m<60?m+' min':(m<1440?Math.floor(m/60)+' h '+(m%60)+' min':Math.floor(m/1440)+' d')}
var UPL={'new':['New order','warn','waiting to be confirmed'],callback:['Callback','info','asked for a call back'],noanswer:['No answer','warn','try again'],missed:['Missed call','bad','not called back']},OC={confirmed:'Order confirmed',pending:'Pending',call_later:'Call later',no_answer:'No answer',cancelled:'Order cancelled'};
var data=null;
function k(l,v,c,href){return (href?'<a href="'+href+'"':'<div')+' class="'+(c||'')+'"><span>'+l+'</span><b>'+v+'</b>'+(href?'</a>':'</div>')}
function draw(){var d=data;if(!d)return;var K=d.kpi||{};
 document.getElementById('cbK').innerHTML=k('On a call now',K.on_call||0,'ok')+k('Free',K.free||0)+k('Orders waiting for a call',K.new_orders||0,K.new_orders?'bad':'')+k('Missed, not called back',K.missed||0,K.missed?'bad':'','/admin/call-center/history?f=noback')+k('Callbacks due',K.callbacks||0)+k('Calls today',K.calls||0)+k('Confirmed by call today',K.confirmed||0,'ok');
 var now=Date.now();document.getElementById('cbA').innerHTML=(d.agents||[]).length?d.agents.map(function(a){var s=a.since?Math.floor((now-new Date(a.since).getTime())/1000):null;
  var chip=a.state==='call'?'<span class="cc-p ok">On call</span>':(a.state==='off'?'<span class="cc-p bad">Offline</span>':'<span class="cc-p">Free</span>');
  var det=a.state==='call'?esc(a.detail)+(s!==null?' · '+dur(s):''):(a.state==='off'?(s!==null?'for '+dur(s):''):(s!==null&&a.calls?'since last call '+dur(s):esc(a.detail)));
  return '<tr><td><span class="cc-who"><span class="cc-av'+(a.state==='off'?'':(a.state==='call'?' busy':' on'))+'">'+esc(String(a.name||'?').trim().charAt(0).toUpperCase())+'</span><span><b>'+esc(a.name)+'</b><small>Ext '+esc(a.ext)+'</small></span></span></td><td>'+chip+' <span style="color:#475467">'+det+'</span></td><td class="cc-num">'+a.calls+'</td><td class="cc-num">'+a.answered+'</td><td><b style="color:#067647">'+a.confirmed+'</b></td><td class="hm">'+(a.avg?dur(a.avg):'—')+'</td></tr>'}).join(''):'<tr><td colspan="6" style="color:#475467">No staff member has an extension yet.</td></tr>';
 document.getElementById('cbN').innerHTML=(d.attention||[]).length?d.attention.map(function(x){var L=UPL[x.type]||['Call','',''];return '<div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-top:1px solid #F2F4F7"><span class="cc-p '+L[1]+'">'+L[0]+'</span><span style="flex:1;min-width:0;overflow-wrap:anywhere"><b>'+esc(x.name||x.phone)+'</b> · '+ago(x.mins)+', '+L[2]+(x.invoice?' · #'+esc(x.invoice):'')+'</span><button type="button" class="cc-call" data-n="'+esc(x.phone)+'" title="Call" aria-label="Call"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg></button></div>'}).join(''):'<div style="padding:8px 0;color:#475467">Nothing is waiting right now.</div>';
 var R=d.results||{},tot=0;Object.keys(R).forEach(function(x){tot+=+R[x]});
 document.getElementById('cbR').innerHTML=tot?Object.keys(OC).map(function(x){var n=+(R[x]||0);return '<div style="display:flex;align-items:center;gap:10px;padding:5px 0"><span style="width:120px;color:#475467">'+OC[x]+'</span><div class="cc-bar" style="flex:1"><i class="'+(x==='confirmed'?'':'w')+'" style="width:'+Math.round(n*100/tot)+'%"></i></div><b style="width:30px;text-align:right">'+n+'</b></div>'}).join(''):'<div style="padding:8px 0;color:#475467">No call has a saved result yet today.</div>';
 document.getElementById('cbT').textContent=d.error||('Updated '+new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',second:'2-digit'}))}
function load(){if(document.hidden)return;fetch('/admin/call-center/board/data',{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){data=j;draw()}).catch(function(){document.getElementById('cbT').textContent='Could not refresh. Trying again.'})}
document.addEventListener('click',function(e){var b=e.target.closest('#cbN .cc-call');if(b&&window.dfCall)dfCall(b.dataset.n)});
fetch('/admin/call-center/daily/status',{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){document.getElementById('cdS').textContent=(j.on?'Sent on WhatsApp every day at '+j.send_at+' to '+(j.to||[]).join(', '):'Switched off')+' · WhatsApp template: '+String(j.tpl||'').toLowerCase().replace(/_/g,' ')}).catch(function(){});
load();setInterval(load,15000);setInterval(draw,1000);document.addEventListener('visibilitychange',function(){if(!document.hidden)load()});
})();
</script>
@endverbatim
@endsection
