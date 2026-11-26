@extends('layout')@section('title','Marine Supply')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Marine Supply</h1><div style="font-size:12px;color:#6B7280">marinedutyfreebd.com: B2B supply for vessels and crew. Everything for the marine business lives in this group.</div></div>
@if(!$ready)<div class="card" style="background:#FEF3C7;border-color:#FDE68A;color:#713F12">The marine tables are not ready yet. Open this page again when the deploy has finished.</div>
@else
@php $rv = \App\Support\MarineWeather::live() ? \App\Support\MarineWeather::review() : []; @endphp
@if($rv)<a class="card" href="/admin/marine/weather" style="display:block;text-decoration:none;background:#FFF7ED;border-color:#FDBA74;color:#7C2D12"><b>Review delivery time</b> · New official BMD warning for {{ implode(', ', array_unique(array_merge(...array_map(fn($w) => array_map('ucfirst', $w['ports']), $rv)))) }}. Re-confirm delivery times with the local shipping agent.</a>@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-bottom:12px">
<a class="card" href="/admin/marine/requests" style="margin:0;text-decoration:none;color:inherit"><div style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase">Pending access requests</div><div style="font-size:24px;font-weight:700;color:{{ ($n['pending'] ?? 0) ? '#C2410C' : '#0F172A' }}">{{ $n['pending'] ?? 0 }}</div><div style="font-size:12px;color:#6B7280">Approved {{ $n['approved'] ?? 0 }} · Rejected {{ $n['rejected'] ?? 0 }}</div></a>
<a class="card" href="/admin/marine/catalog?show=on" style="margin:0;text-decoration:none;color:inherit"><div style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase">Products on marine channel</div><div style="font-size:24px;font-weight:700">{{ $p['on'] }}</div><div style="font-size:12px;color:#6B7280">Public {{ $p['public'] }} · After login {{ $p['on'] - $p['public'] }}</div></a>
<a class="card" href="/admin/marine/catalog?show=on" style="margin:0;text-decoration:none;color:inherit"><div style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase">Without USD price</div><div style="font-size:24px;font-weight:700;color:{{ $p['noprice'] ? '#B45309' : '#0F172A' }}">{{ $p['noprice'] }}</div><div style="font-size:12px;color:#6B7280">On, but no USD price yet</div></a>
<a class="card" href="/admin/marine/settings" style="margin:0;text-decoration:none;color:inherit"><div style="font-size:11px;font-weight:600;color:#6B7280;text-transform:uppercase">Marine desk contact</div><div style="font-size:14px;font-weight:600;margin-top:6px">{{ $conf['marine_desk_email'] ?: 'No email set' }}</div><div style="font-size:12px;color:#6B7280">{{ $conf['marine_desk_phone'] ?: 'No phone set' }}</div></a>
</div>
<div class="card"><b>Latest access requests</b>
<table style="width:100%;font-size:13px;border-collapse:collapse;margin-top:8px">
@forelse($last as $x)<tr style="border-top:1px solid #EEE"><td style="padding:7px 4px"><b>{{ $x->company ?: $x->name ?: '-' }}</b> <span style="font-size:11.5px;color:#6B7280">{{ ucfirst($x->kind) }}</span></td><td>{{ $x->vessel }} {{ $x->imo ? '· IMO '.$x->imo : '' }}</td><td>{{ $x->email }}</td><td>{{ ucfirst($x->status) }}</td><td style="text-align:right;color:#6B7280">{{ \Carbon\Carbon::parse($x->created_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</td></tr>
@empty<tr><td style="padding:10px 4px;color:#6B7280">No requests yet.</td></tr>@endforelse
</table></div>
<div class="card"><b>Marine module map</b><div style="font-size:12px;color:#6B7280;margin:2px 0 10px">All marine work sits in this group, arranged by subject.</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:10px;font-size:13px">
<div style="border:1px solid #E5E7EB;border-radius:8px;padding:12px"><b>Requests &amp; Accounts</b><div style="line-height:1.9;margin-top:4px"><a href="/admin/marine/requests">Access Requests</a><br><span style="color:#9CA3AF">Companies &amp; Vessels (coming)</span><br><span style="color:#9CA3AF">Crew Accounts (coming)</span></div></div>
<div style="border:1px solid #E5E7EB;border-radius:8px;padding:12px"><b>Catalog &amp; Pricing</b><div style="line-height:1.9;margin-top:4px"><a href="/admin/marine/catalog">Marine Catalog</a><br><span style="color:#9CA3AF">Port Stock (coming)</span><br><span style="color:#9CA3AF">Contract Prices (coming)</span></div></div>
<div style="border:1px solid #E5E7EB;border-radius:8px;padding:12px"><b>Marine Website</b><div style="line-height:1.9;margin-top:4px"><a href="/admin/marine/site">Site Content &amp; Banners</a><br><a href="/admin/marine/ports">Ports</a><br><a href="/admin/marine/settings">Desk Contact</a></div></div>
<div style="border:1px solid #E5E7EB;border-radius:8px;padding:12px"><b>Operations</b><div style="line-height:1.9;margin-top:4px;color:#9CA3AF">Port Calls (coming)<br>Requisitions &amp; Quotations (coming)<br>Orders &amp; Delivery (coming)<br>Crew Orders (coming)</div></div>
<div style="border:1px solid #E5E7EB;border-radius:8px;padding:12px"><b>Finance &amp; Reports</b><div style="line-height:1.9;margin-top:4px;color:#9CA3AF">Credit &amp; Receivables (coming)<br>Shop Payable (coming)<br>Profit per Order (coming)</div></div>
</div></div>
<div class="card"><b>Links</b><div style="font-size:13px;line-height:1.8;margin-top:6px"><a href="{{ $site }}" target="_blank" rel="noopener">{{ $site }}</a> (public site) · <a href="https://search.google.com/search-console?resource_id=sc-domain:marinedutyfreebd.com" target="_blank" rel="noopener">Google Search Console</a></div></div>
@endif
@endsection
