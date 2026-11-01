@extends('layout')@section('title','Legacy Customer Import')
@section('body')
<h1 style="margin:0">Old DutyFreeBD customers → new system</h1>
<div style="font-size:12px;color:#6B7280;margin-bottom:10px">Review first. Only real customers are imported: one per mobile number, obvious test entries without orders are skipped. Existing customers keep their name and data; only empty fields and old order totals are filled in.</div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<div class="card"><table><tbody>
@foreach([['Staged from old site (unique mobile numbers)',$d['staged']],['Real customers to import',$d['real']],['Skipped test entries',$d['test']],['Already in the new system (will be filled, not replaced)',$d['matched']],['New customers to create',$d['new']],['Customers with old orders',$d['with_orders']],['Old orders in total',number_format($d['sum_orders'])],['Old order value in total','৳'.number_format($d['sum_amount'])],['With email',$d['email']],['Already imported',$d['done']],['Customers in the new system now',$d['now']]] as [$l,$v])<tr><td>{{ $l }}</td><td class="n"><b>{{ is_numeric($v) ? number_format($v) : $v }}</b></td></tr>@endforeach</tbody></table></div>
<div class="card" style="margin-top:12px;padding:12px"><b>Top amounts (check for bad values) — rows over ৳50 lakh: {{ $d['huge'] }}</b><table>@foreach($d['top'] as $t)<tr><td>{{ $t->phone }}</td><td>{{ $t->name }}</td><td>{{ $t->orders_count }}</td><td>৳{{ number_format($t->orders_amount) }}</td></tr>@endforeach</table></div>
<div class="card"><h2>Membership by lifetime order value</h2><table><tbody>@foreach($d['tiers'] as $t)<tr><td>{{ $t->t }}</td><td class="n">{{ number_format($t->n) }}</td></tr>@endforeach</tbody></table></div>
@if($d['done']==0)<form class="card" method="post" action="/admin/legacy-import">@csrf<label>Type IMPORT to confirm</label><input name="confirm" autocomplete="off" required style="max-width:200px"> <button>Import now</button></form>@endif
@endsection
