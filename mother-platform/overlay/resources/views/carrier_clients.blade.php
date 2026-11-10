@extends('layout')@section('title','Call Clients')
@section('body')
@php($tc=collect($usage)->sum('calls'))@php($ts=collect($usage)->sum('secs'))@php($tb=collect($usage)->sum('billed_min'))@php($rem=collect($usage)->pluck('remaining')->filter(fn($v)=>$v!==null)->sum())
<style>.cc input,.cc select,.cc textarea{padding:5px 8px;border:1px solid #E5E7EB;border-radius:6px;font-size:12px}.cc .sec{margin-top:12px;overflow-x:auto}.cc .sec>.h{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:6px}.cc .sec>.h b{font-size:14px}.cc .sub{font-size:12px;color:#6B7280}.cc .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:12px;color:#6B7280}.cc .tbl{width:100%;border-collapse:collapse;font-size:12px}.cc .tbl th{text-align:left;color:#6B7280;padding:6px}.cc .tbl td{padding:6px}.cc .tbl tr+tr td{border-top:1px solid #E5E7EB}.cc pre{background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:10px;font-size:12px;white-space:pre-wrap;margin:6px 0 0}.cc .n{text-align:right}</style>
<div class="cc">
<h1 style="margin:0 0 4px">Call Clients</h1>
<span class="sub">Wholesale call-termination clients: live calls, connected minutes, billing cycle and relay line.</span>
@if(session('ok'))<div class="card" style="margin-top:12px;background:#DCFCE7;border-color:#A7F3D0">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card err" style="margin-top:12px">@foreach($errors->all() as $e){{ $e }}<br>@endforeach</div>@endif

<div class="grid" style="margin-top:12px">
<div class="card k" style="margin:0"><b id="kLive">0</b><span>Live calls</span></div>
<div class="card k" style="margin:0"><b>{{ $tc }}</b><span>Connected calls (this cycle)</span></div>
<div class="card k" style="margin:0"><b>{{ intdiv($ts,60) }}:{{ str_pad($ts%60,2,'0',STR_PAD_LEFT) }}</b><span>Talk time (min:sec)</span></div>
<div class="card k" style="margin:0"><b>{{ $tb }}</b><span>Billable minutes</span></div>
<div class="card k" style="margin:0"><b style="color:{{ $rem<0?'#B91C1C':'inherit' }}">{{ collect($usage)->pluck('remaining')->filter(fn($v)=>$v!==null)->isEmpty() ? '—' : $rem }}</b><span>Prepaid minutes left</span></div>
<div class="card k" style="margin:0"><b><span style="font-size:13px;color:inherit">{{ strtoupper($relay['mode']==='live'?'live':'test') }}</span></b><span>Relay mode</span></div>
</div>

<div class="card sec"><div class="h"><b>Live calls</b> <span id="lvN" class="pill ok">0</span> <span id="lvT" class="sub"></span></div>
<table class="tbl"><thead><tr><th>Client</th><th>Caller</th><th>Customer number</th><th>State</th><th>Duration</th></tr></thead><tbody id="lvB"><tr><td colspan="5" class="sub">Loading…</td></tr></tbody></table></div>
<script>(function(){var B=document.getElementById('lvB'),N=document.getElementById('lvN'),T=document.getElementById('lvT'),K=document.getElementById('kLive');function f(s){return Math.floor(s/60)+':'+('0'+s%60).slice(-2)}
function tick(){fetch('/admin/call-center/clients/live',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){
if(!d.ok){B.innerHTML='<tr><td colspan="5" class="sub" style="color:#B45309"></td></tr>';B.firstChild.firstChild.textContent='Live data not available from the PBX: '+d.error;N.textContent='-';return}
N.textContent=d.total;K.textContent=d.total;T.textContent='Updated '+d.at+' · PBX channels '+d.channels+(d.unknown.length?' · Unknown IP: '+d.unknown.map(function(u){return u.ip+' → '+u.to}).join(', '):'');
if(!d.calls.length){B.innerHTML='<tr><td colspan="5" class="sub">No active calls.</td></tr>';return}
B.innerHTML='';d.calls.forEach(function(c){var tr=document.createElement('tr');[c.client,c.from,c.to,c.state,f(c.secs)].forEach(function(v){var td=document.createElement('td');td.textContent=v;tr.appendChild(td)});B.appendChild(tr)})}).catch(function(){})}
tick();setInterval(function(){if(!document.hidden)tick()},3000)})();</script>

