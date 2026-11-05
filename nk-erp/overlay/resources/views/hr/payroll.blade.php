@extends('layout')
@section('title', 'Payroll · '.\Carbon\Carbon::parse($m.'-01')->format('F Y'))
@section('content')
@include('expenses._css')
@php($U = \App\Support\Ui::class)
@php($u = auth()->user())
@php($mName = \Carbon\Carbon::parse($m.'-01')->format('F Y'))
@php($L = $p ? $p->lines->sortBy(fn ($l) => $l->employee->name) : collect())
@php($sum = fn ($f) => (float) $L->sum($f))
@php($basic = $sum('basic') + $sum('allowance'))
@php($gross = $basic + $sum('commission') + $sum('overtime') + $sum('bonus'))
@php($edit = $p && in_array($p->status, ['draft', 'checked'], true))
@php($stIdx = ! $p ? 0 : ['draft' => 6, 'checked' => 7, 'approved' => 8, 'paid' => 9][$p->status])
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Payroll · {{ $mName }}</h1><span class="sub">{{ $p ? $L->count() : $count }} employees · nothing posts to the ledger until approved</span></div>
  <form method="get" style="display:flex;gap:6px"><input type="month" name="m" value="{{ $m }}" aria-label="Month"><button class="btn" type="submit">Show</button></form>
  <button class="btn" type="button" onclick="window.print()">Salary sheet PDF</button>
  @if($p && $p->status === 'checked' && $u->isSuperAdmin())
    <form method="post" action="{{ route('hr.payroll.approve', $p) }}" onsubmit="return confirm('Approve payroll {{ $m }}? It will post to every employee ledger.')">@csrf<button class="btn pri" type="submit">Approve payroll</button></form>
  @else
    <button class="btn pri" type="button" disabled title="{{ ! $p ? 'Prepare first' : ($p->status === 'draft' ? 'Must be checked first' : ($p->status === 'checked' ? 'Super Admin approves' : 'Already approved')) }}">Approve payroll</button>
  @endif
</header>

<section class="card" style="padding:14px 20px;display:flex;flex-wrap:wrap;gap:6px;align-items:center">
  @foreach(['Attendance', 'Leave', 'Overtime', 'Commission', 'Bonus', 'Advances', 'Review', 'Approve', 'Post & pay'] as $i => $l)
    <span style="font-size:var(--fs-12);font-weight:700;padding:6px 12px;border-radius:999px;{{ $i < $stIdx - 1 || ($p && $p->status === 'paid') ? 'background:#e3f6ec;color:#0f6b3c' : ($i === $stIdx - 1 ? 'background:#8a4b00;color:#fff' : 'background:#eef2f9;color:#51607a') }}">{{ $l }}{{ $i < 6 && $p ? ' ✓' : '' }}</span>
  @endforeach
</section>

@if(! $p)
  <section class="card pad" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
    <span style="flex-grow:1">No payroll for {{ $mName }} yet. Preparing it takes each active employee's salary structure, cylinders sold on his trips (commission) and approved expense claims paid with salary.</span>
    <form method="post" action="{{ route('hr.payroll.prepare') }}">@csrf<input type="hidden" name="m" value="{{ $m }}"><button class="btn pri" type="submit">Prepare {{ \Carbon\Carbon::parse($m.'-01')->format('F') }} payroll</button></form>
  </section>
@else
@php($tot = [['Basic + allowances', $basic, 'gray'], ['Commission', $sum('commission'), 'vio'], ['Overtime', $sum('overtime'), 'blue'], ['Gross', $gross, 'blue'], ['Advance recovery', -($sum('advance_recovery') + $sum('loan_recovery')), 'warn'], ['Deductions', $sum('deductions'), 'gray'], ['Net pay', $sum('net'), 'pos']])
<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px">
  @foreach($tot as [$l, $v, $c])
    <div style="background:{{ $U::bg($c) }};border:1px solid #dbe3f0;border-radius:12px;padding:12px;display:flex;flex-direction:column"><span style="font-size:var(--fs-11);font-weight:600;color:#51607a">{{ $l }}</span><span style="font-size:var(--fs-20);font-weight:700;color:{{ $U::ink($c) }};font-variant-numeric:tabular-nums">{{ $U::tk($v) }}</span></div>
  @endforeach
</section>

