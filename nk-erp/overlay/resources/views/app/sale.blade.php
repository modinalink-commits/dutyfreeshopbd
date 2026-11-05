@extends('app.layout', ['tab' => 'sale'])
@section('title', 'নতুন বিক্রি')
@php($U = \App\Support\Ui::class)
@section('bar')@include('app._back', ['href' => route('app.home'), 'title' => 'নতুন বিক্রি', 'right' => 'ড্রাফট <span id="dno">'.$draftNo.'</span>'])@endsection
@section('content')
@if(! $trip || $trip->status !== 'dispatched')
  <div class="card" style="background:#fff1d6;border:0;color:#8a4b00">চালু ট্রিপ ছাড়া বিক্রি করা যাবে না। অফিস থেকে লোড অনুমোদন হলে শুরু করুন।</div>
@elseif(! $prices)
  <div class="card" style="background:#fff1d6;border:0;color:#8a4b00">দামের তালিকা এখনো ঠিক করা নেই। অফিস Products &amp; prices-এ দাম বসালে বিক্রি করা যাবে।</div>
@else
<div class="card" style="display:flex;align-items:center;gap:10px">
  <div style="flex-grow:1;display:flex;flex-direction:column">
    @if($cust)<span style="font-size:16px;font-weight:700">{{ $cust->name }}</span><span style="font:500 12px 'Plus Jakarta Sans',sans-serif;color:#51607a">{{ $cust->phone }} · <span style="font-family:'Hind Siliguri'">আগের বকেয়া</span> {{ $U::tk($due) }} · <span style="font-family:'Hind Siliguri'">খালি বাকি</span> {{ $emptyOut }}</span>
    @else<span style="font-size:16px;font-weight:700">দোকান বাছাই করুন</span><input class="txt" id="walkin" placeholder="অথবা খুচরা ক্রেতার নাম" style="margin-top:6px;min-height:40px">@endif
  </div>
  <a href="{{ route('app.shops', ['pick' => 'sale']) }}" style="min-height:40px;padding:0 12px;border-radius:999px;border:1px solid #7b8aa8;display:flex;align-items:center;font:600 13px 'Hind Siliguri',sans-serif;color:#123a86;text-decoration:none">{{ $cust ? 'বদলান' : 'দোকান' }}</a>
</div>
<div style="display:flex;align-items:center;gap:10px"><span class="lb" style="flex-grow:1">ব্র্যান্ড</span><span style="min-height:44px;padding:0 14px;border-radius:12px;border:1px solid #7b8aa8;display:flex;align-items:center;font:700 14px 'Plus Jakarta Sans',sans-serif">{{ $brand?->name ?? 'Aygaz United' }}</span></div>
<div style="display:flex;flex-direction:column;gap:6px"><span class="lb">সিলিন্ডার সাইজ</span>
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
    @foreach($onVeh as $s => $q)<button type="button" class="chip sz" data-s="{{ $s }}" @disabled(! isset($prices[$s]))><span style="font-size:16px">{{ $U::bn($s) }} কেজি</span><span style="font-size:11px">গাড়িতে {{ $q }}</span></button>@endforeach
  </div></div>
<div style="display:flex;flex-direction:column;gap:6px"><span class="lb">বিক্রির ধরন</span>
  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px"><button type="button" class="chip ty" data-t="ex">রিফিল / এক্সচেঞ্জ</button><button type="button" class="chip ty" data-t="nw">নতুন সিলিন্ডার</button></div></div>
<div style="display:flex;align-items:center;gap:10px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><span class="lb">পরিমাণ</span><span style="font-size:12px;color:#51607a">সর্বোচ্চ <span id="maxQ">0</span>টি</span></div>
  <button class="step" type="button" id="dec" aria-label="একটি কম">−</button><span class="num" id="qty">1</span><button class="step" type="button" id="inc" aria-label="একটি বেশি">+</button>
