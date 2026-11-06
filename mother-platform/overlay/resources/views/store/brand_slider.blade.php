@php $__bs = \App\Http\Controllers\StoreController::brandSlider(); $__cur = trim((string)request()->path(), '/'); @endphp
@if(count($__bs) > 0)
<style>
.bsl{padding-top:14px;padding-bottom:2px}
.bsl .bh2{display:flex;align-items:center;gap:8px;padding:0 0 8px;font-size:14px;font-weight:700;color:var(--n);line-height:1.3}
.bsl .bh2:before{content:"";width:4px;height:15px;border-radius:2px;background:var(--o)}
.bsl .bt{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none;padding:2px 0 4px;cursor:grab;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain;-webkit-mask-image:linear-gradient(90deg,transparent 0,#000 22px,#000 calc(100% - 22px),transparent 100%);mask-image:linear-gradient(90deg,transparent 0,#000 22px,#000 calc(100% - 22px),transparent 100%)}
.bsl .bt::-webkit-scrollbar{display:none}
.bsl .bt.dr{cursor:grabbing;user-select:none}
.bsl a.bp{flex:none;display:inline-flex;align-items:center;height:34px;padding:0 16px;border:1.25px solid var(--bd);border-radius:10px;background:#fff;color:var(--n);font-size:12px;font-weight:600;letter-spacing:.15px;white-space:nowrap;text-decoration:none;transition:border-color .15s,color .15s,background .15s}
.bsl a.bp:hover,.bsl a.bp:focus-visible{border-color:var(--o);color:var(--o);background:#FFF4EC}
.bsl a.bp.on{border-color:var(--o);background:var(--o);color:#fff}
@media(max-width:700px){.bsl{padding-top:10px}.bsl a.bp{height:32px;padding:0 13px;font-size:11.5px}}
</style>
<section class="bsl w" aria-label="Shop by Brand"><div class="bh2">Shop by Brand</div><div class="bt" id="bslT">
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
