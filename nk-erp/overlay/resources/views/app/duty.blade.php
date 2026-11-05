@extends('app.layout', ['tab' => 'home'])
@section('title', 'ডিউটি ও লোকেশন')
@section('bar')@include('app._back', ['href' => route('app.home'), 'title' => 'ডিউটি ও লোকেশন'])@endsection
@section('content')
<div style="border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:6px;background:{{ $d ? '#e3f6ec' : '#f4f7fc' }};color:{{ $d ? '#0f6b3c' : '#14213d' }}">
  <div style="display:flex;align-items:center;gap:8px"><span style="width:12px;height:12px;border-radius:999px;background:{{ $d ? '#1f9d55' : '#7b8aa8' }}"></span><b style="font-size:var(--fs-18)">{{ $d ? 'ডিউটিতে আছেন' : 'ডিউটি শুরু হয়নি' }}</b></div>
  <span style="font-size:var(--fs-13)">{{ $d ? 'শুরু '.$d->started_at->timezone('Asia/Dhaka')->format('H:i').($trip ? ' · গাড়ি '.$trip->vehicle->code.' · রুট '.($trip->route?->name ?? '—') : '') : 'ডিউটি শুরু করলে তবেই লোকেশন পাঠানো শুরু হবে।' }}</span>
</div>
<div class="card" style="display:flex;flex-direction:column;gap:6px;font-size:var(--fs-14)">
  <div style="display:flex"><span style="flex-grow:1;color:#51607a">লোকেশন অনুমতি</span><b id="perm">দেখা হচ্ছে…</b></div>
  <div style="display:flex"><span style="flex-grow:1;color:#51607a">GPS</span><b id="gps" style="color:#51607a">বন্ধ</b></div>
  <div style="display:flex"><span style="flex-grow:1;color:#51607a">ট্র্যাকিং</span><b id="trk">{{ $d ? 'চালু' : 'বন্ধ' }}</b></div>
  <div style="display:flex"><span style="flex-grow:1;color:#51607a">শেষ সিঙ্ক</span><b class="n" id="sync">{{ $last ? $last->recorded_at->timezone('Asia/Dhaka')->format('H:i:s') : '—' }}</b></div>
</div>
<div style="background:#fff1d6;color:#8a4b00;border-radius:12px;padding:12px;font-size:var(--fs-13);line-height:20px">জেনে রাখুন: ডিউটি চলার সময় প্রতি ১৫–৩০ সেকেন্ডে আপনার ফোনের লোকেশন অফিসে যায়, যাতে ম্যানেজার রুট দেখতে পারেন। ডিউটি শেষ করলে বা লোকেশন অনুমতি না দিলে কিছুই পাঠানো হয় না। অ্যাপ খোলা থাকা অবস্থায় লোকেশন যায়।</div>
<span style="font-size:var(--fs-13);color:#51607a">ফোনের Settings → Location থেকে অনুমতি বদলানো যায়।</span>
<form method="post" action="{{ route('app.duty.toggle') }}" id="df">@csrf<input type="hidden" name="consent" id="consent" value="0">
  <button class="big" type="submit" style="{{ $d ? 'background:#fff;color:#b3261e;border:2px solid #b3261e' : 'background:#0f6b3c' }}">{{ $d ? 'ডিউটি শেষ করুন' : 'ডিউটি শুরু করুন' }}</button></form>
@endsection
@push('js')
<script>
(function(){
  const on={{ $d ? 'true' : 'false' }}, url=@json(route('app.gps')), $=i=>document.getElementById(i); let buf=[], wid=null;
  const setPerm=s=>{ $('perm').textContent={granted:'দেওয়া আছে',denied:'দেওয়া হয়নি',prompt:'জিজ্ঞেস করবে'}[s]||s; };
  if(navigator.permissions) navigator.permissions.query({name:'geolocation'}).then(p=>{setPerm(p.state);p.onchange=()=>setPerm(p.state);}).catch(()=>setPerm('অজানা')); else setPerm('অজানা');
  $('df').addEventListener('submit',e=>{ if(on||!navigator.geolocation) return; e.preventDefault();
    navigator.geolocation.getCurrentPosition(()=>{ $('consent').value=1; $('df').submit(); },()=>{ $('consent').value=0; $('df').submit(); },{enableHighAccuracy:true,timeout:15000}); });
  if(!on||!navigator.geolocation) return;
  async function flush(){ if(!buf.length) return; const pts=buf.splice(0,buf.length);
    try{ const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':NK.tok},body:JSON.stringify({points:pts})}); const j=await r.json(); if(j.stop){ navigator.geolocation.clearWatch(wid); $('trk').textContent='বন্ধ'; } else if(j.at) $('sync').textContent=j.at; }
    catch(e){ buf=pts.concat(buf).slice(-200); } }
  wid=navigator.geolocation.watchPosition(p=>{ $('gps').textContent='চালু · ±'+Math.round(p.coords.accuracy)+' মিটার'; $('gps').style.color='#0f6b3c';
      buf.push({lat:p.coords.latitude,lng:p.coords.longitude,acc:p.coords.accuracy,spd:p.coords.speed,t:p.timestamp}); },
    err=>{ $('gps').textContent=err.code===1?'অনুমতি নেই — পাঠানো হচ্ছে না':'সিগন্যাল নেই'; $('trk').textContent='বন্ধ'; },{enableHighAccuracy:true,maximumAge:10000,timeout:30000});
  setInterval(flush,20000);
})();
</script>
@endpush
