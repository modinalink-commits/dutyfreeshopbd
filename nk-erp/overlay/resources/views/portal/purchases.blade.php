@extends('portal.layout', ['tab' => 'buy'])
@section('title', 'ক্রয়ের ইতিহাস')
@php($U = \App\Support\Ui::class)
@section('bar')@include('portal._back', ['title' => 'ক্রয়ের ইতিহাস'])@endsection
@section('content')
<form method="get" style="display:flex;flex-direction:column;gap:8px"><input class="txt" name="q" value="{{ $q }}" placeholder="ইনভয়েস নম্বর খুঁজুন">
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px"><a class="chip {{ $r === '2m' ? 'on' : '' }}" href="?r=2m" style="text-decoration:none">২ মাস</a><a class="chip {{ $r === 'month' ? 'on' : '' }}" href="?r=month" style="text-decoration:none">এই মাস</a><label class="chip {{ $r === 'date' ? 'on' : '' }}" style="position:relative">তারিখ বাছুন<input type="date" name="from" onchange="location='?r=date&from='+this.value" style="position:absolute;inset:0;opacity:0"></label></div></form>
@forelse($inv as $v)
  <a href="{{ route('my.invoice', $v) }}" class="card" style="display:flex;flex-direction:column;gap:6px;text-decoration:none;color:inherit">
    <div style="display:flex"><b class="n" style="flex-grow:1">{{ $v->invoice_no }}</b><span class="n" style="font-size:var(--fs-12);color:#51607a">{{ $v->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }}</span></div>
    @foreach($v->items as $l)<div style="display:flex;font-size:var(--fs-14)"><span style="flex-grow:1">{{ $U::bn($l->size_kg) }} কেজি {{ $l->category === 'refill' ? 'রিফিল' : 'নতুন সিলিন্ডার' }} × {{ $l->qty }}</span><span class="n">{{ $U::tk($l->unit_price) }}</span></div>@endforeach
    <div style="display:flex;align-items:center;gap:6px"><span class="pill" style="{{ $v->due() > 0 ? 'background:#fff1d6;color:#8a4b00' : 'background:#e3f6ec;color:#0f6b3c' }}">{{ $v->due() > 0 ? ($v->paid > 0 ? 'আংশিক · বাকি '.$U::tk($v->due()) : 'বাকি '.$U::tk($v->due())) : 'পরিশোধিত' }}</span><span class="pill" style="background:#eef2f9;color:#51607a">ডেলিভারি সম্পন্ন</span><b class="n" style="margin-left:auto">{{ $U::tk($v->total) }}</b></div>
  </a>
@empty<p style="color:#51607a;margin:0">এই সময়ে কোনো ক্রয় নেই।</p>@endforelse
<span style="font-size:var(--fs-13);color:#51607a">{{ $U::bn($inv->count()) }}টি ইনভয়েস · মোট {{ $U::tk($inv->sum('total')) }}</span>
@endsection
