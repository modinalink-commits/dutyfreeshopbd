@extends('layout')@section('title','App Users')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">App Users</h1><div style="font-size:12px;color:#6B7280">Customers using the installed App — who is inside it right now, when they installed, last seen, device, push and reward.</div></div></div>
@include('components.app-live-card')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:12px">
@foreach([['App customers',number_format($st['users'])],['Logged in now',count($live['people'])],['Not logged in (live)',$live['guests']],['Push on (App customers)',number_format($st['push'])]] as [$l,$v])
<div class="card" style="margin:0;padding:10px 12px"><span style="font-size:11.5px;color:#6B7280;display:block">{{ $l }}</span><b style="font-size:18px">{{ $v }}</b></div>@endforeach</div>
<form class="card" style="display:flex;gap:8px;flex-wrap:wrap"><input name="q" value="{{ $f['q'] ?? '' }}" placeholder="Search name or mobile" style="flex:1;min-width:160px">
<select name="f" style="max-width:190px">@foreach([''=>'All installs','live'=>'Live now','users'=>'Customers only','guest'=>'Not logged in'] as $k=>$l)<option value="{{ $k }}" {{ ($f['f'] ?? '')===(string)$k?'selected':'' }}>{{ $l }}</option>@endforeach</select><button>Filter</button></form>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Customer</th><th>Status</th><th>Installed</th><th>Last seen</th><th>Device</th><th>Push</th><th>App orders</th><th>Reward</th></tr></thead><tbody>
@forelse($rows as $x)@php $live1 = $x->login_customer_id && $x->last_seen_at && strtotime((string)$x->last_seen_at) >= time() - \App\Support\AppUsers::LIVE_SEC; @endphp
<tr><td>@if($x->id)<a href="/admin/m/customers/{{ $x->id }}/edit"><b>{{ $x->name ?: $x->phone }}</b></a><br><small style="color:#6B7280">{{ $x->phone }}</small>@else<span style="color:#6B7280">Not logged in</span>@endif</td>
<td>{!! $live1 ? '<span class="pill ok">🟢 Live</span>' : '<span class="pill">Offline</span>' !!}</td>
<td>{{ $x->first_seen_at ? \Carbon\Carbon::parse($x->first_seen_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') : '—' }}</td>
<td>{{ \App\Support\AppUsers::ago($x->last_seen_at) }}</td><td>{{ \App\Support\AppUsers::device($x->ua) }}</td>
<td>{{ $x->_push > 0 ? '🔔 On' : '—' }}</td><td>{{ $x->_ord }}</td>
<td>{{ $x->_rw ? '৳'.number_format((float)$x->_rw->amount).' · '.ucfirst($x->_rw->status) : '—' }}</td></tr>
@empty<tr><td colspan="8">No App users yet.</td></tr>@endforelse</tbody></table>@if(method_exists($rows,'links')){{ $rows->links('pagination.global', ['perSel'=>true]) }}@endif</div>
@endsection
