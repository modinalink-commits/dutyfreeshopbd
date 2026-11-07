@extends('layout')@section('title','MCP Server')
@section('body')
<h1 style="margin:0 0 4px">MCP Server (ChatGPT / AI connection)</h1>
<p style="color:#6B7280;margin:0 0 12px;max-width:900px">Phase 1: read-only business tools. No raw database access, no passwords or secrets are returned, no write actions. Every call is token-checked, rate-limited (60/min per token) and logged below.</p>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif @if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
@if(session('newtoken'))<div class="card" style="border:2px solid #16A34A"><b>New token (copy now, shown once):</b><br><code style="font-size:14px;word-break:break-all;user-select:all">{{ session('newtoken') }}</code></div>@endif
<div class="card"><b>Status:</b> <span style="font-weight:800;color:{{ $on ? '#166534' : '#B91C1C' }}">{{ $on ? 'ENABLED' : 'DISABLED (kill switch off)' }}</span>
<form method="post" action="/admin/mcp/toggle" style="display:inline;margin-left:12px">@csrf<input type="hidden" name="on" value="{{ $on ? 0 : 1 }}"><button type="submit" class="{{ $on ? 'g' : '' }}">{{ $on ? 'Turn OFF now' : 'Turn ON' }}</button></form>
<div style="margin-top:8px">Endpoint: <code>{{ $url }}</code> · Health: <code>{{ $url }}/health</code><br><small>ChatGPT → Plugins → Add custom MCP server → URL above → Authentication: Bearer token (create one below).</small></div></div>
<form method="post" action="/admin/mcp/tokens" class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">@csrf
<div><label>Token name (who/what uses it)</label><input name="name" required placeholder="ChatGPT workspace"></div><div><label>Expires after (days, blank = never)</label><input name="days" type="number" min="1" max="365" value="90"></div><button type="submit">Create read-only token</button></form>
<div class="card" style="overflow:auto"><b>Tokens</b><table><thead><tr><th>Name</th><th>Token</th><th>Scope</th><th>Expires</th><th>Last used</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($tokens as $t)<tr><td>{{ $t->name }}</td><td><code>{{ $t->token_hint }}</code></td><td>{{ $t->scope }}</td><td>{{ $t->expires_at ?: 'never' }}</td><td>{{ $t->last_used_at ?: '—' }}</td><td>{{ $t->revoked_at ? 'Revoked' : 'Active' }}</td>
<td>@if(!$t->revoked_at)<form method="post" action="/admin/mcp/tokens/{{ $t->id }}/revoke" onsubmit="return confirm('Revoke this token?')">@csrf<button class="g" type="submit">Revoke</button></form>@endif</td></tr>
@empty<tr><td colspan="7">No tokens yet.</td></tr>@endforelse</tbody></table></div>
<div class="card"><b>Read tools ({{ count($tools) }})</b><br><small>{{ implode(' · ', $tools) }}</small></div>
<div class="card" style="overflow:auto"><b>Call log (last 60)</b><table><thead><tr><th>Time</th><th>Client</th><th>Tool</th><th>Status</th><th>ms</th><th>IP</th><th>Request</th></tr></thead><tbody>
@forelse($log as $l)<tr><td>{{ $l->created_at }}</td><td>{{ $l->client ?: '—' }}</td><td>{{ $l->tool ?: '—' }}</td><td>{{ $l->status }}{{ $l->error ? ' · '.$l->error : '' }}</td><td>{{ $l->ms }}</td><td>{{ $l->ip }}</td><td><small>{{ $l->request_id }}</small></td></tr>
@empty<tr><td colspan="7">No calls yet.</td></tr>@endforelse</tbody></table></div>
@endsection
