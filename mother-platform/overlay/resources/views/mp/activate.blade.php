@extends('mp.layout')@section('title','Set password')@section('main','narrow')
@section('body')
@if($bad)
<h1>This link is no longer valid</h1>
<div class="card"><p style="margin:0 0 12px">The link was already used, has expired, or was replaced by a newer one.</p><p style="margin:0">If your account is active, use <a href="/marine-portal/forgot">Forgot password</a>. If you are still waiting to activate it, ask our marine desk to resend your invitation.</p></div>
@else
<h1>{{ $a->status === 'active' ? 'Set a new password' : 'Activate your account' }}</h1><p class="sub">Username: <b>{{ $a->username }}</b></p>
<ol class="steps"><li class="on">1. Get a code by email</li><li class="{{ $sent ? 'on' : '' }}">2. Enter the code and your password</li></ol>
<form class="card" method="post" action="/marine-portal/activate/{{ $token }}/code">@csrf
<h2>Step 1</h2><p style="margin:0 0 14px;font-size:14px">We send a 6-digit code to <b>{{ $masked }}</b> to confirm this address is yours. The code works for 10 minutes.</p>
<button class="{{ $sent ? 'g' : '' }} w">{{ $sent ? 'Send a new code' : 'Send code' }}</button></form>
<form class="card" method="post" action="/marine-portal/activate/{{ $token }}" autocomplete="off">@csrf
<h2>Step 2</h2>
@if(!$sent)<div class="al info">Press "Send code" first.</div>@endif
<label for="cd">6-digit code from the email</label><input id="cd" class="code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
<input type="text" name="username" value="{{ $a->username }}" autocomplete="username" hidden>
<label for="p1">Your new password</label><input id="p1" type="password" name="password" autocomplete="new-password" minlength="10" maxlength="128" required>
<p class="hint">At least 10 characters. A sentence of several words is strong and easy to remember.</p>
<label for="p2">Repeat the password</label><input id="p2" type="password" name="password2" autocomplete="new-password" minlength="10" maxlength="128" required>
<button class="w" style="margin-top:18px">{{ $a->status === 'active' ? 'Save password' : 'Activate account' }}</button></form>
@endif
@endsection
