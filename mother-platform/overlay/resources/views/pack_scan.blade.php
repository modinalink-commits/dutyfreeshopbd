@extends('layout')@section('title','Packaging Scan')
@section('body')
<h1>Packaging Scan</h1>
<p style="font-size:13px;color:#6B7280;margin:0 0 12px">Scan the label of each finished parcel when the courier collects. A Packaging order moves to Shipment; a second parcel of an order that already left is stopped.</p>
<button type="button" class="btn ps-open">Open Packaging Scan</button>
@include('pack_scan_ui')
<script>window.__psPage=true;window.addEventListener('load',function(){if(window.dfPackScan)dfPackScan()});</script>
@endsection
