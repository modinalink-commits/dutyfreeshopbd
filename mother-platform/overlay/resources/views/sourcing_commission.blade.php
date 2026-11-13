@extends('layout')@section('title','Sourcing Commission')
@section('body')
@php $tk = fn($v) => '৳'.number_format((float)$v, 0); $t = $calc['totals'] ?? null; $sids = $ag ? (json_decode((string)$ag->store_ids, true) ?: []) : []; @endphp
<h1>Sourcing Commission</h1>
<p style="color:#6B7280;font-size:13px;margin:0 0 10px">Commission of people who source products. It is built only from the order lines they sourced (entered on <a href="/admin/vendor-orders">Vendor Orders</a>) and approved here.</p>
@if(session('ok'))<div class="card" style="border-color:#BFE3CC;background:#F0FAF3;color:#14683A;font-weight:600">{{ session('ok') }}</div>@endif
@if(!$ag)
<div class="card">No sourcing agreement yet. Create the first one below.</div>
@else
<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
<select name="a">@foreach($ags as $g)<option value="{{ $g->id }}" {{ $g->id === $ag->id ? 'selected' : '' }}>{{ $g->name }}{{ $g->active ? '' : ' (inactive)' }}</option>@endforeach</select>
<input type="month" name="month" value="{{ $mon }}"><button class="btn g">Show</button>
<span style="font-size:12px;color:#6B7280">Counts orders in status: <b>{{ $ag->count_statuses }}</b>, by delivery date.</span>
</form>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-bottom:12px">
@foreach([['Lines counted', $t['line_count'], false], ['Profit of sourced lines', $t['gross_profit'], true], ['Marketing cost', -$t['marketing_cost'], true], ['Brought forward', -$t['carry_in'], true], ['To be shared', $t['shareable'], true], [$ag->name.' ('.rtrim(rtrim(number_format((float)$ag->share_pct, 2), '0'), '.').'%)', $t['staff_share'], true], ['DutyFreeBD', $t['company_share'], true]] as [$lb, $v, $money])
@if($lb !== 'Brought forward' || $t['carry_in'] > 0)<div class="card" style="margin:0;padding:10px 12px"><div style="font-size:11px;font-weight:700;letter-spacing:.4px;color:#6B7280;text-transform:uppercase">{{ $lb }}</div><div style="font-size:19px;font-weight:700;color:{{ $money && $v < 0 ? '#B91C1C' : '#0B1220' }}">{{ $money ? ($v < 0 ? '−' : '').$tk(abs($v)) : number_format($v) }}</div></div>@endif
@endforeach
</div>

<div class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between">
@if($locked)<span><b style="color:#14683A">Approved and locked</b> on {{ \Illuminate\Support\Str::limit((string)$st->approved_at, 16, '') }}. @if($st->payroll_entry_id)The share is in <a href="/admin/m/payroll">Payroll</a> as the {{ $mon }} commission.@endif</span>
@else<span><b style="color:#B45309">Not approved yet.</b> These are live numbers and can still change. @if($t['shareable'] < 0)Profit is below the marketing cost, so the share is ৳0{{ $ag->carry_deficit ? ' and the shortfall is carried to the next month' : '' }}.@endif @if($waiting) {{ $waiting }} approved line(s) are still on the way (not delivered yet).@endif</span>
@if($boss)<form method="post" action="/admin/sourcing-commission/approve" onsubmit="return this.c.checked">@csrf<input type="hidden" name="agreement_id" value="{{ $ag->id }}"><input type="hidden" name="month" value="{{ $mon }}"><label style="font-size:12px;margin-right:8px"><input type="checkbox" name="c" required> I checked this statement</label><button class="btn">Approve {{ $mon }} and send to Payroll</button></form>@endif
@endif
</div>

