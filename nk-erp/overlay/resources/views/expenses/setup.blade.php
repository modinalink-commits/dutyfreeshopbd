@extends('layout')
@section('title', 'Expense settings')
@section('content')
@include('expenses._css')
@php($U = \App\Support\Ui::class)
@php($isSa = auth()->user()->isSuperAdmin())
@php($nCats = $groups->sum(fn ($g) => $g->children->count()))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Expense settings</h1><span class="sub">Categories, budgets, recurring bills and approval limits · Super Admin can change all of these without code</span></div>
  <a class="btn" href="{{ route('expenses.index') }}">All expenses</a>
</header>

<section class="card" style="overflow:hidden">
  <div style="padding:14px 20px;display:flex;align-items:center"><h2 class="h2" style="flex-grow:1">Expense categories · {{ $groups->count() }} groups, {{ $nCats }} categories</h2>@if($isSa)<a class="btn" href="#cat-form">＋ Category</a>@endif</div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Code</th><th>Category</th><th>Parent</th><th>Default GL account</th><th>Type</th><th>Approval rule</th><th class="r">Monthly budget</th><th>Status</th>@if($isSa)<th class="no-sort">Edit</th>@endif</tr></thead>
    <tbody>
    @foreach($groups as $g)
      <tr><td style="background:#f4f7fc;color:#51607a">{{ $g->code }}</td><td style="background:#f4f7fc;font-weight:700">{{ $g->name }} · {{ $g->children->count() }}</td><td style="background:#f4f7fc">—</td><td style="background:#f4f7fc"></td><td style="background:#f4f7fc"></td><td style="background:#f4f7fc"></td><td class="r" style="background:#f4f7fc">{{ $g->children->sum('monthly_budget') > 0 ? $U::tk($g->children->sum('monthly_budget')) : '' }}</td><td style="background:#f4f7fc"></td>@if($isSa)<td style="background:#f4f7fc"></td>@endif</tr>
      @foreach($g->children as $c)
        <tr><td style="color:#51607a">{{ $c->code }}</td><td style="padding-left:24px;font-weight:600">{{ $c->name }}@if($c->source)<br><span class="sub" style="font-size:var(--fs-11)">entered in {{ \App\Models\ExpenseCategory::SOURCES[$c->source] }}</span>@endif</td><td>{{ $g->name }}</td><td>{{ $c->gl_account ?: '—' }}</td><td>{{ $c->type ?: '—' }}</td><td>{{ $c->approval_rule ?: '—' }}</td>
          <td class="r">{{ $c->monthly_budget !== null ? $U::tk($c->monthly_budget) : '—' }}</td>
          <td class="c"><span class="pill" style="{{ $c->is_active ? 'background:#e3f6ec;color:#0f6b3c' : '' }}">{{ $c->is_active ? 'Active' : 'Inactive' }}</span></td>
          @if($isSa)<td class="c"><button class="btn sm" type="button" onclick='fillCat(@json($c))'>Edit</button></td>@endif</tr>
      @endforeach
    @endforeach
    </tbody>
  </table></div>
  <div style="padding:10px 20px;font-size:var(--fs-11);color:#51607a;border-top:1px solid #dbe3f0">Salary, overtime, bonus and commission come from Payroll; vehicle repair from Fleet; stock purchases from Purchase. They show here for reporting but are entered only in their own module. Deposits, loans and asset purchases are not operating expenses.</div>
</section>

