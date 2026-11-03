@php $__g = collect($deals ?? [])->take(6); @endphp
@if($__g->count())
<style>
.dfg{position:relative;overflow:hidden;color:#fff;background:radial-gradient(1.2px 1.2px at 8% 18%,#fff,transparent),radial-gradient(1px 1px at 22% 72%,#cfe0ff,transparent),radial-gradient(1.4px 1.4px at 37% 30%,#fff,transparent),radial-gradient(1px 1px at 55% 12%,#fff,transparent),radial-gradient(1.2px 1.2px at 63% 86%,#cfe0ff,transparent),radial-gradient(1px 1px at 78% 40%,#fff,transparent),radial-gradient(1.3px 1.3px at 91% 14%,#fff,transparent),radial-gradient(1px 1px at 94% 78%,#cfe0ff,transparent),radial-gradient(1px 1px at 4% 90%,#fff,transparent),linear-gradient(120deg,#050B17 0%,#0B1220 45%,#14233F 100%);border-radius:14px;margin:18px 0}
.dfg-in{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.05fr);gap:10px;align-items:center;padding:30px 34px}
.dfg-k{display:inline-block;font-size:11px;font-weight:800;letter-spacing:.14em;color:#FF8A4C;border:1px solid rgba(255,138,76,.45);border-radius:20px;padding:5px 12px}
.dfg h2{margin:14px 0 8px;font-size:34px;line-height:1.12;font-weight:800;letter-spacing:-.01em}.dfg h2 b{color:#FF6A1A}
.dfg p.s{margin:0 0 18px;color:#B6C3DA;font-size:14.5px;line-height:1.6;max-width:440px}
.dfg-cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;max-width:520px}
.dfg-c{display:flex;gap:10px;align-items:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:8px;color:#fff;text-decoration:none;transition:border-color .25s,background .25s,transform .25s}
.dfg-c:hover,.dfg-c.on{border-color:#FF6A1A;background:rgba(255,106,26,.14);transform:translateY(-1px)}
.dfg-c img{width:54px;height:54px;object-fit:contain;background:#fff;border-radius:8px;flex:none}
.dfg-c i{display:block;font-style:normal;font-size:12px;font-weight:600;line-height:1.3;max-height:2.6em;overflow:hidden}
.dfg-c b{display:block;margin-top:3px;font-size:13px;color:#FFB48A}.dfg-c s{color:#8394B2;font-size:11px;margin-left:4px;font-weight:500}.dfg-c em{font-style:normal;background:#FF5A00;color:#fff;font-size:10.5px;font-weight:800;border-radius:4px;padding:1px 5px;margin-left:5px}
.dfg-all{display:inline-block;margin-top:16px;background:#FF5A00;color:#fff;font-weight:800;font-size:13.5px;border-radius:8px;padding:11px 22px;text-decoration:none}.dfg-all:hover{background:#ff7021}
.dfg-gl{position:relative;min-width:0}.dfg-gl canvas{display:block;width:100%;aspect-ratio:1/1;max-height:560px;margin:0 auto;cursor:grab;touch-action:pan-y}.dfg-gl canvas:active{cursor:grabbing}
@media (max-width:860px){.dfg-in{grid-template-columns:1fr;padding:22px 16px 26px;gap:0}.dfg-gl{order:-1;margin:-6px -10px 0}.dfg h2{font-size:25px}.dfg-cards{grid-template-columns:repeat(2,minmax(0,1fr));max-width:none}.dfg-c img{width:46px;height:46px}.dfg-all{display:block;text-align:center}}
@media (max-width:420px){.dfg-cards{grid-template-columns:1fr}}
</style>
<section class="dfg" aria-label="Duty Free exceptional offers">
<div class="dfg-in">
<div>
<span class="dfg-k">✈ DUTY FREE · EXCEPTIONAL OFFERS</span>
<h2>Duty Free <b>Exceptional</b> Offers</h2>
<p class="s">আকাশ পেরিয়ে আসা সেরা ডিউটি ফ্রি ডিল, সরাসরি আপনার দরজায়। আজকের বাছাই করা অফারগুলো দেখুন।</p>
<div class="dfg-cards">
@foreach($__g as $q)@php $off = \App\Http\Controllers\StoreController::off($q); $tg = \Illuminate\Support\Str::limit($q->name, 20, '…').($off > 0 ? ' −'.$off.'%' : ''); @endphp
<a class="dfg-c" href="{{ \App\Http\Controllers\StoreController::url($q) }}" data-tag="{{ $tg }}"><img src="{{ $q->image ?: '/product-placeholder' }}" alt="" width="54" height="54" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/product-placeholder'"><span><i>{{ $q->name }}</i><b>{{ number_format($q->price) }} TK @if($off > 0)<s>{{ number_format($q->compare_price) }}</s><em>-{{ $off }}%</em>@endif</b></span></a>
@endforeach
</div>
<a class="dfg-all" href="/deals">সব অফার দেখুন →</a>
</div>
<div class="dfg-gl"><canvas role="img" aria-label="Rotating globe with planes flying between Dubai, Dhaka and Chattogram"></canvas></div>
</div>
</section>
<script src="/dfglobe.js?v=1" defer></script>
@endif
