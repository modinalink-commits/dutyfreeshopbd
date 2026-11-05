@extends('layout')
@section('title', 'Route sales')
@section('content')
@include('route._css')
@php($U = \App\Support\Ui::class)
@php($d = \Carbon\Carbon::parse($day))
@php($isToday = $day === now()->timezone('Asia/Dhaka')->toDateString())
@php($sa = auth()->user()->isSuperAdmin())
@php($cnt = fn ($st) => $active->filter(fn ($x) => in_array($x['trip']->status, (array) $st, true))->count())
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Route sales</h1>
    <span class="sub">{{ $d->format('l, d-m-Y') }} · {{ $vehicles->count() }} vehicles · live, updates as salesmen enter sales</span></div>
  <div role="group" aria-label="Day" class="noprint" style="display:flex;align-items:center;border:1px solid #7b8aa8;border-radius:999px;background:#fff">
    <a href="{{ route('fleet.dash', ['d' => $d->copy()->subDay()->toDateString()]) }}" aria-label="Previous day" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;text-decoration:none;color:#14213d;font-size:var(--fs-16)">‹</a>
    <a href="{{ route('fleet.dash') }}" style="font-size:var(--fs-13);font-weight:600;padding:0 6px;color:#14213d;text-decoration:none">{{ $isToday ? 'Today' : $d->format('d-m-Y') }}</a>
    <a href="{{ route('fleet.dash', ['d' => $d->copy()->addDay()->toDateString()]) }}" aria-label="Next day" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;text-decoration:none;color:#14213d;font-size:var(--fs-16)">›</a>
  </div>
  <button class="btn noprint" type="button" onclick="window.print()">Download PDF</button>
  <a class="btn pri noprint" href="{{ route('trips.loading', ['d' => $day]) }}">＋ Morning loading</a>
  @include('partials.export-menu', ['r' => 'route_vehicle'])
</header>
@php($short = ($T['missingEmpty'] ? $T['missingEmpty'].' empty' : '').($T['missingEmpty'] && $T['missingFull'] ? ' · ' : '').($T['missingFull'] ? $T['missingFull'].' full' : ''))
@php($groups = [
  'VEHICLES' => [['Total vehicles', $vehicles->count(), 'blue'], ['Active today', $active->count(), 'blue'], ['In market', $cnt('dispatched'), 'vio'], ['Returned', $cnt(['returned', 'settled']), 'info'], ['Closed trips', $cnt('closed'), 'pos'], ['Maintenance', $vehicles->where('status', 'Maintenance')->count(), 'gray']],
  'CYLINDERS' => [['Loaded', $T['loaded'], 'blue'], ['Sold', $T['sold'], 'pos'], ['Empty collected', $T['empty'], 'info'], ['Unsold', $T['loaded'] - $T['sold'], 'warn'], ['Stock shortage', $short ?: '0', 'neg'], ['Empties pending', $T['pending'], 'warn']],
  'MONEY' => [["Today's sales", $U::lakh($T['sales']), 'info'], ['Cash collected', $U::lakh($T['cash']), 'pos'], ['Outstanding due', $U::lakh($T['credit']), 'warn'], ['Cash shortage', $U::tk($T['short']), 'neg'],
     ["Today's net profit", $sa ? $U::tk($T['sales'] - $T['cost'] - $T['exp']).($T['unrated'] ? '*' : '') : 'Super Admin', 'pos'], ['Expenses', $U::tk($T['exp']), 'gray']],
])
<section aria-label="Today's numbers" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">
  @foreach($groups as $label => $tiles)
  <div class="card" style="padding:14px;display:flex;flex-direction:column;gap:10px">
    <span style="font-size:var(--fs-10);font-weight:700;letter-spacing:.08em;color:#51607a">{{ $label }}</span>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px">
      @foreach($tiles as [$k, $v, $c])
      <div class="tile" style="background:{{ $U::bg($c) }};border:0"><span class="k">{{ $k }}</span><span style="font-size:var(--fs-20);line-height:26px;font-weight:700;color:{{ $U::ink($c) }};font-variant-numeric:tabular-nums">{{ $v }}</span></div>
      @endforeach
    </div>
  </div>
  @endforeach
</section>
@if($T['unrated'] && $sa)<p class="sub" style="margin:0">* {{ $T['unrated'] }} cylinders have no company DO rate for their date, so their cost is not in the profit. Add the rate in Company ledger → Rates.</p>@endif

