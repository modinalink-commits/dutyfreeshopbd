@php $__rows = \App\Http\Controllers\CallCenterController::callRows($cchPhone ?? null, isset($cchOrder) ? (int)$cchOrder : null);
$__out = ['confirmed'=>'Order Confirmed','pending'=>'Pending','call_later'=>'Call Later','no_answer'=>'No Answer','cancelled'=>'Order Cancelled'];
$__rec = ['recording'=>'Recording…','processing'=>'Processing','available'=>'Available','none'=>'—']; @endphp
<div class="card" style="margin-top:12px"><h2 style="margin-top:0">Call History <small style="font-weight:400;color:#6B7280">({{ $__rows->count() }})</small></h2>
@if($__rows->isEmpty())<div style="font-size:12.5px;color:#6B7280">কোনো কল রেকর্ড নেই।</div>@else
<div style="overflow:auto"><table><thead><tr><th>Date</th><th>Time</th><th>Agent</th><th>Dir</th><th class="n">Duration</th><th>Outcome</th><th>Note</th><th>Recording</th></tr></thead><tbody>
@foreach($__rows as $__l)@php $__t = \Carbon\Carbon::parse($__l->started_at ?? $__l->created_at, 'UTC')->timezone('Asia/Dhaka'); @endphp
<tr><td>{{ $__t->format('d M Y') }}</td><td>{{ $__t->format('h:i A') }}</td><td>{{ $__l->user_name ?: '—' }}</td><td>{{ $__l->direction === 'in' ? '↙ In' : '↗ Out' }}</td><td class="n">{{ gmdate('i:s', (int)$__l->duration) }}</td>
<td>{{ ($__out[$__l->outcome ?? ''] ?? ucfirst($__l->status)) }}@if(!empty($__l->response))<br><small style="color:#6B7280">{{ $__l->response }}</small>@endif @if(!empty($__l->cancel_reason))<br><small style="color:#B91C1C">{{ $__l->cancel_reason }}</small>@endif</td>
<td style="max-width:260px">{{ $__l->note ?: '—' }}</td><td>@if(!empty($__l->recording_url))<a href="{{ $__l->recording_url }}" target="_blank" rel="noopener">▶ Play</a>@else{{ $__rec[$__l->recording_status ?? 'none'] ?? '—' }}@endif</td></tr>
@endforeach</tbody></table></div>@endif</div>
