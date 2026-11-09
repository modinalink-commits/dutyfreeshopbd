@extends('layout')@section('title','Command Board')
@section('body')
@php $B = ['pending'=>['#FEF3C7','#92400E','Pending'],'working'=>['#DBEAFE','#1E40AF','Working'],'done'=>['#DCFCE7','#166534','✔ Completed by Claude']]; @endphp
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Command Board</h1><div style="font-size:12px;color:#6B7280">Add commands one after another — each one shows <b>Completed</b> only when Claude has finished it</div></div></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif @if($errors->any())<div class="card" style="color:#B91C1C">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="card"><h2>New command</h2><form method="post" action="/admin/commands">@csrf
<div style="display:grid;grid-template-columns:2fr 1fr;gap:10px"><div><label>Command</label><input name="title" maxlength="200" placeholder="যা করতে হবে — এক লাইনে" required></div><div><label>Priority</label><select name="priority"><option value="normal">Normal</option><option value="high">High</option></select></div></div>
<label>Details (optional)</label><textarea name="details" rows="3" placeholder="বিস্তারিত, উদাহরণ, কোন পেজ/কাস্টমার..."></textarea><p><button>Add to queue</button></p></form></div>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin:6px 0;font-size:12.5px">@foreach([[''=>'All'],['pending'=>'Pending ('.$n->p.')'],['working'=>'Working ('.$n->w.')'],['done'=>'Completed ('.$n->d.')']] as $o)@foreach($o as $k=>$l)<a href="?s={{ $k }}" style="{{ $f===(string)$k ? 'font-weight:700;color:#FF5A00' : '' }}">{{ $l }}</a>@endforeach @endforeach</div>
@forelse($rows as $c)@php $b=$B[$c->status]; @endphp
<div class="card" style="border-left:4px solid {{ $b[1] }}"><div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center"><b style="flex:1;font-size:14px">#{{ $c->id }} · {{ $c->title }}@if($c->priority==='high') <span style="color:#B91C1C;font-size:11px">● HIGH</span>@endif</b>
<span style="font-size:11px;font-weight:700;border-radius:999px;padding:3px 10px;background:{{ $b[0] }};color:{{ $b[1] }}">{{ $b[2] }}</span></div>
@if($c->details)<div style="font-size:12.5px;white-space:pre-line;margin-top:6px">{{ $c->details }}</div>@endif
<div style="font-size:11.5px;color:#6B7280;margin-top:6px">Added {{ \Carbon\Carbon::parse($c->created_at,'UTC')->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}{{ $c->created_by_name ? ' by '.$c->created_by_name : '' }}@if($c->done_at) · Completed {{ \Carbon\Carbon::parse($c->done_at,'UTC')->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}@endif</div>
@if($c->result_note)<div style="font-size:12.5px;background:#DCFCE7;border-radius:8px;padding:8px 10px;margin-top:6px;white-space:pre-line"><b>Claude:</b> {{ $c->result_note }}</div>@endif
@if($c->status==='pending')<details style="margin-top:6px"><summary style="cursor:pointer;font-size:12px">Edit / remove</summary><form method="post" action="/admin/commands/{{ $c->id }}/update">@csrf<input name="title" value="{{ $c->title }}" maxlength="200"><textarea name="details" rows="2">{{ $c->details }}</textarea><select name="priority"><option value="normal" @selected($c->priority==='normal')>Normal</option><option value="high" @selected($c->priority==='high')>High</option></select> <button>Save</button></form><form method="post" action="/admin/commands/{{ $c->id }}/delete" onsubmit="return confirm('Remove this command?')" style="margin-top:4px">@csrf<button class="g">Remove</button></form></details>@endif
</div>
@empty<div class="card" style="color:#6B7280">No commands here yet.</div>@endforelse
@endsection