<section class="card" style="overflow:hidden">
  <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:16px 20px"><h2 class="h2" style="flex-grow:1">Vehicle monitoring</h2><span class="pill" style="background:#e6eeff;color:#1d4ed8">Cylinders count all sizes</span></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Vehicle</th><th>Route</th><th>Driver</th><th>Salesman</th><th class="r">Loaded</th><th class="r">Sold</th><th class="r">Empty collected</th><th class="r">Cash collected</th><th>Status</th><th class="no-sort">Action</th></tr></thead>
    <tbody>
    @forelse($rows as $r)
      @php($v = $r['v']) @php($s = $r['s'])
      @php($status = $s ? $s['stage'] : ($v->status === 'Maintenance' ? 'Maintenance' : 'Not out today'))
      @php($t = $s['trip'] ?? null)
      <tr @if($status === 'Mismatch found') style="background:#fff8f7" @endif>
        <td class="nw"><b>{{ $v->code }}</b><br><span class="sub" style="font-size:var(--fs-11)">{{ $v->reg_no }}</span></td>
        <td>{{ $t?->route?->name ?? $v->route?->name ?? '—' }}</td><td>{{ $t?->driver?->name ?? $v->driver?->name ?? '—' }}</td><td>{{ $t?->salesman?->name ?? $v->salesman?->name ?? '—' }}</td>
        <td class="r">{{ $s ? $s['c']['loadedTotal'] : 0 }}</td><td class="r">{{ $s ? $s['c']['soldTotal'] : 0 }}</td><td class="r">{{ $s ? $s['c']['collectedTotal'] : 0 }}</td>
        <td class="r">{{ $s && $t->status !== 'draft' ? $U::tk($s['c']['collection']) : '—' }}</td>
        <td class="c"><span class="pill" style="{{ $U::pill($status) }}">{{ $status }}</span></td>
        <td class="nw" style="font-size:var(--fs-12)"><a href="{{ route('fleet.vehicles', ['v' => $v->id]) }}">View</a>
          @if($t) · <a href="{{ route('trips.sales', $t) }}">Sales</a> · <a href="{{ route('trips.return', ['trip' => $t->id]) }}">Return</a> · <a href="{{ route('trips.closing', ['trip' => $t->id]) }}">Closing</a>@endif</td>
      </tr>
    @empty
      <tr><td colspan="10" class="empty">No vehicles yet. Add them in Vehicles &amp; routes.</td></tr>
    @endforelse
    @if($active->count())
      <tr class="tot"><td colspan="4">Total · {{ $active->count() }} trips</td><td class="r">{{ $T['loaded'] }}</td><td class="r">{{ $T['sold'] }}</td><td class="r">{{ $T['empty'] }}</td><td class="r">{{ $U::tk($T['cash']) }}</td><td colspan="2"></td></tr>
    @endif
    </tbody>
  </table></div>
</section>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(340px,100%),1fr));gap:16px">
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:10px">
    <h2 class="h2">Closing queue</h2>
    @php($q = collect($stages)->groupBy('stage'))
    @php($codes = fn ($g) => $g->map(fn ($x) => $x['trip']->vehicle->code)->implode(', '))
    @php($box = 'border-radius:12px;padding:12px;font-size:var(--fs-12);line-height:18px')
    @foreach($q->get('Mismatch found', collect()) as $x)
      @php($why = collect($x['c']['stockDiff'])->map(fn ($z, $sz) => trim(($z['vehEmpty'] > 0 ? $z['vehEmpty'].' empty '.$sz.' kg missing ' : '').($z['vehFull'] > 0 ? $z['vehFull'].' full '.$sz.' kg missing' : '')))->filter()->values()->all())
      @if($x['c']['cashDiff'] !== null && abs($x['c']['cashDiff']) >= .5 && ! $x['c']['cashAdj']) @php($why[] = $U::tk(abs($x['c']['cashDiff'])).' cash '.($x['c']['cashDiff'] < 0 ? 'short' : 'extra')) @endif
      <div style="background:#fde9e7;color:#b3261e;{{ $box }}"><strong>{{ $x['trip']->vehicle->code }} · Mismatch found</strong> — {{ implode(' and ', $why) }}. Manager must approve an adjustment before closing. <a href="{{ route('trips.closing', ['trip' => $x['trip']->id]) }}" style="color:#b3261e;font-weight:700">Open closing →</a></div>
    @endforeach
    @if($q->has('Closing pending'))<div style="background:#fff1d6;color:#8a4b00;{{ $box }}"><strong>{{ $codes($q['Closing pending']) }} · Ready for review</strong> — stock and cash match. Waiting for manager approval.</div>@endif
    @if($q->has('Returned'))<div style="background:#def5f7;color:#0b6670;{{ $box }}"><strong>{{ $codes($q['Returned']) }} · Returned</strong> — cash settlement not entered yet.</div>@endif
    @if($q->has('In market'))<div style="background:#efe7fb;color:#5b2fa8;{{ $box }}"><strong>{{ $codes($q['In market']) }} · In market</strong> — vehicle return not saved yet.</div>@endif
    @if($q->has('Waiting approval'))<div style="background:#fff1d6;color:#8a4b00;{{ $box }}"><strong>{{ $codes($q['Waiting approval']) }} · Waiting approval</strong> — loading counted, manager must approve dispatch.</div>@endif
    @if($q->has('Closed'))<div style="background:#e3f6ec;color:#0f6b3c;{{ $box }}"><strong>{{ $codes($q['Closed']) }} · Closed</strong> — locked. Changes only by correction entry.</div>@endif
    @if($q->isEmpty())<p class="sub" style="margin:0">No trips on this day.</p>@endif
  </section>
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:10px">
    <div style="display:flex;align-items:center"><h2 class="h2" style="flex-grow:1">Live from the market{{ $live ? ' — '.$live['trip']->vehicle->code : '' }}</h2>@if($live)<span class="pill" style="background:#fff1d6;color:#8a4b00">In market</span>@endif</div>
    @forelse($liveSales as $l)
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-top:1px solid #dbe3f0;font-size:var(--fs-12)">
        <span style="font-weight:700;color:#51607a;width:42px">{{ $l->created_at->timezone('Asia/Dhaka')->format('H:i') }}</span>
        <div style="flex-grow:1;display:flex;flex-direction:column"><b style="font-size:var(--fs-13)">{{ $l->buyerName() }}</b><span class="sub" style="margin:0;font-size:var(--fs-11)">{{ $l->items->map(fn ($i) => $i->size_kg.' kg × '.$i->qty.' '.($i->category === 'refill' ? 'exchange' : 'new cylinder'))->implode(' · ') }}{{ $l->due() > 0 ? ' · '.$U::tk($l->due()).' due' : '' }}</span></div>
        <b style="font-variant-numeric:tabular-nums">{{ $U::tk($l->total) }}</b>
      </div>
    @empty
      <p class="sub" style="margin:0">{{ $live ? 'No sales entered on this trip yet.' : 'No vehicle is in the market right now.' }}</p>
    @endforelse
  </section>
</div>
@endsection
