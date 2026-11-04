@extends('layout')
@section('title', 'Cylinder brands')
@section('content')
@php($canEdit = in_array(auth()->user()->role, ['super_admin', 'admin']))
@php($canMove = in_array(auth()->user()->role, ['super_admin', 'admin', 'manager']))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Cylinder brands</h1><p class="sub">Every brand keeps its own full and empty stock · brands not sold are kept for empty tracking</p></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn" href="{{ route('brands.report', ['type' => 'purchase']) }}">Purchase report</a>
    <a class="btn" href="{{ route('brands.report', ['type' => 'sales']) }}">Sales report</a>
    <a class="btn" href="{{ route('brands.report', ['type' => 'empty']) }}">Empty collection report</a>
    <a class="btn" href="{{ route('brands.report', ['type' => 'stock']) }}">Stock by brand</a>
  </div>
  @if($canEdit)<a class="btn pri" href="{{ route('brands.index') }}#brand-form">＋ Add brand</a>@endif
</header>
<section class="kpis">
  <div class="card kpi" style="background:#e6eeff"><span>Brands</span><b style="color:#1d4ed8">{{ $stats['count'] }}</b></div>
  <div class="card kpi" style="background:#e3f6ec"><span>Available for sale</span><b style="color:#0f6b3c">{{ $stats['forSale'] }}</b></div>
  <div class="card kpi" style="background:#def5f7"><span>Empty tracking only</span><b style="color:#0b6670">{{ $stats['trackOnly'] }}</b></div>
  <div class="card kpi"><span>Other-brand empties held</span><b>{{ number_format($stats['otherEmpty']) }}</b></div>
  <div class="card kpi" style="background:#efe7fb"><span>Exchanges to approve</span><b style="color:#5b2fa8">{{ $stats['waiting'] }}</b></div>
</section>
<section class="card">
  <form method="get" class="pad" style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
    <h2 style="margin:0;font-size:15px;flex-grow:1">Brand list</h2>
    <input type="search" name="q" value="{{ $q }}" placeholder="Search brand" aria-label="Search brand" style="max-width:200px;border-radius:999px">
    <div class="tabs">
      <a class="{{ ! $filter ? 'on' : '' }}" href="{{ route('brands.index', ['q' => $q]) }}">All</a>
      <a class="{{ $filter === 'active' ? 'on' : '' }}" href="{{ route('brands.index', ['q' => $q, 'f' => 'active']) }}">Active</a>
      <a class="{{ $filter === 'inactive' ? 'on' : '' }}" href="{{ route('brands.index', ['q' => $q, 'f' => 'inactive']) }}">Inactive</a>
    </div>
  </form>
  <div class="dt-wrap"><table class="dt">
    <thead><tr><th>Brand ID</th><th>Brand</th><th>Company name</th><th>Sizes</th><th>Status</th><th>For sale</th><th>Empty collection</th><th>Full stock</th><th>Empty stock</th><th>Remarks</th>@if($canEdit)<th></th>@endif</tr></thead>
    <tbody>
    @foreach($brands as $b)
      <tr @if($b->for_sale) style="background:#f5f8ff" @endif>
        <td style="color:var(--muted)">{{ $b->code }}</td><td><b>{{ $b->name }}</b></td>
        <td>@if($b->company_name){{ $b->company_name }}@else<i style="color:var(--muted)">Not entered</i>@endif</td>
        <td style="white-space:nowrap">{{ implode(' · ', $b->sizes ?? []) }} kg</td>
        <td><span class="pill {{ $b->is_active ? 'st-delivered' : '' }}">{{ $b->is_active ? 'Active' : 'Inactive' }}</span></td>
        <td>{{ $b->for_sale ? 'Yes' : 'No' }}</td><td>{{ $b->empty_collection ? 'Yes' : 'No' }}</td>
        <td class="c" style="font-weight:700">{{ number_format((int) ($totals[$b->id]->f ?? 0)) }}</td>
        <td class="c">{{ number_format((int) ($totals[$b->id]->e ?? 0)) }}</td>
        <td style="color:var(--muted)">{{ $b->remarks }}</td>
        @if($canEdit)<td><a class="btn sm" href="{{ route('brands.index', ['edit' => $b->id]) }}#brand-form">Edit</a></td>@endif
      </tr>
    @endforeach
    </tbody>
  </table></div>
