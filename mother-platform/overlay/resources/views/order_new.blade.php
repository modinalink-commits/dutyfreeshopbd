@extends('layout')@section('title','Create Order')
@section('body')<h1>Create Order</h1><form class="card" method="post" action="/admin/orders" style="max-width:640px">@csrf
<label>Customer name</label><input name="customer_name" required><label>Phone</label><input name="phone" required>
<label>Address</label><textarea name="address" rows="2"></textarea><label>District</label><input name="district">
<label>Order source</label><select name="source">@foreach(['Web Site','messenger','Whatsapp','FB','youtube','google','tiktok','instagram','Landing Page'] as $s)<option>{{ $s }}</option>@endforeach</select>
<label>Product</label><input name="item_name" required><label>Quantity</label><input name="qty" type="number" min="1" value="1" required>
<label>Total (TK)</label><input name="total" type="number" step="0.01" min="0" required><label>Shipping (TK)</label><input name="shipping" type="number" step="0.01" min="0" value="0">
<label>Note</label><textarea name="note" rows="2"></textarea>@if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
<div style="margin-top:14px"><button>Save order</button></div></form>@endsection
