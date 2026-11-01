@extends('layout')@section('title','SMS Automation')
@section('body')
<h1 style="margin:0 0 12px">SMS Automation</h1>@include('smsmk.nav')
<div class="card" style="font-size:13px;color:#6B7280">Trigger → check customer → fill template → send → saved in the SMS log. Everything is <b>off</b> until you switch it on. Order, payment and delivery SMS are transactional; welcome, win-back and recovery are marketing and respect the opt-out list. Variables: @foreach($vars as $v){{ '{'.$v.'}' }} @endforeach</div>
<form method="post" action="/admin/sms-marketing/automation">@csrf
@foreach($rows->groupBy('grp') as $g => $items)<div class="card"><b>{{ $g }}</b>
@foreach($items as $a)<div style="border-top:1px solid var(--bd);padding:10px 0;margin-top:8px"><label style="display:flex;gap:8px;align-items:center;font-weight:700"><input type="checkbox" name="a{{ $a->id }}_on" value="1" style="width:auto" {{ $a->active ? 'checked' : '' }}> {{ $a->name }}</label>
<textarea name="a{{ $a->id }}_body" rows="2" maxlength="1000" style="margin-top:6px">{{ $a->body }}</textarea>
@if(in_array($a->trigger,['inactive','incomplete_recovery']))<div style="margin-top:6px;font-size:12px">{{ $a->trigger==='inactive' ? 'Days without an order:' : 'Minutes after leaving checkout:' }} <input name="a{{ $a->id }}_param" type="number" min="1" value="{{ $a->param }}" style="width:90px;display:inline-block"></div>@endif</div>@endforeach</div>@endforeach
<button>Save automations</button></form>
<p style="font-size:12px;color:#6B7280;margin-top:10px">Not automated yet: membership expiring and birthday SMS (send these as scheduled campaigns for now).</p>
@endsection
