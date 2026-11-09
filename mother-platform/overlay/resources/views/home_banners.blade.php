@extends('layout')@section('title','Homepage Banners')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Homepage Banners: On / Off</h1><div style="font-size:12px;color:#6B7280">Switch any banner area of the home page on or off. Press Save and the home page changes straight away. Nothing is deleted when a banner is off.</div></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<form method="post" action="/admin/home-banners">@csrf
<div class="card" style="padding:0">
@foreach($rows as $x)
<div style="display:flex;align-items:center;gap:14px;padding:14px 16px;{{ $loop->last ? '' : 'border-bottom:1px solid #EEF0F3' }}">
<label class="hbs"><input type="checkbox" name="sw[{{ $x['k'] }}]" value="1" {{ $x['on'] ? 'checked' : '' }}><i></i></label>
<div style="flex:1;min-width:0"><b>{{ $x['t'] }}</b><div style="font-size:12px;color:#6B7280">{{ $x['d'] }}</div></div>
@if($x['u'])<a href="{{ $x['u'] }}" style="font-size:12px;white-space:nowrap">{{ $x['ul'] }} →</a>@endif
</div>
@endforeach
</div>
@if(!empty($sl))
<div style="margin:18px 0 8px"><b>Uploaded slider slides (Hero Media Slider)</b><div style="font-size:12px;color:#6B7280">Each picture or video slide of the main slider. Switch any one on or off, then press Save.</div></div>
<div class="card" style="padding:0">
@foreach($sl as $x)
<div style="display:flex;align-items:center;gap:14px;padding:14px 16px;{{ $loop->last ? '' : 'border-bottom:1px solid #EEF0F3' }}">
<label class="hbs"><input type="checkbox" name="ss[{{ $x['id'] }}]" value="1" {{ $x['on'] ? 'checked' : '' }}><i></i></label>
@if($x['img'])<img src="{{ $x['img'] }}" alt="" style="width:96px;height:40px;object-fit:cover;border-radius:6px;background:#0B1220">@endif
<div style="flex:1;min-width:0"><b>Slide {{ $x['pos'] }}: {{ $x['kind'] }}{{ $x['title'] !== '' ? ' - '.$x['title'] : '' }}</b><div style="font-size:12px;color:#6B7280">{{ $x['on'] ? 'Showing on the home page now.' : 'Off: not shown on the home page.' }}</div></div>
<a href="/admin/hero-slider" style="font-size:12px;white-space:nowrap">Edit slides →</a>
</div>
@endforeach
</div>
@endif
<div style="margin-top:12px"><button class="btn" type="submit">Save</button></div>
</form>
<style>.hbs{position:relative;display:inline-block;width:46px;height:26px;flex:none}.hbs input{opacity:0;width:0;height:0}.hbs i{position:absolute;inset:0;background:#D1D5DB;border-radius:26px;transition:background .2s;cursor:pointer}.hbs i:before{content:"";position:absolute;left:3px;top:3px;width:20px;height:20px;border-radius:50%;background:#fff;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.3)}.hbs input:checked+i{background:#16A34A}.hbs input:checked+i:before{transform:translateX(20px)}</style>
@endsection
