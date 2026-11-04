<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Statement {{ $c->name }}</title>
<style>@page{size:A4;margin:14mm}body{font-family:'Plus Jakarta Sans','Hind Siliguri',Arial,sans-serif;color:#14213d;font-size:12px;margin:0}.bar{padding:10px;text-align:center;background:#eef2f9}@media print{.bar{display:none}}.paper{max-width:182mm;margin:0 auto}
table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid #c9d2e3;border-radius:8px;font-variant-numeric:tabular-nums}th,td{border-right:1px solid #e1e6ef;border-bottom:1px solid #e1e6ef;padding:6px 8px}th:last-child,td:last-child{border-right:0}tr:last-child td{border-bottom:0}th{background:#f1f4f9;text-align:center}.r{text-align:right}.c{text-align:center}</style></head>
<body><div class="bar"><button type="button" onclick="window.print()" style="min-height:40px;padding:0 18px;border-radius:999px;border:0;background:#123a86;color:#fff;font-weight:700">Print / Save PDF</button></div>
<div class="paper">
<div style="display:flex;gap:14px;align-items:center;border-bottom:2px solid #123a86;padding:10px 0"><img src="/img/logo.webp" alt="" style="width:56px;height:56px;border-radius:10px"><div><b style="font-size:18px">{{ $s['company_name'] ?? 'NK Corporation' }}</b><div>{{ $s['address_en'] ?? '' }} · {{ $s['phone'] ?? '' }}</div></div></div>
<h2 style="margin:12px 0 4px">Customer statement</h2>
<div>{{ $c->name }} · {{ $c->code }} · {{ $c->phone }} @if($c->area)· {{ $c->area }}@endif · printed {{ now()->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}</div>
<table style="margin-top:10px"><thead><tr><th>Date</th><th>Detail</th><th>Bill</th><th>Paid</th><th>Due after</th></tr></thead><tbody>
@foreach($rows as $r)<tr><td class="c">{{ $r->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }}</td><td>{{ ucfirst($r->kind) }} {{ $r->reference }}</td><td class="r">{{ (float) $r->debit ? number_format($r->debit, 2) : '' }}</td><td class="r">{{ (float) $r->credit ? number_format($r->credit, 2) : '' }}</td><td class="r"><b>{{ number_format($r->running, 2) }}</b></td></tr>@endforeach
</tbody></table>
<p><b>Due now: ৳{{ number_format($rows->last()?->running ?? 0, 2) }}</b> @if(array_sum($cyl)) · Our cylinders with you: @foreach($cyl as $sz => $q){{ $sz }} kg × {{ $q }} @endforeach @endif</p>
</div></body></html>
