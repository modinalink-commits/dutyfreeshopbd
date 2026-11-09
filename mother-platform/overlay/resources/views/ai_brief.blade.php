@extends('layout')@section('title','AI Brief')
@section('body')
<style>
.ab{display:flex;flex-direction:column;gap:12px;font-size:13px}
.ab .cd{background:#fff;border:1px solid #CED4DA;border-radius:12px;padding:12px 14px;min-width:0}
.ab h1{margin:0;font-size:17px}.ab h2{margin:0 0 8px;font-size:13px;font-weight:700}
.ab .mu{color:#6B7280;font-size:12px}
.ab .bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.ab .g4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.ab .g2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.ab .t{border:1px solid #CED4DA;border-radius:10px;padding:9px 11px;background:#FAFBFC}.ab .t b{display:block;font-size:20px;line-height:1.2;color:#0B1220}.ab .t span{font-size:11.5px;color:#6B7280}
.ab .ok{color:#166534}.ab .wn{color:#B45309}.ab .bd{color:#B91C1C}
.ab button.b{height:32px;padding:0 12px;border-radius:9px;border:1px solid #CED4DA;background:#fff;font-weight:700;font-size:12px;cursor:pointer;width:auto;margin:0;color:#0B1220}.ab button.pri{background:#FF5A00;color:#fff;border-color:#FF5A00}
.ab table{width:100%;border-collapse:collapse;font-size:12px}.ab th{text-align:left;color:#6B7280;font-size:10.5px;padding:5px 8px}.ab td{padding:6px 8px;border-top:1px solid #E5E7EB;vertical-align:top}
.ab .txt{white-space:pre-wrap;line-height:1.65;font-size:13.5px;color:#0B1220}
.ab input,.ab select{height:32px;border:1px solid #CED4DA;border-radius:9px;padding:0 9px;font-size:12px;width:auto;margin:0;background:#fff}
.ab .pl{display:inline-block;font-size:10.5px;font-weight:800;border-radius:6px;padding:2px 8px}
@media (max-width:900px){.ab .g4{grid-template-columns:repeat(2,minmax(0,1fr))}.ab .g2{grid-template-columns:minmax(0,1fr)}}
</style>
@php
  $o = $F['orders'] ?? []; $y = $o['yesterday'] ?? []; $st = $F['stock'] ?? []; $cu = $F['customers'] ?? []; $sk = $F['stuck_orders'] ?? []; $rk = $F['risky_orders_last24h'] ?? [];
  $pct = (float)($o['yesterday_vs_target_pct'] ?? 0); $tc = $pct >= 100 ? 'ok' : ($pct >= 60 ? 'wn' : 'bd');
  $n = fn($v) => number_format((float)$v);
@endphp
<div class="ab">
  <div class="bar"><div style="flex:1;min-width:220px"><h1>AI Brief</h1><div class="mu">Phase 1 — read only. The system calculates every number; Claude reads them and writes the brief in Bengali. Nothing here changes an order, a product or a price.</div></div>
    <form method="post" action="/admin/ai-brief/run">@csrf<button class="b pri" @if(!$ready || !$hasKey) disabled @endif>Run now</button></form></div>
  @if(session('ok'))<div class="cd ok">{{ session('ok') }}</div>@endif
  @if(session('err'))<div class="cd wn">{{ session('err') }}</div>@endif
  @if(!$ready)<div class="cd wn">The AI Brief table is being prepared by the update. Refresh in a minute.</div>@endif
  @if(!$hasKey)<div class="cd bd">Claude API key is not saved yet (Settings → AI Chat). The brief can only show system numbers until then.</div>@endif

  @if($cur)
  <div class="cd"><div class="bar" style="justify-content:space-between"><h2 style="margin:0">Brief · {{ \Carbon\Carbon::parse($cur->created_at, 'UTC')->timezone('Asia/Dhaka')->format('d M Y, g:i A') }} <span class="mu">({{ $cur->kind }} · {{ $cur->model }}@if($cur->tokens_in) · {{ number_format($cur->tokens_in) }} in / {{ number_format($cur->tokens_out) }} out tokens @endif)</span></h2>
    <span class="pl" style="background:{{ $cur->status === 'ok' ? '#DCFCE7' : '#FEF3C7' }};color:{{ $cur->status === 'ok' ? '#166534' : '#B45309' }}">{{ $cur->status === 'ok' ? 'AI WRITTEN' : 'SYSTEM NUMBERS ONLY' }}</span></div>
    @if($cur->error)<div class="mu wn" style="margin-top:4px">{{ $cur->error }}</div>@endif
    <div class="txt" style="margin-top:10px">{{ $cur->body }}</div></div>

  <div class="g4">
    <div class="t"><b class="{{ $tc }}">{{ $n($y['orders'] ?? 0) }}</b><span>Orders yesterday ({{ $F['for_day'] ?? '' }}) · {{ $pct }}% of {{ $n($target) }}</span></div>
    <div class="t"><b>{{ $n($y['revenue'] ?? 0) }} TK</b><span>Sales yesterday (7-day avg {{ $n($o['avg_per_day_last7']['revenue'] ?? 0) }} TK)</span></div>
    <div class="t"><b>{{ $y['cancelled'] ?? 0 }} / {{ $y['returns'] ?? 0 }}</b><span>Cancelled / Returns yesterday ({{ $o['yesterday_cancel_rate_pct'] ?? 0 }}% cancel)</span></div>
    <div class="t"><b>{{ $n($o['today_so_far']['orders'] ?? 0) }}</b><span>Orders today so far @if(!empty($o['today_projected_orders']))· on pace for ~{{ $n($o['today_projected_orders']) }}@endif</span></div>
  </div>

  <div class="g2">
    <div class="cd"><h2>Stuck orders</h2>
      <div class="mu">New / Pending over 24 h: <b>{{ $sk['new_or_pending_over_24h'] ?? 0 }}</b> · In shipment over 5 days: <b>{{ $sk['in_shipment_over_5_days'] ?? 0 }}</b></div>
      @if(!empty($sk['examples']))<table><thead><tr><th>Invoice</th><th>Age</th><th>Total</th></tr></thead><tbody>@foreach($sk['examples'] as $e)<tr><td>{{ $e['invoice'] }}</td><td>{{ $e['hours_old'] }} h</td><td>{{ $n($e['total']) }} TK</td></tr>@endforeach</tbody></table>@endif
      @if(!empty($sk['shipment_examples']))<div class="mu" style="margin-top:6px">Shipment too long: @foreach($sk['shipment_examples'] as $e){{ $e['invoice'] }}@if(!empty($e['courier'])) ({{ $e['courier'] }})@endif{{ $loop->last ? '' : ', ' }}@endforeach</div>@endif</div>
    <div class="cd"><h2>Risk checks (last 24 h)</h2>
      <div class="mu">Same number ordered 2+ times: <b>{{ count($rk['same_phone_ordered_2plus_times'] ?? []) }}</b> · Unusually large: <b>{{ count($rk['unusually_large_orders'] ?? []) }}</b> · Short address: <b>{{ count($rk['short_address_new_orders'] ?? []) }}</b>@if(isset($rk['orders_from_blocked_customers'])) · Blocked customers: <b>{{ count($rk['orders_from_blocked_customers']) }}</b>@endif</div>
      @if(!empty($rk['same_phone_ordered_2plus_times']))<table><thead><tr><th>Number</th><th>Orders</th><th>Invoices</th></tr></thead><tbody>@foreach($rk['same_phone_ordered_2plus_times'] as $e)<tr><td>{{ $e['phone'] }}</td><td>{{ $e['orders'] }}</td><td>{{ implode(', ', $e['invoices']) }}</td></tr>@endforeach</tbody></table>@endif
      @if(!empty($rk['unusually_large_orders']))<div class="mu" style="margin-top:6px">Large: @foreach($rk['unusually_large_orders'] as $e){{ $e['invoice'] }} ({{ $n($e['total']) }} TK){{ $loop->last ? '' : ', ' }}@endforeach</div>@endif
      @if(!empty($rk['short_address_new_orders']))<div class="mu" style="margin-top:4px">Short address: {{ implode(', ', $rk['short_address_new_orders']) }}</div>@endif</div>
  </div>

  <div class="g2">
    <div class="cd"><h2>Stock — will run out within 7 days</h2>
      @if(!empty($st['will_run_out_within_7_days']))<table><thead><tr><th>Product</th><th>Stock</th><th>Sold 7d</th><th>Days left</th></tr></thead><tbody>@foreach($st['will_run_out_within_7_days'] as $e)<tr><td>{{ $e['product'] }}</td><td>{{ $e['stock'] }}</td><td>{{ $e['sold_7d'] }}</td><td class="wn"><b>{{ $e['days_left'] }}</b></td></tr>@endforeach</tbody></table>@else<div class="mu">Nothing urgent.</div>@endif</div>
    <div class="cd"><h2>Demand but zero stock</h2>
      @if(!empty($st['demand_but_zero_stock']))<table><thead><tr><th>Product</th><th>Sold 7d</th><th>State</th></tr></thead><tbody>@foreach($st['demand_but_zero_stock'] as $e)<tr><td>{{ $e['product'] }}</td><td>{{ $e['sold_7d'] }}</td><td>{{ $e['state'] }}</td></tr>@endforeach</tbody></table>@else<div class="mu">None.</div>@endif
      <div class="mu" style="margin-top:6px">Published without price: <b>{{ $st['published_without_price'] ?? 0 }}</b> · without image: <b>{{ $st['published_without_image'] ?? 0 }}</b> · Cigarette/Vape at zero stock: <b>{{ $st['stock_controlled_published_at_zero'] ?? 0 }}</b> · Published total: {{ $n($st['published_total'] ?? 0) }}</div></div>
  </div>

  <div class="g2">
    <div class="cd"><h2>Top products — last 7 days</h2>
      @if(!empty($F['top_products_7d']) && !isset($F['top_products_7d']['error']))<table><thead><tr><th>Product</th><th>Qty</th><th>Orders</th><th>Prev 7d</th></tr></thead><tbody>@foreach($F['top_products_7d'] as $e)<tr><td>{{ $e['product'] }}</td><td><b>{{ $e['qty_7d'] }}</b></td><td>{{ $e['orders_7d'] }}</td><td class="mu">{{ $e['qty_previous_7d'] }}</td></tr>@endforeach</tbody></table>@else<div class="mu">No data.</div>@endif</div>
    <div class="cd"><h2>Customers &amp; couriers</h2>
      <div class="mu">Customers who ordered yesterday: <b>{{ $cu['customers_ordered_yesterday'] ?? 0 }}</b> · repeat: <b>{{ $cu['of_which_repeat_customers'] ?? 0 }}</b> ({{ $cu['repeat_share_pct'] ?? 0 }}%) · Lapsed 45–120 days (2+ orders): <b>{{ $cu['lapsed_45_to_120_days_2plus_orders'] ?? 0 }}</b>@if(!empty($cu['incomplete_orders'])) · Abandoned yesterday: <b>{{ $cu['incomplete_orders']['abandoned_yesterday'] }}</b> ({{ $n($cu['incomplete_orders']['abandoned_value_yesterday']) }} TK)@endif</div>
      @if(!empty($F['couriers_last14d']) && !isset($F['couriers_last14d']['error']))<table style="margin-top:6px"><thead><tr><th>Courier (14 d)</th><th>Sent</th><th>Delivered</th><th>Returned</th><th>Return %</th></tr></thead><tbody>@foreach($F['couriers_last14d'] as $e)<tr><td>{{ $e['courier'] }}</td><td>{{ $e['sent'] }}</td><td>{{ $e['delivered'] }}</td><td>{{ $e['returned'] }}</td><td class="{{ $e['return_rate_pct'] > 15 ? 'bd' : '' }}">{{ $e['return_rate_pct'] }}%</td></tr>@endforeach</tbody></table>@endif</div>
  </div>
  @else
  <div class="cd mu">No brief yet. The first one is written automatically after {{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00 (Dhaka), or press "Run now".</div>
  @endif

  <div class="g2">
    <div class="cd" style="overflow-x:auto"><h2>History</h2>
      <table><thead><tr><th>When (Dhaka)</th><th>Kind</th><th>Status</th><th></th></tr></thead><tbody>
      @forelse($rows as $r)<tr><td>{{ \Carbon\Carbon::parse($r->created_at, 'UTC')->timezone('Asia/Dhaka')->format('d M, g:i A') }}</td><td>{{ $r->kind }}</td><td>{{ $r->status === 'ok' ? 'AI' : 'numbers only' }}</td><td><a href="/admin/ai-brief?id={{ $r->id }}">Open</a></td></tr>@empty<tr><td colspan="4" class="mu">Nothing yet.</td></tr>@endforelse</tbody></table></div>
    <div class="cd"><h2>Settings</h2>
      <form method="post" action="/admin/ai-brief/settings" class="bar">@csrf
        <label><input type="checkbox" name="on" value="1" @if($on) checked @endif style="width:auto;height:auto"> Write a brief every day</label>
        <label class="mu">at</label><select name="hour">@for($h = 5; $h <= 12; $h++)<option value="{{ $h }}" @if($h === $hour) selected @endif>{{ $h }}:00 {{ $h < 12 ? 'AM' : 'PM' }}</option>@endfor</select>
        <button class="b">Save</button></form>
      <div class="mu" style="margin-top:8px">Model: {{ $model }}. Phone numbers are masked and no address is ever sent to Claude. One brief uses a few thousand tokens.</div></div>
  </div>
</div>
@endsection
