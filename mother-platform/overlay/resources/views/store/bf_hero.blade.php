<?php [$__hA, $__hB] = \App\Support\BlackFriday::window(); $__hEnd = $__hB->timestamp * 1000; $__hImgs = ['perfume', 'skincare', 'fashion', 'sunglasses', 'watches', 'gadgets', 'baby-care', 'daily-needs', 'chocolates', 'hair-body']; ?>
<section class="bfh" aria-label="Black Friday" data-end="{{ $__hEnd }}">
<div class="bfh-in">
<div class="bfh-l">
<span class="bfh-k">{{ $__hA->format('j') }}–{{ $__hB->format('j F Y') }} · DutyFree Shop</span>
<h1 class="bfh-h"><span>BLACK</span><span>FRIDAY</span></h1>
<p class="bfh-s">Offer ends in</p>
<div class="bfh-cd" role="timer" aria-live="off">
<div><b data-u="d">00</b><i>Days</i></div><em>:</em><div><b data-u="h">00</b><i>Hours</i></div><em>:</em><div><b data-u="m">00</b><i>Minutes</i></div><em>:</em><div><b data-u="s">00</b><i>Seconds</i></div>
</div>
<a class="bfh-b" href="/black-friday">See the offers &rarr;</a>
</div>
<div class="bfh-r" aria-hidden="true">@foreach($__hImgs as $__i => $__n)<img src="/bf/kit/kit-{{ $__n }}.jpg" alt="" width="540" height="540" @if($__i > 0) loading="lazy" @endif class="{{ $__i === 0 ? 'on' : '' }}">@endforeach</div>
</div>
</section>
<style>
.bfh{display:block}
.bfh-in{position:relative;display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);align-items:center;gap:24px;background:#0C161F;color:#fff;overflow:hidden;padding:18px 36px;border-left:10px solid #F95505;isolation:isolate;aspect-ratio:1440/480}
.bfh-in:before{content:"";position:absolute;inset:-40% -20% auto auto;width:70%;height:180%;background:radial-gradient(closest-side,rgba(249,85,5,.38),transparent);z-index:-1;animation:bfhG 6s ease-in-out infinite alternate}
.bfh-k{display:block;font-size:13px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#FDC2A5}
.bfh-h{margin:4px 0 2px;font-size:clamp(34px,4.4vw,64px);line-height:.95;font-weight:900;letter-spacing:.02em;display:flex;flex-wrap:wrap;gap:0 .3em}
.bfh-h span{display:block;animation:bfhIn .9s cubic-bezier(.2,.8,.2,1) both}.bfh-h span+span{color:#F95505;animation-delay:.18s}
.bfh-s{margin:8px 0 6px;font-size:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#D6DEEA}
.bfh-cd{display:flex;align-items:flex-start;gap:6px 8px;flex-wrap:nowrap}
.bfh-cd div{display:flex;flex-direction:column;align-items:center;min-width:0;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.16);border-radius:12px;padding:6px 10px 5px;min-width:clamp(56px,6.4vw,92px)}
.bfh-cd b{font-size:clamp(30px,4.6vw,60px);line-height:1;font-weight:900;font-variant-numeric:tabular-nums;letter-spacing:.02em}
.bfh-cd i{font-style:normal;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#FDC2A5;margin-top:4px}
.bfh-cd em{font-style:normal;font-size:clamp(24px,3.6vw,46px);font-weight:900;line-height:1.15;color:#F95505;animation:bfhB 1s steps(2,end) infinite}
.bfh-cd div:last-of-type b{color:#F95505}
.bfh-b{display:inline-flex;align-items:center;margin-top:12px;min-height:42px;padding:0 24px;border-radius:999px;background:#F95505;color:#fff;font-weight:800;font-size:15px;text-decoration:none;animation:bfhP 2.2s ease-in-out infinite}
.bfh-r{position:relative;aspect-ratio:1/1;width:100%;max-height:100%;max-width:min(100%,380px);height:100%;justify-self:center;border-radius:12px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.45)}
.bfh-r img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transform:scale(1.06);transition:opacity 1s ease,transform 4.5s ease}
.bfh-r img.on{opacity:1;transform:scale(1)}
@keyframes bfhIn{from{opacity:0;transform:translateX(-40px)}to{opacity:1;transform:none}}
@keyframes bfhB{50%{opacity:.25}}
@keyframes bfhP{50%{transform:scale(1.05);box-shadow:0 0 0 8px rgba(249,85,5,.2)}}
@keyframes bfhG{to{transform:translate(-8%,6%) scale(1.1)}}
@media(max-width:1000px){.bfh-in{aspect-ratio:auto;padding:22px 20px}.bfh-r{height:auto;max-height:none;max-width:300px}}
@media(max-width:760px){.bfh-in{aspect-ratio:auto;grid-template-columns:1fr;padding:22px 14px;border-left-width:5px;gap:16px}.bfh-r{max-width:260px}.bfh-cd div{padding:8px 6px 6px;min-width:0;flex:1}.bfh-cd{gap:3px}.bfh-cd em{display:none}.bfh-b{width:100%;justify-content:center}}
@media(prefers-reduced-motion:reduce){.bfh *,.bfh:before,.bfh-in:before{animation:none!important;transition:none!important}}
</style>
<script>(function(){var r=document.querySelector('.bfh');if(!r)return;var end=+r.getAttribute('data-end'),u={};['d','h','m','s'].forEach(function(k){u[k]=r.querySelector('[data-u="'+k+'"]')});function p(n){return n<10?'0'+n:''+n}
function t(){var d=Math.max(0,end-Date.now()),s=Math.floor(d/1000);u.d.textContent=p(Math.floor(s/86400));u.h.textContent=p(Math.floor(s%86400/3600));u.m.textContent=p(Math.floor(s%3600/60));u.s.textContent=p(s%60)}t();setInterval(t,1000);
var im=r.querySelectorAll('.bfh-r img'),i=0;if(im.length>1)setInterval(function(){im[i].classList.remove('on');i=(i+1)%im.length;im[i].classList.add('on')},3200)})();</script>
