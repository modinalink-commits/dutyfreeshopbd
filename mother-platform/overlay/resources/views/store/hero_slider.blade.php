@php $__hn = $c->name ?? ''; @endphp
<section class="w hm" aria-label="{{ $__hn }} top sellers" data-hm data-n="{{ $tops->count() }}">
<div class="hm-s">
@foreach($tops as $q)@php $__ss = \App\Support\Img::srcset($q->image); @endphp
<a class="hm-sl{{ $loop->first ? ' on' : '' }}" href="{{ \App\Http\Controllers\StoreController::url($q) }}" @if(!$loop->first) tabindex="-1" aria-hidden="true" @endif>
<span class="hm-c"><span class="hm-tag">Top seller &middot; {{ $__hn }}</span><span class="hm-nm">{{ $q->name }}</span><span class="hm-cta">Shop Now &rarr;</span></span>
<span class="hm-p"><img src="{{ $q->image }}" @if($__ss) srcset="{{ $__ss }}" sizes="(max-width:700px) 60vw, 300px" @endif alt="{{ !empty($q->image_alt) ? $q->image_alt : $q->name }}" width="300" height="300" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async" draggable="false" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/product-placeholder'"></span></a>
@endforeach
<div class="hm-d" role="tablist">@foreach($tops as $q)<b role="tab" aria-label="Slide {{ $loop->iteration }}" @if($loop->first) class="on" @endif></b>@endforeach</div>
</div>
</section>
<style>
.hm{margin:14px auto 6px}.hm-s{position:relative;border-radius:16px;overflow:hidden;min-height:300px;background:linear-gradient(135deg,#FFFBF8,#FFF3EA);border:1px solid #FDC2A5;color:#0B1220}
.hm-sl{position:absolute;inset:0;display:grid;grid-template-columns:1.1fr 1fr;align-items:center;gap:10px;padding:30px 44px 44px;opacity:0;visibility:hidden;transition:opacity .6s,visibility .6s;color:inherit;text-decoration:none}.hm-sl.on{opacity:1;visibility:visible}
.hm-c{display:grid;gap:10px;justify-items:start;transform:translateX(-24px);opacity:0;transition:all .7s .1s}.hm-sl.on .hm-c{transform:none;opacity:1}
.hm-tag{color:#FF5A00;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.hm-nm{font-size:30px;font-weight:800;line-height:1.15}
.hm-cta{display:inline-flex;background:#FF5A00;color:#fff;font-weight:700;font-size:13px;padding:10px 18px;border-radius:999px;box-shadow:0 8px 18px rgba(255,90,0,.3)}
.hm-p{position:relative;display:grid;place-items:center;height:240px}
.hm-p::before{content:"";position:absolute;width:220px;height:220px;border-radius:50%;background:#FF5A00;opacity:.14;transform:scale(.5);transition:transform .8s}.hm-sl.on .hm-p::before{transform:scale(1)}
.hm-p img{position:relative;height:230px;width:auto;max-width:100%;object-fit:contain;filter:drop-shadow(0 18px 18px rgba(11,18,32,.25));transform:translateY(30px) rotate(-6deg) scale(.9);opacity:0;transition:all .8s .15s cubic-bezier(.2,.9,.3,1.2)}
.hm-sl.on .hm-p img{transform:none;opacity:1;animation:hmf 4s 1s ease-in-out infinite}@keyframes hmf{50%{transform:translateY(-8px)}}
.hm-d{position:absolute;left:0;right:0;bottom:12px;display:flex;gap:6px;justify-content:center;z-index:3}
.hm-d b{width:8px;height:8px;border-radius:99px;background:rgba(11,18,32,.25);cursor:pointer;transition:all .3s;overflow:hidden;position:relative}.hm-d b.on{width:30px;background:rgba(11,18,32,.18)}.hm-d b.on::after{content:"";position:absolute;inset:0;background:#FF5A00;transform-origin:left;animation:hmfill 4s linear both}@keyframes hmfill{from{transform:scaleX(0)}to{transform:scaleX(1)}}
@media(max-width:700px){.hm{margin:10px auto 4px}.hm-s{min-height:430px;border-radius:14px}.hm-sl{grid-template-columns:1fr;grid-template-rows:auto auto;align-content:center;justify-items:center;text-align:center;padding:22px 18px 40px;gap:6px}.hm-p{order:-1;height:210px}.hm-p::before{width:190px;height:190px}.hm-p img{height:195px}.hm-c{justify-items:center;transform:translateY(14px)}.hm-nm{font-size:21px}}
@media(prefers-reduced-motion:reduce){.hm *{animation:none!important;transition:none!important}}
</style>
<script>(function(){var r=document.currentScript.previousElementSibling.previousElementSibling;if(!r||!r.hasAttribute('data-hm'))return;var s=r.querySelectorAll('.hm-sl'),d=r.querySelectorAll('.hm-d b'),n=s.length,i=0,t=null,rm=window.matchMedia&&matchMedia('(prefers-reduced-motion:reduce)').matches;function go(k){i=(k+n)%n;for(var j=0;j<n;j++){var on=j===i;s[j].classList.toggle('on',on);s[j].setAttribute('aria-hidden',on?'false':'true');s[j].tabIndex=on?0:-1;d[j].classList.remove('on')}void d[i].offsetWidth;d[i].classList.add('on')}function play(){if(rm||n<2)return;clearInterval(t);t=setInterval(function(){go(i+1)},4000)}for(var j=0;j<n;j++)(function(k){d[k].addEventListener('click',function(){go(k);play()})})(j);r.addEventListener('mouseenter',function(){clearInterval(t)});r.addEventListener('mouseleave',play);var x=null;r.addEventListener('touchstart',function(e){x=e.touches[0].clientX},{passive:true});r.addEventListener('touchend',function(e){if(x===null)return;var dx=e.changedTouches[0].clientX-x;if(Math.abs(dx)>40){go(i+(dx<0?1:-1));play()}x=null});play()})();</script>
