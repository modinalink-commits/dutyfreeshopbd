@extends('store.layout')
@section('main')
<style>
.bfw{padding:16px 0 34px}
.bfh{border-radius:18px;background:#0C161F;color:#fff;padding:26px 28px;display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:24px;align-items:center}
.bfh img{display:block;width:100%;max-width:520px;height:auto;background:#fff;border-radius:14px;padding:10px 14px;box-sizing:border-box}
.bfh h1{margin:0 0 8px;font-size:30px;line-height:1.15;font-weight:800}.bfh p{margin:0;font-size:15px;line-height:1.6;color:#D6DEEA}
.bfd{display:inline-block;margin-bottom:10px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#0C161F;background:#fff;border-radius:999px;padding:5px 12px}.bfd.l{background:#F95505;color:#fff}
.bfc{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;align-items:center}.bfc span{font-size:13px;color:#D6DEEA;margin-right:4px}
.bfc div{min-width:58px;text-align:center;background:#16212E;border:1px solid #2A3A4D;border-radius:10px;padding:8px 6px}.bfc b{display:block;font-size:22px;font-weight:800;font-variant-numeric:tabular-nums}.bfc i{font-style:normal;font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;color:#9FB0C3}
.bfk{display:flex;gap:8px;overflow-x:auto;padding:16px 0 6px;scrollbar-width:none}.bfk::-webkit-scrollbar{display:none}
.bfk a{flex:none;display:inline-flex;align-items:center;min-height:40px;padding:0 14px;border:1px solid #D1D5DB;border-radius:999px;background:#fff;color:var(--n,#0C161F);font-size:13.5px;font-weight:600;text-decoration:none;white-space:nowrap}.bfk a.on{background:var(--o,#F95505);border-color:var(--o,#F95505);color:#fff}.bfk a small{font-weight:500;opacity:.75;margin-left:6px}
.bfg{padding-top:10px}.bfg .gr{grid-template-columns:repeat(5,minmax(0,1fr))}@media(max-width:1200px){.bfg .gr{grid-template-columns:repeat(4,minmax(0,1fr))}}
.bfn{font-size:13px;color:#6B7280;padding:6px 0 0}
.bfe{text-align:center;padding:60px 10px;color:#6B7280}.bfe b{display:block;font-size:18px;color:var(--n,#0C161F);margin-bottom:6px}
@media(max-width:900px){.bfh{grid-template-columns:1fr;padding:18px;gap:14px}.bfh h1{font-size:22px}.bfg .gr{grid-template-columns:repeat(2,minmax(0,1fr))}.bfc div{min-width:50px}.bfc b{font-size:19px}}
</style>
<div class="w bfw">
<div class="bfh">
<div><span class="bfd {{ $last ? 'l' : '' }}">{{ $over ? 'Ended' : ($last ? 'Cyber Monday · Last day' : $from->format('j') . ' to ' . $to->format('j F Y')) }}</span>
<h1>Black Friday on DutyFree Shop</h1>
<p>{{ $over ? 'The Black Friday campaign has ended. Thank you for shopping with DutyFreeBD.' : ($run ? 'Special Black Friday prices on the products below. The price you see is the price you pay; no other discount is added to a Black Friday price. Ends ' . $to->format('j F') . ' at 11:59 PM.' : 'Black Friday prices start on ' . $from->format('j F') . ' at 12:00 AM and end on ' . $to->format('j F') . ' at 11:59 PM.') }}</p>
@if($run)
<div class="bfc" id="bfc" data-end="{{ $to->copy()->utc()->format('Y-m-d\TH:i:s\Z') }}" aria-live="off"><span>Ends in</span><div><b data-u="d">--</b><i>Days</i></div><div><b data-u="h">--</b><i>Hours</i></div><div><b data-u="m">--</b><i>Min</i></div><div><b data-u="s">--</b><i>Sec</i></div></div>
@endif
</div>
<img src="/black-friday-badge.webp" alt="Black Friday at DutyFreeBD" width="900" height="295">
</div>
@if($run)
@if(count($chips) > 1)
<nav class="bfk" aria-label="Deal categories"><a href="/black-friday" class="{{ $cat === 0 ? 'on' : '' }}">All deals<small>{{ number_format($total) }}</small></a>
@foreach($chips as $c)
<a href="/black-friday?cat={{ $c['id'] }}" class="{{ $cat === $c['id'] ? 'on' : '' }}">{{ $c['name'] }}<small>{{ number_format($c['n']) }}</small></a>
@endforeach
</nav>
@endif
<div class="bfg">
@if($items->count())
<div class="gr">@foreach($items as $q)@include('store.card', ['fd'=>true])@endforeach</div>
@else
<div class="bfe"><b>No deal in this category right now</b>Please see all deals.</div>
@endif
@include('store.pager', ['pg'=>$items])
</div>
<p class="bfn">Prices and stock are live from the store. A product leaves this page when it sells out.</p>
<script>(function(){var c=document.getElementById('bfc');if(!c)return;var e=Date.parse(c.getAttribute('data-end'));function p(n){return(n<10?'0':'')+n}function t(){var s=Math.max(0,Math.floor((e-Date.now())/1000));var v={d:Math.floor(s/86400),h:Math.floor(s%86400/3600),m:Math.floor(s%3600/60),s:s%60};for(var k in v){var b=c.querySelector('[data-u='+k+']');if(b)b.textContent=p(v[k])}if(s<=0){clearInterval(i);location.reload()}}var i=setInterval(t,1000);t()})();</script>
@else
<div class="bfe"><b>{{ $over ? 'This campaign has ended' : 'Starts ' . $from->format('j F') . ' at 12:00 AM' }}</b><a href="/" style="color:var(--o,#F95505);font-weight:700">Continue shopping</a></div>
@endif
</div>
@endsection
