@extends('layout')@section('title','Access Management')
@section('body')
@php
$bst = fn($t) => $t ? \Carbon\Carbon::parse($t)->timezone('Asia/Dhaka')->format('d M Y, H:i') : '-';
$what = ['owner' => 'Company details, authorized representative, company documents and the IMO list of its vessels. Sees its own fleet\'s requests, USD quotations and purchase orders.',
 'agency' => 'Agency details, authorized port and proof of appointment for each vessel. Sees port calls, documents and deliveries of the vessels it is appointed for.',
 'vessel' => 'Vessel name, IMO, owner company and the captain or authorized officer. Sees its own vessel\'s supply requisitions and order status; crew members may place personal orders.'];
$sl = ['invite' => 'Invitation', 'resend' => 'Invitation (resent)', 'documents' => 'Documents request', 'reset' => 'Password reset link'];
@endphp
<div style="margin-bottom:10px"><h1 style="margin:0">Access Management</h1><div style="font-size:12px;color:#6B7280">Verified accounts for the marine site. Each approved person gets their own username and sets their own password by email. Times are Bangladesh time.</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">@foreach($types as $k => $l)<a class="btn {{ $type === $k ? '' : 'g' }}" href="/admin/marine/access/{{ $k }}">{{ $l }}@if(($tabs[$k] ?? 0) > 0) <span style="font-weight:700">· {{ $tabs[$k] }} to review</span>@endif</a>@endforeach</div>
@if(!$ready)<div class="card" style="background:#FEF3C7;border-color:#FDE68A;color:#713F12">The account tables are not ready yet. Open this page again when the deploy has finished.</div>
@else
<div class="card" style="background:#F8FAFC;font-size:12px;color:#475569"><b style="color:#0C161F">{{ $types[$type] }}:</b> {{ $what[$type] }}</div>
@if(!$mailReady || $blocked)<div class="card" style="background:#FEF3C7;border-color:#FDE68A;color:#713F12;font-size:13px">Email sending is {{ !$mailReady ? 'not set up (no mailbox password saved)' : 'blocked by the mail provider since ' . $blocked }}. Invitations and codes cannot reach applicants until this is fixed.</div>@endif
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">@foreach(['review' => 'Under Review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l)<a href="/admin/marine/access/{{ $type }}?status={{ $k }}" style="font-size:12px;padding:5px 12px;border-radius:999px;text-decoration:none;border:1px solid {{ $st === $k ? '#0C161F' : '#D1D5DB' }};background:{{ $st === $k ? '#0C161F' : '#FFFFFF' }};color:{{ $st === $k ? '#FFFFFF' : '#0C161F' }}">{{ $l }} ({{ $n[$k] ?? 0 }})</a>@endforeach</div>
<div class="card" style="padding:0;overflow:auto"><table style="width:100%;min-width:1040px;font-size:13px;border-collapse:collapse">
<thead><tr style="text-align:left;color:#6B7280;font-size:11px;text-transform:uppercase"><th style="padding:8px 10px">{{ $type === 'vessel' ? 'Vessel and person' : ($type === 'agency' ? 'Agency and representative' : 'Company and representative') }}</th><th style="padding:8px 10px">{{ $type === 'agency' ? 'Port and vessels' : 'Vessel / IMO' }}</th><th style="padding:8px 10px">Contact</th><th style="padding:8px 10px">Documents</th><th style="padding:8px 10px">Status and record</th><th style="padding:8px 10px;width:250px">Action</th></tr></thead><tbody>
@forelse($rows as $x)
@php $a = $acc[$x->id] ?? null; $ds = $docs[$x->id] ?? collect(); $ms = $mail[$x->id] ?? collect(); $vs = $a ? ($ves[$a->id] ?? collect()) : collect(); @endphp
<tr style="border-top:1px solid #E5E7EB;vertical-align:top">
<td style="padding:10px"><b>{{ $type === 'vessel' ? ($x->vessel ?: $x->name ?: '-') : ($x->company ?: $x->name ?: '-') }}</b>
<div style="font-size:11.5px;color:#6B7280">{{ $x->name }}{{ $x->role ? ' · ' . $x->role : '' }}{{ $x->crew_rank ? ' · ' . $x->crew_rank : '' }}</div>
@if($type === 'vessel')<div style="font-size:11.5px;color:#0C161F;font-weight:600">{{ $vroles[$x->vessel_role ?? 'crew'] ?? 'Crew Member' }}</div>@if($x->company)<div style="font-size:11.5px;color:#6B7280">Owner: {{ $x->company }}</div>@endif @endif
@if($a)<div style="font-size:11.5px;margin-top:4px">Username <b>{{ $a->username }}</b></div>@endif</td>
<td style="padding:10px">@if($type !== 'agency'){{ $x->vessel ?: '-' }}<div style="font-size:11.5px;color:#6B7280">{{ $x->imo ? 'IMO ' . $x->imo : 'No IMO' }}{{ $x->port ? ' · ' . $x->port : '' }}</div>@else{{ $x->port ?: '-' }}@endif
@foreach($vs as $v)<div style="font-size:11.5px;color:#374151">IMO {{ $v->imo }}{{ $v->name ? ' · ' . $v->name : '' }}{{ $v->note ? ' · ' . $v->note : '' }}</div>@endforeach</td>
<td style="padding:10px">{{ $x->email }}<div style="font-size:11.5px;color:#6B7280">{{ $x->phone }}</div>@if($x->message)<div style="font-size:11.5px;color:#374151;max-width:220px;margin-top:4px">{{ $x->message }}</div>@endif</td>
<td style="padding:10px;max-width:190px">@forelse($ds as $d)<div style="font-size:12px"><a href="/admin/marine/access/doc/{{ $d->id }}">{{ \Illuminate\Support\Str::limit($d->name, 28) }}</a> <span style="color:#6B7280">{{ number_format($d->size / 1024) }} KB</span></div>@empty<span style="color:#B45309;font-size:12px">No document sent</span>@endforelse</td>
<td style="padding:10px;font-size:12px">
@if($x->status === 'pending')<b style="color:#B45309">Under Review</b>@elseif($x->status === 'docs')<b style="color:#B45309">Documents requested</b><div style="color:#6B7280">{{ $bst($x->docs_requested_at) }}</div><div style="color:#374151;max-width:220px">{{ $x->docs_note }}</div>
@elseif($x->status === 'rejected')<b style="color:#991B1B">Rejected</b><div style="color:#6B7280">{{ $x->decided_by }} · {{ $bst($x->decided_at) }}</div>
@else<b style="color:{{ $a && $a->status === 'active' ? '#166534' : ($a && $a->status === 'suspended' ? '#991B1B' : '#1D4ED8') }}">{{ !$a ? 'Approved, no account yet' : ($a->status === 'active' ? 'Active' : ($a->status === 'suspended' ? 'Suspended' : 'Invited, not activated')) }}</b>
<div style="color:#6B7280">Approved by {{ $a->approved_by ?? $x->decided_by }} · {{ $bst($a->approved_at ?? $x->decided_at) }}</div>@if($a && $a->activated_at)<div style="color:#6B7280">Activated {{ $bst($a->activated_at) }}</div>@endif @if($a && $a->last_login_at)<div style="color:#6B7280">Last sign-in {{ $bst($a->last_login_at) }}</div>@endif @if($a && ($mcr ?? false) && $a->bonded_ok)<div style="color:#B45309;font-weight:600">Bonded Stores approved · {{ $a->bonded_by }}</div>@endif @endif
@if($x->staff_note)<div style="color:#6B7280">Note: {{ $x->staff_note }}</div>@endif
@foreach($ms->take(3) as $m)<div style="color:{{ $m->ok ? '#6B7280' : '#991B1B' }}">{{ $sl[$m->kind] ?? ucfirst($m->kind) }}: {{ $m->ok ? 'emailed' : 'NOT sent' }} {{ $bst($m->created_at) }} · {{ $m->sent_by }}@if(!$m->ok)<br>{{ \Illuminate\Support\Str::limit($m->error, 120) }}@endif</div>@endforeach</td>
<td style="padding:10px">
@if(in_array($x->status, ['pending', 'docs'], true))
<form method="post" action="/admin/marine/access/{{ $x->id }}" style="display:flex;flex-direction:column;gap:6px;margin:0">@csrf<input type="hidden" name="st" value="{{ $st }}">
<textarea name="note" rows="2" maxlength="600" placeholder="What is missing (for Request Documents), or a note" style="font-size:12px;min-height:44px"></textarea>
<div style="display:flex;gap:6px;flex-wrap:wrap"><button name="action" value="approve" class="btn" style="padding:6px 10px;font-size:12px" onclick="return confirm('Approve and email the invitation to this applicant?')">Approve &amp; Send Invite</button><button name="action" value="docs" class="btn g" style="padding:6px 10px;font-size:12px">Request Documents</button><button name="action" value="reject" class="btn g" style="padding:6px 10px;font-size:12px;color:#991B1B" onclick="return confirm('Reject this application?')">Reject</button></div></form>
@elseif($x->status === 'approved')
<form method="post" action="/admin/marine/access/{{ $x->id }}" style="display:flex;gap:6px;flex-wrap:wrap;margin:0">@csrf<input type="hidden" name="st" value="{{ $st }}">
@if(!$a)<button name="action" value="approve" class="btn" style="padding:6px 10px;font-size:12px" onclick="return confirm('Create the account and email the invitation to this applicant?')">Send Invite</button>
@else
@if($a->status === 'invited')<button name="action" value="resend" class="btn" style="padding:6px 10px;font-size:12px" onclick="return confirm('Send a new invitation? The earlier link and code stop working.')">Resend Invite</button>@endif
@if(($mcr ?? false) && ($adm ?? false) && $a->status === 'active')<button name="action" value="{{ $a->bonded_ok ? 'bonded_off' : 'bonded_on' }}" class="btn g" style="padding:6px 10px;font-size:12px">{{ $a->bonded_ok ? 'Remove bonded approval' : 'Approve Bonded Stores' }}</button>@endif
@if($a->status !== 'suspended')<button name="action" value="suspend" class="btn g" style="padding:6px 10px;font-size:12px;color:#991B1B" onclick="return confirm('Suspend this account and sign it out?')">Suspend</button>@else<button name="action" value="reactivate" class="btn g" style="padding:6px 10px;font-size:12px">Reactivate</button>@endif
@endif</form>
@if($a && $type !== 'vessel')<form method="post" action="/admin/marine/access/{{ $x->id }}" style="display:flex;gap:4px;flex-wrap:wrap;margin:8px 0 0">@csrf<input type="hidden" name="st" value="{{ $st }}"><input type="hidden" name="action" value="imo"><input name="imo" placeholder="IMO" inputmode="numeric" maxlength="7" style="width:76px;font-size:12px"><input name="vessel" placeholder="Vessel name" maxlength="120" style="width:110px;font-size:12px"><button class="btn g" style="padding:4px 8px;font-size:12px">{{ $type === 'owner' ? 'Add to fleet' : 'Link vessel' }}</button></form>@endif
@endif</td></tr>
@empty<tr><td colspan="6" style="padding:16px 10px;color:#6B7280">{{ $st === 'review' ? 'Nothing waiting for review.' : 'None.' }}</td></tr>@endforelse</tbody></table></div>
<div style="margin-top:10px">{{ $rows->links('pagination.global') }}</div>
<div style="font-size:12px;color:#6B7280;margin-top:8px">Activation: the invitation email carries the username and a single-use Set Password link (72 hours). On that page the person asks for a 6-digit code, sent to the same email (10 minutes), then sets their own password. Only then the account is Active. Staff never see or set a password.</div>
@endif
@endsection
