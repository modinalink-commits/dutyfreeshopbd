@extends('portal.layout', ['tab' => 'buy'])
@section('title', 'ইনভয়েস')
@php($U = \App\Support\Ui::class)
@php($s = collect($sale->company ?? \App\Models\Setting::snapshot()))
@php($t = $sale->trip)
@php($pend = max(0, (int) $sale->empties_expected - (int) $sale->empties_received))
@section('bar')@include('portal._back', ['title' => 'ইনভয়েস', 'href' => route('my.purchases')])@endsection
@section('content')
<style>.r{display:flex;font-size:var(--fs-14);padding:6px 0}.r span:first-child{flex-grow:1;color:#51607a}.r span:last-child{font-weight:600;font-variant-numeric:tabular-nums}</style>
<article class="card" style="display:flex;flex-direction:column;gap:4px">
  <div style="display:flex;align-items:center;padding-bottom:10px;border-bottom:1px dashed #7b8aa8;margin-bottom:6px;gap:8px">
    <div style="flex-grow:1;display:flex;flex-direction:column"><b style="font-size:var(--fs-16);color:#123a86">{{ $s['company_name'] ?? 'NK Corporation' }}</b><span class="n" style="font-size:var(--fs-11);color:#51607a">{{ trim(($s['brand'] ?? '').' LPG') }}{{ ! empty($s['phone']) ? ' · '.$s['phone'] : '' }}</span></div>
    @if($sale->stage === 'voided')<span class="pill" style="background:#fde9e7;color:#b3261e">বাতিল</span>
    @elseif($sale->due() <= 0)<span class="pill" style="background:#e3f6ec;color:#0f6b3c">পরিশোধিত</span>
    @else<span class="pill" style="background:#fff1d6;color:#8a4b00">{{ (float) $sale->paid > 0 ? 'আংশিক পরিশোধ' : 'বাকি' }}</span>@endif
  </div>
  <div class="r"><span>ইনভয়েস নং</span><span class="n">{{ $sale->invoice_no }}</span></div>
  <div class="r"><span>তারিখ</span><span class="n">{{ $sale->created_at->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}</span></div>
  @if($t)<div class="r"><span>সেলসম্যান · গাড়ি</span><span>{{ $t->salesman?->name ?? '—' }} · {{ $t->vehicle?->code ?? '—' }}</span></div>@endif
  <div class="r"><span>ডেলিভারি</span><span style="color:#0f6b3c">{{ $sale->stage === 'voided' ? '—' : 'সম্পন্ন' }}</span></div>
  @foreach($sale->items as $l)
  <div style="background:#f4f7fc;border-radius:12px;padding:10px 12px;margin:6px 0 0;display:flex;flex-direction:column;gap:2px">
    <div style="display:flex;font-size:var(--fs-15);font-weight:700"><span style="flex-grow:1">{{ $l->size_kg ? $U::bn(rtrim(rtrim(number_format((float) $l->size_kg, 1), '0'), '.')).' কেজি ' : '' }}{{ ['refill' => 'রিফিল', 'new_package' => 'নতুন সিলিন্ডার'][$l->category] ?? $l->description }} × {{ $l->qty }}</span><span class="n">{{ $U::tk($l->line_total) }}</span></div>
    <span class="n" style="font-size:var(--fs-12);color:#51607a">{{ $U::tk($l->unit_price) }} × {{ $l->qty }}</span>
  </div>
  @endforeach
  <div class="r" style="margin-top:6px"><span>মোট</span><span style="font-weight:700">{{ $U::tk($sale->total) }}</span></div>
  <div class="r"><span>পরিশোধ ({{ ['cash' => 'নগদ', 'bkash' => 'বিকাশ', 'nagad' => 'নগদ (Nagad)', 'bank' => 'ব্যাংক', 'due' => 'বাকি'][$sale->payment_method] ?? $sale->payment_method }})</span><span style="color:#0f6b3c">{{ $U::tk($sale->paid) }}</span></div>
  <div class="r"><span>এই বিলের বাকি</span><span style="color:{{ $sale->due() > 0 ? '#8a4b00' : '#14213d' }}">{{ $U::tk($sale->due()) }}</span></div>
  @if((int) $sale->empties_expected > 0)
  <div style="border-top:1px solid #dbe3f0;margin-top:6px;padding-top:6px;display:flex;flex-direction:column">
    <span style="font-size:var(--fs-13);font-weight:700;padding:4px 0">খালি সিলিন্ডার</span>
    <div class="r"><span>দেওয়ার কথা</span><span>{{ (int) $sale->empties_expected }}</span></div>
    <div class="r"><span>দিয়েছি</span><span>{{ (int) $sale->empties_received }}</span></div>
    <div class="r"><span>বাকি</span><span style="color:{{ $pend > 0 ? '#8a4b00' : '#14213d' }}">{{ $pend }}</span></div>
  </div>
  @endif
</article>
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
  <a class="btn" href="{{ route('my.invoice', ['sale' => $sale, 'print' => 'a4']) }}" target="_blank" style="min-height:52px;border-radius:14px;border:1px solid #123a86;background:#fff;color:#123a86;font-weight:700;display:flex;align-items:center;justify-content:center;text-decoration:none">PDF ডাউনলোড</a>
  <button type="button" id="shareBtn" style="min-height:52px;border-radius:14px;border:1px solid #123a86;background:#fff;color:#123a86;font:700 var(--fs-15) 'Hind Siliguri',sans-serif;cursor:pointer">শেয়ার</button>
</div>
<span style="font-size:var(--fs-12);color:#51607a;text-align:center;line-height:18px">হিসাবে ভুল মনে হলে {{ $s['phone'] ?? 'অফিসে' }}-এ জানান। অফিস যাচাই করে সংশোধন করবে।</span>
<script>
document.getElementById('shareBtn').addEventListener('click',function(){var d={title:'{{ $sale->invoice_no }}',text:'{{ $sale->invoice_no }} · {{ $U::tk($sale->total) }}',url:location.href};
if(navigator.share){navigator.share(d).catch(function(){});}else if(navigator.clipboard){navigator.clipboard.writeText(d.url).then(function(){alert('লিংক কপি হয়েছে');});}});
</script>
@endsection
