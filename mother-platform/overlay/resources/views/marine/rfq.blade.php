@extends('layout')@section('title','Ship Provisions')
@section('body')
@php
$col = ['draft' => ['#F3F4F6', '#374151'], 'published' => ['#DCFCE7', '#166534']];
$res = ['created' => ['#DCFCE7', '#166534'], 'updated' => ['#DBEAFE', '#1E40AF'], 'conflict' => ['#FEE2E2', '#991B1B'], 'rejected' => ['#FEE2E2', '#991B1B'], 'skipped' => ['#F3F4F6', '#374151']];
$chip = 'font-size:12px;padding:5px 12px;border-radius:999px;text-decoration:none;border:1px solid ';
@endphp
<div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:10px"><div><h1 style="margin:0">Ship Provisions</h1><div style="font-size:12px;color:#6B7280;max-width:760px">Quotation-only items for vessels. No price is kept here: the marine desk prices every requisition in USD. An item is on the marine site only when it is Published and the catalogue is released.</div></div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
@if(session('bad'))<div class="card" style="background:#FEE2E2;border-color:#FECACA;color:#991B1B">{{ session('bad') }}</div>@endif
@if(!($ready ?? false))<div class="card" style="background:#FEF3C7;border-color:#FDE68A;color:#713F12">The tables are not ready yet. Open this page again when the deploy has finished.</div>
@else
@php $total = (int)collect($n)->sum(); $prevUrl = $site . '/provisions.html?preview=' . $token; @endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:10px">
@foreach([['Items', $total], ['Draft', (int)($n['draft'] ?? 0)], ['Published', (int)($n['published'] ?? 0)], ['With a valid port check', $checked], ['With approved packshot', $packshots]] as $c)<div class="card" style="margin:0;padding:10px 12px"><div style="font-size:11px;color:#6B7280;text-transform:uppercase">{{ $c[0] }}</div><div style="font-size:20px;font-weight:700">{{ $c[1] }}</div></div>@endforeach
<div class="card" style="margin:0;padding:10px 12px;border-color:{{ $released ? '#A7F3D0' : '#FDE68A' }};background:{{ $released ? '#F0FDF4' : '#FFFBEB' }}"><div style="font-size:11px;color:#6B7280;text-transform:uppercase">Marine site</div><div style="font-size:14px;font-weight:700;color:{{ $released ? '#166534' : '#713F12' }}">{{ $released ? 'Released' : 'Not released' }}</div></div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:10px;margin-bottom:10px">
<div class="card" style="margin:0"><b>Import: Bangladesh Ship Provisions (160 items, 12 sections)</b>
<div style="font-size:12px;color:#6B7280;margin:4px 0 10px">The SKU is the key. A repeat import adds nothing twice, never changes Draft or Published, a packshot, a port check or a price. A different brand or pack on an existing SKU is reported as a conflict and not written.</div>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<form method="post" action="/admin/marine/provisions/run" style="margin:0">@csrf<input type="hidden" name="mode" value="dry"><button class="btn g">1. Preflight and dry run</button></form>
<form method="post" action="/admin/marine/provisions/run" style="margin:0">@csrf<input type="hidden" name="mode" value="apply"><button class="btn">2. Import as drafts</button></form>
</div>
@if($runs)<table style="width:100%;font-size:12px;border-collapse:collapse;margin-top:10px"><thead><tr style="text-align:left;color:#6B7280;font-size:11px;text-transform:uppercase"><th style="padding:4px 6px 4px 0">Run</th><th>Created</th><th>Updated</th><th>Conflict</th><th>Skipped</th><th>Rejected</th></tr></thead><tbody>
@foreach($runs as $x)<tr style="border-top:1px solid #E5E7EB"><td style="padding:4px 6px 4px 0">{{ $x['at'] }} · {{ $x['mode'] }}<span style="color:#6B7280"> · {{ $x['by'] }}</span></td>@foreach(['created','updated','conflict','skipped','rejected'] as $k)<td style="{{ in_array($k, ['conflict','rejected']) && ($x['counts'][$k] ?? 0) ? 'color:#991B1B;font-weight:700' : '' }}">{{ $x['counts'][$k] ?? 0 }}</td>@endforeach</tr>@endforeach
</tbody></table>@endif
</div>
<div class="card" style="margin:0"><b>Review and release</b>
<div style="font-size:12px;color:#6B7280;margin:4px 0 8px">Reviewers open the preview link to see every item, drafts included, exactly as the catalogue page will show it. The link is not listed anywhere and search engines are told not to index it.</div>
<input readonly value="{{ $prevUrl }}" onclick="this.select()" style="width:100%;font-size:12px" aria-label="Preview link">
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"><a class="btn g" style="font-size:12px" href="{{ $prevUrl }}" target="_blank" rel="noopener">Open preview</a>
<form method="post" action="/admin/marine/provisions/release" style="margin:0">@csrf<input type="hidden" name="action" value="newkey"><button class="btn g" style="font-size:12px">Make a new preview link</button></form>
@if(($n['draft'] ?? 0) > 0)<form method="post" action="/admin/marine/provisions/release" style="margin:0" data-ask="Publish all {{ (int)$n['draft'] }} draft items?">@csrf<input type="hidden" name="action" value="publish_all"><button class="btn g" style="font-size:12px">Publish all drafts ({{ (int)$n['draft'] }})</button></form>@endif
<form method="post" action="/admin/marine/provisions/release" style="margin:0" data-ask="{{ $released ? 'Take the provisions catalogue off the marine site?' : 'Release the provisions catalogue? Published items will be shown on the marine site and in the requisition search.' }}">@csrf<input type="hidden" name="action" value="{{ $released ? 'off' : 'on' }}"><button class="btn {{ $released ? 'g' : '' }}" style="font-size:12px">{{ $released ? 'Take off the marine site' : 'Release to the marine site' }}</button></form></div>
<form method="post" action="/admin/marine/provisions/sourcing" enctype="multipart/form-data" style="margin:10px 0 0;padding-top:8px;border-top:1px solid #E5E7EB;display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:12px">@csrf<b>Sourcing notes (desk only)</b><input type="file" name="sheet" accept=".csv" required aria-label="Sourcing sheet as CSV" style="font-size:12px"><button class="btn g" style="font-size:12px">Upload</button><span style="color:#6B7280;flex-basis:100%">The Sourcing sheet of the workbook saved as CSV. Kept in the database and shown only on this page.</span></form>
<div style="font-size:12px;color:#6B7280;margin-top:8px">Release only after engineering has approved the preview. Releasing shows Published items only; drafts stay hidden.</div>
</div></div>

