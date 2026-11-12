@extends('layout')@section('title','Sunglass Try-On')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Sunglass Virtual Try-On</h1><span style="font-size:13px;color:#6B7280">Each sunglass needs a transparent front-view overlay. The customer's own photo is never edited: photo + overlay = preview. Selfies are saved only after the customer's separate consent.</span></div>
<button class="btn" id="gen-all" type="button">Generate all missing</button></div>
@if(session('ok'))<div style="background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;padding:8px 12px;border-radius:8px;margin-bottom:10px;font-size:13px">{{ session('ok') }}</div>@endif
<div style="overflow:auto;border:1px solid #E5E7EB;border-radius:10px;margin-bottom:18px"><table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr style="background:#F9FAFB;text-align:left"><th style="padding:8px">Product</th><th>Photo</th><th>Overlay</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($prods as $p)@php $a = $assets->get($p->id); $st = $a->status ?? 'none'; @endphp
<tr data-id="{{ $p->id }}" data-st="{{ $st }}" style="border-top:1px solid #F1F5F9"><td style="padding:6px 8px;max-width:340px">{{ $p->name }}</td>
<td><img src="{{ $p->image }}" width="44" height="44" style="object-fit:contain;border-radius:6px" loading="lazy" alt=""></td>
<td class="ov">@if($a && $a->png)<img src="{{ $a->png }}" height="40" style="background:repeating-conic-gradient(#e5e7eb 0 25%,#fff 0 50%) 0 0/10px 10px;border-radius:6px" alt="">@endif</td>
<td class="s">{{ $st }}@if($a && $a->error)<br><span style="color:#B91C1C;font-size:11px">{{ \Illuminate\Support\Str::limit($a->error, 90) }}</span>@endif</td>
<td><button type="button" class="btn g1" style="padding:4px 10px">{{ $st === 'ready' ? 'Redo' : 'Generate' }}</button></td></tr>
@empty<tr><td colspan="5" style="padding:14px;color:#6B7280">No Sunglass products found.</td></tr>@endforelse</tbody></table></div>
<h2 style="font-size:16px;margin:0 0 6px">Customer photos (saved with consent)</h2>
<form method="post" action="/admin/tryon/settings" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;font-size:13px">@csrf Keep photos for <input type="number" name="days" min="1" max="365" value="{{ $days }}" style="width:70px"> days, then delete automatically <button class="btn" style="padding:4px 10px">Save</button></form>
<div style="overflow:auto;border:1px solid #E5E7EB;border-radius:10px"><table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr style="background:#F9FAFB;text-align:left"><th style="padding:8px">#</th><th>Saved</th><th>Consent</th><th>Expires</th><th>Size</th><th></th></tr></thead><tbody>
@forelse($photos as $ph)<tr style="border-top:1px solid #F1F5F9"><td style="padding:6px 8px">{{ $ph->id }}</td><td>{{ \Carbon\Carbon::parse($ph->created_at)->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td><td>{{ $ph->consent_version }} · {{ $ph->consent_at ? \Carbon\Carbon::parse($ph->consent_at)->timezone('Asia/Dhaka')->format('d M H:i') : '-' }}</td><td>{{ $ph->expires_at ? \Carbon\Carbon::parse($ph->expires_at)->timezone('Asia/Dhaka')->format('d M Y') : '-' }}</td><td>{{ round($ph->bytes/1024) }} KB</td>
<td style="white-space:nowrap"><a class="btn" style="padding:4px 10px" target="_blank" href="/admin/tryon/photo/{{ $ph->id }}">View</a> <form method="post" action="/admin/tryon/photo/{{ $ph->id }}/delete" style="display:inline" onsubmit="return confirm('Delete this photo permanently?')">@csrf<button class="btn" style="padding:4px 10px">Delete</button></form></td></tr>
@empty<tr><td colspan="6" style="padding:14px;color:#6B7280">No saved photos. Photos are saved only when a customer gives consent.</td></tr>@endforelse</tbody></table></div>
<p style="font-size:12px;color:#6B7280;margin-top:8px">Photos are never sold, shared, used for ads or AI training. Opening or deleting a photo is written to the audit log.</p>
<script>
(function(){var tok=document.querySelector('meta[name=csrf-token]');tok=tok?tok.content:'{{ csrf_token() }}';
function gen(tr){var id=tr.dataset.id;tr.querySelector('.s').textContent='processing…';return fetch('/admin/tryon/generate',{method:'POST',headers:{'X-CSRF-TOKEN':tok,'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'},body:'id='+id}).then(function(r){return r.json()}).then(function(j){if(j.ok){tr.dataset.st='ready';tr.querySelector('.s').textContent='ready';tr.querySelector('.ov').innerHTML='<img src="'+j.png+'?v='+Date.now()+'" height="40" alt="">'}else{tr.dataset.st='failed';tr.querySelector('.s').textContent='failed: '+(j.error||'error')}}).catch(function(){tr.querySelector('.s').textContent='network error'})}
document.querySelectorAll('.g1').forEach(function(b){b.onclick=function(){b.disabled=true;gen(b.closest('tr')).then(function(){b.disabled=false})}});
document.getElementById('gen-all').onclick=async function(){var b=this;b.disabled=true;var rows=[].slice.call(document.querySelectorAll('tr[data-id]')).filter(function(r){return r.dataset.st!=='ready'});for(var i=0;i<rows.length;i++){b.textContent='Generating '+(i+1)+' / '+rows.length;await gen(rows[i])}b.textContent='Done';b.disabled=false}})();
</script>
@endsection
