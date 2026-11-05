<!doctype html>
<html lang="en">
<head><meta charset="utf-8">
<link rel="stylesheet" href="/css/nk-type.css?v=1"><meta name="robots" content="noindex"><title>{{ $label }} · {{ $f['from'] }} to {{ $f['to'] }}</title>
<style>
body{margin:0;font-family:'Plus Jakarta Sans','Hind Siliguri',Arial,sans-serif;color:#14213d;font-variant-numeric:tabular-nums}
.bar{padding:10px;background:#eef2f9;display:flex;gap:8px;justify-content:center}.bar button{min-height:40px;padding:0 18px;border-radius:999px;border:0;background:#123a86;color:#fff;font-weight:700;cursor:pointer}
@media print{.bar{display:none}}
@if($size === 'a4')@page{size:A4 {{ ($f['page'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape' }};margin:12mm}.paper{max-width:{{ ($f['page'] ?? 'landscape') === 'portrait' ? '186mm' : '273mm' }};margin:0 auto;font-size:var(--fs-11);padding:10px}
@else @page{size:80mm auto;margin:2mm}.paper{width:72mm;margin:0 auto;font-size:var(--fs-11)}@endif
table{width:100%;border-collapse:collapse}th{background:#f1f4f9;font-weight:600}th,td{border:1px solid #d5dce8;padding:5px 6px}td.n{text-align:right}tr.t td{font-weight:700;background:#e6eeff}
</style></head>
<body>
@unless($word)<div class="bar"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>@endunless
<div class="paper">
  <div style="display:flex;gap:12px;align-items:center;border-bottom:2px solid #123a86;padding-bottom:8px">
    @if($size === 'a4' && ! $word)<img src="{{ $s['logo'] ?? '/img/logo.webp' }}" alt="" style="width:48px;height:48px;border-radius:10px">@endif
    <div style="flex-grow:1"><b style="font-size:{{ $size === 'a4' ? '16px' : '13px' }};color:#123a86">{{ $s['company_name'] ?? 'NK Corporation' }}</b><br><span style="color:#51607a">{{ $s['address_en'] ?? '' }} · {{ $s['phone'] ?? '' }}</span></div>
    <div style="text-align:right"><b>{{ strtoupper($label) }}</b><br>{{ \Carbon\Carbon::parse($f['from'])->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($f['to'])->format('d-m-Y') }}</div>
  </div>
  <p style="color:#51607a;margin:6px 0">Filters: {{ $f['brand'] ? 'brand #'.$f['brand'] : 'all brands' }} · {{ $f['vehicle'] ? 'vehicle #'.$f['vehicle'] : 'all vehicles' }} · {{ $f['route'] ? 'route #'.$f['route'] : 'all routes' }} · Generated {{ now()->timezone('Asia/Dhaka')->format('d-m-Y H:i') }} by {{ auth()->user()?->name }} ({{ auth()->user()?->roleLabel() }})</p>
  <table>
    <thead><tr>@foreach($head as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
      @forelse($rows as $r)<tr>@foreach($r as $c)<td class="{{ is_int($c) || is_float($c) ? 'n' : '' }}">{{ is_float($c) ? number_format($c, 2) : (is_int($c) ? number_format($c) : $c) }}</td>@endforeach</tr>
      @empty<tr><td colspan="{{ count($head) }}" style="text-align:center;color:#51607a">No rows for these filters.</td></tr>@endforelse
      @if($total)<tr class="t">@foreach($total as $c)<td class="{{ is_numeric($c) ? 'n' : '' }}">{{ is_float($c) ? number_format($c, 2) : (is_int($c) ? number_format($c) : $c) }}</td>@endforeach</tr>@endif
    </tbody>
  </table>
  <div style="display:flex;justify-content:space-between;color:#51607a;margin-top:10px;border-top:1px solid #d5dce8;padding-top:6px"><span>{{ $s['company_name'] ?? 'NK Corporation' }} · {{ $s['slogan'] ?? '' }}</span><span>Page 1 of 1</span></div>
</div>
@if($auto)<script>window.print()</script>@endif
</body>
</html>
