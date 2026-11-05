@extends('layout')
@section('title', 'HR & payroll')
@section('content')
@include('expenses._css')
@php($U = \App\Support\Ui::class)
@php($cnt = fn ($r) => $active->where('role', $r)->count())
@php($gross = $last ? $last->gross() : 0)
@php($lastLbl = $last ? \Carbon\Carbon::parse($last->month.'-01')->format('F') : '—')
@php($advOut = $bal->filter(fn ($b) => $b['advance'] > 0))
@php($loanOut = $bal->filter(fn ($b) => $b['loan'] > 0))
@php($payable = $bal->sum(fn ($b) => max(0, $b['payable'])))
@php($waiting = $claims->where('status', 'submitted'))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>HR &amp; payroll</h1><span class="sub">{{ auth()->user()->roleLabel() }} view · salary data is confidential · {{ $last ? $lastLbl.' '.substr($last->month, 0, 4).' payroll' : 'no payroll approved yet' }}</span></div>
  <button class="btn" type="button" onclick="window.print()">Export ▾</button>
  <a class="btn pri" href="{{ route('hr.payroll', ['m' => $next?->month ?? $now->format('Y-m')]) }}">Run {{ \Carbon\Carbon::parse(($next?->month ?? $now->format('Y-m')).'-01')->format('F') }} payroll</a>
</header>
@php($kpis = [
  ['Employees', $active->count(), $cnt('salesman').' salesmen · '.$cnt('driver').' drivers · '.($active->count() - $cnt('salesman') - $cnt('driver')).' staff', 'blue'],
  ['Payroll cost · '.$lastLbl, $U::tk($gross), 'gross', 'info'],
  ['Pending salary', $U::tk($payable), $last && $last->status === 'paid' ? $lastLbl.' paid '.$last->paid_at->timezone('Asia/Dhaka')->format('d-m') : 'approved, not paid', $payable > 0 ? 'warn' : 'pos'],
  ['Advances out', $U::tk($advOut->sum('advance')), $advOut->count().' employees', 'warn'],
  ['Loans out', $U::tk($loanOut->sum('loan')), $loanOut->count().' employee'.($loanOut->count() === 1 ? '' : 's'), 'warn'],
  ['Expenses approved · '.$now->format('M'), $U::tk($approvedNov), '', 'pos'],
  ['Claims waiting', $waiting->count().' · '.$U::tk($waiting->sum('amount')), '', 'vio'],
  ['Employee cost per cylinder', $cylMonth ? $U::tk($gross / $cylMonth) : '—', $last ? $lastLbl.' · '.$U::tk($gross).' ÷ '.number_format($cylMonth) : '', 'blue'],
])
<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
  @foreach($kpis as [$l, $v, $f, $c])
    <div style="background:{{ $U::bg($c) }};border:1px solid #dbe3f0;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:var(--fs-11);font-weight:600;color:#51607a">{{ $l }}</span><span style="font-size:var(--fs-20);line-height:28px;font-weight:700;color:{{ $U::ink($c) }};font-variant-numeric:tabular-nums">{{ $v }}</span><span style="font-size:var(--fs-11);color:#51607a">{{ $f }}</span></div>
  @endforeach
</section>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(340px,100%),1fr));gap:16px">
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:10px">
    <h2 class="h2">Department-wise salary cost · {{ $lastLbl }}</h2>
    @php($dm = max(1, $depts->max() ?? 1))
    @forelse($depts as $n => $v)
      <div style="display:flex;flex-direction:column;gap:4px"><div style="display:flex;font-size:var(--fs-12)"><span style="flex-grow:1;font-weight:600">{{ $n }}</span><span style="font-weight:700">{{ $U::tk($v) }}</span></div>
        <div style="height:10px;border-radius:999px;background:#eef2f9"><div style="height:10px;border-radius:999px;background:#123a86;width:{{ $v / $dm * 100 }}%"></div></div></div>
    @empty
      <p class="sub" style="margin:0">Shown after the first payroll is approved.</p>
    @endforelse
    @if($gross > 0)<span class="bn">{{ $lastLbl }}-এ মোট বেতন খরচ {{ $U::tk($gross) }}, যার {{ $U::bn(round(($depts->first() ?? 0) / $gross * 100)) }}% গেছে {{ $depts->keys()->first() }} বিভাগে।</span>@endif
  </section>
  <section class="card" style="overflow:hidden">
    <div style="padding:16px 20px"><h2 class="h2">Vehicle-wise employee cost · {{ $lastLbl }}</h2></div>
    <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
      <thead><tr><th>Vehicle</th><th>Salesman · driver</th><th class="r">Cost</th><th class="r">Per cylinder sold</th></tr></thead>
      <tbody>
      @forelse($vehRows as $r)
        <tr><td class="c"><b>{{ $r['v'] }}</b></td><td>{{ $r['p'] }}</td><td class="r">{{ $U::tk($r['c']) }}</td><td class="r">{{ $r['per'] !== null ? '৳'.number_format($r['per'], 1) : '—' }}</td></tr>
      @empty
        <tr><td colspan="4" class="empty">Shown after the first payroll is approved.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
</div>

