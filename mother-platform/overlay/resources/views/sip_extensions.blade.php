@extends('layout')@section('title','Extensions')
@section('body')
<h1 style="margin:0 0 4px">Extensions</h1>
<span style="font-size:13px;color:#6B7280">প্রতিটি ইউজারের নিজস্ব এক্সটেনশন। PBX (FusionPBX) এ এক্সটেনশন তৈরি করে এখানে নম্বর ও পাসওয়ার্ড দিন — ইউজার লগইন করলে তার ডায়ালার নিজের এক্সটেনশনে কানেক্ট হবে। পাসওয়ার্ড এনক্রিপ্টেড থাকে। সার্ভার (WSS/Domain) সেটিং: <a href="/admin/call-center/account">PBX Settings</a>।</span>
@if(session('ok'))<div class="card" style="margin-top:12px;background:#ECFDF5;border-color:#A7F3D0">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card err" style="margin-top:12px">@foreach($errors->all() as $e){{ $e }}<br>@endforeach</div>@endif
<div class="card" style="margin-top:12px;overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px">
<tr style="text-align:left;color:#6B7280"><th>User</th><th>Extension</th><th>Auth username</th><th>Display name</th><th>Password</th><th></th></tr>
@foreach($users as $u)@php($x=$rows[$u->id] ?? null)
<tr style="border-top:1px solid #E5E7EB"><form method="post" action="/admin/call-center/extensions/{{ $u->id }}">@csrf
<td style="padding:7px 6px"><b>{{ $u->name }}</b><br><small style="color:#6B7280">{{ $u->email }} · {{ $u->role }}{{ $u->active ? '' : ' · inactive' }}</small></td>
<td><input name="username" value="{{ $x->username ?? '' }}" placeholder="{{ $next }}" style="width:90px"></td>
<td><input name="auth_username" value="{{ $x->auth_username ?? '' }}" placeholder="same" style="width:90px"></td>
<td><input name="display_name" value="{{ $x->display_name ?? '' }}" placeholder="{{ $u->name }}" style="width:130px"></td>
<td><input type="text" name="password" value="{{ \App\Http\Controllers\CallCenterController::pwPlain($x ?? null) }}" autocomplete="off" autocapitalize="off" spellcheck="false" data-lpignore="true" placeholder="required" style="width:150px;font-family:ui-monospace,Menlo,monospace"></td>
<td><button class="btn" type="submit">Save</button></td></form></tr>
@endforeach</table>
<div style="margin-top:8px;font-size:12px;color:#6B7280">Extension ফাঁকা রেখে Save করলে ওই ইউজারের এক্সটেনশন সরে যায়। একই এক্সটেনশন দুজনকে দেওয়া যাবে না।</div></div>
@endsection
