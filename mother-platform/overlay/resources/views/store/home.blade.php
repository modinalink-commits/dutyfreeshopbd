@extends('store.layout')
@section('ld')<script type="application/ld+json">{!! json_encode([['@context'=>'https://schema.org','@type'=>'OnlineStore','name'=>'DutyFree Shop BD','url'=>url('/').'/','logo'=>url('/icon.png'),'telephone'=>'+880'.ltrim($s['call_number'],'0'),'areaServed'=>'BD'],['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'DutyFree Shop BD','url'=>url('/').'/','inLanguage'=>'bn-BD','potentialAction'=>['@type'=>'SearchAction','target'=>url('/search').'?q={q}','query-input'=>'required name=q']]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) !!}</script>@endsection
@section('main')
@php
  $ini = fn($n) => collect(preg_split('/[\s&\/-]+/', $n))->filter()->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(3)->implode('');
  $flash = $deals->take(6); $plus = $deals->slice(6, 6);
  $hsub = str_replace('{max}', (string)$maxOff, $s['hero_sub']);
@endphp
<style>
.hero{height:555px;background:linear-gradient(120deg,#0B1220 0%,#1F2A44 55%,#FF5A00 140%);display:flex;align-items:center;padding:0 140px;color:#fff;position:relative;padding-left:max(140px,calc((100% - 1440px) / 2 + 140px))}
.hero .in{display:flex;flex-direction:column;gap:18px;max-width:640px}.hero .k{font-size:13px;letter-spacing:.14em;font-weight:600;color:#FDC2A5}.hero h1{font-size:60px;font-weight:700;line-height:1.05}.hero .sb{font-size:20px;color:#E5E7EB}.hero .bt{align-self:flex-start;background:var(--o);color:#fff;font-weight:700;font-size:16px;border-radius:10px;padding:14px 30px}
.hero-i{max-width:1440px;margin:0 auto;display:block}.hero-i img{width:100%;height:auto;display:block}
.hm{display:none}
.fs{border:1.25px solid var(--bd);border-radius:12px;background:#fff;overflow:hidden}.fs .hd2{background:linear-gradient(90deg,#DC2626,#FF5A00);color:#fff;padding:10px 16px;display:flex;align-items:center;gap:14px}.fs .hd2 b.t{font-size:18px;letter-spacing:.02em}.fs .hd2 .e{font-size:11px;opacity:.9}
.tm{display:flex;gap:4px;font-weight:700;font-size:14px}.tm span.b{background:var(--n);border-radius:4px;padding:3px 7px}
.fs .tb{margin-left:auto;display:flex;gap:6px}.fs .tb button,.fs .tb a{font-size:11px;font-weight:700;border-radius:20px;padding:4px 12px;border:1px solid #fff;background:transparent;color:#fff}.fs .tb button.on{background:#fff;color:var(--red)}.fs .tb a{border:0;padding:4px 6px}
.fs .gr{padding:12px}.fs .pc{gap:6px}.fs .pc .bo{background:var(--red)}.fs .pc .ph{height:150px}.fs .pc .pr{font-size:13px}.fs .pc .pr b{color:var(--red);font-weight:700}.fs .pc .pr s{color:var(--mut);text-decoration:line-through;font-size:11px}
.bar{height:6px;background:#FEE2E2;border-radius:3px}.bar i{display:block;height:6px;background:var(--red);border-radius:3px}.sold{font-size:9px;color:var(--mut);margin-top:2px}.fs .pc .on{border-radius:4px;padding:7px 0}
.fs .mtb{display:none}
.lvh{display:flex;flex-direction:column;gap:6px}.chip{align-self:flex-start;display:inline-flex;align-items:center;gap:6px;background:#DCFCE7;color:#14594A;font-size:11px;font-weight:700;letter-spacing:.06em;border-radius:20px;padding:4px 12px}.dot{width:7px;height:7px;border-radius:50%;background:#16A34A;display:inline-block}
.lvh h2{font-size:22px;font-weight:700;color:var(--n)}.lvh .su{font-size:13px;color:var(--mut)}
.lg2{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:11px}.lo{display:flex;flex-direction:column;gap:6px;min-width:0}.lo .tg{align-self:flex-start;display:inline-flex;align-items:center;gap:5px;background:#F0FDF4;color:#14594A;font-size:11px;font-weight:600;border-radius:20px;padding:3px 10px}.lo .tg .dot{width:6px;height:6px}
.lo .bx{border:1.25px solid var(--bd);border-radius:10px;background:#fff;padding:8px;display:flex;flex-direction:column;gap:6px}.lo .im{height:110px;border-radius:8px;background:#F5F5F5;display:flex;align-items:center;justify-content:center;overflow:hidden}.lo .im img{max-width:100%;max-height:100%;object-fit:contain}
.lo .wh{font-size:11px;color:#14594A;font-weight:600}.lo .nm{font-size:12px;font-weight:600;color:var(--n);line-height:1.3;height:32px;overflow:hidden}.lo .rw{display:flex;align-items:center;justify-content:space-between}.lo .rw b{font-size:14px;color:var(--o)}.lo .rw span{font-size:11px;font-weight:600;background:#FFF1E8;color:#C2410C;border-radius:6px;padding:2px 7px}.lo .dl{font-size:10.5px;color:var(--grn);font-weight:600}
.cts{display:flex;flex-direction:column;align-items:center;gap:16px}.cts h2{font-size:18px;font-weight:600}.ctl{display:flex;gap:11px;width:100%;flex-wrap:wrap}.ct{width:144px;height:104px;border:1.25px solid var(--bd);border-radius:10px;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px}.ct .ci{width:52px;height:52px;border-radius:50%;background:var(--n);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;overflow:hidden}.ct .ci img{width:100%;height:100%;object-fit:cover}.ct .cn{font-size:11px;font-weight:600;text-transform:uppercase;text-align:center;padding:0 4px}.ct .cn .m{display:none}
.st{display:flex;flex-direction:column;gap:16px}.st h2{font-size:20px;font-weight:600;text-align:center}
.ofr{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0}.of{height:330px;display:flex;flex-direction:column;justify-content:center;gap:10px;padding:0 40px;color:#fff;position:relative;overflow:hidden}.of .k{font-size:12px;font-weight:700;letter-spacing:.12em;color:#FDE68A}.of .t{font-size:34px;font-weight:700;line-height:1.15}.of .s{font-size:15px;color:#F3F4F6}.of .b{display:flex;gap:10px;margin-top:6px}.of .b a{font-weight:700;font-size:13px;padding:8px 14px;border-radius:6px}
.cs{display:flex;flex-direction:column;gap:14px}.cs .hh{display:flex;align-items:center;gap:14px}.cs .hh .t{font-size:18px;font-weight:600;flex-shrink:0;text-transform:uppercase}.cs .hh .sp{flex-grow:1}.cs .hh .tb{font-size:10px;font-weight:500;text-decoration:underline;color:#374151;text-transform:uppercase;white-space:nowrap}.cs .hh .va{background:var(--n);color:#fff;font-size:10px;font-weight:600;padding:4px 8px;white-space:nowrap}
.cs .mt,.cs .mv{display:none}
@media (max-width:1200px){.hero{padding:0 60px}.hero h1{font-size:48px}.lg2{grid-template-columns:repeat(3,minmax(0,1fr))}.cs .hh .tb:nth-of-type(n+4){display:none}}
@media (max-width:760px){
  .hero{display:none}.hero-i{display:none}
  .hm{display:flex;height:140px;background:linear-gradient(120deg,#7C2D12,#FF5A00);color:#fff;flex-direction:column;justify-content:center;padding:0 16px}.hm b{font-size:17px;line-height:1.2}.hm span{font-size:10px;margin-top:6px}.hm.im{height:auto;padding:0;background:none}.hm.im img{width:100%;height:auto;display:block}
  .sec-fs{padding:8px 8px 0!important}.fs{border-radius:10px}.fs .hd2{padding:7px 8px;gap:6px}.fs .hd2 b.t{font-size:12px}.fs .hd2 .e,.fs .tb button{display:none}.fs .tb{gap:0}.fs .tb a{font-size:9px;padding:0}
  .tm{gap:2px;font-size:10px}.tm span.b{border-radius:3px;padding:2px 4px}
  .fs .mtb{display:flex;gap:6px;padding:6px 6px 0}.fs .mtb button{font-size:9px;font-weight:700;border-radius:20px;padding:3px 10px;border:1px solid var(--red);background:#fff;color:var(--red)}.fs .mtb button.on{background:var(--red);color:#fff}
  .fs .gr{padding:6px;gap:5px}.fs .pc{border-radius:8px;padding:3px;gap:3px}.fs .pc:nth-child(n+4){display:none}.fs .pc .bo{left:4px;top:4px;font-size:8px;padding:1px 5px}.fs .pc .bo i{display:none}.fs .pc .ph{height:80px}.fs .pc .nm{font-size:9px;height:22px}.fs .pc .pr{font-size:9.5px}.fs .pc .pr s,.fs .pc .pr br,.fs .pc .sold,.fs .pc .on{display:none}.bar,.bar i{height:4px}
  .sec-lv{padding:10px 8px 0!important}.chip{font-size:9px;padding:3px 9px;gap:5px;letter-spacing:0}.chip .dot{width:6px;height:6px}.lvh{gap:0}.lvh h2{font-size:14px;margin:4px 0 6px}.lvh .su{display:none}
  .lg2{grid-template-columns:repeat(3,minmax(0,1fr));gap:5px}.lo:nth-child(n+4){display:none}.lo{gap:0}.lo .tg{font-size:8px;padding:2px 6px;margin-bottom:3px}.lo .tg .dot{display:none}.lo .bx{border-radius:8px;padding:3px;gap:3px}.lo .im{height:56px;border-radius:6px}.lo .wh{font-size:8px}.lo .nm{font-size:9px;line-height:1.2;height:22px}.lo .rw b{font-size:10px}.lo .rw span,.lo .dl{display:none}
  .sec-ct{padding:0 8px!important}.cts{gap:0}.cts h2{font-size:13px;margin:10px 0 6px}.ctl{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}.ct{width:auto;height:auto;border-radius:8px;padding:6px 2px;gap:4px}.ct .ci{width:36px;height:36px;background:#14261F}.ct .cn{font-size:8px}.ct .cn .d{display:none}.ct .cn .m{display:inline}
  .sec-st{padding:0 8px!important}.st{gap:0}.st h2{font-size:13px;margin:14px 0 4px}.st .gr .pc:nth-child(n+7){display:none}
  .sec-top,.sec-of{display:none}
  .sec-cs{padding:0 8px!important}.cs{gap:0}.cs .hh{display:none}.cs .mt{display:block;text-align:center;margin:12px 0 6px}.cs .mt span{background:#14594A;color:#fff;font-size:11px;font-weight:700;padding:5px 16px;border-radius:2px;text-transform:uppercase}.cs .gr .pc:nth-child(n+7){display:none}
  .cs .mv{display:block;text-align:center;margin:12px 0 0}.cs .mv a{background:var(--o);color:#fff;font-size:11px;font-weight:700;padding:7px 22px;border-radius:20px;display:inline-block}
}

.pm{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding-top:14px}.pm a{display:flex;gap:10px;align-items:center;background:#fff;border:1.25px solid var(--bd);border-radius:10px;padding:10px 14px}.pm i{font-style:normal;width:34px;height:34px;border-radius:50%;background:var(--n);color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;flex:none}.pm b{display:block;font-size:13px;color:var(--n)}.pm span{font-size:11px;color:var(--mut)}
.fst{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.fst a{background:#fff;border:1.25px solid var(--bd);border-radius:12px;overflow:hidden;display:flex;flex-direction:column}.fst .cv{height:64px;background:linear-gradient(120deg,var(--n),#1F2A44 60%,var(--o))}.fst .bd{padding:0 14px 14px;margin-top:-22px;display:flex;flex-direction:column;gap:4px}.fst .lg{width:44px;height:44px;border-radius:12px;background:var(--o);color:#fff;border:3px solid #fff;display:flex;align-items:center;justify-content:center;font-weight:700}.fst b{font-size:14px;color:var(--n)}.fst .vf{font-size:10px;font-weight:700;color:#14594A;background:#DCFCE7;border-radius:20px;padding:1px 8px;align-self:flex-start}.fst .tp{font-size:11px;color:var(--mut)}.fst .vs{margin-top:6px;background:var(--n);color:#fff;font-size:11px;font-weight:700;border-radius:6px;text-align:center;padding:6px 0}
.td{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr);background:#fff;border:1.25px solid var(--bd);border-radius:14px;overflow:hidden}.td .im{background:#fff;display:flex;align-items:center;justify-content:center;min-height:260px}.td .im img{max-width:80%;max-height:240px;object-fit:contain}.td .tx{padding:26px;display:flex;flex-direction:column;gap:10px;justify-content:center}.td .k{font-size:12px;font-weight:700;letter-spacing:.1em;color:var(--o)}.td h3{font-size:22px;color:var(--n);line-height:1.25}.td .pp{font-size:26px;font-weight:700;color:var(--o)}.td .pp s{font-size:15px;color:var(--mut);font-weight:500;margin-left:8px}.td .pp em{font-style:normal;font-size:12px;background:var(--red);color:#fff;border-radius:20px;padding:2px 10px;margin-left:8px;vertical-align:middle}.td .bt{align-self:flex-start;background:var(--o);color:#fff;font-weight:700;border-radius:10px;padding:12px 28px}
.tr{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.tr div{background:#fff;border:1.25px solid var(--bd);border-radius:10px;padding:14px 8px;text-align:center;font-size:12px;font-weight:600;color:var(--n)}.tr i{display:block;font-style:normal;font-size:22px;margin-bottom:4px}
.sl{background:linear-gradient(120deg,var(--n),#1F2A44);border-radius:14px;color:#fff;padding:30px 36px;display:flex;align-items:center;gap:20px;flex-wrap:wrap}.sl div{flex:1;min-width:220px}.sl b{font-size:22px}.sl span{display:block;font-size:13px;color:#CBD5E1;margin-top:4px}.sl a{background:var(--o);color:#fff;font-weight:700;border-radius:10px;padding:12px 26px}
.ap{background:#fff;border:1.25px solid var(--bd);border-radius:14px;padding:22px 28px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}.ap div{flex:1;min-width:200px}.ap b{font-size:18px;color:var(--n)}.ap a{background:var(--n);color:#fff;font-weight:700;font-size:12px;border-radius:8px;padding:10px 18px}
.fq{max-width:860px;margin:0 auto;display:flex;flex-direction:column;gap:8px}.fq details{background:#fff;border:1.25px solid var(--bd);border-radius:10px;padding:12px 16px}.fq summary{font-weight:600;font-size:14px;color:var(--n);cursor:pointer}.fq p{margin:8px 0 0;font-size:13px;color:#374151;line-height:1.55}
@media (max-width:760px){.pm{grid-template-columns:repeat(3,minmax(0,1fr));gap:5px;padding-top:8px}.pm a{padding:6px;gap:5px;flex-direction:column;text-align:center;border-radius:8px}.pm i{width:24px;height:24px;font-size:11px}.pm b{font-size:9px}.pm span{display:none}
.fst{display:flex;overflow-x:auto;gap:8px;scrollbar-width:none}.fst a{flex:0 0 150px}.fst::-webkit-scrollbar{display:none}.td{grid-template-columns:1fr}.td .im{min-height:150px}.td .tx{padding:14px}.td h3{font-size:15px}.td .pp{font-size:19px}
.tr{grid-template-columns:repeat(3,minmax(0,1fr));gap:5px}.tr div{padding:8px 3px;font-size:9px;border-radius:8px}.tr i{font-size:16px}.sl{padding:16px}.sl b{font-size:15px}.ap{padding:14px}}
</style>
@if($s['home_hero']==='1')
@if($slides->count())
<a class="hero-i" href="{{ $slides[0]->link ?: '#flash' }}"><img src="{{ $slides[0]->image }}" alt="DutyFree Shop BD offer" width="1440" height="555" fetchpriority="high"></a>
<a class="hm im" href="{{ $slides[0]->link ?: '#flash' }}"><img src="{{ $slides[0]->image }}" alt="DutyFree Shop BD offer" width="390" height="140" loading="lazy"></a>
@else
<div class="hero"><div class="in"><span class="k">{{ $s['hero_kicker'] }} <span data-cd="all">00:00:00</span></span><h1>{!! nl2br(e(str_replace(', ', ",\n", $s['hero_title']))) !!}</h1><span class="sb">{{ $hsub }}</span><a class="bt" href="#flash">{{ $s['hero_button'] }}</a></div></div>
<div class="hm"><b>{{ $s['hero_mobile_title'] }}</b><span>{{ $s['hero_mobile_sub'] }}</span></div>
@endif
@endif

@if($s['home_promos']==='1')<div class="w pm"><a href="/delivery-policy/"><i>🚚</i><span><b>Free Delivery</b><span>On selected orders</span></span></a><a href="/about-us/"><i>✔</i><span><b>100% Original</b><span>Imported and authentic</span></span></a><a href="#flash"><i>%</i><span><b>Special Offers</b><span>Up to {{ $maxOff }}% off</span></span></a></div>@endif
@if($s['home_flash']==='1' && $flash->count())
<div class="w sec-fs" id="flash" style="padding-top:22px"><div class="fs">
<div class="hd2"><b class="t">⚡ FLASH SALE</b><span class="e">ENDS IN</span><div class="tm"><span class="b" data-cd="h">00</span><span>:</span><span class="b" data-cd="m">00</span><span>:</span><span class="b" data-cd="s">00</span></div>
<div class="tb"><button type="button" class="on" data-ft="f">Flash Sale</button>@if($plus->count())<button type="button" data-ft="p">Plus Sale</button>@endif<a href="/search?deals=1">View All ›</a></div></div>
<div class="mtb"><button type="button" class="on" data-ft="f">Flash Sale</button>@if($plus->count())<button type="button" data-ft="p">Plus Sale</button>@endif</div>
@foreach(['f'=>$flash, 'p'=>$plus] as $fk => $list)
@if($list->count())
<div class="gr" data-fl="{{ $fk }}" @if($fk==='p') style="display:none" @endif>@foreach($list as $q)
@php $off = \App\Http\Controllers\StoreController::off($q); $u = \App\Http\Controllers\StoreController::url($q); $w = $q->sold > 0 ? max(8, min(100, round($q->sold / max(1, $q->sold + max(0, (int)$q->stock)) * 100))) : max(8, min(90, 100 - (int)$q->stock)); @endphp
<div class="pc"><span class="bo">{{ $off }}%<i> OFF</i></span><a class="ph" href="{{ $u }}" style="background:#fff"><img src="{{ $q->image ?: '/icon.png' }}" alt="{{ $q->name }}" width="150" height="150" loading="lazy" decoding="async" draggable="false"></a>
<a class="nm" href="{{ $u }}">{{ $q->name }}</a><div class="pr"><b>{{ number_format($q->price) }} TK</b> <s>{{ number_format($q->compare_price) }} TK</s></div>
<div style="padding:0 2px"><div class="bar"><i style="width:{{ $w }}%"></i></div><div class="sold">{{ $q->sold > 0 ? $q->sold.' sold' : ((int)$q->stock > 0 ? (int)$q->stock.' in stock' : 'Limited stock') }}</div></div>
<a class="on" href="/checkout?add={{ $q->id }}&amp;p={{ (float)$q->price }}" rel="nofollow">ORDER NOW</a></div>
@endforeach</div>
@endif
@endforeach
</div></div>
@endif

@if($live->count())
<div class="w sec-lv" style="padding-top:24px;display:flex;flex-direction:column;gap:12px"><div class="lvh"><span class="chip"><span class="dot"></span>LIVE ORDERS</span><h2>Recently ordered</h2><span class="su">Bought by shoppers across Bangladesh, updated every minute</span></div>
<div class="lg2">@foreach($live as $l)
@php $lu = $l->pid ? \App\Http\Controllers\StoreController::url((object)['id'=>$l->pid,'ext_id'=>$l->ext_id,'name'=>$l->pname]) : null; @endphp
<div class="lo"><span class="tg"><span class="dot"></span>{{ \Carbon\Carbon::parse($l->created_at)->diffForHumans(null, true) }} ago</span><a class="bx" @if($lu) href="{{ $lu }}" @endif>
<div class="im">@if($l->image)<img src="{{ $l->image }}" alt="{{ $l->name }}" width="110" height="110" loading="lazy" draggable="false">@endif</div>
<span class="wh">Recent order</span><span class="nm">{{ $l->name }}</span>
<div class="rw"><b>{{ number_format($l->price) }} TK</b><span>x{{ $l->qty }}</span></div><span class="dl">{{ (float)$l->shipping > 0 ? 'Delivery ৳'.number_format($l->shipping) : 'FREE delivery' }}</span></a></div>
@endforeach</div></div>
@endif

@if($s['home_categories']==='1')
<div class="w sec-ct" style="padding-top:24px"><div class="cts"><h2>Categories</h2><div class="ctl">@foreach($cats as $c)
<a class="ct" href="/{{ $c->slug }}/"><span class="ci">@if(!empty($c->image))<img src="{{ $c->image }}" alt="{{ $c->name }}" width="52" height="52" loading="lazy">@else{{ $ini($c->name) }}@endif</span><span class="cn"><span class="d">{{ $c->name }}</span><span class="m">{{ explode(' ', $c->name)[0] }}</span></span></a>
@endforeach</div></div></div>
@endif

@if($s['home_stores']==='1' && count($stores))
<div class="w sec-st" id="stores" style="padding-top:34px"><div class="st"><h2>Featured Stores</h2><div class="fst">
<a href="/search?deals=1"><div class="cv"></div><div class="bd"><span class="lg">DF</span><b>DutyFree Shop BD</b><span class="vf">✔ Verified</span><span class="tp">{{ number_format($count) }} products</span><span class="vs">View Store</span></div></a>
@foreach($stores as $st)<a href="/search?q={{ rawurlencode(parse_url('http://'.$st->domain, PHP_URL_HOST) ?: $st->name) }}"><div class="cv"></div><div class="bd"><span class="lg">{{ $ini($st->name) }}</span><b>{{ $st->name }}</b><span class="vf">✔ Verified</span><span class="tp">{{ number_format((int)$st->scan_published) }} products</span><span class="vs">View Store</span></div></a>@endforeach
</div></div></div>
@endif

@if($s['home_new']==='1' && $new->count())
<div class="w sec-st" id="new" style="padding-top:34px"><div class="st"><h2><span class="only-d" style="display:inline">New Arrival Products</span><span class="only-m">New Arrival</span></h2>
<div class="gr">@foreach($new as $q)@include('store.card', ['fd'=>false])@endforeach</div></div></div>
@endif

@if($s['home_top']==='1' && $top->count())
<div class="w sec-st sec-top" id="top" style="padding-top:34px"><div class="st"><h2>{{ $topLabel }}</h2>
<div class="gr">@foreach($top as $q)@include('store.card', ['fd'=>true])@endforeach</div></div></div>
@endif

@if($s['home_todays_deal']==='1' && $deals->count())
@php $dd = $deals[0]; $du = \App\Http\Controllers\StoreController::url($dd); @endphp
<div class="w sec-st" id="deal" style="padding-top:34px"><div class="st"><h2>Today's Deal</h2><div class="td"><a class="im" href="{{ $du }}"><img src="{{ $dd->image ?: '/icon.png' }}" alt="{{ $dd->name }}" width="240" height="240" loading="lazy" draggable="false"></a>
<div class="tx"><span class="k">DEAL ENDS IN <span data-cd="all">00:00:00</span></span><h3><a href="{{ $du }}">{{ $dd->name }}</a></h3><div class="pp">{{ number_format($dd->price) }} TK <s>{{ number_format($dd->compare_price) }} TK</s><em>{{ \App\Http\Controllers\StoreController::off($dd) }}% OFF</em></div><span style="font-size:12px;color:var(--mut)">Sold by DutyFree Shop BD · Cash on delivery</span><a class="bt" href="/checkout?add={{ $dd->id }}&amp;p={{ (float)$dd->price }}" rel="nofollow">ORDER NOW</a></div></div></div></div>
@endif

@if($s['home_best']==='1' && $best->count())
<div class="w sec-st" id="best" style="padding-top:34px"><div class="st"><h2>Best Sellers</h2><div class="gr">@foreach($best->take(12) as $q)@include('store.card', ['fd'=>true])@endforeach</div></div></div>
@endif

@if($s['home_offer_banners']==='1' && count($offers))
<div class="w sec-of" style="padding-top:34px"><div class="ofr">@foreach($offers as $i => [$oc, $mx])
<div class="of" style="background:{{ $i === 0 ? 'linear-gradient(120deg,#7C2D12,#FF5A00)' : 'linear-gradient(120deg,#0B1220,#1D4ED8)' }}"><span class="k">SPECIAL OFFER</span><span class="t">Up to {{ $mx }}% off<br>{{ $oc->name }}</span><span class="s">{{ $i === 0 ? 'Delivery across the country · Cash on delivery' : 'Original imported products · Cash on delivery' }}</span>
<div class="b"><a href="/{{ $oc->slug }}/" style="background:#fff;color:#0B1220">Save up to {{ $mx }}%</a><a href="/{{ $oc->slug }}/" style="background:#FF5A00;color:#fff">Order now</a></div></div>
@endforeach</div></div>
@endif

@if($s['home_cat_sections']==='1')
@foreach($sec as [$c, $items])
@if($items->count())
<div class="w sec-cs" style="padding-top:34px"><div class="cs">
<div class="hh"><span class="t">{{ $c->name }}</span><span class="sp"></span>@foreach(array_slice($subs[$c->id] ?? [], 0, 5) as $sb)<a class="tb" href="/{{ $c->slug }}/?sub={{ rawurlencode($sb->sub) }}">{{ $sb->sub }}</a>@endforeach<a class="va" href="/{{ $c->slug }}/">View All</a></div>
<div class="mt"><span>{{ $c->name }}</span></div>
<div class="gr">@foreach($items as $q)@include('store.card', ['fd'=>true])@endforeach</div>
<div class="mv"><a href="/{{ $c->slug }}/">View All</a></div></div></div>
@endif
@endforeach
@endif
@if($s['home_trust']==='1')
<div class="w" style="padding-top:34px"><div class="tr"><div><i>🚚</i>Free Delivery</div><div><i>✔</i>100% Original</div><div><i>💵</i>Cash on Delivery</div><div><i>↩</i>Easy Return</div><div><i>🔒</i>Secure Payment</div><div><i>☎</i>Customer Support</div></div></div>
@endif
@if($s['home_seller']==='1')
<div class="w" style="padding-top:24px"><div class="sl"><div><b>Grow Your Business With MultiStore</b><span>Open your store on our platform and reach customers across Bangladesh.</span></div><a href="{{ $s['seller_link'] ?: '/contact-us/' }}">Open Your Store</a></div></div>
@endif
@if($s['home_app']==='1' && ($s['app_android'] || $s['app_ios']))
<div class="w" style="padding-top:24px"><div class="ap"><div><b>Shop faster with our app</b></div>@if($s['app_android'])<a href="{{ $s['app_android'] }}" rel="noopener">Android</a>@endif @if($s['app_ios'])<a href="{{ $s['app_ios'] }}" rel="noopener">iOS</a>@endif</div></div>
@endif
@if($s['home_faq']==='1')
@php $faq = collect(preg_split('/\R/', (string)$s['faq_items']))->map(fn($l) => array_map('trim', explode('|', $l, 2)))->filter(fn($a) => count($a) === 2 && $a[0] !== ''); @endphp
@if($faq->count())<div class="w" style="padding-top:34px"><div class="st"><h2>Frequently Asked Questions</h2><div class="fq">@foreach($faq as [$fqq, $fqa])<details><summary>{{ $fqq }}</summary><p>{{ $fqa }}</p></details>@endforeach</div></div></div>
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$faq->map(fn($a) => ['@type'=>'Question','name'=>$a[0],'acceptedAnswer'=>['@type'=>'Answer','text'=>$a[1]]])->values()->all()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>@endif
@endif
@endsection
@section('js')
<script>document.querySelectorAll('[data-ft]').forEach(function(b){b.onclick=function(){var k=b.getAttribute('data-ft');document.querySelectorAll('[data-ft]').forEach(function(x){x.classList.toggle('on',x.getAttribute('data-ft')===k)});document.querySelectorAll('[data-fl]').forEach(function(g){g.style.display=g.getAttribute('data-fl')===k?'':'none'})}});</script>
@endsection
