@extends('layout')@section('title','Staff Activity')
@section('body')
@php $fmt = fn($m) => $m === null ? '—' : ($m >= 1440 ? round($m/1440,1).' days' : ($m >= 60 ? round($m/60,1).' h' : round($m).' min')); @endphp
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:260px"><h1 style="margin:0">Staff Activity and Order Tracking</h1><span style="font-size:13px;color:#6B7280">Who touched which order, at what time, and how long each stage took. Every action is recorded and cannot be edited.</span></div>
<span style="display:inline-flex;gap:6px;font-size:12px;font-weight:600;color:#FF5A00;background:#FFF1E8;border-radius:8px;padding:6px 10px">⬇ Download: <a target="_blank" href="/admin/m/r-staff-log/export/pdf">PDF</a> · <a href="/admin/m/r-staff-log/export/xls">Excel</a> · <a href="/admin/m/r-staff-log/export/doc">Word</a> · <a href="/admin/m/r-staff-log/export/csv">CSV</a></span>
<form style="display:flex;gap:6px;margin:0"><select name="staff" style="width:auto"><option value="">All staff</option>@foreach($staffNames as $sn)<option {{ request('staff')===$sn?'selected':'' }}>{{ $sn }}</option>@endforeach</select><button class="g">Filter</button></form></div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:16px">@foreach($kpi as [$l,$v,$n])<div class="card k" style="margin:0"><span style="font-size:13px">{{ $l }}</span><b>{{ $v }}</b><span style="color:#FF5A00;font-weight:600">{{ $n }}</span></div>@endforeach</div>
<style>
.sl-g{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:start}
.sl-g>.card{margin:0;overflow-x:auto;padding:14px 16px}
.sl-g>.sl-w{grid-column:1/-1}
.sl-g .card>b{font-size:15px}
.sl-g table{width:100%;border-collapse:collapse;margin-top:8px}
.sl-g th{font-size:12px;color:#6B7280;font-weight:700;text-align:left;padding:9px 12px;background:#F8FAFC;border-bottom:1px solid #E5E7EB;white-space:nowrap}
.sl-g td{padding:10px 12px;border-bottom:1px solid #E5E7EB;vertical-align:middle;font-size:13px;line-height:1.35}
.sl-g tbody tr:last-child td{border-bottom:0}
.sl-g tbody tr:hover td{background:#FFFBF7}
.sl-g td.nw,.sl-g th.nw{white-space:nowrap}
.sl-g .pill{white-space:nowrap;display:inline-block;min-width:62px;text-align:center}
.sl-g th:first-child,.sl-g td:first-child{padding-left:12px}
@media (max-width:1100px){.sl-g{grid-template-columns:minmax(0,1fr)}}
</style>
<div class="sl-g">
<div class="card sl-w"><b>Live staff activity</b><table><thead><tr><th>Staff</th><th>Role</th><th>Now working on</th><th>Since</th><th>Today</th><th>Status</th></tr></thead><tbody>@foreach($live as $x)<tr><td class="nw"><b>{{ $x['name'] }}</b></td><td class="nw">{{ ucfirst($x['role']) }}</td><td>{{ $x['now'] }}</td><td class="nw">{{ $x['since'] }}</td><td class="nw">{{ $x['today'] }} orders</td><td class="nw"><span class="pill {{ $x['status']==='Active'?'ok':($x['status']==='Idle'?'':'red') }}">{{ $x['status'] }}</span></td></tr>@endforeach</tbody></table></div>
<div class="card"><b>Staff performance (today)</b><table><thead><tr><th>Staff</th><th>Orders</th><th>Avg confirm</th><th>Cancels</th><th>Returns</th></tr></thead><tbody>@forelse($perf as $p)<tr><td class="nw">{{ $p->user_name }}</td><td class="nw">{{ $p->n }}</td><td class="nw">{{ $fmt($p->conf) }}</td><td class="nw">{{ $p->cancels }}</td><td class="nw">{{ $p->rets }}</td></tr>@empty<tr><td colspan="5">No staff actions today.</td></tr>@endforelse</tbody></table></div>
<div class="card"><b>Rules</b><p style="font-size:13px;color:#6B7280;margin:6px 0">Orders waiting over 30 min in New or Pending appear on the dashboard under Needs attention. Staff cannot edit or delete log entries. Each order's full journey is on its order page.</p><b>Documents</b><table><tbody><tr><td>Staff activity report</td><td><a target="_blank" href="/admin/m/r-staff-log/export/pdf">PDF</a> · <a href="/admin/m/r-staff-log/export/xls">Excel</a></td></tr><tr><td>Audit log export</td><td><a href="/admin/m/audit-log/export/csv">CSV</a></td></tr></tbody></table></div>
<div class="card sl-w"><b>Time spent in each stage</b> <small style="color:#6B7280">all 18 stages tracked</small><table><thead><tr><th>Stage</th><th>Average time</th><th>Slowest</th><th>Orders</th></tr></thead><tbody>@forelse($stages as $s)<tr><td class="nw">{{ $s->from_status }} → {{ $s->to_status }}</td><td class="nw">{{ $fmt($s->av) }}</td><td class="nw">{{ $fmt($s->mx) }}</td><td class="nw">{{ $s->n }}</td></tr>@empty<tr><td colspan="4">Appears after orders move between stages.</td></tr>@endforelse</tbody></table></div>
</div>
<div class="card" style="margin-top:16px;overflow-x:auto"><b>Activity timeline (audit log)</b> <small style="color:#6B7280">append-only</small><table><thead><tr><th>Time</th><th>Staff</th><th>Order</th><th>From → To</th><th>Note</th></tr></thead><tbody>
@forelse($logs as $l)<tr><td>{{ \Carbon\Carbon::parse($l->created_at)->timezone('Asia/Dhaka')->format('d M H:i:s') }}</td><td>{{ $l->user_name }}</td><td><a href="/admin/orders/{{ $l->order_id }}">{{ $l->invoice }}</a></td><td>{{ $l->from_status ?: '—' }} → {{ $l->to_status }}</td><td>{{ $l->note }}</td></tr>@empty<tr><td colspan="5">Nothing yet.</td></tr>@endforelse</tbody></table>{{ $logs->links('pagination.global', ['perSel'=>true]) }}</div>
@endsection
