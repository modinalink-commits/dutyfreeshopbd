@extends('layout')@section('title','Order WhatsApp Confirmation')
@section('body')
<h1 style="margin:0 0 4px">Order WhatsApp Confirmation</h1><span style="font-size:13px;color:#6B7280">Sends a WhatsApp message automatically when an order is approved. Uses the WhatsApp number already connected in Chat Settings.</span>
@if(session('ok'))<div class="card" style="color:#166534;margin-top:10px">{{ session('ok') }}</div>@endif @if(session('err'))<div class="card err" style="margin-top:10px">{{ session('err') }}</div>@endif @if($errors->any())<div class="card err" style="margin-top:10px">{{ $errors->first() }}</div>@endif
@if(!$ready)<div class="card err" style="margin-top:10px">The database tables are not ready yet (migration pending).</div>@endif
<form class="card" method="post" action="/admin/wa-confirm-settings" style="max-width:720px;margin-top:12px">@csrf
<label><input type="checkbox" name="enabled" value="1" @checked($c('enabled','1')==='1')> Enable automatic confirmation</label><br>
<label><input type="checkbox" name="on_ai" value="1" @checked($c('on_ai','1')==='1')> Send when AI approves an order</label><br>
<label><input type="checkbox" name="on_manual" value="1" @checked($c('on_manual','0')==='1')> Send when staff approve manually</label><br>
<label><input type="checkbox" name="retry" value="1" @checked($c('retry','1')==='1')> Automatic retry (3 tries) on temporary errors</label>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-top:10px">
<div><label>Template name</label><input name="template" value="{{ $c('template','order_confirmation') }}"></div>
<div><label>Language</label><input name="lang" value="{{ $c('lang','bn') }}"></div>
<div><label>Brand name in message</label><input name="brand" value="{{ $c('brand','DutyFreeBD') }}"></div>
<div><label>WhatsApp Business Account ID</label><input name="waba" value="{{ $c('waba','1728673528206378') }}"></div></div>
<button class="btn" style="margin-top:10px" type="submit">Save</button></form>
<div class="card" style="max-width:720px"><b>Template</b> · Status: <b>{{ $tplStatus }}</b>
<pre style="white-space:pre-wrap;font-family:inherit;font-size:12.5px;background:#F8FAFC;border:1px solid #E5E7EB;border-radius:8px;padding:8px;margin:8px 0">{{ $body }}</pre>
<div style="display:flex;gap:8px;flex-wrap:wrap"><form method="post" action="/admin/wa-confirm-settings/submit" onsubmit="return confirm('Submit this template to Meta for approval?')">@csrf<button class="btn" type="submit">Submit to Meta</button></form>
<form method="post" action="/admin/wa-confirm-settings/sync">@csrf<button class="btn g" type="submit">Refresh status</button></form></div>
<small style="color:#6B7280">Meta must approve the template before messages can go out (minutes to 24 hours). Only the five highlighted values change per order; they always come from the order record.</small></div>
<form class="card" method="post" action="/admin/wa-confirm-settings/test" style="max-width:720px">@csrf<b>Test message</b><br><small style="color:#6B7280">Sends the real template with sample values. Creates no order record.</small>
<div style="display:flex;gap:8px;margin-top:6px"><input name="phone" placeholder="01XXXXXXXXX" style="max-width:220px"><button class="btn" type="submit">Send test</button></div></form>
@if($stats)<div class="card" style="max-width:720px"><b>Last 7 days</b> · automatic confirmations: {{ $stats['total'] }}@foreach($stats['by'] as $k=>$v) · {{ $k }}: {{ $v }}@endforeach</div>@endif
@if(!empty($failed) && count($failed))<div class="card" style="max-width:900px"><b>Failed in the last 7 days</b><table style="width:100%;font-size:12px;margin-top:6px;border-collapse:collapse">@foreach($failed as $f)<tr style="border-top:1px solid #eee"><td style="padding:4px"><a href="/admin/orders/{{ $f->order_id }}">{{ $f->invoice }}</a></td><td style="padding:4px">{{ $f->mobile }}</td><td style="padding:4px">{{ $f->failure_reason }}</td><td style="padding:4px">{{ stripos((string)$f->failure_reason, 'undeliverable') !== false ? 'Number not reachable on WhatsApp — contact by call/SMS' : ($f->permanent ? 'Cannot retry' : 'Needs manual resend') }}</td></tr>@endforeach</table></div>@endif
@endsection
