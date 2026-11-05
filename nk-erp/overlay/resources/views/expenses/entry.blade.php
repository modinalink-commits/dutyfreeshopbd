@extends('layout')
@section('title', 'Add expense')
@section('content')
@include('expenses._css')
@php($U = \App\Support\Ui::class)
@php($ex = $claim?->extra ?? [])
@php($selCat = old('category_id', $claim?->category_id ?? $cats->firstWhere('form', 'fuel')?->id ?? $cats->first()?->id))
@php($quick = collect(['fuel', 'rent', 'reimb', 'bill'])->map(fn ($f) => $cats->first(fn ($c) => $c->form === $f && ! $c->source))->filter())
<form method="post" action="{{ route('expenses.store') }}" enctype="multipart/form-data" id="exForm" style="display:flex;flex-direction:column;gap:16px">@csrf
@if($claim)<input type="hidden" name="id" value="{{ $claim->id }}">@endif
<input type="hidden" name="category_id" id="catId" value="{{ $selCat }}">
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>{{ $claim ? 'Edit '.$claim->claim_no : 'Add expense' }}</h1><span class="sub">6 fields to start · the form adds only what this category needs</span></div>
  <a class="btn" href="{{ route('expenses.index') }}">All expenses</a>
</header>
<section class="card" aria-label="Status" style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:6px;align-items:center">
  @foreach(['Draft', 'Submitted', 'Pending approval', 'Approved', 'Posted', 'Paid'] as $i => $t)
    <span style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;{{ $i === 0 ? 'background:#8a4b00;color:#fff' : 'background:#eef2f9;color:#51607a' }}">{{ $t }}</span>
  @endforeach
  <span style="font-size:11px;color:#51607a;margin-left:auto">Approval and payment are separate steps.</span>
</section>

