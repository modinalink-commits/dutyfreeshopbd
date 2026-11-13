@php
 /* Luxury watch hero slide: the most expensive branded watches (৳50,000+), one in the spotlight at a time. Data comes from the live catalogue; nothing is hard-coded. */
 $__lx = \App\Http\Controllers\StoreController::rem('store.watchlux2', 900, function () {
   try {
     $D = \Illuminate\Support\Facades\DB::class;
     $cat = $D::table('categories')->where('slug', 'watch')->value('id'); if (!$cat) return [];
     $base = fn() => $D::table('products')->where('category_id', $cat)->where('published', true)->whereNotNull('image')->where('image', '!=', '');
     $rows = $base()->where('price', '>=', 50000)->orderByDesc('price')->limit(12)->get(['name', 'price', 'image']);
     if ($rows->count() < 4) $rows = $base()->where('price', '>=', 20000)->orderByDesc('price')->limit(10)->get(['name', 'price', 'image']);
     return $rows->map(function ($r) { $w = preg_split('/\s+/', trim((string)$r->name)); $b = (string)($w[0] ?? ''); if (in_array(mb_strtolower($b), ['grand', 'frederique', 'tag', 'michael', 'emporio', 'tommy', 'daniel', 'calvin', 'maurice', 'raymond', 'baume', 'jaeger', 'ulysse', 'franck', 'patek', 'audemars', 'vacheron'], true) && isset($w[1])) $b .= ' ' . $w[1]; if ($b !== '' && mb_strtoupper($b) === $b) $b = mb_convert_case($b, MB_CASE_TITLE); $b = mb_convert_case($b, MB_CASE_TITLE); return ['n' => $r->name, 'b' => $b, 'p' => (int)$r->price, 'i' => $r->image]; })->values()->all();
   } catch (\Throwable $e) { return []; }
 });
 $__ln = count($__lx); $__ls = 3.8; $__lp = $__ln ? 100 / $__ln : 100;
