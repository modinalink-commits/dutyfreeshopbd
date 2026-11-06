@extends('layout')
@section('title', 'Company ledger')
@section('content')
@php($tk = fn ($n) => ($n < 0 ? '−' : '').'৳'.number_format(abs((float) $n), (fmod(abs((float) $n), 1) > 0.004) ? 2 : 0))
@php($lakh = fn ($n) => '৳'.number_format($n / 100000, 2).' Lakh')
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Company ledger — {{ $s['supplier_name'] ?? 'AYGas' }}</h1><p class="sub">{{ $s['supplier_branch'] ?? 'Zorarganj Mirsarai branch' }} · {{ $s['supplier_code'] ?? 'Cusc000419' }} · {{ $s['supplier_status'] ?? 'Exclusive' }}</p></div>
  <div role="group" aria-label="Month" style="display:flex;align-items:center;gap:6px">
    <a class="btn sm" href="{{ route('company.index', ['m' => $prev]) }}" aria-label="Previous month">‹</a>
    <b style="min-width:120px;text-align:center">{{ $start->format('F Y') }}</b>
    <a class="btn sm" href="{{ route('company.index', ['m' => $next]) }}" aria-label="Next month">›</a>
  </div>
  <a class="btn" href="{{ route('company.rates') }}">Rate Master</a>
  <a class="btn" href="#dep-form">＋ Deposit</a>
  <a class="btn pri" href="#challan-form">＋ Challan</a>
  @include('partials.export-menu', ['r' => 'company_ledger', 'from' => $start->toDateString(), 'to' => $start->copy()->endOfMonth()->toDateString()])
</header>
<section class="kpis">
  <div class="card kpi" style="background:#e6eeff"><span>Balance with company</span><b style="color:#1d4ed8">{{ $tk($balanceToday) }}</b><span>{{ $balanceToday >= 0 ? 'money held by AYGas today' : 'bills above deposits today' }}</span></div>
  <div class="card kpi"><span>Deposited this month</span><b>{{ $lakh($deposited) }}</b><span>{{ $depCount }} deposits</span></div>
  <div class="card kpi"><span>Lifted this month</span><b>{{ number_format($tons, 1) }} t</b><span>{{ $challans }} challans · {{ $lakh($billed) }} billed</span></div>
  <div class="card kpi" style="background:#efe7fb"><span>Incentive coming</span><b style="color:#5b2fa8">{{ $calc['incentiveReceived'] ? 'Received' : $lakh($calc['net']) }}</b><span>{{ \Carbon\Carbon::parse($calc['month']->month.'-01')->format('F') }} · arrives {{ \Carbon\Carbon::parse($calc['incentiveDate'])->format('d-m-Y') }}</span></div>
  <div class="card kpi" style="background:#efe7fb"><span>Commission coming</span><b style="color:#5b2fa8">{{ $calc['commissionReceived'] ? 'Received' : $lakh($calc['commission']) }}</b><span>{{ \Carbon\Carbon::parse($calc['month']->month.'-01')->format('M') }} · Tk {{ rtrim(rtrim(number_format($calc['month']->kg_commission, 2), '0'), '.') }}/kg · {{ \Carbon\Carbon::parse($calc['commissionDate'])->format('d-m-Y') }}</span></div>
