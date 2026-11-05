@extends('layout')
@section('title', 'Expense management')
@section('content')
@include('expenses._css')
@php($U = \App\Support\Ui::class)
@php($canApprove = in_array(auth()->user()->role, ['super_admin', 'admin', 'manager'], true))
@php($canPay = in_array(auth()->user()->role, ['super_admin', 'admin', 'manager', 'accounts'], true))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:10px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Expense management</h1><span class="sub">Finance &amp; Accounts → Expenses · {{ $label }}</span></div>
  <form method="get" style="display:contents" onchange="this.submit()">
    <select class="sel" name="r" aria-label="Date range"><option value="month" @selected($range === 'month')>This month</option><option value="last" @selected($range === 'last')>Last month</option><option value="year" @selected($range === 'year')>This year</option></select>
    <select class="sel" name="cat" aria-label="Category"><option value="">All categories</option>@foreach($groups as $g)<option value="{{ $g->id }}" @selected(request('cat') == $g->id)>{{ $g->name }}</option>@endforeach</select>
    <select class="sel" name="cc" aria-label="Cost center"><option value="">All cost centers</option>@foreach($ccs as $c)<option @selected(request('cc') === $c)>{{ $c }}</option>@endforeach</select>
    <select class="sel" name="st" aria-label="Payment status"><option value="">Any status</option>@foreach(\App\Models\ExpenseClaim::FLOW as $k => $l)<option value="{{ $k }}" @selected(request('st') === $k)>{{ $l }}</option>@endforeach</select>
  </form>
  @if(auth()->user()->role !== 'salesman')<a class="btn" href="{{ route('expenses.setup') }}">Settings</a>@endif
  <a class="btn pri" href="{{ route('expenses.create') }}">＋ Add expense</a>
</header>

@php($over = $budgets->filter(fn ($b) => $b['a'] / $b['b'] > $pace + 0.05))
@php($kpis = [
  ["Today's expenses", $U::tk($todayRows->sum('amount')), $todayRows->pluck('veh')->filter()->unique()->count().' vehicles', 'blue'],
  ['This month', $U::tk($month), '1–'.$now->day.' '.$now->format('M').' · payroll at month end', 'info'],
  ['Last month', $U::tk($last), $now->copy()->subMonthNoOverflow()->format('F'), 'gray'],
  ['Year to date', $U::tk($ytd), 'entries in this system only', 'gray'],
  ['Pending approval', $pending->count().' · '.$U::tk($pending->sum('amount')), '', 'vio'],
  ['Unpaid', $U::tk($unpaid->sum('amount')), $unpaid->count().' bills', 'warn'],
  ['Budget variance', $budgets->isEmpty() ? 'No budget' : ($over->isEmpty() ? 'On track' : $over->count().' ahead'), $budgets->isEmpty() ? 'set budgets in Settings' : ($over->isEmpty() ? 'within pace' : $over->pluck('n')->implode(', ').' ahead of pace'), $over->isEmpty() ? 'pos' : 'warn'],
])
<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
  @foreach($kpis as [$l, $v, $f, $c])
    <div style="background:{{ $U::bg($c) }};border:1px solid #dbe3f0;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:var(--fs-11);font-weight:600;color:#51607a">{{ $l }}</span><span style="font-size:var(--fs-20);line-height:28px;font-weight:700;color:{{ $U::ink($c) }};font-variant-numeric:tabular-nums">{{ $v }}</span><span style="font-size:var(--fs-11);color:#51607a">{{ $f }}</span></div>
  @endforeach
</section>

