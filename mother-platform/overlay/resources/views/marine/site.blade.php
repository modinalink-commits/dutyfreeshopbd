@extends('layout')@section('title','Marine Site Content')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Site Content &amp; Banners</h1><div style="font-size:12px;color:#6B7280"><a href="{{ $site }}" target="_blank" rel="noopener">মেরিন সাইটের</a> ওপরের ফিতা, হোমপেজের শিরোনাম ও ব্যানার। ঘর ফাঁকা রাখলে সাইটের নিজস্ব লেখা দেখাবে। সাইটের ভাষা ইংরেজি।</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
<form method="post" action="/admin/marine/site">@csrf
<div class="card" style="display:flex;flex-direction:column;gap:12px"><b>Top strip and home headline</b>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Top strip (সব পাতার ওপরে, সর্বোচ্চ ১৬০ অক্ষর)<input name="strip" maxlength="160" value="{{ $c['strip'] }}" placeholder="Launching soon at Chattogram · Request access to be among the first vessels served"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Home headline (সর্বোচ্চ ৯০ অক্ষর)<input name="hero_title" maxlength="90" value="{{ $c['hero_title'] }}" placeholder="Ship Supplies at Bangladesh Ports"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Home sub-headline (সর্বোচ্চ ৩০০ অক্ষর)<textarea name="hero_sub" maxlength="300" rows="2" placeholder="Duty-free stores for your vessel and your crew, quoted in USD and delivered alongside or at anchorage.">{{ $c['hero_sub'] }}</textarea></label>
</div>
<div class="card"><b>Home banners</b><div style="font-size:12px;color:#6B7280;margin:2px 0 10px">হোমপেজে "How it works"-এর ওপরে দেখায়। সর্বোচ্চ ৪টি। ছবির মাপ: ১২০০×৫০০ ভালো। তামাক বা অ্যালকোহলের ছবি এখানে দেবেন না, কারণ এগুলো পাবলিক।</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px">
@foreach($banners as $i => $b)
<div data-mbox style="border:1px solid #E5E7EB;border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:8px">
<div style="display:flex;justify-content:space-between;align-items:center"><b style="font-size:13px">Banner {{ $i + 1 }}</b><label style="font-size:13px;display:flex;gap:6px;align-items:center"><input type="checkbox" name="b[{{ $i }}][on]" value="1" @checked($b['on'])> Show</label></div>
<input type="hidden" name="b[{{ $i }}][image]" value="{{ $b['image'] }}">
<img src="{{ $b['image'] }}" alt="" @if(!$b['image']) hidden @endif style="width:100%;aspect-ratio:12/5;object-fit:cover;border-radius:6px;border:1px solid #E5E7EB">
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input type="file" data-mimg accept="image/jpeg,image/png,image/webp" style="font-size:12px;max-width:210px"><button type="button" data-mclr class="btn g">Remove</button></div>
<div data-mst style="font-size:12px;color:#6B7280"></div>
<input name="b[{{ $i }}][title]" maxlength="80" value="{{ $b['title'] }}" placeholder="Title">
<input name="b[{{ $i }}][text]" maxlength="160" value="{{ $b['text'] }}" placeholder="Short text">
<input name="b[{{ $i }}][link]" maxlength="300" value="{{ $b['link'] }}" placeholder="Link, e.g. /request-access.html">
</div>
@endforeach
</div></div>
<div><button class="btn">Save</button></div>
</form>
@include('marine.upload_js')
@endsection
