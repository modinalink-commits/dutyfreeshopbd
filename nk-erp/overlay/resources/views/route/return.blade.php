@extends('layout')
@section('title', 'Vehicle return')
@section('content')
@include('route._css')
@php($U = \App\Support\Ui::class)
@php($edit = $trip->status === 'dispatched')
@php($sz = $c['sizes'])
<form method="post" action="{{ route('trips.return.store', $trip) }}" id="retForm" style="display:flex;flex-direction:column;gap:16px">@csrf
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Vehicle return</h1>
    <span class="sub">{{ $trip->vehicle->code }} · {{ $trip->trip_no }} · {{ $trip->route?->name ?? 'No route' }} · Driver {{ $trip->driver?->name ?? '—' }} · Salesman {{ $trip->salesman?->name ?? '—' }}</span></div>
  <button class="btn noprint" type="button" onclick="window.print()">Empty cylinder receipt</button>
  @if($edit)<button class="btn pri" type="submit" onclick="return confirm('Save the return? Unsold, damaged and empty cylinders will move back to the warehouse. This cannot be edited later, only adjusted in Daily closing.')">Save &amp; go to cash →</button>
  @else<a class="btn pri" href="{{ route('trips.settle', ['trip' => $trip->id]) }}">Go to cash →</a>@endif
  @include('partials.export-menu', ['r' => 'empties'])
</header>
@include('route._tripbar', ['routeName' => 'trips.return'])
@if($trip->status === 'dispatched' && $c['invoices'] === 0)<div class="flash" style="background:#fff1d6;color:#8a4b00">No sales are entered on this trip yet. Enter them in <a href="{{ route('trips.sales', $trip) }}">Trip sales</a> before saving the return.</div>@endif

<section class="card" style="padding:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
  <fieldset @disabled(! $edit) style="display:contents">
  <label class="fld">Return date<input type="date" name="return_date" value="{{ ($trip->returned_at ?? now())->timezone('Asia/Dhaka')->toDateString() }}"></label>
  <label class="fld">Return time<input type="time" name="return_time" value="{{ $trip->return_time ?? now()->timezone('Asia/Dhaka')->format('H:i') }}"></label>
  <label class="fld">Checked by<select name="checked_by"><option value="">—</option>@foreach($checkers as $e)<option value="{{ $e->id }}" @selected($trip->return_checked_by == $e->id)>{{ $e->name }}</option>@endforeach</select></label>
  <label class="fld">Remarks<input name="remarks" maxlength="255" value="{{ old('remarks', $trip->return_remarks) }}"></label>
  </fieldset>
</section>

<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px;display:flex;align-items:center"><h2 class="h2" style="flex-grow:1">Full cylinders — loaded = sold + returned</h2></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Size</th><th class="r">Morning loaded</th><th class="r">Sold (exchange)</th><th class="r">Sold (new cylinder)</th><th class="r">Unsold full returned</th><th class="r">Damaged</th><th class="r">Missing</th><th>Check</th></tr></thead>
    <tbody>
    @php($tt = ['l' => 0, 'x' => 0, 'n' => 0, 'f' => 0, 'd' => 0, 'm' => 0])
    @foreach($sz as $s => $z)
      @php($exp = $z['loaded'] - $z['exch'] - $z['new'])
      @php($tt['l'] += $z['loaded']) @php($tt['x'] += $z['exch']) @php($tt['n'] += $z['new']) @php($tt['f'] += $z['full_returned']) @php($tt['d'] += $z['damaged']) @php($tt['m'] += $edit ? 0 : $z['missing'])
      <tr class="frow" data-exp="{{ $exp }}"><td class="c">{{ $s }} kg</td><td class="r">{{ $z['loaded'] }}</td><td class="r">{{ $z['exch'] }}</td><td class="r">{{ $z['new'] }}</td>
        <td class="r">@if($edit)<input class="qin f" type="number" min="0" required name="full[{{ $s }}]" value="{{ old('full.'.$s) }}" placeholder="{{ $exp }}" aria-label="{{ $s }} kg unsold full returned">@else{{ $z['full_returned'] }}@endif</td>
        <td class="r">@if($edit)<input class="qin d" type="number" min="0" name="damaged[{{ $s }}]" value="{{ old('damaged.'.$s, 0) }}" aria-label="{{ $s }} kg damaged">@else{{ $z['damaged'] }}@endif</td>
        <td class="r m" style="font-weight:700">{{ $edit ? '' : $z['missing'] }}</td>
        <td class="c chk">@unless($edit)<span class="pill" style="{{ $U::pill($z['missing'] ? 'Mismatch found' : 'Matches') }}">{{ $z['missing'] ? $z['missing'].' missing' : 'Matches' }}</span>@endunless</td></tr>
    @endforeach
    @if($sz)<tr class="tot"><td>Total</td><td class="r">{{ $tt['l'] }}</td><td class="r">{{ $tt['x'] }}</td><td class="r">{{ $tt['n'] }}</td><td class="r">{{ $edit ? '' : $tt['f'] }}</td><td class="r">{{ $edit ? '' : $tt['d'] }}</td><td class="r">{{ $edit ? '' : $tt['m'] }}</td><td></td></tr>
    @else<tr><td colspan="8" class="empty">Nothing was loaded on this trip.</td></tr>@endif
    </tbody>
  </table></div>
