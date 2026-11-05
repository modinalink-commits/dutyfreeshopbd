@extends('layout')
@section('title', 'Customers & dues')
@section('content')
@php($admin = in_array(auth()->user()->role, ['super_admin', 'admin']))
@php($tk = fn ($n) => '৳'.number_format((float) $n))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Customers &amp; dues</h1><p class="sub">Retail customers, dealers and their cylinders</p></div>
  @if(auth()->user()->isSuperAdmin())
  <form method="post" action="{{ route('sms.notice') }}" onsubmit="this.message.value=prompt('Due reminder text','{{ addslashes(\App\Support\Sms::render('due', ['due' => '…'])) }}')||'';return !!this.message.value">@csrf<input type="hidden" name="to" value="due"><input type="hidden" name="message"><button class="btn" type="submit" @disabled(! \App\Support\Sms::configured()) title="{{ \App\Support\Sms::configured() ? 'Send an SMS to every customer with due' : 'Set up the SMS gateway in Invoices & SMS first' }}">Send due SMS ({{ $counts['due'] }})</button></form>
  @else<button class="btn" type="button" disabled title="Super Admin sends due SMS">Send due SMS ({{ $counts['due'] }})</button>@endif
  <a class="btn pri" href="{{ route('customers.index', ['new' => 1]) }}#cust-form">＋ Add customer</a>
