@extends('layout')
@section('title', 'দিন শেষের হিসাব — '.$trip->vehicle->code)
@section('content')
@include('route._css')
@php($U = \App\Support\Ui::class)
@php($bn = fn ($s) => $U::bn($s))
@php($T = ['লোড মিলান', 'বিক্রি মিলান', 'ভরা ফেরত', 'খালি সংগ্রহ', 'নগদ আদায়', 'পার্থক্য দেখুন', 'অনুমোদনে পাঠান'])
@php($cur = $step - 1)
@php($go = fn ($n) => route('trips.wizard', ['trip' => $trip->id, 'step' => $n]))
@php($see = fn ($url) => '<a href="'.e($url).'" style="font-size:12px;color:#1d4ed8;margin-left:6px">হিসাব দেখুন</a>')
<style>.wz{font-family:'Hind Siliguri','Plus Jakarta Sans',sans-serif}.wz .btn{min-height:44px;padding:0 18px;font:700 14px 'Hind Siliguri',sans-serif}.wz .n{font-family:'Plus Jakarta Sans',sans-serif;font-variant-numeric:tabular-nums}.wz .bx{border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:8px}</style>
<div class="wz" style="display:flex;flex-direction:column;gap:16px;max-width:980px">
<header style="display:flex;flex-direction:column;gap:2px">
  <h1 style="font-size:24px">গাড়ির দিন শেষের হিসাব — {{ $trip->vehicle->code }}</h1>
  <span style="font-size:14px;color:#51607a">{{ $trip->trip_no }} · {{ $trip->trip_date->format('d-m-Y') }} · {{ $trip->salesman?->name ?? '—' }} / {{ $trip->driver?->name ?? '—' }} · ধাপে ধাপে মিলিয়ে নিন</span>
</header>
<ol style="list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px">
  @foreach($T as $i => $t)
    <li><a href="{{ $go($i + 1) }}" style="display:flex;flex-direction:column;gap:6px;text-decoration:none">
      <span style="height:6px;border-radius:999px;background:{{ $i < $cur ? '#1f9d55' : ($i === $cur ? '#c46f00' : '#dbe3f0') }}"></span>
      <span style="font-size:12px;line-height:16px;font-weight:{{ $i === $cur ? 700 : 500 }};color:{{ $i < $cur ? '#0f6b3c' : ($i === $cur ? '#8a4b00' : '#51607a') }}">{{ $bn($i + 1) }}. {{ $t }}</span></a></li>
  @endforeach
