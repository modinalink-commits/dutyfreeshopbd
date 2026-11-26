@extends('mp.layout')@section('title','Add documents')@section('main','narrow')
@section('body')
@if($bad)
<h1>This link is no longer valid</h1><div class="card"><p style="margin:0">It has expired or your request has already been decided. Reply to our email if you still need to send documents.</p></div>
@else
<h1>Add documents</h1><p class="sub">For the account request of <b>{{ $q->company ?: $q->name }}</b>.</p>
@if($q->docs_note)<div class="al info" style="white-space:pre-line"><b>Our marine desk asked for:</b>
{{ $q->docs_note }}</div>@endif
<form class="card" method="post" action="/marine-portal/documents/{{ $token }}" enctype="multipart/form-data">@csrf
<label for="fl">Files (up to 4 at a time)</label><input id="fl" type="file" name="docs[]" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" multiple required>
<p class="hint">PDF, JPG, PNG or WEBP, up to 5 MB each. {{ $n }} file(s) received so far.</p>
<button class="w" style="margin-top:18px">Send documents</button></form>
@endif
@endsection
