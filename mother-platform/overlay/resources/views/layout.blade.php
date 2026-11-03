<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">@include('favicon_head')<title>@yield('title','Mother Commerce')</title>
<style>
:root{--o:#FF5A00;--n:#0B1220;--bg:#FFFBF8;--bd:#FDC2A5}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:var(--bg);color:var(--n);display:flex;min-height:100vh}
aside{width:240px;background:#0B1220;color:#C7CEDB;padding:16px 12px;display:flex;flex-direction:column;gap:6px;flex-shrink:0;overflow:hidden;height:100vh;max-height:100vh;position:sticky;top:0}
aside .brand{display:flex;gap:10px;align-items:center;color:#fff;font-weight:600;text-decoration:none;padding:4px 6px}aside .brand b{background:var(--o);color:#fff;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px}aside .brand small{display:block;font-weight:400;font-size:12px;color:#9AA4B5}
aside .viewing{background:#161E30;border:1px solid #232D44;border-radius:12px;padding:10px 12px;margin:8px 0}aside .viewing small{display:block;font-size:10px;letter-spacing:.08em;color:#8A94A8}aside .viewing strong{display:block;font-size:13px;color:#fff;margin-top:2px}
aside nav{display:flex;flex-direction:column;gap:2px;flex:1 1 auto;min-height:0;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;scrollbar-width:thin;scrollbar-color:#2A3550 transparent;margin:0 -6px;padding:0 6px}aside>.brand,aside>.viewing,aside>.sbs{flex:none}
aside a.nv,aside details summary{display:flex;align-items:center;gap:12px;color:#C7CEDB;text-decoration:none;padding:10px 12px;border-radius:10px;font-size:14px;cursor:pointer;list-style:none}aside a.nv:hover,aside details summary:hover{background:#161E30;color:#fff}
aside a.nv.on{background:#232B3D;color:#fff}aside a.nv.on svg{color:var(--o)}aside a.nv svg,aside details summary svg{flex-shrink:0;opacity:.9}aside a.nv span,aside details summary span{flex:1}
aside em{font-style:normal;background:var(--o);color:#fff;font-size:11px;font-weight:700;border-radius:20px;padding:1px 8px}
aside .gh{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#7C879B;padding:10px 12px 2px}aside a.sub{display:block;color:#C7CEDB;text-decoration:none;padding:6px 12px 6px 42px;font-size:13px;border-radius:8px}aside a.sub:hover,aside a.sub.on{background:#161E30;color:#fff}
aside .me{flex:none;margin-top:0;border-top:1px solid #1B2438;font-size:12px;padding:10px 6px;color:#C7CEDB}aside .me form{margin-top:8px}
@media(max-width:800px){body{display:block}aside{width:auto;height:auto;max-height:none;overflow:visible;position:static}aside nav{overflow:visible;flex:none}}
main{flex:1;padding:24px;min-width:0}h1{margin:0 0 16px;font-size:22px}
.card{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:16px;margin-bottom:16px}
table{width:100%;border-collapse:collapse;font-size:13px}th{text-align:left;color:#6B7280;font-size:12px;padding:8px 8px 8px 0}td{padding:9px 8px 9px 0;border-top:1px solid #FFE7D6}
.btn,button{background:var(--o);color:#fff;border:0;border-radius:9px;padding:9px 14px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
.btn.g,button.g{background:#fff;color:var(--n);border:1px solid var(--bd)}
input,select,textarea{border:1px solid var(--bd);border-radius:9px;padding:9px 10px;font:inherit;width:100%;background:#fff}
label{font-size:12px;color:#6B7280;display:block;margin:10px 0 4px}
.s-new{--c:#4F46E5;--b:#E0E7FF}.s-incomplete{--c:#6B7280;--b:#F3F4F6}.s-pre-order{--c:#CA8A04;--b:#FEF9C3}.s-assign{--c:#0D9488;--b:#CCFBF1}.s-pending{--c:#EA580C;--b:#FFEDD5}.s-ai-order{--c:#7C3AED;--b:#EDE9FE}.s-approved{--c:#16A34A;--b:#DCFCE7}.s-scheduled{--c:#0284C7;--b:#E0F2FE}.s-wfp{--c:#D97706;--b:#FEF3C7}.s-packaging{--c:#2563EB;--b:#DBEAFE}.s-shipment{--c:#0891B2;--b:#CFFAFE}.s-partial-delivered{--c:#65A30D;--b:#ECFCCB}.s-delivered{--c:#166534;--b:#BBF7D0}.s-late-delivery{--c:#DC2626;--b:#FEE2E2}.s-partial-return-pending{--c:#C2410C;--b:#FFEDD5}.s-partial-return-received{--c:#7C3AED;--b:#EDE9FE}.s-partial-returned{--c:#7E22CE;--b:#F3E8FF}.s-return-pending{--c:#9333EA;--b:#F3E8FF}.s-returned{--c:#6B21A8;--b:#E9D5FF}.s-exchange-pending{--c:#A21CAF;--b:#F5D0FE}.s-exchange-received{--c:#C026D3;--b:#FAE8FF}.s-cancel{--c:#DC2626;--b:#FEE2E2}.chip[class*=' s-']{border:1.5px solid var(--c);color:var(--c);font-weight:700;background:var(--b)}.chip.on[class*=' s-']{background:var(--c);color:#fff}.pill[class*=' s-']{background:var(--b);color:var(--c);border:1px solid var(--c);font-weight:800;padding:3px 10px;border-radius:999px;font-size:12px}.qa{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 10px}.qa form{margin:0}.ab{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:44px;padding:10px 18px;border-radius:12px;border:0;background:var(--c);color:#fff;font-size:14px;font-weight:800;letter-spacing:.2px;cursor:pointer;box-shadow:0 2px 0 rgba(0,0,0,.12)}.ab:hover{filter:brightness(.92)}.ab:active{transform:translateY(1px)}@media(max-width:700px){.qa .ab,.qa form{flex:1 1 46%}.qa form .ab{width:100%}.ab{min-height:50px;font-size:15px}}.chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}.chip{border:1px solid #FED7AA;background:#fff;border-radius:20px;padding:5px 12px;font-size:12px;color:var(--n);text-decoration:none}.chip.on{background:var(--o);color:#fff;border-color:var(--o)}.chip.red{border-color:#DC2626;color:#B91C1C}
.pill{font-size:11px;font-weight:600;border-radius:6px;padding:2px 8px;background:#FFE7D6;color:#C2410C}.pill.red{background:#FEE2E2;color:#B91C1C}.pill.ok{background:#DCFCE7;color:#166534}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}.k b{font-size:24px;display:block}.k span{font-size:12px;color:#6B7280}
.err{color:#B91C1C;font-size:12px}
.ibar{display:flex;gap:6px;margin-top:5px;align-items:center;flex-wrap:wrap}@media(max-width:760px){.ibar{gap:8px}.ib,.ib-call{height:40px!important}.ib{width:40px!important}}
.ib{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:9px;border:1px solid #E5E7EB;background:#fff;color:#374151;text-decoration:none;transition:transform .15s,box-shadow .15s;box-shadow:0 1px 2px rgba(15,23,42,.05);vertical-align:middle}
.ib.sm{width:26px;height:26px;border-radius:8px}
.ib:hover,.ib-call:hover{transform:translateY(-1px);box-shadow:0 5px 12px rgba(15,23,42,.12)}
.ib.wa{color:#16A34A;background:#ECFDF5;border-color:#BBF7D0}.ib.pf{color:#4F46E5;background:#EEF2FF;border-color:#C7D2FE}.ib.hs{color:#C2410C;background:#FFF7ED;border-color:#FED7AA}
.ib-call{display:inline-flex;align-items:center;gap:5px;height:32px;padding:0 14px;border-radius:9px;background:#16A34A;color:#fff!important;font-weight:700;font-size:12px;text-decoration:none;box-shadow:0 2px 6px rgba(22,163,74,.3);transition:transform .15s,box-shadow .15s}
.vd{display:inline-flex;align-items:center;gap:5px}.vbtn{display:inline-flex;align-items:center;gap:5px}
table.ordt{border-collapse:separate;border-spacing:0 8px}
table.ordt>tbody>tr>td{color:#1E293B}table.ordt>tbody>tr>td:not(:first-child),table.ordt>tbody>tr>td:first-child{border-top-color:#FED7AA;border-bottom-color:#FED7AA}table.ordt>tbody>tr>td:last-child{border-right-color:#FED7AA}
table.ordt .pill{font-weight:700;color:color-mix(in srgb,var(--c,#C2410C) 78%,#000);border:1px solid color-mix(in srgb,var(--c,#C2410C) 30%,transparent)}
table.ordt small{font-weight:500}table.ordt .ib{color:#1E293B}table.ordt .ib.ch{color:#1D4ED8;background:#DBEAFE;border-color:#93C5FD}table.ordt .ib.wa{color:#15803D;background:#DCFCE7;border-color:#86EFAC}table.ordt .ib.pf{color:#3730A3;background:#E0E7FF;border-color:#A5B4FC}table.ordt .ib.hs{color:#9A3412;background:#FFEDD5;border-color:#FDBA74}.ib.cp{color:#475569;background:#F1F5F9;border-color:#CBD5E1}
table.ordt .ib-call{background:#16A34A;box-shadow:0 2px 6px rgba(22,163,74,.3)}
table.ordt .vbtn{border:1px solid #FF5A00;color:#0F172A;background:#fff}table.ordt .vbtn:hover{background:#FFF7ED}table.ordt .vbtn svg{stroke:#0F172A}
/* shared order Action column (all order views) */
th.action-column,td.action-column{min-width:150px;text-align:right;white-space:nowrap}td.incomplete-cell{min-width:160px}
.order-action-group{display:flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:wrap}
.order-action-group.incomplete-actions{flex-direction:column;align-items:flex-end;flex-wrap:nowrap}.order-action-group.incomplete-actions-secondary{flex-direction:row;flex-wrap:nowrap;gap:6px}
.order-action-group .oa{display:inline-flex;align-items:center;justify-content:center;gap:5px;min-height:30px;padding:0 9px;margin:0;border-radius:7px;font-size:12px;font-weight:600;line-height:1;white-space:nowrap;cursor:pointer;text-decoration:none;box-sizing:border-box;background:#fff;color:#0F172A;border:1px solid #CFD4DA}
.order-action-group .oa:hover{background:#F3F4F6;filter:none}
.order-action-group .oa-primary{background:#FF5A00;border-color:#FF5A00;color:#fff;padding:0 12px}.order-action-group .oa-primary:hover{background:#E04E00;border-color:#E04E00}
.order-action-group .oa-view{border-color:#FF5A00}.order-action-group .oa-view:hover{background:#FFF7ED}
.order-action-group .oa-rec{color:#16803C;border-color:#9ED9B1}.order-action-group .oa-rec:hover{background:#F0FDF4}
.order-action-group .oa-arc{color:#5F6670;border-color:#CFD4DA}.order-action-group .oa-ghost{color:#374151}
.order-action-group .oa-more{width:30px;padding:0;font-size:18px;line-height:1}.order-action-group .oa-more:hover{border-color:#FF5A00;background:#FFF7ED}
@media(max-width:1100px){.order-action-group .oa-edit{display:none}}
@media(max-width:760px){.order-action-group .oa{min-height:40px}.order-action-group .oa-more{width:40px}.order-action-group.incomplete-actions{align-items:stretch}}
table.ordt>tbody>tr>td{border-top:1px solid #EFE6DF;border-bottom:1px solid #EFE6DF;background:var(--rb,#fff);transition:background .15s}
table.ordt>tbody>tr>td:first-child{border-left:4px solid var(--ra,#FDBA8C);border-radius:12px 0 0 12px;padding-left:10px}
table.ordt>tbody>tr>td:last-child{border-right:1px solid #EFE6DF;border-radius:0 12px 12px 0;padding-right:10px}
table.ordt>tbody>tr:nth-child(4n+1){--rb:#FFFAF6;--ra:#FDBA8C}table.ordt>tbody>tr:nth-child(4n+2){--rb:#F5F8FF;--ra:#93C5FD}
table.ordt>tbody>tr:nth-child(4n+3){--rb:#F4FBF6;--ra:#86EFAC}table.ordt>tbody>tr:nth-child(4n){--rb:#FAF6FF;--ra:#C4B5FD}
table.ordt>tbody>tr[style*="FFFBEB"]{--rb:#FFFBEB;--ra:#F59E0B}
table.ordt>tbody>tr:hover>td{background:#FFFFFF;box-shadow:0 2px 0 rgba(0,0,0,0)}
table.ordt>tbody>tr:hover{filter:drop-shadow(0 4px 10px rgba(15,23,42,.08))}
/* ===== Mother admin design standard (matches Dashboard Overview) ===== */
main{padding:16px 18px}
main h1{font-size:17px;font-weight:700;margin:0 0 10px;line-height:1.25;letter-spacing:-.005em}main h2{font-size:14px;font-weight:700;margin:0 0 8px;line-height:1.3}main h3{font-size:13px;font-weight:700;margin:0 0 6px}
main .card{border:1px solid #E6EAF0;border-radius:12px;padding:12px 14px;margin-bottom:12px;box-shadow:0 1px 2px rgba(11,18,32,.04)}
main table:not(.ordt){font-size:12.5px}main table:not(.ordt) th{font-size:11px;font-weight:700;color:#6B7280;background:#F8FAFC;padding:7px 8px;white-space:nowrap}main table:not(.ordt) td{padding:7px 8px;border-top:1px solid #EEF1F5;vertical-align:middle}
main table:not(.ordt) tbody tr:hover td{background:#FFFBF8}
main .btn,main button{padding:7px 12px;min-height:32px;font-size:12px;font-weight:600;border-radius:9px;line-height:1.2;transition:background .15s,border-color .15s,filter .15s}
main .btn:hover,main button:hover{filter:brightness(.94)}main .btn.g:hover,main button.g:hover{background:#FFF1E8;border-color:#FDC2A5;filter:none}
main input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]),main select{height:34px;padding:0 10px;border:1px solid #E6EAF0;border-radius:9px;font-size:12.5px;background:#fff}
main textarea{padding:8px 10px;border:1px solid #E6EAF0;border-radius:9px;font-size:12.5px;line-height:1.45}
main input:focus,main select:focus,main textarea:focus{outline:0;border-color:#FF5A00;box-shadow:0 0 0 3px rgba(255,90,0,.12)}
main label{font-size:11.5px;font-weight:600;margin:8px 0 3px}
main .grid{gap:10px}main .k b{font-size:20px}main .k span{font-size:11.5px}
main .pill{font-size:10.5px;font-weight:700;border-radius:6px;padding:2px 8px}
@media(max-width:800px){main{padding:12px}main table:not(.ordt){display:block;overflow-x:auto;max-width:100%}main .btn,main button{min-height:34px}}
/* v1.303 global hover border contrast: same 1px width, only colour darkens; no layout shift */
:root{--border-grid:#CED4DA;--border-hover:#9CA3AF}
main input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]),main select,main textarea,main .chip,main .pre button,main .rf{transition:border-color .15s ease,background-color .15s ease,box-shadow .15s ease}
main input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]):not(:focus),main select:not(:focus),main textarea:not(:focus),main .pre button,main .rf{border-color:var(--border-grid)}
main input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]):not(:focus):hover,main select:not(:focus):hover,main textarea:not(:focus):hover{border-color:var(--border-hover)}
main .btn:hover,main button:hover,main a.btn:hover{box-shadow:inset 0 0 0 1px rgba(15,23,42,.32)}
main .pre button:hover,main .rf:hover{border-color:var(--border-hover)!important;background-color:#EEF1F4}
main .btn:focus-visible,main button:focus-visible,main select:focus-visible,main a.chip:focus-visible{outline:2px solid #FF5A00;outline-offset:1px}
nav[role=navigation] svg,.pagination svg{width:16px!important;height:16px!important;max-width:16px!important;max-height:16px!important;display:inline-block;vertical-align:middle}nav[role=navigation] a,nav[role=navigation] span[aria-current] span{padding:2px 8px}
</style></head><body>
@auth @include('sidebar') @include('admin_grid') @include('admin_theme') @endauth
<main>@yield('body')</main>
@auth @include('chat_widget') @include('softphone_fab') @endauth
@yield('js')
@auth<script>function dfCopyNo(a,n){n=String(n||"").trim();var ok=function(){var o=a.innerHTML;a.innerHTML='<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="#15803D" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';a.title="Copied "+n;setTimeout(function(){a.innerHTML=o;a.title="Copy mobile number"},1200)};try{if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(n).then(ok,function(){fb()})}else fb()}catch(e){fb()}function fb(){var x=document.createElement("textarea");x.value=n;x.style.cssText="position:fixed;opacity:0";document.body.appendChild(x);x.select();try{document.execCommand("copy");ok()}catch(e){}x.remove()}return false}</script><script src="/js/df-export.js?v=4" defer></script>@endauth
@auth<script src="/js/report-grid.js?v=1" defer></script>@endauth
@auth<script src="/js/live-search.js?v=2" defer></script>@endauth

@if(auth()->check())
<div id="dfsm" style="display:none;position:fixed;inset:0;background:rgba(10,20,50,.55);z-index:99999;align-items:center;justify-content:center;padding:14px"><div class="card" style="max-width:460px;width:100%;margin:0;max-height:92vh;overflow:auto">
<b style="font-size:16px">Send SMS <span id="dfsmw" style="font-weight:400;font-size:13px;color:#6B7280"></span></b>
<label>Template</label><select id="dfsmt"><option value="">— write your own —</option></select>
<label>Message</label><textarea id="dfsmb" rows="4" maxlength="1000"></textarea><div style="font-size:11px;color:#6B7280">Use {customer_name}. <span id="dfsmc"></span></div>
<div style="display:flex;gap:8px;margin-top:10px"><button type="button" id="dfsms">Send</button><button type="button" class="g" id="dfsmx">Close</button></div><div id="dfsmr" style="font-size:13px;margin-top:8px"></div>
<details style="margin-top:8px"><summary style="cursor:pointer;font-size:12px;font-weight:600">SMS history</summary><div id="dfsmh" style="font-size:12px;margin-top:6px"></div></details></div></div>
<script>(function(){var m=document.getElementById('dfsm'),ph='',nm='',loaded=false,T=[];
function tk(){var e=document.querySelector('meta[name=csrf-token]');return e?e.content:''}
function post(u,d){return fetch(u,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':tk(),Accept:'application/json'},body:JSON.stringify(d)}).then(function(r){return r.json()})}
window.dfSms=function(p,n){ph=p;nm=n||'';document.getElementById('dfsmw').textContent='to '+(nm?nm+' · ':'')+p;document.getElementById('dfsmr').textContent='';document.getElementById('dfsmb').value='';m.style.display='flex';
if(!loaded){loaded=true;fetch('/admin/sms-marketing/send',{headers:{Accept:'text/html'}}).then(function(r){return r.text()}).then(function(h){var d=new DOMParser().parseFromString(h,'text/html'),s=document.getElementById('dfsmt');[].forEach.call(d.querySelectorAll('#tpl option[data-b]'),function(o){var x=document.createElement('option');x.textContent=o.textContent;x.dataset.b=o.dataset.b;s.appendChild(x)})}).catch(function(){})}
var H=document.getElementById('dfsmh');H.textContent='Loading…';fetch('/admin/sms-marketing/history?phone='+encodeURIComponent(p),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(a){H.innerHTML=a.length?a.map(function(x){return '<div style="border-top:1px solid #eee;padding:4px 0"><b>'+x.at+'</b> · '+x.status+'<br>'+x.body.replace(/</g,'&lt;')+'</div>'}).join(''):'No SMS yet.'});return true};
document.getElementById('dfsmt').onchange=function(){var o=this.options[this.selectedIndex];if(o.dataset.b)document.getElementById('dfsmb').value=o.dataset.b};
document.getElementById('dfsmx').onclick=function(){m.style.display='none'};
document.getElementById('dfsms').onclick=function(){var b=document.getElementById('dfsmb').value.trim(),r=document.getElementById('dfsmr');if(!b){r.textContent='Write a message first.';return}
r.textContent='Sending…';post('/admin/sms-marketing/quick',{phone:ph,name:nm,body:b}).then(function(j){r.style.color=j.ok?'#166534':'#B91C1C';r.textContent=j.ok?('SMS '+j.status+'.'):(j.msg||'Failed')}).catch(function(){r.style.color='#B91C1C';r.textContent='Could not send.'})};})();</script>
@endif
@include('popup_close')@include('phone_js')<script>document.addEventListener('error',function(e){var t=e.target;if(t&&t.tagName==='IMG'&&!t.dataset.fb&&t.getAttribute('src')!=='/product-placeholder'){t.dataset.fb='1';t.removeAttribute('srcset');t.src='/product-placeholder'}},true)</script></body></html>
