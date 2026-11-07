@extends('layout')@section('title','Connect AI client')
@section('body')
<div class="card" style="max-width:560px;margin:30px auto">
<h1 style="margin:0 0 6px">Connect “{{ $client->name }}” to Mother Commerce?</h1>
<p style="color:#6B7280">This AI client will get <b>read-only</b> access to orders, products, stock, customers, expenses, balances and reports. It cannot change anything, run database queries or see passwords/secrets. Every call is logged under MCP Server, and you can revoke access any time.</p>
<p style="font-size:13px">Signed in as <b>{{ Auth::user()->name }}</b> · Access lasts 1 hour and renews automatically for up to 30 days.</p>
<form method="post" action="/admin/oauth/authorize" style="display:flex;gap:10px">@csrf
@foreach($q as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
<button type="submit" name="decision" value="allow">Allow read-only access</button><button type="submit" name="decision" value="deny" class="g">Deny</button></form></div>
@endsection
