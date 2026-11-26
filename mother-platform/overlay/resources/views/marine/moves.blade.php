@extends('layout')@section('title','Port Arrivals & Departures')
@section('body')
@php
$bt = fn($t) => $t ? \Carbon\Carbon::parse($t, 'UTC')->timezone('Asia/Dhaka')->format('d M Y, H:i') : '-';
$col = ['draft' => ['#F3F4F6', '#374151'], 'verified' => ['#DBEAFE', '#1E40AF'], 'published' => ['#DCFCE7', '#166534']];
@endphp
<div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:10px"><div><h1 style="margin:0">Port Arrivals &amp; Departures</h1><div style="font-size:12px;color:#6B7280">Confirmed vessel arrivals and departures at Chattogram, Mongla and Payra. Only Published entries are shown on the marine site. Times are Bangladesh time (UTC+6).</div></div>
@if($ready ?? false)<a class="btn" href="/admin/marine/movements?new=1#form">Add arrival or departure</a>@endif</div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
@if(session('bad'))<div class="card" style="background:#FEE2E2;border-color:#FECACA;color:#991B1B">{{ session('bad') }}</div>@endif
@if(!$ready)<div class="card" style="background:#FEF3C7;border-color:#FDE68A;color:#713F12">The tables are not ready yet. Open this page again when the deploy has finished.</div>
@else
<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
@foreach(['all' => 'All'] + $status as $k => $l)<a href="/admin/marine/movements?status={{ $k }}" style="font-size:12px;padding:5px 12px;border-radius:999px;text-decoration:none;border:1px solid {{ $st === $k ? '#0C161F' : '#D1D5DB' }};background:{{ $st === $k ? '#0C161F' : '#FFFFFF' }};color:{{ $st === $k ? '#FFFFFF' : '#0C161F' }}">{{ $l }}@if($k !== 'all') ({{ $n[$k] ?? 0 }})@endif</a>@endforeach
<form method="get" action="/admin/marine/movements" style="margin:0 0 0 auto;display:flex;gap:6px"><input type="hidden" name="status" value="{{ $st }}"><input name="q" value="{{ $q }}" placeholder="Vessel name or IMO" style="font-size:12px;width:180px"><button class="btn g" style="padding:5px 12px;font-size:12px">Search</button></form></div>
<div class="card" style="padding:0;overflow:auto"><table style="width:100%;min-width:1040px;font-size:13px;border-collapse:collapse">
<thead><tr style="text-align:left;color:#6B7280;font-size:11px;text-transform:uppercase"><th style="padding:8px 10px;width:76px">Photo</th><th style="padding:8px 10px">Vessel</th><th style="padding:8px 10px">Port visit</th><th style="padding:8px 10px">Actual arrival</th><th style="padding:8px 10px">Actual departure</th><th style="padding:8px 10px">Status and record</th><th style="padding:8px 10px;width:230px">Action</th></tr></thead><tbody>
@forelse($rows as $v)
@php $p = $photos[$v->imo] ?? null; $pu = $p ? ($p->path ? '/marine-api/vessel-photo/' . $v->imo . '?v=' . strtotime((string)$p->updated_at) : $p->source_url) : null; $c = $col[$v->status] ?? $col['draft']; @endphp
<tr style="border-top:1px solid #E5E7EB;vertical-align:top">
<td style="padding:8px 10px">@if($pu && ($p->path ? $v->status === 'published' : true))<img src="{{ $pu }}" alt="" loading="lazy" style="width:64px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #E5E7EB">@elseif($p)<div style="width:64px;height:44px;border-radius:6px;background:#EFF6FF;border:1px solid #BFDBFE;font-size:10px;color:#1E40AF;display:flex;align-items:center;justify-content:center;text-align:center">Photo on file</div>@else<div style="width:64px;height:44px;border-radius:6px;background:#F3F4F6;border:1px dashed #D1D5DB;font-size:10px;color:#6B7280;display:flex;align-items:center;justify-content:center;text-align:center">No photo</div>@endif</td>
<td style="padding:8px 10px"><b>{{ $v->vessel }}</b><div style="font-size:11.5px;color:#6B7280">IMO {{ $v->imo }}{{ $v->mmsi ? ' · MMSI ' . $v->mmsi : '' }}{{ $v->vessel_type ? ' · ' . $v->vessel_type : '' }}</div>@if($p)<div style="font-size:11px;color:#6B7280">Photo: {{ $p->credit }}</div>@endif</td>
<td style="padding:8px 10px">{{ $ports[$v->port] ?? $v->port }}<div style="font-size:11.5px;color:#6B7280">{{ $v->visit_ref ? 'Ref ' . $v->visit_ref : 'No visit reference' }}{{ $v->berth ? ' · ' . $v->berth : '' }}</div></td>
<td style="padding:8px 10px">{{ $bt($v->arrived_at) }}@if($v->arrived_at)<div style="font-size:11.5px;color:#6B7280">{{ $sources[$v->arrival_source] ?? '-' }}{{ $v->arrival_ref ? ' · ' . $v->arrival_ref : '' }}</div>@endif</td>
<td style="padding:8px 10px">{{ $bt($v->departed_at) }}@if($v->departed_at)<div style="font-size:11.5px;color:#6B7280">{{ $sources[$v->departure_source] ?? '-' }}{{ $v->departure_ref ? ' · ' . $v->departure_ref : '' }}</div>@endif</td>
<td style="padding:8px 10px;font-size:12px"><span style="display:inline-block;padding:2px 9px;border-radius:999px;font-weight:600;background:{{ $c[0] }};color:{{ $c[1] }}">{{ $status[$v->status] ?? $v->status }}</span>
@if($v->verified_at)<div style="color:#6B7280">Verified by {{ $v->verified_by }} · {{ $bt($v->verified_at) }}</div>@endif @if($v->published_at)<div style="color:#6B7280">Published by {{ $v->published_by }} · {{ $bt($v->published_at) }}</div>@endif
<div style="color:#6B7280">Entered by {{ $v->created_by }}</div>@foreach($docs[$v->id] ?? [] as $d)<div><a href="/admin/marine/movements/doc/{{ $d->id }}">{{ \Illuminate\Support\Str::limit($d->name, 26) }}</a></div>@endforeach
@if($v->internal_note)<div style="color:#374151;max-width:220px">Note: {{ \Illuminate\Support\Str::limit($v->internal_note, 90) }}</div>@endif</td>
<td style="padding:8px 10px"><div style="display:flex;gap:6px;flex-wrap:wrap"><a class="btn g" style="padding:5px 10px;font-size:12px" href="/admin/marine/movements?edit={{ $v->id }}#form">Edit</a>
<form method="post" action="/admin/marine/movements/{{ $v->id }}/act" style="margin:0;display:flex;gap:6px;flex-wrap:wrap">@csrf
@if($v->status === 'draft')<button name="action" value="verify" class="btn" style="padding:5px 10px;font-size:12px">Mark Verified</button><button name="action" value="delete" class="btn g" style="padding:5px 10px;font-size:12px;color:#991B1B">Delete draft</button>@endif
@if($v->status === 'verified')<button name="action" value="publish" class="btn" style="padding:5px 10px;font-size:12px">Publish</button>@endif
@if($v->status === 'published')<button name="action" value="unpublish" class="btn g" style="padding:5px 10px;font-size:12px;color:#991B1B">Take off the site</button>@endif
</form></div></td></tr>
@empty<tr><td colspan="7" style="padding:16px 10px;color:#6B7280">No entry yet. Use "Add arrival or departure" when the port record or the local agent confirms an event.</td></tr>@endforelse</tbody></table></div>
<div style="margin-top:10px">{{ $rows->links('pagination.global') }}</div>

