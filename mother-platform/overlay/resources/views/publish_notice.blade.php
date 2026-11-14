@extends('layout')@section('title','Back in Store SMS')
@section('body')
<style>.pbn .card{padding:12px 14px;margin-bottom:10px}.pbn h2{font-size:14px;margin:0 0 8px;color:#0B1B33}.pbn .f{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end}.pbn label{font-size:12.5px;color:#374151;display:block}.pbn input[type=number]{width:96px}.pbn small{color:#6B7280}.pbn table{width:100%;border-collapse:collapse;font-size:13px}.pbn th{font-size:11px;color:#6B7280;text-align:left;padding:7px 8px;border-bottom:1px solid #E5E7EB}.pbn td{padding:7px 8px;border-bottom:1px solid #EEF1F4}.pbn pre{white-space:pre-wrap;font-family:inherit;font-size:13px;background:#F8FAFC;border:1px solid #E5E7EB;border-radius:8px;padding:8px;margin:6px 0}</style>
<div class="pbn">
<div style="margin-bottom:8px"><h1 style="font-size:18px;margin:0">Back in Store SMS</h1><small>When a product goes from Unpublished to Published, everyone who received that product before gets one SMS. Opted-out numbers are never messaged.</small></div>
@if(session('ok'))<div class="card" style="background:#F0FAF3;border-color:#BFE3CC;color:#14683A;font-weight:600">{{ session('ok') }}</div>@endif
@if(session('err'))<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">{{ session('err') }}</div>@endif
@if(!$ready)<div class="card" style="background:#FFF4ED;border-color:#FED7AA;color:#B93815">The database table is not ready yet; it is created on the next deploy.</div>@endif
<div class="card"><h2>Settings</h2>
<form method="post" action="/admin/back-in-store-sms">@csrf<input type="hidden" name="act" value="settings">
<div class="f"><label style="display:flex;gap:6px;align-items:center;font-size:13px;color:#0B1B33"><input type="hidden" name="on" value="0"><input type="checkbox" name="on" value="1" @checked($on)> Send automatically</label>
<label>Do not repeat to the same customer for (days)<br><input type="number" name="days" min="1" value="{{ $days }}"></label>
<label>Product must have been unpublished for (minutes)<br><input type="number" name="min_off" min="0" value="{{ $minOff }}"></label>
<label>Hold if more products than this are published at once<br><input type="number" name="max_products" min="1" value="{{ $maxAt }}"></label>
<label>Cigarette / Vape: minimum stock to announce (0 = none)<br><input type="number" name="min_stock" min="0" value="{{ $minStock }}"></label>
<label>Daily SMS cap (0 = none)<br><input type="number" name="daily_cap" min="0" value="{{ $cap }}"></label></div>
<label style="margin-top:10px">Message text. {product} = product name, {link} = short link, {price} = price<br><textarea name="text" rows="3" style="width:100%;max-width:640px">{{ old('text', $text) }}</textarea></label>
<button class="btn" type="submit" style="margin-top:8px">Save</button></form>
<div style="border-top:1px solid #E5E7EB;margin-top:10px;padding-top:8px;font-size:13px">Example{{ $p ? ' for '.$p->name : '' }}:<pre>{{ $body }}</pre>
<small>{{ $len }} characters · {{ $parts }} SMS part{{ $parts === 1 ? '' : 's' }} · about {{ number_format($parts * $unit, 2) }} TK per customer. Sent today: {{ number_format($today) }}.</small></div></div>
<div class="card"><h2>One product</h2>
<form method="get" action="/admin/back-in-store-sms" class="f"><label>Product ID<br><input type="number" name="pid" min="1" value="{{ $p->id ?? '' }}"></label><button class="btn g" type="submit">Count customers</button></form>
@if($p)<div style="margin-top:8px;font-size:13px"><b>{{ $p->name }}</b> · {{ $p->published ? 'Published' : 'Unpublished' }} · <b>{{ number_format($cnt) }}</b> customer{{ $cnt === 1 ? '' : 's' }} would get the SMS now (about {{ number_format($cnt * $parts * $unit) }} TK).</div>
<div class="f" style="margin-top:8px"><form method="post" action="/admin/back-in-store-sms" class="f">@csrf<input type="hidden" name="act" value="test"><input type="hidden" name="pid" value="{{ $p->id }}"><input name="phone" placeholder="01XXXXXXXXX" style="max-width:170px"><button class="btn g" type="submit">Test to this number</button></form>
<form method="post" action="/admin/back-in-store-sms">@csrf<input type="hidden" name="act" value="send"><input type="hidden" name="pid" value="{{ $p->id }}"><button class="btn" type="submit" @disabled(!$p->published || !$cnt)>Send to these {{ number_format($cnt) }} now</button></form></div>@endif</div>
<div class="card" data-nogrid data-no-export><h2>Delivery status (last 7 days)</h2>@if($status->isEmpty())<div style="color:#6B7280">No Back in Store SMS in the last 7 days.</div>@else
<div style="overflow-x:auto"><table class="no-export"><tr><th>Status</th><th style="text-align:right">SMS</th><th style="text-align:right">Parts</th><th style="text-align:right">Cost (TK)</th><th>Last update</th></tr>
@foreach($status as $x)<tr><td>{{ ucfirst($x->status) }}</td><td style="text-align:right">{{ number_format($x->n) }}</td><td style="text-align:right">{{ number_format($x->parts) }}</td><td style="text-align:right">{{ number_format($x->cost, 2) }}</td><td>{{ $x->last ? \Carbon\Carbon::parse($x->last, config('app.timezone'))->timezone('Asia/Dhaka')->format('d M, h:i A') : '' }}</td></tr>@endforeach
<tr style="font-weight:700"><td>Total</td><td style="text-align:right">{{ number_format($status->sum('n')) }}</td><td style="text-align:right">{{ number_format($status->sum('parts')) }}</td><td style="text-align:right">{{ number_format($status->sum('cost'), 2) }}</td><td></td></tr></table></div>
<small style="display:block;margin-top:6px">Queued = waiting to go to the SMS gateway. The status is what the gateway reported back to us.</small>@endif</div>
<div class="card" data-nogrid data-no-export><h2>Recent sends</h2>@if($log->isEmpty())<div style="color:#6B7280">Nothing sent yet.</div>@else
<table class="no-export"><tr><th>When</th><th>Detail</th></tr>@foreach($log as $l)<tr><td style="white-space:nowrap">{{ \Carbon\Carbon::parse($l->created_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</td><td>{{ $l->subject }}</td></tr>@endforeach</table>@endif</div>
</div>
@endsection
