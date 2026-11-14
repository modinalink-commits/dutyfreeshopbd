@extends('layout')@section('title','Customer Email')
@section('body')
<style>.ema{color:#0B1B33}.ema h1{font-size:18px;margin:0}.ema h2{font-size:14px;margin:0 0 8px}.ema .card{padding:12px 14px;margin-bottom:10px}.ema label{font-size:12.5px;display:block}.ema input[type=text],.ema input[type=email],.ema input[type=password],.ema input[type=number],.ema select{height:34px;border:1px solid #D0D5DD;border-radius:8px;padding:0 10px;font-size:13px;max-width:100%}
.ema .row{display:flex;flex-wrap:wrap;gap:12px;align-items:end}.ema .btn{height:34px;border-radius:8px;border:1px solid #C2410C;background:#C2410C;color:#fff;padding:0 14px;font-size:13px;font-weight:600;cursor:pointer}.ema .btn.g{background:#fff;color:#0B1B33;border-color:#D0D5DD}
.ema .st{display:inline-block;font-size:11.5px;font-weight:600;border-radius:999px;padding:1px 9px}.ema .st.ok{background:#DCFCE7;color:#166534}.ema .st.no{background:#FEE2E2;color:#B91C1C}.ema .st.w{background:#FEF3C7;color:#B45309}
.ema .sw{display:inline-flex;align-items:center;gap:10px;font-weight:600;font-size:14px}.ema .sw input{width:46px;height:26px;appearance:none;background:#D1D5DB;border-radius:99px;position:relative;cursor:pointer;transition:.15s}.ema .sw input:checked{background:#16A34A}.ema .sw input:before{content:"";position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#fff;transition:.15s}.ema .sw input:checked:before{left:23px}
.ema table{width:100%;border-collapse:collapse;font-size:12.5px}.ema th{font-size:11px;color:#6B7280;text-align:left;padding:7px 8px;border-bottom:1px solid #E5E7EB;white-space:nowrap}.ema td{padding:7px 8px;border-bottom:1px solid #EEF1F4}
.ema .tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}.ema .tabs a{border:1px solid #E5E7EB;border-radius:999px;padding:4px 12px;font-size:12px;text-decoration:none;color:#0B1B33;background:#fff}.ema .tabs a.on{border-color:#F95505;background:#FFF1E8;color:#C2410C;font-weight:600}
.ema iframe{width:100%;height:720px;border:1px solid #E5E7EB;border-radius:10px;background:#F3F4F6}
@media(max-width:760px){.ema .row>*{flex:1 1 100%}.ema input,.ema select,.ema .btn{width:100%;height:42px}.ema iframe{height:620px}}</style>
<div class="ema">
<div style="margin-bottom:8px"><h1>Customer Email</h1><small>Order emails from {{ $user }}: order received, payment received, confirmed, shipped (with our tracking link), delivered and cancelled. Customers without an email address get nothing; SMS and WhatsApp are not changed.</small></div>
@if(session('ok'))<div class="card" style="background:#F0FAF3;border-color:#BFE3CC;color:#14683A;font-weight:600">{{ session('ok') }}</div>@endif
@if(session('err'))<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">{{ session('err') }}</div>@endif
@if($errors->any())<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">{{ $errors->first() }}</div>@endif

<div class="card"><h2>Status</h2>
<div class="row" style="align-items:center">
<span>Mailbox password <span class="st {{ $hasPw ? 'ok' : 'no' }}">{{ $hasPw ? 'Saved' : 'Not saved' }}</span></span>
<span>Email log table <span class="st {{ $table ? 'ok' : 'w' }}">{{ $table ? 'Ready' : 'Being created' }}</span></span>
<span>Customer emails <span class="st {{ $on && $ready ? 'ok' : 'no' }}">{{ $on && $ready ? 'ON' : 'OFF' }}</span></span>
<span>Sent today <b>{{ number_format($today) }}</b> of {{ number_format($cap) }}</span>
@if($withEmail !== null)<span>Customers with an email address <b>{{ number_format($withEmail) }}</b></span>@endif
</div></div>

<div class="card"><h2>1. Mailbox password</h2>
<form method="post" action="/admin/email" class="row" autocomplete="off">@csrf<input type="hidden" name="act" value="password">
<label>Password of {{ $user }}<br><input type="password" name="password" autocomplete="new-password" placeholder="{{ $hasPw ? 'Saved. Type only to replace it' : 'Type the mailbox password' }}" style="width:280px"></label>
<button class="btn">Save password</button></form>
<small style="display:block;margin-top:6px;color:#6B7280">Stored encrypted. It is never shown again and never appears in logs.</small></div>

<div class="card"><h2>2. Send a test email</h2>
<form method="post" action="/admin/email" class="row">@csrf<input type="hidden" name="act" value="test">
<label>Send to<br><input type="email" name="to" placeholder="your own email address" style="width:260px" required></label>
<label>Which email<br><select name="kind">@foreach($kinds as $k => $v)<option value="{{ $k }}">{{ $v['label'] }}</option>@endforeach</select></label>
<button class="btn g" @disabled(!$hasPw || !$hasSample)>Send test</button></form>
<small style="display:block;margin-top:6px;color:#6B7280">Uses the newest order as sample content, with [TEST] in the subject. It goes only to the address you type.</small></div>

<div class="card"><h2>3. Settings</h2>
<form method="post" action="/admin/email">@csrf<input type="hidden" name="act" value="settings">
<label class="sw"><input type="hidden" name="on" value="0"><input type="checkbox" name="on" value="1" @checked($on)> Send order emails to customers</label>
<div class="row" style="margin-top:12px">
<label>Sender name<br><input type="text" name="from_name" value="{{ $fromName }}" style="width:200px"></label>
<label>Replies go to<br><input type="email" name="reply_to" value="{{ $replyTo }}" style="width:240px"></label>
<label>Daily limit<br><input type="number" name="daily_cap" min="50" max="1000" value="{{ $cap }}" style="width:110px"></label></div>
<div class="row" style="margin-top:12px">@foreach($kinds as $k => $v)<label style="display:inline-flex;gap:6px;align-items:center"><input type="hidden" name="kind_{{ $k }}" value="0"><input type="checkbox" name="kind_{{ $k }}" value="1" @checked($v['on'])> {{ $v['label'] }}</label>@endforeach</div>
<button class="btn" style="margin-top:12px">Save settings</button></form>
<small style="display:block;margin-top:6px;color:#6B7280">The mailbox plan allows 1,000 emails a day; the limit here keeps a margin. When switched on, emails start with orders changed from that moment: old orders never get one.</small></div>

<div class="card" data-nogrid data-no-export><h2>Preview</h2>@if(!$hasSample)<div style="color:#6B7280">No order to preview with.</div>@else
<div class="tabs" id="emTabs">@foreach($kinds as $k => $v)<a href="/admin/email/preview/{{ $k }}" target="emPrev" class="{{ $loop->first ? 'on' : '' }}">{{ $v['label'] }}</a>@endforeach</div>
<iframe name="emPrev" src="/admin/email/preview/{{ $kinds->keys()->first() }}" title="Email preview"></iframe>
<script>document.getElementById('emTabs').addEventListener('click',function(e){var a=e.target.closest('a');if(!a)return;[].forEach.call(this.querySelectorAll('a'),function(x){x.classList.remove('on')});a.classList.add('on')});</script>@endif</div>

<div class="card" data-nogrid data-no-export><h2>Last 7 days</h2>
<div class="row" style="margin-bottom:8px">@forelse($counts as $s => $n)<span>{{ ucfirst($s) }} <b>{{ number_format($n) }}</b></span>@empty<span style="color:#6B7280">No emails yet.</span>@endforelse</div>
@if($log->isNotEmpty())<div style="overflow-x:auto"><table class="no-export"><tr><th>Time</th><th>To</th><th>Email</th><th>Subject</th><th>Status</th><th>Detail</th></tr>
@foreach($log as $l)<tr><td style="white-space:nowrap">{{ \Carbon\Carbon::parse($l->created_at, config('app.timezone'))->timezone('Asia/Dhaka')->format('d M, h:i A') }}</td><td>{{ $l->to }}</td><td>{{ $l->type }}</td><td>{{ $l->subject }}</td><td><span class="st {{ $l->status === 'sent' ? 'ok' : ($l->status === 'failed' ? 'no' : 'w') }}">{{ ucfirst($l->status) }}</span></td><td>{{ $l->error }}</td></tr>@endforeach</table></div>@endif</div>
</div>
@endsection
