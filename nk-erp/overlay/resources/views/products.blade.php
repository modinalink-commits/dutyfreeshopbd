@extends('layout')
@section('title', 'Products & prices')
@section('content')
@php($admin = in_array(auth()->user()->role, ['super_admin', 'admin']))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Products &amp; prices</h1><p class="sub">What the POS and dealer orders can sell. A product without a price cannot be sold.</p></div>
  @if($admin)<a class="btn pri" href="{{ route('products.index') }}#product-form">＋ Add product</a>@endif
</header>
<div class="dt-wrap"><table class="dt">
  <thead><tr><th>Code</th><th>Product</th><th>Category</th><th>Brand</th><th>Size</th><th>Retail price</th><th>Dealer price</th><th>In stock</th><th>Status</th>@if($admin)<th class="no-sort"></th>@endif</tr></thead>
  <tbody>
  @foreach($products as $p)
    @php($av = $p->available())
    <tr>
      <td class="c">{{ $p->code }}</td><td><b>{{ $p->name }}</b>@if($p->note)<br><span class="sub">{{ $p->note }}</span>@endif</td>
      <td>{{ \App\Models\Product::CATEGORIES[$p->category] }}</td><td>{{ $p->brand?->name }}</td><td class="c">{{ $p->size_kg ? $p->size_kg.' kg' : '—' }}</td>
      <td class="r">@if($p->retail_price !== null)৳{{ number_format($p->retail_price) }}@else<span style="color:#b3261e;font-weight:600">Set price</span>@endif</td>
      <td class="r">@if($p->dealer_price !== null)৳{{ number_format($p->dealer_price) }}@else<span class="sub">same as retail</span>@endif</td>
      <td class="c">{{ $av === null ? '—' : number_format($av) }}</td>
      <td class="c"><span class="pill {{ $p->is_active ? 'st-delivered' : '' }}">{{ $p->is_active ? 'Active' : 'Off' }}</span></td>
      @if($admin)<td class="c nw"><a class="btn sm" href="{{ route('products.index', ['edit' => $p->id]) }}#product-form">Edit</a>
        @if($p->category === 'accessory')
        <form method="post" action="{{ route('products.stock', $p) }}" style="display:inline-flex;gap:4px;margin-top:4px">@csrf<input type="number" name="qty" placeholder="+/−" style="width:70px;min-height:30px" required><input type="text" name="reference" placeholder="Ref" style="width:80px;min-height:30px" required><button class="btn sm" type="submit">Stock</button></form>
        @endif
      </td>@endif
    </tr>
  @endforeach
  </tbody>
</table></div>
@if($admin)
<form id="product-form" class="card pad" method="post" action="{{ route('products.save') }}" style="display:flex;flex-direction:column;gap:12px;max-width:760px">
  @csrf
  <h2 style="margin:0;font-size:15px">{{ $edit ? 'Edit '.$edit->name : 'Add product' }}</h2>
  @if($edit)<input type="hidden" name="id" value="{{ $edit->id }}">@endif
  <div class="grid2">
    <label class="f">Name<input type="text" name="name" value="{{ old('name', $edit?->name) }}" required></label>
    <label class="f">Category<select name="category">@foreach(\App\Models\Product::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected(old('category', $edit?->category) === $k)>{{ $l }}</option>@endforeach</select></label>
    <label class="f">Brand (cylinders only)<select name="brand_id"><option value="">—</option>@foreach($brands as $b)<option value="{{ $b->id }}" @selected(old('brand_id', $edit?->brand_id) == $b->id)>{{ $b->name }}</option>@endforeach</select></label>
    <label class="f">Size (cylinders only)<select name="size_kg"><option value="">—</option>@foreach(\App\Models\Brand::SIZES as $s)<option value="{{ $s }}" @selected(old('size_kg', $edit?->size_kg) == $s)>{{ $s }} kg</option>@endforeach</select></label>
    <label class="f">Retail price (৳)<input type="number" step="0.01" min="0" name="retail_price" value="{{ old('retail_price', $edit?->retail_price) }}"></label>
    <label class="f">Dealer price (৳, optional)<input type="number" step="0.01" min="0" name="dealer_price" value="{{ old('dealer_price', $edit?->dealer_price) }}"></label>
    <label class="f">Note<input type="text" name="note" value="{{ old('note', $edit?->note) }}"></label>
    <label class="f">Status<select name="is_active"><option value="1" @selected(old('is_active', $edit?->is_active ?? true))>Active</option><option value="0" @selected(! old('is_active', $edit?->is_active ?? true))>Off</option></select></label>
  </div>
  <span class="sub">Every price change is written to the Activity log with the old and new price.</span>
  <div style="display:flex;gap:8px"><button class="btn pri" type="submit">Save product</button>@if($edit)<a class="btn" href="{{ route('products.index') }}">Cancel</a>@endif</div>
</form>
@endif
@endsection
