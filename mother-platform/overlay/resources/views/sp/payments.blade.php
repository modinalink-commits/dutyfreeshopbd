@extends('sp.layout')@section('title','Payments')
@section('body')<h1>Payments</h1><p class="sub">Payments made to you by DutyFreeBD.</p>
@if(count($rows))<div class="tw mcards"><table><thead><tr><th>Payment Date</th><th>Payment Reference</th><th class="n">Amount</th><th>Payment Method</th><th>Related Bill</th><th>Status</th></tr></thead><tbody>
@foreach($rows as $p)<tr><td data-l="Date">{{ $p->pay_date }}</td><td data-l="Reference"><b>{{ $p->code }}</b>@if($p->reference || $p->txn_id)<br><small style="color:var(--mut)">{{ $p->reference ?: $p->txn_id }}</small>@endif</td><td class="n" data-l="Amount"><b>৳{{ number_format($p->amount,2) }}</b></td><td data-l="Method">{{ $p->method }}</td><td data-l="Bill">{{ $p->bills ?: '—' }}</td><td data-l="Status"><span class="pb pb-{{ $p->status==='paid' ? 'paid' : 'pending' }}">{{ $p->status }}</span></td></tr>@endforeach</tbody>
<tfoot><tr><td colspan="2">Total paid</td><td class="n">৳{{ number_format($rows->where('status','paid')->sum('amount'),2) }}</td><td colspan="3"></td></tr></tfoot></table></div>
@else<div class="card empty"><b>No payments yet</b>Payments will show here once DutyFreeBD pays your bills.</div>@endif @endsection