</section>

<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px"><h2 class="h2">Empty cylinders — collected = returned to warehouse</h2></div>
  <div class="dt-wrap" style="margin:0 16px"><table class="dt">
    <thead><tr><th>Size</th><th class="r">Expected (exchange sales)</th><th class="r">Collected from shops</th><th class="r">Pending at shops</th><th class="r">Returned to warehouse</th><th class="r">Difference</th><th>Check</th></tr></thead>
    <tbody>
    @php($te = ['e' => 0, 'c' => 0, 'p' => 0, 'r' => 0, 'd' => 0])
    @foreach($sz as $s => $z)
      @php($te['e'] += $z['expected']) @php($te['c'] += $z['collected']) @php($te['p'] += $z['pending']) @php($te['r'] += $z['empty_returned']) @php($te['d'] += $z['emptyDiff'])
      <tr class="erow" data-col="{{ $z['collected'] }}"><td class="c">{{ $s }} kg</td><td class="r">{{ $z['expected'] }}</td><td class="r">{{ $z['collected'] }}</td><td class="r">{{ $z['pending'] }}</td>
        <td class="r">@if($edit)<input class="qin e" type="number" min="0" required name="empty[{{ $s }}]" value="{{ old('empty.'.$s) }}" placeholder="{{ $z['collected'] }}" aria-label="{{ $s }} kg empties returned">@else{{ $z['empty_returned'] }}@endif</td>
        <td class="r dif" style="font-weight:700">{{ $edit ? '' : ($z['emptyDiff'] < 0 ? '−'.abs($z['emptyDiff']) : $z['emptyDiff']) }}</td>
        <td class="c chk">@unless($edit)<span class="pill" style="{{ $U::pill($z['emptyDiff'] ? 'Mismatch found' : 'Matches') }}">{{ $z['emptyDiff'] < 0 ? abs($z['emptyDiff']).' missing' : ($z['emptyDiff'] > 0 ? $z['emptyDiff'].' extra' : 'Matches') }}</span>@endunless</td></tr>
    @endforeach
    @if($sz)<tr class="tot"><td>Total</td><td class="r">{{ $te['e'] }}</td><td class="r">{{ $te['c'] }}</td><td class="r">{{ $te['p'] }}</td><td class="r">{{ $edit ? '' : $te['r'] }}</td><td class="r">{{ $edit ? '' : ($te['d'] < 0 ? '−'.abs($te['d']) : $te['d']) }}</td><td></td></tr>@endif
    </tbody>
  </table></div>
  <div style="padding:12px 20px;font-size:var(--fs-11);color:#51607a">Pending = expected − collected. New-cylinder sales expect no empty back. A missing empty must be found or approved as an adjustment in Daily closing.</div>
</section>
</form>

<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px"><h2 class="h2">Empties still at shops — added to customer empty ledger</h2></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Shop</th><th>Mobile</th><th>Size</th><th class="r">Sold</th><th class="r">Empty received</th><th class="r">Pending</th><th>Condition</th></tr></thead>
    <tbody>
    @forelse($shops as $r)
      <tr><td><a href="{{ route('customers.statement', $r['c']) }}">{{ $r['c']->name }}</a></td><td class="nw">{{ $r['c']->phone }}</td><td class="c">{{ $r['size'] }} kg</td><td class="r">{{ $r['sold'] }}</td><td class="r">{{ $r['got'] }}</td><td class="r" style="font-weight:700">{{ $r['pending'] }}</td><td>{{ $r['note'] ?: '—' }}</td></tr>
    @empty
      <tr><td colspan="7" class="empty">No empties left at shops on this trip.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@if($edit)
<script>
(function(){
  const f=document.getElementById('retForm'), ok='<span class="pill" style="background:#e3f6ec;color:#0f6b3c">Matches</span>', bad=t=>'<span class="pill" style="background:#fde9e7;color:#b3261e">'+t+'</span>';
  function calc(){
    f.querySelectorAll('tr.frow').forEach(r=>{ const fi=r.querySelector('.f'); if(fi.value===''){r.querySelector('.m').textContent='';r.querySelector('.chk').innerHTML='';return;}
      const m=+r.dataset.exp-(+fi.value)-(+(r.querySelector('.d').value||0)); r.querySelector('.m').textContent=m; r.querySelector('.chk').innerHTML=m===0?ok:bad(m>0?m+' missing':Math.abs(m)+' too many'); });
    f.querySelectorAll('tr.erow').forEach(r=>{ const e=r.querySelector('.e'); if(e.value===''){r.querySelector('.dif').textContent='';r.querySelector('.chk').innerHTML='';return;}
      const d=(+e.value)-(+r.dataset.col); r.querySelector('.dif').textContent=d<0?'−'+Math.abs(d):d; r.querySelector('.chk').innerHTML=d===0?ok:bad(d<0?Math.abs(d)+' missing':d+' extra'); });
  }
  f.addEventListener('input',calc); calc();
})();
</script>
@endif
@endsection
