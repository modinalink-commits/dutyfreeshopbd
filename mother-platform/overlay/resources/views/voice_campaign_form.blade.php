@extends('layout')@section('title', $c ? 'Edit Voice Campaign' : 'New Voice Campaign')
@section('body')
@php $cfg = $c ? (json_decode($c->segment_cfg, true) ?: []) : []; $o = fn($k, $d = '') => old($k, $c->$k ?? $d); $val = old('validity', $c ? ($c->expiry_date ? 'custom' : $c->validity_days) : 3); @endphp
<h1 style="margin:0 0 4px">{{ $c ? 'Edit' : 'New' }} Voice Campaign</h1><div style="font-size:12px;color:#6B7280;margin-bottom:10px">Last Ordered Product → personal discount → scheduled voice call</div>
@if($errors->any())<div class="card" style="color:#B91C1C">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<form method="post" action="/admin/voice/{{ $c ? $c->id.'/save' : 'save' }}" enctype="multipart/form-data">@csrf
<div class="card"><h2>Campaign</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
<div><label>Campaign name</label><input name="name" value="{{ $o('name', 'Last Product 5% Win-Back Offer') }}" required></div>
<div><label>Campaign type</label><select disabled><option>Last Ordered Product</option></select></div>
<div><label>Discount % (on the last ordered product)</label><input type="number" step="0.5" min="0.5" max="90" name="discount_value" value="{{ $o('discount_value', 5) }}"></div>
<div><label>Offer valid for</label><select name="validity" id="vv" onchange="document.getElementById('xd').style.display=this.value==='custom'?'block':'none'">@foreach([1=>'1 Day',3=>'3 Days',7=>'7 Days'] as $k=>$l)<option value="{{ $k }}" @selected((string)$val===(string)$k)>{{ $l }}</option>@endforeach<option value="custom" @selected($val==='custom')>Custom expiry date</option></select></div>
<div id="xd" style="display:{{ $val==='custom' ? 'block' : 'none' }}"><label>Expiry date</label><input type="date" name="expiry_date" value="{{ $o('expiry_date') ? substr($o('expiry_date'),0,10) : '' }}"></div></div>
<label style="display:block"><input type="checkbox" name="whole_cart" value="1" @checked($o('whole_cart', false))> Apply the % to the whole cart (default: only the last ordered product)</label>
<label style="display:block"><input type="checkbox" name="reusable" value="1" @checked($o('reusable', false))> Offer can be used more than once</label></div>
<div class="card"><h2>Customers</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
<div><label>Audience</label><select name="segment" onchange="seg(this.value)">@foreach($segs as $k=>$l)<option value="{{ $k }}" @selected($o('segment','all')===$k)>{{ $l }}</option>@endforeach</select></div>
<div><label>Cooldown between promo calls (days)</label><input type="number" min="0" name="cooldown_days" value="{{ $o('cooldown_days', 7) }}"></div></div>
<div id="sel" style="display:none"><label>Phone numbers (one per line)</label><textarea name="segment_phones" rows="4">{{ $o('segment_phones') }}</textarea></div>
<div id="cus" style="display:none;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px"><div><label>Inactive at least (days)</label><input type="number" min="0" name="min_days" value="{{ old('min_days', $cfg['min_days'] ?? 0) }}"></div><div><label>Inactive at most (days, 0 = any)</label><input type="number" min="0" name="max_days" value="{{ old('max_days', $cfg['max_days'] ?? 0) }}"></div><div><label>Minimum orders</label><input type="number" min="0" name="min_orders" value="{{ old('min_orders', $cfg['min_orders'] ?? 0) }}"></div><div><label>Category</label><select name="category"><option value="">Any</option><option value="vip" @selected(($cfg['category'] ?? '')==='vip')>VIP</option><option value="regular" @selected(($cfg['category'] ?? '')==='regular')>Regular</option></select></div></div>
<div style="font-size:11.5px;color:#6B7280;margin-top:6px">Only customers with a delivered order, no newer open order, calls allowed, an active in-stock last product and outside the cooldown are included.</div></div>
<div class="card"><h2>Schedule</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
<div><label>Campaign start date</label><input type="date" name="start_date" value="{{ $o('start_date') ? substr($o('start_date'),0,10) : '' }}"></div><div><label>Campaign end date</label><input type="date" name="end_date" value="{{ $o('end_date') ? substr($o('end_date'),0,10) : '' }}"></div>
<div><label>Call start time</label><input type="time" name="call_start_time" value="{{ $o('call_start_time','10:00') }}"></div><div><label>Call end time</label><input type="time" name="call_end_time" value="{{ $o('call_end_time','20:00') }}"></div>
<div><label>Max calls per day</label><input type="number" min="1" name="daily_limit" value="{{ $o('daily_limit',200) }}"></div>
<div><label>Max attempts per customer</label><input type="number" min="1" max="5" name="retry_limit" value="{{ $o('retry_limit',2) }}"></div><div><label>Retry after (hours)</label><input type="number" min="1" name="retry_gap_hours" value="{{ $o('retry_gap_hours',6) }}"></div></div>
<label style="display:block"><input type="checkbox" name="retry_on" value="1" @checked($o('retry_on', true))> Retry no-answer / busy / failed calls</label></div>
<div class="card"><h2>Voice & follow-up</h2>
<label>Voice message (use {product} {pct} {days} {name}; blank = standard Bangla script)</label><textarea name="script" rows="4" placeholder="{{ $script }}">{{ $o('script') }}</textarea>
<label style="display:block"><input type="checkbox" name="dtmf_on" value="1" @checked($o('dtmf_on', true))> Interactive keys: 1 = take offer, 2 = talk to agent, 9 = stop promotional calls</label>
<label>Prerecorded audio instead of text-to-speech (.wav 8 kHz recommended) {{ $c && $c->audio_path ? '— one is uploaded' : '' }}</label><input type="file" name="audio" accept=".wav,.mp3">@if($c && $c->audio_path)<label style="display:block"><input type="checkbox" name="remove_audio" value="1"> Remove uploaded audio</label>@endif
<label style="display:block;margin-top:10px"><input type="checkbox" name="sms_follow" value="1" @checked($o('sms_follow', false))> Send follow-up SMS when the customer answers</label>
<label>SMS text ({product} {pct} {date})</label><textarea name="sms_text" rows="2" placeholder="{{ $sms }}">{{ $o('sms_text') }}</textarea>
<div style="font-size:11.5px;color:#6B7280;margin-top:4px">WhatsApp follow-up is not connected yet, so it is not offered here.</div></div>
<p><button>Save campaign</button> <a class="btn g" href="/admin/voice" style="text-decoration:none">Cancel</a></p></form>
<script>function seg(v){document.getElementById('sel').style.display=v==='selected'?'block':'none';document.getElementById('cus').style.display=v==='custom'?'grid':'none'}seg(document.querySelector('[name=segment]').value)</script>
@endsection