</section>
@php($pct = $prevTons > 0 ? ($tons - $prevTons) / $prevTons * 100 : null)
@php($mN = $bnMonth[$start->format('F')] ?? $start->format('F'))
@php($pN = $bnMonth[\Carbon\Carbon::parse($prev.'-01')->format('F')] ?? '')
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:stretch">
  <details class="card pad" style="flex:2 1 420px;min-width:0">
    <summary style="display:flex;align-items:center;gap:10px;cursor:pointer;list-style:none"><span style="flex-grow:1;display:flex;flex-direction:column"><span style="font-size:var(--fs-12);color:var(--muted)">কোম্পানির কাছে জমা · {{ now()->timezone('Asia/Dhaka')->format('d-m-Y') }}</span><b style="font-size:var(--fs-22)">{{ \App\Support\Ui::bd($balanceToday) }}</b></span><span class="btn sm">হিসাব দেখুন ▾</span></summary>
    <div style="display:flex;flex-direction:column;margin-top:10px">
      @foreach($explain as $x)
        <a href="{{ route('company.index', ['m' => $x['m']]) }}" style="display:flex;font-size:var(--fs-13);padding:6px 0;border-bottom:1px solid #eef2f9;text-decoration:none;color:inherit"><span style="flex-grow:1;color:var(--muted)">{{ $x['t'] }} <span style="font-size:var(--fs-11)">({{ $x['n'] }})</span></span><span style="font-weight:600;font-variant-numeric:tabular-nums">{{ $x['a'] >= 0 ? '+' : '−' }} {{ \App\Support\Ui::bd(abs($x['a']), true) }}</span></a>
      @endforeach
      <div style="display:flex;font-size:var(--fs-14);padding:8px 0;font-weight:700"><span style="flex-grow:1">মোট</span><span>{{ \App\Support\Ui::bd($balanceToday, true) }}</span></div>
      <span class="sub" style="margin:0">বিল = সিলিন্ডার × (DO − Transport) · প্রতিটা লাইনে ক্লিক করলে সেই মাসের এন্ট্রিগুলো খোলে।</span>
    </div>
  </details>
  <section class="card pad" style="flex:1 1 280px;min-width:0;display:flex;flex-direction:column;gap:8px">
    <div style="display:flex;align-items:baseline;gap:8px"><b style="font-size:var(--fs-22)">{{ number_format($tons, 1) }} t</b>@if($pct !== null)<span style="font-weight:700;color:{{ $pct >= 0 ? '#0f6b3c' : '#b42318' }}">{{ $pct >= 0 ? '▲' : '▼' }} {{ number_format(abs($pct), 0) }}%</span>@endif</div>
    <span style="font-size:var(--fs-13);line-height:20px">{{ $mN }}ে কোম্পানি থেকে {{ number_format($tons, 1) }} টন গ্যাস তোলা হয়েছে@if($pct !== null), {{ $pN }}ের ({{ number_format($prevTons, 1) }} টন) চেয়ে <b>{{ number_format(abs($pct), 0) }}% {{ $pct >= 0 ? 'বেশি' : 'কম' }}</b>@endif।</span>
    @php($mx = max($tons, $prevTons, 0.1))
    <div style="display:flex;align-items:flex-end;gap:14px;height:70px">
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;flex:1"><span style="font-size:var(--fs-11)">{{ number_format($prevTons, 1) }}</span><div style="width:100%;max-width:60px;height:{{ max(2, round($prevTons / $mx * 44)) }}px;background:#c9d2e3;border-radius:6px 6px 0 0"></div><span style="font-size:var(--fs-11);color:var(--muted)">{{ $pN }}</span></div>
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;flex:1"><span style="font-size:var(--fs-11);font-weight:700">{{ number_format($tons, 1) }}</span><div style="width:100%;max-width:60px;height:{{ max(2, round($tons / $mx * 44)) }}px;background:#123a86;border-radius:6px 6px 0 0"></div><span style="font-size:var(--fs-11);color:var(--muted)">{{ $mN }}</span></div>
    </div>
  </section>
