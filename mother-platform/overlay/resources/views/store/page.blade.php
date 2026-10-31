@extends('store.layout')
@section('main')
<div class="w" style="padding-top:30px;padding-bottom:50px;font-size:12px;line-height:1.8">
<div style="text-align:center;font-size:18px;font-weight:600;margin-bottom:24px">{{ $page->title }}</div>
<h1 style="font-size:26px;margin:0 0 6px">{{ $page->title }}</h1>
@if($page->body)
<div style="white-space:pre-line">{{ $page->body }}</div>
@else
<p style="margin:0 0 12px">DutyFree Shop BD — original imported products, delivered all over Bangladesh with cash on delivery.</p>
<h3 style="font-size:20px;margin:14px 0 4px">Contact</h3><p style="margin:0">Call: <a href="tel:{{ $s['call_number'] }}" style="color:#FF5A00;font-weight:600">{{ $s['call_number'] }}</a><br>WhatsApp: <a href="https://wa.me/{{ $wa }}" style="color:#FF5A00;font-weight:600" rel="noopener">{{ $s['whatsapp_number'] }}</a><br>Website: {{ $s['site_address'] }}</p>
@endif
</div>
@endsection
