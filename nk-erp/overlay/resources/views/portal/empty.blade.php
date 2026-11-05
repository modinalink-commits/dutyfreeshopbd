@extends('portal.layout', ['tab' => 'empty'])
@section('title', 'খালি সিলিন্ডার')
@php($U = \App\Support\Ui::class)
@section('bar')@include('portal._back', ['title' => 'খালি সিলিন্ডার'])@endsection
@section('content')
<span style="font-size:13px;color:#51607a">ব্র্যান্ড আর সাইজ ধরে আলাদা হিসাব</span>
<div class="card" style="display:flex;flex-direction:column;gap:6px">
  <div style="display:flex"><b style="flex-grow:1">{{ $brand }}</b><span style="font-size:12px;color:#51607a">রিফিল / এক্সচেঞ্জ</span></div>
  <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;font-size:12px;color:#51607a;gap:4px"><span>সাইজ</span><span style="text-align:right">কেনা</span><span style="text-align:right">ফেরত</span><span style="text-align:right">বাকি</span></div>
  @foreach($bySize as $s => $r)<div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;font-size:15px;gap:4px;border-top:1px solid #eef2f9;padding-top:6px"><span>{{ $U::bn($s) }} কেজি</span><b class="n" style="text-align:right">{{ $r['bought'] }}</b><b class="n" style="text-align:right">{{ max(0, $r['ret']) }}</b><b class="n" style="text-align:right;color:{{ $r['owed'] > 0 ? '#8a4b00' : '#0f6b3c' }}">{{ $r['owed'] }}</b></div>@endforeach
  @if($newPkg->isNotEmpty())<span style="font-size:12px;color:#51607a">নতুন সিলিন্ডার কেনা: {{ $newPkg->map(fn ($q, $s) => $U::bn($s).' কেজি × '.$q)->implode(', ') }} — খালি দিতে হয় না, তাই উপরের হিসাবে নেই।</span>@endif
</div>
<span style="font-size:12px;color:#51607a">অন্য ব্র্যান্ডের খালি নিজে থেকে {{ $brand }}-এর হিসাবে যোগ হয় না; অফিস অনুমোদন দিলে যোগ হবে।</span>
<div class="card" style="display:flex;flex-direction:column;gap:2px"><b style="font-size:14px">সাম্প্রতিক খালি লেনদেন</b>
  @forelse($tx as $x)
    @php($pend = $x->empties_expected - $x->empties_received)
    <div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-top:1px solid #eef2f9"><div style="flex-grow:1;display:flex;flex-direction:column"><span style="font-size:14px">{{ $x->items->where('category', 'refill')->map(fn ($i) => $U::bn($i->size_kg).' কেজি')->implode(', ') }} · {{ $U::bn($x->empties_expected) }}টি কেনা, {{ $U::bn($x->empties_received) }}টি খালি দিলাম</span><span class="n" style="font-size:12px;color:#51607a">{{ $x->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }} · {{ $x->invoice_no }}</span></div>
      <b style="font-size:13px;color:{{ $pend > 0 ? '#8a4b00' : '#0f6b3c' }}">{{ $pend > 0 ? $U::bn($pend).'টি বাকি' : 'মিলেছে' }}</b></div>
  @empty<span style="font-size:13px;color:#51607a">কোনো লেনদেন নেই।</span>@endforelse
</div>
@endsection