@if($pending->count())
<div class="card" style="overflow-x:auto"><b>Entries waiting for approval ({{ $pending->count() }})</b>
<table style="margin-top:8px"><thead><tr><th>Order</th><th>Product</th><th>Qty</th><th>Sale price</th><th>Source price</th><th>Entered buy price</th><th>Vendor ref</th><th></th></tr></thead><tbody>
@foreach($pending as $p)@php $bp = $p->sourced_cost !== null ? (float)$p->sourced_cost : (float)$p->src_price; $diff = (float)$p->src_price > 0 && abs($bp - (float)$p->src_price) / (float)$p->src_price > 0.02; @endphp
<tr><td><a href="/admin/orders/{{ $p->order_id }}">{{ $p->invoice }}</a><br><small style="color:#6B7280">{{ $p->status }}</small></td>
<td><b>{{ $p->name }}</b><br><small style="color:#6B7280">{{ $p->src_store }}</small>@if($p->src_url) <a href="{{ $p->src_url }}" target="_blank" rel="noopener noreferrer" style="font-size:12px">link ↗</a>@endif</td>
<td>{{ $p->qty }}</td><td>{{ $tk($p->price) }}</td><td>{{ $tk($p->src_price) }}</td>
<td>{{ $tk($bp) }}@if($p->sourced_cost === null)<br><small style="color:#6B7280">not entered, source price used</small>@elseif($diff)<br><b style="color:#B91C1C;font-size:11px">differs from source price</b>@endif @if($bp > (float)$p->price)<br><b style="color:#B91C1C;font-size:11px">above sale price</b>@endif</td>
<td style="font-size:12px">{{ $p->vendor_ref ?: '—' }}</td>
<td>@if($checker)<form method="post" action="/admin/sourcing-commission/entry/{{ $p->id }}" style="display:flex;gap:4px;flex-wrap:wrap">@csrf<input name="sourced_cost" type="number" step="0.01" min="0" value="{{ $bp }}" style="max-width:110px" aria-label="Buy price per unit"><button class="btn" name="do" value="approve">Approve</button><button class="btn g" name="do" value="reject">Reject</button></form>@else<span style="color:#B45309">Waiting</span>@endif</td></tr>@endforeach
</tbody></table></div>
@endif

<div class="card" style="overflow-x:auto"><b>Statement {{ $mon }}</b> <span style="color:#6B7280;font-size:12px">· profit = sale − discount − buy cost − courier + shipping paid by customer − packaging</span>
@if(count($calc['lines']))
<table style="margin-top:8px"><thead><tr><th>Order</th><th>Product</th><th>Store</th><th>Qty</th><th>Sale</th><th>Discount</th><th>Buy cost</th><th>Courier</th><th>Shipping in</th><th>Packaging</th><th>Profit</th></tr></thead><tbody>
@foreach($calc['lines'] as $l)<tr><td><a href="/admin/orders/{{ $l['order_id'] }}">{{ $l['invoice'] }}</a></td><td>{{ $l['name'] }}</td><td style="font-size:12px">{{ $l['store'] }}</td><td>{{ $l['qty'] }}</td><td>{{ $tk($l['sale']) }}</td><td>{{ $l['discount'] ? '−'.$tk($l['discount']) : '—' }}</td><td>−{{ $tk($l['cost']) }}</td><td>−{{ $tk($l['courier']) }}@if(empty($l['courier_known']))<br><small style="color:#B45309">courier bill not synced, delivery charge used</small>@endif</td><td>{{ $l['ship_income'] ? $tk($l['ship_income']) : '—' }}</td><td>−{{ $tk($l['packaging']) }}</td><td><b style="color:{{ $l['profit'] < 0 ? '#B91C1C' : '#14683A' }}">{{ ($l['profit'] < 0 ? '−' : '').$tk(abs($l['profit'])) }}</b></td></tr>@endforeach
<tr><td colspan="10" style="text-align:right"><b>Profit of sourced lines</b></td><td><b>{{ ($t['gross_profit'] < 0 ? '−' : '').$tk(abs($t['gross_profit'])) }}</b></td></tr>
</tbody></table>
@else<div style="color:#6B7280;margin-top:8px">No approved, delivered sourcing lines in this month yet.</div>@endif
</div>

@if($history->count())
<div class="card" style="overflow-x:auto"><b>Approved months</b>
<table style="margin-top:8px"><thead><tr><th>Month</th><th>Lines</th><th>Profit</th><th>Marketing</th><th>Shared</th><th>{{ $ag->name }}</th><th>DutyFreeBD</th></tr></thead><tbody>
@foreach($history as $h)<tr><td><a href="?a={{ $ag->id }}&month={{ $h->month }}">{{ $h->month }}</a></td><td>{{ $h->line_count }}</td><td>{{ $tk($h->gross_profit) }}</td><td>{{ $tk($h->marketing_cost) }}</td><td>{{ $tk($h->shareable) }}</td><td><b>{{ $tk($h->staff_share) }}</b></td><td>{{ $tk($h->company_share) }}</td></tr>@endforeach
</tbody></table></div>
@endif
@endif

