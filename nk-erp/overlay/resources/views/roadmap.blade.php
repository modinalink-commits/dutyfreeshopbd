@extends('layout')
@section('title', 'Build progress')
@section('content')
<div><h1>Build progress</h1><p class="sub">Every screen of the approved design, and whether it works yet. Live = you can use it now.</p></div>
@php($live = 0) @php($all = 0)
@foreach($menu as $items) @foreach($items as $it) @php($all++) @if($it[2]) @php($live++) @endif @endforeach @endforeach
<div class="kpis">
  <div class="card kpi"><span>Back-office pages live</span><b>{{ $live }} / {{ $all }}</b></div>
  <div class="card kpi"><span>Apps &amp; portals</span><b>0 / {{ count($apps) }}</b></div>
</div>
<section class="card"><div class="scroll"><table>
  <thead><tr><th>Menu</th><th>Page</th><th>Design screens</th><th>Status</th></tr></thead>
  <tbody>
  @foreach($menu as $group => $items)
    @foreach($items as [$key, $label, $route, $design])
      <tr><td style="color:var(--muted)">{{ $group }}</td><td><b>{{ $label }}</b></td><td>{{ $design }}</td>
      <td>@if($route)<span class="pill st-delivered">Live</span>@else<span class="pill">Planned</span>@endif</td></tr>
    @endforeach
  @endforeach
  @foreach($apps as [$label, $design])
      <tr><td style="color:var(--muted)">APPS</td><td><b>{{ $label }}</b></td><td>{{ $design }}</td><td><span class="pill">Planned</span></td></tr>
  @endforeach
  </tbody>
</table></div></section>
@endsection
