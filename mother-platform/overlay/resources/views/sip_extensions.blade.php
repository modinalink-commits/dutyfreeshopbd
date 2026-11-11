@extends('layout')@section('title','Extensions')
@section('body')
<h1 style="margin:0 0 4px">Extensions</h1>
<span style="font-size:13px;color:#6B7280">প্রতিটি ইউজারের নিজস্ব এক্সটেনশন। PBX (FusionPBX) এ এক্সটেনশন তৈরি করে এখানে নম্বর ও পাসওয়ার্ড দিন — ইউজার লগইন করলে তার ডায়ালার নিজের এক্সটেনশনে কানেক্ট হবে। পাসওয়ার্ড এনক্রিপ্টেড থাকে। সার্ভার (WSS/Domain) সেটিং: <a href="/admin/call-center/account">PBX Settings</a>।</span>
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
<td style="white-space:nowrap">@if($x && $x->username)@php($both=in_array((string)$x->username, $relay, true))<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:{{ $both ? '#FEF3C7' : '#D1FAE5' }};color:{{ $both ? '#B45309' : '#047857' }}">{{ $both ? 'AGENT + RELAY' : 'AGENT' }}</span>@endif</td>
<td><input name="auth_username" value="{{ $x->auth_username ?? '' }}" placeholder="same" style="width:90px"></td>
<td><input name="display_name" value="{{ $x->display_name ?? '' }}" placeholder="{{ $u->name }}" style="width:130px"></td>
<td><input type="text" name="password" value="{{ \App\Http\Controllers\CallCenterController::pwPlain($x ?? null) }}" autocomplete="off" autocapitalize="off" spellcheck="false" data-lpignore="true" placeholder="required" style="width:150px;font-family:Inter,'Bornomala',sans-serif"></td>
<td><button class="btn" type="submit">Save</button></td></form></tr>
@endforeach
@php($n=max(10,$pool->count()+1))
@for($i=0;$i<$n;$i++)@php($p=$pool[$i] ?? null)
<tr style="border-top:1px solid #E5E7EB"><td style="padding:7px 6px;color:#6B7280;white-space:nowrap">Pool #{{ $i+1 }}<input form="poolForm" type="hidden" name="rows[{{ $i }}][id]" value="{{ $p->id ?? '' }}"></td>
<td><input form="poolForm" name="rows[{{ $i }}][extension]" value="{{ $p->extension ?? '' }}" style="width:90px" autocomplete="off"></td>
<td style="white-space:nowrap">@if($p && $p->extension)@php($rl=in_array((string)$p->extension, $relay, true) || in_array((string)($p->auth_username ?? ''), $relay, true))<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:{{ $rl ? '#FFEDD5' : '#F3F4F6' }};color:{{ $rl ? '#B45309' : '#6B7280' }}">{{ $rl ? 'RELAY' : 'FREE' }}</span>@endif</td>
<td><input form="poolForm" name="rows[{{ $i }}][auth_username]" value="{{ $p->auth_username ?? '' }}" placeholder="same" style="width:90px" autocomplete="off"></td>
<td><input form="poolForm" name="rows[{{ $i }}][display_name]" value="{{ ($p->display_name ?? '') === 'DutyFreeBD' ? '' : ($p->display_name ?? '') }}" placeholder="DutyFreeBD" style="width:130px" autocomplete="off"></td>
<td><input form="poolForm" type="text" name="rows[{{ $i }}][password]" value="{{ \App\Http\Controllers\CallCenterController::pwPlain($p) }}" autocomplete="off" autocapitalize="off" spellcheck="false" data-lpignore="true" style="width:150px;font-family:Inter,'Bornomala',sans-serif"></td><td></td></tr>
@endfor</table>
<form id="poolForm" method="post" action="/admin/call-center/extensions-pool">@csrf</form>
<div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap"><button class="btn" type="submit" form="poolForm">Save pool extensions</button><span style="font-size:12px;color:#6B7280">User rows save with their own Save button. Pool rows: fill the rows you need and press Save pool extensions. Agent name left blank = default agent "DutyFreeBD". Clear the Extension box of a saved pool row and Save to remove it. Passwords are stored encrypted. Extension ফাঁকা রেখে Save করলে ইউজারের এক্সটেনশন সরে যায়; একই এক্সটেনশন দুজনকে দেওয়া যাবে না।</span></div></div>
@endforeach
@endsection
