<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Loading slip {{ $trip->trip_no }}</title>
<link rel="stylesheet" href="/css/nk-table.css?v=1">
<style>body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;color:#14213d;margin:24px;font-size:13px}h1{font-size:18px;margin:0}.g{display:grid;grid-template-columns:repeat(2,1fr);gap:6px 20px;margin:14px 0}.g span{color:#51607a}.sig{display:flex;gap:40px;margin-top:48px}.sig div{flex:1;border-top:1px solid #14213d;padding-top:6px;text-align:center}@media print{button{display:none}}</style></head>
<body>
<div style="display:flex;align-items:center;gap:12px"><img src="/img/logo.webp" alt="" style="width:44px;height:44px;border-radius:8px"><div style="flex-grow:1"><h1>NK Corporation · Loading slip</h1><div>{{ $trip->trip_no }} · {{ $trip->trip_date->format('d-m-Y') }} · {{ \App\Models\Trip::STATUS_LABELS[$trip->status] }}</div></div><button onclick="window.print()">Print</button></div>
<div class="g">
  <div><span>Vehicle:</span> <b>{{ $trip->vehicle->code }} · {{ $trip->vehicle->reg_no }}</b></div><div><span>Route:</span> <b>{{ $trip->route?->name ?? '—' }}</b></div>
  <div><span>Driver:</span> <b>{{ $trip->driver?->name ?? '—' }}</b></div><div><span>Salesman:</span> <b>{{ $trip->salesman?->name ?? '—' }}</b></div>
  <div><span>Loading time:</span> <b>{{ $trip->loading_time ?? '—' }}</b></div><div><span>Warehouse staff:</span> <b>{{ $trip->warehouseStaff?->name ?? $counter?->name ?? '—' }}</b></div>
</div>
<div class="dt-wrap"><table class="dt"><thead><tr><th>Product</th><th>Size</th><th class="r">Quantity loaded</th></tr></thead><tbody>
@foreach($trip->loads->sortBy('size_kg') as $l)<tr><td>LPG refill cylinder</td><td class="c">{{ $l->size_kg }} kg</td><td class="r">{{ $l->qty }}</td></tr>@endforeach
<tr><td colspan="2"><b>Total</b></td><td class="r"><b>{{ $trip->loads->sum('qty') }}</b></td></tr></tbody></table></div>
<p>Loading is not a sale. These cylinders stay company stock on the vehicle until sold or returned.</p>
<div class="sig"><div>Warehouse staff</div><div>Driver</div><div>Salesman</div><div>Manager{{ $approver ? ' · '.$approver->name : '' }}</div></div>
</body></html>
