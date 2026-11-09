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
.er{color:#B91C1C;font-size:12.5px;margin-top:9px;min-height:16px}.ok2{color:#166534;font-size:12.5px}
.sep{text-align:center;color:#9CA3AF;font-size:12px;margin:14px 0 4px}.alt{width:100%;height:42px;border:1.25px solid #E5E7EB;background:#fff;border-radius:11px;font-weight:600;font-size:13.5px;cursor:pointer;margin-top:8px;color:var(--n)}
.soc{display:flex;flex-direction:column;gap:0}.soc .alt{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;box-sizing:border-box;line-height:1;font-size:12.5px;padding:0 6px;margin-top:8px}.soc .alt.gg{flex:none;width:100%;height:50px;min-height:50px;font-size:15px;font-weight:700;gap:11px;border:1.5px solid #D1D5DB;border-radius:12px;color:#0B1220;background:#fff;margin-top:10px}.soc .alt.gg:hover{background:#F8FAFC;border-color:#94A3B8}.soc .alt.gg svg{width:22px;height:22px}.alt:hover{border-color:#D1D5DB;box-shadow:0 1px 4px rgba(15,23,42,.08)}.alt.fbx{background:#EAF2FF;border-color:#BBD3FA}.alt.fbx:hover{background:#DDEBFF;border-color:#93BEF8}.ic{position:relative}.ic svg{position:absolute;left:13px;top:13px;pointer-events:none}.ic input{padding-left:42px!important;background:#F1F5FF!important}.pw.ic input{padding-right:44px!important}.alt.fbx{display:flex;align-items:center;justify-content:center;gap:9px;text-decoration:none;box-sizing:border-box;line-height:1}.sm{display:flex;gap:8px}.sm input{flex:1}.sm button{height:44px;border:1.25px solid var(--o);color:var(--o);background:#fff;border-radius:11px;padding:0 12px;font-weight:700;cursor:pointer;white-space:nowrap}
.pn{display:none}.pn.on{display:block}
.gl{text-align:center;font-size:12.5px;color:var(--mut);margin-top:14px}.gl a{color:var(--o);font-weight:700}
</style>
<div class="au"><div class="auc">
<h1 id="ttl">My Account</h1><p class="s">অর্ডার ট্র্যাক, ইনভয়েস, ডিসকাউন্ট ও আপনার অ্যাকাউন্ট পরিচালনা করুন।</p>
<div class="aut"><button type="button" id="tl" class="{{ $mode==='login'?'on':'' }}">Login</button><button type="button" id="tr" class="{{ $mode==='register'?'on':'' }}">Register</button></div>

<form class="pn" id="p-login" autocomplete="on">
<label>Mobile Number or Email</label><div class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><input type="text" id="l-id" inputmode="text" placeholder="01XXXXXXXXX অথবা your@email.com" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required></div>
<label>Password</label><div class="pw ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0F172A" stroke-width="1.8" stroke-linecap="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><input type="password" id="l-pw" placeholder="Password" autocomplete="current-password" required><button type="button" data-eye="l-pw" aria-label="Show password">👁</button></div>
<div class="row"><label style="margin:0;font-weight:500"><input type="checkbox" id="l-rm" checked style="width:auto;height:auto"> Remember me</label><button type="button" class="lk" id="to-forgot">Forgot Password?</button></div>@if($smsOn)<div class="row" style="margin-top:-4px"><button type="button" class="lk" id="to-otp">📱 মোবাইলে SMS কোড দিয়ে লগইন</button></div>@endif
<button class="go" id="l-go">Login</button>
<div class="er" id="l-er">@if($fbErr === "off")এই লগইন পদ্ধতি এখনো চালু হয়নি। অনুগ্রহ করে Email দিয়ে লগইন করুন।@elseif($fbErr)Google দিয়ে লগইন করা যায়নি। আবার চেষ্টা করুন অথবা Email দিয়ে লগইন করুন।@endif</div>
<div class="sep">──── অথবা দ্রুত লগইন করুন ────</div><div class="soc"><a class="alt gg" href="/account/google?next={{ urlencode(request('next','')) }}"><svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.2 5.4-4.7 7.1l7.6 5.9c4.4-4.1 6.9-10.1 6.9-17.5z"/><path fill="#FBBC05" d="M10.5 28.7a14.5 14.5 0 0 1 0-9.4l-7.9-6.1a24 24 0 0 0 0 21.6l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>Google দিয়ে লগইন করুন</a></div>
<div class="gl" style="margin-top:12px">নতুন Customer? <a href="#" id="to-reg">Create Account</a></div></form>

<form class="pn" id="p-otp"><label>Mobile Number</label><div class="sm"><input type="tel" id="o-ph" data-bdphone placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="11"><button type="button" id="o-send">কোড পাঠান</button></div>
<label>৬ ডিজিটের কোড</label><input type="text" id="o-code" inputmode="numeric" maxlength="6" placeholder="------">
<button class="go" id="o-go">Login</button><div class="row"><button type="button" class="lk" id="o-back">← পাসওয়ার্ড দিয়ে লগইন</button></div><div class="er" id="o-er"></div></form>

<form class="pn" id="p-reg" autocomplete="on">
<label>Full Name</label><input type="text" id="r-nm" maxlength="80" autocomplete="name" required>
<label>Email Address</label><input type="email" id="r-em" placeholder="your@email.com" autocomplete="email" required>
<label>Password</label><div class="pw"><input type="password" id="r-pw" minlength="8" autocomplete="new-password" required><button type="button" data-eye="r-pw" aria-label="Show password">👁</button></div>
<label>Confirm Password</label><input type="password" id="r-pw2" minlength="8" autocomplete="new-password" required>
<label>Referral Code (ঐচ্ছিক)</label><input type="text" id="r-rf" maxlength="40">
<button class="go" id="r-go">Create Account</button><div class="row" style="justify-content:center"><button type="button" class="lk" id="to-regm">📱 মোবাইল নম্বর দিয়ে অ্যাকাউন্ট খুলুন</button></div><div class="er" id="r-er"></div>
<div class="sep">──── অথবা দ্রুত লগইন করুন ────</div><div class="soc"><a class="alt gg" href="/account/google"><svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.2 5.4-4.7 7.1l7.6 5.9c4.4-4.1 6.9-10.1 6.9-17.5z"/><path fill="#FBBC05" d="M10.5 28.7a14.5 14.5 0 0 1 0-9.4l-7.9-6.1a24 24 0 0 0 0 21.6l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.6-5.9c-2.1 1.4-4.9 2.3-8.3 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>Google দিয়ে লগইন করুন</a></div></form>

<form class="pn" id="p-regm" autocomplete="on">
<label>Full Name</label><input type="text" id="rm-nm" maxlength="80" autocomplete="name" required>
<label>Mobile Number</label><div class="sm"><input type="tel" id="rm-ph" data-bdphone placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="11" autocomplete="tel" required>@if($smsOn)<button type="button" id="rm-send">কোড পাঠান</button>@endif</div>
@if($smsOn)<label>৬ ডিজিটের কোড</label><input type="text" id="rm-code" inputmode="numeric" maxlength="6" placeholder="------" autocomplete="one-time-code">@endif
<label>Email (ঐচ্ছিক)</label><input type="email" id="rm-em" placeholder="your@email.com" autocomplete="email">
<label>Password</label><div class="pw"><input type="password" id="rm-pw" minlength="8" autocomplete="new-password" required><button type="button" data-eye="rm-pw" aria-label="Show password">👁</button></div>
<label>Confirm Password</label><input type="password" id="rm-pw2" minlength="8" autocomplete="new-password" required>
<label>Referral Code (ঐচ্ছিক)</label><input type="text" id="rm-rf" maxlength="40">
<button class="go" id="rm-go">Create Account</button><div class="row" style="justify-content:center"><button type="button" class="lk" id="to-rege">✉️ Email দিয়ে অ্যাকাউন্ট খুলুন</button></div><div class="er" id="rm-er"></div></form>

<form class="pn" id="p-forgot"><p class="s" style="margin-top:0">আপনার Email দিন — পাসওয়ার্ড রিসেট লিংক পাঠানো হবে।</p>
<div class="sm"><input type="email" id="fe-em" placeholder="your@email.com"><button type="button" id="fe-send">লিংক পাঠান</button></div><div class="er" id="fe-er"></div>
@if($smsOn)<p class="s" style="margin:12px 0 0">অথবা রেজিস্টার্ড মোবাইল নম্বরে কোড পাঠানো হবে।</p>@endif
<div @if(!$smsOn) style="display:none" @endif>
<label>Mobile Number</label><div class="sm"><input type="tel" id="f-ph" data-bdphone placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="11"><button type="button" id="f-send">কোড পাঠান</button></div>
<label>৬ ডিজিটের কোড</label><input type="text" id="f-code" inputmode="numeric" maxlength="6" placeholder="------"><label>নতুন পাসওয়ার্ড</label><input type="password" id="f-pw" minlength="8" autocomplete="new-password"><label>নতুন পাসওয়ার্ড (আবার)</label><input type="password" id="f-pw2" minlength="8" autocomplete="new-password">
<button class="go" id="f-go">পাসওয়ার্ড বদলান</button><div class="er" id="f-er"></div></div><div class="row"><button type="button" class="lk" id="f-back">← লগইনে ফিরুন</button></div></form>

<div class="gl">অর্ডার করতে অ্যাকাউন্ট লাগবে না — <a href="/checkout">গেস্ট হিসেবেও অর্ডার করুন</a><br>আগের অর্ডার ট্র্যাক: <a href="/track">Track Order</a></div>
</div></div>
@endsection
@section('js')
<script>window.__m=@json($mode==='register'?'reg':'login');</script>
@verbatim
<script>(function(){
var $=function(i){return document.getElementById(i)},P=['login','otp','reg','regm','forgot'],next=(new URLSearchParams(location.search)).get('next')||'';
function show(n){P.forEach(function(p){$('p-'+p).classList.toggle('on',p===n)});$('tl').classList.toggle('on',n==='login'||n==='otp'||n==='forgot');$('tr').classList.toggle('on',n==='reg'||n==='regm');$('ttl').textContent=(n==='reg'||n==='regm')?'Create Account':(n==='forgot'?'Reset Password':'My Account')}
function post(u,b){return fetch(u,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(b)}).then(function(r){return r.json().catch(function(){return{}}).then(function(j){j._s=r.status;return j})})}
function err(e,j){if(j._s===429&&!j.error){$(e).textContent='অনেকবার চেষ্টা করা হয়েছে। ১ মিনিট অপেক্ষা করে আবার চেষ্টা করুন।';return}var m=j.error||(j.errors&&Object.values(j.errors)[0][0])||j.message||'কিছু ভুল হয়েছে';if(j._s===422&&j.errors){m=Object.values(j.errors)[0][0];if(/confirmation/.test(m))m='দুটি পাসওয়ার্ড মেলেনি';else if(/at least/.test(m))m='পাসওয়ার্ড কমপক্ষে ৮ অক্ষর দিন'}$(e).textContent=m}
function done(j){if(j.ok){try{fetch('/account/me',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(m){if(m.in){localStorage.setItem('df_cust',JSON.stringify({p:m.p,n:m.n,e:m.e,a:m.a,d:m.d}));var w=JSON.parse(localStorage.getItem('dfw')||'[]');return post('/account/wishlist/sync',{ids:w})}}).catch(function(){}).then(function(){location.href=(next&&next.charAt(0)==='/'&&next.charAt(1)!=='/')?next:j.go})}catch(e){location.href=j.go}}}
if($('to-reg'))$('to-reg').onclick=function(e){e.preventDefault();show('reg')};$('fe-send').onclick=function(){var b=this;b.disabled=true;post('/account/forgot-email',{email:$('fe-em').value}).then(function(j){b.disabled=false;$('fe-er').className=j.ok?'er ok2':'er';if(j.ok)$('fe-er').textContent=j.msg;else err('fe-er',j)})};
$('tl').onclick=function(){show('login')};$('tr').onclick=function(){show('reg')};$('to-forgot').onclick=function(){show('forgot')};$('f-back').onclick=function(){show('login')};
if($('to-otp')){$('to-otp').onclick=function(){show('otp')};$('o-back').onclick=function(){show('login')}}
document.querySelectorAll('[data-eye]').forEach(function(b){b.onclick=function(){var i=$(b.getAttribute('data-eye'));i.type=i.type==='password'?'text':'password'}});
function cool(btn){btn.disabled=true;var s=60,t=setInterval(function(){s--;btn.textContent=s>0?s+' সে.':'আবার পাঠান';if(s<=0){clearInterval(t);btn.disabled=false}},1000)}
function sendCode(btn,ph,purpose,er){post('/account/otp',{phone:$(ph).value,purpose:purpose}).then(function(j){if(j.sent){$(er).className='er ok2';$(er).textContent='কোড পাঠানো হয়েছে (৫ মিনিট বৈধ)';cool(btn)}else{$(er).className='er';err(er,j)}})}
if($('o-send'))$('o-send').onclick=function(){sendCode(this,'o-ph','login','o-er')};
$('f-send').onclick=function(){sendCode(this,'f-ph','reset','f-er')};
function bn(v){return String(v||'').replace(/[০-৯]/g,function(c){return '০১২৩৪৫৬৭৮৯'.indexOf(c)}).trim()}function lid(v){v=bn(v);return /^[+\d\s().-]+$/.test(v)?v.replace(/[\s().-]/g,''):v}
$('p-login').onsubmit=function(e){e.preventDefault();$('l-er').textContent='';$('l-go').disabled=true;post('/account/login',{id:lid($('l-id').value),password:$('l-pw').value,remember:$('l-rm').checked?1:0,next:next}).then(function(j){$('l-go').disabled=false;if(j.ok)done(j);else err('l-er',j)})};
if($('p-otp'))$('p-otp').onsubmit=function(e){e.preventDefault();post('/account/otp-login',{phone:$('o-ph').value,code:$('o-code').value}).then(function(j){if(j.ok)done(j);else{$('o-er').className='er';err('o-er',j)}})};
$('p-reg').onsubmit=function(e){e.preventDefault();$('r-er').className='er';$('r-er').textContent='';if($('r-pw').value!==$('r-pw2').value){$('r-er').textContent='দুটি পাসওয়ার্ড মেলেনি';return}$('r-go').disabled=true;
 post('/account/register-email',{name:$('r-nm').value,password:$('r-pw').value,password_confirmation:$('r-pw2').value,email:$('r-em').value,ref:$('r-rf').value}).then(function(j){$('r-go').disabled=false;if(j.ok)done(j);else err('r-er',j)})};
$('p-forgot').onsubmit=function(e){e.preventDefault();$('f-er').className='er';if($('f-pw').value!==$('f-pw2').value){$('f-er').textContent='দুটি পাসওয়ার্ড মেলেনি';return}post('/account/reset',{phone:$('f-ph').value,code:$('f-code').value,password:$('f-pw').value,password_confirmation:$('f-pw2').value}).then(function(j){if(j.ok)done(j);else err('f-er',j)})};
if($('to-regm')){$('to-regm').onclick=function(){show('regm')};$('to-rege').onclick=function(){show('reg')}}
if($('rm-send'))$('rm-send').onclick=function(){$('rm-ph').value=bn($('rm-ph').value);sendCode(this,'rm-ph','register','rm-er')};
$('p-regm').onsubmit=function(e){e.preventDefault();$('rm-er').className='er';$('rm-er').textContent='';if($('rm-pw').value!==$('rm-pw2').value){$('rm-er').textContent='দুটি পাসওয়ার্ড মেলেনি';return}$('rm-go').disabled=true;
 post('/account/register',{name:$('rm-nm').value,phone:bn($('rm-ph').value),code:$('rm-code')?$('rm-code').value:'',email:$('rm-em').value||null,password:$('rm-pw').value,password_confirmation:$('rm-pw2').value,ref:$('rm-rf').value}).then(function(j){$('rm-go').disabled=false;if(j.ok)done(j);else err('rm-er',j)})};
if(/[?&]out=1/.test(location.search)){try{localStorage.removeItem('df_cust');localStorage.removeItem('dfchat');sessionStorage.removeItem('wsync')}catch(e){}}
show(window.__m||'login');
})();</script>
@endverbatim
@endsection
