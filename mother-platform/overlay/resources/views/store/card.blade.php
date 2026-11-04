@php $off = \App\Http\Controllers\StoreController::off($q); $u = \App\Http\Controllers\StoreController::url($q); @endphp
<div class="pc{{ !empty($tb) ? ' hast' : '' }} {{ $cls ?? '' }}"><div class="pbs">@if($off > 0 && $s['badge_discount']==='1')<span class="bo">{{ $off }}%</span>@endif
@if(!empty($fd) && $s['badge_free_delivery']==='1' && \App\Support\Delivery::freeFor($q))<span class="bf"><svg width="14" height="14" aria-hidden="true"><use href="#dfi-truck"/></svg><i>FREE DELIVERY</i></span>@endif</div>
@if(!empty($tb))<span class="tsb">Today's Top Seller</span>@endif
@if(!empty($ts))<span class="tsn">{{ number_format($ts) }} sold today</span>@endif
<a class="ph" href="{{ $u }}"><img src="{{ $q->image ?: '/product-placeholder' }}" @php $__ss = \App\Support\Img::srcset($q->image); @endphp @if($__ss) srcset="{{ $__ss }}" sizes="(max-width:640px) 45vw, 200px" @endif alt="{{ !empty($q->image_alt) ? $q->image_alt : $q->name }}" width="200" height="200" loading="lazy" decoding="async" draggable="false" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/product-placeholder'">@php $__i2 = \App\Support\CardImg::second($q->id); @endphp @if($__i2)<img class="im2" src="{{ $__i2 }}" alt="" width="200" height="200" loading="lazy" decoding="async" draggable="false" aria-hidden="true" onerror="this.remove()">@endif</a>
<a class="nm" href="{{ $u }}">{{ $q->name }}</a>
<div class="pr"><b>{{ number_format($q->price) }} TK</b> <br>@if($off > 0)<s>{{ number_format($q->compare_price) }} TK</s><span class="dp">-{{ $off }}%</span>@endif</div>
@php $__in = \App\Support\StockGuard::sellable($q); @endphp<span class="ck {{ $__in ? '' : 'no' }}">{{ $__in ? 'In stock' : 'Out of stock' }}</span>
@if($s['btn_order_now']==='1')<a class="on" href="/checkout?add={{ $q->id }}&amp;p={{ (float)$q->price }}" rel="nofollow"><svg width="16" height="16" aria-hidden="true"><use href="#dfi-cart"/></svg><i>ORDER NOW</i></a>@endif</div>
