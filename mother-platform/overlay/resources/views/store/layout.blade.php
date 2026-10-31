<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
@if(!empty($desc))
<meta name="description" content="{{ $desc }}">
@endif
<link rel="canonical" href="{{ $canon }}"><meta name="robots" content="{{ !empty($noindex) ? 'noindex,follow' : 'index,follow,max-image-preview:large' }}">
<meta name="theme-color" content="#0B1220"><meta property="og:type" content="{{ isset($p) ? 'product' : 'website' }}"><meta property="og:site_name" content="DutyFree Shop BD"><meta property="og:locale" content="bn_BD">
<meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $desc ?? '' }}"><meta property="og:url" content="{{ $canon }}"><meta property="og:image" content="{{ $ogimg ?? url('/og.png') }}"><meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/icon.png"><link rel="apple-touch-icon" href="/icon.png">
@yield('ld')
@php
  $logo = $s['logo_url'] ?: '/logo.webp';
  $tk = array_values(array_filter(array_map('trim', explode("\n", (string)$s['ticker']))));
  $ico = [
    'search' => '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
    'heart' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 3 4.5 6.8 4.5c2.1 0 3.6 1.2 5.2 3 1.6-1.8 3.1-3 5.2-3 3.8 0 5.9 3.9 4.4 7.3C19.5 16.4 12 21 12 21z"/></svg>',
    'user' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>',
    'phone' => '<svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1z"/></svg>',
    'wa' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-3.3-.8a12 12 0 0 1-4.6-4.3c-.4-.6-1-1.8-1-3.1 0-1.3.7-2 1-2.3.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .5l-.4.6-.4.4c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l2 1c.3.1.4.2.5.3 0 .2 0 .7-.2 1.2z"/></svg>',
    'msg' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C6.5 2 2 6.1 2 11.3c0 2.9 1.4 5.5 3.7 7.2V22l3.4-1.9c.9.3 1.9.4 2.9.4 5.5 0 10-4.1 10-9.2S17.5 2 12 2zm1 12.4-2.6-2.7-5 2.7 5.5-5.8 2.6 2.7 5-2.7-5.5 5.8z"/></svg>',
  ];
  $soc = ['social_facebook'=>'f','social_youtube'=>'▶','social_tiktok'=>'♪','social_instagram'=>'◎'];
  $pays = ['CASH ON DELIVERY'];
  if ($s['co_card']==='1') array_push($pays, 'VISA', 'Mastercard', 'AMEX', 'DBBL Nexus');
  if ($s['co_wallets']==='1') array_push($pays, 'bKash', 'Nagad', 'Rocket');
