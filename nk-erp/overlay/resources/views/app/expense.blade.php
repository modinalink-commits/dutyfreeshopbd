@extends('app.layout', ['tab' => 'home'])
@section('title', 'খরচের দাবি')
@php($U = \App\Support\Ui::class)
@section('bar')@include('app._back', ['href' => route('app.home'), 'title' => 'খরচের দাবি'])@endsection
@section('content')
<form method="post" action="{{ route('app.expense.store') }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:12px">@csrf
  <input type="hidden" name="t" id="t" value="fuel">
  <span class="lb">কী খরচ</span>
  <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">@foreach(['fuel' => 'জ্বালানি', 'toll' => 'টোল', 'food' => 'নাস্তা', 'mob' => 'মোবাইল', 'fix' => 'মেরামত', 'other' => 'অন্যান্য'] as $k => $l)<button type="button" class="chip tb {{ $loop->first ? 'on' : '' }}" data-t="{{ $k }}" style="min-height:48px">{{ $l }}</button>@endforeach</div>
  <label class="lb" style="display:flex;flex-direction:column;gap:6px">কত টাকা<input class="inp" name="amount" inputmode="numeric" required></label>
  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;font-size:14px">
    <div style="background:#f4f7fc;border-radius:12px;padding:10px"><span class="lb">তারিখ</span><br><b class="n">{{ now()->timezone('Asia/Dhaka')->format('d-m-Y') }}</b></div>
    <div style="background:#f4f7fc;border-radius:12px;padding:10px"><span class="lb">গাড়ি / রুট</span><br><b>{{ $trip ? $trip->vehicle->code.' · '.\Illuminate\Support\Str::before($trip->route?->name ?? '—', ' ') : '—' }}</b></div>
  </div>
  <span class="lb">টাকা কোথা থেকে দিলেন</span>
  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px"><label class="chip" style="min-height:44px"><input type="radio" name="from" value="trip" @checked($trip) @disabled(! $trip) style="width:auto;min-height:0"> ট্রিপের আদায় থেকে</label><label class="chip" style="min-height:44px"><input type="radio" name="from" value="own" @checked(! $trip) style="width:auto;min-height:0"> নিজের টাকা (বেতনের সাথে ফেরত)</label></div>
  <label class="lb" style="display:flex;flex-direction:column;gap:6px">বিবরণ<input class="txt" name="description" maxlength="255"></label>
  <label class="chip" style="min-height:56px;cursor:pointer;border-style:dashed"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h3l2-2h6l2 2h3v12H4zM12 17a4 4 0 100-8 4 4 0 000 8z"/></svg><span id="rname">রসিদের ছবি তুলুন</span><input type="file" name="receipt" accept="image/*,application/pdf" capture="environment" style="display:none" onchange="document.getElementById('rname').textContent=this.files[0]?this.files[0].name:'রসিদের ছবি তুলুন'"></label>
  <button class="big" type="submit" style="background:#123a86">অনুমোদনের জন্য পাঠান</button>
</form>
<div class="card" style="display:flex;flex-direction:column;gap:6px"><b style="font-size:14px">আমার দাবি</b>
  @forelse($mine as $m)
    @php($s = ['draft' => 'ড্রাফট', 'submitted' => 'অপেক্ষায়', 'posted' => 'অনুমোদিত', 'paid' => 'পরিশোধিত', 'rejected' => 'বাতিল'][$m->status])
    <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-top:1px solid #eef2f9"><div style="flex-grow:1;display:flex;flex-direction:column"><span style="font-size:14px">{{ $m->extra['type'] ?? $m->description }}</span><span class="n" style="font-size:11px;color:#51607a">{{ $m->expense_date->format('d-m') }} · {{ $m->claim_no }}</span></div><b class="n">{{ $U::tk($m->amount) }}</b>
      <span class="pill" style="{{ ['অপেক্ষায়' => 'background:#fff1d6;color:#8a4b00', 'অনুমোদিত' => 'background:#e6eeff;color:#1d4ed8', 'পরিশোধিত' => 'background:#e3f6ec;color:#0f6b3c', 'বাতিল' => 'background:#fde9e7;color:#b3261e'][$s] ?? '' }}">{{ $s }}</span></div>
  @empty<span style="font-size:13px;color:#51607a">এখনো কোনো দাবি নেই।</span>@endforelse
</div>
@endsection
@push('js')
<script>document.querySelectorAll('.tb').forEach(b=>b.onclick=()=>{document.querySelectorAll('.tb').forEach(x=>x.classList.toggle('on',x===b));document.getElementById('t').value=b.dataset.t;});</script>
@endpush
