@extends('layout')@section('title','Restock Follow-up')
@section('body')
<style>.rsk .card{padding:12px 14px;margin-bottom:10px}.rsk h2{font-size:14px;margin:0 0 8px}.rsk table{width:100%;border-collapse:collapse;font-size:12.5px}.rsk th{font-size:11px;color:#6B7280;text-align:left;padding:6px 8px;border-bottom:1px solid #E5E7EB;white-space:nowrap}.rsk td{padding:6px 8px;border-bottom:1px solid #EEF1F4;vertical-align:top}.rsk .n{text-align:right;font-variant-numeric:tabular-nums}.rsk .kp{display:flex;gap:10px;flex-wrap:wrap}.rsk .kp div{border:1px solid #E5E7EB;border-radius:10px;padding:8px 12px;min-width:120px;background:#fff}.rsk .kp b{display:block;font-size:18px}.rsk .kp small{color:#6B7280}.rsk .f{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.rsk label{font-size:12px;color:#374151}.rsk input[type=number],.rsk input[type=text]{height:30px}.rsk .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11.5px;font-weight:700;background:#EEF2F7;color:#0B1B33;white-space:nowrap}.rsk .mu{color:#6B7280}.rsk img.th{width:34px;height:34px;object-fit:contain;border:1px solid #E5E7EB;border-radius:6px;background:#fff}</style>
<div class="rsk">
<div style="margin-bottom:8px"><h1 style="font-size:18px;margin:0">Restock Follow-up</h1><small class="mu">Watches stock and follows up with the purchase manager on WhatsApp. Products covered: {{ ['smoke'=>'Cigarette and Vape categories only','general'=>'general products only (no Cigarette / Vape)','all'=>'all categories'][$c['scope']] }}. Single Packet items are never listed.</small></div>
@if($off)<div class="card">The tables for this module are not installed yet. They are created on the next deploy.</div>@else
@if(session('ok'))<div class="card" style="background:#F0FAF3;border-color:#BFE3CC;color:#14683A;font-weight:600">{{ session('ok') }}</div>@endif
@if(session('err'))<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">{{ session('err') }}</div>@endif
@if($errors->any())<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">{{ $errors->first() }}</div>@endif
@if($blocked === null)<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">The Cigarette / Vape categories could not be found, so nothing is scanned. This is a safety stop: fix the category names first.</div>@endif
@php $needRows = collect($fig)->where('need', true); $st = fn($x) => \App\Support\Restock::LAB[$x] ?? $x; $dt = fn($x) => $x ? \Carbon\Carbon::parse($x, 'UTC')->timezone('Asia/Dhaka')->format('d M h:i A') : '—'; @endphp
<div class="kp" style="margin-bottom:10px">
<div><b style="color:{{ $c['on'] ? '#067647' : '#B42318' }}">{{ $c['on'] ? 'ON' : 'OFF' }}</b><small>Automatic follow-up</small></div>
<div><b>{{ count($fig) }}</b><small>Products watched</small></div>
<div><b style="color:#B54708">{{ $needRows->count() }}</b><small>Need buying now</small></div>
<div><b>{{ $open->count() }}</b><small>Open tasks</small></div>
<div><b style="font-size:14px;line-height:26px;color:{{ $verified ? '#067647' : '#B54708' }}">{{ $verified ? 'Verified' : 'Not verified' }}</b><small>{{ $c['name'] }} …{{ substr($c['wa'], -4) }}</small></div>
<div><b style="font-size:14px;line-height:26px">{{ $tpl }}</b><small>WhatsApp template</small></div>
<div><b style="font-size:14px;line-height:26px">{{ $window ? 'Open' : 'Closed' }}</b><small>24-hour window</small></div>
</div>
@if($pause > time())<div class="card" style="background:#FFFAEB;border-color:#FEDF89;color:#93370D;display:flex;gap:10px;align-items:center;flex-wrap:wrap">{{ $c['name'] }} asked to wait. No messages until <b>{{ \Carbon\Carbon::createFromTimestamp($pause, 'Asia/Dhaka')->format('d M h:i A') }}</b>.@if($admin)<form method="post" action="/admin/purchase/restock/act/resume">@csrf<button class="btn g" type="submit">Remove pause</button></form>@endif</div>@endif

@if($admin)
<div class="card"><h2>Settings</h2>
<form method="post" action="/admin/purchase/restock/settings">@csrf<div class="f">
<label><input type="checkbox" name="on" value="1" @checked($c['on'])> Automatic follow-up on</label>
<label>Products covered<br><select name="scope" style="height:30px"><option value="smoke" @selected($c['scope']==='smoke')>Cigarette and Vape only</option><option value="general" @selected($c['scope']==='general')>General products only</option><option value="all" @selected($c['scope']==='all')>All categories</option></select></label>
<label>Purchase manager<br><input type="text" name="name" value="{{ $c['name'] }}" style="width:120px"></label>
<label>WhatsApp number<br><input type="text" name="phone" value="{{ $c['wa'] ? '0'.substr($c['wa'], 3) : '' }}" style="width:130px"></label>
<label>Messages from<br><input type="text" name="from" value="{{ $s('from','10:00') }}" style="width:70px"></label>
<label>until<br><input type="text" name="to" value="{{ $s('to','24:00') }}" style="width:70px"></label>
<label>Remind every (minutes)<br><input type="number" name="every" min="30" max="720" value="{{ $c['every'] }}" style="width:90px"></label>
<label>Supply time (days)<br><input type="number" name="lead" min="0" max="60" value="{{ $c['lead'] }}" style="width:80px"></label>
<label>Safety (days)<br><input type="number" name="safety" min="0" max="30" value="{{ $c['safety'] }}" style="width:80px"></label>
<label>Buy for (days of sale)<br><input type="number" name="cover" min="3" max="90" value="{{ $c['cover'] }}" style="width:90px"></label>
<label>Min. sold in 30 days<br><input type="number" name="min_sold" min="1" value="{{ $c['min_sold'] }}" style="width:90px"></label>
<label>Photo cards per round<br><input type="number" name="cards" min="0" max="10" value="{{ $c['cards'] }}" style="width:90px"></label>
</div>
<div style="margin-top:8px"><label>Leave out these categories:</label><div style="display:flex;gap:4px 14px;flex-wrap:wrap;margin-top:4px">@foreach($cats as $ct)@php $smoke = false; @endphp<label style="white-space:nowrap"><input type="checkbox" name="exclude[]" value="{{ $ct->id }}" @checked(in_array((int)$ct->id, $c['exclude'], true) || $smoke) @disabled($smoke)> {{ $ct->name }}</label>@endforeach</div></div>
<button class="btn" type="submit" style="margin-top:8px">Save</button></form>
<small class="mu" style="display:block;margin-top:6px">A product is listed when its usable stock (stock minus units in confirmed orders that have not shipped) plus what is already on order will not last the supply time plus the safety days, or when it is at or below its own stock limit. Cancelled and returned orders are not counted as sales.</small>
<div style="border-top:1px solid #E5E7EB;margin-top:10px;padding-top:8px">Template <code>{{ \App\Support\Restock::TPL }}</code> · status: <b>{{ $tpl }}</b> <span class="mu">(used only to open the conversation when the 24-hour window is closed)</span>
<pre style="white-space:pre-wrap;font-family:inherit;font-size:12.5px;background:#F8FAFC;border:1px solid #E5E7EB;border-radius:8px;padding:8px;margin:8px 0">{{ \App\Support\Restock::BODY }}

[ {{ \App\Support\Restock::BTN }} ]</pre>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<form method="post" action="/admin/purchase/restock/act/submit">@csrf<button class="btn" type="submit">Submit to Meta</button></form>
<form method="post" action="/admin/purchase/restock/act/sync">@csrf<button class="btn g" type="submit">Refresh status</button></form>
<form method="post" action="/admin/purchase/restock/act/verify">@csrf<button class="btn g" type="submit">Send verification message</button></form>
<form method="post" action="/admin/purchase/restock/act/scan">@csrf<button class="btn g" type="submit">Scan now (no message)</button></form>
<form method="post" action="/admin/purchase/restock/act/send">@csrf<button class="btn g" type="submit">Send reminder now</button></form></div>
<form method="post" action="/admin/purchase/restock/simulate" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center">@csrf<span class="mu" style="font-size:12px">Test an answer as if {{ $c['name'] }} wrote it:</span><input type="text" name="text" placeholder="1 অর্ডার করেছি, কাল আসবে" style="flex:1;min-width:220px"><button class="btn g" type="submit">Test answer</button></form>
<small class="mu" style="display:block;margin-top:6px">The answer to a test goes to the saved WhatsApp number. Use the test number while trying this out.</small></div></div>
@endif

<div class="card"><h2>Open tasks <span class="mu">({{ $open->count() }})</span></h2>@if($open->isEmpty())<div class="mu">No open task.</div>@else
<div style="overflow-x:auto"><table><tr><th></th><th>Product</th><th>Status</th><th class="n">Usable</th><th class="n">On order</th><th class="n">Per day</th><th class="n">Days left</th><th class="n">Buy</th><th class="n">Received</th><th>Arrives</th><th>Wait until</th><th>Last message</th><th>Note</th>@if($admin)<th></th>@endif</tr>
@foreach($open as $t)<tr @if((int)$t->usable<=0) style="background:#FEF3F2" @endif><td>@if($t->image)<img class="th" src="{{ $t->image }}" alt="" loading="lazy">@endif</td><td><a href="/admin/products/{{ $t->product_id }}/edit">{{ $t->name }}</a><br><small class="mu">{{ $t->reason }}</small></td>
<td><span class="pill" style="{{ $t->status==='problem' ? 'background:#FEE4E2;color:#B42318' : ($t->status==='ordered' ? 'background:#E7F8EE;color:#0A6B3B' : '') }}">{{ $st($t->status) }}</span></td>
<td class="n">{{ (int)$t->usable }}</td><td class="n">{{ (int)$t->on_order }}</td><td class="n">{{ \App\Support\Restock::num($t->per_day) }}</td><td class="n">{{ (int)$t->usable<=0 ? 'Out' : \App\Support\Restock::num($t->days_left) }}</td><td class="n"><b>{{ (int)$t->need_qty }}</b></td><td class="n">{{ (int)$t->received_qty }}</td>
<td>{{ $t->eta_at ? \Carbon\Carbon::parse($t->eta_at,'UTC')->timezone('Asia/Dhaka')->format('d M') : '—' }}</td><td>{{ $t->snooze_until && strtotime($t->snooze_until.' UTC') > time() ? $dt($t->snooze_until) : '—' }}</td><td>{{ $dt($t->last_msg_at) }}<br><small class="mu">{{ (int)$t->reminders }} sent</small></td><td style="max-width:220px;white-space:pre-line">{{ $t->note ?: '—' }}</td>
@if($admin)<td><form method="post" action="/admin/purchase/restock/task/{{ $t->id }}" style="display:flex;gap:4px">@csrf<input type="text" name="note" placeholder="note" style="width:110px"><button class="btn g" name="do" value="note" type="submit">Note</button><button class="btn g" name="do" value="cancel" type="submit">Cancel</button></form></td>@endif</tr>@endforeach</table></div>@endif</div>

<div class="card"><h2>Watched products <span class="mu">({{ count($fig) }})</span></h2>@if(!$fig)<div class="mu">No product in this scope has sold enough in the last 30 days to be watched.</div>@else
<div style="overflow-x:auto;max-height:520px"><table><tr><th>Product</th><th class="n">Stock</th><th class="n">In open orders</th><th class="n">Usable</th><th class="n">On order</th><th class="n">Sold 7 d</th><th class="n">Sold 30 d</th><th class="n">Per day</th><th class="n">Days left</th><th class="n">Supply days</th><th class="n">Buy</th><th>State</th>@if($admin)<th>Limit · Supply days · Target · Leave out</th>@endif</tr>
@foreach(array_slice($fig, 0, 300, true) as $f)<tr @if($f['need']) style="background:#FFFAEB" @endif><td><a href="/admin/products/{{ $f['id'] }}/edit">{{ $f['name'] }}</a>@if($f['reason'])<br><small class="mu">{{ $f['reason'] }}</small>@endif</td><td class="n">{{ $f['stock'] }}</td><td class="n">{{ $f['committed'] }}</td><td class="n"><b>{{ $f['usable'] }}</b></td><td class="n">{{ $f['on_order'] }}</td><td class="n">{{ $f['q7'] }}</td><td class="n">{{ $f['q30'] }}</td><td class="n">{{ \App\Support\Restock::num($f['per_day']) }}</td><td class="n">{{ $f['usable']<=0 ? 'Out' : \App\Support\Restock::num($f['days_left']) }}</td><td class="n">{{ $f['lead'] }}</td><td class="n">{{ $f['need'] ? $f['qty'] : '—' }}</td>
<td>@if(isset($openBy[$f['id']]))<span class="pill">{{ $st($openBy[$f['id']]->status) }}</span>@elseif($f['need'])<span class="pill" style="background:#FEF0C7;color:#93370D">Needs buying</span>@else<span class="mu">OK</span>@endif</td>
@if($admin)<td><form method="post" action="/admin/purchase/restock/product" style="display:flex;gap:4px;align-items:center">@csrf<input type="hidden" name="product_id" value="{{ $f['id'] }}"><input type="number" name="limit" min="0" value="{{ $f['limit'] ?: '' }}" placeholder="limit" style="width:66px"><input type="number" name="lead" min="0" max="60" value="{{ $f['lead'] !== $c['lead'] ? $f['lead'] : '' }}" placeholder="{{ $c['lead'] }}" style="width:56px"><input type="number" name="target" min="0" value="{{ $f['rt'] ?: '' }}" placeholder="target" style="width:66px"><label><input type="checkbox" name="off" value="1"> out</label><button class="btn g" type="submit">Save</button></form></td>@endif</tr>@endforeach</table></div>@endif</div>

@if($ov->count())<div class="card"><h2>Products with their own setting</h2><div style="overflow-x:auto"><table><tr><th>Product</th><th class="n">Limit</th><th class="n">Supply days</th><th class="n">Target</th><th>Left out</th>@if($admin)<th></th>@endif</tr>
@foreach($ov as $p)<tr><td><a href="/admin/products/{{ $p->id }}/edit">{{ $p->name }}</a></td><td class="n">{{ $p->low_stock_alert ?: '—' }}</td><td class="n">{{ $p->restock_lead_days ?? '—' }}</td><td class="n">{{ $p->reorder_target ?: '—' }}</td><td>{{ $p->restock_off ? 'Yes' : 'No' }}</td>
@if($admin)<td><form method="post" action="/admin/purchase/restock/product">@csrf<input type="hidden" name="product_id" value="{{ $p->id }}"><input type="hidden" name="limit" value="{{ $p->low_stock_alert }}"><input type="hidden" name="target" value="{{ $p->reorder_target }}"><button class="btn g" type="submit">Back to default</button></form></td>@endif</tr>@endforeach</table></div></div>@endif

<div class="card" id="history"><h2>{{ $conv ? 'Conversation with '.$c['name'] : 'History' }} <span class="mu">({{ $conv ? 'messages sent and answers received' : 'messages, answers and status changes' }}, newest first)</span> <a href="/admin/purchase/restock{{ $conv ? '' : '?conv=1' }}#history" style="font-size:12px;font-weight:600;margin-left:8px">{{ $conv ? 'Show everything' : 'Show the conversation only' }}</a></h2>@if($ev->isEmpty())<div class="mu">Nothing yet.</div>@else
<div style="overflow-x:auto;max-height:520px"><table><tr><th>Time</th><th>Type</th><th>Product</th><th>Detail</th><th>By</th></tr>
@php $kl = ['detect'=>'Detected','msg_out'=>'Message sent','msg_fail'=>'Message not sent','msg_in'=>'Answer received','status'=>'Status','receive'=>'Stock received','setting'=>'Setting','round'=>'Round','pause'=>'Pause','escalate'=>'To supervisor']; @endphp
@foreach($ev as $e)<tr><td style="white-space:nowrap">{{ $dt($e->created_at) }}</td><td><span class="pill" style="{{ $e->kind==='msg_fail' || $e->kind==='escalate' ? 'background:#FEE4E2;color:#B42318' : ($e->kind==='msg_in' ? 'background:#E7F8EE;color:#0A6B3B' : '') }}">{{ $kl[$e->kind] ?? $e->kind }}</span></td><td>{{ $e->name ?: '—' }}</td><td style="white-space:pre-line;max-width:560px">{{ $e->body }}</td><td>{{ $e->by_name }}</td></tr>@endforeach</table></div>@endif</div>

@if($closed->count())<div class="card"><h2>Closed tasks <span class="mu">(last 30)</span></h2><div style="overflow-x:auto"><table><tr><th>Product</th><th>Result</th><th>Why</th><th class="n">Asked</th><th class="n">Received</th><th>Opened</th><th>Closed</th></tr>
@foreach($closed as $t)<tr><td>{{ $t->name }}</td><td>{{ $st($t->status) }}</td><td>{{ $t->closed_why }}</td><td class="n">{{ (int)$t->suggest_qty }}</td><td class="n">{{ (int)$t->received_qty }}</td><td>{{ $dt($t->created_at) }}</td><td>{{ $dt($t->closed_at) }}</td></tr>@endforeach</table></div></div>@endif
@endif
</div>
@endsection
