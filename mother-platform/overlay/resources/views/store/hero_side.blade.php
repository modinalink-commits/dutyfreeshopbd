@php
$__hf = (($s['home_flash'] ?? '0') === '1' && isset($flash) && $flash->count() && ($fOn ?? true)) ? $flash->take(5)->values() : collect();
$__bolt = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>';
$__and = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M12 8v6M9.5 11.5 12 14l2.5-2.5"/></svg>';
$__ios = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="3.5"/><path d="M10 18.5h4M12 7v5M9.5 9.5 12 12l2.5-2.5"/></svg>';
@endphp
<aside class="hxside" aria-label="Flash Sale and App">
<div class="hxf">
<div class="hxfh"><b>{!! $__bolt !!}Flash Sale</b>@if($__hf->count() && isset($fmt) && isset($fEnd))<span class="tm" data-end="{{ !empty($fFix) ? $fEnd * 1000 : '' }}"><b data-cd="h">{{ substr($fmt($fEnd),0,2) }}</b>:<b data-cd="m">{{ substr($fmt($fEnd),3,2) }}</b>:<b data-cd="s">{{ substr($fmt($fEnd),6,2) }}</b></span>@endif</div>
@if($__hf->count())
<div class="hxfs">
@foreach($__hf as $__i => $__p)
@php $__o = \App\Http\Controllers\StoreController::off($__p); $__u = \App\Http\Controllers\StoreController::url($__p); @endphp
<a class="hxfi{{ $__i === 0 ? ' on' : '' }}" href="{{ $__u }}"@if($__i) tabindex="-1" aria-hidden="true"@endif><span class="hxfp"><img src="{{ $__p->image ?: '/product-placeholder' }}" alt="{{ $__p->name }}" width="86" height="86" @if($__i) loading="lazy" @endif></span><span class="hxft"><span class="hxfn">{{ $__p->name }}</span><span class="hxfr">{{ number_format($__p->price) }} TK@if($__o > 0 && $__p->compare_price)<s>{{ number_format($__p->compare_price) }}</s>@endif</span>@if($__o > 0)<span class="hxfo">SAVE {{ $__o }}%</span>@endif</span></a>
@endforeach
</div>
<div class="hxfd" aria-hidden="true">@foreach($__hf as $__i => $__p)<i class="{{ $__i === 0 ? 'on' : '' }}"></i>@endforeach</div>
@else
<a class="hxfa" href="#flash">সব ডিল দেখুন →</a>
@endif
</div>
<div class="hxa2">
<div class="hxat"><b>অ্যাপ ইনস্টল করুন</b><span>এক ট্যাপে মোবাইলে</span></div>
<div class="hxab"><button type="button" class="hxbt" data-hxapp="android" aria-label="Android অ্যাপ ইনস্টল">{!! $__and !!}<span>Android<small>ইনস্টল</small></span></button><button type="button" class="hxbt" data-hxapp="ios" aria-label="iPhone অ্যাপ ইনস্টল">{!! $__ios !!}<span>iPhone<small>ইনস্টল</small></span></button></div>
</div>
</aside>
<script>(function(){var s=document.querySelector('.hxside');if(!s)return;var A=s.querySelector('.hxa2');try{if(matchMedia('(display-mode: standalone)').matches&&A)A.remove()}catch(e){}
s.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-hxapp]');if(!b)return;e.preventDefault();var k=b.getAttribute('data-hxapp');try{if(window.DFPWA&&DFPWA.install)DFPWA.install(k);else location.hash='#dfapp'}catch(x){location.hash='#dfapp'}});
var it=[].slice.call(s.querySelectorAll('.hxfi')),dt=[].slice.call(s.querySelectorAll('.hxfd i')),n=0,t=0,hov=false,vis=true;if(it.length<2)return;
function show(k){n=(k+it.length)%it.length;it.forEach(function(e,j){e.classList.toggle('on',j===n);if(j===n){e.removeAttribute('tabindex');e.removeAttribute('aria-hidden')}else{e.setAttribute('tabindex','-1');e.setAttribute('aria-hidden','true')}});dt.forEach(function(e,j){e.classList.toggle('on',j===n)})}
dt.forEach(function(e,j){e.onclick=function(){show(j)}});
var f=s.querySelector('.hxf');f.addEventListener('mouseenter',function(){hov=true});f.addEventListener('mouseleave',function(){hov=false});
if('IntersectionObserver' in window)new IntersectionObserver(function(x){vis=x[0].isIntersecting}).observe(s);
if(!(window.matchMedia&&matchMedia('(prefers-reduced-motion:reduce)').matches))setInterval(function(){if(!hov&&vis&&!document.hidden)show(n+1)},3500)})();</script>
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
.hxbt{flex:1;border:0;border-radius:11px;padding:9px 6px;display:flex;align-items:center;justify-content:center;gap:7px;font:inherit;font-size:12px;font-weight:700;cursor:pointer;color:#fff;background:rgba(255,255,255,.14);min-width:0;min-height:44px;transition:transform .15s,filter .15s}
.hxbt:hover{transform:translateY(-1px);filter:brightness(1.15)}.hxbt svg{width:20px;height:20px;flex:none}.hxbt span{text-align:left;line-height:1.15}.hxbt small{display:block;font-size:9.5px;font-weight:500;opacity:.8}
@media (min-width:1024px){.hx:has(.hxside){grid-template-columns:minmax(0,1fr) 250px}.hxside{display:flex;flex-direction:column;gap:10px;min-width:0}.hxf{flex:1.55}.hxa2{flex:1}}
@media (max-width:1023px){.hx:has(.hxside) .hxside{display:flex;gap:8px;margin-top:8px}.hxf{flex:1.3}.hxa2{flex:1}.hxfp{width:64px;height:64px}.hxfs{min-height:68px}.hxfn{font-size:12px}.hxab{flex-direction:column}.hxbt{justify-content:flex-start;padding:7px 10px}.hxat span{display:none}}
.pm{padding-top:12px}.pm a{padding:7px 12px}.pm i{width:28px;height:28px}</style>
