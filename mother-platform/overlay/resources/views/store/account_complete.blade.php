@extends('store.layout')
@section('main')
<style>.au{padding:26px 12px 50px;display:flex;justify-content:center}.auc{width:420px;max-width:100%;background:#fff;border:1.25px solid var(--bd);border-radius:18px;padding:22px;box-shadow:var(--sh)}.auc h1{font-size:19px;margin:0 0 4px;color:var(--n)}.auc p{margin:0 0 12px;color:var(--mut);font-size:12.5px}.auc label{display:block;font-size:12px;font-weight:700;margin:11px 0 5px;color:var(--n)}.auc input{width:100%;height:44px;border:1.25px solid #E5E7EB;border-radius:11px;padding:0 13px;font-size:14px;box-sizing:border-box}.go{width:100%;height:46px;border:0;border-radius:11px;background:var(--o);color:#fff;font-weight:800;font-size:15px;cursor:pointer;margin-top:12px}.er{color:#B91C1C;font-size:12.5px;margin-top:9px}</style>
<div class="au"><div class="auc">
@if(!empty($reset))
<h1>নতুন পাসওয়ার্ড</h1><p>নতুন পাসওয়ার্ড দিন (কমপক্ষে ৬ অক্ষর)।</p>
<label>Password</label><input type="password" id="a" minlength="6"><label>Confirm Password</label><input type="password" id="b" minlength="6"><button class="go" id="g">পাসওয়ার্ড সেট করুন</button><div class="er" id="e"></div>
@else
<h1>অ্যাকাউন্ট সম্পূর্ণ করুন</h1><p>{{ $name }}, আপনার অ্যাকাউন্ট সম্পূর্ণ করতে একটি Email Address দিন।</p>
<label>Email Address</label><input type="email" id="a" placeholder="your@email.com"><button class="go" id="g">চালিয়ে যান</button><div class="er" id="e"></div>
@endif
</div></div>
@endsection
@section('js')
<script>(function(){var $=function(i){return document.getElementById(i)},reset=@json($reset ?? null),t=@json($t);
$('g').onclick=function(){var b={_token:'{{ csrf_token() }}'},u;if(reset){if($('a').value!==$('b').value){$('e').textContent='দুটি পাসওয়ার্ড মেলেনি';return}u='/account/reset-email/'+reset;b.password=$('a').value;b.password_confirmation=$('b').value}else{u='/account/complete';b.t=t;b.email=$('a').value}
fetch(u,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify(b)}).then(function(r){return r.json().catch(function(){return{}})}).then(function(j){if(j.ok)location.href=j.go;else $('e').textContent=j.error||(j.errors&&Object.values(j.errors)[0][0])||'কিছু ভুল হয়েছে'})}})();</script>
@endsection
