@extends('layout')@section('title','AI Chat (Claude)')
@section('body')
<h1 style="margin:0 0 4px">AI Chat (Claude)</h1><span style="font-size:13px;color:#6B7280">A shopping assistant on the storefront. It answers from your live product list (name, price, stock, link) and sends customers to ORDER NOW or to your phone number. It cannot see orders or customer data.</span>
@if(session('ok'))<div class="card" style="color:#166534;margin-top:12px">{{ session('ok') }}</div>@endif
@if(session('bad'))<div class="card err" style="margin-top:12px">{{ session('bad') }}</div>@endif
@if($errors->any())<div class="card err" style="margin-top:12px">{{ $errors->first() }}</div>@endif
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin:14px 0"><div class="card k" style="margin:0"><span style="font-size:13px">Status</span><b style="font-size:22px">{{ $on && $hasKey ? 'ON' : 'OFF' }}</b></div><div class="card k" style="margin:0"><span style="font-size:13px">API key</span><b style="font-size:22px">{{ $hasKey ? 'Saved' : 'Not set' }}</b></div><div class="card k" style="margin:0"><span style="font-size:13px">Chats today</span><b style="font-size:26px">{{ $today }}</b></div></div>
<form class="card" method="post" action="/admin/ai-chat" style="max-width:640px">@csrf
<b>Settings</b><p style="font-size:12px;color:#6B7280;margin:4px 0 10px">Create a key at console.anthropic.com (API keys) and paste it here yourself. It is stored encrypted and never shown again; leave blank to keep the saved key. A daily limit of 1,500 messages protects your bill.</p>
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="enabled" value="1" style="width:auto" {{ $on ? 'checked' : '' }}> Show the chat button on the store</label>
<label>Claude API key {{ $hasKey ? '(saved, leave blank to keep)' : '' }}</label><input type="password" name="api_key" autocomplete="new-password" placeholder="{{ $hasKey ? '••••••••••••' : 'sk-ant-...' }}">
<label>Model</label><select name="model"><option value="claude-haiku-5-5" {{ $model==='claude-haiku-5-5'?'selected':'' }}>Claude Haiku 5.5 (fast, cheapest, recommended)</option><option value="claude-sonnet-5-5" {{ $model==='claude-sonnet-5-5'?'selected':'' }}>Claude Sonnet 5.5 (smarter, costs more)</option></select>
<div style="margin-top:14px"><button>Save</button></div></form>
<form class="card" method="post" action="/admin/ai-chat/test" style="max-width:640px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">@csrf<button>Send a test message</button><span style="font-size:12px;color:#6B7280">Checks that the key works.</span></form>
@endsection
