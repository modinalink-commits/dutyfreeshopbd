@extends('layout')@section('title','Purchase settings')
@section('body')
<h1 style="margin:0 0 10px">Purchase Settings</h1>@include('proc.nav')
<form class="card" method="post" action="/admin/purchase/settings">@csrf
<b>Automatic supplier SMS</b><p class="mut" style="margin:4px 0 8px">Each automation can be switched on or off. Payment SMS is on by default.</p>
@foreach(['purchase'=>'Purchase created','payment'=>'Payment made (with remaining due)','due'=>'Due reminder (daily check)','return'=>'Purchase return','damage'=>'Damage claim','statement'=>'Statement generated'] as $k=>$l)
<label style="display:flex;gap:8px;align-items:center;font-size:14px;color:var(--n)"><input type="checkbox" style="width:auto" name="proc_sms_{{ $k }}" value="1" @checked($v('proc_sms_'.$k,'0')==='1')> {{ $l }}</label>@endforeach
<label>Due reminder days relative to invoice due date (comma separated; -3 = 3 days before, 0 = due date, 3 = 3 days after)</label><input name="proc_due_offsets" value="{{ $v('proc_due_offsets','-3,0,3') }}">
<hr style="border:0;border-top:1px solid #FFE7D6;margin:16px 0"><b>Approval workflow</b>
<label style="display:flex;gap:8px;align-items:center;font-size:14px;color:var(--n)"><input type="checkbox" style="width:auto" name="proc_approval" value="1" @checked($v('proc_approval','0')==='1')> Require admin approval for large purchases and payments</label>
<div class="fg"><div><label>Purchase approval needed from (৳)</label><input type="number" name="proc_approval_limit" value="{{ $v('proc_approval_limit','100000') }}"></div><div><label>Payment approval needed from (৳)</label><input type="number" name="proc_pay_approval_limit" value="{{ $v('proc_pay_approval_limit','100000') }}"></div></div>
<p class="mut">Only Super/Admin roles can approve. Stock is added when a purchase is approved.</p>
<hr style="border:0;border-top:1px solid #FFE7D6;margin:16px 0"><b>SMS wording</b><p class="mut">Variables: {supplier_name} {invoice_no} {invoice_total} {amount} {due} {date} {invoice_link} {statement_link}</p>
@foreach($tpl as $k=>$d)<label>{{ ucfirst($k) }}</label><textarea name="tpl_{{ $k }}" rows="2">{{ $v('tpl_'.$k) ?: $d }}</textarea>@endforeach
<div style="margin-top:14px"><button>Save settings</button></div></form>
@endsection
