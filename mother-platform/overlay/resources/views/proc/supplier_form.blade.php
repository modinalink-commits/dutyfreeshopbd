@extends('layout')@section('title','Supplier')
@section('body')
<h1 style="margin:0 0 10px">{{ $s ? 'Edit supplier' : 'New supplier' }}</h1>@include('proc.nav')
<form class="card" method="post" action="/admin/purchase/suppliers/save">@csrf<input type="hidden" name="id" value="{{ $s->id ?? '' }}">
<div class="fg">
<div><label>Supplier name *</label><input name="name" required value="{{ old('name',$s->name ?? '') }}"></div>
<div><label>Supplier code</label><input value="{{ $s->code ?? 'Auto (SUP-00001…)' }}" disabled></div>
<div><label>Business name</label><input name="business_name" value="{{ old('business_name',$s->business_name ?? '') }}"></div>
<div><label>Contact person</label><input name="contact_person" value="{{ old('contact_person',$s->contact_person ?? '') }}"></div>
<div><label>Mobile (SMS goes here)</label><input name="phone" value="{{ old('phone',$s->phone ?? '') }}" placeholder="01XXXXXXXXX"></div>
<div><label>WhatsApp number</label><input name="whatsapp" value="{{ old('whatsapp',$s->whatsapp ?? '') }}"></div>
<div><label>Email</label><input name="email" type="email" value="{{ old('email',$s->email ?? '') }}"></div>
<div><label>Opening balance (we owe, ৳)</label><input name="opening_balance" type="number" step="0.01" value="{{ old('opening_balance',$s->opening_balance ?? 0) }}"></div>
<div><label>Credit limit (৳, 0 = none)</label><input name="credit_limit" type="number" step="0.01" value="{{ old('credit_limit',$s->credit_limit ?? 0) }}"></div>
<div><label>Payment terms (days)</label><input name="payment_terms" type="number" value="{{ old('payment_terms',$s->payment_terms ?? 0) }}"></div></div>
<label>Address</label><textarea name="address" rows="2">{{ old('address',$s->address ?? '') }}</textarea>
<label>Internal notes</label><textarea name="notes" rows="2">{{ old('notes',$s->notes ?? '') }}</textarea>
<label style="display:flex;gap:8px;align-items:center;margin-top:12px"><input type="checkbox" name="active" value="1" style="width:auto" @checked(old('active',$s->active ?? true))> Active</label>
<div style="margin-top:14px"><button>Save supplier</button> <a class="btn g" href="/admin/purchase/suppliers">Cancel</a></div></form>
@endsection
