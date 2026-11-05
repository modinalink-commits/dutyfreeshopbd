@extends('layout')
@section('title', 'Accounts & profit')
@section('content')
@php($tk = fn ($n) => ($n < 0 ? '−' : '').'৳'.number_format(abs((float) $n)))
@php($big = fn ($n) => abs($n) >= 10000000 ? '৳'.number_format($n / 10000000, 2).' Cr' : (abs($n) >= 100000 ? '৳'.number_format($n / 100000, 2).' Lakh' : '৳'.number_format($n)))
@php($pl = $pl)
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Accounts &amp; profit</h1><p class="sub">{{ $start->format('F Y') }} · {{ $start->format('d-m') }} to {{ \Carbon\Carbon::parse($toDate)->format('d-m') }}</p></div>
  <form method="get" style="display:flex;gap:6px"><input type="month" name="m" value="{{ $ym }}" aria-label="Month"><button class="btn" type="submit">Show</button></form>
  <a class="btn" href="#accounts-setup">Money accounts</a>
  <button class="btn pri" type="button" onclick="window.print()">Download PDF</button>
</header>
<section class="kpis" aria-label="Money accounts">
  @forelse($balances as $b)
    <div class="card kpi"><span>{{ $b['account']->name }}</span><b>{{ $big($b['balance']) }}</b><span>{{ \App\Models\MoneyAccount::TYPES[$b['account']->type] }} · from {{ \Carbon\Carbon::parse($b['from'])->format('d-m-Y') }}</span></div>
  @empty
    <div class="card kpi"><span>Cash in hand</span><b>—</b><span>Add your cash, bank and bKash accounts below</span></div>
  @endforelse
  <div class="card kpi" style="background:#efe7fb"><span>Balance with company</span><b style="color:#5b2fa8">{{ $tk($companyBal) }}</b><span>AYGas · <a href="{{ route('company.index') }}">see ledger</a></span></div>
