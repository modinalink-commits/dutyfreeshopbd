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

<div class="card" style="margin-top:12px"><b>Relay লাইন (PBX এক্সটেনশন)</b> <small style="color:#6B7280">— রিলে এই এক্সটেনশনগুলো দিয়ে PBX-এ রেজিস্টার হয়ে কাস্টমারের মোবাইলে কল পাঠায়। পাসওয়ার্ড সেভের পর আর দেখানো হয় না (ফাঁকা রাখলে আগেরটা থাকে)।</small>
<form method="post" action="/admin/call-center/clients/relay" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:flex-start">@csrf
<input name="host" value="{{ old('host',$relay['host']) }}" placeholder="PBX host" style="width:190px" required>
<input name="port" value="{{ old('port',$relay['port']) }}" style="width:70px" required>
<select name="transport" style="padding:6px 8px;border:1px solid #E5E7EB;border-radius:6px"><option value="tcp" @selected($relay['transport']==='tcp')>TCP</option><option value="udp" @selected($relay['transport']==='udp')>UDP</option></select>
<input name="realm" value="{{ old('realm',$relay['realm']) }}" placeholder="Realm/Domain (ঐচ্ছিক)" style="width:190px">
<input name="caller_id" value="{{ old('caller_id',$relay['caller_id']) }}" placeholder="Caller ID (ঐচ্ছিক)" style="width:150px">
<select name="mode" style="padding:6px 8px;border:1px solid #E5E7EB;border-radius:6px"><option value="test" @selected($relay['mode']!=='live')>TEST (কোথাও যাবে না)</option><option value="live" @selected($relay['mode']==='live')>LIVE (কাস্টমারকে কল যাবে)</option></select>
<textarea name="exts" rows="4" placeholder="প্রতি লাইনে একটা: এক্সটেনশন:পাসওয়ার্ড&#10;যেমন 1001:xxxxxx&#10;পাসওয়ার্ড ফাঁকা (শুধু 1001) = আগেরটা থাকবে" style="width:320px;padding:6px 8px;border:1px solid #E5E7EB;border-radius:6px;font-size:13px">{{ old('exts', $relay['exts']) }}</textarea>
<button class="btn" type="submit">Save</button></form>
<div style="margin-top:8px;font-size:12px;color:#6B7280">সেভ করা এক্সটেনশন: <b>{{ $relay['exts'] ? str_replace("\n", ', ', trim($relay['exts'])) : 'নেই' }}</b> ·
<form method="post" action="/admin/call-center/clients/relay-agent" style="display:inline">@csrf<input name="ext" placeholder="এজেন্ট এক্সটেনশন (যেমন 101)" style="width:170px" required> <button class="btn" type="submit">এজেন্ট এক্সটেনশন থেকে নিন</button></form> · <form method="post" action="/admin/call-center/clients/relay-token" style="display:inline">@csrf<button class="btn" type="submit" onclick="return confirm('নতুন টোকেন বানালে পুরনোটা বাতিল হবে। চালিয়ে যাবেন?')">Relay টোকেন নতুন করে বানান</button></form></div></div>

