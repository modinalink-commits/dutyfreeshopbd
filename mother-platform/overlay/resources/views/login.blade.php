@extends('layout')@section('title','Sign in')
@section('body')<div style="max-width:380px;margin:8vh auto"><div class="card"><h1>Sign in</h1>
<form method="post" action="/admin/login">@csrf<label>Email</label><input name="email" type="email" value="{{ old('email') }}" required autofocus>
<label>Password</label><input name="password" type="password" required>@error('email')<div class="err">{{ $message }}</div>@enderror
<div style="margin-top:14px"><button>Sign in</button></div></form></div></div>@endsection
