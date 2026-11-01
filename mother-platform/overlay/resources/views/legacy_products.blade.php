@extends('layout')@section('title','Legacy Products')
@section('body')
<h1 class="h">Legacy Product Import (dry-run)</h1>
<div class="card" style="max-width:760px"><table class="tb"><tbody>
@foreach([['Staged from old site',$d['staged']],['Products in the new system now',$d['live_products']],['Already in the new system (same old ID)',$d['matched']],['Missing in the new system',$d['unmatched']],['Matched with a different price',$d['price_diff']],['In new system but not in old export',$d['live_not_in_old']],['New products without an old ID',$d['live_no_ext']],['Already imported',$d['done']]] as [$l,$v])<tr><td>{{ $l }}</td><td><b>{{ number_format($v) }}</b></td></tr>@endforeach
</tbody></table></div>
<div class="card" style="max-width:760px;margin-top:12px;padding:12px"><b>Diagnostics</b><table class="tb">@foreach($d['cmp'] as $k=>$v)<tr><td>{{ $k }}</td><td>{{ number_format($v) }}</td></tr>@endforeach</table><b>In new system, not in old export</b><table class="tb">@foreach($d['liveonly'] as $s)<tr><td>{{ $s->ext_id }}</td><td>{{ $s->name }}</td><td>৳{{ number_format($s->price) }}</td><td>{{ $s->published ? 'pub' : 'unpub' }}</td></tr>@endforeach</table><b>Old status (all)</b><table class="tb">@foreach($d['status'] as $s)<tr><td>status {{ $s->s }}</td><td>{{ number_format($s->n) }}</td></tr>@endforeach</table>
<b>Missing in new system, by old status</b><table class="tb">@foreach($d['unm_by_status'] as $s)<tr><td>status {{ $s->s }}</td><td>{{ number_format($s->n) }}</td></tr>@endforeach</table>
<b>Newest missing products</b><table class="tb">@foreach($d['sample'] as $s)<tr><td>{{ $s->old_id }}</td><td>{{ $s->name }}</td><td>st {{ $s->status }}</td><td>৳{{ number_format($s->price) }}</td><td>stock {{ $s->stock }}</td></tr>@endforeach</table>
<p style="font-size:11px;color:#6b7280">products columns: {{ $d['cols'] }}</p></div>
@endsection
