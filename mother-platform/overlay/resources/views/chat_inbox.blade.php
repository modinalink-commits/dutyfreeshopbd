@extends('layout')@section('title','Messages')
@section('body')
<style>@import url('https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap');</style>
<style>
.mbx{display:grid;grid-template-columns:340px 1fr;gap:14px;height:calc(100vh / var(--z,1) - 150px);min-height:480px}
.mbx .ls,.mbx .cv{background:#fff;border:1px solid var(--bd);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;min-height:0}
.mbx .ls .stk{flex:none;background:#fff;border-bottom:1px solid #FFE7D6;position:relative;z-index:1}
.fbar{background:#fff;border:1px solid #FDD9C2;border-radius:18px;box-shadow:0 8px 24px rgba(255,90,0,.06);padding:10px 12px;margin:0 0 12px;display:flex;flex-direction:column;gap:8px}
.fbar .fr{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.fbar .fr .sp{flex:1}
.fbar a,.fbar button.fa{display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 15px;border-radius:19px;border:1px solid #E5E7EB;background:#fff;color:#374151;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;white-space:nowrap;box-sizing:border-box;box-shadow:0 2px 6px rgba(15,23,42,.05);transition:background .15s,box-shadow .15s,transform .1s;font-family:inherit}
.fbar a:hover,.fbar button.fa:hover{background:#FFF1E8;box-shadow:0 4px 10px rgba(15,23,42,.09)}
.fbar a svg,.fbar button.fa svg{width:19px;height:19px;flex:none}
.fbar a.on{background:linear-gradient(135deg,#FF7A1A,#FF5A00);border-color:#FF5A00;color:#fff;box-shadow:0 6px 14px rgba(240,82,0,.32)}.fbar a.on svg *{stroke:#fff}.fbar a.on svg .kp{fill:#fff}
.fbar .cn{min-width:20px;height:20px;line-height:20px;border-radius:10px;background:#B91C1C;color:#fff;font-size:11px;font-weight:700;text-align:center;padding:0 6px;box-sizing:border-box}
.fbar a.on .cn{background:#fff;color:#C2410C}
.fbar button.fa:disabled{opacity:.7;cursor:default}.fbar button.fa.ok{color:#166534;border-color:#BBF7D0;background:#DCFCE7}
.mbx .stk{border-bottom:0}
@media(max-width:900px){.fbar .fr{flex-wrap:wrap}.fbar a,.fbar button.fa{height:42px;padding:0 13px}.fbar .fr .sp{display:none}}
.mbx .st2{display:inline-flex;align-items:center;height:17px;line-height:17px;border-radius:9px;padding:0 8px;font-size:10px;font-weight:700}.mbx .st2.aw{background:#FFF1E8;color:#C2410C}.mbx .st2.rp{background:#DCFCE7;color:#166534}.mbx .st2.old{background:#F3F4F6;color:#6B7280}
.mbx .sq{padding:0 10px 9px}.mbx .sq input{height:34px;font-size:12px;border-radius:7px;padding:0 10px;width:100%;box-sizing:border-box}
.mbx .lt{flex:1;overflow:auto;padding:6px 6px 10px}
.mbx .sep{font-size:10.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#6B7280;padding:8px 8px 4px}.mbx .sep.u{color:#B91C1C}
.mbx .th{display:grid;grid-template-columns:34px 1fr;gap:0 10px;padding:9px 10px;margin:0 0 4px;border:1px solid #F1E9E2;border-radius:10px;cursor:pointer;background:#fff;transition:background .15s,border-color .15s;box-sizing:border-box}
.mbx .th:hover{background:#FFF1E8;border-color:#E7D5C7}
.mbx .th.un{background:#FFF1E8;border-color:#FAD3B5;box-shadow:inset 3px 0 0 #FF5A00}.mbx .th.un:hover{background:#FFF1E8;border-color:#F5B98C}
.mbx .th.on{background:#FFF1E8;border-color:#FDBA8C;box-shadow:inset 3px 0 0 #FF5A00}
.mbx .th.fl8{animation:mbfl 1.6s ease-out}@keyframes mbfl{from{background:#FFE3A8}}
.mbx .av{position:relative;width:34px;height:34px}.mbx .av .mcav{width:34px;height:34px;font-size:14px;background:#FFF1E8}.mbx .av .mcb{position:absolute;right:-4px;bottom:-4px;width:16px;height:16px;border-radius:5px;font-size:9px;border:1.5px solid #fff;box-sizing:content-box}
.mbx .bd{min-width:0}.mbx .l1,.mbx .l2,.mbx .l4{display:flex;align-items:center;gap:6px}
.mbx .nm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;font-weight:600;color:#0B1220}.mbx .th.un .nm{font-weight:700}
.mbx .tm{font-size:10.5px;color:#6B7280;font-weight:400;flex:none}.mbx .th.un .tm{color:#C2410C}
.mbx .lm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#6B7280;margin-top:1px}.mbx .th.un .lm{color:#0B1220;font-weight:600}
.mbx .ub{flex:none;min-width:20px;height:18px;line-height:18px;text-align:center;border-radius:9px;background:#B91C1C;color:#fff;font-size:10px;font-weight:700;padding:0 5px;box-sizing:border-box}
.mbx .l3{font-size:10.5px;font-weight:500;color:#6B7280;margin-top:2px}
.mbx .l4{margin-top:4px}.mbx .bg{display:inline-block;height:17px;line-height:17px;border-radius:9px;padding:0 8px;font-size:10px;font-weight:700}
.mbx .bg.new{background:#E6EFF9;color:#1D5FA8}.mbx .bg.open{background:#FFF1E8;color:#C2410C}.mbx .bg.waiting{background:#FEF3C7;color:#B45309}.mbx .bg.resolved{background:#DCFCE7;color:#166534}
.mbx .bg.ai{background:#E0F2FE;color:#075985}.mbx .bg.human{background:#F3F4F6;color:#4B5563}.mbx .bg.need{background:#FEE2E2;color:#B91C1C}
.mbx .emp{padding:40px 20px;text-align:center;color:#6B7280;font-size:13px}
#ibtop h1{font-size:19px;font-weight:700}#ibtop #ibu .ub{display:inline-block;min-width:20px;height:19px;line-height:19px;border-radius:10px;background:#B91C1C;color:#fff;font-size:11px;font-weight:700;padding:0 8px;margin-left:6px;box-sizing:border-box;vertical-align:middle}
@media(max-width:900px){.mbx{grid-template-columns:1fr;height:auto}.mbx .cv{height:78vh}.mbx .ls{max-height:75vh}.mbx.sel .ls{display:none}.mbx:not(.sel) .cv{display:none}.mbx.sel .cv{height:calc(100vh - 110px);height:calc(100dvh - 110px)}.mbx .th{min-height:64px}#ibback{min-height:44px}}
@media(prefers-reduced-motion:reduce){.mbx .th,.mbx .fl a{transition:none}.mbx .th.fl8{animation:none}}

/* ===== premium inbox list ===== */
.mbx{grid-template-columns:330px 1fr;gap:12px}
.mbx .ls,.mbx .cv{border-radius:12px;border-color:#EFE7E0}
.mbx .stk .tp{padding:10px 10px 0}
#iball{display:inline-flex;align-items:center;gap:6px;height:30px;padding:0 13px;border-radius:999px;border:0;background:linear-gradient(135deg,#FF7A1A,#FF5A00);color:#fff;font-size:12px;font-weight:700;cursor:pointer;box-shadow:0 3px 8px rgba(240,82,0,.25)}
.mbx .sq input{height:36px;border-radius:8px;border:1px solid #E5E7EB;font-size:12.5px;background:#fff}
.mbx .sep{display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600;letter-spacing:0;text-transform:none;color:#0B1220;padding:10px 8px 5px}.mbx .sep.u{color:#0B1220}.mbx .sep b{color:#FF5A00;font-weight:700;margin-left:auto}
.mbx .sep.u::before{content:"";width:8px;height:8px;border-radius:50%;background:#FF5A00;flex:none}
.mbx .th{padding:9px 10px;margin:0 0 5px;border-radius:10px;border-color:#F1E9E2;gap:0 10px}
.mbx .th.un{background:#fff;border-color:#F1E9E2;box-shadow:inset 3px 0 0 #FF5A00}.mbx .th.un:hover{background:#FFF1E8}
.mbx .th.on{background:#FFF1E8;border-color:#FDBA8C;box-shadow:inset 3px 0 0 #FF5A00}
.mbx .nm{font-size:13.5px;font-weight:600}.mbx .th.un .nm{font-weight:700}
.mbx .lm{font-size:11.5px;color:#6B7280}.mbx .th.un .lm{color:#0B1220;font-weight:600}
.mbx .l3{font-size:11px;color:#6B7280;margin-top:3px}.srcb{display:inline-block;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom;padding:0 6px;border-radius:6px;font-size:10px;font-weight:600;background:#E6EFF9;color:#5B3FA6}.srcb.s-whatsapp{background:#DCFCE7;color:#166534}.srcb.s-facebook{background:#E6EFF9;color:#1D5FA8}.srcb.s-instagram{background:#FCE7F3;color:#9D174D}
.mbx .l4{flex-wrap:wrap;gap:4px;margin-top:5px}
.mbx .bg,.mbx .st2{height:18px;line-height:18px;padding:0 8px;font-size:10.5px;font-weight:600}
.mbx .st2.aw{background:#FFF1E8;border:1px solid #FDBA8C;color:#C2410C;line-height:16px}
.mbx .bg.open,.mbx .bg.new,.mbx .bg.waiting{background:#FFF1E8;color:#C2410C}.mbx .bg.ai{background:#EAF4FF;color:#1E5FA8}.mbx .bg.human{background:#F3F4F6;color:#4B5563}
.mbx .ub{min-width:20px;height:20px;line-height:20px;border-radius:50%;padding:0;font-size:11px}
.mbx .av .mcb.ico{background:#fff!important;border-radius:50%;width:15px;height:15px;padding:0;right:-3px;bottom:-3px}
.mbx .cv .mc .ms{padding:14px 18px}
@media(max-width:900px){.mbx{grid-template-columns:1fr}}
#ibback{display:none}@media(max-width:900px){body.ibsel #ibback{display:inline-block}}
@if(request()->boolean('embed'))
.mbx{display:block;height:calc(100vh / var(--z,1) - 20px);min-height:0}.mbx .ls,.mbx .cv{height:100%}.mbx .cv{display:none}
.mbx.sel .ls{display:none}.mbx.sel .cv{display:flex}body.ibsel .mbx{height:calc(100vh / var(--z,1) - 62px)}
#ibtop{display:none!important}#ibback{margin:0 0 6px;padding:6px 12px;border-radius:9px;border:1.5px solid #0B1220;background:#fff;color:#0B1220;font-weight:700;font-size:12.5px;cursor:pointer}
@endif
/* Owner request 11 Nov 2026: Bornomala for Bangla (self-hosted), calmer weights, one readable size scale across list, chat and banners */
@font-face{font-family:'Bornomala';src:url('/fonts/Bornomala-Regular.ttf') format('truetype');font-weight:400 500;font-style:normal;font-display:swap;unicode-range:U+0980-09FF,U+200C-200D,U+0964-0965}
@font-face{font-family:'Bornomala';src:url('/fonts/Bornomala-Bold.ttf') format('truetype');font-weight:600 800;font-style:normal;font-display:swap;unicode-range:U+0980-09FF,U+200C-200D,U+0964-0965}
.mbx,.mbx *,.mbx .mc textarea,.mbx .mc input{font-family:'Bornomala',Inter,'Segoe UI',system-ui,Arial,sans-serif}
.mbx .mc .b{font-size:16px;line-height:1.65;padding:9px 14px;max-width:78%;font-weight:400}
.mbx .mc .b small{font-size:11.5px}
.mbx .mc .cm textarea,.mbx .mc .cm input[type=text]{font-size:16px}
.mbx .nm{font-size:16px;font-weight:400}.mbx .th.un .nm{font-weight:700}
.mbx .lm{font-size:14.5px;line-height:1.5;font-weight:400}.mbx .th.un .lm{font-weight:400;color:#0B1220}
.mbx .tm{font-size:12.5px}.mbx .l3{font-size:12.5px}.mbx .sep{font-size:14.5px;font-weight:700}
.mbx .bg,.mbx .st2{font-size:12px;height:21px;line-height:21px;font-weight:700}.mbx .sq input{font-size:14.5px;height:40px}.mbx .ub{font-size:12px}
.mbx .mc .hd .r1 b{font-size:16px;font-weight:700}.mbx .mc .hd .r1 small{font-size:12.5px}
.mbx .mc .lk,.mbx .mc .aw,.mbx .mc .rp,.mbx .mc .hd select{font-size:13px}
.mbx .mc .cb summary{font-size:14px}.mbx .mc .cb .ch{font-size:12.5px}
.mbx .mc .cm .tb .tg,.mbx .mc .cm .tb .qrw select{font-size:13.5px}
.mbx .mc .hb{font-size:14.5px!important;line-height:1.55!important}.mbx .mc .sumb{font-size:14px!important;line-height:1.55!important}.mbx .mc .sumb b{font-size:11px!important}
/* Owner request 10 Nov 2026 (desktop): the Messages page is ONE fixed screen - no page up/down scrolling; only the conversation list and the open chat scroll inside their own panels. Fits the viewport at any zoom. */
@media(min-width:901px){
html,body{height:calc(100vh / var(--z,1));overflow:hidden!important}
body{min-height:0!important}
main{height:calc(100vh / var(--z,1));overflow:hidden;display:flex;flex-direction:column;padding-top:12px!important;padding-bottom:10px!important}
#ibtop{margin-bottom:6px!important}#ibtop h1{font-size:20px}#ibtop span[style]{display:none}
.mbx{flex:1;min-height:0!important;height:auto!important}
.mbx .ls{max-height:none;height:100%;min-height:0;overflow:auto}
.mbx .cv{height:100%!important;min-height:0}
}
</style>
<div id="ibtop" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1"><h1 style="margin:0">Messages <span id="ibu"></span></h1><span style="font-size:12px;color:#6B7280">Facebook, Instagram, WhatsApp and Website Chat in one inbox.</span></div>
</div>
<div class="fbar" id="ibfb"><div class="fr" id="ibf1"></div><div class="fr" id="ibf2"></div></div>
<button type="button" id="ibback">← All conversations</button>
<div class="mbx" id="ibx"><div class="ls"><div class="stk"><div class="tp"><button type="button" id="iball">← All conversations</button></div><div class="sq" style="padding-top:9px"><input id="ibq" type="search" placeholder="Search name, mobile, invoice, message..."></div></div><div class="lt" id="ibl"></div></div>
<div class="cv" id="ibc"><div class="emp" style="margin:auto">Select a conversation to read and reply.<br>New messages appear here and as a popup anywhere in the dashboard.</div></div></div>
@endsection
@section('js')
@verbatim
<script>
(function(){
var I={
all:'<svg viewBox="0 0 24 24" fill="none" stroke="#FF5A00" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/></svg>',
facebook:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#1877F2"/><path class="kp" d="M13.2 20v-7h2.3l.4-2.8h-2.7V8.5c0-.8.3-1.3 1.4-1.3H16V4.7c-.3 0-1.1-.1-2.1-.1-2.1 0-3.5 1.3-3.5 3.6v2H8v2.8h2.4v7" fill="#fff"/></svg>',
instagram:'<svg viewBox="0 0 24 24"><defs><linearGradient id="ig" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#FEC053"/><stop offset=".4" stop-color="#F2203E"/><stop offset=".7" stop-color="#B729A8"/><stop offset="1" stop-color="#5342D6"/></linearGradient></defs><rect x="2" y="2" width="20" height="20" rx="6" fill="none" stroke="url(#ig)" stroke-width="2.2"/><circle cx="12" cy="12" r="4.4" fill="none" stroke="url(#ig)" stroke-width="2.2"/><circle cx="17.4" cy="6.6" r="1.3" fill="#B729A8"/></svg>',
whatsapp:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#25D366"/><path class="kp" d="M16.7 14.3c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1-1.4-.7-2.4-1.5-3.2-3-.1-.2 0-.3.1-.4l.5-.6.2-.4c0-.1 0-.3 0-.4l-.7-1.7c-.2-.4-.4-.4-.6-.4h-.4c-.2 0-.4.1-.6.3-.6.6-.9 1.4-.8 2.3.2 1.1.8 2.1 1.5 3 1.1 1.4 2.5 2.4 4.2 3 .8.2 1.5.2 2.2-.1.6-.3 1.1-.9 1.2-1.5 0-.2 0-.3-.1-.4l-.4-.1Z" fill="#fff"/></svg>',
web:'<svg viewBox="0 0 24 24" fill="none" stroke="#FF5A00" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><path d="M21 12a8 8 0 0 1-11.8 7L4 20l1.2-4.4A8 8 0 1 1 21 12Z"/><circle cx="9" cy="12" r=".6" fill="#FF5A00"/><circle cx="12.2" cy="12" r=".6" fill="#FF5A00"/><circle cx="15.4" cy="12" r=".6" fill="#FF5A00"/></svg>',
orders:'<svg viewBox="0 0 24 24" fill="none" stroke="#4B5563" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h5"/></svg>',
  unread:'<svg viewBox="0 0 24 24" fill="none" stroke="#4B5563" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/></svg>',
assigned:'<svg viewBox="0 0 24 24" fill="none" stroke="#4B5563" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 20c.8-3.6 3.8-5.5 7.5-5.5s6.7 1.9 7.5 5.5"/></svg>',
resolved:'<svg viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="1.9" stroke-linejoin="round" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="m8 12.3 2.6 2.6L16.2 9"/></svg>'};
var F1=[['all','All'],['facebook','Facebook'],['instagram','Instagram'],['whatsapp','WhatsApp'],['web','Website Chat']],F2=[['unread','Unread'],['assigned','Assigned to me'],['resolved','Resolved'],['orders','Order Requests']],waiting=0,orq=0;
var f=new URLSearchParams(location.search).get('f')||'all',q='',sel=null,tmr;
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function ago(iso){if(!iso)return '';var s=(Date.now()-new Date(iso).getTime())/1000;if(s<60)return 'now';if(s<3600)return Math.floor(s/60)+'m';if(s<86400)return Math.floor(s/3600)+'h';return new Date(iso).toLocaleDateString([], {day:'numeric',month:'short'})}
var CHL={facebook:'f',instagram:'IG',whatsapp:'W',web:'💬'};
function pill(x){return '<a data-f="'+x[0]+'" class="'+(f===x[0]?'on':'')+'" role="button" tabindex="0">'+I[x[0]]+'<span>'+x[1]+'</span>'+(x[0]==='unread'&&waiting>0?'<span class="cn">'+waiting+'</span>':'')+(x[0]==='orders'&&orq>0?'<span class="cn">'+orq+'</span>':'')+'</a>'}
function drawF(){document.getElementById('ibf1').innerHTML=F1.map(pill).join('');
  document.getElementById('ibf2').innerHTML=F2.map(pill).join('')+'<span class="sp"></span><button class="fa" type="button" id="ibup" title="Fetch the latest messages. This never marks anything as seen.">↻ Update</button><button class="fa" type="button" id="ibm">🔔 Sound on</button><a href="/admin/chat/memory" style="height:38px;border-color:#FF5A00;color:#C2410C">🧠 AI Memory</a><a href="/admin/chat-settings" style="height:38px">Chat settings</a>';
  document.querySelectorAll('#ibfb a[data-f]').forEach(function(a){a.onclick=function(){f=a.dataset.f;drawF();load()};a.onkeydown=function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();a.click()}}});
  var bm=document.getElementById('ibm');bm.onclick=function(){MChat.cycleSound();mm()};mm();
  document.getElementById('ibup').onclick=doUpdate}
/* Manual synchronisation only: refreshes the list and the open conversation. It never marks anything as seen or replied. */
var upBusy=false;function doUpdate(){if(upBusy)return;upBusy=true;var b=document.getElementById('ibup');b.disabled=true;b.textContent='↻ Updating…';
  Promise.all([load(),(window.MChat&&MChat.refresh)?MChat.refresh():null]).catch(function(){}).then(function(){b=document.getElementById('ibup');if(b){b.textContent='✓ Updated';b.classList.add('ok')}setTimeout(function(){upBusy=false;var c=document.getElementById('ibup');if(c){c.disabled=false;c.classList.remove('ok');c.textContent='↻ Update'}},1400)})}
var seen={},first=true;
function row(t){var st=t.needs_human?'waiting':(t.status||'open');var stL={new:'New',open:'Open',waiting:'Waiting',resolved:'Resolved'}[st]||st;
  var h=t.needs_human?'<span class="bg need" title="'+esc(t.handoff_reason||'')+'">Needs human</span>':'<span class="bg '+(t.ai?'ai':'human')+'">'+(t.ai?'AI':'Human')+'</span>';
  var un=!!t.awaiting&&t.status!=='resolved',ico=(window.MChat&&MChat.ico&&MChat.ico[t.channel])||CHL[t.channel];
  return '<div class="th'+(un?' un':'')+(sel===t.id?' on':'')+'" data-id="'+t.id+'"><div class="av"><span class="mcav">'+(t.avatar?'<img src="'+esc(t.avatar)+'" alt="" referrerpolicy="no-referrer" onerror="this.remove()">':'')+esc((t.name||'?').charAt(0).toUpperCase())+'</span><span class="mcb ico '+t.channel+'" title="'+esc(t.channel_name)+'">'+ico+'</span></div>'
  +'<div class="bd"><div class="l1"><span class="nm">'+esc(t.name)+'</span><span class="tm">'+ago(t.last_at)+'</span></div>'
  +'<div class="l2"><span class="lm">'+(t.last_dir==='out'?'You: ':'')+esc(t.last||'')+'</span>'+(un?'<span class="ub">'+t.unread+'</span>':'')+'</div>'
  +'<div class="l3">'+esc(t.channel_name)+(t.source?' <span class="srcb s-'+t.channel+'" title="Received on: '+esc(t.source)+'">'+esc(t.source)+'</span>':'')+' · Holding: '+(t.orders>0?t.orders+' order'+(t.orders===1?'':'s'):'--')+'</div>'
  +'<div class="l4">'+(un&&window.MSla?MSla.chip(t):'')+(un?'<span class="st2 aw">Awaiting</span>':(t.replied?'<span class="st2 rp">✓ Replied</span>':(t.stale?'<span class="st2 old" title="Customer message older than 7 days">Old</span>':'')))+'<span class="bg '+st+'">'+stL+'</span>'+h+(t.assigned_name?'<span class="bg human" title="Assigned to">👤 '+esc(t.assigned_name)+'</span>':'<span class="bg" style="color:#9CA3AF">Unassigned</span>')+'</div></div></div>'}
function money(n){return '৳'+Math.round(n).toLocaleString('en-US')}
function loadOrq(){return fetch('/admin/chat/order-requests?s=review',{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){var l=document.getElementById('ibl');orq=j.requests.length;
  if(!j.requests.length){l.innerHTML='<div class="emp">No order requests waiting.</div>';return}
  l.innerHTML=j.requests.map(function(x){return '<div class="th" style="display:block;cursor:default" data-r="'+x.id+'"><div class="l1"><span class="nm">'+esc(x.name)+' · '+esc(x.phone)+'</span><span class="tm">'+ago(x.at)+'</span></div><div class="l3">'+esc(x.channel||'')+' · '+esc(x.address)+'</div><div style="margin:6px 0;font-size:13px">'+(x.items||[]).map(function(i){return esc(i.name)+' × '+i.qty}).join('<br>')+'<br><b>'+money(x.total)+'</b></div><div class="l3" style="color:#B45309">Why a request: '+esc(x.reason||'staff decision')+'</div><div style="display:flex;gap:6px;margin-top:8px"><button class="fa" data-a="create" type="button">Create order</button><button class="fa" data-a="chat" type="button">Open chat</button><button class="fa" data-a="dismiss" type="button">Dismiss</button></div></div>'}).join('');
  l.querySelectorAll('[data-r]').forEach(function(n){var id=n.dataset.r,x=j.requests.filter(function(q){return String(q.id)===id})[0];n.querySelectorAll('button').forEach(function(b){b.onclick=function(){var a=b.dataset.a;
    if(a==='chat'){f='all';sel=x.thread_id;drawF();document.getElementById('ibx').classList.add('sel');document.body.classList.add('ibsel');MChat.mount(document.getElementById('ibc'),sel,{onchange:load,focus:true});load();return}
    if(a==='create'&&!confirm('Create the real order for '+x.name+' ('+money(x.total)+')?'))return;if(a==='dismiss'&&!confirm('Dismiss this order request?'))return;
    b.disabled=true;var tok=(document.querySelector('meta[name=csrf-token]')||{}).content||'';
    fetch('/admin/chat/order-requests/'+id+'/'+a,{method:'POST',headers:{'X-CSRF-TOKEN':tok,'Accept':'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){if(j.ok){if(a==='create')alert('Order created: '+j.invoice);loadOrq();drawF()}else{alert(j.error||'Failed');b.disabled=false}}).catch(function(){alert('Failed');b.disabled=false})}})})})}
function load(){if(f==='orders')return loadOrq();return fetch('/admin/chat/threads?f='+f+'&q='+encodeURIComponent(q),{headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  if((j.order_requests||0)!==orq){orq=j.order_requests||0;drawF()}var u=document.getElementById('ibu');u.innerHTML=j.unread?'<span class="ub">'+j.unread+' waiting</span>':'';if(window.MChat)MChat.badge(j.unread);
  if(waiting!==j.unread){waiting=j.unread;var uf=document.querySelector('#ibfb a[data-f=unread]');if(uf){var cn=uf.querySelector('.cn');if(waiting>0){if(!cn){cn=document.createElement('span');cn.className='cn';uf.appendChild(cn)}cn.textContent=waiting}else if(cn)cn.remove()}}
  var l=document.getElementById('ibl');if(!j.threads.length){l.innerHTML='<div class="emp">No conversations here yet.</div>';return}
  var ur=j.threads.filter(function(t){return t.awaiting&&t.status!=='resolved'}),rd=j.threads.filter(function(t){return !(t.awaiting&&t.status!=='resolved')});
  var top=l.scrollTop,html='';
  if(ur.length){html+='<div class="sep u">Waiting for Reply<b>'+ur.length+'</b></div>'+ur.map(row).join('');if(rd.length)html+='<div class="sep">Others</div>'}
  html+=rd.map(row).join('');l.innerHTML=html;l.scrollTop=top;
  if(!first)l.querySelectorAll('.th.un').forEach(function(n){var id=n.dataset.id,t=j.threads.filter(function(x){return String(x.id)===id})[0];if(t&&(seen[id]===undefined||t.unread>seen[id]))n.classList.add('fl8')});
  first=false;j.threads.forEach(function(t){seen[t.id]=t.unread});
  l.querySelectorAll('.th').forEach(function(n){n.onclick=function(){sel=+n.dataset.id;l.querySelectorAll('.th').forEach(function(x){x.classList.toggle('on',x===n)});document.getElementById('ibx').classList.add('sel');document.body.classList.add('ibsel');MChat.mount(document.getElementById('ibc'),sel,{onchange:load,focus:true})}})})}
document.getElementById('ibq').oninput=function(){clearTimeout(tmr);q=this.value;tmr=setTimeout(load,300)};
function mm(){var bm=document.getElementById('ibm');if(bm&&window.MChat)bm.textContent=MChat.soundLabel?MChat.soundLabel():(MChat.mute?'🔕 Sound off':'🔔 Sound on')}
document.getElementById('ibback').onclick=function(){document.getElementById('ibx').classList.remove('sel');document.body.classList.remove('ibsel');sel=null;load()};
document.getElementById('iball').onclick=function(){f='all';q='';document.getElementById('ibq').value='';drawF();load()};
drawF();load();setInterval(function(){if(!document.hidden)load()},8000);
var wait=setInterval(function(){if(window.MChat){clearInterval(wait);MChat.onNew=load;mm()}},300);
var oid=new URLSearchParams(location.search).get('open');if(oid){var w2=setInterval(function(){if(window.MChat){clearInterval(w2);sel=+oid;MChat.mount(document.getElementById('ibc'),sel,{onchange:load})}},300)}
})();
</script>
@endverbatim
@endsection
