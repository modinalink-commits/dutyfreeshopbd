@extends('app.layout', ['tab' => 'pay'])
@section('title', 'টাকা আদায়')
@php($U = \App\Support\Ui::class)
@section('bar')@include('app._back', ['href' => route('app.home'), 'title' => 'টাকা আদায়'])@endsection
@section('content')
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px">
  <a class="chip {{ $tab === 'today' ? 'on' : '' }}" href="{{ route('app.pay', ['tab' => 'today']) }}" style="text-decoration:none">আজকের ইনভয়েস</a>
  <a class="chip {{ $tab !== 'today' ? 'on' : '' }}" href="{{ route('app.shops', ['pick' => 'pay']) }}" style="text-decoration:none">আগের বকেয়া</a>
</div>
@if($tab === 'today' && ! $cust)
  @forelse($todayDue as $s)
    <a href="{{ route('app.pay', ['customer' => $s->customer_id, 'sale' => $s->id]) }}" class="card" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit"><div style="flex-grow:1;display:flex;flex-direction:column"><b>{{ $s->buyerName() }}</b><span class="n" style="font-size:12px;color:#51607a">{{ $s->invoice_no }}</span></div><b class="n" style="color:#8a4b00">{{ $U::tk($s->due()) }}</b></a>
  @empty<p style="color:#51607a;margin:0">আজকের কোনো ইনভয়েসে বাকি নেই।</p>@endforelse
@elseif(! $cust)
  <p style="color:#51607a;margin:0">"আগের বকেয়া" চেপে দোকান বাছাই করুন।</p>
@else
<div class="card" style="display:flex;flex-direction:column;gap:4px">
  <div style="display:flex;align-items:center"><b style="flex-grow:1;font-size:16px">{{ $cust->name }}</b><span class="n" style="font-size:12px;color:#51607a">{{ $cust->phone }}</span></div>
  <div style="display:flex;align-items:center"><span style="flex-grow:1;color:#51607a">মোট বকেয়া</span><b class="n" style="font-size:22px;color:#8a4b00">{{ $U::tk($due) }}</b></div>
  @if($oldest)<span style="font-size:12px;color:#51607a">সবচেয়ে পুরোনো বিল {{ $U::bn((int) $oldest->created_at->diffInDays(now())) }} দিন আগের</span>@endif
</div>
@if($due > 0 && $trip && $trip->status === 'dispatched')
<form method="post" action="{{ route('app.pay.store') }}" id="pf" style="display:flex;flex-direction:column;gap:12px">@csrf
  <input type="hidden" name="customer_id" value="{{ $cust->id }}"><input type="hidden" name="tx" id="tx"><input type="hidden" name="method" id="mt" value="cash">
  @if(request('sale'))<input type="hidden" name="sale_id" value="{{ request('sale') }}">@endif
  <label class="lb" style="display:flex;flex-direction:column;gap:6px">কত টাকা পেলেন<input class="inp" name="amount" id="amt" inputmode="numeric" required value="{{ (int) round($due) }}"></label>
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px"><button type="button" class="chip qk" data-v="1">পুরোটা</button><button type="button" class="chip qk" data-v=".5">অর্ধেক</button><button type="button" class="chip qk" data-v="0">অন্য পরিমাণ</button></div>
  <span class="lb">কীভাবে পেলেন</span>
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px"><button type="button" class="chip mtb on" data-m="cash">নগদ</button><button type="button" class="chip mtb" data-m="bkash">বিকাশ / নগদ</button><button type="button" class="chip mtb" data-m="bank">ব্যাংক</button></div>
  <div style="display:flex;align-items:center;background:#e3f6ec;border-radius:12px;padding:10px 12px"><span style="flex-grow:1">আদায়ের পরে বকেয়া</span><b class="n" id="after" style="font-size:18px;color:#0f6b3c">৳0</b></div>
  <span style="font-size:12px;color:#51607a">আগের বকেয়া আদায় আজকের বিক্রিতে যোগ হয় না — শুধু টাকা আদায়ে আর দোকানের লেজারে যায়।</span>
  @if($hist->isNotEmpty())<div class="card" style="display:flex;flex-direction:column;gap:4px"><b style="font-size:13px">আগের আদায়</b>@foreach($hist as $h)<div style="display:flex;font-size:13px"><span class="n" style="flex-grow:1">{{ $h->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }} · {{ ['cash' => 'নগদ', 'bkash' => 'বিকাশ', 'nagad' => 'নগদ', 'bank' => 'ব্যাংক'][$h->method] ?? $h->method }}</span><b class="n">{{ $U::tk($h->credit) }}</b></div>@endforeach</div>@endif
  <button class="big" type="submit" id="sv" style="background:#0f6b3c">আদায় সংরক্ষণ</button>
</form>
@endif
@endif
@endsection
@push('js')
<script>
(function(){ const f=document.getElementById('pf'); if(!f) return; const due={{ (float) $due }}, a=document.getElementById('amt'), tk=n=>'৳'+Math.round(n).toLocaleString('en-IN');
  document.getElementById('tx').value=crypto.randomUUID?crypto.randomUUID():Date.now()+'-'+Math.random();
  const d=()=>{ const v=Math.min(due,+String(a.value).replace(/[^0-9.]/g,'')||0); document.getElementById('after').textContent=tk(due-v); document.getElementById('sv').textContent='আদায় সংরক্ষণ — '+tk(v); };
  document.querySelectorAll('.qk').forEach(b=>b.onclick=()=>{ if(+b.dataset.v) a.value=Math.round(due*b.dataset.v); else {a.value='';a.focus();} d(); });
  document.querySelectorAll('.mtb').forEach(b=>b.onclick=()=>{ document.querySelectorAll('.mtb').forEach(x=>x.classList.toggle('on',x===b)); document.getElementById('mt').value=b.dataset.m; });
  a.oninput=d; d(); f.onsubmit=()=>{ a.value=String(a.value).replace(/[^0-9.]/g,''); };
})();
</script>
@endpush
