@extends('layout')@section('title','Website and Content')
@section('body')
<form method="post" action="/admin/site">@csrf
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:260px"><h1 style="margin:0">Website and Content</h1><span style="font-size:13px;color:#6B7280">Everything a customer sees on the storefront is controlled here. Edit, then publish.</span></div>
<span style="display:inline-flex;gap:6px;font-size:12px;font-weight:600;color:#FF5A00;background:#FFF1E8;border-radius:8px;padding:6px 10px">⬇ Download: <a target="_blank" href="/admin/m/settings/export/pdf">PDF</a> · <a href="/admin/m/settings/export/xls">Excel</a> · <a href="/admin/m/settings/export/doc">Word</a> · <a href="/admin/m/settings/export/csv">CSV</a></span>
<a class="btn g" href="/" target="_blank">Preview store</a><button>Publish changes</button></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<div class="card" style="display:flex;flex-wrap:wrap;gap:16px;align-items:center"><span>Editing store: <b>DutyFreeBD</b></span><span style="font-size:13px;color:#6B7280">Theme: orange and navy. Each store has its own theme and content.</span><span style="margin-left:auto;font-size:13px;color:#6B7280">Last published: {{ $last ? \Carbon\Carbon::parse($last->created_at)->timezone('Asia/Dhaka')->format('d M, H:i').' by '.$last->user_name : 'never' }}</span></div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(360px,1fr))">
@foreach($groups as $g=>$items)<div class="card" style="margin:0"><b>{{ $g }}</b>
@foreach($items as $it)@if($it['t']==='bool')<label style="display:flex;gap:8px;align-items:center;font-size:13px;color:#0B1220;margin:8px 0 0"><input type="checkbox" name="s[{{ $it['k'] }}]" value="1" {{ $it['v']==='1'?'checked':'' }} style="width:auto">{{ $it['l'] }}</label>
@elseif($it['t']==='textarea')<label>{{ $it['l'] }}</label><textarea name="s[{{ $it['k'] }}]" rows="3">{{ $it['v'] }}</textarea>
@elseif(str_starts_with($it['t'],'select:'))<label>{{ $it['l'] }}</label><select name="s[{{ $it['k'] }}]">@foreach(explode(',', substr($it['t'],7)) as $op)@php [$ov,$ol] = explode('=',$op); @endphp<option value="{{ $ov }}" {{ $it['v']===$ov?'selected':'' }}>{{ $ol }}</option>@endforeach</select>
@else<label>{{ $it['l'] }}</label><input type="{{ $it['t']==='number'?'number':'text' }}" name="s[{{ $it['k'] }}]" value="{{ $it['v'] }}">@endif
@endforeach
@if($g==='Header and main menu')<p style="font-size:12px;color:#6B7280;margin-top:10px">Menu tree: {{ $cats->pluck('name')->map(fn($n) => strtoupper($n))->implode(' · ') ?: 'add top categories in Category tree' }} — <a href="/admin/taxonomy">edit order</a></p>@endif
@if($g==='Checkout and delivery')<p style="font-size:12px;color:#6B7280;margin-top:10px">Delivery charges are rules: <a href="/admin/m/districts">charge by district</a> and free-delivery rules in the <a href="/admin/discounts">Discount Center</a>.</p>@endif
</div>@endforeach
</div>
<div style="margin:16px 0"><button>Publish changes</button></div></form>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(360px,1fr))">
<div class="card" style="margin:0"><div style="display:flex"><b style="flex:1">Hero slider and banners</b><a href="/admin/m/sliders/new" style="font-size:13px;color:#FF5A00;font-weight:600">Add slide</a></div><table><thead><tr><th>Slide</th><th>Link to</th><th>Status</th></tr></thead><tbody>@forelse($sliders as $s)<tr><td>{{ \Illuminate\Support\Str::limit($s->image, 30) }}</td><td>{{ $s->link }}</td><td><span class="pill {{ $s->active?'ok':'' }}">{{ $s->active?'Live':'Off' }}</span></td></tr>@empty<tr><td colspan="3">No slides yet.</td></tr>@endforelse
@foreach($banners as $b)<tr><td>Banner · {{ $b->placement }}</td><td>{{ $b->link }}</td><td><span class="pill {{ $b->active?'ok':'' }}">{{ $b->active?'Live':'Off' }}</span></td></tr>@endforeach</tbody></table><p style="font-size:12px;color:#6B7280">Upload size 1568 × 555 for desktop.</p></div>
<div class="card" style="margin:0"><div style="display:flex"><b style="flex:1">Static pages</b><a href="/admin/m/pages/new" style="font-size:13px;color:#FF5A00;font-weight:600">Add page</a></div><table><thead><tr><th>Page</th><th>Address</th><th>Status</th></tr></thead><tbody>@foreach($pages as $p)<tr><td><a href="/admin/m/pages/{{ $p->id }}/edit">{{ $p->title }}</a></td><td>/{{ $p->slug }}</td><td><span class="pill {{ $p->active && $p->body ? 'ok' : '' }}">{{ $p->active && $p->body ? 'Live' : 'Not set up' }}</span></td></tr>@endforeach</tbody></table></div>
<div class="card" style="margin:0"><b>Mobile layout</b><p style="font-size:13px;color:#6B7280;margin:6px 0">Products per row on mobile cannot go below 3. Category chips scroll sideways, menu button and bottom bar, flash sale block on top of home.</p><b>Sitemap</b><p style="font-size:13px;color:#6B7280;margin:6px 0">Generated automatically with the storefront.</p></div>
</div>
@endsection
