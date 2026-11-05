@extends('portal.layout', ['tab' => 'pay'])
@section('title', 'পেমেন্ট')
@php($U = \App\Support\Ui::class)
@section('bar')@include('portal._back', ['title' => 'পেমেন্ট', 'right' => 'বকেয়া <span class="n">'.$U::tk($due).'</span>'])@endsection
@section('content')
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px"><a class="chip {{ request('r', '2m') === '2m' ? 'on' : '' }}" href="?r=2m" style="text-decoration:none">২ মাস</a><a class="chip {{ request('r') === 'all' ? 'on' : '' }}" href="?r=all" style="text-decoration:none">সব</a></div>
@forelse($list as $x)
  @php($r = $x['r'])
  <div class="card" style="display:flex;flex-direction:column;gap:6px">
    <div style="display:flex;align-items:center"><div style="flex-grow:1;display:flex;flex-direction:column"><b>{{ ['cash' => 'নগদ', 'bkash' => 'বিকাশ', 'nagad' => 'নগদ (মোবাইল)', 'bank' => 'ব্যাংক'][$r->method] ?? $r->method }}</b><span class="n" style="font-size:12px;color:#51607a">{{ $r->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }} · {{ $r->reference }}</span></div><b class="n" style="font-size:18px;color:#0f6b3c">{{ $U::tk($r->credit) }}</b></div>
    <span style="font-size:13px;color:#51607a">{{ $r->sale ? 'ইনভয়েস '.$r->sale->invoice_no.'-এর '.($r->sale->created_at->isSameDay($r->created_at) ? 'সাথে' : 'বাকি') : 'আগের বকেয়া পরিশোধ' }}</span>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;font-size:13px"><div style="background:#f4f7fc;border-radius:10px;padding:6px 10px">আগের বকেয়া <b class="n">{{ $U::tk($x['prev']) }}</b></div><div style="background:#f4f7fc;border-radius:10px;padding:6px 10px">পরে বকেয়া <b class="n">{{ $U::tk($x['cur']) }}</b></div></div>
  </div>
@empty<p style="color:#51607a;margin:0">কোনো পেমেন্ট নেই।</p>@endforelse
<span style="font-size:13px;color:#51607a">আগের বকেয়া শোধ করলে সেটা নতুন ক্রয় হিসেবে ধরা হয় না।</span>
@endsection
