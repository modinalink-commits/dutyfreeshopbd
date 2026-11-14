@php
 /* Best Sellers campaign slide (owner, 14 Nov 2026): the two flagship products with their live 2-carton offer price. Everything shown is read from the catalogue and the offer rule at render time:
    pack price (follows the purchase cost), the plain price of two cartons, the saving between them, free delivery only when the delivery rule really gives it. No slide when the offer is off or a product cannot be ordered. */
 $__bx = [];
 try {
   if (\App\Support\AutoPrice::on() && \App\Support\Procure::set('home_bestseller', '0') === '1') {
     foreach (\App\Http\Controllers\StoreController::bestSellers() as $__p) {
       $__t = \App\Support\BundlePricing::total((int)$__p->id, 2); $__pl = (float)$__p->price * 2;
       if (!$__t || $__t >= $__pl || (int)$__p->stock < 2 || !\App\Support\StockGuard::sellable($__p)) continue;
       $__bx[] = ['id' => (int)$__p->id, 'n' => (string)$__p->name, 'i' => $__p->bs_image ?: ($__p->image ?: '/product-placeholder'), 't' => (float)$__t, 'pl' => $__pl, 'u' => (float)$__p->price, 'fd' => \App\Support\Delivery::freeFor($__p)];
     }
   }
 } catch (\Throwable $e) { $__bx = []; }
 $__bfd = count($__bx) === 2 && $__bx[0]['fd'] && $__bx[1]['fd'];