</section>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;align-items:start">
  <section class="card pad" style="display:flex;flex-direction:column;gap:2px;font-variant-numeric:tabular-nums">
    <h2 style="margin:0 0 8px;font-size:var(--fs-15)">Profit &amp; loss — month to date</h2>
    @php($row = fn ($k, $v, $kind = '') => compact('k', 'v', 'kind'))
    @php($lines = [
      $row('SALES', null, 'h'), $row('Refill sales', $pl['refill']), $row('New package sales', $pl['pkg']), $row('Accessories & service', $pl['acc']),
      $row('Delivery charges', $pl['delivery']), $row('(−) Discounts', -$pl['discount'], 'm'), $row('Total sales', $pl['totalSales'], 't'),
      $row('(−) Cost of gas and cylinders (company DO)', -$pl['cost'], 'm'), $row('Gross profit', $pl['gross'], 't'),
      $row('OTHER INCOME', null, 'h'), $row('Transportation from company', $pl['transport']),
      $row('Company incentive (earned this month)', $calc['gross']), $row('(−) AIT '.rtrim(rtrim(number_format($calc['month']->ait_pct, 2), '0'), '.').'%', -$calc['ait'], 'm'),
      $row('Company commission (earned this month)', $calc['commission']),
      $row('EXPENSES', null, 'h'),
    ])
    @foreach($expenses as $h => $v) @php($lines[] = $row($h, -$v, 'm')) @endforeach
    @if($expenses->isEmpty()) @php($lines[] = $row('No expenses entered', 0)) @endif
    @php($lines[] = $row('Net profit', $pl['net'], 'n'))
    @foreach($lines as $l)
      <div style="display:flex;font-size:{{ $l['kind'] === 'h' ? '11px' : ($l['kind'] === 'n' ? '16px' : '13px') }};padding:{{ $l['kind'] === 'h' ? '14px 0 7px' : '7px 0' }};{{ $l['kind'] === 'h' ? 'font-weight:700;letter-spacing:.08em;color:var(--muted)' : '' }}{{ $l['kind'] === 't' ? 'font-weight:700;border-top:1px solid var(--line)' : '' }}{{ $l['kind'] === 'n' ? 'font-weight:700;border-top:2px solid var(--ink);color:'.($pl['net'] >= 0 ? '#0f6b3c' : '#b3261e') : '' }}{{ $l['kind'] === 'm' ? 'color:#b3261e' : '' }}">
        <span style="flex-grow:1">{{ $l['k'] }}</span><span>{{ $l['v'] === null ? '' : $tk($l['v']) }}</span></div>
    @endforeach
    <span class="sub" style="margin-top:8px">Company incentive and commission are counted in the month they are earned, not the month they arrive. @if($pl['pkgUncosted'])New package cost uses the refill DO; set a package rate to include the cylinder price.@endif</span>
  </section>
  <section style="display:flex;flex-direction:column;gap:16px">
    <div class="card pad" style="display:flex;flex-direction:column;gap:12px">
      <h2 style="margin:0;font-size:var(--fs-15)">Profit by investment</h2>
      <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <div style="background:#e6eeff;border-radius:12px;padding:12px;display:flex;flex-direction:column"><span class="sub" style="margin:0;font-size:var(--fs-11);font-weight:600">Total investment</span><b style="font-size:var(--fs-20);color:#1d4ed8">{{ $investment ? $big($investment) : 'Not entered' }}</b></div>
        <div style="background:#e3f6ec;border-radius:12px;padding:12px;display:flex;flex-direction:column"><span class="sub" style="margin:0;font-size:var(--fs-11);font-weight:600">Profit per ৳1,00,000</span><b style="font-size:var(--fs-20);color:#0f6b3c">{{ $investment ? $tk($pl['net'] / $investment * 100000) : '—' }}</b></div>
      </div>
      <div class="dt-wrap"><table class="dt">
        <thead><tr><th>Partner</th><th>Investment</th><th>Share</th><th>Profit</th></tr></thead>
        <tbody>
        @forelse($partners as $p)
          <tr><td><b>{{ $p->name }}</b></td><td class="r">{{ $tk($p->investment) }}</td><td class="c">{{ $investment ? number_format($p->investment / $investment * 100, 1).'%' : '—' }}</td><td class="r" style="font-weight:700;color:#0f6b3c">{{ $investment ? $tk($pl['net'] * $p->investment / $investment) : '—' }}</td></tr>
        @empty
          <tr><td colspan="4" class="empty">Add partners and their investment below.</td></tr>
        @endforelse
        </tbody>
      </table></div>
      <form method="post" action="{{ route('accounts.partner') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">@csrf
        <label class="f" style="flex:1 1 160px">Partner<select name="id"><option value="">＋ New partner</option>@foreach($partners as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></label>
        <label class="f" style="flex:1 1 160px">Name<input type="text" name="name" required></label>
        <label class="f" style="flex:1 1 140px">Investment (৳)<input type="number" step="1" min="0" name="investment" required></label>
        <button class="btn" type="submit">Save</button>
      </form>
    </div>
    <form class="card pad" method="post" action="{{ route('accounts.expense') }}" style="display:flex;flex-direction:column;gap:12px">@csrf
      <h2 style="margin:0;font-size:var(--fs-15)">Add expense</h2>
      <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <label class="f">Head<select name="head">@foreach(\App\Models\Expense::HEADS as $h)<option>{{ $h }}</option>@endforeach</select></label>
        <label class="f">Amount (৳)<input type="number" step="0.01" min="1" name="amount" required></label>
        <label class="f">Paid from<select name="account_id" required>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></label>
        <label class="f">Date<input type="date" name="expense_date" value="{{ now()->timezone('Asia/Dhaka')->toDateString() }}" required></label>
      </div>
      <label class="f">Note<input type="text" name="note"></label>
      <button class="btn pri" type="submit" @disabled($accounts->isEmpty())>Save expense</button>
      @if($accounts->isEmpty())<span class="sub">Add a money account first (below).</span>@endif
    </form>
  </section>
</div>
<section class="card">
  <div class="pad"><h2 style="margin:0;font-size:var(--fs-15)">Latest expenses</h2></div>
  <div class="dt-wrap"><table class="dt">
    <thead><tr><th>Date</th><th>Head</th><th>Amount</th><th>Paid from</th><th>Note</th><th class="no-sort"></th></tr></thead>
    <tbody>
    @forelse($recent as $x)
      <tr><td class="c">{{ $x->expense_date->format('d-m-Y') }}</td><td>{{ $x->head }}</td><td class="r">{{ $tk($x->amount) }}</td><td>{{ $x->account?->name }}</td><td>{{ $x->note }}</td>
        <td class="c">@if(! $x->reversal_of && ! $x->reversedBy)<details><summary style="cursor:pointer;font-size:var(--fs-12)">Reverse</summary><form method="post" action="{{ route('accounts.expense.reverse', $x) }}" style="display:flex;gap:4px;margin-top:4px">@csrf<input type="text" name="reason" placeholder="Reason" required style="min-height:30px;width:120px"><button class="btn sm" type="submit">OK</button></form></details>@elseif($x->reversedBy)<span class="pill st-cancelled">reversed</span>@endif</td></tr>
    @empty
      <tr><td colspan="6" class="empty">No expenses yet.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
<div id="accounts-setup" class="grid2" style="align-items:start">
  <form class="card pad" method="post" action="{{ route('accounts.account') }}" style="display:flex;flex-direction:column;gap:10px">@csrf
    <h2 style="margin:0;font-size:var(--fs-15)">Add a money account</h2>
    <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
      <label class="f">Name<input type="text" name="name" placeholder="Cash in hand / BRAC Bank / bKash" required></label>
      <label class="f">Type<select name="type">@foreach(\App\Models\MoneyAccount::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
      <label class="f">Balance on opening date (৳)<input type="number" step="0.01" name="opening_balance" required></label>
      <label class="f">Opening date<input type="date" name="opening_date" value="{{ now()->timezone('Asia/Dhaka')->toDateString() }}" required></label>
    </div>
    <span class="sub">Money from sales and payments goes to the first account of each type: cash to Cash, bKash/Nagad to Mobile, bank to Bank. Company deposits come out of the first Bank account.</span>
    <button class="btn pri" type="submit">Add account</button>
  </form>
  <form class="card pad" method="post" action="{{ route('accounts.transfer') }}" style="display:flex;flex-direction:column;gap:10px">@csrf
    <h2 style="margin:0;font-size:var(--fs-15)">Move money between accounts</h2>
    <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
      <label class="f">From<select name="from_account_id">@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></label>
      <label class="f">To<select name="to_account_id">@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></label>
      <label class="f">Amount (৳)<input type="number" step="0.01" min="1" name="amount" required></label>
      <label class="f">Date<input type="date" name="transfer_date" value="{{ now()->timezone('Asia/Dhaka')->toDateString() }}" required></label>
    </div>
    <label class="f">Note<input type="text" name="note" placeholder="e.g. cash deposited to bank"></label>
    <button class="btn" type="submit" @disabled($accounts->count() < 2)>Save transfer</button>
  </form>
</div>
@endsection
