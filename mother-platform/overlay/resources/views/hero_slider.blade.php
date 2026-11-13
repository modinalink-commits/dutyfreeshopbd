@extends('layout')@section('title','Hero Media Slider')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Hero Media Slider</h1><div style="font-size:12px;color:#6B7280">Every home hero slide in one list: uploaded image and YouTube slides. Drag a row by the ⠿ handle to change the order. Videos play muted, stop when the slide changes, and move on when they end.</div></div><a class="btn" href="/admin/m/sliders/new">+ Add slide</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<div class="card" style="overflow-x:auto"><table><thead><tr><th></th><th>Preview</th><th>Type</th><th>Title</th><th>Pos</th><th>Desktop</th><th>Mobile</th><th>Status</th><th></th></tr></thead><tbody id="rows">
@forelse($rows as $x)@php $v=($x->media_type??'image')==='video'&&$x->video_id; $th=$x->image ?: ($v?'https://i.ytimg.com/vi/'.$x->video_id.'/hqdefault.jpg':''); @endphp
<tr draggable="true" data-id="{{ $x->id }}" style="{{ $x->active?'':'opacity:.55' }}"><td style="cursor:grab;font-size:18px;color:#9CA3AF">⠿</td>
<td>@if($th)<img src="{{ $th }}" alt="" style="width:110px;height:37px;object-fit:cover;border-radius:6px;display:block" referrerpolicy="no-referrer">@endif</td>
<td><b style="font-size:11px;padding:2px 8px;border-radius:999px;background:{{ $v?'#FEE2E2':'#E6EFF9' }};color:{{ $v?'#B91C1C':'#1D5FA8' }}">{{ $v?'VIDEO':'IMAGE' }}</b></td>
<td>{{ $x->title ?: ($v ? 'YouTube '.$x->video_id : '—') }}</td><td class="pos">{{ $x->position }}</td>
<td>{{ $x->image ? '✓' : ($v ? 'poster auto' : '—') }}</td><td>{{ $x->mobile_image ? '✓' : ($v ? ($x->v_mobile ? 'video' : 'fallback') : '—') }}</td>
<td>{{ $x->active ? 'Active' : 'Paused' }}</td>
<td style="white-space:nowrap">@if($v)<a href="#" data-pv="{{ $x->video_id }}">Preview</a> · @endif<a href="/admin/m/sliders/{{ $x->id }}/edit">Edit</a> · <form method="post" action="/admin/hero-slider/{{ $x->id }}/toggle" style="display:inline">@csrf<button style="all:unset;color:#1D5FA8;cursor:pointer">{{ $x->active?'Pause':'Publish' }}</button></form> · <form method="post" action="/admin/m/sliders/{{ $x->id }}/delete" style="display:inline" onsubmit="return confirm('Delete this slide?')">@csrf<button style="all:unset;color:#B91C1C;cursor:pointer">Delete</button></form></td></tr>
@empty<tr><td colspan="9" style="color:#6B7280">No uploaded slides yet. Add the first image or YouTube slide.</td></tr>@endforelse
@foreach($bi as $b)<tr style="{{ $b['on']?'':'opacity:.55' }}"><td style="color:#D1D5DB">⠿</td>
<td><div style="width:110px;height:37px;border-radius:6px;background:#F3F4F6;color:#6B7280;font-size:10px;display:flex;align-items:center;justify-content:center;text-align:center;line-height:1.1">Built-in</div></td>
<td><b style="font-size:11px;padding:2px 8px;border-radius:999px;background:#F1EAF8;color:#5B3FA6">BUILT-IN</b></td>
<td>{{ $b['label'] }}</td><td>auto</td><td>✓</td><td>✓</td>
<td>{{ $b['on']?'Active':'Paused' }}</td>
<td style="white-space:nowrap"><a href="/admin/home-banners">Settings</a> · <form method="post" action="/admin/hero-slider/builtin/{{ $b['key'] }}/toggle" style="display:inline">@csrf<button style="all:unset;color:#1D5FA8;cursor:pointer">{{ $b['on']?'Pause':'Publish' }}</button></form></td></tr>@endforeach
</tbody></table></div>
<div id="pvm" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:99;align-items:center;justify-content:center"><div style="width:min(900px,92vw);aspect-ratio:16/9;background:#000;position:relative"><button type="button" id="pvx" style="position:absolute;right:-6px;top:-34px;background:#fff;border:0;border-radius:6px;padding:4px 10px;cursor:pointer">Close ✕</button><div id="pvb" style="width:100%;height:100%"></div></div></div>
<script>(function(){var tb=document.getElementById('rows'),drag=null,tok=document.querySelector('meta[name=csrf-token]'),T=tok?tok.content:'{{ csrf_token() }}';
tb.addEventListener('dragstart',function(e){drag=e.target.closest('tr');if(drag){e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain','x')}});
tb.addEventListener('dragover',function(e){e.preventDefault();var t=e.target.closest('tr');if(!drag||!t||t===drag)return;var r=t.getBoundingClientRect();tb.insertBefore(drag,(e.clientY-r.top)>r.height/2?t.nextSibling:t)});
tb.addEventListener('dragend',function(){if(!drag)return;drag=null;var ids=[].map.call(tb.querySelectorAll('tr[data-id]'),function(r){return r.dataset.id});[].forEach.call(tb.querySelectorAll('tr[data-id] .pos'),function(c,i){c.textContent=i+1});fetch('/admin/hero-slider/order',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':T,Accept:'application/json'},body:JSON.stringify({ids:ids})})});
var m=document.getElementById('pvm'),b=document.getElementById('pvb');document.addEventListener('click',function(e){var a=e.target.closest('[data-pv]');if(a){e.preventDefault();b.innerHTML='<iframe src="https://www.youtube-nocookie.com/embed/'+a.dataset.pv+'?autoplay=1&mute=1&rel=0" style="width:100%;height:100%;border:0" allow="autoplay; encrypted-media" allowfullscreen></iframe>';m.style.display='flex'}if(e.target.id==='pvx'||e.target===m){m.style.display='none';b.innerHTML=''}})})();</script>
@endsection
