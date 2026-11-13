@foreach($slider as $__i => $__r)
@php [$c, $items] = $__r; $tops = $__r[2] ?? collect(); @endphp
{{-- single-product Top Seller card removed from the app: the website order (Top Selling Products shelf above the showcase) is the one design --}}
@include('store.cat_slider_one', ['pi'=>$__i])
@endforeach
