@extends('layout')@section('title','Marine Access Requests')
@section('body')
<div style="margin-bottom:10px"><h1 style="margin:0">Marine Access Requests</h1><div style="font-size:12px;color:#6B7280">মেরিন সাইটের Request Access ফর্ম থেকে আসা কোম্পানি ও নাবিকের আবেদন। অনুমোদনের আগে কোম্পানি, প্রতিনিধি ও IMO নম্বর যাচাই করুন।</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
<div style="margin-bottom:10px">@foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$l)<a class="btn {{ $st===$k ? '' : 'g' }}" href="/admin/marine/requests?status={{ $k }}">{{ $l }} ({{ $n[$k] ?? 0 }})</a> @endforeach</div>
<div class="card" style="overflow:auto"><table style="width:100%;min-width:900px;font-size:13px;border-collapse:collapse"><thead><tr style="text-align:left;color:#6B7280"><th style="padding:6px 4px">Applicant</th><th>Vessel</th><th>Contact</th><th>Message</th><th>Received</th><th></th></tr></thead><tbody>
@forelse($rows as $x)<tr style="border-top:1px solid #EEE;vertical-align:top"><td style="padding:8px 4px"><b>{{ $x->company ?: $x->name ?: '-' }}</b><div style="font-size:11.5px;color:#6B7280">{{ ucfirst($x->kind) }}{{ $x->role ? ' · '.$x->role : '' }}{{ $x->crew_rank ? ' · '.$x->crew_rank : '' }}{{ ($x->company && $x->name) ? ' · '.$x->name : '' }}</div></td>
<td>{{ $x->vessel ?: '-' }}<div style="font-size:11.5px;color:#6B7280">{{ $x->imo ? 'IMO '.$x->imo : 'IMO নেই' }}{{ $x->port ? ' · '.$x->port : '' }}</div></td>
<td>{{ $x->email }}<div style="font-size:11.5px;color:#6B7280">{{ $x->phone }}</div></td>
<td style="max-width:240px">{{ $x->message }}@if($x->staff_note)<div style="font-size:11.5px;color:#6B7280">Staff: {{ $x->staff_note }}</div>@endif</td>
<td style="white-space:nowrap">{{ \Carbon\Carbon::parse($x->created_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</td>
<td>@if($x->status==='pending')<form method="post" action="/admin/marine/requests/{{ $x->id }}" style="display:flex;gap:6px;flex-wrap:wrap">@csrf<input name="note" placeholder="নোট (ঐচ্ছিক)" style="width:140px"><button name="action" value="approve">Approve</button><button name="action" value="reject" class="btn g">Reject</button></form>@else{{ ucfirst($x->status) }} · {{ $x->decided_by }}@endif</td></tr>
@empty<tr><td colspan="6" style="padding:14px;color:#6B7280">কোনো আবেদন নেই।</td></tr>@endforelse</tbody></table></div>
<div style="margin-top:10px">{{ $rows->links('pagination.global') }}</div>
@endsection
