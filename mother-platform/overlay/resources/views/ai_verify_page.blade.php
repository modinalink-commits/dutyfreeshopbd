@extends('layout')@section('title','AI Verify #'.$id)
@section('body')
<div style="display:block;flex:1 1 100%;width:100%;min-width:0;max-width:100%">
<div data-aipage="{{ $id }}" style="padding:6px 14px"><a href="/admin/orders/{{ $id }}" style="font-size:12.5px;font-weight:700">← Order #{{ $id }}</a> &nbsp; <a href="/admin/orders" style="font-size:12.5px">Order list</a></div>
@include('ai_verify_ui')
</div>
@endsection
