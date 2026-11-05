@extends('layout')
@section('title', 'ERP modules')
@section('content')
<header><h1>ERP modules</h1><p class="sub">All 15 modules from the design. Each opens with the same form, approval and history layout.</p></header>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px">
  @foreach($defs as $k => $d)
    <a class="card" href="{{ route('erp.module', $k) }}" style="padding:16px;display:flex;gap:12px;text-decoration:none;color:inherit">
      <span style="font-size:12px;font-weight:800;color:#1d4ed8;background:#e6eeff;border-radius:8px;padding:6px 8px;height:fit-content">{{ str_pad((string) $d['no'], 2, '0', STR_PAD_LEFT) }}</span>
      <span style="display:flex;flex-direction:column;gap:2px"><b>{{ $d['title'] }}</b><span style="font-family:'Hind Siliguri';font-size:13px;color:#51607a">{{ $d['bn'] }}</span><span class="sub" style="margin:0;font-size:11px">{{ $d['path'] }} · {{ $counts[$k] ?? 0 }} records</span></span></a>
  @endforeach
</div>
@endsection
