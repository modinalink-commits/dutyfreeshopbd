@extends('store.layout')
@section('main')
@php $body = \App\Support\Cms::render($p->body, $s); @endphp
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'BlogPosting','headline'=>$p->title,'datePublished'=>$p->published_at ? \Carbon\Carbon::parse($p->published_at)->toIso8601String() : null,'image'=>$ogimg ?? null,'mainEntityOfPage'=>$canon,'description'=>$desc], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
<style>.cms{max-width:860px;margin:0 auto;font-size:15px;line-height:1.85;color:#1F2937}.cms h1{font-size:30px;margin:0 0 10px;line-height:1.3;color:#0B1220}.cms h2{font-size:21px;margin:26px 0 8px;padding-left:12px;border-left:4px solid #FF5A00;color:#0B1220}.cms h3{font-size:17px;margin:18px 0 4px}.cms p{margin:0 0 12px}.cms ul,.cms ol{margin:0 0 14px;padding-left:22px}.cms li{margin-bottom:4px}.cms a{color:#FF5A00}.cms img{max-width:100%;height:auto;border-radius:12px}.cms time{font-size:12px;color:#6B7280;display:block;margin-bottom:12px}.cms .fi{width:100%;aspect-ratio:16/9;object-fit:cover;margin-bottom:16px}</style>
<div class="w" style="padding-top:30px;padding-bottom:56px">
<nav class="bc" style="font-size:12px;margin-bottom:14px"><a href="/">Home</a> › <a href="/blog">Blog</a> › <b>{{ \Illuminate\Support\Str::limit($p->title, 50) }}</b></nav>
<article class="cms">
<h1>{{ $p->title }}</h1>
@if($p->published_at)<time datetime="{{ \Carbon\Carbon::parse($p->published_at)->toDateString() }}">{{ \Carbon\Carbon::parse($p->published_at)->format('d M Y') }}</time>@endif
@if($p->featured_image)<img class="fi" src="{{ $p->featured_image }}" alt="{{ $p->title }}">@endif
{!! $body !!}
</article>
</div>
@endsection
