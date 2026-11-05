@extends('sp.layout')@section('title','Dashboard')
@section('body')@php $tk = fn($v) => '৳'.number_format($v); @endphp
<h1>Supplier Partner</h1><p class="sub">{{ $u->supplier_name }} · {{ $u->supplier_code }} · Last login {{ $u->last_login_at ? \Carbon\Carbon::parse($u->last_login_at)->timezone('Asia/Dhaka')->format('d M, g:i A') : 'first visit' }}</p>
<div class="kpis">
@if(\App\Support\SupplierPortal::can('ledger'))<a class="kpi hot" href="/supplier-portal/ledger"><span>Outstanding Due</span><b>{{ $tk($k['outstanding']) }}</b></a>@endif
@if(\App\Support\SupplierPortal::can('po'))<a class="kpi" href="/supplier-portal/pos"><span>Open Purchase Orders</span><b>{{ $k['open_po'] }}</b></a><a class="kpi" href="/supplier-portal/pos"><span>Pending Supply</span><b>{{ $k['pending_supply'] }}</b></a>@endif
@if(\App\Support\SupplierPortal::can('rfq'))<a class="kpi {{ $k['pending_rfq'] ? 'hot' : '' }}" href="/supplier-portal/rfq"><span>Pending RFQ</span><b>{{ $k['pending_rfq'] }}</b></a>@endif
@if(\App\Support\SupplierPortal::can('bills'))<a class="kpi" href="/supplier-portal/bills"><span>Total Supply</span><b>{{ $tk($k['total_supply']) }}</b></a>@endif
@if(\App\Support\SupplierPortal::can('payments'))<a class="kpi" href="/supplier-portal/payments"><span>Paid Amount</span><b>{{ $tk($k['paid']) }}</b></a>@endif
<div class="kpi"><span>Return / Damage</span><b>{{ $k['returns'] }}</b></div></div>
<div class="two"><div><h2>Action Required</h2>
@forelse($act as [$l,$c,$h,$p])<a class="ar" href="{{ $h }}"><span>{{ $l }}</span><span class="row"><span class="pb pb-{{ $p }}">{{ $p }}</span><b>{{ $c }}</b></span></a>
@empty<div class="card empty"><b>All caught up</b>No pending price requests or orders.</div>@endforelse
<div class="row noprint" style="margin-top:6px">@if(\App\Support\SupplierPortal::can('rfq'))<a class="btn" href="/supplier-portal/rfq">Submit Price</a>@endif @if(\App\Support\SupplierPortal::can('po'))<a class="btn g" href="/supplier-portal/pos">View PO</a>@endif @if(\App\Support\SupplierPortal::can('ledger'))<a class="btn g" href="/supplier-portal/ledger">View Ledger</a>@endif @if(\App\Support\SupplierPortal::can('payments'))<a class="btn g" href="/supplier-portal/payments">Payment Status</a>@endif</div></div>
<div><h2>Recent Activity</h2>@forelse($recent as $n)<a class="nt {{ $n->read_at ? '' : 'un' }}" href="{{ $n->link ?: '/supplier-portal/notifications' }}"><b>{{ $n->title }}</b><small>{{ \Carbon\Carbon::parse($n->created_at)->timezone('Asia/Dhaka')->diffForHumans() }}</small></a>@empty<div class="card empty">Nothing yet.</div>@endforelse</div></div>@endsection
