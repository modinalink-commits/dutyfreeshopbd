@php $off = \App\Http\Controllers\StoreController::off($q); $u = \App\Http\Controllers\StoreController::url($q); @endphp
<div class="pc {{ $cls ?? '' }}">@if($off > 0 && $s['badge_discount']==='1')<span class="bo">{{ $off }}%</span>@endif
@if(!empty($fd) && $s['badge_free_delivery']==='1')<span class="bf">FREE DELIVERY</span>@endif
<a class="ph" href="{{ $u }}"><img src="{{ $q->image ?: '/icon.png' }}" alt="{{ $q->name }}" width="200" height="200" loading="lazy" decoding="async" draggable="false"></a>
<a class="nm" href="{{ $u }}">{{ $q->name }}</a>
<div class="pr"><b>{{ number_format($q->price) }} TK</b> <br>@if($off > 0)<s>{{ number_format($q->compare_price) }} TK</s>@endif</div>
@if($s['btn_order_now']==='1')<a class="on" href="/checkout?add={{ $q->id }}&amp;p={{ (float)$q->price }}" rel="nofollow">ORDER NOW</a>@endif</div>