@if($boss)
<details class="card" {{ $ag ? '' : 'open' }}><summary style="cursor:pointer;font-weight:700">Agreement terms {{ $ag ? '· '.$ag->name : '' }}</summary>
<p style="color:#6B7280;font-size:12px;margin:8px 0">Changing a number here changes months that are not approved yet. Approved months keep the terms they were approved with.</p>
<form method="post" action="/admin/sourcing-commission/agreement" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;align-items:end">@csrf
@if($ag)<input type="hidden" name="id" value="{{ $ag->id }}">@endif
<label>Person (login user)<select name="user_id" required>@foreach($users as $u)<option value="{{ $u->id }}" {{ $ag && (int)$ag->user_id === (int)$u->id ? 'selected' : '' }}>{{ $u->name }} · {{ $u->email }}</option>@endforeach</select></label>
<label>Employee in Payroll<select name="employee_id"><option value="">Not linked</option>@foreach($emps as $e)<option value="{{ $e->id }}" {{ $ag && (int)$ag->employee_id === (int)$e->id ? 'selected' : '' }}>{{ $e->name }}</option>@endforeach</select></label>
<label>Packaging cost per order (৳)<input name="packaging_cost" type="number" step="0.01" min="0" required value="{{ $ag ? (float)$ag->packaging_cost : 30 }}"></label>
<label>Marketing cost per month (৳)<input name="marketing_cost" type="number" step="0.01" min="0" required value="{{ $ag ? (float)$ag->marketing_cost : 30000 }}"></label>
<label>Person's share (%)<input name="share_pct" type="number" step="0.01" min="0" max="100" required value="{{ $ag ? (float)$ag->share_pct : 50 }}"></label>
<label>Order statuses that count (comma separated)<input name="count_statuses" required maxlength="200" value="{{ $ag ? $ag->count_statuses : 'Delivered' }}"></label>
<label>Counts orders created from<input name="starts_on" type="date" value="{{ $ag ? $ag->starts_on : '' }}"></label>
<label style="grid-column:1/-1">Source stores sourced by this person by default (other stores: choose the person line by line on Vendor Orders)
<span style="display:flex;gap:6px 14px;flex-wrap:wrap;margin-top:6px;font-weight:400">@forelse($stores as $s)<span><input type="checkbox" name="store_ids[]" value="{{ $s->id }}" id="ss{{ $s->id }}" {{ in_array((int)$s->id, array_map('intval', $sids), true) ? 'checked' : '' }}> <label for="ss{{ $s->id }}" style="display:inline;font-weight:400">{{ $s->name }}</label></span>@empty<span style="color:#6B7280">No stores found.</span>@endforelse</span></label>
<span style="grid-column:1/-1;display:flex;gap:6px 18px;flex-wrap:wrap">
<span><input type="checkbox" name="deduct_discount" value="1" id="scd" {{ !$ag || $ag->deduct_discount ? 'checked' : '' }}> <label for="scd" style="display:inline;font-weight:400">Customer discount reduces the profit</label></span>
<span><input type="checkbox" name="add_shipping_income" value="1" id="scs" {{ !$ag || $ag->add_shipping_income ? 'checked' : '' }}> <label for="scs" style="display:inline;font-weight:400">Shipping paid by the customer is added back</label></span>
<span><input type="checkbox" name="carry_deficit" value="1" id="scc" {{ $ag && $ag->carry_deficit ? 'checked' : '' }}> <label for="scc" style="display:inline;font-weight:400">A month below the marketing cost carries its shortfall to the next month</label></span>
<span><input type="checkbox" name="active" value="1" id="sca" {{ !$ag || $ag->active ? 'checked' : '' }}> <label for="sca" style="display:inline;font-weight:400">Active</label></span></span>
<div style="grid-column:1/-1"><button class="btn">Save agreement</button></div>
</form></details>
@endif
@endsection
