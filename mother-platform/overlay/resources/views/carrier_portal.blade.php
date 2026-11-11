<!doctype html><html lang="en"><head>@include('typography')<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Client Portal</title>
<style>*{box-sizing:border-box}body{margin:0;background:#F8FAFC;color:#0B1B33;font-family:Inter,Bornomala,sans-serif;font-size:14px}.top{display:flex;align-items:center;justify-content:space-between;gap:10px;background:#fff;border-bottom:1px solid #E5E7EB;padding:10px max(16px,env(safe-area-inset-left))}.top img{height:34px}.top b{font-size:14px}.top form{margin:0}.top button{border:1px solid #E5E7EB;background:#fff;border-radius:8px;padding:6px 12px;font:inherit;cursor:pointer}.wrap{max-width:980px;margin:0 auto;padding:16px max(16px,env(safe-area-inset-left))}h2{font-size:15px;margin:18px 0 8px}.sub{color:#6B7280;font-size:12px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}.k{background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:12px}.k b{display:block;font-size:20px;font-variant-numeric:tabular-nums}.k span{font-size:12px;color:#6B7280}.k.or{border-top:3px solid #FF5A00}.card{background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:12px;overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:13px}th{text-align:left;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:.4px;padding:6px;border-bottom:1px solid #E5E7EB}td{padding:7px 6px;border-top:1px solid #F1F5F9;font-variant-numeric:tabular-nums}.n{text-align:right}</style></head><body>
<div class="top"><div style="display:flex;align-items:center;gap:10px"><img src="/brand/export-logo.png" alt=""><b>{{ $c->name }}</b></div><form method="post" action="/call-portal/logout">@csrf<button type="submit">Sign out</button></form></div>
<div class="wrap">
<div class="sub">Billing cycle: <b style="color:#0B1B33">{{ $u['from'] }} – {{ $cycleEnds }}</b> ({{ $cycleDays }} days)</div>
<h2>Usage this cycle</h2>
<div class="grid">
<div class="k or"><b>{{ $u['calls'] }}</b><span>Connected calls</span></div>
<div class="k"><b>{{ intdiv($u['secs'],60) }}:{{ str_pad($u['secs']%60,2,'0',STR_PAD_LEFT) }}</b><span>Talk time (min:sec)</span></div>
<div class="k"><b>{{ $u['billed_min'] }}</b><span>Billable minutes</span></div>
<div class="k"><b>{{ $c->rate_per_min === null ? '—' : number_format((float)$c->rate_per_min,2) }}</b><span>Rate per minute</span></div>
<div class="k or"><b>{{ $u['amount'] === null ? '—' : number_format($u['amount'],2) }}</b><span>Amount this cycle</span></div>
@if($u['prepaid'] !== null)<div class="k"><b>{{ $u['prepaid'] }}</b><span>Prepaid minutes</span></div><div class="k"><b style="color:{{ $u['remaining']<0 ? '#B91C1C' : '#059669' }}">{{ $u['remaining'] }}</b><span>Remaining minutes</span></div>@endif
</div>
<p class="sub">Only answered calls are billed; each call rounds up to the next full minute.</p>
<h2>Calls this cycle <span class="sub">(latest {{ count($rows) }})</span></h2>
<div class="card"><table><thead><tr><th>Time</th><th>Number</th><th class="n">Talk time</th><th class="n">Billed min</th><th class="n">Amount</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td>{{ $x['time'] }}</td><td>{{ $x['number'] }}</td><td class="n">{{ intdiv($x['secs'],60) }}:{{ str_pad($x['secs']%60,2,'0',STR_PAD_LEFT) }}</td><td class="n">{{ $x['billed'] }}</td><td class="n">{{ $x['amount'] === null ? '—' : number_format($x['amount'],2) }}</td></tr>
@empty<tr><td colspan="5" class="sub">No calls in this cycle yet.</td></tr>@endforelse
</tbody></table></div></div></body></html>
