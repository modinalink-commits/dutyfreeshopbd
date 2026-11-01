@extends('layout')@section('title','Debit / credit note')
@section('body')
<h1 style="margin:0 0 10px">New Debit / Credit Note</h1>@include('proc.nav')
<form class="card" method="post" action="/admin/purchase/notes">@csrf<div class="fg">
<div><label>Type *</label><select name="kind"><option value="debit">Debit note — reduces what we owe</option><option value="credit">Credit note — adds to what we owe</option></select></div>
<div><label>Supplier *</label><select name="supplier_id" id="sup" required><option value="">Select…</option>@foreach($sup as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
<div><label>Related invoice</label><select name="purchase_id" id="inv"><option value="">—</option></select></div>
<div><label>Reason</label><select name="reason">@foreach(['Damage Claim','Price Difference','Purchase Return','Extra Charge','Supplier Adjustment'] as $r)<option>{{ $r }}</option>@endforeach</select></div>
<div><label>Amount (৳) *</label><input type="number" step="0.01" min="0.01" name="amount" required></div><div><label>Date *</label><input type="date" name="note_date" required value="{{ now('Asia/Dhaka')->toDateString() }}"></div></div>
<label>Note</label><textarea name="note" rows="2"></textarea><div style="margin-top:12px"><button>Save note</button> <a class="btn g" href="/admin/purchase/notes">Cancel</a></div></form>
<script>document.getElementById('sup').onchange=function(){var i=document.getElementById('inv');i.innerHTML='<option value="">—</option>';if(!this.value)return;fetch('/admin/purchase/api/supplier/'+this.value).then(function(r){return r.json()}).then(function(d){d.invoices.forEach(function(x){i.insertAdjacentHTML('beforeend','<option value="'+x.id+'">'+x.invoice+' · '+x.date+'</option>')})})};</script>
@endsection