</ol>
<section class="card" style="padding:22px;display:flex;flex-direction:column;gap:14px">
  <span style="font:800 11px 'Plus Jakarta Sans',sans-serif;letter-spacing:.1em;color:#8a4b00">ধাপ {{ $bn($step) }} · {{ $T[$cur] }}</span>
  @php($sz = $c['sizes'])
  @if($step === 1)
    <h2 style="margin:0;font-size:20px">সকালে গাড়িতে কত সিলিন্ডার উঠেছে {!! $see(route('trips.loading', ['trip' => $trip->id])) !!}</h2>
    <div style="display:flex;flex-wrap:wrap;gap:12px">@forelse($sz as $s => $z)<div class="bx" style="background:#e6eeff;min-width:150px"><strong style="color:#1d4ed8;font-size:16px"><span class="n">{{ $s }}</span> কেজি</strong><span style="font-size:22px;font-weight:700" class="n">{{ $z['loaded'] }}</span></div>@empty<p>এই ট্রিপে কিছু লোড হয়নি।</p>@endforelse</div>
    <span style="font-size:14px">মোট লোড <strong class="n">{{ $c['loadedTotal'] }}</strong>টা। {{ $trip->status === 'draft' ? 'ম্যানেজার এখনো অনুমোদন দেননি।' : 'ম্যানেজার অনুমোদন দিয়ে গাড়ি পাঠিয়েছেন।' }}</span>
  @elseif($step === 2)
    <h2 style="margin:0;font-size:20px">বাজারে কত বিক্রি হয়েছে {!! $see(route('trips.sales', $trip)) !!}</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">@foreach($sz as $s => $z)<div class="bx" style="background:#def5f7"><strong style="color:#0b6670;font-size:16px"><span class="n">{{ $s }}</span> কেজি</strong><span style="font-size:14px">বদলে বিক্রি <strong class="n">{{ $z['exch'] }}</strong> · নতুন সিলিন্ডার <strong class="n">{{ $z['new'] }}</strong></span></div>@endforeach</div>
    <span style="font-size:14px"><span class="n">{{ $c['invoices'] }}</span>টা মেমো · মোট বিক্রি <strong class="n">{{ $c['soldTotal'] }}</strong>টা · <strong class="n">{{ $U::tk($c['totalSales']) }}</strong></span>
  @elseif($step === 3)
    <h2 style="margin:0;font-size:20px">ভরা সিলিন্ডার: লোড = বিক্রি + ফেরত {!! $see(route('trips.return', ['trip' => $trip->id])) !!}</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">@foreach($sz as $s => $z)<div class="bx" style="background:{{ $z['missing'] && $trip->status !== 'dispatched' ? '#fde9e7' : '#e3f6ec' }}"><strong style="font-size:16px"><span class="n">{{ $s }}</span> কেজি</strong>
      <span style="font-size:14px;line-height:22px">লোড <span class="n">{{ $z['loaded'] }}</span> − বিক্রি <span class="n">{{ $z['exch'] + $z['new'] }}</span> − ফেরত <span class="n">{{ $z['full_returned'] }}</span> − নষ্ট <span class="n">{{ $z['damaged'] }}</span> = <strong class="n">{{ $trip->status === 'dispatched' ? '—' : $z['missing'] }}</strong> কম</span></div>@endforeach</div>
    @if($trip->status === 'dispatched')<span style="font-size:14px;color:#8a4b00">গাড়ি এখনো ফেরত আসেনি। <a href="{{ route('trips.return', ['trip' => $trip->id]) }}">ফেরত এন্ট্রি দিন</a></span>@endif
  @elseif($step === 4)
    <h2 style="margin:0;font-size:20px">খালি সিলিন্ডার: সংগ্রহ = গুদামে জমা {!! $see(route('trips.return', ['trip' => $trip->id])) !!}</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">@foreach($sz as $s => $z)<div class="bx" style="background:{{ $z['emptyDiff'] && $trip->status !== 'dispatched' ? '#fde9e7' : '#e3f6ec' }}"><strong style="font-size:16px"><span class="n">{{ $s }}</span> কেজি</strong>
      <span style="font-size:14px;line-height:22px">দোকান থেকে নিয়েছেন <span class="n">{{ $z['collected'] }}</span>টা, গুদামে জমা <span class="n">{{ $z['empty_returned'] }}</span>টা। দোকানে বাকি <span class="n">{{ $z['pending'] }}</span>টা।</span></div>@endforeach</div>
  @elseif($step === 5)
    <h2 style="margin:0;font-size:20px">নগদ আদায় {!! $see(route('trips.settle', ['trip' => $trip->id])) !!}</h2>
    <div class="bx" style="background:#eef2f9;font-size:15px;line-height:26px">
      <span>আজকের নগদ বিক্রি <strong class="n">{{ $U::tk($c['cashSales']) }}</strong> + আগের বাকি আদায় <strong class="n">{{ $U::tk($c['prevCash']) }}</strong> = নগদ আদায় <strong class="n">{{ $U::tk($c['cashCollected']) }}</strong></span>
      <span>নগদ আদায় − খরচ <strong class="n">{{ $U::tk($c['expFromCash']) }}</strong> = জমা হওয়ার কথা <strong class="n">{{ $U::tk($c['expected']) }}</strong></span>
      <span>গুনে পাওয়া গেছে <strong class="n">{{ $c['counted'] !== null ? $U::tk($c['counted']) : '—' }}</strong></span>
    </div>
  @elseif($step === 6)
    @php($items = [])
    @foreach($sz as $s => $z)
      @if($z['vehEmpty'] > 0) @php($items[] = ['t' => $bn($z['vehEmpty']).'টা খালি '.$bn($s).' কেজি কম', 'b' => 'দোকান থেকে নিয়েছেন <span class="n">'.$z['collected'].'</span>টা, গুদামে জমা হয়েছে <span class="n">'.$z['empty_returned'].'</span>টা।', 'note' => $trip->return_remarks,
        'btns' => [['দোকানের বাকিতে রাখুন', route('trips.closing', ['trip' => $trip->id, 'adj' => collect($options)->keys()->first(fn ($k) => str_starts_with($k, "empty_shop:$s:"))])], ['খুঁজে পেয়েছি', route('trips.closing', ['trip' => $trip->id, 'adj' => "empty_found:$s:{$z['vehEmpty']}"])]]]) @endif
      @if($z['vehFull'] > 0) @php($items[] = ['t' => $bn($z['vehFull']).'টা ভরা '.$bn($s).' কেজি কম', 'b' => 'লোড <span class="n">'.$z['loaded'].'</span>, বিক্রি <span class="n">'.($z['exch'] + $z['new']).'</span>, ফেরত <span class="n">'.$z['full_returned'].'</span>।', 'note' => null,
        'btns' => [['খুঁজে পেয়েছি', route('trips.closing', ['trip' => $trip->id, 'adj' => "full_found:$s:{$z['vehFull']}"])]]]) @endif
    @endforeach
    @if($c['cashDiff'] !== null && abs($c['cashDiff']) >= .5 && ! $c['cashAdj'])
      @php($items[] = ['t' => '<span class="n">'.$U::tk(abs($c['cashDiff'])).'</span> নগদ '.($c['cashDiff'] < 0 ? 'কম' : 'বেশি'), 'b' => 'জমা হওয়ার কথা <span class="n">'.$U::tk($c['expected']).'</span>, গুনে পাওয়া গেছে <span class="n">'.$U::tk($c['counted']).'</span>।',
        'note' => 'হিসাব: নগদ আদায় '.$U::tk($c['cashCollected']).' − খরচ '.$U::tk($c['expFromCash']).' = '.$U::tk($c['expected']),
        'btns' => $c['cashDiff'] < 0 ? [['আবার গুনুন', route('trips.settle', ['trip' => $trip->id])], ['সেলসম্যানের কাছ থেকে নেওয়া হবে', route('trips.closing', ['trip' => $trip->id, 'adj' => 'cash_recover'])]] : [['বেশি টাকা রাখুন', route('trips.closing', ['trip' => $trip->id, 'adj' => 'cash_extra'])]]])
    @endif
    <h2 style="margin:0;font-size:20px">{{ $trip->status !== 'settled' && $trip->status !== 'closed' ? 'আগের ধাপগুলো এখনো শেষ হয়নি' : (count($items) ? $bn(count($items)).'টা জিনিস মেলেনি' : 'সব মিলে গেছে') }}</h2>
    @if(count($items))
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px">
      @foreach($items as $it)
        <div class="bx" style="background:#fde9e7"><strong style="color:#b3261e;font-size:16px">{!! $it['t'] !!}</strong><span style="font-size:14px;line-height:22px">{!! $it['b'] !!}</span>
          @if($it['note'])<span style="font-size:13px;color:#51607a">{{ $it['note'] }}</span>@endif
          <div style="display:flex;gap:8px;flex-wrap:wrap">@foreach($it['btns'] as [$l, $u])<a class="btn" href="{{ $u }}">{{ $l }}</a>@endforeach</div></div>
      @endforeach
    </div>
    @endif
    <div style="background:#e3f6ec;border-radius:14px;padding:14px 16px;display:flex;flex-wrap:wrap;gap:20px;font-size:14px;color:#0f6b3c">
      <span>✓ লোড <strong class="n">{{ $c['loadedTotal'] }}</strong></span><span>✓ বিক্রি <strong class="n">{{ $c['soldTotal'] }}</strong></span><span>✓ ভরা ফেরত <strong class="n">{{ $c['returnedTotal'] }}</strong></span><span>✓ ডিজিটাল <strong class="n">{{ $U::tk($c['digitalTotal']) }}</strong></span><span>✓ বাকি অনুমোদিত <strong class="n">{{ $U::tk($c['credit']) }}</strong></span>
    </div>
  @else
    <h2 style="margin:0;font-size:20px">{{ $trip->status === 'closed' ? 'ট্রিপ বন্ধ ও লক করা হয়েছে' : (($c['stockOk'] && $c['cashOk'] && $trip->status === 'settled') ? 'সব মিলেছে — ম্যানেজার অনুমোদন দিয়ে বন্ধ করবেন' : 'এখনো পার্থক্য আছে বা আগের ধাপ বাকি') }}</h2>
    <span style="font-size:14px">ম্যানেজার <a href="{{ route('trips.closing', ['trip' => $trip->id]) }}">Daily closing</a> পেজে "Close trip" চাপলে ট্রিপ লক হবে। এরপর পরিবর্তন শুধু অনুমোদিত সংশোধনী এন্ট্রিতে।</span>
  @endif
  <div style="display:flex;gap:10px;justify-content:space-between;flex-wrap:wrap;border-top:1px solid #dbe3f0;padding-top:14px">
    @if($step > 1)<a class="btn" href="{{ $go($step - 1) }}">← ধাপ {{ $bn($step - 1) }}: {{ $T[$step - 2] }}</a>@else<span></span>@endif
    <div style="display:flex;gap:10px"><a class="btn" href="{{ $go($step) }}" title="প্রতিটা এন্ট্রি দেওয়ার সাথে সাথেই সেভ হয়ে যায়">ড্রাফট সেভ</a>
      @if($step < 7)<a class="btn pri" href="{{ $go($step + 1) }}">ধাপ {{ $bn($step + 1) }}: {{ $T[$step] }} →</a>@else<a class="btn pri" href="{{ route('trips.closing', ['trip' => $trip->id]) }}">Daily closing →</a>@endif</div>
  </div>
</section>
<span style="font-size:12px;color:#51607a">যেকোনো সংখ্যার পাশে "হিসাব দেখুন" চাপলে দেখাবে সংখ্যাটা কোন কোন এন্ট্রি থেকে এসেছে। এই উইজার্ডের সব সংখ্যা <a href="{{ route('trips.closing', ['trip' => $trip->id]) }}" style="color:#1d4ed8">Daily closing</a> পেজের সাথে একই।</span>
</div>
@endsection
