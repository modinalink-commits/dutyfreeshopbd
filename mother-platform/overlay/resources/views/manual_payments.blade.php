@extends('layout')@section('title','Manual Payments')
@section('body')
<style>
.mpk{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;margin-bottom:10px}.mpk div{background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:8px 10px}.mpk small{display:block;color:#6B7280;font-size:11px}.mpk b{font-size:18px}.mpk .w b{color:#D97706}
.mpf{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:10px}.mpf input,.mpf select{width:auto!important;margin:0!important;padding:6px 8px;border:1px solid #D1D5DB;border-radius:8px;font-size:13px}
table.mpt{width:100%;border-collapse:collapse;font-size:13px;background:#fff}.mpt th,.mpt td{padding:7px 8px;border-bottom:1px solid #F1F5F9;text-align:left;vertical-align:middle}.mpt th{background:#F8FAFC;font-size:12px;color:#475569;white-space:nowrap}.mpt tr:hover td{background:#FFFBEB}
.tg{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;white-space:nowrap}.tg.p{background:#FEF3C7;color:#92400E}.tg.v{background:#DCFCE7;color:#166534}.tg.r{background:#FEE2E2;color:#991B1B}.tg.d{background:#FFE4E6;color:#BE123C}.tg.m{background:#EDE9FE;color:#5B21B6}.tg.c{background:#E5E7EB;color:#374151}
#mpd{border:0;border-radius:14px;padding:16px;max-width:520px;width:94%;box-shadow:0 20px 60px rgba(0,0,0,.3)}#mpd::backdrop{background:rgba(15,23,42,.5)}#mpd dl{display:grid;grid-template-columns:130px 1fr;gap:4px 10px;margin:8px 0;font-size:13px}#mpd dt{color:#6B7280}#mpd dd{margin:0;word-break:break-word}
.mpa a,.mpa button{font-size:12px;padding:3px 8px}
</style>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if(session('bad'))<div class="card err">{{ session('bad') }}</div>@endif
<div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap"><h1 style="margin:0;flex:1">Manual Payments <span class="tg p" id="mpc">{{ (int)$k->pend }} pending</span></h1><button type="button" class="btn" onclick="document.getElementById('mpAdd').showModal()">+ Add Manual Payment</button> <a class="btn g" href="/admin/payment-methods">Payment Methods</a><a class="btn g" href="/admin/orders">Orders</a></div>
<div class="mpk">
<div class="w"><small>Pending Verification</small><b>{{ (int)$k->pend }}</b></div><div><small>Verified Today</small><b>{{ (int)$k->vtoday }}</b></div><div><small>Failed / Rejected</small><b>{{ (int)$k->rej }}</b></div>
<div><small>Pending Bank</small><b>{{ (int)$k->bn }}</b></div><div><small>Pending bKash</small><b>{{ (int)$k->bk }}</b></div><div><small>Pending Nagad</small><b>{{ (int)$k->ng }}</b></div><div class="w"><small>Total Pending Amount</small><b>৳{{ number_format((float)$k->pamt) }}</b></div>
</div>
<form class="mpf" id="mpf" method="get" action="/admin/manual-payments">
<input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, mobile, invoice, TrxID…" style="min-width:260px;width:260px!important" autocomplete="off">
<select name="range"><option value="">All dates</option><option value="today" @selected(request('range')==='today')>Today</option><option value="yesterday" @selected(request('range')==='yesterday')>Yesterday</option></select>
<input type="date" name="from" value="{{ request('from') }}" title="From"><input type="date" name="to" value="{{ request('to') }}" title="To">
<select name="method"><option value="">All methods</option><option value="bank" @selected(request('method')==='bank')>{{ \App\Support\Brand::BANK_LABEL }}</option><option value="bkash" @selected(request('method')==='bkash')>bKash</option><option value="nagad" @selected(request('method')==='nagad')>Nagad</option></select>
<select name="bank"><option value="">All banks</option>@foreach($banks as $b)<option @selected(request('bank')===$b)>{{ $b }}</option>@endforeach</select>
<select name="status"><option value="">All status</option>@foreach(['Pending Verification','Verified','Rejected','Amount Mismatch','Duplicate','Cancelled'] as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
<input type="number" name="min" value="{{ request('min') }}" placeholder="Min ৳" style="width:90px"><input type="number" name="max" value="{{ request('max') }}" placeholder="Max ৳" style="width:90px">
<select name="sort"><option value="">Newest first</option><option value="old" @selected(request('sort')==='old')>Oldest first</option></select>
<button>Filter</button><a class="btn g" href="/admin/manual-payments">Reset</a>
</form>
<div id="mpw" style="overflow:auto"><table class="mpt"><thead><tr><th>Date</th><th>Customer</th><th>Mobile</th><th>Invoice</th><th>Method</th><th>Amount</th><th>Txn ID / Ref</th><th>Verification</th><th>Order Payment</th><th>Verified By</th><th>Action</th></tr></thead><tbody>
@forelse($rows as $p)
<tr><td style="white-space:nowrap">{{ \Carbon\Carbon::parse($p->paid_at ?? $p->created_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</td><td>{{ $p->customer_name }}</td><td>{{ $p->phone }}</td>
<td><a href="/admin/orders/{{ $p->order_id }}#payhist"><b>{{ $p->invoice }}</b></a></td><td>@include('pay_badge',['o'=>(object)['payment_method'=>$p->method,'pay_account'=>$p->order_pay_account ?? null],'h'=>20])</td><td><b>৳{{ number_format((float)$p->amount) }}</b></td><td>{{ $p->txn_id ?: '—' }}@if($p->txn_id && ($dups[$p->txn_id] ?? 0) > 1) <span class="tg d" title="Same Transaction ID used more than once">Possible Duplicate</span>@endif</td>
<td><span class="tg {{ $p->status==='verified' ? 'v' : ($p->status==='rejected' ? 'r' : 'p') }}">{{ $p->status==='pending' ? 'Pending Verification' : ucfirst($p->status) }}</span>@if($p->reject_reason) <small title="{{ $p->reject_reason }}">ⓘ</small>@endif</td>
<td>{{ $p->payment_status ?: 'Unpaid' }} <small style="color:#6B7280">৳{{ number_format((float)$p->paid_total) }}/{{ number_format((float)$p->order_total) }}</small></td><td>{{ $p->verified_by ?: '—' }}</td>
<td class="mpa" style="white-space:nowrap"><a class="btn g" href="/admin/orders/{{ $p->order_id }}#payhist">View Order</a>@if($p->proof) <a class="btn g" href="/admin/manual-payments/entry/{{ $p->id }}/proof" target="_blank">Proof</a>@endif
@if($p->status==='pending' && $canAct) <form method="post" action="/admin/manual-payments/entry/{{ $p->id }}/verify" style="display:inline">@csrf <button class="btn">Verify</button></form> <form method="post" action="/admin/manual-payments/entry/{{ $p->id }}/reject" style="display:inline">@csrf <input name="reason" placeholder="Reason" required style="width:110px"> <button class="btn g">Reject</button></form>@endif</td></tr>
@empty<tr><td colspan="11" style="text-align:center;color:#6B7280;padding:24px">No manual payments found.</td></tr>@endforelse
</tbody></table><div style="margin-top:10px">{{ $rows->links('pagination.global') }}</div></div>

<dialog id="mpd"><div style="display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0" id="mpt"></h3><button type="button" class="g" onclick="mpd.close()">✕</button></div>
<div id="mpb"></div>
<div id="mpacts"></div></dialog>
<script>
const mpd=document.getElementById('mpd'),CSRF=@json(csrf_token()),CAN=@json($canAct),REASONS=@json($reasons);
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]))}
function mpOpen(tr){const j=JSON.parse(tr.dataset.j);document.getElementById('mpt').textContent=j.inv+' · '+j.st;
 let h='<dl><dt>Customer</dt><dd>'+esc(j.cust)+' · '+esc(j.ph)+'</dd><dt>Order total</dt><dd>৳'+Math.round(j.tot).toLocaleString()+'</dd><dt>Submitted amount</dt><dd>'+(j.amt!=null?'৳'+Math.round(j.amt).toLocaleString():'Not entered (same as total)')+(j.mm?' <b style="color:#5B21B6">Amount Mismatch</b>':'')+'</dd><dt>Method</dt><dd>'+esc(j.m)+'</dd><dt>Bank / Wallet no.</dt><dd>'+esc(j.acc||'—')+'</dd><dt>Transaction ID</dt><dd><b>'+esc(j.tx||'—')+'</b>'+(j.dup?' <b style="color:#BE123C">Possible Duplicate</b>':'')+'</dd><dt>Submitted</dt><dd>'+esc(j.at)+'</dd>'+(j.by?'<dt>'+esc(j.st)+' by</dt><dd>'+esc(j.by)+' · '+esc(j.pat)+'</dd>':'')+(j.rs?'<dt>Reason</dt><dd>'+esc(j.rs)+'</dd>':'')+'<dt>Notes</dt><dd style="white-space:pre-wrap">'+esc(j.note||'—')+'</dd></dl>';
 if(j.proof)h+='<div style="margin:6px 0"><a href="/admin/orders/'+j.id+'/pay-proof" target="_blank"><img src="/admin/orders/'+j.id+'/pay-proof" alt="" style="max-width:100%;max-height:220px;border:1px solid #E5E7EB;border-radius:8px" onerror="this.parentNode.textContent=\'Open proof\'"></a></div>';
 document.getElementById('mpb').innerHTML=h;
 let a='';if(CAN){const A='/admin/manual-payments/'+j.id+'/';
  a+='<form method="post" action="'+A+'note" style="display:flex;gap:6px;margin:8px 0"><input type="hidden" name="_token" value="'+CSRF+'"><input name="note" placeholder="Add note" required style="flex:1"><button class="g">Add Note</button></form>';
  if(j.pend){a+='<form method="post" action="'+A+'verify" style="border-top:1px solid #E5E7EB;padding-top:8px"><input type="hidden" name="_token" value="'+CSRF+'"><div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">Received ৳ <input type="number" name="amount" value="'+Math.round(j.tot)+'" style="width:110px"><label><input type="checkbox" name="sms" value="1" checked> Send SMS</label>'+(j.dup?'<label style="color:#BE123C"><input type="checkbox" name="dup_ok" value="1"> I checked the duplicate</label>':'')+'<button>Verify Payment</button></div></form>';
   a+='<form method="post" action="'+A+'reject" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap"><input type="hidden" name="_token" value="'+CSRF+'"><select name="reason" required><option value="">Reject reason…</option>'+REASONS.map(r=>'<option>'+r+'</option>').join('')+'</select><input name="note" placeholder="Note (optional)" style="flex:1;min-width:120px"><label><input type="checkbox" name="sms" value="1"> SMS</label><button class="g" style="color:#B91C1C">Reject</button></form>';}
 }
 a+='<div style="margin-top:8px"><a class="btn g" href="/admin/orders/'+j.id+'">View Order</a></div>';
 document.getElementById('mpacts').innerHTML=a;mpd.showModal();}
// live search / filters without page reload
(function(){const f=document.getElementById('mpf');let t,ctl;
 async function go(){const u='/admin/manual-payments?'+new URLSearchParams(new FormData(f)).toString();if(ctl)ctl.abort();ctl=new AbortController();
  try{const r=await fetch(u,{signal:ctl.signal,headers:{'X-Requested-With':'XMLHttpRequest'}});const d=new DOMParser().parseFromString(await r.text(),'text/html');
   document.getElementById('mpw').innerHTML=d.getElementById('mpw').innerHTML;history.replaceState(null,'',u);}catch(e){}}
 f.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(go,300)});f.addEventListener('submit',e=>{e.preventDefault();go()});
 setInterval(async()=>{try{const j=await (await fetch('/admin/manual-payments/count')).json();document.getElementById('mpc').textContent=j.pending+' pending'}catch(e){}},30000);
})();
</script>
@endsection
<dialog id="mpAdd" style="border:0;border-radius:14px;padding:16px;max-width:560px;width:94%"><form method="post" action="/admin/manual-payments/entry" enctype="multipart/form-data" id="mpAf">@csrf
<div style="display:flex;justify-content:space-between;align-items:center"><b style="font-size:16px">+ Add Manual Payment</b><button type="button" onclick="this.closest('dialog').close()">✕</button></div>
<label>Customer Mobile</label><div style="display:flex;gap:6px"><input id="mpPh" placeholder="01XXXXXXXXX" inputmode="tel"><button type="button" id="mpFind">Find</button></div>
<div id="mpCu" style="font-size:13px;color:#475569;margin:6px 0"></div>
<label>Order / Invoice</label><select name="order_id" id="mpOr" required><option value="">Find customer first</option></select>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><div><label>Payment Method</label><select name="method"><option>bKash Personal</option><option>Nagad Personal</option><option>{{ \App\Support\Brand::BANK_LABEL }}</option><option>Cash</option><option>Other</option></select></div>
<div><label>Amount (৳)</label><input name="amount" id="mpAm" type="number" step="0.01" min="1" required></div>
<div><label>Transaction ID / Reference</label><input name="txn_id"></div><div><label>Sender Number</label><input name="sender"></div>
<div><label>Payment Date</label><input name="paid_at" type="datetime-local"></div><div><label>Received By</label><input name="received_by" value="{{ auth()->user()->name }}"></div></div>
<label>Note</label><input name="note"><label>Payment Proof / Screenshot</label><input type="file" name="proof" accept="image/*">
<div id="mpOv" style="display:none;background:#FEF3C7;border-radius:8px;padding:8px;margin-top:8px;font-size:13px"></div>
<label style="display:block;margin-top:8px"><input type="checkbox" name="dup_ok" value="1"> I checked the duplicate warning</label>
<label style="display:block"><input type="checkbox" name="mark_paid" value="1"> Save &amp; verify now (authorized staff)</label>
<div style="margin-top:10px;display:flex;gap:8px"><button class="btn">Save Payment</button><button type="button" class="btn g" onclick="this.closest('dialog').close()">Cancel</button></div></form></dialog>
<script>(function(){var due={};function $(i){return document.getElementById(i)}
$('mpFind').onclick=function(){fetch('/admin/manual-payments/lookup?phone='+encodeURIComponent($('mpPh').value),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(d){var s=$('mpOr');s.innerHTML='';due={};
if(!d.found){$('mpCu').textContent='No customer found for this number.';s.innerHTML='<option value="">No orders</option>';return}
$('mpCu').innerHTML='<b>'+esc(d.name)+'</b> · Orders '+d.total_orders+' · Unpaid '+d.unpaid+' · Partially paid '+d.partial+' · Outstanding ৳'+Math.round(d.outstanding).toLocaleString();
if(!d.orders.length){s.innerHTML='<option value="">No unpaid orders</option>';return}
d.orders.forEach(function(o){due[o.id]=o.due;var e=document.createElement('option');e.value=o.id;e.textContent=o.invoice+' · Total ৳'+o.total+' · Paid ৳'+o.paid+' · Due ৳'+o.due+' · '+o.status;s.appendChild(e)});s.onchange();}).catch(function(){$('mpCu').textContent='Lookup failed.'})};
$('mpOr').onchange=function(){if(due[this.value]!=null&&!$('mpAm').value)$('mpAm').value=due[this.value];chk()};
function chk(){var d=due[$('mpOr').value],a=parseFloat($('mpAm').value),b=$('mpOv');if(d!=null&&a>d){b.style.display='block';b.innerHTML='Payment amount exceeds order due by ৳'+Math.round(a-d)+'. <label><input type="radio" name="overpay" value="apply_due" checked> Apply ৳'+Math.round(d)+' to order</label> (or press Cancel)'}else b.style.display='none'}
$('mpAm').oninput=chk;if(location.hash==='#add'){document.getElementById('mpAdd').showModal();var q=new URLSearchParams(location.search);if(q.get('phone')){$('mpPh').value=q.get('phone');$('mpFind').onclick();var t=setInterval(function(){var s=$('mpOr');if(s.options.length>1||s.options[0].value){clearInterval(t);if(q.get('order'))s.value=q.get('order');s.onchange()}},300);setTimeout(function(){clearInterval(t)},6000)}}})();</script>
