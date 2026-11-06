@extends('layout')
@section('title', 'Customer accounts & order requests')
@section('content')
@php($U = \App\Support\Ui::class)
@php($SC = ['new' => ['#e6eeff', '#1d4ed8'], 'approved' => ['#fff1d6', '#8a4b00'], 'on_trip' => ['#efe7fb', '#5b2fa8'], 'delivered' => ['#e3f6ec', '#0f6b3c'], 'rejected' => ['#fde9e7', '#b3261e']])
<style>.fld{display:flex;flex-direction:column;gap:4px;font-size:var(--fs-11);font-weight:600;color:#51607a}.kv{display:flex;font-size:var(--fs-13);padding:6px 0;border-top:1px solid #eef2f9}.kv span:first-child{flex-grow:1;color:#51607a}</style>
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Customer accounts &amp; order requests</h1><span class="sub">Orders customers send from the customer app · approve, then attach to a route trip · customer app: <a href="{{ route('my.login') }}" target="_blank">{{ url('/my') }}</a></span></div>
  <a class="btn" href="{{ route('customers.index') }}">Portal access list</a>
  <a class="btn pri" href="{{ route('customers.index') }}#new">＋ Register customer</a>
</header>
<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
  @foreach([['New requests', $k['new'], 'blue'], ['Approved, not on a trip', $k['approved'], 'warn'], ['On a trip', $k['on_trip'], 'vio'], ['Delivered this week', $k['delivered'], 'pos'], ['Customers using the app', $k['app'], 'gray']] as [$l, $v, $tone])
    <div style="background:{{ $U::bg($tone) }};border:1px solid #dbe3f0;border-radius:12px;padding:12px;display:flex;flex-direction:column"><span style="font-size:var(--fs-11);font-weight:600;color:#51607a">{{ $l }}</span><b style="font-size:var(--fs-22);color:{{ $U::ink($tone) }}">{{ $v }}</b></div>
  @endforeach
</section>
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section class="card" style="flex:999 1 620px;min-width:0;overflow:hidden">
    <div style="padding:16px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:8px"><h2 style="margin:0;font-size:var(--fs-15);flex-grow:1">Order requests</h2>
      <div class="tabs">@foreach(['all' => 'All', 'new' => 'New '.$k['new'], 'approved' => 'Approved '.$k['approved']] as $kk => $l)<a href="{{ route('corders.index', ['f' => $kk]) }}" class="{{ $f === $kk ? 'on' : '' }}">{{ $l }}</a>@endforeach</div></div>
    <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
      <thead><tr><th>Request</th><th>Sent</th><th>Customer</th><th>Type</th><th>Order</th><th>Wanted on</th><th>Route / salesman</th><th>Status</th><th class="no-sort">Action</th></tr></thead>
      <tbody>
      @forelse($orders as $o)
        <tr @if($sel && $sel->id === $o->id) style="background:#f6f9ff" @endif><td class="nw"><a href="{{ route('corders.index', ['o' => $o->id, 'f' => $f]) }}">{{ $o->order_no }}</a></td><td class="nw">{{ $o->created_at->timezone('Asia/Dhaka')->format('d-m H:i') }}</td><td>{{ $o->customer->name }}</td><td>{{ \App\Models\Customer::TYPES[$o->customer->type] ?? $o->customer->type }}</td><td>{{ $o->itemsText() }}</td><td class="nw">{{ $o->wanted_on?->format('d-m-Y') }}</td>
          <td>{{ $o->route ? $o->route->name.' · '.($o->salesman?->name ?? '—') : '—' }}{{ $o->trip ? ' · '.$o->trip->trip_no : '' }}</td>
          <td class="c"><span class="pill" style="background:{{ $SC[$o->status][0] }};color:{{ $SC[$o->status][1] }}">{{ \App\Models\CustomerOrder::STATUS[$o->status] }}</span></td>
          <td class="nw" style="font-size:var(--fs-12)">@if($o->status === 'new')<a class="btn sm pri" href="{{ route('corders.index', ['o' => $o->id, 'f' => $f]) }}#approve">Approve</a> <form method="post" action="{{ route('corders.reject', $o) }}" style="display:inline" onsubmit="const r=prompt('Reason?');if(!r)return false;this.reason.value=r">@csrf<input type="hidden" name="reason"><button class="btn sm" type="submit">Reject</button></form>
            @elseif($o->status === 'approved')<form method="post" action="{{ route('corders.attach', $o) }}" style="display:inline">@csrf<button class="btn sm" type="submit">Add to trip</button></form>
            @elseif($o->sale_id)<a href="{{ route('sales.show', $o->sale_id) }}">Invoice</a>@else — @endif</td></tr>
      @empty
        <tr><td colspan="9" class="empty">No order requests yet. Customers order from {{ url('/my') }}.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
  <aside style="flex:1 1 320px;min-width:290px;display:flex;flex-direction:column;gap:16px">
    @if($c)
    <section class="card" style="padding:18px;display:flex;flex-direction:column">
      <div style="display:flex;align-items:center"><h2 style="margin:0;font-size:var(--fs-15);flex-grow:1">Customer account · {{ $c->code }}</h2><span class="pill" style="{{ $c->is_active ? 'background:#e3f6ec;color:#0f6b3c' : '' }}">{{ $c->is_active ? 'Active' : 'Inactive' }}</span></div>
      @php($out = $c->cylindersOut())
      @foreach([['Shop name', $c->shop_name ?: $c->name], ['Owner', $c->owner_name ?: '[Owner name]'], ['Mobile', $c->phone], ['Address', $c->address ?: '—'], ['Customer type', \App\Models\Customer::TYPES[$c->type] ?? $c->type], ['Route · salesman', $routeOf ? ($routeOf->route?->name ?? '—').' · '.($routeOf->salesman?->name ?? '—') : '—'], ['Registered', $c->created_at->format('d-m-Y')], ['Customer app', ($c->portal_enabled ? 'OTP login on' : 'Off').($c->portal_last_seen ? ' · last seen '.$c->portal_last_seen->timezone('Asia/Dhaka')->format('d-m-Y H:i') : ' · not used yet')], ['Due · pending empties', $U::tk($c->due()).' · '.($out ? collect($out)->map(fn ($q, $s) => $q.' × '.$s.' kg')->implode(', ') : '0')]] as [$kk, $v])
        <div class="kv"><span>{{ $kk }}</span><b>{{ $v }}</b></div>
      @endforeach
      <div style="display:flex;gap:8px;margin-top:8px"><a class="btn sm" href="{{ route('customers.statement', $c) }}" target="_blank">See what the customer sees</a>
        <form method="post" action="{{ route('corders.portal', $c) }}">@csrf<button class="btn sm" type="submit">{{ $c->portal_enabled ? 'Turn app off' : 'Turn app on' }}</button></form></div>
    </section>
    @endif
    @if($sel && in_array($sel->status, ['new', 'approved'], true))
    <form class="card" id="approve" method="post" action="{{ route('corders.approve', $sel) }}" style="padding:18px;display:flex;flex-direction:column;gap:10px">@csrf
      <h2 style="margin:0;font-size:var(--fs-15)">Approve {{ $sel->order_no }}</h2>
      <span class="sub" style="margin:0">{{ $sel->customer->name }} · {{ \App\Models\Customer::TYPES[$sel->customer->type] ?? '' }} · {{ $sel->itemsText() }} · wanted {{ $sel->wanted_on?->format('d-m-Y') }}@if($sel->note) · “{{ $sel->note }}”@endif</span>
      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
        <label class="fld">Route<select name="route_id"><option value="">—</option>@foreach($routes as $r)<option value="{{ $r->id }}" @selected(($sel->route_id ?? $routeOf?->route_id) == $r->id)>{{ $r->name }}</option>@endforeach</select></label>
        <label class="fld">Delivery date<input type="date" name="delivery_date" required value="{{ ($sel->delivery_date ?? $sel->wanted_on)?->toDateString() }}"></label>
        <label class="fld">Vehicle<select name="vehicle_id"><option value="">—</option>@foreach($vehicles as $v)<option value="{{ $v->id }}" @selected(($sel->vehicle_id ?? $routeOf?->vehicle_id) == $v->id)>{{ $v->code }} · {{ $v->reg_no }}</option>@endforeach</select></label>
        <label class="fld">Salesman<select name="salesman_id"><option value="">—</option>@foreach($salesmen as $s)<option value="{{ $s->id }}" @selected(($sel->salesman_id ?? $routeOf?->salesman_id) == $s->id)>{{ $s->name }}</option>@endforeach</select></label>
      </div>
      <span class="sub" style="margin:0;font-size:var(--fs-12)">When the salesman confirms the sale, one database transaction makes the invoice, updates the purchase, empty and payment ledgers and the customer dashboard, lowers vehicle stock, and queues the SMS. The order is then marked delivered.</span>
      <button class="btn pri" type="submit">Approve &amp; add to trip</button>
    </form>
    @endif
  </aside>
</div>
@endsection
