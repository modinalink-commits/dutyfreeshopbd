@extends('app.layout', ['tab' => 'empty'])
@section('title', 'খালি সিলিন্ডার সংগ্রহ')
@php($U = \App\Support\Ui::class)
@section('bar')@include('app._back', ['href' => route('app.home'), 'title' => 'খালি সিলিন্ডার সংগ্রহ'])@endsection
@section('content')
<section style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
  @foreach(\App\Models\Brand::SIZES as $s)<div style="background:#def5f7;border-radius:12px;padding:8px 10px;display:flex;flex-direction:column"><span style="font-size:var(--fs-12);color:#51607a">{{ $U::bn($s) }} কেজি পেলাম</span><b class="n" style="font-size:var(--fs-22);color:#0b6670">{{ $collected[$s] ?? 0 }}</b></div>@endforeach
</section>
<div style="display:flex;flex-direction:column;gap:8px"><span class="lb">যেসব দোকানে খালি বাকি</span>
  @forelse($holders as $h)
    @php($on = $sel && $sel->customer_id === $h->customer_id && $sel->size_kg === $h->size_kg)
    <a href="{{ route('app.empty', ['c' => $h->customer_id, 's' => $h->size_kg]) }}" class="card" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;{{ $on ? 'border:2px solid #123a86;background:#e6eeff' : '' }}">
      <div style="flex-grow:1;display:flex;flex-direction:column"><b style="font-size:var(--fs-15)">{{ $h->customer?->name }}</b><span style="font-size:var(--fs-12);color:#51607a">{{ $U::bn($h->size_kg) }} কেজি · {{ \Carbon\Carbon::parse($h->last)->isToday() ? 'আজ থেকে' : \Carbon\Carbon::parse($h->last)->diffForHumans() }}</span></div>
      <b class="n" style="font-size:var(--fs-22);color:#8a4b00">{{ (int) $h->q }}</b></a>
  @empty
    <p style="color:#51607a;margin:0">এই রুটে কোনো দোকানে খালি বাকি নেই।</p>
  @endforelse
</div>
@if($sel && $trip && $trip->status === 'dispatched')
<form method="post" action="{{ route('app.empty.store') }}" id="ef" class="card" style="display:flex;flex-direction:column;gap:10px">@csrf
  <input type="hidden" name="customer_id" value="{{ $sel->customer_id }}"><input type="hidden" name="size" value="{{ $sel->size_kg }}">
  <input type="hidden" name="qty" id="q" value="1"><input type="hidden" name="brand_id" id="b" value="{{ $own?->id }}"><input type="hidden" name="condition" id="cd" value="ভালো">
  <b>{{ $sel->customer?->name }} · {{ $U::bn($sel->size_kg) }} কেজি</b>
  <span class="lb">খালির ব্র্যান্ড</span>
  <div style="display:flex;flex-wrap:wrap;gap:6px"><button type="button" class="chip br on" data-b="{{ $own?->id }}" style="min-height:44px">{{ $own?->name }}</button>
    <select class="txt" id="other" style="flex:1;min-height:44px"><option value="">অন্য ব্র্যান্ড…</option>@foreach($brands->where('id', '!=', $own?->id) as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
  <span style="font-size:var(--fs-12);color:#51607a">অন্য ব্র্যান্ডের খালি আলাদা জমা হবে, অফিসের অনুমোদন ছাড়া {{ $own?->name }}-এর সাথে বদল হবে না।</span>
  <div style="display:flex;align-items:center;gap:10px"><div style="flex-grow:1;display:flex;flex-direction:column"><span class="lb">এখন খালি পেলাম</span><span style="font-size:var(--fs-12);color:#51607a">বাকি থাকবে <span id="left">{{ (int) $sel->q - 1 }}</span>টি</span></div>
    <button class="step" type="button" id="m">−</button><span class="num" id="qn">1</span><button class="step" type="button" id="p">+</button></div>
  <span class="lb">সিলিন্ডারের অবস্থা</span>
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px">@foreach(['ভালো', 'ভাল্ভ সমস্যা', 'নষ্ট'] as $k)<button type="button" class="chip cdb {{ $loop->first ? 'on' : '' }}" data-c="{{ $k }}" style="min-height:44px;font-size:var(--fs-13)">{{ $k }}</button>@endforeach</div>
  <span style="font-size:var(--fs-12);color:#51607a">সময় নিজে থেকে বসবে · {{ now()->timezone('Asia/Dhaka')->format('H:i') }}</span>
  <button class="big" type="submit" style="background:#0b6670">খালি সংরক্ষণ</button>
</form>
@endif
<span style="font-size:var(--fs-12);color:#51607a">নতুন সিলিন্ডার বিক্রিতে খালি লাগে না, তাই সেগুলো এই তালিকায় আসে না।</span>
@endsection
@if($sel)
@push('js')
<script>
(function(){ const max={{ (int) $sel->q }}; let q=1; const $=i=>document.getElementById(i); if(!$('ef')) return;
  const d=()=>{ $('q').value=q; $('qn').textContent=q; $('left').textContent=max-q; };
  $('p').onclick=()=>{q=Math.min(max,q+1);d();}; $('m').onclick=()=>{q=Math.max(1,q-1);d();};
  document.querySelectorAll('.cdb').forEach(b=>b.onclick=()=>{ document.querySelectorAll('.cdb').forEach(x=>x.classList.toggle('on',x===b)); $('cd').value=b.dataset.c; });
  document.querySelector('.br').onclick=()=>{ $('b').value=document.querySelector('.br').dataset.b; $('other').value=''; document.querySelector('.br').classList.add('on'); };
  $('other').onchange=e=>{ if(e.target.value){ $('b').value=e.target.value; document.querySelector('.br').classList.remove('on'); } };
})();
</script>
@endpush
@endif
