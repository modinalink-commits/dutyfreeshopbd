@extends('store.layout')
@section('main')
@php
  $isContact = $page->slug === 'contact-us';
  $mail = $s['contact_email'] ?? ''; $addr = $s['contact_address'] ?? ''; $hrs = $s['business_hours'] ?? ''; $mob = ($s['contact_mobile'] ?? '') ?: '';
  $map = \App\Support\Cms::mapEmbed($s['contact_map'] ?? '');
  $body = \App\Support\Cms::render($page->body, $s);
@endphp
<style>.cms{max-width:860px;margin:0 auto;font-size:15px;line-height:1.85;color:#374151}.cms h1{font-size:30px;margin:0 0 14px;color:#0B1220}.cms h2{font-size:21px;margin:26px 0 8px;padding-left:12px;border-left:4px solid #FF5A00;color:#0B1220}.cms h3{font-size:17px;margin:18px 0 4px}.cms p{margin:0 0 12px}.cms ul,.cms ol{margin:0 0 14px;padding-left:22px}.cms li{margin-bottom:4px}.cms a{color:#FF5A00}.cms img{max-width:100%;height:auto;border-radius:12px}.cc{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:18px 0}.cc div{border:1.25px solid #fdc2a5;border-radius:12px;padding:14px 16px;background:#fff}.cc b{display:block;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6B7280;margin-bottom:4px}.cc a,.cc span{font-size:15px;font-weight:600;color:#0B1220;text-decoration:none;word-break:break-word}.cmap{border:0;width:100%;height:320px;border-radius:14px;margin-top:6px}</style>
<div class="w" style="padding-top:30px;padding-bottom:56px">
<nav class="bc" style="font-size:12px;margin-bottom:14px"><a href="/">Home</a> › <b>{{ $page->title }}</b></nav>
<div class="cms">
<h1>{{ $page->title }}</h1>
@if($isContact)
<div class="cc">
<div><b>Hotline</b><a href="{{ \App\Support\Biz::telUrl(\App\Support\Biz::hotline()) }}">{{ \App\Support\Biz::hotline() }}</a></div>
<div><b>Mobile</b><a href="{{ \App\Support\Biz::telUrl(\App\Support\Biz::mobile()) }}">{{ \App\Support\Biz::mobile() }}</a></div>
<div><b>WhatsApp</b><a href="{{ \App\Support\Biz::waUrl() }}" rel="noopener">{{ \App\Support\Biz::whatsapp() }}</a></div>
<div><b>Email</b><a href="mailto:{{ \App\Support\Biz::email() }}">{{ \App\Support\Biz::email() }}</a></div>
<div><b>Business Address</b><span>{{ \App\Support\Biz::address() }}</span></div>
<div><b>Business Hours</b><span>{{ \App\Support\Biz::hours() }}</span></div>
</div>
@endif
{!! $body !!}
@if($isContact && $map)<iframe class="cmap" src="{{ $map }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe>@endif
</div>
</div>
@endsection
