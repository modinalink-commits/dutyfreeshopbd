@extends('layout')@section('title','Supplier: '.$s->name)
@section('body')
<style>.scs .card{padding:12px 14px;margin-bottom:10px}.scs h2{font-size:14px;margin:0 0 8px}.scs table{width:100%;border-collapse:collapse;font-size:12.5px}.scs th{font-size:11px;color:#6B7280;text-align:left;padding:6px 8px;border-bottom:1px solid #E5E7EB}.scs td{padding:6px 8px;border-bottom:1px solid #EEF1F4;vertical-align:top}.scs .f{display:flex;gap:10px;flex-wrap:wrap;align-items:end}.scs label{font-size:12px;color:#374151}.scs input[type=text],.scs select{height:30px}.scs .mu{color:#6B7280}.scs .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11.5px;font-weight:700;background:#EEF2F7;color:#0B1B33;white-space:nowrap}
.scs .tl{display:flex;flex-direction:column;gap:8px;max-height:620px;overflow:auto;padding:4px}.scs .m{max-width:78%;border:1px solid #E5E7EB;border-radius:10px;padding:7px 10px;background:#fff;font-size:13px}.scs .m.in{align-self:flex-start;background:#F8FAFC}.scs .m.out{align-self:flex-end;background:#FFF7ED;border-color:#FED7AA}.scs .m.auto{background:#EFF8FF;border-color:#B2DDFF}.scs .m.note{align-self:center;background:#FFFAEB;border:1px dashed #FEC84B;max-width:90%}.scs .m .h{font-size:11px;color:#6B7280;margin-bottom:3px;display:flex;gap:8px;flex-wrap:wrap}.scs .m .h b{color:#0B1B33}.scs .m .b{white-space:pre-wrap;word-break:break-word}.scs .day{align-self:center;font-size:11px;color:#6B7280;background:#F2F4F7;border-radius:999px;padding:2px 10px}</style>
<div class="scs">
<div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:8px"><div><h1 style="font-size:18px;margin:0">{{ $s->name }}</h1><small class="mu">{{ $s->business_name ?: 'No business name' }} · <a href="/admin/purchase/supplier-chats">All supplier conversations</a></small></div>
@if($t)<a class="btn g" href="/admin/chat?open={{ $t->id }}" target="_blank" rel="noopener">Open in Messages</a>@endif</div>
@if(session('ok'))<div class="card" style="background:#F0FAF3;border-color:#BFE3CC;color:#14683A;font-weight:600">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card" style="background:#FEF3F2;border-color:#F6C4C0;color:#B42318;font-weight:600">{{ $errors->first() }}</div>@endif

<div class="card"><h2>Contact details @if($gaps)<span class="pill" style="background:#FEF0C7;color:#93370D;margin-left:6px">Missing: {{ implode(', ', $gaps) }}</span>@endif</h2>
<form method="post" action="/admin/purchase/supplier-chats/{{ $s->id }}/contact">@csrf<div class="f">
<label>Name<br><input type="text" name="name" value="{{ old('name', $s->name) }}" style="width:200px" required></label>
<label>Business<br><input type="text" name="business_name" value="{{ old('business_name', $s->business_name) }}" style="width:200px"></label>
<label>Contact person<br><input type="text" name="contact_person" value="{{ old('contact_person', $s->contact_person) }}" style="width:160px"></label>
<label>Mobile<br><input type="text" name="phone" value="{{ old('phone', $s->phone) }}" style="width:140px" placeholder="01XXXXXXXXX"></label>
<label>WhatsApp<br><input type="text" name="whatsapp" value="{{ old('whatsapp', $s->whatsapp) }}" style="width:140px" placeholder="01XXXXXXXXX"></label>
<button class="btn" type="submit">Save</button></div></form>
@if($log->count())<details style="margin-top:8px"><summary style="cursor:pointer;font-size:12.5px;font-weight:600">Change history ({{ $log->count() }})</summary><div style="overflow-x:auto"><table><tr><th>When</th><th>Field</th><th>Before</th><th>After</th><th>By</th></tr>
@foreach($log as $l)<tr><td style="white-space:nowrap">{{ \Carbon\Carbon::parse($l->created_at,'UTC')->timezone('Asia/Dhaka')->format('d M Y h:i A') }}</td><td>{{ \App\Support\SupplierComm::FIELDS[$l->field] ?? $l->field }}</td><td>{{ $l->old_value ?? '—' }}</td><td>{{ $l->new_value ?? '—' }}</td><td>{{ $l->user_name }}</td></tr>@endforeach</table></div></details>@endif</div>

<div class="card"><h2>Responsibility</h2>
@if(!$t)<div class="mu">@if(!$numbers)No valid mobile or WhatsApp number is saved, so no WhatsApp conversation can be matched. Add the number above.@else No WhatsApp conversation with this supplier yet ({{ implode(', ', array_map(fn($n) => '0'.substr($n, 3), $numbers)) }}). It appears here by itself when the first message is exchanged.@endif</div>
@else
<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
<span>Responsible: <b id="ownName">{{ $owner['name'] ?: 'Nobody yet' }}</b></span>
<span class="pill" style="{{ $bot ? 'background:#EFF8FF;color:#175CD3' : '' }}">{{ $bot ? 'Automatic replies: on' : 'Automatic replies: off' }}</span>
@if(!$owner['mine'])@if($owner['id'] === null)<button class="btn" type="button" onclick="scTake(false)">Take responsibility</button>@elseif($owner['supervisor'])<button class="btn" type="button" onclick="scTake(true)">Take over (supervisor)</button>@endif @endif
@if($owner['supervisor'])<select id="ownTo"><option value="">Give to…</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach<option value="0">Nobody (back to the queue)</option></select><button class="btn g" type="button" onclick="scGive()">Change</button>@elseif($owner['mine'])<button class="btn g" type="button" onclick="scRelease()">Release</button>@endif
<span id="ownMsg" style="font-size:12.5px;font-weight:600"></span></div>
<small class="mu" style="display:block;margin-top:6px">Only the responsible person can reply. A supervisor can move the conversation to anyone. While a person is responsible, automatic replies stay off. The server checks this again at the moment a reply is sent.</small>
@if($events)<details style="margin-top:6px"><summary style="cursor:pointer;font-size:12.5px;font-weight:600">Responsibility history ({{ count($events) }})</summary><table><tr><th>When</th><th>Action</th><th>From</th><th>To</th><th>By</th><th>Note</th></tr>
@foreach($events as $e)<tr><td style="white-space:nowrap">{{ \Carbon\Carbon::parse($e['at'])->timezone('Asia/Dhaka')->format('d M Y h:i A') }}</td><td>{{ $e['action'] }}</td><td>{{ $e['from'] ?? '—' }}</td><td>{{ $e['to'] ?? '—' }}</td><td>{{ $e['by'] }}</td><td>{{ $e['note'] }}</td></tr>@endforeach</table></details>@endif
@endif</div>

<div class="card"><h2>Conversation <span class="mu">({{ count($tl) }} entries, oldest first)</span></h2>
@if(!$tl)<div class="mu">No message or note yet.</div>@else
<div class="tl" id="scTl">@php $day = ''; $stl = ['sending'=>'Sending…','sent'=>'Sent','delivered'=>'Delivered','read'=>'Read','failed'=>'Failed','received'=>'Received','internal'=>'Internal note · not sent']; @endphp
@foreach($tl as $m)@php $at = \Carbon\Carbon::parse($m['t'],'UTC')->timezone('Asia/Dhaka'); $d = $at->format('d M Y'); @endphp
@if($d !== $day)@php $day = $d; @endphp<div class="day">{{ $d }}</div>@endif
<div class="m {{ $m['side'] }} {{ $m['who_kind']==='auto' ? 'auto' : '' }}"><div class="h"><b>{{ $m['side']==='note' ? 'Internal note · '.$m['who'] : $m['who'] }}</b><span>{{ $at->format('h:i A') }}</span><span>{{ $m['src'] }}</span><span style="{{ $m['status']==='failed' ? 'color:#B42318;font-weight:700' : ($m['status']==='read' ? 'color:#067647' : '') }}">{{ $stl[$m['status']] ?? ucfirst($m['status']) }}</span></div>
@if($m['body'] !== '')<div class="b">{{ $m['body'] }}</div>@endif
@if($m['att'])<div style="margin-top:4px"><a href="{{ $m['att'][0]==='chat' ? '/admin/purchase/supplier-chats/'.$s->id.'/att/'.$m['att'][1] : '/admin/purchase/portal/files/'.$m['att'][1] }}" target="_blank" rel="noopener">📎 Open attachment ({{ $m['att'][2] }})</a></div>@endif
@if($m['error'])<div style="color:#B42318;font-size:11.5px;margin-top:3px">{{ $m['error'] }}</div>@endif</div>@endforeach</div>@endif

@if($t)<div style="border-top:1px solid #E5E7EB;margin-top:10px;padding-top:10px">
@if($owner['mine'] || $owner['id'] === null)
<div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap"><textarea id="scBody" rows="2" placeholder="Reply to the supplier on WhatsApp…" style="flex:1;min-width:260px"></textarea><button class="btn" type="button" id="scSend" onclick="scReply()">Send reply</button></div>
<small class="mu">@if($owner['id'] === null)Nobody is responsible yet: sending a reply makes you responsible.@else You are responsible for this conversation.@endif WhatsApp delivers a free-text reply only within 24 hours of the supplier's last message.</small>
@else<div style="background:#F8FAFC;border:1px solid #E5E7EB;border-radius:8px;padding:8px 10px;font-size:13px">This conversation is with <b>{{ $owner['name'] }}</b>. You can read it and add internal notes, but you cannot reply.</div>@endif
<div id="scMsg" style="font-size:12.5px;font-weight:600;margin-top:6px"></div></div>@endif
<form method="post" action="/admin/purchase/supplier-chats/{{ $s->id }}/note" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-top:10px">@csrf<textarea name="body" rows="2" placeholder="Internal note (staff only, never sent to the supplier)…" style="flex:1;min-width:260px;background:#FFFAEB"></textarea><button class="btn g" type="submit">Save note</button></form>
</div>
</div>
<script>
(function(){
  var TID = {{ $t ? (int)$t->id : 0 }}, ME = {{ (int)auth()->id() }}, CSRF = '{{ csrf_token() }}';
  var tl = document.getElementById('scTl'); if (tl) tl.scrollTop = tl.scrollHeight;
  function post(url, data) { var fd = new FormData(); fd.append('_token', CSRF); for (var k in data) if (data[k] !== null && data[k] !== undefined) fd.append(k, data[k]); return fetch(url, {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: fd}).then(function (r) { return r.json().catch(function () { return {ok: false, error: 'Unexpected answer from the server (' + r.status + ').'}; }); }); }
  function say(id, ok, text) { var e = document.getElementById(id); if (e) { e.style.color = ok ? '#067647' : '#B42318'; e.textContent = text; } }
  window.scTake = function (sup) { (sup ? post('/admin/chat/thread/' + TID + '/assign', {user_id: ME, note: 'Taken over from the supplier page'}) : post('/admin/chat/thread/' + TID + '/claim', {})).then(function (j) { if (j.ok) location.reload(); else say('ownMsg', false, j.error || 'Could not take the conversation.'); }); };
  window.scGive = function () { var v = document.getElementById('ownTo').value; if (v === '') return; var d = {note: 'Changed from the supplier page'}; if (v !== '0') d.user_id = v; post('/admin/chat/thread/' + TID + '/assign', d).then(function (j) { if (j.ok) location.reload(); else say('ownMsg', false, j.error || 'Could not change.'); }); };
  window.scRelease = function () { post('/admin/chat/thread/' + TID + '/assign', {note: 'Released from the supplier page'}).then(function (j) { if (j.ok) location.reload(); else say('ownMsg', false, j.error || 'Could not release.'); }); };
  var tok = null;
  window.scReply = function () {
    var b = document.getElementById('scBody'), btn = document.getElementById('scSend'), text = (b.value || '').trim(); if (!text) { say('scMsg', false, 'Write the message first.'); return; }
    tok = tok || ('sc' + Date.now() + Math.random().toString(36).slice(2, 10)); btn.disabled = true; say('scMsg', true, 'Sending…');
    post('/admin/chat/thread/' + TID + '/reply', {body: text, kind: 'text', client_token: tok}).then(function (j) {
      btn.disabled = false;
      if (j.ok) { tok = null; b.value = ''; say('scMsg', true, 'Sent.'); setTimeout(function () { location.reload(); }, 600); }
      else { if (j.blocked) tok = null; say('scMsg', false, 'Not sent: ' + (j.error || 'the message could not be delivered.')); }
    }).catch(function () { btn.disabled = false; say('scMsg', false, 'Not sent: connection problem. Press Send again; it will not be sent twice.'); });
  };
})();
</script>
@endsection
