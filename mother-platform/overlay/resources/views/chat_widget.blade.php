@php $__cu = auth()->user(); $__P = \App\Http\Middleware\ModulePermission::class; @endphp
@if($__P::can($__cu, 'orders', 'view'))
<script>window.MCSRF=@json(csrf_token());window.MCAN=@json($__P::can($__cu, 'orders', 'edit'));window.MUID=@json($__cu->id);</script>
<style>
.mcp{position:fixed;bottom:18px;right:18px;z-index:9990;display:flex;flex-direction:column;gap:10px;width:350px;max-width:calc(100vw - 28px)}
.mcc{background:#fff;border:1.5px solid #FDC2A5;border-radius:16px;box-shadow:0 14px 34px rgba(11,18,32,.22);padding:12px;animation:mcin .22s ease-out;font-size:13px;color:#0B1220}
.mcs{background:#0B1220;color:#fff;border-radius:14px;padding:10px 12px;font-size:12.5px;display:none;align-items:center;gap:8px;box-shadow:0 10px 26px rgba(11,18,32,.28);animation:mcin .22s ease-out}.mcs b{font-weight:700}.mcs .sp{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.mcs button{padding:5px 10px;font-size:12px;border-radius:8px;border:1px solid #FF5A00;background:#FF5A00;color:#fff;font-weight:600;cursor:pointer}.mcs button.gh{background:transparent;border-color:#475569}
@media(prefers-reduced-motion:reduce){.mcc,.mcs{animation:none!important}}
@keyframes mcin{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.mcc .h{display:flex;align-items:center;gap:8px;font-weight:700;font-size:12.5px}.mcc .h .x{margin-left:auto;background:none;color:#6B7280;padding:0 4px;font-size:18px;line-height:1}
.mcc .cnt{background:#FF5A00;color:#fff;border-radius:20px;font-size:11px;padding:1px 8px}
.mcc .who{display:flex;gap:10px;align-items:center;margin:10px 0 6px}.mcc .who b{display:block;font-size:14px}.mcc .who small{color:#6B7280}
.mcc .msg{background:#FFF6EF;border-radius:10px;padding:8px 10px;margin:4px 0;font-size:13px;line-height:1.4;word-break:break-word}
.mcc .meta{font-size:12px;color:#374151;margin:6px 0 8px}.mcc .meta .rk-Low{color:#15803D}.mcc .meta .rk-Medium{color:#B45309}.mcc .meta .rk-High{color:#B91C1C}.mcc .meta b{font-weight:700}
.mcc .bt{display:flex;flex-wrap:wrap;gap:6px}.mcc .bt button,.mcc .bt a{padding:6px 10px;font-size:12px;border-radius:8px;border:1px solid #FDC2A5;background:#fff;color:#0B1220;text-decoration:none;font-weight:600;cursor:pointer}
.mcc .bt .pr{background:#FF5A00;border-color:#FF5A00;color:#fff}.mcc .qr{display:none;gap:6px;margin-top:8px}.mcc .qr input{flex:1;padding:7px 9px;font-size:12.5px}
.mcav{width:38px;height:38px;border-radius:50%;background:#FFE7D6;color:#C2410C;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;overflow:hidden;position:relative}.mcav img{width:100%;height:100%;object-fit:cover}
.mcb{display:inline-flex;width:22px;height:22px;border-radius:7px;color:#fff;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}.mcb.facebook{background:#1877F2}.mcb.instagram{background:linear-gradient(45deg,#F9A03F,#E1306C,#833AB4)}.mcb.whatsapp{background:#25D366}.mcb.web{background:#FF5A00}
.mcd{position:fixed;top:0;right:0;bottom:0;width:460px;max-width:100vw;background:#fff;z-index:9995;box-shadow:-16px 0 40px rgba(11,18,32,.25);display:flex;flex-direction:column;transform:translateX(105%);transition:transform .2s}.mcd.op{transform:none}
.mcd .cls{position:absolute;top:10px;right:12px;background:none;color:#6B7280;font-size:22px;padding:0 6px;z-index:2}
.mc{display:flex;flex-direction:column;height:100%;min-height:0;font-size:13px;color:#0B1220}
.mc .hd{padding:12px 44px 10px 14px;border-bottom:1px solid #FFE7D6;display:flex;flex-direction:column;gap:8px}
.mc .hd .r1{display:flex;gap:10px;align-items:center}.mc .hd .r1 b{font-size:15px;display:block}.mc .hd .r1 small{color:#6B7280}
.mc .hd .r2{display:flex;gap:6px;flex-wrap:wrap;align-items:center}.mc .hd select{width:auto;padding:5px 8px;font-size:12px}
.mc .aw{display:inline-flex;align-items:center;height:26px;padding:0 10px;border-radius:13px;background:#FFF1E8;border:1px solid #FDBA8C;color:#C2410C;font-size:11.5px;font-weight:700;white-space:nowrap}.mc .rp{display:inline-flex;align-items:center;height:26px;padding:0 10px;border-radius:13px;background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;font-size:11.5px;font-weight:600;white-space:nowrap}
.mc .lk{padding:5px 10px;font-size:12px;border-radius:8px;border:1px solid #FDC2A5;background:#fff;color:#0B1220;text-decoration:none;font-weight:600;cursor:pointer}
.mc .cb{border-bottom:1px solid #FFE7D6;padding:8px 14px;background:#FFFBF8;max-height:38%;overflow:auto}.mc .cb summary{cursor:pointer;font-weight:700;font-size:12.5px;padding:2px 0}
.mc .cb .st{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0}.mc .cb .st span{background:#fff;border:1px solid #FFE7D6;border-radius:8px;padding:3px 8px;font-size:12px}
.mc .cb table{font-size:12px}.mc .cb td{padding:5px 6px 5px 0}.mc .cb .cur{background:#FFF1E8;border-radius:8px;padding:6px 8px;margin:4px 0;font-size:12px}
.mc .cb .mini{padding:2px 7px;font-size:11px;border-radius:6px;border:1px solid #FDC2A5;background:#fff;color:#0B1220;font-weight:600;cursor:pointer}
.mc .ms{flex:1;overflow:auto;padding:12px 14px;background:#F8F5F2;display:flex;flex-direction:column;gap:8px;min-height:120px}
.mc .b{max-width:82%;padding:8px 11px;border-radius:14px;line-height:1.45;word-break:break-word;white-space:pre-wrap;font-size:13px}
.mc .b.in{align-self:flex-start;background:#fff;border:1px solid #EDE7E1;border-bottom-left-radius:4px}.mc .b.out{align-self:flex-end;background:#FFE3D2;border-bottom-right-radius:4px}
.mc .mol{align-self:center;flex:none;padding:5px 12px;font-size:12px;border-radius:14px;border:1px solid #FDC2A5;background:#fff;color:#0B1220;font-weight:600;cursor:pointer}
@media(max-width:900px){.mc .cm .tb .tg,.mc .cm select,.mc .cm .sd button,.mc .lk,.mc .hd select{min-height:44px}.mc .cm{position:sticky;bottom:0;padding-bottom:max(8px,env(safe-area-inset-bottom))}.mc .cm textarea{font-size:16px}}
.mc .b.fail{background:#FEE2E2}.mc .b.pend{opacity:.7}.mc .b .rt{margin-left:6px;padding:1px 8px;font-size:11px;border-radius:6px;border:1px solid #DC2626;background:#fff;color:#B91C1C;font-weight:700;cursor:pointer}
.mc .mnp{position:sticky;bottom:4px;align-self:center;display:none;padding:5px 12px;font-size:12px;border-radius:14px;border:0;background:#FF5A00;color:#fff;font-weight:700;box-shadow:0 4px 12px rgba(11,18,32,.25);cursor:pointer;flex:none}.mc .b small{display:block;color:#6B7280;font-size:10.5px;margin-top:3px}.mc .b img{width:auto;height:auto;max-width:min(240px,100%);max-height:240px;object-fit:contain;border-radius:8px;display:block;margin-bottom:4px;cursor:zoom-in;background:#F3F4F6}.mc .b video{max-width:min(260px,100%);max-height:240px}.mc .b audio{width:240px;max-width:100%}.mc .b a{color:#C2410C}
.mc .cm{border-top:1px solid #FFE7D6;padding:8px 10px;display:flex;flex-direction:column;gap:6px;background:#fff}
.mc .cm .tb{display:flex;flex-wrap:wrap;gap:5px;align-items:center}.mc .cm .tb select{width:auto;max-width:160px;padding:5px 7px;font-size:12px}
.mc .cm .tb .tg{padding:5px 9px;font-size:12px;border-radius:8px;border:1px solid #FDC2A5;background:#fff;color:#0B1220;font-weight:600;cursor:pointer}
.mc .cm textarea{min-height:44px;max-height:120px;resize:vertical;font-size:13px}.mc .cm .sd{display:flex;gap:8px;align-items:flex-end}.mc .cm .sd textarea{flex:1}
.mc .pk{border:1px solid #FFE7D6;border-radius:10px;padding:6px;max-height:170px;overflow:auto;display:none}.mc .pk div.it{padding:6px 8px;border-radius:8px;cursor:pointer;display:flex;justify-content:space-between;gap:8px}.mc .pk div.it:hover{background:#FFF1E8}
.mc .ro{padding:10px 14px;color:#6B7280;font-size:12px;border-top:1px solid #FFE7D6}
@media(max-width:600px){.mcp{bottom:8px;right:8px;left:8px;width:auto;max-width:none}}
/* ===== premium messenger redesign (visual only) ===== */
.mc,.mbx,.fbar{font-family:system-ui,-apple-system,'Segoe UI',Roboto,'Hind Siliguri',Arial,sans-serif}
.mc .ic{width:15px;height:15px;flex:none;fill:none;stroke:#F05200;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.mcb.ico{background:none!important;width:auto;height:auto}.mcb.ico svg{width:100%;height:100%;display:block}.mcc .h .mcb.ico{width:22px;height:22px}.mc .hd .r1 b .mcb.ico{width:18px;height:18px;vertical-align:-3px;margin-left:4px}
.mc .hd{padding:12px 44px 10px 14px;gap:9px;background:#fff}
.mc .hd .r1 b{font-size:14px;font-weight:700}.mc .hd .r1 small{font-size:11.5px}
.mc .hd .r2{gap:6px}.mc .hd select{height:32px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;font-size:12px;color:#0B1220}
.mc .lk{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 11px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;color:#0B1220;font-size:12px;font-weight:600;box-shadow:none}.mc .lk:hover{background:#FFF8F2;border-color:#FDC2A5}
.mc .lk.rs{border-color:#BBF7D0;background:#fff;color:#166534}.mc .lk.rs:hover{background:#F0FDF4}
.mc .lk.dt{display:none}
.mc .aw{height:32px;border-radius:8px;font-size:12px}.mc .rp{height:32px;border-radius:8px;font-size:12px}
.mc .cb{padding:8px 14px 10px;background:#fff;max-height:42%}.mc .cb summary{font-size:12.5px;color:#0B1220}
.mc .cb .chips{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0}
.mc .cb .ch{display:inline-flex;align-items:center;gap:6px;height:30px;padding:0 11px;border-radius:999px;border:1px solid #F1E3D8;background:#fff;color:#0B1220;font-size:11px;font-weight:600;white-space:nowrap}
.mc .cb .ch.rk-Low{color:#15803D}.mc .cb .ch.rk-Medium{color:#B45309}.mc .cb .ch.rk-High{color:#B91C1C}
.mc .cb .idl{display:flex;align-items:flex-start;gap:7px;font-size:11.5px;color:#6B7280;margin:2px 0 8px;line-height:1.4}.mc .cb .idl .ic{margin-top:1px}
.mc .cb .cur{display:flex;align-items:center;gap:9px;background:#FFF4EC;border:1px solid #FBE1D0;border-radius:9px;padding:8px 11px;margin:0 0 9px;font-size:12.5px}.mc .cb .cur b{font-weight:700}.mc .cb .cur .cs{color:#6B7280;font-size:11px}.mc .cb .cur .amt{margin-left:auto;font-weight:700}
.mc .sb{display:inline-block;height:20px;line-height:20px;padding:0 9px;border-radius:999px;font-size:10.5px;font-weight:700;white-space:nowrap}
.mc .sb.warn{background:#FFEFD9;color:#B45309}.mc .sb.ok{background:#DCFCE7;color:#166534}.mc .sb.bad{background:#FEE2E2;color:#B91C1C}.mc .sb.mut{background:#F1F5F9;color:#475569}
.mc .cb table.ot{width:100%;border-collapse:separate;border-spacing:0;font-size:12px;border:1px solid #F1E3D8;border-radius:9px}
.mc .cb table.ot th{position:sticky;top:0;z-index:1;background:#FAF6F2;text-align:left;font-size:11px;font-weight:700;color:#475569;padding:7px 9px;border-bottom:1px solid #F1E3D8}
.mc .cb table.ot td{padding:6px 9px;border-bottom:1px solid #F5EEE8;vertical-align:middle}.mc .cb table.ot tr:last-child td{border-bottom:0}
.mc .cb table.ot td a{color:#1D4ED8;font-weight:600;text-decoration:none}
.mc .cb table.ot td.ac{white-space:nowrap;display:flex;gap:6px}
.mc .cb .mini{display:inline-flex;align-items:center;gap:5px;height:30px;padding:0 10px;border-radius:8px;border:1px solid #F1D4C0;background:#fff;color:#0B1220;font-size:11.5px;font-weight:600}.mc .cb .mini:hover{background:#FFF8F2}
.mc .ms{background:#FAFAFA;padding:14px 16px;gap:9px}
.mc .b{border-radius:12px;font-size:13px;box-shadow:0 1px 2px rgba(15,23,42,.05)}
.mc .b.in{background:#fff;border:1px solid #EEE8E2;border-radius:12px 12px 12px 4px}
.mc .b.out{background:#FFF1E8;border:1px solid #FBDCC7;border-radius:12px 12px 4px 12px}
.mc .b small{color:#9CA3AF;font-size:10.5px}.mc .b .at{display:inline-block;height:15px;line-height:15px;padding:0 6px;border-radius:8px;background:#E0F2FE;color:#075985;font-size:9.5px;font-weight:700}
.mc .cm{padding:10px 12px;gap:8px;border-top:1px solid #F1E3D8}
.mc .cm .tb{gap:6px}
.mc .cm .tb .tg,.mc .cm .tb .qrw select{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 12px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;color:#0B1220;font-size:12.5px;font-weight:600;box-shadow:none}
.mc .cm .tb .tg:hover{background:#FFF8F2;border-color:#FDC2A5}
.mc .cm .tb .qrw{position:relative;display:inline-flex;align-items:center}.mc .cm .tb .qrw .ic{position:absolute;left:10px;pointer-events:none}.mc .cm .tb .qrw select{padding-left:30px;max-width:170px}
.mc .cm textarea{min-height:48px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;font-size:13px;padding:12px 13px}
.mc .cm .sd{align-items:stretch}
.mc .cm .sd button#ms{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-width:92px;height:48px;padding:0 20px;border-radius:8px;border:0;background:#FF5A00;color:#fff;font-size:14px;font-weight:700;cursor:pointer;align-self:flex-end}.mc .cm .sd button#ms:hover{background:#E85100}.mc .cm .sd button#ms .ic{stroke:#fff}
.mc .cm .sd .pl{display:none}
.mcbd{display:none}
@media(max-width:900px){
  .mc .lk.dt{display:inline-flex}
  .mc .cb{position:fixed;left:0;right:0;bottom:0;max-height:78vh;max-height:78dvh;z-index:9996;border-radius:16px 16px 0 0;border:0;box-shadow:0 -12px 36px rgba(11,18,32,.28);transform:translateY(110%);transition:transform .2s;padding-bottom:max(14px,env(safe-area-inset-bottom))}
  .mc .cb.sheet{transform:none}
  .mc .mcbd.sh{display:block;position:fixed;inset:0;background:rgba(11,18,32,.38);z-index:9995}
}
@media(max-width:600px){
  .mc .cm .tb{display:none}.mc .cm.tools .tb{display:flex}
  .mc .cm .sd{gap:6px}.mc .cm .sd .pl{display:inline-flex;align-items:center;justify-content:center;width:44px;height:48px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;color:#0B1220;font-size:22px;font-weight:600;cursor:pointer;flex:none;align-self:flex-end}
  .mc .cm .sd button#ms{min-width:48px;padding:0 14px}.mc .cm .sd button#ms .sl{display:none}
}
.mc .vr{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #FBDCC7;background:#FFF8F2;border-radius:8px;font-size:12.5px}.mc .vr[hidden]{display:none}.mc .vr b{margin-right:auto;font-weight:600}.mc .vr .rd{width:9px;height:9px;border-radius:50%;background:#DC2626;animation:vrp 1s infinite;flex:none}.mc .vr audio{height:34px;flex:1;min-width:0}.mc .vr .mini{height:30px;padding:0 11px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;font-weight:600;font-size:12px;cursor:pointer}.mc .vr .mini.pr{background:#FF5A00;border-color:#FF5A00;color:#fff;display:inline-flex;align-items:center;gap:6px}.mc .vr .mini.pr .ic{stroke:#fff}
@keyframes vrp{50%{opacity:.25}}
.mc .cm .tb .tg.rec{background:#FEE2E2;border-color:#FCA5A5;color:#B91C1C}
.mc .b .tr{margin-top:6px;padding:6px 8px;border-radius:8px;background:rgba(15,23,42,.05);font-size:12px;line-height:1.45}.mc .b .tr b{display:block;font-size:10.5px;color:#075985;margin-bottom:2px}.mc .b .trl{font-size:10.5px;font-weight:700;color:#075985;margin:2px 0 3px}.mc .b .trl small,.mc .b .tr small{display:inline;color:#9CA3AF;font-weight:400;margin-left:4px}
@media(prefers-reduced-motion:reduce){.mc .cb{transition:none}.mc .vr .rd{animation:none}}
</style>
<div class="mcp" id="mcp" aria-live="polite"><div class="mcs" id="mcs"><span class="sp"></span><button type="button" data-s="open">Open</button><button type="button" class="gh" data-s="all">View All</button></div></div>
<div class="mcd" id="mcd" role="dialog" aria-label="Customer chat"><button class="cls" type="button" id="mcx" aria-label="Close chat">×</button><div id="mcdb" style="height:100%"></div></div>
@verbatim
<script>
(function(){
var CSRF=window.MCSRF,CAN=window.MCAN;
var CH={facebook:['f','Facebook'],instagram:['IG','Instagram'],whatsapp:['W','WhatsApp'],web:['💬','Website Chat']};
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function lnk(s){return esc(s).replace(/(https?:\/\/[^\s<]+)/g,function(u){return '<a href="'+u+'" target="_blank" rel="noopener">'+u+'</a>'})}
function api(u,o){o=o||{};o.headers=Object.assign({'Accept':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},o.headers||{});o.credentials='same-origin';return fetch(u,o).then(function(r){return r.json().catch(function(){return {}}).then(function(j){if(!r.ok&&!j.error){j.error='Error '+r.status}return j})})}
function post(u,obj){return api(u,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(obj||{})})}
function tm(iso){try{return new Date(iso).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}catch(e){return ''}}
function av(c){var l=(c.name||'?').trim().charAt(0).toUpperCase();return '<span class="mcav">'+(c.avatar?'<img src="'+esc(c.avatar)+'" alt="" referrerpolicy="no-referrer" onerror="this.remove()">':'')+esc(l)+'</span>'}
var ICO={facebook:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#1877F2"/><path d="M13.2 20v-7h2.3l.4-2.8h-2.7V8.5c0-.8.3-1.3 1.4-1.3H16V4.7c-.3 0-1.1-.1-2.1-.1-2.1 0-3.5 1.3-3.5 3.6v2H8v2.8h2.4v7" fill="#fff"/></svg>',
instagram:'<svg viewBox="0 0 24 24"><defs><linearGradient id="ig2" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#FEC053"/><stop offset=".4" stop-color="#F2203E"/><stop offset=".7" stop-color="#B729A8"/><stop offset="1" stop-color="#5342D6"/></linearGradient></defs><rect x="2" y="2" width="20" height="20" rx="6" fill="none" stroke="url(#ig2)" stroke-width="2.4"/><circle cx="12" cy="12" r="4.4" fill="none" stroke="url(#ig2)" stroke-width="2.4"/><circle cx="17.4" cy="6.6" r="1.3" fill="#B729A8"/></svg>',
whatsapp:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#25D366"/><path d="M16.7 14.3c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1-1.4-.7-2.4-1.5-3.2-3-.1-.2 0-.3.1-.4l.5-.6.2-.4c0-.1 0-.3 0-.4l-.7-1.7c-.2-.4-.4-.4-.6-.4h-.4c-.2 0-.4.1-.6.3-.6.6-.9 1.4-.8 2.3.2 1.1.8 2.1 1.5 3 1.1 1.4 2.5 2.4 4.2 3 .8.2 1.5.2 2.2-.1.6-.3 1.1-.9 1.2-1.5 0-.2 0-.3-.1-.4l-.4-.1Z" fill="#fff"/></svg>',
web:'<svg viewBox="0 0 24 24" fill="none" stroke="#F05200" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"><path d="M21 12a8 8 0 0 1-11.8 7L4 20l1.2-4.4A8 8 0 1 1 21 12Z"/><circle cx="9" cy="12" r=".7" fill="#F05200"/><circle cx="12.2" cy="12" r=".7" fill="#F05200"/><circle cx="15.4" cy="12" r=".7" fill="#F05200"/></svg>'};
var ICN={crown:'<path d="M3 8l4 4 5-7 5 7 4-4-2 11H5L3 8Z"/>',cart:'<path d="M3 4h2l2.4 11h10l2-8H6.5"/><circle cx="9" cy="19.5" r="1.4"/><circle cx="17" cy="19.5" r="1.4"/>',shield:'<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z"/>',truck:'<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',ret:'<path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/>',coin:'<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3 3 7 3s7-1.3 7-3V6M5 12v6c0 1.7 3 3 7 3s7-1.3 7-3v-6"/>',pin:'<path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.4"/>',box:'<path d="M3 8l9-5 9 5v9l-9 5-9-5V8Z"/><path d="M3 8l9 5 9-5M12 13v9"/>',doc:'<path d="M7 3h8l4 4v14H7V3Z"/><path d="M15 3v4h4M10 12h6M10 16h6"/>',bag:'<path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8a3 3 0 0 1 6 0"/>',clip:'<path d="M20 12l-7.5 7.5a5 5 0 0 1-7-7L13 5a3.4 3.4 0 0 1 5 5l-7.5 7.5a1.7 1.7 0 0 1-2.4-2.4L15 8.5"/>',bolt:'<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',send:'<path d="M21 3 3 10.5l7 2.5 2.5 7L21 3Z"/><path d="M10 13 21 3"/>',mic:'<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',user:'<circle cx="12" cy="8" r="4"/><path d="M4.5 20c.8-3.6 3.8-5.5 7.5-5.5s6.7 1.9 7.5 5.5"/>',info:'<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',ok:'<circle cx="12" cy="12" r="9" stroke="#16A34A"/><path d="m8 12.3 2.6 2.6L16.2 9" stroke="#16A34A"/>'};
function ic(n){return '<svg class="ic" viewBox="0 0 24 24">'+(ICN[n]||'')+'</svg>'}
function sbadge(s){s=String(s||'');var k=/deliver/i.test(s)?'ok':(/cancel|return|reject|fail/i.test(s)?'bad':(/approv|process|confirm|ship|dispatch|courier|hold|new/i.test(s)?'warn':'mut'));return '<span class="sb '+k+'">'+esc(s)+'</span>'}
function badge(ch){var c=CH[ch]||['?',ch];return '<span class="mcb ico '+esc(ch)+'" title="'+esc(c[1])+'">'+(ICO[ch]||c[0])+'</span>'}
var M=window.MChat={current:null,mute:false,smode:'on',_tid:Math.random().toString(36).slice(2,8)};
var SM=['on','assigned','urgent','off'],SL={on:'🔔 Sound on',assigned:'🔔 Assigned only',urgent:'🔔 Urgent only',off:'🔕 Sound off'};
try{var _m=localStorage.getItem('mchat_smode');if(!_m&&localStorage.getItem('mchat_mute')==='1')_m='off';if(SL[_m])M.smode=_m}catch(e){}
M.mute=M.smode==='off';
M.soundLabel=function(){return SL[M.smode]};M.ico=ICO;
M.cycleSound=function(){M.smode=SM[(SM.indexOf(M.smode)+1)%SM.length];M.mute=M.smode==='off';try{localStorage.setItem('mchat_smode',M.smode);localStorage.setItem('mchat_mute',M.mute?'1':'0')}catch(e){}
  try{if(M.smode!=='off'&&window.Notification&&Notification.permission==='default')Notification.requestPermission()}catch(e){}return M.smode};
M.toggleMute=function(){M.cycleSound();return M.mute};
M.wantsSound=function(it){if(M.smode==='off')return false;if(M.smode==='assigned')return !!it&&String(it.assigned_to||'')===String(window.MUID);if(M.smode==='urgent')return !!it&&(!!it.needs_human||it.priority==='high');return true};
/* ---------- conversation panel (used by drawer and inbox page) ---------- */
M.mount=function(el,id,opt){
  opt=opt||{};if(el._mc)el._mc.stop();
  var S={id:id,last:0,timer:null,d:null,kind:'text'};el._mc=S;
  el.innerHTML='<div class="mc"><div class="mcbd" id="mcbd"></div><div class="hd" id="mh"></div><details class="cb" data-noexport="1" id="mcu"'+(window.DF_EMBED?'':' open')+'></details><div class="ms" id="mm"></div><div class="cm" id="mf"></div></div>';
  var $=function(s){return el.querySelector(s)};
  S.stop=function(){if(S.vstop)S.vstop();clearInterval(S.timer);if(M.current===id)M.current=null;if(M._cur===S)M._cur=null};
  M.current=id;
  el.addEventListener('click',function(e){var b=e.target.closest('[data-rb]');if(!b||!S.send)return;var bb=b.closest('.b');S.send(null,{body:bb.dataset.b||'',kind:'text'});b.remove()});
  function trBlock(t){return '<div class="tr"><b>📝 AI লেখা রূপ</b>'+esc(t)+'<small>স্বয়ংক্রিয় লেখা, ভুল থাকতে পারে</small></div>'}
  function bub(m){var a=m.att?(m.att_type==='image'?'<a href="'+esc(m.att)+'" target="_blank"><img src="'+esc(m.att)+'" alt="" loading="lazy"></a>':(m.att_type==='audio'?'<audio controls preload="none" src="'+esc(m.att)+'" style="max-width:100%"></audio>':(m.att_type==='video'?'<video controls preload="none" src="'+esc(m.att)+'" style="max-width:100%;border-radius:8px"></video>':'<a href="'+esc(m.att)+'" target="_blank">📎 Attachment</a><br>'))):'';
    var tq=m.transcript&&m.transcript!==m.body?trBlock(m.transcript):'',tl=m.dir==='in'&&m.transcript&&m.transcript===m.body?'<div class="trl">📝 AI লেখা রূপ <small>ভুল থাকতে পারে</small></div>':'';
    return '<div class="b '+(m.dir==='in'?'in':'out')+(m.status==='failed'?' fail':'')+'" data-id="'+m.id+'" data-b="'+esc(m.body||'')+'">'+a+tl+lnk(m.body||'')+tq+'<small>'+(m.dir==='out'&&m.by?(m.is_ai?'<span class="at">AI Auto</span> ':esc(m.by)+' · '):'')+tm(m.at)+(m.dir==='out'&&m.status!=='failed'?(m.read?' · ✓✓ Read':' · ✓ Sent'):'')+(m.status==='failed'?' · ⚠ not delivered: '+esc(m.error||'')+(m.body?' <button type="button" class="rt" data-rb="1">Retry</button>':''):'')+'</small></div>'}
  function addMsgs(list){var box=$('#mm'),near=box.scrollHeight-box.scrollTop-box.clientHeight<120||!S.last,pill=box.querySelector('.mnp'),hadIn=false,fresh=!!S.last;list.forEach(function(m){if(m.id>S.last){var h=bub(m);if(pill)pill.insertAdjacentHTML('beforebegin',h);else box.insertAdjacentHTML('beforeend',h);S.last=m.id;if(m.dir==='in')hadIn=true}});
    if(near){box.scrollTop=box.scrollHeight;if(pill)pill.style.display='none'}else if(hadIn&&fresh&&pill)pill.style.display='block'}
  function head(){var d=S.d,t=d.thread,opts=Object.keys(d.statuses).map(function(k){return '<option value="'+k+'"'+(t.status===k?' selected':'')+'>'+d.statuses[k]+'</option>'}).join('');
    var ag='<option value="">Unassigned</option>'+d.agents.map(function(a){return '<option value="'+a.id+'"'+(t.assigned_to==a.id?' selected':'')+'>'+esc(a.name)+'</option>'}).join('');
    var lo=d.orders[0];
    var hs=t.needs_human?'<div style="margin:6px 0 0;padding:6px 9px;border:1px solid #FCA5A5;background:#FEF2F2;border-radius:8px;font-size:12px;line-height:1.4;color:#7F1D1D"><b>Handoff'+(t.priority==='high'?' · HIGH':'')+':</b> '+esc(t.handoff_reason||'')+(t.ai_summary?'<br>'+esc(t.ai_summary):'')+'</div>':'';
    $('#mh').innerHTML='<div class="r1">'+av(t)+'<div style="flex:1;min-width:0"><b>'+esc(t.name)+' '+badge(t.channel)+'</b><small>'+esc(t.phone||t.username||'No number yet')+' · '+esc(t.channel_name)+'</small></div></div>'+
      '<div class="r2"><select id="hs" title="Status"'+(CAN?'':' disabled')+'>'+opts+'</select><select id="ha" title="Assigned to"'+(CAN?'':' disabled')+'>'+ag+'</select>'+
      (d.customer?'<a class="lk" href="/admin/m/customers/'+d.customer.id+'/edit">'+ic('user')+'View Customer</a>':(t.phone?'<a class="lk" href="/admin/m/customers?q='+encodeURIComponent(t.phone)+'">'+ic('user')+'Find Customer</a>':''))+
      (lo?'<a class="lk" href="/admin/orders/'+lo.id+'">'+ic('box')+'View Order</a>':'')+'<button class="lk dt" type="button" id="hdt">'+ic('info')+'Details</button>'+(t.awaiting?'<span class="aw" title="The customer is waiting for a staff reply. Opening the chat does not clear this.">● Unread · Awaiting Reply</span>'+(CAN?'<button class="lk rs" type="button" id="hrs" title="Mark as handled without replying (for example the customer only said thanks)">'+ic('ok')+'Resolve · no reply needed</button>':''):(t.replied?'<span class="rp">✓ Replied</span>':''))+(t.channel==='web'&&CAN?'<button class="lk" type="button" id="hai" title="Claude AI auto-reply for this chat">'+(t.ai?'🤖 AI: ON':'🤖 AI: OFF')+'</button>':'')+(t.channel==='web'&&CAN?'<select id="hlang" class="lk" title="AI reply language for this chat. Auto follows the customer. Only wording changes, never the facts."'+'>'+[['auto','AI Language: Auto'+(t.ai_detected?' ('+t.ai_detected+')':'')],['bangla','AI: বাংলা'],['english','AI: English'],['banglish','AI: Banglish']].map(function(o){return '<option value="'+o[0]+'"'+((t.ai_lang||'auto')===o[0]?' selected':'')+'>'+o[1]+'</option>'}).join('')+'</select>':'')+(opt.drawer?'<button class="lk" type="button" id="hm" title="'+M.soundLabel()+'">'+(M.mute?'🔕':'🔔')+'</button>':'')+'</div>'+hs;
    $('#hs').onchange=function(){post('/admin/chat/thread/'+id+'/status',{status:this.value}).then(function(j){if(opt.onchange)opt.onchange();M.badge(j.unread);pull()})};
    var hdt=$('#hdt');if(hdt)hdt.onclick=function(){var u=$('#mcu'),bd=$('#mcbd');u.open=true;u.classList.add('sheet');bd.classList.add('sh');bd.onclick=function(){u.classList.remove('sheet');bd.classList.remove('sh')}};
    var hrs=$('#hrs');if(hrs)hrs.onclick=function(){hrs.disabled=true;post('/admin/chat/thread/'+id+'/status',{status:'resolved'}).then(function(j){S.d.thread.status='resolved';S.d.thread.awaiting=false;head();if(opt.onchange)opt.onchange();M.badge(j.unread)})};
    $('#ha').onchange=function(){post('/admin/chat/thread/'+id+'/assign',{user_id:this.value||null}).then(function(){if(opt.onchange)opt.onchange()})};
    var hlg=$('#hlang');if(hlg)hlg.onchange=function(){var v=hlg.value;post('/admin/chat/thread/'+id+'/lang',{lang:v}).then(function(j){if(j&&j.ok)t.ai_lang=v==='auto'?null:v})};
    var hai=$('#hai');if(hai)hai.onclick=function(){var on=!t.ai;post('/admin/chat/thread/'+id+'/ai',{on:on?1:0}).then(function(j){t.ai=j.ai;hai.textContent=j.ai?'🤖 AI: ON':'🤖 AI: OFF'})};
    var hm=$('#hm');if(hm)hm.onclick=function(){M.cycleSound();this.textContent=M.mute?'🔕':'🔔';this.title=M.soundLabel()}}
  function cust(){var d=S.d,t=d.thread,i=d.intel,o=d.orders,cur=o[0];
    var h='<summary>Customer · '+esc(t.cat)+' · '+t.orders+' orders · '+esc(t.risk)+' risk</summary><div class="chips"><span class="ch">'+ic('crown')+esc(t.cat)+' customer</span><span class="ch">'+ic('cart')+'Orders: '+t.orders+'</span><span class="ch rk-'+esc(t.risk)+'">'+ic('shield')+'Risk: '+esc(t.risk)+(i?' ('+i.fraud+')':'')+'</span>'+(i&&i.rate!=null?'<span class="ch">'+ic('truck')+'Delivered: '+i.rate+'%</span>':'')+(i?'<span class="ch">'+ic('ret')+'Returned: '+i.returned+'</span><span class="ch">'+ic('coin')+'Spent: ৳'+Math.round(i.spent).toLocaleString()+'</span>':'')+'</div>';
    if(!t.phone&&CAN)h+='<div style="display:flex;gap:6px;margin:6px 0"><input id="hp" placeholder="Customer mobile (01XXXXXXXXX)" style="padding:6px 8px;font-size:12px"><button class="mini" id="hpb" type="button" style="padding:6px 10px">Link</button></div>';
    if(d.customer)h+='<div class="idl">'+ic('pin')+'<span>'+esc(d.customer.name)+(d.customer.address?' · '+esc(d.customer.address):'')+'</span></div>';
    if(cur)h+='<div class="cur">'+ic('box')+'<b>Current Order #'+esc(cur.invoice)+'</b>'+sbadge(cur.status)+(cur.courier_status?'<span class="cs">'+esc(cur.courier_status)+'</span>':'')+'<span class="amt">৳'+Math.round(cur.total).toLocaleString()+'</span></div>';
    if(o.length){h+='<table class="ot"><thead><tr><th>Order ID</th><th>Date</th><th>Status</th><th>Amount</th>'+(CAN?'<th>Actions</th>':'')+'</tr></thead><tbody>'+o.map(function(x){return '<tr><td><a href="/admin/orders/'+x.id+'">'+esc(x.invoice)+'</a></td><td>'+esc(x.date)+'</td><td>'+sbadge(x.status)+'</td><td>৳'+Math.round(x.total).toLocaleString()+'</td>'+(CAN?'<td class="ac"><button class="mini" data-o="'+x.id+'" data-k="invoice" type="button">'+ic('doc')+'Invoice</button><button class="mini" data-o="'+x.id+'" data-k="tracking" type="button">'+ic('pin')+'Track</button></td>':'')+'</tr>'}).join('')+'</tbody></table>'}
    var u=$('#mcu');u.innerHTML=h;
    u.querySelectorAll('button[data-o]').forEach(function(b){b.onclick=function(){fillOrder(b.dataset.o,b.dataset.k);var bd=$('#mcbd');if(bd&&bd.classList.contains('sh')){u.classList.remove('sheet');bd.classList.remove('sh')}}});
    var pb=$('#hpb');if(pb)pb.onclick=function(){post('/admin/chat/thread/'+id+'/phone',{phone:$('#hp').value}).then(function(j){if(j.ok)load();else alert(j.error||'Invalid number')})}}
  function fillOrder(oid,k){api('/admin/chat/order/'+oid+'/text?kind='+k).then(function(j){var ta=$('#mt');ta.value=j.text||'';S.kind=k;ta.focus()})}
  function composer(){var d=S.d;
    if(!CAN){$('#mf').innerHTML='<div class="ro">You have view-only access to chats.</div>';return}
    $('#mf').innerHTML='<div class="pk" id="mpk"><input id="mpq" placeholder="Search product name or SKU…" style="margin-bottom:6px;padding:7px 9px"><div id="mpl"></div></div>'+
    '<div class="tb"><span class="qrw">'+ic('bolt')+'<select id="mq" title="Quick reply"><option value="">Quick reply</option>'+d.quick.map(function(q){return '<option value="'+q.id+'">'+esc(q.title)+'</option>'}).join('')+'</select></span>'+
    '<button class="tg" type="button" id="mpb">'+ic('bag')+'Product</button><button class="tg" type="button" id="mib">'+ic('doc')+'Invoice</button><button class="tg" type="button" id="mtb">'+ic('truck')+'Tracking</button><label class="tg" style="margin:0;cursor:pointer">'+ic('clip')+'Attach<input type="file" id="mfi" accept="image/*,.pdf" style="display:none"></label><button class="tg" type="button" id="mvb">'+ic('mic')+'Voice</button><button class="tg" type="button" id="mdb" title="Speak in Bangla and it is typed for you (Chrome / Edge)">'+ic('mic')+'বাংলা টাইপ</button></div><div class="vr" id="mvr" hidden></div>'+
    '<div class="sd"><button type="button" class="pl" id="mtl" aria-label="More tools">+</button><textarea id="mt" placeholder="Type a reply… (Enter to send, Shift+Enter for a new line)"></textarea><button type="button" id="ms">'+ic('send')+'<span class="sl">Send</span></button></div><div id="mer" class="err"></div>';
    var qm={};d.quick.forEach(function(q){qm[q.id]=q.body});
    $('#mq').onchange=function(){if(this.value){$('#mt').value=String(qm[this.value]).replace(/\{agent\}/g,d.me||'');S.kind='quick';$('#mt').focus();this.value=''}};
    function pickOrder(k){var o=S.d.orders;if(!o.length){$('#mer').textContent='This customer has no orders yet.';return}fillOrder(o[0].id,k)}
    $('#mib').onclick=function(){pickOrder('invoice')};$('#mtb').onclick=function(){pickOrder('tracking')};
    var tmr;$('#mpb').onclick=function(){var p=$('#mpk');p.style.display=p.style.display==='block'?'none':'block';if(p.style.display==='block')$('#mpq').focus()};
    $('#mpq').oninput=function(){clearTimeout(tmr);var q=this.value;tmr=setTimeout(function(){api('/admin/chat/products?q='+encodeURIComponent(q)).then(function(r){$('#mpl').innerHTML=(r||[]).map(function(p,i){return '<div class="it" data-i="'+i+'"><span>'+esc(p.name)+'</span><b>৳'+Math.round(p.price).toLocaleString()+'</b></div>'}).join('')||'<div style="color:#6B7280;padding:6px">No product found</div>';$('#mpl').querySelectorAll('.it').forEach(function(n){n.onclick=function(){var p=r[n.dataset.i];S.kind='product';S.img=p.image&&p.image.charAt(0)==='/'?p.image:null;$('#mt').value=p.name+'\n৳'+Math.round(p.price).toLocaleString()+'\n'+p.url;$('#mpk').style.display='none'}})})},250)};
    $('#mfi').onchange=function(){if(this.files[0])send(this.files[0])};
    /* ---- voice reply: record in the browser, encode to MP3 (plays everywhere, accepted by WhatsApp and Messenger), preview, then send ---- */
    var rc=null;function vrBar(h){var b=$('#mvr');b.hidden=!h;b.innerHTML=h||''}function fmtT(s){s=Math.floor(s);return Math.floor(s/60)+':'+('0'+s%60).slice(-2)}
    function lameLoad(){return window.lamejs&&lamejs.Mp3Encoder?Promise.resolve():new Promise(function(res,rej){var sc=document.createElement('script');sc.src='/js/lame.min.js';sc.onload=res;sc.onerror=rej;document.head.appendChild(sc)})}
    function toMp3(blob){return lameLoad().then(function(){return blob.arrayBuffer()}).then(function(buf){var AC=window.AudioContext||window.webkitAudioContext,ac;try{ac=new AC({sampleRate:44100})}catch(e){ac=new AC()}
      return ac.decodeAudioData(buf).then(function(ab){try{ac.close()}catch(e){}var ch=ab.getChannelData(0),n=ch.length,pcm=new Int16Array(n);for(var i=0;i<n;i++){var v=Math.max(-1,Math.min(1,ch[i]));pcm[i]=v<0?v*32768:v*32767}
        var enc=new lamejs.Mp3Encoder(1,ab.sampleRate,64),out=[],b2;for(var k=0;k<n;k+=1152){b2=enc.encodeBuffer(pcm.subarray(k,k+1152));if(b2.length)out.push(new Uint8Array(b2))}b2=enc.flush();if(b2.length)out.push(new Uint8Array(b2));return new Blob(out,{type:'audio/mpeg'})})})}
    function preview(mp3){var u=URL.createObjectURL(mp3);vrBar('<audio controls src="'+u+'"></audio><button class="mini" type="button" id="vrd">Delete</button><button class="mini pr" type="button" id="vrg">'+ic('send')+'Send voice</button>');
      $('#vrd').onclick=function(){vrBar('')};$('#vrg').onclick=function(){vrBar('');send(new File([mp3],'voice-message.mp3',{type:'audio/mpeg'}))}}
    function startRec(){if(!navigator.mediaDevices||!window.MediaRecorder){$('#mer').textContent='This browser cannot record audio.';return}
      navigator.mediaDevices.getUserMedia({audio:true}).then(function(st){var chunks=[],mr=new MediaRecorder(st);rc={mr:mr,t0:Date.now(),cancel:false};
        mr.ondataavailable=function(e){if(e.data&&e.data.size)chunks.push(e.data)};
        mr.onstop=function(){var c=rc;clearInterval(c.tick);st.getTracks().forEach(function(t){t.stop()});rc=null;var vb=$('#mvb');if(vb)vb.classList.remove('rec');if(c.cancel||!chunks.length){vrBar('');return}
          if((Date.now()-c.t0)<700){vrBar('');$('#mer').textContent='Recording was too short.';return}
          vrBar('<span class="vd">Preparing the voice message…</span>');toMp3(new Blob(chunks,{type:mr.mimeType||'audio/webm'})).then(preview).catch(function(){vrBar('');$('#mer').textContent='Could not prepare the recording. Try again.'})};
        mr.start();$('#mvb').classList.add('rec');
        function paint(){var el=(Date.now()-rc.t0)/1000;vrBar('<span class="rd"></span><b>'+fmtT(el)+' · Recording…</b><button class="mini" type="button" id="vrc">Cancel</button><button class="mini pr" type="button" id="vrs">Stop</button>');$('#vrc').onclick=function(){rc.cancel=true;mr.stop()};$('#vrs').onclick=function(){mr.stop()};if(el>=119)mr.stop()}
        paint();rc.tick=setInterval(paint,500)}).catch(function(){$('#mer').textContent='Microphone is blocked. Allow the microphone for this site in the browser.'})}
    $('#mvb').onclick=function(){if(rc){rc.mr.stop()}else startRec()};
    // Bangla speech-to-text (browser speech recognition, Chrome / Edge). Keeps listening through pauses, rebuilds the text from every result so nothing is lost or doubled, then the AI tidies spelling and wording. Nothing is sent until Send is pressed.
    (function(){var db=$('#mdb'),fx=$('#mfx'),un=$('#mdu'),SR=window.SpeechRecognition||window.webkitSpeechRecognition,rg=null,want=false,base='',orig=null,busy=false;if(!db)return;
      var ta=function(){return $('#mt')};
      var mode='refine',vl='bn';try{}catch(x){}
      var mb=$('#mvm'),lb=$('#mvl');
      function paint(){if(mb)[].forEach.call(mb.querySelectorAll('button'),function(b){var on=b.getAttribute('data-m')===mode;b.style.background=on?'#FF6A00':'none';b.style.color=on?'#fff':'inherit'});if(lb)lb.textContent=vl.toUpperCase()}
      if(mb)mb.onclick=function(e){var m=e.target.getAttribute&&e.target.getAttribute('data-m');if(!m)return;mode=m;try{localStorage.setItem('dfb_vmode',m)}catch(x){}paint()};
      if(lb)lb.onclick=function(){vl=vl==='bn'?'en':'bn';try{localStorage.setItem('dfb_vlang',vl)}catch(x){}paint()};
      paint();
      function lbl(t){db.lastChild.nodeValue=t}
      function join(a,b){a=(a||'').replace(/\s+$/,'');return a?(b?a+' '+b:a):(b||'')}
      function setv(v){var t=ta();t.value=v;t.dispatchEvent(new Event('input',{bubbles:true}))}
      function polish(auto){var t=ta(),v=t.value.trim();if(busy||v.length<3)return;busy=true;var before=t.value;if(fx)fx.disabled=true;lbl(' AI ঠিক করছে…');db.disabled=true;
        var tok=(document.querySelector('meta[name=csrf-token]')||{}).content||'';
        fetch('/admin/chat/polish',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':tok,'Accept':'application/json'},body:JSON.stringify({text:v})})
          .then(function(r){return r.json()}).then(function(j){if(j&&j.ok&&j.text&&ta().value===before){orig=before;setv(j.text);if(un)un.hidden=false}}).catch(function(){})
          .then(function(){busy=false;db.disabled=false;if(fx)fx.disabled=false;lbl(want?' থামান':'বাংলা টাইপ');ta().focus()})}
      if(un)un.onclick=function(){if(orig!==null){setv(orig);orig=null}un.hidden=true;ta().focus()};
      if(fx)fx.onclick=function(){polish(false)};
      if(!SR){db.disabled=true;db.title='Voice typing needs Chrome or Edge';return}
      function begin(){var r=new SR();rg=r;r.lang=vl==='en'?'en-US':'bn-BD';r.continuous=true;r.interimResults=true;r.maxAlternatives=1;
        r.onstart=function(){db.classList.add('rec');lbl(' থামান')};
        r.onresult=function(e){var fin='',im='';for(var i=0;i<e.results.length;i++){var x=e.results[i][0].transcript;if(e.results[i].isFinal)fin+=x+' ';else im+=x}
          setv(join(join(base,fin.trim()),im.trim()))};
        r.onerror=function(e){if(e.error==='not-allowed'||e.error==='service-not-allowed'){want=false;db.title='Allow the microphone for this site'}else if(e.error==='network'){want=false;db.title='Voice typing needs an internet connection'}};
        r.onend=function(){rg=null;if(want){base=ta().value.replace(/\s+$/,'');setTimeout(function(){if(want&&!rg){try{begin()}catch(x){want=false;finish()}}},200)}else finish()};
        r.start()}
      function finish(){db.classList.remove('rec');lbl('বাংলা টাইপ');var t=ta();if(mode==='refine'&&t.value.trim()!==(base0||'').trim())polish(true)}
      var base0='';
      db.onclick=function(){if(busy)return;if(want){want=false;if(rg)rg.stop();else finish();return}
        orig=null;if(un)un.hidden=true;base=ta().value.replace(/\s+$/,'');base0=base;want=true;try{begin()}catch(x){want=false;rg=null}};
      // typing by hand while listening stops the dictation so it never overwrites what was typed
      document.addEventListener('keydown',function(e){if(want&&e.target&&e.target.id==='mt'){want=false;if(rg)rg.stop()}},true);
    })();
    S.vstop=function(){if(rc){rc.cancel=true;try{rc.mr.stop()}catch(e){}}};
    $('#ms').onclick=function(){send()};$('#mtl').onclick=function(){$('#mf').classList.toggle('tools')};
    $('#mt').onkeydown=function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();send()}};
    S.send=send;
    function send(file,retry){if(S.sending&&!retry)return;var ta=$('#mt'),body=retry?retry.body:ta.value.trim(),kind=retry?retry.kind:(S.kind||'text'),img=retry?retry.img:S.img;file=retry?retry.file:file;if(!body&&!file)return;
      S.sending=true;$('#mer').textContent='';
      var box=$('#mm'),tmp=document.createElement('div');tmp.className='b out pend';tmp.innerHTML=lnk(body||(file?'📎 '+file.name:''))+'<small>Sending…</small>';var pl=box.querySelector('.mnp');if(pl)box.insertBefore(tmp,pl);else box.appendChild(tmp);box.scrollTop=box.scrollHeight;
      if(!retry){ta.value='';S.kind='text';S.img=null;$('#mfi').value=''}
      var fd=new FormData();fd.append('client_token',Date.now().toString(36)+Math.random().toString(36).slice(2,10));fd.append('body',body);fd.append('kind',kind);if(file)fd.append('file',file);else if(img)fd.append('image_url',img);
      function fail(msg){S.sending=false;tmp.className='b out fail';tmp.innerHTML=lnk(body||(file?'📎 '+file.name:''))+'<small>⚠ Failed: '+esc(msg)+' <button type="button" class="rt">Retry</button></small>';tmp.querySelector('.rt').onclick=function(){tmp.remove();send(null,{body:body,kind:kind,img:img,file:file})}}
      api('/admin/chat/thread/'+id+'/reply',{method:'POST',body:fd}).then(function(j){if(j.error&&!j.id){fail(j.error);return}S.sending=false;tmp.remove();if(j.ok===false)$('#mer').textContent='Saved, but not delivered: '+(j.error||'');pull();if(opt.onchange)opt.onchange()}).catch(function(){fail('Network error')})}}
  /* Unread is never cleared here: a new incoming message only shows up. Only a successful staff reply or Resolve changes the waiting state. */
  function pull(){return api('/admin/chat/thread/'+id+'/messages?after='+S.last).then(function(j){if(!j.messages)return;addMsgs(j.messages);if(opt.onchange&&j.messages.some(function(m){return m.dir==='in'}))opt.onchange();(j.transcripts||[]).forEach(function(x){var b=$('#mm .b[data-id="'+x.id+'"]');if(b&&!b.querySelector('.tr')&&x.transcript!==b.dataset.b){var sm=b.querySelector('small:last-child');sm?sm.insertAdjacentHTML('beforebegin',trBlock(x.transcript)):b.insertAdjacentHTML('beforeend',trBlock(x.transcript))}});var ch=false;if(S.d){if(j.status&&j.status!==S.d.thread.status){S.d.thread.status=j.status;ch=true}if(j.awaiting!==undefined&&(!!j.awaiting!==!!S.d.thread.awaiting||!!j.replied!==!!S.d.thread.replied)){S.d.thread.awaiting=!!j.awaiting;S.d.thread.replied=!!j.replied;ch=true}if(ch)head()}})}
  function paint(d,full){S.d=d;head();cust();if(full){composer();var box=$('#mm');box.innerHTML='<button type="button" class="mnp" id="mnp">↓ New Message</button>';var np=box.querySelector('.mnp');np.onclick=function(){box.scrollTop=box.scrollHeight;np.style.display='none'};box.onscroll=function(){if(box.scrollHeight-box.scrollTop-box.clientHeight<60)np.style.display='none'};S.last=0;S.first=d.messages.length?d.messages[0].id:0;S.more=!!d.has_more;olderBtn();addMsgs(d.messages)}else{addMsgs(d.messages);d.messages.forEach(function(m){if(m.dir==='out'&&m.read){var sm=$('#mm .b[data-id="'+m.id+'"] small');if(sm&&sm.innerHTML.indexOf('✓ Sent')>-1)sm.innerHTML=sm.innerHTML.replace('✓ Sent','✓✓ Read')}})}}
  function olderBtn(){var box=$('#mm'),b=box.querySelector('.mol');if(b)b.remove();if(!S.more)return;b=document.createElement('button');b.type='button';b.className='mol';b.textContent='Load older messages';box.insertBefore(b,box.firstChild);
    b.onclick=function(){b.textContent='Loading…';api('/admin/chat/thread/'+id+'/messages?before='+S.first).then(function(j){if(!j.messages)return;var h0=box.scrollHeight;S.more=!!j.has_more;if(j.messages.length)S.first=j.messages[0].id;var html=j.messages.map(bub).join('');b.remove();box.insertAdjacentHTML('afterbegin',html);olderBtn();box.scrollTop=box.scrollHeight-h0})}}
  M._c=M._c||{};
  M.refresh=function(){var c=M._cur;return c&&c.load?c.load():Promise.resolve()};
  function load(){var c=M._c[id];if(c&&!S.painted){S.painted=true;paint(c,true)}
    return api('/admin/chat/thread/'+id).then(function(d){if(!d.thread){if(!c)el.innerHTML='<div class="ro">Conversation not found.</div>';return}M._c[id]=d;var first=!S.painted;S.painted=true;paint(d,first);if(!S.pinged){S.pinged=1;post('/admin/chat/thread/'+id+'/read')}})}
  S.load=load;S.pull=pull;M._cur=S;
  load().then(function(){S.timer=setInterval(function(){if(!document.hidden&&document.body.contains(el))pull();else if(!document.body.contains(el))S.stop()},2000);if(opt.focus&&CAN){var t=$('#mt');if(t)t.focus()}});
  return S};
/* ---------- right-side drawer ---------- */
var dr=document.getElementById('mcd'),db=document.getElementById('mcdb');
M.open=function(id,focus){dr.classList.add('op');M.mount(db,id,{drawer:true,focus:focus});dismissCard(id,true)};
M.close=function(){dr.classList.remove('op');if(db._mc)db._mc.stop();db.innerHTML=''};
document.getElementById('mcx').onclick=M.close;
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&dr.classList.contains('op')&&!/INPUT|TEXTAREA|SELECT/.test((document.activeElement||{}).tagName||''))M.close()});
/* ---------- popups, grouped per customer ---------- */
var P=document.getElementById('mcp'),cards={},dismissed={},lastBeep=0;
function dismissCard(id,silent){var c=cards[id];if(c){clearTimeout(c.t);c.el.remove();delete cards[id]}if(!silent)dismissed[id]=Date.now();if(typeof sumUpdate==='function')sumUpdate()}
function cardHtml(it,n){var risk=it.risk||'Low';return '<div class="h">'+badge(it.channel)+'<span>'+esc(it.channel_name)+' — New Message</span><span class="cnt" style="display:'+(n>1?'inline':'none')+'">'+n+' new</span><button class="x" type="button" aria-label="Dismiss">×</button></div>'+
  '<div class="who">'+av(it)+'<div style="min-width:0"><b>'+esc(it.name)+'</b><small>'+esc(it.phone||it.username||it.channel_name)+' · '+tm(it.at)+'</small></div></div>'+
  '<div class="msg">“'+esc((it.preview||'').slice(0,160))+'”</div>'+
  '<div class="meta"><b>'+esc(it.cat)+' Customer</b> · '+it.orders+' Order'+(it.orders===1?'':'s')+' · <span class="rk-'+esc(risk)+'">'+esc(risk)+' Risk</span> · Awaiting reply · '+it.unread+' new</div>'+
  '<div class="bt"><button class="pr" type="button" data-a="open">Open Chat</button>'+(CAN?'<button type="button" data-a="reply">Reply</button>':'')+
  '<a href="'+(it.customer_id?'/admin/m/customers/'+it.customer_id+'/edit':'/admin/m/customers?q='+encodeURIComponent(it.phone||it.name))+'">View Customer</a>'+
  '<button type="button" data-a="order">View Order</button><button type="button" data-a="dis">Dismiss</button></div>'+
  (CAN?'<div class="qr"><input type="text" placeholder="Quick reply…" maxlength="500"><button type="button" class="pr" data-a="send" style="padding:6px 12px;font-size:12px;border-radius:8px;border:0;background:#FF5A00;color:#fff;font-weight:600">Send</button></div>':'')}
var mcs=document.getElementById('mcs');
function sumUpdate(){var ids=Object.keys(cards),all=[].slice.call(P.querySelectorAll('.mcc')),tot=0;ids.forEach(function(k){tot+=cards[k].n});
  all.forEach(function(el,i){el.style.display=(ids.length>2&&i>=2)?'none':''});
  if(ids.length>2){var last=cards[ids[ids.length-1]];var lastIt=null;ids.forEach(function(k){if(!lastIt||cards[k].it.at>lastIt.at)lastIt=cards[k].it});mcs.style.display='flex';mcs.querySelector('.sp').innerHTML='<b>'+tot+' New Messages</b> · Latest: '+esc(lastIt.name);mcs._id=lastIt.id}else mcs.style.display='none'}
mcs.onclick=function(ev){var b=ev.target.closest('[data-s]');if(!b)return;if(b.dataset.s==='open')M.open(mcs._id);else location.href='/admin/chat'};
function showCard(it){
  if(M.current===it.id&&dr.classList.contains('op')&&!document.hidden)return;
  var c=cards[it.id];if(c){c.n+=it.new_count}else{if(dismissed[it.id]&&Date.now()-dismissed[it.id]<45000)return;var e=document.createElement('div');e.className='mcc';P.prepend(e);c=cards[it.id]={el:e,n:it.new_count};while(Object.keys(cards).length>6){var ks=Object.keys(cards);dismissCard(ks[0],true)}}
  c.it=it;var q=c.el.querySelector('.qr input'),keep=q?q.value:'',open=q&&c.el.querySelector('.qr').style.display==='flex';
  c.el.innerHTML=cardHtml(it,c.n);var q2=c.el.querySelector('.qr input');if(q2){q2.value=keep;if(open)c.el.querySelector('.qr').style.display='flex'}
  c.el.onclick=function(ev){var b=ev.target.closest('[data-a],.x');if(!b)return;var a=b.dataset.a||'dis';
    if(a==='open')M.open(it.id);else if(a==='reply'){var r=c.el.querySelector('.qr');r.style.display='flex';r.querySelector('input').focus()}
    else if(a==='send'){var inp=c.el.querySelector('.qr input'),v=inp.value.trim();if(!v)return;var fd=new FormData();fd.append('body',v);api('/admin/chat/thread/'+it.id+'/reply',{method:'POST',body:fd}).then(function(j){if(j.id){inp.value='';dismissCard(it.id)}else inp.placeholder=j.error||'Failed'})}
    else if(a==='order'){api('/admin/chat/thread/'+it.id).then(function(d){location.href=d.orders&&d.orders[0]?'/admin/orders/'+d.orders[0].id:'/admin/orders?q='+encodeURIComponent(it.phone||'')})}
    else dismissCard(it.id)};
  c.el.onkeydown=function(ev){if(ev.key==='Enter'&&ev.target.tagName==='INPUT')c.el.querySelector('[data-a=send]').click()};
  sumUpdate();clearTimeout(c.t);c.t=setTimeout(function(){if(!c.el.matches(':hover')&&!c.el.querySelector('.qr input:focus')&&!(c.el.querySelector('.qr input')||{}).value)dismissCard(it.id);},25000);
}
function beep(){if(M.smode==='off'||Date.now()-lastBeep<8000)return;lastBeep=Date.now();try{var A=window.AudioContext||window.webkitAudioContext,a=M._a||(M._a=new A()),n=a.currentTime;[[880,0],[1175,.16]].forEach(function(f){var o=a.createOscillator(),g=a.createGain();o.frequency.value=f[0];o.type='sine';g.gain.setValueAtTime(.0001,n+f[1]);g.gain.exponentialRampToValueAtTime(.25,n+f[1]+.02);g.gain.exponentialRampToValueAtTime(.0001,n+f[1]+.2);o.connect(g);g.connect(a.destination);o.start(n+f[1]);o.stop(n+f[1]+.22)})}catch(e){}}
/* ---------- unread badges: sidebar, tab title, favicon ---------- */
var baseTitle=document.title.replace(/^\(\d+\)\s*/,''),icon=null;
M.badge=function(n,fromStore){n=+n||0;M.unread=n;if(!fromStore){try{localStorage.setItem('mchat_unread',n+'|'+Date.now())}catch(e){}}var b=document.getElementById('chatBadge');if(b){b.textContent=n>99?'99+':n;b.style.display=n?'':'none'}
  document.title=(n?'('+n+') ':'')+baseTitle;
  try{var c=document.createElement('canvas');c.width=c.height=64;var x=c.getContext('2d');x.fillStyle='#0B1220';x.beginPath();x.roundRect?x.roundRect(4,4,56,56,14):x.rect(4,4,56,56);x.fill();x.fillStyle='#FF5A00';x.font='bold 36px system-ui';x.textAlign='center';x.fillText('M',32,46);
    if(n){x.fillStyle='#E11D48';x.beginPath();x.arc(46,18,17,0,7);x.fill();x.fillStyle='#fff';x.font='bold 22px system-ui';x.fillText(n>9?'9+':String(n),46,26)}
    if(!icon){icon=document.querySelector('link[rel~=icon]');if(!icon){icon=document.createElement('link');icon.rel='icon';document.head.appendChild(icon)}}icon.type='image/png';icon.href=c.toDataURL('image/png')}catch(e){}};
document.addEventListener('click',function(){try{if(!M._a){var A=window.AudioContext||window.webkitAudioContext;M._a=new A()}if(M._a.state==='suspended')M._a.resume()}catch(e){}},{once:false,passive:true});
window.addEventListener('storage',function(e){if(e.key==='mchat_unread'&&e.newValue){var v=+e.newValue.split('|')[0];if(v!==M.unread)M.badge(v,true)}else if(e.key==='mchat_smode'&&SL[e.newValue]){M.smode=e.newValue;M.mute=e.newValue==='off'}});
/* ---------- real-time poll ---------- */
var since=null,busy=false;
var pdelay=4000;
function tick(){if(busy)return;busy=true;var t0=Date.now();api('/admin/chat/poll'+(since===null?'':'?since='+since)).then(function(j){busy=false;pdelay=Date.now()-t0>1500?12000:4000;if(j.last_id===undefined)return;
  var first=since===null;since=j.last_id;M.badge(j.unread);
  if(!document.hidden){try{localStorage.setItem('mchat_vis',String(Date.now()))}catch(e){}}
  if(!first&&j.items&&j.items.length){var any=false,snd=false,claim=true;
    try{var vis=+(localStorage.getItem('mchat_vis')||0),pv=(localStorage.getItem('mchat_ann')||'0|0').split('|');
      if(document.hidden&&Date.now()-vis<6000)claim=false;
      else if(+pv[0]>=j.last_id&&Date.now()-(+pv[1])<20000&&pv[2]!==M._tid)claim=false;
      if(claim)localStorage.setItem('mchat_ann',j.last_id+'|'+Date.now()+'|'+M._tid)}catch(e){}
    j.items.forEach(function(it){if(M.current===it.id&&!document.hidden){return}any=true;showCard(it);if(claim){if(M.wantsSound(it))snd=true;
      try{if(document.hidden&&window.Notification&&Notification.permission==='granted'&&M.smode!=='off'){var nt=new Notification(it.channel_name+' — '+it.name,{body:(it.preview||'').slice(0,120),tag:'mc'+it.id});nt.onclick=function(){window.focus();M.open(it.id);nt.close()}}}catch(e){}}});
    if(any&&claim&&snd)beep();if(M.onNew)M.onNew(j.items)}
  }).catch(function(){busy=false;pdelay=12000})}
if(!window.DF_EMBED){tick();(function sched(){setTimeout(function(){if(!document.hidden||Math.random()<.3)tick();sched()},pdelay)})();
document.addEventListener('visibilitychange',function(){if(!document.hidden)tick()});}
})();
</script>
@endverbatim
@endif