</div>
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section class="card" style="flex:999 1 620px;min-width:0">
    <div class="pad" style="display:flex;align-items:center;gap:8px"><h2 style="margin:0;font-size:var(--fs-15);flex-grow:1">Ledger — {{ $start->format('F Y') }}</h2>@if($hiddenRev)<a class="btn sm" href="{{ route('company.index', ['m' => $ym] + ($showRev ? [] : ['rev' => 1])) }}">{{ $showRev ? 'Hide reversed entries' : 'Show reversed entries ('.$hiddenRev.')' }}</a>@endif<button class="btn sm" type="button" onclick="window.print()">Download PDF</button></div>
    <div class="dt-wrap"><table class="dt">
      <thead><tr><th>Date</th><th>Detail</th><th>12 / 35 / 45 kg</th><th>DO</th><th>Transport</th><th>Bill</th><th>Deposit</th><th>Balance</th><th class="no-sort"></th></tr></thead>
      <tbody>
      @foreach($rows as $r)
        <tr style="{{ $r['kind'] === 'p' ? '--rb:#efe7fb' : ($r['kind'] === 'pf' ? '--rb:#f7f3fd;opacity:.75' : ($r['kind'] === 'o' ? '--rb:#e6eeff' : '')) }}" class="{{ $r['kind'] ? 'tint' : '' }}">
          <td class="c nw">{{ $r['d'] }}</td>
          <td style="color:{{ in_array($r['kind'], ['p', 'pf']) ? '#5b2fa8' : ($r['kind'] === 'd' ? '#0b6670' : 'inherit') }};font-weight:{{ $r['kind'] === 'o' ? 700 : 500 }}">{{ $r['t'] }}@if(! empty($r['rev'])) <span class="pill st-cancelled">reversed</span>@endif</td>
          <td class="c">{{ $r['q'] }}</td>
          <td class="r">{{ $r['do'] !== null ? $tk($r['do']) : '' }}</td><td class="r">{{ $r['tr'] !== null ? $tk($r['tr']) : '' }}</td>
          <td class="r">{{ $r['bill'] !== null ? $tk($r['bill']) : '' }}</td><td class="r">{{ $r['dep'] !== null ? $tk($r['dep']) : '' }}</td>
          <td class="r"><b>{{ $tk($r['bal']) }}</b></td>
          <td class="c">@if($r['ids'] && empty($r['rev']))
            <details><summary style="cursor:pointer;font-size:var(--fs-12)">Reverse</summary><form method="post" action="{{ route('company.reverse') }}" style="display:flex;gap:4px;margin-top:4px">@csrf @foreach($r['ids'] as $id)<input type="hidden" name="ids[]" value="{{ $id }}">@endforeach<input type="text" name="reason" placeholder="Reason" required style="min-height:30px;width:120px"><button class="btn sm" type="submit">OK</button></form></details>
          @endif</td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
    <p class="sub" style="padding:0 16px 16px;margin:0">Bill = DO − Transport. Purple rows are incentive and commission the company adds back to our balance; faded rows are still to come.</p>
  </section>
  <aside class="card pad" style="flex:1 1 300px;min-width:280px;display:flex;flex-direction:column;gap:12px">
    <h2 style="margin:0;font-size:var(--fs-15)">{{ \Carbon\Carbon::parse($calc['month']->month.'-01')->format('F') }} incentive — by our rules</h2>
    <div style="display:flex;flex-direction:column;gap:6px;font-size:var(--fs-13);font-variant-numeric:tabular-nums">
      @forelse($calc['parts'] as $p)
        <div style="display:flex;gap:8px"><span style="flex-grow:1">{{ $p['label'] }} <span class="sub">({{ $p['calc'] }})</span></span><span>{{ $tk($p['amount']) }}</span></div>
      @empty
        <span class="sub">No incentive rule is switched on for this month. Set it in Rate Master → Month rules.</span>
      @endforelse
      <div style="display:flex;color:#b3261e"><span style="flex-grow:1">(−) AIT {{ rtrim(rtrim(number_format($calc['month']->ait_pct, 2), '0'), '.') }}%</span><span>−{{ $tk($calc['ait']) }}</span></div>
      <div style="display:flex;font-weight:700;font-size:var(--fs-15);border-top:1px solid var(--line);padding-top:8px"><span style="flex-grow:1">Net incentive</span><span>{{ $tk($calc['net']) }}</span></div>
      <div style="display:flex" class="sub"><span style="flex-grow:1">Commission · {{ number_format($calc['kg']) }} kg</span><span>{{ $tk($calc['commission']) }}</span></div>
    </div>
    @foreach(['incentive' => 'Actual incentive received', 'commission' => 'Actual commission received'] as $k => $l)
      @if($calc[$k.'Received'])
        <div class="flash" style="font-size:var(--fs-12)">{{ ucfirst($k) }} recorded: {{ $tk($calc[$k.'Received']->amount) }} on {{ $calc[$k.'Received']->entry_date->format('d-m-Y') }}</div>
      @else
        <form method="post" action="{{ route('company.receive') }}" style="display:flex;flex-direction:column;gap:6px">@csrf
          <input type="hidden" name="for_month" value="{{ $calc['month']->month }}"><input type="hidden" name="kind" value="{{ $k }}">
          <label class="f">{{ $l }}<input type="number" step="0.01" name="actual" placeholder="Leave empty to use the calculated amount"></label>
          <div style="display:flex;gap:6px"><input type="date" name="entry_date" value="{{ $calc[$k.'Date'] }}" aria-label="Date received"><button class="btn sm pri" type="submit">Record</button></div>
        </form>
      @endif
    @endforeach
  </aside>
