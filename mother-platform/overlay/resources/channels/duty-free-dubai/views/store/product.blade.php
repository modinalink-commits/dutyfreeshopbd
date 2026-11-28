@php
  $__ps = (string)($p->path_slug ?? '');
  $__b = '/product/' . ((preg_match('/^[a-z0-9-]+$/', $__ps) ? $__ps . '-' : 'p') . $p->id);
@endphp
@include('store.dfd_app', ['boot' => $__b])
