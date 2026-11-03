{{-- Shared Action column controls for every order view. kind=order → [View][Edit][⋮]; kind=incomplete → Convert on top, Recovered/Archive below. Behaviour/routes are unchanged. --}}
@if(($kind ?? 'order') === 'incomplete')
<div class="order-action-group incomplete-actions">
@if(in_array($o->status, ['open','recovered']))
<a class="btn oa oa-primary btn-convert" href="/admin/orders/new?inc={{ $o->id }}">Convert to Order</a>
@elseif($o->order_id)
<a class="btn oa oa-ghost btn-linked" href="/admin/orders/{{ $o->order_id }}">Order #{{ $o->order_id }}</a>
@endif
<div class="order-action-group incomplete-actions-secondary">
@if($o->status==='open')
<button class="oa oa-rec rc" type="submit" formmethod="post" formaction="/admin/incomplete/{{ $o->id }}/recovered" formtarget="_self">Recovered</button>
<button class="oa oa-arc ar" type="submit" formmethod="post" formaction="/admin/incomplete/{{ $o->id }}/archive" formtarget="_self">Archive</button>
@elseif($o->status!=='converted')
<button class="oa oa-arc ro" type="submit" formmethod="post" formaction="/admin/incomplete/{{ $o->id }}/reopen" formtarget="_self">Reopen</button>
@endif
</div>
</div>
@else
<div class="order-action-group">
<a class="btn oa oa-view vbtn" href="/admin/orders/{{ $o->id }}"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>View</a>
<a class="btn oa oa-ghost oa-edit" href="/admin/orders/{{ $o->id }}/edit">Edit</a>
<button type="button" class="oa oa-more qa-btn" data-id="{{ $o->id }}" data-st="{{ $o->status }}" title="More actions / quick status" aria-label="More actions">⋮</button>
</div>
@endif
