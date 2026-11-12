@extends('layout')@section('title','Stores & Scan')
@section('body')
@php $PH = \App\Support\StoreScanner::ph(); @endphp
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Stores &amp; Domains</h1><span style="font-size:13px;color:#6B7280">Add a store or domain, press Scan Products, and its products arrive in the Central Inventory.</span></div>
<span style="display:inline-flex;gap:6px;font-size:12px;font-weight:600;color:#FF5A00;background:#FFF1E8;border-radius:8px;padding:6px 10px">⬇ Download: <a target="_blank" href="/admin/m/stores/export/pdf">PDF</a> · <a href="/admin/m/stores/export/xls">Excel</a> · <a href="/admin/m/stores/export/doc">Word</a> · <a href="/admin/m/stores/export/csv">CSV</a></span>
<a class="btn" href="/admin/inventory">Open Central Inventory</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<form class="card" method="post" action="/admin/stores/auto-scan" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">@csrf
<div style="flex:1;min-width:260px"><b>Daily Auto Scan</b><br><span style="font-size:12px;color:#6B7280">Every source store is re-scanned automatically each day. In-stock products stay published, out-of-stock products are unpublished, new products and price changes come in, so our site always matches the source websites.</span><br><span style="font-size:12px">Last auto scan: <b>{{ $auto['last'] ?: 'not yet' }}</b>@if($auto['running']) · <span style="color:#FF5A00">running now…</span>@endif</span></div>
<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="on" value="1" {{ $auto['on'] ? 'checked' : '' }}> On</label>
<div><label>Time (Dhaka)</label><input type="time" name="time" value="{{ $auto['time'] }}" style="max-width:130px"></div>
<button>Save time</button><button name="run" value="1" class="btn g" onclick="return confirm('Start the scan of all stores now?')">Scan all now</button></form>
<form class="card" method="post" action="/admin/stores" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">@csrf
<div style="flex:1;min-width:180px"><label>Store name</label><input name="name" placeholder="e.g. Perfume Store" required maxlength="120"></div>
<div style="flex:1;min-width:220px"><label>Domain</label><input name="domain" placeholder="example.com" required maxlength="200"></div>
<div style="flex:1;min-width:180px"><label>Source category</label><details style="position:relative"><summary style="cursor:pointer;border:1px solid #D1D5DB;border-radius:8px;padding:8px 10px;background:#fff;font-size:13px;list-style:none">Choose categories ▾</summary><div style="position:absolute;z-index:20;background:#fff;border:1px solid #E5E7EB;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.08);padding:8px;max-height:260px;overflow:auto;min-width:240px">@foreach($catNames as $__cn)<label style="display:flex;gap:8px;align-items:center;padding:3px 2px;font-size:13px;font-weight:400"><input type="checkbox" name="source_categories[]" value="{{ $__cn }}" style="width:auto"> {{ $__cn }}</label>@endforeach</div></details></div><button>Add store</button></form>
<form class="card" method="post" action="/admin/stores/category" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">@csrf<div style="flex:1;min-width:220px"><label>New category <small style="color:#6B7280">(created on the site as a prepaid-only main category; then tick it as a store's Source category)</small></label><input name="name" placeholder="e.g. Bags" required maxlength="80"></div><button class="btn g">Create category</button></form>
@php $__fail = $stores->where('scan_status', 'failed'); @endphp@if($__fail->count())<div class="card" style="border-color:#FCA5A5;background:#FEF2F2;color:#B91C1C;font-size:13px"><b>Scan failed:</b> {{ $__fail->pluck('name')->implode(', ') }}. The vendor site may have changed. Press Re-scan, and check Edit Store if it keeps failing.</div>@endif
<div class="card" style="font-size:13px;color:#374151"><b>How the scan works.</b> It detects the shop type automatically: Shopify, WooCommerce, a Google product feed, or any website with a sitemap (it opens each product page and reads name, image, SKU, price, stock and category). It only reads public pages, so products a shop keeps as draft or hidden cannot be seen from outside. Products that were imported earlier but are no longer public are marked <b>Unpublished</b>. Re-scan at any time to refresh.</div>
<div style="margin:0 0 8px"><a class="btn" href="/admin/stores/sales">Store Sales dashboard</a></div>
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Store</th><th>Type</th><th>Source category</th><th>Products</th><th>Published</th><th>Unpublished</th><th>Commission</th><th>Last scan</th><th>Status</th><th>Action</th></tr></thead><tbody>
<tr><td><b>DutyFreeBD</b> <span class="pill ok" style="font-size:10.5px">Main store</span><br><small style="color:#6B7280">dutyfreebd.com · live database</small></td><td>Mother Platform</td><td>All categories</td><td>{{ number_format($own['n']) }}</td><td>{{ number_format($own['pub']) }}</td><td>{{ number_format($own['n'] - $own['pub']) }}</td><td>—</td><td>always live</td><td><span class="pill ok">Live</span></td><td><a class="btn g" href="/admin/inventory?store=0">View inventory</a></td></tr>
@php $__host = preg_replace('/^www\./i', '', strtolower((string)request()->getHost())); @endphp
@foreach($stores as $s)@php $c = $counts[$s->id] ?? null; $__dom = preg_replace('/^www\./i', '', strtolower(preg_replace('#^https?://#i', '', trim((string)$s->domain, " /")))); @endphp
@continue($__dom !== '' && $__dom === $__host)
<tr id="row{{ $s->id }}"><td><b>{{ $s->name }}</b><br><small style="color:#6B7280">{{ $s->domain ?: 'no domain' }}</small>@if(!empty($s->company_name) || !empty($s->company_phone) || !empty($s->support_phone))<br><small style="color:#9A3412">{{ $s->company_name ?? '' }}@if(!empty($s->company_phone)) · {{ $s->company_phone }}@endif @if(!empty($s->support_phone)) · Support {{ $s->support_phone }}@endif</small>@endif
<div id="bar{{ $s->id }}" style="display:none;margin-top:8px;min-width:240px"><div style="height:10px;background:#FFF1E8;border-radius:6px;overflow:hidden"><div id="fill{{ $s->id }}" style="height:100%;width:0;background:#FF5A00;transition:width .4s"></div></div><div id="note{{ $s->id }}" style="font-size:12px;color:#6B7280;margin-top:4px"></div>
<div id="cnt{{ $s->id }}" style="font-size:12px;margin-top:4px;line-height:1.7"></div></div></td>
<td id="plat{{ $s->id }}">{{ $s->platform ?: '—' }}</td><td>@foreach(array_filter(array_map('trim', explode(',', (string)($s->source_category ?? '')))) as $__sc)<span class="pill" style="margin:1px">{{ $__sc }}</span> @endforeach @if(!trim((string)($s->source_category ?? '')))<a href="/admin/stores/{{ $s->id }}/edit" style="font-size:11px;color:#6B7280">+ add</a>@endif</td><td id="tot{{ $s->id }}">{{ number_format($c->n ?? 0) }}</td><td id="pub{{ $s->id }}">{{ number_format($c->pub ?? 0) }}</td><td id="dr{{ $s->id }}">{{ number_format(($c->n ?? 0) - ($c->pub ?? 0)) }}</td>
<td>@if(isset($comm[$s->id]))<b>+{{ rtrim(rtrim(number_format($comm[$s->id], 2), '0'), '.') }}%</b><br><small style="color:#6B7280">sale · regular +{{ rtrim(rtrim(number_format($regc, 2), '0'), '.') }}%</small>@else—@endif</td><td id="last{{ $s->id }}">{{ $s->last_scan_at ? \Carbon\Carbon::parse($s->last_scan_at)->timezone('Asia/Dhaka')->format('d M Y, H:i') : 'never' }}</td>
<td><span id="st{{ $s->id }}" class="pill {{ $s->scan_status==='completed'?'ok':($s->scan_status==='failed'?'red':'') }}">{{ ucfirst($s->scan_status === 'never' ? 'Not scanned' : $s->scan_status) }}</span>@if($s->scan_status==='failed' && $s->scan_note)<div style="font-size:11px;color:#B91C1C;max-width:200px">{{ $s->scan_note }}</div>@endif</td>
<td style="white-space:nowrap">@if($s->domain)<button type="button" id="btn{{ $s->id }}" onclick="scan({{ $s->id }})">{{ $s->scan_status==='never' ? 'Scan Products' : 'Re-scan' }}</button> @endif<a class="btn g" href="/admin/inventory?store={{ $s->id }}">View</a>@if(trim((string)($s->source_category ?? '')) !== '' && $s->scan_status==='completed') <a class="btn g" href="/admin/watch-import?c=s{{ $s->id }}" title="Import this store's products into its first Source category">Import</a>@endif <a class="btn g" href="/admin/stores/{{ $s->id }}/edit">Edit Store</a> <button type="button" class="g" style="color:#B91C1C" onclick="askDelete({{ $s->id }}, @js($s->name), {{ (int)($c->n ?? 0) }})">Delete Store</button></td></tr>
@if($s->scan_status==='running')<script>document.addEventListener('DOMContentLoaded',function(){scan({{ $s->id }},true)})</script>@endif
@endforeach</tbody></table></div>
<div id="dm" style="display:none;position:fixed;inset:0;background:rgba(11,18,32,.55);z-index:99;align-items:center;justify-content:center;padding:16px"><form id="df" method="post" class="card" style="max-width:460px;width:100%;margin:0">@csrf<input type="hidden" name="mode" id="dmode" value="store"><h3 style="margin:0 0 8px">Are you sure you want to delete this store?</h3><p id="dt" style="font-size:13px;color:#374151;margin:0 0 14px"></p>
<div style="display:flex;flex-direction:column;gap:8px"><button type="submit" class="g" onclick="document.getElementById('dmode').value='store'"><b>Delete Store Only</b><br><small style="font-weight:400">Store is removed, imported product data stays in the inventory.</small></button>
<button type="submit" style="background:#B91C1C" onclick="document.getElementById('dmode').value='all'"><b>Delete Store + Products</b><br><small style="font-weight:400">Store and all its imported products are removed.</small></button>
<button type="button" class="g" onclick="document.getElementById('dm').style.display='none'">Cancel</button></div></form></div>
<script>
function askDelete(id,name,n){document.getElementById('df').action='/admin/stores/'+id+'/delete';document.getElementById('dt').textContent=name+' · '+n.toLocaleString('en-US')+' imported products';document.getElementById('dm').style.display='flex'}
var TOK='{{ csrf_token() }}';
function nf(n){return (n||0).toLocaleString('en-US')}
function paint(d){
  var id=d.id,$=function(x){return document.getElementById(x+id)};
  $('bar').style.display='block';$('fill').style.width=d.percent+'%';
  $('note').textContent=(d.percent+'% · ')+(d.note||'');
  $('cnt').innerHTML='Total Products Found: <b>'+nf(d.total)+'</b> · Published: <b>'+nf(d.published)+'</b> · Unpublished/Draft: <b>'+nf(d.draft)+'</b><br>Successfully Imported: <b style="color:#166534">'+nf(d.imported)+'</b> · Failed/Skipped: <b style="color:#B91C1C">'+nf(d.failed)+'</b>';
  if(d.platform)$('plat').textContent=d.platform;
  var st=$('st');st.textContent=d.status==='running'?'Scanning…':d.status.charAt(0).toUpperCase()+d.status.slice(1);st.className='pill '+(d.status==='completed'?'ok':(d.status==='failed'?'red':''));
  if(d.status!=='running'){ $('tot').textContent=nf(d.published+d.draft); $('pub').textContent=nf(d.published); $('dr').textContent=nf(d.draft); if(d.last)$('last').textContent=d.last; var b=$('btn');b.disabled=false;b.textContent='Re-scan'; }
}
async function post(u){var r=await fetch(u,{method:'POST',headers:{'X-CSRF-TOKEN':TOK,'Accept':'application/json'},credentials:'same-origin'});if(!r.ok)throw new Error(r.status);return r.json()}
async function scan(id,resume){
  var b=document.getElementById('btn'+id);if(b){b.disabled=true;b.textContent='Scanning…'}
  try{
    var d=resume?null:await post('/admin/stores/'+id+'/scan'); if(d)paint(d);
    for(var i=0;i<4000;i++){ d=await post('/admin/stores/'+id+'/scan-step'); paint(d); if(d.status!=='running')break; await new Promise(function(r){setTimeout(r,d.status==='running'?250:0)}); }
  }catch(e){var n=document.getElementById('note'+id);if(n){document.getElementById('bar'+id).style.display='block';n.textContent='Scan paused ('+e.message+'). Press Re-scan to continue.'}if(b){b.disabled=false;b.textContent='Re-scan'}}
}
</script>
@endsection
