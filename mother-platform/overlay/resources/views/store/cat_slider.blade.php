@foreach($slider as $__i => [$c, $items])
@include('store.cat_slider_one', ['pi'=>$__i])
@endforeach
