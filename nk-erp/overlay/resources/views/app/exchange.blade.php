@extends('app.layout', ['tab' => 'home'])
@section('title', 'সিলিন্ডার এক্সচেঞ্জ')
@php($U = \App\Support\Ui::class)
@php($X = \App\Models\CylinderExchange::class)
@php($others = $brands->where('for_sale', false)->values())
@section('bar')@include('app._back', ['href' => route('app.home'), 'title' => 'সিলিন্ডার এক্সচেঞ্জ'])@endsection
@section('content')
<p style="margin:0;font-size:var(--fs-13);color:#51607a">দোকান অন্য ব্র্যান্ডের খালি দেবে, আমরা {{ $own?->name }}-এর ভরা দেব। দাম = রিফিল দাম + এক্সচেঞ্জ চার্জ। অফিস অনুমোদন দিলে তবেই মেমো হবে।</p>
<div id="draftBar" class="card" style="display:none;background:#fff4e5;color:#8a4b00;font-size:var(--fs-13)"></div>
@if(! $trip || $trip->status !== 'dispatched')
  <div class="card" style="background:#fff4e5;color:#8a4b00">চালু ট্রিপ নেই। গাড়ি লোড হয়ে বের হলে এক্সচেঞ্জ করা যাবে।</div>
@elseif(! $others->count() || ! in_array('B', $types, true))
  <div class="card" style="background:#fff4e5;color:#8a4b00">এখন অন্য ব্র্যান্ডের এক্সচেঞ্জ বন্ধ আছে। অফিসে যোগাযোগ করুন।</div>
@else
<form method="post" action="{{ route('app.exchange.store') }}" id="xf" enctype="multipart/form-data" class="card" style="display:flex;flex-direction:column;gap:10px">@csrf
  <input type="hidden" name="tx" id="tx" value="{{ old('tx', (string) \Illuminate\Support\Str::uuid()) }}">
  <input type="hidden" name="qty" id="q" value="{{ old('qty', 1) }}">
  <span class="lb">দোকান</span>
  @if($cust)
    <input type="hidden" name="customer_id" value="{{ $cust->id }}">
    <div style="display:flex;align-items:center;gap:8px"><b style="flex-grow:1;font-size:var(--fs-15)">{{ $cust->name }}</b><a class="chip" href="{{ route('app.shops', ['pick' => 'exchange']) }}" style="min-height:40px;display:flex;align-items:center">বদলান</a></div>
  @else
    <a class="chip" href="{{ route('app.shops', ['pick' => 'exchange']) }}" style="min-height:44px;display:flex;align-items:center;justify-content:center">দোকান বাছাই করুন</a>
    <input class="txt" name="walkin_name" data-d value="{{ old('walkin_name') }}" placeholder="অথবা খুচরা ক্রেতার নাম">
    <input class="txt" name="walkin_phone" data-d type="tel" value="{{ old('walkin_phone') }}" placeholder="মোবাইল নম্বর (ইচ্ছামতো)">
  @endif
  <span class="lb">দোকান কোন ব্র্যান্ডের খালি দিচ্ছে</span>
  <select class="txt" name="given_brand_id" id="gb" data-d style="min-height:48px">
    @foreach($others as $b)<option value="{{ $b->id }}" data-sizes="{{ implode(',', $b->sizes ?? []) }}" @selected(old('given_brand_id') == $b->id)>{{ $b->name }}</option>@endforeach
  </select>
  <span class="lb">সাইজ</span>
  <input type="hidden" name="size_kg" id="sz" data-d value="{{ old('size_kg', 12) }}">
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px">@foreach(\App\Models\Brand::SIZES as $s)<button type="button" class="chip szb" data-s="{{ $s }}" style="min-height:44px">{{ $U::bn($s) }} কেজি</button>@endforeach</div>
  <div style="display:flex;align-items:center;gap:10px"><span class="lb" style="flex-grow:1">কয়টি</span>
    <button class="step" type="button" id="m" aria-label="কম">−</button><span class="num" id="qn">1</span><button class="step" type="button" id="p" aria-label="বেশি">+</button></div>
  <span class="lb">খালির অবস্থা</span>
  <input type="hidden" name="condition" id="cd" data-d value="{{ old('condition', 'good') }}">
  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px">
    @foreach(['good' => 'ভালো', 'valve' => 'ভাল্ভ সমস্যা', 'damaged' => 'নষ্ট', 'unknown' => 'দেখা হয়নি'] as $k => $lbl)<button type="button" class="chip cdb" data-c="{{ $k }}" style="min-height:44px;font-size:var(--fs-13)">{{ $lbl }}</button>@endforeach
  </div>
  <span class="lb">খালিটা কার</span>
  <select class="txt" name="ownership" data-d style="min-height:48px">
    @foreach(['customer' => 'দোকানের নিজের', 'brand' => 'ব্র্যান্ড কোম্পানির (সেটেল করতে হবে)', 'company' => 'আমাদের সিলিন্ডার ফেরত', 'unknown' => 'জানি না'] as $k => $lbl)<option value="{{ $k }}" @selected(old('ownership', 'customer') === $k)>{{ $lbl }}</option>@endforeach
  </select>
  <div id="qt" style="background:#f5f8ff;border-radius:12px;padding:10px 12px;display:grid;grid-template-columns:1fr auto;gap:4px 10px;font-size:var(--fs-13)" aria-live="polite"></div>
  <span class="lb">টাকা</span>
  <input type="hidden" name="method" id="md" data-d value="{{ old('method', 'cash') }}">
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px">
    @foreach(['cash' => 'নগদ', 'bkash' => 'বিকাশ', 'due' => 'বাকি'] as $k => $lbl)<button type="button" class="chip mdb" data-m="{{ $k }}" style="min-height:44px;{{ $k === 'due' && ! $cust ? 'opacity:.45' : '' }}" @if($k === 'due' && ! $cust) disabled title="বাকির জন্য দোকান বাছাই করুন" @endif>{{ $lbl }}</button>@endforeach
  </div>
  <label id="pw" style="display:none;flex-direction:column;gap:4px"><span class="lb">এখন কত পেলেন (৳)</span><input class="txt" type="number" name="paid" data-d min="0" step="1" value="{{ old('paid', 0) }}"></label>
  <span class="lb">রসিদ / সিলিন্ডারের ছবি (ইচ্ছামতো)</span>
  <input class="txt" type="file" name="photo" accept="image/*" capture="environment" style="padding:10px">
  <span class="lb">ক্রেতার স্বীকৃতি</span>
  <input class="txt" name="ack_name" data-d value="{{ old('ack_name', $cust?->name) }}" placeholder="যিনি খালি দিলেন তাঁর নাম" required>
  <label style="display:flex;gap:8px;align-items:center;font-size:var(--fs-13)"><input type="checkbox" id="ackOk" required style="width:22px;height:22px"> ক্রেতা ব্র্যান্ড, সংখ্যা ও দাম দেখে রাজি হয়েছেন</label>
  <input class="txt" name="note" data-d value="{{ old('note') }}" placeholder="নোট (ইচ্ছামতো)">
  <button class="big" type="submit" id="go" style="background:#5b2fa8">অনুমোদনের জন্য পাঠান</button>
  <span style="font-size:var(--fs-12);color:#51607a">অনুমোদন না হওয়া পর্যন্ত মেমো নম্বর হবে না। ভরা সিলিন্ডার দেওয়ার আগে অফিসের অনুমোদন দেখে নিন। নষ্ট, অজানা বা চুক্তি ছাড়া ব্র্যান্ডের খালি রিভিউতে আটকে থাকবে।</span>
