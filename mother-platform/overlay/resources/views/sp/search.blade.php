@extends('sp.layout')@section('title','Search')
@section('body')<h1>Search</h1><form method="get" class="noprint" style="margin-bottom:12px"><input name="q" value="{{ $q }}" placeholder="PO number, bill, payment reference, product" autofocus></form>
@if($q === '' || mb_strlen($q) < 2)<div class="card empty">Type at least 2 characters.</div>@elseif(!count($out))<div class="card empty"><b>No results</b>Nothing matches “{{ $q }}”.</div>
@else @foreach($out as [$k,$a,$b,$h])<a class="ar" href="{{ $h }}"><span><span class="pb pb-info">{{ $k }}</span> &nbsp;<b style="color:var(--n);font-size:13px">{{ $a }}</b></span><small style="color:var(--mut)">{{ $b }}</small></a>@endforeach @endif @endsection
