@extends('layout')@section('title','Reports and Documents')
@section('body')<h1>Reports and Document Center</h1>
<div class="grid" style="margin-bottom:16px">
<div class="card" style="margin:0"><b>Last 30 days</b><table><thead><tr><th>Date</th><th>Orders</th><th>Sales</th></tr></thead><tbody>@forelse($byDay as $d)<tr><td>{{ $d->d }}</td><td>{{ $d->n }}</td><td>৳{{ number_format($d->s) }}</td></tr>@empty<tr><td colspan="3">No orders yet.</td></tr>@endforelse</tbody></table></div>
<div class="card" style="margin:0"><b>By order stage</b><table><tbody>@forelse($byStatus as $s)<tr><td>{{ $s->status }}</td><td>{{ $s->n }}</td><td>৳{{ number_format($s->s) }}</td></tr>@empty<tr><td>No orders yet.</td></tr>@endforelse</tbody></table></div>
<div class="card" style="margin:0"><b>Top products</b><table><tbody>@forelse($top as $t)<tr><td>{{ $t->name }}</td><td>{{ $t->q }}</td><td>৳{{ number_format($t->s) }}</td></tr>@empty<tr><td>No sales yet.</td></tr>@endforelse</tbody></table></div></div>
<div class="card"><b>Download any module</b><p style="font-size:12px;color:#6B7280;margin:4px 0 10px">PDF opens a print-ready page (Save as PDF); Excel and Word open directly in those apps; CSV works everywhere.</p>
<table><thead><tr><th>Document</th><th>Records</th><th>PDF</th><th>Excel</th><th>Word</th><th>CSV</th></tr></thead><tbody>
@foreach($docs as $g=>$items)<tr><td colspan="6" style="background:#FFF1E8;font-weight:600">{{ $g }}</td></tr>
@foreach($items as [$s,$t,$n])<tr><td>{{ $t }}</td><td>{{ number_format($n) }}</td><td><a href="/admin/m/{{ $s }}/export/pdf" target="_blank">PDF</a></td><td><a href="/admin/m/{{ $s }}/export/xls">Excel</a></td><td><a href="/admin/m/{{ $s }}/export/doc">Word</a></td><td><a href="/admin/m/{{ $s }}/export/csv">CSV</a></td></tr>@endforeach @endforeach</tbody></table></div>@endsection
