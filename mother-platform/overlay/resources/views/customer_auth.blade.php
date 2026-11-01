@extends('layout')@section('title','Customer Authentication')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Customer Authentication</h1><div style="font-size:12px;color:#6B7280">Customer login methods: Email + Password এবং Facebook। একজন customer = একটি account।</div></div>
@if(session('ok'))<div class="card" style="background:#ECFDF5;border-color:#A7F3D0;color:#065F46">{{ session('ok') }}</div>@endif
<form method="post" action="/admin/customer-auth" class="card" style="max-width:640px">@csrf
<h3 style="margin-top:0">Email Login</h3>
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="email_on" value="1" {{ $emailOn ? 'checked' : '' }}> Email Login & Registration ON</label>
<div style="font-size:12px;color:#6B7280;margin:4px 0 14px">Password reset ইমেইল: {{ $mail ? 'Mail সার্ভার চালু আছে' : 'Mail সার্ভার কনফিগার করা নেই — তখন মোবাইল কোডে রিসেট কাজ করবে' }}</div>
<h3>Facebook Login</h3>
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="fb_on" value="1" {{ $fbFlag ? 'checked' : '' }}> Facebook Login ON</label>
<label>Facebook App ID</label><input name="app_id" value="{{ $appId }}" autocomplete="off" placeholder="App ID">
<label>Facebook App Secret</label><input type="password" name="app_secret" autocomplete="new-password" placeholder="{{ $hasSecret ? '•••••••• (সেভ করা আছে — বদলাতে নতুনটি দিন)' : 'App Secret' }}">
<label>Redirect URL (Facebook App → Valid OAuth Redirect URIs-এ বসান)</label><input value="{{ $redirect }}" readonly onclick="this.select()">
<div style="margin:12px 0;font-size:13px">Status: <b id="st" style="color:{{ $configured ? '#15803D' : '#B45309' }}">{{ $configured ? 'Saved — Test Connection চালান' : 'Configuration Required' }}</b></div>
<button>Save</button> <button type="button" class="btn g" id="tc">Test Connection</button>
</form>
<script>document.getElementById('tc').onclick=function(){var s=document.getElementById('st');s.textContent='Testing…';fetch('/admin/customer-auth/test',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('input[name=_token]').value,Accept:'application/json'}}).then(function(r){return r.json()}).then(function(j){s.textContent=j.status;s.style.color=j.ok?'#15803D':'#B91C1C'}).catch(function(){s.textContent='Configuration Required';s.style.color='#B91C1C'})}</script>
@endsection
