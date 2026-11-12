<style>
/* 6-product animated showcase: transform/opacity only, fixed aspect ratio (no layout shift), paused when off screen */
.sxw{padding-top:22px;padding-bottom:26px}
.sxr{display:flex;gap:8px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;-webkit-overflow-scrolling:touch}.sxr::-webkit-scrollbar{display:none}.sxc{flex:0 0 calc((100% - 32px)/5);scroll-snap-align:start}.sxd{display:flex;justify-content:center;gap:5px;margin-top:10px}.sxd i{width:5px;height:5px;border-radius:50%;background:#D1D5DB;transition:background .2s,width .2s}.sxd i.on{background:#FF5A00;width:14px;border-radius:3px}
.sxc{position:relative;display:block;aspect-ratio:941/1672;border-radius:14px;overflow:hidden;border:1px solid color-mix(in srgb,var(--c) 45%,#fff 0%);background:linear-gradient(165deg,color-mix(in srgb,var(--c) 55%,#0b1220) 0%,#0b1220 78%);isolation:isolate;opacity:0;transform:translateY(12px);transition:opacity .4s ease,transform .4s ease,border-color .2s;-webkit-tap-highlight-color:transparent}
.sxc.in{opacity:1;transform:none}
.sxc.in:hover{transform:translateY(-3px);border-color:color-mix(in srgb,var(--c) 85%,#fff)}
.sxg{position:absolute;left:-20%;top:12%;width:140%;height:70%;background:radial-gradient(closest-side,color-mix(in srgb,var(--c) 75%,#fff 10%),transparent 72%);opacity:.55;animation:sxg 9s ease-in-out infinite;z-index:0}
.sxo{position:absolute;left:50%;top:48%;width:92%;aspect-ratio:1;margin:-46% 0 0 -46%;border-radius:50%;border:2px solid transparent;background:conic-gradient(from 0deg,transparent 0 55%,color-mix(in srgb,var(--c) 70%,#fff) 80%,transparent 100%) border-box;-webkit-mask:linear-gradient(#000 0 0) padding-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;opacity:.45;animation:sxo 26s linear infinite;z-index:1}
.sxp{position:absolute;left:50%;top:50%;height:82%;width:auto;max-width:88%;object-fit:contain;transform:translate(-50%,-50%);z-index:2;filter:drop-shadow(0 14px 18px rgba(0,0,0,.45));animation:sxf 5.2s ease-in-out infinite;transition:scale .2s}
.sxc:hover .sxp{scale:1.035}
.sxs{position:absolute;inset:0;z-index:3;background:linear-gradient(105deg,transparent 40%,rgba(255,255,255,.16) 50%,transparent 60%);transform:translateX(-120%);animation:sxs 9s ease-in-out infinite;pointer-events:none}
@keyframes sxf{0%,100%{transform:translate(-50%,calc(-50% - 4px))}50%{transform:translate(-50%,calc(-50% + 4px))}}
@keyframes sxg{0%,100%{transform:translate(-6%,0) scale(1)}50%{transform:translate(6%,10%) scale(1.1)}}
@keyframes sxo{to{transform:rotate(360deg)}}
@keyframes sxs{0%,62%{transform:translateX(-120%)}86%,100%{transform:translateX(120%)}}
.sxr.pz .sxg,.sxr.pz .sxo,.sxr.pz .sxp,.sxr.pz .sxs{animation-play-state:paused}
.sxc.p .sxg,.sxc.p .sxo{display:none}.sxc.p .sxp{left:0;top:0;width:100%;height:100%;max-width:none;object-fit:cover;filter:none;transform:none;animation:sxk 16s ease-in-out infinite}.sxc.p:hover .sxp{scale:1.02}
@keyframes sxk{0%,100%{transform:scale(1)}50%{transform:scale(1.035)}}
.sxd{display:flex}
@media(max-width:1199px){.sxc{flex:0 0 calc((100% - 16px)/3)}.sxr{display:flex;gap:8px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;-webkit-overflow-scrolling:touch}.sxr::-webkit-scrollbar{display:none}.sxc{flex:0 0 calc((100% - 16px)/3);scroll-snap-align:start}
.sxd{display:flex;justify-content:center;gap:5px;margin-top:8px}.sxd i{width:5px;height:5px;border-radius:50%;background:#D1D5DB;transition:background .2s,width .2s}.sxd i.on{background:#FF5A00;width:14px;border-radius:3px}}
@media(max-width:700px){.sxw{padding-top:14px;padding-bottom:16px}.sxc{flex:0 0 calc((100% - 8px)/2);border-radius:12px}.sxg{animation-duration:12s}.sxo{display:none}}
@media(prefers-reduced-motion:reduce){.sxc{opacity:1;transform:none;transition:none}.sxg,.sxo,.sxp,.sxs{animation:none}.sxs{display:none}}
</style>
<div class="w sxw" id="sx"><div class="sxr" role="list">@foreach($showcase as $i => $sc)
<a class="sxc{{ ($sc['m'] ?? '') === 'poster' ? ' p' : '' }}" role="listitem" href="{{ $sc['u'] }}" data-sx="{{ $sc['n'] }}" data-pid="{{ $sc['pid'] }}" style="--c:{{ $sc['c'] }};transition-delay:{{ $i * 70 }}ms" aria-label="{{ $sc['title'] }}"><span class="sxg"></span><span class="sxo"></span><img class="sxp" src="{{ $sc['img'] }}?v={{ $sc['v'] }}" alt="{{ $sc['title'] }}" width="640" height="1137" @if($i < 3) fetchpriority="high" @else loading="lazy" @endif decoding="async"><span class="sxs"></span></a>
@endforeach</div><div class="sxd" aria-hidden="true">@foreach($showcase as $sc)<i></i>@endforeach</div></div>
<script>(function(){var w=document.getElementById('sx');if(!w)return;var r=w.querySelector('.sxr'),cs=[].slice.call(r.children),orig=cs.length;cs.forEach(function(c){var k=c.cloneNode(true);k.setAttribute('aria-hidden','true');k.setAttribute('tabindex','-1');k.classList.add('in');k.removeAttribute('data-sx');r.appendChild(k)});var all=[].slice.call(r.children),dots=[].slice.call(w.querySelectorAll('.sxd i')),dev=innerWidth<700?'mobile':innerWidth<1200?'tablet':'desktop',seen=false,paused=false,tm=null;
function dl(n,c){try{(window.dataLayer=window.dataLayer||[]).push(c)}catch(e){}}
function ev(name,c){dl(0,{event:name,slot_number:+c.getAttribute('data-sx'),product_id:+c.getAttribute('data-pid')||null,device_type:dev})}
cs.forEach(function(c){c.addEventListener('click',function(e){ev('showcase_click',c)})});
function show(){cs.forEach(function(c){c.classList.add('in')})}
function dot(){if(!dots.length)return;var w1=cs[0].getBoundingClientRect().width+8,i=Math.round(r.scrollLeft/w1)%orig;dots.forEach(function(d,k){d.classList.toggle('on',k===i)})}
r.addEventListener('scroll',dot,{passive:true});dot();
function wrap(){var w1=cs[0].getBoundingClientRect().width+8,L=w1*orig;if(r.scrollLeft>=L-2){r.style.scrollSnapType='none';r.scrollTo({left:r.scrollLeft-L,behavior:'auto'});requestAnimationFrame(function(){r.style.scrollSnapType=''})}}
function step(){if(paused||document.hidden)return;wrap();var w1=cs[0].getBoundingClientRect().width+8;r.scrollTo({left:r.scrollLeft+w1,behavior:'smooth'});setTimeout(wrap,800)}
function hold(){paused=true;clearTimeout(tm);tm=setTimeout(function(){paused=false},9000)}
['touchstart','pointerdown','wheel'].forEach(function(t){r.addEventListener(t,hold,{passive:true})});
var reduce=window.matchMedia&&matchMedia('(prefers-reduced-motion:reduce)').matches,timer=null;
if(!('IntersectionObserver' in window)){show();return}
new IntersectionObserver(function(es){es.forEach(function(e){var on=e.isIntersecting;r.classList.toggle('pz',!on);if(on){show();if(!seen){seen=true;cs.forEach(function(c){ev('showcase_impression',c)})}if(!reduce&&!timer)timer=setInterval(step,3500)}else if(timer){clearInterval(timer);timer=null}})},{threshold:.15}).observe(w);
})();</script>
