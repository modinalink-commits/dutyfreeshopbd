@extends('store.layout')
@section('ld')<script type="application/ld+json">{!! json_encode([['@context'=>'https://schema.org','@type'=>'Product','name'=>$p->name,'image'=>[$p->image ?: url('/icon.png')],'sku'=>$p->sku ?: (string)$p->id,'description'=>$p->description ?: $p->name,'category'=>$c->name ?? '','brand'=>['@type'=>'Brand','name'=>$p->sub ?: explode(' ', $p->name)[0]],'offers'=>['@type'=>'Offer','url'=>$canon,'priceCurrency'=>'BDT','price'=>(string)(float)$p->price,'availability'=>$p->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock','itemCondition'=>'https://schema.org/NewCondition','seller'=>['@type'=>'Organization','name'=>'DutyFree Shop BD']]] + ($reviews->count() ? ['aggregateRating'=>['@type'=>'AggregateRating','ratingValue'=>round($reviews->avg('rating'),1),'reviewCount'=>$reviews->count()]] : []),['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'হোম','item'=>url('/').'/'],['@type'=>'ListItem','position'=>2,'name'=>$c->name ?? 'Shop','item'=>url('/'.($c->slug ?? '')).'/'],['@type'=>'ListItem','position'=>3,'name'=>$p->name,'item'=>$canon]]]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) !!}</script>@endsection
@section('main')
@php
  $price = (float)$p->price; $cmp = $p->compare_price && $p->compare_price > $p->price ? (float)$p->compare_price : 0; $save = $cmp ? $cmp - $price : 0;
  $code = $p->sku ?: $p->ext_id;
  $bd = $rules['bundle'] ?? null; $bpct = $bd ? (float)($bd['Bundle %'] ?? 0) : 0; $bmin = $bd ? (int)($bd['Minimum products'] ?? 2) : 99;
  $bundles = [];
  foreach ([1,2,3] as $n) { $ex = ($bpct > 0 && $n >= $bmin) ? $bpct : 0; $fin = round($price * $n * (1 - $ex / 100)); $orig = ($cmp ?: $price) * $n; $pc = $orig > 0 ? round(($orig - $fin) / $orig * 100, 2) : 0;
    $bundles[] = ['n'=>$n, 'fin'=>$fin, 'orig'=>$orig, 'save'=>$orig - $fin, 'tag'=>$ex > 0 ? 'Get Extra '.rtrim(rtrim(number_format($pc, 2), '0'), '.').'% Off' : ($pc > 0 ? rtrim(rtrim(number_format($pc, 2), '0'), '.').' % Off' : ''), 'tc'=>$ex > 0 ? '#15803D' : '#DC2626']; }
  $msgLink = $s['pp_messenger'] ?: 'https://wa.me/'.$wa.'?text='.rawurlencode('আমি এই পণ্যটি অর্ডার করতে চাই: '.$p->name.' ('.$code.') '.$canon);
  $side = $rel->take(5); $bottom = $rel->slice(5, 6); if ($bottom->count() < 6) $bottom = $rel->take(6);
@endphp
<style>
.pp{padding-top:18px;display:grid;grid-template-columns:568px minmax(0,1fr) 334px;gap:16px;align-items:start}
.pp .ph{height:568px;background:#fff;display:flex;align-items:center;justify-content:center;border-radius:2px;position:relative;overflow:hidden}.pp .ph img{max-width:100%;max-height:100%;object-fit:contain;pointer-events:none}.pp .ph .wm{position:absolute;bottom:10px;right:14px;font-size:11px;font-weight:700;color:rgba(11,18,32,.22)}
.pi{display:flex;flex-direction:column;gap:10px}.pi .bc{font-size:10px;display:flex;gap:6px;align-items:center;text-transform:uppercase;flex-wrap:wrap}.pi h1{font-size:19px;font-weight:600;line-height:1.3}
.pi .pz{display:flex;align-items:center;gap:8px}.pi .pz b{font-size:18px;font-weight:700}.pi .pz s{font-size:16px;color:var(--mut);font-weight:600}.pi .pz span{background:var(--o);color:#fff;font-size:10px;font-weight:700;border-radius:20px;padding:3px 9px}
.bnl{text-align:center;font-size:11px;font-weight:700;letter-spacing:.04em;border-top:1px solid #E5E7EB;margin-top:8px;padding-top:2px}.bnl span{background:var(--bg);padding:0 10px;position:relative;top:-9px}
.bn{border:1.25px solid #E5E7EB;background:#fff;border-radius:6px;padding:10px 14px;display:flex;align-items:center;gap:12px;cursor:pointer;text-align:left;width:100%}.bn.on{border-color:var(--o);background:#FFF1EA}.bn .rd{width:13px;height:13px;border-radius:50%;border:1.5px solid #9CA3AF;display:flex;align-items:center;justify-content:center;flex:none}.bn .rd i{width:7px;height:7px;border-radius:50%}.bn.on .rd{border-color:var(--o)}.bn.on .rd i{background:var(--o)}
.bn .md{flex-grow:1;display:flex;flex-direction:column;gap:3px}.bn .md div{display:flex;gap:8px;align-items:center}.bn .q{font-size:12px;font-weight:600}.bn .tg{font-size:9px;font-weight:700;color:#fff;padding:1px 5px;border-radius:2px}.bn .sv{font-size:9px;color:var(--mut);font-weight:500}.bn .rp{display:flex;flex-direction:column;align-items:flex-end;line-height:1.3}.bn .rp b{font-size:12px}.bn .rp s{font-size:10px;color:var(--mut)}
.acts{display:flex;gap:10px;margin-top:10px}.acts .ac{flex-grow:1;background:var(--o);color:#fff;font-size:12px;font-weight:600;text-align:center;border-radius:3px;padding:12px 0;border:0}.acts .wl{width:46px;border:1.25px solid #E5E7EB;background:#fff;border-radius:4px;display:flex;align-items:center;justify-content:center;color:var(--o);font-size:16px}.acts .wl.on{background:#FFF1EA;border-color:var(--o)}
.onw{background:var(--n);color:#fff;font-size:12px;font-weight:600;text-align:center;border-radius:3px;padding:12px 0;display:block;border:0;width:100%}
.cm{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.cm a{border:1.25px solid var(--bd);background:#fff;border-radius:4px;padding:10px 12px;display:flex;gap:10px;align-items:center}.cm .ic{width:24px;height:24px;border-radius:50%;background:#F5F5F5;display:flex;align-items:center;justify-content:center;color:var(--n);flex:none}.cm .t{display:flex;flex-direction:column;font-size:10px;line-height:1.4}.cm .t b{font-size:11px}
.rs{background:#F3F4F6;padding:14px 14px 40px;display:flex;flex-direction:column;gap:6px;min-height:568px}.rs .h{text-align:center;font-size:15px;font-weight:600;padding:2px 0 8px}.rs a{background:#fff;display:flex;gap:10px;padding:5px;align-items:center}.rs .ri{width:46px;height:46px;border:1px solid var(--bd);background:var(--bg);flex:none;display:flex;align-items:center;justify-content:center;overflow:hidden}.rs .ri img{max-width:100%;max-height:100%;object-fit:contain}.rs .rt2{display:flex;flex-direction:column;font-size:10px;line-height:1.5}.rs .rt2 s{color:var(--red)}
.tbs{background:#DDDDDD;display:flex;font-size:11px;font-weight:600}.tbs button{background:none;border:0;padding:12px;font-weight:600;font-size:11px}.tbs button.on{background:var(--n);color:#fff}
.tp{padding:14px 0;font-size:12px;line-height:1.7}.tp h3{margin:14px 0 4px;font-size:16px;border-top:1px solid #D4D4D4;padding-top:10px}.tp ul{margin:0;padding-left:20px}
.rh{text-align:center;font-size:16px;font-weight:600;padding:8px 0 12px}
@media (max-width:1200px){.pp{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.pp .ph{height:auto;aspect-ratio:1}.rs{display:none}}
@media (max-width:760px){.pp{grid-template-columns:1fr;padding-top:6px;gap:0;padding-left:0!important;padding-right:0!important}.pp .ph{height:300px;aspect-ratio:auto}.pi{padding:10px 12px;gap:7px}.pi .bc{display:none}.pi h1{font-size:15px;font-weight:700}.pi .pz b{font-size:16px}.pi .pz s{font-size:13px;font-weight:400}.pi .pz span{font-size:9px;padding:2px 8px}
.bnl{font-size:10px;margin-top:4px}.bnl span{padding:0 8px;top:-7px}.bn{padding:8px 10px;gap:8px}.bn .q{font-size:11px;font-weight:700}.bn .tg{font-size:8px;padding:1px 4px}.bn .sv{font-size:8.5px}.bn .rp s{display:none}.acts{gap:8px;margin-top:0}.acts .ac{padding:11px 0}.acts .wl{width:42px}.onw{padding:11px 0}.cm{display:none}.rh{font-size:13px;margin:8px 0 6px;padding:0}.tbs{margin-top:8px}}
</style>
<div class="w pp">
<div class="ph"><img src="{{ $p->image ?: '/icon.png' }}" alt="{{ $p->name }}" width="568" height="568" fetchpriority="high" draggable="false"><span class="wm">DutyFree Shop BD</span></div>
<div class="pi">
<div class="bc"><a href="/" aria-label="Home">⌂</a><span>›</span>@if($c)<a href="/{{ $c->slug }}/">{{ $c->name }}</a>@endif @if($p->sub)<span>›</span><a href="/{{ $c->slug ?? '' }}/?sub={{ rawurlencode($p->sub) }}" style="color:#555">{{ $p->sub }}</a>@endif</div>
<h1>{{ $p->name }}@if($code) - ( {{ $code }} )@endif</h1>
<div class="pz"><b>Tk {{ number_format($price) }}</b>@if($cmp)<s>Tk {{ number_format($cmp) }}</s>@endif @if($save > 0 && $s['pp_save_badge']==='1')<span>SAVE TK {{ number_format($save) }}</span>@endif</div>
@if($s['pp_bundle']==='1')
<div class="bnl"><span>BUNDLE &amp; SAVE</span></div>
@foreach($bundles as $b)
<button type="button" class="bn {{ $b['n'] === 1 ? 'on' : '' }}" data-n="{{ $b['n'] }}"><span class="rd"><i></i></span><span class="md"><span style="display:flex;gap:8px;align-items:center"><span class="q">Buy {{ $b['n'] }}</span>@if($b['tag'])<span class="tg" style="background:{{ $b['tc'] }}">{{ $b['tag'] }}</span>@endif</span>@if($b['save'] > 0)<span class="sv">YOU SAVE TK {{ number_format($b['save']) }}</span>@endif</span><span class="rp"><b>Tk {{ number_format($b['fin']) }}</b>@if($b['orig'] > $b['fin'])<s>Tk {{ number_format($b['orig']) }}</s>@endif</span></button>
@endforeach
@endif
@if($p->stock !== null && (int)$p->stock <= 0)<div style="font-size:12px;color:#B91C1C;font-weight:600">Stock out — call us for the next arrival</div>@endif
<div class="acts"><button type="button" class="ac" id="addc">Add To Cart</button><button type="button" class="wl" id="wl" aria-label="Wishlist">♡</button></div>
<button type="button" class="onw" id="ordn">Order Now ✓</button>
<div class="cm"><a href="tel:{{ $s['pp_call'] }}"><span class="ic">{!! '<svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1z"/></svg>' !!}</span><span class="t"><b>Call us</b>{{ $s['pp_call'] }}</span></a><a href="{{ $msgLink }}" rel="noopener"><span class="ic" style="background:#DCFCE7;color:#16A34A">✆</span><span class="t"><b>Message us</b>{{ $s['whatsapp_number'] }}</span></a></div>
</div>
@if($s['pp_related']==='1')
<div class="rs"><div class="h">Related Products</div>@foreach($side as $r)
@php $roff = $r->compare_price && $r->compare_price > $r->price; @endphp
<a href="{{ \App\Http\Controllers\StoreController::url($r) }}"><span class="ri">@if($r->image)<img src="{{ $r->image }}" alt="{{ $r->name }}" width="46" height="46" loading="lazy" draggable="false">@endif</span><span class="rt2"><span>{{ $r->name }}</span><span>{{ number_format($r->price) }}TK @if($roff)<s>{{ number_format($r->compare_price) }}TK</s>@endif</span></span></a>
@endforeach</div>
@endif
</div>
<div class="w" style="padding-top:16px;padding-bottom:40px">
<div class="tbs"><button type="button" class="on" data-t="d">DESCRIPTION</button><button type="button" data-t="h">HOW TO ORDER</button><button type="button" data-t="r">REVIEWS{{ $reviews->count() ? ' ('.$reviews->count().')' : '' }}</button></div>
<div class="tp" data-tp="d"><b>Details</b> for {{ $p->name }}:
@if($p->description)<div style="white-space:pre-line;margin-top:6px">{{ $p->description }}</div>@endif
<h3>Product information</h3><ul>@if($c)<li>Category: {{ $c->name }}</li>@endif @if($p->sub)<li>Brand: {{ $p->sub }}</li>@endif @if($p->size)<li>Size: {{ $p->size }}</li>@endif @if($code)<li>Product code: {{ $code }}</li>@endif<li>{{ (int)$p->stock > 0 ? 'In stock' : 'Ask for availability' }}</li></ul>
<h3>Delivery</h3><ul>@foreach($areas as $a)<li>{{ $a['n'] }}: {{ $a['c'] > 0 ? number_format($a['c']).' TK' : 'Free' }}</li>@endforeach<li>Cash on delivery all over Bangladesh</li></ul></div>
<div class="tp" data-tp="h" style="display:none"><h3 style="border:0;padding-top:0;margin-top:0">How to order</h3><ul><li>Choose Buy 1, 2 or 3 and press <b>Order Now</b>, or <b>Add To Cart</b> to buy more products together.</li><li>Write your mobile number, name and full address, then choose your delivery area.</li><li>Press <b>ORDER SUBMIT</b>. Our team will call you to confirm.</li><li>Pay cash when the product reaches you.</li><li>Need help? Call {{ $s['pp_call'] }} or message us on WhatsApp.</li></ul></div>
<div class="tp" data-tp="r" style="display:none">@forelse($reviews as $rv)<div style="border-top:1px solid #D4D4D4;padding:10px 0"><b>{{ $rv->name }}</b> <span style="color:#FF5A00">{{ str_repeat('★', (int)$rv->rating) }}{{ str_repeat('☆', 5 - (int)$rv->rating) }}</span>@if($rv->verified_buyer) <span style="font-size:10px;color:#16A34A;font-weight:700">Verified buyer</span>@endif<div>{{ $rv->body }}</div>@if($rv->reply)<div style="color:#6B7280;margin-top:4px">Reply: {{ $rv->reply }}</div>@endif</div>@empty<p>No reviews yet.</p>@endforelse</div>
@if($s['pp_related']==='1' && $bottom->count())
<div class="rh">Related Products</div>
<div class="gr">@foreach($bottom as $q)@include('store.card', ['fd'=>true])@endforeach</div>
@endif
</div>
@endsection
@section('js')
<script>
(function(){var id={{ $p->id }},pr={{ $price }},n=1;
document.querySelectorAll('.bn').forEach(function(b){b.onclick=function(){document.querySelectorAll('.bn').forEach(function(x){x.classList.remove('on')});b.classList.add('on');n=+b.getAttribute('data-n')}});
var wl=document.getElementById('wl'),sw=function(){var on=DF.wish().indexOf(String(id))>=0;wl.classList.toggle('on',on);wl.textContent=on?'♥':'♡'};sw();wl.onclick=function(){DF.tw(id);sw()};
var ad=document.getElementById('addc');ad.onclick=function(){DF.add(id,n,pr);ad.textContent='Added ✓ ('+DF.cart()[String(id)].q+')';setTimeout(function(){ad.textContent='Add To Cart'},1800)};
document.getElementById('ordn').onclick=function(){DF.add(id,n,pr,true);location.href='/checkout'};
document.querySelectorAll('[data-t]').forEach(function(b){b.onclick=function(){var k=b.getAttribute('data-t');document.querySelectorAll('[data-t]').forEach(function(x){x.classList.toggle('on',x===b)});document.querySelectorAll('[data-tp]').forEach(function(x){x.style.display=x.getAttribute('data-tp')===k?'':'none'})}});
})();
</script>
@if($s['gtag_id'])<script>gtag("event","view_item",{currency:"BDT",value:{{ $price }},items:[{item_id:@json((string)$p->id),item_name:@json($p->name),price:{{ $price }}}]});</script>@endif
@endsection
