@php $__g = collect($deals ?? [])->take(60); $__n = $__g->count(); $__tk = $__n > 4; $__dur = max(14, ceil($__n / 2) * 3.4); @endphp
@if($__g->count())
<style>
.dfg{position:relative;overflow:hidden;color:#fff;background:radial-gradient(1.2px 1.2px at 8% 18%,#fff,transparent),radial-gradient(1px 1px at 22% 72%,#cfe0ff,transparent),radial-gradient(1.4px 1.4px at 37% 30%,#fff,transparent),radial-gradient(1px 1px at 55% 12%,#fff,transparent),radial-gradient(1.2px 1.2px at 63% 86%,#cfe0ff,transparent),radial-gradient(1px 1px at 78% 40%,#fff,transparent),radial-gradient(1.3px 1.3px at 91% 14%,#fff,transparent),radial-gradient(1px 1px at 94% 78%,#cfe0ff,transparent),radial-gradient(1px 1px at 4% 90%,#fff,transparent),linear-gradient(120deg,#050B17 0%,#0B1220 45%,#14233F 100%);border-radius:14px;margin:18px 0;aspect-ratio:1440/480}
.hx .dfg{margin:0}
.dfg-in{display:grid;grid-template-columns:minmax(0,46%) minmax(0,54%);height:100%}
.dfg-l{display:flex;flex-direction:column;min-height:0;padding:18px 0 18px 28px}
.dfg-k{align-self:flex-start;font-size:10.5px;font-weight:800;letter-spacing:.14em;color:#FF8A4C;border:1px solid rgba(255,138,76,.45);border-radius:20px;padding:3px 10px}
.dfg h2{margin:9px 0 10px;font-size:26px;line-height:1.12;font-weight:800;letter-spacing:-.01em}.dfg h2 b{color:#FF6A1A}
.dfg p.s,.dfg-all,.dfg-app>span{display:none}
.dfg-tk{flex:1;min-height:0;position:relative;overflow:hidden}
.dfg-tk.go{-webkit-mask-image:linear-gradient(transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(transparent,#000 8%,#000 92%,transparent)}
.dfg-tr{display:flex;flex-direction:column}.dfg-tk.go .dfg-tr{animation:dfgd var(--dur,20s) linear infinite}.dfg-tk.go:hover .dfg-tr{animation-play-state:paused}
@keyframes dfgd{from{transform:translateY(-50%)}to{transform:translateY(0)}}
@media (prefers-reduced-motion:reduce){.dfg-tk.go{overflow-y:auto}.dfg-tk.go .dfg-tr{animation:none}}
.dfg-cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding-bottom:8px}
.dfg-c{display:flex;gap:9px;align-items:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:11px;padding:6px;color:#fff;text-decoration:none;transition:border-color .25s,background .25s}
.dfg-c:hover,.dfg-c.on{border-color:#FF6A1A;background:rgba(255,106,26,.14)}
.dfg-c img{width:44px;height:44px;object-fit:contain;background:#fff;border-radius:7px;flex:none}
.dfg-c i{display:block;font-style:normal;font-size:11.5px;font-weight:600;line-height:1.3;max-height:2.6em;overflow:hidden}
.dfg-c b{display:block;margin-top:2px;font-size:12.5px;color:#FFB48A;white-space:nowrap}.dfg-c s{color:#8394B2;font-size:10.5px;margin-left:4px;font-weight:500}.dfg-c em{font-style:normal;background:#FF5A00;color:#fff;font-size:10px;font-weight:800;border-radius:4px;padding:1px 4px;margin-left:4px}
.dfg-app{margin-top:10px}
.dfg-ab{display:flex;gap:8px}
.dfg-ab a,.dfg-ab em,.dfg-ab button{display:flex;align-items:center;gap:7px;background:#000;color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.35);border-radius:9px;padding:5px 12px 5px 9px;font-style:normal;min-width:0;transition:border-color .2s}
.dfg-ab a:hover,.dfg-ab button:hover{border-color:#FF6A1A}.dfg-ab button{cursor:pointer;font:inherit;text-align:left}.dfg-ab em{opacity:.75}
.dfg-ab svg{width:20px;height:20px;flex:none}.dfg-ab small{display:block;font-size:8.5px;letter-spacing:.04em;color:#cbd5e6;line-height:1.1}.dfg-ab b{display:block;font-size:13px;line-height:1.2;font-weight:700}
.dfg-gl{position:relative;min-width:0}.dfg-gl canvas{position:absolute;left:0;top:0;width:100%;height:100%;display:block;cursor:grab;touch-action:pan-y}.dfg-gl canvas:active{cursor:grabbing}
@media (max-width:900px){.dfg{aspect-ratio:auto;max-height:none;border-radius:12px}.dfg-in{display:flex;flex-direction:column;height:auto;position:relative}.dfg-l{display:contents}
.dfg-gl{order:-1;height:clamp(185px,50vw,270px);flex:none}.dfg-gl:after{content:"";position:absolute;left:0;top:0;right:0;height:56px;background:linear-gradient(rgba(5,11,23,.85),transparent);pointer-events:none}
.dfg-k{display:none}.dfg h2{position:absolute;z-index:2;left:12px;top:9px;margin:0;font-size:16px;line-height:1.15;text-shadow:0 1px 6px rgba(0,0,0,.7)}
.dfg-tk{flex:none;height:60px;margin:6px 10px 0}.dfg-tk.go .dfg-tr{animation-duration:calc(var(--dur,20s)*1.9)}
.dfg-cards{grid-template-columns:1fr;gap:6px;padding-bottom:6px}.dfg-c{padding:5px 8px;gap:9px;border-radius:10px}.dfg-c img{width:38px;height:38px;border-radius:6px}.dfg-c i{font-size:12px;line-height:1.25;max-height:1.3em;white-space:nowrap;text-overflow:ellipsis}.dfg-c b{font-size:12.5px;margin-top:1px}.dfg-c s{display:none}
.dfg-app{margin:6px 10px 10px}.dfg-ab{gap:8px}.dfg-ab a,.dfg-ab em,.dfg-ab button{flex:1;gap:6px;padding:5px 8px;border-radius:9px;justify-content:center}.dfg-ab svg{width:17px;height:17px}.dfg-ab small{display:none}.dfg-ab b{font-size:12px;white-space:nowrap}}
@media (max-width:340px){.dfg h2{font-size:14px}}
</style>
<section class="dfg" aria-label="Duty Free exceptional offers">
<div class="dfg-in">
<div class="dfg-l">
<span class="dfg-k">✈ DUTY FREE · EXCEPTIONAL OFFERS</span>
<h2>Duty Free <b>Exceptional</b> Offers</h2>
<p class="s">দুবাই, ওমান, কাতার, মালয়েশিয়া, সিঙ্গাপুর, ইন্ডিয়াসহ আশেপাশের দেশ থেকে অরিজিনাল ডিউটি ফ্রি সিগারেট ও ভেপ উড়ে আসছে বাংলাদেশে।</p>
<div class="dfg-tk{{ $__tk ? ' go' : '' }}" style="--dur:{{ $__dur }}s"><div class="dfg-tr">
@for($__pass = 0; $__pass < ($__tk ? 2 : 1); $__pass++)
<div class="dfg-cards"@if($__pass === 1) aria-hidden="true"@endif>
@foreach($__g as $q)@php $off = \App\Http\Controllers\StoreController::off($q); $tg = \Illuminate\Support\Str::limit($q->name, 20, '…').($off > 0 ? ' −'.$off.'%' : ''); @endphp
<a class="dfg-c" href="{{ \App\Http\Controllers\StoreController::url($q) }}" @if($__pass === 0)data-tag="{{ $tg }}"@else tabindex="-1"@endif><img src="{{ $q->image ?: '/product-placeholder' }}" alt="" width="54" height="54" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/product-placeholder'"><span><i>{{ $q->name }}</i><b>{{ number_format($q->price) }} TK @if($off > 0)<s>{{ number_format($q->compare_price) }}</s>@endif</b></span></a>
@endforeach
</div>
@endfor
</div></div>
<a class="dfg-all" href="/cigarette-vape/">সব প্রোডাক্ট দেখুন →</a>
<div class="dfg-app"><span>📱 আমাদের Android ও iPhone অ্যাপ — সরাসরি ওয়েবসাইট থেকে ইনস্টল করুন</span><div class="dfg-ab">
<button type="button" data-pwa="android" aria-label="Install Android app"><svg viewBox="0 0 24 24" fill="#3DDC84"><path d="M17.6 9.5a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8zM6.4 9.5a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8zM15.9 4.4l1.2-2.1a.3.3 0 0 0-.5-.3l-1.2 2.1A7.2 7.2 0 0 0 12 3.4c-1.1 0-2.1.2-3.4.7L7.4 2a.3.3 0 0 0-.5.3l1.2 2.1A6.6 6.6 0 0 0 5 9.7h14a6.6 6.6 0 0 0-3.1-5.3zM5 10.7v7.1c0 .8.6 1.4 1.4 1.4h.9v2.6a1.3 1.3 0 0 0 2.6 0v-2.6h2.2v2.6a1.3 1.3 0 0 0 2.6 0v-2.6h.9c.8 0 1.4-.6 1.4-1.4v-7.1zM2.3 10.7a1.3 1.3 0 0 0-1.3 1.3v4.5a1.3 1.3 0 0 0 2.6 0V12a1.3 1.3 0 0 0-1.3-1.3zm19.4 0a1.3 1.3 0 0 0-1.3 1.3v4.5a1.3 1.3 0 0 0 2.6 0V12a1.3 1.3 0 0 0-1.3-1.3z"/></svg><span><small>INSTALL DIRECTLY</small><b>Android App</b></span></button>
<button type="button" data-pwa="ios" aria-label="Install iPhone app"><svg viewBox="0 0 24 24" fill="#fff"><path d="M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.1-2.8.9-3.5.9-.7 0-1.8-.8-3-.8-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7c1.3 0 2.1-1.1 2.8-2.2.9-1.3 1.3-2.5 1.3-2.6-.1 0-2.5-1-2.5-3.9zM14.2 5.8c.6-.8 1.1-1.9.9-3-.9 0-2.1.6-2.7 1.4-.6.7-1.1 1.8-1 2.9 1.1.1 2.2-.5 2.8-1.3z"/></svg><span><small>INSTALL DIRECTLY</small><b>iPhone App</b></span></button>
</div></div>
</div>
<div class="dfg-gl"><canvas role="img" aria-label="Globe with planes flying from nearby countries to land in Bangladesh"></canvas></div>
</div>
</section>
<script src="/dfglobe.js?v=15" defer></script>
@endif
