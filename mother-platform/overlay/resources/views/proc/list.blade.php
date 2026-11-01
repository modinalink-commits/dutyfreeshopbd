@extends('layout')@section('title',$title)
@section('body')
<div class="hd"><div><h1>{{ $title }}</h1><div class="sub" style="margin-bottom:0">{{ $sub }}</div></div>@if(!empty($new))<a class="btn" href="{{ $new[1] }}">+ {{ $new[0] }}</a>@endif</div>
@include('proc.nav')
@if(!empty($tabs))<div class="chips" style="margin:-4px 0 12px">@foreach($tabs as [$tl,$tu,$ton])<a class="chip {{ $ton ? 'on' : '' }}" href="{{ $tu }}">{{ $tl }}</a>@endforeach</div>@endif
@if(!empty($ledger) || $kind==='ledger')@endif
<form class="card" method="get" style="padding:12px"><div class="fg">
@foreach($filters as $f)@php [$fn,$fl,$ft] = $f; $fo = $f[3] ?? []; $val = request($fn, $fn==='r' ? ($reportKey ?? '') : ''); @endphp
<div><label>{{ $fl }}</label>
@if($ft==='date')<input type="date" name="{{ $fn }}" value="{{ $val }}">
@elseif($ft==='supplier')<select name="{{ $fn }}"><option value="">All</option>@foreach($sup as $so)<option value="{{ $so->id }}" @selected((string)$val===(string)$so->id)>{{ $so->name }}{{ $so->code ? ' · '.$so->code : '' }}</option>@endforeach</select>
@elseif($ft==='store')<select name="{{ $fn }}"><option value="">All</option>@foreach($stores as $so)<option value="{{ $so->id }}" @selected((string)$val===(string)$so->id)>{{ $so->name }}</option>@endforeach</select>
@elseif($ft==='select')<select name="{{ $fn }}">@if($fn!=='r')<option value="">All</option>@endif @foreach($fo as $k=>$v)<option value="{{ $k }}" @selected((string)$val===(string)$k)>{{ $v }}</option>@endforeach</select>
@else<input name="{{ $fn }}" value="{{ $val }}" placeholder="{{ $fl }}">@endif</div>
@endforeach
<div style="display:flex;gap:6px;align-items:flex-end"><button>Filter</button><a class="btn g" href="{{ request()->url() }}">Reset</a></div></div></form>
@if($kind==='ledger' && empty($ledger))<div class="card">Select a supplier to see the ledger and statement.</div>
@else
@php $qs = http_build_query(request()->except(['page','ids'])); $ex = fn($f) => '/admin/purchase/export/'.$kind.'/'.$f.($qs ? '?'.$qs : ''); $nc = count($head); $skip = $noExportCol ?? -1; @endphp
<div class="card tw"><div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:8px"><b>{{ number_format($n) }} rows</b><span style="flex:1"></span>
<span class="mut" id="selc"></span>
@foreach([['xls','Excel'],['doc','DOCX'],['pdf','PDF'],['csv','CSV']] as [$f,$l])<a class="btn g sm exp" data-base="{{ $ex($f) }}" href="{{ $ex($f) }}">{{ $l }}</a>@endforeach <a class="btn g sm exp" data-base="{{ $ex('pdf') }}" href="{{ $ex('pdf') }}" target="_blank">Print</a>
@if($kind==='ledger' && !empty($s))<a class="btn g sm" href="{{ \App\Support\Procure::statementLink($s, request('from'), request('to')) }}" target="_blank">Statement page</a>
{!! '<form method="post" action="/admin/purchase/suppliers/'.$s->id.'/statement-sms" style="display:inline">'.csrf_field().'<button class="g sm">Send SMS link</button></form>' !!}
<a class="btn g sm" target="_blank" href="https://wa.me/{{ preg_replace('/\D/','',($s->whatsapp ?: $s->phone)) }}?text={{ rawurlencode('DutyFreeBD Statement: '.\App\Support\Procure::statementLink($s, request('from'), request('to'))) }}">WhatsApp</a>@endif</div>
<table><thead><tr><th style="width:24px"><input type="checkbox" id="all"></th>@foreach($head as $i=>$h)<th class="{{ in_array($i,$num ?? []) ? 'n' : '' }}">{{ $h }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr><td><input type="checkbox" class="sel" value="{{ $row['id'] }}"></td>@foreach($row['cells'] as $i=>$c)<td class="{{ in_array($i,$num ?? []) ? 'n' : '' }}">{!! $c !!}</td>@endforeach</tr>
@empty<tr><td colspan="{{ $nc+1 }}" class="mut">Nothing here yet.</td></tr>@endforelse
@if(!empty($foot))<tr class="tf"><td colspan="{{ $foot[1]+1 }}">{{ $foot[0] }}</td>@foreach($foot[2] as $c)<td class="n">{{ $c }}</td>@endforeach @php $rest = $nc - $foot[1] - count($foot[2]); @endphp @if($rest > 0)<td colspan="{{ $rest }}"></td>@endif</tr>@endif
</tbody></table>
@if($pages>1)<div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap">@for($i=1;$i<=$pages;$i++)<a class="chip {{ $i===$page?'on':'' }}" href="?{{ http_build_query(request()->except('page')+['page'=>$i]) }}">{{ $i }}</a>@endfor</div>@endif</div>
<script>(function(){var a=document.getElementById('all'),s=document.querySelectorAll('.sel'),c=document.getElementById('selc'),ex=document.querySelectorAll('.exp');
function up(){var ids=[];s.forEach(function(x){if(x.checked)ids.push(x.value)});c.textContent=ids.length?ids.length+' selected — export uses the selection':'';ex.forEach(function(e){var b=e.dataset.base;e.href=ids.length?b+(b.indexOf('?')>-1?'&':'?')+'ids='+ids.join(','):b})}
if(a)a.onchange=function(){s.forEach(function(x){x.checked=a.checked});up()};s.forEach(function(x){x.onchange=up})})();</script>
@endif
@endsection
