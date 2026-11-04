@extends('layout')
@section('title', 'Stock entry')
@section('content')
@php($admin = in_array(auth()->user()->role, ['super_admin', 'admin']))
<div><h1>Stock entry</h1><p class="sub">Choose what happened. Only the boxes that matter for that choice are shown.</p></div>
<form class="card pad" method="post" action="{{ route('stock.store') }}" style="display:flex;flex-direction:column;gap:14px;max-width:720px" id="sf">
  @csrf
  <label class="f">What happened?
    <select name="kind" id="kind">
      @foreach(\App\Models\StockMovement::KINDS as $k => $l)
        @continue(in_array($k, ['exchange', 'reversal']))
        @continue($k === 'adjustment' && ! $admin)
        <option value="{{ $k }}" @selected(old('kind', $kind) === $k)>{{ $l }}</option>
      @endforeach
    </select>
  </label>
  <div class="grid2">
    <label class="f">Brand<select name="brand_id">@foreach($brands as $b)<option value="{{ $b->id }}" @selected(old('brand_id') == $b->id)>{{ $b->name }}@if(! $b->is_active) (empty tracking)@endif</option>@endforeach</select></label>
    <label class="f">Size<select name="size_kg">@foreach(\App\Models\Brand::SIZES as $s)<option value="{{ $s }}" @selected(old('size_kg') == $s)>{{ $s }} kg</option>@endforeach</select></label>
  </div>
  <div class="grid2">
    <label class="f" data-k="opening received sale damaged"><span data-t="received">Full cylinders received</span><span data-t="opening">Full cylinders in godown now</span><span data-t="sale">Full cylinders sold</span><span data-t="damaged">How many</span><input type="number" name="full" min="0" value="{{ old('full') }}"></label>
    <label class="f" data-k="opening received to_company empty_in sale damaged_out"><span data-t="received">Empties handed to company on the same truck</span><span data-t="opening">Empty cylinders in godown now</span><span data-t="to_company">Empties sent</span><span data-t="empty_in">Empties collected</span><span data-t="sale">Empties taken back from customer</span><span data-t="damaged_out">Damaged cylinders sent out</span><input type="number" name="empty" min="0" value="{{ old('empty') }}"></label>
    <label class="f" data-k="received"><span>Of the full received, against empties sent earlier</span><input type="number" name="from_company" min="0" value="{{ old('from_company') }}"></label>
    <label class="f" data-k="damaged"><span>Taken from</span><select name="damaged_from"><option value="full">Full stock</option><option value="empty">Empty stock</option></select></label>
    <label class="f" data-k="adjustment"><span>Change in full (+ or −)</span><input type="number" name="adj_full" value="{{ old('adj_full') }}"></label>
    <label class="f" data-k="adjustment"><span>Change in empty (+ or −)</span><input type="number" name="adj_empty" value="{{ old('adj_empty') }}"></label>
  </div>
  <label class="f" data-k="received to_company sale empty_in damaged_out"><span data-t="received">Company challan number (required)</span><span data-t="to_company sale empty_in damaged_out">Reference (invoice, challan, customer)</span><input type="text" name="reference" value="{{ old('reference') }}"></label>
  <label class="f"><span>Note</span><span data-t="adjustment" style="color:#b3261e">Reason is required for a count adjustment</span><input type="text" name="note" value="{{ old('note') }}"></label>
  <p class="sub" data-k="sale" style="margin:0">Use this only until the POS and route sales pages are live. Those pages will post sales to stock by themselves.</p>
  <div style="display:flex;gap:8px"><button class="btn pri" type="submit">Post entry</button><a class="btn" href="{{ route('stock.index') }}">Cancel</a></div>
</form>
<script>
(function(){
  var k=document.getElementById('kind');
  function apply(){var v=k.value;
    document.querySelectorAll('[data-k]').forEach(function(el){var on=el.getAttribute('data-k').split(' ').indexOf(v)>=0;el.style.display=on?'':'none';el.querySelectorAll('input,select').forEach(function(i){i.disabled=!on;});});
    document.querySelectorAll('[data-t]').forEach(function(el){el.style.display=el.getAttribute('data-t').split(' ').indexOf(v)>=0?'':'none';});
  }
  k.addEventListener('change',apply);apply();
})();
</script>
@endsection