@if($isSa)
<form class="card pad" id="cat-form" method="post" action="{{ route('expenses.category') }}" style="display:flex;flex-direction:column;gap:10px">@csrf
  <input type="hidden" name="id" id="c_id">
  <h2 class="h2" id="c_title">＋ Category</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px">
    <label class="f"><span>Code</span><input name="code" id="c_code" required maxlength="12" placeholder="EX-105"></label>
    <label class="f"><span>Category</span><input name="name" id="c_name" required maxlength="80"></label>
    <label class="f"><span>Parent group</span><select name="parent_id" id="c_parent"><option value="">— (this is a group)</option>@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->code }} · {{ $g->name }}</option>@endforeach</select></label>
    <label class="f"><span>Default GL account</span><input name="gl_account" id="c_gl" maxlength="60" placeholder="6250 Office supplies"></label>
    <label class="f"><span>Type</span><select name="type" id="c_type"><option value="">—</option>@foreach(\App\Models\ExpenseCategory::TYPES as $t)<option>{{ $t }}</option>@endforeach</select></label>
    <label class="f"><span>Entry form</span><select name="form" id="c_form">@foreach(\App\Models\ExpenseCategory::FORMS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
    <label class="f"><span>Approval rule</span><input name="approval_rule" id="c_rule" maxlength="60" placeholder="Manager"></label>
    <label class="f"><span>Monthly budget (৳)</span><input name="monthly_budget" id="c_budget" type="number" min="0" step="1"></label>
    <label class="f"><span>Status</span><select name="is_active" id="c_active"><option value="1">Active</option><option value="0">Inactive</option></select></label>
  </div>
  <button class="btn pri" type="submit" style="align-self:flex-start">Save category</button>
</form>
<script>
function fillCat(c){ const s=(k,v)=>document.getElementById(k).value=v??''; s('c_id',c.id); s('c_code',c.code); s('c_name',c.name); s('c_parent',c.parent_id); s('c_gl',c.gl_account); s('c_type',c.type); s('c_form',c.form); s('c_rule',c.approval_rule); s('c_budget',c.monthly_budget?Math.round(c.monthly_budget):''); s('c_active',c.is_active?1:0); document.getElementById('c_title').textContent='Edit '+c.code; document.getElementById('cat-form').scrollIntoView({behavior:'smooth'}); }
</script>
@endif

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:16px">
  <section class="card" style="overflow:hidden">
    <div style="padding:14px 20px;display:flex;align-items:center"><h2 class="h2" style="flex-grow:1">Recurring expenses</h2>@if($isSa)<a class="btn" href="#rec-form">＋ Recurring</a>@endif</div>
    <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
      <thead><tr><th>Name</th><th class="r">Amount</th><th>Every</th><th>Cost center</th><th>Next draft</th></tr></thead>
      <tbody>
      @forelse($recurring as $r)
        <tr><td>{{ $r->name }}</td><td class="r">{{ $r->amount !== null ? $U::tk($r->amount) : '—' }}</td><td>{{ \App\Models\ExpenseRecurring::EVERY[$r->every] }}{{ $r->every === 'month' && $r->day ? ', '.$r->day.(in_array($r->day, [11, 12, 13]) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$r->day % 10] ?? 'th')) : '' }}</td><td>{{ $r->cost_center ?: '—' }}</td><td class="nw">{{ $r->every === 'low' ? 'reminder only' : ($r->next_date?->format('d-m-Y') ?? '—') }}</td></tr>
      @empty
        <tr><td colspan="5" class="empty">No recurring expenses yet (rent, internet, hosting, SMS top-up…).</td></tr>
      @endforelse
      </tbody>
    </table></div>
    @if($isSa)
    <form id="rec-form" method="post" action="{{ route('expenses.recurring') }}" style="padding:0 20px 14px;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;align-items:end">@csrf
      <label class="f"><span>Name</span><input name="name" required maxlength="80" placeholder="Warehouse rent"></label>
      <label class="f"><span>Category</span><select name="category_id">@foreach($cats as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
      <label class="f"><span>Amount</span><input name="amount" type="number" min="0" step="0.01"></label>
      <label class="f"><span>Every</span><select name="every">@foreach(\App\Models\ExpenseRecurring::EVERY as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
      <label class="f"><span>Day of month</span><input name="day" type="number" min="1" max="28"></label>
      <label class="f"><span>Cost center</span><select name="cost_center"><option value="">—</option>@foreach($ccs as $c)<option>{{ $c }}</option>@endforeach</select></label>
      <label class="f"><span>First draft on</span><input name="next_date" type="date"></label>
      <button class="btn pri" type="submit">Save</button>
    </form>
    @endif
    <div style="padding:10px 20px;font-size:var(--fs-11);color:#51607a;border-top:1px solid #dbe3f0">Each run makes a draft for review with a reminder. Nothing is paid automatically.</div>
  </section>

  <section class="card" style="overflow:hidden">
    <div style="padding:14px 20px;display:flex;align-items:center"><h2 class="h2" style="flex-grow:1">Approval limits</h2>@if($isSa)<a class="btn" href="#rule-form">＋ Rule</a>@endif</div>
    <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
      <thead><tr><th>Who submits</th><th>Up to</th><th>Verified by</th><th>Approved by</th>@if($isSa)<th class="no-sort"></th>@endif</tr></thead>
      <tbody>
      @foreach($rules as $r)
        <tr><td>{{ $r->who }}</td><td class="nw">{{ $r->up_to !== null ? $U::tk($r->up_to) : ($r->above !== null ? 'above '.$U::tk($r->above) : 'any') }}</td><td>{{ $r->verified_by }}</td><td>{{ \App\Models\ExpenseRule::APPROVERS[$r->approver] }}</td>
          @if($isSa)<td class="c"><form method="post" action="{{ route('expenses.rule.delete', $r) }}" onsubmit="return confirm('Remove this rule?')">@csrf<button class="btn sm" type="submit">Remove</button></form></td>@endif</tr>
      @endforeach
      </tbody>
    </table></div>
    @if($isSa)
    <form id="rule-form" method="post" action="{{ route('expenses.rule') }}" style="padding:0 20px 14px;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;align-items:end">@csrf
      <label class="f"><span>Who submits</span><input name="who" required maxlength="60"></label>
      <label class="f"><span>Up to (৳)</span><input name="up_to" type="number" min="0"></label>
      <label class="f"><span>or above (৳)</span><input name="above" type="number" min="0"></label>
      <label class="f"><span>Verified by</span><input name="verified_by" required maxlength="60"></label>
      <label class="f"><span>Approved by</span><select name="approver">@foreach(\App\Models\ExpenseRule::APPROVERS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
      <button class="btn pri" type="submit">Save rule</button>
    </form>
    @endif
    <div style="padding:10px 20px;font-size:var(--fs-11);color:#51607a;border-top:1px solid #dbe3f0">Sample limits — set your own. Checked on the server, not only in the app.</div>
  </section>
</div>

<form class="card" method="post" action="{{ route('expenses.alerts') }}" style="padding:18px 20px;display:flex;flex-wrap:wrap;gap:20px;align-items:center">@csrf
  <h2 class="h2" style="flex-grow:1">Budget alerts</h2>
  <label style="display:flex;align-items:center;gap:8px;font-size:var(--fs-13)">First alert at <input type="number" name="alert1" value="{{ $alert1 }}" min="1" max="200" aria-label="First alert" style="width:72px;min-height:36px;text-align:center;font-weight:600" @disabled(! $isSa)>%</label>
  <label style="display:flex;align-items:center;gap:8px;font-size:var(--fs-13)">Second alert at <input type="number" name="alert2" value="{{ $alert2 }}" min="1" max="300" aria-label="Second alert" style="width:72px;min-height:36px;text-align:center;font-weight:600" @disabled(! $isSa)>%</label>
  <label style="display:flex;align-items:center;gap:8px;font-size:var(--fs-13)">Over budget <select name="over" aria-label="Over budget action" style="width:auto;min-height:36px;font-size:var(--fs-12);font-weight:600" @disabled(! $isSa)><option value="allow" @selected($over === 'allow')>Allow, needs Super Admin</option><option value="block" @selected($over === 'block')>Block</option></select></label>
  <label style="display:flex;align-items:center;gap:8px;font-size:var(--fs-12);color:#51607a">Receipts: JPG, PNG or PDF, up to <input type="number" name="mb" value="{{ $mb }}" min="1" max="20" style="width:60px;min-height:36px;text-align:center" @disabled(! $isSa)> MB</label>
  @if($isSa)<button class="btn pri" type="submit">Save</button>@endif
</form>
@endsection
