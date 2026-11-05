<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><title>Sign in · DutyFreeBD Control Center</title><link rel="icon" href="/pwa/icon-192.png">
<style>
:root{--o:#FF5A00;--n:#0B1220}
*{box-sizing:border-box}html,body{height:100%}
body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:var(--n);background:#FFFAF6;display:flex;align-items:center;justify-content:center;min-height:100vh;min-height:100dvh;padding:20px;overflow:hidden;position:relative}
.bg{position:fixed;inset:0;z-index:0;overflow:hidden;background:radial-gradient(1100px 600px at 50% -10%,#FFE9DA 0%,rgba(255,250,246,0) 60%),#FFFAF6}
.bg i{position:absolute;border-radius:50%;filter:blur(60px);opacity:.55;animation:drift 16s ease-in-out infinite alternate}
.bg i:nth-child(1){width:380px;height:380px;background:#FFD2B3;left:-90px;top:-80px}
.bg i:nth-child(2){width:320px;height:320px;background:#FFE3CF;right:-70px;bottom:-60px;animation-delay:-6s}
.bg i:nth-child(3){width:220px;height:220px;background:#FFC9A1;right:18%;top:8%;opacity:.35;animation-delay:-11s}
@keyframes drift{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(34px,26px,0) scale(1.08)}}
.card{position:relative;z-index:1;width:100%;max-width:400px;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border:1px solid #F3E4D8;border-radius:22px;padding:30px 28px 26px;box-shadow:0 30px 70px -28px rgba(11,18,32,.28),0 2px 6px rgba(11,18,32,.04);animation:rise .9s cubic-bezier(.2,.8,.2,1) both;overflow:hidden}
.card:before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,transparent,var(--o),#FFB37A,var(--o),transparent);background-size:220% 100%;animation:sheen 3.6s linear infinite}
@keyframes sheen{from{background-position:120% 0}to{background-position:-120% 0}}
@keyframes rise{from{opacity:0;transform:translateY(22px) scale(.985)}to{opacity:1;transform:none}}
.lg{display:flex;flex-direction:column;align-items:center;margin-bottom:20px;animation:fade .9s .2s ease both}
.lg img{width:178px;max-width:70%;height:auto;display:block;animation:float 5s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}
.lg small{margin-top:8px;font-size:11px;letter-spacing:.22em;font-weight:700;color:#9A6B4B;text-transform:uppercase}
@keyframes fade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
h1{margin:0 0 3px;font-size:20px;text-align:center;letter-spacing:-.01em;animation:fade .9s .3s ease both}
.sub{margin:0 0 18px;text-align:center;font-size:12.5px;color:#6B7280;animation:fade .9s .35s ease both}
label{display:block;font-size:12px;font-weight:700;margin:12px 0 6px;color:#334155;animation:fade .9s .4s ease both}
input{width:100%;height:46px;border:1.25px solid #E7E1DB;border-radius:12px;padding:0 14px;font-size:16px;background:#fff;color:var(--n);transition:border-color .2s,box-shadow .2s;font-family:inherit}
input:focus{outline:0;border-color:var(--o);box-shadow:0 0 0 4px rgba(255,90,0,.12)}
.pw{position:relative}.pw input{padding-right:48px}.pw button{position:absolute;right:6px;top:5px;width:36px;height:36px;border:0;background:none;cursor:pointer;font-size:17px;opacity:.65}
.go{width:100%;height:48px;margin-top:20px;border:0;border-radius:12px;background:linear-gradient(135deg,#FF7A1A,#FF5A00);color:#fff;font-weight:800;font-size:15px;letter-spacing:.02em;cursor:pointer;box-shadow:0 12px 24px -10px rgba(255,90,0,.7);transition:transform .15s,box-shadow .2s;position:relative;overflow:hidden;font-family:inherit;animation:fade .9s .5s ease both}
.go:hover{transform:translateY(-1px);box-shadow:0 16px 28px -10px rgba(255,90,0,.75)}.go:active{transform:translateY(0)}.go:disabled{opacity:.7;cursor:wait}
.go:after{content:"";position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.45),transparent);transform:skewX(-18deg);animation:shine 3.4s ease-in-out infinite}
@keyframes shine{0%,55%{left:-60%}100%{left:130%}}
.err{margin-top:12px;padding:9px 12px;border-radius:10px;background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;font-size:12.5px;animation:shake .4s}
@keyframes shake{20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}
.ft{margin-top:16px;text-align:center;font-size:11px;color:#9CA3AF;animation:fade .9s .6s ease both}
@media (prefers-reduced-motion:reduce){*,*:before,*:after{animation:none!important;transition:none!important}}
</style></head><body>
<div class="bg" aria-hidden="true"><i></i><i></i><i></i></div>
<main class="card" role="main">
<div class="lg"><img src="/dutyfree-bd-logo.png" alt="DutyFree BD" width="178" height="48"><small>Control Center</small></div>
<h1>Welcome back</h1><p class="sub">Sign in to manage your store</p>
<form method="post" action="/admin/login" id="lf">@csrf
<label for="em">Email</label><input id="em" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false">
<label for="pw">Password</label><div class="pw"><input id="pw" name="password" type="password" required autocomplete="current-password"><button type="button" id="eye" aria-label="Show password">👁</button></div>
@error('email')<div class="err" role="alert">{{ $message }}</div>@enderror
<button class="go" id="gb">Sign in</button></form>
<div class="ft">DutyFreeBD · Secure admin access</div>
</main>
<script>document.getElementById('eye').onclick=function(){var i=document.getElementById('pw');i.type=i.type==='password'?'text':'password'};document.getElementById('lf').onsubmit=function(){var b=document.getElementById('gb');b.disabled=true;b.textContent='Signing in…'};</script>
</body></html>
