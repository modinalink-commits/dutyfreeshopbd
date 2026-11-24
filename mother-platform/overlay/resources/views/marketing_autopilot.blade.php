@extends('layout')@section('title','Marketing Autopilot')
@section('body')
<style>.mau td,.mau th{font-size:12.5px;vertical-align:middle}.mau .st{display:inline-block;min-width:54px;text-align:center;border-radius:6px;padding:2px 8px;font-weight:700;font-size:11px;border:1px solid}.mau .on{background:#DCFCE7;color:#166534;border-color:#22C55E}.mau .off{background:#F3F4F6;color:#6B7280;border-color:#D1D5DB}.mau .wait{background:#FFF1E8;color:#C2410C;border-color:#FB923C}.mau .ch{color:#6B7280;font-size:11.5px}.mau .bar{height:5px;border-radius:3px;background:#E5E7EB;min-width:90px;margin-top:3px}.mau .bar i{display:block;height:5px;border-radius:3px;background:#F95505}.mau .kp{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}.mau .kp div{flex:1;min-width:150px;border:1px solid #E5E7EB;border-radius:10px;background:#fff;padding:8px 12px}.mau .kp span{display:block;color:#6B7280;font-size:11.5px}.mau .kp b{font-size:18px;color:#0C161F}</style>
<div class="mau">
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px"><div style="flex:1;min-width:260px"><h1 style="margin:0">Marketing Autopilot</h1><span style="font-size:13px;color:#6B7280">Every automatic marketing channel on one screen: what is running, what went out today, and its daily limit. To change a channel open its page.</span></div><a class="btn g" href="/admin/winback/campaign-profit">Campaign Profit</a> <a class="btn g" href="/admin/m/campaigns">Marketing &amp; Ads</a> <a class="btn g" href="/admin/funnel">Sales Funnel</a></div>
@php $on = collect($rows)->where(3, 'on')->count(); $sent = collect($rows)->whereIn(1, ['SMS', 'WhatsApp', 'Email'])->sum(fn($r) => (int)($r[4] ?? 0)); @endphp
<div class="kp"><div><span>Channels running</span><b>{{ $on }} / {{ count($rows) }}</b></div><div><span>Messages sent today (SMS, WhatsApp, email)</span><b>{{ number_format($sent) }}</b></div><div><span>Orders today (not cancelled)</span><b>{{ number_format((int)($orders->n ?? 0)) }}</b></div><div><span>Sales today</span><b>৳{{ number_format((float)($orders->v ?? 0)) }}</b></div></div>
@if($quiet)<div class="card" style="padding:8px 12px;font-size:12.5px;color:#B45309">Quiet hours now (10 PM – 10 AM, Dhaka): customer messages wait and start again at 10 AM.</div>@endif
<div class="card" style="overflow-x:auto"><table><thead><tr><th>Channel</th><th>What it does</th><th>Status</th><th>Sent today</th><th>Sending hours</th><th></th></tr></thead><tbody>
@foreach($rows as [$name, $ch, $what, $st, $n, $cap, $hrs, $url, $note])
<tr><td style="white-space:nowrap"><b>{{ $name }}</b><br><span class="ch">{{ $ch }}</span></td>
<td style="min-width:240px;white-space:normal">{{ $what }}@if($note !== '')<br><span style="color:#B45309">{{ $note }}</span>@endif</td>
<td><span class="st {{ $st }}">{{ ['on' => 'ON', 'off' => 'OFF', 'wait' => 'WAITING'][$st] }}</span></td>
<td style="white-space:nowrap">@if($n === null)<span class="ch">—</span>@else<b>{{ number_format($n) }}</b>@if($cap) <span class="ch">/ {{ number_format($cap) }}</span><div class="bar"><i style="width:{{ min(100, (int)round($n * 100 / max(1, $cap))) }}%"></i></div>@endif @endif</td>
<td class="ch" style="white-space:nowrap">{{ $hrs }}</td><td><a class="btn g" href="{{ $url }}" style="font-size:12px">Open</a></td></tr>
@endforeach</tbody></table></div>
<div class="card" style="padding:8px 12px;font-size:12.5px;color:#6B7280">Paid ads (Meta, Google) are managed in their own ad managers; spend and results are not copied here. Tobacco products are never promoted in ads or in WhatsApp templates.</div>
</div>
@endsection
