@extends('layout')@section('title','Marine Settings')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Marine Settings</h1><div style="font-size:12px;color:#6B7280">মেরিন ডেস্কের যোগাযোগ তথ্য। সেভ করলে <a href="{{ $site }}" target="_blank" rel="noopener">মেরিন সাইটে</a> দেখাবে।</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card" style="background:#FEE2E2;border-color:#FECACA;color:#991B1B">{{ $errors->first() }}</div>@endif
<form method="post" action="/admin/marine/settings" class="card" style="display:flex;flex-direction:column;gap:12px;max-width:520px">@csrf
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Marine desk email<input type="email" name="marine_desk_email" value="{{ old('marine_desk_email', $conf['marine_desk_email']) }}" placeholder="marine@example.com"></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">Marine desk phone (দেশের কোডসহ)<input name="marine_desk_phone" value="{{ old('marine_desk_phone', $conf['marine_desk_phone']) }}" placeholder="+880 ..."></label>
<label style="display:flex;flex-direction:column;gap:4px;font-size:13px">WhatsApp (দেশের কোডসহ)<input name="marine_desk_whatsapp" value="{{ old('marine_desk_whatsapp', $conf['marine_desk_whatsapp']) }}" placeholder="+880 ..."></label>
<div><button class="btn">Save</button></div>
</form>
@endsection
