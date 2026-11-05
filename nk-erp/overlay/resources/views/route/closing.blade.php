@extends('layout')
@section('title', 'Daily closing — '.$trip->vehicle->code)
@section('content')
@include('route._css')
@php($U = \App\Support\Ui::class)
@php($isMgr = in_array(auth()->user()->role, ['super_admin', 'admin', 'manager'], true))
@php($labels = ['Pending', 'Ready for review', 'Mismatch found', 'Approved', 'Closed'])
@php($hasAdj = $trip->adjustments->isNotEmpty())
@php($cur = $trip->status === 'closed' ? 4 : ($trip->status !== 'settled' ? 0 : (! ($c['stockOk'] && $c['cashOk']) ? 2 : ($hasAdj ? 3 : 1))))
@php($canClose = $trip->status === 'settled' && $c['stockOk'] && $c['cashOk'])
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Daily closing — {{ $trip->vehicle->code }}</h1>
    <span class="sub">{{ $trip->trip_no }} · {{ $trip->trip_date->format('d-m-Y') }} · {{ $trip->route?->name ?? 'No route' }} · {{ $trip->driver?->name ?? '—' }} / {{ $trip->salesman?->name ?? '—' }}</span></div>
  <a class="btn noprint" href="{{ route('trips.wizard', ['trip' => $trip->id]) }}">ধাপে ধাপে হিসাব</a>
  <button class="btn noprint" type="button" onclick="window.print()">Closing report PDF</button>
  <form method="post" action="{{ route('trips.close', $trip) }}" class="noprint">@csrf
    <button class="btn {{ $canClose ? 'pri' : '' }}" type="submit" @disabled(! $canClose || ! $isMgr) title="{{ $canClose ? 'Lock this trip' : 'Stock and cash must match or have an approved adjustment' }}" onclick="return confirm('Close and lock {{ $trip->trip_no }}?')">{{ $trip->status === 'closed' ? 'Closed · locked' : 'Close trip' }}</button></form>
</header>
@include('route._tripbar', ['routeName' => 'trips.closing'])

<section class="card" aria-label="Closing status" style="padding:16px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:8px">
  @foreach($labels as $i => $l)
    <div style="display:flex;align-items:center;gap:8px">
      <span style="font-size:12px;font-weight:700;padding:6px 14px;border-radius:999px;{{ $i === $cur ? ($i === 2 ? 'background:#b3261e;color:#fff' : 'background:#123a86;color:#fff') : ($i < $cur && ! ($i === 2 && $cur === 3 && false) ? 'background:#e3f6ec;color:#0f6b3c' : 'background:#eef2f9;color:#51607a') }}">{{ $l }}</span>
      @if($i < count($labels) - 1)<span aria-hidden="true" style="color:#7b8aa8">→</span>@endif
    </div>
  @endforeach
</section>

@php($sz = collect($c['sizes']))
@php($stockTxt = $sz->filter(fn ($z) => $z['vehEmpty'] || $z['vehFull'])->map(fn ($z, $s) => trim(($z['vehEmpty'] ? '−'.$z['vehEmpty'].' empty ' : '').($z['vehFull'] ? '−'.$z['vehFull'].' full' : '')).' · '.$s.' kg')->implode(', '))
@php($tiles = [
  ['Morning loading', $c['loadedTotal'], $sz->map(fn ($z, $s) => $s.' kg '.$z['loaded'])->implode(' · '), 'blue'],
  ['Total sales', $c['soldTotal'].' · '.$U::tk($c['totalSales']), $c['invoices'].' invoices', 'info'],
  ['Unsold full returned', $c['returnedTotal'], 'back in warehouse', 'blue'],
  ['Empty collected', $c['collectedTotal'], $c['pendingTotal'].' still at shops', 'info'],
  ['Warehouse empty return', $c['emptyReturnedTotal'], $c['emptyReturnedTotal'] === $c['collectedTotal'] ? 'same as collected' : abs($c['collectedTotal'] - $c['emptyReturnedTotal']).($c['emptyReturnedTotal'] < $c['collectedTotal'] ? ' less' : ' more').' than collected', $c['emptyReturnedTotal'] === $c['collectedTotal'] ? 'pos' : 'neg'],
  ['Total collection', $U::tk($c['collection']), 'incl. '.$U::tk($c['prevCash'] + $c['prevDigital']).' old due', 'pos'],
  ['Cash deposit', $c['counted'] !== null ? $U::tk($c['counted']) : '—', $trip->settled_at ? 'counted '.$trip->settled_at->timezone('Asia/Dhaka')->format('H:i') : 'not counted yet', 'pos'],
  ['Approved due', $U::tk($c['credit']), $c['creditShops']->count().' shops', 'warn'],
  ['Expenses', $U::tk($c['expTotal']), $c['expenses']->pluck('head')->unique()->map(fn ($h) => strtolower($h))->implode(', ') ?: 'none', 'vio'],
  ['Stock difference', $stockTxt ? explode(' · ', $stockTxt)[0] : '0', $stockTxt ? (explode(' · ', $stockTxt)[1] ?? '') : 'matches', $stockTxt ? 'neg' : 'pos'],
  ['Cash difference', $c['cashDiff'] === null ? '—' : $U::tk($c['cashDiff']), $c['cashDiff'] === null ? 'not counted' : ($c['cashDiff'] < -0.5 ? 'short' : ($c['cashDiff'] > 0.5 ? 'extra' : 'matches')), $c['cashDiff'] !== null && abs($c['cashDiff']) >= .5 ? 'neg' : 'pos'],
])
<section aria-label="Trip summary" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
  @foreach($tiles as [$k, $v, $f, $cl])
    <div class="tile" style="background:{{ $U::bg($cl) }}"><span class="k">{{ $k }}</span><span style="font-size:20px;line-height:26px;font-weight:700;color:{{ $U::ink($cl) }};font-variant-numeric:tabular-nums">{{ $v }}</span><span class="f">{{ $f }}</span></div>
  @endforeach