</div>
<div class="grid2" style="align-items:start">
  <form id="dep-form" class="card pad" method="post" action="{{ route('company.deposit') }}" style="display:flex;flex-direction:column;gap:10px">@csrf
    <h2 style="margin:0;font-size:var(--fs-15)">＋ Deposit</h2>
    <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
      <label class="f">Date<input type="date" name="entry_date" value="{{ now()->timezone('Asia/Dhaka')->toDateString() }}" required></label>
      <label class="f">Amount (৳)<input type="number" step="0.01" name="amount" min="1" required></label>
      <label class="f">Type<select name="deposit_kind">@foreach(\App\Models\CompanyEntry::DEPOSIT_KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
      <label class="f">From<input type="text" name="source" value="NK Corporation · BRAC Bank"></label>
    </div>
    <label class="f">Note<input type="text" name="note"></label>
    <button class="btn pri" type="submit">Save deposit</button>
  </form>
  <form id="challan-form" class="card pad" method="post" action="{{ route('company.challan') }}" style="display:flex;flex-direction:column;gap:10px">@csrf
    <h2 style="margin:0;font-size:var(--fs-15)">＋ Challan</h2>
    @if($dup = session('dup'))
      <div role="alert" style="border:1px solid #f0c36d;background:#fff8e6;border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:8px">
        <b style="color:#8a4b00">এই চালান আগেই লেখা আছে</b>
        <span style="font-size:var(--fs-13);line-height:20px">চালান <b>{{ $dup['no'] }}</b>, {{ $dup['q'] }} — {{ $dup['date'] }} তারিখে সেভ করা। একই এন্ট্রি দুইবার দিলে বিল দুইবার কাটবে।</span>
        <div style="display:flex;flex-wrap:wrap;gap:8px"><a class="btn" href="{{ route('company.index', ['m' => $dup['m']]) }}">আগেরটা দেখুন</a>
        <button class="btn" type="submit" name="confirm_dup" value="1">ভিন্ন চালান, তবুও সেভ</button></div>
      </div>
    @endif
    <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
      <label class="f">Date<input type="date" name="entry_date" value="{{ old('entry_date', now()->timezone('Asia/Dhaka')->toDateString()) }}" required></label>
      <label class="f"><span>Challan number @include('partials.help', ['t' => 'চালান নম্বর কী?', 'b' => 'কোম্পানির চালানের কাগজের উপরে ছাপা নম্বর।', 'e' => 'উদাহরণ: 144794', 'f' => 'একই নম্বর আগেই থাকলে সিস্টেম সতর্ক করবে।'])</span><input type="text" name="challan_no" value="{{ old('challan_no') }}" required></label>
    </div>
    <div class="grid2" style="grid-template-columns:repeat(3,minmax(0,1fr))">@foreach(\App\Models\Brand::SIZES as $sz)<label class="f">{{ $sz }} kg full<input type="number" min="0" name="qty[{{ $sz }}]" value="{{ old('qty.'.$sz, 0) }}"></label>@endforeach</div>
    <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="to_stock" value="1" checked style="width:auto;min-height:0"> Also add to godown stock</label>
    <div class="grid2" style="grid-template-columns:repeat(3,minmax(0,1fr))">@foreach(\App\Models\Brand::SIZES as $sz)<label class="f">{{ $sz }} kg empties handed over<input type="number" min="0" name="empties[{{ $sz }}]" value="{{ old('empties.'.$sz, 0) }}"></label>@endforeach</div>
    <span class="sub">Bill is worked out from the Rate Master row for the challan date.</span>
    <button class="btn pri" type="submit">Save challan</button>
  </form>
</div>
@unless(\App\Models\CompanyEntry::query()->exists())
<form class="card pad" method="post" action="{{ route('company.import') }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end">@csrf
  <label class="f" style="flex-grow:1">Import the existing ledger (file from Claude: nk-company-ledger.json)<input type="file" name="file" accept=".json,application/json" required></label>
  <button class="btn pri" type="submit">Import</button>
</form>
@endunless
<style>.dt tr.tint td{background:var(--rb)}</style>
@endsection