<section class="card" style="padding:20px;display:flex;flex-direction:column;gap:12px">
  <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px"><h2 class="h2" style="flex-grow:1">HR modules</h2><span class="sub" style="margin:0;font-size:var(--fs-12)">Each opens with the same form, approval and history layout as the ERP modules</span></div>
  @php($emp0 = $active->first())
  @php($mods = [
    ['Employee master profile', $active->count().' employees', '#employees'], ['Department & designation', $active->pluck('department')->filter()->unique()->count().' departments', '#employees'],
    ['Salary structure', $active->whereNotNull('basic')->count().' of '.$active->count().' set', '#employees'], ['Attendance & shift', 'days entered in payroll', null],
    ['Leave & holiday', 'leave days in payroll', null], ['Overtime', 'hours in payroll', null], ['Bonus & incentive', 'per payroll line', null],
    ['Expense & reimbursement', $waiting->count().' claims waiting', route('expenses.index')], ['Advance & loan', $U::tk($advOut->sum('advance')).' advances · '.$U::tk($loanOut->sum('loan')).' loan', $emp0 ? route('hr.employee', $emp0) : null],
    ['Monthly payroll', $last ? $lastLbl.' '.\App\Models\Payroll::STATUS[$last->status] : 'not run yet', route('hr.payroll')], ['Salary payment & payslip', $last && $last->status === 'paid' ? 'payslips ready' : '—', $emp0 ? route('hr.employee', $emp0) : null],
    ['Benefits & statutory deductions', '[rules to configure]', null], ['Performance evaluation', 'not set up', null], ['Resignation & final settlement', 'none open', null], ['Employee cost analysis', 'per vehicle, per cylinder', '#'],
  ])
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
    @foreach($mods as $i => [$n, $s, $h])
      <a href="{{ $h ?? route('soon', 'hr') }}" style="border:1px solid #dbe3f0;border-radius:12px;padding:12px;display:flex;gap:10px;align-items:flex-start;text-decoration:none;color:#14213d;background:{{ $h ? '#fff' : '#f4f7fc' }}">
        <span style="font-size:var(--fs-11);font-weight:700;color:#1d4ed8;background:#e6eeff;border-radius:8px;padding:4px 6px">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
        <span style="display:flex;flex-direction:column;gap:2px"><b style="font-size:var(--fs-13)">{{ $n }}</b><span style="font-size:var(--fs-11);color:#51607a">{{ $s }}</span></span></a>
    @endforeach
  </div>
</section>

<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px;display:flex;align-items:center"><h2 class="h2" style="flex-grow:1">Pending expense claims</h2><span class="sub" style="margin:0;font-size:var(--fs-12)">Manager approves → Accounts pays</span></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Claim</th><th>Employee</th><th>Type</th><th>Date</th><th>Vehicle / route</th><th class="r">Amount</th><th>Receipt</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($claims as $c)
      @php($st = ['draft' => 'Draft', 'submitted' => 'Pending', 'posted' => 'Approved'][$c->status])
      <tr><td class="nw"><a href="{{ route('expenses.index') }}">{{ $c->claim_no }}</a></td><td>{{ $c->extra['employee'] ?? '—' }}</td><td>{{ $c->extra['type'] ?? $c->category->name }}</td><td class="nw">{{ $c->expense_date->format('d-m') }}</td><td>{{ $c->trip?->vehicle?->code ?? $c->cost_center ?? '—' }}</td><td class="r">{{ $U::tk($c->amount) }}</td>
        <td class="c">@if($c->receipt_path)<a href="{{ route('expenses.receipt', $c) }}" target="_blank">{{ str_ends_with($c->receipt_path, '.pdf') ? 'PDF' : 'Photo' }}</a>@else — @endif</td>
        <td class="c"><span style="font-size:var(--fs-11);font-weight:700;padding:3px 9px;border-radius:999px;{{ ['Pending' => 'background:#fff1d6;color:#8a4b00', 'Approved' => 'background:#e6eeff;color:#1d4ed8', 'Draft' => 'background:#eef2f9;color:#51607a'][$st] }}">{{ $st }}</span></td></tr>
    @empty
      <tr><td colspan="8" class="empty">No expense claims waiting.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>

<section class="card" id="employees" style="overflow:hidden">
  <div style="padding:16px 20px;display:flex;align-items:center"><h2 class="h2" style="flex-grow:1">Employees</h2><a class="btn sm" href="{{ route('fleet.vehicles') }}#staff-form">＋ Employee</a></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>ID</th><th>Name</th><th>Role</th><th>Department</th><th class="r">Monthly salary</th><th class="r">Advance</th><th class="r">Loan</th><th class="r">Salary payable</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($emps as $e)
      <tr><td class="c">{{ $e->code }}</td><td><a href="{{ route('hr.employee', $e) }}"><b>{{ $e->name }}</b></a></td><td>{{ \App\Models\Employee::ROLES[$e->role] ?? $e->role }}</td><td>{{ $e->department ?: '—' }}</td>
        <td class="r">{{ $e->basic !== null ? $U::tk($e->monthly()) : 'not set' }}</td><td class="r">{{ $U::tk($bal[$e->id]['advance']) }}</td><td class="r">{{ $U::tk($bal[$e->id]['loan']) }}</td><td class="r">{{ $U::tk($bal[$e->id]['payable']) }}</td>
        <td class="c"><span class="pill" style="{{ $e->is_active ? 'background:#e3f6ec;color:#0f6b3c' : '' }}">{{ $e->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
    @empty
      <tr><td colspan="9" class="empty">No employees yet. Add drivers, salesmen and staff in Vehicles &amp; routes.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@endsection
