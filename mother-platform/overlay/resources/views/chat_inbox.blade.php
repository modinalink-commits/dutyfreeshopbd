@extends('layout')@section('title','Messages')
@section('body')
<style>
.mbx{display:grid;grid-template-columns:340px 1fr;gap:14px;height:calc(100vh / var(--z,1) - 150px);min-height:480px}
.mbx .ls,.mbx .cv{background:#fff;border:1px solid var(--bd);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;min-height:0}
.mbx .ls .stk{flex:none;background:#fff;border-bottom:1px solid #FFE7D6;position:relative;z-index:1}
.mbx .fl{display:flex;flex-wrap:wrap;gap:5px;padding:9px 10px 7px}
.mbx .fl a{height:28px;line-height:26px;padding:0 11px;border-radius:15px;font-size:11px;font-weight:600;border:1px solid #FF5A00;color:#C2410C;text-decoration:none;background:#fff;cursor:pointer;white-space:nowrap;box-sizing:border-box;transition:background .15s,color .15s}.mbx .fl a:hover{background:#FFF1E8}.mbx .fl a.on{background:#FF5A00;border-color:#FF5A00;color:#fff}
.mbx .sq{padding:0 10px 9px}.mbx .sq input{height:34px;font-size:12px;border-radius:7px;padding:0 10px;width:100%;box-sizing:border-box}
.mbx .lt{flex:1;overflow:auto;padding:6px 6px 10px}
.mbx .sep{font-size:10.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#6B7280;padding:8px 8px 4px}.mbx .sep.u{color:#B91C1C}
.mbx .th{display:grid;grid-template-columns:34px 1fr;gap:0 10px;padding:9px 10px;margin:0 0 4px;border:1px solid #F1E9E2;border-radius:10px;cursor:pointer;background:#fff;transition:background .15s,border-color .15s;box-sizing:border-box}
.mbx .th:hover{background:#FFFBF7;border-color:#E7D5C7}
.mbx .th.un{background:#FFF8F2;border-color:#FAD3B5;box-shadow:inset 3px 0 0 #FF5A00}.mbx .th.un:hover{background:#FFF1E8;border-color:#F5B98C}
.mbx .th.on{background:#FFF1E8;border-color:#FDBA8C;box-shadow:inset 3px 0 0 #FF5A00}
.mbx .th.fl8{animation:mbfl 1.6s ease-out}@keyframes mbfl{from{background:#FFE3A8}}
.mbx .av{position:relative;width:34px;height:34px}.mbx .av .mcav{width:34px;height:34px;font-size:14px;background:#FFE7D6}.mbx .av .mcb{position:absolute;right:-4px;bottom:-4px;width:16px;height:16px;border-radius:5px;font-size:9px;border:1.5px solid #fff;box-sizing:content-box}
.mbx .bd{min-width:0}.mbx .l1,.mbx .l2,.mbx .l4{display:flex;align-items:center;gap:6px}
.mbx .nm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;font-weight:600;color:#0B1220}.mbx .th.un .nm{font-weight:700}
.mbx .tm{font-size:10.5px;color:#6B7280;font-weight:400;flex:none}.mbx .th.un .tm{color:#C2410C}
.mbx .lm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#6B7280;margin-top:1px}.mbx .th.un .lm{color:#111827;font-weight:600}
.mbx .ub{flex:none;min-width:20px;height:18px;line-height:18px;text-align:center;border-radius:9px;background:#DC2626;color:#fff;font-size:10px;font-weight:700;padding:0 5px;box-sizing:border-box}
.mbx .l3{font-size:10.5px;font-weight:500;color:#6B7280;margin-top:2px}
.mbx .l4{margin-top:4px}.mbx .bg{display:inline-block;height:17px;line-height:17px;border-radius:9px;padding:0 8px;font-size:10px;font-weight:700}
.mbx .bg.new{background:#DBEAFE;color:#1E40AF}.mbx .bg.open{background:#FFEDD5;color:#C2410C}.mbx .bg.waiting{background:#FEF3C7;color:#92400E}.mbx .bg.resolved{background:#DCFCE7;color:#166534}
.mbx .bg.ai{background:#E0F2FE;color:#075985}.mbx .bg.human{background:#F1F5F9;color:#334155}.mbx .bg.need{background:#FEE2E2;color:#B91C1C}
.mbx .emp{padding:40px 20px;text-align:center;color:#6B7280;font-size:13px}
#ibtop h1{font-size:19px;font-weight:700}#ibtop #ibu .ub{display:inline-block;min-width:20px;height:19px;line-height:19px;border-radius:10px;background:#DC2626;color:#fff;font-size:11px;font-weight:700;padding:0 8px;margin-left:6px;box-sizing:border-box;vertical-align:middle}
@media(max-width:900px){.mbx{grid-template-columns:1fr;height:auto}.mbx .cv{height:78vh}.mbx .ls{max-height:75vh}.mbx.sel .ls{display:none}.mbx:not(.sel) .cv{display:none}.mbx.sel .cv{height:calc(100vh - 110px);height:calc(100dvh - 110px)}.mbx .th{min-height:64px}#ibback{min-height:44px}}
@media(prefers-reduced-motion:reduce){.mbx .th,.mbx .fl a{transition:none}.mbx .th.fl8{animation:none}}
#ibback{display:none}body.ibsel #ibback{display:inline-block}
@if(request()->boolean('embed'))
.mbx{display:block;height:calc(100vh / var(--z,1) - 20px);min-height:0}.mbx .ls,.mbx .cv{height:100%}.mbx .cv{display:none}
.mbx.sel .ls{display:none}.mbx.sel .cv{display:flex}body.ibsel .mbx{height:calc(100vh / var(--z,1) - 62px)}
#ibtop{display:none!important}#ibback{margin:0 0 6px;padding:6px 12px;border-radius:9px;border:1.5px solid #0B1220;background:#fff;color:#0B1220;font-weight:700;font-size:12.5px;cursor:pointer}
@endif
</style>
<div id="ibtop" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1"><h1 style="margin:0">Messages <span id="ibu"></span></h1><span style="font-size:12px;color:#6B7280">Facebook, Instagram, WhatsApp and Website Chat in one inbox.</span></div>
<button class="btn g" type="button" id="ibm">🔔 Sound on</button><a class="btn g" href="/admin/chat-settings">Chat settings</a></div>
<button type="button" id="ibback">← All conversations</button>
<div class="mbx" id="ibx"><div class="ls"><div class="stk"><div class="fl" id="ibf"></div><div class="sq"><input id="ibq" type="search" placeholder="Search name, mobile, invoice, message..."></div></div><div class="lt" id="ibl"></div></div>
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
var seen={},first=true;
function row(t){var st=t.needs_human?'waiting':(t.status||'open');var stL={new:'New',open:'Open',waiting:'Waiting',resolved:'Resolved'}[st]||st;
  var h=t.needs_human?'<span class="bg need" title="'+esc(t.handoff_reason||'')+'">Needs human</span>':'<span class="bg '+(t.ai?'ai':'human')+'">'+(t.ai?'AI':'Human')+'</span>';
  var un=t.unread>0&&t.status!=='resolved';
  return '<div class="th'+(un?' un':'')+(sel===t.id?' on':'')+'" data-id="'+t.id+'"><div class="av"><span class="mcav">'+(t.avatar?'<img src="'+esc(t.avatar)+'" alt="" referrerpolicy="no-referrer" onerror="this.remove()">':'')+esc((t.name||'?').charAt(0).toUpperCase())+'</span><span class="mcb '+t.channel+'">'+CHL[t.channel]+'</span></div>'
  +'<div class="bd"><div class="l1"><span class="nm">'+esc(t.name)+'</span><span class="tm">'+ago(t.last_at)+'</span></div>'
  +'<div class="l2"><span class="lm">'+(t.last_dir==='out'?'You: ':'')+esc(t.last||'')+'</span>'+(un?'<span class="ub">'+t.unread+'</span>':'')+'</div>'
  +'<div class="l3">'+esc(t.cat)+' · '+t.orders+' order'+(t.orders===1?'':'s')+'</div>'
  +'<div class="l4"><span class="bg '+st+'">'+stL+'</span>'+h+(t.assigned_name?'<span class="bg human" title="Assigned to">👤 '+esc(t.assigned_name)+'</span>':'<span class="bg" style="color:#9CA3AF">Unassigned</span>')+'</div></div></div>'}
function load(){fetch('/admin/chat/threads?f='+f+'&q='+encodeURIComponent(q),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  var u=document.getElementById('ibu');u.innerHTML=j.unread?'<span class="ub">'+j.unread+' unread</span>':'';if(window.MChat)MChat.badge(j.unread);
  var l=document.getElementById('ibl');if(!j.threads.length){l.innerHTML='<div class="emp">No conversations here yet.</div>';return}
  var ur=j.threads.filter(function(t){return t.unread>0&&t.status!=='resolved'}),rd=j.threads.filter(function(t){return !(t.unread>0&&t.status!=='resolved')});
  var top=l.scrollTop,html='';
  if(ur.length){html+='<div class="sep u">Unread — '+ur.length+'</div>'+ur.map(row).join('');if(rd.length)html+='<div class="sep">Read</div>'}
  html+=rd.map(row).join('');l.innerHTML=html;l.scrollTop=top;
  if(!first)l.querySelectorAll('.th.un').forEach(function(n){var id=n.dataset.id,t=j.threads.filter(function(x){return String(x.id)===id})[0];if(t&&(seen[id]===undefined||t.unread>seen[id]))n.classList.add('fl8')});
  first=false;j.threads.forEach(function(t){seen[t.id]=t.unread});
  l.querySelectorAll('.th').forEach(function(n){n.onclick=function(){sel=+n.dataset.id;l.querySelectorAll('.th').forEach(function(x){x.classList.toggle('on',x===n)});n.classList.remove('un');document.getElementById('ibx').classList.add('sel');document.body.classList.add('ibsel');MChat.mount(document.getElementById('ibc'),sel,{onchange:load,focus:true})}})})}
document.getElementById('ibq').oninput=function(){clearTimeout(tmr);q=this.value;tmr=setTimeout(load,300)};
var bm=document.getElementById('ibm');function mm(){bm.textContent=MChat.soundLabel?MChat.soundLabel():(MChat.mute?'🔕 Sound off':'🔔 Sound on')}bm.onclick=function(){MChat.cycleSound();mm()};
document.getElementById('ibback').onclick=function(){document.getElementById('ibx').classList.remove('sel');document.body.classList.remove('ibsel');sel=null;load()};
drawF();load();mm();setInterval(function(){if(!document.hidden)load()},8000);
var wait=setInterval(function(){if(window.MChat){clearInterval(wait);MChat.onNew=load}},300);
var oid=new URLSearchParams(location.search).get('open');if(oid){var w2=setInterval(function(){if(window.MChat){clearInterval(w2);sel=+oid;MChat.mount(document.getElementById('ibc'),sel,{onchange:load})}},300)}
})();
</script>
@endverbatim
@endsection
