@include('store.dfd_app', ['boot' => '/store?q=' . rawurlencode(trim(mb_substr((string)request('q'), 0, 80))), 'noindex' => true])
