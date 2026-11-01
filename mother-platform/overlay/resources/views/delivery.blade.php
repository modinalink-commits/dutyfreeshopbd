@extends('layout')@section('title','Delivery Charge')
@section('body')
@php $tk=fn($n)=>'৳'.number_format((float)$n); $ed=$edit; @endphp
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Delivery Charge</h1><div style="font-size:12px;color:#6B7280">Courier &amp; Delivery — one charge system for website orders and admin-created orders</div></div>
<span style="font-size:11px;font-weight:700;border-radius:999px;padding:4px 12px;background:{{ $cfg['on'] ? '#DCFCE7' : '#FEE2E2' }};color:{{ $cfg['on'] ? '#166534' : '#B91C1C' }}">{{ $cfg['on'] ? 'DELIVERY CHARGE ON' : 'DELIVERY CHARGE OFF' }}</span></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card" style="color:#B91C1C">{{ $errors->first() }}</div>@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:12px">
@foreach([['Total Delivery Collected',$tk($kpi->collected)],['Paid Delivery Orders',$kpi->paid_n],['Free Delivery Orders',$kpi->free_n],['Average Charge',$tk($kpi->avg_ch)],['Delivery Waived',$tk($kpi->waived)],['Manual Adjustments',$kpi->manual_n]] as [$l,$v])<div class="card" style="margin:0;padding:10px 12px"><span style="font-size:11.5px;color:#6B7280;display:block">{{ $l }}</span><b style="font-size:18px">{{ $v }}</b></div>@endforeach</div>

<div class="card"><h2>Main settings</h2><form method="post" action="/admin/delivery/settings">@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;align-items:end">
<div><label>Delivery charge</label><select name="delv_on"><option value="1" @selected($cfg['on'])>ON</option><option value="0" @selected(!$cfg['on'])>OFF (no delivery charge)</option></select></div>
<div><label>Default delivery charge ৳</label><input type="number" min="0" step="0.01" name="delv_default" value="{{ $cfg['default'] }}"></div>
<div><label>Inside city charge ৳</label><input type="number" min="0" step="0.01" name="delv_inside" value="{{ $cfg['inside'] }}"></div>
<div><label>Outside city charge ৳</label><input type="number" min="0" step="0.01" name="delv_outside" value="{{ $cfg['outside'] }}"></div>
<div><label>Same day / Express charge ৳ (0 = hide option)</label><input type="number" min="0" step="0.01" name="delv_express" value="{{ $cfg['express'] }}"></div>
<div><label>Free delivery</label><select name="delv_free_on"><option value="1" @selected($cfg['free_on'])>ON</option><option value="0" @selected(!$cfg['free_on'])>OFF</option></select></div>
<div><label>Free delivery above ৳</label><input type="number" min="0" step="1" name="delv_free_min" value="{{ $cfg['free_min'] }}"></div></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:10px">
<div><label>Inside-city label</label><input name="delv_inside_label" value="{{ $cfg['l_in'] }}"></div><div><label>Outside-city label</label><input name="delv_outside_label" value="{{ $cfg['l_out'] }}"></div><div><label>Express label</label><input name="delv_express_label" value="{{ $cfg['l_ex'] }}"></div>
<div style="grid-column:1/-1"><label>Words that mean "inside city" in an address (comma separated)</label><input name="delv_inside_names" value="{{ implode(',', $cfg['names']) }}"></div></div>
<div style="margin-top:10px"><button>Save settings</button></div></form>
<p style="font-size:12px;color:#6B7280;margin:8px 0 0">Order of rules: matching zone (area &gt; thana &gt; district &gt; division) → inside/outside city → default. Product / category / campaign overrides apply only when you add them below. Free delivery above the minimum does not apply to Express. Rate changes affect new orders only; existing orders keep their saved charge.</p></div>

