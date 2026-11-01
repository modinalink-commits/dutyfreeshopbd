@extends('layout')@section('title','Bulk SMS')
@section('body')
<h1 style="margin:0 0 12px">{{ $offer ? 'Offer / Coupon SMS' : 'Bulk SMS' }}</h1>@include('smsmk.nav')
@php $cj = $camp ? (json_decode((string)$camp->segments,true) ?: []) : []; $cf = $camp ? (json_decode((string)$camp->filters,true) ?: []) : []; $cv = $camp ? (json_decode((string)$camp->vars,true) ?: []) : []; $tz = $camp && $camp->schedule_at ? \Carbon\Carbon::parse($camp->schedule_at,'UTC')->timezone('Asia/Dhaka') : null; @endphp
<form method="post" action="/admin/sms-marketing/send" id="f" style="display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,1fr);gap:16px;align-items:start">@csrf
@if($camp)<input type="hidden" name="campaign_id" value="{{ $camp->id }}">@endif<input type="hidden" name="kind" value="{{ $ids ? 'selected' : ($offer ? 'offer' : 'bulk') }}">
<div>
<div class="card"><b>1. Campaign</b><label>Campaign name</label><input name="name" required maxlength="160" value="{{ old('name', $camp->name ?? ($offer ? 'Offer '.now('Asia/Dhaka')->format('d M') : '')) }}"><label>Subject (internal)</label><input name="subject" maxlength="160" value="{{ old('subject', $camp->subject ?? '') }}"></div>
<div class="card"><b>2. Target audience</b>
@if($ids)<p style="font-size:13px">{{ count($ids) }} selected customer(s) @foreach($idRows as $r){{ $loop->first ? ':' : ',' }} {{ $r->name }}@endforeach</p>@foreach($ids as $i)<input type="hidden" name="ids[]" value="{{ $i }}">@endforeach
@else<div class="sg" style="margin-top:8px">@foreach($segments as $k=>$v)<label><input type="checkbox" name="segments[]" value="{{ $k }}" class="rf" {{ in_array($k,old('segments',$cj))?'checked':'' }}>{{ $v }}</label>@endforeach</div>
<details style="margin-top:6px" {{ $cf ? 'open' : '' }}><summary style="cursor:pointer;font-size:13px;font-weight:600">Smart filters (all optional, combined with AND)</summary><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px">
<div><label>Min orders</label><input type="number" name="min_orders" class="rf" value="{{ $cf['min_orders'] ?? '' }}"></div><div><label>Max orders</label><input type="number" name="max_orders" class="rf" value="{{ $cf['max_orders'] ?? '' }}"></div>
<div><label>Min total spend ৳</label><input type="number" name="min_spend" class="rf" value="{{ $cf['min_spend'] ?? '' }}"></div><div><label>No order in last (days)</label><input type="number" name="no_order_days" class="rf" value="{{ $cf['no_order_days'] ?? '' }}"></div>
<div><label>Bought product containing</label><input name="keyword" class="rf" placeholder="e.g. Perfume" value="{{ $cf['keyword'] ?? '' }}"></div></div></details>@endif
<div id="aud" style="margin-top:10px;font-size:13px;color:#6B7280"></div></div>
<div class="card"><b>3. Message</b>
<label>Template</label><select id="tpl" name="template_id"><option value="">— write your own —</option>@foreach($templates as $t)<option value="{{ $t->id }}" data-b="{{ $t->body }}" {{ (request('tpl')==$t->id || ($camp && $camp->template_id==$t->id))?'selected':'' }}>[{{ $t->category }}] {{ $t->name }}</option>@endforeach</select>
@if($offer)<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px"><div><label>Coupon</label><select id="cpn" onchange="if(this.value){document.getElementById('cc').value=this.value.split('|')[0];document.getElementById('dc').value=this.value.split('|')[1];document.getElementById('ex').value=this.value.split('|')[2];up()}"><option value="">choose…</option>@foreach($coupons as $c)<option value="{{ $c->code }}|{{ $c->type==='percent'?rtrim(rtrim($c->value,'0'),'.'):'Tk '.(int)$c->value }}|{{ $c->valid_to ? \Carbon\Carbon::parse($c->valid_to)->format('d M') : '' }}">{{ $c->code }}</option>@endforeach</select></div>@endif
<div style="display:{{ $offer ? 'contents' : 'none' }}"><input type="hidden" id="x1"></div>
@if($offer)<div><label>Coupon code</label><input name="coupon_code" id="cc" class="rf" value="{{ $cv['coupon_code'] ?? '' }}"></div><div><label>Discount</label><input name="discount" id="dc" class="rf" value="{{ $cv['discount'] ?? '' }}" placeholder="10"></div><div><label>Expiry</label><input name="expiry_date" id="ex" class="rf" value="{{ $cv['expiry_date'] ?? '' }}" placeholder="5 Nov"></div></div>
@else<input type="hidden" name="coupon_code" value="{{ $cv['coupon_code'] ?? '' }}">@endif
<label>Message body</label><textarea name="body" id="body" rows="5" required maxlength="1000">{{ old('body', $camp->body ?? (request('tpl') ? \Illuminate\Support\Facades\DB::table('sms_templates')->where('id',request('tpl'))->value('body') : '')) }}</textarea>
<div style="font-size:12px;color:#6B7280;margin-top:4px">Variables: {customer_name} {total} {membership} @if($offer){coupon_code} {discount} {expiry_date}@endif — replaced for each customer. Characters: <b id="ch">0</b> · SMS parts: <b id="pt">0</b></div></div>
<div class="card"><b>4. Send</b>
<div class="sg" style="margin-top:8px"><label><input type="radio" name="when" value="now" {{ !$camp || !$tz ? 'checked' : '' }}>Send now</label><label><input type="radio" name="when" value="later" {{ $tz ? 'checked' : '' }}>Schedule</label><label><input type="radio" name="when" value="draft">Save as draft</label></div>
<div id="sch" style="display:{{ $tz ? 'grid' : 'none' }};grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px"><div><label>Send date</label><input type="date" name="schedule_date" id="sd" min="{{ now('Asia/Dhaka')->format('Y-m-d') }}" value="{{ $tz?->format('Y-m-d') }}"></div><div><label>Send time (Asia/Dhaka)</label><input type="time" name="schedule_time" id="st" value="{{ $tz?->format('H:i') ?: '20:00' }}"></div>
<div><label>Repeat</label><select name="repeat">@foreach(['none'=>'One time','daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly'] as $k=>$v)<option value="{{ $k }}" {{ ($camp->repeat ?? 'none')===$k?'selected':'' }}>{{ $v }}</option>@endforeach</select></div></div>
<div id="when" style="font-size:13px;font-weight:600;margin-top:6px"></div>
<label style="display:flex;gap:8px;align-items:center;margin-top:10px;font-size:12px"><input type="checkbox" name="allow_dup" value="1" style="width:auto" {{ ($camp->allow_dup ?? false)?'checked':'' }}> Allow sending to the same number again within this campaign (off = duplicate protection)</label>
<div style="margin-top:12px"><button type="button" id="sub">Review &amp; send</button></div></div>
</div>
<div style="position:sticky;top:12px"><div class="card"><b>Summary</b>
<div style="font-size:14px;line-height:2;margin-top:6px">Recipients: <b id="rc">—</b><br>SMS parts: <b id="rp">—</b><br>Cost per SMS: <b>৳{{ number_format($unit,2) }}</b><br>Estimated cost: <b id="ec">—</b><br>Available balance: <b id="bl">—</b><br>Sender ID: <b>{{ $sender ?: 'not set' }}</b></div><div id="warn" style="color:#B91C1C;font-size:13px;margin-top:6px"></div>
@if(!$ready)<div style="color:#B91C1C;font-size:12px;margin-top:6px">Gateway not connected — messages will stay queued. <a href="/admin/sms">Connect</a></div>@endif</div>
<div class="card"><b>Preview</b><div id="pv" style="margin-top:8px;background:#F3F4F6;border-radius:14px;padding:12px;font-size:13px;white-space:pre-wrap;min-height:60px"></div><div id="smp" style="font-size:11px;color:#6B7280;margin-top:6px"></div></div></div>
</form>
<div id="cf" style="display:none;position:fixed;inset:0;background:rgba(10,20,50,.55);z-index:9999;align-items:center;justify-content:center;padding:16px"><div class="card" style="max-width:420px;margin:0"><b style="font-size:16px">Confirm sending</b><p id="cm" style="font-size:14px"></p><div style="display:flex;gap:8px"><button type="button" id="ok">Confirm Send</button><button type="button" class="g" id="no">Cancel</button></div></div></div>
<style>@media(max-width:900px){#f{grid-template-columns:1fr!important}#f>div:last-child{position:static!important}}</style>
<script>(function(){var f=document.getElementById('f'),b=document.getElementById('body'),t,last=null,tok=document.querySelector('input[name=_token]').value;
function fmt(n){return Number(n).toLocaleString('en-US')}
function up(){clearTimeout(t);t=setTimeout(run,350)}
function run(){var fd=new FormData(f);fetch('/admin/sms-marketing/estimate',{method:'POST',body:fd,headers:{'X-CSRF-TOKEN':tok,Accept:'application/json'}}).then(function(r){return r.json()}).then(function(j){last=j;
rc.textContent=fmt(j.recipients);rp.textContent=j.parts;ec.textContent='৳'+fmt(j.cost);bl.textContent=j.balance===null?'unknown':'৳'+fmt(j.balance);ch.textContent=j.chars;pt.textContent=j.parts;pv.textContent=j.preview||'';
smp.textContent=j.unicode?'Bengali/Unicode text: 70 characters per part':'English text: 160 characters per part';
aud.innerHTML='<b style="color:#0A1E50">Total recipients: '+fmt(j.recipients)+'</b>'+(j.sample.length?' — e.g. '+j.sample.map(function(s){return s.name}).join(', '):'');
warn.textContent=j.enough?'':'Estimated cost is more than your available balance.'}).catch(function(){})}
f.addEventListener('input',up);f.addEventListener('change',up);
document.getElementById('tpl').onchange=function(){var o=this.options[this.selectedIndex];if(o.dataset.b){b.value=o.dataset.b;up()}};
function w(){var l=f.when.value==='later';sch.style.display=l?'grid':'none';var d=sd.value,s=st.value;when.textContent=l&&d?'Scheduled For: '+new Date(d+'T'+(s||'09:00')).toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:true})+' · Asia/Dhaka':''}
f.addEventListener('change',w);f.addEventListener('input',w);
sub.onclick=function(){if(!f.reportValidity())return;if(!last||!last.recipients){alert('No recipients match this audience yet.');return}
var w=f.when.value;cm.textContent=(w==='draft'?'Save draft for ':w==='later'?'Schedule SMS for ':'You are about to send ')+fmt(last.recipients)+' recipients ('+last.parts+' part'+(last.parts>1?'s':'')+' each). Estimated cost: ৳'+fmt(last.cost)+'.'+(last.enough?'':' ⚠ Balance may be insufficient.');cf.style.display='flex'};
no.onclick=function(){cf.style.display='none'};ok.onclick=function(){ok.disabled=true;f.submit()};
var rc=document.getElementById('rc'),rp=document.getElementById('rp'),ec=document.getElementById('ec'),bl=document.getElementById('bl'),ch=document.getElementById('ch'),pt=document.getElementById('pt'),pv=document.getElementById('pv'),smp=document.getElementById('smp'),aud=document.getElementById('aud'),warn=document.getElementById('warn'),sd=document.getElementById('sd'),st=document.getElementById('st'),when=document.getElementById('when'),sch=document.getElementById('sch'),sub=document.getElementById('sub'),cm=document.getElementById('cm'),cf=document.getElementById('cf'),no=document.getElementById('no'),ok=document.getElementById('ok');
window.up=up;run();w();})();</script>
@endsection