@endphp
@if($__ln >= 4)
<a class="hxa hxlx" href="/watch/?sort=price_desc" aria-label="DutyFreeBD Luxury Watch Collection">
<style>
.hxlx{container-type:inline-size;position:relative;display:block;aspect-ratio:1440/480;overflow:hidden;color:#fff;text-decoration:none;background:radial-gradient(90% 150% at 72% 50%,#2B2014 0%,#131A2B 46%,#0B1220 100%);font-family:'Bornomala',Inter,sans-serif}
.hxlx *{box-sizing:border-box}
.hxlx .xg{position:absolute;left:58cqw;top:50%;width:46cqw;height:46cqw;transform:translate(-50%,-50%);background:radial-gradient(closest-side,rgba(255,176,72,.34),rgba(255,176,72,0) 70%);animation:lxGlow 5s ease-in-out infinite alternate;pointer-events:none}
.hxlx .xs{position:absolute;top:0;bottom:0;left:-30%;width:18%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.1),transparent);transform:skewX(-18deg);animation:lxSweep 7s cubic-bezier(.4,0,.2,1) infinite;pointer-events:none}
.hxlx .xl{position:absolute;left:5cqw;top:0;bottom:0;width:30cqw;display:flex;flex-direction:column;justify-content:center;gap:1.2cqw;z-index:3}
.hxlx .xk{font-size:max(9px,.95cqw);letter-spacing:.22em;font-weight:600;color:#F5C98A}
.hxlx .xh{margin:0;font-size:max(16px,4cqw);line-height:1.1;font-weight:700}.hxlx .xh em{display:block;font-style:normal;color:#FFB347}
.hxlx .xd{font-size:max(10px,1.25cqw);color:#D6DCE8;line-height:1.5}
.hxlx .xb{align-self:flex-start;display:inline-flex;align-items:center;height:max(26px,3cqw);padding:0 max(14px,1.8cqw);border-radius:999px;background:#FF5A00;border:1.5px solid #0B1220;outline:1px solid rgba(255,179,71,.6);font-size:max(11px,1.3cqw);font-weight:700;color:#fff;animation:lxPulse 2.4s ease-in-out infinite}
.hxlx .xst{position:absolute;left:58cqw;top:50%;width:25cqw;height:25cqw;transform:translate(-50%,-50%);z-index:2}
.hxlx .xr{position:absolute;inset:-1.1cqw;border-radius:50%;background:conic-gradient(from 0deg,#FFB347,rgba(255,179,71,.05) 28%,#FF5A00 52%,rgba(255,90,0,.05) 78%,#FFB347);animation:lxSpin 9s linear infinite;-webkit-mask:radial-gradient(closest-side,transparent calc(100% - .45cqw),#000 calc(100% - .4cqw));mask:radial-gradient(closest-side,transparent calc(100% - .45cqw),#000 calc(100% - .4cqw))}
.hxlx .xr2{position:absolute;inset:-3cqw;border-radius:50%;border:1px dashed rgba(245,201,138,.35);animation:lxSpin 26s linear infinite reverse}
.hxlx .xw{position:absolute;inset:0;border-radius:50%;background:#fff;overflow:hidden;box-shadow:0 2cqw 5cqw rgba(0,0,0,.55);opacity:0;animation:lxShow {{ round($__ln * $__ls, 2) }}s infinite}
.hxlx .xw img{width:100%;height:100%;object-fit:contain;display:block}
.hxlx .xi{position:absolute;left:74cqw;right:3cqw;top:0;bottom:0;z-index:3}
.hxlx .xc{position:absolute;left:0;right:0;top:50%;display:flex;flex-direction:column;gap:.9cqw;transform:translateY(-50%);opacity:0;animation:lxText {{ round($__ln * $__ls, 2) }}s infinite}
.hxlx .xbr{font-size:max(13px,2.6cqw);font-weight:700;letter-spacing:.04em;color:#F5C98A;line-height:1.05;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hxlx .xn{font-size:max(10px,1.15cqw);color:#D6DCE8;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.hxlx .xp{align-self:flex-start;font-size:max(12px,1.9cqw);font-weight:700;color:#0B1220;background:#FFB347;border-radius:.6cqw;padding:.35cqw 1.1cqw}
.hxlx .xbg{position:absolute;left:36cqw;right:0;bottom:-2.4cqw;font-size:11cqw;font-weight:700;letter-spacing:.06em;color:rgba(255,255,255,.045);white-space:nowrap;text-align:center;pointer-events:none;line-height:1}
@keyframes lxShow{0%{opacity:0;transform:scale(.78) rotate(-12deg)}{{ round($__lp * .14, 3) }}%{opacity:1;transform:none}{{ round($__lp * .86, 3) }}%{opacity:1;transform:none}{{ round($__lp, 3) }}%{opacity:0;transform:scale(1.1)}100%{opacity:0}}
@keyframes lxText{0%{opacity:0;transform:translate(2.4cqw,-50%)}{{ round($__lp * .2, 3) }}%{opacity:1;transform:translateY(-50%)}{{ round($__lp * .84, 3) }}%{opacity:1;transform:translateY(-50%)}{{ round($__lp, 3) }}%{opacity:0;transform:translate(-1.6cqw,-50%)}100%{opacity:0}}
@keyframes lxSpin{to{transform:rotate(360deg)}}
@keyframes lxSweep{0%{left:-30%}60%,100%{left:130%}}
@keyframes lxGlow{to{opacity:.55;transform:translate(-50%,-50%) scale(1.1)}}
@keyframes lxPulse{50%{transform:scale(1.05)}}
@media(max-width:760px){
.hxlx,.hxs .sl-t>a.hxlx{aspect-ratio:auto;display:flex!important;flex-direction:column;align-items:center;gap:4cqw;padding:5cqw 0 max(62px,15cqw)}
.hxlx .xl{position:static;width:100%;padding:0 5cqw;align-items:center;text-align:center;gap:2cqw}
.hxlx .xk,.hxlx .xd,.hxlx .xbg{display:none}.hxlx .xh{font-size:7cqw}.hxlx .xh em{display:inline;margin-left:1.4cqw}
.hxlx .xb{align-self:center;height:8cqw;padding:0 4.4cqw;font-size:max(11px,3.4cqw)}
.hxlx .xst{position:relative;left:auto;top:auto;transform:none;width:44cqw;height:44cqw;flex:none;margin:3cqw 0}
.hxlx .xg{left:50%;top:60%;width:90cqw;height:90cqw}
.hxlx .xr{inset:-2cqw}.hxlx .xr2{inset:-5cqw}
.hxlx .xi{position:relative;left:auto;right:auto;width:100%;height:21cqw;flex:none}
.hxlx .xc{align-items:center;text-align:center;padding:0 6cqw;gap:1.4cqw}
.hxlx .xbr{font-size:5.4cqw}.hxlx .xn{font-size:max(10px,3cqw);-webkit-line-clamp:1}.hxlx .xp{align-self:center;font-size:4.2cqw;padding:.8cqw 2.6cqw;border-radius:1.4cqw}
.df-app .hxlx,.df-app .hxs .sl-t>a.hxlx{padding-bottom:6cqw}
}
@media(prefers-reduced-motion:reduce){.hxlx .xg,.hxlx .xs,.hxlx .xb,.hxlx .xr,.hxlx .xr2{animation:none!important}.hxlx .xw,.hxlx .xc{animation:none!important}.hxlx .xw:first-of-type,.hxlx .xc:first-child{opacity:1}}
</style>
<span class="xg"></span><span class="xs"></span><span class="xbg" aria-hidden="true">LUXURY</span>
<span class="xl"><span class="xk">DUTYFREEBD · LUXURY WATCH</span><span class="xh">ব্র্যান্ডেড<em>লাক্সারি ওয়াচ</em></span><span class="xd">বিশ্বখ্যাত ব্র্যান্ডের প্রিমিয়াম ঘড়ি, সারা বাংলাদেশে হোম ডেলিভারি।</span><span class="xb">কালেকশন দেখুন &rsaquo;</span></span>
<span class="xst" aria-hidden="true"><span class="xr2"></span><span class="xr"></span>@foreach($__lx as $__i => $__w)<span class="xw" style="animation-delay:{{ round($__i * $__ls, 2) }}s"><img src="{{ $__w['i'] }}" alt="" width="360" height="360" loading="{{ $__i ? 'lazy' : 'eager' }}" decoding="async" draggable="false"></span>@endforeach</span>
<span class="xi" aria-hidden="true">@foreach($__lx as $__i => $__w)<span class="xc" style="animation-delay:{{ round($__i * $__ls, 2) }}s"><span class="xbr">{{ $__w['b'] }}</span><span class="xn">{{ \Illuminate\Support\Str::limit($__w['n'], 70) }}</span><span class="xp">৳{{ number_format($__w['p']) }}</span></span>@endforeach</span>
</a>
@else
@include('store.watch_slide')
@endif
