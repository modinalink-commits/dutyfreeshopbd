@extends('layout')@section('title','Recurring Fixed Expenses')
@section('body')
@php
  $tk = fn($v) => '৳'.number_format((float)$v);
  $pill = function($x) { $s = $x->payment_status; if (in_array($x->status, ['skipped','cancelled'])) return [$x->status, '#6B7280']; if ($s === 'paid') return ['Paid','#166534']; if ($s === 'failed') return ['Failed','#B91C1C']; if ($x->due_date < now('Asia/Dhaka')->toDateString()) return ['Overdue','#B91C1C']; return [$x->status === 'due' ? 'Due (not posted)' : 'Posted · unpaid','#B45309']; };
  $types = ['fixed'=>'Fixed Expense','variable'=>'Recurring Variable','loan'=>'Loan Installment','payroll'=>'Payroll','subscription'=>'Subscription','utility'=>'Utility'];
  $freqs = ['monthly'=>'Monthly','weekly'=>'Weekly (day 1=Mon…7=Sun)','quarterly'=>'Quarterly','half_yearly'=>'Half-Yearly','yearly'=>'Yearly','custom'=>'Custom (every N months)'];
@endphp
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:260px"><h1 style="margin:0">Recurring Fixed Expenses</h1><span style="font-size:13px;color:#6B7280">Set up once. Every month's bill appears in Upcoming — type the real amount, pick the payment date and account, then press Post. The amount is deducted from the account and written to Expense history.</span></div>
@if($can)<form method="post" action="/admin/recurring-expenses/run">@csrf<button class="g" type="submit">Post due items now</button></form>@endif
<a class="btn g" href="/admin/m/entries">Income &amp; Expenses</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif @if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif @if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:12px">
@foreach([['Total fixed this month',$k['total'],'#111827'],['Paid',$k['paid'],'#166534'],['Upcoming',$k['upcoming'],'#1D4ED8'],['Overdue / unpaid past due',$k['overdue'],'#B91C1C'],['Loan installments',$k['loan'],'#111827'],['Salary',$k['salary'],'#111827'],['Subscriptions',$k['subs'],'#111827']] as $c)
<div class="card" style="margin:0"><small>{{ $c[0] }}</small><div style="font-size:20px;font-weight:800;color:{{ $c[2] }}">{{ $tk($c[1]) }}</div></div>
@endforeach
</div>

<div class="card" style="overflow:auto"><b>Upcoming — post when you pay</b> <span style="font-size:12px;color:#6B7280">Type the real amount, pick the payment date, choose the account and press Post: the amount is deducted from the account and written to Expense history on that date.</span>
<table><thead><tr><th>Due</th><th>Expense</th><th>Category</th><th>Amount</th><th>Auto post</th><th></th></tr></thead><tbody>
@forelse($upcoming as $u)<tr><td>{{ \Carbon\Carbon::parse($u->due)->format('d M Y') }}@if($u->due < now('Asia/Dhaka')->toDateString()) <b style="color:#B91C1C">· overdue</b>@endif</td><td>{{ $u->name }}</td><td>{{ $u->category }}</td><td>{{ $tk($u->amount) }}</td><td>{{ $u->auto ? 'ON' : 'OFF' }}</td><td>@if($can && ($u->type ?? '') !== 'payroll')<form method="post" action="/admin/recurring-expenses/{{ $u->mid }}/pay-upcoming" style="display:inline-flex;gap:4px">@csrf<input type="hidden" name="period" value="{{ $u->period }}"><input type="hidden" name="due" value="{{ $u->due }}"><input name="amount" type="number" step="any" min="0" value="{{ (float)$u->amount }}" title="This month's real amount" style="width:100px"><input name="date" type="date" value="{{ now('Asia/Dhaka')->toDateString() }}" title="Payment date"><select name="account_id" style="max-width:150px">@foreach($acc as $a)<option value="{{ $a->id }}" @selected($a->id == $u->acc)>{{ $a->name }}</option>@endforeach</select><button type="submit">Post</button></form>@endif</td></tr>
@empty<tr><td colspan="6">Nothing scheduled.</td></tr>@endforelse</tbody></table></div>

