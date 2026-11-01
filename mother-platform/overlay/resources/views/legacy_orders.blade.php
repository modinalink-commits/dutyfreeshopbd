@extends('layout')@section('title','Legacy Orders')
@section('body')
<h1 class="h">Legacy Order Import</h1>
<div class="card" style="max-width:760px"><table class="tb"><tbody>
@foreach([['Staged from old site',$d['staged']],['Already imported (action set)',$d['done']],['Live orders now',$d['live_orders']],['Live orders from old site',$d['imported_orders']],['Invoice already present in new system',$d['dups']]] as $r)<tr><td>{{ $r[0] }}</td><td>{{ number_format($r[1]) }}</td></tr>@endforeach
<tr><td>Date range</td><td>{{ $d['range']->a }} → {{ $d['range']->b }}</td></tr><tr><td>Total value</td><td>৳{{ number_format($d['range']->t) }}</td></tr>
</tbody></table></div>
<div class="card" style="max-width:760px;margin-top:12px;padding:12px"><b>By old status</b><table class="tb">@foreach($d['by_status'] as $s)<tr><td>{{ $s->status }} → {{ $map[$s->status] ?? '?' }}</td><td>{{ number_format($s->n) }}</td><td>৳{{ number_format($s->t) }}</td></tr>@endforeach</table>@if($d['result'])<p>{{ $d['result'] }}</p>@endif
<form method="post" action="/admin/legacy-orders">@csrf<input type="hidden" name="confirm" value="IMPORT"><button>Import one batch (600)</button></form></div>
@endsection
