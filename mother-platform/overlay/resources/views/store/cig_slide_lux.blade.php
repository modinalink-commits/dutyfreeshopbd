@php
 /* Premium cigarette hero slide: the highest-priced in-stock cigarettes (no vape, no cigars). Live catalogue data only; never shown on the tobacco-free site. */
 $__cx = \App\Support\CleanSite::on() ? [] : \App\Http\Controllers\StoreController::rem('store.ciglux1', 900, function () {
   try {
     $D = \Illuminate\Support\Facades\DB::class;
     $ids = $D::table('categories')->get(['id', 'slug', 'name'])->filter(fn($c) => \App\Http\Controllers\StoreController::isSmokeCat($c))->pluck('id')->all(); if (!$ids) return [];
     $q = \App\Support\StockGuard::listable($D::table('products')->whereIn('products.category_id', $ids)->where('products.published', true)->where('products.price', '>', 0)->whereNotNull('products.image')->where('products.image', '!=', ''));
     $rows = $q->orderByDesc('products.price')->limit(60)->get(['products.name', 'products.price', 'products.image']);
     return $rows->reject(fn($r) => preg_match('/puff|vape|pod|cigar(?!ette)|lighter|paper|ভেপ/iu', (string)$r->name) === 1)->take(8)->map(fn($r) => ['n' => $r->name, 'p' => (int)$r->price, 'i' => $r->image])->values()->all();
   } catch (\Throwable $e) { return []; }
 });
 $__cn = count($__cx); $__cs = 3.4; $__cp = $__cn ? 100 / $__cn : 100;
 $__ck = fn($f) => round($__cp * $f, 3);
