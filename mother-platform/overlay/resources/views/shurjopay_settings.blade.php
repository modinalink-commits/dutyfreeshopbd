@extends('layout')@section('title','ShurjoPay')
@section('body')
<h1 class="h">ShurjoPay Online Payment</h1>
@if(session('ok'))<div class="ok">{{ session('ok') }}</div>@endif
@if(session('err'))<div class="err" style="background:#FEE2E2;color:#991B1B;padding:10px;border-radius:8px;margin-bottom:10px">{{ session('err') }}</div>@endif
<form class="card" method="post" action="/admin/shurjopay" style="max-width:560px">@csrf
<p style="margin-top:0;color:#6b7280">Customers can pay by bKash, Nagad, card and more through ShurjoPay. The password is stored encrypted and never shown again.</p>
<label>Mode</label><select name="mode"><option value="sandbox" @selected($s['mode']==='sandbox')>Sandbox (test, no real money)</option><option value="live" @selected($s['mode']==='live')>Live (real payments)</option></select>
<label>Username</label><input name="username" value="{{ $s['username'] }}" autocomplete="off">
<label>Password {{ $s['has_pw'] ? '(saved; leave blank to keep)' : '' }}</label><input name="password" type="password" autocomplete="new-password" placeholder="{{ $s['has_pw'] ? '••••••••' : '' }}">
<label>Prefix</label><input name="prefix" value="{{ $s['prefix'] }}" maxlength="20" autocomplete="off">
<label style="display:flex;gap:8px;align-items:center;margin-top:10px"><input type="checkbox" name="enabled" value="1" @checked($s['enabled'])> Show ShurjoPay at checkout</label>
<p style="font-size:12px;color:#6b7280">API: {{ $base }} · Status: <b>{{ $ready ? 'credentials saved' : 'credentials missing' }}</b></p>
<button class="btn">Save</button>
</form>
<form method="post" action="/admin/shurjopay/test" style="margin:10px 0">@csrf<button class="btn">Test connection (login only, no payment)</button></form>
<h3>Recent online payments</h3>
<table class="tb"><tr><th>Invoice</th><th>Amount</th><th>Status</th><th>Method</th><th>Reference</th><th>Date</th></tr>
@forelse($recent as $o)<tr><td>{{ $o->invoice }}</td><td>৳{{ number_format($o->total) }}</td><td>{{ $o->pay_status }}</td><td>{{ $o->sp_method }}</td><td>{{ $o->pay_txn }}</td><td>{{ $o->created_at }}</td></tr>@empty<tr><td colspan="6">No online payments yet.</td></tr>@endforelse
</table>
@endsection
