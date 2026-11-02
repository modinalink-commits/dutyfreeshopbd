@extends('layout')@section('title','Payment Methods')
@section('body')
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<div style="display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap"><h1 style="margin:0;flex:1">Manual Payment Methods</h1><a class="btn g" href="/admin/orders">Orders</a></div>
<div class="card" style="margin-bottom:12px"><b>{{ $pending }}</b> orders are waiting for payment verification. Rule: every manual payment must be approved on WhatsApp first. The customer sends the payment proof on WhatsApp, then you open the order here and press Verify. Until then the order shows <b>Payment Verification Pending</b>.</div>
<div class="card" style="max-width:520px;margin-bottom:12px">WhatsApp number for payment approval: <b>{{ $wa }}</b><br><small>This number is the shop WhatsApp number. Change it once in <a href="/admin/site">Site settings &gt; Business contact</a>, and it updates everywhere.</small></div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
@foreach($rows as $m)
<form class="card" method="post" enctype="multipart/form-data" action="/admin/payment-methods/{{ $m->id }}">@csrf
<div style="display:flex;gap:10px;align-items:center;margin-bottom:6px">@if($lg = \App\Support\Brand::logoUrl($m))<img src="{{ $lg }}" alt="" style="height:30px;max-width:110px;object-fit:contain">@endif<h3 style="margin:0">{{ $m->title }}</h3></div>
<input type="hidden" name="kind" value="{{ $m->kind }}">
<label>Name shown</label><input name="title" value="{{ $m->title }}" required>
<label>Short description (shown under the name)</label><input name="description" value="{{ $m->description }}" maxlength="300">
<label>Logo (PNG/JPG/WebP, max 1 MB — updates checkout, orders, invoice)</label><input type="file" name="logo_file" accept="image/*">@if(\App\Support\Brand::logoUrl($m))<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="remove_logo" value="1" style="width:auto"> Remove logo</label>@endif
@if(!in_array($m->kind,['cod','shurjopay']))<label>Account name</label><input name="account_name" value="{{ $m->account_name }}">
<label>{{ $m->kind==='bank' ? 'Account number' : 'Number' }}</label><input name="account_no" value="{{ $m->account_no }}">@endif
@if($m->kind==='bank')<label>Branch</label><input name="branch" value="{{ $m->branch }}"><label>Routing number</label><input name="routing" value="{{ $m->routing }}"><label>SWIFT</label><input name="swift" value="{{ $m->swift }}">@endif
@if(!in_array($m->kind,['cod','shurjopay']))<label>Extra instruction (optional)</label><input name="instruction" value="{{ $m->instruction }}">@endif
<label>Display order (smaller = higher on checkout)</label><input name="position" type="number" value="{{ $m->position }}">
<label style="display:flex;gap:8px;align-items:center;margin-top:10px"><input type="checkbox" name="active" value="1" {{ $m->active?'checked':'' }} style="width:auto"> Show on checkout</label><div style="margin-top:10px"><button>Save</button></div></form>
@endforeach
<form class="card" method="post" action="/admin/payment-methods">@csrf<h3 style="margin-top:0">Add another</h3>
<label>Type</label><select name="kind"><option value="bank">Bank</option><option value="bkash">bKash Personal</option><option value="nagad">Nagad Personal</option></select>
<label>Name shown</label><input name="title" required><label>Account name</label><input name="account_name"><label>Account number or mobile number</label><input name="account_no"><label>Branch</label><input name="branch"><label>Routing number</label><input name="routing"><label>SWIFT</label><input name="swift">
<input type="hidden" name="active" value="1"><div style="margin-top:10px"><button>Add</button></div></form>
</div>
@endsection