@if($last)
<details class="card" @if(($last['counts']['conflict'] ?? 0) || ($last['counts']['rejected'] ?? 0) || ($last['file'] ?? [])) open @endif><summary style="cursor:pointer"><b>Last run: {{ $last['mode'] }}</b> · {{ $last['at'] }} · {{ $last['by'] }} · preflight {{ ($last['ok'] ?? false) ? 'passed' : 'found problems' }} ({{ $last['stats']['rows'] ?? 0 }} rows, {{ $last['stats']['unique_skus'] ?? 0 }} unique SKUs, {{ $last['stats']['sections'] ?? 0 }} sections) · <a href="/admin/marine/provisions?report=csv">Download the row report (CSV)</a>@if($wrote ?? null) · <a href="/admin/marine/provisions?report=csv&amp;run=write">Row report of the last import that changed something ({{ $wrote['at'] }})</a>@endif</summary>
@foreach(($last['file'] ?? []) as $x)<div style="color:#991B1B;font-size:13px;margin-top:6px">{{ $x }}</div>@endforeach
<div style="max-height:320px;overflow:auto;margin-top:8px"><table style="width:100%;font-size:12px;border-collapse:collapse"><thead><tr style="text-align:left;color:#6B7280;font-size:11px;text-transform:uppercase"><th style="padding:4px 8px 4px 0;width:120px">SKU</th><th style="width:90px">Result</th><th>Detail</th></tr></thead><tbody>
@foreach(collect($last['rows'] ?? [])->sortBy(fn($x) => ['rejected' => 0, 'conflict' => 1, 'updated' => 2, 'created' => 3, 'skipped' => 4][$x[1]] ?? 5) as $x)@php $c = $res[$x[1]] ?? $res['skipped']; @endphp<tr style="border-top:1px solid #E5E7EB;vertical-align:top"><td style="padding:4px 8px 4px 0;white-space:nowrap">{{ $x[0] }}</td><td><span style="display:inline-block;padding:1px 8px;border-radius:999px;font-weight:600;background:{{ $c[0] }};color:{{ $c[1] }}">{{ $x[1] }}</span></td><td>{{ $x[2] }}</td></tr>@endforeach
</tbody></table></div></details>
@endif

