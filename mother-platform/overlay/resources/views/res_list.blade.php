@extends('layout')@section('title',$c['title'])
@section('body')
<div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap"><div style="flex:1;min-width:240px"><h1 style="margin:0">{{ $c['title'] }}</h1>@if(!empty($c['subtitle']))<span style="font-size:13px;color:#6B7280">{{ $c['subtitle'] }}</span>@endif</div>
@foreach(['pdf'=>'PDF','xls'=>'Excel','doc'=>'Word','csv'=>'CSV'] as $k=>$l)<a class="btn g" @if($k==='pdf') target="_blank" @endif href="/admin/m/{{ $c['slug'] }}/export/{{ $k }}?{{ http_build_query(request()->query()) }}">{{ $l }}</a>@endforeach
@if(empty($c['ro']) && empty($c['noCreate']))<a class="btn" href="/admin/m/{{ $c['slug'] }}/new">Add new</a>@endif</div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
@if(!empty($c['note']))<p style="font-size:12px;color:#6B7280">{{ $c['note'] }}</p>@endif
@if($sum)<div class="grid" style="margin-bottom:16px">@foreach($sum as [$l,$v])<div class="card k" style="margin:0"><b>{{ $v }}</b><span>{{ $l }}</span></div>@endforeach</div>@endif
@if(!empty($c['search']) || !empty($c['filters']))<form class="card" style="display:flex;gap:8px;flex-wrap:wrap">
@if(!empty($c['search']))<input name="q" value="{{ request('q') }}" placeholder="Search" style="flex:1;min-width:160px">@endif
@foreach($c['filters'] ?? [] as $fn)@php $ff = collect($c['fields'])->firstWhere('n',$fn); @endphp
<select name="{{ $fn }}" style="max-width:190px"><option value="">{{ $ff['l'] }}: all</option>
@if($ff['t']==='select')@foreach($ff['o'] as $ov=>$ol)<option value="{{ $ov }}" {{ request($fn)==(string)$ov?'selected':'' }}>{{ $ol }}</option>@endforeach
@elseif($ff['t']==='fk')@foreach($lk[$fn] ?? [] as $ov=>$ol)<option value="{{ $ov }}" {{ request($fn)==(string)$ov?'selected':'' }}>{{ $ol }}</option>@endforeach
@else
@foreach(\App\Models\Mother::STAGES as $ol)<option value="{{ $ol }}" {{ request($fn)===$ol?'selected':'' }}>{{ $ol }}</option>@endforeach @endif</select>@endforeach
<button>Filter</button></form>@endif
<div class="card" style="overflow-x:auto"><table><thead><tr><th>#</th>@foreach($c['fields'] as $f)@if(($f['t']??'')!=='password' && empty($f['noList']))<th>{{ $f['l'] }}</th>@endif @endforeach@foreach($c['extra'] ?? [] as [$xk,$xl])<th>{{ $xl }}</th>@endforeach<th></th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ $row->id }}</td>
@foreach($c['fields'] as $f)@if(($f['t']??'')!=='password' && empty($f['noList']))<td>@php $v = $ctl->cell($c,$row,$f,$lk); @endphp
@if($f['t']==='bool')<span class="pill {{ $v==='Yes'?'ok':'' }}">{{ $v }}</span>@else{{ \Illuminate\Support\Str::limit($v, 70) }}@endif</td>@endif @endforeach
@foreach($c['extra'] ?? [] as [$xk,$xl])<td>{{ $ex[$row->id][$xk] ?? '' }}</td>@endforeach
<td style="white-space:nowrap">@if(empty($c['ro']) && empty($c['noEdit']))<a class="btn g" href="/admin/m/{{ $c['slug'] }}/{{ $row->id }}/edit">Edit</a>@endif
@if(empty($c['ro']) && empty($c['noCreate']))<form method="post" action="/admin/m/{{ $c['slug'] }}/{{ $row->id }}/delete" style="display:inline" onsubmit="return confirm('Delete this record?')">@csrf<button class="g">Delete</button></form>@endif</td></tr>
@empty<tr><td colspan="30">No records yet.</td></tr>@endforelse</tbody></table>{{ $rows->links() }}</div>
@includeIf('panels.'.$c['slug'])
@endsection
