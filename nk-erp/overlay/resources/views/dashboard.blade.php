@extends('layout')
@section('title', 'Dashboard')
@section('content')
@php($sa = auth()->user()->isSuperAdmin())
@php($tone = ['pos' => ['#e3f6ec', '#0f6b3c'], 'neg' => ['#fde9e7', '#b3261e'], 'warn' => ['#fff1d6', '#8a4b00'], 'info' => ['#def5f7', '#0b6670'], 'vio' => ['#efe7fb', '#5b2fa8'], 'blue' => ['#e6eeff', '#1d4ed8']])
@php($lakh = fn ($n) => abs($n) >= 100000 ? '৳'.number_format($n / 100000, 2).' Lakh' : '৳'.number_format($n))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Dashboard</h1><p class="sub">NK Corporation · Zorarganj, Mirsarai · {{ $now->format('l, d-m-Y') }}</p></div>
  <form method="get" action="{{ route('customers.index') }}" style="display:flex"><input type="search" name="q" placeholder="Search customer or phone" aria-label="Search customer" style="border-radius:999px;min-width:220px"></form>
  <a class="btn" href="{{ route('pos.index') }}">＋ New retail sale</a>
  <button class="btn pri" type="button" onclick="window.print()">Download PDF</button>
</header>
@php($k = [
  ["Today's sales", '৳'.number_format($todaySales), $todayCyl.' cylinders · '.number_format($todayKg / 1000, 1).' t', 'info'],
  ['Sold this month', number_format($monthKg / 1000, 1).' t', $now->day.' days · '.$monthCyl.' cylinders', 'blue'],
])
@if($sa)
  @php($k[] = ['Gross profit (month)', $lakh($gross), 'sales less company bill', 'pos'])
  @php($k[] = ['Balance with company', '৳'.number_format($balance), 'deposit held by AYGas', 'vio'])
@endif
@php($k[] = ['Customer dues', $lakh($dues), $dueCount.' customers', 'warn'])
@php($k[] = ['Collected today', '৳'.number_format($collected), 'cash + bKash + Nagad today', 'neg'])
<section class="kpis" aria-label="Today at a glance">
  @foreach($k as [$l, $v, $f, $t])
  <div class="card kpi" style="background:{{ $tone[$t][0] }}"><span>{{ $l }}</span><b style="color:{{ $tone[$t][1] }}">{{ $v }}</b><span>{{ $f }}</span></div>
  @endforeach
