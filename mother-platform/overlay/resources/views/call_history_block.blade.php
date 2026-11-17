{{-- One call history for a customer (by phone) or an order: PBX / IP calls and WhatsApp calls together, each marked with its channel.
     Owner 17 Nov 2026: a call that has no audio must say so; a saved call row is never shown as a recording. --}}
@php
$__pbx = \App\Http\Controllers\CallCenterController::callRows($cchPhone ?? null, isset($cchOrder) ? (int)$cchOrder : null);
$__wa = []; try { $__wa = \App\Support\WaCalls::historyRows($cchPhone ?? null, isset($cchOrder) ? (int)$cchOrder : null, auth()->user()); } catch (\Throwable $__e) { report($__e); }
$__out = ['confirmed'=>'Order Confirmed','pending'=>'Pending','call_later'=>'Call Later','no_answer'=>'No Answer','cancelled'=>'Order Cancelled'];
$__rec = ['recording'=>'Recording…','processing'=>'Processing','available'=>'Available','none'=>'No recording'];
$__all = [];
foreach ($__pbx as $__l) $__all[] = ['t' => \Carbon\Carbon::parse($__l->started_at ?? $__l->created_at, 'UTC'), 'ch' => 'ip', 'l' => $__l];
foreach ($__wa as $__w) $__all[] = ['t' => \Carbon\Carbon::parse($__w['call']->created_at, 'UTC'), 'ch' => 'wa', 'w' => $__w];
usort($__all, fn($a, $b) => $b['t']->getTimestamp() <=> $a['t']->getTimestamp());
$__waLab = ['ringing'=>'Ringing','answering'=>'Answering','active'=>'On call','ended'=>'Answered','missed'=>'Missed','rejected'=>'Rejected','failed'=>'Failed'];
$__recTxt = ['none'=>'No recording','na'=>'—','denied'=>'No permission to listen','nofile'=>'Recording file not found','recording'=>'Recording in progress…','failed'=>'Recording failed','deleted'=>'Recording deleted','expired'=>'Recording expired'];
$__bs = 'height:26px;padding:0 9px;border:1px solid #D0D5DD;border-radius:7px;background:#fff;color:#0B1B33;font-weight:700;font-size:12px;cursor:pointer';
@endphp
<div class="card os" style="margin-top:12px"><div class="os-h" style="display:flex;gap:10px;align-items:baseline;flex-wrap:wrap">Call History <span class="mu">({{ count($__all) }})</span>
<span class="mu" style="font-weight:500">IP {{ $__pbx->count() }} · WhatsApp {{ count($__wa) }}</span>@if(!count($__all))<span class="mu" style="font-weight:500">কোনো কল রেকর্ড নেই</span>@endif</div>
@if(count($__all))
<div style="overflow:auto"><table><thead><tr><th>Channel</th><th>Date</th><th>Time</th><th>Agent</th><th>Dir</th><th class="n">Duration</th><th>Outcome</th><th>Note</th><th>Recording</th></tr></thead><tbody>
@foreach($__all as $__r)@php $__t = $__r['t']->copy()->timezone('Asia/Dhaka'); @endphp
@if($__r['ch'] === 'ip')@php $__l = $__r['l']; @endphp
<tr><td><span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#EEF2F7;color:#0B1B33;font-weight:700;font-size:11.5px;white-space:nowrap">☎ IP Call</span></td><td>{{ $__t->format('d M Y') }}</td><td>{{ $__t->format('h:i A') }}</td><td>{{ $__l->user_name ?: '—' }}</td><td>{{ $__l->direction === 'in' ? '↙ In' : '↗ Out' }}</td><td class="n">{{ gmdate('i:s', (int)$__l->duration) }}</td>
<td>{{ ($__out[$__l->outcome ?? ''] ?? ucfirst($__l->status)) }}@if(!empty($__l->response))<br><small style="color:#6B7280">{{ $__l->response }}</small>@endif @if(!empty($__l->cancel_reason))<br><small style="color:#B91C1C">{{ $__l->cancel_reason }}</small>@endif @if(!empty($__l->callback_at))<br><small style="color:#B54708">Follow-up {{ \Carbon\Carbon::parse($__l->callback_at, 'UTC')->timezone('Asia/Dhaka')->format('d M h:i A') }}</small>@endif</td>
<td style="max-width:260px">{{ $__l->note ?: '—' }}</td><td>@if(!empty($__l->recording_url))<a href="{{ $__l->recording_url }}" target="_blank" rel="noopener">▶ Play</a>@if(\App\Http\Controllers\CallCenterController::recDl($__l)) <button type="button" onclick="RecDL.get('/admin/call-center/rec/{{ $__l->id }}/download',{{ $__l->id }},this)" style="{{ $__bs }};margin-left:6px">Download</button>@endif @else<small style="color:#475467">{{ $__rec[$__l->recording_status ?? 'none'] ?? 'No recording' }}</small>@endif</td></tr>
@else @php $__w = $__r['w']; $__c = $__w['call']; $__in = ($__c->direction ?? 'USER_INITIATED') !== 'BUSINESS_INITIATED'; @endphp
<tr><td><span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#E7F8EE;color:#0A6B3B;font-weight:700;font-size:11.5px;white-space:nowrap">✆ WhatsApp</span></td><td>{{ $__t->format('d M Y') }}</td><td>{{ $__t->format('h:i A') }}</td><td>{{ $__c->user_name ?: '—' }}</td>
<td>@if($__c->status === 'missed')<span style="color:#B42318;font-weight:700">✕ Missed</span>@else{{ $__in ? '↙ In' : '↗ Out' }}@endif</td><td class="n">{{ $__c->duration !== null ? gmdate('i:s', (int)$__c->duration) : '—' }}</td>
<td>{{ !empty($__c->outcome) ? (\App\Support\WaCalls::OUTCOMES[$__c->outcome] ?? $__c->outcome) : ($__waLab[$__c->status] ?? ucfirst((string)$__c->status)) }}@if(!empty($__c->followup_at))<br><small style="color:{{ !empty($__c->followup_done_at) ? '#067647' : '#B54708' }}">Follow-up {{ \Carbon\Carbon::parse($__c->followup_at, 'UTC')->timezone('Asia/Dhaka')->format('d M h:i A') }}{{ !empty($__c->followup_done_at) ? ' · done' : '' }}</small>@endif</td>
<td style="max-width:260px">{{ $__c->note ?: '—' }}</td>
<td>@if($__w['state'] === 'play')<button type="button" style="{{ $__bs }}" onclick="var b=this,a=b.parentNode.querySelector('audio');if(!a){a=document.createElement('audio');a.controls=true;a.preload='none';a.style.cssText='display:block;margin-top:6px;height:32px;max-width:240px';a.src='/admin/call-center/whatsapp/rec/{{ $__w['rec_id'] }}/audio?call={{ $__c->id }}';a.onerror=function(){var e=document.createElement('small');e.style.color='#B42318';e.textContent='Recording could not be played';a.replaceWith(e)};b.parentNode.appendChild(a)}document.querySelectorAll('audio').forEach(function(x){if(x!==a)x.pause()});if(a.paused)a.play();else a.pause()">▶ Play</button>@if($__w['dl']) <button type="button" style="{{ $__bs }};margin-left:6px" onclick="RecDL.get('/admin/call-center/whatsapp/rec/{{ $__w['rec_id'] }}/audio?dl=1&call={{ $__c->id }}',{{ $__c->id }},this)">Download</button>@endif @if($__w['incomplete'])<br><small style="color:#B54708">Incomplete recording</small>@endif
@else<small style="color:{{ in_array($__w['state'], ['failed','nofile'], true) ? '#B42318' : '#475467' }}">{{ $__recTxt[$__w['state']] ?? '—' }}</small>@endif</td></tr>
@endif
@endforeach</tbody></table></div>@endif</div>