@if($edit || $new)
@php $e = $edit; $ph = $e ? ($photos[$e->imo] ?? null) : null; $val = fn($k, $d = '') => old($k, $e->$k ?? $d); $lt = fn($k) => old($k, ($e && $e->$k) ? \App\Support\MarineMoves::local($e->$k) : ''); @endphp
<form id="form" method="post" action="/admin/marine/movements{{ $e ? '/' . $e->id : '' }}" enctype="multipart/form-data" class="card" style="margin-top:16px;max-width:980px">@csrf
<h2 style="margin:0 0 4px;font-size:16px">{{ $e ? 'Edit visit #' . $e->id : 'Add arrival or departure' }}</h2>
<div style="font-size:12px;color:#6B7280;margin-bottom:12px">Record an event only when the source confirms it. Expected times belong in Port Calls. Arrival and departure of one stay are kept on the same visit.</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px">
<div style="grid-column:1/-1;font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280">Vessel details</div>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Vessel name<input name="vessel" required maxlength="120" value="{{ $val('vessel') }}"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">IMO number (7 digits)<input name="imo" required inputmode="numeric" maxlength="7" pattern="[0-9]{7}" value="{{ $val('imo') }}"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">MMSI (optional)<input name="mmsi" inputmode="numeric" maxlength="9" value="{{ $val('mmsi') }}"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Vessel type (optional)<input name="vessel_type" maxlength="60" placeholder="Bulk carrier, Container ship…" value="{{ $val('vessel_type') }}"></label>
<div style="grid-column:1/-1;font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280;margin-top:6px">Port call</div>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Port<select name="port" required>@foreach($ports as $k => $l)<option value="{{ $k }}" @selected($val('port') === $k)>{{ $l }}</option>@endforeach</select></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Visit reference<input name="visit_ref" maxlength="60" placeholder="Port rotation or call number" value="{{ $val('visit_ref') }}"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Berth or anchorage (if known)<input name="berth" maxlength="120" value="{{ $val('berth') }}"></label>
@foreach(['arrival' => ['arrived_at', 'Actual arrival'], 'departure' => ['departed_at', 'Actual departure']] as $k => $x)
<div style="grid-column:1/-1;font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280;margin-top:6px">{{ $x[1] }}</div>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">{{ $x[1] }} date and time (Bangladesh)<input type="datetime-local" name="{{ $x[0] }}" value="{{ $lt($x[0]) }}"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Confirmation source<select name="{{ $k }}_source"><option value="">Choose…</option>@foreach($sources as $sk => $sl)<option value="{{ $sk }}" @selected($val($k . '_source') === $sk)>{{ $sl }}</option>@endforeach</select></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Source evidence<input name="{{ $k }}_ref" maxlength="200" placeholder="Record number, agent name, or feed reference" value="{{ $val($k . '_ref') }}"></label>
@endforeach
<div style="grid-column:1/-1;font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280;margin-top:6px">Vessel photo (linked to the IMO)</div>
<div style="grid-column:1/-1;font-size:12px;color:#6B7280">@if($ph)A photo is on file for this IMO (credit: {{ $ph->credit }}, approved by {{ $ph->approved_by }}). Upload again only to replace it.@else No verified photo yet: the site shows a labelled placeholder. Upload a photo you are allowed to use, or link an authorised source.@endif</div>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Upload photo (JPG, PNG, WEBP, 5 MB)<input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Or link to an authorised photo (https)<input name="photo_url" maxlength="400" placeholder="https://…/photo.jpg" value="{{ old('photo_url', ($ph && !$ph->path) ? $ph->source_url : '') }}"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Photo credit<input name="photo_credit" maxlength="160" placeholder="Photographer or owner of the photo" value="{{ old('photo_credit', $ph->credit ?? '') }}"></label>
<div style="grid-column:1/-1;font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280;margin-top:6px">Kept in the admin only</div>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Supporting documents (up to 6)<input type="file" name="docs[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.csv,.docx,.doc,.txt"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px;grid-column:span 2">Internal note<textarea name="internal_note" rows="2" maxlength="1000">{{ $val('internal_note') }}</textarea></label>
</div>
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:14px"><button class="btn">Save</button><a class="btn g" href="/admin/marine/movements">Close</a>
<span style="font-size:12px;color:#6B7280">@if($e)Status: {{ $status[$e->status] }}. Changing the vessel, port, a time, a source or its evidence sends the entry back to Draft.@else New entries are saved as Draft. Mark Verified, then Publish, from the list.@endif</span></div>
</form>
@endif
@endif
@endsection
