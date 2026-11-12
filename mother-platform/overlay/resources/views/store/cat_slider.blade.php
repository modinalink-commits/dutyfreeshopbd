@foreach($slider as $__i => $__r)
@php [$c, $items] = $__r; $tops = $__r[2] ?? collect(); @endphp
@if($tops->count() >= 3 && preg_match('/^cigarette/i', (string)$c->name))@include('store.hero_slider')@endif
@include('store.cat_slider_one', ['pi'=>$__i])
@if($tops->count() >= 3 && preg_match('/^skin/i', (string)$c->name))@include('store.hero_slider', ['__label'=>'Beauty'])@endif
@endforeach
