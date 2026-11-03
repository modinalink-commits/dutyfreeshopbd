@extends('layout')@section('title','Incomplete checkout')
@section('body')
@php $ph = \App\Support\StoreScanner::ph(); $inv = $o->invoice ?: 'INC-'.str_pad($o->id, 5, '0', STR_PAD_LEFT); $dh = preg_replace('/\D/', '', $o->phone); @endphp
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px"><h1 style="margin:0;flex:1">Incomplete checkout {{ $inv }}</h1>
@if($o->status !== 'converted')<a class="btn g" href="/admin/incomplete/{{ $o->id }}/edit">Edit</a>@endif
@if(in_array($o->status, ['open','recovered']))<a class="btn" href="/admin/orders/new?inc={{ $o->id }}">Convert to Order</a>@elseif($o->order_id)<a class="btn g" href="/admin/orders/{{ $o->order_id }}">Order #{{ $o->order_id }}</a>@endif
<a class="btn g" href="/admin/orders?status=Incomplete">Back</a></div>
@if(session('ok'))<div class="card" style="background:#F0FDF4;border-color:#BBF7D0;color:#166534;margin-bottom:10px">{{ session('ok') }}</div>@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;margin-bottom:12px">
<div class="card"><h3 style="margin:0 0 8px">Customer</h3>
<div><b>{{ $o->name ?: 'No name yet' }}</b> @if($o->customer_category)<span class="pill" style="background:#DCFCE7;color:#166534">{{ $o->customer_category }}</span>@endif</div>
<div style="margin-top:4px"><a href="tel:{{ $o->phone }}" style="color:#4F46E5">{{ $o->phone }}</a> · <a href="https://wa.me/{{ str_starts_with($dh, '0') ? '88'.$dh : $dh }}" target="_blank" rel="noopener" style="color:#16A34A">WhatsApp</a>@if($cust) · <a href="/admin/m/customers/{{ $cust }}/edit" style="color:#1D4ED8">Customer profile</a>@endif</div>
@if($o->email)<div style="margin-top:4px;color:#4B5563">{{ $o->email }}</div>@endif
<div style="margin-top:8px;color:#374151">{{ $o->address ?: 'Address not given yet' }}@if($o->district)<br><small style="color:#6B7280">City: {{ $o->district }}</small>@endif</div>
@if($o->membership)<div style="margin-top:6px"><small style="color:#6B7280">Membership: {{ $o->membership }}</small></div>@endif</div>
<div class="card"><h3 style="margin:0 0 8px">Checkout status</h3>
<div><span class="pill" style="background:#FEF3C7;color:#92400E">{{ ucfirst($o->status) }}</span></div>
<div style="height:6px;background:#E5E7EB;border-radius:4px;margin-top:8px"><div style="height:6px;width:{{ (int)$o->progress }}%;background:{{ $o->progress >= 80 ? '#16A34A' : ($o->progress >= 60 ? '#F59E0B' : '#EF4444') }};border-radius:4px"></div></div>
<div style="margin-top:4px"><b>{{ (int)$o->progress }}%</b> · {{ $o->last_step }}</div>
<div style="margin-top:6px;color:#6B7280;font-size:12px">Started {{ \Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}<br>Last activity {{ \Carbon\Carbon::parse($o->last_activity_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') }} ({{ \Carbon\Carbon::parse($o->last_activity_at)->diffForHumans() }})</div>
<div style="margin-top:6px">Payment: <b>{{ $o->pay_method ?: 'not chosen yet' }}</b></div>
<div style="margin-top:6px;color:#374151">Note: {{ $o->note ?: '—' }}</div></div></div>
<div class="card" style="overflow-x:auto"><h3 style="margin:0 0 8px">Products</h3><table><thead><tr><th></th><th>Product</th><th>Price</th><th>Qty</th><th>Line total</th></tr></thead><tbody>
@foreach($items as $i)<tr><td><img src="{{ ($imgs[$i['id'] ?? 0] ?? null) ?: $ph }}" alt="" width="40" height="40" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ $ph }}'" style="object-fit:cover;border-radius:6px;border:1px solid #E5E7EB"></td><td>{{ $i['name'] ?? '' }}</td><td>৳{{ number_format((float)($i['price'] ?? 0)) }}</td><td>{{ (int)($i['qty'] ?? 0) }}</td><td>৳{{ number_format((float)($i['price'] ?? 0) * (int)($i['qty'] ?? 0)) }}</td></tr>@endforeach
</tbody></table>
<div style="margin-top:10px;text-align:right;line-height:1.7">Subtotal: ৳{{ number_format((float)$o->subtotal) }}@if($o->discount > 0)<br><span style="color:#B91C1C">Discount: -৳{{ number_format((float)$o->discount) }}@if($o->discount_note) <small>({{ $o->discount_note }})</small>@endif</span>@endif<br>Delivery: ৳{{ number_format((float)$o->shipping) }}<br><b style="font-size:16px">COD total: ৳{{ number_format((float)$o->total) }}</b></div></div>
@if($o->status !== 'converted')<form method="post" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">@csrf
@if($o->status==='open')<button class="btn g" formaction="/admin/incomplete/{{ $o->id }}/recovered">Recovered</button><button class="btn g" formaction="/admin/incomplete/{{ $o->id }}/archive">Archive</button>@else<button class="btn g" formaction="/admin/incomplete/{{ $o->id }}/reopen">Reopen</button>@endif</form>@endif
@endsection
