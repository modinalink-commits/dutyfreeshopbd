@extends('layout')@section('title','Category Banners')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Category Banners</h1><span style="font-size:13px;color:#6B7280">One image slider for each category. It shows at the top of that category page. Best size: 1600 x 400 px (mobile image optional, 800 x 400 px).</span></div></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<div class="card" style="display:flex;gap:8px;flex-wrap:wrap">@foreach($cats as $c)<a class="btn {{ $c->id==$cid ? '' : 'g' }}" href="/admin/category-banners?cat={{ $c->id }}">{{ $c->name }}@if(($counts[$c->id] ?? 0) > 0) · {{ $counts[$c->id] }}@endif</a>@endforeach</div>
<form class="card" method="post" action="/admin/category-banners" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">@csrf<input type="hidden" name="category_id" value="{{ $cid }}">
<div style="flex:1;min-width:220px"><label>Banner image (required)</label><input type="file" name="image" accept="image/*" required></div>
<div style="flex:1;min-width:220px"><label>Mobile image (optional)</label><input type="file" name="mobile_image" accept="image/*"></div>
<div style="flex:1;min-width:200px"><label>Link when clicked (optional)</label><input name="link" placeholder="/perfume-and-atar/ or https://..." maxlength="300"></div>
<div style="flex:1;min-width:160px"><label>Alt text (optional)</label><input name="alt" maxlength="160"></div>
<button>Add slide</button></form>
<div class="card"><b>{{ $rows->count() }} slide{{ $rows->count()==1 ? '' : 's' }} in this category</b>
@forelse($rows as $b)
<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;border-top:1px solid #EEE;padding:10px 0"><img src="{{ $b->image }}" alt="" style="width:220px;max-width:100%;height:56px;object-fit:cover;border-radius:8px;border:1px solid #EEE{{ $b->active ? '' : ';opacity:.4' }}">
<div style="flex:1;min-width:160px;font-size:13px">{{ $b->link ?: 'No link' }}<br><small style="color:#6B7280">{{ $b->active ? 'Showing' : 'Hidden' }}@if($b->mobile_image) · has mobile image @endif</small></div>
<form method="post" action="/admin/category-banners/{{ $b->id }}/move">@csrf<input type="hidden" name="dir" value="up"><button class="btn g" title="Move earlier">↑</button></form>
<form method="post" action="/admin/category-banners/{{ $b->id }}/move">@csrf<input type="hidden" name="dir" value="down"><button class="btn g" title="Move later">↓</button></form>
<form method="post" action="/admin/category-banners/{{ $b->id }}/toggle">@csrf<button class="btn g">{{ $b->active ? 'Hide' : 'Show' }}</button></form>
<form method="post" action="/admin/category-banners/{{ $b->id }}/delete" onsubmit="return confirm('Delete this slide?')">@csrf<button class="btn g" style="color:#B91C1C">Delete</button></form></div>
@empty<p style="color:#6B7280">No slides yet for this category. Add the first one above.</p>@endforelse</div>
@endsection
