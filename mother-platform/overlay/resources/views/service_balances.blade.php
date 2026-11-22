@extends('layout')@section('title','Services and Balances')
@section('body')
<style>.sb .card{padding:12px 14px;margin-bottom:10px}.sb table{width:100%;border-collapse:collapse;font-size:12.5px}.sb th{font-size:11px;color:#6B7280;text-align:left;padding:6px 8px;border-bottom:1px solid #E5E7EB;white-space:nowrap}.sb td{padding:7px 8px;border-bottom:1px solid #F1F2F4;vertical-align:top}.sb .pill{display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600}.sb .ok{background:#ECFDF3;color:#067647}.sb .low,.sb .expired,.sb .failed{background:#FEF3F2;color:#B42318}.sb .soon{background:#FFFAEB;color:#B54708}.sb .unset{background:#F2F4F7;color:#475467}.sb .mu{color:#6B7280}.sb form.row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;padding:8px 0}.sb label{font-size:11px;color:#6B7280;display:block;margin-bottom:2px}.sb input,.sb select{width:100%;box-sizing:border-box}.sb .scroll{overflow-x:auto}</style>
<div class="sb">
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:8px"><div><h1 style="font-size:18px;margin:0">Services and Balances</h1><small class="mu">What each paid service, API or subscription still has and when it renews. Automatic rows are read from the provider every 30 minutes; the others are typed in by hand. Low balance and near renewal raise an alert in the Issues Center.</small></div>
@unless($off)<form method="post" action="/admin/services/refresh" style="margin:0">@csrf<button class="btn" type="submit">Check automatic services now</button></form>@endunless</div>
@if($off)<div class="card">This page is installed on the next deploy.</div>@else
@if(session('ok'))<div class="card" style="background:#F0FAF3;border-color:#BFE3CC;color:#14683A;font-weight:600">{{ session('ok') }}</div>@endif
@if(session('err'))<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">{{ session('err') }}</div>@endif
@if($errors->any())<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">{{ $errors->first() }}</div>@endif
@php $dt = fn($x) => $x ? \Carbon\Carbon::parse($x, 'UTC')->timezone('Asia/Dhaka')->format('d M h:i A') : '—'; $lab = ['ok'=>'OK','low'=>'Low','expired'=>'Expired','soon'=>'Renew soon','failed'=>'Check failed','unset'=>'Not set']; $attn = collect($st)->filter(fn($x) => in_array($x[0], ['low','expired','soon','failed'], true))->count(); @endphp
<div class="card" style="font-weight:600;{{ $attn ? 'background:#FFFAEB;border-color:#FEDF89;color:#B54708' : 'background:#F0FAF3;border-color:#BFE3CC;color:#14683A' }}">{{ $attn ? $attn.' service(s) need attention' : 'Nothing needs attention right now' }}</div>
<div class="card"><div class="scroll"><table><thead><tr><th>Service</th><th>Type</th><th>Balance</th><th>Daily use</th><th>Lasts about</th><th>Alert limit</th><th>Renews / resets</th><th>Status</th><th>Last update</th><th></th></tr></thead><tbody>
@foreach($rows as $r)@php [$s, $l, $days] = $st[$r->id]; $u = $use[$r->id] ?? ['perDay' => null, 'days' => null, 'since' => null]; $renewOnly = in_array($r->category, ['Hosting', 'Domain'], true); @endphp
<tr style="{{ $r->active ? '' : 'opacity:.5' }}"><td><b>{{ $r->name }}</b><br><small class="mu">{{ $r->category }}</small></td><td>{{ $r->kind === 'auto' ? 'Automatic' : 'Typed in' }}</td>
<td>{{ $r->balance !== null ? number_format((float)$r->balance, 2).' '.$r->unit : '—' }}</td>
<td>@if($u['perDay'] === null){{ $r->balance !== null ? 'Collecting…' : '—' }}@if($r->balance !== null)<br><small class="mu">needs 12 h of readings</small>@endif @elseif($u['perDay'] <= 0)No use<br><small class="mu">since {{ $u['since'] }}</small>@else{{ number_format($u['perDay'], 2).' '.$r->unit }}/day<br><small class="mu">average since {{ $u['since'] }}</small>@endif</td>
<td>@if($r->code === 'bdcourier')<small class="mu">resets daily</small>@elseif($u['days'] !== null)~{{ $u['days'] }} day(s)<br><small class="mu">till {{ now('Asia/Dhaka')->addDays($u['days'])->format('d M Y') }}</small>@else—@endif</td>
<td>{{ $r->low_at !== null ? number_format((float)$r->low_at, 2).' '.$r->unit : '—' }}</td>
<td>{{ $r->expires_on ? \Carbon\Carbon::parse($r->expires_on)->format('d M Y') : '—' }}@if($days !== null)<br><small class="mu">{{ $days < 0 ? abs($days).' day(s) ago' : 'in '.$days.' day(s)' }}</small>@endif</td>
<td><span class="pill {{ $s }}">{{ $lab[$s] ?? $l }}</span></td><td><small class="mu">{{ $dt($r->checked_at) }}@if($r->last_text)<br>{{ $r->last_text }}@endif</small></td>
<td style="white-space:nowrap">@if(!empty($r->recharge_url))<a class="btn" href="{{ $r->recharge_url }}" target="_blank" rel="noopener noreferrer" style="margin-right:6px">{{ $renewOnly ? 'Renew' : 'Recharge' }}</a>@endif<details style="display:inline-block"><summary style="cursor:pointer;color:#E8590C">Edit</summary></details></td></tr>
<tr><td colspan="10"style="padding:0 8px;border:0">
<form class="row" method="post" action="/admin/services/save" style="display:none" data-edit="{{ $r->id }}">@csrf<input type="hidden" name="id" value="{{ $r->id }}">
<div><label>Name</label><input name="name" value="{{ $r->name }}" {{ $r->kind === 'auto' ? 'readonly' : 'required' }}></div>
<div><label>Category</label><input name="category" value="{{ $r->category }}" {{ $r->kind === 'auto' ? 'readonly' : '' }}></div>
<div><label>Unit</label><input name="unit" value="{{ $r->unit }}" {{ $r->kind === 'auto' ? 'readonly' : '' }}></div>
@if($r->kind === 'manual')<div><label>Balance</label><input name="balance" type="number" step="0.01" value="{{ $r->balance }}"></div>@endif
<div><label>Alert when at or below</label><input name="low_at" type="number" step="0.01" min="0" value="{{ $r->low_at }}"></div>
<div><label>Renewal / expiry date</label><input name="expires_on" type="date" value="{{ $r->expires_on }}"></div>
<div><label>Remind before (days)</label><input name="remind_days" type="number" min="0" max="365" value="{{ $r->remind_days }}"></div>
<div style="grid-column:1/-1"><label>Recharge / renew page (https link, opens the provider's own page)</label><input name="recharge_url" type="url" maxlength="300" value="{{ $r->recharge_url ?? '' }}"></div>
<div style="grid-column:1/-1"><label>Note</label><input name="note" value="{{ $r->note }}"></div>
<div><label>Active</label><select name="active"><option value="1" @selected($r->active)>Yes</option><option value="0" @selected(!$r->active)>No (hide alerts)</option></select></div>
<div style="display:flex;gap:6px;align-items:flex-end"><button class="btn" type="submit">Save</button></div></form>
@if($r->kind === 'manual')<form method="post" action="/admin/services/{{ $r->id }}/delete" style="display:none;padding:0 0 8px" data-del="{{ $r->id }}" onsubmit="return confirm('Remove this service?')">@csrf<button class="btn" type="submit" style="color:#B42318">Remove</button></form>@endif
@if($r->kind === 'auto')<form method="post" action="/admin/services/refresh" style="display:none;padding:0 0 8px" data-chk="{{ $r->id }}">@csrf<input type="hidden" name="id" value="{{ $r->id }}"><button class="btn" type="submit">Check this one now</button></form>@endif
</td></tr>
@endforeach</tbody></table></div></div>
<div class="card"><b style="font-size:13px">Add a service</b>
<form class="row" method="post" action="/admin/services/save">@csrf
<div><label>Name</label><input name="name" required maxlength="120" placeholder="e.g. Courier prepaid credit"></div>
<div><label>Category</label><input name="category" maxlength="30" placeholder="SMS, Hosting, AI…"></div>
<div><label>Unit</label><input name="unit" maxlength="16" value="BDT"></div>
<div><label>Balance</label><input name="balance" type="number" step="0.01"></div>
<div><label>Alert when at or below</label><input name="low_at" type="number" step="0.01" min="0"></div>
<div><label>Renewal / expiry date</label><input name="expires_on" type="date"></div>
<div><label>Remind before (days)</label><input name="remind_days" type="number" min="0" max="365" value="14"></div>
<div style="grid-column:1/-1"><label>Recharge / renew page (https link)</label><input name="recharge_url" type="url" maxlength="300" placeholder="https://"></div>
<div style="grid-column:1/-1"><label>Note</label><input name="note" maxlength="1000"></div>
<div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Add</button></div></form>
<small class="mu">Hostinger, domains, PBX, Meta Ads, OpenAI and Anthropic have no balance we can read automatically, so they are typed in here. A blank balance means "not set"; nothing is estimated. Daily use is the real average drop between the saved readings of the last 14 days (a rise counts as a recharge, not as use), so it appears after about 12 hours of readings; typed-in rows need a fresh balance typed in now and then. The Recharge / Renew button only opens the provider's own page: nothing is paid from here.</small></div>
<script>document.querySelectorAll('.sb tbody tr').forEach(function(tr){var s=tr.querySelector('summary');if(!s)return;var nx=tr.nextElementSibling;s.addEventListener('click',function(e){e.preventDefault();if(!nx)return;nx.querySelectorAll('form').forEach(function(f){f.style.display=f.style.display==='none'?'':'none'})})});</script>
@endif
</div>
@endsection