<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <form class="card" method="post" action="{{ route('hr.payroll.lines', $p) }}" style="flex:999 1 640px;min-width:0;overflow:hidden">@csrf
    <div style="padding:16px 20px;display:flex;align-items:center;gap:8px"><h2 class="h2" style="flex-grow:1">Salary sheet</h2><span class="sub" style="margin:0;font-size:var(--fs-12)">showing {{ $L->count() }} of {{ $L->count() }}</span>
      @if($edit)<button class="btn sm" type="submit">Save changes</button>@endif</div>
    <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
      <thead><tr><th>Employee</th><th>Dept.</th><th class="r">Days</th><th class="r">Basic + allow.</th><th class="r">Commission</th><th class="r">Overtime</th><th class="r">Bonus</th><th class="r">Reimb.</th><th class="r">Advance rec.</th><th class="r">Deductions</th><th class="r">Net pay</th></tr></thead>
      <tbody>
      @foreach($L as $l)
        @php($in = fn ($f, $w = 70) => $edit ? '<input type="number" min="0" step="0.01" name="l['.$l->id.']['.$f.']" value="'.e((float) $l->$f).'" style="width:'.$w.'px;min-height:30px;text-align:right">' : e($U::tk($l->$f)))
        <tr><td><a href="{{ route('hr.employee', $l->employee) }}">{{ $l->employee->name }}</a></td><td>{{ $l->employee->department ?: '—' }}</td>
          <td class="r">@if($edit)<input type="number" min="0" max="31" name="l[{{ $l->id }}][days]" value="{{ $l->days }}" style="width:56px;min-height:30px;text-align:right"><br><input type="number" min="0" max="31" name="l[{{ $l->id }}][leave_days]" value="{{ $l->leave_days }}" title="Leave days" style="width:56px;min-height:26px;text-align:right;font-size:var(--fs-11)">@else{{ $l->days }}@endif</td>
          <td class="r">{{ $U::tk((float) $l->basic + (float) $l->allowance) }}</td>
          <td class="r">{{ (float) $l->commission ? $U::tk($l->commission) : '—' }}@if($l->cylinders)<br><span class="sub" style="font-size:var(--fs-10)">{{ $l->cylinders }} cyl</span>@endif</td>
          <td class="r">{!! $in('overtime') !!}@if($edit)<br><input type="number" min="0" step="0.5" name="l[{{ $l->id }}][overtime_hours]" value="{{ (float) $l->overtime_hours }}" title="Overtime hours" style="width:70px;min-height:26px;text-align:right;font-size:var(--fs-11)">@endif</td>
          <td class="r">{!! $in('bonus') !!}</td><td class="r">{{ (float) $l->reimbursement ? $U::tk($l->reimbursement) : '—' }}</td>
          <td class="r">{!! $in('advance_recovery') !!}@if($edit)<br><input type="number" min="0" step="0.01" name="l[{{ $l->id }}][loan_recovery]" value="{{ (float) $l->loan_recovery }}" title="Loan recovery" style="width:70px;min-height:26px;text-align:right;font-size:var(--fs-11)">@endif</td>
          <td class="r">{!! $in('deductions') !!}</td><td class="r" style="font-weight:700">{{ $U::tk($l->net) }}</td></tr>
      @endforeach
        <tr class="tot" style="font-weight:700;background:#e6eeff"><td colspan="3">All {{ $L->count() }} employees</td><td class="r">{{ $U::tk($basic) }}</td><td class="r">{{ $U::tk($sum('commission')) }}</td><td class="r">{{ $U::tk($sum('overtime')) }}</td><td class="r">{{ $U::tk($sum('bonus')) }}</td><td class="r">{{ $U::tk($sum('reimbursement')) }}</td><td class="r">{{ $U::tk($sum('advance_recovery') + $sum('loan_recovery')) }}</td><td class="r">{{ $U::tk($sum('deductions')) }}</td><td class="r">{{ $U::tk($sum('net')) }}</td></tr>
      </tbody>
    </table></div>
    @if($edit)<p class="sub" style="margin:0 20px 14px;font-size:var(--fs-11)">Small grey boxes: leave days under Days, hours under Overtime, loan recovery under Advance rec. Saving sends the sheet back for checking.</p>@endif
  </form>

  <aside style="flex:1 1 320px;min-width:290px;display:flex;flex-direction:column;gap:16px">
    <section class="card" style="overflow:hidden">
      <div style="padding:14px 16px"><h2 class="h2">Ledger posting after approval</h2></div>
      <div class="dt-wrap" style="margin:0 16px"><table class="dt">
        <thead><tr><th>Account</th><th class="r">Debit</th><th class="r">Credit</th></tr></thead>
        <tbody>
          <tr><td>6110 Salary &amp; wages expense</td><td class="r">{{ $U::tk($gross) }}</td><td></td></tr>
          <tr><td>2210 Salary payable</td><td></td><td class="r">{{ $U::tk($sum('net') - $sum('reimbursement')) }}</td></tr>
          <tr><td>1410 Employee advances &amp; loans</td><td></td><td class="r">{{ $U::tk($sum('advance_recovery') + $sum('loan_recovery')) }}</td></tr>
          @if($sum('deductions') > 0)<tr><td>Other income · deductions</td><td></td><td class="r">{{ $U::tk($sum('deductions')) }}</td></tr>@endif
          <tr><td colspan="3" style="font-size:var(--fs-11);color:#51607a;background:#f4f7fc">On payment day</td></tr>
          <tr><td>2210 Salary payable</td><td class="r">{{ $U::tk($sum('net') - $sum('reimbursement')) }}</td><td></td></tr>
          <tr><td>{{ $p->account?->name ?? 'Bank / cash account' }}</td><td></td><td class="r">{{ $U::tk($sum('net') - $sum('reimbursement')) }}</td></tr>
        </tbody>
      </table></div>
      <div style="padding:10px 16px;font-size:var(--fs-11);color:#51607a;line-height:16px">Expense reimbursements ({{ $U::tk($sum('reimbursement')) }} this month) post separately to 2220 Reimbursement payable, so they are never counted as salary. Salary expense is posted once, here only.</div>
    </section>
    <section class="card" style="padding:16px;display:flex;flex-direction:column;gap:8px">
      <h2 class="h2">Approval</h2>
      @php($stepRow = fn ($done, $label, $who, $when) => compact('done', 'label', 'who', 'when'))
      @foreach([
        $stepRow(true, 'Prepared', $p->preparer?->name.' ('.$p->preparer?->roleLabel().')', $p->prepared_at),
        $stepRow((bool) $p->checked_by, 'Checked', $p->checker ? $p->checker->name.' ('.$p->checker->roleLabel().')' : 'Admin or Accounts', $p->checked_at),
        $stepRow((bool) $p->approved_by, 'Approve', $p->approver ? $p->approver->name : 'Super Admin', $p->approved_at),
        $stepRow($p->status === 'paid', 'Post to ledger → pay → payslips', $p->account?->name, $p->paid_at),
      ] as $i => $s)
        <div style="display:flex;gap:10px;font-size:var(--fs-12);align-items:flex-start"><span style="font-weight:700;color:{{ $s['done'] ? '#0f6b3c' : '#8a4b00' }}">{{ $s['done'] ? '✓' : ($i === $stIdx - 6 ? '●' : '○') }}</span>
          <span style="flex-grow:1"><b>{{ $s['label'] }}</b>{{ $s['who'] ? ' · '.$s['who'] : '' }}</span><span style="color:#51607a">{{ $s['when'] ? $s['when']->timezone('Asia/Dhaka')->format('d-m H:i') : ($s['done'] ? '' : 'waiting') }}</span></div>
      @endforeach
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">
        @if($edit)<form method="post" action="{{ route('hr.payroll.prepare') }}">@csrf<input type="hidden" name="m" value="{{ $m }}"><button class="btn sm" type="submit" title="Reload salaries, commission and claims">Recalculate</button></form>@endif
        @if($p->status === 'draft' && in_array($u->role, ['super_admin', 'admin', 'accounts'], true))<form method="post" action="{{ route('hr.payroll.check', $p) }}">@csrf<button class="btn sm" type="submit">Mark checked</button></form>@endif
        @if($p->status === 'approved')
          <form method="post" action="{{ route('hr.payroll.pay', $p) }}" style="display:flex;gap:6px" onsubmit="return confirm('Pay all salaries now?')">@csrf<select name="account_id" style="width:auto;min-height:32px">@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select><button class="btn sm pri" type="submit">Pay salaries</button></form>
        @endif
      </div>
    </section>
    @if($changes->isNotEmpty())
      <div style="background:#fff1d6;color:#8a4b00;border-radius:12px;padding:12px;font-size:var(--fs-12);line-height:18px">Salary change{{ $changes->count() > 1 ? 's' : '' }} since last month: @foreach($changes as $c){{ \Illuminate\Support\Str::after($c->details, ' ') }} (approved {{ $c->created_at->timezone('Asia/Dhaka')->format('d-m') }} by {{ $c->user?->name }})@if(! $loop->last); @endif @endforeach. Shown here so nothing changes silently.</div>
    @endif
  </aside>
</div>
@endif
@endsection
