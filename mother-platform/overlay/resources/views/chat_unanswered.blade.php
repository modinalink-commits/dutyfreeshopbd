@extends('layout')@section('title','Unanswered WhatsApp chats')
@section('body')
<h1>Unanswered WhatsApp chats</h1>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<p style="font-size:13px;color:#6B7280;margin:0 0 12px">Chats where the customer's message was never answered. "Close" sends one short last message (an apology for the delay and "let us know if you need anything") and resolves the chat. A chat stays open if the send fails. Chats with a complaint, refund, payment, cancel, order change, human request or open hand-off, and chats older than the 24-hour WhatsApp window, are never touched.</p>
<div class="card"><b>Ready to close: {{ count($ok) }}</b>@if(count($ok))<form method="post" style="display:inline;margin-left:12px">@csrf<button class="btn" type="submit" onclick="return confirm('Send the last message and close {{ min(40, count($ok)) }} chats?')">Send last message and close (max 40 at a time)</button></form>@endif
<table style="width:100%;margin-top:8px"><thead><tr><th style="text-align:left">Customer</th><th style="text-align:left">Last customer message</th><th>When</th></tr></thead><tbody>
@forelse($ok as $t)<tr><td>{{ $t->name ?: $t->phone ?: $t->external_id }}</td><td>{{ \Illuminate\Support\Str::limit((string)$t->last_message, 90) }}</td><td style="white-space:nowrap">{{ $t->last_at ? \Carbon\Carbon::parse($t->last_at)->timezone('Asia/Dhaka')->format('d M H:i') : '' }}</td></tr>@empty<tr><td colspan="3" style="color:#6B7280">Nothing to close.</td></tr>@endforelse</tbody></table></div>
@foreach($blocked as $why => $list)<div class="card" style="margin-top:12px"><b>Left open — {{ $why }}: {{ count($list) }}</b><div style="font-size:12.5px;color:#6B7280;margin-top:4px">@foreach(array_slice($list,0,15) as $t){{ $t->name ?: $t->phone }}@if(!$loop->last), @endif @endforeach @if(count($list) > 15) … @endif</div></div>@endforeach
@endsection