</section>
<div class="grid2" style="align-items:start">
  <section class="card pad" style="display:flex;flex-direction:column;gap:10px">
    <h2 style="margin:0;font-size:15px">Brand exchange &amp; transfer — needs approval</h2>
    <span class="sub">An empty of one brand never goes into another brand's stock by itself. Each exchange is its own transaction with a reason and an approver.</span>
    @forelse($exchanges as $x)
      <div style="border:1px solid var(--line);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:6px">
        <div style="display:flex;align-items:center"><b style="flex-grow:1">{{ $x->ref_no }}@if($x->party) · {{ $x->party }}@endif</b><span class="pill" style="background:#efe7fb;color:#5b2fa8">Waiting</span></div>
        <span>{{ $x->qty }} × {{ $x->fromBrand->name }} {{ $x->size_kg }} kg empty → count as {{ $x->qty }} × {{ $x->toBrand->name }} {{ $x->size_kg }} kg empty</span>
        <span class="sub">{{ $x->reason }} · requested by {{ $x->requester?->name }} · {{ $x->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }}</span>
        @if($canMove)
        <div style="display:flex;gap:6px">
          <form method="post" action="{{ route('exchanges.decide', $x) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn sm pri" type="submit">Approve</button></form>
          <form method="post" action="{{ route('exchanges.decide', $x) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn sm" type="submit">Reject</button></form>
        </div>
        @endif
      </div>
    @empty
      <span class="sub">Nothing waiting.</span>
    @endforelse
    @if($canMove)
    <details style="margin-top:6px"><summary style="cursor:pointer;font-weight:600">＋ Request an exchange</summary>
      <form method="post" action="{{ route('exchanges.store') }}" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px">
        @csrf
        <label class="f">Empty given (brand)<select name="from_brand_id">@foreach($allBrands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
        <label class="f">Count as (brand)<select name="to_brand_id">@foreach($allBrands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
        <label class="f">Size<select name="size_kg">@foreach(\App\Models\Brand::SIZES as $s)<option value="{{ $s }}">{{ $s }} kg</option>@endforeach</select></label>
        <label class="f">Quantity<input type="number" name="qty" min="1" value="1" required></label>
        <label class="f">Customer / party<input type="text" name="party" placeholder="Optional"></label>
        <label class="f">Reason<input type="text" name="reason" required></label>
        <div style="grid-column:1/-1"><button class="btn pri" type="submit">Send for approval</button></div>
      </form>
    </details>
    @endif
  </section>
  @if($canEdit)
  <form id="brand-form" class="card pad" method="post" action="{{ route('brands.save') }}" style="display:flex;flex-direction:column;gap:12px">
    @csrf
    <h2 style="margin:0;font-size:15px">{{ $edit ? 'Edit brand' : 'Add brand' }}</h2>
    @if($edit)<input type="hidden" name="id" value="{{ $edit->id }}">@endif
    <div class="grid2">
      <label class="f">Brand ID<input type="text" value="{{ $edit?->code ?? $nextCode.' (automatic)' }}" disabled></label>
      <label class="f">Brand name<input type="text" name="name" value="{{ old('name', $edit?->name) }}" required></label>
    </div>
    <label class="f">Company name<input type="text" name="company_name" value="{{ old('company_name', $edit?->company_name) }}" placeholder="Company that owns the brand"></label>
    <div class="f">Cylinder sizes
      <div style="display:flex;gap:14px">@foreach(\App\Models\Brand::SIZES as $s)<label style="display:flex;align-items:center;gap:6px;font-weight:600;color:var(--ink)"><input type="checkbox" name="sizes[]" value="{{ $s }}" style="width:auto;min-height:0" @checked(in_array($s, old('sizes', $edit?->sizes ?? [12])))> {{ $s }} kg</label>@endforeach</div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px">
      <label class="f">Status<select name="is_active"><option value="1" @selected(old('is_active', $edit?->is_active ? 1 : 0) == 1)>Active</option><option value="0" @selected(old('is_active', $edit?->is_active ? 1 : 0) == 0)>Inactive</option></select></label>
      <label class="f">For sale<select name="for_sale"><option value="0" @selected(! old('for_sale', $edit?->for_sale))>No</option><option value="1" @selected(old('for_sale', $edit?->for_sale))>Yes</option></select></label>
      <label class="f">Empty collection<select name="empty_collection"><option value="1" @selected(old('empty_collection', $edit?->empty_collection ?? true))>Yes</option><option value="0" @selected(! old('empty_collection', $edit?->empty_collection ?? true))>No</option></select></label>
    </div>
    <label class="f">Remarks<input type="text" name="remarks" value="{{ old('remarks', $edit?->remarks) }}" placeholder="Optional"></label>
    <span class="sub">Set Active only after checking that the brand is really being supplied.</span>
    <div style="display:flex;gap:8px"><button class="btn pri" type="submit">Save brand</button>@if($edit)<a class="btn" href="{{ route('brands.index') }}">Cancel</a>@endif</div>
  </form>
  @endif
</div>
@endsection
