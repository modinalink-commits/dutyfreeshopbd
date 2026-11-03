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
<div class="oar"><button type="button" class="oa oa-quick qk-btn" data-id="{{ $o->id }}" title="Quick Edit" aria-label="Quick Edit">⚡ Quick ▾</button><button type="button" class="oa oa-more qa-btn" data-id="{{ $o->id }}" data-st="{{ $o->status }}" data-cl="{{ \App\Support\ShipmentOutcome::courierLocked($o) ? 1 : 0 }}" title="More actions / quick status" aria-label="More actions">⋮</button></div>
</div>
@endif
