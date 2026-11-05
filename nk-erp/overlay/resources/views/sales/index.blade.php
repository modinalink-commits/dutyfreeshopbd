@extends('layout')
@section('title', 'Sales list')
@section('content')
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Sales list</h1><p class="sub">Memos and dealer orders by day. Open one to print or void it.</p></div>
  <form method="get" style="display:flex;gap:8px;align-items:end">
    <label class="f">Day<input type="date" name="day" value="{{ $day }}"></label>
    <label class="f">Type<select name="type"><option value="retail" @selected($type === 'retail')>Retail</option><option value="wholesale" @selected($type === 'wholesale')>Wholesale</option></select></label>
    <button class="btn" type="submit">Show</button>
  </form>
  @include('partials.export-menu', ['r' => 'daily_sales', 'from' => $day ?? null, 'to' => $day ?? null])
</header>
@php($live = $sales->where('stage', '!=', 'voided'))
<div class="kpis">
  <div class="card kpi"><span>Memos</span><b>{{ $live->count() }}</b></div>
  <div class="card kpi"><span>Sales</span><b>৳{{ number_format($live->sum('total')) }}</b></div>
  <div class="card kpi"><span>Received</span><b>৳{{ number_format($live->sum('paid')) }}</b></div>
  <div class="card kpi"><span>On due</span><b>৳{{ number_format($live->sum(fn ($s) => $s->due())) }}</b></div>
</div>
<div class="dt-wrap tall"><table class="dt">
  <thead><tr><th>Memo</th><th>Time</th><th>Customer</th><th>Total</th><th>Paid</th><th>Method</th><th>Status</th><th>By</th></tr></thead>
  <tbody>
  @forelse($sales as $s)
    <tr><td class="c"><a href="{{ route('sales.show', $s) }}">{{ $s->invoice_no }}</a></td><td class="c">{{ $s->created_at->timezone('Asia/Dhaka')->format('H:i') }}</td><td>{{ $s->buyerName() }}</td>
      <td class="r">৳{{ number_format($s->total) }}</td><td class="r">৳{{ number_format($s->paid) }}</td><td class="c">{{ \App\Models\Sale::METHODS[$s->payment_method] ?? '—' }}</td>
      <td class="c"><span class="pill {{ $s->stage === 'voided' ? 'st-cancelled' : 'st-delivered' }}">{{ $s->type === 'wholesale' ? $s->wholesaleStatus() : ($s->stage === 'voided' ? 'Voided' : 'Posted') }}</span></td><td>{{ $s->user?->name }}</td></tr>
  @empty
    <tr><td colspan="8" class="empty">No sales on this day.</td></tr>
  @endforelse
  </tbody>
</table></div>
@endsection
