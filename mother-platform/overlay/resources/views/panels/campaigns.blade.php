@php
  $since = now()->subDays(30);
  $src = DB::table('orders')->where('created_at','>=',$since)->select('source',
    DB::raw('count(*) n'), DB::raw('coalesce(sum(total),0) s'),
    DB::raw("sum(case when status in ('Delivered','Partial Delivered','Late Delivery') then 1 else 0 end) ok"),
    DB::raw("sum(case when status = 'Cancel' then 1 else 0 end) cx"),
    DB::raw("sum(case when status in ('Returned','Return Pending') then 1 else 0 end) rt"))
    ->groupBy('source')->orderByDesc('n')->get();
  $tag30 = 0; try { $tag30 = DB::table('orders')->where('created_at','>=',$since)->where('px_meta','like','%"utm"%')->count(); } catch (\Throwable $e) {}
  $known = []; try { $known = DB::table('order_sources')->where('active', true)->pluck('name')->map(fn($v) => mb_strtolower((string)$v))->all(); } catch (\Throwable $e) {}
  $cfg = []; try { $cfg = \App\Support\SmsGate::cfg(); } catch (\Throwable $e) {}
  $smsRow = function (string $p, string $label) use ($cfg) {
    try { $has = \App\Support\SmsGate::hasKey($p); $ready = \App\Support\SmsGate::ready($p); $on = $cfg['on'][$p] ?? false; $prim = ($cfg['primary'] ?? '') === $p; } catch (\Throwable $e) { return $label . ': unknown'; }
    return $label . ': ' . (!$has ? 'No key saved' : (!$ready ? 'Key saved, sender ID missing' : ($on ? 'Connected' . ($prim ? ' (main)' : ' (backup)') : 'Connected but switched off')));
  };
  $capiOn = false; try { $capiOn = \App\Support\Capi::enabled(); } catch (\Throwable $e) {}
  $cnt = function (string $t, array $st = []) { try { $q = DB::table($t); if ($st) $q->whereIn('status', $st); return $q->count(); } catch (\Throwable $e) { return 0; } };
@endphp
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
<div class="card" style="margin:0"><b>Orders by source, last 30 days</b>
<div style="overflow-x:auto"><table><thead><tr><th>Source</th><th>Orders</th><th>Value</th><th>Delivered</th><th>Cancelled</th><th>Returned</th></tr></thead><tbody>
@forelse($src as $x)@php $n = max(1, (int)$x->n); $miss = $known && !in_array(mb_strtolower((string)$x->source), $known, true); @endphp
<tr><td>{{ $x->source ?: '(no source)' }}@if($miss) <small style="color:#B45309" title="Not in the Order sources list">not in list</small>@endif</td><td>{{ $x->n }}</td><td>৳{{ number_format($x->s) }}</td><td>{{ $x->ok }} <small style="color:#6B7280">({{ round($x->ok * 100 / $n) }}%)</small></td><td>{{ $x->cx }} <small style="color:#6B7280">({{ round($x->cx * 100 / $n) }}%)</small></td><td>{{ $x->rt }}</td></tr>
@empty<tr><td colspan="6">No orders yet.</td></tr>@endforelse</tbody></table></div>
<p style="font-size:12px;color:#6B7280;margin:8px 0 0">Delivered counts Delivered, Partial Delivered and Late Delivery; recent orders may still be in transit. Orders from a Facebook/Instagram ad link are often saved as "Web Site"; {{ $tag30 }} orders in the last 30 days carry ad tags (utm).</p></div>
<div class="card" style="margin:0"><b>Connection and tracking</b><table><tbody>
<tr><td>Meta pixel ID</td><td>{{ DB::table('settings')->where('key','pixel_id')->value('value') ?: 'Not set' }} <a href="/admin/site">edit</a></td></tr>
<tr><td>Meta Conversions API (server events)</td><td>{{ $capiOn ? 'Connected' : 'Not connected' }} <a href="/admin/pixel">open</a></td></tr>
<tr><td>Meta Ads spend / ROAS</td><td>Not synced into this platform (see Meta Ads Manager)</td></tr>
<tr><td>Google tag ID</td><td>{{ DB::table('settings')->where('key','gtag_id')->value('value') ?: 'Not set' }} <a href="/admin/site">edit</a></td></tr>
<tr><td>SMS</td><td>{{ $smsRow('alpha','Alpha SMS') }}<br>{{ $smsRow('bulk','Bulk SMS') }} <a href="/admin/sms-marketing">open</a></td></tr>
</tbody></table>
<p style="font-size:12px;color:#6B7280">Order counts always come from the platform, not Facebook's estimate. Ad spend, cost per order and return on spend are read in Meta Ads Manager until a spend sync is set up.</p>
<b style="display:block;margin-top:10px">Real campaigns (managed on their own pages)</b><table><tbody>
<tr><td>SMS campaigns</td><td>{{ $cnt('sms_campaigns', ['scheduled']) }} scheduled · {{ $cnt('sms_campaigns', ['sending']) }} sending · {{ $cnt('sms_campaigns') }} total <a href="/admin/sms-marketing">open</a></td></tr>
<tr><td>Voice (offer call) campaigns</td><td>{{ $cnt('voice_campaigns', ['active']) }} active · {{ $cnt('voice_campaigns') }} total</td></tr>
<tr><td>WhatsApp campaigns</td><td>{{ $cnt('wa_campaigns', ['scheduled','sending']) }} scheduled/sending · {{ $cnt('wa_campaigns') }} total</td></tr>
<tr><td>Push campaigns</td><td>{{ $cnt('push_campaigns', ['scheduled']) }} scheduled · {{ $cnt('push_campaigns') }} total <a href="/admin/push">open</a></td></tr>
</tbody></table></div>
</div>
