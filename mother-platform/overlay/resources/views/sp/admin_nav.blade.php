@include('proc.nav')
@php $p = '/'.trim(request()->path(), '/'); $T = [['Portal home','/admin/purchase/portal'],['Purchase Orders','/admin/purchase/portal/pos'],['RFQ / Price Requests','/admin/purchase/portal/rfqs']]; @endphp
<div class="chips" style="margin:-4px 0 12px">@foreach($T as [$n,$u])<a class="chip {{ ($u==='/admin/purchase/portal' ? $p===$u : str_starts_with($p,$u)) ? 'on' : '' }}" href="{{ $u }}">{{ $n }}</a>@endforeach</div>
