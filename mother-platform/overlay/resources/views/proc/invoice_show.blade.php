@extends('layout')@section('title',$p->invoice)
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><h1 style="margin:0;flex:1">{{ $p->invoice }} <span class="pb pb-{{ $p->status }}">{{ $p->status }}</span></h1></div>@include('proc.nav')
<div class="card" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
@php $f = fn($a,$l,$cls='g sm',$ask='') => '<form method="post" action="/admin/purchase/invoices/'.$p->id.'/action" style="display:inline"'.($ask?' onsubmit="return confirm(\''.$ask.'\')"':'').'>'.csrf_field().'<input type="hidden" name="a" value="'.$a.'"><button class="'.$cls.'">'.$l.'</button></form>'; $wa = preg_replace('/\D/','',($s->whatsapp ?: $s->phone)); @endphp
<a class="btn g sm" target="_blank" href="/admin/purchase/invoices/{{ $p->id }}/export/pdf">Print / PDF</a><a class="btn g sm" href="/admin/purchase/invoices/{{ $p->id }}/export/xls">Excel</a><a class="btn g sm" href="/admin/purchase/invoices/{{ $p->id }}/export/doc">DOCX</a>
<button class="g sm" type="button" onclick="navigator.clipboard.writeText('{{ $link }}');this.textContent='Link copied'">Share link</button>{!! $f('sms','SMS invoice link') !!}<a class="btn g sm" target="_blank" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('DutyFreeBD Purchase Invoice '.$p->invoice.': '.$link) }}">WhatsApp link</a>
<a class="btn g sm" target="_blank" href="{{ $link }}">Open public page</a><span style="flex:1"></span>
@if(!in_array($p->status,['cancelled','draft','pending']))<a class="btn sm" href="/admin/purchase/payments/new?supplier={{ $p->supplier_id }}&invoice={{ $p->id }}">Make payment</a>@endif
@if(in_array($p->status,['draft','pending']) && \App\Support\Procure::canApprove()){!! $f('approve','Approve & receive stock','sm') !!}@endif @if($p->status==='approved'){!! $f('receive','Mark received','sm') !!}@endif {!! (!in_array($p->status,['draft','pending','cancelled']) && \Illuminate\Support\Facades\DB::table('purchase_items')->where('purchase_id',$p->id)->whereNotNull('new_selling_rate')->exists()) ? $f('rates','Check / apply selling price','sm') : '' !!}
@if($p->status!=='cancelled')<a class="btn g sm" href="/admin/purchase/edit/{{ $p->id }}">Edit</a>{!! $f('cancel','Cancel','g sm','Cancel this purchase and reverse the stock?') !!}@endif {!! $f('delete','Delete','g sm','Delete this purchase permanently?') !!}</div>
<div class="card" style="overflow:auto">@include('proc.invoice_doc')</div>
@if($p->note)<div class="card"><b>Internal note</b> <span class="mut">(not visible to the supplier)</span><p>{{ $p->note }}</p></div>@endif
@endsection
