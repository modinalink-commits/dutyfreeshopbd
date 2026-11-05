@extends('layout')
@section('title', 'Cash collection & settlement')
@section('content')
@include('route._css')
@php($U = \App\Support\Ui::class)
@php($edit = $trip->status === 'returned')
@php($saved = $trip->cash_notes ?? [])
<form method="post" action="{{ route('trips.settle.store', $trip) }}" id="cashForm" style="display:flex;flex-direction:column;gap:16px">@csrf
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Cash collection &amp; settlement</h1>
    <span class="sub">{{ $trip->vehicle->code }} · {{ $trip->trip_no }} · Salesman {{ $trip->salesman?->name ?? '—' }}</span></div>
  <button class="btn noprint" type="button" onclick="window.print()">Cash collection receipt</button>
  @if($edit)<button class="btn pri" type="submit">Save &amp; send to closing →</button>
  @else<a class="btn pri" href="{{ route('trips.closing', ['trip' => $trip->id]) }}">Go to closing →</a>@endif
</header>
@include('route._tripbar', ['routeName' => 'trips.settle'])

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
  <section class="card" style="padding:20px;display:flex;flex-direction:column">
    <span class="ttl" style="font-size:var(--fs-10);margin-bottom:6px">TODAY'S SALES</span>
    <div class="ln"><span>Cash sales</span><span>{{ $U::tk($c['cashSales']) }}</span></div>
    <div class="ln"><span>Bank / mobile banking sales</span><span>{{ $U::tk($c['digitalSales']) }}</span></div>
    <div class="ln"><span>Approved credit sales ({{ $c['creditShops']->count() }} shops)</span><span>{{ $U::tk($c['credit']) }}</span></div>
    <div class="tl"><span>Total sales</span><span>{{ $U::tk($c['totalSales']) }}</span></div>
    <span style="font-size:var(--fs-11);color:#51607a;margin-top:10px">From {{ $c['invoices'] }} invoices entered on this trip.</span>
  </section>
  <section class="card" style="padding:20px;display:flex;flex-direction:column">
    <span class="ttl" style="font-size:var(--fs-10);margin-bottom:6px">TODAY'S COLLECTION</span>
    <div class="ln"><span>Cash from today's sales</span><span>{{ $U::tk($c['cashSales']) }}</span></div>
    <div class="ln"><span>Previous due collected (cash)</span><span>{{ $U::tk($c['prevCash']) }}</span></div>
    <div class="ln"><span>Digital payments{{ $trip->digital_verified !== null ? ' verified' : '' }}</span><span>{{ $U::tk($c['digitalTotal']) }}</span></div>
    <div class="tl"><span>Total collection</span><span>{{ $U::tk($c['collection']) }}</span></div>
    <div style="background:#def5f7;color:#0b6670;border-radius:12px;padding:10px 12px;font-size:var(--fs-12);line-height:18px;margin-top:10px">Previous due collected is <strong>not</strong> counted as today's sale. It reduces the shop's due in its ledger.</div>
  </section>
  <section class="card" style="padding:20px;display:flex;flex-direction:column">
    <span class="ttl" style="font-size:var(--fs-10);margin-bottom:6px">CASH HAND-OVER</span>
    <div class="ln"><span>Cash collected</span><span>{{ $U::tk($c['cashCollected']) }}</span></div>
    <div class="ln"><span>(−) Approved expenses paid from cash</span><span>{{ $U::tk(-$c['expFromCash']) }}</span></div>
    <div class="tl"><span>Cash expected</span><span id="expected" data-v="{{ $c['expected'] }}">{{ $U::tk($c['expected']) }}</span></div>
    <div class="ln" style="margin-top:6px"><span>Cash deposited (counted)</span><span style="font-weight:700" id="countedTop">{{ $c['counted'] !== null ? $U::tk($c['counted']) : '—' }}</span></div>
    @php($diff = $c['cashDiff'])
    <div id="diffBox" style="background:{{ $diff !== null && $diff >= 0 ? '#e3f6ec' : '#fde9e7' }};color:{{ $diff !== null && $diff >= 0 ? '#0f6b3c' : '#b3261e' }};border-radius:12px;padding:12px;display:flex;align-items:center;margin-top:8px">
      <span style="flex-grow:1;font-size:var(--fs-13);font-weight:700" id="diffLbl">{{ $diff === null ? 'Not counted yet' : ($diff < 0 ? 'Cash short' : ($diff > 0 ? 'Cash extra' : 'Cash matches')) }}</span><span style="font-size:var(--fs-20);font-weight:700" id="diffVal">{{ $diff === null ? '' : $U::tk(abs($diff)) }}</span></div>
  </section>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:6px">
    <h2 class="h2" style="margin:0 0 6px">Cash count</h2>
    @foreach($notes as $n)
      <div style="display:flex;align-items:center;gap:10px;font-size:var(--fs-13);font-variant-numeric:tabular-nums">
        <span style="width:70px;font-weight:600">৳{{ number_format($n) }}</span><span style="color:#51607a">×</span>
        <input type="number" min="0" name="notes[{{ $n }}]" data-d="{{ $n }}" value="{{ old('notes.'.$n, $saved[$n] ?? '') }}" aria-label="৳{{ $n }} note count" @disabled(! $edit) style="width:80px;min-height:36px;text-align:right;font:600 13px 'Plus Jakarta Sans',sans-serif">
        <span style="flex-grow:1;text-align:right;font-weight:600" class="nt">{{ $U::tk($n * (int) ($saved[$n] ?? 0)) }}</span>
      </div>
    @endforeach
    <div class="tl"><span>Counted</span><span id="counted">{{ $U::tk($c['counted'] ?? 0) }}</span></div>
  </section>
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:6px">
    <h2 class="h2" style="margin:0 0 6px">Digital payments</h2>
    @php($ml = ['bkash' => 'bKash', 'nagad' => 'Nagad', 'bank' => 'Bank deposit'])
    @forelse($c['digitalRows'] as $d)
      <div class="ln"><span>{{ $ml[$d['method']] }} · {{ $d['count'] }} payment{{ $d['count'] === 1 ? '' : 's' }}</span><span>{{ $U::tk($d['amount']) }} <span class="pill" style="{{ $U::pill($trip->digital_verified !== null ? 'Verified' : 'Not verified') }}">{{ $trip->digital_verified !== null ? 'Verified' : 'Not verified' }}</span></span></div>
    @empty
      <div class="ln"><span>No digital payments on this trip</span><span></span></div>
    @endforelse
    @if($edit && $c['digitalRows']->isNotEmpty())<label style="display:flex;gap:8px;align-items:center;font-size:var(--fs-12);font-weight:600"><input type="checkbox" name="digital_ok" value="1" style="width:auto;min-height:0"> I checked these in the bKash / Nagad / bank statement</label>@endif
    <h2 class="h2" style="margin:14px 0 6px">Approved credit</h2>
    @forelse($c['creditShops'] as $r)
      <div class="ln"><span>{{ $r['sale']->buyerName() }} · {{ $r['sale']->invoice_no }}</span><span>{{ $U::tk($r['due']) }}</span></div>
    @empty
      <div class="ln"><span>No credit sales</span><span></span></div>
    @endforelse
    @foreach($c['prevDue'] as $p)
      <div class="ln"><span>Previous due collected — {{ $p->customer?->name }} ({{ \App\Models\Sale::METHODS[$p->method] ?? $p->method }})</span><span>{{ $U::tk($p->credit) }}</span></div>
    @endforeach
  </section>
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:6px">
    <h2 class="h2" style="margin:0 0 6px">Expenses paid from collection</h2>
    @forelse($c['expenses']->where('from_collection', true) as $e)
      <div class="ln"><span>{{ $e->head }}</span><span>{{ $U::tk($e->amount) }}</span></div>
    @empty
      <div class="ln"><span>None</span><span></span></div>
    @endforelse
    <div class="tl"><span>Total</span><span>{{ $U::tk($c['expFromCash']) }}</span></div>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:12px">
      <label class="fld">Received by<select name="received_by" @disabled(! $edit)>@foreach($receivers as $u)<option value="{{ $u->id }}" @selected(($trip->received_by ?? auth()->id()) == $u->id)>{{ $u->roleLabel() }} — {{ $u->name }}</option>@endforeach</select></label>
      <label class="fld">Settlement time<input type="time" name="time" @disabled(! $edit) value="{{ ($trip->settled_at ?? now())->timezone('Asia/Dhaka')->format('H:i') }}"></label>
    </div>
  </section>
