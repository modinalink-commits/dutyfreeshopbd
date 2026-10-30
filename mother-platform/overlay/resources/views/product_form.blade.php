@extends('layout')@section('title','Product')
@section('body')<h1>{{ $p ? 'Edit product' : 'Add product' }}</h1>
<form class="card" method="post" action="{{ $p ? '/admin/products/'.$p->id : '/admin/products' }}" style="max-width:640px">@csrf
<label>Name</label><input name="name" value="{{ old('name',$p->name??'') }}" required>
<label>SKU / product code</label><input name="sku" value="{{ old('sku',$p->sku??'') }}">
<label>Category</label><select name="category_id"><option value="">-</option>@foreach($cats as $c)<option value="{{ $c->id }}" {{ old('category_id',$p->category_id??'')==$c->id?'selected':'' }}>{{ $c->name }}</option>@endforeach</select>
<label>Brand</label><select name="brand_id"><option value="">-</option>@foreach($brands as $b)<option value="{{ $b->id }}" {{ old('brand_id',$p->brand_id??'')==$b->id?'selected':'' }}>{{ $b->name }}</option>@endforeach</select>
<label>Price (TK)</label><input name="price" type="number" step="0.01" min="0" value="{{ old('price',$p->price??'') }}" required>
<label>Cost (TK)</label><input name="cost" type="number" step="0.01" min="0" value="{{ old('cost',$p->cost??0) }}">
<label>Stock</label><input name="stock" type="number" min="0" value="{{ old('stock',$p->stock??0) }}" required>
@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
<div style="margin-top:14px"><button>Save</button> <a class="btn g" href="/admin/products">Back</a></div></form>@endsection
