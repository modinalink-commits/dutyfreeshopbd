{{-- Area / thana / city / district / division read from the customer's one address box. Shown only on orders the reader has seen. --}}
@if(($o->addr_check ?? null) !== null)
@php $__ac = (int)$o->addr_check; $__f = ['Area' => $o->addr_area ?? null, 'Thana' => $o->addr_thana ?? null, 'City' => $o->addr_city ?? null, 'District' => $o->addr_district ?? null, 'Division' => $o->addr_division ?? null]; @endphp
<div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:8px 0;padding:6px 8px;border:1px solid #E5E7EB;border-radius:8px;background:#F9FAFB;font-size:12px">
<b style="color:#0C161F">Delivery area</b>
@foreach($__f as $__l => $__v)<span style="border:1px solid #E5E7EB;border-radius:6px;background:#fff;padding:2px 8px"><span style="color:#6B7280">{{ $__l }}:</span> <b>{{ $__v ?: '—' }}</b></span>@endforeach
@if($__ac === 1)<a href="/admin/address-check?q={{ urlencode((string)$o->invoice) }}" style="border:1px solid #FB923C;border-radius:6px;background:#FFF1E8;color:#C2410C;padding:2px 8px;font-weight:700;text-decoration:none">Needs check</a><span style="color:#6B7280">{{ $o->addr_note }}</span>
@elseif($__ac === 2)<span style="color:#166534;font-weight:700">Checked by staff</span>
@else<span style="color:#6B7280">read from the address</span>@endif
</div>
@endif
