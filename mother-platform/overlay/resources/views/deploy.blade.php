@extends('layout')@section('title','Deployment Center')
@section('body')
<style>
.dc{--bd:#CED4DA;--bg2:#BFC6CE;--n:#0B1220;--mu:#6B7280;display:flex;flex-direction:column;gap:10px;font-size:12.5px;color:var(--n)}
.dc .cd{background:#fff;border:1px solid var(--bd);border-radius:10px;padding:10px 12px;min-width:0}
.dc .cd:hover{border-color:var(--bg2)}
.dc h1{margin:0;font-size:clamp(18px,1.5vw,22px)}.dc h2{margin:0 0 6px;font-size:12.5px;font-weight:700}
.dc .mu{color:var(--mu);font-size:11.5px}
.dc .bar{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.dc .g2{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,7fr);gap:10px}
.dc .g5{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
.dc .sb{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));border:1px solid var(--bg2);border-radius:10px;overflow:hidden;background:#fff}
.dc .sc{padding:7px 10px;border-right:1px solid var(--bd);min-width:0}.dc .sc:last-child{border-right:0}
.dc .sc small{display:block;font-size:10.5px;color:var(--mu);font-weight:600}
.dc .sc b{display:flex;align-items:center;gap:5px;font-size:12px;letter-spacing:.02em;white-space:nowrap}
.dc .sc em{display:block;font-style:normal;font-size:10px;color:var(--mu);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dc .dt{width:8px;height:8px;border-radius:50%;flex:0 0 auto;background:#9CA3AF}.dc .dt.ok{background:#16A34A}.dc .dt.warn{background:#D97706}.dc .dt.fail{background:#B91C1C}.dc .dt.run{background:#1D5FA8}
.dc button.b{height:30px;padding:0 11px;border-radius:8px;border:1px solid var(--bd);background:#fff;font-weight:700;font-size:12px;cursor:pointer;width:auto;margin:0;color:var(--n);transition:border-color .15s,background-color .15s}
.dc button.b:hover{border-color:#9CA3AF;background:#F8FAFC}
.dc button.b.pri{background:#FF5A00;border-color:#FF5A00;color:#fff}.dc button.b.pri:hover{background:#E65100}
.dc button.b.fu{background:#FFF3EA;border-color:#FDBA8C;color:#B45309}.dc button.b.ro{background:#F1EAF8;border-color:#D4CCF5;color:#5B3FA6}
.dc button.b.hc{background:#F0FBF4;border-color:#BFE6CE;color:#166534}.dc button.b.sm{height:26px;padding:0 9px;font-size:11.5px;font-weight:600}
.dc button.b[disabled]{opacity:.45;pointer-events:none}
.dc .pl{display:inline-block;font-size:10px;font-weight:800;border-radius:6px;padding:2px 7px;letter-spacing:.03em;white-space:nowrap;border:1px solid transparent}
.dc .pb{height:8px;background:#EEF1F5;border-radius:5px;overflow:hidden;margin:6px 0 3px;border:1px solid #E2E6EB}.dc .pb i{display:block;height:100%;background:#FF5A00;transition:width .6s}
.dc table{width:100%;border-collapse:collapse;font-size:12px}.dc th{text-align:left;color:var(--mu);font-size:10.5px;padding:5px 8px;border-bottom:1px solid var(--bd);white-space:nowrap}.dc td{padding:6px 8px;border-bottom:1px solid #EEF1F4;vertical-align:middle}
.dc input,.dc select,.dc textarea{height:30px;border:1px solid var(--bd);border-radius:8px;padding:0 9px;font-size:12px;width:auto;margin:0;background:#fff}.dc textarea{height:auto;padding:7px 9px;width:100%;font-family:inherit}
.dc pre{white-space:pre-wrap;word-break:break-word;font-size:11px;max-height:150px;overflow:auto;margin:6px 0 0;background:#F8FAFC;color:#374151;border:1px solid var(--bd);border-radius:8px;padding:8px;font-family:Inter,'Bornomala',sans-serif}
.dc pre.big{max-height:60vh}
.dc .ov{position:fixed;inset:0;background:rgba(11,18,32,.42);z-index:80;display:none;align-items:center;justify-content:center;padding:14px}.dc .ov.on{display:flex}
.dc .md{background:#fff;border:1px solid var(--bg2);border-radius:12px;max-width:680px;width:100%;max-height:90vh;overflow:auto;padding:14px 16px}
.dc .stp{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:2px 14px;margin-top:6px}
.dc .stp div{display:flex;align-items:center;gap:7px;padding:2px 0;font-size:12px}
.dc .stp i{font-style:normal;width:16px;text-align:center;font-weight:800}
.dc .stp .done i{color:#16A34A}.dc .stp .run i{color:#1D5FA8}.dc .stp .wait{color:#9CA3AF}.dc .stp .fail i{color:#B91C1C}.dc .stp .fail{color:#B91C1C;font-weight:700}
.dc .stp .sk{font-size:10px;color:var(--mu);margin-left:auto}
.dc .kv{display:grid;grid-template-columns:110px minmax(0,1fr);gap:3px 8px;font-size:12px}.dc .kv span{color:var(--mu)}
.dc .is{display:grid;grid-template-columns:92px minmax(0,1fr) auto;gap:8px;padding:7px 0;border-bottom:1px solid #EEF1F4;align-items:start}.dc .is:last-child{border-bottom:0}
.dc .is small{display:block;color:var(--mu);font-size:11px;line-height:1.35}
.dc .fl{background:#FEF3F2;border:1px solid #F6C4C0;border-radius:8px;padding:8px 10px;margin-top:6px}
.dc .lk{background:#EEF3FB;border:1px solid #C5D3EC;color:#1E3A6E;border-radius:8px;padding:6px 10px;font-weight:600}
.dc details.cd>summary{cursor:pointer;font-weight:700;font-size:12.5px}
.dc .toast{position:fixed;right:16px;bottom:16px;z-index:90;background:#fff;border:1px solid var(--bg2);border-left:4px solid #16A34A;border-radius:10px;padding:10px 14px;font-weight:700;box-shadow:0 6px 20px rgba(11,18,32,.14);max-width:360px}
.dc .toast.bad{border-left-color:#DC2626}
@media (max-width:1100px){.dc .sb{grid-template-columns:repeat(4,minmax(0,1fr))}.dc .sc{border-bottom:1px solid var(--bd)}.dc .g5{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:860px){.dc .g2{grid-template-columns:minmax(0,1fr)}.dc .sb{grid-template-columns:repeat(2,minmax(0,1fr))}.dc .stp{grid-template-columns:minmax(0,1fr)}.dc .is{grid-template-columns:minmax(0,1fr)}}
</style>
<div class="dc" id="dc">
  <div class="bar" style="justify-content:space-between"><div style="min-width:220px"><h1>Deployment Center</h1><div class="mu">Manage releases, deployments, health checks and recovery.</div></div><div class="bar" id="hdr"></div></div>
  <div class="sb" id="sb"></div>
  <div class="cd"><div class="bar" id="acts"></div><div id="msg" class="mu" style="margin-top:5px"></div></div>
  <div class="cd" id="wk"></div>
  <div class="g2"><div id="curBox" style="display:flex;flex-direction:column;gap:10px"></div><div class="cd" id="prog"></div></div>
  <div class="cd" id="iss"></div>
  <div class="cd" style="overflow-x:auto"><div class="bar" style="justify-content:space-between;margin-bottom:4px"><h2 style="margin:0">Recent Deployments</h2><div class="bar" id="flt"></div></div>
    <table><thead><tr><th>Release</th><th>Date</th><th>Commit</th><th>Type</th><th>Status</th><th>Duration</th><th>Deployed By</th><th>Issues</th><th>Actions</th></tr></thead><tbody id="hist"></tbody></table></div>
  <div class="g5" id="ops"></div>
  <details class="cd"><summary>Work Log <span class="mu" id="wsum"></span></summary><div style="overflow-x:auto;margin-top:6px"><table><thead><tr><th style="width:36px">#</th><th>Task</th><th>Status</th><th style="width:150px">Progress</th><th>Updated</th></tr></thead><tbody id="wl"></tbody></table></div></details>
  <details class="cd"><summary>Automation settings</summary><div class="bar" style="margin-top:8px"><label class="mu">Mode</label><select id="sMode"><option value="auto">Automatic: deploy every new commit</option><option value="hold">Hold: wait for my deploy button</option></select><label class="mu">Branch</label><input id="sBranch" style="width:110px"><label class="mu">GitHub token (optional, read-only)</label><input id="sGh" type="password" autocomplete="new-password" placeholder="leave blank to keep" style="width:170px"><button class="b" id="sSave">Save</button></div><div class="mu" id="head" style="margin-top:6px"></div>
    <div class="mu" style="margin-top:6px">How the engine works: every deployment builds a separate release, tests it before it goes live, switches in one atomic step (no maintenance mode) and rolls back by itself if the live check fails. The server keeps the newest 3 releases; deployment logs and issue history stay in the database.</div></details>
  <div class="ov" id="ov"><div class="md" id="md"></div></div>
</div>
<script type="application/json" id="dd">{!! json_encode($d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
<form style="display:none">@csrf</form>
@endsection
@section('js')
@verbatim
<script>
(function(){
var $=function(i){return document.getElementById(i)},D=JSON.parse($('dd').textContent),WK='',busy=false,F='def',T0=Date.now()/1000-D.ts,lastAct=D.active?D.active.id:null,tTimer=null;
var SC={ok:'#16A34A',warn:'#D97706',fail:'#DC2626',na:'#9CA3AF'};
var C={'Queued':['#4B5563','#F3F4F6','#D5D9DE'],'Downloading':['#1D5FA8','#E6EFF9','#BCD3EE'],'Building':['#1D5FA8','#E6EFF9','#BCD3EE'],'Testing':['#1D5FA8','#E6EFF9','#BCD3EE'],'Deploying':['#1D5FA8','#E6EFF9','#BCD3EE'],'Running':['#1D5FA8','#E6EFF9','#BCD3EE'],'Live':['#166534','#F0FBF4','#BFE6CE'],'Success':['#166534','#F0FBF4','#BFE6CE'],'Completed':['#166534','#F0FBF4','#BFE6CE'],'Rollback':['#5B3FA6','#F5F3FF','#D4CCF5'],'Rolled Back':['#5B3FA6','#F5F3FF','#D4CCF5'],'Failed':['#B42318','#FEF3F2','#F6C4C0'],'Cancelled':['#4B5563','#F3F4F6','#D5D9DE'],
 'critical':['#B42318','#FEF3F2','#F6C4C0'],'high':['#C2410C','#FFF1E8','#FDBA8C'],'warning':['#9A5B0B','#FFF8E8','#F4D79B'],'info':['#1D5FA8','#E6EFF9','#BCD3EE'],
 'open':['#B42318','#FEF3F2','#F6C4C0'],'investigating':['#9A5B0B','#FFF8E8','#F4D79B'],'resolved':['#166534','#F0FBF4','#BFE6CE'],'ignored':['#4B5563','#F3F4F6','#D5D9DE'],
 'LIVE':['#166534','#F0FBF4','#BFE6CE'],'WARNING':['#9A5B0B','#FFF8E8','#F4D79B'],'ISSUE':['#B42318','#FEF3F2','#F6C4C0'],'UNKNOWN':['#4B5563','#F3F4F6','#D5D9DE'],'HEALTHY':['#166534','#F0FBF4','#BFE6CE']};
var SEV={critical:'🔴 Critical',high:'🟠 High',warning:'🟡 Warning',info:'🔵 Information'};
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function pill(s,t){var c=C[s]||C.Queued;return '<span class="pl" style="color:'+c[0]+';background:'+c[1]+';border-color:'+c[2]+'">'+esc(String(t||s).toUpperCase())+'</span>'}
function csrf(){var i=document.querySelector('input[name=_token]');return i?i.value:''}
function jf(u,o){o=o||{};o.credentials='same-origin';o.headers=Object.assign({Accept:'application/json','X-CSRF-TOKEN':csrf()},o.headers||{});return fetch(u,o).then(function(r){return r.json().catch(function(){return{ok:false,msg:'Error '+r.status}})})}
function post(u,b){return jf(u,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b||{})})}
function say(t){$('msg').textContent=t||''}
function dur(n){if(n==null||n==='')return '—';n=+n;return n<90?pad(Math.floor(n/60))+':'+pad(n%60):pad(Math.floor(n/60))+':'+pad(n%60)}
function pad(n){return n<10?'0'+n:''+n}
function rel(r){return r?(r.no?'#'+r.no:'—'):'—'}
function modal(h){$('md').innerHTML=h;$('ov').classList.add('on')}
function closeM(){$('ov').classList.remove('on')}
$('ov').onclick=function(e){if(e.target===$('ov'))closeM()};
function toast(t,bad){var el=document.createElement('div');el.className='toast'+(bad?' bad':'');el.textContent=t;$('dc').appendChild(el);clearTimeout(tTimer);setTimeout(function(){el.remove()},9000)}
function cellHtml(c){return '<div class="sc" title="'+esc(c.detail)+'"><small>'+esc(c.label)+'</small><b><span class="dt '+c.state+'"></span>'+esc(c.text)+'</b><em>'+esc(c.detail)+'</em></div>'}
function stepsHtml(st){return '<div class="stp">'+st.map(function(s){var ic={done:'✓',run:'●',wait:'○',fail:'✕'}[s.state];return '<div class="'+s.state+'"><i>'+ic+'</i>'+esc(s.label)+(s.skip?'<span class="sk">Skipped — Not Required</span>':'')+'</div>'}).join('')+'</div>'}
function stepName(){var f=(D.steps||[]).filter(function(s){return s.state==='fail'||s.state==='run'})[0];return f?f.label:'Build'}

function render(){
  if(!D.enabled){$('sb').innerHTML='';$('curBox').innerHTML='<div class="cd"><b>Release layout is not active yet.</b></div>';return}
  var H=D.health||{cells:[],overall:'UNKNOWN'},c=D.current,a=D.active,lock=D.locked,sup=D.super;
  $('hdr').innerHTML='<span class="mu">Environment:</span> <b>PRODUCTION</b> <span class="mu">· Website:</span> '+pill(H.overall)+' <span class="mu">· Current Release:</span> <b>'+(c?rel(c):'Base')+'</b>';
  $('sb').innerHTML=H.cells.map(cellHtml).join('');
  var dis=lock?' disabled':'';
  $('acts').innerHTML='<button class="b pri" data-d="quick"'+dis+' title="Fastest safe update: views, CSS, JS and code only (no migration or dependency change)">Quick Deploy</button><button class="b" data-d="standard"'+dis+' title="Normal production update. Safe migrations run if pending.">Standard Deploy</button><button class="b fu" data-d="full"'+dis+(sup?'':' title="Super Admin only"')+(sup?'':' disabled')+'>Full Deploy</button><button class="b ro" id="bRoll"'+dis+(sup&&D.previous&&D.previous.valid?'':' disabled')+'>Rollback</button><button class="b hc" id="bHealth">Run Health Check</button><button class="b" id="bRefresh">Refresh</button><span style="width:1px;height:20px;background:#CED4DA;margin:0 4px"></span><button class="b sm" id="bRec"'+(sup?'':' disabled')+'>Recovery Check</button><button class="b sm" data-k="clear"'+(sup?'':' disabled')+'>Clear Safe Cache</button><button class="b sm" id="bIssues">View Issues</button><button class="b sm" id="bLogs">View Logs</button>'+(a&&a.status==='Queued'?'<button class="b sm" id="bCancel">Cancel Pending</button>':'')+(lock?'<span class="lk" style="margin-left:6px">Deployment currently in progress.</span>':'');
  // current + previous
  var pv=D.previous,box='';
  box+='<div class="cd"><h2>Current Production Release</h2>'+(c?'<div class="kv"><span>Release</span><b>'+rel(c)+' '+pill('Live')+'</b><span>Commit</span><b>'+esc(c.sha?c.sha.slice(0,7):'—')+'</b><span>Status</span><b>'+pill(H.overall==='LIVE'?'Healthy':H.overall,H.overall==='LIVE'?'Healthy':H.overall)+'</b><span>Deployed</span><span style="color:inherit">'+esc(c.at)+'</span><span>Deployed By</span><span style="color:inherit">'+esc(c.by)+'</span><span>Type</span><span style="color:inherit">'+esc(cap(c.dtype)||'—')+'</span><span>Duration</span><span style="color:inherit">'+dur(c.dur)+'</span></div><div style="margin-top:4px" class="mu">'+esc(c.subject||'')+'</div><div class="bar" style="margin-top:8px"><button class="b sm" data-v="'+c.id+'">View Details</button><button class="b sm" data-c="'+c.id+'">View Changes</button></div>':'<div class="kv"><span>Release</span><b>Base release</b><span>Folder</span><span style="color:inherit">'+esc(D.liveId)+'</span></div>')+'</div>';
  box+='<div class="cd"><h2>Previous Stable Release</h2>'+(pv?'<div class="kv"><span>Release</span><b>'+esc(pv.version||pv.id)+'</b><span>Subject</span><span style="color:inherit">'+esc(pv.subject||'—')+'</span><span>Integrity</span><span style="color:inherit">'+(pv.valid?'✓ complete and ready':'✕ incomplete (rollback blocked)')+'</span></div><div class="bar" style="margin-top:8px"><button class="b ro sm" id="bRoll2"'+(sup&&pv.valid&&!lock?'':' disabled')+'>Rollback to '+esc(pv.version||'previous')+'</button></div>':'<div class="mu">No previous release is kept yet.</div>')+'</div>';
  $('curBox').innerHTML=box;
  // work status (Bengali): waiting -> deploying -> done today; lists open only on "View"
  var P=(D.pending||[]).filter(function(x){return !x.running}),R=a?[a]:[],TD=(D.history||[]).filter(function(x){return x.short===D.today&&x.cat!=='running'}),OKs=TD.filter(function(x){return x.cat!=='failed'}),BAD=TD.filter(function(x){return x.cat==='failed'});
  function wrow(t,r,col){return '<div style="display:flex;gap:8px;justify-content:space-between;padding:4px 0;border-bottom:1px solid #EEF1F4;font-size:12px"><span style="min-width:0">'+esc(t)+'</span><span style="color:'+(col||'var(--mu)')+';white-space:nowrap;font-weight:600">'+esc(r)+'</span></div>'}
  function wbox(k,label,n,col,rows){var open=WK===k;return '<div style="flex:1;min-width:150px;border:1px solid var(--bd);border-radius:10px;padding:8px 10px"><div style="display:flex;align-items:center;justify-content:space-between;gap:6px"><span style="font-size:12px;color:var(--mu)">'+label+'</span><button class="b sm" data-wk="'+k+'"'+(n?'':' disabled')+'>'+(open?'Hide':'View')+'</button></div><b style="font-size:20px;color:'+col+'">'+n+'</b></div>'}
  var wl={p:P.map(function(x){return wrow(x.subject,'অপেক্ষায় · '+x.time,'#9A5B0B')}),r:R.map(function(x){return wrow(x.subject,'চলছে · '+x.progress+'%','#1D5FA8')}),o:OKs.map(function(x){return wrow(x.subject,'সফল · '+x.time,'#15803D')}),f:BAD.map(function(x){return wrow(x.subject,'ব্যর্থ · '+x.time,'#B91C1C')})};
  $('wk').innerHTML='<h2 style="margin:0 0 6px">কাজের অবস্থা <span class="mu" style="font-weight:500">· উপরে অপেক্ষায়/চলছে, শেষ হলে নিচে সফল তালিকায় যায়</span></h2><div class="bar" style="align-items:stretch">'+wbox('p','অপেক্ষায় (ডিপ্লয় বাকি)',P.length,'#9A5B0B')+wbox('r','ডিপ্লয় চলছে',R.length,'#1D5FA8')+wbox('o','আজ সফল',OKs.length,'#15803D')+wbox('f','আজ ব্যর্থ',BAD.length,BAD.length?'#B91C1C':'#9CA3AF')+'</div>'+(WK&&wl[WK]&&wl[WK].length?'<div style="margin-top:8px;max-height:260px;overflow:auto">'+wl[WK].join('')+'</div>':'');
  // progress / last result
  var f=a||D.history[0],p='';
  if(a){var el=Math.max(0,Math.round(Date.now()/1000-T0-(a.started_ts||0)));
    p='<h2>Deployment #'+a.id+' <span class="mu">'+esc(cap(a.dtype)||'')+'</span></h2><div style="font-weight:700">'+esc(a.subject)+'</div><div style="color:#B45309;font-weight:600;font-size:13px;margin:2px 0 6px">এই কাজটির ডিপ্লয় চলছে — '+a.progress+'% শেষ। শেষ হলে সাইটে দেখা যাবে।</div>'+stepsHtml(D.steps||[])+'<div class="pb"><i style="width:'+a.progress+'%"></i></div><b>'+a.progress+'%</b> <span class="mu">· Running for '+pad(Math.floor(el/60))+':'+pad(el%60)+(a.changed?' · '+a.changed+' files changed':'')+' · '+esc(a.stage||a.status)+'</span>';
  }else if(f){var bad=f.cat==='failed'||(f.status==='Rolled Back'&&f.error);
    p='<h2>Last deployment: #'+f.id+' '+pill(f.badge)+'</h2><div style="font-weight:700">'+esc(f.subject||'')+'</div><div style="font-weight:600;font-size:13px;margin:2px 0 6px;color:'+(bad?'#B91C1C':'#15803D')+'">'+(bad?'এই কাজটির ডিপ্লয় ব্যর্থ হয়েছে। সাইটে আগের সংস্করণই চলছে।':'এই কাজটির ডিপ্লয় শেষ হয়েছে। সাইটে এখন এটি চালু আছে। কোনো ডিপ্লয় চলছে না।')+'</div>'+(D.steps&&D.steps.length?stepsHtml(D.steps):'');
    if(bad)p+='<div class="fl"><b style="color:#B42318">Deployment #'+f.id+' FAILED</b><div class="kv" style="margin-top:4px"><span>Failed Step</span><b>'+esc(stepName())+'</b><span>Reason</span><span style="color:inherit">'+esc((f.error||'See log').slice(0,300))+'</span><span>Production Impact</span><span style="color:inherit">'+(f.status==='Rolled Back'?'Automatic rollback completed: the previous release is live.':'Previous release remains active.')+'</span><span>Recommended</span><span style="color:inherit">Review logs before retry.</span></div><div class="bar" style="margin-top:6px"><button class="b sm" data-e="'+f.id+'">View Error</button><button class="b sm" data-l="'+f.id+'">View Logs</button>'+(f.retry?'<button class="b sm" data-r="'+f.retry+'"'+(lock?' disabled':'')+'>Retry Safe Step</button>':'')+'<button class="b ro sm" id="bRoll3"'+(sup&&pv&&pv.valid&&!lock?'':' disabled')+'>Rollback</button></div></div>';
  }else p='<h2>Deployment</h2><div class="mu">No deployment yet.</div>';
  p+='<div class="bar" style="justify-content:space-between;margin-top:8px"><b style="font-size:12px">Live Deployment Log</b><span class="bar"><button class="b sm" id="lgCopy">Copy Log</button><button class="b sm" id="lgDl">Download Log</button><button class="b sm" id="lgEx">Expand</button></span></div><pre id="lgPre">'+esc(D.log||'No log yet.')+'</pre>';
  $('prog').innerHTML=p;var pre=$('lgPre');if(pre&&a)pre.scrollTop=pre.scrollHeight;
  // issues
  var I=D.issues||[],ih='<div class="bar" style="justify-content:space-between;margin-bottom:2px"><h2 style="margin:0">Active Issues '+(I.length?'<span class="pl" style="color:#B42318;background:#FEF3F2;border-color:#F6C4C0">'+I.length+'</span>':'')+'</h2><button class="b sm" id="bIssH">Issue History ('+(D.issueHist||0)+')</button></div>';
  ih+=I.length?I.map(function(x){return '<div class="is"><div>'+pill(x.severity,SEV[x.severity])+'<small style="margin-top:3px">'+esc(x.detected)+'</small></div><div><b>'+esc(x.title)+'</b> <span class="mu">· '+esc(x.service||'')+'</span><small>'+esc(x.description||'')+'</small><small><b>Possible cause:</b> '+esc(x.cause||'—')+'</small><small><b>Recommended:</b> '+esc(x.action||'—')+'</small></div><div style="text-align:right">'+pill(x.status)+'<div style="margin-top:5px"><button class="b sm" data-i="'+x.id+'">View Issue</button></div></div></div>'}).join(''):'<div class="mu" style="padding:6px 0">✓ No active issues. Everything the system checks is healthy.</div>';
  $('iss').innerHTML=ih;
  // history
  $('flt').innerHTML=[['def','Default'],['all','All'],['live','Live'],['running','Running'],['completed','Completed'],['failed','Failed / Issues']].map(function(x){return '<button class="b sm'+(F===x[0]?' pri':'')+'" data-f="'+x[0]+'">'+x[1]+'</button>'}).join('');
  var rows=D.history.filter(function(r){return F==='all'||(F==='def'?r.cat!=='failed':r.cat===F)});
  $('hist').innerHTML=rows.map(function(r){var ty=r.dtype?cap(r.dtype):(r.badge==='Rollback'?'Rollback':'—');return '<tr><td><b>'+(r.no?'#'+r.no:'—')+'</b> <span class="mu">'+esc((r.subject||'').slice(0,46))+'</span></td><td>'+esc(r.short)+' <span class="mu">'+esc(r.time)+'</span></td><td><code>'+esc(r.sha?r.sha.slice(0,7):'—')+'</code></td><td>'+esc(ty)+'</td><td>'+pill(r.badge==='Completed'?'Success':r.badge)+'</td><td>'+dur(r.dur)+'</td><td>'+esc(r.by)+'</td><td>'+(r.issues||0)+'</td><td style="white-space:nowrap"><button class="b sm" data-v="'+r.id+'">View</button>'+(r.retry?' <button class="b sm" data-r="'+r.retry+'"'+(lock?' disabled':'')+'>Retry</button>':'')+'</td></tr>'}).join('')||'<tr><td colspan="9" class="mu">Nothing to show for this filter.</td></tr>';
  // ops
  var q=D.queue||{},ca=D.cache||{},cells={};H.cells.forEach(function(x){cells[x.key]=x});
  function line(k,v){return '<div class="kv" style="grid-template-columns:96px minmax(0,1fr)"><span>'+k+'</span><b style="font-weight:600">'+v+'</b></div>'}
  function dot(s){return '<span class="dt '+s+'" style="display:inline-block;margin-right:4px"></span>'}
  var ops='';
  ops+='<div class="cd"><h2>Queue</h2>'+line('Mode',q.driver==='sync'?'Sync (instant)':esc(q.driver))+line('Running Workers',q.driver==='sync'?'none needed':'—')+line('Pending Jobs',q.pending==null?'—':q.pending)+line('Failed Jobs',q.failed)+(q.oldest!=null?line('Oldest Pending',pad(Math.floor(q.oldest/60))+':'+pad(q.oldest%60)):'')+'<div class="bar" style="margin-top:6px"><button class="b sm" id="bFj">View Failed Jobs</button><button class="b sm" id="bQr"'+(sup?'':' disabled')+'>Restart Workers</button></div></div>';
  var sch=cells.scheduler||{};ops+='<div class="cd"><h2>Laravel Scheduler</h2>'+line('Status',dot(sch.state||'na')+esc(sch.text||'UNKNOWN'))+line('Heartbeat',esc((sch.detail||'—').replace('Last heartbeat ','')))+((sch.state==='warn'||sch.state==='fail')?'<div class="mu" style="margin-top:4px;color:#9A5B0B">🟡 Scheduler appears delayed.</div>':'')+'<div class="mu" style="margin-top:4px">The background worker loop (order pushes, scheduled SMS, AI Brief, auto-deploy) beats every ~30 seconds.</div></div>';
  var db=cells.database||{};ops+='<div class="cd"><h2>Database</h2>'+line('Connection',dot(db.state||'na')+esc(db.text||'—'))+line('Migration',D.migs?'<span style="color:#9A5B0B">🟡 '+D.migs+' Pending Migration(s)</span>':'Up to Date')+line('Engine',esc(db.detail||''))+'<div class="bar" style="margin-top:6px"><button class="b sm" id="bMig">View Migrations</button></div><div class="mu" style="margin-top:4px">Migrations run only through a deployment.</div></div>';
  ops+='<div class="cd"><h2>Cache</h2>'+line('Application Cache',esc(ca.driver||'—'))+line('Config Cache',ca.config?'Built':'Not built')+line('Route Cache',ca.route?'Built':'Not built')+line('View Cache',(ca.views||0)+' compiled')+'<div class="bar" style="margin-top:6px"><button class="b sm" data-k="clear"'+(sup?'':' disabled')+'>Clear Safe Cache</button><button class="b sm" data-k="rebuild"'+(sup?'':' disabled')+'>Rebuild Cache</button></div></div>';
  var ct=cells.containers||{},wb=cells.website||{},ss=cells.ssl||{};ops+='<div class="cd"><h2>Containers</h2>'+line('Application',dot(cells.application?cells.application.state:'na')+'Running')+line('Web (nginx)',dot(wb.state||'na')+(wb.state==='ok'?'Running':'Check'))+line('PostgreSQL',dot(db.state||'na')+(db.state==='ok'?'Running':'Check'))+line('Queue Worker',dot(sch.state||'na')+(sch.state==='ok'?'Running':'Check'))+line('Redis','Not used')+line('Traefik / SSL',dot(ss.state||'na')+esc(ss.text||'—'))+'<div class="mu" style="margin-top:4px">Inferred from live probes. Server-level restarts stay with the server owner.</div></div>';
  $('ops').innerHTML=ops;
  // work log + settings
  var T=D.tasks||[],WC={done:['#166534','#F0FBF4','DONE'],partial:['#9A5B0B','#FFF8E8','PARTIAL'],pending:['#4B5563','#F3F4F6','PENDING'],blocked:['#B42318','#FEF3F2','BLOCKED']};
  var dn=T.filter(function(x){return x.status==='done'}).length;$('wsum').textContent=T.length?'· '+dn+' of '+T.length+' complete':'';
  $('wl').innerHTML=T.map(function(x,i){var k=WC[x.status]||WC.pending;return '<tr><td><b>'+(i+1)+'</b></td><td>'+esc(x.title)+(x.note?'<div class="mu">'+esc(x.note)+'</div>':'')+'</td><td><span class="pl" style="color:'+k[0]+';background:'+k[1]+'">'+k[2]+'</span></td><td><div class="pb" style="margin:0 0 3px"><i style="width:'+x.percent+'%;background:'+(x.status==='done'?'#16A34A':'#FF5A00')+'"></i></div><b>'+x.percent+'%</b></td><td class="mu">'+esc(x.at)+'</td></tr>'}).join('')||'<tr><td colspan="5" class="mu">No tasks yet.</td></tr>';
  var h=D.head,hs={live:'up to date',pending:'new update, deploying automatically',waiting:'ready for your deploy button',completed:'deployed earlier',failed:'last attempt failed',queued:'queued',unknown:'could not read the branch'}[D.headState]||D.headState;
  $('head').textContent='Latest on “'+D.branch+'”: '+(h?h.subject+' ('+h.sha.slice(0,7)+')':'unknown')+' — '+hs;
  var sm=$('sMode');if(document.activeElement!==sm)sm.value=D.mode;if(document.activeElement!==$('sBranch'))$('sBranch').value=D.branch;
  // completion toast
  if(lastAct&&!a){var d=D.history.filter(function(x){return x.id==lastAct})[0];if(d){if(d.cat==='failed'||(d.status==='Rolled Back'&&d.error))toast('⚠ Deployment #'+d.id+' failed at '+stepName()+'. Previous release remains active.',true);else toast('✓ Deployment #'+(d.no||d.id)+' completed successfully.')}}
  lastAct=a?a.id:null;
}
function cap(s){return s?String(s).charAt(0).toUpperCase()+String(s).slice(1):''}
function load(u,m){if(busy)return;busy=true;return fetch(u,{method:m||'GET',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf()}}).then(function(r){return r.json()}).then(function(j){if(j&&j.history){D=j;T0=Date.now()/1000-D.ts;render()}}).catch(function(){}).then(function(){busy=false})}
function reload(){return load('/admin/deploy/status')}
function logOf(id,t,errOnly){jf('/admin/deploy/'+id+'/log').then(function(l){modal('<h2>'+esc(t||'Log')+'</h2>'+(errOnly?'<div class="fl"><b>Error</b><div style="margin-top:4px;white-space:pre-wrap">'+esc(l.error||'No error text was saved.')+'</div></div>':'<pre class="big" id="lgM">'+esc(l.log||'No log saved.')+'</pre>')+'<div class="bar" style="margin-top:8px"><button class="b" id="mClose">Close</button></div>')})}
function startDeploy(type){
  var h=D.head,tgt=h?'“'+esc(h.subject)+'” ('+esc(h.sha.slice(0,7))+')':'the latest commit',tt={quick:['Quick Deploy','The fastest safe update for Blade, CSS, JavaScript and small code changes. The system first checks with GitHub that the update has no database migration and no dependency change; if it has, Quick Deploy stops and you use Standard.'],standard:['Standard Deploy','Normal production update: builds a separate release, runs composer or safe migrations only if needed, tests it, then switches atomically and checks health.'],full:['Full Deploy','']}[type];
  var full=type==='full';
  modal('<h2>'+(full?'⚠ Full Deployment':esc(tt[0]))+'</h2>'+(full?'<div class="fl" style="margin:0 0 8px"><b>This deployment may rebuild services and take additional system resources.</b><div class="mu" style="margin-top:3px">It also allows migrations that drop or rename columns (a very short maintenance switch). Use it only when a major change needs it.</div></div>':'<div class="mu" style="margin-bottom:8px">'+esc(tt[1])+'</div>')+'<div class="kv"><span>Deploying</span><b>'+tgt+'</b><span>Zero downtime</span><span style="color:inherit">Yes: atomic switch, automatic rollback if the live check fails</span></div><label class="mu" style="display:block;margin-top:8px">Deployment Note (optional)</label><textarea id="dNote" rows="2" placeholder="Example: Order Management UI update and payment calculation fix."></textarea>'+(full?'<label style="display:flex;gap:6px;align-items:center;margin-top:8px"><input type="checkbox" id="dOk" style="width:auto;height:auto"> I understand and want to start a Full Deploy.</label>':'')+'<div class="bar" style="margin-top:10px"><button class="b" id="mClose">Cancel</button><button class="b pri" id="dGo"'+(full?' disabled':'')+'>Start '+esc(tt[0]||'Full Deploy')+'</button></div>');
  if(full)$('dOk').onchange=function(){$('dGo').disabled=!this.checked};
  $('dGo').onclick=function(){$('dGo').disabled=true;post('/admin/deploy',{type:type,note:$('dNote').value}).then(function(j){closeM();say(j.msg||'');if(j.ok===false&&j.suggest)say(j.msg+' (Use Standard Deploy.)');reload()})}
}
function rollbackModal(){
  var c=D.current,p=D.previous;if(!p)return;
  modal('<h2>Rollback Production</h2><div class="kv"><span>Current</span><b>Release '+esc(rel(c))+' '+esc(c?(c.subject||'').slice(0,50):'')+'</b><span>Rollback To</span><b>'+esc(p.version||p.id)+' '+esc((p.subject||'').slice(0,50))+'</b></div><div class="mu" style="margin-top:6px">The current release is kept on the server (not deleted). Workers restart gracefully and a health check runs after the switch.</div><label class="mu" style="display:block;margin-top:8px">Reason (required)</label><textarea id="rReason" rows="2"></textarea><div class="bar" style="margin-top:10px"><button class="b" id="mClose">Cancel</button><button class="b ro" id="rGo" disabled>Confirm Rollback</button></div>');
  $('rReason').oninput=function(){$('rGo').disabled=this.value.trim().length<3};
  $('rGo').onclick=function(){$('rGo').disabled=true;post('/admin/deploy',{type:'rollback',reason:$('rReason').value}).then(function(j){closeM();say(j.msg||'');reload()})}
}
function healthModal(r){
  var icon={ok:'✓',warn:'!',fail:'✕',na:'–'};
  modal('<h2>Health Check</h2><table>'+r.checks.map(function(x){return '<tr><td>'+esc(x.name)+'</td><td><span style="color:'+SC[x.state]+';font-weight:800">'+icon[x.state]+'</span> '+esc(x.text)+'</td><td class="mu">'+esc(x.detail)+'</td></tr>'}).join('')+'</table><div style="margin-top:8px"><b>Overall:</b> '+pill(r.overall)+'</div><div class="bar" style="margin-top:8px"><button class="b" id="mClose">Close</button></div>')
}
function issueModal(id){
  var x=(D.issues||[]).filter(function(y){return y.id==id})[0];if(!x)return;
  modal('<h2>Issue: '+esc(x.title)+'</h2><div class="kv"><span>Severity</span><b>'+pill(x.severity,SEV[x.severity])+'</b><span>Detected</span><span style="color:inherit">'+esc(x.detected)+'</span><span>Affected</span><span style="color:inherit">'+esc(x.service||'—')+'</span><span>Status</span><b>'+pill(x.status)+'</b><span>Description</span><span style="color:inherit">'+esc(x.description||'—')+'</span><span>Possible Cause</span><span style="color:inherit">'+esc(x.cause||'—')+'</span><span>Recommended Action</span><span style="color:inherit">'+esc(x.action||'—')+'</span></div><label class="mu" style="display:block;margin-top:8px">Note</label><textarea id="iNote" rows="2">'+esc(x.note||'')+'</textarea><div class="bar" style="margin-top:10px">'+(x.deployment_id?'<button class="b sm" data-l="'+x.deployment_id+'">View Logs</button>':'')+'<button class="b sm" id="iHc">Run Health Check</button><button class="b ro sm" id="iRb"'+(D.super&&D.previous&&D.previous.valid&&!D.locked?'':' disabled')+'>Rollback</button><button class="b sm" data-s="investigating">Investigating</button><button class="b sm" data-s="ignored">Ignore</button><button class="b pri sm" data-s="resolved">Mark Resolved</button><button class="b sm" id="mClose">Close</button></div>');
  $('md').onclick=function(e){var t=e.target;if(t.dataset.s){post('/admin/deploy/issue/'+id,{status:t.dataset.s,note:$('iNote').value}).then(function(){closeM();reload()})}if(t.id==='iHc'){closeM();runHealth()}if(t.id==='iRb'){closeM();rollbackModal()}if(t.id==='mClose')closeM();if(t.dataset.l){closeM();logOf(t.dataset.l,'Logs')}}
}
function runHealth(){say('Running health check…');jf('/admin/deploy/health',{method:'POST'}).then(function(r){say('Health check finished: '+r.overall);healthModal(r);reload()})}
function detail(id){
  var r=D.history.filter(function(x){return x.id==id})[0];if(!r)return;
  var at=(r.attempts.length>1||r.cat==='failed')?'<label class="mu">Attempts</label><table>'+r.attempts.map(function(x){return '<tr><td>#'+x.n+'</td><td>'+pill(x.status)+'</td><td>'+dur(x.dur)+'</td><td>'+esc(x.at)+'</td><td><button class="b sm" data-l="'+x.id+'">Log</button></td></tr>'+(x.error?'<tr><td></td><td colspan="4" class="mu">'+esc(x.error)+'</td></tr>':'')}).join('')+'</table>':'';
  modal('<h2>Release '+(r.no?'#'+r.no:'')+' '+esc(r.subject)+'</h2><div class="kv"><span>Status</span><b>'+pill(r.badge)+'</b><span>Commit</span><b>'+esc(r.sha||'—')+'</b><span>Type</span><span style="color:inherit">'+esc(cap(r.dtype)||'—')+'</span><span>Started</span><span style="color:inherit">'+esc(r.started)+'</span><span>Completed</span><span style="color:inherit">'+esc(r.finished)+'</span><span>Duration</span><span style="color:inherit">'+dur(r.dur)+'</span><span>Deployed By</span><span style="color:inherit">'+esc(r.by)+'</span><span>Previous Release</span><span style="color:inherit">'+esc(r.from||'—')+'</span><span>New Release</span><span style="color:inherit">'+esc(r.release_id||'—')+'</span><span>Files changed</span><span style="color:inherit">'+(r.files==null?'—':r.files)+'</span><span>Issues</span><span style="color:inherit">'+(r.issues||0)+'</span><span>Rollback</span><span style="color:inherit">'+(r.badge==='Rollback'||r.status==='Rolled Back'?'Yes':'No')+'</span></div>'+(r.error?'<div class="fl"><b>Error:</b> '+esc(r.error)+'</div>':'')+at+'<label class="mu" style="display:block;margin-top:6px">Subject</label><input id="eS" style="width:100%" value="'+esc(r.subject)+'"><label class="mu">Deployment note</label><textarea id="eD" rows="2">'+esc(r.description||'')+'</textarea><label class="mu">Update notes (one per line)</label><textarea id="eN" rows="3">'+esc(r.notes||'')+'</textarea><div class="bar" style="margin:8px 0"><button class="b pri" id="eSave">Save</button><button class="b" data-l="'+r.id+'">Logs</button><button class="b" data-c="'+r.id+'">Changes</button><button class="b" id="mClose">Close</button></div>');
  $('eSave').onclick=function(){post('/admin/deploy/'+id+'/edit',{subject:$('eS').value,description:$('eD').value,notes:$('eN').value}).then(function(){closeM();reload()})}
}
function changes(id){jf('/admin/deploy/'+id+'/changes').then(function(j){var i=j.info;modal('<h2>Changes Summary</h2>'+(i?'<div class="kv"><span>Commit</span><b>'+esc(i.sha.slice(0,7))+'</b><span>Branch</span><span style="color:inherit">'+esc(i.branch)+'</span><span>Author</span><span style="color:inherit">'+esc(i.author)+'</span><span>Message</span><span style="color:inherit;white-space:pre-wrap">'+esc((i.message||'').split('\n').filter(function(l){return !/^(Co-Authored|Claude-Session)/.test(l)}).join('\n'))+'</span></div><label class="mu" style="display:block;margin-top:6px">Changed files ('+i.total+')</label><div style="max-height:38vh;overflow:auto;border:1px solid #CED4DA;border-radius:8px;padding:6px 8px;font-size:11.5px">'+i.files.map(function(f){return '<div><code>'+esc(f.name)+'</code> <span class="mu">'+esc(f.status)+' +'+f.add+' −'+f.del+'</span></div>'}).join('')+'</div>':'<div class="mu">Could not read the changes from GitHub.</div>')+'<div class="bar" style="margin-top:8px"><button class="b" id="mClose">Close</button></div>')})}
function issueHistory(){jf('/admin/deploy/issues').then(function(j){modal('<h2>Issue History</h2>'+((j.history||[]).length?'<div style="max-height:60vh;overflow:auto">'+j.history.map(function(x){return '<div class="is" style="grid-template-columns:90px minmax(0,1fr) auto"><div>'+pill(x.severity,SEV[x.severity])+'</div><div><b>'+esc(x.title)+'</b> <span class="mu">· '+esc(x.service||'')+'</span><small>Detected '+esc(x.detected)+' · '+esc(x.status)+' '+esc(x.resolved)+' by '+esc(x.by||'—')+'</small>'+(x.note?'<small>Note: '+esc(x.note)+'</small>':'')+'</div><div>'+pill(x.status)+'</div></div>'}).join('')+'</div>':'<div class="mu">No resolved issues yet. History is never deleted.</div>')+'<div class="bar" style="margin-top:8px"><button class="b" id="mClose">Close</button></div>')})}

$('dc').onclick=function(e){var t=e.target.closest?e.target.closest('button,[data-v]'):e.target;if(!t)return;var d=t.dataset||{};
  if(d.wk){WK=WK===d.wk?'':d.wk;render();return}
  if(t.id==='mClose')return closeM();
  if(d.d)return startDeploy(d.d);
  if(t.id==='bRoll'||t.id==='bRoll2'||t.id==='bRoll3')return rollbackModal();
  if(t.id==='bHealth')return runHealth();
  if(t.id==='bRefresh'){say('Checking…');return jf('/admin/deploy/refresh',{method:'POST'}).then(function(j){if(j&&j.history){D=j;T0=Date.now()/1000-D.ts;render()}say('Updated '+D.now)})}
  if(t.id==='bRec'){say('Running recovery check…');return jf('/admin/deploy/recover',{method:'POST'}).then(function(j){if(j.data&&j.data.history){D=j.data;render()}say('');modal('<h2>Recovery Check</h2><table>'+(j.items||[]).map(function(x){return '<tr><td>'+esc(x.name)+'</td><td><span style="color:'+SC[x.state]+';font-weight:800">'+({ok:'✓',warn:'!',fail:'✕',na:'–'}[x.state])+'</span></td><td class="mu">'+esc(x.text)+'</td></tr>'}).join('')+'</table><div class="mu" style="margin-top:6px">Recovery only clears stale deployment state. It never deletes a release or restarts services.</div><div class="bar" style="margin-top:8px"><button class="b" id="mClose">Close</button></div>')})}
  if(d.k)return jf('/admin/deploy/cache',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({mode:d.k})}).then(function(j){say(j.msg||'');reload()});
  if(t.id==='bQr'){modal('<h2>Restart Workers</h2><div class="mu">Sends a restart signal. Workers finish their current job first, so nothing is cut off.</div><div class="bar" style="margin-top:10px"><button class="b" id="mClose">Cancel</button><button class="b pri" id="qGo">Restart</button></div>');$('qGo').onclick=function(){post('/admin/deploy/queue-restart').then(function(j){closeM();say(j.msg||'')})};return}
  if(t.id==='bFj')return jf('/admin/deploy/failed-jobs').then(function(j){modal('<h2>Failed Jobs</h2>'+((j.rows||[]).length?'<table>'+j.rows.map(function(x){return '<tr><td>#'+x.id+'</td><td>'+esc(x.queue)+'</td><td class="mu">'+esc(x.at)+'</td><td>'+esc(x.error)+'</td></tr>'}).join('')+'</table>':'<div class="mu">No failed jobs.</div>')+'<div class="bar" style="margin-top:8px"><button class="b" id="mClose">Close</button></div>')});
  if(t.id==='bMig')return jf('/admin/deploy/migrations').then(function(j){modal('<h2>Migrations</h2><div class="kv"><span>Applied</span><b>'+j.ran+'</b><span>Pending</span><b>'+(j.pending.length?j.pending.length:'None, up to date')+'</b></div>'+(j.pending.length?'<pre>'+esc(j.pending.join('\n'))+'</pre>':'')+'<label class="mu" style="display:block;margin-top:6px">Latest applied</label><pre>'+esc((j.last||[]).join('\n'))+'</pre><div class="bar" style="margin-top:8px"><button class="b" id="mClose">Close</button></div>')});
  if(t.id==='bIssues'||t.id==='bIssH')return t.id==='bIssH'?issueHistory():$('iss').scrollIntoView({behavior:'smooth',block:'start'});
  if(t.id==='bLogs')return logOf(D.stepsFor||(D.history[0]&&D.history[0].id)||0,'Latest deployment log');
  if(t.id==='bCancel')return post('/admin/deploy/cancel').then(function(j){say(j.msg||'');reload()});
  if(t.id==='lgCopy'){var tx=D.log||'';if(navigator.clipboard)navigator.clipboard.writeText(tx);return say('Log copied')}
  if(t.id==='lgDl'){var b=new Blob([D.log||''],{type:'text/plain'}),a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='deployment-log.txt';a.click();return}
  if(t.id==='lgEx'){var p=$('lgPre');p.classList.toggle('big');t.textContent=p.classList.contains('big')?'Collapse':'Expand';return}
  if(d.f){F=d.f;return render()}
  if(d.i)return issueModal(d.i);
  if(d.l)return logOf(d.l,'Logs');
  if(d.e)return logOf(d.e,'Error',true);
  if(d.c)return changes(d.c);
  if(d.r)return post('/admin/deploy/'+d.r+'/retry').then(function(j){say(j.msg||'');reload()});
  if(d.v)return detail(d.v);
};
$('sSave').onclick=function(){post('/admin/deploy/settings',{mode:$('sMode').value,branch:$('sBranch').value,gh_token:$('sGh').value}).then(function(){$('sGh').value='';say('Saved');jf('/admin/deploy/refresh',{method:'POST'}).then(function(j){if(j&&j.history){D=j;render()}})})};
render();
setInterval(function(){if(!document.hidden&&!$('ov').classList.contains('on'))reload()},2500);
})();
</script>
@endverbatim
@endsection
