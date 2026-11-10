@php
 $__c = DB::table('customers')->where('id', $cid)->first(); $__p = DB::table('customer_auth_providers')->where('customer_id', $cid)->pluck('provider')->all();
 $__h = DB::table('customer_login_history')->where('customer_id', $cid)->orderByDesc('id')->limit(15)->get();
@endphp
@if($__c)
<div class="card" style="margin-top:14px"><h3 style="margin-top:0">Login & Security</h3>
<div style="font-size:13px;line-height:1.9"><b>Login Methods:</b> {!! in_array('email', $__p) || !empty($__c->password) ? '✓ Email' : '' !!} {!! in_array('google', $__p) ? ' &nbsp; ✓ Google' : '' !!}{!! in_array('facebook', $__p) ? ' &nbsp; ✓ Facebook' : '' !!}{!! (!$__p && empty($__c->password)) ? '—' : '' !!}<br>
Email: {{ $__c->email ?: '—' }} · Phone: {{ $__c->phone ?: '—' }}<br>Google Connected: <b>{{ in_array('google', $__p) ? 'Yes' : 'No' }}</b> · Facebook Connected: <b>{{ in_array('facebook', $__p) ? 'Yes' : 'No' }}</b> · Last Login: {{ $__c->last_login_at ? \Carbon\Carbon::parse($__c->last_login_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') : '—' }}<br>Account Created: {{ \Carbon\Carbon::parse($__c->created_at)->timezone('Asia/Dhaka')->format('d M Y') }} · Status: {{ !empty($__c->blocked) ? 'Blocked' : 'Active' }}</div>
@if($__h->count())<table style="width:100%;font-size:12px;margin-top:10px;border-collapse:collapse"><tr style="color:#6B7280;text-align:left"><th>Time</th><th>Provider</th><th>IP</th><th>Device</th><th>Status</th></tr>@foreach($__h as $h)<tr style="border-top:1px solid #EEE"><td>{{ \Carbon\Carbon::parse($h->created_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</td><td>{{ ucfirst($h->provider) }}</td><td>{{ $h->ip }}</td><td>{{ \Illuminate\Support\Str::limit((string)$h->device, 38) }}</td><td style="color:{{ $h->status === 'success' ? '#166534' : '#B91C1C' }}">{{ $h->status }}</td></tr>@endforeach</table>@endif</div>
@endif
