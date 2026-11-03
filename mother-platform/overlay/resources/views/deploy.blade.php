@extends('layout')@section('title','Deployment Center')
@section('body')
<style>
.dc{display:flex;flex-direction:column;gap:12px;font-size:13px}
.dc .cd{background:#fff;border:1px solid #E6EAF0;border-radius:12px;padding:12px 14px;min-width:0}
.dc h1{margin:0;font-size:17px}.dc h2{margin:0 0 8px;font-size:13px;font-weight:700}
.dc .mu{color:#6B7280;font-size:12px}
.dc .g2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.dc .bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.dc button.b{height:32px;padding:0 12px;border-radius:9px;border:1px solid #E6EAF0;background:#fff;font-weight:700;font-size:12px;cursor:pointer;width:auto;margin:0;color:#0B1220}
.dc button.b:hover{background:#FFF1E8;border-color:#FDC2A5}.dc button.pri{background:#FF5A00;color:#fff;border-color:#FF5A00}.dc button.pri:hover{background:#E65100}
.dc button.b[disabled]{opacity:.45;pointer-events:none}
.dc .pl{display:inline-block;font-size:10.5px;font-weight:800;border-radius:6px;padding:2px 8px;letter-spacing:.03em;white-space:nowrap}
.dc .pb{height:8px;background:#EEF1F5;border-radius:5px;overflow:hidden;margin:8px 0 4px}.dc .pb i{display:block;height:100%;background:#FF5A00;border-radius:5px;transition:width .6s}
.dc .st{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}.dc .st span{font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:999px;background:#F3F5F8;color:#9CA3AF}
.dc .st span.dn{background:#DCFCE7;color:#166534}.dc .st span.cu{background:#FFF1E8;color:#C2410C}
.dc table{width:100%;border-collapse:collapse;font-size:12px}.dc th{text-align:left;color:#6B7280;font-size:10.5px;padding:5px 8px}.dc td{padding:7px 8px;border-top:1px solid #F1F3F6;vertical-align:middle}
.dc input,.dc select,.dc textarea{height:32px;border:1px solid #E6EAF0;border-radius:9px;padding:0 9px;font-size:12px;width:auto;margin:0;background:#fff}.dc textarea{height:auto;padding:8px 9px;width:100%;font-family:inherit}
.dc pre{white-space:pre-wrap;font-size:11.5px;max-height:320px;overflow:auto;margin:6px 0 0;background:#0B1220;color:#DDE3EA;border-radius:9px;padding:10px}
.dc .ov{position:fixed;inset:0;background:rgba(11,18,32,.45);z-index:80;display:none;align-items:center;justify-content:center;padding:16px}.dc .ov.on{display:flex}
.dc .md{background:#fff;border-radius:14px;max-width:640px;width:100%;max-height:90vh;overflow:auto;padding:16px}
.dc ul{margin:4px 0 0 18px;padding:0}
@media (max-width:800px){.dc .g2{grid-template-columns:minmax(0,1fr)}.dc table{min-width:620px}}
</style>
<div class="dc" id="dc">
  <div class="bar"><div style="flex:1;min-width:200px"><h1>Deployment Center</h1><div class="mu">Approved updates go live automatically with zero downtime.</div></div>
    <button class="b pri" id="bDeploy">Deploy Now</button><button class="b" id="bRefresh">Refresh</button><button class="b" id="bCancel">Cancel Pending</button></div>
  <div id="msg" class="mu"></div>
  <div class="g2"><div class="cd" id="cur"></div><div class="cd" id="act"></div></div>
  <div class="cd" style="overflow-x:auto"><div class="bar" style="justify-content:space-between;margin-bottom:6px"><h2 style="margin:0">Work Log <span class="mu" id="wsum"></span></h2></div>
    <table><thead><tr><th style="width:36px">#</th><th>Task</th><th>Status</th><th style="width:150px">Progress</th><th>Updated</th></tr></thead><tbody id="wl"></tbody></table></div>
  <div class="cd" style="overflow-x:auto"><div class="bar" style="justify-content:space-between;margin-bottom:6px"><h2 style="margin:0">History</h2><div class="bar" id="flt"></div></div>
    <table><thead><tr><th>Version</th><th>Subject</th><th>Status</th><th>Duration</th><th>Deployed</th><th>Action</th></tr></thead><tbody id="hist"></tbody></table></div>
  <details class="cd"><summary style="cursor:pointer;font-weight:700;font-size:13px">Automation settings</summary><div class="bar" style="margin-top:8px"><label class="mu">Mode</label><select id="sMode"><option value="auto">Automatic — deploy every new commit</option><option value="hold">Hold — wait for my Deploy Now</option></select><label class="mu">Branch</label><input id="sBranch" style="width:110px"><label class="mu">GitHub token (optional, read-only)</label><input id="sGh" type="password" autocomplete="new-password" placeholder="leave blank to keep" style="width:170px"><button class="b" id="sSave">Save</button></div><div class="mu" id="head" style="margin-top:6px"></div></details>
  <div class="ov" id="ov"><div class="md" id="md"></div></div>
</div>
<script type="application/json" id="dd">{!! json_encode($d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
<form style="display:none">@csrf</form>
@endsection
@section('js')
@verbatim
<script>
(function(){
var $=function(i){return document.getElementById(i)},D=JSON.parse($('dd').textContent),busy=false,F='def',T0=Date.now()/1000-D.ts;
var C={'Queued':['#4B5563','#F3F4F6'],'Downloading':['#1D5FA8','#E6EFF9'],'Building':['#5B3FA6','#F1EAF8'],'Testing':['#C2410C','#FFF1E8'],'Deploying':['#1D5FA8','#E6EFF9'],'Live':['#166534','#DCFCE7'],'Completed':['#14532D','#BBF7D0'],'Rollback':['#B45309','#FEF3C7'],'Failed':['#B91C1C','#FEE2E2'],'Rolled Back':['#B45309','#FEF3C7']};
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function pill(s){var c=C[s]||C.Queued;return '<span class="pl" style="color:'+c[0]+';background:'+c[1]+'">'+esc(String(s).toUpperCase())+'</span>'}
function csrf(){var i=document.querySelector('input[name=_token]');return i?i.value:''}
function post(u,b){return fetch(u,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify(b||{})}).then(function(r){return r.json().catch(function(){return{ok:false,msg:'Error '+r.status}})})}
function say(t){$('msg').textContent=t||''}
function dur(n){if(n==null||n==='')return '—';n=+n;return n<90?n+' sec':Math.floor(n/60)+' min '+(n%60)+' sec'}
var FL=[['def','Default'],['all','All'],['live','Live'],['running','Running'],['completed','Completed'],['failed','Failed / Issues']];
function show(r){return F==='all'||(F==='def'?r.cat!=='failed':r.cat===F)}
function render(){
  if(!D.enabled){$('cur').innerHTML='<b>Release layout is not active yet.</b>';return}
  var c=D.current;
  $('cur').innerHTML='<h2>Current Live</h2>'+(c?'<div style="font-size:14px;font-weight:800">'+esc(c.version)+' — '+esc(c.subject||'Release')+'</div><div style="margin:5px 0">'+pill('Live')+'</div><div class="mu">Deployed '+esc(c.at)+' · '+dur(c.dur)+'</div><div class="bar" style="margin-top:8px"><button class="b" data-v="'+c.id+'">View</button><button class="b" id="bRoll">Rollback</button><button class="b" data-l="'+c.id+'">Logs</button></div>':'<div style="font-size:14px;font-weight:800">Base release</div><div class="mu">'+esc(D.liveId)+'</div><div style="margin:5px 0">'+pill('Live')+'</div><div class="bar"><button class="b" id="bRoll">Rollback</button></div>');
  var a=D.active,S=['Downloading','Building','Testing','Deploying','Verifying'],ix;
  if(a){ix=S.indexOf(a.stage);var el=Math.max(0,Math.round(Date.now()/1000-T0-(a.started_ts||0)));
    $('act').innerHTML='<h2>In progress</h2><div style="font-size:14px;font-weight:800">'+esc(a.subject)+'</div><div class="st">'+S.map(function(s,i){return '<span class="'+(i<ix?'dn':(i===ix?'cu':''))+'">'+s+(i<ix?' ✓':'')+'</span>'}).join('')+'</div><div class="pb"><i style="width:'+a.progress+'%"></i></div><b>'+a.progress+'%</b> <span class="mu">· '+esc(a.stage||a.status)+' · '+dur(el)+' elapsed'+(a.changed?' · '+a.changed+' files changed':'')+' · '+esc(a.status)+'</span>';
  }else{var f=D.history[0];$('act').innerHTML='<h2>In progress</h2><div class="mu">Idle — no deployment is running.</div>'+(f&&f.cat==='failed'?'<div class="mu" style="margin-top:6px">Last attempt failed: '+esc(f.subject)+' — see Failed / Issues.</div>':'')}
  var h=D.head,hs={live:'up to date',pending:'new update, deploying automatically',waiting:'ready — press Deploy Now',completed:'deployed earlier',failed:'last attempt failed',queued:'queued',unknown:'could not read the branch'}[D.headState]||D.headState;
  $('head').textContent='Latest on “'+D.branch+'”: '+(h?h.subject+' ('+h.sha.slice(0,7)+')':'unknown')+' — '+hs;
  var sm=$('sMode');if(document.activeElement!==sm)sm.value=D.mode;if(document.activeElement!==$('sBranch'))$('sBranch').value=D.branch;
  $('flt').innerHTML=FL.map(function(x){return '<button class="b'+(F===x[0]?' pri':'')+'" data-f="'+x[0]+'">'+x[1]+'</button>'}).join('');
  var rows=D.history.filter(show);
  $('hist').innerHTML=rows.map(function(r){return '<tr><td><b>'+esc(r.version||'—')+'</b></td><td>'+esc(r.subject||'')+(r.attempts.length>1?' <span class="mu">('+r.attempts.length+' attempts)</span>':'')+'</td><td>'+pill(r.badge)+'</td><td>'+dur(r.dur)+'</td><td>'+esc(r.short||'')+'</td><td style="white-space:nowrap"><button class="b" data-v="'+r.id+'">View</button>'+(r.retry?' <button class="b" data-r="'+r.retry+'">Retry</button>':'')+'</td></tr>'}).join('')||'<tr><td colspan="6" class="mu">Nothing to show for this filter.</td></tr>';
  var T=D.tasks||[],WC={done:['#14532D','#BBF7D0','DONE'],partial:['#B45309','#FEF3C7','PARTIAL'],pending:['#4B5563','#F3F4F6','PENDING'],blocked:['#B91C1C','#FEE2E2','BLOCKED']};
  if($('wl')){var dn=T.filter(function(x){return x.status==='done'}).length;$('wsum').textContent=T.length?'· '+dn+' of '+T.length+' complete':'';
   $('wl').innerHTML=T.map(function(x,i){var c=WC[x.status]||WC.pending;return '<tr><td><b>'+(i+1)+'</b></td><td>'+esc(x.title)+(x.note?'<div class="mu">'+esc(x.note)+'</div>':'')+'</td><td><span class="pl" style="color:'+c[0]+';background:'+c[1]+'">'+c[2]+'</span></td><td><div class="pb" style="margin:0 0 3px"><i style="width:'+x.percent+'%;background:'+(x.status==='done'?'#16A34A':'#FF5A00')+'"></i></div><b>'+x.percent+'%</b></td><td class="mu">'+esc(x.at)+'</td></tr>'}).join('')||'<tr><td colspan="5" class="mu">No tasks yet.</td></tr>'}
  var act=!!a;$('bDeploy').disabled=act;if($('bRoll'))$('bRoll').disabled=act;$('bCancel').disabled=!(a&&a.status==='Queued');
}
function load(u,m){if(busy)return;busy=true;return fetch(u,{method:m||'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf()}}).then(function(r){return r.json()}).then(function(j){D=j;T0=Date.now()/1000-D.ts;render()}).catch(function(){}).then(function(){busy=false})}
function modal(h){$('md').innerHTML=h;$('ov').classList.add('on')}
$('ov').onclick=function(e){if(e.target===$('ov'))$('ov').classList.remove('on')};
$('bRefresh').onclick=function(){say('Checking…');load('/admin/deploy/refresh','POST').then(function(){say('Updated '+D.now)})};
$('bDeploy').onclick=function(){var h=D.head;if(!confirm('Deploy '+(h?'“'+h.subject+'” ('+h.sha.slice(0,7)+')':'the latest commit')+' now with zero downtime?'))return;post('/admin/deploy',{}).then(function(j){say(j.msg||'');load('/admin/deploy/status')})};
$('bCancel').onclick=function(){post('/admin/deploy/cancel').then(function(j){say(j.msg||'');load('/admin/deploy/status')})};
$('sSave').onclick=function(){post('/admin/deploy/settings',{mode:$('sMode').value,branch:$('sBranch').value,gh_token:$('sGh').value}).then(function(){$('sGh').value='';say('Saved');load('/admin/deploy/refresh','POST')})};
function logOf(id,t){fetch('/admin/deploy/'+id+'/log',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(x){return x.json()}).then(function(l){modal('<h2>'+esc(t||'Log')+'</h2><pre>'+esc(l.log||'No log saved.')+'</pre><div class="bar" style="margin-top:8px"><button class="b" id="eClose">Close</button></div>');$('eClose').onclick=function(){$('ov').classList.remove('on')}})}
$('dc').onclick=function(e){var t=e.target;
  if(t.dataset.f){F=t.dataset.f;render()}
  if(t.id==='bRoll'){if(!confirm('Switch back to the previous release?'))return;post('/admin/deploy',{ref:'rollback'}).then(function(j){say(j.msg||'');load('/admin/deploy/status')})}
  if(t.dataset.l)logOf(t.dataset.l,'Logs');
  if(t.dataset.r){post('/admin/deploy/'+t.dataset.r+'/retry').then(function(j){say(j.msg||'');load('/admin/deploy/status')})}
  if(t.dataset.v){var id=t.dataset.v,r=D.history.filter(function(x){return x.id==id})[0];if(!r)return;
    var at=(r.attempts.length>1||r.cat==='failed')?'<label class="mu">Attempts</label><table>'+r.attempts.map(function(x){return '<tr><td>#'+x.n+'</td><td>'+pill(x.status)+'</td><td>'+dur(x.dur)+'</td><td>'+esc(x.at)+'</td><td><button class="b" data-l="'+x.id+'">Log</button></td></tr>'+(x.error?'<tr><td></td><td colspan="4" class="mu">'+esc(x.error)+'</td></tr>':'')}).join('')+'</table>':'';
    modal('<h2>'+esc(r.version||'')+' '+esc(r.subject)+'</h2><div class="mu">'+pill(r.badge)+' '+esc(r.at)+' · '+dur(r.dur)+(r.files?' · '+r.files+' files changed':'')+'</div>'+at+'<label class="mu">Subject</label><input id="eS" style="width:100%" value="'+esc(r.subject)+'"><label class="mu">Description</label><textarea id="eD" rows="2">'+esc(r.description||'')+'</textarea><label class="mu">Update notes (one per line)</label><textarea id="eN" rows="4">'+esc(r.notes||'')+'</textarea><div class="bar" style="margin:8px 0"><button class="b pri" id="eSave">Save</button><button class="b" id="eClose">Close</button></div>');
    $('eClose').onclick=function(){$('ov').classList.remove('on')};$('eSave').onclick=function(){post('/admin/deploy/'+id+'/edit',{subject:$('eS').value,description:$('eD').value,notes:$('eN').value}).then(function(){$('ov').classList.remove('on');load('/admin/deploy/status')})}}};
render();
setInterval(function(){if(!document.hidden&&!$('ov').classList.contains('on'))load('/admin/deploy/status')},2500);
})();
</script>
@endverbatim
@endsection
