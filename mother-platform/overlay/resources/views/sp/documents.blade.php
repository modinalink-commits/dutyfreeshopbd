@extends('sp.layout')@section('title','Documents')
@section('body')<h1>Documents</h1><p class="sub">Purchase orders, bills, ledger statements, payment advices, return notes, delivery challans and agreements.</p>
@if(count($out))<div class="tw mcards"><table><thead><tr><th>Type</th><th>Document</th><th>Date</th><th></th></tr></thead><tbody>@foreach($out as [$k,$n,$d,$h])<tr><td data-l="Type"><span class="pb pb-info">{{ $k }}</span></td><td data-l="Document"><b>{{ $n }}</b></td><td data-l="Date">{{ $d }}</td><td><a class="btn g sm" href="{{ $h }}" target="_blank">Open / Print</a></td></tr>@endforeach</tbody></table></div>
@else<div class="card empty"><b>No documents yet</b>Documents appear as orders, bills and payments are created.</div>@endif @endsection
