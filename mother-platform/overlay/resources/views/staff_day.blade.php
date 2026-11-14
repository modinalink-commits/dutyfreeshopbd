@extends('layout')@section('title','My Day')
@section('body')
@include('cc_ui')
@php
  $dur = fn($s) => $s === null ? '—' : ($s < 60 ? 'under 1 min' : ($s < 3600 ? round($s/60).' min' : floor($s/3600).' h '.round(($s%3600)/60).' min'));
  $t = $today;
@endphp
<style>.md .cc-k{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-bottom:12px}.md .cc-k>div{background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:10px 12px}.md .cc-k span{display:block;font-size:12px;color:#475467}.md .cc-k b{font-size:22px;color:#0B1B33}.md .cc-k .ok b{color:#067647}.md .cc-k .bad{border-color:#F6C4C0}.md .cc-k .bad b{color:#B42318}
.md .sc{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}.md .sc>div{flex:1 1 260px;border-radius:12px;padding:12px 14px;border:1px solid}.md .sc .g{background:#ECFDF3;border-color:#ABEFC6;color:#067647}.md .sc .b{background:#FEF3F2;border-color:#F6C4C0;color:#B42318}.md .sc b{font-size:28px;display:block}.md .sc span{font-weight:600}
.md .rl{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px 12px;align-items:center;padding:8px 0;border-top:1px solid #F2F4F7;font-size:13px}.md .rl:first-of-type{border-top:0}.md .rl small{display:block;color:#475467;font-size:12px}
.md h2{font-size:14px;margin:0 0 8px;color:#0B1B33}</style>
<div class="md">
<div class="cc-h"><h1>My Day{{ $u->id !== $me->id ? ' · '.$u->name : '' }}</h1><span>Your own work today, from the same records the reports use. {{ now('Asia/Dhaka')->format('D, d M Y') }}.</span>
@if($admin && $staff->isNotEmpty())<form method="get" style="margin-left:auto"><select name="u" onchange="this.form.submit()" style="height:32px;border-radius:8px;border:1px solid #D0D5DD;padding:0 8px"><option value="">My own page</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected($u->id===$s->id && $u->id!==$me->id)>{{ $s->name }}</option>@endforeach</select></form>@endif</div>

<div class="sc"><div class="g"><b>{{ $t['good'] }}</b><span>Done right today</span><div style="font-size:12.5px;margin-top:2px;color:#065F46">{{ $t['chats'] }} customers answered · {{ $t['calls'] }} calls answered · {{ $t['approved'] }} orders confirmed</div></div>
<div class="b"><b>{{ $t['bad'] }}</b><span>Slips today</span><div style="font-size:12.5px;margin-top:2px;color:#912018">{{ $t['late'] }} late replies · {{ $t['idle'] }} idle notices · {{ $t['missed'] }} missed calls</div></div></div>

<div class="cc-k"><div class="ok"><span>Customers answered in chat</span><b>{{ $t['chats'] }}</b></div><div><span>Replies sent</span><b>{{ $t['replies'] }}</b></div><div><span>Typical wait before your reply</span><b style="font-size:17px">{{ $dur($t['reply_s']) }}</b></div><div class="ok"><span>Calls answered</span><b>{{ $t['calls'] }}</b></div><div class="ok"><span>Orders confirmed by call</span><b>{{ $t['call_ok'] }}</b></div><div><span>Orders worked on</span><b>{{ $t['orders'] }}</b></div><div class="ok"><span>Orders moved to Approved</span><b>{{ $t['approved'] }}</b></div></div>

<div class="cc-two">
<div class="cc-box" data-nogrid data-no-export style="padding:12px 14px"><h2>The rules, and how you did today</h2>
<div class="rl"><div>Answer every customer within {{ $limit }} minutes<small>After {{ $limit }} minutes the admin is alerted and the AI steps in.</small></div><span class="cc-p {{ $t['late'] ? 'bad' : 'ok' }}">{{ $t['late'] ? $t['late'].' late' : 'Kept' }}</span></div>
<div class="rl"><div>Do not stay idle while chats or orders are waiting<small>An idle notice is written when work waits and nothing is done.</small></div><span class="cc-p {{ $t['idle'] ? 'bad' : 'ok' }}">{{ $t['idle'] ? $t['idle'].' notices' : 'Kept' }}</span></div>
<div class="rl"><div>Pick up every incoming call<small>A missed call must be called back the same day.</small></div><span class="cc-p {{ $t['missed'] ? 'bad' : 'ok' }}">{{ $t['missed'] ? $t['missed'].' missed' : 'Kept' }}</span></div>
<div class="rl"><div>Never promise a price, stock, refund or delivery date that the system does not show<small>Checked by the admin in Call Review and in chats.</small></div><span class="cc-p">By review</span></div>
</div>
<div class="cc-box" data-nogrid data-no-export style="padding:12px 14px"><h2>Today's slips</h2>
@forelse($slips as $s)<div class="rl"><div><b>{{ $s['what'] }}</b> · {{ \Carbon\Carbon::parse($s['at'], 'UTC')->timezone('Asia/Dhaka')->format('h:i A') }}<small>{{ $s['detail'] }}</small></div>@if($s['href'])<a href="{{ $s['href'] }}" style="font-weight:600;font-size:12.5px">Open</a>@else<span></span>@endif</div>
@empty<div style="color:#067647;font-weight:600;padding:6px 0">No slips today. Keep it up.</div>@endforelse
</div></div>

<div class="cc-box" data-nogrid data-no-export style="margin-top:12px"><table class="cc-t no-export"><thead><tr><th style="width:18%">Day</th><th>Customers answered</th><th>Calls answered</th><th>Orders confirmed</th><th>Typical reply wait</th><th>Late replies</th><th>Idle notices</th><th>Missed calls</th></tr></thead><tbody>
<tr class="hot"><td><b>Today</b></td><td class="cc-num">{{ $t['chats'] }}</td><td class="cc-num">{{ $t['calls'] }}</td><td class="cc-num">{{ $t['approved'] }}</td><td>{{ $dur($t['reply_s']) }}</td><td>{{ $t['late'] }}</td><td>{{ $t['idle'] }}</td><td>{{ $t['missed'] }}</td></tr>
@foreach($days as $d)<tr><td>{{ $d['d'] }}</td><td class="cc-num">{{ $d['chats'] }}</td><td class="cc-num">{{ $d['calls'] }}</td><td class="cc-num">{{ $d['approved'] }}</td><td>{{ $dur($d['reply_s']) }}</td><td>{{ $d['late'] }}</td><td>{{ $d['idle'] }}</td><td>{{ $d['missed'] }}</td></tr>@endforeach
</tbody></table></div>
</div>
@endsection
