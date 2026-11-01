@extends('layout')@section('title','Make payment')
@section('body')
<h1>Payments &amp; Due</h1><div class="sub">Due <b>৳{{ number_format($sumDue) }}</b> &nbsp;|&nbsp; Paid This Month <b>৳{{ number_format($sumPaid) }}</b></div>@include('proc.nav')
<form class="card" method="post" action="/admin/purchase/payments" enctype="multipart/form-data">@csrf<div class="fg">
<div><label>Supplier *</label><select name="supplier_id" id="sup" required><option value="">Select…</option>@foreach($sup as $s)<option value="{{ $s->id }}" @selected(old('supplier_id',$pre)==$s->id)>{{ $s->name }} · {{ $s->code }}</option>@endforeach</select></div>
<div><label>Against invoice (optional — otherwise oldest dues first)</label><select name="purchase_id" id="inv"><option value="">On account / oldest first</option></select></div>
<div><label>Payment date *</label><input type="date" name="pay_date" required value="{{ old('pay_date',now('Asia/Dhaka')->toDateString()) }}"></div>
<div><label>Amount (৳) *</label><input type="number" step="0.01" min="0.01" name="amount" id="amt" required value="{{ old('amount') }}"></div>
<div><label>Payment method *</label><select name="method">@foreach($methods as $m)<option>{{ $m }}</option>@endforeach</select></div>
<div><label>Bank / account</label><input name="account" value="{{ old('account') }}"></div>
<div><label>Transaction ID</label><input name="txn_id" value="{{ old('txn_id') }}"></div>
<div><label>Reference</label><input name="reference" value="{{ old('reference') }}"></div>
<div><label>Payment proof (image / PDF)</label><input type="file" name="proof" accept="image/*,.pdf"></div></div>
<div id="info" class="mut" style="margin-top:8px"></div>
<label>Note</label><textarea name="note" rows="2">{{ old('note') }}</textarea>
<label style="display:flex;gap:8px;align-items:center;font-size:14px;color:var(--n)"><input type="checkbox" style="width:auto" name="nosms" value="1"> Do not send the payment SMS this time</label>
<div style="margin-top:12px"><button>Save payment</button> <a class="btn g" href="/admin/purchase/payments">Cancel</a></div></form>
<script>(function(){var s=document.getElementById('sup'),i=document.getElementById('inv'),f=document.getElementById('info'),pre={{ $inv }};
function load(){i.innerHTML='<option value="">On account / oldest first</option>';f.textContent='';if(!s.value)return;fetch('/admin/purchase/api/supplier/'+s.value).then(function(r){return r.json()}).then(function(d){f.innerHTML='Current due to supplier: <b>৳'+d.due.toFixed(2)+'</b>';d.invoices.filter(function(x){return x.due>0.009}).forEach(function(x){var o=document.createElement('option');o.value=x.id;o.textContent=x.invoice+' · '+x.date+' · due ৳'+x.due.toFixed(2);o.dataset.due=x.due;if(x.id==pre)o.selected=true;i.appendChild(o)});if(pre&&i.value){document.getElementById('amt').value=i.selectedOptions[0].dataset.due}})}
s.onchange=load;i.onchange=function(){var o=i.selectedOptions[0];if(o&&o.dataset.due)document.getElementById('amt').value=o.dataset.due};load()})();</script>
@endsection
