@extends('mp.layout')@section('title','Forgot password')@section('main','narrow')
@section('body')
<h1>Forgot password</h1><p class="sub">Enter your username or the email of your account. We email a link that works once, for 2 hours.</p>
<form class="card" method="post" action="/marine-portal/forgot">@csrf
<label for="lg">Username or email</label><input id="lg" type="text" name="login" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="160" required autofocus>
<button class="w" style="margin-top:18px">Email me a link</button>
<p style="margin:14px 0 0;text-align:center;font-size:14px"><a href="/marine-portal/login">Back to sign in</a></p></form>
@endsection
