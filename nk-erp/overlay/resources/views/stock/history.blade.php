@extends('layout')
@section('title', 'Stock history')
@section('content')
<div><h1>Stock history</h1><p class="sub">Posted entries are never edited or deleted. A mistake is fixed with a reversal, and both stay on record.</p></div>
<form method="get" class="card pad" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end">
  <label class="f" style="min-width:180px">Brand<select name="brand"><option value="">All brands</option>@foreach($brands as $b)<option value="{{ $b->id }}" @selected(request('brand') == $b->id)>{{ $b->name }}</option>@endforeach</select></label>
  <label class="f" style="min-width:180px">Movement<select name="kind"><option value="">All</option>@foreach(\App\Models\StockMovement::KINDS as $k => $l)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $l }}</option>@endforeach</select></label>
  <button class="btn" type="submit">Filter</button>
</form>
<div class="dt-wrap tall"><table class="dt">
  <thead><tr><th>Time</th><th>Ref</th><th>Movement</th><th>Brand</th><th>Size</th><th>Full</th><th>Empty</th><th>Reference / note</th><th>By</th><th></th></tr></thead>
  <tbody>
  @forelse($moves as $m)
    @include('stock.row', ['m' => $m, 'withReverse' => true])
  @empty
    <tr><td colspan="10" class="empty">No entries.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['p' => $moves])
@endsection
