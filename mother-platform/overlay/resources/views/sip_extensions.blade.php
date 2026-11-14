@extends('layout')@section('title','Extensions')
@section('body')
@include('cc_ui')
<style>.ccx .card{overflow:hidden!important}.ccx table{table-layout:fixed;width:100%}.ccx th{font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:#475467;font-weight:700;padding:6px}.ccx td{padding:6px!important;white-space:normal!important;overflow-wrap:anywhere;vertical-align:middle}.ccx td input:not([type=hidden]){width:100%!important;min-width:0}.ccx td small{display:block}.ccx td .btn{padding:6px 10px;margin:1px 0}.ccx th:nth-child(1){width:22%}.ccx th:nth-child(2){width:10%}.ccx th:nth-child(3){width:13%}.ccx th:nth-child(4){width:12%}.ccx th:nth-child(5){width:14%}.ccx th:nth-child(6){width:15%}@media(max-width:760px){.ccx th:nth-child(4),.ccx td:nth-child(4),.ccx th:nth-child(5),.ccx td:nth-child(5){display:none}}</style>
<div class="ccx"><div class="cc-h"><h1>Extensions</h1></div>@if(!empty($regErr))<div style="font-size:11px;color:#B91C1C">Status display error: {{ $regErr }}</div>@endif
<span style="font-size:13px;color:#6B7280">Each user has their own extension. Create the extension in the PBX (FusionPBX), then enter its number and password here. When the user signs in, their dialer connects to their own extension. Passwords are stored encrypted. Server (WSS / Domain) settings: <a href="/admin/call-center/account">PBX Settings</a>.</span>
@if(session('ok'))<div class="card" style="margin-top:12px;background:#DCFCE7;border-color:#A7F3D0">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card err" style="margin-top:12px">@foreach($errors->all() as $e){{ $e }}<br>@endforeach</div>@endif
@foreach($pbxs as $pbx)
<div class="card" style="margin-top:12px;overflow-x:auto">
<div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;flex-wrap:wrap;margin-bottom:8px"><b>PBX · {{ $pbx->account_name ?: ($pbx->domain ?? 'FusionPBX') }}</b><a href="/admin/call-center/account" style="font-size:12px">Edit PBX settings</a></div>
<div style="display:flex;flex-wrap:wrap;gap:6px 18px;font-size:12px;color:#6B7280;margin-bottom:10px">
@foreach(['sip_server'=>'SIP server','wss_url'=>'WSS','domain'=>'Domain','outbound_proxy'=>'Proxy','dial_prefix'=>'Prefix','caller_id'=>'Caller ID','stun_server'=>'STUN'] as $k=>$lb)@if(!empty($pbx->$k))<span><b style="color:#111827">{{ $lb }}</b> {{ $pbx->$k }}</span>@endif @endforeach
</div>
<table style="width:100%;border-collapse:collapse;font-size:13px">
<tr style="text-align:left;color:#6B7280"><th>User</th><th>Extension</th><th>Use</th><th>Auth username</th><th>Display name</th><th>Password</th><th></th></tr>
@foreach($users as $u)@php($x=$rows[$u->id] ?? null)
<tr style="border-top:1px solid #E5E7EB"><form method="post" action="/admin/call-center/extensions/{{ $u->id }}">@csrf
<td style="padding:7px 6px;white-space:nowrap"><b>{{ $u->name }}</b> <small style="color:#6B7280">{{ $u->email }} · {{ $u->role }}{{ $u->active ? '' : ' · inactive' }}</small></td>
<td><input name="username" value="{{ $x->username ?? '' }}" placeholder="{{ $next }}" style="width:90px"></td>
<td style="white-space:nowrap">@if($x && $x->username)@php($both=in_array((string)$x->username, $relay, true))<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:{{ $both ? '#FEF3C7' : '#D1FAE5' }};color:{{ $both ? '#B45309' : '#047857' }}">{{ $both ? 'AGENT + RELAY' : 'AGENT' }}</span>{!! $regTag[(string)$x->username] ?? '' !!}@endif</td>
<td><input name="auth_username" value="{{ $x->auth_username ?? '' }}" placeholder="same" style="width:90px"></td>
<td><input name="display_name" value="{{ $x->display_name ?? '' }}" placeholder="{{ $u->name }}" style="width:130px"></td>
<td><input type="text" name="password" value="{{ \App\Http\Controllers\CallCenterController::pwPlain($x ?? null) }}" autocomplete="off" autocapitalize="off" spellcheck="false" data-lpignore="true" placeholder="required" style="width:150px;font-family:Inter,'Bornomala',sans-serif"></td>
<td style="white-space:nowrap"><button class="btn" type="submit">Save</button> @if($x && $x->username)<button class="btn" type="submit" onclick="if(!confirm('Delete extension '+this.form.username.value+' of {{ addslashes($u->name) }}?'))return false;this.form.username.value='';" style="background:#fff;color:#B91C1C;border:1px solid #FCA5A5">Delete</button>@endif</td></form></tr>
@endforeach
@include('sip_pool_rows', ['list'=>$pool, 'cid'=>0, 'min'=>4, 'relay'=>$relay, 'regTag'=>$regTag])</table>
<form id="poolForm0" method="post" action="/admin/call-center/extensions-pool">@csrf<input type="hidden" name="company_id" value="0"></form>
<div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap"><button class="btn" type="submit" form="poolForm0">Save extensions</button><button class="btn" type="button" onclick="addPoolRow(this)" style="background:#fff;color:#111827;border:1px solid #D1D5DB">+ Add extension</button><span style="font-size:12px;color:#6B7280">Every row has its own Save and Delete. + Add extension adds a new row; fill it and press its Save. Agent name left blank = default agent "DutyFreeBD". Passwords are stored encrypted. Saving a user row with an empty Extension removes that user's extension; the same extension cannot be given to two people.</span></div></div>
@endforeach
@foreach($companies as $co)@php($clist=$poolBy[$co->id] ?? collect())
<div class="card" style="margin-top:14px;overflow-x:auto">
<div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;flex-wrap:wrap;margin-bottom:8px"><b>Company · {{ $co->name }}</b>
<form method="post" action="/admin/call-center/extensions-company/{{ $co->id }}/delete" onsubmit="return confirm('Delete this company table? (extensions must be removed first)')">@csrf<button class="btn" type="submit" style="background:#fff;color:#B91C1C;border:1px solid #FCA5A5;padding:2px 10px;font-size:12px">Delete company</button></form></div>
<div style="display:flex;flex-wrap:wrap;gap:6px 18px;font-size:12px;color:#6B7280;margin-bottom:10px">@foreach(['sip_server'=>'SIP server','wss_url'=>'WSS','domain'=>'Domain'] as $k=>$lb)@if(!empty($co->$k))<span><b style="color:#111827">{{ $lb }}</b> {{ $co->$k }}</span>@endif @endforeach</div>
<table style="width:100%;border-collapse:collapse;font-size:13px">
<tr style="text-align:left;color:#6B7280"><th>#</th><th>Extension</th><th>Use</th><th>Auth username</th><th>Display name</th><th>Password</th><th></th></tr>
@include('sip_pool_rows', ['list'=>$clist, 'cid'=>$co->id, 'min'=>2, 'relay'=>[], 'regTag'=>[]])
</table>
<form id="poolForm{{ $co->id }}" method="post" action="/admin/call-center/extensions-pool">@csrf<input type="hidden" name="company_id" value="{{ $co->id }}"></form>
<div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap"><button class="btn" type="submit" form="poolForm{{ $co->id }}">Save extensions</button><button class="btn" type="button" onclick="addPoolRow(this)" style="background:#fff;color:#111827;border:1px solid #D1D5DB">+ Add extension</button><span style="font-size:12px;color:#6B7280">This company's extensions are kept here only; they are not used by the client-call relay.</span></div></div>
@endforeach
<div class="card" style="margin-top:14px"><b>Add another company / PBX</b>
<form method="post" action="/admin/call-center/extensions-company" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;align-items:center">@csrf
<input name="name" placeholder="Company name" required style="width:180px"><input name="sip_server" placeholder="SIP server (optional)" style="width:200px"><input name="wss_url" placeholder="wss://… (optional)" style="width:220px"><input name="domain" placeholder="Domain (optional)" style="width:180px"><button class="btn" type="submit">Add company table</button></form></div>
<script>
function poolRowAct(btn,act){var tr=btn.closest('tr'),f=tr.querySelector('input[form]');if(!f)return;var fid=f.getAttribute('form'),form=document.getElementById(fid);var ex=tr.querySelector('input[name$="[extension]"]');
if(act==='delete'){var id=tr.querySelector('input[name$="[id]"]');if(!id||!id.value){ex.value='';tr.querySelectorAll('input[name$="[password]"],input[name$="[auth_username]"],input[name$="[display_name]"]').forEach(function(i){i.value=''});return}if(!confirm('Delete extension '+ex.value+'?'))return;ex.value=''}
else if(!ex.value.trim()){ex.focus();return}
document.querySelectorAll('input[form="'+fid+'"]').forEach(function(i){if(i.closest('tr')!==tr)i.disabled=true});form.submit()}
function addPoolRow(btn){var card=btn.closest('.card'),t=card.querySelector('table'),rows=t.querySelectorAll('tr.prow'),last=rows[rows.length-1];if(!last)return;var n=rows.length,c=last.cloneNode(true);c.querySelectorAll('input').forEach(function(i){if(i.type==='hidden'){i.value=''}else if(i.name.indexOf('[password]')>-1||i.name.indexOf('[extension]')>-1||i.name.indexOf('[auth_username]')>-1||i.name.indexOf('[display_name]')>-1){i.value=''}i.name=i.name.replace(/rows\[\d+\]/,'rows['+n+']')});var b=c.querySelector('td:nth-child(3)');if(b)b.innerHTML='';var f=c.querySelector('td');if(f){var inp=f.querySelector('input');f.firstChild.textContent='Pool #'+(n+1)}t.appendChild(c)}
</script>
</div>
@endsection
