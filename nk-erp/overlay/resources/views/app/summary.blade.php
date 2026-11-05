@extends('app.layout', ['tab' => 'summary'])
@section('title', 'দিনের সারাংশ')
@php($U = \App\Support\Ui::class)
@section('bar')@include('app._back', ['href' => route('app.home'), 'title' => 'দিনের সারাংশ', 'right' => now()->timezone('Asia/Dhaka')->format('H:i')])@endsection
@section('content')
@if(! $c)
  <p style="color:#51607a;margin:0">চালু ট্রিপ নেই।</p>
@else
@php($ln = fn ($k, $v, $b = false) => '<div style="display:flex;font-size:14px;padding:6px 0;border-top:1px solid #eef2f9"><span style="flex-grow:1;'.($b ? 'font-weight:700' : 'color:#51607a').'">'.e($k).'</span><b class="n">'.e($v).'</b></div>')
<div class="card"><span class="lb">সিলিন্ডার</span>
  {!! $ln('সকালে লোড', $c['loadedTotal']) !!}{!! $ln('মোট বিক্রি', $c['soldTotal']) !!}{!! $ln('অবিক্রীত ভরা ফেরত', $trip->status === 'dispatched' ? $c['loadedTotal'] - $c['soldTotal'] : $c['returnedTotal']) !!}{!! $ln('খালি সংগ্রহ', $c['collectedTotal']) !!}{!! $ln('খালি গুদামে ফেরত', $trip->status === 'dispatched' ? 'গুদাম গুনবে' : $c['emptyReturnedTotal']) !!}
</div>
<div class="card"><span class="lb">টাকা</span>
  {!! $ln('মোট বিক্রি', $U::tk($c['totalSales'])) !!}{!! $ln('নগদ আদায় (আগের বকেয়া সহ)', $U::tk($c['cashCollected'])) !!}{!! $ln('ডিজিটাল আদায়', $U::tk($c['digitalTotal'])) !!}{!! $ln('বাকি দেওয়া', $U::tk($c['credit'])) !!}{!! $ln('গাড়ির খরচ', $U::tk($c['expFromCash'])) !!}{!! $ln('হাতে নগদ জমা দেবো', $U::tk($c['expected']), true) !!}{!! $ln('স্টক ও টাকার পার্থক্য', $trip->status === 'settled' || $trip->status === 'closed' ? ($c['stockOk'] && $c['cashOk'] ? 'মিলেছে' : 'পার্থক্য আছে') : 'যাচাইয়ের পরে') !!}
</div>
@php($st = $trip->status)
@php($steps = [['সেলসম্যান রিকোয়েস্ট পাঠাবেন', $trip->return_requested_at ? 'পাঠানো হয়েছে' : 'এখন', ! $trip->return_requested_at], ['গুদাম ভরা ও খালি সিলিন্ডার গুনবে', in_array($st, ['returned', 'settled', 'closed']) ? 'হয়েছে' : 'অপেক্ষা', (bool) $trip->return_requested_at && $st === 'dispatched'], ['হিসাবরক্ষক নগদ গুনবেন', in_array($st, ['settled', 'closed']) ? 'হয়েছে' : 'অপেক্ষা', $st === 'returned'], ['ম্যানেজার মিলিয়ে ট্রিপ বন্ধ করবেন', $st === 'closed' ? 'বন্ধ' : 'অপেক্ষা', $st === 'settled']])
<div class="card" style="display:flex;flex-direction:column;gap:8px"><span class="lb">ক্লোজিং কীভাবে হবে</span>
  @foreach($steps as $i => [$t, $s, $on])<div style="display:flex;align-items:center;gap:10px;color:{{ $on ? '#8a4b00' : '#51607a' }}"><span style="width:24px;height:24px;border-radius:999px;display:flex;align-items:center;justify-content:center;font:700 12px 'Plus Jakarta Sans',sans-serif;flex-shrink:0;background:{{ $on ? '#fff1d6' : '#eef2f9' }}">{{ $i + 1 }}</span><span style="flex-grow:1;font-size:14px">{{ $t }}</span><span style="font-size:12px;font-weight:700">{{ $s }}</span></div>@endforeach
</div>
@if($st === 'dispatched' && ! $trip->return_requested_at)
<form method="post" action="{{ route('app.close') }}" id="cf">@csrf<input type="hidden" name="pending" id="pend" value="0"><button class="big" type="submit" style="background:#123a86">গাড়ি ফেরত ও ক্লোজিং রিকোয়েস্ট পাঠান</button></form>
<span style="font-size:12px;color:#51607a;text-align:center" id="pn">সব বিক্রি সিঙ্ক না হলে পাঠানো যাবে না</span>
@endif
@endif
@endsection
@push('js')
<script>(function(){ const p=document.getElementById('pend'); if(!p) return; const n=NK.get().length; p.value=n; if(n){ document.querySelector('#cf button').disabled=true; document.getElementById('pn').textContent=n+'টি বিক্রি সিঙ্ক বাকি — আগে সিঙ্ক করুন'; } })();</script>
@endpush
