@extends('layout')@section('title','Messages')
@section('body')
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
@font-face{font-family:'Inter Chat';src:url('/fonts/InterChat-Regular.woff2?v=2') format('woff2');font-weight:400;font-style:normal;font-display:swap}
@font-face{font-family:'Inter Chat';src:url('/fonts/InterChat-Medium.woff2?v=2') format('woff2');font-weight:500;font-style:normal;font-display:swap}
@font-face{font-family:'Inter Chat';src:url('/fonts/InterChat-SemiBold.woff2?v=2') format('woff2');font-weight:600;font-style:normal;font-display:swap}
@font-face{font-family:'Inter Chat';src:url('/fonts/InterChat-Bold.woff2?v=2') format('woff2');font-weight:700;font-style:normal;font-display:swap}
.mbx,.mbx *,.mbx .mc textarea,.mbx .mc input,.mbx .mc button,.mbx .mc select{font-family:'Bornomala','Inter Chat',Inter,'Segoe UI',system-ui,Arial,sans-serif}
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
.mbx .mc .hb{font-size:14.5px!important;line-height:1.55!important}.mbx .mc .sumb{font-size:14px!important;line-height:1.55!important}.mbx .mc .sumb b{font-size:11px!important}.mbx .mc .sumb:not(.x){max-height:44px;box-sizing:content-box}
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
.mbx .sep.p{color:#7A271A;background:#FEF3F2;border-top:1px solid #F6C4C0;border-bottom:1px solid #F6C4C0;padding:8px 10px;margin:0 0 4px}.mbx .sep.p::before{content:"";width:8px;height:8px;border-radius:50%;background:#D92D20;flex:none}.mbx .sep.p b{color:#B42318}
.mbx .sep.a{color:#1E3A8A;background:#EEF4FF;border-top:1px solid #C7D7FE;border-bottom:1px solid #C7D7FE;padding:8px 10px;margin:8px 0 4px}.mbx .sep.a::before{content:"";width:8px;height:8px;border-radius:50%;background:#1D4ED8;flex:none}.mbx .sep.a b{color:#1D4ED8}
.mbx .sep small{font-weight:500;color:inherit;opacity:.8;margin-left:auto}.mbx .sep small+b{margin-left:8px}
.mbx .th.cp{padding-top:7px;padding-bottom:7px}.mbx .th.cp .l3,.mbx .th.cp .l4{display:none}.mbx .th.cp .nm{font-weight:600}
.mbx .bg.tp{background:#FFF7ED;color:#9A3412}.mbx .bg.md{background:#FDF2FA;color:#9D174D}
.mbx .slb{display:flex;align-items:center;gap:8px;margin-top:5px}.mbx .slb i{flex:1;height:4px;border-radius:99px;background:#D92D20;display:block}.mbx .slb span{font-size:11px;font-weight:700;color:#B42318;white-space:nowrap}
#ibu{display:inline-flex;gap:8px;flex-wrap:wrap;vertical-align:middle;margin-left:8px;font-size:12.5px;font-weight:600}
#ibu .k{display:inline-flex;align-items:center;gap:6px;border-radius:9px;padding:4px 10px;border:1px solid}#ibu .k b{font-size:15px}
/* ===== Inbox v2 skin, version C (14 Nov 2026): calm neutral frame, flat filters, customer panel on the right. Text sizes from the 11 Nov request are kept. ===== */
.fbar{border:1px solid #E5E7EB;border-radius:12px;box-shadow:none;padding:8px 10px;gap:6px;margin-bottom:8px}.fbar .fr{gap:6px}
.fbar a,.fbar button.fa{height:32px!important;padding:0 11px;border-radius:8px;box-shadow:none;font-size:12.5px;border-color:#E5E7EB;color:#344054}
.fbar a svg,.fbar button.fa svg{width:16px;height:16px}.fbar a:hover,.fbar button.fa:hover{background:#F9FAFB;box-shadow:none}
.fbar a.on{background:#0B1B33;border-color:#0B1B33;color:#fff;box-shadow:none}.fbar .cn{min-width:18px;height:18px;line-height:18px;font-size:10.5px;background:#B42318}
.mbx{gap:10px}.mbx .ls,.mbx .cv{border-color:#E5E7EB}
#iball{background:#fff;color:#0B1B33;border:1px solid #D0D5DD;box-shadow:none;border-radius:8px;font-weight:600}
.mbx .sq input{border-color:#D0D5DD;border-radius:8px}
.mbx .th{border-color:#EEF0F3}.mbx .th:hover{background:#F9FAFB;border-color:#E5E7EB}
.mbx .th.un{border-color:#EEF0F3;box-shadow:inset 3px 0 0 #D92D20}.mbx .th.un:hover{background:#F9FAFB}.mbx .th.cp.un{box-shadow:inset 3px 0 0 #1D4ED8}
.mbx .th.on,.mbx .th.on:hover{background:#FFF7ED;border-color:#FDBA8C;box-shadow:inset 3px 0 0 #F26B1D}
.mbx .av .mcav{background:#F2F4F7;color:#0B1B33}
.mbx .mc .hd{border-bottom-color:#E5E7EB;padding:10px 14px}.mbx .mc .hd .r2 .lk,.mbx .mc .hd .r2 select{border-color:#E5E7EB;border-radius:8px}
.mbx .mc .ms{background:#F9FAFB}.mbx .mc .b.in{border-color:#E5E7EB}.mbx .mc .b.out{background:#FFF1E8}.mbx .mc .cm{border-top-color:#E5E7EB}
@media(min-width:901px){.mbx .cv .mc .hd .r2{flex-wrap:wrap;overflow:visible}}
/* keep the conversation itself the biggest part of the screen: compact header buttons, capped hand-off note, slimmer reply tools */
.mbx .mc .hd{gap:6px;padding:8px 12px}.mbx .mc .hd .r2{gap:5px}.mbx .mc .hd .r2 .lk,.mbx .mc .hd .r2 .aw,.mbx .mc .hd .r2 .rp,.mbx .mc .hd .r2 select{height:28px;padding:0 9px;font-size:12.5px}
.mbx .mc .hb{max-height:60px;overflow:auto}
.mbx .mc .cm{padding:8px 10px;gap:6px}.mbx .mc .cm .tb .tg,.mbx .mc .cm .tb .qrw select,.mbx .mc .cm .ax .tg{height:30px;padding:0 9px}.mbx .mc .cm .tb .qrw select{padding-left:28px}.mbx .mc .ms{min-height:180px} /* nothing hides off to the side: the action buttons wrap */
@media(min-width:1280px){
.mbx{grid-template-columns:320px minmax(0,1fr)}
.mbx .cv .mc{display:grid;grid-template-columns:minmax(0,1fr) 300px;grid-template-rows:auto minmax(0,1fr) auto}
.mbx .cv .mc>.mcbd{display:none}
.mbx .cv .mc>.hd{grid-column:1;grid-row:1;min-width:0}.mbx .cv .mc>.ms{grid-column:1;grid-row:2;min-height:0}.mbx .cv .mc>.cm{grid-column:1;grid-row:3;min-width:0}
.mbx .cv .mc #hdt{display:none}
.mbx .cv .mc>details.cb{grid-column:2;grid-row:1/4;align-self:stretch;height:100%;box-sizing:border-box;max-height:none!important;border-bottom:0;border-left:1px solid #E5E7EB;background:#F9FAFB;padding:12px;overflow:auto;min-width:0}
.mbx .cv .mc>details.cb>summary{list-style:none;pointer-events:none;font-size:11.5px;letter-spacing:.04em;text-transform:uppercase;color:#475467;margin-bottom:6px}
.mbx .cv .mc>details.cb>summary::-webkit-details-marker{display:none}
.mbx .cv .mc>details.cb .chips{gap:5px}.mbx .cv .mc>details.cb .ch{height:26px;padding:0 9px;border-color:#E5E7EB;border-radius:8px;white-space:normal}
.mbx .cv .mc>details.cb .cur{flex-wrap:wrap;background:#fff;border-color:#E5E7EB}
.mbx .cv .mc>details.cb table.ot{border:0;display:block}.mbx .cv .mc>details.cb table.ot thead{display:none}.mbx .cv .mc>details.cb table.ot tbody{display:block}
.mbx .cv .mc>details.cb table.ot tr{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:3px 8px;background:#fff;border:1px solid #E5E7EB;border-radius:9px;padding:7px 9px;margin-bottom:6px}
.mbx .cv .mc>details.cb table.ot td{padding:0;border:0}.mbx .cv .mc>details.cb table.ot td.ac{grid-column:1/-1;margin-top:3px;flex-wrap:wrap}
}
/* short laptop screens (e.g. 1366x768): narrower side columns so buttons fit on one row, one-line hand-off note and summary (click to expand), shorter typing box. The page itself stays one fixed screen (10 Nov rule). */
@media(min-width:1280px) and (max-height:860px){
.mbx{grid-template-columns:270px minmax(0,1fr)}.mbx .cv .mc{grid-template-columns:minmax(0,1fr) 250px}
#ibtop{margin-bottom:6px!important}#ibtop>div>span{display:none}
.mbx .mc .hd{gap:4px;padding:6px 10px}.mbx .mc .hb{max-height:34px}.mbx .mc .sumb:not(.x){max-height:22px}.mbx .mc .sumb{margin-top:0!important}
.mbx .mc .cm{padding:6px 10px;gap:4px}.mbx .mc .cm .sd textarea{height:46px;min-height:46px}.mbx .mc .cm .sd button{height:40px!important}
}
/* staff reply share bar: one line, only staff who replied in the period */
#ibsh{display:flex;align-items:center;gap:10px;min-width:0;box-sizing:border-box}
@media(min-width:901px){.fbar{display:grid!important;grid-template-columns:auto minmax(0,1fr);align-items:center;column-gap:14px}.fbar #ibf2{grid-column:1/-1}} /* the bar sits in the free space beside the channel buttons, so it adds no height */
#ibsh .t{font-size:12px;font-weight:700;color:#475467;white-space:nowrap}#ibsh .br{flex:1;min-width:0;height:24px;display:flex;border-radius:7px;overflow:hidden;border:1px solid #E5E7EB}
#ibsh .sg{min-width:max-content;display:flex;align-items:center;padding:0 9px;color:#fff;font-size:12px;font-weight:700;white-space:nowrap;background:#667085;border-right:1px solid #fff;box-sizing:border-box}#ibsh .sg:last-child{border-right:0}
#ibsh .rt{flex:none;font-size:12px;font-weight:600;color:#475467;white-space:nowrap;border:1px solid #E5E7EB;border-radius:7px;padding:2px 8px;order:5}#ibsh .rt b{color:#0B1B33}#ibsh .pd{order:6}
#ibsh .t i{font-style:normal}#ibsh .t .ts{display:none}@media(max-width:1600px){#ibsh .t .tl{display:none}#ibsh .t .ts{display:inline}}#ibsh .br.cmp .sg{padding:0 5px;font-size:11.5px}#ibsh .sg.mr{flex:0 0 auto!important;background:#98A2B3;color:#0B1B33;cursor:help}
#ibsh .sg.s0{background:#F26B1D}#ibsh .sg.s1{background:#0B1B33}#ibsh .sg.s2{background:#475467}
#ibsh .pd{display:flex;border:1px solid #E5E7EB;border-radius:7px;overflow:hidden;flex:none}#ibsh .pd button{border:0;background:#fff;color:#344054;font-size:12px;font-weight:600;padding:4px 9px;cursor:pointer;font-family:inherit}#ibsh .pd button.on{background:#0B1B33;color:#fff}
#ibsh .no{flex:1;font-size:12px;color:#6B7280}
@media(max-width:900px){#ibsh{display:none!important}}
/* very short laptop screens (1366x768 with browser bars is about 650px tall): everything stays visible, only smaller, so the conversation keeps 200px or more */
@media(min-width:1280px) and (max-height:760px){
main{padding-top:6px!important;padding-bottom:6px!important}
#ibtop{margin-bottom:4px!important}#ibtop h1{font-size:17px!important;line-height:1.2}#ibu .k{padding:1px 8px}#ibu .k b{font-size:13px}
.fbar{padding:5px 8px!important;gap:4px!important;margin-bottom:6px!important}.fbar .fr{gap:4px}.fbar a,.fbar button.fa{height:27px!important;padding:0 8px!important;font-size:12px!important}.fbar a svg,.fbar button.fa svg{width:14px;height:14px}.fbar #ibf2 a svg{display:none}
#ibsh .br{height:22px}#ibsh .pd button{padding:2px 8px}
.mbx .mc .hd{gap:3px!important;padding:5px 10px!important}.mbx .mc .hd .r2{gap:4px!important}.mbx .mc .hd .r2 .lk,.mbx .mc .hd .r2 .aw,.mbx .mc .hd .r2 .rp,.mbx .mc .hd .r2 select{height:25px!important;min-height:0!important;padding:0 7px!important;font-size:12px!important}
.mbx .mc .hb{max-height:27px!important;padding-top:2px!important;padding-bottom:2px!important}.mbx .mc .sumb:not(.x){max-height:20px!important}.mbx .mc .sumb{padding-top:3px!important;padding-bottom:3px!important}
.mbx .mc .cm{padding:5px 8px!important;gap:4px!important}.mbx .mc .cm .tb,.mbx .mc .cm .ax{gap:4px!important}.mbx .mc .cm .tb .tg,.mbx .mc .cm .tb .qrw select,.mbx .mc .cm .ax .tg{height:27px!important;min-height:0!important;padding:0 7px!important;font-size:12px!important}.mbx .mc .cm .tb .qrw select{padding-left:26px!important}.mbx .mc .cm .ax .axt{height:27px!important;min-height:0!important}.mbx .mc .cm .ax .axt>*{height:25px!important;min-height:0!important;padding-top:0!important;padding-bottom:0!important;font-size:12px!important}
.mbx .mc .cm .sd textarea{height:40px!important;min-height:40px!important}.mbx .mc .cm .sd button{height:38px!important}
}
#ibu .k.p{background:#FEF3F2;border-color:#F6C4C0;color:#7A271A}#ibu .k.p b{color:#B42318}#ibu .k.a{background:#EEF4FF;border-color:#C7D7FE;color:#1E3A8A}#ibu .k.a b{color:#1D4ED8}#ibu .k.o{background:#fff;border-color:#E5E7EB;color:#4B5563}#ibu .k.o b{color:#B42318;font-size:12.5px}
</style>
<div id="ibtop" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1"><h1 style="margin:0">Messages <span id="ibu"></span></h1><span style="font-size:12px;color:#6B7280">Facebook, Instagram, WhatsApp and Website Chat in one inbox.</span></div>
</div>
<div class="fbar" id="ibfb"><div class="fr" id="ibf1"></div><div id="ibsh" hidden></div><div class="fr" id="ibf2"></div></div>
<button type="button" id="ibback">← All conversations</button>
<div class="mbx" id="ibx"><div class="ls"><div class="stk"><div class="tp"><button type="button" id="iball">← All conversations</button></div><div class="sq" style="padding-top:9px"><input id="ibq" type="search" placeholder="Search name, mobile, invoice (Ctrl K)"></div></div><div class="lt" id="ibl" data-keep></div></div>
<div class="cv" id="ibc" data-keep><div class="emp" style="margin:auto">Select a conversation to read and reply.<br>New messages appear here and as a popup anywhere in the dashboard.</div></div></div>
@endsection
@section('js')
@verbatim
<script>
(function(){
var I={human:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#C2410C" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>',
all:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FF5A00" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/></svg>',
facebook:'<svg width="20" height="20" viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#1877F2"/><path class="kp" d="M13.2 20v-7h2.3l.4-2.8h-2.7V8.5c0-.8.3-1.3 1.4-1.3H16V4.7c-.3 0-1.1-.1-2.1-.1-2.1 0-3.5 1.3-3.5 3.6v2H8v2.8h2.4v7" fill="#fff"/></svg>',
instagram:'<svg width="20" height="20" viewBox="0 0 24 24"><defs><linearGradient id="ig" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#FEC053"/><stop offset=".4" stop-color="#F2203E"/><stop offset=".7" stop-color="#B729A8"/><stop offset="1" stop-color="#5342D6"/></linearGradient></defs><rect x="2" y="2" width="20" height="20" rx="6" fill="none" stroke="url(#ig)" stroke-width="2.2"/><circle cx="12" cy="12" r="4.4" fill="none" stroke="url(#ig)" stroke-width="2.2"/><circle cx="17.4" cy="6.6" r="1.3" fill="#B729A8"/></svg>',
whatsapp:'<svg width="20" height="20" viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#25D366"/><path class="kp" d="M16.7 14.3c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1-1.4-.7-2.4-1.5-3.2-3-.1-.2 0-.3.1-.4l.5-.6.2-.4c0-.1 0-.3 0-.4l-.7-1.7c-.2-.4-.4-.4-.6-.4h-.4c-.2 0-.4.1-.6.3-.6.6-.9 1.4-.8 2.3.2 1.1.8 2.1 1.5 3 1.1 1.4 2.5 2.4 4.2 3 .8.2 1.5.2 2.2-.1.6-.3 1.1-.9 1.2-1.5 0-.2 0-.3-.1-.4l-.4-.1Z" fill="#fff"/></svg>',
web:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FF5A00" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><path d="M21 12a8 8 0 0 1-11.8 7L4 20l1.2-4.4A8 8 0 1 1 21 12Z"/><circle cx="9" cy="12" r=".6" fill="#FF5A00"/><circle cx="12.2" cy="12" r=".6" fill="#FF5A00"/><circle cx="15.4" cy="12" r=".6" fill="#FF5A00"/></svg>',
orders:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4B5563" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h5"/></svg>',
  unread:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4B5563" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/></svg>',
assigned:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4B5563" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 20c.8-3.6 3.8-5.5 7.5-5.5s6.7 1.9 7.5 5.5"/></svg>',
late:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#B42318" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
report:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#B91C1C" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></svg>',
history:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0B1B33" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg>',
upset:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#B54708" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 16c1-1.3 2.2-2 3.5-2s2.5.7 3.5 2M9 10h.01M15 10h.01"/></svg>',
resolved:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="1.9" stroke-linejoin="round" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="m8 12.3 2.6 2.6L16.2 9"/></svg>'};
var F1=[['all','All'],['facebook','Facebook'],['instagram','Instagram'],['whatsapp','WhatsApp'],['web','Website Chat']],F2=[['unread','Unread'],['late','Late replies'],['upset','Upset customers'],['report','Complaint'],['assigned','Assigned to me'],['human','Human Answer'],['resolved','Closed'],['history','All History'],['orders','Order Requests']],waiting=0,orq=0,hum=0,rep=0;
var f=new URLSearchParams(location.search).get('f')||'all',q='',sel=null,tmr;
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function ago(iso){if(!iso)return '';var s=(Date.now()-new Date(iso).getTime())/1000;if(s<60)return 'now';if(s<3600)return Math.floor(s/60)+'m';if(s<86400)return Math.floor(s/3600)+'h';return new Date(iso).toLocaleDateString([], {day:'numeric',month:'short'})}
var CHL={facebook:'f',instagram:'IG',whatsapp:'W',web:'💬'};
function pill(x){return '<a data-f="'+x[0]+'" class="'+(f===x[0]?'on':'')+'" role="button" tabindex="0">'+I[x[0]]+'<span>'+x[1]+'</span>'+(x[0]==='unread'&&waiting>0?'<span class="cn">'+waiting+'</span>':'')+(x[0]==='orders'&&orq>0?'<span class="cn">'+orq+'</span>':'')+(x[0]==='human'&&hum>0?'<span class="cn">'+hum+'</span>':'')+(x[0]==='report'&&rep>0?'<span class="cn">'+rep+'</span>':'')+'</a>'}
function drawF(){document.getElementById('ibf1').innerHTML=F1.map(pill).join('');
  document.getElementById('ibf2').innerHTML=F2.map(pill).join('')+'<span class="sp"></span><button class="fa" type="button" id="ibup" title="Fetch the latest messages. This never marks anything as seen.">↻ Update</button><button class="fa" type="button" id="ibm">🔔 Sound on</button><a href="/admin/chat/auto" style="height:38px" title="Order confirmations, order updates and campaign messages the system sent by itself">📤 Auto Messages</a><a href="/admin/chat/memory" style="height:38px;border-color:#FF5A00;color:#C2410C">🧠 AI Memory</a><a href="/admin/chat-settings" style="height:38px">Chat settings</a>';
  document.querySelectorAll('#ibfb a[data-f]').forEach(function(a){a.onclick=function(){f=a.dataset.f;drawF();load()};a.onkeydown=function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();a.click()}}});
  var bm=document.getElementById('ibm');bm.onclick=function(){MChat.cycleSound();mm()};mm();
  document.getElementById('ibup').onclick=doUpdate}
/* Manual synchronisation only: refreshes the list and the open conversation. It never marks anything as seen or replied. */
var shD=null,shP='d1';try{if(localStorage.getItem('ib_share_p')==='d7')shP='d7'}catch(e){}
function shEsc(x){return String(x).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function shDur(s){if(s==null)return '';if(s<60)return 'under 1 min';if(s<3600)return Math.round(s/60)+' min';var h=Math.floor(s/3600),m=Math.round((s%3600)/60);return h+' h'+(m?' '+m+' min':'')}
function drawShare(){var el=document.getElementById('ibsh');if(!el)return;if(!shD){el.hidden=true;return}el.hidden=false;var rows=shD[shP]||[],tot=0,msgs=0,wt=shD[shP==='d1'?'w1':'w7']||{by:{}},wa=wt.all;rows.forEach(function(r){tot+=r.c;msgs+=r.m});
  var bar=rows.length?'<div class="br">'+rows.map(function(r,i){var p=Math.round(r.c*100/tot);return '<div class="sg s'+Math.min(i,3)+'" style="flex:'+r.c+' 1 0" title="'+shEsc(r.n)+': answered '+r.c+' customer'+(r.c===1?'':'s')+' with '+r.m+' repl'+(r.m===1?'y':'ies')+' ('+p+'% of customers answered by staff)'+(wt.by&&wt.by[r.n]?'. Typical wait before a reply: '+shDur(wt.by[r.n].s):'')+'" data-s="'+shEsc(String(r.n).split(' ')[0])+' '+p+'%">'+shEsc(r.n)+' · '+r.c+' · '+p+'%</div>'}).join('')+'</div>':'<div class="no">No staff replies '+(shP==='d1'?'today yet':'in the last 7 days')+'.</div>';
  el.innerHTML='<span class="t" title="Customers answered by each staff member. AI replies are not counted. Staff who sent no reply are not listed."><i class="tl">Customers answered by staff </i><i class="ts">Staff </i>'+(shP==='d1'?'today':'· 7 days')+'</span>'+(wa&&wa.k>0?'<span class="rt" title="Typical wait before a staff reply (the middle value of '+wa.k+' replies): from the customer\'s first unanswered message to the staff reply. AI replies are not counted.">Reply time <b>'+shDur(wa.s)+'</b></span>':'')+bar+'<span class="pd"><button type="button" data-p="d1"'+(shP==='d1'?' class="on"':'')+'>Today</button><button type="button" data-p="d7"'+(shP==='d7'?' class="on"':'')+'>7 days</button></span>';
  el.querySelectorAll('.pd button').forEach(function(b){b.onclick=function(){shP=b.getAttribute('data-p');try{localStorage.setItem('ib_share_p',shP)}catch(e){}drawShare()}});
  var br=el.querySelector('.br');if(br&&br.scrollWidth>br.clientWidth+1){br.classList.add('cmp');br.querySelectorAll('.sg').forEach(function(g){g.textContent=g.getAttribute('data-s')});var sg=[].slice.call(br.querySelectorAll('.sg')),hid=0,more=null,names=[];
   while(br.scrollWidth>br.clientWidth+1&&sg.length>1){var last=sg.pop();names.unshift(last.getAttribute('title'));br.removeChild(last);hid++;if(!more){more=document.createElement('div');more.className='sg s3 mr';br.appendChild(more)}more.textContent='+'+hid+' more';more.setAttribute('title',names.join('\n'))}}}
var upBusy=false;function doUpdate(){if(upBusy)return;upBusy=true;var b=document.getElementById('ibup');b.disabled=true;b.textContent='↻ Updating…';
  Promise.all([load(),(window.MChat&&MChat.refresh)?MChat.refresh():null]).catch(function(){}).then(function(){b=document.getElementById('ibup');if(b){b.textContent='✓ Updated';b.classList.add('ok')}setTimeout(function(){upBusy=false;var c=document.getElementById('ibup');if(c){c.disabled=false;c.classList.remove('ok');c.textContent='↻ Update'}},1400)})}
var seen={},first=true;
function row(t,cp){var st=(t.needs_human&&t.awaiting&&t.status!=='resolved')?'waiting':(t.status||'open');var stL={new:'New',open:'Open',waiting:'Waiting for customer',resolved:'Closed'}[st]||st;
  var h=(t.reported?'<span class="bg need" title="'+esc(t.report_reason||'')+'">⚑ Complaint</span>':(t.report_solved?'<span class="bg" style="background:#DCFCE7;color:#166534" title="'+esc(t.report_reason||'')+'">✓ Complaint Solved'+(t.report_solved_by?' · '+esc(t.report_solved_by):'')+'</span>':''))+(t.needs_human?'<span class="bg need" title="'+esc(t.handoff_reason||'')+'">Needs human</span>':'<span class="bg '+(t.ai?'ai':'human')+'">'+(t.ai?'AI':'Human')+'</span>');
  var un=!!t.awaiting&&t.status!=='resolved',ico=(window.MChat&&MChat.ico&&MChat.ico[t.channel])||CHL[t.channel];
  var late=un&&!cp&&t.waiting_since&&(Date.now()-new Date(t.waiting_since).getTime())>(t.sla_r||15)*60000;
  var tpc=!cp&&t.intent&&t.intent!=='other'&&t.intent!=='greeting'?'<span class="bg tp">'+esc(String(t.intent).replace(/_/g,' '))+'</span>':'',mdc=!cp&&t.mood&&!/^(neutral|calm|happy|normal)$/i.test(t.mood)?'<span class="bg md">'+esc(t.mood)+'</span>':'';
  return '<div class="th'+(un?' un':'')+(cp?' cp':'')+(sel===t.id?' on':'')+'" data-id="'+t.id+'"><div class="av"><span class="mcav">'+(t.avatar?'<img src="'+esc(t.avatar)+'" alt="" referrerpolicy="no-referrer" onerror="this.remove()">':'')+esc((t.name||'?').charAt(0).toUpperCase())+'</span><span class="mcb ico '+t.channel+'" title="'+esc(t.channel_name)+'">'+ico+'</span></div>'
  +'<div class="bd"><div class="l1"><span class="nm">'+esc(t.name)+'</span><span class="tm">'+ago(t.last_at)+'</span></div>'
  +'<div class="l2"><span class="lm">'+(t.last_dir==='out'?(cp?'AI: ':'You: '):'')+esc(t.last||'')+'</span>'+(un?'<span class="ub">'+t.unread+'</span>':'')+'</div>'
  +'<div class="l3">'+esc(t.channel_name)+(t.source?' <span class="srcb s-'+t.channel+'" title="Received on: '+esc(t.source)+'">'+esc(t.source)+'</span>':'')+' · Holding: '+(t.orders>0?t.orders+' order'+(t.orders===1?'':'s'):'--')+'</div>'
  +'<div class="l4">'+(un&&window.MSla?MSla.chip(t):'')+(un?'<span class="st2 aw">Awaiting</span>':(t.replied?'<span class="st2 rp">✓ Replied</span>':(t.stale?'<span class="st2 old" title="Customer message older than 7 days">Old</span>':'')))+'<span class="bg '+st+'">'+stL+'</span>'+h+tpc+mdc+(t.assigned_name?'<span class="bg human" title="Assigned to">👤 '+esc(t.assigned_name)+'</span>':'<span class="bg" style="color:#9CA3AF">Unassigned</span>')+'</div>'+(late?'<div class="slb"><i></i><span>Reply target missed</span></div>':'')+'</div></div>'}
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
  if((j.order_requests||0)!==orq||(j.human||0)!==hum||(j.reports||0)!==rep){orq=j.order_requests||0;hum=j.human||0;rep=j.reports||0;drawF()}var u=document.getElementById('ibu'),ah=j.ai_handled||0,old=null;(j.threads||[]).forEach(function(t){if(t.person&&t.awaiting&&t.waiting_since){var d=new Date(t.waiting_since).getTime();if(old===null||d<old)old=d}});
  var oh='';if(old!==null){var mn=Math.floor((Date.now()-old)/60000);oh='<span class="k o">Oldest waiting <b>'+(mn>=60?Math.floor(mn/60)+'h '+(mn%60)+'m':mn+'m')+'</b></span>'}
  var sv=j.svc,sh='';if(sv&&(sv.staff+sv.ai)>0){sh='<span class="k" title="Replies sent today. By staff = sent from this inbox under a staff name. Automatic order messages and replies typed in the WhatsApp phone app ('+(sv.other||0)+' today) are not counted as staff. Customer messages received today: '+sv.in+'">Today <b>'+sv.staff+'</b>by staff · <b>'+sv.ai+'</b>by AI ('+Math.round(sv.ai*100/(sv.staff+sv.ai))+'%)</span>'}
  if(f==='late'){var lim=Date.now()-30*60000;j.threads=(j.threads||[]).filter(function(t){return t.awaiting&&t.waiting_since&&new Date(t.waiting_since).getTime()<lim})}
  shD=j.share||null;drawShare();
  u.innerHTML=(j.unread?'<span class="k p"><b>'+(j.human||0)+'</b>need a person</span><span class="k a"><b>'+ah+'</b>answered by AI</span>'+oh:'')+sh;if(window.MChat)MChat.badge(j.unread);
  if(waiting!==j.unread){waiting=j.unread;var uf=document.querySelector('#ibfb a[data-f=unread]');if(uf){var cn=uf.querySelector('.cn');if(waiting>0){if(!cn){cn=document.createElement('span');cn.className='cn';uf.appendChild(cn)}cn.textContent=waiting}else if(cn)cn.remove()}}
  var l=document.getElementById('ibl');if(!j.threads.length){l.innerHTML='<div class="emp">No conversations here yet.</div>';return}
  var ur=j.threads.filter(function(t){return t.awaiting&&t.status!=='resolved'}),rd=j.threads.filter(function(t){return !(t.awaiting&&t.status!=='resolved')});
  var top=l.scrollTop,html='';
  var pp=ur.filter(function(t){return t.person}),ai=ur.filter(function(t){return !t.person});
  ppIds=null;pp.sort(function(a,b){var x=a.waiting_since?new Date(a.waiting_since).getTime():Infinity,y=b.waiting_since?new Date(b.waiting_since).getTime():Infinity;return x-y});
  ppIds=pp.map(function(t){return t.id});if(pp.length)html+='<div class="sep p">Need a person<small>oldest first</small><b>'+pp.length+'</b></div>'+pp.map(function(t){return row(t)}).join('');
  if(ai.length)html+='<div class="sep a">Answered by AI, waiting on customer<b>'+ai.length+'</b></div>'+ai.map(function(t){return row(t,true)}).join('');
  if(ur.length&&rd.length)html+='<div class="sep">Others</div>';
  html+=rd.map(function(t){return row(t)}).join('');l.innerHTML=html;l.scrollTop=top;
  if(!first)l.querySelectorAll('.th.un').forEach(function(n){var id=n.dataset.id,t=j.threads.filter(function(x){return String(x.id)===id})[0];if(t&&(seen[id]===undefined||t.unread>seen[id]))n.classList.add('fl8')});
  first=false;j.threads.forEach(function(t){seen[t.id]=t.unread});
  l.querySelectorAll('.th').forEach(function(n){n.onclick=function(){sel=+n.dataset.id;l.querySelectorAll('.th').forEach(function(x){x.classList.toggle('on',x===n)});document.getElementById('ibx').classList.add('sel');document.body.classList.add('ibsel');MChat.mount(document.getElementById('ibc'),sel,{onchange:load,focus:true,next:goNext})}})})}
var ppIds=null;function goNext(cur){var a=(ppIds||[]).filter(function(i){return i!==cur});if(!a.length)return;sel=a[0];document.getElementById('ibl').querySelectorAll('.th').forEach(function(x){x.classList.toggle('on',+x.dataset.id===sel)});MChat.mount(document.getElementById('ibc'),sel,{onchange:load,focus:true,next:goNext});load()}
document.getElementById('ibq').oninput=function(){clearTimeout(tmr);q=this.value;tmr=setTimeout(load,300)};
function mm(){var bm=document.getElementById('ibm');if(bm&&window.MChat)bm.textContent=MChat.soundLabel?MChat.soundLabel():(MChat.mute?'🔕 Sound off':'🔔 Sound on')}
document.getElementById('ibback').onclick=function(){document.getElementById('ibx').classList.remove('sel');document.body.classList.remove('ibsel');sel=null;load()};
document.getElementById('iball').onclick=function(){f='all';q='';document.getElementById('ibq').value='';drawF();load()};
drawF();load();setInterval(function(){if(!document.hidden)load()},8000);
/* Ctrl K (or Cmd K): jump to the search box */
document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();var s=document.getElementById('ibq');if(s){s.focus();s.select()}}});
var wait=setInterval(function(){if(window.MChat){clearInterval(wait);MChat.onNew=load;mm()}},300);
var oid=new URLSearchParams(location.search).get('open');if(oid){var w2=setInterval(function(){if(window.MChat){clearInterval(w2);sel=+oid;MChat.mount(document.getElementById('ibc'),sel,{onchange:load})}},300)}
})();
</script>
@endverbatim
@endsection
