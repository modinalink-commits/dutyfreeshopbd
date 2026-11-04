@extends('layout')
@section('title', 'Stock & cylinders')
@section('content')
@php($canMove = in_array(auth()->user()->role, ['super_admin', 'admin', 'manager']))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Stock &amp; cylinders</h1><p class="sub">Full, empty and every cylinder outside the godown · live count from posted entries</p></div>
  @if($canMove)
  <a class="btn" href="{{ route('stock.create', ['kind' => 'adjustment']) }}">Stock count</a>
  <a class="btn" href="{{ route('stock.create', ['kind' => 'to_company']) }}">Send empties to company</a>
  <a class="btn pri" href="{{ route('stock.create', ['kind' => 'received']) }}">＋ Receive company challan</a>
  @endif
</header>
<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px" aria-label="Stock by size">
@foreach($sizes as $z)
  <div class="card" style="padding:18px;display:flex;flex-direction:column;gap:14px">
    <div style="display:flex;align-items:center"><span style="font-size:18px;font-weight:700;flex-grow:1">{{ $z['size'] }} kg cylinder</span></div>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
      <div style="background:#e3f6ec;border-radius:12px;padding:12px;display:flex;flex-direction:column"><span style="font-size:11px;font-weight:600;color:var(--muted)">Full in godown</span><span style="font-size:24px;font-weight:700;color:#0f6b3c">{{ number_format($z['full']) }}</span></div>
      <div style="background:#eef2f9;border-radius:12px;padding:12px;display:flex;flex-direction:column"><span style="font-size:11px;font-weight:600;color:var(--muted)">Empty in godown</span><span style="font-size:24px;font-weight:700">{{ number_format($z['empty']) }}</span></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;font-size:12px">
      <div style="display:flex"><span style="flex-grow:1;color:var(--muted)">With dealers / customers</span><span style="color:var(--muted)">with Sales module</span></div>
      <div style="display:flex"><span style="flex-grow:1;color:var(--muted)">On the road</span><span style="color:var(--muted)">with Route sales</span></div>
      <div style="display:flex"><span style="flex-grow:1;color:var(--muted)">Sent to company for refill</span><b>{{ number_format($z['company']) }}</b></div>
      <div style="display:flex"><span style="flex-grow:1;color:var(--muted)">Damaged / for test</span><b>{{ number_format($z['damaged']) }}</b></div>
      <div style="display:flex;padding-top:6px;border-top:1px solid var(--line)"><b style="flex-grow:1">Cylinders counted so far</b><b>{{ number_format($z['full'] + $z['empty'] + $z['company'] + $z['damaged']) }}</b></div>
    </div>
  </div>
@endforeach
</section>
<section class="card">
  <div class="pad" style="display:flex;align-items:center"><h2 style="margin:0;font-size:15px;flex-grow:1">Stock by brand</h2></div>
  <div class="dt-wrap"><table class="dt">
    <thead><tr><th>Brand</th><th>Size</th><th>Full</th><th>Empty</th><th>At company</th><th>Damaged</th></tr></thead>
    <tbody>
    @forelse($grid as $r)
      <tr><td><b>{{ $r->brand->name }}</b></td><td>{{ $r->size_kg }} kg</td><td class="c" style="font-weight:700">{{ number_format($r->f) }}</td><td class="c">{{ number_format($r->e) }}</td><td class="c">{{ number_format($r->c) }}</td><td class="c">{{ number_format($r->d) }}</td></tr>
    @empty
      <tr><td colspan="6" class="empty">No stock entered yet. Start with <a href="{{ route('stock.create', ['kind' => 'opening']) }}">Opening stock</a> for each brand and size you hold today.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
<section class="card">
  <div class="pad" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><h2 style="margin:0;font-size:15px;flex-grow:1">Stock movement — today</h2>
    @if($canMove)<a class="btn sm" href="{{ route('stock.create', ['kind' => 'opening']) }}">Opening stock</a><a class="btn sm" href="{{ route('stock.create', ['kind' => 'empty_in']) }}">Empty collected</a><a class="btn sm" href="{{ route('stock.create', ['kind' => 'damaged']) }}">Damaged</a>@endif
    <a href="{{ route('stock.history') }}" style="font-size:12px;font-weight:600;text-decoration:none">Full history →</a></div>
  <div class="dt-wrap"><table class="dt">
    <thead><tr><th>Time</th><th>Ref</th><th>Movement</th><th>Brand</th><th>Size</th><th>Full</th><th>Empty</th><th>Reference / note</th><th>By</th></tr></thead>
    <tbody>
    @forelse($today as $m)
      @include('stock.row', ['m' => $m, 'withReverse' => false])
    @empty
      <tr><td colspan="9" class="empty">No movement today.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
<p class="sub">Days of stock left, cylinders with customers and on the road, and the company deposit card come when Sales, Route sales and the Company ledger are built.</p>
@endsection
