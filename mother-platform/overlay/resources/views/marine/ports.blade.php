@extends('layout')@section('title','Marine Ports')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Ports</h1><div style="font-size:12px;color:#6B7280">Each port's status, the short note and photo shown on <a href="{{ $site }}" target="_blank" rel="noopener">the site</a>. The partner shop name is internal and never shown on the site. Delivery charge is not set here: it is worked out per quotation from the weight of the goods and the distance.</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
<form method="post" action="/admin/marine/ports">@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px">
@foreach($ports as $k => $p)
<div class="card" data-mbox style="margin:0;display:flex;flex-direction:column;gap:8px">
<b>{{ $p['name'] }}</b>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Status<select name="p[{{ $k }}][status]">@foreach($status as $sk => $sl)<option value="{{ $sk }}" @selected($p['status']===$sk)>{{ $sl }}</option>@endforeach</select></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Note on the site (max 200 characters)<textarea name="p[{{ $k }}][note]" maxlength="200" rows="2">{{ $p['note'] }}</textarea></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Partner duty-free shop (internal only)<input name="p[{{ $k }}][shop]" maxlength="120" value="{{ $p['shop'] }}"></label>
<input type="hidden" name="p[{{ $k }}][photo]" value="{{ $p['photo'] }}">
<img src="{{ $p['photo'] }}" alt="" @if(!$p['photo']) hidden @endif style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:6px;border:1px solid #E5E7EB">
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input type="file" data-mimg accept="image/jpeg,image/png,image/webp" style="font-size:12px;max-width:210px"><button type="button" data-mclr class="btn g">Remove</button></div>
<div data-mst style="font-size:12px;color:#6B7280">Port photo (a real photo you have the right to use)</div>
</div>
@endforeach
</div>
<div style="margin-top:12px"><button class="btn">Save</button></div>
</form>
@include('marine.upload_js')
@endsection
