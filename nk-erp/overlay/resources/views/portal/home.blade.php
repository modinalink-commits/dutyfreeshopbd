@extends('portal.layout', ['tab' => 'home'])
@section('title', 'আমার হিসাব')
@php($U = \App\Support\Ui::class)
@section('bar')
<header style="padding:16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid #dbe3f0">
  <div style="flex-grow:1;display:flex;flex-direction:column"><span style="font-size:var(--fs-12);color:#51607a">স্বাগতম</span><b style="font-size:var(--fs-18)">{{ $c->name }}</b><span class="n" style="font-size:var(--fs-12);color:#51607a">{{ $c->code }} · <span style="font-family:'Hind Siliguri'">{{ ['retail' => 'খুচরা', 'commercial' => 'হোটেল / বাণিজ্যিক', 'dealer' => 'ডিলার'][$c->type] ?? $c->type }}</span></span></div>
  <form method="post" action="{{ route('my.logout') }}">@csrf<button class="chip" type="submit" style="min-height:40px;padding:0 12px;font-size:var(--fs-13)">লগআউট</button></form>
</header>
@endsection
@section('content')
<div style="background:{{ $due > 0 ? '#fff1d6' : '#e3f6ec' }};border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:2px">
  <span style="font-size:var(--fs-13);color:#51607a">বর্তমান বকেয়া</span><b class="n" style="font-size:var(--fs-24);color:{{ $due > 0 ? '#8a4b00' : '#0f6b3c' }}">{{ $U::tk($due) }}</b>
  @if($lastSale && $due > 0)<span class="n" style="font-size:var(--fs-12);color:#51607a">{{ $lastSale->invoice_no }} <span style="font-family:'Hind Siliguri'">থেকে</span> · {{ $lastSale->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }}</span>@endif
</div>
@php($kv = fn ($k, $v) => '<div style="display:flex;font-size:var(--fs-14);padding:7px 0;border-top:1px solid #eef2f9"><span style="flex-grow:1;color:#51607a">'.e($k).'</span><b class="n">'.e($v).'</b></div>')
<div class="card">
  {!! $kv('মোট ক্রয় ('.$U::bn(now()->year).')', $U::tk($yearBuy)) !!}{!! $kv('মোট পরিশোধ', $U::tk($yearPay)) !!}{!! $kv('মোট সিলিন্ডার কেনা', $bought) !!}{!! $kv('খালি ফেরত দিয়েছি', max(0, $returned)) !!}
  {!! $kv('খালি দেওয়া বাকি', $out ? collect($out)->map(fn ($q, $s) => $q.' · '.$U::bn($s).' কেজি')->implode(', ') : '০') !!}
  {!! $kv('শেষ ক্রয় / পেমেন্ট', ($lastSale ? $lastSale->created_at->timezone('Asia/Dhaka')->format('d-m') : '—').' / '.($lastPay ? $lastPay->created_at->timezone('Asia/Dhaka')->format('d-m') : '—')) !!}
</div>
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px"><a class="chip" href="{{ route('my.statement') }}" style="text-decoration:none">স্টেটমেন্ট</a><a class="chip on" href="{{ route('my.order') }}" style="text-decoration:none">গ্যাস অর্ডার করুন</a></div>
<div class="card" style="display:flex;flex-direction:column;gap:2px"><b style="font-size:var(--fs-14)">সাম্প্রতিক লেনদেন</b>
  @forelse($tx as $x)
    @php($isPay = $x->kind === 'payment')
    <a href="{{ $x->sale_id ? route('my.invoice', $x->sale_id) : route('my.payments') }}" style="display:flex;align-items:center;gap:8px;padding:8px 0;border-top:1px solid #eef2f9;text-decoration:none;color:inherit">
      <div style="flex-grow:1;display:flex;flex-direction:column"><span style="font-size:var(--fs-14)">{{ $isPay ? 'পেমেন্ট · '.(['cash' => 'নগদ', 'bkash' => 'বিকাশ', 'nagad' => 'নগদ', 'bank' => 'ব্যাংক'][$x->method] ?? '') : ($x->kind === 'sale' ? 'ক্রয় '.$x->reference : 'সমন্বয় '.$x->reference) }}</span><span class="n" style="font-size:var(--fs-12);color:#51607a">{{ $x->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }}</span></div>
      <b class="n" style="color:{{ $isPay ? '#0f6b3c' : '#14213d' }}">{{ $isPay ? '−' : '+' }}{{ $U::tk($isPay ? $x->credit : (float) $x->debit - (float) $x->credit) }}</b></a>
  @empty<span style="font-size:var(--fs-13);color:#51607a">এখনো কোনো লেনদেন নেই।</span>@endforelse
</div>
@endsection
