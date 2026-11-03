@php
  $__d = collect($deals ?? [])->take(12);
  $__logo = ($s['logo_url'] ?? '') ?: '/logo.webp';
  [$__end, $__fix] = \App\Http\Controllers\StoreController::endAt($s['deal_end_at'] ?? '');
  $__dt = \Carbon\Carbon::now('Asia/Dhaka');
  $__fl = 'DFB '.$__dt->format('md');
  mt_srand(crc32($__fl)); $__bars = []; $__x = 0;
  for ($i = 0; $i < 64; $i++) { $w = mt_rand(1, 4); if ($i % 2 === 0) $__bars[] = [$__x, $w]; $__x += $w + (($i % 2) ? 0 : 0); }
@endphp
@if($__d->count())
<style>
.bpx{--o:#FF5A00;--n:#0B1220;--pg:var(--bg,#FFFBF8);position:relative;display:grid;grid-template-columns:minmax(0,1fr) 272px;filter:drop-shadow(0 14px 30px rgba(11,18,32,.14)) drop-shadow(0 2px 4px rgba(11,18,32,.06));margin:18px 0;font-family:inherit}
.bpx *{box-sizing:border-box}
.bpx-m,.bpx-s{position:relative;background:#FFFDFA}
.bpx-m{border-radius:20px 0 0 20px;overflow:hidden;display:flex;flex-direction:column;min-width:0}
.bpx-m:before{content:"";position:absolute;left:-14px;top:50%;width:28px;height:28px;margin-top:-14px;border-radius:50%;background:var(--pg);z-index:4}
.bpx-wm{position:absolute;right:4%;top:46%;width:300px;height:326px;margin-top:-163px;background:url(/pwa/logo-240.png) center/contain no-repeat;opacity:.045;pointer-events:none}
.bpx-gl{position:absolute;inset:0;background:repeating-radial-gradient(circle at 100% 0,transparent 0 9px,rgba(255,90,0,.035) 9px 10px);pointer-events:none}
.bpx-hd{position:relative;display:flex;align-items:center;gap:18px;padding:12px 24px;background:linear-gradient(100deg,#FF5A00 0%,#FF6F1F 70%,#FF8240 100%);color:#fff;min-height:68px;overflow:hidden}
.bpx-hd:after{content:"";position:absolute;right:-20px;top:-10px;bottom:-10px;width:190px;background:repeating-linear-gradient(115deg,transparent 0 14px,rgba(255,255,255,.18) 14px 24px)}
.bpx-lg{background:#fff;border-radius:12px;padding:7px 14px;display:flex;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.15);position:relative;z-index:1}.bpx-lg img{height:34px;width:auto;display:block}
.bpx-bp{position:relative;z-index:1;display:flex;flex-direction:column;line-height:1.1}.bpx-bp b{font-size:20px;letter-spacing:.34em;font-weight:800}.bpx-bp small{font-size:10.5px;letter-spacing:.3em;opacity:.9;margin-top:4px;font-weight:600}
.bpx-cl{margin-left:auto;position:relative;z-index:1;background:var(--n);color:#fff;border-radius:30px;padding:7px 16px;font-size:11px;font-weight:800;letter-spacing:.2em;display:flex;align-items:center;gap:8px;margin-right:130px}.bpx-cl svg{width:15px;height:15px;fill:#FF8A4C}
.bpx-info{position:relative;padding:20px 28px 4px;display:grid;grid-template-columns:auto minmax(0,1fr);gap:8px 34px;align-items:end}
.bpx-ti{grid-column:1/-1;display:flex;align-items:baseline;gap:16px;flex-wrap:wrap}
.bpx-ti h2{margin:0;font-size:40px;line-height:1;font-weight:800;color:var(--n);letter-spacing:-.02em}.bpx-ti h2 b{color:var(--o)}
.bpx-ti em{font-style:italic;color:#6B7280;font-size:14.5px}
.bpx-rt{display:flex;align-items:center;gap:14px;min-width:0}
.bpx-rt .cd{line-height:1}.bpx-rt strong{display:block;font-size:34px;font-weight:800;color:var(--n);letter-spacing:.02em}.bpx-rt small{display:block;font-size:9.5px;letter-spacing:.18em;color:#6B7280;font-weight:700;margin-top:5px}
.bpx-ln{position:relative;flex:1;min-width:90px;height:22px}.bpx-ln:before{content:"";position:absolute;left:0;right:0;top:50%;border-top:2px dotted #C9B8AC}
.bpx-ln i{position:absolute;top:-1px;left:0;width:24px;height:24px;background:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path fill='%23FF5A00' transform='rotate(90 12 12)' d='M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5z'/></svg>") center/contain no-repeat;animation:bpxfly 5.5s ease-in-out infinite}
@keyframes bpxfly{0%{left:0;opacity:0}10%{opacity:1}90%{opacity:1}100%{left:calc(100% - 24px);opacity:0}}
.bpx-fd{display:grid;grid-template-columns:repeat(4,auto);gap:6px 26px;justify-content:end}
.bpx-fd div{min-width:0}.bpx-fd small{display:block;font-size:9px;letter-spacing:.18em;color:#6B7280;font-weight:700}.bpx-fd b{display:block;font-size:15px;color:var(--n);font-weight:800;margin-top:2px;white-space:nowrap}.bpx-fd b.o{color:var(--o)}
.bpx-lb{position:relative;padding:14px 28px 0;display:flex;align-items:center;gap:12px;font-size:10.5px;letter-spacing:.22em;font-weight:800;color:#6B7280}.bpx-lb:after{content:"";flex:1;border-top:1px dashed #E3D6CC}.bpx-lb span{white-space:nowrap}.bpx-lb .n{color:var(--n);letter-spacing:.1em;font-size:12px}
.bpx-sl{position:relative;padding:8px 28px 6px}
.bpx-tr{display:flex;gap:14px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;padding:12px 2px 10px;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain}.bpx-tr::-webkit-scrollbar{display:none}
.bpx-c{flex:0 0 calc((100% - 28px)/3);scroll-snap-align:start;min-width:0;background:#fff;border:1px solid #EFE5DD;border-radius:16px;padding:12px 12px 14px;position:relative;display:flex;flex-direction:column;transition:transform .25s,box-shadow .25s,border-color .25s}
.bpx-c:hover{transform:translateY(-3px);box-shadow:0 12px 24px rgba(11,18,32,.12);border-color:#FFC7A5}
.bpx-c .im{display:flex;align-items:center;justify-content:center;height:150px;background:linear-gradient(#FFF8F3,#fff);border-radius:12px}.bpx-c .im img{max-width:88%;max-height:136px;object-fit:contain}
.bpx-c .st{position:absolute;right:-8px;top:-10px;width:54px;height:54px;border-radius:50%;background:var(--o);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;transform:rotate(10deg);box-shadow:0 4px 10px rgba(255,90,0,.4)}.bpx-c .st:before{content:"";position:absolute;inset:3px;border-radius:50%;border:1.5px dashed rgba(255,255,255,.7)}
.bpx-c .k{margin-top:10px;font-size:9.5px;letter-spacing:.2em;color:#C2410C;font-weight:800}
.bpx-c h3{margin:4px 0 6px;font-size:14.5px;line-height:1.3;font-weight:700;min-height:2.6em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.bpx-c h3 a{color:var(--n);text-decoration:none}
.bpx-c .pp{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;font-size:22px;font-weight:800;color:var(--o)}.bpx-c .pp s{font-size:12.5px;font-weight:500;color:#6B7280}
.bpx-c .sv{font-size:11.5px;font-weight:800;color:#15803D;margin:3px 0 10px}
.bpx-c .bt{margin-top:auto;text-align:center;text-decoration:none;background:var(--n);color:#fff;border-radius:10px;padding:10px;font-size:12.5px;font-weight:800;letter-spacing:.08em;transition:background .2s}.bpx-c .bt:hover{background:var(--o)}
.bpx-ar{position:absolute;top:50%;z-index:6;width:42px;height:42px;margin-top:-26px;border-radius:50%;border:1px solid #E5E7EB;background:#fff;color:var(--n);font-size:22px;font-weight:800;box-shadow:0 4px 12px rgba(0,0,0,.16);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;line-height:1}.bpx-ar.l{left:10px}.bpx-ar.r{right:10px;background:var(--o);border-color:var(--o);color:#fff}
.bpx-ft{position:relative;display:flex;align-items:center;gap:16px;margin:0 28px;padding:10px 0 16px;border-top:1px solid #F1E8E1;font-size:10.5px;letter-spacing:.2em;color:#6B7280;font-weight:700}.bpx-ft i{flex:1}.bpx-ft .c{color:var(--n)}.bpx-ft .pg{font-variant-numeric:tabular-nums;color:var(--o)}
.bpx-s{border-radius:0 20px 20px 0;display:flex;flex-direction:column;overflow:hidden}
.bpx-s:before{content:"";position:absolute;left:0;top:0;bottom:0;width:14px;background:radial-gradient(circle at 0 50%,var(--pg) 0 4.5px,transparent 5px) 0 0/14px 18px repeat-y;z-index:3}
.bpx-s:after{content:"";position:absolute;left:14px;top:14px;bottom:14px;border-left:2px dashed #E7D9CE}
.bpx-nt,.bpx-nb{position:absolute;left:-14px;width:28px;height:28px;border-radius:50%;background:var(--pg);z-index:5}.bpx-nt{top:-14px}.bpx-nb{bottom:-14px}
.bpx-sh{background:linear-gradient(100deg,#FF5A00,#FF7A2A);color:#fff;padding:12px 18px 12px 30px;min-height:68px;display:flex;align-items:center;gap:12px}.bpx-sh .ic{width:44px;height:44px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;flex:none;box-shadow:0 2px 8px rgba(0,0,0,.18)}.bpx-sh .ic img{width:25px;height:27px;display:block}.bpx-sh b{display:block;font-size:19px;font-weight:800;letter-spacing:-.01em;line-height:1.1}.bpx-sh small{display:block;font-size:9.5px;letter-spacing:.24em;font-weight:700;opacity:.92;margin-top:3px}
.bpx-sb{padding:16px 20px 16px 32px;display:flex;flex-direction:column;gap:12px;flex:1;position:relative;z-index:1}
.bpx-pr{display:grid;grid-template-columns:1fr 1fr;gap:8px}.bpx-pr small{display:block;font-size:9px;letter-spacing:.18em;color:#6B7280;font-weight:700}.bpx-pr b{display:block;font-size:14px;font-weight:800;color:var(--n);margin-top:2px}
.bpx-cdb{text-align:center;border:1px dashed #F2B995;background:#FFF5EC;border-radius:12px;padding:9px 6px 10px}.bpx-cdb>small{display:block;font-size:9.5px;letter-spacing:.2em;color:#9A3412;font-weight:800;margin-bottom:6px}
.bpx-t{display:flex;justify-content:center;gap:6px;align-items:flex-start}.bpx-t div{background:var(--n);color:#fff;border-radius:8px;width:50px;padding:6px 0 4px;text-align:center}.bpx-t b{display:block;font-size:22px;line-height:1;font-variant-numeric:tabular-nums;font-weight:800}.bpx-t small{display:block;font-size:8px;letter-spacing:.14em;opacity:.7;margin-top:3px;font-weight:600}.bpx-t u{text-decoration:none;color:var(--o);font-weight:800;font-size:20px;line-height:36px}
.bpx-bc{margin-top:auto}.bpx-bc svg{display:block;width:100%;height:56px}.bpx-bc span{display:flex;justify-content:space-between;font-size:10px;letter-spacing:.14em;color:#4B5563;font-weight:700;margin-top:6px}.bpx-bc b{color:var(--n)}
.bpx-all{text-align:center;text-decoration:none;border:1.5px solid var(--o);color:#C2410C;border-radius:10px;padding:9px;font-size:12px;font-weight:800;letter-spacing:.1em;transition:background .2s,color .2s}.bpx-all:hover{background:var(--o);color:#fff}
@media(max-width:1100px){.bpx{grid-template-columns:minmax(0,1fr) 236px}.bpx-c{flex-basis:calc((100% - 14px)/2)}.bpx-fd{grid-template-columns:repeat(2,auto)}.bpx-cl{margin-right:90px}}
@media(max-width:860px){
.bpx{grid-template-columns:1fr;margin:12px 0;filter:drop-shadow(0 8px 18px rgba(11,18,32,.14))}
.bpx-m{border-radius:18px 18px 0 0}.bpx-m:before{display:none}.bpx-wm{width:190px;height:207px;margin-top:-100px;right:-20px}
.bpx-hd{padding:10px 14px;gap:12px;min-height:56px}.bpx-hd:after{width:90px}.bpx-lg{padding:5px 10px;border-radius:9px}.bpx-lg img{height:26px}.bpx-bp b{font-size:14px;letter-spacing:.26em}.bpx-bp small{display:none}.bpx-cl{display:none}
.bpx-info{padding:12px 14px 2px;grid-template-columns:1fr;gap:10px}.bpx-ti{gap:6px 10px}.bpx-ti h2{font-size:27px}.bpx-ti em{font-size:12.5px;width:100%}
.bpx-rt strong{font-size:26px}.bpx-rt small{font-size:8.5px}.bpx-fd{display:none}
.bpx-lb{padding:12px 14px 0;font-size:9.5px}.bpx-sl{padding:4px 14px 2px}
.bpx-tr{gap:10px}.bpx-c{flex-basis:100%;display:grid;grid-template-columns:42% minmax(0,1fr);column-gap:10px;padding:10px 12px 10px 10px;align-items:center}
.bpx-c .im{grid-row:1/6;height:140px}.bpx-c .im img{max-height:128px}.bpx-c .k{margin-top:0}.bpx-c h3{min-height:0;font-size:14px;margin:2px 0 4px}.bpx-c .pp{font-size:20px}.bpx-c .sv{margin:2px 0 8px}.bpx-c .st{right:auto;left:-6px;top:-8px;width:46px;height:46px;font-size:12.5px;transform:rotate(-10deg)}
.bpx-ar{width:34px;height:34px;font-size:18px;margin-top:-22px}.bpx-ar.l{left:4px}.bpx-ar.r{right:4px}
.bpx-ft{margin:0 14px;font-size:9px;letter-spacing:.12em;padding:8px 0 12px}.bpx-ft .hid{display:none}
.bpx-s{border-radius:0 0 18px 18px}
.bpx-s:before{left:0;right:0;top:0;bottom:auto;width:auto;height:14px;background:radial-gradient(circle at 50% 0,var(--pg) 0 4.5px,transparent 5px) 0 0/18px 14px repeat-x}
.bpx-s:after{left:14px;right:14px;top:14px;bottom:auto;border-left:0;border-top:2px dashed #E7D9CE}
.bpx-nt,.bpx-nb{top:-14px;bottom:auto}.bpx-nt{left:-14px}.bpx-nb{left:auto;right:-14px}
.bpx-sh{padding:12px 14px 8px;min-height:0;display:none}
.bpx-sb{padding:24px 14px 14px;display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:stretch}
.bpx-pr{display:none}.bpx-cdb{grid-column:1/-1}.bpx-t div{width:58px}.bpx-bc{grid-column:1;margin:0}.bpx-all{grid-column:2;display:flex;align-items:center;justify-content:center}
}
@media(prefers-reduced-motion:reduce){.bpx-ln i{animation:none;left:calc(50% - 12px);opacity:1}}
</style>
<div class="bpx" data-end="{{ $__fix ? $__end * 1000 : '' }}">
<div class="bpx-m"><div class="bpx-gl"></div><div class="bpx-wm"></div>
<div class="bpx-hd"><span class="bpx-lg"><img src="{{ $__logo }}" alt="DutyFreeBD" width="140" height="34"></span><span class="bpx-bp"><b>BOARDING PASS</b><small>DUTY FREE CLASS · EXCLUSIVE</small></span><span class="bpx-cl"><svg viewBox="0 0 24 24"><path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5z"/></svg>LIMITED DEALS</span></div>
<div class="bpx-info"><div class="bpx-ti"><h2>Today's <b>Deal</b></h2><em>Board now for limited-time offers</em></div>
<div class="bpx-rt"><div class="cd"><strong>DFB</strong><small>DUTYFREEBD</small></div><span class="bpx-ln"><i></i></span><div class="cd"><strong>YOU</strong><small>YOUR DOOR · BD</small></div></div>
<div class="bpx-fd"><div><small>PASSENGER</small><b>VALUED CUSTOMER</b></div><div><small>FLIGHT</small><b>{{ $__fl }}</b></div><div><small>DATE</small><b>{{ strtoupper($__dt->format('d M Y')) }}</b></div><div><small>BOARDING</small><b class="o">NOW</b></div></div></div>
<div class="bpx-lb"><span>ITEMS ON BOARD</span><span class="n"><span class="pg">01</span> / {{ str_pad($__d->count(), 2, '0', STR_PAD_LEFT) }}</span></div>
<div class="bpx-sl"><button type="button" class="bpx-ar l" aria-label="Previous">&#8249;</button><div class="bpx-tr">@foreach($__d as $dd)@php $du = \App\Http\Controllers\StoreController::url($dd); $dof = \App\Http\Controllers\StoreController::off($dd); $dsv = max(0, (float)$dd->compare_price - (float)$dd->price); @endphp<div class="bpx-c">@if($dof > 0)<span class="st">-{{ $dof }}%</span>@endif<a class="im" href="{{ $du }}"><img src="{{ $dd->image ?: '/product-placeholder' }}" alt="{{ $dd->name }}" width="240" height="240" loading="lazy" draggable="false"></a><span class="k">LIMITED DEAL</span><h3><a href="{{ $du }}">{{ $dd->name }}</a></h3><div class="pp">{{ number_format($dd->price) }} TK @if($dd->compare_price > $dd->price)<s>{{ number_format($dd->compare_price) }} TK</s>@endif</div>@if($dsv > 0)<span class="sv">YOU SAVE {{ number_format($dsv) }} TK</span>@else<span class="sv">&nbsp;</span>@endif<a class="bt" href="/checkout?add={{ $dd->id }}&amp;p={{ (float)$dd->price }}" rel="nofollow">ORDER NOW</a></div>@endforeach</div><button type="button" class="bpx-ar r" aria-label="Next">&#8250;</button></div>
<div class="bpx-ft"><span class="c">DUTYFREEBD.COM</span><i></i><span class="hid">100% ORIGINAL</span><span class="hid">&bull;</span><span>CASH ON DELIVERY</span></div>
</div>
<div class="bpx-s"><span class="bpx-nt"></span><span class="bpx-nb"></span>
<div class="bpx-sh"><span class="ic"><img src="/pwa/logo-240.png" alt="" width="25" height="27"></span><div><b>DutyFreeBD</b><small>BOARDING PASS</small></div></div>
<div class="bpx-sb">
<div class="bpx-pr"><div><small>FROM</small><b>DFB</b></div><div><small>TO</small><b>YOUR DOOR</b></div><div><small>GATE</small><b>D10</b></div><div><small>CLASS</small><b>DUTY FREE</b></div></div>
<div class="bpx-cdb"><small>BOARDING CLOSES IN</small><div class="bpx-t"><div><b data-h>00</b><small>HRS</small></div><u>:</u><div><b data-m>00</b><small>MIN</small></div><u>:</u><div><b data-s>00</b><small>SEC</small></div></div></div>
<div class="bpx-bc"><svg viewBox="0 0 {{ $__x }} 40" preserveAspectRatio="none" aria-hidden="true">@foreach($__bars as $b)<rect x="{{ $b[0] }}" y="0" width="{{ $b[1] }}" height="40" fill="#0B1220"/>@endforeach</svg><span><b>{{ $__fl }}</b><span>TKT {{ $__dt->format('dmY') }}0010</span></span></div>
<a class="bpx-all" href="/deals">VIEW ALL DEALS &rarr;</a>
</div></div>
</div>
<script>(function(){var r=document.currentScript.previousElementSibling;if(!r||!r.classList.contains('bpx'))return;var tr=r.querySelector('.bpx-tr'),L=r.querySelector('.bpx-ar.l'),R=r.querySelector('.bpx-ar.r'),pg=r.querySelector('.pg'),kids=[].slice.call(tr.children),paused=false,vis=true,rm=matchMedia('(prefers-reduced-motion: reduce)').matches;
function step(){var c=kids[0];return c.getBoundingClientRect().width+(parseFloat(getComputedStyle(tr).columnGap)||14)}
function idx(){return Math.round(tr.scrollLeft/step())}
function upd(){if(pg)pg.textContent=String(Math.min(kids.length,idx()+1)).padStart(2,'0')}
function nx(){if(tr.scrollLeft+tr.clientWidth>=tr.scrollWidth-4)tr.scrollTo({left:0,behavior:'smooth'});else tr.scrollBy({left:step(),behavior:'smooth'})}
function pv(){if(tr.scrollLeft<=2)tr.scrollTo({left:tr.scrollWidth,behavior:'smooth'});else tr.scrollBy({left:-step(),behavior:'smooth'})}
L.onclick=pv;R.onclick=nx;tr.addEventListener('scroll',upd,{passive:true});
r.addEventListener('mouseenter',function(){paused=true});r.addEventListener('mouseleave',function(){paused=false});r.addEventListener('touchstart',function(){paused=true},{passive:true});r.addEventListener('touchend',function(){setTimeout(function(){paused=false},4500)},{passive:true});
if('IntersectionObserver' in window)new IntersectionObserver(function(e){vis=e[0].isIntersecting}).observe(r);
if(!rm)setInterval(function(){if(!paused&&vis&&!document.hidden&&tr.scrollWidth>tr.clientWidth+2)nx()},4200);
var ea=r.getAttribute('data-end'),H=r.querySelector('[data-h]'),M=r.querySelector('[data-m]'),S=r.querySelector('[data-s]'),f=function(x){return(x<10?'0':'')+x};
function tick(){var n=Date.now(),d=new Date(n+6*36e5),end=ea?+ea:Date.UTC(d.getUTCFullYear(),d.getUTCMonth(),d.getUTCDate()+1)-6*36e5,s=Math.max(0,Math.floor((end-n)/1e3));H.textContent=f(Math.floor(s/3600));M.textContent=f(Math.floor(s%3600/60));S.textContent=f(s%60)}
tick();setInterval(tick,1000);upd()})();</script>
@endif
