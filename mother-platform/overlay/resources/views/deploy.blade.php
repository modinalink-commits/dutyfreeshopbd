@extends('layout')@section('title','Deployments')
@section('body')
<div style="margin-bottom:14px"><h1 style="margin:0">Deployments</h1><span style="font-size:13px;color:#6B7280">Zero-downtime updates: the new version is built beside the live one, checked, then switched in one step. Customers keep shopping the whole time.</span></div>
<form style="display:none">@csrf</form>
@if(!$enabled)<div class="card err">Release layout is not active yet. It switches on after the next full redeploy.</div>@endif
<div class="grid" style="margin-bottom:16px"><div class="card k" style="margin:0"><span style="font-size:13px">Live release</span><b style="font-size:18px;word-break:break-all" id="dl">{{ $live }}</b></div><div class="card k" style="margin:0"><span style="font-size:13px">Status</span><b style="font-size:18px" id="ds">{{ $status }}</b></div></div>
<div class="card"><b>Deploy a new version</b><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center"><input id="dr" placeholder="full 40-character commit id" style="flex:1;min-width:260px;font-family:monospace"><label style="font-size:13px"><input type="checkbox" id="db"> allow breaking migration (short maintenance)</label><button class="btn" id="dg" type="button">Deploy</button><button class="btn g" id="drb" type="button">Rollback to previous</button></div><div id="dm" style="font-size:13px;margin-top:8px"></div></div>
<div class="card"><b>Releases kept</b><table class="t" style="width:100%;margin-top:6px"><tbody id="dt">@foreach($releases as $r)<tr><td>{{ $r['id'] }}</td><td>{{ \Carbon\Carbon::createFromTimestamp($r['at'])->timezone('Asia/Dhaka')->format('d M, h:i A') }}</td><td>{!! $r['live'] ? '<b style="color:#16A34A">LIVE</b>' : '' !!}</td></tr>@endforeach</tbody></table></div>
<div class="card"><b>Last deploy log</b><pre id="dlg" style="white-space:pre-wrap;font-size:12px;max-height:340px;overflow:auto;margin:8px 0 0">{{ $log }}</pre></div>
@endsection
@section('js')
<script>
(function(){var $=function(i){return document.getElementById(i)};
function csrf(){var i=document.querySelector('input[name=_token]');return i?i.value:''}
function refresh(){fetch('/admin/deploy/status',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(j){$('dl').textContent=j.live;$('ds').textContent=j.status;$('dlg').textContent=j.log;$('dt').innerHTML=j.releases.map(function(r){return '<tr><td>'+r.id+'</td><td>'+new Date(r.at*1000).toLocaleString()+'</td><td>'+(r.live?'<b style="color:#16A34A">LIVE</b>':'')+'</td></tr>'}).join('')})['catch'](function(){})}
function go(ref){fetch('/admin/deploy',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({ref:ref,breaking:$('db').checked})}).then(function(r){return r.json()}).then(function(j){$('dm').textContent=j.ok?'Queued. Watch the log below.':(j.msg||'Could not queue');refresh()})}
$('dg').onclick=function(){var v=$('dr').value.trim();if(!/^[0-9a-f]{40}$/i.test(v)){$('dm').textContent='Enter the full 40-character commit id';return}if(confirm('Deploy '+v.slice(0,7)+' with zero downtime?'))go(v)};
$('drb').onclick=function(){if(confirm('Switch back to the previous release?'))go('rollback')};
setInterval(refresh,4000);})();
</script>
@endsection
