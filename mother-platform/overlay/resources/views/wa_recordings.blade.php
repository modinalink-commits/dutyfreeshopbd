@extends('layout')@section('title','Call Recordings')
@section('body')
@include('cc_ui')
@include('wa_rec_ui')
<div class="cc-h"><h1>Call Recordings</h1><span>WhatsApp calls answered here are recorded in the representative's browser (both sides). Every listen, download and delete is logged.</span></div>
@if(!$ready)<div class="cc-box"><div class="cc-note">The recording tables are not installed yet. They are created by the next deploy.</div></div>@else
<div class="cc-box" style="padding:10px 14px;display:flex;gap:18px;flex-wrap:wrap;font-size:13px">
<div><small style="color:#475467;display:block">Recordings kept</small><b>{{ number_format($use['count']) }}</b></div>
<div><small style="color:#475467;display:block">Talk time</small><b>{{ floor($use['seconds'] / 3600) }}h {{ floor($use['seconds'] % 3600 / 60) }}m</b></div>
<div><small style="color:#475467;display:block">Storage used</small><b>{{ $use['bytes'] > 1048576 ? number_format($use['bytes'] / 1048576, 1).' MB' : number_format($use['bytes'] / 1024).' KB' }}</b></div>
<div><small style="color:#475467;display:block">Free on server</small><b>{{ $use['free'] ? number_format($use['free'] / 1073741824, 1).' GB' : '—' }}</b></div>
<div><small style="color:#475467;display:block">Kept for</small><b>{{ $cfg['days'] }} days</b></div>
<div><small style="color:#475467;display:block">Recording</small><b style="color:{{ $cfg['on'] ? '#067647' : '#B42318' }}">{{ $cfg['on'] ? 'ON' : 'OFF' }}</b></div>
</div>
@if($admin)
<div class="cc-box"><b class="t">Settings</b><div style="padding:10px 14px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;font-size:13px">
<label>Recording<br><select id="rsOn" style="height:34px"><option value="1" @selected($cfg['on'])>On</option><option value="0" @selected(!$cfg['on'])>Off</option></select></label>
<label>Keep for (days)<br><input id="rsDays" type="number" min="1" max="3650" value="{{ $cfg['days'] }}" style="height:34px;width:90px"></label>
<label>Who may listen<br><select id="rsListen" style="height:34px"><option value="admin" @selected($cfg['listen']==='admin')>Super/Admin only</option><option value="handler" @selected($cfg['listen']==='handler')>Admin + the representative who took the call</option><option value="all" @selected($cfg['listen']==='all')>Everyone in Call Center</option></select></label>
<label>Who may download (PBX + WhatsApp)<br><select id="rsDl" style="height:34px"><option value="admin" @selected($cfg['download']==='admin')>Super/Admin only</option><option value="handler" @selected($cfg['download']==='handler')>Admin + the representative of that call</option><option value="all" @selected($cfg['download']==='all')>Everyone in Call Center</option></select></label>
<label style="flex:1;min-width:240px">Notice the representative reads to the customer<br><input id="rsNotice" maxlength="300" value="{{ $cfg['notice'] }}" style="height:34px;width:100%"></label>
<button class="wc-b" style="height:34px;padding:0 14px;border-radius:8px;border:1px solid #F95505;background:#F95505;color:#fff;font-weight:700;cursor:pointer" onclick="rsSave(this)">Save</button><small id="rsMsg" style="color:#475467"></small></div>
<div class="cc-note">Delete is for Super/Admin only. A download is an MP3 copy made for phones and computers; the stored original is not changed. Every download is logged with who, when and which call. After the keep-for period a recording is removed by the system; the call row and its notes stay.</div></div>
@endif
<div class="cc-box"><form method="get" style="padding:10px 14px;display:flex;gap:8px;flex-wrap:wrap"><input name="q" value="{{ $q }}" placeholder="Customer, number, representative or order no." style="height:34px;flex:1;min-width:200px;border:1px solid #D0D5DD;border-radius:8px;padding:0 10px">
<select name="status" style="height:34px;border:1px solid #D0D5DD;border-radius:8px"><option value="">All</option>@foreach(['complete'=>'Recorded','incomplete'=>'Incomplete','failed'=>'Failed','deleted'=>'Deleted','expired'=>'Expired'] as $k=>$v)<option value="{{ $k }}" @selected($status===$k)>{{ $v }}</option>@endforeach</select>
<button style="height:34px;padding:0 14px;border-radius:8px;border:1px solid #D0D5DD;background:#fff;font-weight:700;cursor:pointer">Search</button></form>
<div id="rsList"></div></div>
@endif
@endsection
@section('js')
<script>
(function(){var h=document.getElementById('rsList');if(h)WaRecUI.render(h,@json($rows),@json($admin),{full:true,reload:true,empty:'No recording matches. A recording appears here after a WhatsApp call is answered.'})})();
function rsSave(b){b.disabled=true;var t=document.querySelector('meta[name=csrf-token]').content;fetch('/admin/call-center/whatsapp/rec/settings',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':t},body:JSON.stringify({on:document.getElementById('rsOn').value==='1',days:+document.getElementById('rsDays').value,listen:document.getElementById('rsListen').value,download:document.getElementById('rsDl').value,notice:document.getElementById('rsNotice').value})}).then(function(r){return r.json()}).then(function(k){b.disabled=false;document.getElementById('rsMsg').textContent=k.ok?'Saved':'Not saved: check the values';if(k.ok)setTimeout(function(){location.reload()},600)}).catch(function(){b.disabled=false;document.getElementById('rsMsg').textContent='Not saved'})}
</script>
@endsection
