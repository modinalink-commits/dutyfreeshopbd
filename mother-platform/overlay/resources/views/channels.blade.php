@extends('layout')@section('title','Sales Channels')
@section('body')
<style>
.ch .row{display:grid;grid-template-columns:minmax(0,2.2fr) minmax(0,1.6fr) 110px minmax(0,1.4fr) 120px;gap:10px;align-items:center;padding:9px 12px;border-bottom:1px solid #E3E6EC;font-size:13px}
.ch .hd{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6B7280;background:#F7F8FA;font-weight:700}
.ch .tb{background:#fff;border:1px solid #E3E6EC;border-radius:10px;overflow:hidden;box-shadow:0 1px 2px rgba(15,27,51,.04)}
.ch .lg{width:34px;height:34px;border-radius:8px;border:1px solid #E3E6EC;display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;overflow:hidden;flex:none}.ch .lg img{width:100%;height:100%;object-fit:contain;background:#fff}
.ch .st{display:inline-block;border-radius:999px;padding:2px 9px;font-size:11px;font-weight:700}
.ch .st.draft{background:#F3F4F6;color:#4B5563}.ch .st.preview{background:#FFF1E8;color:#F95505}.ch .st.live{background:#DCFCE7;color:#166534}.ch .st.paused{background:#FEE2E2;color:#B91C1C}
.ch .sw{display:inline-block;width:14px;height:14px;border-radius:4px;border:1px solid #E3E6EC;vertical-align:middle}
@media(max-width:760px){.ch .row{grid-template-columns:minmax(0,1fr) auto}.ch .hd{display:none}.ch .hm{display:none}}
</style>
<div class="ch">
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px"><div style="flex:1;min-width:240px"><h1 style="margin:0;font-size:20px">Sales Channels</h1><span style="font-size:12.5px;color:#6B7280">Each channel is its own storefront (domain + design). Orders, payments, SMS, WhatsApp and numbers stay on the one DutyFreeBD setup.</span></div><a class="btn" href="/admin/channels/new">+ Add Channel</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<div class="tb">
<div class="row hd"><div>Channel</div><div>Domain</div><div>Status</div><div class="hm">Categories shown</div><div></div></div>
@forelse($rows as $c)@php $s = $set[$c->id]; $nm = trim($c->name); $cl = []; foreach ($s['cats'] as $x) { $n = $catNames[(int)$x['id']] ?? null; if ($n) $cl[] = $n . ((float)($x['adj'] ?? 0) != 0 ? ' (' . ((float)$x['adj'] > 0 ? '+' : '') . ((float)$x['adj'] + 0) . '%)' : ''); } @endphp
<div class="row"><div style="display:flex;gap:10px;align-items:center;min-width:0"><span class="lg" style="background:{{ $s['brand']['primary'] }}">@if($s['brand']['logo_url'])<img src="{{ $s['brand']['logo_url'] }}" alt="">@else{{ mb_strtoupper(mb_substr($nm,0,1)) }}@endif</span><div style="min-width:0"><b>{{ $c->name }}</b><div style="font-size:11.5px;color:#6B7280"><span class="sw" style="background:{{ $s['brand']['primary'] }}"></span> <span class="sw" style="background:{{ $s['brand']['accent'] }}"></span> {{ ucfirst($s['brand']['theme']) }}</div></div></div>
<div style="min-width:0;overflow-wrap:anywhere">{{ $c->domain ?: '—' }}</div>
<div><span class="st {{ $c->status ?: 'draft' }}">{{ $statuses[$c->status ?: 'draft'] ?? 'Draft' }}</span></div>
<div class="hm" style="color:#4B5563">{{ $cl ? implode(', ', $cl) : 'None allowed' }}</div>
<div style="text-align:right"><a class="btn g" href="/admin/channels/{{ $c->id }}/edit">Edit Settings</a></div></div>
@empty<div style="padding:26px;text-align:center;color:#6B7280">No channels yet. Press "+ Add Channel".</div>@endforelse
</div>
<div style="font-size:12px;color:#6B7280;margin-top:10px">Orders from each channel will show a badge and filter in Order Management (next step).</div>
</div>
@endsection
