@extends('layout')@section('title','Call Clients')
@section('body')
<style>.cc input{padding:6px 8px;border:1px solid #E5E7EB;border-radius:6px;font-size:13px}.cc pre{background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:10px;font-size:12px;white-space:pre-wrap;margin:6px 0 0}.cc th{padding:6px;font-weight:600}.cc td{padding:6px}.tag{font-size:11px;padding:2px 8px;border-radius:99px;background:#FEF3C7;color:#92400E;font-weight:600}</style>
<div class="cc">
<h1 style="margin:0 0 4px">Call Clients <span class="tag">TEST</span></h1>
<span style="font-size:13px;color:#6B7280">ক্লায়েন্টের IP, Port ও Prefix যোগ করুন। এই পাতা ক্লায়েন্ট-ডকুমেন্ট ও PBX কনফিগ বানিয়ে দেয়। টেস্ট মোডে PBX-এ নিজে থেকে কিছু পাঠায় না — কনফিগ PBX-এ বসালে তবেই কল চলবে।</span>
@if(session('ok'))<div class="card" style="margin-top:12px;background:#DCFCE7;border-color:#A7F3D0">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card err" style="margin-top:12px">@foreach($errors->all() as $e){{ $e }}<br>@endforeach</div>@endif

<div class="card" style="margin-top:12px"><b>কল কীভাবে যায়</b>
<pre>ক্লায়েন্ট (তার IP, port) ──SIP/UDP──▶ আমাদের PBX IP:{{ $cfg['port'] }}
   dial করে: PREFIX + 01XXXXXXXXX
PBX: ① IP চেনে (ACL) ② prefix কেটে ফেলে ③ concurrent limit দেখে
   ──▶ আমাদের outbound gateway ──▶ কাস্টমারের মোবাইল</pre></div>

<div class="card" style="margin-top:12px"><b>লাইভ কল</b> <span id="lvN" class="tag" style="background:#DCFCE7;color:#166534">0</span> <small id="lvT" style="color:#6B7280"></small>
<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:6px"><thead><tr style="text-align:left;color:#6B7280"><th>ক্লায়েন্ট</th><th>কলার</th><th>কাস্টমার নম্বর</th><th>অবস্থা</th><th>সময়</th></tr></thead><tbody id="lvB"><tr><td colspan="5" style="padding:8px;color:#6B7280">লোড হচ্ছে…</td></tr></tbody></table></div></div>
<script>(function(){var B=document.getElementById('lvB'),N=document.getElementById('lvN'),T=document.getElementById('lvT');function f(s){return Math.floor(s/60)+':'+('0'+s%60).slice(-2)}
function tick(){fetch('/admin/call-center/clients/live',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){
if(!d.ok){B.innerHTML='<tr><td colspan="5" style="padding:8px;color:#B45309"></td></tr>';B.firstChild.firstChild.textContent='PBX থেকে লাইভ তথ্য পাওয়া যায়নি: '+d.error;N.textContent='-';return}
N.textContent=d.total;T.textContent='আপডেট '+d.at+' · PBX-এ মোট চ্যানেল '+d.channels+(d.unknown.length?' · অচেনা IP থেকে কল: '+d.unknown.map(function(u){return u.ip+' → '+u.to}).join(', '):'');
if(!d.calls.length){B.innerHTML='<tr><td colspan="5" style="padding:8px;color:#6B7280">এখন কোনো কল চলছে না।</td></tr>';return}
B.innerHTML='';d.calls.forEach(function(c){var tr=document.createElement('tr');tr.style.borderTop='1px solid #E5E7EB';[c.client,c.from,c.to,c.state,f(c.secs)].forEach(function(v){var td=document.createElement('td');td.style.padding='6px';td.textContent=v;tr.appendChild(td)});B.appendChild(tr)})}).catch(function(){})}
tick();setInterval(function(){if(!document.hidden)tick()},3000)})();</script>

<div class="card" style="margin-top:12px"><b>আমাদের PBX endpoint</b> <small style="color:#6B7280">(ক্লায়েন্টকে এই IP ও port দেওয়া হবে)</small>
<form method="post" action="/admin/call-center/clients/endpoint" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">@csrf
<input name="ip" value="{{ old('ip',$cfg['ip']) }}" placeholder="PBX public IP" style="width:200px" required>
<input name="port" value="{{ old('port',$cfg['port']) }}" style="width:80px" required><button class="btn" type="submit">Save</button></form></div>

<div class="card" style="margin-top:12px;overflow-x:auto"><b>নতুন ক্লায়েন্ট</b>
<form method="post" action="/admin/call-center/clients" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">@csrf
<input name="name" placeholder="নাম" style="width:150px" required>
<input name="ip" placeholder="ক্লায়েন্ট IP" style="width:150px" required>
<input name="port" value="5060" style="width:70px" required>
<input name="prefix" placeholder="Prefix (যেমন 7788)" style="width:130px" required>
<input name="max_calls" value="20" title="max concurrent calls" style="width:70px" required>
<input name="note" placeholder="নোট" style="width:160px"><button class="btn" type="submit">যোগ করুন</button></form></div>

<div class="card" style="margin-top:12px;overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px">
<tr style="text-align:left;color:#6B7280"><th>নাম</th><th>IP</th><th>Port</th><th>Prefix</th><th>Max</th><th>Status</th><th></th></tr>
@forelse($rows as $c)
<tr style="border-top:1px solid #E5E7EB"><form method="post" action="/admin/call-center/clients/{{ $c->id }}">@csrf
<td><input name="name" value="{{ $c->name }}" style="width:130px"></td><td><input name="ip" value="{{ $c->ip }}" style="width:130px"></td>
<td><input name="port" value="{{ $c->port }}" style="width:65px"></td><td><input name="prefix" value="{{ $c->prefix }}" style="width:90px"></td>
<td><input name="max_calls" value="{{ $c->max_calls }}" style="width:60px"><input type="hidden" name="note" value="{{ $c->note }}"></td>
<td><b style="color:{{ $c->status==='active'?'#059669':'#B45309' }}">{{ $c->status }}</b></td>
<td style="white-space:nowrap"><button class="btn" type="submit">Save</button>
<button class="btn" type="submit" formaction="/admin/call-center/clients/{{ $c->id }}/toggle" formnovalidate>{{ $c->status==='active'?'Pause':'Resume' }}</button>
<button class="btn" type="submit" formaction="/admin/call-center/clients/{{ $c->id }}/delete" formnovalidate onclick="return confirm('মুছে ফেলবেন?')">Delete</button></td></form></tr>
<tr><td colspan="7"><details><summary style="cursor:pointer;color:#E8590C">ক্লায়েন্টকে দেওয়ার ডকুমেন্ট</summary>
<pre>{{ \App\Http\Controllers\CarrierClientController::document((array)$c, $cfg) }}</pre></details></td></tr>
@empty<tr><td colspan="7" style="padding:14px;color:#6B7280">এখনো কোনো ক্লায়েন্ট নেই।</td></tr>@endforelse
</table></div>

<div class="card" style="margin-top:12px"><b>PBX কনফিগ (FreeSWITCH)</b> <small style="color:#6B7280">— active ক্লায়েন্টদের থেকে বানানো; PBX অ্যাডমিন বসাবেন, তারপর <code>fs_cli -x reloadacl</code> ও <code>reloadxml</code></small>
<pre>{{ $pbx }}</pre></div>
</div>
@endsection
