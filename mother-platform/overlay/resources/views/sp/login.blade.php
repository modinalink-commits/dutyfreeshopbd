<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Sign in · {{ $brand }}</title><meta name="theme-color" content="#FF5A00"><link rel="manifest" href="/supplier-portal/manifest.webmanifest">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">@include('sp.css')
<style>body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:16px}.box{width:100%;max-width:380px}.box .logo{text-align:center;margin-bottom:14px}.box .logo img{height:44px}.box h1{text-align:center;font-size:18px}</style></head><body>
<div class="box"><div class="logo"><img src="/brand/export-logo.png" alt=""></div><h1>{{ $brand }}</h1><p class="sub" style="text-align:center">Sign in to see your orders, price requests, ledger and payments.</p>
@if(session('ok'))<div class="al ok">{{ session('ok') }}</div>@endif @if(session('bad'))<div class="al bad">{{ session('bad') }}</div>@endif
<form class="card" method="post" action="/supplier-portal/login">@csrf
<label>Supplier code / mobile / e-mail</label><input name="login" value="{{ old('login') }}" autocomplete="username" required autofocus>
<label>Password</label><input type="password" name="password" autocomplete="current-password" required>
<button style="width:100%;margin-top:14px">Sign in</button><p style="text-align:center;margin:12px 0 0"><a href="/supplier-portal/forgot" style="color:var(--or)">Forgot password?</a></p></form></div></body></html>
