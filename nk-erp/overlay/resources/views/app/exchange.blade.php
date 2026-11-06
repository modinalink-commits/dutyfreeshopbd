@extends('app.layout', ['tab' => 'home'])
@section('title', 'সিলিন্ডার এক্সচেঞ্জ')
@php($U = \App\Support\Ui::class)
@section('bar')@include('app._back', ['href' => route('app.home'), 'title' => 'সিলিন্ডার এক্সচেঞ্জ'])@endsection
@section('content')
<p style="margin:0;font-size:var(--fs-13);color:#51607a">দোকান অন্য ব্র্যান্ডের খালি দেবে, আমরা {{ $own?->name }}-এর ভরা দেব। দাম = রিফিল দাম + এক্সচেঞ্জ চার্জ। অফিস অনুমোদন দিলে তবেই মেমো হবে।</p>
@if(! $trip || $trip->status !== 'dispatched')
  <div class="card" style="background:#fff4e5;color:#8a4b00">চালু ট্রিপ নেই। গাড়ি লোড হয়ে বের হলে এক্সচেঞ্জ করা যাবে।</div>
@elseif(! $others->count())
  <div class="card" style="background:#fff4e5;color:#8a4b00">অন্য কোনো ব্র্যান্ড চালু নেই। অফিসে যোগাযোগ করুন।</div>
@else
<form method="post" action="{{ route('app.exchange.store') }}" id="xf" class="card" style="display:flex;flex-direction:column;gap:10px">@csrf
  <input type="hidden" name="tx" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
  <input type="hidden" name="qty" id="q" value="{{ old('qty', 1) }}">
  <span class="lb">দোকান</span>
  @if($cust)
    <input type="hidden" name="customer_id" value="{{ $cust->id }}">
    <div style="display:flex;align-items:center;gap:8px"><b style="flex-grow:1;font-size:var(--fs-15)">{{ $cust->name }}</b><a class="chip" href="{{ route('app.shops', ['pick' => 'exchange']) }}" style="min-height:40px;display:flex;align-items:center">বদলান</a></div>
  @else
    <a class="chip" href="{{ route('app.shops', ['pick' => 'exchange']) }}" style="min-height:44px;display:flex;align-items:center;justify-content:center">দোকান বাছাই করুন</a>
    <input class="txt" name="walkin_name" value="{{ old('walkin_name') }}" placeholder="অথবা খুচরা ক্রেতার নাম">
    <input class="txt" name="walkin_phone" type="tel" value="{{ old('walkin_phone') }}" placeholder="মোবাইল নম্বর (ইচ্ছামতো)">
  @endif
  <span class="lb">দোকান কোন ব্র্যান্ডের খালি দিচ্ছে</span>
  <select class="txt" name="given_brand_id" id="gb" style="min-height:48px">
    @foreach($others as $b)<option value="{{ $b->id }}" data-sizes="{{ implode(',', $b->sizes ?? []) }}" @selected(old('given_brand_id') == $b->id)>{{ $b->name }}</option>@endforeach
  </select>
  <span class="lb">সাইজ</span>
  <input type="hidden" name="size_kg" id="sz" value="{{ old('size_kg', 12) }}">
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px">@foreach(\App\Models\Brand::SIZES as $s)<button type="button" class="chip szb" data-s="{{ $s }}" style="min-height:44px">{{ $U::bn($s) }} কেজি</button>@endforeach</div>
  <div style="display:flex;align-items:center;gap:10px"><span class="lb" style="flex-grow:1">কয়টি</span>
    <button class="step" type="button" id="m">−</button><span class="num" id="qn">1</span><button class="step" type="button" id="p">+</button></div>
  <div id="qt" style="background:#f5f8ff;border-radius:12px;padding:10px 12px;display:grid;grid-template-columns:1fr auto;gap:4px 10px;font-size:var(--fs-13)" aria-live="polite"></div>
  <span class="lb">টাকা</span>
  <input type="hidden" name="method" id="md" value="{{ old('method', 'cash') }}">
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px">
    @foreach(['cash' => 'নগদ', 'bkash' => 'বিকাশ', 'due' => 'বাকি'] as $k => $v)<button type="button" class="chip mdb" data-m="{{ $k }}" style="min-height:44px;{{ $k === 'due' && ! $cust ? 'opacity:.45' : '' }}" @if($k === 'due' && ! $cust) disabled title="বাকির জন্য দোকান বাছাই করুন" @endif>{{ $v }}</button>@endforeach
  </div>
  <label id="pw" style="display:none;flex-direction:column;gap:4px"><span class="lb">এখন কত পেলেন (৳)</span><input class="txt" type="number" name="paid" min="0" step="1" value="{{ old('paid', 0) }}"></label>
  <input class="txt" name="note" value="{{ old('note') }}" placeholder="নোট (ইচ্ছামতো)">
  <button class="big" type="submit" id="go" style="background:#5b2fa8">অনুমোদনের জন্য পাঠান</button>
  <span style="font-size:var(--fs-12);color:#51607a">অনুমোদন না হওয়া পর্যন্ত মেমো নম্বর হবে না। ভরা সিলিন্ডার দেওয়ার আগে অফিসের অনুমোদন দেখে নিন।</span>
