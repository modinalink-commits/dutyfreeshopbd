@extends('layout')@section('title', 'Staff Activity & Order Tracking')
@section('body')
<?php
use App\Support\StaffTrack as T;
use App\Support\Inv;
$qs = request()->only('range', 'from', 'to');
$u = fn($p, $extra = []) => url('/admin/staff-tracking/' . $p) . ($qs || $extra ? '?' . http_build_query(array_filter(array_merge($qs, $extra), fn($x) => $x !== null && $x !== '')) : '');
$isAct = in_array($page, ['activity', 'status', 'payment', 'courier', 'price', 'comm'], true);
$exp = in_array($page, ['activity', 'status', 'payment', 'courier', 'price', 'comm', 'logins', 'audit', 'reports'], true);
$rk = $rg[3]; $roles ??= [];
$f = fn($k) => request()->query($k);
?>
<style>
.stk{font-size:13px;color:#0B1220}
.stk .top{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px}
.stk .top h1{margin:0;font-size:20px}.stk .sub{color:#6B7280;font-size:12px}
.stk .tabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px}
.stk .tabs a{border:1px solid #FDC2A5;background:#fff;color:#0B1220;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap}
.stk .tabs a.on{background:#FF5A00;border-color:#FF5A00;color:#fff}
.stk form.flt{display:flex;flex-wrap:wrap;gap:8px;align-items:end;background:#fff;border:1px solid #FDC2A5;border-radius:12px;padding:10px 12px;margin-bottom:12px}
.stk form.flt label{margin:0 0 3px;font-size:11px}.stk form.flt select,.stk form.flt input{width:auto;min-width:120px;padding:7px 9px}
.stk .cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(165px,1fr));gap:10px;margin-bottom:14px}
.stk .cd{background:#fff;border:1px solid #FDC2A5;border-radius:12px;padding:10px 12px}.stk .cd span{font-size:12px;color:#6B7280;display:block}.stk .cd b{font-size:24px;display:block;line-height:1.2}.stk .cd small{color:#9CA3AF;font-size:11px}
.stk .cd.bad b{color:#B91C1C}
.stk .box{background:#fff;border:1px solid #FDC2A5;border-radius:12px;padding:12px 14px;margin-bottom:14px;overflow-x:auto}
.stk .box>b{font-size:14px}
.stk table{width:100%;border-collapse:collapse;margin-top:8px}
.stk th{font-size:11.5px;color:#6B7280;font-weight:700;text-align:left;padding:8px 10px;background:#F8FAFC;border-bottom:1px solid #E5E7EB;white-space:nowrap}
.stk td{padding:8px 10px;border-top:1px solid #F1F3F6;vertical-align:top;line-height:1.35}
.stk td.nw{white-space:nowrap}.stk tbody tr:hover td{background:#FFFBF7}
.stk .mut{color:#6B7280;font-size:11.5px}
.stk .pill.gr{background:#F3F4F6;color:#4B5563}
.stk .chg{display:inline-flex;gap:5px;align-items:center;flex-wrap:wrap}.stk .chg s{color:#9CA3AF}
.stk .tl{position:relative;margin:10px 0 0;padding-left:20px;border-left:2px solid #FDC2A5}
.stk .ev{position:relative;padding:0 0 14px 12px}.stk .ev:before{content:'';position:absolute;left:-27px;top:3px;width:12px;height:12px;border-radius:50%;background:#FF5A00;border:2px solid #fff;box-shadow:0 0 0 1px #FDC2A5}
.stk .ev.call:before{background:#16A34A}.stk .ev.sms:before{background:#4F46E5}.stk .ev.flag:before{background:#B91C1C}
.stk .ev.hl{background:#FFF7ED;border-radius:8px;padding:6px 8px 14px 12px}
.stk .ev .h{font-weight:700}.stk .rule{font-size:12px;color:#4B5563;margin:6px 0 0;padding-left:18px}
.stk .empty{padding:18px;text-align:center;color:#6B7280}
.stk .warn{background:#FEF9C3;border:1px solid #FDE68A;border-radius:10px;padding:10px 12px;margin-bottom:12px}
</style>
<div class="stk">
<div class="top"><div style="flex:1;min-width:240px"><h1>Staff Activity &amp; Order Tracking</h1><span class="sub">Who did what, on which order, when, from where. Entries are append-only: nobody (admins included) can edit or delete them.</span></div>
@if($exp && $ready)<a class="btn g" href="{{ url('/admin/staff-tracking-export/' . $page) }}?{{ http_build_query(request()->query()) }}">⬇ Export CSV</a>@endif</div>
<div class="tabs">@foreach($pages as $k => $l)<a class="{{ $k === $page ? 'on' : '' }}" href="{{ $u($k) }}">{{ $l }}</a>@endforeach<a href="{{ url('/admin/staff-log') }}">Live Staff Board</a></div>

@if(!$ready)<div class="box"><div class="empty">The activity log is not set up on this server yet.</div></div>
@else
<form class="flt" method="get" action="{{ url('/admin/staff-tracking/' . $page) }}">
  <div><label>Date range</label><select name="range" id="stk-range"><option value="today" {{ $rk === 'today' ? 'selected' : '' }}>Today</option><option value="yesterday" {{ $rk === 'yesterday' ? 'selected' : '' }}>Yesterday</option><option value="7" {{ $rk === '7' ? 'selected' : '' }}>Last 7 Days</option><option value="30" {{ $rk === '30' ? 'selected' : '' }}>Last 30 Days</option><option value="custom" {{ $rk === 'custom' ? 'selected' : '' }}>Custom</option></select></div>
  <div class="stk-c" style="{{ $rk === 'custom' ? '' : 'display:none' }}"><label>From</label><input type="date" name="from" value="{{ $rg[4] }}"></div>
  <div class="stk-c" style="{{ $rk === 'custom' ? '' : 'display:none' }}"><label>To</label><input type="date" name="to" value="{{ $rg[5] }}"></div>
  @if(!in_array($page, ['dashboard', 'reports']))
  <div><label>Staff</label><select name="staff"><option value="">All staff</option>@if($isAct || $page === 'orders')<option value="System" {{ $f('staff') === 'System' ? 'selected' : '' }}>System (automatic)</option>@endif @foreach($staffNames as $sn)<option {{ $f('staff') === $sn ? 'selected' : '' }}>{{ $sn }}</option>@endforeach</select></div>
  @endif
  @if($isAct && !empty($types))<div><label>Action type</label><select name="type"><option value="">All</option>@foreach($types as $t)<option value="{{ $t }}" {{ $f('type') === $t ? 'selected' : '' }}>{{ T::label($t) }}</option>@endforeach</select></div>@endif
  @if($page === 'logins')<div><label>Event</label><select name="event"><option value="">All</option>@foreach(['login' => 'Sign-in', 'failed' => 'Failed sign-in', 'logout' => 'Sign-out'] as $k => $l)<option value="{{ $k }}" {{ $f('event') === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select></div>@endif
  @if($isAct || in_array($page, ['orders', 'audit']))<div><label>{{ $page === 'audit' ? 'Search action / detail' : 'Order / customer / phone' }}</label><input type="text" name="q" value="{{ $f('q') }}" placeholder="{{ $page === 'audit' ? 'e.g. price' : 'Invoice, name or phone' }}"></div>@endif
  @if($page === 'activity')<div><label>&nbsp;</label><label style="display:flex;gap:5px;align-items:center;color:#0B1220;font-size:12px;margin:0;padding:8px 0"><input type="checkbox" name="flag" value="1" style="width:auto;min-width:0" {{ $f('flag') ? 'checked' : '' }}> Suspicious only</label></div>@endif
  <div><button>Apply</button></div>
</form>
<script>(function(){var s=document.getElementById('stk-range');if(!s)return;s.addEventListener('change',function(){document.querySelectorAll('.stk-c').forEach(function(e){e.style.display=s.value==='custom'?'':'none'})})})();</script>
<div class="mut" style="margin:-4px 0 10px">Showing <b>{{ $rg[2] }}</b> · {{ \Carbon\Carbon::parse($rg[4])->format('d M Y') }} – {{ \Carbon\Carbon::parse($rg[5])->format('d M Y') }} (Dhaka time)</div>

{{-- ============ DASHBOARD ============ --}}
@if($page === 'dashboard')
<div class="cards">@foreach($cards as [$l, $n, $s])<div class="cd {{ $l === 'Suspicious Actions' && $n > 0 ? 'bad' : '' }}"><span>{{ $l }}</span><b>{{ number_format($n) }}</b><small>{{ $s }}</small></div>@endforeach</div>
<div class="box"><b>Staff overview</b> <a class="mut" href="{{ $u('reports') }}">full report →</a>
<table><thead><tr><th>Staff</th><th>Actions</th><th>Orders</th><th>Confirmed</th><th>Cancelled</th><th>Edits</th><th>Payments</th><th>Discounts</th><th>Last action</th></tr></thead><tbody>
@forelse($summary as $x)<tr><td class="nw"><b>{{ $x->staff_name }}</b></td><td>{{ $x->actions }}</td><td>{{ $x->orders }}</td><td>{{ $x->confirmed }}</td><td>{{ $x->cancelled }}</td><td>{{ $x->edits }}</td><td>{{ $x->pay }}</td><td>{{ $x->disc }}</td><td class="nw">{{ T::dt($x->last_at) }}</td></tr>@empty<tr><td colspan="9" class="empty">No staff actions in this period.</td></tr>@endforelse</tbody></table></div>
<div class="box"><b>Latest suspicious actions</b> <a class="mut" href="{{ url('/admin/staff-tracking/activity') }}?{{ http_build_query(array_merge($qs, ['flag' => 1])) }}">see all →</a>
<ul class="rule" style="margin-bottom:8px"><li>Cancelled after Shipment / Delivered, or a status moved backwards (e.g. Delivered → New), or a Returned order re-opened</li><li>Manual override, large payment verified (৳20,000+), discount raised by ৳500+ on an order</li><li>Any staff edit made between 12:00 AM and 5:00 AM (Dhaka)</li></ul>
@include('staff_tracking_rows', ['list' => $sus, 'flagsOnly' => true])</div>

{{-- ============ ORDER TRACKING ============ --}}
@elseif($page === 'orders' && isset($order))
@php $o = $order; @endphp
<div class="box"><div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center"><div style="flex:1;min-width:240px"><b style="font-size:16px">Invoice {{ Inv::show($o->invoice) }}</b> <span class="pill">{{ $o->status }}</span><div class="mut">{{ $o->customer_name }} · {{ $o->phone }} · Total ৳{{ number_format((float)$o->total) }} · Courier: {{ $o->courier ?: '—' }} · Created {{ T::dt($o->created_at) }}</div></div>
<a class="btn g" href="{{ url('/admin/orders/' . $o->id) }}">Open order</a>
<a class="btn g" href="{{ $u('orders', ['order' => $o->id, 'sort' => $asc ? 'desc' : 'asc']) }}">{{ $asc ? '↓ Newest first' : '↑ Oldest first' }}</a>
<a class="btn g" href="{{ $u('orders') }}">← All orders</a></div></div>
<div class="box"><b>Activity timeline</b> <span class="mut">{{ $asc ? 'oldest first' : 'newest first' }} · {{ $events->count() }} entries (activity, calls and SMS)</span>
<div class="tl">@forelse($events as $e)
<div class="ev {{ $e->kind }} {{ $e->flag ? 'flag' : '' }} {{ (string)$e->id === $hl ? 'hl' : '' }}" id="a{{ $e->id }}">
  <div class="h">{{ $e->label }} @if($e->flag)<span class="pill red">⚠ {{ $e->flag }}</span>@endif</div>
  <div class="mut">{{ T::dt($e->at) }} · by <b>{{ $e->who ?: 'System' }}</b>@if($e->source === 'system') (automatic)@endif @if($e->device)· {{ $e->device }}@endif</div>
  @if($e->old !== null && $e->old !== '' || $e->new !== null && $e->new !== '')<div class="chg" style="margin-top:3px">@if($e->old !== null && $e->old !== '')<span class="pill gr"><s>{{ \Illuminate\Support\Str::limit($e->old, 80) }}</s></span> →@endif<span class="pill ok">{{ \Illuminate\Support\Str::limit((string)$e->new, 80) }}</span></div>@endif
  @if($e->desc)<div style="margin-top:3px">{{ \Illuminate\Support\Str::limit(Inv::strip((string)$e->desc), 300) }}</div>@endif
</div>@empty<div class="empty">No activity recorded for this order.</div>@endforelse</div></div>

@elseif($page === 'orders')
@if($notFound)<div class="warn">No tracked activity matched “{{ $f('q') }}” in this period. Try a wider date range.</div>@endif
<div class="box"><b>Orders with staff activity</b> <span class="mut">click an order to see its full timeline</span>
<table><thead><tr><th>Invoice</th><th>Customer</th><th>Status</th><th>Entries</th><th>Staff involved</th><th>Last action</th><th>Last by</th><th>View</th></tr></thead><tbody>
@forelse($rows as $x)@php $l = $last[$x->order_id] ?? null; @endphp<tr><td class="nw"><a href="{{ $u('orders', ['order' => $x->order_id]) }}"><b>{{ Inv::show($x->invoice) }}</b></a></td><td>{{ $x->customer_name }}<div class="mut">{{ $x->phone }}</div></td><td class="nw"><span class="pill">{{ $x->status }}</span></td><td>{{ $x->n }}</td><td>{{ $x->staffs }}</td><td class="nw">{{ T::dt($x->last_at) }}@if($l)<div class="mut">{{ T::label($l->action_type) }}@if($l->action_type === 'status_changed') → {{ $l->new_value }}@endif</div>@endif</td><td class="nw">{{ $l->staff_name ?? '—' }}</td><td><a href="{{ $u('orders', ['order' => $x->order_id]) }}">Timeline</a></td></tr>@empty<tr><td colspan="8" class="empty">No orders with activity in this period.</td></tr>@endforelse</tbody></table>
{{ $rows->links('pagination.global', ['perSel' => true]) }}</div>

{{-- ============ ACTIVITY TABLES ============ --}}
@elseif($isAct)
<div class="box">
@if(!empty($flagMode))<b>Suspicious actions</b> <span class="mut">{{ count($flags) }} found (newest 300 shown)</span>
@include('staff_tracking_rows', ['list' => $flags, 'flagsOnly' => true])
@else
<b>{{ $title }}</b> <span class="mut">{{ $rows->total() }} entries</span>
@include('staff_tracking_rows', ['list' => $rows, 'flagsOnly' => false])
{{ $rows->links('pagination.global', ['perSel' => true]) }}
@endif</div>

{{-- ============ LOGINS ============ --}}
@elseif($page === 'logins')
@if(!$ok)<div class="warn">Sign-in history starts recording after the next deploy finishes. Nothing earlier is available.</div>
@else
<div class="box"><b>Sessions by staff</b>
<table><thead><tr><th>Staff</th><th>Sign-ins</th><th>Failed</th><th>Sign-outs</th><th>Different IPs</th><th>Last sign-in</th><th>Last sign-out</th></tr></thead><tbody>
@forelse($sess as $x)<tr><td class="nw"><b>{{ $x->who }}</b></td><td>{{ $x->logins }}</td><td>@if($x->failed)<span class="pill red">{{ $x->failed }}</span>@else 0 @endif</td><td>{{ $x->logouts }}</td><td>{{ $x->ips }}</td><td class="nw">{{ T::dt($x->last_login) }}</td><td class="nw">{{ T::dt($x->last_logout) }}</td></tr>@empty<tr><td colspan="7" class="empty">No sign-in events in this period yet.</td></tr>@endforelse</tbody></table></div>
<div class="box"><b>Sign-in history</b> <span class="mut">{{ $rows->total() }} events</span>
<table><thead><tr><th>Date &amp; Time</th><th>Staff</th><th>Email</th><th>Event</th><th>IP / Device</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td class="nw">{{ T::dt($x->created_at) }}</td><td>{{ $x->user_name ?: '—' }}</td><td>{{ $x->email }}</td><td class="nw"><span class="pill {{ $x->event === 'failed' ? 'red' : ($x->event === 'login' ? 'ok' : 'gr') }}">{{ ['login' => 'Sign-in', 'failed' => 'Failed sign-in', 'logout' => 'Sign-out'][$x->event] ?? $x->event }}</span></td><td>{{ T::device($x->user_agent, $x->ip_address) }}</td></tr>@empty<tr><td colspan="5" class="empty">Nothing recorded in this period.</td></tr>@endforelse</tbody></table>
{{ $rows->links('pagination.global', ['perSel' => true]) }}</div>
@endif

{{-- ============ AUDIT ============ --}}
@elseif($page === 'audit')
<div class="box"><b>Audit logs</b> <span class="mut">{{ $rows->total() }} entries · system-wide changes (prices, settings, rules, AI runs…)</span>
<table><thead><tr><th>Date &amp; Time</th><th>Staff</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td class="nw">{{ T::dt($x->created_at) }}</td><td class="nw">{{ $x->user_name ?: 'System' }}</td><td>{{ $x->action }}</td><td>{{ \Illuminate\Support\Str::limit(Inv::strip((string)$x->subject), 260) }}</td><td class="nw">{{ $x->ip }}</td></tr>@empty<tr><td colspan="5" class="empty">Nothing recorded in this period.</td></tr>@endforelse</tbody></table>
{{ $rows->links('pagination.global', ['perSel' => true]) }}</div>

{{-- ============ REPORTS ============ --}}
@elseif($page === 'reports')
<div class="box"><b>Staff report</b> <span class="mut">{{ $rg[2] }}</span>
<table><thead><tr><th>Staff</th><th>Actions</th><th>Orders</th><th>Confirmed</th><th>Cancelled</th><th>Status changes</th><th>Order edits</th><th>Payment</th><th>Discount</th><th>Courier</th><th>Communication</th><th>Last action</th></tr></thead><tbody>
@forelse($summary as $x)<tr><td class="nw"><b>{{ $x->staff_name }}</b></td><td>{{ $x->actions }}</td><td>{{ $x->orders }}</td><td>{{ $x->confirmed }}</td><td>{{ $x->cancelled }}</td><td>{{ $x->status_ch }}</td><td>{{ $x->edits }}</td><td>{{ $x->pay }}</td><td>{{ $x->disc }}</td><td>{{ $x->courier }}</td><td>{{ $x->comm }}</td><td class="nw">{{ T::dt($x->last_at) }}</td></tr>@empty<tr><td colspan="12" class="empty">No staff actions in this period.</td></tr>@endforelse</tbody></table></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px">
<div class="box"><b>By day</b><table><thead><tr><th>Date</th><th>Actions</th><th>Staff</th><th>Orders</th></tr></thead><tbody>@forelse($daily as $x)<tr><td class="nw">{{ \Carbon\Carbon::parse($x->d)->format('d M Y') }}</td><td>{{ $x->actions }}</td><td>{{ $x->staff }}</td><td>{{ $x->orders }}</td></tr>@empty<tr><td colspan="4" class="empty">No data.</td></tr>@endforelse</tbody></table></div>
<div class="box"><b>By action type</b><table><thead><tr><th>Action</th><th>Count</th></tr></thead><tbody>@forelse($types as $x)<tr><td>{{ T::label($x->action_type) }}</td><td>{{ $x->n }}</td></tr>@empty<tr><td colspan="2" class="empty">No data.</td></tr>@endforelse</tbody></table></div>
</div>
@endif
@endif
</div>
@endsection
