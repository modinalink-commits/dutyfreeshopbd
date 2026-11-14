<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><meta name="csrf" content="{{ csrf_token() }}"><title>DutyFree Dialer</title>
@include('typography')
<style>
:root{--n:#0F1F3D;--n2:#1B2F55;--o:#F26B1D;--g:#16A34A;--r:#DC2626;--bd:#E5E7EB;--gr:#F3F4F6;--mu:#6B7280}*{box-sizing:border-box}
body{margin:0;font-family:Inter,'Bornomala',sans-serif;background:#F8FAFC;color:var(--n);font-size:13px}
button{font-family:inherit}button:disabled{opacity:.5;cursor:not-allowed}
.ph{max-width:400px;margin:0 auto;height:100vh;background:#fff;display:flex;flex-direction:column;overflow:hidden}.ph.emb{max-width:none}
.top{background:var(--n);color:#fff;padding:10px 12px;display:flex;align-items:center;gap:8px}.top .tt{flex:1;min-width:0}.top b{display:block;font-size:15px;letter-spacing:.2px}.top small{display:flex;align-items:center;gap:6px;font-size:11px;color:#C5CEE0;margin-top:2px}
.badge{font-size:10.5px;font-weight:800;padding:1px 8px;border-radius:10px;color:#fff;background:var(--r)}.b-ok{background:var(--g)}.b-wait{background:#2563EB}.b-bad{background:var(--r)}
.tb{border:0;background:rgba(255,255,255,.13);color:#fff;width:26px;height:26px;border-radius:6px;font-size:15px;cursor:pointer;line-height:1}.tb:hover{background:rgba(255,255,255,.25)}
.tabs{display:flex;border-bottom:1px solid var(--bd);background:#fff}.tabs button{flex:1;border:0;background:none;padding:9px 2px;font-size:12px;font-weight:700;color:var(--mu);cursor:pointer;border-bottom:2px solid transparent}.tabs button.on{color:var(--o);border-bottom-color:var(--o)}.tabs button .dot{display:none;width:7px;height:7px;border-radius:50%;background:var(--g);margin-left:4px;vertical-align:middle}.tabs button.live .dot{display:inline-block}
.disc{display:none;background:#FEF2F2;border-bottom:1px solid #FECACA;color:#991B1B;padding:7px 12px;font-size:12px;font-weight:700;align-items:center;gap:8px}.disc.on{display:flex}.disc span{flex:1}.disc button{border:0;background:var(--r);color:#fff;border-radius:6px;padding:5px 10px;font-weight:700;cursor:pointer;font-size:12px}
.box{padding:12px;flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:9px}
.num{display:flex;gap:6px}.num input{flex:1;font-size:20px;letter-spacing:1px;text-align:center;padding:8px;border:1.5px solid var(--bd);border-radius:8px;color:var(--n);font-weight:700;min-width:0}.num input:focus{outline:0;border-color:var(--o)}.num button{border:1.5px solid var(--bd);background:#fff;border-radius:8px;width:44px;font-size:16px;cursor:pointer}
.stt{text-align:center;font-size:12px;color:var(--mu);font-weight:700}.stt span{font-variant-numeric:tabular-nums}
.keys{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}.keys button{height:46px;border:1px solid var(--bd);border-radius:8px;background:var(--gr);font-size:18px;font-weight:700;color:var(--n);cursor:pointer}.keys button:active{background:#FFEDE0}.keys small{display:block;font-size:8.5px;color:#9CA3AF;font-weight:600;letter-spacing:1px;line-height:1}
.go{border:0;border-radius:8px;height:48px;font-size:15px;font-weight:800;color:#fff;cursor:pointer;width:100%;display:flex;align-items:center;justify-content:center;gap:8px}.call{background:var(--g)}.call.busyb{background:#64748B}.hang{background:var(--r)}.hang.big{height:54px;font-size:16px}
.msg{font-size:12px;color:#B91C1C;text-align:center;min-height:14px}.mut{font-size:12px;color:var(--mu);text-align:center}.mut a{color:#4F46E5}
.fnd{display:none;background:#F0FDF4;border:1px solid #BBF7D0;color:#14532D;border-radius:8px;padding:6px 9px;font-size:12px}.fnd.on{display:block}.fnd.unk{background:var(--gr);border-color:var(--bd);color:#374151}.fnd a{color:var(--o);font-weight:700;text-decoration:none}
.srch{width:100%;border:1.5px solid var(--bd);border-radius:8px;padding:8px 10px;font:inherit}.srch:focus{outline:0;border-color:var(--o)}
.lst{display:flex;flex-direction:column}.li{display:flex;align-items:center;gap:9px;padding:8px 2px;border-top:1px solid #F1F3F6;cursor:pointer}.li:first-child{border-top:0}.li:hover{background:#FAFAFB}.li .mid{flex:1;min-width:0}.li b{display:block;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.li small{color:var(--mu);font-size:11.5px;display:block}
.av2{width:32px;height:32px;border-radius:50%;background:var(--n);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12.5px;flex:none}
.li .cb{border:0;background:#E8F8EE;color:#166534;padding:0 11px;height:30px;border-radius:6px;cursor:pointer;font-weight:800;font-size:12px;flex:none}
.tg{display:inline-block;font-size:10.5px;font-weight:800;padding:1px 7px;border-radius:8px;background:#EEF2FF;color:#3730A3;margin-right:3px}.tg.vip{background:#FEF3C7;color:#92400E}.tg.new{background:#E0F2FE;color:#075985}.tg.o{background:#DCFCE7;color:#166534}.tg.x{background:#FEE2E2;color:#991B1B}
.act{text-align:center;padding:4px 0 0}.act .av{width:58px;height:58px;border-radius:50%;background:var(--n);color:#fff;font-size:22px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 6px}.act .nm{font-size:17px;font-weight:800;display:block}.act .ph1{color:var(--mu);margin-top:2px;font-variant-numeric:tabular-nums}
.act .as{font-weight:800;margin-top:6px;font-size:13px;color:var(--mu)}.act .as.ok{color:var(--g)}.act .as.bad{color:var(--r)}.act .as.wt{color:#2563EB}.act .at{font-size:26px;font-weight:800;font-variant-numeric:tabular-nums;margin-top:1px}
.rec{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;color:#B91C1C}.rec i{width:8px;height:8px;border-radius:50%;background:var(--r);animation:bl 1.2s infinite}@keyframes bl{50%{opacity:.25}}
.card2{background:var(--gr);border:1px solid var(--bd);border-radius:8px;padding:8px 10px;text-align:left}.card2 .r{display:flex;justify-content:space-between;gap:8px;padding:2px 0}.card2 .r span{color:var(--mu)}.card2 .r b{text-align:right}
.qa{display:grid;grid-template-columns:1fr 1fr;gap:7px}.qa a{text-align:center;border:1px solid var(--bd);border-radius:8px;padding:8px;font-size:12.5px;font-weight:700;color:var(--n);text-decoration:none;background:#fff}.qa a:hover{border-color:var(--o);color:var(--o)}.qa a.off{pointer-events:none;opacity:.4}
.ctl{display:grid;grid-template-columns:repeat(4,1fr);gap:7px}.ctl.c5{grid-template-columns:repeat(5,1fr)}.ctl button{border:1px solid var(--bd);background:#fff;border-radius:8px;padding:8px 2px;font-size:11.5px;font-weight:700;color:var(--n);cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:2px}.ctl button i{font-style:normal;font-size:16px}.ctl button.on{background:var(--n);color:#fff;border-color:var(--n)}
.fr{display:inline-block;font-size:11.5px;font-weight:800;padding:2px 8px;border-radius:8px}
.chkb{order:4;flex:none;white-space:nowrap;border:1px solid #D0D5DD;background:#fff;color:#0B1B33;border-radius:8px;height:32px;padding:0 9px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit}.chkb:hover{background:#F9FAFB}
.ck2{position:relative}.ck2 .ckx{position:absolute;right:10px;top:10px;width:28px;height:28px;border-radius:8px;border:0;background:#FEE2E2;color:#B91C1C;font-size:19px;line-height:1;cursor:pointer;font-weight:700}.ck2 .ckx:hover{background:#FECACA}
.ckr{border:1px solid #E5E7EB;border-radius:9px;padding:8px 10px;margin-top:8px;display:grid;grid-template-columns:1fr auto;gap:4px 8px;align-items:center;font-size:13px}.ckr b{color:#0B1B33}.ckr .ckv{grid-column:1/-1;color:#475467;font-size:12.5px}.ckr .ckv.ok{color:#047857;font-weight:600}.ckr .ckv.bad{color:#B91C1C;font-weight:600}.ckr .ckv.mid{color:#B45309;font-weight:600}.ckr small{grid-column:1/-1;color:#6B7280;font-size:11.5px}.ckr small:empty{display:none}
.ckb{grid-column:1/-1;height:8px;border-radius:5px;background:#EEF0F3;overflow:hidden}.ckb i{display:block;height:100%;width:0;background:#12B76A;transition:width .08s}
.ckt{border:1px solid #D0D5DD;background:#fff;color:#0B1B33;border-radius:8px;height:30px;padding:0 10px;font-size:12.5px;font-weight:600;cursor:pointer;font-family:inherit}.ckt:hover{background:#F9FAFB}.ckr .ckt{grid-column:2;grid-row:1}.ckt.wide{width:100%;margin-top:10px;height:34px}
.fmid small.bt{color:#047857}
.ov{position:fixed;inset:0;background:rgba(15,31,61,.6);display:none;align-items:center;justify-content:center;z-index:9;padding:10px}.ov.on{display:flex}.card{background:#fff;border-radius:10px;padding:14px;width:min(380px,100%);max-height:96vh;overflow-y:auto}
.card h3{margin:0;font-size:16px}.card .sub{color:var(--mu);font-size:12px;margin:2px 0 8px}
.sg{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px}.sg div{background:var(--gr);border:1px solid var(--bd);border-radius:7px;padding:5px 8px}.sg span{display:block;font-size:10.5px;color:var(--mu)}.sg b{font-size:12.5px}
.lb{font-size:11.5px;font-weight:800;color:#374151;margin:8px 0 4px}
.oc{display:grid;grid-template-columns:1fr 1fr;gap:6px}.oc button{border:1.5px solid var(--bd);background:#fff;border-radius:7px;padding:8px 4px;font-weight:700;font-size:12px;cursor:pointer;color:var(--n)}.oc button.on{border-color:var(--o);background:#FFF1E8;color:#B94A0B}.oc button.on.cn{border-color:var(--r);background:#FEF2F2;color:#991B1B}.oc button.on.cf{border-color:var(--g);background:#F0FDF4;color:#166534}
.chip{display:inline-block;margin:0 4px 5px 0;padding:4px 9px;border:1px solid var(--bd);border-radius:14px;font-size:11.5px;font-weight:600;cursor:pointer;background:#fff;color:var(--n)}.chip:hover{border-color:var(--o)}.chip.on{background:var(--n);color:#fff;border-color:var(--n)}
textarea,.ti{width:100%;border:1.5px solid var(--bd);border-radius:8px;padding:8px;font:inherit}textarea:focus,.ti:focus{outline:0;border-color:var(--o)}
.acts{display:flex;gap:7px;margin-top:10px}.pri{border:0;background:var(--o);color:#fff;border-radius:8px;padding:10px 14px;font-weight:800;cursor:pointer;flex:1}.sec{border:1px solid var(--bd);background:#fff;border-radius:8px;padding:10px 12px;font-weight:700;cursor:pointer;color:var(--n)}.pri.gn{background:var(--g)}.pri.rd{background:var(--r)}
.err{color:#B91C1C;font-size:12px;margin-top:6px;min-height:14px}
.kp{display:none;grid-template-columns:repeat(3,1fr);gap:6px}.kp.on{display:grid}.kp button{height:38px;border:1px solid var(--bd);border-radius:7px;background:var(--gr);font-size:16px;font-weight:700;cursor:pointer;color:var(--n)}
.inc .nm{font-size:18px;font-weight:800}.inc .ln{font-size:12.5px;color:#4B5563;margin-top:3px}
.ph{height:100vh}.top{padding:8px 10px}.top b{font-size:14px}.tabs button{padding:7px 2px}.box{padding:10px;gap:7px}.num input{font-size:18px;padding:6px}.keys{gap:6px}.keys button{height:42px}.go{height:42px}
.stsel{max-width:150px;border:0;border-radius:14px;padding:3px 6px;text-overflow:ellipsis;font:700 11px inherit;color:#fff;cursor:pointer;outline:0}.stsel.st-available{background:var(--g)}.stsel.st-busy{background:var(--r)}.stsel option{color:#111;background:#fff}
.dfoot{display:flex;justify-content:space-between;align-items:center;padding:7px 12px;border-top:1px solid var(--bd);font-size:11.5px;color:var(--mu);background:#fff}.dfoot a{color:var(--o);font-weight:700;text-decoration:none}.dfoot i{font-style:normal;font-weight:700}.dfoot i.ok{color:var(--g)}.dfoot i.no{color:var(--r)}
.crow{display:flex;gap:8px}.crow .go.call{flex:1}.go.sm{width:52px;flex:none;font-size:18px;padding:0}.go.hang.sm:disabled,.go.mq:disabled{opacity:.45}.go.mq{background:#fff;color:#374151;border:1.5px solid var(--bd)}.go.mq.on{background:#FEF3C7;border-color:#F59E0B}
.tabs button svg{width:14px;height:14px;vertical-align:-2px;margin-right:4px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

.mn{display:block;flex:1;min-height:0;overflow:auto}
@media(min-width:900px){.top b{font-size:17px}.mn{display:grid;grid-template-columns:56% 44%;overflow:hidden}.mn>.tp{overflow-y:auto}.mn #tp-pad,.mn #tp-con,.mn #tp-rec{grid-column:1;grid-row:1}.mn #tp-act{display:flex!important;grid-column:2;grid-row:1;border-left:1px solid var(--bd);background:#F8FAFC}.keys{gap:6px;max-width:264px;margin:0 auto;width:100%}.keys button{height:42px;font-size:19px;border-radius:10px;line-height:1.05}.keys small{font-size:8.5px;margin-top:1px}.num{max-width:264px;margin:0 auto;width:100%}.num input{font-size:18px;padding:8px}.crow{max-width:264px;margin:0 auto;width:100%}.go{height:42px;font-size:15px;border-radius:10px}.go.sm{width:44px}.tabs button{font-size:14px;padding:10px 2px}}
.cidr{display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--mu)}.actb{background:#DCFCE7;color:#166534;font-weight:800;border-radius:12px;padding:2px 10px}
.wv{display:flex;align-items:center;justify-content:center;gap:3px;height:34px;background:#ECFDF5;border-radius:17px}.wv i{width:3px;height:6px;background:#86EFAC;border-radius:2px}.wv.on i{background:var(--g);animation:wvb 1s ease-in-out infinite}.wv.on i:nth-child(3n){animation-delay:.2s}.wv.on i:nth-child(3n+1){animation-delay:.45s}@keyframes wvb{0%,100%{height:5px}50%{height:22px}}
.cn{margin-top:auto;border:1px solid var(--bd);border-radius:14px;background:#fff;padding:10px}.cnh{font-weight:800;font-size:13px;margin-bottom:6px}.cn textarea{width:100%;border:1.5px solid var(--bd);border-radius:10px;padding:8px;font:inherit;resize:none}.cn textarea:focus{outline:0;border-color:var(--o)}.cnb{display:flex;justify-content:space-between;align-items:center;margin-top:6px;font-size:12px;color:var(--g)}.cnb button{border:0;background:#FFEDD5;color:#C2410C;font-weight:800;border-radius:10px;padding:7px 18px;cursor:pointer}.cnb button:disabled{opacity:.5}
.dfoot{gap:8px;flex-wrap:wrap;justify-content:space-between}.dfoot .ag{flex:1;text-align:center}
.cpy{border:1px solid var(--bd);background:#fff;border-radius:6px;width:24px;height:22px;cursor:pointer;font-size:12px;color:#4B5563;vertical-align:middle}.cpy:hover{border-color:var(--o);color:var(--o)}
.top{background:linear-gradient(135deg,#0F1F3D,#16305C)}.badge{padding:2px 10px}.b-ok{background:#16A34A}.tabs button.on{background:#FFF4EA;border-radius:10px 10px 0 0}.tabs{padding:0 6px}
.keys button{box-shadow:0 1px 0 rgba(15,23,42,.06);transition:background .12s}.keys button:hover{background:#FFF4EA}.go.call{box-shadow:0 3px 0 #166534;border-radius:12px}.go.hang{box-shadow:0 3px 0 #B91C1C}
.cn,.cidr+.wv{box-shadow:0 1px 3px rgba(15,23,42,.05)}

/* ---- final softphone typography (reference) ---- */
body,button,input,textarea,select{font-family:'Bornomala',Inter,'Bornomala',sans-serif}
.ph{max-width:none}
.top{display:block;padding:12px 14px 12px;background:#0F2748}.tr1{display:flex;align-items:flex-start;gap:8px}.tr1 .tt{flex:1;min-width:0}.top b{font-size:22px;font-weight:700;line-height:1.15;color:#fff;letter-spacing:0}.top .sub{display:block;font-size:16px;font-weight:500;color:#fff;margin-top:1px}
.tb{width:34px;height:34px;border-radius:9px;font-size:18px;background:rgba(255,255,255,.14)}
.tr2{display:flex;align-items:center;gap:10px;margin-top:10px}.vs{width:1px;height:22px;background:rgba(255,255,255,.28)}
.stsel{max-width:none;font-size:15px;font-weight:700;padding:7px 12px;border-radius:18px}.stsel.st-available{background:#16A34A}
.badge{font-size:15px;font-weight:700;padding:5px 12px;border-radius:16px}.badge.b-ok{background:rgba(34,197,94,.18);color:#4ADE80}
.tabs{padding:0;background:#fff}.tabs button{font-size:16px;font-weight:600;color:#4B5563;padding:12px 2px;border-radius:0}.tabs button.on{color:#FF6600;font-weight:700;background:#FFF4EA;border-bottom-color:#FF6600}.tabs button svg{width:17px;height:17px;margin-right:6px;vertical-align:-3px}
/* compact active-call panel: everything fits the popup without up/down scrolling */
#ovSum .card{padding:9px 11px}#ovSum h3{margin:0;font-size:15px}#ovSum .sub{font-size:11px;margin:0 0 5px}
#ovSum .sg{grid-template-columns:1fr 1fr 1fr;gap:4px;margin-bottom:4px}#ovSum .sg div{padding:3px 6px;min-width:0}#ovSum .sg span{font-size:9.5px}#ovSum .sg b{font-size:11px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#ovSum .lb{margin:5px 0 3px;font-size:11px}#ovSum .oc{grid-template-columns:repeat(4,1fr);gap:4px}#ovSum .oc button{padding:6px 2px;font-size:11px}
#ovSum .chip{padding:2px 7px;font-size:11px}#ovSum #ocR,#ovSum #ocC{gap:3px}#ovSum textarea{height:44px;padding:5px 7px}#ovSum .acts{margin-top:6px}#ovSum .pri{padding:8px 10px}#ovSum #sEdit{padding:5px;margin-bottom:3px}
#tp-act{padding:8px 10px;gap:5px}#aBox{gap:5px!important}
#tp-act .cidr{font-size:11px}#tp-act .actb{padding:1px 8px}
#tp-act .wv{height:18px;border-radius:9px}#tp-act .wv.on i{animation-name:wvc}@keyframes wvc{0%,100%{height:4px}50%{height:13px}}
#tp-act .act{padding:0}#tp-act .act .av{width:34px;height:34px;font-size:15px;margin:0 auto 2px}#tp-act .act .nm{font-size:15px;line-height:1.2}#tp-act .act .ph1{margin-top:0;font-size:12.5px}#tp-act .act .as{margin-top:2px;font-size:12px}#tp-act .act .at{font-size:22px;margin-top:0;line-height:1.15}
#tp-act .card2{padding:4px 8px;font-size:11.5px}#tp-act .card2 .r{padding:1px 0}
#tp-act .qa{gap:5px}#tp-act .qa a{padding:5px;font-size:12px}
#tp-act .ctl{gap:5px}#tp-act .ctl button{padding:4px 2px;font-size:11px;gap:0}#tp-act .ctl button i{font-size:14px}
#tp-act .go.big{height:38px;font-size:14px}
@media(max-height:640px){#tp-act .wv{display:none}#tp-act .act .av{display:none}#tp-act .qa a{padding:4px}}
#tp-act .msg{min-height:0;font-size:11px}
#tp-act .cn{padding:6px 8px;border-radius:10px}#tp-act .cnh{font-size:12px;margin-bottom:3px}#tp-act .cn textarea{height:38px;padding:5px 7px;border-radius:8px}#tp-act .cnb{margin-top:3px}#tp-act .cnb button{padding:4px 14px}
#tAct{display:none}#tAct.live{display:block}
.stt{font-size:14px;font-weight:600;color:#6B7280}
.num{max-width:none}.num input{font-size:22px;font-weight:500;color:#263952;text-align:left;letter-spacing:0;height:52px;padding:0 12px 0 46px;background:#fff url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='22' height='22' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z'/></svg>") no-repeat 14px center;border-radius:12px}
.num input::placeholder{color:#7B8496;font-weight:500}.num button{width:52px;height:52px;border-radius:12px}
.keys{max-width:none;gap:7px}.keys button{height:50px;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:12px;background:#F3F4F6;color:#0F2748;padding:0}
.keys .kn{font-size:24px;font-weight:700;line-height:1;color:#0F2748}.keys small{font-size:10px;font-weight:500;line-height:1;letter-spacing:.2px;color:#7B8496;margin-top:3px}.keys small.pl{font-size:11px;margin-top:2px;letter-spacing:0}
.crow{max-width:none;gap:10px}.go{height:50px;border-radius:12px}.go.call{font-size:22px;font-weight:700;gap:8px;box-shadow:none}.go.call svg,.go.sm svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.go.call svg{fill:#fff;stroke:#fff;stroke-width:1}
.go.sm{width:54px;height:50px}.go.hang.sm{background:#EF4444;box-shadow:none}.go.hang.sm svg{fill:#fff;stroke:#fff;stroke-width:1;transform:rotate(135deg)}.go.mq{color:#374151}.go.mq .mo{display:none}.go.mq.on .mi{display:none}.go.mq.on .mo{display:block;color:#B45309}
.dfoot{font-size:13px;font-weight:500;color:#374151;padding:10px 14px;gap:10px;justify-content:space-between}.dfoot b{font-weight:700;color:#0F172A}.dfoot a{color:#2563EB;font-weight:700}.dfoot .sep{width:1px;height:20px;background:var(--bd)}.dfoot .ag{flex:none}.dfoot i{font-size:15px}
.dfl{display:inline-flex;align-items:center;background:#fff;border-radius:10px;padding:5px 11px}.dfl img{display:block;height:26px;width:auto}
.stsel{max-width:none;border:0;color:#fff;cursor:pointer;padding:7px 18px;border-radius:18px;font:700 14px inherit;min-width:104px;box-shadow:0 1px 3px rgba(0,0,0,.25)}.stsel.st-available{background:#16A34A}.stsel.st-busy{background:#DC2626}.stsel:active{transform:scale(.97)}
.stsel,.stsel:hover,.stsel:focus,.stsel:active{color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important;background:#16A34A!important;border:0!important;text-shadow:0 1px 1px rgba(0,0,0,.25)}
.stsel.st-busy,.stsel[value="busy"],.stsel.st-busy:hover,.stsel[value="busy"]:hover{background:#DC2626!important}
.stsel.st-available,.stsel[value="available"]{background:#16A34A!important}

/* ---- v3 (14 Nov 2026): light header, segmented tabs, smart number field, customer card, Up next. Layout and colours only: the call engine below is untouched. ---- */
body{background:#fff}
.top{background:#fff!important;border-bottom:1px solid #E5E7EB;padding:10px 12px;display:flex;align-items:center;gap:6px;flex-wrap:nowrap}
.tr1,.tr2{display:contents}.tr1 .tt{flex:0 0 auto;order:1}.dfl{padding:0;background:none;border-radius:0}.dfl img{height:24px}.vs{display:none}
button.stsel.stsel{order:2;background:#ECFDF3!important;color:#067647!important;-webkit-text-fill-color:#067647!important;border:1px solid #ABEFC6!important;text-shadow:none;box-shadow:none;font-size:12px;font-weight:700;padding:6px 10px;min-width:0;border-radius:999px}
button.stsel.stsel.st-busy{background:#FEF3F2!important;color:#B42318!important;-webkit-text-fill-color:#B42318!important;border-color:#FECDCA!important}
.top .badge{order:3;margin-right:auto;font-size:12px;font-weight:600;padding:5px 10px;border-radius:999px;min-width:0;flex:0 1 auto;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}button.stsel.stsel{flex:none;white-space:nowrap}.tb{flex:none}.badge.b-ok{background:#F2F4F7;color:#0B1B33}.badge.b-wait{background:#EEF4FF;color:#1E3A8A}.badge.b-bad{background:#FEF3F2;color:#B42318}
.tb{order:4;width:32px;height:32px;border-radius:8px;background:#F4F5F7;color:#344054;font-size:16px}.tb:hover{background:#E5E7EB}
.tabs{margin:10px 12px 0;padding:3px;background:#F2F4F7;border-radius:10px;border:0;gap:4px}
.tabs button{height:34px;padding:0 4px;border:0;border-radius:8px;font-size:13px;font-weight:600;color:#475467;background:transparent}
.tabs button.on{background:#fff;color:#0B1B33;border:0;border-radius:8px;box-shadow:0 1px 2px rgba(16,24,40,.08);font-weight:700}.tabs button.live{color:#067647}
.tabs button svg{width:15px;height:15px;margin-right:5px;vertical-align:-3px}
.stt{font-size:12px;font-weight:600;color:#475467}
.num input{height:48px;border:1.5px solid #D0D5DD;border-radius:12px;font-size:21px;font-weight:600;color:#0B1B33;letter-spacing:.3px;padding:0 12px 0 42px;background:#fff url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23667085' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='11' cy='11' r='7'/><path d='M20 20l-3.5-3.5'/></svg>") no-repeat 14px center}
.num input::placeholder{color:#667085;font-weight:500;font-size:16px;letter-spacing:0}.num input:focus{border-color:#F26B1D;box-shadow:0 0 0 3px #FFEAD9}
.num button{width:48px;height:48px;border:1px solid #E5E7EB;background:#F4F5F7;border-radius:12px;color:#344054}
.fnd.on{display:flex;align-items:center;gap:10px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:12px;padding:8px 10px;color:#0B1B33;font-size:12px}
.fnd .fav{width:36px;height:36px;border-radius:50%;background:#0B1B33;color:#fff;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;flex:none}
.fnd .fmid{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.fnd .fmid b{font-size:13.5px;font-weight:700}.fnd .fmid small{color:#475467;font-size:12px}.fnd .tg{font-style:normal;margin:0 0 0 4px}.fnd a{color:#C2410C;font-weight:600;flex:none}
.fnd.sgl{display:flex;flex-direction:column;align-items:stretch;gap:0;padding:4px}.sg1{display:flex;flex-direction:column;gap:1px;text-align:left;border:0;background:none;border-radius:8px;padding:7px 8px;cursor:pointer;color:#0B1B33;min-height:44px;justify-content:center}.sg1:hover{background:#FFF7ED}.sg1 b{font-size:13px}.sg1 span{font-size:12px;color:#475467}.fno{padding:8px;color:#475467}
.keys button{height:46px;background:#fff;border:1px solid #E5E7EB;border-radius:12px;box-shadow:none}.keys button:hover{background:#FFF7ED;border-color:#FED7AA}.keys .kn{font-size:20px;font-weight:600;color:#0B1B33}.keys small{font-size:9px;color:#667085;margin-top:2px}
.go{height:50px;border-radius:12px}.go.call{background:#15803D;font-size:16px;font-weight:700}.go.call.busyb{background:#64748B}.go.call svg{width:18px;height:18px}
.go.sm{width:52px;height:50px}.go.mq{border:1px solid #E5E7EB}.go.hang.sm{background:#B42318}
a.go.chat{background:#fff;color:#344054;border:1px solid #E5E7EB;text-decoration:none}a.go.chat svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}a.go.chat:hover{border-color:#FDC2A5;color:#C2410C}a.go.chat.off{opacity:.4;pointer-events:none}
.upn{display:flex;flex-direction:column;gap:6px}.upn:empty{display:none}
.uh{display:flex;align-items:center;justify-content:space-between;font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:#475467;margin-top:2px}.uh button{border:0;background:none;color:#C2410C;font:600 12px inherit;cursor:pointer;text-transform:none;letter-spacing:0;padding:4px 0}
.ur{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:8px 8px 8px 10px;text-align:left}.ur.n{border-color:#FED7AA;background:#FFF7ED}
.ur .um{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.ur .um b{font-size:13px;color:#0B1B33;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ur .um small{font-size:12px;color:#475467;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ur .ut{font-style:normal;font-size:11px;font-weight:700;border-radius:6px;padding:1px 6px;margin-right:6px;background:#F2F4F7;color:#344054;border:1px solid #E5E7EB}.ur.n .ut{background:#fff;border-color:#FED7AA;color:#9A3412}.ur.c .ut{background:#EEF4FF;border-color:#C7D7FE;color:#1E3A8A}.ur.m .ut{background:#FEF3F2;border-color:#FECDCA;color:#B42318}.ur.a .ut{background:#FFFAEB;border-color:#FEDF89;color:#B54708}
.ur .ub{flex:none;height:44px;padding:0 14px;border:1px solid #ABEFC6;background:#ECFDF3;color:#067647;border-radius:10px;font:700 13px inherit;cursor:pointer}.ur.n .ub{background:#15803D;border-color:#15803D;color:#fff}
#aNone{padding:18px 0}#upn2{margin-top:12px}
/* v3 step 2: incoming screen, caller alerts, order checklist, callback time, no-answer SMS */
.awn{display:none;gap:8px;align-items:flex-start;border:1px solid #FEDF89;background:#FFFAEB;border-radius:10px;padding:7px 10px;color:#7A2E0E;font-size:12px;text-align:left;line-height:1.4}.awn.on{display:flex}.awn svg{flex:none;margin-top:1px}
.inc{text-align:center}.inc .ict{display:inline-flex;align-items:center;gap:6px;background:#EEF4FF;border:1px solid #C7D7FE;color:#1E3A8A;font-weight:700;font-size:12px;border-radius:999px;padding:5px 10px}.inc .ict i{width:8px;height:8px;border-radius:50%;background:#1D4ED8;animation:bl 1.2s infinite}
.inc .icav{width:64px;height:64px;border-radius:50%;background:#0B1B33;color:#fff;font-weight:700;font-size:22px;display:flex;align-items:center;justify-content:center;margin:12px auto 6px}.inc .nm{font-size:20px}.inc .ln{font-size:13px}.inc #icW{margin-top:8px}.inc .acts .pri{height:52px;border-radius:12px;font-size:15px}.inc .acts .pri.gn{flex:1.4}
.chk{display:none;flex-direction:column;border:1px solid #E5E7EB;border-radius:10px;background:#fff;text-align:left;font-size:12px}.chk.on{display:flex}.chk .ch{display:flex;justify-content:space-between;gap:8px;padding:7px 10px;font-weight:700;font-size:12.5px}.chk .ch a{color:#C2410C;font-weight:600;text-decoration:none}
.chk label{display:flex;gap:8px;align-items:flex-start;padding:6px 10px;border-top:1px solid #F2F4F7;cursor:pointer;margin:0}.chk label input{width:16px;height:16px;margin:1px 0 0;accent-color:#15803D;flex:none}.chk label b{display:block}.chk label span{color:#475467;overflow-wrap:anywhere}.chk .cf{padding:6px 10px;border-top:1px solid #F2F4F7;color:#475467;display:flex;justify-content:space-between;gap:8px}
.cbx{display:none;border:1px solid #C7D7FE;background:#F5F8FF;border-radius:8px;padding:7px 9px;margin-top:6px}.cbx.on{display:block}.cbx b{font-size:11.5px}.cbx .chip.on{background:#1D4ED8;border-color:#1D4ED8;color:#fff}.smx{display:none;gap:8px;align-items:center;border:1px solid #E5E7EB;border-radius:8px;padding:6px 9px;margin-top:6px;font-size:12px;cursor:pointer}.smx.on{display:flex}.smx input{width:16px;height:16px;accent-color:#15803D}
.rmd{position:fixed;left:10px;right:10px;bottom:46px;z-index:8;background:#0B1B33;color:#fff;border-radius:12px;padding:10px 12px;display:none;gap:10px;align-items:center;font-size:12.5px;box-shadow:0 8px 24px rgba(11,27,51,.25)}.rmd.on{display:flex}.rmd span{flex:1;min-width:0}.rmd button{border:0;border-radius:8px;height:34px;padding:0 12px;font:700 12.5px inherit;cursor:pointer}.rmd .g{background:#15803D;color:#fff}.rmd .x{background:rgba(255,255,255,.14);color:#fff}
@media(max-width:480px){.top{padding:9px 10px;gap:5px}.dfl img{height:20px}.tb{width:28px;height:28px;font-size:14px}button.stsel.stsel{padding:5px 8px;font-size:11.5px}.top .badge{padding:5px 8px;font-size:11.5px}}
@media(min-width:900px){#upn{display:none}.mn #tp-act{background:#F9FAFB}}
.dfoot{font-size:12px;color:#475467;padding:8px 12px}.dfoot a{color:#C2410C}
</style></head><body>
<div class="ph{{ $embed ? ' emb' : '' }}">
<div class="top"><div class="tr1"><div class="tt"><span class="dfl"><img src="/dutyfree-bd-logo.png" alt="DutyFree BD"></span></div>@if($embed)<button type="button" class="tb" id="pPop" title="Pop out (keeps the call alive while you browse)">⧉</button><button type="button" class="tb" id="pMin" title="Minimize">–</button><button type="button" class="tb" id="pX" title="Close">×</button>@endif</div><div class="tr2"><button type="button" id="stSel" class="stsel st-{{ ($agst ?? 'available') === 'busy' ? 'busy' : 'available' }}" value="{{ ($agst ?? 'available') === 'busy' ? 'busy' : 'available' }}" title="Click to switch Online / Offline">{{ ($agst ?? 'available') === 'busy' ? '● Offline' : '● Online' }}</button><span class="vs"></span><span class="badge b-bad" id="bdg">Disconnected</span><button type="button" id="bChk" class="chkb" title="Test your headset and network before calling">Check</button></div></div>
<div class="disc" id="disc"><span>PBX connection required</span><button type="button" id="bRc">Reconnect</button></div>
<div class="tabs"><button type="button" data-t="pad" class="on"><svg viewBox="0 0 24 24"><circle cx="5" cy="5" r="1.5"/><circle cx="12" cy="5" r="1.5"/><circle cx="19" cy="5" r="1.5"/><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>Dial</button><button type="button" data-t="con"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>Contacts</button><button type="button" data-t="rec"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Recent</button><button type="button" data-t="act" id="tAct">Active Call<span class="dot"></span></button></div>

<div class="mn"><div class="box tp" id="tp-pad">
<div class="stt"><span id="cst">Ready</span> · <span id="tmr">00:00:00</span></div>
<div id="ctxL" style="display:none;margin:0 0 6px;padding:6px 10px;border-radius:8px;background:#EFF6FF;color:#1E3A8A;font-size:12.5px;font-weight:600"></div><div class="num"><input id="num" inputmode="tel" autocomplete="off" placeholder="Name or number" value="{{ $n }}"><button type="button" id="bk" title="Backspace">⌫</button></div>
<div id="found" class="fnd"></div>
<div class="keys">@foreach([['1',''],['2','ABC'],['3','DEF'],['4','GHI'],['5','JKL'],['6','MNO'],['7','PQRS'],['8','TUV'],['9','WXYZ'],['*',''],['0','+'],['#','']] as $k)<button type="button" data-k="{{ $k[0] }}"><span class="kn">{{ $k[0] }}</span><small{!! $k[1]==='+' ? ' class="pl"' : '' !!}>{{ $k[1] ?: ' ' }}</small></button>@endforeach</div>
<div class="crow"><a class="go chat sm off" id="bChat" href="#" target="_blank" rel="noopener" title="Open this customer's chat in Messages" aria-label="Open chat"><svg viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg></a><button type="button" class="go call" id="bGo"><svg class="ph1i" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg><span>Call</span></button><button type="button" class="go mq sm" id="bMuteQ" title="Mute" disabled><svg class="mi" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg><svg class="mo" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M4 4l16 16"/></svg></button><button type="button" class="go hang sm" id="bHang1" title="Hang Up" disabled><svg viewBox="0 0 24 24" class="hg"><path d="M3 14c5-5 13-5 18 0l-2 3-3-1v-2c-3-1-7-1-10 0v2l-3 1z"/></svg></button></div>
<div class="msg" id="msg">@if(!$has)No extension is set for you. Ask an admin to add one in Call Center → Extensions.@endif</div>
<div class="upn" id="upn"></div>
</div>

<div class="box tp" id="tp-con" style="display:none"><input id="cq" placeholder="Search name, phone or customer ID" class="srch"><div id="cl" class="lst"></div></div>
<div class="box tp" id="tp-rec" style="display:none"><div id="rl" class="lst"></div></div>

<div class="box tp" id="tp-act" style="display:none">
<div id="aNone" class="mut">No active call. Dial a number or pick a contact to start.<div class="upn" id="upn2"></div></div>
<div id="aBox" style="display:none;flex-direction:column;gap:9px"><div class="cidr"><span class="actb">● Active Call</span><span id="cid"></span></div><div class="wv" id="wv"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
 <div class="act"><div class="av" id="aAv">?</div><span class="nm" id="aN">Unknown Customer</span><div class="ph1"><span id="aP"></span> <button type="button" class="cpy" id="aCp" title="Copy number">⧉</button></div><div class="as" id="aS">—</div><div class="at" id="aT">00:00:00</div><div id="aQ" style="font-size:11.5px;font-weight:700;color:#6B7280;margin-top:2px"></div><div id="aRec" style="margin-top:2px"></div></div>
 <div class="awn" id="aWarn"></div>
 <div class="card2" id="aCard"></div>
 <div class="chk" id="aChk"></div>
 <div class="qa"><a id="aCu" href="#" target="_blank">Open Customer</a><a id="aOr" href="#" target="_blank">Open Order</a></div>
 <div class="kp" id="kp">@foreach(['1','2','3','4','5','6','7','8','9','*','0','#'] as $k)<button type="button" data-d="{{ $k }}">{{ $k }}</button>@endforeach</div>
 <div class="ctl" id="ctl"><button type="button" id="bMute" disabled><i>🎙</i><span>Mute</span></button><button type="button" id="bHold" disabled><i>⏸</i><span>Hold</span></button><button type="button" id="bKp" disabled><i>⌨</i><span>Keypad</span></button><button type="button" id="bTr" disabled><i>↗</i><span>Transfer</span></button><button type="button" id="bSpk" style="display:none"><i>🔊</i><span>Speaker</span></button></div>
 <button type="button" class="go hang big" id="bHang2" disabled>⛔ End Call</button>
 <div class="msg" id="msg2"></div>
</div>
<div class="cn"><div class="cnh">Call Notes</div><textarea id="cNote" rows="2" maxlength="1000" placeholder="Write a note..."></textarea><div class="cnb"><span id="cNm"></span><button type="button" id="cSave">Save</button></div></div>
</div>
</div>
<div class="dfoot"><span class="fl"><i id="dfc" class="no">●</i> <span id="dfw">WSS</span> · Ext: <b id="dfx">—</b></span><span class="sep"></span><span>Agent: <b>{{ explode(' ', trim((string)($agname ?? '')))[0] }}</b></span><span class="sep"></span><a href="/admin/call-center/history" target="_blank">☰ History</a></div>
</div>

<div class="ov" id="ovInc"><div class="card inc"><span class="ict"><i></i>Incoming call</span><div class="icav" id="icAv">?</div><div class="nm" id="icN">Unknown Customer</div><div class="ln" id="icP"></div><div class="ln" id="icI"></div><div class="awn" id="icW"></div><div class="acts"><button type="button" class="pri gn" id="icA">Answer</button><button type="button" class="pri rd" id="icR">Reject</button></div></div></div>

<div class="ov" id="ovTr"><div class="card"><h3>Transfer to Extension</h3><div class="sub">Connect this call to another extension.</div><input class="ti" id="trExt" inputmode="numeric" placeholder="Extension"><div class="err" id="trErr"></div><div class="acts"><button type="button" class="pri" id="trGo">Transfer Call</button><button type="button" class="sec" id="trX">Cancel</button></div></div></div>

<div class="ov" id="ovChk"><div class="card ck2"><button type="button" class="ckx" id="ckX" title="Close" aria-label="Close">×</button><h3>Headset and network check</h3><div class="sub">Nothing here touches a live call. The test is switched off while you are on a call.</div>
<div class="ckr"><b>Microphone</b><span id="ckM" class="ckv">Not tested</span><div class="ckb"><i id="ckL"></i></div><small id="ckD"></small></div>
<div class="ckr"><b>Speaker</b><span id="ckS" class="ckv">Press to hear a short tone</span><button type="button" id="ckT" class="ckt">Play test sound</button></div>
<div class="ckr"><b>Network</b><span id="ckN" class="ckv">Not tested</span><small id="ckC"></small></div>
<div class="ckr"><b>Phone line (PBX)</b><span id="ckP" class="ckv"></span></div>
<button type="button" id="ckR" class="ckt wide">Run the check again</button></div></div>
<div class="ov" id="ovSum"><div class="card"><h3>Call Summary</h3><div class="sub">Save the result of this conversation with the customer</div>
<div class="sg"><div><span>Customer Name</span><b id="sN">—</b></div><div><span>Phone</span><b id="sP">—</b></div><div><span>Call Duration</span><b id="sD">—</b></div><div><span>Order Number</span><b id="sO">—</b></div><div><span>Agent Name</span><b id="sA">—</b></div><div><span>Recording Status</span><b id="sR">—</b></div></div>
<a id="sEdit" href="#" target="_blank" style="display:none;margin-bottom:6px;text-align:center;border:1.5px solid var(--o);color:var(--o);border-radius:8px;padding:8px;font-weight:800;text-decoration:none">✏️ Edit Current Order</a>
<div class="lb">Order Outcome</div>
<div class="oc" id="ocO"><button type="button" data-v="confirmed" class="cf">Order Confirmed</button><button type="button" data-v="pending">Pending</button><button type="button" data-v="call_later">Call Later</button><button type="button" data-v="no_answer">No Answer</button><button type="button" data-v="cancelled" class="cn" style="grid-column:1/-1">Order Cancelled</button></div>
<div id="cwrap" style="display:none"><div class="lb">Cancel Reason</div><div id="ocC">@foreach(['Price Issue','Delivery Charge Issue','Changed Mind','Duplicate Order','Ordered by Mistake','Product Unavailable','Delay','Not Needed','Suspicious','Other'] as $c)<span class="chip" data-v="{{ $c }}">{{ $c }}</span>@endforeach</div></div>
<div class="cbx" id="cbx"><b>Call back</b><div id="cbC" style="margin-top:5px"><span class="chip" data-m="30">In 30 min</span><span class="chip" data-m="60">In 1 hour</span><span class="chip" data-m="180">In 3 hours</span><span class="chip" data-m="tom">Tomorrow 11 AM</span></div></div>
<label class="smx" id="smx"><input type="checkbox" id="sMsg"> Send a "we tried to call you" SMS to the customer</label>
<div class="lb">Customer Response</div>
<div id="ocR">@foreach(['Customer Received Call','Busy','No Answer','Phone Off','Unreachable','Wrong Number','Call Later','WhatsApp Required','Need More Information'] as $c)<span class="chip" data-v="{{ $c }}">{{ $c }}</span>@endforeach</div>
<div class="lb">Call Note</div><textarea id="sNote" rows="2" maxlength="1000" placeholder="Briefly, what was said with the customer..."></textarea>
<div class="err" id="sErr"></div>
<div class="acts"><button type="button" class="pri" id="sSave">Save &amp; Close</button><button type="button" class="pri" id="sNext" style="background:var(--n)">Save &amp; Next</button><button type="button" class="sec" id="sSkip">Skip</button></div></div></div>

<div class="ov" id="ovDet"><div class="card"><h3>Call Details</h3><div class="sub" id="dSub"></div><div class="card2" id="dBody"></div><div id="dRec" style="margin-top:8px"></div><div class="acts"><button type="button" class="pri gn" id="dCall">Call Again</button><button type="button" class="sec" id="dX">Close</button></div></div></div>
<div class="rmd" id="rmd"><span id="rmdT"></span><button type="button" class="g" id="rmdG">Dial</button><button type="button" class="x" id="rmdX">Later</button></div>
<audio id="aud" autoplay></audio>

@verbatim
<script src="/js/jssip.min.js"></script>
<script>
(function(){
var $=function(i){return document.getElementById(i)},T=document.querySelector('meta[name=csrf]').content,emb=!!document.querySelector('.ph.emb');
var tries=0,retrying=false,cancelReq=false,ua=null,cfg=null,ses=null,logId=null,connAt=0,tick=null,dir='out',phase='idle',muted=false,held=false,cur='',info=null,ring=null,inCtx=null,sum=null,lastBusy=null,okCount=0;
var ch=window.BroadcastChannel?new BroadcastChannel('dfphone'):null,pend=null;
/* ---------- availability (Available / Busy) ---------- */
var agBusy=($('stSel')||{}).value==='busy';
var tmpReg=false,pendCall=null,lastLog=null;
function busyUnreg(){if(ua&&agBusy&&!inCall()&&!tmpReg&&!pend){try{ua.unregister({all:true})}catch(e){}}}
function stApply(v,tell){if(v!=='busy'&&v!=='available')return;var was=agBusy;agBusy=v==='busy';var e=$('stSel');if(e){e.value=v;e.className='stsel st-'+v;e.textContent=v==='busy'?'\u25CF Offline':'\u25CF Online'}if(emb&&tell!==false)parent.postMessage({dfdial:'status',v:v},location.origin);if(tell!==false&&ch)try{ch.postMessage({type:'status',v:v})}catch(x){}
 if(ua&&agBusy!==was){if(agBusy)busyUnreg();else{try{ua.register()}catch(x){}}}if(agBusy)badge('Busy · not receiving calls','b-bad');else if(was&&ua&&reg())badge('Connected','b-ok')}
setInterval(function(){getj('/admin/call-center/my-status').then(function(j){if(j&&j.status&&(j.status==='busy')!==agBusy)stApply(j.status,false)}).catch(function(){})},20000);
if($('stSel')){$('stSel').onclick=function(){var v=agBusy?'available':'busy';stApply(v);post('/admin/call-center/my-status',{status:v}).then(function(j){if(!j||!j.ok)stApply(agBusy?'available':'busy')}).catch(function(){})};stApply(agBusy?'busy':'available')}
(function(){var b=$('bdg'),f=$('dfc');if(!b||!f||!window.MutationObserver)return;var u=function(){var t=b.textContent.trim();f.textContent='●';f.title=t;f.className=/connected|registered|ready/i.test(t)&&!/dis/i.test(t)?'ok':'no'};new MutationObserver(u).observe(b,{childList:true,characterData:true,subtree:true});u()})();

function ctxShow(o,nm){var e=$('ctxL');if(!e)return;var t=[o,nm].filter(Boolean).join(' · ');e.textContent=t?'📄 '+t:'';e.style.display=t?'block':'none'}
function autoDial(n,d){n=clean(n);ctxShow(d&&d.o,d&&d.cn);if(!n)return;if(inCall()){tab('act');err('An active call is currently in progress. End or hold the current call first.');return}$('num').value=n;lastN='';found();tab('pad');try{$('num').focus()}catch(e){}} /* Call button elsewhere only opens the dialer with the number filled in; the staff member decides and presses Call */
function esc(t){return String(t==null?'':t).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function post(u,o){var f=new FormData();f.append('_token',T);for(var k in o||{})if(o[k]!=null)f.append(k,o[k]);return fetch(u,{method:'POST',body:f,headers:{Accept:'application/json'}}).then(function(r){return r.json().then(function(j){j._s=r.status;return j})})}
function getj(u){return fetch(u,{headers:{Accept:'application/json'}}).then(function(r){return r.json()})}
function clean(n){return String(n||'').replace(/[^0-9+*#]/g,'')}
function fmt(s){var h=Math.floor(s/3600),m=Math.floor(s%3600/60),x=s%60;return [h,m,x].map(function(v){return (v<10?'0':'')+v}).join(':')}
function fmtD(s){return s>=60?Math.floor(s/60)+'m '+(s%60)+'s':s+'s'}
function inCall(){return !!ses&&(phase==='connecting'||phase==='ringing'||phase==='connected')}
function connected(){return inCall()&&phase==='connected'}
/* ---------- connection badge ---------- */
function badge(t,c){var b=$('bdg');b.textContent=t;b.className='badge '+(c||'b-bad');if(ch)ch.postMessage({type:'status',text:t,cls:c||'b-bad'});ui()}
function reg(){return !!(ua&&ua.isRegistered())}
function err(t){$('msg').textContent=t||'';$('msg2').textContent=t||''}
/* ---------- state / UI ---------- */
var LBL={idle:'Ready',connecting:'Connecting...',ringing:'Ringing...',connected:'Connected',ended:'Call Ended',failed:'Failed'};
function setPhase(p,label){phase=p;var t=label||LBL[p];$('cst').textContent=t;var a=$('aS');a.textContent=t.replace('...','');a.className='as '+(p==='connected'?'ok':(p==='failed'?'bad':(p==='connecting'||p==='ringing'?'wt':'')));ui()}
function ui(){var r=reg(),ic=inCall(),cn=connected();
 $('disc').classList.toggle('on',!r&&!ic&&!agBusy);
 var g=$('bGo');if(ic){g.innerHTML='<span>'+(phase==='connected'?'✔ Connected':LBL[phase])+'</span>';g.disabled=true;g.className='go call busyb'}else{g.innerHTML='<svg class="ph1i" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg><span>Call</span>';g.disabled=!r&&!(agBusy&&!!ua);g.className='go call'}
 var pa=(phase==='connecting'||phase==='ringing'||phase==='connected'); /* cancel must work from the very first second, even before the call object exists */
 $('bHang1').disabled=!pa;$('bMuteQ').disabled=!cn;$('bHang2').disabled=!pa;
 ['bMute','bHold','bKp','bTr'].forEach(function(i){$(i).disabled=!cn});
 $('tAct').classList.toggle('live',ic);try{$('cid').textContent=ic&&logId?'Call ID: #'+logId:'';$('wv').className='wv'+(phase==='connected'?' on':'')}catch(e){}
 var b=ic?'busy':'idle';if(b!==lastBusy){lastBusy=b;if(emb)parent.postMessage({dfdial:b},location.origin)}}
function startTimer(){connAt=Date.now();clearInterval(tick);tick=setInterval(function(){var s=fmt(Math.floor((Date.now()-connAt)/1000));$('tmr').textContent=s;$('aT').textContent=s},500)}
function secs(){return connAt?Math.floor((Date.now()-connAt)/1000):0}
var lastLeft='pad';
function tab(t){if(window.innerWidth>=900){if(t==='act')t=lastLeft;else lastLeft=t}document.querySelectorAll('.tabs button').forEach(function(b){b.classList.toggle('on',b.dataset.t===t)});document.querySelectorAll('.tp').forEach(function(p){p.style.display=p.id==='tp-'+t?'':'none'});var tpe=document.getElementById('tp-'+t);if(tpe)tpe.scrollTop=0;if(t==='rec')recent();if(t==='con')contacts()}
document.querySelectorAll('.tabs button').forEach(function(b){b.onclick=function(){tab(b.dataset.t)}});
/* ---------- customer info ---------- */
function tagCls(c){c=String(c||'').toLowerCase();return c==='vip'?'vip':(c==='new'?'new':'')}
function fr(f){return f?'<span class="fr" style="color:'+esc(f.color)+';background:'+esc(f.bg)+'">Courier Reliability: '+esc(f.label)+'</span>':''}
function renderAct(){var c=info||{},ph=cur;
 $('aNone').style.display='none';$('aBox').style.display='flex';
 var nm=c.found&&c.name?c.name:'Unknown Customer';$('aN').textContent=nm;$('aAv').textContent=c.found&&c.name?c.name.trim().charAt(0).toUpperCase():'?';$('aP').textContent=ph;
 var h='';if(c.found){h+='<div class="r"><span>Category</span><b><span class="tg '+tagCls(c.category)+'">'+esc(c.category||'Regular')+'</span> '+c.orders+' Orders</b></div>';
  if(c.customer_id)h+='<div class="r"><span>Customer ID</span><b>#'+c.customer_id+'</b></div>';
  h+='<div class="r"><span>Current / Last Order</span><b>'+(c.last?esc(c.last.invoice)+' · '+esc(c.last.status):'—')+'</b></div>';
  if(c.fraud)h+='<div class="r"><span>Reliability</span><b>'+fr(c.fraud)+'</b></div>'}
 else h='<div class="r"><span>Customer</span><b>Unknown Customer</b></div><div class="r"><span>Total Orders</span><b>0</b></div>';
 $('aCard').innerHTML=h;warn('aWarn',c.alerts);
 var ck=$('aChk');if(c.last&&c.last.status==='New'&&dir==='out'){if(ck.dataset.o!==String(c.last.id)){ck.dataset.o=String(c.last.id);ck.className='chk';getj('/admin/call-center/order/'+c.last.id).then(function(o){if(!o||!o.id||ck.dataset.o!==String(o.id))return;
   var it=(o.items||[]).map(function(i){return esc(i.name)+' × '+i.qty}).join('<br>')||'—',tk=function(n){return '৳'+Math.round(n).toLocaleString()};
   ck.innerHTML='<div class="ch"><span>Confirm order #'+esc(o.invoice)+'</span><a href="/admin/orders/'+o.id+'" target="_blank">Open order</a></div>'+
    '<label><input type="checkbox"><span><b>Items</b>'+it+'</span></label><label><input type="checkbox"><span><b>Delivery address</b>'+esc([o.address,o.district].filter(Boolean).join(', ')||'No address on the order')+'</span></label>'+
    '<label><input type="checkbox"><span><b>Total told to the customer: '+tk(o.total)+'</b>Products '+tk(o.subtotal)+' + delivery '+tk(o.shipping)+(o.discount>0?' − discount '+tk(o.discount):'')+'</span></label>'+
    '<label><input type="checkbox"><span><b>Payment</b>'+esc(o.pay||'Cash on delivery')+(o.pay_status?' · '+esc(o.pay_status):'')+'</span></label><div class="cf"><span id="ckN">0 of 4 checked</span><span>Approve the order from the order page</span></div>';
   ck.className='chk on';ck.onchange=function(){$('ckN').textContent=ck.querySelectorAll('input:checked').length+' of 4 checked'}}).catch(function(){})}}else{ck.className='chk';ck.dataset.o='';ck.innerHTML=''}
 var cu=$('aCu'),or=$('aOr');
 if(c.customer_id){cu.textContent='Open Customer';cu.href='/admin/m/customers/'+c.customer_id+'/edit';cu.className=''}else{cu.textContent='Create Customer';cu.href='/admin/m/customers/new?phone='+encodeURIComponent(ph);cu.className=''}
 if(c.last){or.href='/admin/orders/'+c.last.id;or.className=''}else{or.href='#';or.className='off'}
 recInd()}
function warn(id,a){var e=$(id);if(!e)return;a=a||[];if(!a.length){e.className='awn';e.innerHTML='';return}e.className='awn on';e.innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#B54708" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l9 16H3l9-16zM12 10v4M12 17v.5"/></svg><span>'+a.map(esc).join('<br>')+'</span>'}
function recInd(){var e=$('aRec');if(inCall()&&connected()&&cfg&&cfg.rec)e.innerHTML='<span class="rec"><i></i>Recording</span>';else e.innerHTML=''}
function look(n){return getj('/admin/call-center/lookup?phone='+encodeURIComponent(n)).then(function(c){return c}).catch(function(){return {found:false}})}
/* ---------- connect ---------- */
function connect(){if(ua)return;badge('Connecting','b-wait');
 getj('/admin/call-center/creds').then(function(c){if(!c.ok){badge('Disconnected','b-bad');err(c.error);return}cfg=c;try{$('dfx').textContent=c.ext||'—'}catch(e){}
  try{var sock=new JsSIP.WebSocketInterface(c.wss);ua=new JsSIP.UA({sockets:[sock],uri:'sip:'+c.user+'@'+c.domain,password:c.pw,authorization_user:c.auth,display_name:c.name,register:true,session_timers:false})}catch(e){console.warn('dialer',e);badge('Registration Failed','b-bad');err('Registration Failed. Please contact admin.');ua=null;return}
  try{var _s=sock.send.bind(sock);sock.send=function(m){try{trc('tx',m)}catch(x){}return _s(m)};ua.on('connected',function(){try{var w=sock._ws;if(w&&!w._bdT){w._bdT=1;w.addEventListener('message',function(ev){trc('rx',ev.data)})}}catch(x){}trc('ws','connected')});ua.on('disconnected',function(){trc('ws','DISCONNECTED')})}catch(x){}
  ua.on('registered',function(){badge('Connected','b-ok');err('');if(!inCall())setPhase('idle');if(tmpReg&&pendCall){tmpReg=false;var qc=pendCall;pendCall=null;setTimeout(function(){call(qc)},120);return}if(agBusy&&!pend){setTimeout(busyUnreg,300);badge('Busy · not receiving calls','b-bad')}if(pend){var px=pend;pend=null;setTimeout(function(){dialNum(px)},150)}});
  ua.on('unregistered',function(){badge(agBusy?'Busy · not receiving calls':'Disconnected','b-bad')});
  ua.on('registrationFailed',function(e){console.warn('dialer reg fail',e&&e.cause);badge('Registration Failed','b-bad');err('Registration Failed. Please contact admin.')});
  ua.on('disconnected',function(){if(ua&&!ua.isRegistered()){badge('Disconnected','b-bad');err('PBX connection required')}});
  ua.on('newRTCSession',function(e){if(e.session.direction==='incoming')incoming(e.session)});
  ua.start()}).catch(function(){badge('Disconnected','b-bad');err('PBX connection required')})}
function disconnect(){if(ua){try{ua.stop()}catch(e){}ua=null}badge('Disconnected','b-bad')}
function reconnect(){err('');disconnect();setTimeout(connect,300)}
/* ---------- call flow ---------- */
function target(n){var p=cfg.prefix||'';n=clean(n);if(p&&n.indexOf(p)!==0&&n.charAt(0)!=='+')n=p+n;return 'sip:'+n+'@'+cfg.domain}
var SIPT=[],TS0=Date.now();function trc(d,m){var t='';if(typeof m==='string'){var L=m.split('\r\n');t=L[0].replace(/ SIP\/2\.0$/,'').replace(/^(INVITE|ACK|REGISTER|CANCEL|BYE) sip:[^ ]*@/,'$1 ');var hv=function(n){for(var i=1;i<L.length;i++)if(L[i].toLowerCase().indexOf(n+':')===0)return L[i].slice(n.length+1).trim();return ''};
 if(/^(INVITE|SIP\/2\.0 [45])/.test(L[0])){var fr=hv('from').match(/sip:([^@>;]*)/i),pa=hv('proxy-authorization'),pc=hv('proxy-authenticate'),cs=hv('cseq');t+=' ['+(cs?'cseq '+cs.split(' ')[0]+' ':'')+(fr?'from '+fr[1]+' ':'')+(pa?'auth-user '+((pa.match(/username="([^"]*)"/)||[])[1]||'?')+' ':(L[0].indexOf('INVITE')===0?'NO-AUTH ':''))+(pc?'realm '+((pc.match(/realm="([^"]*)"/)||[])[1]||'?')+(/stale=true/i.test(pc)?' STALE':''):'')+']'}}else t=String(m);SIPT.push(((Date.now()-TS0)/1000).toFixed(1)+'s '+d+' '+t.slice(0,110));if(SIPT.length>14)SIPT.shift()}
function iceCfg(){return {iceServers:cfg.stun?[{urls:'stun:'+cfg.stun.replace(/^stun:/,'')}]:[{urls:'stun:stun.l.google.com:19302'}]}}
/* voice quality: explicit echo cancel / noise suppression / auto gain, mono (ideal = never fails on devices without it) */
var AC={echoCancellation:{ideal:true},noiseSuppression:{ideal:true},autoGainControl:{ideal:true},channelCount:{ideal:1}};
/* SDP tuning: prefer Opus then G.722 (wideband) over G.711, add Opus FEC so lost packets are repaired; the PBX still picks only what it supports */
function tuneSdp(sdp,offer){try{var L=sdp.split('\r\n'),mi=-1,i,op=null;for(i=0;i<L.length;i++)if(L[i].indexOf('m=audio')===0){mi=i;break}if(mi<0)return sdp;
 for(i=0;i<L.length;i++){var m=/^a=rtpmap:(\d+) opus\/48000/i.exec(L[i]);if(m){op=m[1];break}}
 if(op){var has=false,add=['useinbandfec=1','usedtx=0','stereo=0'];for(i=0;i<L.length;i++){if(L[i].indexOf('a=fmtp:'+op+' ')===0){has=true;add.forEach(function(k){if(L[i].indexOf(k.split('=')[0]+'=')<0)L[i]+=';'+k})}}
  if(!has)for(i=0;i<L.length;i++){if(L[i].indexOf('a=rtpmap:'+op+' ')===0){L.splice(i+1,0,'a=fmtp:'+op+' minptime=10;'+add.join(';'));break}}}
 if(offer){var P=L[mi].split(' '),h=P.slice(0,3),pts=P.slice(3),first=[];[op,'9'].forEach(function(x){if(x&&pts.indexOf(x)>-1)first.push(x)});L[mi]=h.concat(first,pts.filter(function(x){return first.indexOf(x)<0})).join(' ')}
 return L.join('\r\n')}catch(e){return sdp}}
/* live voice-quality read-out (packet loss, jitter, delay) + a bigger jitter buffer when the line is unstable */
var qT=null;
function qStop(){clearInterval(qT);qT=null;var q=$('aQ');if(q)q.innerHTML=''}
function qStart(s){qStop();var pc=s&&s.connection;if(!pc||!pc.getStats)return;var lp=0,lr=0;
 qT=setInterval(function(){pc.getStats().then(function(r){var jit=0,lost=0,rec=0,rtt=0;r.forEach(function(x){if(x.type==='inbound-rtp'&&x.kind==='audio'){jit=x.jitter||0;lost=x.packetsLost||0;rec=x.packetsReceived||0}if(x.type==='remote-inbound-rtp'&&x.kind==='audio'&&x.roundTripTime)rtt=x.roundTripTime});
  var dl=Math.max(0,lost-lp),dr=Math.max(0,rec-lr);lp=lost;lr=rec;var pct=(dl+dr)>0?dl*100/(dl+dr):0,lab,col;
  if(pct>8||jit>0.08||rtt>0.6){lab='Poor';col='#DC2626'}else if(pct>3||jit>0.04||rtt>0.35){lab='Fair';col='#D97706'}else{lab='Good';col='#16A34A'}
  var q=$('aQ');if(q)q.innerHTML='<span style="color:'+col+'">● Voice '+lab+'</span> · loss '+pct.toFixed(1)+'% · jitter '+Math.round(jit*1000)+' ms'+(rtt?' · delay '+Math.round(rtt*1000)+' ms':'');
  try{pc.getReceivers().forEach(function(rv){if(rv.track&&rv.track.kind==='audio'&&('jitterBufferTarget' in rv))rv.jitterBufferTarget=(jit>0.03||pct>2)?200:null})}catch(e){}
 }).catch(function(){})},3000)}
function mic(){if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia)return Promise.reject({name:'NoMedia'});return navigator.mediaDevices.getUserMedia({audio:AC}).then(function(s){s.getTracks().forEach(function(t){t.stop()})})}
function micErr(){setPhase('failed','Failed');err('Microphone access is required to make calls.')}
function resetCtl(){setTimeout(busyUnreg,1500);muted=false;held=false;var q=$('bMuteQ');if(q){q.classList.remove('on')}$('bMute').classList.remove('on');$('bHold').classList.remove('on');$('bMute').lastChild.textContent='Mute';$('bHold').lastChild.textContent='Hold';$('bKp').classList.remove('on');$('kp').classList.remove('on')}
function begin(n,d){cancelReq=false;cur=n;dir=d;info=null;connAt=0;clearInterval(tick);$('tmr').textContent='00:00:00';$('aT').textContent='00:00:00';resetCtl();renderAct();look(n).then(function(c){if(cur===n){info=c;renderAct();if(d==='in'){$('icN').textContent=c.found&&c.name?c.name:'Unknown Customer';$('icI').textContent=c.found?((c.category||'Customer')+' · '+(c.delivered||0)+' received of '+c.orders+' orders'+(c.last?' · Last: '+c.last.invoice+' ('+c.last.status+')':'')):'No customer record';$('icAv').textContent=c.found&&c.name?c.name.trim().charAt(0).toUpperCase():'?';warn('icW',c.alerts)}}})}
function call(n){n=clean(n||$('num').value);if(!retrying)tries=0;retrying=false;if(!n){err('Enter a phone number first.');return}if(inCall())return;if(!reg()){if(agBusy&&ua&&!tmpReg){tmpReg=true;pendCall=n;err('');try{ua.register()}catch(e){tmpReg=false;pendCall=null;err('PBX connection required')}setTimeout(function(){if(tmpReg){tmpReg=false;pendCall=null;err('PBX connection required')}},8000);return}err('PBX connection required');return}err('');
 begin(n,'out');setPhase('connecting');tab('act');
 mic().then(function(){return post('/admin/call-center/log',{direction:'out',phone:n,extension:cfg.ext})}).then(function(j){logId=j.id;lastLog=j.id;
  if(cancelReq){cancelReq=false;if(j.id)post('/admin/call-center/log/'+j.id+'/end',{status:'missed',duration:0});return}
  var s=ua.call(target(n),{mediaConstraints:{audio:AC,video:false},pcConfig:iceCfg(),rtcOfferConstraints:{offerToReceiveAudio:true}});wire(s);ui()}).catch(function(e){ses=null;if(e&&(e.name==='NotAllowedError'||e.name==='NotFoundError'||e.name==='NoMedia'||e.name==='SecurityError'))micErr();else{console.warn('dialer call',e);setPhase('failed','PBX Error');err('PBX Error. Please try again.')}})}
function pbxId(){try{return ses._request&&ses._request.call_id||''}catch(e){return ''}}
/* ---------- call recording, made in the browser from a separate copy of the two voices. The call's own audio path is not touched. ---------- */
var rcd=null;
function recStart(s){try{if(rcd||!cfg||!cfg.rec||!window.MediaRecorder||!s||!s.connection)return;var AC2=window.AudioContext||window.webkitAudioContext;if(!AC2)return;
 var pc=s.connection,pick=function(a){return a.map(function(r){return r.track}).filter(function(t){return t&&t.kind==='audio'&&t.readyState==='live'})},rt=pick(pc.getReceivers()),lt=pick(pc.getSenders());if(!rt.length&&!lt.length)return;
 var mt=['audio/webm;codecs=opus','audio/webm','audio/ogg;codecs=opus'].filter(function(m){return MediaRecorder.isTypeSupported(m)})[0];if(!mt)return;
 var ctx=new AC2(),dst=ctx.createMediaStreamDestination();[rt,lt].forEach(function(ts){if(ts.length)ctx.createMediaStreamSource(new MediaStream(ts)).connect(dst)});
 var mr=new MediaRecorder(dst.stream,{mimeType:mt,audioBitsPerSecond:16000}),ch=[];mr.ondataavailable=function(e){if(e.data&&e.data.size)ch.push(e.data)};
 rcd={mr:mr,ctx:ctx,ch:ch,mt:mt};mr.start(5000)}catch(e){console.warn('dialer rec start',e);rcd=null}}
function recStop(id){var r=rcd;rcd=null;if(!r)return;try{r.mr.onstop=function(){try{r.ctx.close()}catch(e){}if(!id||!r.ch.length)return;var b=new Blob(r.ch,{type:r.mt});if(b.size<3000)return;
  var f=new FormData();f.append('_token',T);f.append('file',b,'call-'+id+(r.mt.indexOf('ogg')>-1?'.ogg':'.webm'));
  var send=function(n){fetch('/admin/call-center/log/'+id+'/recording-file',{method:'POST',body:f,headers:{Accept:'application/json'},credentials:'same-origin'}).then(function(x){if(!x.ok&&n>0)setTimeout(function(){send(n-1)},5000)}).catch(function(){if(n>0)setTimeout(function(){send(n-1)},5000)})};send(2)};
 r.mr.stop()}catch(e){console.warn('dialer rec stop',e)}}
function wire(s){ses=s;
 s.on('sdp',function(e){if(e&&e.sdp)e.sdp=tuneSdp(e.sdp,e.originator==='local'&&e.type==='offer')});
 var sent=false;s.on('sending',function(){sent=true});
 /* AUTO-RETRY: the PBX sometimes never answers the authenticated INVITE (stale WSS / lost packet). No 100/18x within 6 s -> silently drop this attempt and redial (1st: re-register, 2nd: fresh WSS connection). The agent just sees "Connecting". */
 if(dir==='out')setTimeout(function(){if(ses!==s||phase!=='connecting'||s._dead||tries>=2||!sent)return;tries++;var n2=cur,id0=logId;console.warn('dialer auto-retry '+tries+' | '+SIPT.join(' | '));s._dead=1;try{s.terminate()}catch(e){}if(id0)post('/admin/call-center/log/'+id0+'/end',{status:'missed',duration:0,fail_reason:'auto-retry'});ses=null;logId=null;clearInterval(tick);
  var again=function(){retrying=true;phase='idle';call(n2)};
  if(tries===1){try{ua.register()}catch(e){}setTimeout(again,900)}
  else{try{ua.stop()}catch(e){}setTimeout(function(){try{ua.start()}catch(e){}var k=0,iv=setInterval(function(){k++;if(reg()||k>16){clearInterval(iv);again()}},500)},400)}},6000);
 /* watchdog: an outgoing call that is still "Connecting" after 12 s says WHERE it is stuck (never sent = browser/ICE side, sent = no answer from the PBX) */
 if(dir==='out'){var wd0=Date.now(),wdI=0;var wdF=function(){if(ses!==s||phase!=='connecting'){clearInterval(wdI);return;}var pc=s.connection,st='';try{st=(pc?'ice-gather '+pc.iceGatheringState+', ice '+pc.iceConnectionState+', sig '+pc.signalingState:'no peer connection')+', INVITE '+(sent?'sent':'NOT sent')+', registered '+(ua&&ua.isRegistered())+', socket '+(ua&&ua.isConnected())+' | '+SIPT.join(' | ')}catch(x){}console.warn('dialer stuck:',st);err('No reply yet '+Math.round((Date.now()-wd0)/1000)+'s ('+st+')')};setTimeout(function(){wdF();wdI=setInterval(wdF,3000)},12000)}
 s.on('progress',function(){if(phase==='connecting')setPhase('ringing')});
 var up=function(){if(phase==='connected')return;setPhase('connected');startTimer();qStart(s);setTimeout(function(){if(ses===s)recStart(s)},500);if(logId)post('/admin/call-center/log/'+logId+'/connected',{pbx_call_id:pbxId()});recInd();err('')};
 s.on('accepted',up);s.on('confirmed',up);
 s.on('ended',function(e){if(s._dead)return;finish('ended',e&&e.cause)});
 s.on('failed',function(e){if(s._dead)return;finish('failed',(e&&e.cause)||'')});
 s.on('hold',function(e){if(e.originator==='local'){held=true;$('bHold').classList.add('on');$('bHold').lastChild.textContent='Resume'}});
 s.on('unhold',function(e){if(e.originator==='local'){held=false;$('bHold').classList.remove('on');$('bHold').lastChild.textContent='Hold'}});
 /* remote audio: an INCOMING call has no RTCPeerConnection yet when it is wired (JsSIP creates it on answer), so listen for 'peerconnection' too; build a stream from the track when the PBX sends none; always (re)start playback */
 var attach=function(pc){if(!pc||pc._bdA)return;pc._bdA=1;pc.addEventListener('track',function(ev){var a=$('aud'),st=ev.streams&&ev.streams[0];if(!st){st=a.srcObject instanceof MediaStream?a.srcObject:new MediaStream();if(ev.track&&st.getTracks().indexOf(ev.track)<0)st.addTrack(ev.track)}a.srcObject=st;try{var pr=a.play();if(pr&&pr.catch)pr.catch(function(){})}catch(x){}if(phase==='connecting'&&dir==='out')setPhase('ringing')})};
 attach(s.connection);s.on('peerconnection',function(e){attach(e.peerconnection)});
 s.on('confirmed',function(){attach(s.connection);var a=$('aud');try{if(a.srcObject===null&&s.connection){var ts=s.connection.getReceivers().map(function(r){return r.track}).filter(function(t){return t&&t.kind==='audio'});if(ts.length)a.srcObject=new MediaStream(ts)}var pr=a.play();if(pr&&pr.catch)pr.catch(function(){})}catch(x){}})}
function friendly(c){c=String(c||'');
 if(/busy|rejected|decline/i.test(c))return 'Call Rejected';
 if(/unavailable|not found|address incomplete|no answer|timeout|expired/i.test(c))return 'Number Unreachable';
 if(/connection|rtp|websocket/i.test(c))return 'Connection Lost';
 if(/authent/i.test(c))return 'Registration Failed';
 return 'PBX Error'}
function finish(kind,cause){if(!ses&&phase!=='connecting'&&phase!=='ringing'&&phase!=='connected')return;
 var wasConn=!!connAt,d=secs(),id=logId,raw=String(cause||''),canceled=/cancel|bye|terminated/i.test(raw);
 clearInterval(tick);qStop();stopRing();recStop(wasConn?id:null);$('ovInc').classList.remove('on');
 var status,label,isFail=false;
 if(wasConn){status='answered';label='Call Ended'}
 else if(dir==='in'){status=/cancel/i.test(raw)?'missed':'rejected';label='Call Ended'}
 else if(canceled||kind==='ended'){status='missed';label='Call Ended'}
 else{isFail=true;label=friendly(raw);status=/busy/i.test(raw)?'busy':(/rejected|decline/i.test(raw)?'rejected':'failed')}
 if(raw)console.warn('dialer call end:',raw);
 setPhase(isFail?'failed':'ended',label);
 if(isFail)err(label);
 if(id)post('/admin/call-center/log/'+id+'/end',{status:status,duration:d,fail_reason:raw.slice(0,150)});
 var sc={id:id,dur:d,connected:wasConn,dir:dir,phone:cur,info:info,status:status};
 ses=null;logId=null;recInd();ui();
 if(id&&(wasConn||dir==='out')&&!(isFail&&/Registration/.test(label)))setTimeout(function(){openSum(sc)},350)}
function hang(){if(ses){try{ses.terminate()}catch(e){console.warn(e)}
 setTimeout(function(){if(ses&&inCall())finish('ended','Canceled')},1500)}else if(phase==='connecting'||phase==='ringing'){cancelReq=true;finish('ended','Canceled')}}
/* ---------- incoming ---------- */
function ringStart(){try{inCtx=inCtx||new (window.AudioContext||window.webkitAudioContext)();if(inCtx.state==='suspended')inCtx.resume();
 var cp=inCtx.createDynamicsCompressor(),mg=inCtx.createGain();mg.gain.value=1;cp.connect(mg);mg.connect(inCtx.destination);
 var seq=[[880,0],[1109,.16],[1319,.32],[1760,.48],[1319,.78],[1760,.94],[2093,1.1],[1760,1.5]];
 var burst=function(){var t=inCtx.currentTime+.02;seq.forEach(function(n){[['triangle',1,.55],['sine',2,.22],['square',.5,.06]].forEach(function(w){var o=inCtx.createOscillator(),g=inCtx.createGain();o.type=w[0];o.frequency.value=n[0]*w[1];var a=t+n[1];g.gain.setValueAtTime(0,a);g.gain.linearRampToValueAtTime(w[2],a+.015);g.gain.exponentialRampToValueAtTime(.0008,a+.42);o.connect(g);g.connect(cp);o.start(a);o.stop(a+.45)})})};
 burst();ring=setInterval(burst,2600);if(navigator.vibrate)navigator.vibrate([400,200,400])}catch(e){}}
function stopRing(){clearInterval(ring);ring=null;noteClose()}
function incoming(s){if(ses||agBusy){try{s.terminate({status_code:486})}catch(e){}return}
 var num=clean((s.remote_identity&&s.remote_identity.uri&&s.remote_identity.uri.user)||'');
 ses=s;begin(num,'in');setPhase('ringing');wire(s);ringStart();
 $('icN').textContent=(s.remote_identity&&s.remote_identity.display_name)||'Unknown Customer';$('icP').textContent=num;$('icI').textContent='';$('icAv').textContent='?';warn('icW',[]);$('ovInc').classList.add('on');tab('act');
 if(emb)parent.postMessage({dfdial:'incoming'},location.origin);
 post('/admin/call-center/log',{direction:'in',phone:num,extension:cfg&&cfg.ext}).then(function(j){logId=j.id;lastLog=j.id});
 callNote('Incoming call',num)}
$('icA').onclick=function(){stopRing();$('ovInc').classList.remove('on');setPhase('connecting');mic().then(function(){ses.answer({mediaConstraints:{audio:AC,video:false},pcConfig:iceCfg()})}).catch(function(){micErr();try{ses.terminate({status_code:486})}catch(e){}})};
$('icR').onclick=function(){stopRing();$('ovInc').classList.remove('on');try{ses.terminate({status_code:486})}catch(e){}};
/* ---------- in-call controls ---------- */
function dtmf(k){if(ses&&connected()){try{ses.sendDTMF(k,{duration:160,interToneGap:80})}catch(e){console.warn(e)}}}
document.querySelectorAll('#tp-pad [data-k]').forEach(function(b){b.onclick=function(){var k=b.dataset.k;if(connected())dtmf(k);else if(!inCall()){$('num').value+=k;found()}}});
document.querySelectorAll('#kp [data-d]').forEach(function(b){b.onclick=function(){dtmf(b.dataset.d)}});
$('bk').onclick=function(){if(!inCall()){$('num').value=$('num').value.slice(0,-1);found()}};
$('bMuteQ').onclick=function(){$('bMute').click();var on=$('bMute').classList.contains('on');this.classList.toggle('on',on)};$('aCp').onclick=function(){var t=$('aP').textContent;try{navigator.clipboard.writeText(t);this.textContent='✓';var b=this;setTimeout(function(){b.textContent='⧉'},1200)}catch(e){}};$('cSave').onclick=function(){var id=logId||(sum&&sum.id)||lastLog,n=$('cNote').value.trim();if(!n){return}if(!id){$('cNm').textContent='';return}var b=this;b.disabled=true;post('/admin/call-center/log/'+id+'/note',{note:n}).then(function(j){b.disabled=false;$('cNm').textContent=j&&j.ok?'Saved':'Could not save';if(j&&j.ok)$('cNote').value='';setTimeout(function(){$('cNm').textContent=''},2500)}).catch(function(){b.disabled=false;$('cNm').textContent='Could not save'})};$('bGo').onclick=function(){call()};$('bHang1').onclick=hang;$('bHang2').onclick=hang;
$('bMute').onclick=function(){if(!connected())return;muted=!muted;try{muted?ses.mute({audio:true}):ses.unmute({audio:true})}catch(e){}this.classList.toggle('on',muted);this.lastChild.textContent=muted?'Unmute':'Mute'};
$('bHold').onclick=function(){if(!connected())return;try{held?ses.unhold():ses.hold()}catch(e){console.warn(e)}};
$('bKp').onclick=function(){var k=$('kp');k.classList.toggle('on');this.classList.toggle('on',k.classList.contains('on'))};
if(typeof HTMLMediaElement!=='undefined'&&HTMLMediaElement.prototype.setSinkId&&navigator.mediaDevices&&navigator.mediaDevices.enumerateDevices){$('bSpk').style.display='';$('ctl').classList.add('c5');
 var spkI=0;$('bSpk').onclick=function(){navigator.mediaDevices.enumerateDevices().then(function(l){var o=l.filter(function(d){return d.kind==='audiooutput'});if(o.length<2){err('No other speaker found on this device.');return}spkI=(spkI+1)%o.length;return $('aud').setSinkId(o[spkI].deviceId).then(function(){$('bSpk').lastChild.textContent=(o[spkI].label||'Speaker').slice(0,9)})}).catch(function(){err('Could not switch speaker.')})}}
$('bTr').onclick=function(){if(!connected())return;$('trErr').textContent='';$('trExt').value='';$('ovTr').classList.add('on');setTimeout(function(){$('trExt').focus()},50)};
$('trX').onclick=function(){$('ovTr').classList.remove('on')};
$('trGo').onclick=function(){var x=clean($('trExt').value);if(!x){$('trErr').textContent='Enter an extension.';return}
 if(!ses||!connected()){$('ovTr').classList.remove('on');return}
 $('trErr').style.color='#374151';$('trErr').textContent='Transferring...';
 try{var rs=ses.refer('sip:'+x+'@'+cfg.domain);
  rs.on('requestSucceeded',function(){$('ovTr').classList.remove('on');err('Call transferred to '+x)});
  rs.on('requestFailed',function(){$('trErr').style.color='#B91C1C';$('trErr').textContent='Transfer failed. The PBX did not accept it.'});
  rs.on('accepted',function(){setTimeout(function(){if(ses&&connected())hang()},400)});
  rs.on('failed',function(){err('Transfer did not complete.')})}catch(e){console.warn(e);$('trErr').style.color='#B91C1C';$('trErr').textContent='Transfer is not available.'}};
/* ---------- call summary ---------- */
var sel={out:null,resp:null,can:null};
function openSum(c){sum=c;sel={out:c.connected?null:'no_answer',resp:c.connected?null:'No Answer',can:null,cb:null};$('sMsg').checked=!c.connected&&c.dir!=='in';
 var i=c.info||{};$('sN').textContent=i.found&&i.name?i.name:'Unknown Customer';$('sP').textContent=c.phone;$('sD').textContent=c.connected?fmtD(c.dur):'Not connected';$('sO').textContent=i.last?i.last.invoice:'—';$('sA').textContent=(cfg&&cfg.staff)||'—';
 $('sR').textContent=!c.connected?'No Recording':(cfg&&cfg.rec?'Saving recording':'No Recording');
 var ed=$('sEdit');if(i.last){ed.style.display='block';ed.href='/admin/orders/'+i.last.id+'/edit?call='+c.id}else ed.style.display='none';
 $('sNote').value='';$('sErr').textContent='';paintSum();$('ovSum').classList.add('on');if(emb)parent.postMessage({dfdial:'summary'},location.origin)}
function paintSum(){document.querySelectorAll('#ocO button').forEach(function(b){b.classList.toggle('on',b.dataset.v===sel.out)});
 document.querySelectorAll('#ocR .chip').forEach(function(b){b.classList.toggle('on',b.dataset.v===sel.resp)});
 document.querySelectorAll('#ocC .chip').forEach(function(b){b.classList.toggle('on',b.dataset.v===sel.can)});
 $('cwrap').style.display=sel.out==='cancelled'?'':'none';
 var cbOn=sel.out==='call_later'||sel.out==='no_answer';$('cbx').classList.toggle('on',cbOn);if(!cbOn)sel.cb=null;document.querySelectorAll('#cbC .chip').forEach(function(b){b.classList.toggle('on',b.dataset.m===String(sel.cb))});
 $('smx').classList.toggle('on',sel.out==='no_answer'&&!!sum&&sum.dir!=='in')}
document.querySelectorAll('#cbC .chip').forEach(function(b){b.onclick=function(){sel.cb=String(sel.cb)===b.dataset.m?null:b.dataset.m;paintSum()}});
document.querySelectorAll('#ocO button').forEach(function(b){b.onclick=function(){sel.out=sel.out===b.dataset.v?null:b.dataset.v;if(sel.out!=='cancelled')sel.can=null;paintSum()}});
document.querySelectorAll('#ocR .chip').forEach(function(b){b.onclick=function(){sel.resp=sel.resp===b.dataset.v?null:b.dataset.v;paintSum()}});
document.querySelectorAll('#ocC .chip').forEach(function(b){b.onclick=function(){sel.can=sel.can===b.dataset.v?null:b.dataset.v;paintSum()}});
function saveSum(next){if(!sum)return;var note=$('sNote').value.trim();
 if(!sel.out&&!sel.resp&&!note){$('sErr').textContent='Select an outcome or write a note first.';return}
 if(sel.out==='cancelled'&&!sel.can){$('sErr').textContent='Select a cancel reason.';return}
 if(sel.out==='cancelled'&&sel.can==='Other'&&!note){$('sErr').textContent='Write a note when the reason is Other.';return}
 var o={outcome:sel.out,response:sel.resp,cancel_reason:sel.out==='cancelled'?sel.can:null,note:note};if(sel.cb){if(sel.cb==='tom'){var t=new Date();t.setDate(t.getDate()+1);t.setHours(11,0,0,0);o.callback_min=Math.max(5,Math.round((t.getTime()-Date.now())/60000))}else o.callback_min=+sel.cb}if(sel.out==='no_answer'&&$('sMsg').checked&&sum.dir!=='in')o.send_msg=1;if(sum.info&&sum.info.last)o.order_id=sum.info.last.id;
 $('sSave').disabled=true;$('sNext').disabled=true;
 post('/admin/call-center/log/'+sum.id+'/summary',o).then(function(j){$('sSave').disabled=false;$('sNext').disabled=false;if(j&&j.ok){$('ovSum').classList.remove('on');sum=null;if(next){$('num').value='';found();tab('pad');$('num').focus();upNextFill(cur)}else{tab('pad');if(emb)parent.postMessage({dfdial:'done'},location.origin)}}else $('sErr').textContent=(j&&j.error)||'Could not save. Try again.'}).catch(function(){$('sSave').disabled=false;$('sNext').disabled=false;$('sErr').textContent='Could not save. Try again.'})}
$('sSave').onclick=function(){saveSum(false)};$('sNext').onclick=function(){saveSum(true)};$('sSkip').onclick=function(){$('ovSum').classList.remove('on');sum=null;tab('pad');if(emb)parent.postMessage({dfdial:'done'},location.origin)};
/* ---------- dial pad: customer found ---------- */
var lastN='',ft=null;
function chatBtn(c){var b=$('bChat');if(!b)return;if(c&&c.thread_id){b.href='/admin/chat?open='+c.thread_id;b.classList.remove('off')}else{b.href='#';b.classList.add('off')}}
function found(){clearTimeout(ft);ft=setTimeout(function(){var raw=$('num').value||'',f=$('found');
 if(/[A-Za-zঀ-৿]/.test(raw)){var q=raw.trim();lastN='~'+q;chatBtn(null);if(q.length<2){f.className='fnd';f.innerHTML='';return}
  getj('/admin/call-center/contacts?q='+encodeURIComponent(q)).then(function(a){if(($('num').value||'').trim()!==q)return;f.className='fnd on sgl';f.innerHTML=a.length?a.slice(0,4).map(function(c){return '<button type="button" class="sg1" data-n="'+esc(c.phone)+'"><b>'+esc(c.name)+'</b><span>'+esc(c.phone)+' · '+esc(c.category)+' · '+c.orders+' orders</span></button>'}).join(''):'<span class="fno">No customer found with this name.</span>'}).catch(function(){});return}
 var n=raw.replace(/\D/g,'');if(n===lastN)return;lastN=n;if(n.length<6){f.className='fnd';f.innerHTML='';chatBtn(null);return}
 look(n).then(function(c){if(($('num').value||'').replace(/\D/g,'')!==n)return;chatBtn(c);
  if(c.found){var nm=c.name||'Customer';f.className='fnd on';f.innerHTML='<span class="fav">'+esc(nm.trim().charAt(0).toUpperCase())+'</span><span class="fmid"><b>'+esc(nm)+'<i class="tg '+tagCls(c.category)+'">'+esc(c.category||'Regular')+'</i></b><small>'+(c.delivered||0)+' received of '+c.orders+' orders'+(c.last?' · Last: '+esc(c.last.invoice)+' · '+esc(c.last.status):'')+'</small>'+(c.fraud?'<small>'+fr(c.fraud)+'</small>':'')+(c.best?'<small class="bt" title="From this number\'s earlier calls that were answered. A hint only.">Usually answers '+esc(c.best.when)+' ('+c.best.n+' of '+c.best.of+' answered calls)</small>':'')+'</span>'+(c.customer_id?'<a href="/admin/m/customers/'+c.customer_id+'/edit" target="_blank">Profile</a>':'')}
  else if(n.length>=10){f.className='fnd on unk';f.innerHTML='<span class="fmid"><b>Unknown customer</b><small>No order or profile with this number</small></span><a href="/admin/m/customers/new?phone='+encodeURIComponent(n)+'" target="_blank">Create</a>'}else{f.className='fnd'}})},250)}
$('num').addEventListener('input',found);
$('num').addEventListener('keydown',function(e){if(e.key==='Enter'&&!inCall()){e.preventDefault();call()}});
/* ---------- Up next: who should be called now (read-only list; the staff member still presses Call) ---------- */
var UPL={'new':['New order','n'],callback:['Callback','c'],noanswer:['No answer','a'],missed:['Missed call','m']},upItems=[],upAll=false;
function ago(m){return m<60?m+' min':(m<1440?Math.floor(m/60)+' h '+(m%60)+' min':Math.floor(m/1440)+' d')}
function upRow(x){var L=UPL[x.type]||['Call',''];var sub=x.type==='new'?'Waiting '+ago(x.mins)+' to be confirmed':(x.type==='callback'?(x.due?(x.late>=0?'Call back was due '+x.due+(x.late>0?', '+ago(x.late)+' late':''):'Call back at '+x.due):'Asked for a call back '+ago(x.mins)+' ago')+(x.note?' · '+x.note:''):(x.type==='noanswer'?'Not answered '+ago(x.mins)+' ago, try again':'Called us '+ago(x.mins)+' ago, not called back'));
 return '<div class="ur '+L[1]+'"><div class="um"><b><i class="ut">'+L[0]+'</i>'+esc(x.name||x.phone)+'</b><small>'+esc(sub)+(x.invoice?' · #'+esc(x.invoice):'')+'</small></div><button type="button" class="ub" data-n="'+esc(x.phone)+'" data-o="'+esc(x.invoice||'')+'" data-cn="'+esc(x.name||'')+'" title="Put this number in the dialer">Dial</button></div>'}
function upDraw(){var n=upItems.length,a=$('upn'),b=$('upn2');if(!n){if(a)a.innerHTML='';if(b)b.innerHTML='';return}
 var hd=function(more){return '<div class="uh"><span>Up next · '+n+' waiting</span>'+(more?'<button type="button" id="upMore">'+(upAll?'Show less':'See all')+'</button>':'')+'</div>'};
 if(a)a.innerHTML=hd(n>2)+upItems.slice(0,upAll?n:2).map(upRow).join('');if(b)b.innerHTML=hd(false)+upItems.map(upRow).join('');
 var m=$('upMore');if(m)m.onclick=function(){upAll=!upAll;upDraw()}}
var rmSeen={},rmCur=null;
function remind(){if(inCall()||$('rmd').classList.contains('on'))return;var x=upItems.filter(function(i){return i.type==='callback'&&i.due&&i.late>=0&&!rmSeen[i.phone+'|'+i.due]})[0];if(!x)return;rmSeen[x.phone+'|'+x.due]=1;rmCur=x;$('rmdT').textContent='Call back due: '+(x.name||x.phone)+' at '+x.due;$('rmd').classList.add('on');if(window.Notification&&Notification.permission==='granted')try{new Notification('Call back due',{body:(x.name||x.phone)+' at '+x.due})}catch(e){}}
$('rmdX').onclick=function(){$('rmd').classList.remove('on')};$('rmdG').onclick=function(){$('rmd').classList.remove('on');if(rmCur)autoDial(rmCur.phone,{o:rmCur.invoice?'#'+rmCur.invoice:'',cn:rmCur.name})};
function upLoad(cb){if(document.hidden&&!cb)return;getj('/admin/call-center/up-next').then(function(j){upItems=(j&&j.items)||[];upDraw();remind();if(cb)cb()}).catch(function(){})}
function upNextFill(skip){var sk=String(skip||'').replace(/\D/g,'').slice(-11);upLoad(function(){var x=upItems.filter(function(i){return i.phone!==sk})[0];if(x&&!inCall())autoDial(x.phone,{o:x.invoice?'#'+x.invoice:'',cn:x.name})})}
document.addEventListener('click',function(e){var u=e.target.closest('.ub');if(u){autoDial(u.dataset.n,{o:u.dataset.o?'#'+u.dataset.o:'',cn:u.dataset.cn});return}var g=e.target.closest('.sg1');if(g){$('num').value=g.dataset.n;lastN='';found();$('num').focus()}});
upLoad(function(){});setInterval(function(){upLoad()},45000);
/* shortcuts while on a call (never while typing): M mute, H hold, Esc end */
document.addEventListener('keydown',function(e){if(e.ctrlKey||e.metaKey||e.altKey)return;var t=(e.target&&e.target.tagName)||'';if(/INPUT|TEXTAREA|SELECT/.test(t))return;if(document.querySelector('.ov.on'))return;if(!connected())return;var k=e.key.toLowerCase();if(k==='m'){e.preventDefault();$('bMute').click()}else if(k==='h'){e.preventDefault();$('bHold').click()}else if(k==='escape'){e.preventDefault();hang()}});
/* ---------- contacts / recent / details ---------- */
function contacts(){getj('/admin/call-center/contacts?q='+encodeURIComponent($('cq').value)).then(function(a){$('cl').innerHTML=a.length?a.map(function(c){return '<div class="li"><span class="av2">'+esc((c.name||'?').trim().charAt(0).toUpperCase())+'</span><div class="mid"><b>'+esc(c.name)+'</b><small>'+esc(c.phone)+' · <span class="tg '+tagCls(c.category)+'">'+esc(c.category)+'</span>'+c.orders+' Orders</small></div><button type="button" class="cb" data-n="'+esc(c.phone)+'">Call</button></div>'}).join(''):'<div class="mut" style="padding:14px">No customers found.</div>'})}
var qt;$('cq').oninput=function(){clearTimeout(qt);qt=setTimeout(contacts,250)};
var OC={confirmed:'Confirmed',pending:'Pending',call_later:'Call Later',no_answer:'No Answer',cancelled:'Cancelled'},RI={available:'🎧',processing:'⏳',recording:'⏳'};
function recent(){getj('/admin/call-center/recent').then(function(a){$('rl').innerHTML=a.length?a.map(function(c){return '<div class="li" data-id="'+c.id+'"><span class="av2">'+esc((c.name||c.phone||'?').trim().charAt(0).toUpperCase())+'</span><div class="mid"><b>'+esc(c.name||c.phone)+'</b><small>'+esc(c.phone)+' · '+(c.dir==='in'?'↙ Incoming':'↗ Outgoing')+'<br>'+esc(c.at)+' · '+fmtD(c.dur)+' · '+esc(OC[c.outcome]||c.status)+(RI[c.rec]?' · '+RI[c.rec]:'')+'</small></div></div>'}).join(''):'<div class="mut" style="padding:14px">No calls yet.</div>'})}
var detPhone='';
function detail(id){getj('/admin/call-center/call/'+id).then(function(d){detPhone=d.phone;$('dSub').textContent=(d.name||'Unknown Customer')+' · '+d.phone;
 var rows=[['Direction',d.dir==='in'?'Incoming':'Outgoing'],['Agent',d.agent||'—'],['Extension',d.ext||'—'],['Status',d.status],['Dial started',d.dial||'—'],['Connected',d.conn||'—'],['Ended',d.end||'—'],['Duration',fmtD(d.dur)],['Order',d.order?d.order.invoice+' · '+d.order.status:'—'],['Outcome',OC[d.outcome]||'—'],['Response',d.response||'—'],['Cancel reason',d.cancel||'—'],['Note',d.note||'—']];
 $('dBody').innerHTML=rows.map(function(r){return '<div class="r"><span>'+r[0]+'</span><b>'+esc(r[1])+'</b></div>'}).join('');
 $('dRec').innerHTML=d.rec_url?'<audio controls preload="none" src="'+esc(d.rec_url)+'" style="width:100%"></audio>':'<div class="mut">'+(d.rec==='processing'||d.rec==='recording'?'Recording Processing':'No Recording')+'</div>';
 $('ovDet').classList.add('on')})}
$('dX').onclick=function(){$('ovDet').classList.remove('on')};$('dCall').onclick=function(){$('ovDet').classList.remove('on');dialNum(detPhone)};
function dialNum(n){if(inCall()){tab('act');err('Call already in progress');return}$('num').value=n;lastN='';found();call(n)}
document.addEventListener('click',function(e){var b=e.target.closest('.cb');if(b){e.stopPropagation();dialNum(b.dataset.n);return}var li=e.target.closest('#rl .li');if(li)detail(li.dataset.id)});
$('bRc').onclick=reconnect;
/* ---------- window / PBX messages ---------- */
if(emb){var P=function(a){parent.postMessage({dfdial:a},location.origin)};$('pX').onclick=function(){P('close')};$('pMin').onclick=function(){P('min')};$('pPop').onclick=function(){P('pop')}}
if(ch)ch.onmessage=function(m){var d=m.data||{};if(d.type==='status'){stApply(d.v,false);return}if(d.type==='ping')ch.postMessage({type:'here'});if(d.type==='dial'){$('num').value=clean(d.n);lastN='';found();tab('pad');window.focus();$('num').focus()}if(d.type==='call'){window.focus();autoDial(d.n,d)}if(d.type==='takeover'){if(inCall()){ch.postMessage({type:'takeover-busy'})}else{try{disconnect()}catch(e){}ch.postMessage({type:'takeover-ok'})}}if(d.type==='disconnect')disconnect();if(d.type==='connect')connect();if(d.type==='getstatus')ch.postMessage({type:'status',text:$('bdg').textContent,cls:$('bdg').className.replace('badge ','')})};
window.addEventListener('beforeunload',function(e){if(inCall()){e.preventDefault();e.returnValue=''}});
if(window.Notification&&Notification.permission==='default')try{Notification.requestPermission()}catch(e){}
/* ---------- incoming-call notice that also works on phones (through the site's service worker when one is registered) ---------- */
function callNote(title,body){try{if(!window.Notification||Notification.permission!=='granted')return;var o={body:body||'',tag:'df-call',renotify:true,requireInteraction:true,vibrate:[400,200,400,200,400],data:{url:'/admin/call-center/phone'}};
 var plain=function(){try{var n=new Notification(title,{body:o.body,tag:o.tag});n.onclick=function(){try{window.focus()}catch(e){}n.close()};callNote.n=n}catch(e){}};
 if(navigator.serviceWorker&&navigator.serviceWorker.getRegistration){navigator.serviceWorker.getRegistration('/').then(function(r){if(r&&r.showNotification)r.showNotification(title,o).catch(plain);else plain()}).catch(plain)}else plain()}catch(e){}}
function noteClose(){try{if(callNote.n){callNote.n.close();callNote.n=null}if(navigator.serviceWorker&&navigator.serviceWorker.getRegistration)navigator.serviceWorker.getRegistration('/').then(function(r){if(r&&r.getNotifications)r.getNotifications({tag:'df-call'}).then(function(a){a.forEach(function(n){n.close()})})}).catch(function(){})}catch(e){}}
/* ---------- headset and network check. Uses its own microphone stream and audio context, never the call's; refuses to run during a call. ---------- */
var ck={st:null,ctx:null,raf:0};
function ckSet(id,t,c){var e=$(id);if(!e)return;e.textContent=t;e.className='ckv'+(c?' '+c:'')}
function ckStop(){try{cancelAnimationFrame(ck.raf)}catch(e){}try{if(ck.st)ck.st.getTracks().forEach(function(t){t.stop()})}catch(e){}try{if(ck.ctx)ck.ctx.close()}catch(e){}ck.st=null;ck.ctx=null;$('ckL').style.width='0'}
function ckMic(){$('ckD').textContent='';if(inCall()){ckSet('ckM','You are on a call. Run this after the call ends.','mid');return}
 if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){ckSet('ckM','This browser cannot use a microphone.','bad');return}
 ckSet('ckM','Listening… say a few words');
 navigator.mediaDevices.getUserMedia({audio:true}).then(function(st){if(!$('ovChk').classList.contains('on')||inCall()){st.getTracks().forEach(function(t){t.stop()});return}
  ck.st=st;var tr=st.getAudioTracks()[0];$('ckD').textContent=tr&&tr.label?'Using: '+tr.label:'';var A=window.AudioContext||window.webkitAudioContext;if(!A){ckSet('ckM','Microphone is allowed.','ok');return}
  ck.ctx=new A();var an=ck.ctx.createAnalyser();an.fftSize=512;ck.ctx.createMediaStreamSource(st).connect(an);var buf=new Uint8Array(an.fftSize),peak=0,t0=Date.now();
  (function loop(){if(!ck.st)return;an.getByteTimeDomainData(buf);var m=0;for(var i=0;i<buf.length;i++){var v=Math.abs(buf[i]-128);if(v>m)m=v}var lv=Math.min(100,Math.round(m/128*220));if(lv>peak)peak=lv;$('ckL').style.width=lv+'%';
   if(Date.now()-t0>2500){if(peak>=12)ckSet('ckM','Working. Your voice is being picked up.','ok');else ckSet('ckM','Allowed, but no sound is coming in. Check the headset plug or the mute switch.','bad')}
   ck.raf=requestAnimationFrame(loop)})()}).catch(function(e){ckSet('ckM',e&&e.name==='NotAllowedError'?'Blocked. Allow the microphone for this site in the browser.':'No microphone found. Plug in the headset.','bad')})}
function ckTone(){try{var A=window.AudioContext||window.webkitAudioContext,c=new A(),o=c.createOscillator(),g=c.createGain();o.frequency.value=660;g.gain.setValueAtTime(.0001,c.currentTime);g.gain.exponentialRampToValueAtTime(.25,c.currentTime+.05);g.gain.exponentialRampToValueAtTime(.0001,c.currentTime+.9);o.connect(g);g.connect(c.destination);o.start();o.stop(c.currentTime+1);o.onended=function(){try{c.close()}catch(e){}};ckSet('ckS','Tone played. If you did not hear it, check the headset volume or the output device.')}catch(e){ckSet('ckS','Could not play a sound in this browser.','bad')}}
function ckNet(){ckSet('ckN','Testing…');var ts=[],i=0,cn=navigator.connection;$('ckC').textContent=cn&&cn.effectiveType?'Connection type reported by the browser: '+cn.effectiveType:'';
 (function one(){var t=performance.now();fetch('/manifest.webmanifest?ck='+Date.now(),{cache:'no-store'}).then(function(r){return r.text()}).then(function(){ts.push(performance.now()-t)}).catch(function(){ts.push(-1)}).then(function(){if(++i<5)return setTimeout(one,150);
   var ok=ts.filter(function(x){return x>=0}),lost=ts.length-ok.length;if(!ok.length){ckSet('ckN','No connection to the server.','bad');return}ok.sort(function(a,b){return a-b});var med=Math.round(ok[Math.floor(ok.length/2)]),jit=Math.round(ok[ok.length-1]-ok[0]);
   var txt='Response '+med+' ms, variation '+jit+' ms'+(lost?', '+lost+' of 5 failed':'');if(lost||med>600||jit>400)ckSet('ckN','Weak. '+txt+'. Calls may break up.','bad');else if(med>250||jit>180)ckSet('ckN','Usable. '+txt+'.','mid');else ckSet('ckN','Good. '+txt+'.','ok')})})()}
function ckRun(){ckStop();var b=$('bdg'),t=b?b.textContent.trim():'';ckSet('ckP',t||'Unknown',/disconn|fail|required/i.test(t)?'bad':'ok');ckMic();ckNet()}
$('bChk').onclick=function(){$('ovChk').classList.add('on');ckSet('ckS','Press to hear a short tone');ckRun()};
$('ckX').onclick=function(){ckStop();$('ovChk').classList.remove('on')};$('ckR').onclick=ckRun;$('ckT').onclick=ckTone;

function chk(){if(inCall()||!cfg)return;getj('/admin/call-center/creds').then(function(c){if(c&&c.ok&&(c.user!==cfg.user||c.pw!==cfg.pw||c.domain!==cfg.domain||c.wss!==cfg.wss)){reconnect()}}).catch(function(){})}
document.addEventListener('visibilitychange',function(){if(!document.hidden)chk()});window.addEventListener('focus',chk);setInterval(chk,60000);
setPhase('idle');ui();
if($('num').value)found();
(function(){var seen={};window.addEventListener('message',function(e){var d=e.data;if(e.origin!==location.origin||!d||!d.dfcall)return;try{parent.postMessage({dfdial:'callack',id:d.id},location.origin)}catch(x){}if(seen[d.id])return;seen[d.id]=1;window.focus();autoDial(d.n,d)})})();
(function(){var q=new URLSearchParams(location.search);if(q.get('auto')==='1'&&q.get('n'))autoDial(q.get('n'),{o:q.get('o'),cn:q.get('cn')})})();
if($('msg').textContent==='')connect();else badge('Disconnected','b-bad');
})();
</script>
@endverbatim
@include('popup_close')</body></html>
