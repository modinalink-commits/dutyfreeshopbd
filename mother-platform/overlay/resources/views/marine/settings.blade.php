@extends('layout')@section('title','Marine Settings')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Desk Contact</h1><div style="font-size:12px;color:#6B7280">Marine desk contact details. Once saved they show on <a href="{{ $site }}" target="_blank" rel="noopener">the marine site</a>.</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
@if(session('bad'))<div class="card" style="background:#FEE2E2;border-color:#FECACA;color:#991B1B">{{ session('bad') }}</div>@endif
@if($errors->any())<div class="card" style="background:#FEE2E2;border-color:#FECACA;color:#991B1B">{{ $errors->first() }}</div>@endif
<form method="post" action="/admin/marine/settings" class="card" style="display:flex;flex-direction:column;gap:12px;max-width:520px">@csrf
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Marine desk email<input type="email" name="marine_desk_email" value="{{ old('marine_desk_email', $conf['marine_desk_email']) }}" placeholder="marine@example.com"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Marine desk phone (with country code)<input name="marine_desk_phone" value="{{ old('marine_desk_phone', $conf['marine_desk_phone']) }}" placeholder="+880 ..."></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">WhatsApp (with country code)<input name="marine_desk_whatsapp" value="{{ old('marine_desk_whatsapp', $conf['marine_desk_whatsapp']) }}" placeholder="+880 ..."></label>
<div><button class="btn">Save</button></div>
</form>
@if($admin ?? false)
<form method="post" action="/admin/marine/demo-accounts" class="card" style="display:grid;gap:10px;max-width:620px;margin-top:14px" autocomplete="off">@csrf
<b>Demo accounts</b>
<div style="font-size:12px;color:#6B7280">Three permanent test accounts to see the portal as a shipping owner, a local agency and a vessel, from any device. Type a password here and it is set for the accounts you tick. It is stored encrypted, shown nowhere and written to no log; only an administrator sees this form. Requisitions sent from these accounts carry the name DEMO. They carry every approval, bonded stores included, and their demo vessel is verified.</div>
<div style="display:grid;gap:4px;font-size:13px">@foreach($demoDef as $t => $x)@php $d = $demo[$t] ?? null; @endphp<label><input type="checkbox" name="accounts[]" value="{{ $t }}" checked> <b>{{ $x[0] }}</b> <span style="color:#6B7280">· {{ ['owner' => 'Shipping Owner', 'agency' => 'Local Agency', 'vessel' => 'Vessel (captain)'][$t] }} · {{ $d ? ($d['ready'] ? 'ready' . ($d['last'] ? ', last sign-in ' . \Carbon\Carbon::parse($d['last'])->timezone('Asia/Dhaka')->format('d M, H:i') : ', not used yet') : 'no password yet') : 'not created yet' }}</span></label>@endforeach</div>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Password (at least 10 characters)<input type="password" name="password" minlength="10" maxlength="128" required autocomplete="new-password"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Password again<input type="password" name="password2" minlength="10" maxlength="128" required autocomplete="new-password"></label>
<div><button class="btn">Set password for the ticked accounts</button> <a class="btn g" href="/marine-portal/login" target="_blank" rel="noopener">Open portal sign-in</a></div>
</form>
@endif
@endsection