<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section class="card" style="flex:999 1 560px;min-width:0;padding:20px;display:flex;flex-direction:column;gap:14px">
    <div class="f"><span>Expense category</span>
      <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
        @foreach($quick as $q)<button type="button" class="chip" data-id="{{ $q->id }}" style="min-height:38px;padding:0 14px;border-radius:999px;cursor:pointer;font:600 12px 'Plus Jakarta Sans',sans-serif;border:1px solid #7b8aa8;background:#fff;color:#14213d">{{ $q->name }}</button>@endforeach
        <select id="catSel" aria-label="All categories" style="width:auto;min-height:38px;border-radius:999px;font-size:12px;font-weight:600">
          @foreach($cats->groupBy(fn ($c) => $c->parent->name) as $gname => $list)<optgroup label="{{ $gname }}">@foreach($list as $c)<option value="{{ $c->id }}" @selected($selCat == $c->id) @disabled($c->source) data-form="{{ $c->form }}" data-gl="{{ $c->gl_account ?: $c->name }}" data-name="{{ $c->name }}" data-budget="{{ (float) $c->monthly_budget }}" data-used="{{ (float) ($used[$c->id] ?? 0) }}" data-group="{{ $gname }}">{{ $c->code }} · {{ $c->name }}{{ $c->source ? ' (from '.\App\Models\ExpenseCategory::SOURCES[$c->source].')' : '' }}</option>@endforeach</optgroup>@endforeach
        </select>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px">
      <label class="f"><span>Expense date</span><input type="date" name="expense_date" required value="{{ old('expense_date', $claim?->expense_date?->toDateString() ?? now()->timezone('Asia/Dhaka')->toDateString()) }}"></label>
      <label class="f"><span>Amount</span><input type="number" name="amount" id="amt" min="1" step="0.01" required value="{{ old('amount', $claim?->amount) }}" style="background:#e6eeff;border-color:#9db4e3;color:#123a86;font-weight:700"></label>
      <label class="f"><span>Expense for / cost center</span><select name="cost_center"><option value="">—</option>@foreach($ccs as $c)<option @selected(old('cost_center', $claim?->cost_center) === $c)>{{ $c }}</option>@endforeach</select></label>
      <label class="f"><span>Payment method</span><select name="payment" id="pay">@foreach(\App\Models\ExpenseClaim::PAYMENTS as $k => $l)<option value="{{ $k }}" data-cr="{{ \App\Models\ExpenseClaim::CREDIT[$k] }}" @selected(old('payment', $claim?->payment) === $k)>{{ $l }}</option>@endforeach</select></label>
      <label class="f" data-pay="bank mobile cash"><span>Pay from account</span><select name="account_id"><option value="">Default account</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected(old('account_id', $claim?->account_id) == $a->id)>{{ $a->name }}</option>@endforeach</select></label>
      <label class="f" data-pay="trip"><span>Trip</span><select name="trip_id"><option value="">—</option>@foreach($trips as $t)<option value="{{ $t->id }}" @selected(old('trip_id', $claim?->trip_id) == $t->id)>{{ $t->trip_no }} · {{ $t->vehicle->code }}</option>@endforeach</select></label>
      <label class="f" style="grid-column:span 2"><span>Description</span><input name="description" maxlength="255" value="{{ old('description', $claim?->description) }}"></label>
    </div>
    <div style="border-top:1px solid #dbe3f0;padding-top:14px;display:flex;flex-direction:column;gap:10px">
      <span class="ttl" id="forLbl">FOR</span>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px">
        <label class="f" data-form="fuel"><span>Vehicle</span><select name="extra[vehicle]"><option value="">—</option>@foreach($vehicles as $v)<option @selected(($ex['vehicle'] ?? '') === $v->code.' · '.$v->reg_no)>{{ $v->code }} · {{ $v->reg_no }}</option>@endforeach</select></label>
        <label class="f" data-form="fuel"><span>Fuel quantity (L)</span><input name="extra[litres]" id="lit" inputmode="decimal" value="{{ $ex['litres'] ?? '' }}"></label>
        <label class="f" data-form="fuel"><span>Fuel rate</span><input name="extra[rate]" id="rate" inputmode="decimal" value="{{ $ex['rate'] ?? '' }}"></label>
        <div class="f" data-form="fuel"><span>Total</span><div class="box auto" id="fuelTot">—</div></div>
        <label class="f" data-form="fuel"><span>Odometer</span><input name="extra[odometer]" inputmode="numeric" placeholder="km" value="{{ $ex['odometer'] ?? '' }}"></label>
        <label class="f" data-form="rent"><span>Warehouse / office</span><select name="extra[place]"><option>Main warehouse</option><option @selected(($ex['place'] ?? '') === 'Head office')>Head office</option></select></label>
        <label class="f" data-form="rent"><span>Rent month</span><input type="month" name="extra[month]" value="{{ $ex['month'] ?? '' }}"></label>
        <label class="f" data-form="rent bill"><span>Landlord / vendor</span><input name="extra[vendor]" maxlength="120" value="{{ $ex['vendor'] ?? '' }}"></label>
        <label class="f" data-form="reimb"><span>Employee</span><select name="extra[employee]"><option value="">—</option>@foreach($employees as $e)<option @selected(($ex['employee'] ?? '') === $e->name)>{{ $e->name }}</option>@endforeach</select></label>
        <label class="f" data-form="reimb"><span>Expense type</span><select name="extra[type]">@foreach(['Mobile', 'Transport', 'Food', 'Other'] as $t)<option @selected(($ex['type'] ?? '') === $t)>{{ $t }}</option>@endforeach</select></label>
        <div class="f" data-form="reimb"><span>Approval status</span><div class="box auto">{{ $claim ? \App\Models\ExpenseClaim::FLOW[$claim->status] : 'New — will wait for manager' }}</div></div>
        <label class="f" data-form="bill"><span>Bill month</span><input type="month" name="extra[bill_month]" value="{{ $ex['bill_month'] ?? '' }}"></label>
        <label class="f" data-form="bill"><span>Invoice ref.</span><input name="extra[invoice]" maxlength="60" value="{{ $ex['invoice'] ?? '' }}"></label>
        <label class="f" data-form="general fuel rent reimb bill"><span>Receipt (JPG, PNG or PDF, up to {{ $maxMb }} MB)</span><input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" style="padding:8px">@if($claim?->receipt_path)<a href="{{ route('expenses.receipt', $claim) }}" target="_blank" style="font-size:11px">current receipt</a>@endif</label>
      </div>
    </div>
    <div style="border-top:1px solid #dbe3f0;padding-top:14px;display:flex;flex-direction:column;gap:8px">
      <div style="display:flex;align-items:center"><span class="ttl" style="flex-grow:1">SPLIT ACROSS COST CENTERS</span><button type="button" id="addSplit" style="border:0;background:transparent;font:700 12px 'Plus Jakarta Sans',sans-serif;color:#1d4ed8;cursor:pointer">＋ Add split</button></div>
      <div class="dt-wrap"><table class="dt" id="splitT">
        <thead><tr><th>Cost center</th><th class="r">%</th><th class="r">Amount</th></tr></thead>
        <tbody>
          @foreach(($claim?->splits ?: [['cc' => '', 'pct' => '']]) as $s)
            <tr class="sp"><td><select name="split_cc[]"><option value="">Same as above (100%)</option>@foreach($ccs as $c)<option @selected($s['cc'] === $c)>{{ $c }}</option>@endforeach</select></td><td class="r"><input class="pct" name="split_pct[]" type="number" min="0" max="100" step="0.01" value="{{ $s['pct'] }}" style="width:80px;text-align:right"></td><td class="r sa">—</td></tr>
          @endforeach
          <tr style="font-weight:700"><td>Total</td><td class="r" id="pctT">100%</td><td class="r" id="amtT">—</td></tr>
        </tbody>
      </table></div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;border-top:1px solid #dbe3f0;padding-top:14px">
      <span style="flex-grow:1"></span>
      <button class="btn" type="submit" name="action" value="draft">Save draft</button>
      <button class="btn pri" type="submit" name="action" value="submit">Submit for approval</button>
    </div>
  </section>

  <aside style="flex:1 1 300px;min-width:280px;display:flex;flex-direction:column;gap:16px">
    <section class="card" style="overflow:hidden">
      <div style="padding:14px 16px"><h2 class="h2">Ledger posting preview</h2></div>
      <div class="dt-wrap" style="margin:0 16px"><table class="dt">
        <thead><tr><th>Account</th><th class="r">Debit</th><th class="r">Credit</th></tr></thead>
        <tbody><tr><td id="glDr">—</td><td class="r amtV">—</td><td class="r"></td></tr><tr><td id="glCr">—</td><td class="r"></td><td class="r amtV">—</td></tr></tbody>
      </table></div>
      <div style="padding:10px 16px;font-size:11px;color:#51607a;line-height:16px">Posts only after approval. <span id="src"></span></div>
    </section>
    <section class="card" style="padding:16px;display:flex;flex-direction:column;gap:8px">
      <h2 class="h2">Budget check</h2>
      <div style="display:flex;font-size:12px"><span style="flex-grow:1;color:#51607a" id="bName">—</span><span style="font-weight:700" id="bUse">—</span></div>
      <div style="height:10px;border-radius:999px;background:#eef2f9"><div id="bBar" style="height:10px;border-radius:999px;background:#1f9d55;width:0"></div></div>
      <span class="bn" id="bText">—</span>
    </section>
  </aside>
