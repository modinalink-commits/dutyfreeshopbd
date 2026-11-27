@php $__cu = auth()->user(); $__P = \App\Http\Middleware\ModulePermission::class; @endphp
@if($__P::can($__cu, 'orders', 'view'))
<script>window.MCSRF=@json(csrf_token());window.MCAN=@json($__P::can($__cu, 'orders', 'edit'));window.MUID=@json($__cu->id);</script>
<style>
.mcp{position:fixed;top:64px;right:18px;z-index:9990;display:flex;flex-direction:column;gap:10px;width:350px;max-width:calc(100vw - 28px)}
.mcc{background:#fff;border:1.5px solid #FDC2A5;border-radius:16px;box-shadow:0 14px 34px rgba(11,18,32,.22);padding:12px;animation:mcin .22s ease-out;font-size:13px;color:#0C161F}
.mcs{background:#0C161F;color:#fff;border-radius:14px;padding:10px 12px;font-size:12.5px;display:none;align-items:center;gap:8px;box-shadow:0 10px 26px rgba(11,18,32,.28);animation:mcin .22s ease-out}.mcs b{font-weight:700}.mcs .sp{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.mcs button{padding:5px 10px;font-size:12px;border-radius:8px;border:1px solid #F95505;background:#F95505;color:#fff;font-weight:600;cursor:pointer}.mcs button.gh{background:transparent;border-color:#4B5563}
@media(prefers-reduced-motion:reduce){.mcc,.mcs{animation:none!important}}
@keyframes mcin{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:none}}
.mcc .h{display:flex;align-items:center;gap:8px;font-weight:700;font-size:12.5px}.mcc .h .x{margin-left:auto;background:none;color:#6B7280;padding:0 4px;font-size:18px;line-height:1}
.mcc .cnt{background:#F95505;color:#fff;border-radius:20px;font-size:11px;padding:1px 8px}
.mcc .who{display:flex;gap:10px;align-items:center;margin:10px 0 6px}.mcc .who b{display:block;font-size:14px}.mcc .who small{color:#6B7280}
.mcc .msg{background:#FFF6EF;border-radius:10px;padding:8px 10px;margin:4px 0;font-size:13px;line-height:1.4;word-break:break-word}
.mcc .meta{font-size:12px;color:#374151;margin:6px 0 8px}.mcc .meta .rk-Low{color:#166534}.mcc .meta .rk-Medium{color:#B45309}.mcc .meta .rk-High{color:#B91C1C}.mcc .meta b{font-weight:700}
.mcc .bt{display:flex;flex-wrap:wrap;gap:6px}.mcc .bt button,.mcc .bt a{padding:6px 10px;font-size:12px;border-radius:8px;border:1px solid #FDC2A5;background:#fff;color:#0C161F;text-decoration:none;font-weight:600;cursor:pointer}
.mcc .bt .pr{background:#F95505;border-color:#F95505;color:#fff}.mcc .qr{display:none;gap:6px;margin-top:8px}.mcc .qr input{flex:1;padding:7px 9px;font-size:12.5px}
.mcav{width:38px;height:38px;border-radius:50%;background:#FFF1E8;color:#C2410C;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;overflow:hidden;position:relative}.mcav img{width:100%;height:100%;object-fit:cover}
.mcb{display:inline-flex;width:22px;height:22px;border-radius:7px;color:#fff;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}.mcb.facebook{background:#1877F2}.mcb.instagram{background:linear-gradient(45deg,#F9A03F,#E1306C,#833AB4)}.mcb.whatsapp{background:#25D366}.mcb.web{background:#F95505}
.mcd{position:fixed;top:0;right:0;bottom:0;width:460px;max-width:100vw;background:#fff;z-index:9995;box-shadow:-16px 0 40px rgba(11,18,32,.25);display:flex;flex-direction:column;transform:translateX(105%);transition:transform .2s}.mcd.op{transform:none}
.mcd .cls{position:absolute;top:10px;right:12px;background:none;color:#6B7280;font-size:22px;padding:0 6px;z-index:2}
.mc{display:flex;flex-direction:column;height:100%;min-height:0;font-size:13px;color:#0C161F}
.mc .hd{padding:12px 44px 10px 14px;border-bottom:1px solid #FFE7D6;display:flex;flex-direction:column;gap:8px}
.mc .hd .r1{display:flex;gap:10px;align-items:center}.mc .hd .r1 b{font-size:15px;display:block}.mc .hd .r1 small{color:#6B7280}
.mc .hd .r2{display:flex;gap:6px;flex-wrap:wrap;align-items:center}.mc .hd select{width:auto;padding:5px 8px;font-size:12px}
.mc .aw{display:inline-flex;align-items:center;height:26px;padding:0 10px;border-radius:13px;background:#FFF1E8;border:1px solid #FDBA8C;color:#C2410C;font-size:11.5px;font-weight:700;white-space:nowrap}.mc .rp{display:inline-flex;align-items:center;height:26px;padding:0 10px;border-radius:13px;background:#DCFCE7;border:1px solid #BBF7D0;color:#166534;font-size:11.5px;font-weight:600;white-space:nowrap}
.mc .lk{padding:5px 10px;font-size:12px;border-radius:8px;border:1px solid #FDC2A5;background:#fff;color:#0C161F;text-decoration:none;font-weight:600;cursor:pointer}
.mc .cb{border-bottom:1px solid #FFE7D6;padding:8px 14px;background:#FFFBF8;max-height:38%;overflow:auto}.mc .cb summary{cursor:pointer;font-weight:700;font-size:12.5px;padding:2px 0}
.mc .cb .st{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0}.mc .cb .st span{background:#fff;border:1px solid #FFE7D6;border-radius:8px;padding:3px 8px;font-size:12px}
.mc .cb table{font-size:12px}.mc .cb td{padding:5px 6px 5px 0}.mc .cb .cur{background:#FFF1E8;border-radius:8px;padding:6px 8px;margin:4px 0;font-size:12px}
.mc .cb .mini{padding:2px 7px;font-size:11px;border-radius:6px;border:1px solid #FDC2A5;background:#fff;color:#0C161F;font-weight:600;cursor:pointer}
.mc .ms{flex:1;overflow:auto;padding:12px 14px;background:#F8F5F2;display:flex;flex-direction:column;gap:8px;min-height:120px}
.mc .b{max-width:82%;padding:8px 11px;border-radius:14px;line-height:1.45;word-break:break-word;white-space:pre-wrap;font-size:13px}
.mc .b.in{align-self:flex-start;background:#fff;border:1px solid #EDE7E1;border-bottom-left-radius:4px}.mc .b.out{align-self:flex-end;background:#FFE3D2;border-bottom-right-radius:4px}
.mc .mol{align-self:center;flex:none;padding:5px 12px;font-size:12px;border-radius:14px;border:1px solid #FDC2A5;background:#fff;color:#0C161F;font-weight:600;cursor:pointer}
@media(max-width:900px){.mc .cm .tb .tg,.mc .cm select,.mc .cm .sd button,.mc .lk,.mc .hd select{min-height:44px}.mc .cm{position:sticky;bottom:0;padding-bottom:max(8px,env(safe-area-inset-bottom))}.mc .cm textarea{font-size:16px}}
.mc .b.fail{background:#FEE2E2}.mc .b.pend{opacity:.7}.mc .b .rt{margin-left:6px;padding:1px 8px;font-size:11px;border-radius:6px;border:1px solid #DC2626;background:#fff;color:#B91C1C;font-weight:700;cursor:pointer}
.mc .mnp{position:sticky;bottom:4px;align-self:center;display:none;padding:5px 12px;font-size:12px;border-radius:14px;border:0;background:#F95505;color:#fff;font-weight:700;box-shadow:0 4px 12px rgba(11,18,32,.25);cursor:pointer;flex:none}.mc .b small{display:block;color:#6B7280;font-size:10.5px;margin-top:3px}.mc .b img{width:auto;height:auto;max-width:min(240px,100%);max-height:240px;object-fit:contain;border-radius:8px;display:block;margin-bottom:4px;cursor:zoom-in;background:#F3F4F6}.mc .b video{max-width:min(260px,100%);max-height:240px}.mc .b audio{width:240px;max-width:100%}.mc .b a{color:#C2410C}
.mc .cm{border-top:1px solid #FFE7D6;padding:8px 10px;display:flex;flex-direction:column;gap:6px;background:#fff}
.mc .cm .tb{display:flex;flex-wrap:wrap;gap:5px;align-items:center}.mc .cm .tb select{width:auto;max-width:160px;padding:5px 7px;font-size:12px}
.mc .cm .tb .tg{padding:5px 9px;font-size:12px;border-radius:8px;border:1px solid #FDC2A5;background:#fff;color:#0C161F;font-weight:600;cursor:pointer}
.mc .cm textarea{min-height:44px;max-height:120px;resize:vertical;font-size:13px}.mc .cm .sd{display:flex;gap:8px;align-items:flex-end}.mc .cm .sd textarea{flex:1}
.mc .pk{border:1px solid #FFE7D6;border-radius:10px;padding:6px;max-height:170px;overflow:auto;display:none}.mc .pk div.it{padding:6px 8px;border-radius:8px;cursor:pointer;display:flex;justify-content:space-between;gap:8px}.mc .pk div.it:hover{background:#FFF1E8}
.mc .ro{padding:10px 14px;color:#6B7280;font-size:12px;border-top:1px solid #FFE7D6}
@media(max-width:600px){.mcp{top:56px;right:8px;left:8px;width:auto;max-width:none}}
/* ===== premium messenger redesign (visual only) ===== */
.mc,.mbx,.fbar,#mcp,.mcp{font-family:Inter,'Bornomala',sans-serif}.mc button,.mc input,.mc textarea,.mc select,.mbx button,.mbx input,.mbx select,.fbar button,.mcp button{font-family:inherit}.mbx .lm{font-size:12.5px}.mbx .l3{font-size:11.5px}.mbx .bg,.mbx .st2{font-size:11px}.msla{font-size:11px}
.mc .ic{width:15px;height:15px;flex:none;fill:none;stroke:#F95505;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.mcb.ico{background:none!important;width:auto;height:auto}.mcb.ico svg{width:100%;height:100%;display:block}.mcc .h .mcb.ico{width:22px;height:22px}.mc .hd .r1 b .mcb.ico{width:18px;height:18px;vertical-align:-3px;margin-left:4px}
.mc .hd{padding:12px 44px 10px 14px;gap:9px;background:#fff}
.mc .hd .r1 b{font-size:14px;font-weight:700}.mc .hd .r1 small{font-size:11.5px}
.mc .hd .r2{gap:6px}.mc .hd select{height:32px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;font-size:12px;color:#0C161F}
.mc .lk{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 11px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;color:#0C161F;font-size:12px;font-weight:600;box-shadow:none}.mc .lk:hover{background:#FFF1E8;border-color:#FDC2A5}
.mc .lk.cls{background:#0B1B33!important;color:#fff!important;border:1.5px solid #F95505!important;font-weight:700;padding:0 14px}.mc .lk.cls:hover{background:#F95505!important;border-color:#0B1B33!important}.mc .lk.rpt{background:#fff;color:#B91C1C;border:1px solid #FCA5A5;font-weight:600}.mc .lk.rpt.on{background:#FEE2E2;border-color:#B91C1C;font-weight:700}.mc .lk.rop{background:#fff;color:#0B1B33;border:1.5px solid #0B1B33;font-weight:700}.mc .clsd{display:inline-flex;align-items:center;height:32px;padding:0 10px;border-radius:8px;background:#F1F5F9;border:1px solid #CBD5E1;color:#334155;font-size:12px;font-weight:700}.mc .mcm{display:inline-flex;align-items:center;height:26px;padding:0 9px;border-radius:13px;font-size:12px;font-weight:600;background:#FEE4E2;border:1px solid #FECDCA;color:#B42318;white-space:nowrap}
.mc .fuc{display:inline-flex;align-items:center;height:26px;padding:0 9px;border-radius:13px;font-size:12px;font-weight:600;background:#FEF0C7;color:#B54708;white-space:nowrap}.fuc.due{background:#FEE4E2;color:#B42318}
.clsw{margin-top:6px;padding:8px 10px;border:1px solid #FCD34D;background:#FFFBEB;border-radius:8px;font-size:12.5px;color:#0B1B33}.mc .clsw div{display:flex;gap:6px;margin-top:6px;flex-wrap:wrap}.mc .clsw input{flex:1;min-width:160px;height:32px;border:1px solid #D1D5DB;border-radius:8px;padding:0 8px;font-size:13px}.mc .lk.rs{border-color:#BBF7D0;background:#fff;color:#166534}.mc .lk.rs:hover{background:#DCFCE7}
.mc .lk.dt{display:none}
.mc .aw{height:32px;border-radius:8px;font-size:12px}.mc .rp{height:32px;border-radius:8px;font-size:12px}
.mc .cb{padding:8px 14px 10px;background:#fff;max-height:42%}.mc .cb summary{font-size:12.5px;color:#0C161F}
.mc .cb .chips{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0}
.mc .cb .ch{display:inline-flex;align-items:center;gap:6px;height:30px;padding:0 11px;border-radius:999px;border:1px solid #F1E3D8;background:#fff;color:#0C161F;font-size:11px;font-weight:600;white-space:nowrap}
.mc .cb .ch.rk-Low{color:#166534}.mc .cb .ch.rk-Medium{color:#B45309}.mc .cb .ch.rk-High{color:#B91C1C}
.mc .cb .idl{display:flex;align-items:flex-start;gap:7px;font-size:11.5px;color:#6B7280;margin:2px 0 8px;line-height:1.4}.mc .cb .idl .ic{margin-top:1px}
.mc .cb .cur{display:flex;align-items:center;gap:9px;background:#FFF1E8;border:1px solid #FBE1D0;border-radius:9px;padding:8px 11px;margin:0 0 9px;font-size:12.5px}.mc .cb .cur b{font-weight:700}.mc .cb .cur .cs{color:#6B7280;font-size:11px}.mc .cb .cur .amt{margin-left:auto;font-weight:700}
.mc .sb{display:inline-block;height:20px;line-height:20px;padding:0 9px;border-radius:999px;font-size:10.5px;font-weight:700;white-space:nowrap}
.mc .sb.warn{background:#FFEFD9;color:#B45309}.mc .sb.ok{background:#DCFCE7;color:#166534}.mc .sb.bad{background:#FEE2E2;color:#B91C1C}.mc .sb.mut{background:#F3F4F6;color:#4B5563}
.mc .cb table.ot{width:100%;border-collapse:separate;border-spacing:0;font-size:12px;border:1px solid #F1E3D8;border-radius:9px}
.mc .cb table.ot th{position:sticky;top:0;z-index:1;background:#FAF6F2;text-align:left;font-size:11px;font-weight:700;color:#4B5563;padding:7px 9px;border-bottom:1px solid #F1E3D8}
.mc .cb table.ot td{padding:6px 9px;border-bottom:1px solid #F5EEE8;vertical-align:middle}.mc .cb table.ot tr:last-child td{border-bottom:0}
.mc .cb table.ot td a{color:#1D5FA8;font-weight:600;text-decoration:none}
.mc .cb table.ot td.ac{white-space:nowrap;display:flex;gap:6px}
.mc .cb .mini{display:inline-flex;align-items:center;gap:5px;height:30px;padding:0 10px;border-radius:8px;border:1px solid #F1D4C0;background:#fff;color:#0C161F;font-size:11.5px;font-weight:600}.mc .cb .mini:hover{background:#FFF1E8}
.mc .ms{background:#F8FAFC;padding:14px 16px;gap:9px}
.mc .b{border-radius:12px;font-size:13px;box-shadow:0 1px 2px rgba(15,23,42,.05)}
.mc .b.in{background:#fff;border:1px solid #EEE8E2;border-radius:12px 12px 12px 4px}
.mc .b.out{background:#FFF1E8;border:1px solid #FBDCC7;border-radius:12px 12px 4px 12px}
.mc .b small{color:#9CA3AF;font-size:10.5px}.mc .b .at{display:inline-block;height:15px;line-height:15px;padding:0 6px;border-radius:8px;background:#E0F2FE;color:#075985;font-size:9.5px;font-weight:700}
.mc .cm{padding:10px 12px;gap:8px;border-top:1px solid #F1E3D8}
.mc .cm .tb{gap:6px}
.mc .cm .tb .tg,.mc .cm .tb .qrw select{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 12px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;color:#0C161F;font-size:12.5px;font-weight:600;box-shadow:none}
.mc .cm .tb .tg:hover{background:#FFF1E8;border-color:#FDC2A5}
.mc .cm .tb .qrw{position:relative;display:inline-flex;align-items:center}.mc .cm .tb .qrw .ic{position:absolute;left:10px;pointer-events:none}.mc .cm .tb .qrw select{padding-left:30px;max-width:170px}
.mc .cm textarea{min-height:48px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;font-size:13px;padding:12px 13px}
.mc .cm .sd{align-items:stretch}
.mc .cm .sd button#ms{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-width:92px;height:48px;padding:0 20px;border-radius:8px;border:0;background:#F95505;color:#fff;font-size:14px;font-weight:700;cursor:pointer;align-self:flex-end}.mc .cm .sd button#ms:hover{background:#E85100}.mc .cm .sd button#ms .ic{stroke:#fff}
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
  .mc .cm .sd{gap:6px}.mc .cm .sd .pl{display:inline-flex;align-items:center;justify-content:center;width:44px;height:48px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;color:#0C161F;font-size:22px;font-weight:600;cursor:pointer;flex:none;align-self:flex-end}
  .mc .cm .sd button#ms{min-width:48px;padding:0 14px}.mc .cm .sd button#ms .sl{display:none}
}
.mc .vr{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #FBDCC7;background:#FFF1E8;border-radius:8px;font-size:12.5px}.mc .vr[hidden]{display:none}.mc .vr b{margin-right:auto;font-weight:600}.mc .vr .rd{width:9px;height:9px;border-radius:50%;background:#B91C1C;animation:vrp 1s infinite;flex:none}.mc .vr audio{height:34px;flex:1;min-width:0}.mc .vr .mini{height:30px;padding:0 11px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;font-weight:600;font-size:12px;cursor:pointer}.mc .vr .mini.pr{background:#F95505;border-color:#F95505;color:#fff;display:inline-flex;align-items:center;gap:6px}.mc .vr .mini.pr .ic{stroke:#fff}
@keyframes vrp{50%{opacity:.25}}
.mc .cm .tb .tg.rec{background:#FEE2E2;border-color:#FCA5A5;color:#B91C1C}
/* Inbox v2, part 2 (14 Nov 2026): internal notes, order cards in the timeline, AI drafts, Polish / Shorter, Send and next */
.mc .nt{align-self:center;max-width:86%;background:#FFFAEB;border:1px solid #FEDF89;border-radius:10px;padding:7px 10px;font-size:12.5px;color:#7A2E0E;line-height:1.45;white-space:pre-wrap;word-break:break-word}.mc .nt b{display:block;font-size:11px}.mc .nt small{display:block;font-size:10.5px;color:#93370D;margin-top:2px}
.mc .oe{align-self:center;display:flex;align-items:center;gap:8px;max-width:92%;background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:6px 10px;font-size:12px;color:#0B1B33;text-decoration:none}.mc .oe small{color:#6B7280;white-space:nowrap}.mc .oe:hover{border-color:#FDC2A5}
.mc .cm .ax{display:flex;flex-wrap:wrap;gap:6px;align-items:center}.mc .cm .ax .tg{display:inline-flex;align-items:center;height:32px;padding:0 11px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;color:#0C161F;font-size:12.5px;font-weight:600;cursor:pointer}.mc .cm .ax .tg:hover{background:#FFF1E8;border-color:#FDC2A5}.mc .cm .ax .tg:disabled{opacity:.55;cursor:default}.mc .cm .ax .tg[hidden]{display:none}
.mc .cm .axt{display:inline-flex;gap:3px;padding:3px;background:#F2F4F7;border-radius:9px;margin-right:4px}.mc .cm .axt button{height:26px;padding:0 10px;border:0;border-radius:7px;background:transparent;color:#475467;font-size:12.5px;font-weight:600;cursor:pointer}.mc .cm .axt button.on{background:#fff;color:#0B1B33;box-shadow:0 1px 2px rgba(16,24,40,.08)}
.mc .cm.note textarea{background:#FFFAEB;border-color:#FEDF89}.mc .cm.note .sd button#ms{background:#B54708}.mc .cm.note #msn,.mc .cm.note #mad,.mc .cm.note #mfx,.mc .cm.note #msh{display:none}
.mc .cm .dr{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}.mc .cm .dr button{text-align:left;border:1px solid #E5E7EB;background:#F9FAFB;border-radius:10px;padding:8px 10px;font-size:12.5px;line-height:1.45;color:#0B1B33;cursor:pointer;white-space:pre-wrap;word-break:break-word;max-height:150px;overflow:auto}.mc .cm .dr button:hover{border-color:#FDC2A5;background:#FFF7ED}.mc .cm .dr button b{display:block;font-size:10.5px;letter-spacing:.04em;text-transform:uppercase;color:#475467;margin-bottom:3px}
.mc .cm .sd .sn{height:48px;padding:0 12px;border-radius:8px;border:0;background:#0B1B33;color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;align-self:flex-end;white-space:nowrap}.mc .cm .sd .sn:hover{background:#16305C}
@media(max-width:900px){.mc .cm .dr{grid-template-columns:1fr}.mc .cm .sd .sn{display:none}.mc .cm .ax .tg,.mc .cm .axt button{min-height:36px}}
.mc .b .tr{margin-top:6px;padding:9px 11px;border-radius:8px;background:rgba(15,23,42,.05);font-size:15.5px;line-height:1.55;color:#0C161F;font-weight:500}.mc .b .tr b{display:block;font-size:11.5px;color:#075985;margin-bottom:3px}.mc .b .trl{font-size:10.5px;font-weight:700;color:#075985;margin:2px 0 3px}.mc .b .trl small{display:inline;color:#9CA3AF;font-weight:400;margin-left:4px}.mc .b .tr small{display:block;color:#9CA3AF;font-weight:400;font-size:11px;margin-top:3px}
@media(prefers-reduced-motion:reduce){.mc .cb{transition:none}.mc .vr .rd{animation:none}}

/* Owner request 11 Nov 2026: chat header action bar on ONE tidy line, plus a Call button that dials from our dialer */
.mc .hd .r2{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;gap:6px}.mc .hd .r2::-webkit-scrollbar{display:none}
.mc .hd .r2>*{flex:0 0 auto;white-space:nowrap;margin:0}.mc .hd .r2 .lk,.mc .hd .r2 .aw,.mc .hd .r2 .rp,.mc .hd .r2 select{height:32px;display:inline-flex;align-items:center;gap:6px;padding:0 11px;border-radius:8px;line-height:1}
.mc .hd .r2 .call{background:#16A34A;border-color:#16A34A;color:#fff;font-weight:700}.mc .hd .r2 .call .ic{stroke:#fff}.mc .hd .r2 .call:hover{background:#15803D}
</style>
<div class="mcp" id="mcp" aria-live="polite"><div class="mcs" id="mcs"><span class="sp"></span><button type="button" data-s="open">Open</button><button type="button" class="gh" data-s="all">View All</button></div></div>
<div class="mcd" id="mcd" role="dialog" aria-label="Customer chat"><button class="cls" type="button" id="mcx" aria-label="Close chat">×</button><div id="mcdb" style="height:100%"></div></div>
<script src="/js/admin-chat-widget.js?v={{ @filemtime(public_path('js/admin-chat-widget.js')) ?: 1 }}"></script>
@verbatim
<style>.msla{display:inline-flex;align-items:center;height:17px;line-height:17px;border-radius:9px;padding:0 8px;font-size:10px;font-weight:700;font-variant-numeric:tabular-nums;border:1px solid #D1D5DB;background:#F3F4F6;color:#0B1B33;white-space:nowrap}.msla.o{background:#FFF1E8;border-color:#FDBA8C;color:#C2410C}.msla.r{background:#FEE2E2;border-color:#FCA5A5;color:#B91C1C}.mc .pcs{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}.mc a.pc{display:flex;flex-direction:column;width:132px;border:1px solid #F1E3D8;border-radius:10px;background:#fff;overflow:hidden;text-decoration:none;color:#0C161F}.mc a.pc img{width:132px;height:110px;object-fit:contain;background:#fff;display:block}.mc a.pc .pn{font-size:11.5px;font-weight:600;line-height:1.3;padding:6px 8px 0;max-height:46px;overflow:hidden}.mc a.pc .pp{font-size:12px;font-weight:700;color:#C2410C;padding:2px 8px 7px}.mc .sumb{max-height:42px;overflow:hidden;position:relative}.mc .sumb.x{max-height:none}.mc .sumb:not(.x):after{content:'… more';position:absolute;right:8px;bottom:5px;background:#F8FAFC;color:#6B7280;font-size:11px;padding-left:6px}.mc .cb{max-height:30%!important}.mc .ms{min-height:240px}
</style>
<script>
(function(){if(window.MSla)return;
function fmt(ms){var s=Math.max(0,Math.floor(ms/1000)),h=Math.floor(s/3600),m=Math.floor(s%3600/60),x=s%60,p=function(n){return (n<10?'0':'')+n};return h>0?h+'h '+p(m)+'m':p(m)+'m '+p(x)+'s'}
function cls(ms,w,r){var m=ms/60000;return m>=r?'r':(m>=w?'o':'')}
window.MSla={chip:function(t){if(!t||!t.waiting_since||!t.awaiting||t.status==='resolved')return '';var ms=Date.now()-new Date(t.waiting_since).getTime();return '<span class="msla '+cls(ms,t.sla_w||5,t.sla_r||15)+'" data-since="'+t.waiting_since+'" data-w="'+(t.sla_w||5)+'" data-r="'+(t.sla_r||15)+'" title="Customer waiting for a staff reply. Opening the chat does not stop this timer.">Waiting: '+fmt(ms)+'</span>'}};
setInterval(function(){var n=document.querySelectorAll('.msla[data-since]');for(var i=0;i<n.length;i++){var e=n[i],ms=Date.now()-new Date(e.getAttribute('data-since')).getTime(),c=cls(ms,+e.getAttribute('data-w'),+e.getAttribute('data-r'));e.className='msla '+c;e.textContent='Waiting: '+fmt(ms)}},1000);
})();
</script>
@endverbatim
@endif