</form>
@endif
<div style="display:flex;flex-direction:column;gap:8px"><span class="lb">এই ট্রিপের এক্সচেঞ্জ · সিঙ্কের অবস্থা</span>
  @forelse($mine as $x)
    <div class="card" style="display:flex;align-items:center;gap:10px">
      <div style="flex-grow:1;min-width:0;display:flex;flex-direction:column"><b>{{ $x->ref_no }} · {{ $x->buyer() }}</b>
        @foreach($x->lines as $l)<span style="font-size:var(--fs-12);color:#51607a">{{ $l->in_qty }} × {{ $l->inBrand?->name }} {{ $U::bn($l->in_size) }} কেজি → {{ $l->outBrand?->name }}</span>@endforeach
        <span style="font-size:var(--fs-12);color:#51607a">৳{{ $U::bd($x->total) }} · সার্ভারে সংরক্ষিত</span>
        @if($x->decision_note)<span style="font-size:var(--fs-12);color:#b3261e">{{ $x->decision_note }}</span>@endif
        @if($x->status === 'held' && $x->hold_reason)<span style="font-size:var(--fs-12);color:#8a4b00">রিভিউ: {{ $x->hold_reason }}</span>@endif</div>
      @if($x->sale)<a class="chip" href="{{ route('app.invoice', $x->sale) }}" style="min-height:40px;display:flex;align-items:center;white-space:nowrap;flex-shrink:0;background:#e3f6ec;color:#0f6b3c">অনুমোদিত · মেমো</a>
      @else<span class="chip" style="min-height:40px;display:flex;align-items:center;white-space:nowrap;flex-shrink:0;{{ in_array($x->status, ['rejected', 'reversed'], true) ? 'background:#fde8e6;color:#b3261e' : ($x->status === 'held' ? 'background:#fff4e5;color:#8a4b00' : 'background:#efe7fb;color:#5b2fa8') }}">{{ $X::STATUS_BN[$x->status] }}</span>@endif
    </div>
  @empty
    <p style="color:#51607a;margin:0">এখনও কোনো এক্সচেঞ্জ হয়নি।</p>
  @endforelse
  @if($mine->whereIn('status', ['pending', 'held'])->count())<a class="chip" href="{{ route('app.exchange') }}" style="min-height:44px;display:flex;align-items:center;justify-content:center">অবস্থা আবার দেখুন</a>@endif