<div class="card sec"><div class="h"><b>Billing cycle</b> <span class="sub">Only connected (answered) calls are counted; each call rounds up to the next full minute. <span id="bcAt"></span></span></div>
<table class="tbl"><thead><tr><th>Client</th><th>Cycle</th><th class="n">Connected calls</th><th class="n">Talk time</th><th class="n">Billable min</th><th class="n">Prepaid</th><th class="n">Remaining</th><th class="n">Amount</th></tr></thead><tbody>
@forelse($rows as $c)@php($u=$usage[$c->id])
<tr data-bc="{{ $c->id }}"><td><b>{{ $c->name }}</b></td><td data-f="range">{{ $u['from'] }} – {{ $u['to'] }}</td><td class="n" data-f="calls">{{ $u['calls'] }}</td><td class="n" data-f="time">{{ intdiv($u['secs'],60) }}:{{ str_pad($u['secs']%60,2,'0',STR_PAD_LEFT) }}</td><td class="n" data-f="bmin"><b>{{ $u['billed_min'] }}</b></td><td class="n" data-f="pre">{{ $u['prepaid'] ?? '—' }}</td>
<td class="n" data-f="rem" style="font-weight:700;color:{{ ($u['remaining'] ?? 1) < 0 ? '#B91C1C' : '#059669' }}">{{ $u['remaining'] ?? '—' }}</td><td class="n" data-f="amt">{{ $u['amount'] === null ? '—' : number_format($u['amount'],2) }}</td></tr>
<tr><td colspan="8"><form method="post" action="/admin/call-center/clients/{{ $c->id }}/billing" class="row">@csrf
Cycle start <input type="date" name="cycle_start" value="{{ $c->cycle_start }}" style="width:140px"> every <input name="cycle_days" value="{{ $c->cycle_days ?? 30 }}" style="width:55px" required> days · Prepaid minutes <input name="prepaid_minutes" value="{{ $c->prepaid_minutes }}" placeholder="blank = unlimited" style="width:120px"> · Rate / min <input name="rate_per_min" value="{{ $c->rate_per_min }}" placeholder="BDT" style="width:80px"> <button class="btn" type="submit">Save</button></form></td></tr>
@empty<tr><td colspan="8" class="sub">Add a client to see billing.</td></tr>@endforelse
</tbody></table>
<div class="row" style="margin-top:10px;border-top:1px solid #E5E7EB;padding-top:10px"><b style="color:#111827">Minutes by date range</b> From <input type="datetime-local" id="brF" style="width:190px"> To <input type="datetime-local" id="brT" style="width:190px"> <button class="btn" type="button" id="brGo">Show minutes</button> <button class="btn g" type="button" id="brToday">Today</button> <span id="brOut" style="color:#111827;font-weight:600"></span></div>
</div>
<script>(function(){function f(s){return Math.floor(s/60)+':'+('0'+s%60).slice(-2)}
function tick(){fetch('/admin/call-center/clients/billing',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){if(!d.ok)return;document.getElementById('bcAt').textContent='Updated '+d.at;
Object.keys(d.usage).forEach(function(id){var u=d.usage[id],tr=document.querySelector('tr[data-bc="'+id+'"]');if(!tr)return;function s(k,v){var e=tr.querySelector('[data-f="'+k+'"]');if(e)e.textContent=v}
s('range',u.from+' – '+u.to);s('calls',u.calls);s('time',f(u.secs));s('bmin',u.billed_min);s('pre',u.prepaid===null?'—':u.prepaid);s('rem',u.remaining===null?'—':u.remaining);s('amt',u.amount===null?'—':u.amount.toFixed(2));
var r=tr.querySelector('[data-f="rem"]');if(r)r.style.color=(u.remaining!==null&&u.remaining<0)?'#B91C1C':'#059669'})}).catch(function(){})}
setInterval(function(){if(!document.hidden)tick()},5000);
var o=document.getElementById('brOut');function pad(n){return ('0'+n).slice(-2)}function loc(d){return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes())}
document.getElementById('brToday').onclick=function(){var a=new Date();a.setHours(0,0,0,0);document.getElementById('brF').value=loc(a);document.getElementById('brT').value=loc(new Date());document.getElementById('brGo').click()};
document.getElementById('brGo').onclick=function(){var a=document.getElementById('brF').value,b=document.getElementById('brT').value;if(!a||!b){o.textContent='Set both start and end time';return}o.textContent='…';
fetch('/admin/call-center/clients/billing/range?from='+encodeURIComponent(a)+'&to='+encodeURIComponent(b),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){if(!d.ok){o.textContent=d.msg||'Invalid range';return}
o.textContent='Connected calls '+d.calls+' · Talk time '+f(d.secs)+' · Billable minutes '+d.billed_min+(d.rows.length>1?' ('+d.rows.map(function(x){return x.client+': '+x.billed_min}).join(', ')+')':'')}).catch(function(){o.textContent='Could not load'})}})();</script>

