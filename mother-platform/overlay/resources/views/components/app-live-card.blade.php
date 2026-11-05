@php $al = \App\Support\AppUsers::live(); @endphp
<div class="card" id="applive" style="padding:10px 14px;margin-bottom:12px">
<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><b style="font-size:13px">📱 Live in the App</b>
<span id="al-n" style="font-size:11px;font-weight:700;border-radius:999px;padding:3px 10px;background:#DCFCE7;color:#166534">{{ count($al['people']) }} logged in now</span>
<span id="al-g" style="font-size:11px;color:#6B7280">{{ $al['guests'] }} not logged in · {{ number_format($al['installs']) }} installs</span>
<a href="/admin/app-users" style="margin-left:auto;font-size:12px;color:#C2410C;font-weight:600">All App users →</a></div>
<div id="al-list" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">@forelse($al['people'] as $p)<a href="/admin/m/customers/{{ $p['id'] }}/edit" title="{{ $p['phone'] }} · {{ $p['dev'] }} · {{ $p['ago'] }}" style="font-size:12px;font-weight:600;border:1px solid #BBF7D0;background:#F0FDF4;color:#166534;border-radius:999px;padding:3px 10px;text-decoration:none">🟢 {{ $p['name'] }}</a>@empty<span style="font-size:12px;color:#6B7280">No customer is inside the App right now.</span>@endforelse</div></div>
<script>(function(){function e(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function tick(){if(document.hidden)return;fetch('/admin/app-users/live',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(j){
document.getElementById('al-n').textContent=j.people.length+' logged in now';document.getElementById('al-g').textContent=j.guests+' not logged in · '+j.installs+' installs';
document.getElementById('al-list').innerHTML=j.people.length?j.people.map(function(p){return '<a href="/admin/m/customers/'+p.id+'/edit" title="'+e(p.phone+' · '+p.dev+' · '+p.ago)+'" style="font-size:12px;font-weight:600;border:1px solid #BBF7D0;background:#F0FDF4;color:#166534;border-radius:999px;padding:3px 10px;text-decoration:none">🟢 '+e(p.name)+'</a>'}).join(''):'<span style="font-size:12px;color:#6B7280">No customer is inside the App right now.</span>'})['catch'](function(){})}
setInterval(tick,20000);document.addEventListener('visibilitychange',function(){if(!document.hidden)tick()})})();</script>
