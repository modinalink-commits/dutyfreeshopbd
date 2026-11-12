@extends('layout')@section('title','Edit Store')
@section('body')
<h1>Edit Store</h1>
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<form class="card" method="post" action="/admin/stores/{{ $s->id }}/update" style="max-width:520px">@csrf
<label>Store name</label><input name="name" value="{{ old('name', $s->name) }}" required maxlength="120">
<label>Domain</label><input name="domain" value="{{ old('domain', $s->domain) }}" required maxlength="200" placeholder="example.com">
<label>Source category <small style="color:#6B7280">(pick one or more from the main category list)</small></label><details style="position:relative"><summary style="cursor:pointer;border:1px solid #D1D5DB;border-radius:8px;padding:8px 10px;background:#fff;font-size:13px;list-style:none">Choose categories ▾</summary><div style="position:absolute;z-index:20;background:#fff;border:1px solid #E5E7EB;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.08);padding:8px;max-height:260px;overflow:auto;min-width:240px">@foreach($catNames as $__cn)<label style="display:flex;gap:8px;align-items:center;padding:3px 2px;font-size:13px;font-weight:400"><input type="checkbox" name="source_categories[]" value="{{ $__cn }}" style="width:auto" {{ in_array($__cn, array_map('trim', explode(',', (string)($s->source_category ?? ''))), true) ? 'checked' : '' }}> {{ $__cn }}</label>@endforeach</div></details>source_category ?? '') }}" maxlength="160" placeholder="e.g. Fashion">
<label style="display:flex;gap:8px;align-items:center;margin-top:10px"><input type="checkbox" name="active" value="1" style="width:auto" {{ $s->active ? 'checked' : '' }}> Active</label>
<div style="margin-top:14px;display:flex;gap:8px"><button>Save</button><a class="btn g" href="/admin/stores">Cancel</a></div></form>
@endsection
