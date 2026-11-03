<style>
.inc-card{position:relative;margin-top:12px;background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:16px}
.inc-card .dfx-bar{position:absolute;top:14px;right:16px;margin:0!important;float:none!important}
.inc-h{padding-right:110px}.inc-h h2{margin:0;font-size:17px;font-weight:800;color:#0F1F3D}.inc-h p{margin:3px 0 0;font-size:12px;color:#6B7280}
.inc-val{display:inline-block;margin-top:6px;font-size:12px;color:#374151;background:#F3F4F6;border-radius:12px;padding:2px 10px}.inc-val b{color:#0F1F3D}
.inc-bar{display:flex;align-items:center;gap:12px;margin:14px 0 10px;flex-wrap:wrap}
.inc-tabs{display:flex;gap:6px;overflow-x:auto;white-space:nowrap;flex:1;min-width:0;padding-bottom:2px}
.incomplete-tab{display:inline-flex;align-items:center;gap:7px;height:32px;padding:0 12px;border:1px solid #E5E7EB;border-radius:16px;background:#fff;font-size:12px;font-weight:600;color:#111827;text-decoration:none}
.incomplete-tab:hover{background:#F9FAFB;border-color:#CBD5E1}
.incomplete-tab i{font-style:normal;font-size:11px;font-weight:700;min-width:20px;text-align:center;padding:1px 6px;border-radius:10px;background:#F3F4F6;color:#374151}
.incomplete-tab.active{background:#0F1F3D;color:#fff;border-color:#0F1F3D}.incomplete-tab.active i{background:rgba(255,255,255,.2);color:#fff}
.inc-s{display:flex;gap:6px;align-items:center}.inc-s input{width:190px;height:32px;margin:0}.inc-s button{height:32px;padding:0 14px}
.inc-card table tbody tr{border-bottom:1px solid #F1F5F9}
.incomplete-cell{min-width:160px}.incomplete-actions{display:flex;flex-direction:column;align-items:flex-end;gap:6px}.incomplete-actions .inc-act{display:flex;align-items:center;gap:6px;margin:0}.incomplete-actions a.btn-convert,.incomplete-actions .inc-act button{display:inline-flex;align-items:center;justify-content:center;min-height:30px;padding:0 10px;border-radius:7px;font-size:12px;font-weight:600;line-height:1;white-space:nowrap;cursor:pointer;text-decoration:none;box-sizing:border-box}.incomplete-actions a.btn-convert{background:#FF5A00;border:1px solid #FF5A00;color:#fff;padding:0 12px}.incomplete-actions a.btn-convert:hover{background:#E04E00;border-color:#E04E00}.incomplete-actions a.btn-linked{background:#fff;color:#374151;border-color:#CFD4DA}.incomplete-actions a.btn-linked:hover{background:#F3F4F6}.incomplete-actions .inc-act button{background:#fff}@media(max-width:760px){.incomplete-actions{align-items:stretch}.incomplete-actions .inc-act{flex-wrap:wrap}.incomplete-actions a.btn-convert,.incomplete-actions .inc-act button{min-height:40px}}
.inc-act .rc{color:#16803C;border:1px solid #9ED9B1}.inc-act .rc:hover{background:#F0FDF4}.inc-act .ar{color:#5F6670;border:1px solid #CFD4DA}.inc-act .ar:hover{background:#F3F4F6}.inc-act .ro{color:#374151;border:1px solid #CFD4DA}.inc-act .ro:hover{background:#F3F4F6}
.inc-ab{font-size:10px;color:#92400E;background:#FEF3C7;border-radius:8px;padding:0 6px;margin-left:4px}
</style>
<div class="inc-card">
<div class="inc-h"><h2>Incomplete Orders</h2><p>Customers who entered checkout information but did not complete the order. Nothing is confirmed; no SMS, stock or courier is touched.</p><span class="inc-val">Open Cart Value: <b>৳{{ number_format($incSum) }}</b></span></div>
<div class="inc-bar"><div class="inc-tabs">@foreach($incTabs as $k=>$l)<a class="incomplete-tab {{ $incSt===$k ? 'active' : '' }}" href="/admin/orders?status=Incomplete&inc={{ $k }}">{{ str_replace(' to order', '', $l) }} <i>{{ $incCounts[$k] ?? 0 }}</i></a>@endforeach</div>
<form method="get" class="inc-s"><input type="hidden" name="status" value="Incomplete"><input type="hidden" name="inc" value="{{ $incSt }}"><input name="q" value="{{ request('q') }}" placeholder="Phone or name"><button>Search</button></form></div>
<form method="post" action="/admin/orders">@csrf<div style="overflow-x:auto"><table><thead><tr><th></th><th>Invoice</th><th>Customer</th><th>Product</th><th>Total</th><th>Shipping</th><th>Status</th><th>Note</th><th>Courier</th><th>Action</th></tr></thead><tbody>
@forelse($incRows as $o)@include('orders_inc_row', ['o'=>$o, 'imgs'=>$incImgs, 'cid'=>$incCust[$o->phone] ?? null])@empty<tr><td colspan="10" style="padding:14px;color:#6B7280">Nothing here.</td></tr>@endforelse</tbody></table></div></form>
<div style="margin-top:12px">{{ $incRows->links('pagination.global', ['perSel'=>true]) }}</div></div>
