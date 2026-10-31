@extends('store.layout')
@section('main')
<style>
.au{padding:26px 12px 50px;display:flex;justify-content:center}.auc{width:420px;max-width:100%;background:#fff;border:1.25px solid var(--bd);border-radius:18px;padding:22px 22px 20px;box-shadow:var(--sh)}
.auc h1{font-size:19px;margin:0 0 3px;color:var(--n)}.auc p.s{margin:0 0 14px;color:var(--mut);font-size:12.5px}
.aut{display:grid;grid-template-columns:1fr 1fr;background:#F3F4F6;border-radius:12px;padding:4px;margin-bottom:16px}.aut button{border:0;background:none;padding:10px;border-radius:9px;font-weight:700;font-size:14px;cursor:pointer;color:#4B5563}.aut button.on{background:var(--o);color:#fff}
.auc label{display:block;font-size:12px;font-weight:700;margin:11px 0 5px;color:var(--n)}.auc input[type=text],.auc input[type=password],.auc input[type=tel],.auc input[type=email]{width:100%;height:44px;border:1.25px solid #E5E7EB;border-radius:11px;padding:0 13px;font-size:14px;box-sizing:border-box;background:#fff}.auc input:focus{outline:0;border-color:var(--o)}
.pw{position:relative}.pw button{position:absolute;right:6px;top:5px;border:0;background:none;font-size:18px;cursor:pointer;height:34px;width:36px}
.row{display:flex;justify-content:space-between;align-items:center;font-size:12.5px;margin:12px 0}.row a,.lk{color:var(--o);font-weight:600;cursor:pointer;text-decoration:none;background:none;border:0;padding:0;font-size:12.5px}
.go{width:100%;height:46px;border:0;border-radius:11px;background:var(--o);color:#fff;font-weight:800;font-size:15px;cursor:pointer;margin-top:6px}.go:disabled{opacity:.6}
.er{color:#B91C1C;font-size:12.5px;margin-top:9px;min-height:16px}.ok2{color:#15803D;font-size:12.5px}
.sep{text-align:center;color:#9CA3AF;font-size:12px;margin:14px 0 4px}.alt{width:100%;height:42px;border:1.25px solid #E5E7EB;background:#fff;border-radius:11px;font-weight:600;font-size:13.5px;cursor:pointer;margin-top:8px;color:var(--n)}
.sm{display:flex;gap:8px}.sm input{flex:1}.sm button{height:44px;border:1.25px solid var(--o);color:var(--o);background:#fff;border-radius:11px;padding:0 12px;font-weight:700;cursor:pointer;white-space:nowrap}
.pn{display:none}.pn.on{display:block}
.gl{text-align:center;font-size:12.5px;color:var(--mut);margin-top:14px}.gl a{color:var(--o);font-weight:700}
</style>
<div class="au"><div class="auc">
<h1 id="ttl">My Account</h1><p class="s">অর্ডার ট্র্যাক, ইনভয়েস, ঠিকানা ও সাপোর্ট — এক জায়গায়।</p>
<div class="aut"><button type="button" id="tl" class="{{ $mode==='login'?'on':'' }}">Login</button><button type="button" id="tr" class="{{ $mode==='register'?'on':'' }}">Register</button></div>

<form class="pn" id="p-login" autocomplete="on">
<label>Mobile Number / Email</label><input type="text" id="l-id" inputmode="email" placeholder="01XXXXXXXXX অথবা email" autocomplete="username" required>
<label>Password</label><div class="pw"><input type="password" id="l-pw" placeholder="Enter your password" autocomplete="current-password" required><button type="button" data-eye="l-pw" aria-label="Show password">👁</button></div>
<div class="row"><label style="margin:0;font-weight:500"><input type="checkbox" id="l-rm" checked style="width:auto;height:auto"> Remember me</label><button type="button" class="lk" id="to-forgot">Forgot Password?</button></div>
<button class="go" id="l-go">Login</button>
@if($smsOn)<button type="button" class="alt" id="to-otp">📱 মোবাইলে OTP দিয়ে লগইন</button>@endif
<div class="er" id="l-er"></div></form>

<form class="pn" id="p-otp"><label>Mobile Number</label><div class="sm"><input type="tel" id="o-ph" placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="14"><button type="button" id="o-send">কোড পাঠান</button></div>
<label>৬ ডিজিটের কোড</label><input type="text" id="o-code" inputmode="numeric" maxlength="6" placeholder="------">
<button class="go" id="o-go">Login</button><div class="row"><button type="button" class="lk" id="o-back">← পাসওয়ার্ড দিয়ে লগইন</button></div><div class="er" id="o-er"></div></form>

<form class="pn" id="p-reg" autocomplete="on">
<label>Full Name</label><input type="text" id="r-nm" maxlength="80" autocomplete="name" required>
<label>Mobile Number</label>@if($smsOn)<div class="sm"><input type="tel" id="r-ph" placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="14" autocomplete="tel" required><button type="button" id="r-send">কোড পাঠান</button></div><label>মোবাইলে পাঠানো কোড</label><input type="text" id="r-code" inputmode="numeric" maxlength="6" placeholder="------">@else<input type="tel" id="r-ph" placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="14" autocomplete="tel" required>@endif
<label>Password</label><div class="pw"><input type="password" id="r-pw" minlength="6" autocomplete="new-password" required><button type="button" data-eye="r-pw" aria-label="Show password">👁</button></div>
<label>Confirm Password</label><input type="password" id="r-pw2" minlength="6" autocomplete="new-password" required>
<label>Email (ঐচ্ছিক)</label><input type="email" id="r-em" autocomplete="email"><label>Referral Code (ঐচ্ছিক)</label><input type="text" id="r-rf" maxlength="40">
<button class="go" id="r-go">Create Account</button><div class="er" id="r-er"></div></form>

<form class="pn" id="p-forgot"><p class="s" style="margin-top:0">রেজিস্টার্ড মোবাইল নম্বরে কোড পাঠানো হবে।</p>
<label>Mobile Number</label><div class="sm"><input type="tel" id="f-ph" placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="14"><button type="button" id="f-send">কোড পাঠান</button></div>
<label>৬ ডিজিটের কোড</label><input type="text" id="f-code" inputmode="numeric" maxlength="6" placeholder="------"><label>নতুন পাসওয়ার্ড</label><input type="password" id="f-pw" minlength="6" autocomplete="new-password"><label>নতুন পাসওয়ার্ড (আবার)</label><input type="password" id="f-pw2" minlength="6" autocomplete="new-password">
<button class="go" id="f-go">পাসওয়ার্ড বদলান</button><div class="row"><button type="button" class="lk" id="f-back">← লগইনে ফিরুন</button></div><div class="er" id="f-er"></div></form>

<div class="gl">অর্ডার করতে অ্যাকাউন্ট লাগবে না — <a href="/checkout">গেস্ট হিসেবেও অর্ডার করুন</a><br>আগের অর্ডার ট্র্যাক: <a href="/track">Track Order</a></div>
@if(!$smsOn)<div class="gl" style="font-size:11.5px">OTP কোড সাময়িকভাবে বন্ধ আছে।</div>@endif
</div></div>
@endsection
@section('js')
<script>window.__m=@json($mode==='register'?'reg':'login');</script>
@verbatim
<script>(function(){
var $=function(i){return document.getElementById(i)},P=['login','otp','reg','forgot'],next=(new URLSearchParams(location.search)).get('next')||'';
function show(n){P.forEach(function(p){$('p-'+p).classList.toggle('on',p===n)});$('tl').classList.toggle('on',n==='login'||n==='otp'||n==='forgot');$('tr').classList.toggle('on',n==='reg');$('ttl').textContent=n==='reg'?'Create Account':(n==='forgot'?'Reset Password':'My Account')}
function post(u,b){return fetch(u,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(b)}).then(function(r){return r.json().catch(function(){return{}}).then(function(j){j._s=r.status;return j})})}
function err(e,j){var m=j.error||(j.errors&&Object.values(j.errors)[0][0])||j.message||'কিছু ভুল হয়েছে';if(j._s===422&&j.errors){m=Object.values(j.errors)[0][0];if(/confirmation/.test(m))m='দুটি পাসওয়ার্ড মেলেনি';else if(/at least/.test(m))m='পাসওয়ার্ড কমপক্ষে ৬ অক্ষর দিন'}$(e).textContent=m}
function done(j){if(j.ok){try{fetch('/account/me',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(m){if(m.in){localStorage.setItem('df_cust',JSON.stringify({p:m.p,n:m.n,e:m.e,a:m.a,d:m.d}));var w=JSON.parse(localStorage.getItem('dfw')||'[]');return post('/account/wishlist/sync',{ids:w})}}).catch(function(){}).then(function(){location.href=(next&&next.charAt(0)==='/'&&next.charAt(1)!=='/')?next:j.go})}catch(e){location.href=j.go}}}
$('tl').onclick=function(){show('login')};$('tr').onclick=function(){show('reg')};$('to-forgot').onclick=function(){show('forgot')};$('f-back').onclick=function(){show('login')};
if($('to-otp')){$('to-otp').onclick=function(){show('otp')};$('o-back').onclick=function(){show('login')}}
document.querySelectorAll('[data-eye]').forEach(function(b){b.onclick=function(){var i=$(b.getAttribute('data-eye'));i.type=i.type==='password'?'text':'password'}});
function cool(btn){btn.disabled=true;var s=60,t=setInterval(function(){s--;btn.textContent=s>0?s+' সে.':'আবার পাঠান';if(s<=0){clearInterval(t);btn.disabled=false}},1000)}
function sendCode(btn,ph,purpose,er){post('/account/otp',{phone:$(ph).value,purpose:purpose}).then(function(j){if(j.sent){$(er).className='er ok2';$(er).textContent='কোড পাঠানো হয়েছে (৫ মিনিট বৈধ)';cool(btn)}else{$(er).className='er';err(er,j)}})}
if($('o-send'))$('o-send').onclick=function(){sendCode(this,'o-ph','login','o-er')};
if($('r-send'))$('r-send').onclick=function(){sendCode(this,'r-ph','register','r-er')};
$('f-send').onclick=function(){sendCode(this,'f-ph','reset','f-er')};
$('p-login').onsubmit=function(e){e.preventDefault();$('l-er').textContent='';$('l-go').disabled=true;post('/account/login',{id:$('l-id').value,password:$('l-pw').value,remember:$('l-rm').checked?1:0,next:next}).then(function(j){$('l-go').disabled=false;if(j.ok)done(j);else err('l-er',j)})};
if($('p-otp'))$('p-otp').onsubmit=function(e){e.preventDefault();post('/account/otp-login',{phone:$('o-ph').value,code:$('o-code').value}).then(function(j){if(j.ok)done(j);else{$('o-er').className='er';err('o-er',j)}})};
$('p-reg').onsubmit=function(e){e.preventDefault();$('r-er').className='er';$('r-er').textContent='';if($('r-pw').value!==$('r-pw2').value){$('r-er').textContent='দুটি পাসওয়ার্ড মেলেনি';return}$('r-go').disabled=true;
 post('/account/register',{name:$('r-nm').value,phone:$('r-ph').value,password:$('r-pw').value,password_confirmation:$('r-pw2').value,email:$('r-em').value,ref:$('r-rf').value,code:$('r-code')?$('r-code').value:''}).then(function(j){$('r-go').disabled=false;if(j.ok)done(j);else err('r-er',j)})};
$('p-forgot').onsubmit=function(e){e.preventDefault();$('f-er').className='er';if($('f-pw').value!==$('f-pw2').value){$('f-er').textContent='দুটি পাসওয়ার্ড মেলেনি';return}post('/account/reset',{phone:$('f-ph').value,code:$('f-code').value,password:$('f-pw').value,password_confirmation:$('f-pw2').value}).then(function(j){if(j.ok)done(j);else err('f-er',j)})};
if(/[?&]out=1/.test(location.search)){try{localStorage.removeItem('df_cust');localStorage.removeItem('dfchat');sessionStorage.removeItem('wsync')}catch(e){}}
show(window.__m||'login');
})();</script>
@endverbatim
@endsection
