@extends('layout')
@section('title', 'Rate Master')
@section('content')
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1"><h1>Rate Master</h1><p class="sub">Refill DO, package DO and transport per size from a date, plus the per-cylinder incentive rates. A row stays in force until a newer row starts.</p></div>
  <a class="btn" href="{{ route('company.index') }}">← Company ledger</a>
</header>
<div class="dt-wrap"><table class="dt">
  <thead><tr><th>From</th><th>Size</th><th>Refill DO</th><th>Package DO</th><th>Transport</th><th>Refill bill / cyl.</th><th>Package bill / cyl.</th>@foreach(\App\Models\CompanyRate::PARTS as $p => $l)<th>{{ ucfirst($p) }}</th>@endforeach</tr></thead>
  <tbody>
  @forelse($rates as $r)
    <tr><td class="c">{{ $r->from_date->format('d-m-Y') }}</td><td class="c">{{ $r->size_kg }} kg</td><td class="r">৳{{ number_format($r->do_rate, 2) }}</td><td class="r">{{ (float) $r->pkg_rate ? '৳'.number_format($r->pkg_rate, 2) : '—' }}</td><td class="r">৳{{ number_format($r->transport, 2) }}</td><td class="r"><b>৳{{ number_format($r->do_rate - $r->transport, 2) }}</b></td><td class="r">@if((float) $r->pkg_rate)<b>৳{{ number_format($r->pkg_rate - $r->transport, 2) }}</b>@else — @endif</td>
      @foreach(array_keys(\App\Models\CompanyRate::PARTS) as $p)<td class="r">{{ (float) $r->{$p} ? number_format($r->{$p}, 2) : '—' }}</td>@endforeach</tr>
  @empty
    <tr><td colspan="13" class="empty">No rates yet.</td></tr>
  @endforelse
  </tbody>
</table></div>
<div class="grid2" style="align-items:start">
  <form class="card pad" method="post" action="{{ route('company.rates.save') }}" style="display:flex;flex-direction:column;gap:10px">@csrf
    <h2 style="margin:0;font-size:var(--fs-15)">Add or change a rate row</h2>
    <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
      <label class="f">Size<select name="size_kg">@foreach(\App\Models\Brand::SIZES as $s)<option value="{{ $s }}">{{ $s }} kg</option>@endforeach</select></label>
      <label class="f">From date<input type="date" name="from_date" required></label>
      <label class="f"><span>DO (৳) @include('partials.help', ['t' => 'DO রেট কী?', 'b' => 'কোম্পানির চালানে লেখা প্রতি সিলিন্ডারের দাম, ছাড় বাদ দেওয়ার আগে।', 'e' => 'উদাহরণ: ১২ কেজি, সেপ্টেম্বর ২০২৬ → 1575', 'f' => 'ভুল হলে: চালানের কাগজ দেখে আবার লিখুন। আগের বিল নিজে থেকে ঠিক হয়ে যাবে।'])</span><input type="number" step="0.01" name="do_rate" required></label>
      <label class="f"><span>Package DO (৳) @include('partials.help', ['t' => 'প্যাকেজ রেট কী?', 'b' => 'নতুন প্যাকেজ সিলিন্ডারের (সিলিন্ডার + গ্যাস) জন্য কোম্পানির চালানে লেখা প্রতি সিলিন্ডারের দাম।', 'e' => 'রিফিলের রেট আলাদা ঘরে থাকে।', 'f' => 'খালি রাখলে ওই সাইজের প্যাকেজ চালান সেভ হবে না।'])</span><input type="number" step="0.01" name="pkg_rate" value="0"></label>
      <label class="f">Transport (৳)<input type="number" step="0.01" name="transport" value="0"></label>
      @foreach(\App\Models\CompanyRate::PARTS as $p => $l)<label class="f">{{ $l }} (৳ per cylinder)<input type="number" step="0.01" name="{{ $p }}" value="0"></label>@endforeach
    </div>
    <span class="sub">Same size and date replaces that row. Every change is in the Activity log.</span>
    <button class="btn pri" type="submit">Save rate</button>
  </form>
  <form class="card pad" method="post" action="{{ route('company.month.save') }}" style="display:flex;flex-direction:column;gap:10px">@csrf
    <h2 style="margin:0;font-size:var(--fs-15)">Month rules (which incentives were earned)</h2>
    @php($cur = $months->first())
    <div class="grid2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
      <label class="f">Month<input type="month" name="month" value="{{ $cur?->month ?? now()->format('Y-m') }}" required></label>
      <label class="f">Deadline day<input type="number" name="deadline_day" min="1" max="31" value="{{ $cur?->deadline_day ?? 16 }}"></label>
      <label class="f">Commission ৳ per kg<input type="number" step="0.01" name="kg_commission" value="{{ $cur?->kg_commission ?? 0 }}"></label>
      <label class="f">AIT %<input type="number" step="0.01" name="ait_pct" value="{{ $cur?->ait_pct ?? 10 }}"></label>
      <label class="f">Regular target (tons)<input type="number" step="0.01" name="tons_target" value="{{ $cur?->tons_target ?? 0 }}"></label>
      <label class="f">Exclusive target (tons)<input type="number" step="0.01" name="excl_target" value="{{ $cur?->excl_target ?? 0 }}"></label>
    </div>
    <div class="dt-wrap"><table class="dt"><thead><tr><th>Incentive</th>@foreach(\App\Models\Brand::SIZES as $s)<th>{{ $s }} kg</th>@endforeach</tr></thead><tbody>
      @foreach(\App\Models\CompanyRate::PARTS as $p => $l)@continue($p === 'dd')<tr><td>{{ $l }}</td>@foreach(\App\Models\Brand::SIZES as $s)<td class="c"><input type="checkbox" name="flags[{{ $p }}_{{ $s }}]" value="1" style="width:auto;min-height:0" @checked(($cur?->flags ?? [])[$p.'_'.$s] ?? false) aria-label="{{ $l }} {{ $s }} kg earned"></td>@endforeach</tr>@endforeach
    </tbody></table></div>
    <span class="sub">DD incentive is always counted when its rate is above zero.</span>
    <button class="btn pri" type="submit">Save month rules</button>
    @if($months->count())<span class="sub">Saved months: @foreach($months as $m){{ $m->month }} (Tk {{ rtrim(rtrim(number_format($m->kg_commission, 2), '0'), '.') }}/kg) @endforeach</span>@endif
  </form>
</div>
@endsection
