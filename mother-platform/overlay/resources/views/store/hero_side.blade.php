@php
$__hf = (($s['home_flash'] ?? '0') === '1' && isset($flash) && $flash->count() && ($fOn ?? true)) ? $flash->take(5)->values() : collect();
$__bolt = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>';
$__and = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M12 8v6M9.5 11.5 12 14l2.5-2.5"/></svg>';
$__ios = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="3.5"/><path d="M10 18.5h4M12 7v5M9.5 9.5 12 12l2.5-2.5"/></svg>';
@endphp
<style>.hxside{display:none}
.hxf,.hxa2{border-radius:14px;min-width:0;position:relative;overflow:hidden}
.hxf{background:#FF5A00;color:#fff;padding:12px;display:flex;flex-direction:column;gap:8px}
.hxa2{background:#0B1220;color:#fff;padding:12px;display:flex;flex-direction:column;justify-content:space-between;gap:8px}
.hxfh{display:flex;align-items:center;justify-content:space-between;gap:6px;font-size:13px}.hxfh>b{display:inline-flex;align-items:center;gap:5px;font-weight:700}
.hxf .tm{font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;background:rgba(255,255,255,.2);font-variant-numeric:tabular-nums;color:#fff}.hxf .tm b{font-weight:700;color:#fff!important}
.hxfs{position:relative;flex:1;min-height:92px}
.hxfi{position:absolute;inset:0;display:flex;gap:10px;align-items:center;opacity:0;transition:opacity .5s;pointer-events:none;color:#fff;text-decoration:none}
.hxfi.on{opacity:1;pointer-events:auto}
.hxfp{width:86px;height:86px;flex:none;border-radius:10px;background:#fff;display:grid;place-items:center;overflow:hidden}.hxfp img{width:100%;height:100%;object-fit:contain;display:block}
.hxft{display:flex;flex-direction:column;gap:3px;min-width:0}
.hxfn{font-size:12.5px;font-weight:600;line-height:1.25;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.hxfr{font-size:15px;font-weight:800}.hxfr s{font-size:11px;font-weight:500;opacity:.8;margin-left:5px}
.hxfo{font-size:10.5px;font-weight:700;padding:1px 7px;border-radius:99px;background:#fff;color:#C2410C;align-self:flex-start}
.hxfd{display:flex;gap:5px;justify-content:center}.hxfd i{width:6px;height:6px;border-radius:50%;background:#fff;opacity:.4;transition:.3s;cursor:pointer}.hxfd i.on{opacity:1;width:16px;border-radius:4px}
.hxfa{color:#fff;font-weight:700;text-decoration:none;font-size:14px;margin-top:auto}
.hxat b{display:block;font-size:13px}.hxat span{display:block;font-size:11px;opacity:.75;margin-top:2px}
.hxab{display:flex;gap:8px}
.hxbt{flex:1;border:1px solid #a6a6a6;border-radius:10px;padding:6px 9px;display:flex;align-items:center;justify-content:flex-start;gap:7px;font:inherit;cursor:pointer;color:#fff;background:#000;min-width:0;min-height:44px;transition:transform .15s,filter .15s}
.hxbt:hover{transform:translateY(-1px);filter:brightness(1.15)}.hxbt svg{width:22px;height:22px;flex:none}.hxbt span{display:flex;flex-direction:column;text-align:left;line-height:1.1;min-width:0}.hxbt small{font-size:8.5px;font-weight:500;opacity:.8;white-space:nowrap}.hxbt strong{font-size:13px;font-weight:700}
@media (min-width:1024px){.hx:has(.hxside){grid-template-columns:minmax(0,1fr) 250px}.hxside{display:flex;flex-direction:column;gap:10px;min-width:0}.hxf{flex:1.55}.hxa2{flex:1}}
@media (max-width:1023px){.hx:has(.hxside) .hxside{display:flex;gap:8px;margin-top:8px}.hxf{flex:1.3}.hxa2{flex:1}.hxfp{width:64px;height:64px}.hxfs{min-height:68px}.hxfn{font-size:12px}.hxab{flex-direction:column;gap:6px}.hxbt{min-height:38px;padding:4px 10px}.hxat span{display:none}}
.pm{padding-top:12px}.pm a{padding:7px 12px}.pm i{width:28px;height:28px}</style>
<aside class="hxside" aria-label="Flash Sale and App">
<div class="hxf">
<div class="hxfh"><b>{!! $__bolt !!}Flash Sale</b>@if($__hf->count() && isset($fmt) && isset($fEnd))<span class="tm" data-end="{{ !empty($fFix) ? $fEnd * 1000 : '' }}"><b data-cd="h">{{ substr($fmt($fEnd),0,2) }}</b>:<b data-cd="m">{{ substr($fmt($fEnd),3,2) }}</b>:<b data-cd="s">{{ substr($fmt($fEnd),6,2) }}</b></span>@endif</div>
@if($__hf->count())
<div class="hxfs">
@foreach($__hf as $__i => $__p)
@php $__o = \App\Http\Controllers\StoreController::off($__p); $__u = \App\Http\Controllers\StoreController::url($__p); @endphp
<a class="hxfi{{ $__i === 0 ? ' on' : '' }}" href="{{ $__u }}"@if($__i) tabindex="-1" aria-hidden="true"@endif><span class="hxfp"><img src="{{ $__p->image ?: '/product-placeholder' }}" @php $__ss = \App\Support\Img::srcset($__p->image); @endphp @if($__ss) srcset="{{ $__ss }}" sizes="86px" @endif alt="{{ $__p->name }}" width="86" height="86" decoding="async" @if($__i) loading="lazy" @endif></span><span class="hxft"><span class="hxfn">{{ $__p->name }}</span><span class="hxfr">{{ number_format($__p->price) }} TK @if($__o > 0 && $__p->compare_price)<s>{{ number_format($__p->compare_price) }}</s>@endif</span>@if($__o > 0)<span class="hxfo">SAVE {{ $__o }}%</span>@endif</span></a>
@endforeach
</div>
<div class="hxfd" aria-hidden="true">@foreach($__hf as $__i => $__p)<i class="{{ $__i === 0 ? 'on' : '' }}"></i>@endforeach</div>
@else
<a class="hxfa" href="#flash">সব ডিল দেখুন →</a>
@endif
</div>
@php $__bsOn = !empty($bsOn) || \App\Support\Procure::set('home_bestseller', '0') === '1'; $__bs = $__bsOn ? \App\Http\Controllers\StoreController::bestSellers()->take(2)->values() : collect(); @endphp
@if($__bs->count())
{{-- Owner 14 Nov 2026: the two Best Seller cartons take the place of the App install card (Flash Sale stays above). Fixed, no rotation; the price is read from the product on every page load; a tap goes straight to checkout. --}}
<style>.hxbs{border-radius:14px;min-width:0;background:#0B1220;color:#fff;padding:10px 12px 12px;display:flex;flex-direction:column;gap:8px;flex:1.5}
.hxbs .tg{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:800;letter-spacing:.08em;color:#FDBA8C}
.hxbs .gr{display:grid;grid-template-columns:repeat({{ $__bs->count() }},minmax(0,1fr));gap:8px;flex:1}
.hxbs a{display:flex;flex-direction:column;gap:4px;min-width:0;color:#fff;text-decoration:none;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:7px}
.hxbs a:hover{border-color:#FF5A00;background:rgba(255,255,255,.1)}
.hxbs .im{display:grid;place-items:center;background:#fff;border-radius:8px;aspect-ratio:1/1;overflow:hidden}.hxbs .im img{width:100%;height:100%;object-fit:contain;display:block}
.hxbs .nm{font-size:11.5px;font-weight:600;line-height:1.25;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:29px}
.hxbs .pr{font-size:16px;font-weight:800;font-variant-numeric:tabular-nums;line-height:1.1;white-space:nowrap}.hxbs .pr small{font-size:10px;font-weight:600;opacity:.8}
.hxbs .go{display:flex;align-items:center;justify-content:center;height:30px;border-radius:8px;background:#FF5A00;color:#fff;font-weight:800;font-size:12.5px;margin-top:auto}.hxbs .go.no{background:rgba(255,255,255,.2)}
@media (min-width:1024px){.hxside:has(.hxbs) .hxf{flex:1}}
@media (max-width:1023px){.hx:has(.hxside) .hxside:has(.hxbs){flex-direction:column}.hxbs{padding:9px 10px 10px}.hxbs a{flex-direction:row;align-items:center;gap:8px;flex-wrap:wrap}.hxbs .im{width:56px;flex:none}.hxbs .tx{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}.hxbs .nm{min-height:0}.hxbs .go{flex:0 0 100%;height:32px}}
@media (min-width:1024px){.hxbs .tx{display:contents}}</style>
<div class="hxbs" aria-label="DutyFreeBD Best Sellers">
<span class="tg"><svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2l-6.1 3.4 1.4-6.8L2.2 9.1l6.9-.8z"/></svg>BEST SELLERS</span>
<div class="gr">@foreach($__bs as $__p)@php $__in = \App\Support\StockGuard::sellable($__p); $__im = $__p->bs_image ?: ($__p->image ?: '/product-placeholder'); @endphp
<a href="{{ $__in ? '/checkout?add='.$__p->id.'&p='.(float)$__p->price : \App\Http\Controllers\StoreController::url($__p) }}" aria-label="Order {{ $__p->name }}"><span class="im"><img src="{{ $__im }}" alt="{{ $__p->name }}" width="100" height="100" decoding="async" onerror="this.onerror=null;this.src='/product-placeholder'"></span><span class="tx"><span class="nm">{{ $__p->name }}</span><span class="pr">{{ number_format($__p->price) }} <small>TK</small></span></span><span class="go {{ $__in ? '' : 'no' }}">{{ $__in ? 'Order Now' : 'Out of stock' }}</span></a>
@endforeach</div>
</div>
@else
<div class="hxa2">
<div class="hxat"><b>অ্যাপ ইনস্টল করুন</b><span>এক ট্যাপে মোবাইলে</span></div>
<div class="hxab"><button type="button" class="hxbt" data-hxapp="android" aria-label="Android অ্যাপ ইনস্টল">{!! $__and !!}<span><small>ইনস্টল করুন</small><strong>Android</strong></span></button><button type="button" class="hxbt" data-hxapp="ios" aria-label="iPhone অ্যাপ ইনস্টল">{!! $__ios !!}<span><small>ইনস্টল করুন</small><strong>iPhone</strong></span></button></div>
</div>
@endif
</aside>
<script>(function(){var s=document.querySelector('.hxside');if(!s)return;var A=s.querySelector('.hxa2');try{if(matchMedia('(display-mode: standalone)').matches&&A)A.remove()}catch(e){}
s.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-hxapp]');if(!b)return;e.preventDefault();var k=b.getAttribute('data-hxapp');try{if(window.DFPWA&&DFPWA.install)DFPWA.install(k);else location.hash='#dfapp'}catch(x){location.hash='#dfapp'}});
var it=[].slice.call(s.querySelectorAll('.hxfi')),dt=[].slice.call(s.querySelectorAll('.hxfd i')),n=0,t=0,hov=false,vis=true;if(it.length<2)return;
function show(k){n=(k+it.length)%it.length;it.forEach(function(e,j){e.classList.toggle('on',j===n);if(j===n){e.removeAttribute('tabindex');e.removeAttribute('aria-hidden')}else{e.setAttribute('tabindex','-1');e.setAttribute('aria-hidden','true')}});dt.forEach(function(e,j){e.classList.toggle('on',j===n)})}
dt.forEach(function(e,j){e.onclick=function(){show(j)}});
var f=s.querySelector('.hxf');f.addEventListener('mouseenter',function(){hov=true});f.addEventListener('mouseleave',function(){hov=false});
if('IntersectionObserver' in window)new IntersectionObserver(function(x){vis=x[0].isIntersecting}).observe(s);
if(!(window.matchMedia&&matchMedia('(prefers-reduced-motion:reduce)').matches))setInterval(function(){if(!hov&&vis&&!document.hidden)show(n+1)},3500)})();</script>
