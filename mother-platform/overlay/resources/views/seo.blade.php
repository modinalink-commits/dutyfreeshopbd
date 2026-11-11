@extends('layout')@section('title','Pages & SEO')
@section('body')
@php $tabs=['pages'=>'Pages','audit'=>'SEO Audit','redirects'=>'Redirects','settings'=>'Settings & Sitemap']; @endphp
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Pages &amp; SEO</h1><div style="font-size:12px;color:#6B7280">Create pages, control search appearance, redirects, sitemap and indexing</div></div>
<a class="g" href="/admin/seo/export" style="text-decoration:none">Export CSV</a><button class="g" type="button" onclick="window.print()">Print</button><a href="/admin/seo/page" class="b" style="text-decoration:none"><button type="button">+ New page</button></a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:12px">
@foreach([['Total pages',$kpi['total']],['Published',$kpi['pub']],['Draft',$kpi['draft']],['Needs SEO work',$kpi['low']]] as [$l,$v])<div class="card" style="margin:0;padding:10px 12px"><span style="font-size:11.5px;color:#6B7280;display:block">{{ $l }}</span><b style="font-size:20px">{{ $v }}</b></div>@endforeach</div>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">@foreach($tabs as $k=>$l)<a href="?tab={{ $k }}" class="g" style="text-decoration:none;{{ $tab===$k ? 'background:#FF5A00;color:#fff;border-color:#FF5A00' : '' }}">{{ $l }}</a>@endforeach<a href="/admin/seo/items?type=category" class="g" style="text-decoration:none">Categories SEO</a><a href="/admin/seo/items?type=brand" class="g" style="text-decoration:none">Brands SEO</a><a href="/admin/seo/items?type=product" class="g" style="text-decoration:none">Products SEO</a><a href="/admin/seo/alt" class="g" style="text-decoration:none">Image ALT</a></div>

@if($tab==='pages'||$tab==='audit')
<div class="card"><form method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end"><input type="hidden" name="tab" value="{{ $tab }}">
<div><label>Search</label><input name="q" value="{{ $q }}" placeholder="title, slug, SEO title"></div>
@if($tab==='pages')<div><label>Status</label><select name="status"><option value="">All</option>@foreach(\App\Support\SeoMgr::STATUSES as $k=>$l)<option value="{{ $k }}" @selected($fs===$k)>{{ $l }}</option>@endforeach</select></div>
<div><label>Type</label><select name="type"><option value="">All</option>@foreach(\App\Support\SeoMgr::TYPES as $k=>$l)<option value="{{ $k }}" @selected($ft===$k)>{{ $l }}</option>@endforeach</select></div>
@else<div><label>Issue</label><select name="f"><option value="">Any issue</option>@foreach(['title'=>'Missing SEO title','desc'=>'Missing meta description','kw'=>'Missing focus keyword','alt'=>'Images without alt','noindex'=>'Noindex pages','low'=>'Score below 65'] as $k=>$l)<option value="{{ $k }}" @selected($fx===$k)>{{ $l }}</option>@endforeach</select></div>@endif
<button>Filter</button></form></div>
<form method="post" action="/admin/seo/bulk" id="bk" onsubmit="return bulkOk(this)">@csrf<input type="hidden" name="confirm" value="0">
<div class="card" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center"><b style="font-size:12px">Bulk action</b><select name="act"><option value="">Choose…</option><option value="published">Publish</option><option value="draft">Set to Draft</option><option value="inactive">Set Inactive</option><option value="private">Set Private</option><option value="noindex">Set noindex</option><option value="index">Set index</option><option value="autofill">Auto-fill missing SEO title/description</option></select><button>Apply to selected</button></div>
<div class="card"><div style="overflow:auto"><table><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.ck').forEach(c=>c.checked=this.checked)"></th><th>Page</th><th>Type</th><th>Status</th><th class="n">SEO score</th>@if($tab==='audit')<th>Issues</th>@endif<th>Updated</th><th></th></tr></thead><tbody>
@forelse($list as $p)@php $c=$p->score>=85?'#166534':($p->score>=65?'#1D5FA8':($p->score>=40?'#B45309':'#B91C1C')); @endphp
<tr><td><input class="ck" type="checkbox" name="ids[]" value="{{ $p->id }}"></td><td><b>{{ $p->title }}</b><br><small style="color:#6B7280">/{{ $p->slug }}/ @unless($p->robots_index)· noindex @endunless</small></td><td>{{ \App\Support\SeoMgr::TYPES[$p->page_type] ?? $p->page_type }}</td><td>{{ \App\Support\SeoMgr::STATUSES[$p->status] ?? $p->status }}</td><td class="n"><b style="color:{{ $c }}">{{ $p->score }}</b> <small style="color:#6B7280">{{ $p->label }}</small></td>
@if($tab==='audit')<td style="font-size:11.5px">{{ implode(' · ', $p->issues) ?: '—' }}</td>@endif
<td style="white-space:nowrap">{{ \Carbon\Carbon::parse($p->updated_at,'UTC')->timezone('Asia/Dhaka')->format('d M Y') }}</td>
<td style="white-space:nowrap"><a href="/admin/seo/page/{{ $p->id }}">Edit</a> · <a href="/{{ $p->slug }}/" target="_blank">View</a></td></tr>
@empty<tr><td colspan="8" style="color:#6B7280">Nothing found.</td></tr>@endforelse</tbody></table></div></div></form>
<script>function bulkOk(f){if(!f.act.value||!f.querySelector('.ck:checked')){alert('Choose an action and select pages');return false}if(!confirm('Apply "'+f.act.options[f.act.selectedIndex].text+'" to '+f.querySelectorAll('.ck:checked').length+' page(s)?'))return false;f.confirm.value='1';return true}</script>
@endif

