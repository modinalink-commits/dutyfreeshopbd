@extends('app.layout', ['tab' => 'home'])
@section('title', 'হোম')
@php($U = \App\Support\Ui::class)
@php($ini = collect(explode(' ', $e->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''))
@section('bar')
<header style="padding:16px 16px 10px;display:flex;flex-direction:column;gap:8px;border-bottom:1px solid #dbe3f0">
  <div style="display:flex;align-items:center;gap:10px">
    <div style="width:40px;height:40px;border-radius:999px;background:#123a86;color:#fff;display:flex;align-items:center;justify-content:center;font:700 13px 'Plus Jakarta Sans',sans-serif">{{ mb_strtoupper($ini) }}</div>
    <div style="flex-grow:1;display:flex;flex-direction:column"><span style="font-size:var(--fs-16);font-weight:700;line-height:20px">{{ $e->name }}</span><span style="font-size:var(--fs-12);color:#51607a">{{ $trip ? 'ট্রিপ '.$trip->trip_no.' · '.$trip->trip_date->format('d-m-Y') : 'আজ কোনো চালু ট্রিপ নেই' }}</span></div>
    <a href="{{ route('app.sales') }}" id="syncPill" style="display:none;align-items:center;gap:6px;min-height:36px;padding:0 12px;border-radius:999px;background:#fff1d6;color:#8a4b00;font-size:var(--fs-12);font-weight:700;text-decoration:none"><span style="width:8px;height:8px;border-radius:999px;background:#c46f00"></span><span id="syncTxt"></span></a>
    <a href="{{ route('app.notify') }}" aria-label="নোটিফিকেশন" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;color:#123a86"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/></svg></a>
  </div>
  @if($trip)<span style="font-size:var(--fs-13);color:#51607a">গাড়ি {{ $trip->vehicle->reg_no ?: $trip->vehicle->code }} · রুট {{ $trip->route?->name ?? '—' }} · ড্রাইভার {{ $trip->driver?->name ?? '—' }}</span>@endif
</header>
@endsection
@section('content')
<style>.qa{min-height:64px;border-radius:14px;border:1px solid #7b8aa8;background:#fff;color:#14213d;display:flex;align-items:center;justify-content:center;text-align:center;font:600 14px 'Hind Siliguri',sans-serif;text-decoration:none;padding:0 6px}
.t{border-radius:12px;padding:8px 10px;display:flex;flex-direction:column;background:#f4f7fc}.t span:first-child{font-size:var(--fs-12);color:#51607a;font-weight:500}.t span:last-child{font:700 17px 'Plus Jakarta Sans',sans-serif}</style>
@if(! $trip)
  <div class="card" style="background:#fff1d6;border:0;color:#8a4b00">অফিস থেকে আপনার গাড়ির লোড অনুমোদন হলে এখানে ট্রিপ দেখাবে।</div>
@else
<section style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
  <div class="t"><span>লোড</span><span>{{ $c['loadedTotal'] }}</span></div><div class="t"><span>বিক্রি</span><span>{{ $c['soldTotal'] }}</span></div><div class="t"><span>খালি সংগ্রহ</span><span>{{ $c['collectedTotal'] }}</span></div>
</section>
<section class="card" style="border-radius:16px;display:flex;flex-direction:column;gap:8px">
  <span style="font-size:var(--fs-13);font-weight:700">গাড়িতে ভরা সিলিন্ডার আছে</span>
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
    @foreach($onVeh as $s => $q)<div style="background:#e6eeff;border-radius:12px;padding:8px 10px;display:flex;flex-direction:column"><span style="font-size:var(--fs-12);color:#51607a">{{ $U::bn($s) }} কেজি</span><span style="font:700 24px 'Plus Jakarta Sans',sans-serif;color:#123a86">{{ $q }}</span></div>@endforeach
  </div>
</section>
<section style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
  <div class="t"><span>আজকের বিক্রি</span><span>{{ $U::tk($c['totalSales']) }}</span></div>
  <div class="t" style="background:#e3f6ec"><span>নগদ আদায়</span><span style="color:#0f6b3c">{{ $U::tk($c['cashCollected']) }}</span></div>
  <div class="t" style="background:#fff1d6"><span>বকেয়া</span><span style="color:#8a4b00">{{ $U::tk($c['credit']) }}</span></div>
</section>
@endif
@if($orders->isNotEmpty())
<section class="card" style="border-radius:16px;display:flex;flex-direction:column;gap:6px;background:#efe7fb;border:0"><b style="font-size:var(--fs-14);color:#5b2fa8">গ্রাহকের অর্ডার · {{ $orders->count() }}টি</b>
  @foreach($orders as $o)<a href="{{ route('app.sale', ['customer' => $o->customer_id]) }}" style="display:flex;align-items:center;gap:8px;padding:6px 0;border-top:1px solid #dccdf5;text-decoration:none;color:#14213d"><span style="flex-grow:1;font-size:var(--fs-14)">{{ $o->customer->name }} · {{ $o->itemsText(true) }}</span><span class="n" style="font-size:var(--fs-12);color:#5b2fa8">{{ $o->order_no }}</span></a>@endforeach
</section>
@endif
<a href="{{ route('app.sale') }}" style="min-height:64px;border-radius:16px;background:#b45309;color:#fff;display:flex;align-items:center;justify-content:center;gap:10px;font-size:var(--fs-20);font-weight:700;text-decoration:none"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>নতুন বিক্রি</a>
<section style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
  <a class="qa" href="{{ route('app.empty') }}">খালি সংগ্রহ</a><a class="qa" href="{{ route('app.pay') }}">টাকা আদায়</a><a class="qa" href="{{ route('app.shops', ['pick' => 'view']) }}">দোকানের তালিকা</a>
  <a class="qa" href="{{ route('app.sales') }}">আজকের বিক্রি</a><a class="qa" href="{{ route('app.summary') }}">দিনের সারাংশ</a>
  <a class="qa" href="{{ route('app.duty') }}">ডিউটি ও লোকেশন</a><a class="qa" href="{{ route('app.expense') }}">খরচের দাবি</a>
  <form method="post" action="{{ route('app.logout') }}" style="display:contents">@csrf<button class="qa" type="submit" style="color:#51607a;cursor:pointer">লগআউট</button></form>
</section>
@endsection
@push('js')
<script>
(function(){ const f=()=>{const n=NK.get().length,p=document.getElementById('syncPill'); p.style.display=n?'flex':'none'; document.getElementById('syncTxt').textContent=n+'টি সিঙ্ক বাকি';}; f(); document.addEventListener('nk-synced',f); if(navigator.onLine) NK.sync(); })();
</script>
@endpush