<div class="card" style="overflow:auto"><b>Posted / waiting for payment</b>
<table><thead><tr><th>Due</th><th>Expense</th><th>Amount</th><th>Principal / Interest / Fee</th><th>Status</th><th>Note</th><th></th></tr></thead><tbody>
@forelse($owing as $x)@php [$pl,$pc] = $pill($x); @endphp<tr><td>{{ \Carbon\Carbon::parse($x->due_date)->format('d M Y') }}</td><td>{{ $x->name }}</td><td>{{ $tk($x->amount) }}</td><td>@if($x->is_loan){{ $tk($x->principal) }} / {{ $tk($x->interest) }} / {{ $tk($x->fee) }}@else — @endif</td><td style="color:{{ $pc }};font-weight:700">{{ $pl }}</td><td>{{ $x->note }}</td>
<td>@if($can)<form method="post" action="/admin/recurring-expenses/instance/{{ $x->id }}/pay" style="display:inline-flex;gap:4px">@csrf<input name="amount" type="number" step="any" min="0" value="{{ (float)$x->amount }}" title="This month's real amount" style="width:100px"@if($x->is_loan) readonly @endif><input name="date" type="date" value="{{ now('Asia/Dhaka')->toDateString() }}" title="Payment date"><select name="account_id" style="max-width:150px">@foreach($acc as $a)<option value="{{ $a->id }}" @selected($a->id == $x->account_id)>{{ $a->name }}</option>@endforeach</select><button type="submit">Pay</button></form>
<form method="post" action="/admin/recurring-expenses/instance/{{ $x->id }}/skip" style="display:inline" onsubmit="return confirm('Skip this period?')">@csrf<button class="g" type="submit">Skip</button></form>@endif</td></tr>
@empty<tr><td colspan="7">Nothing is waiting.</td></tr>@endforelse</tbody></table></div>

