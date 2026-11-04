@extends('layout')
@section('title', 'Dashboard')
@section('content')
<div><h1>Dashboard</h1><p class="sub">{{ now()->timezone('Asia/Dhaka')->format('l, d-m-Y') }}</p></div>
<div class="kpis">
  <a class="card kpi" href="{{ route('orders.index', ['status' => 'new']) }}" style="text-decoration:none;color:inherit"><span>New website orders</span><b>{{ $newOrders }}</b></a>
  <div class="card kpi"><span>Website orders today</span><b>{{ $todayOrders }}</b></div>
  <div class="card kpi"><span>Open orders</span><b>{{ $openOrders }}</b></div>
  <div class="card kpi"><span>Active users</span><b>{{ $users }}</b></div>
</div>
<section class="card">
  <div class="pad" style="display:flex;align-items:center"><h2 style="margin:0;font-size:15px;flex-grow:1">Latest website orders</h2><a class="btn sm" href="{{ route('orders.index') }}">See all</a></div>
  <div class="scroll"><table>
    <thead><tr><th>#</th><th>Time</th><th>Name</th><th>Mobile</th><th>Cylinder</th><th>Qty</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($latest as $o)
      <tr><td>{{ $o->id }}</td><td>{{ $o->created_at->timezone('Asia/Dhaka')->format('d-m H:i') }}</td><td>{{ $o->name }}</td><td><a href="tel:{{ $o->phone }}">{{ $o->phone }}</a></td><td>{{ $o->cylinder }} · {{ $o->type }}</td><td>{{ $o->quantity }}</td><td><span class="pill st-{{ $o->status }}">{{ \App\Models\WebsiteOrder::STATUSES[$o->status] ?? $o->status }}</span></td></tr>
    @empty
      <tr><td colspan="7" style="color:var(--muted);padding:20px 12px">No website orders yet.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
<p class="sub">Stock, route sales, accounts and the other modules are added here step by step.</p>
@endsection
