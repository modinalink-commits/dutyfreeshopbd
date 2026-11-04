@extends('layout')
@section('title', $item['label'])
@section('content')
<div><h1>{{ $item['label'] }}</h1><p class="sub">{{ $item['group'] }}</p></div>
<div class="card pad" style="display:flex;flex-direction:column;gap:8px;max-width:640px">
  <b>This page is not built yet.</b>
  <span>It will be built to match the approved design: <b>{{ $item['design'] }}</b>.</span>
  <span class="sub">Nothing from the design is dropped — see <a href="{{ route('roadmap') }}">Build progress</a> for every screen and its status.</span>
</div>
@endsection
