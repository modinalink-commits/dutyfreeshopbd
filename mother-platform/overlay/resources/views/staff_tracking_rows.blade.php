<?php use App\Support\StaffTrack as T; use App\Support\Inv; $roles ??= []; ?>
<table><thead><tr><th>Date &amp; Time</th><th>Staff</th><th>Role</th><th>Module</th><th>Action</th><th>Order / Invoice</th><th>Customer</th><th>Previous value</th><th>New value</th><th>IP / Device</th><th>Status</th><th>View</th></tr></thead><tbody>
@forelse($list as $a)
@php $flag = $a->flag ?? T::flag($a); $g = T::group($a->action_type); @endphp
<tr>
<td class="nw">{{ T::dt($a->created_at) }}</td>
<td class="nw"><b>{{ $a->staff_name ?: 'System' }}</b></td>
<td class="nw">{{ $a->source === 'system' ? 'System' : ucfirst((string)($roles[$a->staff_name] ?? 'staff')) }}</td>
<td class="nw">{{ T::MODULE[$g] }}</td>
<td>{{ T::label($a->action_type) }}@if($a->description)<div class="mut">{{ \Illuminate\Support\Str::limit(Inv::strip((string)$a->description), 110) }}</div>@endif</td>
<td class="nw"><a href="{{ url('/admin/staff-tracking/orders') }}?order={{ $a->order_id }}&hl={{ $a->id }}#a{{ $a->id }}"><b>{{ Inv::show($a->invoice) ?: '#' . $a->order_id }}</b></a></td>
<td>{{ $a->customer_name }}</td>
<td>@if($a->old_value !== null && $a->old_value !== '')<span class="pill gr">{{ \Illuminate\Support\Str::limit($a->old_value, 50) }}</span>@else<span class="mut">—</span>@endif</td>
<td>@if($a->new_value !== null && $a->new_value !== '')<span class="pill ok">{{ \Illuminate\Support\Str::limit($a->new_value, 50) }}</span>@else<span class="mut">—</span>@endif</td>
<td class="mut">{{ T::device($a->user_agent, $a->ip_address) }}</td>
<td class="nw">@if($flag)<span class="pill red" title="{{ $flag }}">⚠ {{ \Illuminate\Support\Str::limit($flag, 34) }}</span>@elseif($a->source === 'system')<span class="pill gr">Automatic</span>@else<span class="pill ok">Recorded</span>@endif</td>
<td class="nw"><a href="{{ url('/admin/staff-tracking/orders') }}?order={{ $a->order_id }}&hl={{ $a->id }}">View</a></td>
</tr>
@empty
<tr><td colspan="12" class="empty">{{ !empty($flagsOnly) ? 'Nothing suspicious in this period.' : 'No activity in this period.' }}</td></tr>
@endforelse
</tbody></table>
