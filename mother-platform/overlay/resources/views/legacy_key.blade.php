@extends('layout')@section('title','Old-site import key')
@section('body')
<h1 style="margin:0 0 6px">Old-site import key</h1>
<p style="font-size:13px;color:#6B7280;max-width:620px">Visible to the owner only. You type this key into the sync box on the old site yourself. Do not share it. It is deleted for good once the move is finished.</p>
<div class="card" style="max-width:620px"><label>Key</label><input id="k" readonly value="{{ $token }}" onclick="this.select()" style="font-family:monospace"><div style="margin-top:10px"><button type="button" onclick="document.getElementById('k').select();document.execCommand('copy');this.textContent='Copied'">Copy key</button></div></div>
@endsection
