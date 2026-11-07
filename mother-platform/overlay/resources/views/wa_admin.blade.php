@extends('layout')@section('title','WhatsApp Settings')
@section('body')
@php $ago = fn($t) => $t ? \Carbon\Carbon::parse($t, 'UTC')->timezone('Asia/Dhaka')->format('d M, h:i A') : ''; @endphp
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px"><h1 style="margin:0;flex:1">WhatsApp — Manage</h1><a href="/admin/whatsapp"><button class="g" type="button">← Inbox</button></a></div>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">@foreach(['settings'=>'Settings','templates'=>'Templates','campaigns'=>'Campaigns','activity'=>'Staff activity'] as $k=>$l)<a href="?tab={{ $k }}"><button type="button" class="{{ $tab===$k?'':'g' }}">{{ $l }}</button></a>@endforeach</div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif @if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif @if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif

@if($tab==='settings')
<div class="card"><b>Webhook (paste into Meta → WhatsApp → Configuration)</b>
<p style="font-size:13px">Callback URL: <code>{{ $hook }}</code><br>Verify token: <code>{{ $verify }}</code><br>Subscribe to the <b>messages</b> field.</p></div>
<form class="card" method="post" action="/admin/whatsapp/settings">@csrf <b>Meta Cloud API credentials</b> <span style="font-size:13px;color:{{ $on?'#166534':'#B91C1C' }}">{{ $on?'Connected':'Not connected' }}</span>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;margin-top:8px">
<div><label>Phone number ID</label><input name="phone_id" value="{{ $cfg['phone_id'] }}"></div>
<div><label>WhatsApp Business Account ID</label><input name="waba_id" value="{{ $cfg['waba_id'] }}"></div>
<div><label>Access token (permanent)</label><input name="token" value="{{ $cfg['token'] }}" autocomplete="off"></div>
<div><label>App secret (verifies webhook)</label><input name="app_secret" value="{{ $cfg['secret'] }}" autocomplete="off"></div>
<div><label>Daily campaign cap</label><input type="number" name="daily_cap" value="{{ $cfg['cap'] }}" min="1"></div></div>
@if($can)<button type="submit" style="margin-top:10px">Save</button>@endif <small style="display:block;margin-top:6px;color:#6B7280">Masked values are kept unless you type a new one. Opt-outs on file: {{ $optouts }}.</small></form>
@endif

@if($tab==='templates')
<div class="card"><b>Message templates</b> @if($can)<form method="post" action="/admin/whatsapp/templates/sync" style="display:inline">@csrf<button class="g" type="submit">Sync from Meta</button></form>@endif
<div style="overflow:auto"><table><thead><tr><th>Name</th><th>Lang</th><th>Category</th><th>Status</th><th>Params</th><th>Body</th></tr></thead><tbody>
@forelse($tpls as $t)<tr><td>{{ $t->name }}</td><td>{{ $t->language }}</td><td>{{ $t->category }}</td><td>{{ $t->status }}</td><td>{{ $t->params }}</td><td>{{ \Illuminate\Support\Str::limit($t->body, 140) }}</td></tr>@empty<tr><td colspan="6">None. Create templates in Meta Business Manager, then Sync.</td></tr>@endforelse</tbody></table></div></div>
@endif

@if($tab==='campaigns')
@if($can)<form class="card" method="post" action="/admin/whatsapp/campaigns">@csrf <b>New campaign</b>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:8px">
<div><label>Name</label><input name="name" required maxlength="160" value="{{ old('name') }}"></div>
<div><label>Approved template</label><select name="template" required><option value="">Choose…</option>@foreach($tpls->where('status','APPROVED') as $t)<option value="{{ $t->name }}|{{ $t->language }}">{{ $t->name }} ({{ $t->language }}, {{ $t->params }} param)</option>@endforeach</select></div>
<div><label>Audience</label><select name="audience"><option value="recent">Recent customers</option><option value="lapsed">Lapsed customers</option><option value="numbers">Pasted numbers</option></select></div>
<div><label>Min days since last order</label><input type="number" name="min_days" min="0" placeholder="e.g. 90 for lapsed"></div>
<div><label>Max days since last order</label><input type="number" name="max_days" min="1" placeholder="e.g. 30 for recent"></div>
<div style="grid-column:1/-1"><label>Numbers (one per line, optional "number, name")</label><textarea name="numbers" rows="3"></textarea></div>
<div><label>Param 1</label><input name="p[]" placeholder="{name} = customer name"></div><div><label>Param 2</label><input name="p[]"></div><div><label>Param 3</label><input name="p[]"></div></div>
<button type="submit" style="margin-top:10px">Create draft</button></form>@endif
<div class="card" style="overflow:auto"><b>Campaigns</b><table><thead><tr><th>Name</th><th>Template</th><th>Status</th><th>Total</th><th>Sent</th><th>Delivered</th><th>Read</th><th>Failed</th><th></th></tr></thead><tbody>
@forelse($camps as $c)@php $s = $cs[$c->id] ?? []; @endphp<tr><td>{{ $c->name }}<br><small>{{ $c->created_by }}</small></td><td>{{ $c->template_name }}</td><td>{{ $c->status }}</td><td>{{ $c->total }}</td><td>{{ ($s['sent'] ?? 0) }}</td><td>{{ ($s['delivered'] ?? 0) }}</td><td>{{ ($s['read'] ?? 0) }}</td><td>{{ ($s['failed'] ?? 0) }}</td>
<td style="white-space:nowrap">@if($can)@foreach(['start'=>['Start',['draft','paused']],'pause'=>['Pause',['running']],'cancel'=>['Cancel',['draft','paused','running']]] as $a=>[$l,$ok])@if(in_array($c->status,$ok))<form method="post" action="/admin/whatsapp/campaigns/{{ $c->id }}/{{ $a }}" style="display:inline" @if($a==='start') onsubmit="return confirm('Start sending to {{ $c->total }} recipients?')" @endif>@csrf<button class="g" type="submit">{{ $l }}</button></form> @endif @endforeach @endif</td></tr>
@empty<tr><td colspan="9">No campaigns yet.</td></tr>@endforelse</tbody></table></div>
@endif

@if($tab==='activity')
<div class="card" style="overflow:auto"><b>Staff performance (30 days)</b><table><thead><tr><th>Staff</th><th>Replies / templates</th><th>Closed</th></tr></thead><tbody>
@forelse($perf as $p)<tr><td>{{ $p->user_name }}</td><td>{{ $p->replies }}</td><td>{{ $p->closed }}</td></tr>@empty<tr><td colspan="3">No activity.</td></tr>@endforelse</tbody></table></div>
<div class="card" style="overflow:auto"><b>Recent activity</b><table><thead><tr><th>When</th><th>Staff</th><th>Action</th><th>Conversation</th><th>Detail</th></tr></thead><tbody>
@foreach($events as $e)<tr><td>{{ $ago($e->created_at) }}</td><td>{{ $e->user_name }}</td><td>{{ $e->action }}</td><td>@if($e->conversation_id)<a href="/admin/whatsapp?c={{ $e->conversation_id }}&f=all">{{ $e->cname ?: $e->wa_id }}</a>@endif</td><td>{{ $e->meta }}</td></tr>@endforeach</tbody></table></div>
@endif
@endsection
