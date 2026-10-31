@extends('store.layout')
@section('main')
<div class="w" style="padding-top:14px;padding-bottom:40px">
<div style="font-size:13px;font-weight:600;margin-bottom:12px">@if($t)Search results for “{{ $t }}” <span style="color:#6B7280;font-weight:400">({{ $items->total() }} products)</span>@else Search products @endif</div>
@if($t)
<div class="gr" style="gap:12px">@forelse($items as $q)@include('store.card', ['fd'=>true, 'cls'=>'s170'])@empty<p style="grid-column:1/-1;font-size:13px">No products found. Try another name or message us on WhatsApp.</p>@endforelse</div>
@include('store.pager', ['pg'=>$items])
@endif
</div>
@endsection