</div>
<div style="background:#f4f7fc;border-radius:14px;padding:12px;display:flex;flex-direction:column;gap:6px">
  <div style="display:flex;align-items:center;font-size:14px"><span style="flex-grow:1;color:#51607a">একক দাম (অনুমোদিত তালিকা)</span><span class="n" style="font-weight:700;font-size:15px" id="price">—</span></div>
  <div style="display:flex;align-items:center"><span style="flex-grow:1;font-size:16px;font-weight:700">মোট</span><span class="n" style="font-weight:700;font-size:26px;color:#123a86" id="total">—</span></div>
</div>
<div id="exBox" style="display:flex;flex-direction:column;gap:8px">
  <div style="display:flex;align-items:center;gap:10px">
    <div style="flex-grow:1;display:flex;flex-direction:column"><span class="lb">খালি সিলিন্ডার পেলাম</span><span style="font-size:12px" id="eNote"></span></div>
    <button class="step" type="button" id="edec" aria-label="খালি একটি কম">−</button><span class="num" id="empty">1</span><button class="step" type="button" id="einc" aria-label="খালি একটি বেশি">+</button>
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px">@foreach(['ভালো', 'ভাল্ভ সমস্যা', 'নষ্ট'] as $k)<button type="button" class="chip cd" data-c="{{ $k }}" style="min-height:40px;font-size:13px">{{ $k }}</button>@endforeach</div>
</div>
<div style="display:flex;flex-direction:column;gap:6px"><span class="lb">পেমেন্ট</span>
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px"><button type="button" class="chip py" data-p="cash">নগদ</button><button type="button" class="chip py" data-p="mfs">বিকাশ / নগদ</button><button type="button" class="chip py" data-p="bank">ব্যাংক</button></div></div>
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
  <label class="lb" style="display:flex;flex-direction:column;gap:4px">জমা দিলেন<input class="inp" id="paid" inputmode="numeric"></label>
  <div style="display:flex;flex-direction:column;gap:4px"><span class="lb">বাকি</span><div id="dueBox" class="n" style="min-height:52px;border-radius:12px;padding:0 12px;display:flex;align-items:center;font-weight:700;font-size:22px">৳0</div></div>
