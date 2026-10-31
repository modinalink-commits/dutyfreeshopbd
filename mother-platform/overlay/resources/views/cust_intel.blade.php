@php
 $cc = ['VIP'=>['#7C3AED','#F3E8FF'],'Regular'=>['#2563EB','#DBEAFE'],'New'=>['#0891B2','#CFFAFE'],'High Value'=>['#B45309','#FEF3C7'],'Watchlist'=>['#B91C1C','#FEE2E2']][$ci['cat']];
 $rc = ['Low'=>['#166534','#DCFCE7'],'Medium'=>['#C2410C','#FFEDD5'],'High'=>['#B91C1C','#FEE2E2']][$ci['risk']];
 $wa = \App\Support\CustomerIntel::wa($o->phone);
@endphp
<div class="ci" style="min-width:230px;max-width:330px;font-size:12px;line-height:1.5">
 <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap"><b style="font-size:13px">{{ $o->customer_name }}</b><span style="background:{{ $cc[1] }};color:{{ $cc[0] }};border:1px solid {{ $cc[0] }}33;border-radius:999px;padding:1px 8px;font-weight:800;font-size:10.5px">{{ $ci['cat'] }}</span></div>
 <div style="color:#6B7280">{{ $o->phone }} · ID {{ $ci['cid'] }}</div>
 <div style="color:#6B7280">{{ \Illuminate\Support\Str::limit(trim(($o->address ?? '').' '.($o->district ?? '')) ?: '—', $full ?? false ? 400 : 60) }}</div>
 <div style="margin-top:3px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:3px 7px">Total <b>{{ $ci['total'] }}</b> | Delivered <b style="color:#166534">{{ $ci['delivered'] }}</b> | Return <b style="color:#7C3AED">{{ $ci['returned'] }}</b> | Cancel <b style="color:#B91C1C">{{ $ci['cancelled'] }}</b> | Pending <b style="color:#C2410C">{{ $ci['pending'] }}</b><br>Success rate: <b>{{ $ci['rate'] === null ? '—' : $ci['rate'].'%' }}</b>@if($ci['legacy']) <small style="color:#9CA3AF">(incl. {{ $ci['legacy'] }} imported)</small>@endif</div>
 <div style="margin-top:3px;background:{{ $rc[1] }};color:{{ $rc[0] }};border:1px solid {{ $rc[0] }}44;border-radius:8px;padding:3px 7px;font-weight:700">Reliability {{ $ci['rel'] }}/100 · Fraud {{ $ci['fraud'] }}/100 · {{ $ci['risk'] }} Risk<br><span style="font-weight:600">{{ $ci['verdict']==='safe' ? '✅ Safe to confirm' : ($ci['verdict']==='call' ? '⚠ Verify by call before confirming' : '⛔ High risk — confirm only after verification / advance payment') }}</span></div>
 <div style="display:flex;gap:6px;margin-top:4px;font-size:15px"><a title="Call" href="tel:{{ $o->phone }}" style="text-decoration:none">📞</a><a title="WhatsApp" target="_blank" rel="noopener" href="https://wa.me/{{ $wa }}" style="text-decoration:none">💬</a>@if($ci['cuid'])<a title="Customer profile" href="/admin/m/customers/{{ $ci['cuid'] }}/edit" style="text-decoration:none">👤</a>@endif<a title="Previous orders" href="/admin/orders?q={{ $o->phone }}" style="text-decoration:none">🧾</a></div>
</div>
