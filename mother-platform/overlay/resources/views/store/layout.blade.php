<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>@if(!empty($desc))<meta name="description" content="{{ $desc }}">@endif
<link rel="canonical" href="{{ $canon }}"><meta name="robots" content="{{ !empty($noindex) ? 'noindex,follow' : 'index,follow,max-image-preview:large' }}">
<meta name="theme-color" content="#0B1220"><meta property="og:type" content="{{ isset($p) ? 'product' : 'website' }}"><meta property="og:site_name" content="DutyFree Shop BD"><meta property="og:locale" content="bn_BD">
<meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $desc ?? '' }}"><meta property="og:url" content="{{ $canon }}"><meta property="og:image" content="{{ $ogimg ?? url('/og.png') }}"><meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/icon.png"><link rel="apple-touch-icon" href="/icon.png">
@yield('ld')
<style>
:root{--o:#FF5A00;--n:#0B1220;--bg:#FFFBF8;--bd:#FDC2A5;--mut:#6B7280;--pr:{{ max(3,(int)$s['per_row_mobile']) }};--prd:{{ max(3,(int)$s['per_row_desktop']) }}}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;background:var(--bg);color:var(--n);line-height:1.5;padding-bottom:70px}a{color:inherit;text-decoration:none}
img{-webkit-user-drag:none;user-select:none;-webkit-touch-callout:none}
.w{max-width:1280px;margin:0 auto;padding:0 12px}
.tk{background:var(--n);color:#fff;font-size:12px;overflow:hidden;white-space:nowrap}.tk div{display:inline-block;padding:6px 0;animation:tk 40s linear infinite}.tk span{margin:0 22px}@keyframes tk{from{transform:translateX(0)}to{transform:translateX(-50%)}}
header{background:#fff;border-bottom:1px solid #FFE7D6;position:sticky;top:0;z-index:20}.hd{display:flex;align-items:center;gap:10px;padding:8px 12px;max-width:1280px;margin:0 auto}.hd img{height:34px;width:auto}
.sr{flex:1;display:flex;border:1.5px solid var(--o);border-radius:10px;overflow:hidden;min-width:0}.sr input{flex:1;border:0;padding:9px 10px;font:inherit;font-size:14px;outline:0;min-width:0}.sr button{background:var(--o);color:#fff;border:0;padding:0 14px;font-weight:700}
.hl{font-size:12px;font-weight:700;white-space:nowrap}.hl b{color:var(--o)}@media(max-width:700px){.hl.d{display:none}}
.ch{display:flex;gap:8px;overflow-x:auto;padding:10px 12px;max-width:1280px;margin:0 auto;scrollbar-width:none}.ch a{flex:none;border:1px solid var(--bd);background:#fff;border-radius:20px;padding:6px 14px;font-size:13px;font-weight:600}.ch a.on{background:var(--o);color:#fff;border-color:var(--o)}
h1{font-size:20px;margin:12px 0 4px}h2{font-size:17px;margin:18px 0 10px;display:flex;align-items:center}h2 a{margin-left:auto;font-size:13px;color:var(--o)}
.g{display:grid;grid-template-columns:repeat(var(--pr),minmax(0,1fr));gap:8px}@media(min-width:900px){.g{grid-template-columns:repeat(var(--prd),minmax(0,1fr));gap:12px}}
.cd{background:#fff;border:1px solid #FFE7D6;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;position:relative}.cd .im{aspect-ratio:1;display:grid;place-items:center;background:#fff;padding:6px}.cd .im img{max-width:100%;max-height:100%;object-fit:contain;pointer-events:none}
.cd .t{font-size:12px;font-weight:600;padding:0 7px;height:2.9em;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}.cd .p{padding:3px 7px;font-size:13px}.cd .p b{color:var(--o);font-size:14px}.cd .p s{color:var(--mut);font-size:11px;margin-left:4px}
.cd .bt{margin:auto 6px 6px;background:var(--o);color:#fff;text-align:center;border-radius:8px;padding:6px 4px;font-size:12px;font-weight:700}.bd{position:absolute;top:6px;left:6px;background:var(--o);color:#fff;font-size:10px;font-weight:700;border-radius:6px;padding:2px 6px}.bd.fr{left:auto;right:6px;background:#16A34A}
.lv{background:#fff;border:1px solid #FFE7D6;border-radius:12px;padding:10px 12px;margin:12px 0;font-size:13px;overflow:hidden;white-space:nowrap}.lv b{color:var(--o)}.lv .dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--o);margin-right:6px}
.hero{border-radius:14px;overflow:hidden;margin-top:10px}.hero img{width:100%;height:auto;display:block}
.box{background:#fff;border:1px solid #FFE7D6;border-radius:14px;padding:14px;margin:12px 0}
.btn{display:inline-block;background:var(--o);color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:700;font:inherit;font-weight:700;cursor:pointer;text-align:center}.btn.wa{background:#25D366}.btn.n{background:var(--n)}
input,select,textarea{width:100%;border:1px solid var(--bd);border-radius:10px;padding:10px;font:inherit;background:#fff}label{display:block;font-size:13px;color:var(--mut);margin:8px 0 3px}.err{color:#B91C1C;font-size:13px}
.pg{display:flex;gap:6px;justify-content:center;margin:16px 0;flex-wrap:wrap}.pg a,.pg span{border:1px solid var(--bd);background:#fff;border-radius:8px;padding:6px 11px;font-size:13px}
footer{background:var(--n);color:#CBD5E1;margin-top:24px;padding:20px 0;font-size:13px}footer a{color:#fff;margin-right:12px;display:inline-block;margin-bottom:6px}
.fab{position:fixed;right:12px;bottom:76px;z-index:30;display:flex;flex-direction:column;gap:8px}.fab a{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;box-shadow:0 4px 14px rgba(0,0,0,.18)}
.bar{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid #FFE7D6;display:flex;z-index:25}.bar a{flex:1;text-align:center;padding:9px 0 10px;font-size:11px;font-weight:600}.bar a b{display:block;font-size:17px;color:var(--o)}
</style>
@if($s['gtag_id'])<script async src="https://www.googletagmanager.com/gtag/js?id={{ $s['gtag_id'] }}"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag("js",new Date());gtag("config",@json($s['gtag_id']),{currency:"BDT"});</script>@endif
@if($s['pixel_id'])<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@json($s['pixel_id']));fbq('track','PageView');</script>@endif
</head><body oncontextmenu="if(event.target.tagName==='IMG')return false">
@if($s['ticker'])@php $tk = array_filter(array_map('trim', explode("\n", $s['ticker']))); @endphp<div class="tk"><div>@foreach(array_merge($tk,$tk) as $t)<span>★ {{ $t }}</span>@endforeach</div></div>@endif
<header><div class="hd"><a href="/" aria-label="DutyFree Shop BD"><img src="/logo.webp" alt="DutyFree Shop BD" width="150" height="34"></a>
<form class="sr" action="/search" role="search"><input name="q" value="{{ request('q') }}" placeholder="{{ $s['search_placeholder'] }}" aria-label="Search"><button aria-label="Search">⌕</button></form>
@if($s['show_tracking']==='1')<a class="hl d" href="/track">Order Tracking</a>@endif<a class="hl d" href="tel:{{ $s['call_number'] }}">Call <b>{{ $s['call_number'] }}</b></a></div>
<nav class="ch" aria-label="Categories"><a href="/" class="{{ request()->is('/') ? 'on' : '' }}">সব পণ্য</a>@foreach($cats as $c)<a href="/{{ $c->slug }}/" class="{{ request()->is($c->slug) || (isset($p) && $p->category_id==$c->id) ? 'on' : '' }}">{{ $c->name }}</a>@endforeach</nav></header>
<main class="w">@yield('main')</main>
<footer><div class="w"><p style="margin-bottom:10px">{{ $s['footer_about'] ?: 'DutyFree Shop BD – অরিজিনাল ইমপোর্টেড পণ্য, সারা বাংলাদেশে হোম ডেলিভারি।' }}</p>
<div>@foreach($cats as $c)<a href="/{{ $c->slug }}/">{{ $c->name }}</a>@endforeach</div>
<div style="margin-top:6px"><a href="/track">Order Tracking</a>@foreach(DB::table('pages')->where('active',true)->whereNotNull('body')->get() as $pg)<a href="/{{ $pg->slug }}/">{{ $pg->title }}</a>@endforeach
@foreach(['social_facebook'=>'Facebook','social_youtube'=>'YouTube','social_tiktok'=>'TikTok','social_instagram'=>'Instagram'] as $k=>$l)@if($s[$k])<a href="{{ $s[$k] }}" rel="noopener">{{ $l }}</a>@endif @endforeach</div>
<p style="margin-top:10px">{{ $s['footer_copyright'] }} · Customer care <a href="tel:09613828081">09613828081</a></p></div></footer>
<div class="fab">@if($s['whatsapp_number'])<a href="https://wa.me/{{ $wa }}" style="background:#25D366" aria-label="WhatsApp" rel="noopener">WA</a>@endif @if($s['fab_call']==='1')<a href="tel:{{ $s['call_number'] }}" style="background:var(--n)" aria-label="Call">☎</a>@endif</div>
<nav class="bar"><a href="/"><b>⌂</b>Home</a><a href="/search"><b>⌕</b>Search</a><a href="/track"><b>⎘</b>Track</a><a href="https://wa.me/{{ $wa }}" rel="noopener"><b>✆</b>WhatsApp</a></nav>
</body></html>
