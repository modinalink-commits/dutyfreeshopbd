@extends('layout')@section('title','WhatsApp Inbox')
@section('body')
@php $ago = fn($t) => $t ? \Carbon\Carbon::parse($t, 'UTC')->timezone('Asia/Dhaka')->format('d M, h:i A') : ''; $tk = fn($v) => '৳'.number_format((float)$v, 0); @endphp
<style>
.wa{display:grid;grid-template-columns:320px minmax(0,1fr) 270px;gap:10px;align-items:start}
.wa .col{background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden}
.wa .list{max-height:72vh;overflow:auto}
.wa .cv{display:block;padding:10px 12px;border-bottom:1px solid #F3F4F6;color:inherit;text-decoration:none}
.wa .cv.on{background:#ECFDF5}.wa .cv b{font-size:14px}.wa .cv small{display:block;color:#6B7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wa .un{background:#16A34A;color:#fff;border-radius:99px;font-size:11px;padding:1px 7px;float:right}
.wa .msgs{height:56vh;overflow:auto;padding:12px;background:#F3F4F6;display:flex;flex-direction:column;gap:6px}
.wa .m{max-width:78%;padding:7px 10px;border-radius:10px;font-size:14px;white-space:pre-wrap;word-break:break-word;background:#fff}
.wa .m.out{align-self:flex-end;background:#DCFCE7}.wa .m small{display:block;font-size:11px;color:#6B7280;margin-top:3px}
.wa .tabs a{padding:4px 9px;border-radius:99px;font-size:12px;text-decoration:none;color:#374151;background:#F3F4F6}.wa .tabs a.on{background:#16A34A;color:#fff}
@media(max-width:1000px){.wa{grid-template-columns:1fr}.wa .list{max-height:40vh}}
</style>
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px"><h1 style="margin:0;flex:1">WhatsApp Inbox</h1>
<span style="font-size:13px;color:#6B7280">Open {{ $stats['open'] }} · Unread {{ $stats['unread'] }} · Unassigned {{ $stats['unassigned'] }}</span>
<a href="/admin/whatsapp/manage"><button class="g" type="button">Settings · Templates · Campaigns · Activity</button></a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif @if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif @if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
@if(!$on)<div class="card err">WhatsApp is not connected yet. Open Settings and enter the Meta Cloud API details.</div>@endif
<div class="wa">
<div class="col"><div style="padding:8px"><form method="get" style="display:flex;gap:6px"><input type="hidden" name="f" value="{{ $f }}"><input name="q" value="{{ $q }}" placeholder="Search name / number / text" style="flex:1"><button class="g" type="submit">Go</button></form>
<div class="tabs" style="display:flex;flex-wrap:wrap;gap:5px;margin-top:8px">@foreach(['open'=>'Open','mine'=>'Mine','unassigned'=>'Unassigned','unread'=>'Unread','closed'=>'Closed','all'=>'All'] as $k=>$l)<a class="{{ $f===$k?'on':'' }}" href="?f={{ $k }}">{{ $l }}</a>@endforeach</div></div>
<div class="list">@forelse($convs as $c)<a class="cv {{ $cur && $cur->id==$c->id ? 'on':'' }}" href="?c={{ $c->id }}&f={{ $f }}"><b>{{ $c->name ?: '+'.$c->wa_id }}</b>@if($c->unread>0)<span class="un">{{ $c->unread }}</span>@endif<small>{{ $c->last_text }}</small><small>{{ $ago($c->last_message_at) }}@if($c->assigned_name) · {{ $c->assigned_name }}@endif</small></a>
@empty<div style="padding:16px;color:#6B7280">No conversations.</div>@endforelse</div></div>

<div class="col">
@if($cur)
<div style="padding:10px 12px;border-bottom:1px solid #E5E7EB;display:flex;flex-wrap:wrap;gap:8px;align-items:center"><div style="flex:1"><b>{{ $cur->name ?: '+'.$cur->wa_id }}</b><br><small style="color:#6B7280">+{{ $cur->wa_id }} · {{ $win ? '24h window open' : 'Window closed — templates only' }}</small></div>
@if($can)<form method="post" action="/admin/whatsapp/{{ $cur->id }}/status">@csrf<input type="hidden" name="to" value="{{ $cur->status==='closed'?'open':'closed' }}"><button class="g" type="submit">{{ $cur->status==='closed'?'Reopen':'Close' }}</button></form>@endif</div>
<div class="msgs" id="msgs">@foreach($msgs as $m)<div class="m {{ $m->direction==='out'?'out':'' }}">
@if($m->media_id)@if(str_starts_with((string)$m->mime,'image/'))<a href="/admin/whatsapp/media/{{ $m->media_id }}" target="_blank"><img src="/admin/whatsapp/media/{{ $m->media_id }}" style="max-width:220px;border-radius:8px;display:block" loading="lazy" alt=""></a>@else<a href="/admin/whatsapp/media/{{ $m->media_id }}" target="_blank">📎 {{ $m->type }}</a>@endif @endif
{{ $m->body }}@if($m->template_name)<em> [template: {{ $m->template_name }}]</em>@endif
<small>{{ $ago($m->ts) }}@if($m->direction==='out') · {{ $m->sent_by }} · {{ $m->status }}@endif @if($m->error)<span style="color:#B91C1C"> {{ $m->error }}</span>@endif</small></div>@endforeach</div>
@if($can)
<div style="padding:10px;border-top:1px solid #E5E7EB">
@if($win)<form method="post" action="/admin/whatsapp/{{ $cur->id }}/send" style="display:flex;gap:8px">@csrf<input type="hidden" name="f" value="{{ $f }}"><textarea name="text" rows="2" maxlength="4000" required placeholder="Type a reply…" style="flex:1"></textarea><button type="submit">Send</button></form>
@else<div style="font-size:13px;color:#92400E;margin-bottom:6px">Customer has not written in the last 24 hours — only an approved template can be sent.</div>@endif
<details style="margin-top:6px"><summary style="cursor:pointer;font-size:13px">Send a template</summary>
<form method="post" action="/admin/whatsapp/{{ $cur->id }}/send" style="margin-top:6px">@csrf<input type="hidden" name="f" value="{{ $f }}">
<select name="template" required><option value="">Choose approved template…</option>@foreach($tpls as $t)<option value="{{ $t->name }}|{{ $t->language }}">{{ $t->name }} ({{ $t->language }}) — {{ $t->params }} param</option>@endforeach</select>
<input name="p[]" placeholder="Param 1" style="margin-top:6px"><input name="p[]" placeholder="Param 2"><input name="p[]" placeholder="Param 3"><button type="submit" style="margin-top:6px">Send template</button></form></details></div>
@endif
<script>var m=document.getElementById('msgs');if(m)m.scrollTop=m.scrollHeight;</script>
@else<div style="padding:40px;text-align:center;color:#6B7280">Select a conversation.</div>@endif
</div>

<div class="col" style="padding:12px">
@if($cur)
<b>Customer</b>
@if($cust)<div style="margin-top:4px">{{ $cust->name ?? '' }}<br><small>{{ $cust->phone ?? '' }}</small><br><a href="/admin/m/customers?q={{ urlencode((string)($cust->phone ?? '')) }}">Open customer →</a></div>@else<div style="color:#6B7280;font-size:13px;margin-top:4px">No matching customer.</div>@endif
<hr style="border:0;border-top:1px solid #E5E7EB;margin:10px 0"><b>Recent orders</b>
@forelse($orders as $o)<div style="font-size:13px;margin-top:4px"><a href="/admin/orders?q={{ urlencode((string)$o->invoice) }}">#{{ $o->invoice }}</a> · {{ $tk($o->total) }}<br><small style="color:#6B7280">{{ $o->status }} · {{ \Carbon\Carbon::parse($o->created_at)->format('d M Y') }}</small></div>@empty<div style="color:#6B7280;font-size:13px;margin-top:4px">None found.</div>@endforelse
@if($can)<hr style="border:0;border-top:1px solid #E5E7EB;margin:10px 0"><b>Assigned to</b>
<form method="post" action="/admin/whatsapp/{{ $cur->id }}/assign" style="margin-top:6px">@csrf<select name="user_id"><option value="0">— Unassigned —</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected($cur->assigned_to==$u->id)>{{ $u->name }}</option>@endforeach</select><button class="g" type="submit" style="margin-top:6px">Save</button></form>@endif
@else<span style="color:#6B7280;font-size:13px">Customer and order details appear here.</span>@endif
</div></div>
<script>@if(!$cur)setInterval(function(){var a=document.activeElement,t=a&&a.tagName;if(document.hidden||t==='INPUT'||t==='TEXTAREA'||t==='SELECT')return;if(window.dfSoft)dfSoft({quiet:true})},60000);@endif</script>
@endsection
