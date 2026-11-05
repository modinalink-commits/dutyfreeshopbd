@extends('sp.layout')@section('title','Change password')
@section('body')<h1>Change password</h1><p class="sub">@if($u->must_change)For your security, please set your own password before continuing.@endif</p>
<form class="card" method="post" action="/supplier-portal/password" style="max-width:420px">@csrf
<label>Current (temporary) password</label><input type="password" name="current" required><label>New password (8+ characters)</label><input type="password" name="password" required><label>Repeat new password</label><input type="password" name="password2" required><button style="margin-top:14px">Save password</button></form>@endsection
