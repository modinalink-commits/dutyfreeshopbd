@extends('layout')@section('title','Product')
@section('body')<h1>{{ $p ? 'Edit product' : 'Add product' }}</h1>
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<form class="card" method="post" action="{{ $p ? '/admin/products/'.$p->id : '/admin/products' }}" style="max-width:860px">@csrf
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0 16px">
<div><label>Name *</label><input name="name" value="{{ old('name',$p->name??'') }}" required></div>
<div><label>Code / SKU</label><input name="sku" value="{{ old('sku',$p->sku??'') }}"></div>
<div><label>Barcode</label><input name="barcode" value="{{ old('barcode',$p->barcode??'') }}"></div>
<div><label>Category &gt; Sub &gt; Sub sub</label><select name="category_id"><option value="">-</option>@foreach($cats as $c)<option value="{{ $c->id }}" {{ old('category_id',$p->category_id??'')==$c->id?'selected':'' }}>{{ $c->name }}</option>@endforeach</select></div>
<div><label>Brand</label><select name="brand_id"><option value="">-</option>@foreach($brands as $b)<option value="{{ $b->id }}" {{ old('brand_id',$p->brand_id??'')==$b->id?'selected':'' }}>{{ $b->name }}</option>@endforeach</select></div>
<div><label>Attribute / size</label><input name="size" value="{{ old('size',$p->size??'') }}" placeholder="e.g. 100 ml"></div>
<div><label>Cost price (TK)</label><input name="cost" type="number" step="0.01" min="0" value="{{ old('cost',$p->cost??0) }}"></div>
<div><label>Sale price (TK) *</label><input name="price" type="number" step="0.01" min="0" value="{{ old('price',$p->price??'') }}" required></div>
<div><label>Offer price (TK)</label><input name="offer_price" type="number" step="0.01" min="0" value="{{ old('offer_price',$p->offer_price??'') }}"></div>
<div><label>Stock *</label><input name="stock" type="number" min="0" value="{{ old('stock',$p->stock??0) }}" required></div>
<div><label>Compare / old price (TK)</label><input name="compare_price" type="number" step="0.01" min="0" value="{{ old('compare_price',$p->compare_price??'') }}" placeholder="shown struck-through"></div>
</div>
<label>Product image</label><input name="image" value="{{ old('image',$p->image??'') }}" placeholder="Image URL, or upload below"><div style="margin-top:8px;display:flex;gap:10px;align-items:center"><input type="file" accept="image/*" style="width:auto" onchange="upl(this)"><span class="upm" style="font-size:12px;color:#6B7280">Choose a file to upload (jpg, png, webp - max 8 MB)</span></div><img class="upv" src="{{ old('image',$p->image??'') }}" alt="" style="max-width:160px;margin-top:8px;border-radius:8px;{{ old('image',$p->image??'') ? '' : 'display:none' }}">
<label>Description</label><textarea name="description" rows="6">{{ old('description',$p->description??'') }}</textarea>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0 16px">
<div><label>URL slug</label><input name="slug" value="{{ old('slug',$p->slug??'') }}" placeholder="auto from name"></div>
<div><label>SEO title</label><input name="meta_title" value="{{ old('meta_title',$p->meta_title??'') }}"></div></div>
<label>SEO meta description</label><textarea name="meta_description" rows="2">{{ old('meta_description',$p->meta_description??'') }}</textarea>
<p style="font-size:12px;color:#6B7280">Variants (price and stock each): <a href="/admin/m/variants">Product variants</a>. New products start unpublished; publishing sends the staff SMS.</p>
<div style="margin-top:6px"><button>Save</button> <a class="btn g" href="/admin/products">Back</a></div></form><script>function upl(i){var f=i.files[0];if(!f)return;var m=i.parentNode.querySelector('.upm');m.textContent='Uploading...';var d=new FormData();d.append('file',f);d.append('_token',document.querySelector('input[name=_token]').value);fetch('/admin/upload-image',{method:'POST',body:d,headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(j){if(!j.url)throw 0;i.closest('form').querySelector('input[name=image]').value=j.url;var v=i.closest('form').querySelector('.upv');v.src=j.url;v.style.display='block';m.textContent='Uploaded - press Save'}).catch(function(){m.textContent='Upload failed (check file type/size)'})}</script>@endsection
