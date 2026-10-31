@extends('store.layout')
@section('main')
<style>.bg{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}.bg a{display:block;border:1.25px solid #fdc2a5;border-radius:14px;background:#fff;overflow:hidden;text-decoration:none;color:#0B1220}.bg a:hover{border-color:#FF5A00}.bg .im{aspect-ratio:16/9;background:linear-gradient(120deg,#0B1220,#FF5A00);display:block}.bg .im img{width:100%;height:100%;object-fit:cover;display:block}.bg .tx{padding:14px 16px}.bg h2{font-size:17px;margin:0 0 6px;line-height:1.35}.bg p{margin:0;font-size:13px;color:#4B5563;line-height:1.6}.bg time{font-size:12px;color:#6B7280;display:block;margin-bottom:6px}</style>
<div class="w" style="padding-top:30px;padding-bottom:56px">
<nav class="bc" style="font-size:12px;margin-bottom:14px"><a href="/">Home</a> › <b>Blog</b></nav>
<h1 style="font-size:30px;margin:0 0 18px">Blog</h1>
@if($posts->count())<div class="bg">@foreach($posts as $b)<a href="/blog/{{ $b->slug }}"><span class="im">@if($b->featured_image)<img src="{{ $b->featured_image }}" alt="{{ $b->title }}" loading="lazy">@endif</span><span class="tx">@if($b->published_at)<time>{{ \Carbon\Carbon::parse($b->published_at)->format('d M Y') }}</time>@endif<h2>{{ $b->title }}</h2><p>{{ \Illuminate\Support\Str::limit($b->excerpt ?: strip_tags(\App\Support\Cms::render($b->body,$s)), 140) }}</p></span></a>@endforeach</div>
@else<p>No posts yet.</p>@endif
</div>
@endsection
