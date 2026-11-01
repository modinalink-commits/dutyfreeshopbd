@extends('layout')@section('title','SMS Settings')
@section('body')
<h1 style="margin:0 0 12px">SMS Settings</h1>@include('smsmk.nav')
<div class="card">Gateway: <b>{{ $ready ? 'Connected' : 'Not set' }}</b> · Sender ID: <b>{{ $sender ?: '—' }}</b> · Balance: <b>{{ $bal === null ? '—' : '৳'.number_format($bal,2) }}</b> · <a href="/admin/sms">Sender ID / API settings &amp; test SMS</a> (key is stored encrypted, never shown again)</div>
<form class="card" method="post" action="/admin/sms-marketing/settings" style="max-width:640px">@csrf
<label>Cost per SMS part (৳) — used for estimates</label><input name="unit" type="number" step="0.01" value="{{ $unit }}" required>
<label>VIP when total spend ≥ (৳)</label><input name="vip" type="number" value="{{ $vip }}" required>
<label>High-value when total spend ≥ (৳)</label><input name="high" type="number" value="{{ $high }}" required>
<label>Low-balance alert below (৳)</label><input name="low" type="number" value="{{ $low }}" required>
<label>Last recharge (note)</label><input name="recharge" value="{{ $recharge }}" placeholder="e.g. 01 Nov 2026 · ৳2,000">
<div style="margin-top:12px"><button>Save</button></div></form>
<div class="card" style="max-width:640px"><b>Marketing opt-out list</b><p style="font-size:12px;color:#6B7280">Numbers here never get marketing SMS (order / delivery / payment SMS are not affected).</p>
<form method="post" action="/admin/sms-marketing/optout">@csrf<textarea name="phones" rows="2" placeholder="01XXXXXXXXX, 01XXXXXXXXX"></textarea><input name="reason" placeholder="reason (optional)" style="margin-top:6px"><div style="margin-top:8px"><button>Add to opt-out</button></div></form>
<table style="margin-top:10px"><tbody>@foreach($opt as $o)<tr><td>{{ $o->phone }}</td><td style="font-size:12px">{{ $o->reason }}</td><td><form method="post" action="/admin/sms-marketing/optout" style="margin:0">@csrf<input type="hidden" name="do" value="remove"><input type="hidden" name="id" value="{{ $o->id }}"><button class="g">Remove</button></form></td></tr>@endforeach</tbody></table></div>
@endsection