@endphp
@if($__cn >= 5)
<a class="hxa hxcg" href="/cigarette-vape/?sort=price_desc" aria-label="DutyFreeBD Premium Cigarette Collection">
<style>
.hxcg{container-type:inline-size;position:relative;display:block;aspect-ratio:1440/480;overflow:hidden;color:#fff;text-decoration:none;background:radial-gradient(80% 150% at 70% 50%,#3A1A14 0%,#1B1524 48%,#0B1220 100%);font-family:'Bornomala',Inter,sans-serif}
.hxcg *{box-sizing:border-box}
.hxcg .cg{position:absolute;left:68cqw;top:50%;width:50cqw;height:50cqw;transform:translate(-50%,-50%);background:radial-gradient(closest-side,rgba(245,201,138,.3),rgba(245,201,138,0) 70%);animation:cgGlow 5s ease-in-out infinite alternate;pointer-events:none}
.hxcg .cs{position:absolute;top:0;bottom:0;left:-30%;width:18%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.09),transparent);transform:skewX(-18deg);animation:cgSweep 7s cubic-bezier(.4,0,.2,1) infinite;pointer-events:none}
.hxcg .cl{position:absolute;left:5cqw;top:0;bottom:0;width:31cqw;display:flex;flex-direction:column;justify-content:center;gap:1.2cqw;z-index:6}
.hxcg .ck{font-size:max(9px,.95cqw);letter-spacing:.22em;font-weight:600;color:#F5C98A}
.hxcg .ch{font-size:max(16px,4cqw);line-height:1.15;font-weight:700}.hxcg .ch em{display:block;font-style:normal;color:#FFB347}
.hxcg .cd{font-size:max(10px,1.25cqw);color:#D6DCE8;line-height:1.5}
.hxcg .cb{align-self:flex-start;display:inline-flex;align-items:center;height:max(26px,3cqw);padding:0 max(14px,1.8cqw);border-radius:999px;background:#FF5A00;border:1.5px solid #0B1220;outline:1px solid rgba(255,179,71,.6);font-size:max(11px,1.3cqw);font-weight:700;color:#fff;animation:cgPulse 2.4s ease-in-out infinite}
.hxcg .cst{position:absolute;left:40cqw;right:0;top:0;bottom:0;-webkit-mask-image:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent);mask-image:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent)}
.hxcg .cc{position:absolute;left:50%;top:50%;width:17cqw;padding:1cqw 1cqw 1.2cqw;border-radius:1.4cqw;background:#fff;color:#0B1220;display:flex;flex-direction:column;align-items:center;gap:.7cqw;text-align:center;box-shadow:0 2cqw 5cqw rgba(0,0,0,.5),0 0 0 .25cqw rgba(245,201,138,.7);opacity:0;animation:cgOrbit {{ round($__cn * $__cs, 2) }}s infinite cubic-bezier(.5,0,.2,1)}
.hxcg .ci{width:100%;aspect-ratio:1;border-radius:1cqw;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden}
.hxcg .ci img{width:100%;height:100%;object-fit:contain;display:block}
.hxcg .cn{font-family:Inter,sans-serif;font-size:max(9px,1.05cqw);font-weight:600;line-height:1.3;height:2.6em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.hxcg .cp{font-family:Inter,sans-serif;font-size:max(11px,1.5cqw);font-weight:700;color:#fff;background:#0B1220;border-radius:999px;padding:.3cqw 1.3cqw}
@keyframes cgOrbit{0%{opacity:0;transform:translate(-50%,-50%) translateX(36cqw) scale(.5);z-index:1}{{ $__ck(.4) }}%,{{ $__ck(1) }}%{opacity:.8;transform:translate(-50%,-50%) translateX(19cqw) scale(.7);z-index:2}{{ $__ck(1.4) }}%,{{ $__ck(2) }}%{opacity:1;transform:translate(-50%,-50%) translateX(0) scale(1);z-index:4}{{ $__ck(2.4) }}%,{{ $__ck(3) }}%{opacity:.8;transform:translate(-50%,-50%) translateX(-19cqw) scale(.7);z-index:2}{{ $__ck(3.4) }}%,100%{opacity:0;transform:translate(-50%,-50%) translateX(-36cqw) scale(.5);z-index:1}}
@keyframes cgOrbitM{0%{opacity:0;transform:translate(-50%,-50%) translateX(33cqw) scale(.5);z-index:1}{{ $__ck(.4) }}%,{{ $__ck(1) }}%{opacity:.75;transform:translate(-50%,-50%) translateX(17cqw) scale(.7);z-index:2}{{ $__ck(1.4) }}%,{{ $__ck(2) }}%{opacity:1;transform:translate(-50%,-50%) translateX(0) scale(1);z-index:4}{{ $__ck(2.4) }}%,{{ $__ck(3) }}%{opacity:.75;transform:translate(-50%,-50%) translateX(-17cqw) scale(.7);z-index:2}{{ $__ck(3.4) }}%,100%{opacity:0;transform:translate(-50%,-50%) translateX(-33cqw) scale(.5);z-index:1}}
@keyframes cgSweep{0%{left:-30%}60%,100%{left:130%}}
@keyframes cgGlow{to{opacity:.55;transform:translate(-50%,-50%) scale(1.1)}}
@keyframes cgPulse{50%{transform:scale(1.05)}}
@media(max-width:760px){
/* owner 14 Nov: on a phone this slide is the same 16:9 box as every photo banner (it used to be twice as tall and stretched the whole slider): text left, cards right */
.hxcg,.hxs .sl-t>a.hxcg{aspect-ratio:16/9;display:block!important;padding:0}
.hxcg .cl{left:4cqw;width:42cqw;gap:2.6cqw;align-items:flex-start;text-align:left}
.hxcg .ck,.hxcg .cd{display:none}.hxcg .ch{font-size:5.6cqw;line-height:1.2}
.hxcg .cb{height:8cqw;padding:0 3.6cqw;font-size:max(11px,3.3cqw)}
.hxcg .cst{left:46cqw}
.hxcg .cg{left:73cqw;top:50%;width:74cqw;height:74cqw}
.hxcg .cc{width:25cqw;padding:1.4cqw 1.4cqw 2cqw;border-radius:2.4cqw;gap:1cqw;animation-name:cgOrbitM}
.hxcg .ci{border-radius:1.6cqw}.hxcg .cn{font-size:max(9px,2.5cqw);-webkit-line-clamp:1;height:1.3em}.hxcg .cp{font-size:max(11px,3.2cqw);padding:.5cqw 2.4cqw}
}
@media(prefers-reduced-motion:reduce){.hxcg .cg,.hxcg .cs,.hxcg .cb{animation:none!important}.hxcg .cc{animation-play-state:paused!important}}
</style>
<span class="cg"></span><span class="cs"></span>
<span class="cl"><span class="ck">DUTYFREEBD · PREMIUM CARTON</span><span class="ch">প্রিমিয়াম<em>সিগারেট কালেকশন</em></span><span class="cd">সবচেয়ে প্রিমিয়াম রেঞ্জের কার্টন, সারা বাংলাদেশে হোম ডেলিভারি।</span><span class="cb">কালেকশন দেখুন &rsaquo;</span></span>
<span class="cst" aria-hidden="true">@foreach($__cx as $__i => $__c)<span class="cc" style="animation-delay:{{ round(($__i - 2) * $__cs, 2) }}s"><span class="ci"><img src="{{ $__c['i'] }}" alt="" width="300" height="300" loading="lazy" decoding="async" draggable="false"></span><span class="cn">{{ \Illuminate\Support\Str::limit($__c['n'], 60) }}</span><span class="cp">৳{{ number_format($__c['p']) }}</span></span>@endforeach</span>
</a>
@endif
