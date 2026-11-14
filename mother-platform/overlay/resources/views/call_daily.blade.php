<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Daily Call Report · {{ $d['date'] }}</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#F4F5F7;color:#0B1B33;font:13.5px/1.45 Inter,system-ui,-apple-system,Segoe UI,sans-serif}
.w{max-width:560px;margin:0 auto;padding:14px;display:flex;flex-direction:column;gap:10px}
h1{margin:0;font-size:17px}.s{color:#475467;font-size:12px}
.k{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.k div,.b{background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:9px 11px}.k span{display:block;font-size:11.5px;color:#475467}.k b{font-size:19px}.k .ok b{color:#067647}.k .bad{border-color:#FECDCA}.k .bad span,.k .bad b{color:#B42318}
.b h2{margin:0 0 6px;font-size:13.5px}table{width:100%;border-collapse:collapse;table-layout:fixed}th{font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#475467;text-align:left;padding:5px 4px;border-bottom:1px solid #E5E7EB}td{padding:7px 4px;border-top:1px solid #F2F4F7;overflow-wrap:anywhere}
.r{display:flex;justify-content:space-between;gap:8px;padding:4px 0}.r b.bad{color:#B42318}.r b.warn{color:#9A3412}
</style></head><body><div class="w">
@php($fm=fn($s)=>$s>=3600?floor($s/3600).'h '.floor($s%3600/60).'m':floor($s/60).'m')
<div><h1>Daily Call Report</h1><div class="s">DutyFreeBD · {{ $d['made'] }} · internal use only</div></div>
<div class="k"><div><span>Calls</span><b>{{ $d['calls'] }}</b></div><div class="ok"><span>Confirmed by call</span><b>{{ $d['confirmed'] }}</b></div><div class="{{ $d['left']['missed'] ? 'bad' : '' }}"><span>Missed, not called back</span><b>{{ $d['left']['missed'] }}</b></div>
<div><span>Answered</span><b>{{ $d['answered'] }}</b></div><div><span>Talk time</span><b>{{ $fm($d['talk']) }}</b></div><div><span>Cancelled on call</span><b>{{ $d['cancelled'] }}</b></div></div>
<div class="b"><h2>By staff</h2>
@if($d['staff'])<table><tr><th style="width:30%">Staff</th><th>Calls</th><th>Answered</th><th>Confirmed</th><th>Talk</th><th>Idle notices</th></tr>
@foreach($d['staff'] as $s)<tr><td><b>{{ $s['name'] }}</b></td><td>{{ $s['calls'] }}</td><td>{{ $s['answered'] }}</td><td><b style="color:#067647">{{ $s['confirmed'] }}</b></td><td>{{ $fm($s['talk']) }}</td><td>@if($s['idle_n'])<b style="color:#B42318">{{ $s['idle_n'] }} ({{ $s['idle_m'] }} min)</b>@else—@endif</td></tr>@endforeach</table>
@else<div class="s">No calls were made or received today.</div>@endif</div>
<div class="b"><h2>Left for tomorrow</h2>
<div class="r"><span>New orders nobody has called</span><b class="{{ $d['left']['new'] ? 'warn' : '' }}">{{ $d['left']['new'] }}</b></div>
<div class="r"><span>Callbacks and unanswered calls to retry</span><b class="{{ $d['left']['callbacks'] ? 'warn' : '' }}">{{ $d['left']['callbacks'] }}</b></div>
<div class="r"><span>Missed calls not returned</span><b class="{{ $d['left']['missed'] ? 'bad' : '' }}">{{ $d['left']['missed'] }}</b></div>
<div class="r"><span>Chats still waiting for a person</span><b>{{ $d['left']['chats'] }}</b></div></div>
<div class="b"><h2>Call quality</h2>
<div class="r"><span>Calls reviewed today</span><b>{{ $d['review']['n'] }}</b></div>
<div class="r"><span>Average review score</span><b>{{ $d['review']['avg'] !== null ? $d['review']['avg'].' of 5' : '—' }}</b></div></div>
<div class="s" style="text-align:center">This link stops working four days after it was sent.</div>
</div></body></html>
