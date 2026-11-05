@extends('app.layout', ['tab' => 'home'])
@section('title', 'নোটিফিকেশন')
@section('bar')@include('app._back', ['href' => route('app.home'), 'title' => 'নোটিফিকেশন'])@endsection
@section('content')
<button class="chip" type="button" id="readAll" style="min-height:40px;align-self:flex-end;padding:0 14px">সব পড়া হয়েছে</button>
<div id="local"></div>
@forelse($items as $n)
  <div class="card nt" data-at="{{ optional($n['at'])->getTimestamp() }}" style="display:flex;gap:10px"><span style="width:10px;height:10px;border-radius:999px;margin-top:7px;flex-shrink:0;background:{{ ['neg' => '#d93a2f', 'warn' => '#c46f00', 'pos' => '#1f9d55', 'blue' => '#1d4ed8'][$n['c']] }}"></span>
    <div style="flex-grow:1;display:flex;flex-direction:column"><b style="font-size:15px">{{ $n['t'] }}</b><span style="font-size:13px;color:#51607a">{{ $n['b'] }}</span></div><span class="n" style="font-size:12px;color:#51607a">{{ $n['at'] ? $n['at']->timezone('Asia/Dhaka')->format('H:i') : '' }}</span></div>
@empty
  <p style="color:#51607a;margin:0">নতুন কিছু নেই।</p>
@endforelse
@endsection
@push('js')
<script>
(function(){
  const esc=s=>String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  document.getElementById('local').innerHTML=NK.get().filter(x=>x.state==='fail').map(x=>'<div class="card" style="display:flex;gap:10px;margin-bottom:8px"><span style="width:10px;height:10px;border-radius:999px;margin-top:7px;background:#d93a2f"></span><div style="display:flex;flex-direction:column"><b>সিঙ্ক ব্যর্থ — '+esc(x.draft)+'</b><span style="font-size:13px;color:#51607a">'+esc(x.shop)+' · '+esc(x.error)+'</span></div></div>').join('');
  let seen=0; try{seen=+localStorage.getItem('nk_seen')||0}catch(e){}
  document.querySelectorAll('.nt').forEach(n=>{ if(+n.dataset.at<=seen) n.style.opacity=.6; });
  document.getElementById('readAll').onclick=()=>{ try{localStorage.setItem('nk_seen',Math.floor(Date.now()/1000))}catch(e){} document.querySelectorAll('.nt').forEach(n=>n.style.opacity=.6); };
})();
</script>
@endpush
