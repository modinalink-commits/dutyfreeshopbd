@extends('layout')@section('title','Call Review')
@section('body')
@include('cc_ui')
@php($OC=['confirmed'=>'Order confirmed','pending'=>'Pending','call_later'=>'Call later','no_answer'=>'No answer','cancelled'=>'Order cancelled'])
@php($qs=fn($a)=>'?'.http_build_query(array_filter(array_merge(['f'=>$f,'staff'=>$staff ?: null,'page'=>request('page')], $a), fn($v)=>$v!==null && $v!=='')))
<div class="cc-h"><h1>Call Review</h1><span>Listen to answered calls, score them and leave feedback. Scores show in the Agent Scoreboard. Last 14 days.</span></div>
@if(session('ok'))<div class="card" style="background:#ECFDF3;border-color:#ABEFC6">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card err">@foreach($errors->all() as $e){{ $e }}<br>@endforeach</div>@endif
<div class="cc-k"><div><span>Recorded calls, 7 days</span><b>{{ $kpi['recorded'] }}</b></div><div><span>Reviewed, 7 days</span><b>{{ $kpi['reviewed'] }}</b></div><div class="ok"><span>Average score</span><b>{{ $kpi['reviewed'] ? $kpi['avg'].' of 5' : '—' }}</b></div><a class="{{ $kpi['follow'] ? 'bad' : '' }}" href="?f=follow"><span>Needs follow-up with staff</span><b>{{ $kpi['follow'] }}</b></a></div>
<div class="cc-f">@foreach(['suggested'=>'Suggested','cancelled'=>'Cancelled orders','long'=>'Long calls','reviewed'=>'Reviewed','follow'=>'Needs follow-up'] as $k=>$l)<a class="c {{ $f===$k?'on':'' }}" href="{{ $qs(['f'=>$k,'page'=>null,'c'=>null]) }}">{{ $l }}</a>@endforeach
<form method="get" style="display:inline-flex;gap:6px;align-items:center;margin:0"><input type="hidden" name="f" value="{{ $f }}"><select name="staff" onchange="this.form.submit()" style="height:32px;width:auto" aria-label="Staff"><option value="">All staff</option>@foreach($agents as $a)<option value="{{ $a->user_id }}" @selected($staff===(int)$a->user_id)>{{ $a->name }}</option>@endforeach</select></form></div>
<style>.cc-rv{grid-template-columns:minmax(0,3fr) minmax(320px,2fr)!important}@media(max-width:1100px){.cc-rv{grid-template-columns:1fr!important}}.cc-rv .cc-t td small{white-space:nowrap}</style><div class="cc-two cc-rv">
<div class="cc-box"><table class="cc-t">
<tr><th style="width:24%">Customer</th><th style="width:12%">Staff</th><th style="width:19%">Time</th><th class="hm" style="width:9%">Length</th><th>Outcome</th><th style="width:15%">Review</th></tr>
@forelse($rows as $x)<tr class="{{ $cur && $cur->id===$x->id ? 'hot' : '' }}" style="cursor:pointer" onclick="location.href='{{ $qs(['c'=>$x->id]) }}'">
<td><a href="{{ $qs(['c'=>$x->id]) }}" style="font-weight:600;text-decoration:none;color:#0B1B33">{{ $x->customer_name ?: 'Unknown' }}</a><small>{{ $x->phone }}</small></td><td>{{ $x->user_name ?: '—' }}</td>
<td>{{ \Carbon\Carbon::parse($x->started_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</td><td class="hm">{{ gmdate('i:s',(int)$x->duration) }}</td>
<td>{{ $OC[$x->outcome] ?? '—' }}@if(empty($x->recording_url))<small>no recording</small>@endif</td>
<td>@if($x->score!==null)<span class="cc-p {{ $x->score>=4 ? 'ok' : ($x->score<3 ? 'bad' : 'warn') }}">{{ rtrim(rtrim(number_format($x->score,1),'0'),'.') }} of 5</span>@if($x->follow_up)<small>follow-up</small>@endif @else<span style="color:#475467">Not reviewed</span>@endif</td></tr>
@empty<tr><td colspan="6" style="padding:18px;text-align:center;color:#475467">No calls to show here.</td></tr>@endforelse</table>
<div style="padding:10px 14px">{{ $rows->links('pagination.global') }}</div>
<div class="cc-note">"Suggested" puts cancelled orders and long calls first, among calls that nobody has reviewed yet.</div></div>
<div>
<div class="cc-box" style="padding:12px 14px">
@if($cur)
<div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:8px"><b style="font-size:14px">{{ $cur->customer_name ?: 'Unknown' }} · {{ $cur->user_name ?: 'staff' }} · {{ gmdate('i:s',(int)$cur->duration) }}</b>@if($cur->order_id)<a href="/admin/orders/{{ $cur->order_id }}" target="_blank" style="font-weight:600">Open order</a>@endif</div>
@if(!empty($cur->recording_url))<audio controls preload="none" src="/admin/call-center/play/{{ $cur->id }}" style="width:100%"></audio>@else<div style="color:#475467;margin-bottom:6px">This call has no recording. You can still score it from the note and the result.</div>@endif
@if($cur->note)<div style="margin-top:6px;font-size:12.5px;color:#475467"><b style="color:#0B1B33">Call note:</b> {{ $cur->note }}</div>@endif
<form method="post" action="/admin/call-center/review/{{ $cur->id }}{{ $qs(['c'=>$cur->id]) }}" style="margin-top:10px;display:grid;gap:8px">@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:6px">
@foreach(['s_manners'=>'Greeting and manners','s_info'=>'Correct information','s_save'=>'Tried to save the order','s_note'=>'Note written correctly'] as $k=>$l)<label style="display:flex;align-items:center;justify-content:space-between;gap:8px;border:1px solid #E5E7EB;border-radius:8px;padding:6px 10px;margin:0">{{ $l }}<select name="{{ $k }}" required style="width:64px;height:30px" aria-label="{{ $l }}">@foreach([5,4,3,2,1] as $n)<option value="{{ $n }}" @selected((int)old($k, $rv->$k ?? 5)===$n)>{{ $n }}</option>@endforeach</select></label>@endforeach</div>
<label style="margin:0;font-size:12px;color:#475467">Feedback for the staff member<textarea name="feedback" rows="2" maxlength="1500" placeholder="What was good, what to improve next time">{{ old('feedback', $rv->feedback ?? '') }}</textarea></label>
<label style="display:flex;align-items:center;gap:8px;margin:0"><input type="checkbox" name="follow_up" value="1" style="width:auto" @checked(old('follow_up', $rv->follow_up ?? false))> Needs follow-up with this staff member</label>
<div style="display:flex;gap:8px;align-items:center"><button class="btn" type="submit">Save review</button>@if($rv)<span style="font-size:12px;color:#475467">Reviewed by {{ $rv->reviewer_name }}, {{ \Carbon\Carbon::parse($rv->updated_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</span>@endif</div></form>
@else<div style="color:#475467;padding:10px 0">Pick a call from the list to listen and score it.</div>@endif
</div>
<div class="cc-box"><b class="t">Listening log</b>
@forelse($listens as $l)<div style="display:flex;justify-content:space-between;gap:8px;padding:7px 14px;border-top:1px solid #F2F4F7"><span style="min-width:0;overflow-wrap:anywhere"><b>{{ $l->user_name ?: 'Someone' }}</b> listened to {{ $l->customer_name ?: $l->phone }}{{ $l->agent ? ', call by '.$l->agent : '' }}</span><span style="color:#475467;white-space:nowrap">{{ \Carbon\Carbon::parse($l->created_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</span></div>
@empty<div style="padding:8px 14px;color:#475467;border-top:1px solid #F2F4F7">Nobody has played a recording from here yet.</div>@endforelse
<div class="cc-note">Every recording played from Call Review or Call History is written here.</div></div>
</div></div>
@endsection
