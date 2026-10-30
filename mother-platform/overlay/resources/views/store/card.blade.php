@php $off = $q->compare_price && $q->compare_price > $q->price ? round(($q->compare_price - $q->price) / $q->compare_price * 100) : 0; @endphp
<a class="cd" href="{{ \App\Http\Controllers\StoreController::url($q) }}">@if($off > 0 && $s['badge_discount']==='1')<span class="bd">-{{ $off }}%</span>@endif
<div class="im"><img src="{{ $q->image ?: '/icon.png' }}" alt="{{ $q->name }}" width="200" height="200" loading="lazy" decoding="async" draggable="false"></div>
<div class="t">{{ $q->name }}</div><div class="p"><b>৳{{ number_format($q->price) }}</b>@if($off > 0)<s>৳{{ number_format($q->compare_price) }}</s>@endif</div>
@if($s['btn_order_now']==='1')<span class="bt">ORDER NOW</span>@endif</a>