</section>
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:stretch">
  <section class="card pad" style="flex:999 1 520px;min-width:0;display:flex;flex-direction:column;gap:12px">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap"><h2 style="margin:0;font-size:15px;flex-grow:1">Sales by day — {{ $now->format('F') }}</h2>
      <span class="sub" style="margin:0;display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:3px;background:#1d4ed8"></span>Retail</span>
      <span class="sub" style="margin:0;display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:3px;background:#d9730d"></span>Wholesale</span></div>
    @php($max = max(1, collect($bars)->map(fn ($b) => $b['r'] + $b['w'])->max()))
    <div role="img" aria-label="Cylinders sold per day this month, retail and wholesale" style="display:flex;align-items:flex-end;gap:10px;height:200px;overflow-x:auto;padding-top:16px">
      @foreach($bars as $d => $b)
      <a href="{{ route('sales.index', ['day' => $now->copy()->day($d)->toDateString()]) }}" style="display:flex;flex-direction:column;align-items:center;gap:4px;text-decoration:none;color:var(--ink);min-width:34px">
        <span style="font-size:11px;font-weight:700">{{ $b['r'] + $b['w'] ?: '' }}</span>
        <div style="display:flex;align-items:flex-end;gap:3px;height:150px">
          <div style="width:14px;border-radius:5px 5px 0 0;background:#1d4ed8;height:{{ round($b['r'] / $max * 150) }}px"></div>
          <div style="width:14px;border-radius:5px 5px 0 0;background:#d9730d;height:{{ round($b['w'] / $max * 150) }}px"></div>
        </div>
        <span style="font-size:11px;color:var(--muted)">{{ str_pad($d, 2, '0', STR_PAD_LEFT) }}</span>
      </a>
      @endforeach
    </div>
    <span class="sub">Cylinders sold per day (all sizes). Tap a day to open its sales list.</span>
  </section>
  @if($sa)
  <section class="card pad" style="flex:1 1 300px;min-width:280px;display:flex;flex-direction:column;gap:14px">
    <h2 style="margin:0;font-size:15px">Company target — {{ $now->format('F') }}</h2>
    @foreach([['Regular target', $month?->tons_target], ['Exclusive target', $month?->excl_target]] as [$l, $t])
      <div style="display:flex;flex-direction:column;gap:6px">
        <div style="display:flex;font-size:13px"><span style="flex-grow:1">{{ $l }}</span><b>{{ number_format($lifted, 1) }} t of {{ $t ? number_format($t, 0).' t' : 'not set' }}</b></div>
        <div style="height:8px;border-radius:999px;background:#eef2f9"><div style="height:8px;border-radius:999px;background:#123a86;width:{{ $t ? min(100, round($lifted / $t * 100)) : 0 }}%"></div></div>
      </div>
    @endforeach
    @if($month && $month->tons_target > 0)
      @php($need = max(0, 0.6 * $month->tons_target - $lifted))
      @php($daysLeft = max(0, $month->deadline_day - 1 - $now->day))
      <div style="background:#eef2f9;border-radius:12px;padding:12px;font-size:13px;line-height:20px">
        @if($need <= 0)<strong>60% reached</strong> for the 01–15 additional incentive.@else<strong>60% by the {{ $month->deadline_day - 1 }}th:</strong> lift {{ number_format($need, 1) }} t more in {{ $daysLeft }} days{{ $daysLeft ? ' ('.number_format($need / $daysLeft, 1).' t a day)' : '' }} to earn the 01–15 additional incentive.@endif
      </div>
    @else
      <span class="sub">Set this month's targets in Rate Master → Month rules.</span>
    @endif
    <a href="{{ route('company.index') }}" style="font-weight:600;text-decoration:none">Open company ledger →</a>
  </section>
  @endif
</div>
<section class="card" style="padding:20px;display:flex;flex-wrap:wrap;gap:20px;align-items:center">
  <div style="position:relative;flex:1 1 360px;min-width:0;aspect-ratio:900 / 400;border-radius:12px;overflow:hidden;background:#eef2e8">
    <svg viewBox="0 0 900 400" preserveAspectRatio="none" role="img" aria-label="Schematic map of the distribution zones around Jorarganj, Mirsarai" style="position:absolute;inset:0;width:100%;height:100%">
      <polygon points="0,0 130,0 110,140 140,260 120,400 0,400" fill="#d6e6f2"></polygon>
      <polygon points="220,15 470,15 480,80 400,95 250,85" fill="#7aa7e0" fill-opacity=".35" stroke="#fff" stroke-width="2"></polygon>
      <polygon points="480,15 700,22 690,108 560,122 480,80" fill="#e0a96d" fill-opacity=".35" stroke="#fff" stroke-width="2"></polygon>
      <polygon points="700,22 860,43 850,172 720,178 690,108" fill="#8fc79a" fill-opacity=".35" stroke="#fff" stroke-width="2"></polygon>
      <polygon points="250,85 400,95 480,80 560,122 540,178 420,193 270,172" fill="#c7a4e6" fill-opacity=".35" stroke="#fff" stroke-width="2"></polygon>
      <polygon points="540,178 560,122 690,108 720,178 700,243 560,257" fill="#e7c45a" fill-opacity=".35" stroke="#fff" stroke-width="2"></polygon>
      <polygon points="170,178 270,172 420,193 540,178 560,257 450,278 200,272" fill="#6fc2c9" fill-opacity=".45" stroke="#123a86" stroke-width="3"></polygon>
      <polygon points="200,272 450,278 560,257 700,243 720,372 230,386" fill="#e59a9a" fill-opacity=".35" stroke="#fff" stroke-width="2"></polygon>
      <polyline points="330,0 390,86 430,186 470,286 520,400" fill="none" stroke="#c46f00" stroke-width="3.5"></polyline>
      <rect x="420" y="115" width="20" height="20" rx="5" fill="#14213d"></rect>
    </svg>
  </div>
  <div style="flex:1 1 260px;display:flex;flex-direction:column;gap:8px">
    <h2 style="margin:0;font-size:15px;font-weight:700">Distribution network today</h2>
    @if($net && $net['zones'])
      <span style="font-size:12px;color:#51607a">{{ $net['zones'] }} zones · {{ number_format($net['shops']) }} shops on routes · {{ number_format($net['visited']) }} visited · {{ number_format($net['unvisited']) }} not visited</span>
      @if($net['top'])
        <span style="font-size:12px;color:#51607a">Top zone: {{ $net['top']['name'] }}{{ ', '.\App\Support\Ui::bd($net['top']['amt']) }} · {{ number_format($net['top']['cyl']) }} cylinders</span>
      @else
        <span style="font-size:12px;color:#51607a">{{ $net['trips'] ? 'No route sales posted yet today.' : 'No trips dispatched today.' }}</span>
      @endif
    @else
      <span style="font-size:12px;color:#51607a">Add routes in Vehicles &amp; routes to see zones, shops and visits here.</span>
    @endif
    @if(in_array(auth()->user()->role, ['super_admin', 'admin', 'manager']))<a href="{{ route('map.dist') }}" style="font-size:12px;font-weight:600;text-decoration:none">Open distribution map →</a>@endif
  </div>
