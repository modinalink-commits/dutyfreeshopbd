@extends('layout')@section('title','Push Notifications')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:14px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Push Notifications</h1><span style="font-size:13px;color:#6B7280">Send order updates and offers to customers who installed the DutyFreeBD app or allowed notifications.</span></div></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<div class="grid" style="margin-bottom:16px">
<div class="card k" style="margin:0"><span style="font-size:13px">Subscribed devices</span><b style="font-size:22px">{{ number_format($stats['subs']) }}</b><span>{{ number_format($stats['linked']) }} linked to a customer account</span></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Notifications delivered</span><b style="font-size:22px">{{ number_format($stats['sent']) }}</b><span>all campaigns</span></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Opened by tap</span><b style="font-size:22px">{{ number_format($stats['clicks']) }}</b><span>{{ $stats['sent'] ? round($stats['clicks'] / $stats['sent'] * 100, 1) : 0 }}% click rate</span></div></div>
<div class="pxg"><form class="card" method="post" action="/admin/push" id="pf" style="margin:0">@csrf
<b>New notification</b>
<div class="pxr"><div><label>Group</label><select name="kind" id="pk"><option value="marketing">Marketing</option><option value="transactional">Transactional</option></select></div><div><label>Type</label><select name="type" id="pt"></select></div></div>
<label>Title <small id="tc" style="color:#6B7280"></small></label><input name="title" id="ptl" maxlength="120" required placeholder="e.g. Flash Sale: up to 40% off today" value="{{ old('title') }}">
<label>Message</label><textarea name="body" id="pb" rows="3" maxlength="400" required placeholder="Short and clear. Bangla or English.">{{ old('body') }}</textarea>
<div class="pxr"><div><label>Image link (optional)</label><input name="image" id="pim" type="url" placeholder="https://…/banner.jpg" value="{{ old('image') }}"></div><div><label>Button text (optional)</label><input name="cta" id="pcta" maxlength="40" placeholder="e.g. Shop now" value="{{ old('cta') }}"></div></div>
<label>Opens this page when tapped</label><input name="url" id="pu" placeholder="/ or /#flash or /account/coupons" value="{{ old('url', '/') }}" list="pul"><datalist id="pul"><option value="/"><option value="/#flash"><option value="/track"><option value="/account/orders"><option value="/account/coupons"><option value="/account/support"></datalist>
<label>Audience</label><select name="audience_type" id="pa">@foreach($aud as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
<div id="pav" style="display:none"><label id="pavl">Value</label><input name="audience_value" id="pavi"><select id="pavs" style="display:none"></select></div>
<div id="pac" style="font-size:12.5px;color:#6B7280;margin:6px 0">Devices that will receive it: <b id="pan">…</b></div>
<label>Schedule (Bangladesh time, only for “Schedule”)</label><input type="datetime-local" name="schedule_at" id="ps">
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"><button class="btn" name="action" value="send" type="submit" onclick="return confirm('Send this notification now?')">Send now</button><button class="btn g" name="action" value="schedule" type="submit">Schedule</button><button class="btn g" name="action" value="draft" type="submit">Save draft</button><button class="btn g" type="button" id="ptest">Send test to this device</button></div>
<div style="margin-top:10px;font-size:12.5px;color:#6B7280" id="pdev"><button class="btn g" type="button" id="pen" style="padding:5px 10px;font-size:12px">Enable notifications on this device</button> <span id="pdt"></span></div></form>
<div><div class="card" style="margin:0 0 14px"><b>Preview</b><div class="pv"><img src="/pwa/icon-192.png" alt="" width="40" height="40"><div><small>DutyFreeBD · now</small><b id="vt">Title</b><span id="vb">Message</span></div></div><img id="vi" alt="" style="display:none;width:100%;border-radius:10px;margin-top:8px"></div>
<div class="card" style="margin:0;font-size:12.5px;color:#4B5563"><b>How audiences work</b><br>VIP: membership name contains “VIP” or total spend of ৳30,000+ (type a different amount in the value box). Regular: 3+ completed orders. Returning: 2+. New: 0-1. Only devices where the customer was signed in are matched; “All customers” also reaches visitors who allowed notifications. Customers who turned an offer type off are skipped; order confirmations are always sent.</div></div></div>
<div class="card" style="margin-top:16px"><b>History</b><div style="overflow:auto"><table class="t" style="width:100%"><thead><tr><th>Title</th><th>Type</th><th>Audience</th><th>Status</th><th>Sent</th><th>Failed</th><th>Taps</th><th>When</th><th></th></tr></thead><tbody>
@forelse($camps as $c)<tr><td><b>{{ $c->title }}</b><br><small style="color:#6B7280">{{ \Illuminate\Support\Str::limit($c->body, 70) }}</small></td><td>{{ $types[$c->kind][$c->type][0] ?? $c->type }}</td><td>{{ \Illuminate\Support\Str::before($aud[$c->audience_type] ?? $c->audience_type, ' (') }}@if($c->audience_value)<br><small>{{ $c->audience_value }}</small>@endif</td><td><span class="sc {{ $c->status === 'sent' ? 'resolved' : ($c->status === 'cancelled' ? '' : 'waiting') }}">{{ ucfirst($c->status) }}</span></td><td>{{ $c->sent }}/{{ $c->targeted }}</td><td>{{ $c->failed }}</td><td>{{ $c->clicks }}</td><td>{{ $c->when }}</td><td style="white-space:nowrap">@if($c->status === 'draft')<form method="post" action="/admin/push/{{ $c->id }}/send" style="display:inline">@csrf<button class="btn g" style="padding:4px 9px;font-size:12px">Send</button></form> @endif @if(in_array($c->status, ['draft','scheduled','sending']))<form method="post" action="/admin/push/{{ $c->id }}/cancel" style="display:inline">@csrf<button class="btn g" style="padding:4px 9px;font-size:12px">Cancel</button></form>@endif</td></tr>
@empty<tr><td colspan="9" style="color:#6B7280">No notifications yet.</td></tr>@endforelse</tbody></table></div></div>
<style>.pxg{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:16px}@media(max-width:1000px){.pxg{grid-template-columns:1fr}}.pxr{display:grid;grid-template-columns:1fr 1fr;gap:10px}@media(max-width:560px){.pxr{grid-template-columns:1fr}}
.pv{display:flex;gap:10px;background:#F3F4F6;border-radius:14px;padding:12px;margin-top:8px}.pv img{border-radius:9px;flex:none}.pv small{display:block;color:#6B7280;font-size:11px}.pv b{display:block;font-size:14px}.pv span{font-size:13px;color:#374151}</style>
@endsection
@section('js')

<script>
(function(){
var TYPES=@json($typeNames);
var STORES=@json($stores),CATS=@json($cats);
var $=function(i){return document.getElementById(i)};
function fillTypes(){var k=$('pk').value,h='';for(var t in TYPES[k])h+='<option value="'+t+'">'+TYPES[k][t]+'</option>';$('pt').innerHTML=h}
$('pk').onchange=fillTypes;fillTypes();
function prev(){$('vt').textContent=$('ptl').value||'Title';$('vb').textContent=$('pb').value||'Message';var i=$('pim').value;$('vi').style.display=i?'block':'none';if(i)$('vi').src=i;$('tc').textContent='('+$('ptl').value.length+'/65 recommended)'}
['ptl','pb','pim'].forEach(function(x){$(x).addEventListener('input',prev)});prev();
var tm;function cnt(){clearTimeout(tm);tm=setTimeout(function(){var v=$('pavs').style.display!=='none'?$('pavs').value:$('pavi').value;fetch('/admin/push/count?t='+$('pa').value+'&v='+encodeURIComponent(v),{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){$('pan').textContent=j.n})},250)}
function aud(){var a=$('pa').value,w=$('pav'),i=$('pavi'),s=$('pavs'),l=$('pavl');w.style.display='none';i.style.display='';s.style.display='none';i.name='audience_value';s.name='';i.value='';
 if(a==='customer'){w.style.display='block';l.textContent='Customer phone';i.placeholder='01XXXXXXXXX'}
 else if(a==='vip'){w.style.display='block';l.textContent='VIP minimum spend (৳, optional)';i.placeholder='30000'}
 else if(a==='product'){w.style.display='block';l.textContent='Product ID';i.placeholder='e.g. 125'}
 else if(a==='store'||a==='category'){w.style.display='block';l.textContent=a==='store'?'Store':'Category';i.style.display='none';i.name='';s.name='audience_value';s.style.display='';s.innerHTML=(a==='store'?STORES:CATS).map(function(x){return '<option value="'+x.id+'">'+x.name+'</option>'}).join('')}
 cnt()}
$('pa').onchange=aud;$('pavi').oninput=cnt;$('pavs').onchange=cnt;aud();
// this device (admin) for test sends
function u8(b){var p='='.repeat((4-b.length%4)%4),s=(b+p).replace(/-/g,'+').replace(/_/g,'/'),r=atob(s),o=new Uint8Array(r.length);for(var i=0;i<r.length;i++)o[i]=r.charCodeAt(i);return o}
var ok='serviceWorker' in navigator&&'PushManager' in window;
function reg(){return navigator.serviceWorker.register('/pwa-sw').then(function(){return navigator.serviceWorker.ready})}
if(!ok){$('pdt').textContent='This browser cannot receive notifications.'}
$('pen').onclick=function(){if(!ok)return;Notification.requestPermission().then(function(p){if(p!=='granted'){$('pdt').textContent='Permission was not given.';return}return reg().then(function(r){return fetch('/push/key').then(function(x){return x.json()}).then(function(k){return r.pushManager.getSubscription().then(function(s){return s||r.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:u8(k.key)})})})}).then(function(s){return fetch('/admin/push/device',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('input[name=_token]').value},body:JSON.stringify(s.toJSON()),credentials:'same-origin'})}).then(function(){$('pdt').textContent='This device is ready for test notifications ✓'})})};
$('ptest').onclick=function(){if(!ok)return;reg().then(function(r){return r.pushManager.getSubscription()}).then(function(s){if(!s){$('pdt').textContent='Enable notifications on this device first.';return}return fetch('/admin/push/test',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('input[name=_token]').value},credentials:'same-origin',body:JSON.stringify({endpoint:s.endpoint,title:$('ptl').value||'DutyFreeBD',body:$('pb').value||'Test',url:$('pu').value,image:$('pim').value,cta:$('pcta').value})}).then(function(r){return r.json()}).then(function(j){$('pdt').textContent=j.ok?'Test sent ✓':('Could not send ('+(j.msg||j.code)+')')})})};
})();
</script>
@endsection
