@extends('portal.layout', ['noNav' => true])
@section('title', 'লগইন')
@section('content')
<div style="display:flex;justify-content:flex-end;gap:6px"><span class="pill" style="background:#123a86;color:#fff;padding:6px 12px">বাংলা</span><span class="pill" style="border:1px solid #7b8aa8;padding:6px 12px">English</span></div>
<div style="display:flex;flex-direction:column;align-items:center;gap:8px;margin-top:24px">
  <img src="{{ \App\Models\Setting::logo() }}" alt="Company logo" style="width:84px;height:84px;border-radius:20px">
  <b style="font-size:var(--fs-22)">{{ \App\Models\Setting::get('company_name', 'NK Corporation') }}</b><span style="color:#51607a">আমার হিসাব · {{ \App\Models\Setting::get('brand', 'Aygaz United') }} LPG</span>
</div>
@unless($sms)<div class="er" style="background:#fff1d6;color:#8a4b00">এখনো SMS চালু হয়নি, তাই কোড যাবে না। অফিস SMS চালু করলে লগইন করা যাবে।</div>@endunless
@if(! $phone)
<form method="post" action="{{ route('my.send') }}" style="display:flex;flex-direction:column;gap:12px">@csrf
  <label class="lb" style="display:flex;flex-direction:column;gap:6px">মোবাইল নম্বর<input class="inp" name="phone" inputmode="numeric" autocomplete="tel" required placeholder="01XXXXXXXXX"></label>
  <button class="big" type="submit" style="background:#123a86">কোড পাঠান</button>
</form>
@else
<div class="card" style="display:flex;align-items:center"><span class="lb" style="flex-grow:1">মোবাইল নম্বর<br><b class="n" style="color:#14213d;font-size:var(--fs-18)">{{ $phone }}</b></span>
  <form method="post" action="{{ route('my.logout') }}">@csrf<button type="submit" style="border:0;background:none;color:#1d4ed8;font:600 14px 'Hind Siliguri';cursor:pointer">বদলান</button></form></div>
<form method="post" action="{{ route('my.verify') }}" id="vf" style="display:flex;flex-direction:column;gap:12px">@csrf
  <span class="lb">SMS-এ পাওয়া ৬ সংখ্যার কোড</span>
  <div style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:6px">@for($i = 0; $i < 6; $i++)<input class="inp otp" name="code[]" inputmode="numeric" maxlength="1" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" style="text-align:center;padding:0" aria-label="সংখ্যা {{ $i + 1 }}">@endfor</div>
  <span style="font-size:var(--fs-13);color:#51607a" id="rs">কোড আবার পাঠানো যাবে <span class="n" id="cd">01:00</span> পরে</span>
  <button class="big" type="submit" style="background:#123a86">যাচাই করে ঢুকুন</button>
</form>
<form method="post" action="{{ route('my.send') }}" id="rf" style="display:none">@csrf<input type="hidden" name="phone" value="{{ $phone }}"><button type="submit" class="chip" style="width:100%">কোড আবার পাঠান</button></form>
@endif
<p style="font-size:var(--fs-13);color:#51607a;text-align:center">শুধু আপনার নিজের দোকানের হিসাব দেখা যাবে। আপনার নম্বর আমাদের গ্রাহক তালিকায় না থাকলে অফিসে যোগাযোগ করুন: {{ \App\Models\Setting::get('phone') }}</p>
@endsection
@push('js')
<script>
(function(){ const o=[...document.querySelectorAll('.otp')]; if(!o.length) return;
  o.forEach((x,i)=>{ x.oninput=()=>{ const v=x.value.replace(/\D/g,''); if(v.length>1){ v.split('').slice(0,6-i).forEach((d,k)=>o[i+k].value=d); o[Math.min(5,i+v.length-1)].focus(); } else { x.value=v; if(v&&o[i+1]) o[i+1].focus(); } if(o.every(y=>y.value)) document.getElementById('vf').submit(); };
    x.onkeydown=e=>{ if(e.key==='Backspace'&&!x.value&&o[i-1]) o[i-1].focus(); }; });
  o[0].focus(); let t=60; const cd=document.getElementById('cd'); const iv=setInterval(()=>{ t--; cd.textContent='00:'+String(Math.max(0,t)).padStart(2,'0'); if(t<=0){ clearInterval(iv); document.getElementById('rs').style.display='none'; document.getElementById('rf').style.display='block'; } },1000);
})();
</script>
@endpush