@if($can)
<details class="card"><summary style="cursor:pointer;font-weight:700">+ Add recurring expense</summary>
<form method="post" action="/admin/recurring-expenses" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:10px">@csrf
<div><label>Expense name *</label><input name="name" required placeholder="Head Office Rent"></div>
<div><label>Category *</label><input name="category" list="rcats" required placeholder="Rent"><datalist id="rcats">@foreach($cats as $c)<option value="{{ $c }}">@endforeach</datalist></div>
<div><label>Type *</label><select name="expense_type">@foreach($types as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
<div><label>Amount (BDT) *</label><input name="amount" type="number" step="0.01" min="0" required></div>
<div><label>Frequency *</label><select name="frequency">@foreach($freqs as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
<div><label>Every N months (custom)</label><input name="interval_months" type="number" min="1" value="1"></div>
<div><label>Payment day *</label><input name="pay_day" type="number" min="1" max="31" value="1" required></div>
<div><label>Start date *</label><input name="start_date" type="date" value="{{ $today->toDateString() }}" required></div>
<div><label>End date (blank = until stopped)</label><input name="end_date" type="date"></div>
<div><label>Payment account</label><select name="account_id"><option value="">— none —</option>@foreach($acc as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></div>
<div><label>Expense for</label><select name="expense_for">@foreach(['General Company','Store','Office','Warehouse','Department','Employee','Vehicle','Route','Project'] as $f)<option>{{ $f }}</option>@endforeach</select></div>
<div><label>Store / department</label><input name="store"></div>
<div><label>Loan principal part (loan only)</label><input name="principal" type="number" step="0.01" min="0"></div>
<div><label>Loan interest part</label><input name="interest" type="number" step="0.01" min="0"></div>
<div><label>Other fee</label><input name="fee" type="number" step="0.01" min="0"></div>
<div><label>Loan provider</label><input name="loan_provider"></div>
<div><label>Loan remaining balance</label><input name="remaining_balance" type="number" step="0.01"></div>
<div><label>Currency</label><input name="currency" value="BDT" maxlength="8"></div>
<div><label>Original amount (foreign)</label><input name="orig_amount" type="number" step="0.01"></div>
<div><label>Exchange rate</label><input name="exch_rate" type="number" step="0.0001"></div>
<div style="grid-column:1/-1"><label>Notes</label><input name="notes"></div>
<div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap"><label><input type="checkbox" name="auto_post" value="1" checked> Auto post</label><label><input type="checkbox" name="auto_deduct" value="1"> Auto deduct from account</label><label><input type="checkbox" name="draft" value="1"> Save as draft</label></div>
<div><button type="submit">Save</button></div>
</form><p style="font-size:12px;color:#6B7280;margin:8px 0 0">Loan: the full installment leaves the account, but only interest + fee are an operating expense; principal reduces the loan balance. Salary: use only if Payroll does not post it (Payroll type is never auto-posted here, to avoid double posting).</p></details>
@endif

<div class="card" style="overflow:auto"><b>Fixed Expense Master</b>
<table><thead><tr><th>Name</th><th>Type</th><th>Amount</th><th>Schedule</th><th>Account</th><th>Auto</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($masters as $m)<tr><td>{{ $m->name }}<br><small>{{ $m->category }}</small></td><td>{{ $types[$m->expense_type] ?? $m->expense_type }}</td><td>{{ $tk($m->amount) }}@if($m->is_loan)<br><small>P {{ $tk($m->principal) }} · I {{ $tk($m->interest) }}@if($m->remaining_balance !== null) · left {{ $tk($m->remaining_balance) }}@endif</small>@endif</td>
<td>{{ ucfirst(str_replace('_',' ',$m->frequency)) }} · day {{ $m->pay_day }}<br><small>{{ \Carbon\Carbon::parse($m->start_date)->format('d M Y') }} → {{ $m->end_date ? \Carbon\Carbon::parse($m->end_date)->format('d M Y') : 'open' }}</small></td>
<td>{{ $accName[$m->account_id] ?? '—' }}</td><td>{{ $m->auto_post ? 'Post' : 'Manual' }} · {{ $m->auto_deduct ? 'Deduct' : 'No deduct' }}</td><td>{{ ucfirst($m->status) }}</td>
<td>@if($can)<form method="post" action="/admin/recurring-expenses/{{ $m->id }}/status" style="display:inline">@csrf<input type="hidden" name="to" value="{{ $m->status === 'active' ? 'paused' : 'active' }}"><button class="g" type="submit">{{ $m->status === 'active' ? 'Pause' : 'Resume' }}</button></form>
<details style="display:inline-block"><summary style="cursor:pointer">Edit</summary>
<form method="post" action="/admin/recurring-expenses/{{ $m->id }}" style="display:grid;gap:6px;min-width:260px;padding:8px">@csrf
<input name="name" value="{{ $m->name }}" required><input name="category" value="{{ $m->category }}" required>
<select name="expense_type">@foreach($types as $v=>$l)<option value="{{ $v }}" @selected($m->expense_type===$v)>{{ $l }}</option>@endforeach</select>
<input name="amount" type="number" step="0.01" value="{{ $m->amount }}" required>
<select name="frequency">@foreach($freqs as $v=>$l)<option value="{{ $v }}" @selected($m->frequency===$v)>{{ $l }}</option>@endforeach</select>
<input name="interval_months" type="number" min="1" value="{{ $m->interval_months }}"><input name="pay_day" type="number" min="1" max="31" value="{{ $m->pay_day }}" required>
<input name="start_date" type="date" value="{{ $m->start_date }}" required><input name="end_date" type="date" value="{{ $m->end_date }}">
<select name="account_id"><option value="">— none —</option>@foreach($acc as $a)<option value="{{ $a->id }}" @selected($a->id==$m->account_id)>{{ $a->name }}</option>@endforeach</select>
<input name="principal" type="number" step="0.01" value="{{ $m->principal }}" placeholder="principal"><input name="interest" type="number" step="0.01" value="{{ $m->interest }}" placeholder="interest"><input name="fee" type="number" step="0.01" value="{{ $m->fee }}" placeholder="fee">
<input name="remaining_balance" type="number" step="0.01" value="{{ $m->remaining_balance }}" placeholder="loan balance"><input name="notes" value="{{ $m->notes }}" placeholder="notes">
<label>New amount applies from</label><input name="effective_from" type="date"><input name="reason" placeholder="Reason for amount change">
<label><input type="checkbox" name="auto_post" value="1" @checked($m->auto_post)> Auto post</label><label><input type="checkbox" name="auto_deduct" value="1" @checked($m->auto_deduct)> Auto deduct</label>
<button type="submit">Save</button></form></details>@endif</td></tr>
@empty<tr><td colspan="8">No recurring expenses yet. Use “Add recurring expense”.</td></tr>@endforelse</tbody></table></div>

<div class="card" style="overflow:auto"><b>Expense history</b>
<table><thead><tr><th>Due</th><th>Expense</th><th>Amount</th><th>Status</th><th>Posted at</th><th>Ledger ref</th><th></th></tr></thead><tbody>
@forelse($inst as $x)@php [$pl,$pc] = $pill($x); @endphp<tr><td>{{ \Carbon\Carbon::parse($x->due_date)->format('d M Y') }}</td><td>{{ $x->name }}</td><td>{{ $tk($x->amount) }}</td><td style="color:{{ $pc }};font-weight:700">{{ $pl }}</td><td>{{ $x->posted_at ? \Carbon\Carbon::parse($x->posted_at)->setTimezone('Asia/Dhaka')->format('d M Y H:i') : '—' }}</td><td>{{ $x->entry_ref ?: '—' }}</td><td>@if($can)<details style="display:inline-block"><summary style="cursor:pointer;color:#2563EB">Edit</summary><form method="post" action="/admin/recurring-expenses/instance/{{ $x->id }}/edit" style="display:grid;gap:4px;margin-top:4px">@csrf<input name="amount" type="number" step="any" min="0" value="{{ (float)$x->amount }}"><input name="date" type="date" value="{{ $x->paid_at ? \Carbon\Carbon::parse($x->paid_at)->setTimezone('Asia/Dhaka')->toDateString() : $x->due_date }}"><select name="account_id">@foreach($acc as $a)<option value="{{ $a->id }}" @selected($a->id == $x->account_id)>{{ $a->name }}</option>@endforeach</select><button type="submit">Save</button></form></details> · <form method="post" action="/admin/recurring-expenses/instance/{{ $x->id }}/delete" style="display:inline" onsubmit="return confirm('Delete this entry? The amount goes back to the account.')">@csrf<button class="g" type="submit" style="color:#B91C1C">Delete</button></form>@endif</td></tr>
@empty<tr><td colspan="7">No history yet.</td></tr>@endforelse</tbody></table></div>
@endsection
