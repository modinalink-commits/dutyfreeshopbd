@extends('layout')@section('title','Bundle Pricing')
@section('body')
<div class="noprint" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Sales &amp; Bundle Pricing Settings</h1><span style="font-size:13px;color:#6B7280">One global rule for every product. Bundle price = sale price × (1 − discount %), calculated from the <b>current sale price</b> and automatically capped so the unit price never goes below <b>FIFO cost × 1.15</b>.</span></div><a class="btn g" href="/admin/inventory-pricing">Inventory Pricing</a></div>
@if(session('ok'))<div style="background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;border-radius:10px;padding:8px 12px;margin-bottom:8px;font-size:13px">{{ session('ok') }}</div>@endif
@if($errors->any())<div style="background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;border-radius:10px;padding:8px 12px;margin-bottom:8px;font-size:13px">{{ $errors->first() }}</div>@endif
@if(!$ready)<div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:8px 12px;margin-bottom:8px;font-size:13px">Bundle pricing is being installed. Reload in a minute.</div>@endif
<form method="post" action="/admin/bundle-pricing" style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:14px;margin-bottom:12px;max-width:760px">@csrf
<label class="tg"><input type="checkbox" name="bundle_on" value="1" {{ $cfg['on'] ? 'checked' : '' }}> Bundle pricing ON (Buy 2 / 3 / 4 / 5 offers on the product page)</label>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin:10px 0">
@foreach([2,3,4,5] as $n)<div><label>Buy {{ $n }} discount %</label><input name="pct{{ $n }}" type="number" step="0.01" min="0" max="20" value="{{ rtrim(rtrim(number_format($cfg['pct'][$n], 2, '.', ''), '0'), '.') }}"></div>@endforeach
<div><label>Minimum protected profit</label><input value="15%  (fixed rule)" disabled></div></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;margin-bottom:10px">
<div><label>Unsafe bundle handling</label><select name="unsafe"><option value="reduce" {{ $cfg['unsafe']==='reduce' ? 'selected' : '' }}>Auto Reduce Discount (recommended)</option><option value="disable" {{ $cfg['unsafe']==='disable' ? 'selected' : '' }}>Disable that bundle tier</option></select></div>
<div><label>Bundle price rounding</label><select name="round"><option value="none" {{ $cfg['round']===0 ? 'selected' : '' }}>Exact</option><option value="1" {{ $cfg['round']===1 ? 'selected' : '' }}>Nearest ৳1</option><option value="5" {{ $cfg['round']===5 ? 'selected' : '' }}>Nearest ৳5</option><option value="10" {{ $cfg['round']===10 ? 'selected' : '' }}>Nearest ৳10</option></select></div></div>
<button>Save and recalculate every product</button> <span style="font-size:12.5px;color:#6B7280">Rule version now: {{ $cfg['version'] }}. After rounding, the 15% check runs again. Every change is written to the audit log.</span>
</form>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:10px 14px;margin-bottom:10px;font-size:14px">Global rule: <b>{{ $st['global'] }}</b> products &nbsp;|&nbsp; Custom: {{ $st['custom'] }} &nbsp;|&nbsp; Off: {{ $st['off'] }} &nbsp;|&nbsp; ⚠ Discount auto-reduced: <b>{{ $st['capped'] }}</b> &nbsp;|&nbsp; Some tiers not offered: <b>{{ $st['limited'] }}</b></div>
@if(count($capped))
<h3 style="margin:14px 0 6px">Products where the bundle discount is limited by the 15% protection</h3>
<div class="rp-body"><div style="overflow:auto;max-height:50vh"><table class="pl"><thead><tr><th>Product</th><th class="n">Sale price</th><th>Auto-reduced (Buy → applied %)</th><th>Tiers not offered</th><th></th></tr></thead><tbody>
@foreach($capped as $c)<tr><td>{{ $c['name'] }}</td><td class="n">{{ number_format($c['price'], 2) }}</td><td>{{ implode(' · ', $c['caps']) ?: '—' }}</td><td>{{ $c['miss'] ? 'Buy '.implode(', Buy ', $c['miss']) : '—' }}</td><td><a href="/admin/products/{{ $c['id'] }}/edit">Edit price</a></td></tr>@endforeach
</tbody></table></div></div>
<p style="font-size:12.5px;color:#6B7280">Showing up to 60 products. Raising the sale price opens the full bundle discount again automatically.</p>
@endif
<h3 style="margin:16px 0 6px">Bundle performance (orders placed with the new pricing snapshot)</h3>
<div class="rp-body"><div style="overflow:auto;max-height:50vh"><table class="pl"><thead><tr><th>Product</th><th class="n">Buy 2 orders</th><th class="n">Buy 3 orders</th><th class="n">Buy 4 orders</th><th class="n">Buy 5+ orders</th><th class="n">Bundle revenue</th><th class="n">Bundle discount</th><th class="n">Actual COGS (shipped)</th><th class="n">Profit (shipped)</th><th class="n">Profit %</th></tr></thead><tbody>
@forelse($perf as $x)@php $pr = $x->cogs !== null ? (float)$x->rev_c - (float)$x->cogs : null; @endphp<tr><td>{{ $x->name }}</td><td class="n">{{ $x->b2 }}</td><td class="n">{{ $x->b3 }}</td><td class="n">{{ $x->b4 }}</td><td class="n">{{ $x->b5 }}</td><td class="n">{{ number_format((float)$x->rev, 2) }}</td><td class="n">{{ number_format((float)$x->disc, 2) }}</td><td class="n">{{ $x->cogs !== null ? number_format((float)$x->cogs, 2) : '—' }}</td><td class="n">{{ $pr !== null ? number_format($pr, 2) : '—' }}</td><td class="n">{{ $pr !== null && (float)$x->cogs > 0 ? number_format($pr / (float)$x->cogs * 100, 1).'%' : '—' }}</td></tr>
@empty<tr><td colspan="10" style="padding:14px">No bundle order yet. Orders placed from now on are recorded here (Buy 2 to Buy 5 and above).</td></tr>@endforelse
</tbody></table></div></div>
@endsection
