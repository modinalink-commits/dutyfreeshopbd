@php
 $__wc = \App\Http\Controllers\StoreController::rem('store.watchslide3', 900, function () {
   try {
     $cat = \Illuminate\Support\Facades\DB::table('categories')->where('slug', 'watch')->value('id'); if (!$cat) return [];
     $rows = \Illuminate\Support\Facades\DB::table('products')->where('category_id', $cat)->where('published', true)->whereBetween('price', [35000, 50000])->whereNotNull('image')->where('image', '!=', '')->orderByDesc('price')->limit(80)->get(['name', 'price', 'image']);
     return $rows->shuffle()->take(10)->map(fn($r) => ['n' => $r->name, 'p' => (int)$r->price, 'i' => $r->image])->values()->all();
   } catch (\Throwable $e) { return []; }
 });
 $__bn2 = fn($v) => strtr((string)$v, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
@endphp
@if(count($__wc) >= 4)
<a class="hxa hxwt" href="/watch/" aria-label="DutyFreeBD Watch Collection">
<style>
.hxwt{container-type:inline-size;position:relative;display:block;overflow:hidden;color:#fff;text-decoration:none;background:radial-gradient(120% 140% at 82% 0%,#3A2A1A 0%,#141B2D 48%,#0B1220 100%);font-family:'Bornomala',Inter,sans-serif}
.hxwt *{box-sizing:border-box}
.hxwt .wg{position:absolute;right:-8cqw;top:-14cqw;width:62cqw;height:52cqw;background:radial-gradient(closest-side,rgba(255,170,60,.38),rgba(255,170,60,0) 70%);pointer-events:none;animation:wtGlow 6s ease-in-out infinite alternate}
.hxwt .ws{position:absolute;top:0;bottom:0;left:-30%;width:20%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.12),transparent);transform:skewX(-18deg);animation:wtSweep 6.5s cubic-bezier(.4,0,.2,1) infinite;pointer-events:none}
.hxwt .wl{position:absolute;left:5cqw;top:0;bottom:0;width:34cqw;display:flex;flex-direction:column;justify-content:center;gap:1.3cqw;z-index:2}
.hxwt .wk{font-size:max(9px,.95cqw);letter-spacing:.24em;font-weight:600;color:#F5C98A}
.hxwt .wh{margin:0;font-size:max(16px,4.2cqw);line-height:1.1;font-weight:700;letter-spacing:-.01em}.hxwt .wh em{display:block;font-style:normal;color:#FFB347}
.hxwt .wd{font-size:max(10px,1.3cqw);color:#D6DCE8;line-height:1.5;max-width:30cqw}
.hxwt .wb{align-self:flex-start;display:inline-flex;align-items:center;gap:.8cqw;height:max(26px,3cqw);padding:0 max(14px,1.8cqw);border-radius:999px;background:linear-gradient(135deg,#FF8A00,#FF5A00);font-size:max(11px,1.3cqw);font-weight:700;color:#fff;box-shadow:0 .8cqw 2cqw rgba(255,90,0,.35);animation:wtPulse 2.2s ease-in-out infinite}
.hxwt .wv{position:absolute;left:42cqw;right:0;top:0;bottom:0;overflow:hidden;-webkit-mask-image:linear-gradient(90deg,transparent,#000 9%,#000 100%);mask-image:linear-gradient(90deg,transparent,#000 9%,#000 100%)}
.hxwt .wt{position:absolute;left:0;top:50%;display:flex;gap:2.4cqw;width:max-content;transform:translateY(-50%);animation:wtMar {{ count($__wc) * 5 }}s linear infinite}
.hxwt .wc{flex:none;width:19cqw;display:flex;flex-direction:column;align-items:center;gap:1cqw;text-align:center;animation:wtFloat 4.2s ease-in-out infinite}
.hxwt .wc:nth-child(2n){animation-delay:-2.1s}.hxwt .wc:nth-child(3n){animation-delay:-1s}
.hxwt .wi{width:16cqw;height:16cqw;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:0 1.4cqw 3.2cqw rgba(0,0,0,.45),0 0 0 .5cqw rgba(255,179,71,.55)}
.hxwt .wi img{width:100%;height:100%;object-fit:contain;display:block}
.hxwt .wn{max-width:100%;font-size:max(10px,1.15cqw);font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hxwt .wp{font-size:max(11px,1.5cqw);font-weight:700;color:#FFB347}
@keyframes wtMar{to{transform:translate(-50%,-50%)}}
@keyframes wtFloat{0%,100%{margin-top:0}50%{margin-top:-1.2cqw}}
@keyframes wtSweep{0%{left:-30%}60%,100%{left:130%}}
@keyframes wtGlow{to{opacity:.6;transform:scale(1.08)}}
@keyframes wtPulse{50%{transform:scale(1.05)}}
@media(max-width:760px){
.hxwt,.hxs .sl-t>a.hxwt{aspect-ratio:auto;display:flex!important;flex-direction:column;align-items:center;gap:3cqw;padding:5cqw 0 max(62px,15cqw)}
.hxwt .wl{position:static;width:100%;padding:0 5cqw;align-items:center;text-align:center;gap:2cqw}
.hxwt .wk,.hxwt .wd{display:none}.hxwt .wh{font-size:7cqw}.hxwt .wh em{display:inline;margin-left:1.4cqw}
.hxwt .wb{align-self:center;height:8cqw;padding:0 4.4cqw;font-size:max(11px,3.4cqw)}
.hxwt .wv{position:relative;left:auto;right:auto;top:auto;bottom:auto;width:100%;height:38cqw;flex:none}
.hxwt .wt{gap:5cqw}.hxwt .wc{width:30cqw;gap:1.4cqw}.hxwt .wi{width:25cqw;height:25cqw;box-shadow:0 2cqw 4cqw rgba(0,0,0,.45),0 0 0 .8cqw rgba(255,179,71,.55)}
.df-app .hxwt,.df-app .hxs .sl-t>a.hxwt{padding-bottom:6cqw}.hxwt .wn{font-size:max(10px,3cqw)}.hxwt .wp{font-size:max(11px,3.4cqw)}.hxwt .wg{width:100cqw;height:80cqw}
}
@media(prefers-reduced-motion:reduce){.hxwt .wg,.hxwt .ws,.hxwt .wb,.hxwt .wt,.hxwt .wc{animation:none!important}}
</style>
<span class="wg"></span><span class="ws"></span>
<span class="wl"><span class="wk">DUTYFREEBD WATCH</span><span class="wh">প্রিমিয়াম<em>ওয়াচ কালেকশন</em></span><span class="wd">নির্বাচিত ব্র্যান্ডের স্টাইলিশ ঘড়ি, সারা বাংলাদেশে হোম ডেলিভারি।</span><span class="wb">ওয়াচ দেখুন &rsaquo;</span></span>
<span class="wv" aria-hidden="true"><span class="wt">@foreach([0,1] as $__r)@foreach($__wc as $__w)<span class="wc"><span class="wi"><img src="{{ $__w['i'] }}" alt="" width="200" height="200" loading="lazy" decoding="async" draggable="false"></span><span class="wn">{{ \Illuminate\Support\Str::limit($__w['n'], 26) }}</span><span class="wp">৳{{ number_format($__w['p']) }}</span></span>@endforeach @endforeach</span></span>
</a>
@endif