@php($tot = $rows->sum('amount'))
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px">
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:10px">
    <h2 class="h2">Expense by category · {{ $label }}</h2>
    @php($mx = max(1, $byGroup->max('v') ?? 1))
    @forelse($byGroup as $c)
      <div style="display:flex;flex-direction:column;gap:4px">
        <div style="display:flex;font-size:var(--fs-12)"><span style="flex-grow:1;font-weight:600">{{ $c['n'] }}</span><span style="font-variant-numeric:tabular-nums;font-weight:700">{{ $U::tk($c['v']) }}</span><span style="width:44px;text-align:right;color:#51607a">{{ $tot ? round($c['v'] / $tot * 100) : 0 }}%</span></div>
        <div style="height:10px;border-radius:999px;background:#eef2f9"><div style="height:10px;border-radius:999px;background:#123a86;width:{{ $c['v'] / $mx * 100 }}%"></div></div>
      </div>
    @empty
      <p class="sub" style="margin:0">No expenses in this period.</p>
    @endforelse
    @if($tot > 0)
      @php($topG = $byGroup->first())
      <span class="bn">এই সময়ে খরচ {{ $U::tk($tot) }}। এর {{ $U::bn(round($topG['v'] / $tot * 100)) }}% গেছে {{ $topG['n'] }} খাতে। বেতন মাস শেষে Payroll থেকে আসবে।</span>
    @endif
  </section>
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:10px">
    <h2 class="h2">Budget vs actual · {{ $now->format('F') }}</h2>
    @forelse($budgets as $b)
      @php($pc = round($b['a'] / $b['b'] * 100))
      @php($cl = $pc >= $alert1 ? ['#fff1d6', '#8a4b00'] : ['#e3f6ec', '#0f6b3c'])
      <div style="display:flex;flex-direction:column;gap:4px">
        <div style="display:flex;font-size:var(--fs-12);align-items:center"><span style="flex-grow:1;font-weight:600">{{ $b['n'] }}</span><span style="font-variant-numeric:tabular-nums">{{ $U::tk($b['a']) }} of {{ $U::tk($b['b']) }}</span><span style="margin-left:8px;font-size:var(--fs-11);font-weight:700;padding:1px 8px;border-radius:999px;background:{{ $cl[0] }};color:{{ $cl[1] }}">{{ $pc }}%</span></div>
        <div style="position:relative;height:10px;border-radius:999px;background:#eef2f9"><div style="height:10px;border-radius:999px;width:{{ min($pc, 100) }}%;background:{{ $pc >= $alert2 ? '#c46f00' : '#1f9d55' }}"></div><span style="position:absolute;left:{{ round($pace * 100, 1) }}%;top:-3px;width:2px;height:16px;background:#14213d" aria-hidden="true"></span></div>
      </div>
    @empty
      <p class="sub" style="margin:0">No monthly budgets yet. Set them per category in <a href="{{ route('expenses.setup') }}">Expense settings</a>.</p>
    @endforelse
    <span style="font-size:var(--fs-11);color:#51607a">Black mark = where spending should be after {{ $now->day }} of {{ $now->daysInMonth }} days. Alerts at {{ $alert1 }}% and {{ $alert2 }}% (set in Settings).</span>
    @if($over->isNotEmpty())@php($o = $over->first())<span class="bn">{{ $o['n'] }} খরচ মাসের হিসাবে আগে চলছে: {{ $U::bn($now->day) }} দিনে বাজেটের {{ $U::bn(round($o['a'] / $o['b'] * 100)) }}% খরচ হয়েছে, যেখানে স্বাভাবিক {{ $U::bn(round($pace * 100)) }}%।</span>
    @elseif($budgets->isNotEmpty())<span class="bn">সব খাতের খরচ বাজেটের মধ্যে আছে।</span>@endif
  </section>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px">
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:10px">
    <h2 class="h2">Vehicle-wise expense · {{ $label }}</h2>
    @php($vm = max(1, $veh->max('t') ?? 1))
    @php($topV = $veh->sortByDesc('t')->first())
    <div role="img" aria-label="Vehicle expenses bar chart" style="display:flex;align-items:flex-end;gap:10px;height:150px;border-bottom:1px solid #dbe3f0;padding:0 4px">
      @foreach($veh as $v)
        <a href="{{ route('expenses.index', ['r' => $range, 'cc' => 'Vehicle '.$v['v']]) }}" title="See {{ $v['v'] }} by category" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;text-decoration:none;color:#14213d"><span style="font-size:var(--fs-10);font-weight:700;font-variant-numeric:tabular-nums">{{ $v['t'] ? number_format($v['t'] / 1000, 1).'k' : '0' }}</span><div style="width:100%;max-width:36px;border-radius:6px 6px 0 0;background:{{ $topV && $v['v'] === $topV['v'] && $v['t'] ? '#c46f00' : '#123a86' }};height:{{ max(2, $v['t'] / $vm * 120) }}px"></div></a>
      @endforeach
    </div>
    <div style="display:flex;gap:10px;padding:0 4px">@foreach($veh as $v)<span style="flex:1;text-align:center;font-size:var(--fs-11);color:#51607a">{{ $v['v'] }}</span>@endforeach</div>
    @if($topV && $topV['t'])<span class="bn">সবচেয়ে বেশি খরচ {{ $topV['v'] }}-এর ({{ $U::tk($topV['t']) }})। কোন খাতে কত গেছে দেখতে বারে চাপুন।</span>@elseif($veh->isEmpty())<p class="sub" style="margin:0">No vehicles yet.</p>@endif
  </section>
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:10px">
    <h2 class="h2">Monthly trend</h2>
    @php($tm = max(1, $trend->max('v')))
    <div role="img" aria-label="Monthly expense trend" style="display:flex;align-items:flex-end;gap:24px;height:150px;border-bottom:1px solid #dbe3f0;padding:0 20px">
      @foreach($trend as $i => $t)
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px"><span style="font-size:var(--fs-11);font-weight:700">{{ $t['v'] ? $U::tk($t['v']) : 'no entries' }}</span><div style="width:56px;height:{{ max(4, $t['v'] / $tm * 120) }}px;border-radius:6px 6px 0 0;background:{{ $i === 2 ? '#9db4e3' : '#123a86' }}"></div></div>
      @endforeach
    </div>
    <div style="display:flex;gap:24px;padding:0 20px;font-size:var(--fs-11);color:#51607a">@foreach($trend as $t)<span style="flex:1;text-align:center">{{ $t['m'] }}</span>@endforeach</div>
    <span style="font-size:var(--fs-11);color:#51607a">Payroll is added once from the Payroll module when it runs; earlier months show only what is entered here.</span>
  </section>
