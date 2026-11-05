{{-- Shared Action column controls for every order view. kind=order → [View][Edit][⋮]; kind=incomplete → View/Edit/Quick, Convert, Recovered/Archive. Behaviour/routes are unchanged. --}}
@if(($kind ?? 'order') === 'incomplete')
@php $__ist = ['open'=>'Open', 'recovered'=>'Recovered', 'archived'=>'Cancel', 'converted'=>'Converted'][$o->status] ?? 'Open'; @endphp
<div class="order-action-group oag-col">
<div class="oar"><a class="btn oa oa-view vbtn" href="/admin/incomplete/{{ $o->id }}"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>View</a>@if($o->status !== 'converted')<a class="btn oa oa-ghost oa-edit" href="/admin/incomplete/{{ $o->id }}/edit">Edit</a>@endif</div>
@if($o->status !== 'converted')<div class="oar"><button type="button" class="oa oa-quick qk-btn" data-inc="1" data-id="{{ $o->id }}" title="Quick Edit" aria-label="Quick Edit">⚡ Quick ▾</button><button type="button" class="oa oa-more qa-btn" data-inc="1" data-id="{{ $o->id }}" data-st="{{ $__ist }}" title="More actions / quick status" aria-label="More actions">⋮</button></div>@elseif($o->order_id)<div class="oar"><a class="btn oa oa-ghost btn-linked" href="/admin/orders/{{ $o->order_id }}">Order #{{ $o->order_id }}</a></div>@endif
</div>
@else
<div class="order-action-group oag-col">
<div class="oar"><a class="btn oa oa-view vbtn" href="/admin/orders/{{ $o->id }}"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>View</a><a class="btn oa oa-ghost oa-edit" href="/admin/orders/{{ $o->id }}/edit">Edit</a></div>
@php $__rs = \App\Http\Middleware\ModulePermission::canRestricted(auth()->user()) ? 0 : 1; $__cl = \App\Support\ShipmentOutcome::courierLocked($o) ? 1 : 0; @endphp
<div class="oar"><button type="button" class="oa oa-quick qk-btn" data-id="{{ $o->id }}" title="Quick Edit" aria-label="Quick Edit">⚡ Quick ▾</button><button type="button" class="oa oa-status qa-btn" data-id="{{ $o->id }}" data-st="{{ $o->status }}" data-cl="{{ $__cl }}" data-rs="{{ $__rs }}" data-src="status" title="Change Order Status" aria-label="Change Order Status"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#16A34A" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>Status ▾</button></div>
</div>
@endif
