@extends('layout')
@section('title', 'Change password')
@section('content')
<div><h1>Change password</h1>
@if(auth()->user()->must_change_password)<p class="sub">You are using a temporary password. Choose your own password to continue.</p>@endif</div>
<form class="card pad" method="post" action="{{ route('password.update') }}" style="max-width:460px;display:flex;flex-direction:column;gap:14px">
  @csrf
  <label class="f">Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
  <label class="f">New password (at least 10 characters, letters and numbers)<input type="password" name="password" autocomplete="new-password" required></label>
  <label class="f">Type the new password again<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
  <button class="btn pri" type="submit">Save new password</button>
</form>
@endsection
