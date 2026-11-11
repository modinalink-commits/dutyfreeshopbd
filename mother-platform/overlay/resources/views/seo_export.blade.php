<!doctype html><html><head>@include('bn_font')<meta charset="utf-8"><title>SEO {{ ucfirst($t) }} export</title>
<style>body{font-family:'Bornomala',Arial,sans-serif;font-size:11px;color:#111827}h1{font-size:18px;color:#0F172A}table{border-collapse:collapse;width:100%}th,td{border:1px solid #E5E7EB;padding:4px 6px;vertical-align:top;text-align:left}th{background:#FFF1E8;color:#0F172A}@media print{@page{size:A4 landscape;margin:10mm}}</style></head><body>
<h1>{{ ucfirst($t) }} SEO report — {{ \App\Support\SeoMgr::site() }}</h1><p>{{ $rows->count() }} records · {{ now('Asia/Dhaka')->format('d M Y, h:i A') }}</p>
<table><thead><tr>@foreach($h as $c)<th>{{ $c }}</th>@endforeach</tr></thead><tbody>@foreach($rows as $r)<tr>@foreach($r as $v)<td>{{ $v }}</td>@endforeach</tr>@endforeach</tbody></table>
@if($fmt==='pdf')<script>window.onload=function(){window.print()}</script>@endif</body></html>