</div>

<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px;display:flex;align-items:center"><h2 class="h2" style="flex-grow:1">Expense entries &amp; approvals</h2><span class="sub" style="margin:0;font-size:var(--fs-12)">Approval and payment are separate steps.</span></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>No.</th><th>Date</th><th>Category</th><th>Cost center</th><th>Description</th><th class="r">Amount</th><th>Payment</th><th>Status</th><th class="no-sort">Action</th></tr></thead>
    <tbody>
    @forelse($claims as $c)
      @php($stl = \App\Models\ExpenseClaim::FLOW[$c->status])
      <tr><td class="nw">{{ $c->claim_no }}</td><td class="nw">{{ $c->expense_date->format('d-m-Y') }}</td><td>{{ $c->category->name }}</td><td>{{ $c->cost_center ?: '—' }}</td>
        <td>{{ $c->description }}@if($c->receipt_path) · <a href="{{ route('expenses.receipt', $c) }}" target="_blank">receipt</a>@endif @if($c->reject_reason)<br><span style="color:#b3261e;font-size:var(--fs-11)">{{ $c->reject_reason }}</span>@endif</td>
        <td class="r">{{ $U::tk($c->amount) }}</td><td>{{ \App\Models\ExpenseClaim::PAYMENTS[$c->payment] }}</td>
        <td class="c"><span class="pill" style="{{ ['Draft' => 'background:#eef2f9;color:#51607a', 'Pending approval' => 'background:#efe7fb;color:#5b2fa8', 'Approved' => 'background:#e6eeff;color:#1d4ed8', 'Posted' => 'background:#fff1d6;color:#8a4b00', 'Paid' => 'background:#e3f6ec;color:#0f6b3c', 'Rejected' => 'background:#fde9e7;color:#b3261e'][$stl] }}">{{ $c->status === 'posted' ? 'Approved · unpaid' : $stl }}</span></td>
        <td class="nw" style="font-size:var(--fs-12)">
          @if($c->status === 'draft')<a href="{{ route('expenses.create', ['id' => $c->id]) }}">Edit</a>@endif
          @if($c->status === 'submitted' && $canApprove)
            <form method="post" action="{{ route('expenses.approve', $c) }}" style="display:inline">@csrf<button class="btn sm pri" type="submit">Approve</button></form>
            <form method="post" action="{{ route('expenses.reject', $c) }}" style="display:inline" onsubmit="const r=prompt('Reason for rejecting?');if(!r)return false;this.reason.value=r">@csrf<input type="hidden" name="reason"><button class="btn sm" type="submit">Reject</button></form>
          @endif
          @if($c->status === 'posted' && $canPay)
            <form method="post" action="{{ route('expenses.pay', $c) }}" style="display:inline-flex;gap:4px">@csrf<select name="account_id" style="min-height:32px;width:auto">@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select><button class="btn sm pri" type="submit">Pay</button></form>
          @endif
          @if($c->approver)<span class="sub" style="font-size:var(--fs-11)">by {{ $c->approver->name }}</span>@endif
        </td></tr>
    @empty
      <tr><td colspan="9" class="empty">No expense entries yet. Add one with ＋ Add expense.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@endsection
