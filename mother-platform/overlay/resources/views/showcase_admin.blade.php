@extends('layout')@section('title','Homepage Showcase')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Homepage Product Showcase</h1><div style="font-size:12px;color:#6B7280">Six animated product cards between "Recently ordered" and "Cigarette and Vape". Change the product, image, colour or link here; it appears on the homepage straight away.</div></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card" style="color:#991B1B">{{ $errors->first() }}</div>@endif
<form method="post" action="/admin/showcase" enctype="multipart/form-data">@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px">
@foreach($rows as $x)@php $p = $prods[$x->product_id] ?? null; @endphp
<div class="card" style="{{ $x->active ? '' : 'opacity:.6' }}">
<div style="display:flex;gap:10px;align-items:flex-start">
<div style="width:92px;height:150px;border-radius:10px;background:linear-gradient(160deg,{{ $x->theme }}66,#0f172a);display:flex;align-items:center;justify-content:center;flex:none">@if($x->image)<img src="{{ $x->image }}" alt="" style="max-width:84px;max-height:140px;object-fit:contain">@endif</div>
<div style="flex:1;min-width:0">
<b>Slot {{ $x->position }}</b> <label style="float:right;font-size:12px"><input type="checkbox" name="slots[{{ $x->id }}][active]" value="1" {{ $x->active ? 'checked' : '' }}> Active</label>
<div style="font-size:11px;color:#6B7280;margin-top:6px">Product</div>
<input type="hidden" name="slots[{{ $x->id }}][product_id]" value="{{ $x->product_id }}" class="pid">
<input type="text" class="psr" placeholder="Type to search a product…" value="{{ $p->name ?? '' }}" autocomplete="off" style="width:100%">
<div class="pls" style="font-size:12px"></div>
<div style="font-size:11px;color:#6B7280;margin-top:6px">Link (optional, overrides the product page)</div>
<input type="text" name="slots[{{ $x->id }}][link]" value="{{ $x->link }}" placeholder="/cigarette-vape/..." style="width:100%">
</div></div>
<div style="display:flex;gap:10px;align-items:center;margin-top:8px;flex-wrap:wrap">
<label style="font-size:12px">Colour <input type="color" name="slots[{{ $x->id }}][theme]" value="{{ $x->theme }}"></label>
<label style="font-size:12px">Order <input type="number" min="1" max="6" name="slots[{{ $x->id }}][position]" value="{{ $x->position }}" style="width:56px"></label>
<label style="font-size:12px">Image (transparent PNG/WebP) <input type="file" name="slots[{{ $x->id }}][image_file]" accept="image/png,image/webp,image/jpeg"></label>
</div>
<input type="hidden" name="slots[{{ $x->id }}][title]" value="{{ $x->title }}">
</div>
@endforeach
</div>
<div style="margin-top:12px"><button class="btn" type="submit">Save showcase</button></div>
</form>
<script>
document.querySelectorAll('.psr').forEach(function(inp){var box=inp.parentNode,pid=box.querySelector('.pid'),ls=box.querySelector('.pls'),t;inp.addEventListener('input',function(){clearTimeout(t);var q=inp.value.trim();if(q.length<2){ls.innerHTML='';return}t=setTimeout(function(){fetch('/admin/showcase/search?q='+encodeURIComponent(q)).then(function(r){return r.json()}).then(function(a){ls.innerHTML='';a.forEach(function(p){var b=document.createElement('div');b.textContent=p.name;b.style.cssText='padding:4px 6px;cursor:pointer;border:1px solid #E5E7EB;border-radius:6px;margin-top:3px;background:#fff';b.onclick=function(){pid.value=p.id;inp.value=p.name;ls.innerHTML=''};ls.appendChild(b)})})},250)})});
</script>
@endsection