@endphp
@if(count($__bx) === 2)
<div class="hxa hxbsl" aria-label="DutyFreeBD Best Sellers 2 carton offer">
<style>
.hxs .sl-t>.hxbsl{flex:0 0 100%}
.hxbsl{container-type:inline-size;position:relative;display:block;aspect-ratio:1440/480;overflow:hidden;color:#fff;background:radial-gradient(70% 140% at 78% 45%,#5A2408 0%,#2A1626 46%,#0B1220 100%);font-family:'Bornomala',Inter,sans-serif}
.hxbsl *{box-sizing:border-box}
.hxbsl .bg1{position:absolute;left:70cqw;top:50%;width:62cqw;height:62cqw;transform:translate(-50%,-50%);background:radial-gradient(closest-side,rgba(255,138,48,.42),rgba(255,138,48,0) 70%);animation:bslGlow 5s ease-in-out infinite alternate;pointer-events:none}
.hxbsl .bg2{position:absolute;top:0;bottom:0;left:-30%;width:16%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.1),transparent);transform:skewX(-18deg);animation:bslSweep 6.5s cubic-bezier(.4,0,.2,1) infinite;pointer-events:none}
.hxbsl .bl{position:absolute;left:4.6cqw;top:0;bottom:0;width:33cqw;display:flex;flex-direction:column;justify-content:center;gap:1.15cqw;z-index:3}
.hxbsl .bk{display:inline-flex;align-items:center;gap:.6cqw;font-family:Inter,sans-serif;font-size:max(9px,.95cqw);letter-spacing:.2em;font-weight:700;color:#FFC98A}
.hxbsl .bk svg{width:max(10px,1.1cqw);height:max(10px,1.1cqw)}
.hxbsl .bh{font-size:max(17px,3.9cqw);line-height:1.14;font-weight:700}.hxbsl .bh em{display:block;font-style:normal;color:#FFB347}
.hxbsl .bd{font-size:max(10px,1.25cqw);color:#E3E8F1;line-height:1.5}
.hxbsl .bt{display:flex;flex-wrap:wrap;gap:.7cqw}.hxbsl .bt span{font-size:max(9px,1.02cqw);font-weight:600;border:1px solid rgba(255,201,138,.5);color:#FFE2BD;border-radius:999px;padding:.28cqw 1cqw;background:rgba(255,255,255,.06)}
.hxbsl .bc{position:absolute;left:41cqw;right:3cqw;top:0;bottom:0;display:flex;align-items:center;justify-content:center;gap:1.7cqw;z-index:3}
.hxbsl .bp{position:relative;width:26.2cqw;display:flex;align-items:center;gap:1.1cqw;padding:1.2cqw 1.3cqw 1.2cqw 1.1cqw;border-radius:1.5cqw;background:#fff;color:#0B1220;text-decoration:none;box-shadow:0 2.2cqw 5cqw rgba(0,0,0,.5),0 0 0 .22cqw rgba(255,201,138,.75);overflow:hidden;animation:bslFloat 4.6s ease-in-out infinite}
.hxbsl .bp+.bp{animation-delay:-2.3s}
.hxbsl .bp:hover{box-shadow:0 2.2cqw 5cqw rgba(0,0,0,.5),0 0 0 .34cqw #FF5A00}
.hxbsl .pi{flex:none;width:9.6cqw;aspect-ratio:1;border-radius:1cqw;background:#F8F8F8;overflow:hidden}
.hxbsl .pi img{width:100%;height:100%;object-fit:contain;display:block}
.hxbsl .px{min-width:0;flex:1;display:flex;flex-direction:column;gap:.45cqw;font-family:Inter,sans-serif}
.hxbsl .pn{font-size:max(9px,1.08cqw);font-weight:600;line-height:1.25;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;padding-right:3.4cqw}
.hxbsl .pq{font-size:max(8px,.92cqw);font-weight:700;color:#C2410C;letter-spacing:.04em}
.hxbsl .pp{font-size:max(15px,2.5cqw);font-weight:800;line-height:1;font-variant-numeric:tabular-nums;white-space:nowrap}
.hxbsl .pe{font-size:max(8px,.95cqw);color:#475467;white-space:nowrap}.hxbsl .pe s{color:#9CA3AF}
.hxbsl .ps{align-self:flex-start;font-size:max(8px,.92cqw);font-weight:700;color:#067647;background:#DCFCE7;border-radius:999px;padding:.18cqw .8cqw;white-space:nowrap}
.hxbsl .pg{margin-top:.25cqw;display:flex;align-items:center;justify-content:center;height:max(22px,2.5cqw);border-radius:.8cqw;background:#FF5A00;color:#fff;font-family:'Bornomala',Inter,sans-serif;font-size:max(10px,1.2cqw);font-weight:700;animation:bslPulse 2.4s ease-in-out infinite}
.hxbsl .pr{position:absolute;top:0;right:0;width:6.2cqw;height:6.2cqw;overflow:hidden;pointer-events:none}
.hxbsl .pr i{position:absolute;top:1.15cqw;right:-2.2cqw;width:8.6cqw;transform:rotate(45deg);background:linear-gradient(180deg,#F03A3A,#C91414);color:#fff;font-family:Inter,sans-serif;font-style:normal;font-weight:800;font-size:max(7px,.86cqw);line-height:1.9;letter-spacing:.1em;text-align:center;box-shadow:0 .2cqw .5cqw rgba(0,0,0,.28)}
@keyframes bslFloat{50%{transform:translateY(-.9cqw)}}
@keyframes bslSweep{0%{left:-30%}60%,100%{left:130%}}
@keyframes bslGlow{to{opacity:.6;transform:translate(-50%,-50%) scale(1.08)}}
@keyframes bslPulse{50%{transform:scale(1.04)}}
@media(max-width:760px){
/* phone: the same 16:9 box as every other banner - headline on top, the two offers side by side under it */
.hxbsl,.hxs .sl-t>.hxbsl{aspect-ratio:16/9}
.hxbsl .bg1{left:50%;top:60%;width:120cqw;height:120cqw}
.hxbsl .bl{left:4cqw;right:4cqw;width:auto;top:3.6cqw;bottom:auto;gap:1cqw;align-items:center;text-align:center}
.hxbsl .bk,.hxbsl .bd{display:none}.hxbsl .bh{font-size:5cqw;line-height:1.2}.hxbsl .bh em{display:inline;margin-left:1.4cqw}
.hxbsl .bt{justify-content:center;gap:1.4cqw}.hxbsl .bt span{font-size:max(9px,2.5cqw);padding:.3cqw 2cqw}
.hxbsl .bc{left:3cqw;right:3cqw;top:17cqw;bottom:5.4cqw;gap:2.4cqw;align-items:stretch}
.hxbsl .bp{width:auto;flex:1 1 0;min-width:0;gap:1.6cqw;padding:1.6cqw 1.8cqw 1.6cqw 1.4cqw;border-radius:2.6cqw;animation:none}
.hxbsl .pi{width:15cqw;border-radius:1.8cqw}
.hxbsl .px{gap:.7cqw;justify-content:center}
.hxbsl .pn{font-size:max(9px,2.5cqw);-webkit-line-clamp:1;padding-right:6cqw}.hxbsl .pq{font-size:max(8px,2.1cqw)}
.hxbsl .pp{font-size:5.2cqw}.hxbsl .pe{font-size:max(8px,2.15cqw)}.hxbsl .ps{display:none}
.hxbsl .pg{height:6.4cqw;border-radius:1.6cqw;font-size:max(10px,3cqw);margin-top:.5cqw}
.hxbsl .pr{width:11cqw;height:11cqw}.hxbsl .pr i{top:2.1cqw;right:-3.9cqw;width:15cqw;font-size:max(7px,1.9cqw)}
}
@media(prefers-reduced-motion:reduce){.hxbsl .bg1,.hxbsl .bg2,.hxbsl .bp,.hxbsl .pg{animation:none!important}}
</style>
<span class="bg1"></span><span class="bg2"></span>
<span class="bl"><span class="bk"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2l-6.1 3.4 1.4-6.8L2.2 9.1l6.9-.8z"/></svg>DUTYFREEBD · BEST SELLERS</span><span class="bh">আমাদের বেস্ট সেলার<em>২ কার্টনে স্পেশাল দাম</em></span><span class="bd">সবচেয়ে বেশি বিক্রি হওয়া দুই ব্র্যান্ড, একসাথে ২ কার্টন নিলে কম দামে।</span><span class="bt"><span>২ কার্টন অফার</span>@if($__bfd)<span>ফ্রি ডেলিভারি</span>@endif<span>ক্যাশ অন ডেলিভারি</span></span></span>
<span class="bc">@foreach($__bx as $__b)<a class="bp" href="/checkout?add={{ $__b['id'] }}&p={{ $__b['u'] }}&q=2" rel="nofollow" aria-label="Order 2 cartons of {{ $__b['n'] }} for {{ number_format($__b['t']) }} taka"><span class="pr" aria-hidden="true"><i>SALE</i></span><span class="pi"><img src="{{ $__b['i'] }}" alt="{{ $__b['n'] }}" width="300" height="300" decoding="async" draggable="false" onerror="this.onerror=null;this.src='/product-placeholder'"></span><span class="px"><span class="pq">2 CARTONS</span><span class="pn">{{ $__b['n'] }}</span><span class="pp">৳{{ number_format($__b['t']) }}</span><span class="pe">৳{{ number_format($__b['t'] / 2) }} each · <s>৳{{ number_format($__b['pl']) }}</s></span><span class="ps">Save ৳{{ number_format($__b['pl'] - $__b['t']) }}</span><span class="pg">অর্ডার করুন &rsaquo;</span></span></a>@endforeach</span>
</div>
@endif
