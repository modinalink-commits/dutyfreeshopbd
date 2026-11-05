@extends('layout')
@section('title', 'Morning loading & dispatch')
@section('content')
@include('route._css')
@php($U = \App\Support\Ui::class)
@php($locked = $trip && $trip->status !== 'draft')
@php($isMgr = in_array(auth()->user()->role, ['super_admin', 'admin', 'manager'], true))
@php($loadOf = fn ($s) => (int) old('qty.'.$s, $trip?->loads->firstWhere('size_kg', $s)?->qty ?? 0))
@php($veh = $trip?->vehicle ?? $vehicles->first(fn ($v) => $v->status !== 'Maintenance' && ! $list->firstWhere('v.id', $v->id)['t']))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Morning loading &amp; dispatch</h1>
    <span class="sub">Moves full cylinders from the warehouse to a vehicle. Loading is not a sale.</span></div>
  @if($trip)<a class="btn" href="{{ route('trips.slip', $trip) }}" target="_blank">Print loading slip</a>@else<button class="btn" type="button" disabled title="Save the trip first">Print loading slip</button>@endif
</header>
@unless($brand)<div class="err">Mark your own brand as "for sale" in Cylinder brands before loading.</div>@endunless

<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <form method="post" action="{{ route('trips.loading.store') }}" id="loadForm" style="flex:999 1 600px;min-width:0;display:flex;flex-direction:column;gap:16px">@csrf
    @if($trip)<input type="hidden" name="trip_id" value="{{ $trip->id }}">@endif
    <div class="card" style="padding:20px;display:flex;flex-direction:column;gap:12px">
      <div style="display:flex;align-items:center;gap:10px"><h2 class="h2" style="flex-grow:1">{{ $trip ? 'Trip '.$trip->trip_no : 'New trip' }}</h2>
        <span class="pill" style="background:#e6eeff;color:#1d4ed8">Trip ID {{ $trip?->trip_no ?? $nextNo }} · automatic</span>
        @if($trip)<a class="btn sm" href="{{ route('trips.loading', ['d' => $day]) }}">＋ New trip</a>@endif</div>
      <fieldset @disabled($locked) style="border:0;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
        <label class="fld">Date<input type="date" name="trip_date" required value="{{ old('trip_date', $trip?->trip_date?->toDateString() ?? $day) }}"></label>
        <label class="fld">Vehicle<select name="vehicle_id" id="vehSel" required>@foreach($vehicles as $v)<option value="{{ $v->id }}" data-route="{{ $v->route_id }}" data-driver="{{ $v->driver_id }}" data-sales="{{ $v->salesman_id }}" data-cap="{{ $v->capacity }}" @selected(old('vehicle_id', $veh?->id) == $v->id) @disabled($v->status === 'Maintenance')>{{ $v->code }} · {{ $v->reg_no }}{{ $v->status === 'Maintenance' ? ' (maintenance)' : '' }}</option>@endforeach</select></label>
        <label class="fld">Route<select name="route_id" id="rSel"><option value="">—</option>@foreach($routes as $r)<option value="{{ $r->id }}" @selected(old('route_id', $trip?->route_id ?? $veh?->route_id) == $r->id)>{{ $r->name }}</option>@endforeach</select></label>
        <label class="fld">Driver<select name="driver_id" id="dSel"><option value="">—</option>@foreach($drivers as $e)<option value="{{ $e->id }}" @selected(old('driver_id', $trip?->driver_id ?? $veh?->driver_id) == $e->id)>{{ $e->name }}</option>@endforeach</select></label>
        <label class="fld">Salesman<select name="salesman_id" id="sSel"><option value="">—</option>@foreach($salesmen as $e)<option value="{{ $e->id }}" @selected(old('salesman_id', $trip?->salesman_id ?? $veh?->salesman_id) == $e->id)>{{ $e->name }}</option>@endforeach</select></label>
        <label class="fld">Loading time<input type="time" name="loading_time" value="{{ old('loading_time', $trip?->loading_time ?? now()->timezone('Asia/Dhaka')->format('H:i')) }}"></label>
        <label class="fld">Warehouse staff<select name="warehouse_staff_id"><option value="">—</option>@foreach($warehouse as $e)<option value="{{ $e->id }}" @selected(old('warehouse_staff_id', $trip?->warehouse_staff_id) == $e->id)>{{ $e->name }}</option>@endforeach</select></label>
        <label class="fld">Remarks<input name="remarks" maxlength="255" placeholder="Optional" value="{{ old('remarks', $trip?->remarks) }}"></label>
      </fieldset>
    </div>

    <div class="card" style="overflow:hidden">
      <div style="display:flex;align-items:center;padding:16px 20px"><h2 class="h2" style="flex-grow:1">Cylinders to load</h2><a class="btn sm" href="{{ route('brands.index') }}">＋ Add size / product</a></div>
      <div class="dt-wrap" style="margin:0 16px"><table class="dt">
        <thead><tr><th>Product</th><th>Size</th><th class="r">Warehouse full now</th><th class="r">Load on vehicle</th><th class="r">Warehouse after</th><th>Check</th></tr></thead>
        <tbody>
        @foreach($sizes as $s)
          @php($have = $godown[$s] + ($locked ? $loadOf($s) : 0))
          <tr data-have="{{ $have }}" data-size="{{ $s }}"><td>LPG refill cylinder{{ $brand ? ' · '.$brand->code : '' }}</td><td class="c">{{ $s }} kg</td><td class="r">{{ $have }}</td>
            <td class="r"><input class="qin" type="number" min="0" name="qty[{{ $s }}]" value="{{ $loadOf($s) }}" aria-label="{{ $s }} kg load quantity" @disabled($locked)></td>
            <td class="r after" style="font-weight:700">{{ $have - $loadOf($s) }}</td><td class="c chk"><span class="pill" style="background:#e3f6ec;color:#0f6b3c">Stock OK</span></td></tr>
        @endforeach
          <tr class="tot"><td colspan="3">Total on vehicle</td><td class="r" id="totQ">0</td><td colspan="2" id="capTxt">of {{ $veh?->capacity ?? 0 }} capacity</td></tr>
        </tbody>
      </table></div>
      <div style="padding:14px 20px;display:flex;flex-direction:column;gap:6px">
        <div style="height:10px;border-radius:999px;background:#eef2f9"><div id="capBar" style="width:0;height:10px;border-radius:999px;background:#1d4ed8"></div></div>
        <span style="font-size:11px;color:#51607a"><span id="capPct">0</span>% of vehicle capacity. Warehouse full stock goes down and vehicle stock goes up by the same number only when the trip is dispatched.</span>
      </div>
    </div>

    <div class="card" style="padding:20px;display:flex;flex-direction:column;gap:12px">
      <h2 class="h2">Approval before dispatch</h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">
        @php($counter = $trip?->counted_by ? \App\Models\User::find($trip->counted_by) : null)
        <div style="background:{{ $counter ? '#e3f6ec' : '#eef2f9' }};border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:11px;font-weight:600;color:#51607a">Warehouse staff</span>
          <span style="font-size:14px;font-weight:700;color:{{ $counter ? '#0f6b3c' : '#51607a' }}">{{ $counter ? 'Counted · '.($trip->warehouseStaff?->name ?? $counter->name).' · '.$trip->counted_at->timezone('Asia/Dhaka')->format('H:i') : 'Not counted yet' }}</span></div>
        @php($appr = $trip?->approved_by ? \App\Models\User::find($trip->approved_by) : null)
        <div style="background:{{ $appr ? '#e3f6ec' : '#fff1d6' }};border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:11px;font-weight:600;color:#51607a">Manager</span>
          <span style="font-size:14px;font-weight:700;color:{{ $appr ? '#0f6b3c' : '#8a4b00' }}">{{ $appr ? 'Approved · '.$appr->name.' · '.$trip->dispatched_at->timezone('Asia/Dhaka')->format('H:i') : 'Waiting for approval' }}</span></div>
      </div>
      @unless($locked)
      <div style="display:flex;flex-wrap:wrap;gap:10px">
        <button class="btn" type="submit" name="action" value="draft">Save as draft</button>
        @if($isMgr)<button class="btn pri" type="submit" name="action" value="dispatch" onclick="return confirm('Approve and dispatch? Full cylinders will move from the warehouse to the vehicle.')">Approve &amp; dispatch</button>
        @else<button class="btn pri" type="button" disabled title="Only a manager can approve">Approve &amp; dispatch</button>@endif
      </div>
      @else
      <div style="display:flex;flex-wrap:wrap;gap:10px"><a class="btn" href="{{ route('trips.sales', $trip) }}">Trip sales</a><a class="btn" href="{{ route('trips.return', ['trip' => $trip->id]) }}">Vehicle return →</a></div>
      @endunless
    </div>
  </form>

  <aside class="card" style="flex:1 1 320px;min-width:290px;padding:20px;display:flex;flex-direction:column;gap:10px">
    <h2 class="h2">{{ $day === now()->timezone('Asia/Dhaka')->toDateString() ? "Today's loading" : 'Loading on '.\Carbon\Carbon::parse($day)->format('d-m-Y') }}</h2>
    @forelse($list as $l)
      <a href="{{ $l['t'] ? route('trips.loading', ['trip' => $l['t']->id, 'd' => $day]) : '#' }}" style="display:flex;align-items:center;gap:10px;padding:10px 0;border-top:1px solid #dbe3f0;text-decoration:none;color:inherit">
        <div style="flex-grow:1;display:flex;flex-direction:column"><span style="font-size:13px;font-weight:700">{{ $l['v']->code }} · {{ $l['t']?->trip_no ?? '—' }}</span><span style="font-size:11px;color:#51607a">{{ $l['items'] }}</span></div>
        <span class="pill" style="{{ $U::pill($l['status']) }}">{{ $l['status'] }}</span>
      </a>
    @empty
      <p class="sub" style="margin:0">Add vehicles first in <a href="{{ route('fleet.vehicles') }}">Vehicles &amp; routes</a>.</p>
    @endforelse
    @if($errors->any())
      <div style="background:#fde9e7;color:#b3261e;border-radius:12px;padding:12px;font-size:12px;line-height:18px;margin-top:6px"><strong>Blocked:</strong> {{ $errors->first() }}</div>
    @else
      <div style="background:#fde9e7;color:#b3261e;border-radius:12px;padding:12px;font-size:12px;line-height:18px;margin-top:6px"><strong>Blocked rule:</strong> a load larger than warehouse full stock cannot be saved — the screen names how many are left, e.g. "Only 21 × 45 kg left in the warehouse."</div>
    @endif
  </aside>