@if($tab==='redirects')
<div class="card"><h2>Add redirect</h2><form method="post" action="/admin/seo/redirect" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">@csrf
<div><label>From (path)</label><input name="from" placeholder="/old-page" required></div><div><label>To (path or URL)</label><input name="to" placeholder="/new-page/" required></div>
<div><label>Type</label><select name="type"><option value="301">301 Permanent</option><option value="302">302 Temporary</option></select></div><button>Save</button></form>
<p style="font-size:12px;color:#6B7280;margin:8px 0 0">Changing a page slug or deleting a page creates a 301 automatically. Redirect chains are collapsed.</p></div>
<div class="card"><div style="overflow:auto"><table><thead><tr><th>From</th><th>To</th><th>Type</th><th>Source</th><th class="n">Hits</th><th>Active</th><th></th></tr></thead><tbody>
@forelse($redirects as $x)<tr><td>{{ $x->from_path }}</td><td>{{ $x->to_url }}</td><td>{{ $x->type }}</td><td>{{ $x->source }}</td><td class="n">{{ $x->hits }}</td><td>{{ $x->active ? 'Yes' : 'No' }}</td>
<td style="white-space:nowrap"><form method="post" action="/admin/seo/redirect/{{ $x->id }}" style="display:inline">@csrf<button class="g" name="do" value="toggle">{{ $x->active ? 'Disable' : 'Enable' }}</button> <button class="g" name="do" value="delete" onclick="return confirm('Delete this redirect?')">Delete</button></form></td></tr>
@empty<tr><td colspan="7" style="color:#6B7280">No redirects yet.</td></tr>@endforelse</tbody></table></div></div>
@endif

@if($tab==='settings')
<div class="card"><h2>Sitemap</h2><div style="font-size:13px">URLs in sitemap: <b>{{ $sm['total'] }}</b> · Last rebuilt: {{ $sm['at'] ?: '—' }} · <a href="/sitemap.xml" target="_blank">/sitemap.xml</a></div>
<form method="post" action="/admin/seo/sitemap" style="margin-top:8px">@csrf<button>Rebuild sitemap now</button></form></div>
<div class="card"><h2>Global SEO settings</h2><form method="post" action="/admin/seo/settings">@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px"><div><label>Site name</label><input name="site" value="{{ $set['site'] }}"></div><div><label>City (template variable {city})</label><input name="city" value="{{ $set['city'] }}"></div>
<div><label>Default social image URL</label><input name="og" value="{{ $set['og'] }}" placeholder="/og.png"></div><div><label>Search engine indexing</label><select name="index"><option value="1" @selected($set['index']==='1')>Allow indexing</option><option value="0" @selected($set['index']==='0')>Block all (stored for robots.txt)</option></select></div></div>
<label style="margin-top:12px">Meta templates — variables {site_name} {city} {page_title} {product_name} {category_name} {brand_name}</label>
@foreach($set['tpl'] as $k=>$v)<div style="display:flex;gap:8px;align-items:center;margin-bottom:6px"><span style="width:80px;font-size:12px;text-transform:capitalize">{{ $k }}</span><input name="tpl_{{ $k }}" value="{{ $v }}" style="flex:1"></div>@endforeach
<label style="margin-top:10px">Verification codes (content value only)</label>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px"><div><label>Google Search Console</label><input name="gsc_verify" value="{{ $set['ver']['gsc_verify'] }}"></div><div><label>Bing</label><input name="bing_verify" value="{{ $set['ver']['bing_verify'] }}"></div><div><label>Facebook domain</label><input name="fb_verify" value="{{ $set['ver']['fb_verify'] }}"></div></div>
<label style="margin-top:10px">robots.txt content (saved; shown for reference at /admin/seo)</label><textarea name="robots" rows="7" style="font-family:Inter,'Bornomala',sans-serif">{{ $robots }}</textarea>
<div style="margin-top:10px"><button>Save settings</button></div></form></div>
@endif
@endsection
