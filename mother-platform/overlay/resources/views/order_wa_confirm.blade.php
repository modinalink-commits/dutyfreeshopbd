@php
  $__wc = collect(); $__wev = collect();
  try { if (\App\Support\OrderWaConfirm::ready()) { $__wc = \Illuminate\Support\Facades\DB::table('order_whatsapp_confirmations')->where('order_id', $o->id)->orderBy('seq')->get(); $__wev = \Illuminate\Support\Facades\DB::table('order_whatsapp_events')->where('order_id', $o->id)->orderBy('id')->get(); } } catch (\Throwable $e) {}
  $__wl = $__wc->last();
  $__fmt = fn($t) => $t ? \Carbon\Carbon::parse($t, 'UTC')->timezone('Asia/Dhaka')->format('d M Y, h:i A') : '—';
  $__col = ['SENT'=>'#2563EB','DELIVERED'=>'#16A34A','READ'=>'#15803D','FAILED'=>'#DC2626','QUEUED'=>'#B45309','PENDING'=>'#B45309'];
@endphp
@if(\App\Support\OrderWaConfirm::ready() && ($__wl || ($o->approval_source ?? null)))
<div class="card" style="max-width:720px;border-left:4px solid #25D366" id="waConf"><b>WhatsApp Confirmation</b>
@if(!$__wl)
  <p style="margin:6px 0;color:#6B7280;font-size:13px">No automatic confirmation for this order. @if(in_array($o->status,['Approved','Packaging','Shipment','Delivered'])) <small>(Sent only to orders approved by AI after this feature went live.)</small>@endif</p>
@else
  <dl class="kv" style="margin:8px 0"><dt>Auto Send</dt><dd>{{ $__wc->first()->approval_source==='AI' ? 'Yes · approved by AI' : 'Yes · approved by '.$__wc->first()->approval_source }}</dd>
  <dt>Template</dt><dd>{{ $__wl->template_name }}</dd>
  <dt>Mobile</dt><dd>{{ $__wl->mobile }}</dd>
  <dt>Status</dt><dd><b style="color:{{ $__col[$__wl->send_status] ?? '#374151' }}">{{ $__wl->send_status==='FAILED' ? ($__wl->permanent ? 'Not Sent' : 'Failed — Manual Review') : ucfirst(strtolower($__wl->send_status)) }}</b>@if($__wl->failure_reason) <small style="color:#B91C1C">· {{ $__wl->failure_reason }}</small>@endif</dd>
  <dt>Message ID</dt><dd style="word-break:break-all"><small>{{ $__wl->external_message_id ?: '—' }}</small></dd>
  <dt>Sent</dt><dd>{{ $__fmt($__wl->sent_at) }}</dd><dt>Delivered</dt><dd>{{ $__fmt($__wl->delivered_at) }}</dd><dt>Read</dt><dd>{{ $__fmt($__wl->read_at) }}</dd><dt>Retries</dt><dd>{{ $__wl->retry_count }}</dd></dl>
  @if($__wl->message_body)<details><summary style="cursor:pointer;font-size:12.5px">Message sent</summary><pre style="white-space:pre-wrap;font-family:inherit;font-size:12.5px;background:#F8FAFC;border:1px solid #E5E7EB;border-radius:8px;padding:8px;margin:6px 0">{{ $__wl->message_body }}</pre></details>@endif
  @if($__wc->count()>1)<details><summary style="cursor:pointer;font-size:12.5px">Resend history ({{ $__wc->count()-1 }})</summary>@foreach($__wc->skip(1) as $__r)<div style="font-size:12px;margin:4px 0">#{{ $__r->seq }} · {{ $__fmt($__r->resend_at) }} · by {{ \Illuminate\Support\Facades\DB::table('users')->where('id',$__r->resend_by)->value('name') ?: '—' }} · {{ $__r->resend_reason }} · <b>{{ $__r->send_status }}</b></div>@endforeach</details>@endif
  <details><summary style="cursor:pointer;font-size:12.5px">Events</summary>@foreach($__wev as $__e)<div style="font-size:12px;margin:3px 0">{{ $__fmt($__e->created_at) }} · {{ $__e->event }}@if($__e->detail) · <small>{{ $__e->detail }}</small>@endif</div>@endforeach</details>
@endif
@if(\App\Http\Middleware\ModulePermission::can(auth()->user(),'orders','edit'))
  <form method="post" action="/admin/orders/{{ $o->id }}/wa-confirm/resend" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:8px" onsubmit="return confirm('Send the WhatsApp confirmation to {{ $__wl->mobile ?? $o->phone }}?{{ $__wl ? '\n\nLast sent: '.($__wl->sent_at ? $__fmt($__wl->sent_at) : 'never').'\nStatus: '.$__wl->send_status : '' }}')">@csrf
    <input name="reason" placeholder="Reason (optional)" maxlength="200" style="flex:1;min-width:160px;padding:6px 8px;font-size:12.5px"><button class="btn" type="submit">{{ $__wl && $__wl->send_status==='FAILED' && !$__wl->permanent ? 'Retry' : 'Resend' }}</button></form>
@endif
</div>
@endif