</div>
<script>
(function(){
  const f=document.getElementById('loadForm'), vs=document.getElementById('vehSel');
  function cap(){ const o=vs.selectedOptions[0]; return o ? +o.dataset.cap||0 : 0; }
  function calc(){
    let t=0;
    f.querySelectorAll('tr[data-have]').forEach(r=>{
      const q=+(r.querySelector('input').value||0), h=+r.dataset.have, a=h-q; t+=q;
      r.querySelector('.after').textContent=a;
      r.querySelector('.chk').innerHTML=a<0?'<span class="pill" style="background:#fde9e7;color:#b3261e">Only '+h+' left</span>':'<span class="pill" style="background:#e3f6ec;color:#0f6b3c">Stock OK</span>';
    });
    const c=cap(), p=c?Math.round(t/c*100):0;
    document.getElementById('totQ').textContent=t;
    document.getElementById('capTxt').textContent='of '+c+' capacity';
    document.getElementById('capPct').textContent=p;
    const b=document.getElementById('capBar'); b.style.width=Math.min(100,p)+'%'; b.style.background=p>100?'#b3261e':'#1d4ed8';
  }
  f.addEventListener('input',calc);
  vs.addEventListener('change',()=>{ const o=vs.selectedOptions[0];
    [['rSel','route'],['dSel','driver'],['sSel','sales']].forEach(([id,k])=>{ if(o.dataset[k]) document.getElementById(id).value=o.dataset[k]; });
    calc(); });
  calc();
})();
</script>
@endsection
