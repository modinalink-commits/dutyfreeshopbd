@extends('layout')
@section('title', 'Brand report')
@section('content')
@php($titles = ['purchase' => 'Purchase report', 'sales' => 'Sales report', 'empty' => 'Empty collection report', 'stock' => 'Stock by brand'])
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>{{ $titles[$type] }}</h1><p class="sub">By brand and size{{ $type !== 'stock' ? ', '.\Carbon\Carbon::parse($from)->format('d-m-Y').' to '.\Carbon\Carbon::parse($to)->format('d-m-Y') : ', live balance' }}</p></div>
  <div class="tabs">@foreach($titles as $k => $l)<a class="{{ $type === $k ? 'on' : '' }}" href="{{ route('brands.report', ['type' => $k, 'from' => $from, 'to' => $to]) }}">{{ $l }}</a>@endforeach</div>
  <button class="btn" type="button" onclick="window.print()">Print / PDF</button>
</header>
@if($type !== 'stock')
<form method="get" class="card pad" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end"><input type="hidden" name="type" value="{{ $type }}">
  <label class="f">From<input type="date" name="from" value="{{ $from }}"></label><label class="f">To<input type="date" name="to" value="{{ $to }}"></label><button class="btn" type="submit">Show</button></form>
@endif
<div class="dt-wrap"><table class="dt">
  <thead><tr><th>Brand ID</th><th>Brand</th><th>Size</th>@if($type === 'empty')<th>Empties</th>@elseif($type === 'stock')<th>Full</th><th>Empty</th>@else<th>Full cylinders</th>@endif<th>Entries</th></tr></thead>
  <tbody>
  @forelse($rows as $r)
    <tr><td class="c">{{ $r->code }}</td><td><b>{{ $r->name }}</b></td><td class="c">{{ $r->size_kg }} kg</td>
      @if($type === 'empty')<td class="c">{{ number_format($r->e) }}</td>@elseif($type === 'stock')<td class="c">{{ number_format($r->f) }}</td><td class="c">{{ number_format($r->e) }}</td>@else<td class="c">{{ number_format(abs($r->f)) }}</td>@endif
      <td class="c">{{ $r->n }}</td></tr>
  @empty
    <tr><td colspan="6" class="empty">Nothing in this period.</td></tr>
  @endforelse
  </tbody>
</table></div>
@endsection