</section>
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section class="card" style="flex:999 1 560px;min-width:0">
    <div class="pad" style="display:flex;align-items:center"><h2 style="margin:0;font-size:15px;flex-grow:1">Latest sales</h2><a href="{{ route('sales.index') }}" style="font-weight:600;text-decoration:none">All sales →</a></div>
    <div class="dt-wrap"><table class="dt">
      <thead><tr><th>Memo</th><th>Time</th><th>Customer</th><th>Type</th><th>Items</th><th>Amount</th><th>Payment</th></tr></thead>
      <tbody>
      @forelse($latest as $s)
        @php($pay = $s->stage === 'voided' ? 'Voided' : ($s->due() > 0 ? 'Due' : ($s->payment_method === 'cash' ? 'Paid' : (\App\Models\Sale::METHODS[$s->payment_method] ?? 'Paid'))))
        <tr><td class="c"><a href="{{ route('sales.show', $s) }}">{{ $s->invoice_no }}</a></td><td class="c">{{ $s->created_at->timezone('Asia/Dhaka')->format('H:i') }}</td><td>{{ $s->buyerName() }}</td>
          <td class="c"><span class="pill" style="background:{{ $s->type === 'retail' ? '#e6eeff;color:#1d4ed8' : '#fff1d6;color:#8a4b00' }}">{{ ucfirst($s->type) }}</span></td>
          <td>{{ $s->items->map(fn ($i) => ($i->size_kg ? $i->size_kg.' kg' : $i->description).' × '.$i->qty)->implode(', ') }}</td>
          <td class="r">৳{{ number_format($s->total) }}</td>
          <td class="c"><span class="pill" style="background:{{ $pay === 'Paid' ? '#e3f6ec;color:#0f6b3c' : ($pay === 'Due' || $pay === 'Voided' ? '#fde9e7;color:#b3261e' : '#efe7fb;color:#5b2fa8') }}">{{ $pay }}</span></td></tr>
      @empty
        <tr><td colspan="7" class="empty">No sales yet.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
  <section class="card pad" style="flex:1 1 300px;min-width:280px;display:flex;flex-direction:column;gap:10px">
    <h2 style="margin:0;font-size:15px">Needs attention</h2>
    @forelse($alerts as $a)
      <div style="background:{{ $tone[$a['tone']][0] }};border-radius:12px;padding:12px;font-size:13px;line-height:20px"><strong style="color:{{ $tone[$a['tone']][1] }}">{{ $a['t'] }}</strong> — {{ $a['d'] }} @isset($a['link'])<a href="{{ $a['link'] }}">{{ $a['l'] }}</a>@endisset</div>
    @empty
      <span class="sub">Nothing needs attention right now.</span>
    @endforelse
  </section>
</div>
@endsection