</header>
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section class="card" style="flex:1 1 340px;min-width:290px;overflow:hidden">
    <form method="get" style="padding:16px;display:flex;flex-direction:column;gap:10px">
      <input type="search" name="q" value="{{ $q }}" placeholder="Name, phone or area" aria-label="Search customers" style="border-radius:999px">
      <div class="tabs">
        <a class="{{ ! $f ? 'on' : '' }}" href="{{ route('customers.index', ['q' => $q]) }}">All {{ $counts['all'] }}</a>
        <a class="{{ $f === 'dealer' ? 'on' : '' }}" href="{{ route('customers.index', ['q' => $q, 'f' => 'dealer']) }}">Dealers {{ $counts['dealer'] }}</a>
        <a class="{{ $f === 'due' ? 'on' : '' }}" href="{{ route('customers.index', ['q' => $q, 'f' => 'due']) }}">With due {{ $counts['due'] }}</a>
        <a class="{{ $f === 'empties' ? 'on' : '' }}" href="{{ route('customers.index', ['q' => $q, 'f' => 'empties']) }}">Empties out {{ $counts['empties'] }}</a>
      </div>
    </form>
    <div style="max-height:70vh;overflow:auto">
    @forelse($list as $c)
      @php($d = (float) ($dues[$c->id] ?? 0))
      <a href="{{ route('customers.index', ['c' => $c->id, 'q' => $q, 'f' => $f]) }}" style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-top:1px solid var(--line);text-decoration:none;color:var(--ink);background:{{ $sel && $sel->id === $c->id ? '#dce8ff' : '#fff' }}">
        <span style="width:36px;height:36px;border-radius:999px;background:#e6eeff;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">{{ collect(explode(' ', $c->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span>
        <span style="flex-grow:1;min-width:0;display:flex;flex-direction:column"><b style="font-size:13px">{{ $c->name }}</b><span class="sub" style="font-size:11px;margin:0">{{ \App\Models\Customer::TYPES[$c->type] }} · {{ $c->phone }}</span></span>
        <b style="font-size:13px;color:{{ $d > 0.004 ? '#b3261e' : '#0f6b3c' }}">{{ $tk($d) }}</b>
      </a>
    @empty
      <p class="sub" style="padding:16px">No customers yet.</p>
    @endforelse
    </div>
  </section>
  <section style="flex:999 1 520px;min-width:0;display:flex;flex-direction:column;gap:16px">
  @if($edit)
    <form id="cust-form" class="card pad" method="post" action="{{ route('customers.save') }}" style="display:flex;flex-direction:column;gap:12px">
      @csrf
      <h2 style="margin:0;font-size:15px">{{ $edit->exists ? 'Edit '.$edit->name : 'Add customer' }}</h2>
      @if($edit->exists)<input type="hidden" name="id" value="{{ $edit->id }}">@endif
      <div class="grid2">
        <label class="f">Name<input type="text" name="name" value="{{ old('name', $edit->name) }}" required></label>
        <label class="f">Mobile<input type="tel" name="phone" value="{{ old('phone', $edit->phone) }}" placeholder="01XXXXXXXXX" required></label>
        <label class="f">Type<select name="type">@foreach(\App\Models\Customer::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', $edit->type) === $k)>{{ $l }}</option>@endforeach</select></label>
        <label class="f">Area / bazar<input type="text" name="area" value="{{ old('area', $edit->area) }}"></label>
        <label class="f" style="grid-column:1/-1">Address<input type="text" name="address" value="{{ old('address', $edit->address) }}"></label>
        <label class="f">Credit limit (৳) — 0 means credit off<input type="number" name="credit_limit" min="0" step="1" value="{{ old('credit_limit', (float) $edit->credit_limit) }}" @disabled(! $admin)></label>
        @unless($edit->exists)<label class="f">Opening due (৳, if any)<input type="number" name="opening_due" min="0" step="1" value="{{ old('opening_due', 0) }}"></label>@endunless
        <label class="f" style="grid-column:1/-1">Note<input type="text" name="note" value="{{ old('note', $edit->note) }}"></label>
      </div>
      @unless($admin)<span class="sub">Only Super Admin or Admin can turn on credit.</span>@endunless
      <div style="display:flex;gap:8px"><button class="btn pri" type="submit">Save customer</button><a class="btn" href="{{ route('customers.index') }}">Cancel</a></div>
    </form>
  @elseif($sel)
    @php($cyl = $detail['cyl'])
    <div class="card pad" style="display:flex;flex-wrap:wrap;gap:16px;align-items:center">
      <div style="flex-grow:1;display:flex;flex-direction:column;gap:2px"><span style="font-size:18px;font-weight:700">{{ $sel->name }}</span>
        <span class="sub" style="margin:0">{{ \App\Models\Customer::TYPES[$sel->type] }} @if($sel->area)· {{ $sel->area }}@endif · {{ $sel->phone }} · {{ $sel->code }} · since {{ $sel->created_at->format('m-Y') }}</span></div>
      <a class="btn" href="{{ route('customers.index', ['edit' => $sel->id]) }}#cust-form">Edit</a>
      <a class="btn" href="{{ route('customers.statement', $sel) }}" target="_blank">Statement PDF</a>
      <a class="btn pri" href="#pay-form">Receive payment</a>
    </div>
    <div class="kpis">
      <div class="card kpi" style="background:#fde9e7"><span>Due now</span><b style="color:#b3261e">{{ $tk($detail['due']) }}</b><span>{{ $detail['oldest'] !== null ? 'oldest '.$detail['oldest'].' days' : 'nothing overdue' }}</span></div>
      <div class="card kpi" style="background:#e6eeff"><span>Credit limit</span><b style="color:#1d4ed8">{{ (float) $sel->credit_limit > 0 ? $tk($sel->credit_limit) : 'Off' }}</b>@if((float) $sel->credit_limit > 0)<span>{{ round(max(0, $detail['due']) / (float) $sel->credit_limit * 100) }}% used</span>@endif</div>
      <div class="card kpi" style="background:#fff1d6"><span>Our cylinders with them</span><b style="color:#8a4b00">{{ array_sum($cyl) }}</b><span>@foreach($cyl as $sz => $q){{ $sz }} kg {{ $q }} @endforeach</span></div>
      <div class="card kpi" style="background:#e3f6ec"><span>Bought this month</span><b style="color:#0f6b3c">{{ number_format($detail['monthKg']) }} kg</b></div>
    </div>
    <div class="grid2" style="align-items:start">
 <form id="pay-form" class="card pad" method="post" action="{{ route('customers.pay', $sel) }}" style="display:flex;flex-direction:column;gap:10px">
        @csrf
        <h2 style="margin:0;font-size:15px">Receive payment</h2>
        <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
          <label class="f">Amount (৳)<input type="number" name="amount" min="1" step="1" required></label>
          <label class="f">Method<select name="method"><option value="cash">Cash</option><option value="bkash">bKash</option><option value="nagad">Nagad</option><option value="bank">Bank</option></select></label>
          <label class="f">Against order (optional)<select name="sale_id"><option value="">General payment</option>@foreach($detail['openOrders'] as $o)<option value="{{ $o->id }}">{{ $o->invoice_no }} · ৳{{ number_format($o->due()) }} left</option>@endforeach</select></label>
          <label class="f">Reference<input type="text" name="reference" placeholder="Txn ID / slip"></label>
        </div>
        <button class="btn pri" type="submit">Save payment</button>
      </form>
      <form class="card pad" method="post" action="{{ route('customers.empties', $sel) }}" style="display:flex;flex-direction:column;gap:10px">
        @csrf
        <h2 style="margin:0;font-size:15px">Empties returned</h2>
        <div class="grid2" style="grid-template-columns:repeat(3,minmax(0,1fr))">
          <label class="f">Brand<select name="brand_id">@foreach($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
          <label class="f">Size<select name="size_kg">@foreach(\App\Models\Brand::SIZES as $s)<option value="{{ $s }}">{{ $s }} kg</option>@endforeach</select></label>
          <label class="f">Qty<input type="number" name="qty" min="1" required></label>
        </div>
        <button class="btn" type="submit">Save empties</button>
      </form>
    </div>
    <section class="card">
      <div class="pad"><h2 style="margin:0;font-size:15px">Statement</h2></div>
      <div class="dt-wrap"><table class="dt">
        <thead><tr><th>Date</th><th>Detail</th><th>Bill</th><th>Paid</th><th>Due after</th></tr></thead>
        <tbody>
        @forelse($detail['rows'] as $r)
          <tr><td class="c nw">{{ $r->created_at->timezone('Asia/Dhaka')->format('d-m-Y') }}</td>
            <td>{{ ucfirst($r->kind) }} {{ $r->reference }}@if($r->method) · {{ \App\Models\Sale::METHODS[$r->method] ?? $r->method }}@endif @if($r->note)<span class="sub">{{ $r->note }}</span>@endif</td>
            <td class="r">{{ (float) $r->debit ? $tk($r->debit) : '' }}</td><td class="r" style="color:#0f6b3c">{{ (float) $r->credit ? $tk($r->credit) : '' }}</td><td class="r"><b>{{ $tk($r->running) }}</b></td></tr>
        @empty
          <tr><td colspan="5" class="empty">No transactions yet.</td></tr>
        @endforelse
        </tbody>
      </table></div>
    </section>
  @else
    <div class="card pad"><b>Pick a customer on the left</b><p class="sub">to see their due, cylinders and statement, receive a payment or take back empties.</p></div>
  @endif
  </section>
</div>
@endsection
