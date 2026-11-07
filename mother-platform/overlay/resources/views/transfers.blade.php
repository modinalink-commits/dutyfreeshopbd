@extends('layout')@section('title','Fund Transfers')
@section('body')
@php $tk = fn($v) => '৳'.number_format((float)$v, 2); @endphp
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:260px"><h1 style="margin:0">Fund Transfers</h1><span style="font-size:13px;color:#6B7280">Move money between your own accounts. Every transfer leaves a debit on the source and a credit on the destination, with a code, date and who did it.</span></div><a class="btn g" href="/admin/m/accounts">Accounts</a> <a class="btn g" href="/admin/m/entries">Income &amp; Expenses</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif @if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif @if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:12px">
@foreach($acc as $a)@if(abs($bal[$a->id]) > 0.004)<div class="card" style="margin:0"><small>{{ $a->name }}</small><div style="font-size:18px;font-weight:800;color:{{ $bal[$a->id] < 0 ? '#B91C1C' : '#111827' }}">{{ $tk($bal[$a->id]) }}</div></div>@endif @endforeach
</div>

@if($can)
<form class="card" method="post" action="/admin/transfers">@csrf
<b>New transfer</b>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-top:8px">
<div><label>From account *</label><select name="from_account_id" required><option value="">Select…</option>@foreach($acc as $a)<option value="{{ $a->id }}" @selected(old('from_account_id') == $a->id)>{{ $a->name }} ({{ $tk($bal[$a->id]) }})</option>@endforeach</select></div>
<div><label>To account *</label><select name="to_account_id" required><option value="">Select…</option>@foreach($acc as $a)<option value="{{ $a->id }}" @selected(old('to_account_id') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
<div><label>Amount *</label><input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required></div>
<div><label>Date *</label><input type="date" name="transfer_date" value="{{ old('transfer_date', now('Asia/Dhaka')->toDateString()) }}" max="{{ now('Asia/Dhaka')->toDateString() }}" required></div>
<div style="grid-column:1/-1"><label>Note (reason, bank ref…)</label><input name="note" maxlength="300" value="{{ old('note') }}"></div>
</div>
<button type="submit" style="margin-top:10px" onclick="return confirm('Record this transfer?')">Record transfer</button>
</form>
@endif

<form class="card" method="get" action="/admin/transfers" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">
<div><label>Account</label><select name="account_id"><option value="">All</option>@foreach($acc as $a)<option value="{{ $a->id }}" @selected(($f['account_id'] ?? '') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
<div><label>From</label><input type="date" name="from" value="{{ $f['from'] ?? '' }}"></div><div><label>To</label><input type="date" name="to" value="{{ $f['to'] ?? '' }}"></div><button class="g" type="submit">Filter</button></form>

<div class="card" style="overflow:auto"><b>Transfer history</b>
<table><thead><tr><th>Code</th><th>Date</th><th>From</th><th>To</th><th>Amount</th><th>Note</th><th>By</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($rows as $t)<tr @if($t->status !== 'done') style="opacity:.55;text-decoration:line-through" @endif><td>{{ $t->code }}</td><td>{{ \Carbon\Carbon::parse($t->transfer_date)->format('d M Y') }}</td><td>{{ $names[$t->from_account_id] ?? '#'.$t->from_account_id }}</td><td>{{ $names[$t->to_account_id] ?? '#'.$t->to_account_id }}</td><td>{{ $tk($t->amount) }}</td><td>{{ $t->note }}</td><td>{{ $t->user_name }}</td><td>{{ $t->status === 'done' ? 'Done' : 'Voided by '.$t->voided_by }}</td>
<td>@if($can && $t->status === 'done')<form method="post" action="/admin/transfers/{{ $t->id }}/void" onsubmit="return confirm('Void this transfer? Both ledger entries will be removed.')">@csrf<button class="g" type="submit">Void</button></form>@endif</td></tr>
@empty<tr><td colspan="9">No transfers yet.</td></tr>@endforelse</tbody></table></div>

<div class="card" style="overflow:auto"><b>Supplier payments paid from an account (latest 100)</b>
<table><thead><tr><th>Payment</th><th>Date</th><th>Supplier</th><th>Paid from</th><th>Amount</th><th>Txn ID</th></tr></thead><tbody>
@forelse($sup as $p)<tr><td>{{ $p->code }}</td><td>{{ \Carbon\Carbon::parse($p->pay_date)->format('d M Y') }}</td><td>{{ $p->sname }}</td><td>{{ $names[$p->money_account_id] ?? '#'.$p->money_account_id }}</td><td>{{ $tk($p->amount) }}</td><td>{{ $p->txn_id }}</td></tr>
@empty<tr><td colspan="6">None yet — choose "Paid from account" when you pay a supplier.</td></tr>@endforelse</tbody></table></div>
@endsection