<div class="card sec"><div class="h"><b>Relay line (PBX extensions)</b> <span class="sub">The relay registers with these extensions on the PBX and sends calls to customers' mobiles. Passwords are never shown after saving (leave blank to keep the existing one).</span></div>
<form method="post" action="/admin/call-center/clients/relay" class="row" style="align-items:flex-start">@csrf
<input name="host" value="{{ old('host',$relay['host']) }}" placeholder="PBX host" style="width:190px" required>
<input name="port" value="{{ old('port',$relay['port']) }}" style="width:70px" required>
<select name="transport"><option value="tcp" @selected($relay['transport']==='tcp')>TCP</option><option value="udp" @selected($relay['transport']==='udp')>UDP</option></select>
<input name="realm" value="{{ old('realm',$relay['realm']) }}" placeholder="Realm / domain (optional)" style="width:190px">
<input name="caller_id" value="{{ old('caller_id',$relay['caller_id']) }}" placeholder="Caller ID (optional)" style="width:150px">
<select name="mode"><option value="test" @selected($relay['mode']!=='live')>TEST (no calls leave)</option><option value="live" @selected($relay['mode']==='live')>LIVE (calls reach customers)</option></select>
<textarea name="exts" rows="3" placeholder="One per line: extension:password&#10;e.g. 1001:secret&#10;Extension only (1001) = keep existing password" style="width:300px">{{ old('exts', $relay['exts']) }}</textarea>
<button class="btn" type="submit">Save</button></form>
<div class="row" style="margin-top:8px">Saved extensions: <b style="color:#111827">{{ $relay['exts'] ? str_replace("\n", ', ', trim($relay['exts'])) : 'none' }}</b> ·
<form method="post" action="/admin/call-center/clients/relay-agent" class="row" style="display:inline-flex">@csrf<input name="ext" placeholder="Agent extension (e.g. 101)" style="width:170px" required> <button class="btn g" type="submit">Copy from agent extension</button></form> ·
<form method="post" action="/admin/call-center/clients/relay-token" style="display:inline">@csrf<button class="btn g" type="submit" onclick="return confirm('Generating a new token invalidates the old one. Continue?')">Regenerate relay token</button></form></div></div>

<div class="card sec"><div class="h"><b>Our SIP endpoint</b> <span class="sub">The IP and port given to clients.</span></div>
<form method="post" action="/admin/call-center/clients/endpoint" class="row">@csrf
<input name="ip" value="{{ old('ip',$cfg['ip']) }}" placeholder="Public IP" style="width:200px" required>
<input name="port" value="{{ old('port',$cfg['port']) }}" style="width:80px" required><button class="btn" type="submit">Save</button></form></div>

<div class="card sec"><div class="h"><b>Add client</b></div>
<form method="post" action="/admin/call-center/clients" class="row">@csrf
<input name="name" placeholder="Name" style="width:150px" required>
<input name="ip" placeholder="Client IP" style="width:150px" required>
<input name="port" value="5060" style="width:70px" required>
<input name="prefix" placeholder="Prefix (e.g. 7788)" style="width:130px" required>
<input name="max_calls" value="20" title="Max concurrent calls" style="width:70px" required>
<input name="note" placeholder="Note" style="width:160px"><button class="btn" type="submit">Add client</button></form></div>

<div class="card sec"><div class="h"><b>Clients</b></div><table class="tbl">
<thead><tr><th>Name</th><th>IP</th><th>Port</th><th>Prefix</th><th>Max calls</th><th>Status</th><th></th></tr></thead>
@forelse($rows as $c)
<tr><form method="post" action="/admin/call-center/clients/{{ $c->id }}">@csrf
<td><input name="name" value="{{ $c->name }}" style="width:130px"></td><td><input name="ip" value="{{ $c->ip }}" style="width:130px"></td>
<td><input name="port" value="{{ $c->port }}" style="width:65px"></td><td><input name="prefix" value="{{ $c->prefix }}" style="width:90px"></td>
<td><input name="max_calls" value="{{ $c->max_calls }}" style="width:60px"><input type="hidden" name="note" value="{{ $c->note }}"></td>
<td><span class="pill {{ $c->status==='active'?'ok':'' }}">{{ ucfirst($c->status) }}</span></td>
<td style="white-space:nowrap"><button class="btn" type="submit">Save</button>
<button class="btn g" type="submit" formaction="/admin/call-center/clients/{{ $c->id }}/toggle" formnovalidate>{{ $c->status==='active'?'Pause':'Resume' }}</button>
<button class="btn g" type="submit" formaction="/admin/call-center/clients/{{ $c->id }}/delete" formnovalidate onclick="return confirm('Delete this client?')">Delete</button></td></form></tr>
<tr><td colspan="7"><details><summary style="cursor:pointer;color:#C2410C">Client document (send to the client)</summary>
<pre>{{ \App\Http\Controllers\CarrierClientController::document((array)$c, $cfg) }}</pre></details></td></tr>
@empty<tr><td colspan="7" class="sub">No clients yet.</td></tr>@endforelse
</table></div>
</div>
@endsection
