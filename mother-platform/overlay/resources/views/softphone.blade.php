<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><meta name="csrf" content="{{ csrf_token() }}"><title>DutyFree BD Dialer</title>
<style>
:root{--n:#0F1F3D;--n2:#1B2F55;--o:#F26B1D;--g:#16A34A;--r:#DC2626;--bd:#E5E7EB;--gr:#F3F4F6;--mu:#6B7280}*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',Roboto,'Noto Sans Bengali',Arial,sans-serif;background:#EEF0F4;color:var(--n);font-size:13px}
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
.li .cb{border:0;background:#E8F8EE;color:#15803D;padding:0 11px;height:30px;border-radius:6px;cursor:pointer;font-weight:800;font-size:12px;flex:none}
.tg{display:inline-block;font-size:10.5px;font-weight:800;padding:1px 7px;border-radius:8px;background:#EEF2FF;color:#3730A3;margin-right:3px}.tg.vip{background:#FEF3C7;color:#92400E}.tg.new{background:#E0F2FE;color:#075985}.tg.o{background:#DCFCE7;color:#166534}.tg.x{background:#FEE2E2;color:#991B1B}
.act{text-align:center;padding:4px 0 0}.act .av{width:58px;height:58px;border-radius:50%;background:var(--n);color:#fff;font-size:22px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 6px}.act .nm{font-size:17px;font-weight:800;display:block}.act .ph1{color:var(--mu);margin-top:2px;font-variant-numeric:tabular-nums}
.act .as{font-weight:800;margin-top:6px;font-size:13px;color:var(--mu)}.act .as.ok{color:var(--g)}.act .as.bad{color:var(--r)}.act .as.wt{color:#2563EB}.act .at{font-size:26px;font-weight:800;font-variant-numeric:tabular-nums;margin-top:1px}
.rec{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;color:#B91C1C}.rec i{width:8px;height:8px;border-radius:50%;background:var(--r);animation:bl 1.2s infinite}@keyframes bl{50%{opacity:.25}}
.card2{background:var(--gr);border:1px solid var(--bd);border-radius:8px;padding:8px 10px;text-align:left}.card2 .r{display:flex;justify-content:space-between;gap:8px;padding:2px 0}.card2 .r span{color:var(--mu)}.card2 .r b{text-align:right}
.qa{display:grid;grid-template-columns:1fr 1fr;gap:7px}.qa a{text-align:center;border:1px solid var(--bd);border-radius:8px;padding:8px;font-size:12.5px;font-weight:700;color:var(--n);text-decoration:none;background:#fff}.qa a:hover{border-color:var(--o);color:var(--o)}.qa a.off{pointer-events:none;opacity:.4}
.ctl{display:grid;grid-template-columns:repeat(4,1fr);gap:7px}.ctl.c5{grid-template-columns:repeat(5,1fr)}.ctl button{border:1px solid var(--bd);background:#fff;border-radius:8px;padding:8px 2px;font-size:11.5px;font-weight:700;color:var(--n);cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:2px}.ctl button i{font-style:normal;font-size:16px}.ctl button.on{background:var(--n);color:#fff;border-color:var(--n)}
.fr{display:inline-block;font-size:11.5px;font-weight:800;padding:2px 8px;border-radius:8px}
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
.cpy{border:1px solid var(--bd);background:#fff;border-radius:6px;width:24px;height:22px;cursor:pointer;font-size:12px;color:#475569;vertical-align:middle}.cpy:hover{border-color:var(--o);color:var(--o)}
.top{background:linear-gradient(135deg,#0F1F3D,#16305C)}.badge{padding:2px 10px}.b-ok{background:#16A34A}.tabs button.on{background:#FFF4EA;border-radius:10px 10px 0 0}.tabs{padding:0 6px}
.keys button{box-shadow:0 1px 0 rgba(15,23,42,.06);transition:background .12s}.keys button:hover{background:#FFF4EA}.go.call{box-shadow:0 3px 0 #15803D;border-radius:12px}.go.hang{box-shadow:0 3px 0 #B91C1C}
.cn,.cidr+.wv{box-shadow:0 1px 3px rgba(15,23,42,.05)}

/* ---- final softphone typography (reference) ---- */
body,button,input,textarea,select{font-family:Inter,"Noto Sans Bengali","Segoe UI",Arial,sans-serif}
.ph{max-width:none}
.top{display:block;padding:12px 14px 12px;background:#0F2748}.tr1{display:flex;align-items:flex-start;gap:8px}.tr1 .tt{flex:1;min-width:0}.top b{font-size:22px;font-weight:700;line-height:1.15;color:#fff;letter-spacing:0}.top .sub{display:block;font-size:16px;font-weight:500;color:#fff;margin-top:1px}
.tb{width:34px;height:34px;border-radius:9px;font-size:18px;background:rgba(255,255,255,.14)}
.tr2{display:flex;align-items:center;gap:10px;margin-top:10px}.vs{width:1px;height:22px;background:rgba(255,255,255,.28)}
.stsel{max-width:none;font-size:15px;font-weight:700;padding:7px 12px;border-radius:18px}.stsel.st-available{background:#16A34A}
.badge{font-size:15px;font-weight:700;padding:5px 12px;border-radius:16px}.badge.b-ok{background:rgba(34,197,94,.18);color:#4ADE80}
.tabs{padding:0;background:#fff}.tabs button{font-size:16px;font-weight:600;color:#4B5563;padding:12px 2px;border-radius:0}.tabs button.on{color:#FF6600;font-weight:700;background:#FFF4EA;border-bottom-color:#FF6600}.tabs button svg{width:17px;height:17px;margin-right:6px;vertical-align:-3px}
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
</style></head><body>
<div class="ph{{ $embed ? ' emb' : '' }}">
<div class="top"><div class="tr1"><div class="tt"><span class="dfl"><img src="/dutyfree-bd-logo.png" alt="DutyFree BD"></span></div>@if($embed)<button type="button" class="tb" id="pPop" title="Pop out (keeps the call alive while you browse)">⧉</button><button type="button" class="tb" id="pMin" title="Minimize">–</button><button type="button" class="tb" id="pX" title="Close">×</button>@endif</div><div class="tr2"><button type="button" id="stSel" class="stsel st-{{ ($agst ?? 'available') === 'busy' ? 'busy' : 'available' }}" value="{{ ($agst ?? 'available') === 'busy' ? 'busy' : 'available' }}" title="Click to switch Online / Offline">{{ ($agst ?? 'available') === 'busy' ? '● Offline' : '● Online' }}</button><span class="vs"></span><span class="badge b-bad" id="bdg">Disconnected</span></div></div>
<div class="disc" id="disc"><span>PBX connection required</span><button type="button" id="bRc">Reconnect</button></div>
<div class="tabs"><button type="button" data-t="pad" class="on"><svg viewBox="0 0 24 24"><circle cx="5" cy="5" r="1.5"/><circle cx="12" cy="5" r="1.5"/><circle cx="19" cy="5" r="1.5"/><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>Dial</button><button type="button" data-t="con"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>Contacts</button><button type="button" data-t="rec"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Recent</button><button type="button" data-t="act" id="tAct">Active Call<span class="dot"></span></button></div>

<div class="mn"><div class="box tp" id="tp-pad">
<div class="stt"><span id="cst">Ready</span> · <span id="tmr">00:00:00</span></div>
<div id="ctxL" style="display:none;margin:0 0 6px;padding:6px 10px;border-radius:8px;background:#EFF6FF;color:#1E3A8A;font-size:12.5px;font-weight:600"></div><div class="num"><input id="num" inputmode="tel" autocomplete="off" placeholder="01XXXXXXXXX" value="{{ $n }}"><button type="button" id="bk" title="Backspace">⌫</button></div>
<div id="found" class="fnd"></div>
<div class="keys">@foreach([['1',''],['2','ABC'],['3','DEF'],['4','GHI'],['5','JKL'],['6','MNO'],['7','PQRS'],['8','TUV'],['9','WXYZ'],['*',''],['0','+'],['#','']] as $k)<button type="button" data-k="{{ $k[0] }}"><span class="kn">{{ $k[0] }}</span><small{!! $k[1]==='+' ? ' class="pl"' : '' !!}>{{ $k[1] ?: ' ' }}</small></button>@endforeach</div>
<div class="crow"><button type="button" class="go call" id="bGo"><svg class="ph1i" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg><span>Call</span></button><button type="button" class="go mq sm" id="bMuteQ" title="Mute" disabled><svg class="mi" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg><svg class="mo" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M4 4l16 16"/></svg></button><button type="button" class="go hang sm" id="bHang1" title="Hang Up" disabled><svg viewBox="0 0 24 24" class="hg"><path d="M3 14c5-5 13-5 18 0l-2 3-3-1v-2c-3-1-7-1-10 0v2l-3 1z"/></svg></button></div>
<div class="msg" id="msg">@if(!$has)আপনার এক্সটেনশন সেট করা নেই। অ্যাডমিনকে বলুন Call Center → Extensions থেকে দিতে।@endif</div>
<div class="mut"><a href="/admin/call-center/history" target="_blank">Call history</a></div>
</div>

<div class="box tp" id="tp-con" style="display:none"><input id="cq" placeholder="Search name, phone or customer ID" class="srch"><div id="cl" class="lst"></div></div>
<div class="box tp" id="tp-rec" style="display:none"><div id="rl" class="lst"></div></div>

<div class="box tp" id="tp-act" style="display:none">
<div id="aNone" class="mut" style="padding:30px 0">No active call. Dial a number or pick a contact to start.</div>
<div id="aBox" style="display:none;flex-direction:column;gap:9px"><div class="cidr"><span class="actb">● Active Call</span><span id="cid"></span></div><div class="wv" id="wv"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
 <div class="act"><div class="av" id="aAv">?</div><span class="nm" id="aN">Unknown Customer</span><div class="ph1"><span id="aP"></span> <button type="button" class="cpy" id="aCp" title="Copy number">⧉</button></div><div class="as" id="aS">—</div><div class="at" id="aT">00:00:00</div><div id="aRec" style="margin-top:2px"></div></div>
 <div class="card2" id="aCard"></div>
 <div class="qa"><a id="aCu" href="#" target="_blank">Open Customer</a><a id="aOr" href="#" target="_blank">Open Order</a></div>
 <div class="kp" id="kp">@foreach(['1','2','3','4','5','6','7','8','9','*','0','#'] as $k)<button type="button" data-d="{{ $k }}">{{ $k }}</button>@endforeach</div>
 <div class="ctl" id="ctl"><button type="button" id="bMute" disabled><i>🎙</i><span>Mute</span></button><button type="button" id="bHold" disabled><i>⏸</i><span>Hold</span></button><button type="button" id="bKp" disabled><i>⌨</i><span>Keypad</span></button><button type="button" id="bTr" disabled><i>↗</i><span>Transfer</span></button><button type="button" id="bSpk" style="display:none"><i>🔊</i><span>Speaker</span></button></div>
 <button type="button" class="go hang big" id="bHang2" disabled>⛔ End Call</button>
 <div class="msg" id="msg2"></div>
</div>
<div class="cn"><div class="cnh">Call Notes</div><textarea id="cNote" rows="3" maxlength="1000" placeholder="Write a note..."></textarea><div class="cnb"><span id="cNm"></span><button type="button" id="cSave">Save</button></div></div>
</div>
</div>
<div class="dfoot"><span class="fl"><i id="dfc" class="no">●</i> <span id="dfw">WSS</span> · Ext: <b id="dfx">—</b></span><span class="sep"></span><span>Agent: <b>{{ explode(' ', trim((string)($agname ?? '')))[0] }}</b></span><span class="sep"></span><a href="/admin/call-center/history" target="_blank">☰ History</a></div>
</div>

<div class="ov" id="ovInc"><div class="card inc"><div style="font-size:12px;color:var(--mu);font-weight:800">Incoming Call</div><div class="nm" id="icN">Unknown Customer</div><div class="ln" id="icP"></div><div class="ln" id="icI"></div><div class="acts"><button type="button" class="pri gn" id="icA">Answer</button><button type="button" class="pri rd" id="icR">Reject</button></div></div></div>

<div class="ov" id="ovTr"><div class="card"><h3>Transfer to Extension</h3><div class="sub">Connect this call to another extension.</div><input class="ti" id="trExt" inputmode="numeric" placeholder="Extension"><div class="err" id="trErr"></div><div class="acts"><button type="button" class="pri" id="trGo">Transfer Call</button><button type="button" class="sec" id="trX">Cancel</button></div></div></div>

<div class="ov" id="ovSum"><div class="card"><h3>Call Summary</h3><div class="sub">কাস্টমারের সাথে কথোপকথনের ফলাফল সংরক্ষণ করুন</div>
<div class="sg"><div><span>Customer Name</span><b id="sN">—</b></div><div><span>Phone</span><b id="sP">—</b></div><div><span>Call Duration</span><b id="sD">—</b></div><div><span>Order Number</span><b id="sO">—</b></div><div><span>Agent Name</span><b id="sA">—</b></div><div><span>Recording Status</span><b id="sR">—</b></div></div>
<a id="sEdit" href="#" target="_blank" style="display:none;margin-bottom:6px;text-align:center;border:1.5px solid var(--o);color:var(--o);border-radius:8px;padding:8px;font-weight:800;text-decoration:none">✏️ Edit Current Order</a>
<div class="lb">Order Outcome</div>
<div class="oc" id="ocO"><button type="button" data-v="confirmed" class="cf">Order Confirmed</button><button type="button" data-v="pending">Pending</button><button type="button" data-v="call_later">Call Later</button><button type="button" data-v="no_answer">No Answer</button><button type="button" data-v="cancelled" class="cn" style="grid-column:1/-1">Order Cancelled</button></div>
<div id="cwrap" style="display:none"><div class="lb">Cancel Reason</div><div id="ocC">@foreach(['Price Issue','Delivery Charge Issue','Changed Mind','Duplicate Order','Ordered by Mistake','Product Unavailable','Delay','Not Needed','Suspicious','Other'] as $c)<span class="chip" data-v="{{ $c }}">{{ $c }}</span>@endforeach</div></div>
<div class="lb">Customer Response</div>
<div id="ocR">@foreach(['Customer Received Call','Busy','No Answer','Phone Off','Unreachable','Wrong Number','Call Later','WhatsApp Required','Need More Information'] as $c)<span class="chip" data-v="{{ $c }}">{{ $c }}</span>@endforeach</div>
<div class="lb">Call Note</div><textarea id="sNote" rows="3" maxlength="1000" placeholder="কাস্টমারের সাথে কী কথা হয়েছে, সংক্ষেপে লিখুন..."></textarea>
<div class="err" id="sErr"></div>
<div class="acts"><button type="button" class="pri" id="sSave">Save &amp; Close</button><button type="button" class="pri" id="sNext" style="background:var(--n)">Save &amp; Next</button><button type="button" class="sec" id="sSkip">Skip</button></div></div></div>

<div class="ov" id="ovDet"><div class="card"><h3>Call Details</h3><div class="sub" id="dSub"></div><div class="card2" id="dBody"></div><div id="dRec" style="margin-top:8px"></div><div class="acts"><button type="button" class="pri gn" id="dCall">Call Again</button><button type="button" class="sec" id="dX">Close</button></div></div></div>
<audio id="aud" autoplay></audio>

@verbatim
<script src="/js/jssip.min.js"></script>
<script>
(function(){
var $=function(i){return document.getElementById(i)},T=document.querySelector('meta[name=csrf]').content,emb=!!document.querySelector('.ph.emb');
var ua=null,cfg=null,ses=null,logId=null,connAt=0,tick=null,dir='out',phase='idle',muted=false,held=false,cur='',info=null,ring=null,inCtx=null,sum=null,lastBusy=null,okCount=0;
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
function autoDial(n,d){n=clean(n);ctxShow(d&&d.o,d&&d.cn);if(!n)return;if(inCall()){tab('act');err('An active call is currently in progress. End or hold the current call first.');return}$('num').value=n;lastN='';found();if(reg()){dialNum(n)}else{pend=n;tab('act');err('Connecting to PBX… the call starts automatically.')}}
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
 $('bHang1').disabled=!ic;$('bMuteQ').disabled=!cn;$('bHang2').disabled=!ic;
 ['bMute','bHold','bKp','bTr'].forEach(function(i){$(i).disabled=!cn});
 $('tAct').classList.toggle('live',ic);try{$('cid').textContent=ic&&logId?'Call ID: #'+logId:'';$('wv').className='wv'+(phase==='connected'?' on':'')}catch(e){}
 var b=ic?'busy':'idle';if(b!==lastBusy){lastBusy=b;if(emb)parent.postMessage({dfdial:b},location.origin)}}
function startTimer(){connAt=Date.now();clearInterval(tick);tick=setInterval(function(){var s=fmt(Math.floor((Date.now()-connAt)/1000));$('tmr').textContent=s;$('aT').textContent=s},500)}
function secs(){return connAt?Math.floor((Date.now()-connAt)/1000):0}
var lastLeft='pad';
function tab(t){if(window.innerWidth>=900){if(t==='act')t=lastLeft;else lastLeft=t}document.querySelectorAll('.tabs button').forEach(function(b){b.classList.toggle('on',b.dataset.t===t)});document.querySelectorAll('.tp').forEach(function(p){p.style.display=p.id==='tp-'+t?'':'none'});if(t==='rec')recent();if(t==='con')contacts()}
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
 $('aCard').innerHTML=h;
 var cu=$('aCu'),or=$('aOr');
 if(c.customer_id){cu.textContent='Open Customer';cu.href='/admin/m/customers/'+c.customer_id+'/edit';cu.className=''}else{cu.textContent='Create Customer';cu.href='/admin/m/customers/new?phone='+encodeURIComponent(ph);cu.className=''}
 if(c.last){or.href='/admin/orders/'+c.last.id;or.className=''}else{or.href='#';or.className='off'}
 recInd()}
function recInd(){var e=$('aRec');if(inCall()&&connected()&&cfg&&cfg.rec)e.innerHTML='<span class="rec"><i></i>Recording</span>';else e.innerHTML=''}
function look(n){return getj('/admin/call-center/lookup?phone='+encodeURIComponent(n)).then(function(c){return c}).catch(function(){return {found:false}})}
/* ---------- connect ---------- */
function connect(){if(ua)return;badge('Connecting','b-wait');
 getj('/admin/call-center/creds').then(function(c){if(!c.ok){badge('Disconnected','b-bad');err(c.error);return}cfg=c;try{$('dfx').textContent=c.ext||'—'}catch(e){}
  try{var sock=new JsSIP.WebSocketInterface(c.wss);ua=new JsSIP.UA({sockets:[sock],uri:'sip:'+c.user+'@'+c.domain,password:c.pw,authorization_user:c.auth,display_name:c.name,register:true,session_timers:false})}catch(e){console.warn('dialer',e);badge('Registration Failed','b-bad');err('Registration Failed. Please contact admin.');ua=null;return}
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
function iceCfg(){return {iceServers:cfg.stun?[{urls:'stun:'+cfg.stun.replace(/^stun:/,'')}]:[{urls:'stun:stun.l.google.com:19302'}]}}
function mic(){if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia)return Promise.reject({name:'NoMedia'});return navigator.mediaDevices.getUserMedia({audio:true}).then(function(s){s.getTracks().forEach(function(t){t.stop()})})}
function micErr(){setPhase('failed','Failed');err('Microphone access is required to make calls.')}
function resetCtl(){setTimeout(busyUnreg,1500);muted=false;held=false;var q=$('bMuteQ');if(q){q.classList.remove('on')}$('bMute').classList.remove('on');$('bHold').classList.remove('on');$('bMute').lastChild.textContent='Mute';$('bHold').lastChild.textContent='Hold';$('bKp').classList.remove('on');$('kp').classList.remove('on')}
function begin(n,d){cur=n;dir=d;info=null;connAt=0;clearInterval(tick);$('tmr').textContent='00:00:00';$('aT').textContent='00:00:00';resetCtl();renderAct();look(n).then(function(c){if(cur===n){info=c;renderAct();if(d==='in'){$('icN').textContent=c.found&&c.name?c.name:'Unknown Customer';$('icI').textContent=c.found?((c.category||'Customer')+' · '+c.orders+' orders'+(c.last?' · Last: '+c.last.invoice+' ('+c.last.status+')':'')):'No customer record'}}})}
function call(n){n=clean(n||$('num').value);if(!n){err('Enter a phone number first.');return}if(inCall())return;if(!reg()){if(agBusy&&ua&&!tmpReg){tmpReg=true;pendCall=n;err('');try{ua.register()}catch(e){tmpReg=false;pendCall=null;err('PBX connection required')}setTimeout(function(){if(tmpReg){tmpReg=false;pendCall=null;err('PBX connection required')}},8000);return}err('PBX connection required');return}err('');
 begin(n,'out');setPhase('connecting');tab('act');
 mic().then(function(){return post('/admin/call-center/log',{direction:'out',phone:n,extension:cfg.ext})}).then(function(j){logId=j.id;lastLog=j.id;
  var s=ua.call(target(n),{mediaConstraints:{audio:true,video:false},pcConfig:iceCfg(),rtcOfferConstraints:{offerToReceiveAudio:true}});wire(s)}).catch(function(e){ses=null;if(e&&(e.name==='NotAllowedError'||e.name==='NotFoundError'||e.name==='NoMedia'||e.name==='SecurityError'))micErr();else{console.warn('dialer call',e);setPhase('failed','PBX Error');err('PBX Error. Please try again.')}})}
function pbxId(){try{return ses._request&&ses._request.call_id||''}catch(e){return ''}}
function wire(s){ses=s;
 s.on('progress',function(){if(phase==='connecting')setPhase('ringing')});
 var up=function(){if(phase==='connected')return;setPhase('connected');startTimer();if(logId)post('/admin/call-center/log/'+logId+'/connected',{pbx_call_id:pbxId()});recInd();err('')};
 s.on('accepted',up);s.on('confirmed',up);
 s.on('ended',function(e){finish('ended',e&&e.cause)});
 s.on('failed',function(e){finish('failed',(e&&e.cause)||'')});
 s.on('hold',function(e){if(e.originator==='local'){held=true;$('bHold').classList.add('on');$('bHold').lastChild.textContent='Resume'}});
 s.on('unhold',function(e){if(e.originator==='local'){held=false;$('bHold').classList.remove('on');$('bHold').lastChild.textContent='Hold'}});
 var pc=s.connection;if(pc)pc.addEventListener('track',function(ev){if(ev.streams&&ev.streams[0])$('aud').srcObject=ev.streams[0];if(phase==='connecting'&&dir==='out')setPhase('ringing')})}
function friendly(c){c=String(c||'');
 if(/busy|rejected|decline/i.test(c))return 'Call Rejected';
 if(/unavailable|not found|address incomplete|no answer|timeout|expired/i.test(c))return 'Number Unreachable';
 if(/connection|rtp|websocket/i.test(c))return 'Connection Lost';
 if(/authent/i.test(c))return 'Registration Failed';
 return 'PBX Error'}
function finish(kind,cause){if(!ses&&phase!=='connecting'&&phase!=='ringing'&&phase!=='connected')return;
 var wasConn=!!connAt,d=secs(),id=logId,raw=String(cause||''),canceled=/cancel|bye|terminated/i.test(raw);
 clearInterval(tick);stopRing();$('ovInc').classList.remove('on');
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
 setTimeout(function(){if(ses&&inCall())finish('ended','Canceled')},1500)}}
/* ---------- incoming ---------- */
function ringStart(){try{inCtx=inCtx||new (window.AudioContext||window.webkitAudioContext)();if(inCtx.state==='suspended')inCtx.resume();
 var cp=inCtx.createDynamicsCompressor(),mg=inCtx.createGain();mg.gain.value=1;cp.connect(mg);mg.connect(inCtx.destination);
 var seq=[[880,0],[1109,.16],[1319,.32],[1760,.48],[1319,.78],[1760,.94],[2093,1.1],[1760,1.5]];
 var burst=function(){var t=inCtx.currentTime+.02;seq.forEach(function(n){[['triangle',1,.55],['sine',2,.22],['square',.5,.06]].forEach(function(w){var o=inCtx.createOscillator(),g=inCtx.createGain();o.type=w[0];o.frequency.value=n[0]*w[1];var a=t+n[1];g.gain.setValueAtTime(0,a);g.gain.linearRampToValueAtTime(w[2],a+.015);g.gain.exponentialRampToValueAtTime(.0008,a+.42);o.connect(g);g.connect(cp);o.start(a);o.stop(a+.45)})})};
 burst();ring=setInterval(burst,2600);if(navigator.vibrate)navigator.vibrate([400,200,400])}catch(e){}}
function stopRing(){clearInterval(ring);ring=null}
function incoming(s){if(ses||agBusy){try{s.terminate({status_code:486})}catch(e){}return}
 var num=clean((s.remote_identity&&s.remote_identity.uri&&s.remote_identity.uri.user)||'');
 ses=s;begin(num,'in');setPhase('ringing');wire(s);ringStart();
 $('icN').textContent=(s.remote_identity&&s.remote_identity.display_name)||'Unknown Customer';$('icP').textContent=num;$('icI').textContent='';$('ovInc').classList.add('on');tab('act');
 if(emb)parent.postMessage({dfdial:'incoming'},location.origin);
 post('/admin/call-center/log',{direction:'in',phone:num,extension:cfg&&cfg.ext}).then(function(j){logId=j.id;lastLog=j.id});
 if(window.Notification&&Notification.permission==='granted')try{new Notification('Incoming call',{body:num})}catch(e){}}
$('icA').onclick=function(){stopRing();$('ovInc').classList.remove('on');setPhase('connecting');mic().then(function(){ses.answer({mediaConstraints:{audio:true,video:false},pcConfig:iceCfg()})}).catch(function(){micErr();try{ses.terminate({status_code:486})}catch(e){}})};
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
function openSum(c){sum=c;sel={out:c.connected?null:'no_answer',resp:c.connected?null:'No Answer',can:null};
 var i=c.info||{};$('sN').textContent=i.found&&i.name?i.name:'Unknown Customer';$('sP').textContent=c.phone;$('sD').textContent=c.connected?fmtD(c.dur):'Not connected';$('sO').textContent=i.last?i.last.invoice:'—';$('sA').textContent=(cfg&&cfg.staff)||'—';
 $('sR').textContent=!c.connected?'No Recording':(cfg&&cfg.rec?'Recording Processing':'No Recording');
 var ed=$('sEdit');if(i.last){ed.style.display='block';ed.href='/admin/orders/'+i.last.id+'/edit?call='+c.id}else ed.style.display='none';
 $('sNote').value='';$('sErr').textContent='';paintSum();$('ovSum').classList.add('on');if(emb)parent.postMessage({dfdial:'summary'},location.origin)}
function paintSum(){document.querySelectorAll('#ocO button').forEach(function(b){b.classList.toggle('on',b.dataset.v===sel.out)});
 document.querySelectorAll('#ocR .chip').forEach(function(b){b.classList.toggle('on',b.dataset.v===sel.resp)});
 document.querySelectorAll('#ocC .chip').forEach(function(b){b.classList.toggle('on',b.dataset.v===sel.can)});
 $('cwrap').style.display=sel.out==='cancelled'?'':'none'}
document.querySelectorAll('#ocO button').forEach(function(b){b.onclick=function(){sel.out=sel.out===b.dataset.v?null:b.dataset.v;if(sel.out!=='cancelled')sel.can=null;paintSum()}});
document.querySelectorAll('#ocR .chip').forEach(function(b){b.onclick=function(){sel.resp=sel.resp===b.dataset.v?null:b.dataset.v;paintSum()}});
document.querySelectorAll('#ocC .chip').forEach(function(b){b.onclick=function(){sel.can=sel.can===b.dataset.v?null:b.dataset.v;paintSum()}});
function saveSum(next){if(!sum)return;var note=$('sNote').value.trim();
 if(!sel.out&&!sel.resp&&!note){$('sErr').textContent='Select an outcome or write a note first.';return}
 if(sel.out==='cancelled'&&!sel.can){$('sErr').textContent='Select a cancel reason.';return}
 if(sel.out==='cancelled'&&sel.can==='Other'&&!note){$('sErr').textContent='Other কারণ হলে নোট লিখতে হবে।';return}
 var o={outcome:sel.out,response:sel.resp,cancel_reason:sel.out==='cancelled'?sel.can:null,note:note};if(sum.info&&sum.info.last)o.order_id=sum.info.last.id;
 $('sSave').disabled=true;$('sNext').disabled=true;
 post('/admin/call-center/log/'+sum.id+'/summary',o).then(function(j){$('sSave').disabled=false;$('sNext').disabled=false;if(j&&j.ok){$('ovSum').classList.remove('on');sum=null;if(next){$('num').value='';found();tab('pad');$('num').focus()}else{tab('pad');if(emb)parent.postMessage({dfdial:'done'},location.origin)}}else $('sErr').textContent=(j&&j.error)||'Could not save. Try again.'}).catch(function(){$('sSave').disabled=false;$('sNext').disabled=false;$('sErr').textContent='Could not save. Try again.'})}
$('sSave').onclick=function(){saveSum(false)};$('sNext').onclick=function(){saveSum(true)};$('sSkip').onclick=function(){$('ovSum').classList.remove('on');sum=null;tab('pad');if(emb)parent.postMessage({dfdial:'done'},location.origin)};
/* ---------- dial pad: customer found ---------- */
var lastN='',ft=null;
function found(){clearTimeout(ft);ft=setTimeout(function(){var n=($('num').value||'').replace(/\D/g,'');if(n===lastN)return;lastN=n;var f=$('found');if(n.length<6){f.className='fnd';f.innerHTML='';return}
 look(n).then(function(c){if(($('num').value||'').replace(/\D/g,'')!==n)return;if(c.found){f.className='fnd on';f.innerHTML='<b>'+esc(c.name||'Customer')+'</b> · '+esc(c.category||'Regular')+' · '+c.orders+' Orders'+(c.last?'<br>'+esc(c.last.invoice)+' · '+esc(c.last.status):'')+(c.fraud?'<br>'+fr(c.fraud):'')}else if(n.length>=10){f.className='fnd on unk';f.innerHTML='Unknown Customer · <a href="/admin/m/customers/new?phone='+encodeURIComponent(n)+'" target="_blank">Create Customer</a>'}else{f.className='fnd'}})},250)}
$('num').addEventListener('input',found);
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
if(ch)ch.onmessage=function(m){var d=m.data||{};if(d.type==='status'){stApply(d.v,false);return}if(d.type==='ping')ch.postMessage({type:'here'});if(d.type==='dial'){$('num').value=clean(d.n);lastN='';found();tab('pad');window.focus();$('num').focus()}if(d.type==='call'){window.focus();autoDial(d.n,d)}if(d.type==='disconnect')disconnect();if(d.type==='connect')connect();if(d.type==='getstatus')ch.postMessage({type:'status',text:$('bdg').textContent,cls:$('bdg').className.replace('badge ','')})};
window.addEventListener('beforeunload',function(e){if(inCall()){e.preventDefault();e.returnValue=''}});
if(window.Notification&&Notification.permission==='default')try{Notification.requestPermission()}catch(e){}
function chk(){if(inCall()||!cfg)return;getj('/admin/call-center/creds').then(function(c){if(c&&c.ok&&(c.user!==cfg.user||c.pw!==cfg.pw||c.domain!==cfg.domain||c.wss!==cfg.wss)){reconnect()}}).catch(function(){})}
document.addEventListener('visibilitychange',function(){if(!document.hidden)chk()});window.addEventListener('focus',chk);setInterval(chk,60000);
setPhase('idle');ui();
if($('num').value)found();
(function(){var q=new URLSearchParams(location.search);if(q.get('auto')==='1'&&q.get('n'))autoDial(q.get('n'),{o:q.get('o'),cn:q.get('cn')})})();
if($('msg').textContent==='')connect();else badge('Disconnected','b-bad');
})();
</script>
@endverbatim
</body></html>
