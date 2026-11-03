@php $__g = collect($deals ?? [])->take(60); $__n = $__g->count(); $__tk = $__n > 4; $__dur = max(14, ceil($__n / 2) * 3.4); @endphp
@if($__g->count())
<style>
.dfg{position:relative;overflow:hidden;color:#fff;background:radial-gradient(1.2px 1.2px at 8% 18%,#fff,transparent),radial-gradient(1px 1px at 22% 72%,#cfe0ff,transparent),radial-gradient(1.4px 1.4px at 37% 30%,#fff,transparent),radial-gradient(1px 1px at 55% 12%,#fff,transparent),radial-gradient(1.2px 1.2px at 63% 86%,#cfe0ff,transparent),radial-gradient(1px 1px at 78% 40%,#fff,transparent),radial-gradient(1.3px 1.3px at 91% 14%,#fff,transparent),radial-gradient(1px 1px at 94% 78%,#cfe0ff,transparent),radial-gradient(1px 1px at 4% 90%,#fff,transparent),linear-gradient(120deg,#050B17 0%,#0B1220 45%,#14233F 100%);border-radius:14px;margin:18px 0}
.hx .dfg{margin:0}
.dfg-in{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.05fr);gap:10px;align-items:center;padding:30px 34px}
.dfg-k{display:inline-block;font-size:11px;font-weight:800;letter-spacing:.14em;color:#FF8A4C;border:1px solid rgba(255,138,76,.45);border-radius:20px;padding:5px 12px}
.dfg h2{margin:14px 0 8px;font-size:34px;line-height:1.12;font-weight:800;letter-spacing:-.01em}.dfg h2 b{color:#FF6A1A}
.dfg p.s{margin:0 0 18px;color:#B6C3DA;font-size:14.5px;line-height:1.6;max-width:440px}
.dfg-tk{max-width:520px}.dfg-tk.go{height:238px;overflow:hidden;-webkit-mask-image:linear-gradient(transparent,#000 9%,#000 91%,transparent);mask-image:linear-gradient(transparent,#000 9%,#000 91%,transparent)}
.dfg-tr{display:flex;flex-direction:column}.dfg-tk.go .dfg-tr{animation:dfgd var(--dur,20s) linear infinite}.dfg-tk.go:hover .dfg-tr{animation-play-state:paused}
@keyframes dfgd{from{transform:translateY(-50%)}to{transform:translateY(0)}}
@media (prefers-reduced-motion:reduce){.dfg-tk.go{overflow-y:auto}.dfg-tk.go .dfg-tr{animation:none}}
.dfg-cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding-bottom:10px}
.dfg-c{display:flex;gap:10px;align-items:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:8px;color:#fff;text-decoration:none;transition:border-color .25s,background .25s,transform .25s}
.dfg-c:hover,.dfg-c.on{border-color:#FF6A1A;background:rgba(255,106,26,.14);transform:translateY(-1px)}
.dfg-c img{width:54px;height:54px;object-fit:contain;background:#fff;border-radius:8px;flex:none}
.dfg-c i{display:block;font-style:normal;font-size:12px;font-weight:600;line-height:1.3;max-height:2.6em;overflow:hidden}
.dfg-c b{display:block;margin-top:3px;font-size:13px;color:#FFB48A}.dfg-c s{color:#8394B2;font-size:11px;margin-left:4px;font-weight:500}.dfg-c em{font-style:normal;background:#FF5A00;color:#fff;font-size:10.5px;font-weight:800;border-radius:4px;padding:1px 5px;margin-left:5px}
.dfg-all{display:inline-block;margin-top:16px;background:#FF5A00;color:#fff;font-weight:800;font-size:13.5px;border-radius:8px;padding:11px 22px;text-decoration:none}.dfg-all:hover{background:#ff7021}
.dfg-app{margin-top:18px;padding:12px 14px;border:1px solid rgba(255,138,76,.35);border-radius:14px;background:linear-gradient(135deg,rgba(255,90,0,.16),rgba(255,90,0,.04));max-width:520px}
.dfg-app>span{display:block;font-size:12.5px;font-weight:700;color:#FFB48A;margin-bottom:9px}
.dfg-ab{display:flex;gap:10px;flex-wrap:wrap}
.dfg-ab a,.dfg-ab em{display:flex;align-items:center;gap:9px;background:#000;color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.35);border-radius:10px;padding:7px 14px 7px 11px;font-style:normal;min-width:150px;transition:border-color .2s,transform .2s}
.dfg-ab a:hover{border-color:#FF6A1A;transform:translateY(-1px)}.dfg-ab em{opacity:.75}
.dfg-ab svg{width:24px;height:24px;flex:none}.dfg-ab small{display:block;font-size:9.5px;letter-spacing:.04em;color:#cbd5e6;line-height:1.1}.dfg-ab b{display:block;font-size:15px;line-height:1.2;font-weight:700}
.dfg-gl{position:relative;min-width:0}.dfg-gl canvas{display:block;width:100%;aspect-ratio:1/1;max-height:560px;margin:0 auto;cursor:grab;touch-action:pan-y}.dfg-gl canvas:active{cursor:grabbing}
@media (max-width:860px){.dfg-in{grid-template-columns:1fr;padding:0 12px 14px;gap:0}.dfg-gl{order:-1;margin:0 -12px}.dfg-gl canvas{aspect-ratio:auto;height:clamp(190px,52vw,280px);max-height:none}.dfg h2{font-size:19px;margin:6px 0 10px}.dfg-k,.dfg p.s,.dfg-all{display:none}.dfg-tk{max-width:none}.dfg-tk.go{height:150px}.dfg-cards{gap:8px;padding-bottom:8px}.dfg-c{padding:6px;gap:8px}.dfg-c img{width:40px;height:40px}.dfg-c i{font-size:11.5px}.dfg-c b{font-size:12px;margin-top:2px;white-space:nowrap}.dfg-c s{display:none}.dfg-app{margin-top:12px;padding:8px;max-width:none}.dfg-app>span{display:none}.dfg-ab{flex-wrap:nowrap}.dfg-ab a,.dfg-ab em{flex:1;min-width:0;padding:5px 10px 5px 8px}.dfg-ab svg{width:20px;height:20px}.dfg-ab b{font-size:13.5px}}
@media (max-width:340px){.dfg-cards{grid-template-columns:1fr}}
</style>
<section class="dfg" aria-label="Duty Free exceptional offers">
<div class="dfg-in">
<div>
<span class="dfg-k">✈ DUTY FREE · EXCEPTIONAL OFFERS</span>
<h2>Duty Free <b>Exceptional</b> Offers</h2>
<p class="s">দুবাই, ওমান, কাতার, মালয়েশিয়া, সিঙ্গাপুর, ইন্ডিয়াসহ আশেপাশের দেশ থেকে অরিজিনাল ডিউটি ফ্রি সিগারেট ও ভেপ উড়ে আসছে বাংলাদেশে।</p>
<div class="dfg-tk{{ $__tk ? ' go' : '' }}" style="--dur:{{ $__dur }}s"><div class="dfg-tr">
@for($__pass = 0; $__pass < ($__tk ? 2 : 1); $__pass++)
<div class="dfg-cards"@if($__pass === 1) aria-hidden="true"@endif>
@foreach($__g as $q)@php $off = \App\Http\Controllers\StoreController::off($q); $tg = \Illuminate\Support\Str::limit($q->name, 20, '…').($off > 0 ? ' −'.$off.'%' : ''); @endphp
<a class="dfg-c" href="{{ \App\Http\Controllers\StoreController::url($q) }}" @if($__pass === 0)data-tag="{{ $tg }}"@else tabindex="-1"@endif><img src="{{ $q->image ?: '/product-placeholder' }}" alt="" width="54" height="54" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/product-placeholder'"><span><i>{{ $q->name }}</i><b>{{ number_format($q->price) }} TK @if($off > 0)<s>{{ number_format($q->compare_price) }}</s><em>-{{ $off }}%</em>@endif</b></span></a>
@endforeach
</div>
@endfor
</div></div>
<a class="dfg-all" href="/cigarette-vape/">সব প্রোডাক্ট দেখুন →</a>
@php $__an = trim((string)($s['app_android'] ?? '')); $__io = trim((string)($s['app_ios'] ?? '')); @endphp
<div class="dfg-app"><span>📱 আমাদের Android ও iPhone অ্যাপ — আরও দ্রুত, আরও সহজে অর্ডার করুন</span><div class="dfg-ab">
@if($__an !== '')<a href="{{ $__an }}" rel="noopener" aria-label="Get it on Google Play">@else<em>@endif<svg viewBox="0 0 24 24"><path d="M3.6 2.2c-.3.2-.5.6-.5 1.1v17.4c0 .5.2.9.5 1.1l9.7-9.8z" fill="#00D7FE"/><path d="M16.6 8.7 13.3 12l3.3 3.3 3.9-2.2c1.1-.6 1.1-1.6 0-2.2z" fill="#FFCE00"/><path d="m3.6 21.8 9.7-9.8 3.3 3.3-11.2 6.3c-.7.4-1.4.3-1.8.2z" fill="#F43249"/><path d="M3.6 2.2c.4-.1 1.1-.2 1.8.2l11.2 6.3L13.3 12z" fill="#00F076"/></svg><span><small>{{ $__an !== '' ? 'GET IT ON' : 'COMING SOON' }}</small><b>Google Play</b></span>@if($__an !== '')</a>@else</em>@endif
@if($__io !== '')<a href="{{ $__io }}" rel="noopener" aria-label="Download on the App Store">@else<em>@endif<svg viewBox="0 0 24 24" fill="#fff"><path d="M16.4 12.6c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.1-2.8.9-3.5.9-.7 0-1.8-.8-3-.8-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.200 0 1.600-.7 3-.7s1.800.7 3 .7c1.300 0 2.100-1.100 2.800-2.200.9-1.300 1.300-2.500 1.300-2.600-.1 0-2.500-1-2.500-3.900zM14.200 5.800c.6-.8 1.100-1.900.9-3-.9 0-2.100.6-2.700 1.400-.6.700-1.100 1.800-1 2.900 1.100.1 2.200-.5 2.800-1.300z"/></svg><span><small>{{ $__io !== '' ? 'DOWNLOAD ON THE' : 'COMING SOON' }}</small><b>App Store</b></span>@if($__io !== '')</a>@else</em>@endif
</div></div>
</div>
<div class="dfg-gl"><canvas role="img" aria-label="Globe with planes flying from nearby countries to land in Bangladesh"></canvas></div>
</div>
</section>
<script src="/dfglobe.js?v=9" defer></script>
@endif
