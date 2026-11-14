<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}">@include('favicon_head')<title>@yield('title','Mother Commerce')</title>
<link rel="stylesheet" href="/s/adm-layout-a.css?v=9d53e679"><link rel="preload" href="/fonts/inter-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin><link rel="preload" href="/fonts/inter-latin-500-normal.woff2" as="font" type="font/woff2" crossorigin><link rel="preload" href="/fonts/inter-latin-600-normal.woff2" as="font" type="font/woff2" crossorigin><link rel="preload" href="/fonts/inter-latin-700-normal.woff2" as="font" type="font/woff2" crossorigin><link rel="preload" href="/fonts/hind-siliguri-bengali-400-normal.woff2" as="font" type="font/woff2" crossorigin><link rel="preload" href="/fonts/hind-siliguri-bengali-600-normal.woff2" as="font" type="font/woff2" crossorigin>@include('typography')<style>/* smooth page-to-page navigation: old page stays on screen until the new one is ready; sidebar stays put (no white flash). Browsers without support just navigate normally. */@view-transition{navigation:auto}aside.sbx{view-transition-name:dfsbx}::view-transition-group(dfsbx){animation:none}::view-transition-old(dfsbx),::view-transition-new(dfsbx){animation:none;mix-blend-mode:normal}::view-transition-old(root),::view-transition-new(root){animation-duration:.14s}@media (prefers-reduced-motion:reduce){::view-transition-group(*),::view-transition-old(*),::view-transition-new(*){animation:none!important}}</style></head><body>{!! \App\Support\PrintBrand::layoutPrint() !!}
@php($__emb = request()->isMethod('GET') && request()->boolean('embed'))
@auth
@if(!$__emb)
@include('sidebar')
@endif
@include('admin_grid')
@include('admin_theme')
@include('admin_table')
@include('admin_type')
@if(!$__emb)@include('admin_mobile')@include('admin_bell')@include('staff_idle_js')@include('staff_wait_notice')@endif
<script>if(window.dfSbGo)dfSbGo()</script>
@endauth
@if($__emb)
<style>aside{display:none!important}body{display:block}main{padding:10px 12px!important;width:100%}main h1{font-size:15px!important}</style>
<script>document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a[href]');if(!a||a.target||a.hasAttribute('download'))return;var h=a.getAttribute('href');if(!h||h.charAt(0)==='#'||/^(javascript|tel|mailto):/i.test(h))return;try{var u=new URL(h,location.href);if(u.origin!==location.origin||u.searchParams.has('embed'))return;u.searchParams.set('embed','1');a.setAttribute('href',u.pathname+u.search+u.hash)}catch(x){}},true)</script>
@endif
<main>@yield('body')</main>
@auth @include('report_export_bar') @endauth
@auth
@if($__emb)
<script>window.DF_EMBED=1</script>
@include('chat_widget')
@else
@include('chat_widget')
@include('softphone_fab')
@endif
@endauth
@yield('js')
@auth<script>function dfCopyNo(a,n){n=String(n||"").trim();var ok=function(){var o=a.innerHTML;a.innerHTML='<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="#166534" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';a.title="Copied "+n;setTimeout(function(){a.innerHTML=o;a.title="Copy mobile number"},1200)};try{if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(n).then(ok,function(){fb()})}else fb()}catch(e){fb()}function fb(){var x=document.createElement("textarea");x.value=n;x.style.cssText="position:fixed;opacity:0";document.body.appendChild(x);x.select();try{document.execCommand("copy");ok()}catch(e){}x.remove()}return false}</script><script src="/js/df-export.js?v=6" defer></script>@endauth
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
@include('btn_system')@include('popup_close')@include('keep_position')@include('soft_reload')@include('phone_js')@include('cancel_reason_js')<script>document.addEventListener('error',function(e){var t=e.target;if(t&&t.tagName==='IMG'&&!t.dataset.fb&&t.getAttribute('src')!=='/product-placeholder'){t.dataset.fb='1';t.removeAttribute('srcset');t.src='/product-placeholder'}},true)</script></body></html>
