@extends('layout')@section('title','Chat Settings')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Chat Settings</h1><span style="font-size:13px;color:#6B7280">Connect Facebook, Instagram, WhatsApp and the website chat to the Messages inbox.</span></div><a class="btn g" href="/admin/chat">Open inbox</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if(session('err'))<div class="card err">{{ session('err') }}</div>@endif
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<div class="grid" style="margin-bottom:16px">
@foreach(['facebook'=>['Facebook', $has['fb']], 'instagram'=>['Instagram', $has['ig'] || $has['fb']], 'whatsapp'=>['WhatsApp', $has['wa'] && $wa_phone], 'web'=>['Website Chat', $web_on]] as $k => [$l, $on])
<div class="card k" style="margin:0"><span style="font-size:13px">{{ $l }}</span><b style="font-size:20px">{{ $on ? 'Connected' : 'Not connected' }}</b><span>{{ number_format($counts[$k] ?? 0) }} conversations</span></div>@endforeach</div>
<form class="card" method="post" action="/admin/chat-settings" style="max-width:760px">@csrf
<b>Meta webhook (Facebook, Instagram, WhatsApp)</b>
<p style="font-size:12px;color:#6B7280;margin:4px 0 10px">In your Meta app (developers.facebook.com) open Webhooks and use these two values. Subscribe the Page to <i>messages</i>, the Instagram account to <i>messages</i>, and WhatsApp to <i>messages</i>. A Meta app can use only one callback URL per product; if another tool (for example LazyChat) already uses it for the same page, only one of the two will receive messages.</p>
<label>Callback URL</label><input readonly value="{{ $hook }}" onclick="this.select()">
<div style="margin:6px 0 10px;padding:8px 10px;border-radius:8px;font-size:12.5px;background:{{ ($hook_last ?? null) && str_contains($hook_last, 'accepted') ? '#ECFDF5' : '#FEF3C7' }}"><b>Last webhook from Meta:</b> {{ $hook_last ?? 'nothing received yet' }}</div>
<label>Verify token</label><input readonly value="{{ $verify }}" onclick="this.select()">
<label>App secret {{ $has['secret'] ? '(saved)' : '(required: messages are rejected without it)' }}</label><input type="password" name="app_secret" placeholder="{{ $has['secret'] ? 'Leave blank to keep the saved secret' : 'Paste the Meta app secret' }}" autocomplete="off">
<hr style="border:0;border-top:1px solid #FFE7D6;margin:16px 0">
<b>Page and account tokens</b>
<p style="font-size:12px;color:#6B7280;margin:4px 0 10px">Tokens are stored encrypted and never shown again. Leave a field blank to keep what is saved.</p>
<label>Facebook Page access token {{ $has['fb'] ? '(saved)' : '' }}</label><input type="password" name="fb_token" autocomplete="off">
<label>Facebook Page ID <span style="color:#6B7280">(optional, auto-detected)</span></label><input name="page_id" placeholder="Page ID" autocomplete="off"><button type="submit" formaction="/admin/chat-settings/fb-subscribe" formnovalidate class="g" style="margin:8px 0">Subscribe Facebook Page to this app (receive Messenger messages)</button>
<label>Instagram token {{ $has['ig'] ? '(saved)' : '(optional; the Page token is used when blank)' }}</label><input type="password" name="ig_token" autocomplete="off">
<label>WhatsApp Cloud API token {{ $has['wa'] ? '(saved)' : '' }}</label><input type="password" name="wa_token" autocomplete="off">
<label>WhatsApp phone number ID</label><input name="wa_phone_id" value="{{ $wa_phone }}" autocomplete="off">
<label>WhatsApp account (WABA) ID <span style="color:#6B7280">(optional, auto-detected)</span></label><input name="waba_id" placeholder="e.g. 1728673528206378" autocomplete="off"><button type="submit" formaction="/admin/chat-settings/wa-subscribe" formnovalidate class="g" style="margin:8px 0">Subscribe this app to WhatsApp (receive customer messages)</button>
<div style="margin:10px 0;padding:10px;border:1px dashed #FDBA74;border-radius:8px"><b style="font-size:13px">Step 1: verify the number (code expired)</b><div style="display:flex;gap:8px;margin:6px 0"><button type="submit" name="code_method" value="SMS" formaction="/admin/chat-settings/wa-code" formnovalidate class="g">Send code by SMS</button><button type="submit" name="code_method" value="VOICE" formaction="/admin/chat-settings/wa-code" formnovalidate class="g">Send code by voice call</button></div><input name="wa_code" inputmode="numeric" maxlength="8" placeholder="6-digit code received" autocomplete="off"><button type="submit" formaction="/admin/chat-settings/wa-code" formnovalidate class="g" style="margin-top:6px">Verify code</button></div>
<label>Register number: choose a new 6-digit PIN (not stored)</label><input name="reg_pin" inputmode="numeric" maxlength="6" placeholder="6 digits" autocomplete="off"><button type="submit" formaction="/admin/chat-settings/wa-register" formnovalidate class="g" style="margin:8px 0">Register WhatsApp number (fixes Offline status)</button>
<hr style="border:0;border-top:1px solid #FFE7D6;margin:16px 0">
<label style="display:flex;gap:8px;align-items:center;margin:0;font-size:13px;color:#0B1220"><input type="checkbox" name="web_on" value="1" {{ $web_on ? 'checked' : '' }} style="width:auto"> Show the live chat button on the website</label>
<label style="display:flex;gap:8px;align-items:center;margin:8px 0 0;font-size:13px;color:#0B1220"><input type="checkbox" name="ai_auto" value="1" {{ $ai_auto ? 'checked' : '' }} style="width:auto"> Claude AI answers website chat automatically (stops on a chat once a staff member replies)</label>
<div style="margin-top:14px"><button>Save settings</button></div></form>
<div class="card" style="max-width:760px"><b>Quick replies</b><p style="font-size:12px;color:#6B7280;margin:4px 0 10px">Ready messages that staff pick from the chat panel.</p>
@foreach($quick as $q)<form method="post" action="/admin/chat-settings/quick" style="display:grid;grid-template-columns:150px 1fr auto auto;gap:8px;margin-bottom:8px;align-items:start">@csrf<input type="hidden" name="id" value="{{ $q->id }}"><input name="title" value="{{ $q->title }}" required maxlength="80"><textarea name="body" rows="2" required maxlength="1500">{{ $q->body }}</textarea><button>Save</button><button class="g" formaction="/admin/chat-settings/quick/{{ $q->id }}/delete" formnovalidate onclick="return confirm('Remove this quick reply?')">Remove</button></form>@endforeach
<form method="post" action="/admin/chat-settings/quick" style="display:grid;grid-template-columns:150px 1fr auto;gap:8px;margin-top:12px;align-items:start;border-top:1px solid #FFE7D6;padding-top:12px">@csrf<input name="title" placeholder="Title" required maxlength="80"><textarea name="body" rows="2" placeholder="Message text" required maxlength="1500"></textarea><button>Add</button></form></div>
@endsection
