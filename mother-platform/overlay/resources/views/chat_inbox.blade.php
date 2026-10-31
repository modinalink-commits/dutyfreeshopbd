@extends('layout')@section('title','Messages')
@section('body')
<style>
.ib{display:grid;grid-template-columns:360px 1fr;gap:14px;height:calc(100vh - 150px);min-height:480px}
.ib .ls,.ib .cv{background:#fff;border:1px solid var(--bd);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;min-height:0}
.ib .fl{display:flex;flex-wrap:wrap;gap:5px;padding:10px;border-bottom:1px solid #FFE7D6}
.ib .fl a{padding:5px 10px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid var(--bd);color:#0B1220;text-decoration:none;background:#fff;cursor:pointer}.ib .fl a.on{background:#FF5A00;border-color:#FF5A00;color:#fff}
.ib .lt{flex:1;overflow:auto}.ib .th{display:flex;gap:10px;padding:10px 12px;border-bottom:1px solid #FFF0E6;cursor:pointer;align-items:flex-start}.ib .th:hover{background:#FFF8F3}.ib .th.on{background:#FFF1E8}
.ib .th b{font-size:13.5px}.ib .th .lm{font-size:12.5px;color:#4B5563;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:215px}.ib .th small{color:#6B7280;font-size:11px}
.ib .th .un{background:#E11D48;color:#fff;border-radius:20px;font-size:11px;padding:1px 7px;font-weight:700}
.ib .sc{display:inline-block;font-size:10.5px;border-radius:6px;padding:1px 6px;background:#FFE7D6;color:#C2410C;font-weight:600}.ib .sc.resolved{background:#DCFCE7;color:#166534}.ib .sc.waiting{background:#FEF3C7;color:#92400E}.ib .sc.new{background:#E0E7FF;color:#3730A3}
.ib .emp{padding:40px 20px;text-align:center;color:#6B7280;font-size:13px}
@media(max-width:900px){.ib{grid-template-columns:1fr;height:auto}.ib .cv{height:78vh}}
</style>
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1"><h1 style="margin:0">Messages <span id="ibu" style="font-size:13px;vertical-align:middle"></span></h1><span style="font-size:13px;color:#6B7280">Facebook, Instagram, WhatsApp and website chat in one inbox.</span></div>
<button class="btn g" type="button" id="ibm">🔔 Sound on</button><a class="btn g" href="/admin/chat-settings">Chat settings</a></div>
<div class="ib"><div class="ls"><div class="fl" id="ibf"></div><div style="padding:8px 10px;border-bottom:1px solid #FFE7D6"><input id="ibq" type="search" placeholder="Search name, mobile, message…" style="padding:7px 10px;font-size:13px"></div><div class="lt" id="ibl"></div></div>
<div class="cv" id="ibc"><div class="emp" style="margin:auto">Select a conversation to read and reply.<br>New messages appear here and as a popup anywhere in the dashboard.</div></div></div>
@endsection
@section('js')
@verbatim
<script>
(function(){
var F=[['all','All'],['facebook','Facebook'],['instagram','Instagram'],['whatsapp','WhatsApp'],['web','Website Chat'],['unread','Unread'],['assigned','Assigned to me'],['resolved','Resolved']];
var f=new URLSearchParams(location.search).get('f')||'all',q='',sel=null,tmr;
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function ago(iso){if(!iso)return '';var s=(Date.now()-new Date(iso).getTime())/1000;if(s<60)return 'now';if(s<3600)return Math.floor(s/60)+'m';if(s<86400)return Math.floor(s/3600)+'h';return new Date(iso).toLocaleDateString([], {day:'numeric',month:'short'})}
var CHL={facebook:'f',instagram:'IG',whatsapp:'W',web:'💬'};
function drawF(){document.getElementById('ibf').innerHTML=F.map(function(x){return '<a data-f="'+x[0]+'" class="'+(f===x[0]?'on':'')+'">'+x[1]+'</a>'}).join('');document.querySelectorAll('#ibf a').forEach(function(a){a.onclick=function(){f=a.dataset.f;drawF();load()}})}
function load(){fetch('/admin/chat/threads?f='+f+'&q='+encodeURIComponent(q),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  var u=document.getElementById('ibu');u.innerHTML=j.unread?'<span class="un" style="background:#E11D48;color:#fff;border-radius:20px;padding:1px 9px;font-size:12px">'+j.unread+' unread</span>':'';if(window.MChat)MChat.badge(j.unread);
  var l=document.getElementById('ibl');if(!j.threads.length){l.innerHTML='<div class="emp">No conversations here yet.</div>';return}
  l.innerHTML=j.threads.map(function(t){return '<div class="th'+(sel===t.id?' on':'')+'" data-id="'+t.id+'"><span class="mcav">'+(t.avatar?'<img src="'+esc(t.avatar)+'" alt="" referrerpolicy="no-referrer" onerror="this.remove()">':'')+esc((t.name||'?').charAt(0).toUpperCase())+'</span><div style="flex:1;min-width:0"><div style="display:flex;gap:6px;align-items:center"><span class="mcb '+t.channel+'">'+CHL[t.channel]+'</span><b style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(t.name)+'</b><small>'+ago(t.last_at)+'</small></div><div class="lm">'+(t.last_dir==='out'?'You: ':'')+esc(t.last||'')+'</div><div style="display:flex;gap:5px;align-items:center;margin-top:3px"><span class="sc '+t.status+'">'+t.status+'</span><small>'+esc(t.cat)+' · '+t.orders+' orders'+(t.assigned_name?' · '+esc(t.assigned_name):'')+'</small>'+(t.unread?'<span class="un" style="margin-left:auto">'+t.unread+'</span>':'')+'</div></div></div>'}).join('');
  l.querySelectorAll('.th').forEach(function(n){n.onclick=function(){sel=+n.dataset.id;l.querySelectorAll('.th').forEach(function(x){x.classList.toggle('on',x===n)});MChat.mount(document.getElementById('ibc'),sel,{onchange:load,focus:true})}})})}
document.getElementById('ibq').oninput=function(){clearTimeout(tmr);q=this.value;tmr=setTimeout(load,250)};
var bm=document.getElementById('ibm');function mm(){bm.textContent=MChat.mute?'🔕 Sound off':'🔔 Sound on'}bm.onclick=function(){MChat.toggleMute();mm()};
drawF();load();mm();setInterval(function(){if(!document.hidden)load()},8000);
var wait=setInterval(function(){if(window.MChat){clearInterval(wait);MChat.onNew=load}},300);
var oid=new URLSearchParams(location.search).get('open');if(oid){var w2=setInterval(function(){if(window.MChat){clearInterval(w2);sel=+oid;MChat.mount(document.getElementById('ibc'),sel,{onchange:load})}},300)}
})();
</script>
@endverbatim
@endsection