</section>

<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section class="card" style="flex:1 1 380px;min-width:300px;padding:20px;display:flex;flex-direction:column;gap:12px">
    <h2 class="h2">Mismatches to resolve</h2>
    @php($any = false)
    @foreach($c['sizes'] as $s => $z)
      @if($z['vehEmpty'] > 0) @php($any = true)
      <div style="background:#fde9e7;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:4px"><span style="font-size:13px;font-weight:700;color:#b3261e">Stock — {{ $z['vehEmpty'] }} empty {{ $s }} kg missing</span>
        <span style="font-size:12px;line-height:18px">Collected {{ $z['collected'] }}, returned to warehouse {{ $z['empty_returned'] }}.{{ $trip->return_remarks ? ' Return note: '.$trip->return_remarks : '' }}</span></div>
      @endif
      @if($z['vehFull'] > 0) @php($any = true)
      <div style="background:#fde9e7;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:4px"><span style="font-size:13px;font-weight:700;color:#b3261e">Stock — {{ $z['vehFull'] }} full {{ $s }} kg missing</span>
        <span style="font-size:12px;line-height:18px">Loaded {{ $z['loaded'] }}, sold {{ $z['exch'] + $z['new'] }}, returned {{ $z['full_returned'] }}, damaged {{ $z['damaged'] }}.</span></div>
      @endif
    @endforeach
    @if($c['cashDiff'] !== null && abs($c['cashDiff']) >= .5) @php($any = true)
      <div style="background:{{ $c['cashAdj'] ? '#e3f6ec' : '#fde9e7' }};border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:4px"><span style="font-size:13px;font-weight:700;color:{{ $c['cashAdj'] ? '#0f6b3c' : '#b3261e' }}">Cash — {{ $U::tk(abs($c['cashDiff'])) }} {{ $c['cashDiff'] < 0 ? 'short' : 'extra' }}{{ $c['cashAdj'] ? ' · adjustment approved' : '' }}</span>
        <span style="font-size:12px;line-height:18px">Expected {{ $U::tk($c['expected']) }}, counted {{ $U::tk($c['counted']) }}. A shortage is not misconduct until it is looked into.</span></div>
    @endif
    @if($trip->status !== 'settled' && $trip->status !== 'closed')<p class="sub" style="margin:0">{{ $trip->status === 'dispatched' ? 'Vehicle return is not saved yet.' : ($trip->status === 'returned' ? 'Cash settlement is not saved yet.' : 'Loading not dispatched yet.') }}</p>
    @elseif(! $any)<p class="sub" style="margin:0">Nothing to resolve. Stock and cash match.</p>@endif

    @if($trip->adjustments->isNotEmpty())
      <h2 class="h2" style="margin-top:8px">Approved adjustments</h2>
      @foreach($trip->adjustments as $a)
        <div class="ln" style="flex-direction:column;align-items:flex-start;border-top:1px solid #dbe3f0"><b style="font-size:12px">{{ $a->detail }}</b><span style="font-size:11px;color:#51607a;flex-grow:0">{{ $a->reason }} · {{ $a->approver?->name }} · {{ $a->created_at->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}</span></div>
      @endforeach
    @endif

    @if($trip->status === 'settled' && $options)
    <form method="post" action="{{ route('trips.adjust', $trip) }}" style="display:flex;flex-direction:column;gap:12px">@csrf
      <h2 class="h2" style="margin:8px 0 0">Manager adjustment</h2>
      <label class="fld">Adjustment for<select name="option">@foreach($options as $k => $l)<option value="{{ $k }}" @selected(request('adj') === $k)>{{ $l }}</option>@endforeach</select></label>
      <label class="fld">Reason (required)<input name="reason" required minlength="5" maxlength="255" value="{{ old('reason') }}"></label>
      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
        <label class="fld">Approved by<select disabled><option>{{ auth()->user()->roleLabel() }} — {{ auth()->user()->name }}</option></select></label>
        <label class="fld">Date &amp; time<input disabled value="{{ now()->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}"></label>
      </div>
      @if($isMgr)<button class="btn pri" type="submit">Approve adjustment</button>@else<p class="sub" style="margin:0">Only a manager can approve an adjustment.</p>@endif
    </form>
    @endif
  </section>

  <section class="card" style="flex:1 1 380px;min-width:300px;padding:20px;display:flex;flex-direction:column;gap:8px">
    <h2 class="h2" style="margin:0 0 4px">Audit log</h2>
    @forelse($log as $l)
      <div style="display:flex;gap:12px;padding:8px 0;border-top:1px solid #dbe3f0;font-size:12px">
        <span style="width:44px;color:#51607a;font-variant-numeric:tabular-nums">{{ $l->created_at->timezone('Asia/Dhaka')->format('H:i') }}</span>
        <div style="flex-grow:1;display:flex;flex-direction:column"><span style="font-weight:600">{{ \Illuminate\Support\Str::after($l->details, ' · ') }}</span><span style="color:#51607a;font-size:11px">{{ $l->user?->name ?? 'System' }}{{ $l->user ? ' · '.$l->user->roleLabel() : '' }}</span></div>
      </div>
    @empty
      <p class="sub" style="margin:0">Nothing logged yet.</p>
    @endforelse
    <div style="background:#eef2f9;border-radius:12px;padding:12px;font-size:12px;line-height:18px;margin-top:6px">After closing, the trip is locked. A normal user cannot change its sales, stock or cash. Fixes are made only by an approved correction entry, which is logged here.</div>
  </section>
</div>
@endsection
