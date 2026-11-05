@extends('layout')
@section('title', 'Invoices & SMS')
@section('content')
@php($U = \App\Support\Ui::class)
@php($sa = auth()->user()->isSuperAdmin())
@php($st = \App\Support\Sms::class)
<style>.fld{display:flex;flex-direction:column;gap:4px;font-size:var(--fs-11);font-weight:600;color:#51607a}.sec{font-size:var(--fs-10);font-weight:700;letter-spacing:.08em;color:#51607a}
.tg{display:flex;align-items:center;gap:10px;font-size:var(--fs-13);padding:6px 0;border-top:1px solid #dbe3f0}.sw{position:relative;width:34px;height:20px;border-radius:999px;flex-shrink:0}.sw i{position:absolute;top:2px;width:16px;height:16px;border-radius:999px;background:#fff;transition:left .2s}
.sw input{position:absolute;inset:0;opacity:0;cursor:pointer;margin:0;min-height:0}</style>
<form method="post" action="{{ route('sms.gateway') }}" style="display:flex;flex-direction:column;gap:16px">@csrf
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Invoices &amp; SMS</h1><span class="sub">Today · POS receipts, A4 invoices and customer SMS from all {{ \App\Models\Vehicle::count() }} vehicles</span></div>
  <button class="btn" type="submit" formaction="{{ route('sms.retry') }}" @disabled(! $failed)>Retry failed SMS ({{ $failed }})</button>
  @if($sa)<button class="btn pri" type="submit">Save gateway settings</button>@endif
</header>
@php($sent = $sms->where('status', 'sent')->count())
@php($rate = (float) \App\Models\Setting::get('sms_rate', '0'))
@php($kpis = [['Invoices today', $sales->count(), 'blue'], ['POS prints', $sales->sum('pos_prints'), 'info'], ['A4 PDFs', $sales->sum('a4_prints'), 'info'], ['SMS sent', $sms->whereNotIn('status', ['no_mobile', 'off'])->count(), 'blue'], ['Delivered to gateway', $sent, 'pos'], ['Failed', $sms->where('status', 'failed')->count(), 'neg'], ['SMS cost', $rate ? '৳'.number_format($sent * $rate, 2) : '[rate]', 'gray']])
<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px">
  @foreach($kpis as [$l, $v, $c])<div style="background:{{ $U::bg($c) }};border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:2px;border:1px solid #dbe3f0"><span style="font-size:var(--fs-11);font-weight:600;color:#51607a">{{ $l }}</span><span style="font-size:var(--fs-20);font-weight:700;color:{{ $U::ink($c) }}">{{ $v }}</span></div>@endforeach
</section>
@unless($configured)<div class="flash" style="background:#fff1d6;color:#8a4b00">SMS gateway is not set up yet. Messages are written (status "Gateway not set") and go out with "Retry" once the provider is added.</div>@endunless

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:16px;align-items:start">
  <fieldset @disabled(! $sa) class="card" style="padding:20px;display:flex;flex-direction:column;gap:12px;border:1px solid #dbe3f0;margin:0;min-width:0">
    <h2 style="margin:0;font-size:var(--fs-15)">SMS gateway</h2>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
      <label class="fld">SMS provider<input name="sms_provider" list="prov" value="{{ \App\Models\Setting::get('sms_provider') }}" placeholder="[Choose provider]"><datalist id="prov"><option>BulkSMSBD</option><option>SSL Wireless</option><option>Alpha SMS</option><option>Other (HTTP)</option></datalist></label>
      <label class="fld">Sender ID<input name="sms_sender" value="{{ \App\Models\Setting::get('sms_sender') }}" placeholder="As approved by provider"></label>
    </div>
    <label class="fld">API endpoint<input name="sms_endpoint" value="{{ \App\Models\Setting::get('sms_endpoint') }}" placeholder="Not provided — e.g. https://provider/api?key={api_key}&amp;to={to}&amp;from={sender}&amp;text={message}"></label>
    <label class="fld">API key / token<input type="password" name="sms_key" autocomplete="new-password" placeholder="{{ \App\Models\Setting::get('sms_key') ? 'Saved — leave empty to keep' : 'Not provided' }}"></label>
    <span style="font-size:var(--fs-11);color:#51607a">Kept on the server only, encrypted. The mobile app never receives the key. Use {api_key}, {sender}, {to} and {message} in the endpoint.</span>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
      <label class="fld">SMS language<select name="sms_lang"><option value="bn" @selected(\App\Models\Setting::get('sms_lang', 'bn') === 'bn')>Bangla</option><option value="en" @selected(\App\Models\Setting::get('sms_lang') === 'en')>English</option></select></label>
      <label class="fld">Cost per SMS (৳)<input name="sms_rate" type="number" step="0.01" min="0" value="{{ \App\Models\Setting::get('sms_rate') }}" placeholder="[rate]"></label>
      <div class="fld">SMS balance<div style="min-height:40px;display:flex;align-items:center;font-size:var(--fs-13);color:#51607a">Shows after the provider is connected</div></div>
    </div>
    <span class="sec" style="margin-top:4px">AUTO SMS</span>
    @foreach($st::TYPES as $k => [$l, $sched])
      @php($on = $st::on($k))
      <div class="tg"><span class="sw" style="background:{{ $on ? '#123a86' : '#7b8aa8' }}"><i style="left:{{ $on ? '16px' : '2px' }}"></i><input type="checkbox" name="auto[{{ $k }}]" value="1" @checked($on) aria-label="{{ $l }}" onchange="this.parentNode.style.background=this.checked?'#123a86':'#7b8aa8';this.previousElementSibling.style.left=this.checked?'16px':'2px'"></span><span style="flex-grow:1">{{ $l }}</span><span style="color:#51607a">{{ in_array($sched, ['on', 'off']) ? ($on ? 'on' : 'off') : $sched }}</span></div>
    @endforeach
  </fieldset>

  <fieldset @disabled(! $sa) class="card" style="padding:20px;display:flex;flex-direction:column;gap:12px;border:1px solid #dbe3f0;margin:0;min-width:0">
    <div style="display:flex;align-items:center"><h2 style="margin:0;font-size:var(--fs-15);flex-grow:1">Templates</h2><span style="font-size:var(--fs-12);color:#51607a">বাংলা · English</span></div>
    <div style="display:flex;flex-wrap:wrap;gap:6px">@foreach($st::TYPES as $k => [$l])<a href="{{ route('sms.index', ['t' => $k]) }}" class="pill" style="text-decoration:none;{{ $k === $kind ? 'background:#123a86;color:#fff' : '' }}">{{ $l }}</a>@endforeach</div>
    <label class="fld">{{ $st::TYPES[$kind][0] }} · বাংলা<textarea name="tpl[{{ $kind }}|bn]" rows="4" style="font-family:'Hind Siliguri',sans-serif;line-height:20px">{{ $st::template($kind, 'bn') }}</textarea></label>
    <label class="fld">{{ $st::TYPES[$kind][0] }} · English<textarea name="tpl[{{ $kind }}|en]" rows="3">{{ $st::template($kind, 'en') }}</textarea></label>
    <div style="background:#f4f7fc;border-radius:10px;padding:10px 12px;font-size:var(--fs-12);line-height:18px;font-family:'Hind Siliguri',sans-serif"><b>Preview:</b> {{ $preview }}</div>
    <span style="font-size:var(--fs-11);color:#51607a">Fields: {company}, {phone}, {invoice_no}, {total}, {paid}, {due}, {paid_amount}, {empties}, {message}. Other templates are edited the same way — pick one above.</span>
  </fieldset>
</div>
</form>

@if($sa)
<form class="card pad" method="post" action="{{ route('sms.notice') }}" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end">@csrf
  <label class="fld" style="flex:3 1 300px">Special notice<input name="message" maxlength="300" required placeholder="e.g. Office closed on Friday"></label>
  <label class="fld" style="flex:1 1 160px">Send to<select name="to"><option value="due">Customers with due</option><option value="all">All customers with mobile</option></select></label>
  <button class="btn" type="submit" onclick="return confirm('Send this notice by SMS?')">Send notice</button>
</form>
@endif

<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px;display:flex;align-items:center;gap:10px"><h2 style="margin:0;font-size:var(--fs-15);flex-grow:1">Invoice &amp; SMS history</h2>
    <form method="get"><select name="f" onchange="this.form.submit()" style="width:auto;min-height:34px;border-radius:999px;font-size:var(--fs-12);font-weight:600"><option value="">All</option><option value="failed" @selected($f === 'failed')>SMS failed</option><option value="reprinted" @selected($f === 'reprinted')>Reprinted</option></select></form></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Invoice</th><th>Time</th><th>Customer</th><th>Vehicle</th><th class="r">Total</th><th>POS print</th><th>A4 PDF</th><th>SMS</th></tr></thead>
    <tbody>
    @forelse($hist as $s)
      @php($m = $last[$s->id] ?? null)
      @php($ms = $m ? \App\Models\SmsMessage::STATUS[$m->status] : 'Not sent')
      <tr><td class="nw"><a href="{{ route('sales.show', $s) }}">{{ $s->invoice_no }}</a></td><td class="c">{{ $s->created_at->timezone('Asia/Dhaka')->format('H:i') }}</td><td>{{ $s->buyerName() }}</td><td class="c">{{ $s->trip?->vehicle?->code ?? ($s->type === 'wholesale' ? ($s->vehicle ?: 'Wholesale') : 'Counter') }}</td>
        <td class="r">{{ $U::tk($s->total) }}</td><td class="c">{{ $s->pos_prints ? ($s->pos_prints > 1 ? 'Reprinted '.$s->pos_prints.'×' : 'Printed 1×') : '—' }}</td><td class="c">{{ $s->a4_prints ? ($s->a4_prints > 1 ? 'Opened '.$s->a4_prints.'×' : 'Shared') : '—' }}</td>
        <td class="c"><span class="pill" style="{{ ['Sent' => 'background:#e3f6ec;color:#0f6b3c', 'Failed' => 'background:#fde9e7;color:#b3261e', 'Queued' => 'background:#fff1d6;color:#8a4b00'][$ms] ?? '' }}" title="{{ $m?->response }}">{{ $ms }}</span></td></tr>
    @empty
      <tr><td colspan="8" class="empty">No invoices today.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@endsection
