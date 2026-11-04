@extends('layout')
@section('title', 'Website orders')
@section('content')
<div><h1>Website orders</h1><p class="sub">Orders sent from the nklpgbd.com order form. Call the customer, then update the status.</p></div>
<div class="tabs">
  <a class="{{ $status ? '' : 'on' }}" href="{{ route('orders.index') }}">All</a>
  @foreach(\App\Models\WebsiteOrder::STATUSES as $k => $label)
    <a class="{{ $status === $k ? 'on' : '' }}" href="{{ route('orders.index', ['status' => $k]) }}">{{ $label }} ({{ $counts[$k] ?? 0 }})</a>
  @endforeach
</div>
<div class="dt-wrap tall"><table class="dt">
  <thead><tr><th>#</th><th>Received</th><th>Customer</th><th>Order</th><th>Address</th><th>Status &amp; note</th></tr></thead>
  <tbody>
  @forelse($orders as $o)
    <tr>
      <td>{{ $o->id }}</td>
      <td style="white-space:nowrap">{{ $o->created_at->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}</td>
      <td><b>{{ $o->name }}</b><br><a href="tel:{{ $o->phone }}">{{ $o->phone }}</a></td>
      <td style="white-space:nowrap">{{ $o->quantity }} × {{ $o->cylinder }}<br><span style="color:var(--muted)">{{ $o->type }}</span></td>
      <td style="min-width:180px">{{ $o->address }}</td>
      <td style="min-width:260px">
        @if(in_array(auth()->user()->role, ['super_admin', 'admin', 'manager']))
        <form method="post" action="{{ route('orders.update', $o) }}" style="display:flex;flex-direction:column;gap:6px">
          @csrf
          <div style="display:flex;gap:6px"><select name="status" aria-label="Status">@foreach(\App\Models\WebsiteOrder::STATUSES as $k => $label)<option value="{{ $k }}" @selected($o->status === $k)>{{ $label }}</option>@endforeach</select><button class="btn sm pri" type="submit">Save</button></div>
          <input type="text" name="note" value="{{ $o->note }}" placeholder="Note (optional)" aria-label="Note">
          @if($o->handler)<span style="font-size:11px;color:var(--muted)">Last update: {{ $o->handler->name }}</span>@endif
        </form>
        @else
        <span class="pill st-{{ $o->status }}">{{ \App\Models\WebsiteOrder::STATUSES[$o->status] ?? $o->status }}</span> {{ $o->note }}
        @endif
      </td>
    </tr>
  @empty
    <tr><td colspan="6" class="empty">No orders here.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['p' => $orders])
@endsection
