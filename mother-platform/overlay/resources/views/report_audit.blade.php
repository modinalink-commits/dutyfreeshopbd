@extends('layout')@section('title', 'Report System Audit')
@section('body')
<style>
.ra{font-size:13px;color:#0C161F}
.ra .top{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.ra .top h1{margin:2px 0 0;font-size:22px;line-height:1.2}.ra .mut{color:#6B7280;font-size:11.5px}.ra .crumb{font-size:12px;color:#6B7280}
.ra .btn2{display:inline-flex;align-items:center;min-height:40px;padding:0 14px;border:1px solid #D5D8DC;border-radius:8px;background:#fff;color:#0C161F;font-weight:600;text-decoration:none;cursor:pointer;font-size:13px}
.ra .btn2.p{background:#C2410C;border-color:#C2410C;color:#fff;font-weight:700}
.ra .kp{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));border:1px solid #E6E7E9;border-radius:10px;overflow:hidden;background:#fff;margin-bottom:14px}
.ra .kp>div{padding:8px 12px;border-right:1px solid #E6E7E9;border-bottom:1px solid #E6E7E9;margin:0 -1px -1px 0}
.ra .kp span{display:block;font-size:11.5px;color:#4B5563}.ra .kp b{display:block;font-size:19px;line-height:1.25}
.ra .box{background:#fff;border:1px solid #E6E7E9;border-radius:12px;padding:12px 14px;margin-bottom:14px}
.ra .box h2{margin:0 0 6px;font-size:14px}
.ra .two{display:flex;flex-wrap:wrap;gap:14px}.ra .two>.box{flex:1 1 340px;min-width:0}
.ra .tw{overflow-x:auto}
.ra table.g{width:100%;border-collapse:collapse;font-size:12px}
.ra .g th{height:34px;padding:0 10px;background:#F3F4F6;border:1px solid #D1D5DB;font-weight:700;color:#374151;text-align:left;white-space:nowrap}
.ra .g td{min-height:32px;padding:5px 10px;border:1px solid #E5E7EB;vertical-align:top}
.ra .g .c{text-align:center}.ra .g .nw{white-space:nowrap}
.ra .tg{padding:1px 8px;font-weight:600;display:inline-block;white-space:nowrap}
.ra .bar{height:10px;background:#F1F3F6}.ra .bar i{display:block;height:10px;background:#15803D}
.ra .ok{background:#DCFCE7;border:1px solid #BBF7D0;border-radius:10px;padding:10px 12px;margin-bottom:12px;color:#166534}
.ra .chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}
.ra .chips a{border:1px solid #D5D8DC;background:#fff;color:#0C161F;border-radius:999px;padding:4px 12px;font-size:12px;font-weight:600;text-decoration:none}
.ra .chips a.on{background:#0C161F;border-color:#0C161F;color:#fff}
</style>
<div class="ra">
@if(!$ready)<div class="box">The report registry is not set up on this server yet.</div>
@else
@php
  $tone = ['Broken Route' => '#FEE2E2;color:#B91C1C', 'Missing Permission' => '#FEE2E2;color:#B91C1C', 'Unregistered' => '#FEE2E2;color:#B91C1C', 'Missing Export' => '#FEF3C7;color:#92400E',
           'Duplicate Route' => '#FEF3C7;color:#92400E', 'Duplicate Name' => '#FEF3C7;color:#92400E', 'Legacy' => '#E5E7EB;color:#374151'];
  $pct = $activeN ? round($full * 100 / $activeN) : 0;
@endphp
<div class="top">
  <div style="flex:1;min-width:260px">
    <div class="crumb">Reports &amp; Analytics · admin only</div>
    <h1>Report System Audit</h1>
    <span class="mut" style="font-size:12.5px">Checks that no report has been lost. It compares the report pages found in the code with the registry and flags every mismatch. It is read-only: nothing here changes a report.</span>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn2" href="{{ url('/admin/report-center/audit') }}">↻ Run audit again</a>
    <a class="btn2" href="{{ url('/admin/report-center/all-reports') }}">All Reports</a>
    <form method="post" action="{{ url('/admin/report-center/sync') }}" style="margin:0">@csrf<button class="btn2 p" type="submit" title="Adds catalogue reports that are not in the registry yet. Never removes anything.">Sync registry</button></form>
  </div>
</div>
@if(session('ok'))<div class="ok">{{ session('ok') }}</div>@endif

<div class="kp">
  <div style="box-shadow:inset 0 3px 0 #F95505"><span>Discovered by scan</span><b>{{ $c['discovered'] }}</b></div>
  <div><span>Registered</span><b>{{ $c['registered'] }}</b></div>
  <div><span>Active</span><b>{{ $c['active'] }}</b></div>
  <div><span>Legacy</span><b>{{ $c['legacy'] }}</b></div>
  <div><span>Hidden</span><b>{{ $c['hidden'] }}</b></div>
  <div><span>Missing route</span><b style="color:{{ $c['broken'] ? '#B91C1C' : '#166534' }}">{{ $c['broken'] }}</b></div>
  <div><span>Missing permission</span><b style="color:{{ $c['perm'] ? '#B91C1C' : '#166534' }}">{{ $c['perm'] }}</b></div>
  <div><span>Missing export</span><b style="color:{{ $c['export'] ? '#B91C1C' : '#166534' }}">{{ $c['export'] }}</b></div>
  <div><span>Duplicate candidate</span><b style="color:#92400E">{{ $c['dup'] }}</b></div>
  <div><span>Unregistered</span><b style="color:{{ $c['orphans'] ? '#B91C1C' : '#166534' }}">{{ $c['orphans'] }}</b></div>
</div>

<div class="two">
  <div class="box">
    <h2>Export coverage</h2>
    <div style="display:flex;align-items:baseline;gap:10px"><b style="font-size:26px">{{ $full }} / {{ $activeN }}</b><span class="mut" style="font-size:12.5px">active reports offer all four formats (PDF, Excel, CSV, Print)</span></div>
    <div class="bar" style="margin-top:8px"><i style="width:{{ $pct }}%"></i></div>
    <div style="display:flex;justify-content:space-between;margin-top:4px" class="mut"><span>Reports missing a format: <b style="color:#B91C1C">{{ $activeN - $full }}</b></span><span>Target: <b>100%</b> · now {{ $pct }}%</span></div>
    <p class="mut" style="margin:8px 0 0">“PDF” counts where the page has a print and save-as-PDF button. Staff tracking pages offer CSV only today, so they appear as missing.</p>
  </div>
  <div class="box">
    <h2>Reconciliation</h2>
    <div style="display:grid;grid-template-columns:1fr auto;gap:6px 14px;align-items:center">
      <span>Report pages found by scanning routes and menus</span><b>{{ $c['discovered'] }}</b>
      <span>Rows in the registry</span><b>{{ $c['registered'] }}</b>
      <span style="border-top:1px solid #E6E7E9;padding-top:6px"><b>Found in code but not registered</b></span><b style="border-top:1px solid #E6E7E9;padding-top:6px;color:{{ $c['orphans'] ? '#B91C1C' : '#166534' }}">{{ $c['orphans'] }} {{ $c['orphans'] ? '(must be 0)' : '✓' }}</b>
    </div>
    <p class="mut" style="margin:8px 0 0">The registry also lists pages the scan cannot recognise by name (calls, SMS, AI, documents), so it can be larger than the scan. What matters is that the unregistered number is 0.</p>
  </div>
</div>

<div class="box">
  <h2>Issues found <span class="mut" style="font-weight:400">· {{ count($issues) }} shown</span></h2>
  <div class="chips"><a href="{{ url('/admin/report-center/audit') }}" class="{{ $filter === '' ? 'on' : '' }}">All</a>@foreach($byType as $t => $n)<a href="{{ url('/admin/report-center/audit') }}?type={{ urlencode($t) }}" class="{{ $filter === $t ? 'on' : '' }}">{{ $t }} ({{ $n }})</a>@endforeach</div>
  <div class="tw"><table class="g" style="min-width:760px">
    <thead><tr><th scope="col">Issue</th><th scope="col">Code</th><th scope="col">Report</th><th scope="col">Where found</th><th scope="col">What to do</th></tr></thead>
    <tbody>
    @forelse($issues as $i)<tr><td class="nw"><span class="tg" style="background:{{ $tone[$i['type']] ?? '#E5E7EB;color:#374151' }}">{{ $i['type'] }}</span></td><td class="nw"><b>{{ $i['x']->report_code }}</b></td><td>{{ $i['x']->name }}</td><td>{{ $i['where'] }}</td><td>{{ $i['todo'] }}</td></tr>
    @empty<tr><td colspan="5" style="text-align:center;color:#166534;padding:16px">No issues of this kind.</td></tr>@endforelse
    </tbody></table></div>
</div>

<div class="box">
  <h2>Possible duplicates: side-by-side review</h2>
  <p class="mut" style="margin:0 0 8px">A same name does not prove the same report, and a different name does not prove a different one. These groups only <b>may</b> overlap. Route, controller and permission are read from the system; query, purpose and output still need a person to compare before anything is marked Legacy. Nothing is hidden or deleted.</p>
  @forelse($dups as $g => $list)
  <div style="margin:0 0 12px"><b>{{ ucwords(str_replace('-', ' ', $g)) }}</b> <span class="mut">· {{ count($list) }} reports</span>
    <div class="tw"><table class="g" style="min-width:760px;margin-top:4px">
      <thead><tr><th scope="col">Code</th><th scope="col">Report</th><th scope="col">Route</th><th scope="col">Controller · method</th><th scope="col">Module</th><th scope="col" class="c">Type</th><th scope="col">Exports</th><th scope="col">Query / purpose / output</th></tr></thead>
      <tbody>@foreach($list as $x)<tr><td class="nw"><b>{{ $x->code }}</b></td><td>{{ $x->name }}</td><td class="nw">{{ $x->path }}</td><td class="nw">{{ $x->action }}</td><td>{{ $x->module }}</td><td class="c">{{ $x->type }}</td><td class="nw">{{ $x->exports ?: 'None' }}</td><td class="mut">Needs review</td></tr>@endforeach</tbody>
    </table></div>
  </div>
  @empty<div class="mut">No candidates.</div>@endforelse
  <p class="mut" style="margin:0">Decision rule: if all six match, keep one visible report and mark the other <b>Legacy · Replaced By [code]</b>. If the calculation differs, keep both and rename each clearly.</p>
</div>
@endif
</div>
@endsection
