@php $__bs = \App\Http\Controllers\StoreController::brandSlider(); $__cur = trim((string)request()->path(), '/'); @endphp
@if(count($__bs) > 0)
<style>
.bsl{background:#fff;border-top:1px solid #EEF1F4;border-bottom:1px solid #EEF1F4;padding:10px 0}
.bsl .bh2{max-width:1440px;margin:0 auto;padding:0 16px 6px;font-size:13px;font-weight:700;color:#0B1F3A}
.bsl .bt{display:flex;gap:10px;overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none;padding:2px 16px;cursor:grab;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain}
.bsl .bt::-webkit-scrollbar{display:none}
.bsl .bt.dr{cursor:grabbing;user-select:none}
.bsl a.bp{flex:none;display:inline-flex;align-items:center;height:34px;padding:0 16px;border:1px solid #E2E6EA;border-radius:17px;background:#fff;color:#0B1F3A;font-size:12px;font-weight:600;letter-spacing:.15px;white-space:nowrap;text-decoration:none;transition:border-color .15s,color .15s,background .15s,transform .15s}
.bsl a.bp:hover,.bsl a.bp:focus-visible{border-color:#FF5A00;color:#FF5A00;background:#FFF4EC;transform:translateY(-1px)}
.bsl a.bp.on{border-color:#FF5A00;background:#FFF4EC;color:#FF5A00}
</style>
<section class="bsl" aria-label="Shop by Brand"><div class="bh2">Shop by Brand</div><div class="bt" id="bslT">
@foreach($__bs as $__b)<a class="bp {{ $__cur === 'brand/'.$__b['slug'] ? 'on' : '' }}" href="/brand/{{ $__b['slug'] }}" aria-label="View {{ $__b['name'] }} products">{{ $__b['name'] }}</a>@endforeach
@foreach($__bs as $__b)<a class="bp" href="/brand/{{ $__b['slug'] }}" tabindex="-1" aria-hidden="true">{{ $__b['name'] }}</a>@endforeach
</div></section>
<script>(function(){var t=document.getElementById('bslT');if(!t)return;var rm=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches,pause=false,drag=false,sx=0,sl=0,moved=false,acc=0,last=0,hold=0;
function half(){return t.scrollWidth/2}
t.addEventListener('mouseenter',function(){pause=true});t.addEventListener('mouseleave',function(){pause=false;drag=false;t.classList.remove('dr')});
t.addEventListener('focusin',function(){pause=true});t.addEventListener('focusout',function(){pause=false});
t.addEventListener('touchstart',function(){pause=true},{passive:true});t.addEventListener('touchend',function(){hold=Date.now()+1800;setTimeout(function(){pause=false},1800)},{passive:true});
t.addEventListener('mousedown',function(e){drag=true;moved=false;sx=e.pageX;sl=t.scrollLeft;t.classList.add('dr')});
window.addEventListener('mouseup',function(){drag=false;t.classList.remove('dr')});
t.addEventListener('mousemove',function(e){if(!drag)return;var d=e.pageX-sx;if(Math.abs(d)>4)moved=true;t.scrollLeft=sl-d});
t.addEventListener('click',function(e){if(moved){e.preventDefault();moved=false}},true);
function step(ts){if(!last)last=ts;var dt=Math.min(64,ts-last);last=ts;if(!pause&&!drag&&!document.hidden){acc+=dt*0.028;var w=Math.floor(acc);if(w>=1){acc-=w;t.scrollLeft+=w}}var h=half();if(h>0){if(t.scrollLeft>=h)t.scrollLeft-=h;else if(t.scrollLeft<=0&&drag)t.scrollLeft+=h}requestAnimationFrame(step)}
if(!rm)requestAnimationFrame(step)})();</script>
@endif