<div class="card" id="zones"><h2>Area-based delivery charges</h2>
<form method="post" action="/admin/delivery/zone{{ $ed ? '/'.$ed->id : '' }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">@csrf
<div><label>Level</label><select name="level">@foreach(['division'=>'Division','district'=>'District','thana'=>'Thana / Upazila','area'=>'Area / Zone'] as $k=>$l)<option value="{{ $k }}" @selected(($ed->level ?? 'district')===$k)>{{ $l }}</option>@endforeach</select></div>
<div><label>Name (as customers write it)</label><input name="name" required value="{{ $ed->name ?? '' }}" placeholder="e.g. Dhaka City"></div>
<div><label>Parent (optional)</label><input name="parent" value="{{ $ed->parent ?? '' }}" placeholder="e.g. Dhaka"></div>
<div><label>Charge ৳</label><input type="number" min="0" step="0.01" name="charge" required value="{{ $ed->charge ?? '' }}" style="width:100px"></div>
<div><label>Express ৳ (optional)</label><input type="number" min="0" step="0.01" name="express_charge" value="{{ $ed->express_charge ?? '' }}" style="width:110px"></div>
<label style="display:flex;gap:4px;align-items:center;margin:0 0 8px"><input type="checkbox" name="active" value="1" @checked($ed ? $ed->active : true)> Active</label>
<button>{{ $ed ? 'Update zone' : 'Add area' }}</button>@if($ed)<a href="/admin/delivery#zones" class="g" style="text-decoration:none">Cancel</a>@endif</form>
<div style="overflow:auto;margin-top:8px"><table><thead><tr><th>Level</th><th>Name</th><th>Parent</th><th class="n">Charge</th><th class="n">Express</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($zones as $z)<tr style="{{ $z->active ? '' : 'opacity:.55' }}"><td>{{ ucfirst($z->level) }}</td><td><b>{{ $z->name }}</b></td><td>{{ $z->parent }}</td><td class="n">{{ $tk($z->charge) }}</td><td class="n">{{ $z->express_charge!==null ? $tk($z->express_charge) : '—' }}</td><td>{{ $z->active ? 'Active' : 'Disabled' }}</td>
<td style="white-space:nowrap"><a href="/admin/delivery?edit={{ $z->id }}#zones">Edit</a> <form method="post" action="/admin/delivery/zone/{{ $z->id }}/act" style="display:inline">@csrf<button class="g" name="do" value="toggle">{{ $z->active ? 'Disable' : 'Enable' }}</button> <button class="g" name="do" value="delete" onclick="return confirm('Delete this area?')">Delete</button></form></td></tr>
@empty<tr><td colspan="7" style="color:#6B7280">No areas yet. Orders use the inside / outside / default charge above.</td></tr>@endforelse</tbody></table></div></div>

<div class="card" id="ov"><h2>Product / category / campaign overrides</h2>
<form method="post" action="/admin/delivery/override" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">@csrf
<div><label>Applies to</label><select name="kind" id="ovk" onchange="var k=this.value,c=document.getElementById('ovc'),q=document.getElementById('ovp');c.style.display=k==='category'?'':'none';q.style.display=k==='product'?'':'none';c.querySelector('select').disabled=k!=='category';q.querySelector('input').disabled=k!=='product'"><option value="product">Selected product</option><option value="category">Selected category</option><option value="campaign">Campaign (all orders in the date range)</option></select></div>
<div id="ovp"><label>Product ID / SKU / exact name</label><input name="ref"></div>
<div id="ovc" style="display:none"><label>Category</label><select name="ref" disabled><option value="">Choose</option>@foreach($cats as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
<div><label>Delivery</label><select name="mode"><option value="free">Free delivery</option><option value="custom">Custom charge</option></select></div>
<div><label>Custom ৳</label><input type="number" min="0" step="0.01" name="charge" style="width:90px"></div>
<div><label>Label</label><input name="label" placeholder="e.g. Eid free delivery"></div>
<div><label>From (Dhaka)</label><input type="datetime-local" name="starts_at"></div><div><label>To</label><input type="datetime-local" name="ends_at"></div><button>Add override</button></form>
<div style="overflow:auto;margin-top:8px"><table><thead><tr><th>Applies to</th><th>Delivery</th><th>Label</th><th>Period</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($ov as $x)<tr><td>{{ ucfirst($x->kind) }}{{ $x->ref_name ? ': '.$x->ref_name : '' }}</td><td>{{ $x->mode==='free' ? 'FREE' : $tk($x->charge) }}</td><td>{{ $x->label }}</td><td style="font-size:12px">{{ $x->starts_at ? \Carbon\Carbon::parse($x->starts_at,'UTC')->timezone('Asia/Dhaka')->format('d M H:i') : 'Always' }} → {{ $x->ends_at ? \Carbon\Carbon::parse($x->ends_at,'UTC')->timezone('Asia/Dhaka')->format('d M H:i') : 'No end' }}</td><td>{{ $x->active ? 'Active' : 'Paused' }}</td>
<td><form method="post" action="/admin/delivery/override/{{ $x->id }}/act">@csrf<button class="g" name="do" value="toggle">{{ $x->active ? 'Pause' : 'Enable' }}</button> <button class="g" name="do" value="delete" onclick="return confirm('Delete this override?')">Delete</button></form></td></tr>
@empty<tr><td colspan="6" style="color:#6B7280">No overrides — normal delivery charges apply to every order.</td></tr>@endforelse</tbody></table></div></div>

<div class="card"><h2>Area-wise delivery charges (orders)</h2><div style="overflow:auto"><table><thead><tr><th>Delivery zone</th><th class="n">Orders</th><th class="n">Collected</th><th class="n">Waived</th></tr></thead><tbody>
@forelse($areas as $a)<tr><td>{{ $a->z }}</td><td class="n">{{ $a->n }}</td><td class="n">{{ $tk($a->s) }}</td><td class="n">{{ $tk($a->w) }}</td></tr>@empty<tr><td colspan="4" style="color:#6B7280">Zone data appears here for new orders.</td></tr>@endforelse</tbody></table></div></div>
@endsection
