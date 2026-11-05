@extends('app.layout', ['tab' => 'sale'])
@section('title', 'ইনভয়েস')
@php($U = \App\Support\Ui::class)
@section('bar')@include('app._back', ['href' => route('app.sale'), 'title' => 'ইনভয়েস', 'right' => '<span class="pill" style="background:#e3f6ec;color:#0f6b3c">সার্ভারে নিশ্চিত ✓</span>'])@endsection
@section('content')
<article class="card" style="display:flex;flex-direction:column;gap:8px;font-size:14px">
  <div style="display:flex;gap:10px;align-items:center;border-bottom:1px dashed #c9d2e3;padding-bottom:8px"><img src="{{ $s['logo'] ?? '/img/logo.webp' }}" alt="" style="width:44px;height:44px;border-radius:10px">
    <div style="display:flex;flex-direction:column"><b>{{ $s['company_name'] ?? 'NK Corporation' }}</b><span style="font-size:12px;color:#51607a">{{ $s['brand'] ?? 'Aygaz United' }} LPG · {{ $s['phone'] ?? '' }}</span><span style="font-size:12px;color:#51607a">{{ $s['address_bn'] ?? $s['address_en'] ?? '' }}</span></div></div>
  @foreach([['ইনভয়েস নং', $sale->invoice_no], ['সময়', $sale->created_at->timezone('Asia/Dhaka')->format('d-m-Y H:i')], ['দোকান', $sale->buyerName()]] as [$k, $v])
    <div style="display:flex"><span style="flex-grow:1;color:#51607a">{{ $k }}</span><b class="{{ $loop->index < 2 ? 'n' : '' }}">{{ $v }}</b></div>
  @endforeach
  <div style="border-top:1px dashed #c9d2e3;padding-top:8px;display:flex;flex-direction:column;gap:4px">
    @foreach($sale->items as $i)
      <div style="display:flex"><span style="flex-grow:1">{{ $U::bn($i->size_kg) }} কেজি {{ $i->category === 'refill' ? 'রিফিল' : 'নতুন সিলিন্ডার' }} × {{ $i->qty }}</span><b class="n">{{ $U::tk($i->line_total) }}</b></div>
      <span style="font-size:12px;color:#51607a" class="n">{{ $U::tk($i->unit_price) }} × {{ $i->qty }}@if($i->category === 'refill') · <span style="font-family:'Hind Siliguri'">খালি পেলাম {{ $U::bn($sale->empties_received) }}টি</span>@endif</span>
    @endforeach
  </div>
  <div style="border-top:1px dashed #c9d2e3;padding-top:8px;display:flex;flex-direction:column;gap:4px">
    <div style="display:flex"><span style="flex-grow:1">{{ ['cash' => 'নগদ', 'bkash' => 'বিকাশ / নগদ', 'nagad' => 'নগদ (মোবাইল)', 'bank' => 'ব্যাংক', 'due' => 'বাকি'][$sale->payment_method] ?? '' }} জমা</span><b class="n">{{ $U::tk($sale->paid) }}</b></div>
    <div style="display:flex"><span style="flex-grow:1">এই বিলের বাকি</span><b class="n">{{ $U::tk($sale->due()) }}</b></div>
    @if($shopDue !== null)<div style="display:flex"><span style="flex-grow:1">দোকানের মোট বকেয়া</span><b class="n">{{ $U::tk($shopDue) }}</b></div>@endif
  </div>
  <span style="font-size:11px;color:#51607a" class="n">Salesman {{ $sale->user?->name }} · {{ $sale->trip?->vehicle?->code }} · TX {{ \Illuminate\Support\Str::limit((string) $sale->client_tx, 14, '') }}</span>
</article>
<span style="font-size:13px;color:#51607a">@if(str_contains((string) $sale->note, 'draft'))ড্রাফট {{ \Illuminate\Support\Str::after($sale->note, 'draft ') }} সিঙ্ক হয়ে চূড়ান্ত নম্বর পেয়েছে। @endif{{ $sms ? ($sms->status === 'sent' ? 'SMS পাঠানো হয়েছে '.$sms->to.' নম্বরে।' : ($sms->status === 'no_mobile' ? 'মোবাইল নম্বর নেই, SMS যায়নি।' : 'SMS: '.\App\Models\SmsMessage::STATUS[$sms->status])) : 'SMS চালু নেই।' }}</span>
<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
  <a class="chip" href="{{ route('app.print', $sale) }}" style="text-decoration:none">POS প্রিন্ট</a>
  <a class="chip" href="{{ route('app.receipt', ['sale' => $sale, 'size' => 'a4']) }}" target="_blank" style="text-decoration:none">A4 PDF</a>
  <form method="post" action="{{ route('app.sms', $sale) }}" style="display:contents">@csrf<button class="chip" type="submit">আবার SMS</button></form>
</div>
<a class="big" href="{{ route('app.sale') }}">পরের বিক্রি</a>
@endsection
