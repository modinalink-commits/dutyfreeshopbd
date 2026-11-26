@extends('mp.layout')@section('title','My profile')
@section('body')
@php $T = ['owner' => 'Shipping owner', 'agency' => 'Local agency', 'vessel' => 'Vessel']; @endphp
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:18px"><div><h1>{{ $u->type === 'vessel' ? ($u->vessel ?: $u->name) : ($u->company ?: $u->name) }}</h1><p class="sub" style="margin:0">{{ $T[$u->type] ?? 'Account' }} profile{{ $u->type === 'vessel' && $u->vessel_role ? ' · ' . ($vroles[$u->vessel_role] ?? '') : '' }}</p></div><span class="pill">Active</span></div>
<div class="grid">
<section class="card" style="margin:0"><h2>{{ $u->type === 'owner' ? 'Company' : ($u->type === 'agency' ? 'Agency' : 'Vessel') }}</h2><dl class="dl">
@if($u->type === 'vessel')<dt>Vessel name</dt><dd>{{ $u->vessel ?: '-' }}</dd><dt>IMO number</dt><dd>{{ $u->imo ?: '-' }}</dd><dt>Owner company</dt><dd>{{ $u->company ?: '-' }}</dd>
@else<dt>{{ $u->type === 'owner' ? 'Company' : 'Agency' }}</dt><dd>{{ $u->company ?: '-' }}</dd>@endif
<dt>{{ $u->type === 'agency' ? 'Authorized port' : 'Port of call' }}</dt><dd>{{ $u->port ?: '-' }}</dd></dl></section>
<section class="card" style="margin:0"><h2>{{ $u->type === 'vessel' ? ($u->vessel_role === 'captain' ? 'Captain / authorized officer' : 'Crew member') : 'Authorized representative' }}</h2><dl class="dl">
<dt>Name</dt><dd>{{ $u->name ?: '-' }}</dd>@if($u->role)<dt>Role</dt><dd>{{ $u->role }}</dd>@endif @if($u->crew_rank)<dt>Rank</dt><dd>{{ $u->crew_rank }}</dd>@endif
<dt>Email</dt><dd>{{ $u->email }}</dd><dt>Phone</dt><dd>{{ $u->phone ?: '-' }}</dd><dt>Username</dt><dd>{{ $u->username }}</dd></dl></section>
</div>
@if($u->type !== 'vessel')
<section class="card" style="margin-top:16px"><h2>{{ $u->type === 'owner' ? 'Fleet: vessels by IMO number' : 'Vessels you are appointed for' }}</h2>
@if(count($ves))<table><tr><th>IMO</th><th>Vessel</th><th>Note</th></tr>@foreach($ves as $v)<tr><td>{{ $v->imo }}</td><td>{{ $v->name ?: '-' }}</td><td>{{ $v->note ?: '' }}</td></tr>@endforeach</table>
@else<p style="margin:0;color:#64748B;font-size:14px">No vessel is linked yet. {{ $u->type === 'owner' ? 'Send your fleet list to our marine desk and each IMO number is added after verification.' : 'Send the appointment letter for each vessel below; it is linked after verification.' }}</p>@endif</section>
@endif
<section class="card" style="margin-top:16px"><h2>{{ $u->type === 'owner' ? 'Requests, USD quotations and purchase orders' : ($u->type === 'agency' ? 'Port calls, documents and deliveries' : ($u->vessel_role === 'captain' ? 'Supply requisitions and order status' : 'Personal orders')) }}</h2>
<p style="margin:0;color:#64748B;font-size:14px">Nothing here yet. Ordering opens at launch; you will see only what belongs to {{ $u->type === 'owner' ? 'your own fleet' : ($u->type === 'agency' ? 'the vessels you are appointed for' : 'your own vessel and account') }}.</p></section>
<section class="card"><h2>Documents</h2>
@if(count($docs))<table><tr><th>File</th><th>Added</th></tr>@foreach($docs as $d)<tr><td><a href="/marine-portal/document/{{ $d->id }}">{{ $d->name }}</a></td><td style="white-space:nowrap">{{ \Carbon\Carbon::parse($d->created_at)->timezone('UTC')->format('d M Y') }}</td></tr>@endforeach</table>@else<p style="margin:0 0 4px;color:#64748B;font-size:14px">No document on file.</p>@endif
<form method="post" action="/marine-portal/documents" enctype="multipart/form-data" style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">@csrf<input type="file" name="docs[]" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple required style="flex:1 1 240px"><button class="g">Add documents</button></form>
<p class="hint">PDF, JPG, PNG or WEBP, up to 5 MB each.</p></section>
@endsection
