@extends('layout')@section('title','Announcement Bar')
@section('body')
@php $e=$edit; $dz=fn($v)=>$v?\Carbon\Carbon::parse($v,'UTC')->timezone('Asia/Dhaka')->format('Y-m-d\TH:i'):''; $now=now(); @endphp
<h1 style="margin:0">Announcement Bar</h1>
<div style="font-size:12px;color:#6B7280;margin-bottom:10px">Offers and notices shown in the store's top strip, rotating up and down. Live now: <b>{{ $live }}</b>. When nothing is live, the older "Top strip offer ticker" text from Website &amp; Content is used.</div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif @if($errors->any())<div class="card" style="color:#B91C1C">@foreach($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>@endif
<div class="card"><h2>{{ $e ? 'Edit line #'.$e->id : 'New line' }}</h2>
<form method="post" action="/admin/announcements/{{ $e ? $e->id.'/save' : 'save' }}">@csrf
<div style="display:grid;grid-template-columns:70px 3fr 1fr 1fr;gap:10px"><div><label>Icon</label><input name="icon" maxlength="8" value="{{ old('icon',$e->icon??'🎁') }}"></div>
<div><label>Text (max 200)</label><input name="text" maxlength="200" required value="{{ old('text',$e->text??'') }}" placeholder="যেমন: নতুন কাস্টমারের জন্য ৫% ছাড় — আজই অর্ডার করুন"></div>
<div><label>Type</label><select name="kind"><option value="offer" @selected(old('kind',$e->kind??'offer')==='offer')>Offer</option><option value="notice" @selected(old('kind',$e->kind??'')==='notice')>Notice</option></select></div>
<div><label>Order</label><input type="number" name="sort" min="0" value="{{ old('sort',$e->sort??0) }}"></div></div>
<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px;margin-top:8px"><div><label>Link (optional, /page or https://…)</label><input name="link" maxlength="300" value="{{ old('link',$e->link??'') }}" placeholder="/offers"></div>
<div><label>Show from (Dhaka, optional)</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at',$dz($e->starts_at??null)) }}"></div>
<div><label>Show until (Dhaka, optional)</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at',$dz($e->ends_at??null)) }}"></div></div>
<p><label style="display:inline"><input type="checkbox" name="active" value="1" style="width:auto" @checked(old('active',$e->active??true))> Active</label> &nbsp; <button>{{ $e ? 'Save changes' : 'Add line' }}</button>@if($e) <a href="/admin/announcements" style="margin-left:10px">Cancel</a>@endif</p></form></div>
<div class="card"><h2>All lines</h2><table><thead><tr><th>Order</th><th>Line</th><th>Type</th><th>Window (Dhaka)</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($rows as $x)@php $up=$x->starts_at && $x->starts_at>$now; $ex=$x->ends_at && $x->ends_at<$now; $st=!$x->active?['Paused','#6B7280']:($ex?['Expired','#B91C1C']:($up?['Scheduled','#1D5FA8']:['Live','#166534'])); @endphp
<tr style="{{ $st[0]==='Live'?'':'opacity:.7' }}"><td>{{ $x->sort }}</td><td>{{ $x->icon }} {{ $x->text }}@if($x->link)<div style="font-size:11px;color:#6B7280">→ {{ $x->link }}</div>@endif</td><td>{{ ucfirst($x->kind) }}</td>
<td style="font-size:12px">{{ $x->starts_at?\Carbon\Carbon::parse($x->starts_at,'UTC')->timezone('Asia/Dhaka')->format('d M H:i'):'Always' }} → {{ $x->ends_at?\Carbon\Carbon::parse($x->ends_at,'UTC')->timezone('Asia/Dhaka')->format('d M H:i'):'No end' }}</td>
<td><b style="color:{{ $st[1] }}">{{ $st[0] }}</b></td>
<td style="white-space:nowrap"><a href="/admin/announcements/{{ $x->id }}/edit">Edit</a> · <form method="post" action="/admin/announcements/{{ $x->id }}/toggle" style="display:inline">@csrf<button style="all:unset;color:#1D5FA8;cursor:pointer">{{ $x->active?'Pause':'Resume' }}</button></form> · <form method="post" action="/admin/announcements/{{ $x->id }}/delete" style="display:inline" onsubmit="return confirm('Remove this line?')">@csrf<button style="all:unset;color:#B91C1C;cursor:pointer">Delete</button></form></td></tr>
@empty<tr><td colspan="6" style="color:#6B7280">No lines yet. Add the first offer or notice above.</td></tr>@endforelse</tbody></table></div>
@endsection
