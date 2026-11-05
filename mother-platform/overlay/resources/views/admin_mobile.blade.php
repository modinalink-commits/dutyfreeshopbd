{{-- Admin panel, phone version: top bar + slide-in menu + bottom tabs, roomy touch targets, no sideways page scroll. Phones/tablets up to 900px only; desktop is unchanged. --}}
<style>
.mtb,.mbn,.mbd{display:none}
@media(max-width:900px){
html,body{max-width:100%;overflow-x:hidden}
body{display:block!important;padding-top:calc(54px + env(safe-area-inset-top));padding-bottom:calc(70px + env(safe-area-inset-bottom));-webkit-text-size-adjust:100%;-webkit-tap-highlight-color:transparent}
body.mnav-open{overflow:hidden}
/* menu becomes a slide-in drawer */
aside,aside.sbx{position:fixed!important;left:0;top:0;bottom:0;width:min(86vw,330px)!important;min-width:0!important;max-width:none!important;height:100vh!important;height:100dvh!important;max-height:none!important;overflow:hidden!important;z-index:1300;transform:translateX(-104%);transition:transform .26s cubic-bezier(.2,.8,.2,1)!important;box-shadow:none;padding-top:calc(10px + env(safe-area-inset-top))}
body.mnav-open aside{transform:none;box-shadow:12px 0 40px rgba(0,0,0,.35)}
aside nav{overflow-y:auto!important;flex:1 1 auto!important;-webkit-overflow-scrolling:touch}
aside a.nv,aside details summary{padding:13px 12px;font-size:15px}aside a.sub,aside details.rpg>summary{padding:11px 12px 11px 42px;font-size:14px}
aside .sbs input{font-size:16px;height:42px}
.mbd{display:block;position:fixed;inset:0;background:rgba(11,18,32,.55);z-index:1290;opacity:0;pointer-events:none;transition:opacity .25s}
body.mnav-open .mbd{opacity:1;pointer-events:auto}
/* top bar */
.mtb{display:flex;align-items:center;gap:10px;position:fixed;left:0;right:0;top:0;z-index:1200;height:calc(54px + env(safe-area-inset-top));padding:env(safe-area-inset-top) 12px 0;background:#fff;border-bottom:1px solid #F3E4D8;box-shadow:0 2px 10px rgba(11,18,32,.05)}
.mtb button{width:42px;height:42px;padding:0;border-radius:12px;background:#FFF1E8;color:#C2410C;border:0;display:flex;align-items:center;justify-content:center;flex:none}
.mtb img{height:28px;width:auto;flex:none}
.mtb b{flex:1;min-width:0;font-size:14.5px;color:#0B1220;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:right}
/* bottom tabs */
.mbn{display:flex;position:fixed;left:0;right:0;bottom:0;z-index:1200;height:calc(64px + env(safe-area-inset-bottom));padding-bottom:env(safe-area-inset-bottom);background:#fff;border-top:1px solid #F3E4D8;box-shadow:0 -6px 18px rgba(11,18,32,.06)}
.mbn a,.mbn button{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;font-size:11.5px;font-weight:700;color:#475569;text-decoration:none;background:none;border:0;border-radius:0;padding:0;min-height:0;position:relative}
.mbn svg{width:24px;height:24px}.mbn .on{color:#FF5A00}.mbn .on:before{content:"";position:absolute;top:0;left:30%;right:30%;height:3px;border-radius:0 0 3px 3px;background:#FF5A00}
/* content */
main{padding:12px!important;overflow-x:auto;max-width:100vw}
main h1{font-size:18px!important;margin-bottom:10px}
.card{padding:12px!important;border-radius:12px}
.grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px!important}
main form[style*="display:flex"],main .card[style*="display:flex"]{flex-wrap:wrap}
main input,main select,main textarea{font-size:16px!important;min-height:42px}
main input[type=checkbox],main input[type=radio]{min-height:0;width:auto}
main .btn,main button{min-height:42px;font-size:14px}
main table{font-size:13px}
main [style*="grid-template-columns:1fr 1fr"],main [style*="grid-template-columns:2fr 1fr"],main [style*="grid-template-columns:1fr 2fr"]{grid-template-columns:minmax(0,1fr)!important}
.fb,#dfmc-fab{bottom:calc(78px + env(safe-area-inset-bottom))!important}
}
@media(max-width:420px){.grid{grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important}}
</style>
<div class="mbd" id="mbd" aria-hidden="true"></div>
<header class="mtb" id="mtb"><button type="button" id="mmenu" aria-label="Menu"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button><img src="/dutyfree-bd-logo.png" alt="DutyFree BD"><b id="mtt"></b></header>
<nav class="mbn" id="mbn" aria-label="Quick menu">
<a href="/admin" data-p="/admin"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>Home</a>
<a href="/admin/orders" data-p="/admin/orders"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>Orders</a>
<a href="/admin/chat" data-p="/admin/chat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>Messages</a>
<a href="/admin/m/customers" data-p="/admin/m/customers"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/></svg>Customers</a>
<button type="button" id="mbm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>Menu</button></nav>
<script>(function(){var b=document.body,$=function(i){return document.getElementById(i)};
function open(o){b.classList.toggle('mnav-open',o)}
$('mmenu').onclick=$('mbm').onclick=function(){open(!b.classList.contains('mnav-open'))};$('mbd').onclick=function(){open(false)};
document.addEventListener('keydown',function(e){if(e.key==='Escape')open(false)});
document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('aside a[href]');if(a&&!a.target)open(false)});
var h=document.querySelector('main h1');var t=$('mtt');if(t)t.textContent=h?h.textContent.trim():(document.title||'').replace(/\s*[·|-].*$/,'');
var p=location.pathname.replace(/\/$/,'')||'/admin',best=null;document.querySelectorAll('#mbn a[data-p]').forEach(function(a){var d=a.getAttribute('data-p');if(p===d||(d!=='/admin'&&p.indexOf(d)===0)){if(!best||d.length>best.getAttribute('data-p').length)best=a}});if(best)best.classList.add('on');
addEventListener('resize',function(){if(innerWidth>900)open(false)})})();</script>
