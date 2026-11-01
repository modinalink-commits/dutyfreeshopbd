@php
$__r = \Schema::hasTable('order_revisions') ? \DB::table('order_revisions')->where('order_id',$o->id)->whereIn('status',['applied','pending'])->orderByDesc('id')->get() : collect();
$__ap = $__r->where('status','applied'); $__pd = $__r->where('status','pending'); $__last = $__ap->first();
$__it = $__r->count() ? \DB::table('order_revision_items')->whereIn('order_revision_id',$__r->pluck('id'))->get()->groupBy('order_revision_id') : collect();
$__tz = fn($v) => \Carbon\Carbon::parse($v)->timezone('Asia/Dhaka')->format('d M Y · h:i A');
$__tk = fn($n) => '৳'.number_format((float)$n);
@endphp
@if($__r->count())
<div class="card" id="revisions" style="font-size:13px">
<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap"><b>Order Revision</b>
<span style="color:#6B7280">@if($__last)Last Updated: {{ $__tz($__last->applied_at ?? $__last->created_at) }} · Updated By: <b>{{ $__last->user_name }}</b> · Revision Count: <b>{{ $__ap->count() }}</b>@endif @if($__pd->count())· <b style="color:#B45309">{{ $__pd->count() }} pending</b>@endif</span>
<button type="button" class="btn g" style="font-size:12px" onclick="var h=document.getElementById('revHist');h.style.display=h.style.display==='none'?'block':'none'">View Change History</button></div>
<div id="revHist" style="display:{{ $__pd->count() || request()->has('hist') ? 'block' : 'none' }};margin-top:8px">
<b>Order Change History</b>
@foreach($__r as $v)
<div style="border-top:1px solid #E5E7EB;margin-top:8px;padding-top:8px">
<div><b>{{ $__tz($v->applied_at ?? $v->created_at) }}</b> @if($v->status==='pending')<span class="pill" style="background:#FEF3C7;color:#92400E">Pending revision</span>@endif · Employee: <b>{{ $v->user_name }}</b>@if($v->ip) <small style="color:#9CA3AF">({{ $v->ip }})</small>@endif · Change type: {{ ucfirst($v->revision_type) }}@if($v->call_log_id) · 📞 call #{{ $v->call_log_id }}@endif</div>
@foreach(($__it[$v->id] ?? collect()) as $x)
<div style="margin-left:10px">
@if($x->action==='added')➕ Added: <b>{{ $x->name }}</b> × {{ $x->new_quantity }} @ {{ $__tk($x->new_price) }}
@elseif($x->action==='removed')➖ Removed: <b>{{ $x->name }}</b> × {{ $x->old_quantity }}
@elseif($x->action==='replaced')🔁 Replaced <b>{{ json_decode($x->metadata ?? '{}', true)['old_name'] ?? '' }}</b> → <b>{{ $x->name }}</b> (qty {{ $x->old_quantity }} → {{ $x->new_quantity }})
@else ✏️ <b>{{ $x->name }}</b>@if($x->old_quantity !== $x->new_quantity): Qty {{ $x->old_quantity }} → Qty {{ $x->new_quantity }}@endif @if((float)$x->old_price !== (float)$x->new_price): Price {{ $__tk($x->old_price) }} → {{ $__tk($x->new_price) }}@endif @endif
@php($__m = json_decode($x->metadata ?? '{}', true))@if(!empty($__m['price_reason']))<small style="color:#6B7280"> — price reason: {{ $__m['price_reason'] }}</small>@endif
</div>
@endforeach
<div style="margin-left:10px">Subtotal {{ $__tk($v->old_subtotal) }} → {{ $__tk($v->new_subtotal) }} · Discount {{ $__tk($v->old_discount) }} → {{ $__tk($v->new_discount) }} · Delivery {{ $__tk($v->old_delivery_charge) }} → {{ $__tk($v->new_delivery_charge) }}</div>
<div style="margin-left:10px"><b>Previous Total: {{ $__tk($v->old_grand_total) }} · New Total: {{ $__tk($v->new_grand_total) }}</b> · Difference: <b style="color:{{ $v->amount_difference > 0 ? '#B45309' : '#15803D' }}">{{ $v->amount_difference > 0 ? '+' : ($v->amount_difference < 0 ? '−' : '±') }}{{ $__tk(abs($v->amount_difference)) }}</b></div>
<div style="margin-left:10px;color:#4B5563">Reason: {{ $v->reason }}@if($v->note) — {{ $v->note }}@endif · Customer confirmation: <b>{{ $v->customer_confirmed ? 'Yes' : 'Not yet' }}</b>@if($v->sms_sent) · SMS sent @endif @if($v->courier_warning)· <span style="color:#B45309">⚠ courier parcel needed an update</span>@endif</div>
@if($v->discount_note || $v->delivery_note)<div style="margin-left:10px;color:#6B7280;font-size:12px">@if($v->discount_note)Discount: {{ $v->discount_note }} @endif @if($v->delivery_note)· Delivery: {{ $v->delivery_note }}@endif</div>@endif
@if($v->status==='pending')
<form method="post" action="/admin/orders/{{ $o->id }}/revisions/{{ $v->id }}/apply" style="margin:6px 0 0 10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">@csrf<label style="display:flex;gap:6px;align-items:center;font-weight:700"><input type="checkbox" name="customer_confirmed" value="1" style="width:auto"> Customer confirmed this order change</label><button class="btn" style="font-size:12px">Apply revision</button></form>
<form method="post" action="/admin/orders/{{ $o->id }}/revisions/{{ $v->id }}/discard" style="margin:4px 0 0 10px">@csrf<button class="btn g" style="font-size:12px">Discard</button></form>
@endif
</div>
@endforeach
</div></div>
@endif
