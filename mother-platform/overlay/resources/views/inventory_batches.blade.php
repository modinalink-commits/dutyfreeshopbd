@extends('layout')@section('title','Stock Batches')
@section('body')
@include('report_grid_assets')
@php
  $open = $rows->where('qty_remaining', '>', 0); $remQty = (int)$open->sum('qty_remaining'); $remVal = (float)$open->sum(fn($b) => $b->qty_remaining * $b->unit_cost);
  $fifo = $open->sortBy([['purchase_date', 'asc'], ['id', 'asc']])->first();
@endphp
<div class="noprint" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Stock Batches</h1><span style="font-size:13px;color:#6B7280">{{ $p->name }} · SKU {{ $p->sku ?: $p->id }}. Every purchase is its own batch; shipment uses the oldest batch first (FIFO).</span></div>
<a class="btn g" href="/admin/stock-history/{{ $p->id }}">Stock Tracking</a><a class="btn g" href="/admin/inventory-pricing">Back</a></div>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:12px 14px;margin-bottom:10px;display:flex;flex-wrap:wrap;gap:22px;font-size:13.5px">
<div><span style="color:#6B7280">Stock on hand</span><br><b style="font-size:17px">{{ (int)$p->stock }}</b></div>
<div><span style="color:#6B7280">In open batches</span><br><b style="font-size:17px">{{ $remQty }}</b></div>
<div><span style="color:#6B7280">Current FIFO cost</span><br><b style="font-size:17px">{{ $fifo ? '৳'.number_format($fifo->unit_cost, 2) : '—' }}</b></div>
<div><span style="color:#6B7280">Average cost (reporting)</span><br><b style="font-size:17px">{{ $remQty > 0 ? '৳'.number_format($remVal / $remQty, 2) : '—' }}</b></div>
<div><span style="color:#6B7280">Last purchase price</span><br><b style="font-size:17px">{{ (float)$p->cost > 0 ? '৳'.number_format((float)$p->cost, 2) : '—' }}</b></div>
<div><span style="color:#6B7280">Minimum safe sale (FIFO × {{ number_format(\App\Support\ProfitFloor::rate(), 2) }})</span><br><b style="font-size:17px">{{ $fifo && (float)$fifo->unit_cost > 0 ? '৳'.number_format(\App\Support\ProfitFloor::floor((float)$fifo->unit_cost), 2) : '—' }}</b></div>
<div><span style="color:#6B7280">Sale price</span><br><b style="font-size:17px">৳{{ number_format((float)$p->price, 2) }}</b></div></div>
<div class="rp-body"><div style="overflow:auto;max-height:60vh"><table class="pl"><thead><tr><th>Batch</th><th>Purchase date</th><th>Purchase</th><th>Supplier</th><th class="n">Original qty</th><th class="n">Remaining qty</th><th class="n">Unit cost</th><th class="n">Total remaining value</th><th>Status</th></tr></thead><tbody>
@forelse($rows as $b)@php $st = $b->qty_remaining > 0 ? ($fifo && $fifo->id == $b->id ? 'Next to ship (FIFO)' : 'Open') : ($b->status === 'reversed' ? 'Reversed' : 'Consumed'); @endphp
<tr style="{{ $b->qty_remaining > 0 ? '' : 'color:#9CA3AF' }}"><td>B{{ $b->id }}@if($b->source !== 'purchase') <small style="color:#6B7280">({{ $b->source }})</small>@endif</td><td>{{ \Carbon\Carbon::parse($b->purchase_date)->format('d M Y') }}</td><td>{{ $b->purchase_invoice ?: ($b->reference ?: '—') }}</td><td>{{ $b->supplier_name ?: '—' }}</td><td class="n">{{ $b->qty_received }}</td><td class="n"><b>{{ $b->qty_remaining }}</b></td><td class="n">{{ number_format($b->unit_cost, 2) }}</td><td class="n">{{ number_format($b->qty_remaining * $b->unit_cost, 2) }}</td><td style="{{ $st === 'Next to ship (FIFO)' ? 'font-weight:700;color:#C2410C' : '' }}">{{ $st }}</td></tr>
@empty<tr><td colspan="9" style="padding:14px">No batches yet. The next purchase (stock-in) creates the first one.</td></tr>@endforelse
<tr class="gt"><td colspan="5">Open batches</td><td class="n">{{ $remQty }}</td><td></td><td class="n">{{ number_format($remVal, 2) }}</td><td></td></tr>
</tbody></table></div></div>
<h3 style="margin:16px 0 6px">Shipments from these batches (Actual COGS)</h3>
<div class="rp-body"><div style="overflow:auto;max-height:50vh"><table class="pl"><thead><tr><th>Date</th><th>Invoice</th><th>Order status</th><th>Batch</th><th class="n">Qty shipped</th><th class="n">Returned back</th><th class="n">Unit cost</th><th class="n">Cost</th></tr></thead><tbody>
@forelse($used as $a)<tr><td>{{ \Carbon\Carbon::parse($a->created_at)->utc()->addHours(6)->format('d M Y H:i') }}</td><td>{{ \App\Support\Inv::show($a->invoice) }}</td><td>{{ $a->status }}</td><td>{{ $a->batch_id ? 'B'.$a->batch_id : 'no batch (stock not covered)' }}</td><td class="n">{{ $a->qty }}</td><td class="n">{{ $a->returned_qty }}</td><td class="n">{{ number_format($a->unit_cost, 2) }}</td><td class="n">{{ number_format($a->qty * $a->unit_cost, 2) }}</td></tr>
@empty<tr><td colspan="8" style="padding:14px">No shipment has consumed a batch of this product yet.</td></tr>@endforelse
</tbody></table></div></div>
@include('report_grid_js')
@endsection