@endphp
<style>
:root{--o:#FF5A00;--n:#0B1220;--bg:#FFFBF8;--bd:#FDC2A5;--pk:#FED7AA;--mut:#6B7280;--red:#DC2626;--grn:#16A34A}
*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:var(--bg);color:#000;line-height:1.35}a{color:inherit;text-decoration:none}input,textarea,select,button{font:inherit}button{cursor:pointer}
img{-webkit-user-drag:none;user-select:none;-webkit-touch-callout:none}
.w{max-width:1440px;margin:0 auto;padding-left:80px;padding-right:80px}
.ts{background:#F5F5F5;height:26px;display:flex;align-items:center;gap:20px;font-size:12.5px}.ts .ad{color:var(--o);font-weight:600;white-space:nowrap}.ts .tx{flex-grow:1;text-align:center;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pill-t{font-size:12px;font-weight:600;color:var(--o);border:1.25px solid var(--pk);border-radius:50px;padding:2px 12px;background:#fff;white-space:nowrap}
.hdw{background:#fff}.hd{height:74px;display:flex;align-items:center;gap:40px}.hd .lg img{height:40px;width:auto;display:block}.mb{display:none;border:0;background:none;font-size:20px;padding:0;line-height:1}
.sr{flex-grow:1;max-width:570px;display:flex;align-items:center;background:#F5F5F5;border:1.25px solid #E0E0E0;border-radius:100px;height:36px;padding:0 6px 0 16px}.sr input{flex-grow:1;border:0;background:transparent;font-size:14px;min-width:0;outline:0}.sr button{width:26px;height:26px;border-radius:50%;background:var(--o);color:#fff;border:0;display:flex;align-items:center;justify-content:center;padding:0;flex:none}
.rt{margin-left:auto;display:flex;align-items:center;gap:12px}.call{border:1.25px solid var(--pk);border-radius:8px;padding:6px 12px;display:flex;align-items:center;gap:10px;background:var(--bg)}.call .ic{width:26px;height:26px;border-radius:7px;background:var(--o);color:#fff;display:flex;align-items:center;justify-content:center}.call .t{display:flex;flex-direction:column;line-height:1.2}.call .t span{font-size:9px;font-weight:600}.call .t b{font-size:11px;color:var(--o)}
.ib{width:34px;height:34px;border:1.25px solid var(--pk);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--o);position:relative}.ib i{position:absolute;top:-6px;right:-6px;background:var(--o);color:#fff;font-style:normal;font-size:9px;font-weight:700;border-radius:10px;min-width:15px;height:15px;line-height:15px;text-align:center;padding:0 3px;display:none}
.nv{background:var(--n);height:32px;display:flex;align-items:center;justify-content:center;gap:24px;color:#fff;font-size:11.5px;font-weight:600;letter-spacing:.01em;position:relative;z-index:30}.nv>div{position:relative;height:100%;display:flex;align-items:center}.nv>div>a{text-transform:uppercase;white-space:nowrap}.nv>div>a.on{color:#FDC2A5}
.dd{display:none;position:absolute;top:32px;left:-12px;background:#fff;color:#0B1220;border:1.25px solid var(--bd);border-radius:0 0 8px 8px;min-width:220px;padding:6px 0;box-shadow:0 10px 24px rgba(11,18,32,.12)}.dd a{display:block;padding:6px 14px;font-weight:500;font-size:12px;text-transform:uppercase}.dd a:hover{background:#FFF1E8;color:var(--o)}.nv>div:hover .dd{display:block}
.drw{position:fixed;inset:0;z-index:60;display:none}.drw.op{display:block}.drw .sh{position:absolute;inset:0;background:rgba(11,18,32,.5)}.drw .pn{position:absolute;left:0;top:0;bottom:0;width:82%;max-width:320px;background:#fff;overflow:auto;padding:12px 0}.drw .pn a{display:block;padding:10px 16px;font-size:13px;font-weight:600;text-transform:uppercase;border-bottom:1px solid #FFF1E8}.drw .pn a.sb{font-weight:500;font-size:12px;padding-left:28px;text-transform:none;color:#374151}
h1,h2,h3{margin:0}
/* product card */
.gr{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}
.pc{border:1.25px solid var(--bd);border-radius:10px;background:#fff;padding:5px;display:flex;flex-direction:column;gap:8px;position:relative;min-width:0}
.pc .bo{position:absolute;left:8px;top:8px;background:var(--o);color:#fff;font-size:10px;font-weight:700;border-radius:20px;padding:2px 8px;z-index:1}.pc .bf{position:absolute;right:8px;top:8px;background:var(--grn);color:#fff;font-size:9px;font-weight:700;border-radius:3px;padding:2px 6px;z-index:1}
.pc .ph{height:205px;background:#fff;border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden}.pc .ph img{max-width:100%;max-height:100%;object-fit:contain;pointer-events:none;display:block}.pc.s170 .ph{height:170px}
.pc .nm{font-size:12px;font-weight:600;padding:0 2px;line-height:1.3;height:2.6em;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.pc .pr{font-size:12px;padding:0 2px}.pc .pr b{font-weight:600}.pc .pr s{color:var(--red);font-weight:500;margin-left:4px}.pc .pr br{display:none}
.pc .on{background:var(--n);color:#fff;font-size:11px;font-weight:700;text-align:center;border-radius:4px;padding:8px 0;margin-top:auto}
/* side cart and floating buttons */
.sc{position:fixed;right:0;top:420px;width:54px;background:#fff;border:1px solid #E5E7EB;border-right:0;border-radius:6px 0 0 6px;text-align:center;font-size:10px;padding:8px 0 0;line-height:1.5;z-index:40;overflow:hidden}.sc div{background:var(--n);color:#fff;margin-top:4px;padding:1px 0}
.fb{position:fixed;right:20px;bottom:24px;display:flex;flex-direction:column;gap:6px;z-index:40}.fb a,.fb button{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;border:0;padding:0;box-shadow:0 3px 10px rgba(0,0,0,.18)}.fb .x{width:32px;height:32px;background:#EF4444;font-weight:700;font-size:16px}.fb.cl a{display:none}
.fb .ai{background:var(--o);font-size:9px;font-weight:700;display:none}
/* footers */
.fg{background:#E5E5E5;padding-top:36px}.fg .cols{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:20px;font-size:12px;line-height:2.1}.fg .cols img{height:34px;width:auto;display:block}.fg .ab{line-height:1.6;margin-top:10px;max-width:440px}.fg .cols b.h{font-size:13px;border-bottom:1.5px solid #000}.fg .cols a{display:block}
.fg .so{display:flex;gap:6px;margin-top:10px}.fg .so a,.fg .so span{width:26px;height:26px;border:1px solid var(--pk);background:#fff;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700}
.fg .py{margin-top:18px;border:1.5px dashed #4F46E5;padding:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}.fg .py .pb{font-size:15px;color:var(--mut);margin-right:8px}.fg .py .pb b{color:#4F46E5}.fg .py span.c{background:#fff;border-radius:4px;padding:7px 12px;font-size:11px;font-weight:700;color:#374151}
.fg .bt{display:flex;justify-content:space-between;font-size:10px;padding:12px 0}
.fn{margin-top:44px;background:var(--n);color:#CBD5E1;padding-top:36px;padding-bottom:36px;display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;font-size:13px;line-height:1.8}.fn b{color:#fff}.fn a{display:block}.fn .lb{background:#fff;border-radius:8px;padding:8px 12px;display:inline-block}.fn .lb img{height:34px;width:auto;display:block}
.only-m{display:none}
.pgn{display:flex;justify-content:center;gap:6px;padding-top:30px;font-size:12px;font-weight:600}.pgn a,.pgn span{border:1px solid var(--bd);border-radius:4px;padding:6px 11px;background:#fff}.pgn .cur{background:var(--n);color:#fff;border-color:var(--n)}
@media (max-width:1200px){.w{padding-left:24px;padding-right:24px}.gr{grid-template-columns:repeat(5,minmax(0,1fr))}.hd{gap:20px}}
@media (max-width:980px){.gr{grid-template-columns:repeat(4,minmax(0,1fr))}.call{display:none}}
@media (max-width:760px){
  .w{padding-left:10px;padding-right:10px}
  .only-d{display:none!important}.only-m{display:block}
  .ts{height:22px;justify-content:space-between;font-size:10px}.ts .tx{display:none}.ts .ad{font-weight:700}.pill-t{font-size:10px;border-width:1px;border-radius:20px;padding:0 8px}
  .hd{height:auto;flex-wrap:wrap;gap:0;padding-top:0;padding-bottom:8px}.mb{display:block;order:1;margin-right:10px;height:46px}.hd .lg{order:2;height:46px;display:flex;align-items:center}.hd .lg img{height:26px}.rt{order:3;gap:8px}.call{display:none}
  .ib{width:28px;height:28px;border-radius:7px}.ib svg{width:13px;height:13px}
  .sr{order:4;flex-basis:100%;max-width:none;height:32px;padding:0 5px 0 14px}.sr input{font-size:12px}.sr button{width:24px;height:24px}
  .nv{height:30px;justify-content:flex-start;gap:16px;padding:0 10px;overflow-x:auto;white-space:nowrap;font-size:10.5px;scrollbar-width:none}.nv::-webkit-scrollbar{display:none}.dd{display:none!important}
  .gr{grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}
  .pc{border-radius:8px;padding:3px;gap:4px}.pc .bo{left:5px;top:5px;font-size:8px;padding:1px 5px}.pc .bf{right:5px;top:5px;font-size:7px;padding:1px 4px}
  .pc .ph,.pc.s170 .ph{height:92px;border-radius:5px}.pc .nm{font-size:9.5px;line-height:1.25;height:24px}.pc .pr{font-size:9.5px;line-height:1.3}.pc .pr b{font-weight:700}.pc .pr br{display:inline}.pc .pr s{font-size:8.5px;margin-left:0}
  .pc .on{font-size:9px;border-radius:3px;padding:5px 0}
  .sc{top:300px;width:46px;font-size:9px;padding-top:5px}.sc div{margin-top:3px}
  .fb{right:8px;bottom:16px;gap:5px}.fb a,.fb button{width:26px;height:26px}.fb a svg{width:13px;height:13px}.fb .x{display:none}.fb .ai{display:flex}
  .fg{padding:20px 14px 12px;margin-top:20px;font-size:11px}.fg .cols{grid-template-columns:1fr 1fr;gap:10px;font-size:11px;line-height:1.9}.fg .cols>div:first-child{grid-column:1/-1}.fg .cols img{height:26px}.fg .ab{margin:6px 0 0;line-height:1.5}.fg .cols b.h{font-size:11px;border:0}.fg .fo{display:none}
  .fg .py{margin-top:10px;padding:8px;gap:5px}.fg .py .pb{display:none}.fg .py span.c{padding:3px 7px;border-radius:3px;font-size:9px}.fg .bt{padding:10px 0 0;font-size:9px}
  .fn{display:none}
}
</style>
@if($s['gtag_id'])
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $s['gtag_id'] }}"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag("js",new Date());gtag("config",@json($s['gtag_id']),{currency:"BDT"});</script>
@endif
@if($s['pixel_id'])
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@json($s['pixel_id']));fbq('track','PageView');</script>
@endif
<script>
var DF={g:function(k,d){try{return JSON.parse(localStorage.getItem(k))||d}catch(e){return d}},p:function(k,v){try{localStorage.setItem(k,JSON.stringify(v))}catch(e){}},
cart:function(){return DF.g('dfc',{})},add:function(id,q,price,set){var c=DF.cart();id=String(id);q=parseInt(q)||1;c[id]={q:Math.min(50,set?q:((c[id]?c[id].q:0)+q)),p:+price||(c[id]?c[id].p:0)};DF.p('dfc',c);DF.tab();if(window.fbq)fbq('track','AddToCart',{currency:'BDT',value:(+price||0)*q});},
wish:function(){return DF.g('dfw',[])},tw:function(id){var w=DF.wish();id=String(id);var i=w.indexOf(id);if(i<0)w.push(id);else w.splice(i,1);DF.p('dfw',w);DF.tab();return i<0},
tab:function(){var c=DF.cart(),n=0,t=0;for(var k in c){n+=c[k].q;t+=c[k].q*c[k].p}var a=document.getElementById('scT'),b=document.getElementById('scN');if(a){a.textContent=Math.round(t).toLocaleString('en-US')+' TK';b.textContent=n+' items'}var w=DF.wish().length;document.querySelectorAll('.wn').forEach(function(e){e.textContent=w;e.style.display=w?'block':'none'})},
cd:function(){var now=new Date(),d=new Date(now.getTime()+6*36e5),end=Date.UTC(d.getUTCFullYear(),d.getUTCMonth(),d.getUTCDate()+1)-6*36e5,r=Math.max(0,Math.floor((end-now.getTime())/1e3)),f=function(x){return(x<10?'0':'')+x},h=f(Math.floor(r/3600)),m=f(Math.floor(r%3600/60)),s=f(r%60);document.querySelectorAll('[data-cd]').forEach(function(e){var k=e.getAttribute('data-cd');e.textContent=k==='h'?h:k==='m'?m:k==='s'?s:h+':'+m+':'+s})}};
</script>
</head><body oncontextmenu="if(event.target.tagName==='IMG')return false">
<div class="ts w"><span class="ad">{{ $s['site_address'] }}</span><span class="tx">{{ implode(' · ', $tk) }}</span>@if($s['show_tracking']==='1')<a class="pill-t" href="/track">ORDER TRACKING</a>@endif</div>
<div class="hdw"><div class="hd w"><button class="mb" type="button" aria-label="Menu" onclick="document.getElementById('drw').classList.add('op')">☰</button><a class="lg" href="/" aria-label="DutyFree Shop BD"><img src="{{ $logo }}" alt="DutyFree Shop BD" width="176" height="40"></a>
<form class="sr" action="/search" role="search"><input name="q" value="{{ request('q') }}" placeholder="Search products" aria-label="Search"><button aria-label="Search">{!! $ico['search'] !!}</button></form>
<div class="rt"><a class="call" href="tel:{{ $s['call_number'] }}"><span class="ic">{!! $ico['phone'] !!}</span><span class="t"><span>CALL US NOW:</span><b>{{ $s['call_number'] }}</b></span></a>@if($s['show_wishlist']==='1')<a class="ib" href="/wishlist" aria-label="Wishlist">{!! $ico['heart'] !!}<i class="wn"></i></a><a class="ib" href="/track" aria-label="My orders">{!! $ico['user'] !!}</a>@endif</div></div></div>
<nav class="nv" aria-label="Categories">@foreach($cats as $c)
<div><a href="/{{ $c->slug }}/" class="{{ request()->is($c->slug) || (isset($p) && $p->category_id==$c->id) ? 'on' : '' }}">{{ $c->name }} <span class="only-d" style="display:inline">⌄</span></a>@if(!empty($subs[$c->id]))<div class="dd">@foreach(array_slice($subs[$c->id], 0, 14) as $sb)<a href="/{{ $c->slug }}/?sub={{ rawurlencode($sb->sub) }}">{{ $sb->sub }}</a>@endforeach</div>@endif</div>
@endforeach</nav>
<div class="drw" id="drw"><div class="sh" onclick="this.parentNode.classList.remove('op')"></div><div class="pn"><a href="/">Home</a>@foreach($cats as $c)<a href="/{{ $c->slug }}/">{{ $c->name }}</a>@foreach(array_slice($subs[$c->id] ?? [], 0, 8) as $sb)<a class="sb" href="/{{ $c->slug }}/?sub={{ rawurlencode($sb->sub) }}">{{ $sb->sub }}</a>@endforeach @endforeach<a href="/track">Order Tracking</a><a href="/wishlist">Wishlist</a></div></div>
<main>@yield('main')</main>
@if(!empty($home))
<footer class="fn w only-d"><div><div class="lb"><img src="{{ $logo }}" alt="DutyFree Shop BD" width="150" height="34" loading="lazy"></div><br>{{ $s['footer_about'] ?: 'Imported and duty-free products delivered across the country.' }}<br>{{ $s['site_address'] }} · <a href="tel:{{ $s['call_number'] }}" style="display:inline">{{ $s['call_number'] }}</a></div>
<div><b>Shop</b><a href="#new">New arrivals</a><a href="#top">Top selling</a><a href="#flash">Offers</a></div>
<div><b>Help</b><a href="/track">Order tracking</a><a href="/delivery-policy/">Delivery</a><a href="/return-policy/">Returns</a></div>
<div><b>Company</b><a href="/about-us/">About</a><a href="/contact-us/">Contact</a><a href="/terms/">Terms</a></div></footer>
@endif
<footer class="fg {{ !empty($home) ? 'only-m' : '' }}"><div class="w" style="padding-left:inherit;padding-right:inherit"><div class="cols">
<div><img src="{{ $logo }}" alt="DutyFree Shop BD" width="150" height="34" loading="lazy"><div class="ab">{{ $s['footer_about'] ?: 'Your trusted source for authentic products at the best prices. 100% original. Cash on delivery available.' }}</div></div>
<div><b class="h">Quick Links</b><a href="/track">Order Tracking</a><a href="/about-us/">About Us</a><a href="/contact-us/">Contact Us</a><a href="/wishlist">Wishlist</a></div>
<div><b class="h">Information</b><a href="/delivery-policy/">Delivery policy</a><a href="/privacy-policy/">Privacy Policy</a><a href="/return-policy/">Return Policy</a><a href="/blog/">Blog</a></div>
<div class="fo"><b class="h">Follow Us</b><div class="so">@foreach($soc as $k => $l)
@if($s[$k])<a href="{{ $s[$k] }}" rel="noopener" aria-label="{{ ucfirst(str_replace('social_','',$k)) }}">{{ $l }}</a>@else<span>{{ $l }}</span>@endif
@endforeach<a href="https://wa.me/{{ $wa }}" rel="noopener" aria-label="WhatsApp">✆</a></div></div></div>
<div class="py"><span class="pb">We accept <b>{{ count($pays) > 1 ? 'payment gateway' : 'cash on delivery' }}</b></span>@foreach($pays as $py)<span class="c">{{ $py }}</span>@endforeach</div>
<div class="bt"><span>All rights reserved</span><span>{{ $s['footer_copyright'] }} · Customer care {{ $s['call_number'] }}</span></div></div></footer>
@if($s['fab_side_cart']==='1')<a class="sc" href="/checkout" aria-label="Cart"><b id="scT">0 TK</b><div id="scN">0 items</div></a>@endif
<div class="fb" id="fb">@if($s['fab_ai_chat']==='1')<a class="ai" href="https://wa.me/{{ $wa }}" rel="noopener">AI</a>@endif
@if($s['fab_messenger']==='1' && $s['pp_messenger'])<a href="{{ $s['pp_messenger'] }}" rel="noopener" style="background:#2563EB" aria-label="Messenger">{!! $ico['msg'] !!}</a>@endif
@if($s['whatsapp_number'])<a href="https://wa.me/{{ $wa }}" rel="noopener" style="background:#16A34A" aria-label="WhatsApp">{!! $ico['wa'] !!}</a>@endif
@if($s['fab_call']==='1')<a href="tel:{{ $s['call_number'] }}" style="background:#22C55E" aria-label="Call">{!! $ico['phone'] !!}</a>@endif
<button class="x" type="button" aria-label="Hide" onclick="this.parentNode.classList.toggle('cl');this.textContent=this.parentNode.classList.contains('cl')?'+':'×'">×</button></div>
@if($s['mobile_bottom_nav']==='1')
<style>.bn{display:none}@media(max-width:760px){body{padding-bottom:58px}.bn{display:flex;position:fixed;left:0;right:0;bottom:0;z-index:50;background:#fff;border-top:1.25px solid var(--bd);height:54px}.bn a,.bn button{flex:1;border:0;background:none;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;font-size:10px;font-weight:600;color:var(--n);position:relative}.bn i{font-style:normal;font-size:17px;line-height:1}.bn a.on{color:var(--o)}.bn b{position:absolute;top:4px;left:56%;background:var(--o);color:#fff;font-size:9px;border-radius:10px;min-width:15px;height:15px;line-height:15px;text-align:center;display:none}.fb{bottom:70px}.sc{display:none}}</style>
<nav class="bn" aria-label="Quick menu"><a href="/" class="{{ request()->is('/') ? 'on' : '' }}"><i>⌂</i>Home</a><button type="button" onclick="document.getElementById('drw').classList.add('op')"><i>☰</i>Categories</button><button type="button" onclick="var i=document.querySelector('.sr input');window.scrollTo(0,0);i&&i.focus()"><i>⌕</i>Search</button><a href="/checkout"><i>🛒</i>Cart<b id="bnC"></b></a><a href="/track"><i>👤</i>Account</a></nav>
<script>(function(){var c=DF.cart(),n=0;for(var k in c)n+=c[k].q;var b=document.getElementById('bnC');if(b&&n){b.textContent=n;b.style.display='block'}})();</script>
@endif
@if($s['live_suggest']==='1')
<style>.sg{position:absolute;left:0;right:0;top:100%;margin-top:6px;background:#fff;border:1.25px solid var(--bd);border-radius:12px;box-shadow:0 12px 28px rgba(11,18,32,.14);z-index:70;overflow:hidden;display:none}.sg a{display:flex;align-items:center;gap:10px;padding:8px 12px;font-size:12.5px;color:var(--n)}.sg a:hover,.sg a.hv{background:#FFF1E8}.sg img{width:38px;height:38px;object-fit:contain;border-radius:6px;background:#fff;flex:none}.sg em{font-style:normal;font-size:10px;font-weight:700;color:var(--o);margin-left:auto;white-space:nowrap}.sg .tg2{font-size:9px;font-weight:700;color:#fff;background:var(--n);border-radius:4px;padding:1px 6px}.sr{position:relative}</style>
<script>(function(){var f=document.querySelector('form.sr');if(!f)return;var i=f.querySelector('input'),box=document.createElement('div'),tm,last='';box.className='sg';f.appendChild(box);i.setAttribute('autocomplete','off');
function esc(x){return String(x).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
i.addEventListener('input',function(){clearTimeout(tm);var q=i.value.trim();if(q.length<2){box.style.display='none';return}tm=setTimeout(function(){if(q===last)return;last=q;fetch('/search-suggest?q='+encodeURIComponent(q)).then(function(r){return r.json()}).then(function(d){if(i.value.trim()!==q)return;if(!d.length){box.style.display='none';return}box.innerHTML=d.map(function(x){return '<a href="'+esc(x.u)+'">'+(x.t==='p'?(x.i?'<img src="'+esc(x.i)+'" alt="" loading="lazy">':'')+'<span>'+esc(x.n)+'</span><em>'+esc(x.x||'')+'</em>':'<span class="tg2">'+(x.t==='c'?'CATEGORY':'BRAND')+'</span><span>'+esc(x.n)+'</span>')+'</a>'}).join('');box.style.display='block'}).catch(function(){})},180)});
document.addEventListener('click',function(e){if(!f.contains(e.target))box.style.display='none'});i.addEventListener('keydown',function(e){if(e.key==='Escape')box.style.display='none'})})();</script>
@endif
<script>DF.tab();DF.cd();setInterval(DF.cd,1000);</script>
@yield('js')
</body></html>
