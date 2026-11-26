@extends('mp.layout')@section('title','Sign in')@section('main','narrow')
@section('body')
<h1>Sign in</h1><p class="sub">For verified shipping owners, local agencies, vessels and crew.</p>
<form class="card" method="post" action="/marine-portal/login" autocomplete="on">@csrf
<label for="lg">Username or email</label><input id="lg" type="text" name="login" value="{{ old('login') }}" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="160" required autofocus>
<label for="pw">Password</label><input id="pw" type="password" name="password" autocomplete="current-password" maxlength="128" required>
<button class="w" style="margin-top:18px">Sign in</button>
<p style="margin:14px 0 0;text-align:center;font-size:14px"><a href="/marine-portal/forgot">Forgot password?</a></p></form>
<p class="hint" style="text-align:center">No account yet? <a href="https://marinedutyfreebd.com/request-access">Request access</a>. Accounts are opened after our marine desk verifies your documents.</p>
@endsection
