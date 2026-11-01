@extends('layout')@section('title','Referral / Affiliate')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Referral / Affiliate</h1><div style="font-size:12px;color:#6B7280">Friend gets a first-order discount; the referrer earns wallet credit after the order is delivered.</div></div>
<span style="font-size:11px;font-weight:700;border-radius:999px;padding:4px 12px;background:{{ $set['on'] ? '#DCFCE7' : '#FEE2E2' }};color:{{ $set['on'] ? '#166534' : '#B91C1C' }}">{{ $set['on'] ? 'PROGRAM ON' : 'PROGRAM OFF' }}</span></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:12px">
@foreach([['Total Referrals',$st['total']],['Successful',$st['ok']],['Pending',$st['pending']],['Cancelled / Void',$st['void']],['Total Discount Given','৳'.number_format($st['disc'])],['Credit Issued','৳'.number_format($st['credit'])],['Unused Credit Balance','৳'.number_format($st['bal'])]] as [$l,$v])
<div class="card" style="margin:0;padding:10px 12px"><span style="font-size:11.5px;color:#6B7280;display:block">{{ $l }}</span><b style="font-size:18px">{{ $v }}</b></div>@endforeach</div>
<div class="card"><h2>Settings</h2><form method="post" action="/admin/referral/settings">@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;align-items:end">
<div><label>Referral Program</label><select name="on"><option value="1" @selected($set['on'])>ON</option><option value="0" @selected(!$set['on'])>OFF</option></select></div>
<div><label>New Customer Discount (৳)</label><input type="number" min="0" step="1" name="discount" value="{{ $set['discount'] }}"></div>
<div><label>Referrer Reward (৳)</label><input type="number" min="0" step="1" name="reward" value="{{ $set['reward'] }}"></div>
<div><label>Minimum Order Amount (৳)</label><input type="number" min="0" step="1" name="min_order" value="{{ $set['min_order'] }}"></div>
<div><label>Reward after status</label><select name="after"><option @selected($set['after']==='Delivered')>Delivered</option><option @selected($set['after']==='Completed')>Completed</option></select></div>
<div><label>Max referrals per customer (0 = no limit)</label><input type="number" min="0" step="1" name="max" value="{{ $set['max'] }}"></div>
<div><label>Credit usable at checkout</label><select name="credit_use"><option value="1" @selected($set['credit_use']==='1')>Yes — automatic</option><option value="0" @selected($set['credit_use']!=='1')>No</option></select></div>
<div><button>Save settings</button></div></div></form>
<p style="font-size:12px;color:#6B7280;margin:8px 0 0">Rules: the discount applies only to a new customer's first order, once per customer; a customer cannot use their own link; rewards are confirmed only after the referred order reaches the chosen status; cancelled or returned orders never earn a reward.</p></div>
<div class="card"><form method="get" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px"><input name="q" value="{{ $f['q'] ?? '' }}" placeholder="Search phone / customer / referral code" style="flex:1;min-width:220px"><select name="status" style="width:auto"><option value="">All status</option>@foreach(['pending'=>'Pending','rewarded'=>'Successful','void'=>'Cancelled / Void'] as $k=>$l)<option value="{{ $k }}" @selected(($f['status'] ?? '')===$k)>{{ $l }}</option>@endforeach</select><button>Search</button></form>
<div style="overflow:auto"><table><thead><tr><th>Date</th><th>Referrer</th><th>Code</th><th>Referred customer</th><th>Order</th><th class="n">Amount</th><th class="n">Discount</th><th class="n">Reward</th><th>Order status</th><th>Reward status</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td>{{ \Carbon\Carbon::parse($x->created_at)->timezone('Asia/Dhaka')->format('d M Y') }}</td><td><b>{{ $x->rname }}</b><br><small style="color:#6B7280">{{ $x->rphone }}</small></td><td>{{ $x->code }}</td><td>{{ $x->bname ?: '—' }}<br><small style="color:#6B7280">{{ $x->referred_phone }}</small></td><td>{{ $x->invoice ?: '—' }}</td><td class="n">৳{{ number_format($x->order_amount) }}</td><td class="n">৳{{ number_format($x->discount) }}</td><td class="n">৳{{ number_format($x->reward) }}</td><td>{{ $x->ostatus ?: $x->order_status }}</td>
<td><span class="pill {{ $x->status==='rewarded' ? 'ok' : ($x->status==='void' ? 'red' : '') }}">{{ $x->status==='rewarded' ? 'REWARDED' : ($x->status==='void' ? 'VOID' : 'PENDING') }}</span>@if($x->note)<br><small style="color:#6B7280">{{ $x->note }}</small>@endif</td></tr>
@empty<tr><td colspan="10" style="color:#6B7280">No referrals yet.</td></tr>@endforelse</tbody></table></div></div>
@endsection
