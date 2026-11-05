@extends('app.layout', ['tab' => 'home'])
@section('title', 'আজকের বিক্রি')
@php($U = \App\Support\Ui::class)
@section('bar')@include('app._back', ['href' => route('app.home'), 'title' => 'আজকের বিক্রি'])@endsection
@section('content')
<div id="qbox" class="card" style="background:#fff1d6;border:0;display:none;align-items:center;gap:10px"><div style="flex-grow:1;display:flex;flex-direction:column"><b style="color:#8a4b00" id="qtxt"></b><span style="font-size:12px;color:#8a4b00" id="qsub"></span></div><button class="chip" type="button" id="syncNow" style="min-height:40px">এখন সিঙ্ক</button></div>
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px">
  <div style="background:#e3f6ec;border-radius:12px;padding:10px"><span style="font-size:12px;color:#51607a">নিশ্চিত বিক্রি · {{ $sales->count() }}টি</span><br><b class="n" style="font-size:18px;color:#0f6b3c">{{ $U::tk($sales->sum('total')) }}</b></div>
  <div style="background:#fff1d6;border-radius:12px;padding:10px"><span style="font-size:12px;color:#51607a">অপেক্ষায় · <span id="wn">0</span>টি</span><br><b class="n" style="font-size:18px;color:#8a4b00" id="wamt">৳0</b></div>
</div>
<span style="font-size:12px;color:#51607a">অপেক্ষার বিক্রিগুলো সার্ভার নিশ্চিত না করা পর্যন্ত চূড়ান্ত ইনভয়েস নম্বর পায় না।</span>
<div id="qlist" style="display:flex;flex-direction:column;gap:8px"></div>
@forelse($sales as $s)
  <a href="{{ route('app.invoice', $s) }}" class="card" style="display:flex;gap:10px;align-items:center;text-decoration:none;color:inherit">
    <div style="flex-grow:1;display:flex;flex-direction:column"><b style="font-size:15px">{{ $s->buyerName() }}</b><span class="n" style="font-size:12px;color:#51607a">{{ $s->invoice_no }} · {{ $s->created_at->timezone('Asia/Dhaka')->format('H:i') }}</span>
      <span style="font-size:13px">{{ $s->items->map(fn ($i) => $U::bn($i->size_kg).' কেজি × '.$i->qty.' '.($i->category === 'refill' ? 'রিফিল' : 'নতুন সিলিন্ডার'))->implode(' · ') }}</span></div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><b class="n">{{ $U::tk($s->total) }}</b><span class="pill" style="background:#e3f6ec;color:#0f6b3c">নিশ্চিত ✓</span></div>
  </a>
@empty
  <p style="color:#51607a;margin:0">এই ট্রিপে এখনো বিক্রি নেই।</p>
@endforelse
@endsection
@push('js')
<script>
(function(){
  const tk=n=>'৳'+Math.round(n).toLocaleString('en-IN'), esc=s=>String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  function draw(){
    const q=NK.get(), fail=q.filter(x=>x.state==='fail').length, wait=q.length-fail, last=localStorage.getItem('nk_last_sync')||'—';
    document.getElementById('qbox').style.display=q.length?'flex':'none';
    document.getElementById('qtxt').textContent=wait+'টি সিঙ্ক বাকি'+(fail?' · '+fail+'টি ব্যর্থ':'');
    document.getElementById('qsub').textContent=(navigator.onLine?'':'ইন্টারনেট নেই · ')+'শেষ সিঙ্ক '+last;
    document.getElementById('wn').textContent=q.length; document.getElementById('wamt').textContent=tk(q.reduce((a,x)=>a+(x.amt||0),0));
    document.getElementById('qlist').innerHTML=q.map(x=>'<div class="card" style="display:flex;gap:10px;align-items:center"><div style="flex-grow:1;display:flex;flex-direction:column"><b style="font-size:15px">'+esc(x.shop)+'</b><span class="n" style="font-size:12px;color:#51607a">'+esc(x.draft)+' · '+esc(x.time)+'</span><span style="font-size:13px">'+esc(x.items)+'</span>'+(x.error?'<span style="font-size:12px;color:#b3261e">'+esc(x.error)+'</span>':'')+'</div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px"><b class="n">'+tk(x.amt)+'</b><span class="pill" style="'+(x.state==='fail'?'background:#fde9e7;color:#b3261e">সিঙ্ক ব্যর্থ':'background:#fff1d6;color:#8a4b00">সিঙ্কের অপেক্ষায়')+'</span></div></div>').join('');
  }
  document.getElementById('syncNow').onclick=async()=>{ await NK.sync(); location.reload(); };
  document.addEventListener('nk-synced',()=>location.reload()); draw(); if(navigator.onLine&&NK.get().length) NK.sync();
})();
</script>
@endpush
