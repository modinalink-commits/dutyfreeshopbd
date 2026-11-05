<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<link rel="stylesheet" href="/css/nk-type.css?v=1"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#123a86">
<meta name="csrf-token" content="{{ csrf_token() }}"><meta name="robots" content="noindex">
<title>@yield('title', 'সেলসম্যান অ্যাপ') · NK Corporation</title>
<link rel="manifest" href="{{ route('app.manifest') }}"><link rel="icon" href="/img/logo.webp"><link rel="apple-touch-icon" href="/img/logo.webp">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;700&display=swap">
<style>
*{box-sizing:border-box}html,body{margin:0;background:#eef2f9}
body{font-family:'Hind Siliguri','Plus Jakarta Sans',sans-serif;color:#14213d}
.app{max-width:480px;margin:0 auto;min-height:100dvh;background:#fff;display:flex;flex-direction:column}
.bar{display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid #dbe3f0;position:sticky;top:0;background:#fff;z-index:5}
.bar .back{width:44px;height:44px;display:flex;align-items:center;justify-content:center;color:#123a86;text-decoration:none}
.bar .ttl{font-size:var(--fs-18);font-weight:700;flex-grow:1}
.body{flex-grow:1;padding:12px 16px;display:flex;flex-direction:column;gap:14px}
.lb{font-size:var(--fs-13);font-weight:600;color:#51607a}
.n{font-family:'Plus Jakarta Sans',sans-serif;font-variant-numeric:tabular-nums}
.chip{min-height:52px;border-radius:14px;cursor:pointer;font:700 15px 'Hind Siliguri',sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;border:1px solid #7b8aa8;background:#fff;color:#14213d;padding:0 6px}
.chip.on{border:2px solid #123a86;background:#e6eeff;color:#123a86}
.step{width:52px;height:52px;border-radius:14px;border:1px solid #7b8aa8;background:#fff;font:700 24px 'Plus Jakarta Sans',sans-serif;color:#123a86;cursor:pointer}
.num{font:700 30px 'Plus Jakarta Sans',sans-serif;min-width:56px;text-align:center}
.big{width:100%;min-height:56px;border:0;border-radius:16px;cursor:pointer;font:700 18px 'Hind Siliguri',sans-serif;color:#fff;background:#b45309;display:flex;align-items:center;justify-content:center;text-decoration:none}
.inp{width:100%;min-height:52px;border:1px solid #7b8aa8;border-radius:12px;padding:0 12px;font:700 20px 'Plus Jakarta Sans',sans-serif;color:#14213d}
.txt{width:100%;min-height:48px;border:1px solid #7b8aa8;border-radius:12px;padding:0 12px;font:500 15px 'Hind Siliguri',sans-serif}
.card{border:1px solid #dbe3f0;border-radius:14px;padding:12px}
.pill{font-size:var(--fs-11);font-weight:700;padding:2px 8px;border-radius:999px}
.ok{background:#e3f6ec;color:#0f6b3c;border-radius:12px;padding:10px 12px;font-size:var(--fs-14);font-weight:600}.er{background:#fde9e7;color:#b3261e;border-radius:12px;padding:10px 12px;font-size:var(--fs-14);font-weight:600}
nav.tabs{position:sticky;bottom:0;background:#fff;border-top:1px solid #dbe3f0;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));padding:6px 6px calc(10px + env(safe-area-inset-bottom))}
nav.tabs a{min-height:54px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;font-size:var(--fs-12);text-decoration:none;border-radius:12px;font-weight:500;color:#51607a}
nav.tabs a.on{font-weight:700;color:#123a86;background:#e6eeff}
button:focus-visible,a:focus-visible,input:focus-visible{outline:2px solid #1d4ed8;outline-offset:2px}
</style>
</head>
<body>
<div class="app">
@yield('bar')
<main class="body">
@if(session('ok'))<div class="ok">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="er">{{ $errors->first() }}</div>@endif
@yield('content')
</main>
@hasSection('foot')@yield('foot')@endif
@unless(isset($noNav))
@php($tab = $tab ?? 'home')
<nav class="tabs" aria-label="অ্যাপ মেনু">
  @foreach([['home', 'হোম', 'app.home', 'M3 11l9-7 9 7M5 10v10h5v-6h4v6h5V10'], ['sale', 'বিক্রি', 'app.sale', 'M12 5v14M5 12h14'], ['empty', 'খালি', 'app.empty', 'M8 3h8M9 3v3M15 3v3M6 9a3 3 0 013-3h6a3 3 0 013 3v10a2 2 0 01-2 2H8a2 2 0 01-2-2z'], ['pay', 'টাকা', 'app.pay', 'M2 6h20v12H2zM12 15a3 3 0 100-6 3 3 0 000 6z'], ['summary', 'সারাংশ', 'app.summary', 'M4 20V10M10 20V4M16 20v-7M22 20H2']] as [$k, $l, $r, $ic])
    <a href="{{ route($r) }}" class="{{ $tab === $k ? 'on' : '' }}" @if($tab === $k) aria-current="page" @endif><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $ic }}"></path></svg><span>{{ $l }}</span></a>
  @endforeach
</nav>
@endunless
</div>
<script>
/* Offline queue: a sale or payment saved without internet waits here and is sent again when the phone is online. */
window.NK = (function(){
  const KEY='nk_queue', tok=document.querySelector('meta[name=csrf-token]').content;
  const get=()=>{try{return JSON.parse(localStorage.getItem(KEY)||'[]')}catch(e){return []}}, put=q=>{try{localStorage.setItem(KEY,JSON.stringify(q))}catch(e){}};
  async function send(item){
    const r=await fetch(item.url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':tok,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(item.body),credentials:'same-origin'});
    let j={}; try{j=await r.json()}catch(e){}
    if(r.ok&&j.ok) return {done:true,j};
    if(r.status===422||r.status===403) return {done:false,fail:true,j};
    throw new Error('net');
  }
  async function sync(){
    const q=get(); const keep=[];
    for(const it of q){ try{ const res=await send(it); if(res.done){ it.state='ok'; it.invoice=res.j.invoice; it.url=res.j.url; } else { it.state='fail'; it.error=(res.j&&res.j.error)||'সার্ভার নিল না'; keep.push(it);} } catch(e){ it.state='wait'; keep.push(it); } }
    put(keep); localStorage.setItem('nk_last_sync',new Date().toTimeString().slice(0,5)); document.dispatchEvent(new Event('nk-synced')); return keep;
  }
  window.addEventListener('online',sync);
  if('serviceWorker' in navigator){ navigator.serviceWorker.register('/app-sw.js',{scope:'/app'}).catch(()=>{}); }
  return {get,put,send,sync,tok};
})();
</script>
@stack('js')
</body>
</html>