</div>
</form>
@if($edit)
<form class="card pad noprint" method="post" action="{{ route('fleet.expense') }}" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end">@csrf
  <input type="hidden" name="trip_id" value="{{ $trip->id }}"><input type="hidden" name="from" value="collection">
  <h2 class="h2" style="flex-basis:100%">＋ Expense paid from this collection</h2>
  <label class="fld" style="flex:1 1 160px">Category<select name="head">@foreach(\App\Models\Expense::TRIP_HEADS as $h)<option>{{ $h }}</option>@endforeach</select></label>
  <label class="fld" style="flex:1 1 120px">Amount<input type="number" name="amount" min="1" step="0.01" required></label>
  <label class="fld" style="flex:2 1 200px">Note<input name="note" maxlength="255"></label>
  <button class="btn" type="submit">Save expense</button>
</form>
<script>
(function(){
  const f=document.getElementById('cashForm'), fmt=n=>(n<0?'−':'')+'৳'+Math.abs(Math.round(n)).toLocaleString('en-IN');
  function calc(){ let t=0, any=false;
    f.querySelectorAll('input[data-d]').forEach(i=>{ const v=+(i.value||0); if(i.value!=='') any=true; t+=v*(+i.dataset.d); i.parentNode.querySelector('.nt').textContent=fmt(v*(+i.dataset.d)); });
    document.getElementById('counted').textContent=fmt(t); document.getElementById('countedTop').textContent=any?fmt(t):'—';
    const d=t-(+document.getElementById('expected').dataset.v), b=document.getElementById('diffBox');
    if(!any){ document.getElementById('diffLbl').textContent='Not counted yet'; document.getElementById('diffVal').textContent=''; return; }
    document.getElementById('diffLbl').textContent=Math.abs(d)<.5?'Cash matches':(d<0?'Cash short':'Cash extra'); document.getElementById('diffVal').textContent=fmt(Math.abs(d));
    b.style.background=d<-.5?'#fde9e7':'#e3f6ec'; b.style.color=d<-.5?'#b3261e':'#0f6b3c'; }
  f.addEventListener('input',calc); calc();
})();
</script>
@endif
@endsection
