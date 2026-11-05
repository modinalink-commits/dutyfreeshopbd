@extends('portal.layout', ['tab' => 'home'])
@section('title', 'স্টেটমেন্ট')
@php($U = \App\Support\Ui::class)
@section('bar')@include('portal._back', ['title' => 'স্টেটমেন্ট'])@endsection
@section('content')
<form method="get" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px" onchange="this.submit()"><label class="lb" style="display:flex;flex-direction:column;gap:4px">শুরু<input class="txt" type="date" name="from" value="{{ $from }}"></label><label class="lb" style="display:flex;flex-direction:column;gap:4px">শেষ<input class="txt" type="date" name="to" value="{{ $to }}"></label></form>
@php($close = $open + $buy - $pay + $adj)
@php($kv = fn ($k, $v, $b = false) => '<div style="display:flex;font-size:var(--fs-14);padding:7px 0;border-top:1px solid #eef2f9"><span style="flex-grow:1;'.($b ? 'font-weight:700' : 'color:#51607a').'">'.e($k).'</span><b class="n">'.e($v).'</b></div>')
<div class="card">{!! $kv('শুরুর বকেয়া', $U::tk($open)) !!}{!! $kv('(+) ক্রয়', $U::tk($buy)) !!}{!! $kv('(−) পরিশোধ', $U::tk($pay)) !!}{!! $kv('(±) সমন্বয়', $U::tk($adj)) !!}{!! $kv('শেষ বকেয়া', $U::tk($close), true) !!}</div>
<div class="card" style="display:flex;flex-direction:column;gap:2px"><b style="font-size:var(--fs-14)">লেনদেন</b>
  @forelse($rows as $r)
    @php($amt = (float) $r->debit - (float) $r->credit)
    <div style="display:flex;gap:8px;padding:7px 0;border-top:1px solid #eef2f9;font-size:var(--fs-14)"><span class="n" style="color:#51607a;width:44px">{{ $r->created_at->timezone('Asia/Dhaka')->format('d-m') }}</span><span style="flex-grow:1">{{ ['sale' => 'ক্রয়', 'payment' => 'পরিশোধ', 'void' => 'বাতিল', 'opening' => 'শুরুর বকেয়া'][$r->kind] ?? 'সমন্বয়' }} {{ $r->reference }}</span><b class="n" style="color:{{ $amt < 0 ? '#0f6b3c' : '#14213d' }}">{{ $amt < 0 ? '−' : '+' }}{{ number_format(abs($amt)) }}</b></div>
  @empty<span style="font-size:var(--fs-13);color:#51607a">এই সময়ে লেনদেন নেই।</span>@endforelse
</div>
@if($cyl)<div class="card"><b style="font-size:var(--fs-14)">সিলিন্ডার ({{ \App\Models\Brand::query()->where('for_sale', true)->value('name') ?? 'Aygaz United' }})</b>@foreach($cyl as $s => [$b, $g]){!! $kv($U::bn($s).' কেজি কেনা / খালি দিলাম', $b.' / '.$g) !!}@endforeach</div>@endif
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px"><button class="chip" type="button" onclick="window.print()">PDF ডাউনলোড</button><button class="chip" type="button" onclick="window.print()">A4 প্রিন্ট</button></div>
@endsection