<div class="card" style="margin-top:12px;overflow-x:auto"><b>বিলিং সার্কেল</b> <small style="color:#6B7280">— শুধু কানেক্ট (ধরা) কলের মিনিট গোনা হয়; প্রতি কল পরের পূর্ণ মিনিটে ধরা হয়। প্রিপেইড মিনিট দিলে বাকি মিনিট কমতে থাকবে। <span id="bcAt"></span></small>
<table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:6px"><thead><tr style="text-align:left;color:#6B7280"><th>ক্লায়েন্ট</th><th>সার্কেল</th><th>কানেক্ট কল</th><th>মোট কথা (মি:সে)</th><th>বিলযোগ্য মিনিট</th><th>প্রিপেইড</th><th>বাকি মিনিট</th><th>বিল (টাকা)</th></tr></thead><tbody>
@forelse($rows as $c)@php($u=$usage[$c->id])
<tr style="border-top:1px solid #E5E7EB" data-bc="{{ $c->id }}"><td><b>{{ $c->name }}</b></td><td data-f="range">{{ $u['from'] }} – {{ $u['to'] }}</td><td data-f="calls">{{ $u['calls'] }}</td><td data-f="time">{{ intdiv($u['secs'],60) }}:{{ str_pad($u['secs']%60,2,'0',STR_PAD_LEFT) }}</td><td data-f="bmin"><b>{{ $u['billed_min'] }}</b></td><td data-f="pre">{{ $u['prepaid'] ?? '—' }}</td>
<td data-f="rem" style="font-weight:700;color:{{ ($u['remaining'] ?? 1) < 0 ? '#B91C1C' : '#059669' }}">{{ $u['remaining'] ?? '—' }}</td><td data-f="amt">{{ $u['amount'] === null ? '—' : number_format($u['amount'],2) }}</td></tr>
<tr><td colspan="8"><form method="post" action="/admin/call-center/clients/{{ $c->id }}/billing" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:12px;color:#6B7280">@csrf
সার্কেল শুরু <input type="date" name="cycle_start" value="{{ $c->cycle_start }}" style="width:140px"> প্রতি <input name="cycle_days" value="{{ $c->cycle_days ?? 30 }}" style="width:55px" required> দিন · প্রিপেইড মিনিট <input name="prepaid_minutes" value="{{ $c->prepaid_minutes }}" placeholder="ফাঁকা = সীমাহীন" style="width:120px"> · রেট/মিনিট <input name="rate_per_min" value="{{ $c->rate_per_min }}" placeholder="টাকা" style="width:80px"> <button class="btn" type="submit">সেভ</button></form></td></tr>
@empty<tr><td colspan="8" style="padding:10px;color:#6B7280">ক্লায়েন্ট যোগ করলে এখানে বিলিং দেখাবে।</td></tr>@endforelse
</tbody></table>
<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:12px;color:#6B7280;margin-top:10px;border-top:1px solid #E5E7EB;padding-top:10px"><b style="color:#111827">সময় ধরে মিনিট হিসাব</b> শুরু <input type="datetime-local" id="brF" style="width:190px"> শেষ <input type="datetime-local" id="brT" style="width:190px"> <button class="btn" type="button" id="brGo">হিসাব দেখুন</button> <button class="btn" type="button" id="brToday">আজ</button> <span id="brOut" style="color:#111827;font-weight:600"></span></div>
</div>
<script>(function(){function f(s){return Math.floor(s/60)+':'+('0'+s%60).slice(-2)}
function tick(){fetch('/admin/call-center/clients/billing',{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){if(!d.ok)return;document.getElementById('bcAt').textContent='আপডেট '+d.at;
Object.keys(d.usage).forEach(function(id){var u=d.usage[id],tr=document.querySelector('tr[data-bc="'+id+'"]');if(!tr)return;function s(k,v){var e=tr.querySelector('[data-f="'+k+'"]');if(e)e.textContent=v}
s('range',u.from+' – '+u.to);s('calls',u.calls);s('time',f(u.secs));s('bmin',u.billed_min);s('pre',u.prepaid===null?'—':u.prepaid);s('rem',u.remaining===null?'—':u.remaining);s('amt',u.amount===null?'—':u.amount.toFixed(2));
var r=tr.querySelector('[data-f="rem"]');if(r)r.style.color=(u.remaining!==null&&u.remaining<0)?'#B91C1C':'#059669'})}).catch(function(){})}
setInterval(function(){if(!document.hidden)tick()},5000);
var o=document.getElementById('brOut');function pad(n){return ('0'+n).slice(-2)}function loc(d){return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes())}
document.getElementById('brToday').onclick=function(){var a=new Date();a.setHours(0,0,0,0);document.getElementById('brF').value=loc(a);document.getElementById('brT').value=loc(new Date());document.getElementById('brGo').click()};
document.getElementById('brGo').onclick=function(){var a=document.getElementById('brF').value,b=document.getElementById('brT').value;if(!a||!b){o.textContent='শুরু ও শেষ সময় দিন';return}o.textContent='...';
fetch('/admin/call-center/clients/billing/range?from='+encodeURIComponent(a)+'&to='+encodeURIComponent(b),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){if(!d.ok){o.textContent=d.msg||'ভুল সময়';return}
o.textContent='কানেক্ট কল '+d.calls+' · কথা '+f(d.secs)+' · বিলযোগ্য মিনিট '+d.billed_min+(d.rows.length>1?' ('+d.rows.map(function(x){return x.client+': '+x.billed_min}).join(', ')+')':'')}).catch(function(){o.textContent='লোড হয়নি'})}})();</script>

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
