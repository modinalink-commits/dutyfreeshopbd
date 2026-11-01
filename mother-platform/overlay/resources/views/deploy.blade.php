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
  <div class="bar"><div style="flex:1;min-width:220px"><h1>Deployment Center</h1><div class="mu">New approved updates are downloaded, built, tested and switched live automatically — with zero downtime. Customers keep shopping the whole time.</div></div>
    <button class="b pri" id="bDeploy">Deploy Now</button><button class="b" id="bRefresh">Refresh Status</button><button class="b" id="bLogs">View Logs</button><button class="b" id="bCancel">Cancel Pending</button><button class="b" id="bRoll">Rollback</button></div>
  <div id="msg" class="mu"></div>
  <div class="g2"><div class="cd" id="cur"></div><div class="cd" id="act"></div></div>
  <div class="g2"><div class="cd" id="head"></div>
    <div class="cd"><h2>Automation settings</h2><div class="bar"><label class="mu">Mode</label><select id="sMode"><option value="auto">Automatic — deploy every new commit</option><option value="hold">Hold — wait for my Deploy Now</option></select><label class="mu">Production branch</label><input id="sBranch" style="width:110px"><button class="b" id="sSave">Save</button></div><div class="mu" style="margin-top:6px">Every release is built beside the live one, health-checked, switched atomically, re-checked, and rolled back automatically if anything fails. Commits marked [skip deploy] or starting with WIP are ignored.</div></div></div>
  <div class="cd" style="overflow-x:auto"><h2>Deployment history</h2><table><thead><tr><th>Version</th><th>Subject</th><th>Status</th><th>Started</th><th>Completed</th><th>Duration</th><th>Action</th></tr></thead><tbody id="hist"></tbody></table></div>
  <div class="ov" id="ov"><div class="md" id="md"></div></div>
