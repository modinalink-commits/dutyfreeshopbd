@extends('layout')
@section('title', 'Vehicle expenses & profit')
@section('content')
@include('route._css')
@php($U = \App\Support\Ui::class)
@php($T = ['q' => 0, 'sales' => 0, 'cogs' => 0, 'gross' => 0, 'exp' => 0, 'net' => 0, 'unrated' => 0])
@foreach($rows as $r) @foreach(array_keys($T) as $k) @php($T[$k] += $r[$k]) @endforeach @endforeach
@php($per = fn ($n, $q) => $q ? $U::tk($n / $q) : '—')
@php($label = ['day' => 'today', 'week' => 'last 7 days', 'month' => 'this month'][$p])
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Vehicle expenses &amp; profit</h1>
    <span class="sub">{{ $from === $to ? \Carbon\Carbon::parse($to)->format('d-m-Y') : \Carbon\Carbon::parse($from)->format('d-m-Y').' to '.\Carbon\Carbon::parse($to)->format('d-m-Y') }}{{ $open ? ' · '.implode(', ', array_unique($open)).' still in market, its numbers are so far' : '' }}</span></div>
  <div role="group" aria-label="Period" class="noprint" style="display:flex;border:1px solid #7b8aa8;border-radius:999px;overflow:hidden;background:#fff">
    @foreach(['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'] as $k => $l)
      <a href="{{ route('fleet.profit', ['p' => $k, 'd' => $to]) }}" style="padding:9px 14px;font-size:12px;font-weight:700;text-decoration:none;{{ $p === $k ? 'background:#123a86;color:#fff' : 'color:#14213d' }}">{{ $l }}</a>
    @endforeach
  </div>
  <form method="get" class="noprint" style="display:flex;gap:6px"><input type="hidden" name="p" value="{{ $p }}"><input type="date" name="d" value="{{ $to }}" aria-label="Day"><button class="btn" type="submit">Show</button></form>
  <button class="btn noprint" type="button" onclick="window.print()">Profit &amp; loss PDF</button>
</header>

<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:8px"><h2 class="h2" style="flex-grow:1">Vehicle-wise profit</h2><span class="sub" style="margin:0;font-size:12px">Gross profit = net sales − cost of goods · Net profit = gross profit − vehicle expenses</span></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Vehicle</th><th>Salesman</th><th class="r">Cylinders sold</th><th class="r">Net sales</th><th class="r">Cost of goods</th><th class="r">Gross profit</th><th class="r">Expenses</th><th class="r">Net profit</th><th class="r">Net / cylinder</th></tr></thead>
    <tbody>
    @forelse($rows as $r)
      <tr><td class="c"><b>{{ $r['v'] }}</b></td><td>{{ $r['s'] }}</td><td class="r">{{ $r['q'] }}</td><td class="r">{{ $U::tk($r['sales']) }}</td><td class="r">{{ $U::tk($r['cogs']) }}{{ $r['unrated'] ? '*' : '' }}</td><td class="r">{{ $U::tk($r['gross']) }}</td><td class="r" style="color:#b3261e">{{ $U::tk(-$r['exp']) }}</td><td class="r" style="font-weight:700;color:{{ $r['net'] >= 0 ? '#0f6b3c' : '#b3261e' }}">{{ $U::tk($r['net']) }}</td><td class="r">{{ $per($r['net'], $r['q']) }}</td></tr>
    @empty
      <tr><td colspan="9" class="empty">No trips in this period.</td></tr>
    @endforelse
    @if($rows)<tr class="tot"><td colspan="2">Total</td><td class="r">{{ $T['q'] }}</td><td class="r">{{ $U::tk($T['sales']) }}</td><td class="r">{{ $U::tk($T['cogs']) }}</td><td class="r">{{ $U::tk($T['gross']) }}</td><td class="r">{{ $U::tk(-$T['exp']) }}</td><td class="r">{{ $U::tk($T['net']) }}</td><td class="r">{{ $per($T['net'], $T['q']) }}</td></tr>@endif
    </tbody>
  </table></div>
</section>
<p class="sub" style="margin:0">Cylinder deposits and refundable security deposits are kept outside this table — they are neither profit nor expense.{{ $T['unrated'] ? ' * '.$T['unrated'].' cylinders have no company DO rate for their date, so their cost is missing. Add the rate in Company ledger → Rates.' : '' }}</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start">
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:10px">
    <h2 class="h2">Expenses by category — {{ $label }}</h2>
    @php($mx = max(1, $cats->max()))
    @foreach($cats as $k => $v)
      <div style="display:flex;flex-direction:column;gap:4px"><div style="display:flex;font-size:13px"><span style="flex-grow:1">{{ $k }}</span><b class="n">{{ $U::tk($v) }}</b></div>
        <div style="height:6px;border-radius:999px;background:#eef2f9"><div style="height:6px;border-radius:999px;background:#d9730d;width:{{ max(1, $v / $mx * 100) }}%"></div></div></div>
    @endforeach
    <div class="tl"><span>Total</span><span>{{ $U::tk($cats->sum()) }}</span></div>
  </section>
  <form class="card" method="post" action="{{ route('fleet.expense') }}" style="padding:20px;display:flex;flex-direction:column;gap:10px">@csrf
    <h2 class="h2">Add vehicle expense</h2>
    @unless($hasCash)<div class="err" style="font-size:12px">Add a cash account in Accounts &amp; profit first.</div>@endunless
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
      <label class="fld">Trip ID<select name="trip_id" required>@forelse($openTrips as $t)<option value="{{ $t->id }}">{{ $t->trip_no }} · {{ $t->vehicle->code }}</option>@empty<option value="">No open trip</option>@endforelse</select></label>
      <label class="fld">Category<select name="head">@foreach(\App\Models\Expense::TRIP_HEADS as $h)<option>{{ $h }}</option>@endforeach</select></label>
      <label class="fld">Amount<input type="number" name="amount" min="1" step="0.01" required></label>
      <label class="fld">Paid from<select name="from"><option value="collection">Trip collection</option><option value="office">Office cash</option></select></label>
    </div>
    <label class="fld">Note<input name="note" maxlength="255"></label>
    <button class="btn pri" type="submit" style="align-self:flex-start">Save expense</button>
  </form>
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:6px">
    <h2 class="h2" style="margin-bottom:4px">Salesman performance — {{ $label }}</h2>
    @forelse($perf as $i => $r)
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-top:1px solid #dbe3f0;font-size:13px">
        <span style="width:26px;height:26px;border-radius:999px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;{{ $i === 0 ? 'background:#fff1d6;color:#8a4b00' : 'background:#eef2f9;color:#51607a' }}">{{ $i + 1 }}</span>
        <span style="flex-grow:1;font-weight:600">{{ $r['s'] }} · {{ $r['v'] }}</span><span class="sub" style="margin:0;font-size:12px">{{ $r['q'] }} cyl</span><b style="color:#0f6b3c">{{ $U::tk($r['net']) }}</b>
      </div>
    @empty
      <p class="sub" style="margin:0">No trips in this period.</p>
    @endforelse
  </section>
</div>
@endsection