</div>
@endsection
@push('js')
<script>
(function(){ const $=i=>document.getElementById(i), KEY='nk-exchange-draft', bar=$('draftBar');
  const f=$('xf');
  function showBar(t){ bar.style.display=t?'block':'none'; bar.textContent=t||''; }
  let saved=null; try{ @if(session('ok'))localStorage.removeItem(KEY);@endif saved=JSON.parse(localStorage.getItem(KEY)||'null'); }catch(e){}
  if(!f){ if(saved) showBar('একটি খসড়া এক্সচেঞ্জ ফোনে রাখা আছে — চালু ট্রিপ হলে পাঠাতে পারবেন।'); return; }
  const Q=@json($quotes); let q=Math.max(1,parseInt($('q').value,10)||1);
  @if(! $errors->any() && ! old('tx'))
  if(saved&&saved.v){ Object.keys(saved.v).forEach(k=>{ const e=f.querySelector('[name="'+k+'"]'); if(e&&e.type!=='file') e.value=saved.v[k]; }); if(saved.v.qty) q=+saved.v.qty;
    showBar('আগের খসড়া ফিরিয়ে আনা হয়েছে ('+saved.at+')। এখনও পাঠানো হয়নি।'); }
  @endif
  const tk=n=>'৳'+Number(n).toLocaleString('en-IN',{maximumFractionDigits:2});
  function save(){ const v={}; f.querySelectorAll('[data-d]').forEach(e=>v[e.name]=e.value); v.qty=q; v.tx=$('tx').value;
    try{ localStorage.setItem(KEY,JSON.stringify({v:v,at:new Date().toLocaleTimeString('bn-BD',{hour:'2-digit',minute:'2-digit'})})); }catch(e){} }
  if(saved&&saved.v&&saved.v.tx) $('tx').value=saved.v.tx;
  function sizes(){ const ok=($('gb').selectedOptions[0].dataset.sizes||'').split(','); let first=null;
    document.querySelectorAll('.szb').forEach(b=>{ const on=ok.indexOf(b.dataset.s)>=0&&!!(Q[$('gb').value]||{})[b.dataset.s]; b.disabled=!on; b.style.opacity=on?'':'.45'; if(on&&!first)first=b.dataset.s; });
    if(!(Q[$('gb').value]||{})[$('sz').value]&&first)$('sz').value=first; }
  function draw(){ sizes(); document.querySelectorAll('.szb').forEach(b=>b.classList.toggle('on',b.dataset.s===$('sz').value));
    document.querySelectorAll('.mdb').forEach(b=>b.classList.toggle('on',b.dataset.m===$('md').value)); $('pw').style.display=$('md').value==='due'?'flex':'none';
    document.querySelectorAll('.cdb').forEach(b=>b.classList.toggle('on',b.dataset.c===$('cd').value));
    $('q').value=q; $('qn').textContent=q;
    const p=(Q[$('gb').value]||{})[$('sz').value];
    if(!p){ $('qt').innerHTML='<span style="grid-column:1/-1;color:#b3261e">এই সাইজের দাম ঠিক করা নেই। অফিসকে দাম বসাতে বলুন।</span>'; $('go').disabled=true; return; }
    $('go').disabled=false;
    let h='<span>রিফিল '+tk(p.unit)+' × '+q+'</span><b style="text-align:right">'+tk(p.unit*q)+'</b><span>এক্সচেঞ্জ চার্জ '+tk(p.charge)+' × '+q+'</span><b style="text-align:right">'+tk(p.charge*q)+'</b><span style="font-weight:700">মোট</span><b style="text-align:right;font-size:var(--fs-16)">'+tk((p.unit+p.charge)*q)+'</b>';
    const hold=p.hold.length||$('cd').value!=='good'||f.ownership.value==='unknown';
    if(hold) h+='<span style="grid-column:1/-1;color:#8a4b00">এটা রিভিউতে আটকে থাকবে — অফিস না বলা পর্যন্ত ভরা সিলিন্ডার দেবেন না।</span>';
    $('qt').innerHTML=h; save(); }
  document.querySelectorAll('.szb').forEach(b=>b.onclick=()=>{ $('sz').value=b.dataset.s; draw(); });
  document.querySelectorAll('.mdb').forEach(b=>b.onclick=()=>{ if(!b.disabled){ $('md').value=b.dataset.m; draw(); } });
  document.querySelectorAll('.cdb').forEach(b=>b.onclick=()=>{ $('cd').value=b.dataset.c; draw(); });
  f.addEventListener('input',draw); f.addEventListener('change',draw);
  $('p').onclick=()=>{q=Math.min(100,q+1);draw();}; $('m').onclick=()=>{q=Math.max(1,q-1);draw();};
  f.addEventListener('submit',e=>{ save();
    if(!navigator.onLine){ e.preventDefault(); showBar('নেট নেই — খসড়া ফোনে রাখা হলো। নেট এলে আবার “পাঠান” চাপুন; সার্ভার আবার সব যাচাই করবে।'); return; }
    $('go').disabled=true; $('go').textContent='পাঠানো হচ্ছে…'; });
  draw();
})();
</script>
@endpush