</div>
<script type="application/json" id="dd">{!! json_encode($d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
<form style="display:none">@csrf</form>
@endsection
@section('js')
@verbatim
<script>
(function(){
var $=function(i){return document.getElementById(i)},D=JSON.parse($('dd').textContent),busy=false;
var C={'Queued':['#4B5563','#F3F4F6'],'Downloading':['#1D5FA8','#E6EFF9'],'Building':['#5B3FA6','#F1EAF8'],'Testing':['#C2410C','#FFF1E8'],'Deploying':['#1D5FA8','#E6EFF9'],'Live':['#166534','#DCFCE7'],'Completed':['#14532D','#BBF7D0'],'Failed':['#B91C1C','#FEE2E2'],'Rolled Back':['#B45309','#FEF3C7']};
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function pill(s){var c=C[s]||C.Queued;return '<span class="pl" style="color:'+c[0]+';background:'+c[1]+'">'+esc(String(s).toUpperCase())+'</span>'}
function csrf(){var i=document.querySelector('input[name=_token]');return i?i.value:''}
function post(u,b){return fetch(u,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify(b||{})}).then(function(r){return r.json().catch(function(){return{ok:false,msg:'Error '+r.status}})})}
function say(t){$('msg').textContent=t||''}
function notes(n){if(!n)return '<span class="mu">No notes</span>';return '<ul>'+String(n).split('\n').filter(Boolean).map(function(x){return '<li>'+esc(x)+'</li>'}).join('')+'</ul>'}
function render(){
  if(!D.enabled){$('cur').innerHTML='<b>Release layout is not active yet.</b>';return}
  var c=D.current;
  $('cur').innerHTML='<h2>Current release</h2>'+(c?'<div style="font-size:15px;font-weight:800">'+esc(c.subject||'Release')+'</div><div class="mu" style="margin:3px 0 6px">'+esc(c.version||'')+' · '+esc(c.release_id||'')+'</div>'+pill('Live')+' <span class="mu">Deployed '+esc(c.end||c.start)+'</span><div style="margin-top:6px">'+notes(c.notes)+'</div>':'<div style="font-size:15px;font-weight:800">Base release</div><div class="mu">'+esc(D.liveId)+'</div><div style="margin-top:6px">'+pill('Live')+'</div>');
  var a=D.active,S=['Downloading','Building','Testing','Deploying','Verifying'],L=['Downloading','Building','Testing','Deploying','Verification'];
  if(a){var ix=S.indexOf(a.stage);if(ix<0)ix=-1;
    $('act').innerHTML='<h2>Current activity</h2><div style="font-size:15px;font-weight:800">'+esc(a.subject)+'</div><div class="mu" style="margin:3px 0 6px">'+esc(a.version||'')+' · '+esc(a.source==='auto'?'automatic':'manual')+'</div>'+pill(a.status)+'<div class="st">'+S.map(function(s,i){return '<span class="'+(i<ix?'dn':(i===ix?'cu':''))+'">'+L[i]+(i<ix?' ✓':(i===ix?'…':''))+'</span>'}).join('')+'</div><div class="pb"><i style="width:'+a.progress+'%"></i></div><b>'+a.progress+'%</b> <span class="mu">complete</span>';
  }else{var f=D.history[0];$('act').innerHTML='<h2>Current activity</h2><div style="font-size:15px;font-weight:800">Idle</div><div class="mu" style="margin-top:3px">No deployment is running.'+(f&&(f.status==='Failed'||f.status==='Rolled Back')?'</div><div style="margin-top:6px">Last deployment: '+esc(f.subject)+' '+pill(f.status)+'<div class="mu" style="margin-top:3px">'+esc(f.error||'')+'</div>':'</div>')}
  var h=D.head,hs={live:'Up to date — this commit is live',pending:'New update detected — deploying automatically',waiting:'Update ready — press Deploy Now to release it',completed:'Deployed earlier',failed:'Last attempt failed — use Retry',queued:'Queued',unknown:'Could not read the branch'}[D.headState]||D.headState;
  $('head').innerHTML='<h2>Latest on branch “'+esc(D.branch)+'”</h2>'+(h?'<div style="font-size:14px;font-weight:800">'+esc(h.subject)+'</div><div class="mu" style="margin:3px 0">'+esc(h.sha.slice(0,7))+'</div><div>'+esc(hs)+'</div>':'<div class="mu">'+esc(hs)+'</div>');
  var sm=$('sMode');if(document.activeElement!==sm)sm.value=D.mode;if(document.activeElement!==$('sBranch'))$('sBranch').value=D.branch;
  $('hist').innerHTML=D.history.map(function(r){return '<tr><td><b>'+esc(r.version||'')+'</b>'+(r.live?' <span class="pl" style="color:#166534;background:#DCFCE7">LIVE</span>':'')+'</td><td>'+esc(r.subject||'')+'</td><td>'+pill(r.badge)+(r.status==='Failed'&&r.error?'<div class="mu">'+esc(r.error).slice(0,90)+'</div>':'')+'</td><td>'+esc(r.start)+'</td><td>'+esc(r.end)+'</td><td>'+esc(r.dur)+'</td><td style="white-space:nowrap"><button class="b" data-v="'+r.id+'">View</button>'+((r.status==='Failed'||r.status==='Rolled Back')&&r.sha?' <button class="b" data-r="'+r.id+'">Retry</button>':'')+'</td></tr>'}).join('')||'<tr><td colspan="7" class="mu">No deployments yet.</td></tr>';
  var act=!!a;$('bDeploy').disabled=act;$('bRoll').disabled=act;$('bCancel').disabled=!(a&&a.status==='Queued');
}
function load(u,m){if(busy)return;busy=true;return fetch(u,{method:m||'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf()}}).then(function(r){return r.json()}).then(function(j){D=j;render()}).catch(function(){}).then(function(){busy=false})}
function modal(h){$('md').innerHTML=h;$('ov').classList.add('on')}
$('ov').onclick=function(e){if(e.target===$('ov'))$('ov').classList.remove('on')};
$('bRefresh').onclick=function(){say('Checking…');load('/admin/deploy/refresh','POST').then(function(){say('Updated '+D.now)})};
$('bDeploy').onclick=function(){var h=D.head;if(!confirm('Deploy '+(h?'“'+h.subject+'” ('+h.sha.slice(0,7)+')':'the latest commit')+' now with zero downtime?'))return;post('/admin/deploy',{}).then(function(j){say(j.msg||'');load('/admin/deploy/status')})};
$('bRoll').onclick=function(){if(!confirm('Switch back to the previous release?'))return;post('/admin/deploy',{ref:'rollback'}).then(function(j){say(j.msg||'');load('/admin/deploy/status')})};
$('bCancel').onclick=function(){post('/admin/deploy/cancel').then(function(j){say(j.msg||'');load('/admin/deploy/status')})};
$('bLogs').onclick=function(){modal('<h2>Live deploy log</h2><pre>'+esc(D.log||'No log yet.')+'</pre>')};
$('sSave').onclick=function(){post('/admin/deploy/settings',{mode:$('sMode').value,branch:$('sBranch').value}).then(function(){say('Saved');load('/admin/deploy/refresh','POST')})};
$('hist').onclick=function(e){var t=e.target;if(t.dataset.r){post('/admin/deploy/'+t.dataset.r+'/retry').then(function(j){say(j.msg||'');load('/admin/deploy/status')})}
  if(t.dataset.v){var id=t.dataset.v,r=D.history.filter(function(x){return x.id==id})[0];fetch('/admin/deploy/'+id+'/log',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(x){return x.json()}).then(function(l){
    modal('<h2>'+esc(r.version)+' — '+esc(r.status)+'</h2><label class="mu">Subject</label><input id="eS" style="width:100%" value="'+esc(r.subject)+'"><label class="mu">Description</label><textarea id="eD" rows="2">'+esc(r.description||'')+'</textarea><label class="mu">Update notes (one per line)</label><textarea id="eN" rows="5">'+esc(r.notes||'')+'</textarea><div class="bar" style="margin:8px 0"><button class="b pri" id="eSave">Save</button><button class="b" id="eClose">Close</button><span class="mu">'+esc(r.sha?r.sha.slice(0,7):'')+' · '+esc(r.release_id||'')+'</span></div><h2>Log</h2><pre>'+esc(l.log)+'</pre>');
    $('eClose').onclick=function(){$('ov').classList.remove('on')};$('eSave').onclick=function(){post('/admin/deploy/'+id+'/edit',{subject:$('eS').value,description:$('eD').value,notes:$('eN').value}).then(function(){$('ov').classList.remove('on');load('/admin/deploy/status')})}})}};
render();
setInterval(function(){if(!document.hidden&&!$('ov').classList.contains('on'))load('/admin/deploy/status')},2500);
})();
</script>
@endverbatim
@endsection
