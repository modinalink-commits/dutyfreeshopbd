{{-- DutyFreeBD Best Sellers (14 Nov 2026): the two flagship products side by side, first thing on the home page. The price is read from the product every time, so it follows every price change. --}}
@php $__bs = $bs ?? \App\Http\Controllers\StoreController::bestSellers(); @endphp
@if($__bs->count())
<style>.bsx{max-width:1440px;margin:10px auto 12px;padding:0 12px}.bsx .in{background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:12px 14px}
.bsx .hh{display:flex;align-items:center;gap:8px;margin-bottom:10px}.bsx .hh b{font-size:15px;color:#0B1B33;letter-spacing:.01em}.bsx .hh i{flex:1;height:1px;background:#EEF0F3}.bsx .hh span{font-size:12px;color:#475467}
.bsx .gr{display:grid;grid-template-columns:repeat({{ min(2, $__bs->count()) }},minmax(0,1fr));gap:12px}
.bsx .c{display:grid;grid-template-columns:150px minmax(0,1fr);gap:14px;align-items:center;border:1px solid #EEF0F3;border-radius:12px;padding:10px 14px;text-decoration:none;color:#0B1B33;background:#FCFCFD;position:relative}
.bsx .c:hover{border-color:#FDBA8C;background:#fff}.bsx .im{width:150px;height:150px;border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden}.bsx .im img{width:100%;height:100%;object-fit:contain;display:block}
.bsx .bd{display:inline-block;background:#F26B1D;color:#fff;font-size:11px;font-weight:700;letter-spacing:.06em;border-radius:6px;padding:3px 8px;margin-bottom:6px}
.bsx .nm{font-size:17px;font-weight:700;line-height:1.3;margin-bottom:4px;overflow-wrap:anywhere}.bsx .pr{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;margin-bottom:8px}.bsx .pr b{font-size:26px;font-weight:800;color:#0B1B33;font-variant-numeric:tabular-nums}.bsx .pr small{font-size:12.5px;color:#475467}.bsx .pr s{font-size:13px;color:#98A2B3}
.bsx .go{display:inline-flex;align-items:center;justify-content:center;height:38px;padding:0 18px;border-radius:9px;background:#F26B1D;color:#fff;font-weight:700;font-size:14px}.bsx .c:hover .go{background:#E85D0C}.bsx .st{font-size:12px;color:#067647;font-weight:600;margin-left:10px}.bsx .st.no{color:#B42318}
@media(max-width:760px){.bsx{margin:8px auto 10px;padding:0 8px}.bsx .in{padding:10px}.bsx .hh span{display:none}.bsx .gr{gap:8px}.bsx .c{grid-template-columns:1fr;gap:6px;padding:8px;text-align:center}.bsx .im{width:100%;height:auto;aspect-ratio:1/1;max-width:150px;margin:0 auto}.bsx .nm{font-size:13.5px}.bsx .pr{justify-content:center;margin-bottom:6px}.bsx .pr b{font-size:20px}.bsx .go{width:100%;height:36px;padding:0 8px;font-size:13px}.bsx .st{display:block;margin:4px 0 0}}</style>
<section class="bsx" aria-label="DutyFreeBD Best Sellers"><div class="in"><div class="hh"><b>DutyFreeBD Best Sellers</b><i></i><span>Our two most ordered cartons</span></div><div class="gr">
@foreach($__bs as $q)@php $__u = \App\Http\Controllers\StoreController::url($q); $__in = \App\Support\StockGuard::sellable($q); $__img = $q->bs_image ?: ($q->image ?: '/product-placeholder'); @endphp
<a class="c" href="{{ $__in ? '/checkout?add='.$q->id.'&p='.(float)$q->price : $__u }}"><span class="im"><img src="{{ $__img }}" alt="{{ $q->name }}" width="150" height="150" decoding="async" onerror="this.onerror=null;this.src='/product-placeholder'"></span>
<span><span class="bd">BEST SELLER</span><span class="nm" style="display:block">{{ $q->name }}</span><span class="pr"><b>{{ number_format($q->price) }} TK</b>@if((float)$q->compare_price > (float)$q->price)<s>{{ number_format($q->compare_price) }} TK</s>@endif<small>per carton</small></span><span class="go">{{ $__in ? 'Order Now' : 'View' }}</span><span class="st {{ $__in ? '' : 'no' }}">{{ $__in ? 'In stock' : 'Out of stock' }}</span></span></a>
@endforeach
</div></div></section>
@endif
