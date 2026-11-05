@extends('portal.layout', ['tab' => 'order'])
@section('title', 'গ্যাস অর্ডার')
@php($U = \App\Support\Ui::class)
@section('bar')@include('portal._back', ['title' => 'গ্যাস অর্ডার'])@endsection
@section('content')
<form method="post" action="{{ route('my.order.store') }}" style="display:flex;flex-direction:column;gap:12px">@csrf
  <input type="hidden" name="size" id="sz" value="12"><input type="hidden" name="qty" id="qv" value="1">
  <div style="display:flex;align-items:center"><span class="lb" style="flex-grow:1">ব্র্যান্ড</span><b>{{ $brand }}</b></div>
  <span class="lb">সিলিন্ডার সাইজ</span>
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">@foreach(\App\Models\Brand::SIZES as $s)<button type="button" class="chip szb {{ $loop->first ? 'on' : '' }}" data-s="{{ $s }}">{{ $U::bn($s) }} কেজি</button>@endforeach</div>
  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px"><label class="chip on" style="min-height:44px"><input type="radio" name="type" value="ex" checked style="width:auto;min-height:0"> রিফিল</label><label class="chip" style="min-height:44px"><input type="radio" name="type" value="nw" style="width:auto;min-height:0"> নতুন সিলিন্ডার</label></div>
  <div style="display:flex;align-items:center;gap:10px"><span class="lb" style="flex-grow:1">পরিমাণ</span><button class="step" type="button" id="m">−</button><span class="num" id="qn">1</span><button class="step" type="button" id="p">+</button></div>
  <label class="lb" style="display:flex;flex-direction:column;gap:6px">ডেলিভারির ঠিকানা<input class="txt" name="address" required maxlength="255" value="{{ old('address', $c->address) }}"></label>
  <label class="lb" style="display:flex;flex-direction:column;gap:6px">কবে চান<input class="txt" type="date" name="wanted_on" required min="{{ now()->timezone('Asia/Dhaka')->toDateString() }}" value="{{ now()->timezone('Asia/Dhaka')->addDay()->toDateString() }}"></label>
  <label class="lb" style="display:flex;flex-direction:column;gap:6px">মন্তব্য<input class="txt" name="note" maxlength="255" placeholder="যেমন: সকাল ১০টার আগে"></label>
  <span style="font-size:var(--fs-13);color:#51607a">অফিস অনুমোদন করলে আপনার রুটের গাড়ি আর সেলসম্যানের সাথে ডেলিভারি হবে। দাম ডেলিভারির দিনের অনুমোদিত তালিকা অনুযায়ী।</span>
  <button class="big" type="submit" style="background:#b45309">অর্ডার পাঠান</button>
</form>
<div class="card" style="display:flex;flex-direction:column;gap:2px"><b style="font-size:var(--fs-14)">আগের অর্ডার</b>
  @forelse($orders as $o)
    <div style="display:flex;align-items:center;gap:8px;padding:8px 0;border-top:1px solid #eef2f9"><div style="flex-grow:1;display:flex;flex-direction:column"><span style="font-size:var(--fs-14)">{{ $o->itemsText(true) }}</span><span class="n" style="font-size:var(--fs-12);color:#51607a">{{ $o->order_no }} · {{ $o->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }}</span></div>
      <span class="pill" style="{{ ['delivered' => 'background:#e3f6ec;color:#0f6b3c', 'rejected' => 'background:#fde9e7;color:#b3261e', 'new' => 'background:#e6eeff;color:#1d4ed8'][$o->status] ?? 'background:#fff1d6;color:#8a4b00' }}">{{ \App\Models\CustomerOrder::BN[$o->status] }}</span></div>
  @empty<span style="font-size:var(--fs-13);color:#51607a">আগে কোনো অর্ডার নেই।</span>@endforelse
</div>
@endsection
@push('js')
<script>(function(){ let q=1; const $=i=>document.getElementById(i); const d=()=>{ $('qv').value=q; $('qn').textContent=q; };
  $('p').onclick=()=>{q=Math.min(500,q+1);d();}; $('m').onclick=()=>{q=Math.max(1,q-1);d();};
  document.querySelectorAll('.szb').forEach(b=>b.onclick=()=>{ document.querySelectorAll('.szb').forEach(x=>x.classList.toggle('on',x===b)); $('sz').value=b.dataset.s; });
  document.querySelectorAll('input[name=type]').forEach(r=>r.onchange=()=>document.querySelectorAll('input[name=type]').forEach(x=>x.parentNode.classList.toggle('on',x.checked)));
})();</script>
@endpush
