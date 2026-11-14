@php $m = fn($v) => $v === null ? '—' : ($v < 1 ? 'just now' : ($v < 60 ? $v.' min ago' : floor($v/60).' h '.($v%60).' min ago')); $du = fn($v) => $v < 60 ? $v.' min' : floor($v/60).' h '.($v%60).' min';
$col = ['Online'=>['#DCFCE7','#166534'],'Away'=>['#FEF3C7','#B45309'],'On break'=>['#E0F2FE','#075985'],'Offline'=>['#F3F4F6','#6B7280']]; @endphp
<table class="no-export" style="width:100%"><thead><tr><th>Staff</th><th>Status</th><th>On page</th><th>Last real action</th><th>Chats waiting on them</th><th>Call status</th><th>First seen today</th><th>Screen time today</th><th>Today: customers / calls / orders</th><th>Late replies</th></tr></thead><tbody>
@foreach($board['rows'] as $x)<tr>
<td class="nw"><b>{{ $x['name'] }}</b> <small style="color:#6B7280">{{ ucfirst($x['role']) }}</small></td>
<td class="nw"><span style="display:inline-block;font-size:11.5px;font-weight:600;border-radius:999px;padding:1px 9px;background:{{ $col[$x['state']][0] }};color:{{ $col[$x['state']][1] }}">{{ $x['state'] }}</span>@if($x['state']!=='Online' && $x['seen_min']!==null) <small style="color:#6B7280">seen {{ $m($x['seen_min']) }}</small>@endif</td>
<td class="nw">{{ $x['page'] ?: '—' }}@if($x['page'] && $x['page_min']!==null) <small style="color:#6B7280">{{ $x['page_min'] < 1 ? 'just opened' : 'for '.$du($x['page_min']) }}</small>@endif</td>
<td class="nw" @if($x['state']==='Online' && $x['work_min']!==null && $x['work_min']>=20) style="color:#B42318;font-weight:600" @endif>{{ $m($x['work_min']) }}</td>
<td class="nw" @if($x['waiting']>0) style="color:#B42318;font-weight:700" @endif>{{ $x['waiting'] }}</td>
<td class="nw">{{ $x['call'] === 'busy' ? 'Busy' : 'Available' }}</td>
<td class="nw">{{ $x['first'] ?: '—' }}</td>
<td class="nw">{{ $x['active_min'] ? $du($x['active_min']) : '—' }}</td>
<td class="nw">{{ $x['chats'] }} / {{ $x['calls'] }} / {{ $x['orders'] }}</td>
<td class="nw">{{ $x['late'] }}</td></tr>@endforeach
</tbody></table>
<small style="display:block;margin-top:6px;color:#6B7280">Updated {{ $board['at'] }}. Online = the admin is open on their screen in the last 90 seconds. Last real action = a save, reply or status change (opening pages does not count). Screen time counts from now on, only while the admin is open. Chats waiting with nobody assigned: <b>{{ $board['unassigned'] }}</b>.</small>
