<!doctype html><html><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;color:#0B1220;margin:24px}h1{font-size:20px;margin:0 0 2px}.m{font-size:12px;color:#6B7280;margin-bottom:14px}
.cards{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}.cards div{border:1px solid #E5E7EB;border-radius:10px;padding:8px 12px;min-width:130px}.cards span{font-size:11px;color:#6B7280;display:block}.cards b{font-size:16px}
table{border-collapse:collapse;width:100%;font-size:11px}th{background:#0B1220;color:#fff;text-align:left;padding:6px 8px}td{border-bottom:1px solid #E5E7EB;padding:5px 8px}tr:nth-child(even) td{background:#FAFAFA}
@page{size:A4 landscape;margin:12mm}.bar{margin-bottom:10px}.bar button{padding:8px 14px;border:0;border-radius:8px;background:#FF5A00;color:#fff;font-weight:600;cursor:pointer}@media print{.bar{display:none}}</style></head><body>
<div class="bar"><button onclick="window.print()">Print / Save as PDF</button></div>
<h1>{{ $title }}</h1><div class="m">DutyFree · Reports &amp; Analytics · {{ $f['from'] }} to {{ $f['to'] }} · generated {{ now('Asia/Dhaka')->format('d M Y H:i') }}@if($truncated) · first 5,000 rows only @endif</div>
@if($cards)<div class="cards">@foreach($cards as [$l,$v,$t])<div><span>{{ $l }}</span><b>{{ $t === 'money' ? '৳'.number_format($v) : ($t === 'pct' ? $v.'%' : number_format($v)) }}</b></div>@endforeach</div>@endif
<table><thead><tr>@foreach($head as $h)<th>{{ $h }}</th>@endforeach</tr></thead><tbody>@foreach($body as $b)<tr>@foreach($b as $c)<td>{{ $c }}</td>@endforeach</tr>@endforeach</tbody></table>
<script>setTimeout(function(){window.print()},400)</script></body></html>
