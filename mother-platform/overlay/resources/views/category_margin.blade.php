@extends('layout')@section('title','Category margin update')
@section('body')
<h1>Category margin update</h1>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<form method="get" class="card" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><select name="category">@foreach($cats as $c)<option value="{{ $c->id }}" @selected($c->id==$cat)>{{ $c->name }}</option>@endforeach</select> Profit % (on FIFO cost) <input name="percent" value="{{ $pct }}" style="width:60px"> round up to ৳ <input name="step" value="{{ $step }}" style="width:50px"><button class="btn" type="submit">Preview</button></form>
@if($cat)<div class="card" style="margin-top:10px"><b>{{ $name }}</b>: {{ count($plan) }} prices will be raised to at least {{ $pct }}% profit (prices are only raised, never lowered).
@if(count($plan))<form method="post" style="display:inline;margin-left:12px">@csrf<input type="hidden" name="category" value="{{ $cat }}"><input type="hidden" name="percent" value="{{ $pct }}"><input type="hidden" name="step" value="{{ $step }}"><button class="btn" type="submit">Apply</button></form>@endif</div>
<table style="width:100%;margin-top:8px"><thead><tr><th style="text-align:left">Product</th><th>FIFO cost</th><th>Old price</th><th>Old profit %</th><th>New price</th><th>New profit %</th></tr></thead><tbody>
@foreach($plan as $x)<tr><td>{{ $x['name'] }}</td><td style="text-align:right">{{ number_format($x['cost'],2) }}</td><td style="text-align:right">{{ number_format($x['old'],0) }}</td><td style="text-align:right">{{ number_format(($x['old']-$x['cost'])/$x['cost']*100,1) }}</td><td style="text-align:right"><b>{{ number_format($x['new'],0) }}</b></td><td style="text-align:right">{{ number_format(($x['new']-$x['cost'])/$x['cost']*100,1) }}</td></tr>@endforeach
</tbody></table>@endif
@endsection