</div>
<span style="font-size:12px;color:#51607a">বাকি দিতে হলে ম্যানেজারের অনুমতি লাগবে (দোকানের বাকির সীমা অফিস ঠিক করে)।</span>
<div id="msg"></div>
@endif
@endsection
@if($trip && $trip->status === 'dispatched' && $prices)
@section('foot')
<div style="padding:10px 16px;border-top:1px solid #dbe3f0;background:#fff;position:sticky;bottom:76px"><button type="button" class="big" id="save">বিক্রি সংরক্ষণ</button></div>
@endsection
@push('js')
<script>
(function(){
  const P=@json($prices), V=@json($onVeh), cust=@json($cust?->id), cname=@json($cust?->name), url=@json(route('app.sale.store'));
  const st={size:Object.keys(P).find(s=>V[s]>0)||Object.keys(P)[0],type:'ex',qty:1,empty:1,pay:'cash',paid:null,cond:'ভালো'};
  const $=id=>document.getElementById(id), tk=n=>'৳'+Math.round(n).toLocaleString('en-IN');
  function cur(){ return (P[st.size]||{})[st.type]; }
  function draw(){
    const p=cur(), max=V[st.size]||0; st.qty=Math.max(1,Math.min(st.qty,Math.max(1,max))); st.empty=Math.min(st.empty,st.qty);
    document.querySelectorAll('.sz').forEach(b=>b.classList.toggle('on',b.dataset.s==st.size));
    document.querySelectorAll('.ty').forEach(b=>{b.classList.toggle('on',b.dataset.t===st.type); b.disabled=!(P[st.size]||{})[b.dataset.t];});
    document.querySelectorAll('.py').forEach(b=>b.classList.toggle('on',b.dataset.p===st.pay));
    document.querySelectorAll('.cd').forEach(b=>b.classList.toggle('on',b.dataset.c===st.cond));
    $('maxQ').textContent=max; $('qty').textContent=st.qty; $('empty').textContent=st.empty;
    const total=p?p.price*st.qty:0, paid=st.paid===null?total:Math.min(st.paid,total), due=Math.max(0,total-paid);
    $('price').textContent=p?tk(p.price):'দাম নেই'; $('total').textContent=tk(total);
    if(document.activeElement!==$('paid')) $('paid').value=paid;
    $('dueBox').textContent=tk(due); $('dueBox').style.background=due>0?'#fff1d6':'#e3f6ec'; $('dueBox').style.color=due>0?'#8a4b00':'#0f6b3c';
    $('exBox').style.display=st.type==='ex'?'flex':'none';
    const pend=st.qty-st.empty; $('eNote').textContent=pend>0?pend+'টি খালি দোকানে বাকি থাকবে':'সব খালি পাওয়া গেছে'; $('eNote').style.color=pend>0?'#8a4b00':'#0f6b3c';
    $('save').textContent='বিক্রি সংরক্ষণ — '+tk(total); $('save').disabled=!p||max<1;
  }
  document.querySelectorAll('.sz').forEach(b=>b.onclick=()=>{st.size=b.dataset.s;st.qty=1;st.empty=1;st.paid=null;if(!cur())st.type=st.type==='ex'?'nw':'ex';draw();});
  document.querySelectorAll('.ty').forEach(b=>b.onclick=()=>{st.type=b.dataset.t;st.paid=null;draw();});
  document.querySelectorAll('.py').forEach(b=>b.onclick=()=>{st.pay=b.dataset.p;draw();});
  document.querySelectorAll('.cd').forEach(b=>b.onclick=()=>{st.cond=b.dataset.c;draw();});
  $('inc').onclick=()=>{st.qty++;st.empty++;st.paid=null;draw();}; $('dec').onclick=()=>{st.qty=Math.max(1,st.qty-1);st.empty=Math.min(st.empty,st.qty);st.paid=null;draw();};
  $('einc').onclick=()=>{st.empty=Math.min(st.qty,st.empty+1);draw();}; $('edec').onclick=()=>{st.empty=Math.max(0,st.empty-1);draw();};
  $('paid').oninput=e=>{const v=parseInt(String(e.target.value).replace(/[^0-9]/g,''),10);st.paid=isNaN(v)?0:v;draw();};
  $('save').onclick=async()=>{
    const p=cur(); if(!p) return;
    const body={tx:(crypto.randomUUID?crypto.randomUUID():Date.now()+'-'+Math.random()),customer_id:cust,walkin_name:$('walkin')?$('walkin').value:null,size:+st.size,type:st.type,qty:st.qty,
      empty:st.type==='ex'?st.empty:0,pay:st.pay,paid:st.paid===null?p.price*st.qty:Math.min(st.paid,p.price*st.qty),condition:st.cond,draft:$('dno').textContent,at:new Date().toISOString()};
    const item={url,body,shop:cname||body.walkin_name||'খুচরা ক্রেতা',items:st.size+' কেজি × '+st.qty+(st.type==='ex'?' রিফিল':' নতুন সিলিন্ডার'),amt:p.price*st.qty,time:new Date().toTimeString().slice(0,5),draft:body.draft};
    $('save').disabled=true; $('save').textContent='পাঠানো হচ্ছে…';
    try{ const r=await NK.send(item);
      if(r.done){ location.href=r.j.url; return; }
      $('msg').innerHTML='<div class="er">'+((r.j&&r.j.error)||'সার্ভার বিক্রিটি নিল না।')+'</div>'; $('save').disabled=false; draw();
    }catch(e){ const q=NK.get(); item.state='wait'; q.push(item); NK.put(q);
      $('save').style.background='#0f6b3c'; $('save').textContent='সংরক্ষিত ✓ · সার্ভারের নিশ্চয়তার অপেক্ষায়';
      $('msg').innerHTML='<div class="ok" style="background:#fff1d6;color:#8a4b00">ইন্টারনেট নেই। বিক্রিটি ফোনে ড্রাফট '+body.draft+' হিসেবে রাখা হলো। নেট এলে নিজে থেকে পাঠানো হবে, তখন চূড়ান্ত ইনভয়েস নম্বর পাবে।</div>';
      setTimeout(()=>location.href=@json(route('app.sales')),2500); }
  };
  const base=parseInt(@json($draftNo).slice(2),10)||1; $('dno').textContent='D-'+String(base+NK.get().length).padStart(3,'0');
  draw();
})();
</script>
@endpush
@endif
