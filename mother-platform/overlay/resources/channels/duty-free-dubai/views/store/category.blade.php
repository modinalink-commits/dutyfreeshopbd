@php
  $__b = !empty($subSlug) ? '/store?cat=' . rawurlencode((string)$subSlug) : '/store?top=' . rawurlencode((string)($c->slug ?? ''));
@endphp
@include('store.dfd_app', ['boot' => $__b])
