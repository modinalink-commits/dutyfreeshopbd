@extends('layout')
@section('title', 'Company settings')
@section('content')
<div><h1>Company settings</h1><p class="sub">Leave a field empty if you do not have the information yet. Only the Super Admin can save changes.</p></div>
<form class="card pad" method="post" action="{{ route('settings.update') }}" style="display:flex;flex-direction:column;gap:16px">
  @csrf
  <div class="grid2">
  @foreach($fields as $key => $label)
    <label class="f" @if(in_array($key, ['address_en','address_bn'])) style="grid-column:1/-1" @endif>{{ $label }}<input type="text" name="{{ $key }}" value="{{ old($key, $values[$key] ?? '') }}" @disabled(! auth()->user()->isSuperAdmin())></label>
  @endforeach
  </div>
  @if(auth()->user()->isSuperAdmin())<div><button class="btn pri" type="submit">Save settings</button></div>@endif
</form>
@endsection