</form>
@endif
<div style="display:flex;flex-direction:column;gap:8px"><span class="lb">এই ট্রিপের এক্সচেঞ্জ</span>
  @forelse($mine as $x)
    <div class="card" style="display:flex;align-items:center;gap:10px">
      <div style="flex-grow:1;display:flex;flex-direction:column"><b>{{ $x->ref_no }} · {{ $x->buyer() }}</b>
        <span style="font-size:var(--fs-12);color:#51607a">{{ $x->qty }} × {{ $x->givenBrand?->name }} {{ $U::bn($x->size_kg) }} কেজি → {{ $own?->name }} · ৳{{ $U::bd($x->total) }}</span>
        @if($x->decision_note)<span style="font-size:var(--fs-12);color:#b3261e">{{ $x->decision_note }}</span>@endif</div>
      @if($x->sale)<a class="chip" href="{{ route('app.invoice', $x->sale) }}" style="min-height:40px;display:flex;align-items:center;white-space:nowrap;flex-shrink:0;background:#e3f6ec;color:#0f6b3c">{{ \App\Models\CylinderExchange::STATUS_BN[$x->status] }} · মেমো</a>
      @else<span class="chip" style="min-height:40px;display:flex;align-items:center;white-space:nowrap;flex-shrink:0;{{ $x->status === 'rejected' ? 'background:#fde8e6;color:#b3261e' : 'background:#efe7fb;color:#5b2fa8' }}">{{ \App\Models\CylinderExchange::STATUS_BN[$x->status] }}</span>@endif
    </div>
  @empty
    <p style="color:#51607a;margin:0">এখনও কোনো এক্সচেঞ্জ হয়নি।</p>
  @endforelse
  @if($mine->where('status', 'pending')->count())<a class="chip" href="{{ route('app.exchange') }}" style="min-height:44px;display:flex;align-items:center;justify-content:center">অবস্থা আবার দেখুন</a>@endif
</div>
@endsection
@if($trip && $trip->status === 'dispatched' && $others->count())
@push('js')
<script>
(function(){ const Q=@json($quotes), $=i=>document.getElementById(i); if(!$('xf')) return; let q=Math.max(1,parseInt($('q').value,10)||1);
  const tk=n=>'৳'+Number(n).toLocaleString('en-IN',{maximumFractionDigits:2});
  function sizes(){ const ok=($('gb').selectedOptions[0].dataset.sizes||'').split(','); let first=null;
    document.querySelectorAll('.szb').forEach(b=>{ const on=ok.indexOf(b.dataset.s)>=0; b.disabled=!on; if(on&&!first)first=b.dataset.s; });
    if(ok.indexOf($('sz').value)<0&&first)$('sz').value=first; }
  function draw(){ sizes(); document.querySelectorAll('.szb').forEach(b=>b.classList.toggle('on',b.dataset.s===$('sz').value));
    document.querySelectorAll('.mdb').forEach(b=>b.classList.toggle('on',b.dataset.m===$('md').value)); $('pw').style.display=$('md').value==='due'?'flex':'none';
    $('q').value=q; $('qn').textContent=q;
    const p=(Q[$('gb').value]||{})[$('sz').value];
    if(!p){ $('qt').innerHTML='<span style="grid-column:1/-1;color:#b3261e">এই সাইজের দাম ঠিক করা নেই। অফিসকে দাম বসাতে বলুন।</span>'; $('go').disabled=true; return; }
    $('go').disabled=false;
    $('qt').innerHTML='<span>রিফিল '+tk(p.unit)+' × '+q+'</span><b style="text-align:right">'+tk(p.unit*q)+'</b><span>এক্সচেঞ্জ চার্জ '+tk(p.charge)+' × '+q+'</span><b style="text-align:right">'+tk(p.charge*q)+'</b><span style="font-weight:700">মোট</span><b style="text-align:right;font-size:var(--fs-16)">'+tk((p.unit+p.charge)*q)+'</b>'; }
  document.querySelectorAll('.szb').forEach(b=>b.onclick=()=>{ $('sz').value=b.dataset.s; draw(); });
  document.querySelectorAll('.mdb').forEach(b=>b.onclick=()=>{ if(!b.disabled){ $('md').value=b.dataset.m; draw(); } });
  $('gb').onchange=draw; $('p').onclick=()=>{q=Math.min(100,q+1);draw();}; $('m').onclick=()=>{q=Math.max(1,q-1);draw();};
  $('xf').addEventListener('submit',()=>{ $('go').disabled=true; $('go').textContent='পাঠানো হচ্ছে…'; });
  draw();
})();
</script>
@endpush
@endif