<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:12px 0 10px">
@foreach(['all' => 'All'] + $states as $k => $l)<a href="/admin/marine/provisions?{{ http_build_query(array_filter(['state' => $k, 'section' => $sec])) }}" style="{{ $chip }}{{ $st === $k ? '#0C161F;background:#0C161F;color:#FFFFFF' : '#D1D5DB;background:#FFFFFF;color:#0C161F' }}">{{ $l }}@if($k !== 'all') ({{ $n[$k] ?? 0 }})@endif</a>@endforeach
<form method="get" action="/admin/marine/provisions" style="margin:0 0 0 auto;display:flex;gap:6px;flex-wrap:wrap"><input type="hidden" name="state" value="{{ $st }}"><select name="section" style="font-size:12px" aria-label="Section"><option value="">All sections</option>@foreach($sections as $s)<option value="{{ $s->section_slug }}" @selected($sec === $s->section_slug)>{{ $s->n }} ({{ $s->c }})</option>@endforeach</select><input name="q" value="{{ $q }}" placeholder="SKU, name or brand" style="font-size:12px;width:180px"><button class="btn g" style="padding:5px 12px;font-size:12px">Search</button></form></div>

<div class="card" style="padding:0;overflow:auto"><table style="width:100%;min-width:1040px;font-size:13px;border-collapse:collapse">
<thead><tr style="text-align:left;color:#6B7280;font-size:11px;text-transform:uppercase"><th style="padding:8px 10px;width:66px">Image</th><th style="padding:8px 10px">Item</th><th style="padding:8px 10px">Section</th><th style="padding:8px 10px">Pack size</th><th style="padding:8px 10px">Order unit</th><th style="padding:8px 10px">Handling</th><th style="padding:8px 10px">State and port checks</th></tr></thead><tbody>
@forelse($rows as $it)
@php $c = $col[$it->state] ?? $col['draft']; $ck = $checks[$it->id] ?? collect(); $src = $it->sourcing ? (json_decode($it->sourcing, true) ?: []) : []; $img = '/marine-api/rfq-image/' . $it->sku . '?v=' . strtotime((string)($it->packshot_at ?: $it->created_at)) . '&preview=' . $token; @endphp
<tr style="border-top:1px solid #E5E7EB;vertical-align:top">
<td style="padding:8px 10px"><img src="{{ $img }}" alt="" loading="lazy" width="48" height="48" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #E5E7EB;background:#F9FAFB"></td>
<td style="padding:8px 10px"><b>{{ $it->name_en }}</b>@if($it->brand) <span style="color:#6B7280">· {{ $it->brand }}</span>@endif<div style="font-size:11.5px;color:#6B7280">{{ $it->sku }} · {{ $it->name_bn }}</div><div style="font-size:11px;color:{{ $it->packshot_path ? '#166534' : '#6B7280' }}">{{ $it->packshot_path ? 'Approved packshot' : 'Illustrative image' }}</div></td>
<td style="padding:8px 10px">{{ $it->section_name }}<div style="font-size:11.5px;color:#6B7280">{{ $it->sub_name }}</div></td>
<td style="padding:8px 10px;white-space:nowrap">{{ \App\Support\MarineRfq::pack($it) }}@if($it->pack_note)<div style="font-size:11.5px;color:#6B7280;white-space:normal">{{ $it->pack_note }}</div>@endif</td>
<td style="padding:8px 10px">{{ $it->order_uom }}</td>
<td style="padding:8px 10px;font-size:12px">{{ $handling[$it->handling] ?? $it->handling }}</td>
<td style="padding:8px 10px;font-size:12px"><span style="display:inline-block;padding:2px 9px;border-radius:999px;font-weight:600;background:{{ $c[0] }};color:{{ $c[1] }}">{{ $states[$it->state] ?? $it->state }}</span> <span style="color:#6B7280">RFQ · USD · price on quotation</span>
@foreach($ck as $k)<div style="color:{{ substr((string)$k->valid_until, 0, 10) >= $today ? '#166534' : '#991B1B' }}">{{ $ports[$k->port] ?? $k->port }}: {{ $k->supplier }}, {{ $k->qty_available }} · {{ substr((string)$k->valid_until, 0, 10) >= $today ? 'valid until' : 'expired' }} {{ substr((string)$k->valid_until, 0, 10) }}</div>@endforeach
@if(!$ck->count())<div style="color:#6B7280">No port check yet: availability is not shown.</div>@endif
<details style="margin-top:6px" @if($open === (int)$it->id) open @endif><summary style="cursor:pointer;color:#F95505;font-weight:600">Review</summary>
<div style="display:grid;gap:10px;margin-top:8px;min-width:300px">
<div style="display:flex;gap:6px;flex-wrap:wrap">
<form method="post" action="/admin/marine/provisions/{{ $it->id }}" style="margin:0">@csrf<input type="hidden" name="action" value="{{ $it->state === 'published' ? 'unpublish' : 'publish' }}"><button class="btn {{ $it->state === 'published' ? 'g' : '' }}" style="padding:5px 10px;font-size:12px">{{ $it->state === 'published' ? 'Back to Draft' : 'Publish' }}</button></form>
<a class="btn g" style="padding:5px 10px;font-size:12px" href="/admin/marine/portal-preview/order_new?as=owner&amp;rfq={{ $it->id }}" target="_blank" rel="noopener">See it on a requisition</a></div>
<form method="post" action="/admin/marine/provisions/{{ $it->id }}" style="margin:0;display:grid;gap:6px;border-top:1px solid #E5E7EB;padding-top:8px">@csrf<input type="hidden" name="action" value="check"><b>Port check (desk only)</b>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px"><select name="port" required aria-label="Port">@foreach($ports as $pk => $pl)<option value="{{ $pk }}">{{ $pl }}</option>@endforeach</select><input name="supplier" maxlength="160" required placeholder="Supplier or branch"><input name="qty_available" maxlength="60" required placeholder="Quantity available, e.g. 40 kg"><input name="pack_checked" maxlength="80" placeholder="Pack checked, e.g. {{ \App\Support\MarineRfq::pack($it) }}"><label style="font-size:11.5px;color:#6B7280">Valid until<input type="date" name="valid_until" required min="{{ $today }}" style="width:100%"></label><label style="font-size:12px;align-self:end"><input type="checkbox" name="handling_ok" value="1"> Handling checked</label></div>
<input name="note" maxlength="300" placeholder="Note (optional)"><div><button class="btn g" style="padding:5px 10px;font-size:12px">Save port check</button></div></form>
@foreach($ck as $k)<form method="post" action="/admin/marine/provisions/{{ $it->id }}" style="margin:0;font-size:11.5px;color:#6B7280">@csrf<input type="hidden" name="action" value="check_delete"><input type="hidden" name="check_id" value="{{ $k->id }}">{{ $ports[$k->port] ?? $k->port }} checked by {{ $k->checked_by }}{{ $k->handling_ok ? ' · handling checked' : '' }}{{ $k->note ? ' · ' . $k->note : '' }} <button style="border:0;background:none;color:#991B1B;cursor:pointer;font-size:11.5px;padding:0;text-decoration:underline">Remove</button></form>@endforeach
<form method="post" action="/admin/marine/provisions/{{ $it->id }}" enctype="multipart/form-data" style="margin:0;display:grid;gap:6px;border-top:1px solid #E5E7EB;padding-top:8px">@csrf<input type="hidden" name="action" value="packshot"><b>Approved packshot</b>
@if($it->packshot_path)<div style="font-size:11.5px;color:#166534">Approved by {{ $it->packshot_by }} · rights: {{ $it->packshot_ref }}</div>@endif
<input type="file" name="packshot" accept=".webp,.jpg,.jpeg,.png" required aria-label="Packshot image"><input name="packshot_ref" maxlength="200" required placeholder="Rights reference: who supplied it and the permission"><div style="font-size:11.5px;color:#6B7280">WebP, JPG or PNG, up to 3 MB, white background, 600 × 600 preferred. The SKU does not change.</div><div><button class="btn g" style="padding:5px 10px;font-size:12px">{{ $it->packshot_path ? 'Replace packshot' : 'Use this packshot' }}</button></div></form>
@if($it->packshot_path)<form method="post" action="/admin/marine/provisions/{{ $it->id }}" style="margin:0">@csrf<input type="hidden" name="action" value="packshot_remove"><button class="btn g" style="padding:5px 10px;font-size:12px">Remove packshot, show the illustrative image</button></form>@endif
@if($src)<div style="border-top:1px solid #E5E7EB;padding-top:8px;font-size:11.5px;color:#374151"><b>Sourcing notes (desk only, never shown to customers)</b>
@foreach(['primary_retailer' => 'Primary retailer', 'listing_signal' => 'Seen as', 'reviewed_on' => 'Reviewed on', 'alternate_retailer' => 'Alternate retailer', 'procurement_route' => 'Local procurement route', 'sourcing_region' => 'Sourcing region', 'port_stock' => 'Port stock'] as $sk => $sl)@if(!empty($src[$sk]))<div>{{ $sl }}: {{ $src[$sk] }}</div>@endif @endforeach
@foreach(['source_url' => 'Source', 'alternate_source_url' => 'Alternate source'] as $sk => $sl)@if(!empty($src[$sk]) && str_starts_with($src[$sk], 'https://'))<div>{{ $sl }}: <a href="{{ $src[$sk] }}" target="_blank" rel="noopener noreferrer nofollow">{{ \Illuminate\Support\Str::limit($src[$sk], 60) }}</a></div>@endif @endforeach</div>@endif
</div></details></td></tr>
@empty<tr><td colspan="7" style="padding:18px 10px;color:#6B7280">{{ $total ? 'No item matches this filter.' : 'No item yet. Run the preflight and dry run, then import as drafts.' }}</td></tr>
@endforelse</tbody></table></div>
<div style="margin-top:10px">{{ $rows->links() }}</div>
<script>document.querySelectorAll('form[data-ask]').forEach(function(f){var armed=false,b=f.querySelector('button'),t=b.textContent;f.addEventListener('submit',function(e){if(armed)return;e.preventDefault();armed=true;b.textContent='Click again to confirm';var n=document.createElement('div');n.style.cssText='font-size:12px;color:#713F12;margin-top:6px;flex-basis:100%';n.textContent=f.getAttribute('data-ask');f.appendChild(n);setTimeout(function(){armed=false;b.textContent=t;n.remove()},8000)})});</script>
@endif
@endsection