</div>
</form>
<script>
(function(){
  const sel=document.getElementById('catSel'), id=document.getElementById('catId'), amt=document.getElementById('amt'), pay=document.getElementById('pay');
  const fmt=n=>'৳'+Math.round(n).toLocaleString('en-IN'), bn=s=>String(s).replace(/\d/g,d=>'০১২৩৪৫৬৭৮৯'[d]);
  const SRC={fuel:'Fuel from route trips can also be entered on the Vehicle profit page; each entry is posted once.',rent:'Recurring rent is made as a draft each month; nobody is paid until approved.',reimb:'Paid with salary from 2220, so it is never counted as salary expense.',bill:'When paid later: Dr 2130 Bills payable / Cr Cash or Bank.',general:''};
  function cur(){ return sel.selectedOptions[0]; }
  function show(){
    const o=cur(); if(!o) return; id.value=o.value; const form=o.dataset.form;
    document.querySelectorAll('[data-form]').forEach(el=>{ const on=el.dataset.form.split(' ').includes(form); el.style.display=on?'':'none'; el.querySelectorAll('input,select').forEach(i=>i.disabled=!on); });
    document.querySelectorAll('.chip').forEach(c=>{ const on=c.dataset.id===o.value; c.style.background=on?'#123a86':'#fff'; c.style.color=on?'#fff':'#14213d'; c.style.borderColor=on?'#123a86':'#7b8aa8'; });
    document.getElementById('forLbl').textContent='FOR '+o.dataset.name.toUpperCase();
    document.querySelectorAll('[data-pay]').forEach(el=>{ const on=el.dataset.pay.split(' ').includes(pay.value); el.style.display=on?'':'none'; el.querySelectorAll('select').forEach(i=>i.disabled=!on); });
    const a=+amt.value||0;
    document.getElementById('glDr').textContent=o.dataset.gl; document.getElementById('glCr').textContent=pay.selectedOptions[0].dataset.cr;
    document.querySelectorAll('.amtV').forEach(e=>e.textContent=a?fmt(a):'—');
    document.getElementById('src').textContent=SRC[form]||'';
    const b=+o.dataset.budget, u=+o.dataset.used;
    document.getElementById('bName').textContent=o.dataset.name+' · this month';
    if(b>0){ const p1=Math.round(u/b*100), p2=Math.round((u+a)/b*100); document.getElementById('bUse').textContent=p1+'% → '+p2+'%';
      const bar=document.getElementById('bBar'); bar.style.width=Math.min(100,p2)+'%'; bar.style.background=p2>=100?'#c46f00':'#1f9d55';
      document.getElementById('bText').textContent=p2>100?'বাজেট ছাড়িয়ে যাবে — Super Admin-এর অনুমোদন লাগবে।':(p2>=80?'বাজেটের কাছাকাছি।':'বাজেটের মধ্যে আছে।'); }
    else { document.getElementById('bUse').textContent='No budget'; document.getElementById('bBar').style.width='0'; document.getElementById('bText').textContent='এই খাতের মাসিক বাজেট সেট করা নেই।'; }
    const l=+document.getElementById('lit').value, r=+document.getElementById('rate').value;
    document.getElementById('fuelTot').textContent=l&&r?(l+' × '+r+' = '+fmt(l*r)):'—';
    let tp=0; document.querySelectorAll('tr.sp').forEach(tr=>{ const p=+tr.querySelector('.pct').value||0, cc=tr.querySelector('select').value; tp+=cc?p:0; tr.querySelector('.sa').textContent=cc&&p?fmt(a*p/100):'—'; });
    const anySplit=[...document.querySelectorAll('tr.sp select')].some(s=>s.value);
    document.getElementById('pctT').textContent=anySplit?tp+'%':'100%'; document.getElementById('amtT').textContent=a?fmt(a)+((!anySplit||Math.abs(tp-100)<.01)?' ✓':' ✗'):'—';
  }
  document.querySelectorAll('.chip').forEach(c=>c.addEventListener('click',()=>{ sel.value=c.dataset.id; show(); }));
  ['lit','rate'].forEach(k=>document.getElementById(k).addEventListener('input',()=>{ const l=+document.getElementById('lit').value, r=+document.getElementById('rate').value; if(l&&r) amt.value=(l*r).toFixed(2); }));
  document.getElementById('addSplit').addEventListener('click',()=>{ const t=document.querySelector('tr.sp'), n=t.cloneNode(true); n.querySelector('select').value=''; n.querySelector('.pct').value=''; t.parentNode.insertBefore(n, document.getElementById('pctT').parentNode); show(); });
  document.getElementById('exForm').addEventListener('input',show); document.getElementById('exForm').addEventListener('change',show); show();
})();
</script>
@endsection
