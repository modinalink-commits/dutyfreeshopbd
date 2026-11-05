@extends('layout')
@section('title', $e->name.' · employee ledger')
@section('content')
@include('expenses._css')
@php($U = \App\Support\Ui::class)
@php($isSa = auth()->user()->isSuperAdmin())
@php($ini = collect(explode(' ', $e->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''))
@php($mLbl = $line ? \Carbon\Carbon::parse($line->payroll->month.'-01')->format('M') : null)
<header class="card" style="padding:20px;display:flex;flex-wrap:wrap;align-items:center;gap:16px">
  <span style="width:56px;height:56px;border-radius:999px;background:#123a86;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700">{{ strtoupper($ini) }}</span>
  <div style="flex-grow:1;display:flex;flex-direction:column;gap:2px"><h1 style="font-size:var(--fs-20)">{{ $e->name }}</h1>
    <span style="font-size:var(--fs-12);color:#51607a">{{ $e->code }} · {{ \App\Models\Employee::ROLES[$e->role] ?? $e->role }} · {{ $e->department ?: 'no department' }}{{ $vehicle ? ' · '.$vehicle : '' }} · joined {{ $e->joined_on?->format('d-m-Y') ?? '[date]' }} · confidential</span></div>
  <a class="btn" href="#payslip">Payslip{{ $mLbl ? ' · '.$mLbl : '' }}</a>
  <button class="btn" type="button" onclick="window.print()">Export ▾</button>
  <a class="btn" href="{{ route('hr.index') }}">All employees</a>
</header>
@php($cards = [
  ['Monthly salary', $e->basic !== null ? $U::tk($e->monthly()) : 'Not set', 'basic '.$U::tk($e->basic ?? 0).' + allowance '.$U::tk($e->allowance ?? 0), '#e6eeff', '#1d4ed8'],
  ['Commission · '.now()->timezone('Asia/Dhaka')->format('M'), $e->commission_rate ? $U::tk($cyl * $e->commission_rate) : '—', $cyl.' cylinders · '.($e->commission_rate ? '৳'.rtrim(rtrim(number_format($e->commission_rate, 2), '0'), '.').' each' : '[rule]'), '#efe7fb', '#5b2fa8'],
  ['Advance balance', $U::tk($bal['advance']), $line && $line->advance_recovery > 0 ? $U::tk($line->advance_recovery).' recovered in '.$mLbl : '', '#fff1d6', '#8a4b00'],
  ['Loan balance', $U::tk($bal['loan']), '', '#eef2f9', '#14213d'],
  ['Unpaid salary', $U::tk($bal['payable']), $line && $line->payroll->status === 'paid' ? $mLbl.' paid '.$line->payroll->paid_at->timezone('Asia/Dhaka')->format('d-m') : '', '#e3f6ec', '#0f6b3c'],
])
<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
  @foreach($cards as [$l, $v, $f, $bg, $ink])
    <div style="background:{{ $bg }};border:1px solid #dbe3f0;border-radius:12px;padding:12px;display:flex;flex-direction:column"><span style="font-size:var(--fs-11);font-weight:600;color:#51607a">{{ $l }}</span><span style="font-size:var(--fs-20);font-weight:700;color:{{ $ink }};font-variant-numeric:tabular-nums">{{ $v }}</span><span style="font-size:var(--fs-11);color:#51607a">{{ $f }}</span></div>
  @endforeach
</section>

<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px;display:flex;align-items:center"><h2 class="h2" style="flex-grow:1">Ledger{{ $rows->isNotEmpty() ? ' · '.$rows->first()->entry_date->format('F').' – '.$rows->last()->entry_date->format('F Y') : '' }}</h2><span style="font-size:var(--fs-11);color:#51607a">Posted lines are locked · fixes by adjustment entry</span></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Date</th><th>Entry</th><th>Reference</th><th class="r">Earned</th><th class="r">Deducted / paid</th><th class="r">Salary payable</th><th class="r">Advance balance</th></tr></thead>
    <tbody>
    @php($p = 0) @php($a = 0)
    @forelse($rows as $r)
      @php($p += (float) $r->earned - (float) $r->deducted) @php($a += (float) $r->advance_delta)
      <tr @if($r->kind === 'opening') style="background:#f4f7fc" @endif><td class="nw">{{ $r->entry_date->format('d-m') }}</td><td style="font-weight:600">{{ \App\Models\EmployeeLedger::KINDS[$r->kind] }}{{ $r->note ? ' · '.$r->note : '' }}{{ $r->account ? ' ('.$r->account->name.')' : '' }}</td><td style="color:#51607a">{{ $r->reference }}</td>
        <td class="r" style="color:#0f6b3c">{{ (float) $r->earned ? $U::tk($r->earned) : '' }}</td><td class="r" style="color:#b3261e">{{ (float) $r->deducted ? $U::tk($r->deducted) : '' }}</td><td class="r" style="font-weight:700">{{ $U::tk($p) }}</td><td class="r">{{ $U::tk($a) }}</td></tr>
    @empty
      <tr><td colspan="7" class="empty">No ledger lines yet.</td></tr>
    @endforelse
    @foreach($pendingClaims as $c)
      <tr style="background:#f4f7fc"><td class="nw">{{ $c->expense_date->format('d-m') }}</td><td style="font-weight:600">Expense claim · {{ $c->extra['type'] ?? $c->category->name }}</td><td style="color:#51607a">{{ $c->claim_no }}</td><td class="r" style="color:#0f6b3c">{{ $U::tk($c->amount) }} pending</td><td></td><td class="r">{{ $U::tk($p) }}</td><td class="r">{{ $U::tk($a) }}</td></tr>
    @endforeach
    </tbody>
  </table></div>
</section>
@if($line)
  @php($earn = $line->gross() + (float) $line->reimbursement)
  <span class="bn">{{ $mLbl }}-এ {{ $e->name }} মোট আয় করেছেন {{ $U::tk($earn) }}।@if($line->advance_recovery > 0) এর থেকে অগ্রিমের {{ $U::tk($line->advance_recovery) }} কেটে @endif {{ $U::tk($line->net) }} {{ $line->payroll->status === 'paid' ? 'দেওয়া হয়েছে' : 'দেওয়া বাকি' }}।@if($bal['advance'] > 0) অগ্রিমের বাকি {{ $U::tk($bal['advance']) }}।@endif</span>
@endif

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;align-items:start">
  <section class="card" id="payslip" style="padding:20px;display:flex;flex-direction:column">
    <h2 class="h2" style="margin-bottom:6px">{{ $line ? \Carbon\Carbon::parse($line->payroll->month.'-01')->format('F') : 'Latest' }} payslip</h2>
    @if($line)
      @foreach([['Basic', $line->basic], ['Allowances', $line->allowance], ['Commission (approved)', $line->commission], ['Overtime · bonus', (float) $line->overtime + (float) $line->bonus], ['Expense reimbursement', $line->reimbursement], ['(−) Advance recovery', -$line->advance_recovery], ['(−) Loan recovery', -$line->loan_recovery], ['(−) Authorized deductions', -$line->deductions]] as [$k, $v])
        <div style="display:flex;font-size:var(--fs-13);padding:6px 0"><span style="flex-grow:1;color:#51607a">{{ $k }}</span><span style="font-variant-numeric:tabular-nums">{{ $U::tk($v) }}</span></div>
      @endforeach
      <div style="display:flex;font-size:var(--fs-14);font-weight:700;padding:9px 0 0;border-top:1px solid #dbe3f0"><span style="flex-grow:1">Net {{ $line->payroll->status === 'paid' ? 'paid' : 'payable' }}</span><span>{{ $U::tk($line->net) }}</span></div>
    @else
      <p class="sub" style="margin:0">No approved payroll yet.</p>
    @endif
  </section>
  <section class="card" style="padding:20px;display:flex;flex-direction:column">
    <h2 class="h2" style="margin-bottom:6px">Attendance · {{ $line ? \Carbon\Carbon::parse($line->payroll->month.'-01')->format('F') : '—' }}</h2>
    @php($dim = $line ? \Carbon\Carbon::parse($line->payroll->month.'-01')->daysInMonth : null)
    @foreach([['Present', $line ? $line->days.' days' : '—'], ['Approved leave', $line ? $line->leave_days.' day'.($line->leave_days == 1 ? '' : 's') : '—'], ['Weekly off', $line ? max(0, $dim - $line->days - $line->leave_days).' days' : '—'], ['Overtime', $line ? rtrim(rtrim((string) $line->overtime_hours, '0'), '.').' h' : '—']] as [$k, $v])
      <div style="display:flex;font-size:var(--fs-13);padding:6px 0"><span style="flex-grow:1;color:#51607a">{{ $k }}</span><span>{{ $v }}</span></div>
    @endforeach
    <span style="font-size:var(--fs-11);color:#51607a;margin-top:8px">Entered in the payroll run. From duty start / end in the salesman app once that is live.</span>
  </section>
  <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:6px">
    <h2 class="h2">Final settlement</h2>
    <span style="font-size:var(--fs-13);line-height:20px">{{ $e->left_on ? 'Left on '.$e->left_on->format('d-m-Y').'.' : 'Not applicable.' }} If {{ $e->name }} resigns, the statement will show unpaid salary ({{ $U::tk($bal['payable']) }}), leave encashment, open advance ({{ $U::tk($bal['advance']) }}), loans ({{ $U::tk($bal['loan']) }}) and approved claims, and needs Super Admin approval.</span>
  </section>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;align-items:start">
  <form class="card pad" method="post" action="{{ route('hr.entry', $e) }}" style="display:flex;flex-direction:column;gap:10px">@csrf
    <h2 class="h2">Advance, loan or adjustment</h2>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
      <label class="f"><span>Entry</span><select name="kind">@foreach(['advance' => 'Salary advance paid', 'loan' => 'Loan paid', 'advance_recovery' => 'Advance repaid in cash', 'loan_recovery' => 'Loan repaid in cash', 'opening' => 'Opening salary payable', 'adjustment' => 'Adjustment (+ earned / − deducted)'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
      <label class="f"><span>Date</span><input type="date" name="entry_date" required value="{{ now()->timezone('Asia/Dhaka')->toDateString() }}"></label>
      <label class="f"><span>Amount (৳)</span><input type="number" name="amount" step="0.01" required></label>
      <label class="f"><span>Money account</span><select name="account_id"><option value="">— (not cash)</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></label>
    </div>
    <label class="f"><span>Note (required for adjustment)</span><input name="note" maxlength="255"></label>
    <button class="btn pri" type="submit" style="align-self:flex-start">Save entry</button>
  </form>
  <form class="card pad" method="post" action="{{ route('hr.employee.save', $e) }}" style="display:flex;flex-direction:column;gap:10px">@csrf
    <h2 class="h2">Profile &amp; salary structure</h2>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
      <label class="f"><span>Department</span><select name="department"><option value="">—</option>@foreach(\App\Models\Employee::DEPARTMENTS as $d)<option @selected($e->department === $d)>{{ $d }}</option>@endforeach</select></label>
      <label class="f"><span>Designation</span><input name="designation" maxlength="60" value="{{ $e->designation }}"></label>
      <label class="f"><span>Joined on</span><input type="date" name="joined_on" value="{{ $e->joined_on?->toDateString() }}"></label>
      <label class="f"><span>Mobile</span><input name="phone" maxlength="20" value="{{ $e->phone }}"></label>
      <label class="f"><span>Basic (৳ / month)</span><input type="number" name="basic" min="0" step="0.01" value="{{ $e->basic }}" @disabled(! $isSa)></label>
      <label class="f"><span>Allowance (৳ / month)</span><input type="number" name="allowance" min="0" step="0.01" value="{{ $e->allowance }}" @disabled(! $isSa)></label>
      <label class="f"><span>Commission per cylinder (৳)</span><input type="number" name="commission_rate" min="0" step="0.01" value="{{ $e->commission_rate }}" @disabled(! $isSa)></label>
      <label class="f"><span>Bank account</span><input name="bank_account" maxlength="60" value="{{ $e->bank_account }}"></label>
    </div>
    @unless($isSa)<span class="sub" style="margin:0;font-size:var(--fs-11)">Only the Super Admin can change salary. Every change is logged.</span>@endunless
    <button class="btn pri" type="submit" style="align-self:flex-start">Save</button>
  </form>
</div>
@endsection
