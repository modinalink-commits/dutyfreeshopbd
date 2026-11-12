@extends('layout')@section('title','Edit Store')
@section('body')
<h1>Edit Store</h1>
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<form class="card" method="post" action="/admin/stores/{{ $s->id }}/update" style="max-width:520px">@csrf
<label>Store name</label><input name="name" value="{{ old('name', $s->name) }}" required maxlength="120">
<label>Domain</label><input name="domain" value="{{ old('domain', $s->domain) }}" required maxlength="200" placeholder="example.com">
<label>Source category <small style="color:#6B7280">(shown on the Stores page; separate several with commas)</small></label><input name="source_category" value="{{ old('source_category', $s->source_category ?? '') }}" maxlength="160" placeholder="e.g. Fashion">
<label style="display:flex;gap:8px;align-items:center;margin-top:10px"><input type="checkbox" name="active" value="1" style="width:auto" {{ $s->active ? 'checked' : '' }}> Active</label>
<div style="margin-top:14px;display:flex;gap:8px"><button>Save</button><a class="btn g" href="/admin/stores">Cancel</a></div></form>
@endsection
