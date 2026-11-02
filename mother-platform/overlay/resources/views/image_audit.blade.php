@extends('layout')@section('title','Product Image Audit')
@section('body')
<style>.iak{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin-bottom:10px}.iak div{background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:8px 10px}.iak small{display:block;color:#6B7280;font-size:11px}.iak b{font-size:18px}.iat{width:100%;border-collapse:collapse;font-size:13px;background:#fff}.iat th,.iat td{padding:6px 8px;border-bottom:1px solid #F1F5F9;text-align:left}.iat th{background:#F8FAFC;font-size:12px}</style>
<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px"><h1 style="margin:0;flex:1">Product Image Audit</h1><button id="iscan">Re-scan</button><button id="irep" class="g">Repair (batches)</button></div>
<div class="card" id="ist" style="margin-bottom:10px">Scan checks the file on disk, size, image type and thumbnails for every product (main image and gallery). Repair first saves the old value (backup in the log table), then restores from the old server or rebuilds thumbnails. Product name, price, stock and orders are never touched.</div>
@if($rep)
<div class="iak">
<div><small>Total products</small><b>{{ $rep['total'] }}</b></div><div><small>With images</small><b>{{ $rep['with_images'] }}</b></div><div><small>Working</small><b style="color:#16A34A">{{ $rep['working'] }}</b></div>
<div><small>Broken (file missing / corrupt / bad path)</small><b style="color:#DC2626">{{ $rep['broken'] }}</b></div><div><small>No image assigned</small><b style="color:#D97706">{{ $rep['missing'] }}</b></div><div><small>Old / external server</small><b>{{ $rep['old_server'] }}</b></div><div><small>Thumbnail missing</small><b>{{ $rep['thumb_missing'] }}</b></div><div><small>Fixed so far</small><b style="color:#16A34A">{{ $fixed }}</b></div><div><small>Repair failed</small><b>{{ $failed }}</b></div>
</div>
<div style="font-size:12px;color:#6B7280;margin-bottom:6px">Last scan: {{ $rep['at'] }}</div>
<h3>Products that still need attention ({{ count($rep['bad']) }}@if(count($rep['bad'])>=400)+@endif)</h3>
<div style="overflow:auto;max-height:420px"><table class="iat"><thead><tr><th>ID</th><th>SKU</th><th>Product</th><th>State</th><th>Path</th><th>Published</th></tr></thead><tbody>
@foreach($rep['bad'] as $b)<tr><td>{{ $b['id'] }}</td><td>{{ $b['sku'] }}</td><td>{{ \Illuminate\Support\Str::limit($b['name'], 50) }}</td><td>{{ $b['state'] }}</td><td style="word-break:break-all;max-width:320px">{{ \Illuminate\Support\Str::limit((string)$b['path'], 80) }}</td><td>{{ $b['published'] ? 'yes' : 'no' }}</td></tr>@endforeach
</tbody></table></div>
@else<div class="card">No scan yet. Press <b>Re-scan</b>.</div>@endif
<h3>Repair log</h3>
<table class="iat"><thead><tr><th>When</th><th>Product</th><th>SKU</th><th>State</th><th>Attempt</th><th>Result</th><th>Error</th></tr></thead><tbody>
@forelse($log as $l)<tr><td>{{ $l->created_at }}</td><td>#{{ $l->row_id }} {{ \Illuminate\Support\Str::limit((string)$l->name, 40) }}</td><td>{{ $l->sku }}</td><td>{{ $l->state }}</td><td>{{ $l->attempt }}</td><td>{{ $l->result }}</td><td>{{ \Illuminate\Support\Str::limit((string)$l->error, 60) }}</td></tr>@empty<tr><td colspan="7" style="color:#6B7280">Nothing repaired yet.</td></tr>@endforelse
</tbody></table>
<script>
const T=@json(csrf_token()),st=document.getElementById('ist');
async function post(u,b){const f=new URLSearchParams(b||{});f.set('_token',T);const r=await fetch(u,{method:'POST',headers:{'Accept':'application/json'},body:f});return r.json()}
document.getElementById('iscan').onclick=async()=>{st.textContent='Scanning…';try{await post('/admin/image-audit/scan');location.reload()}catch(e){st.textContent='Scan failed: '+e}};
document.getElementById('irep').onclick=async()=>{if(!confirm('Repair product images in small batches now? Old values are saved first.'))return;const skip=[];let fx=0;
 for(let i=0;i<60;i++){st.textContent='Repairing… fixed '+fx+', failed '+skip.length;const b=new URLSearchParams();skip.forEach(s=>b.append('skip[]',s));b.set('n','12');
  try{const j=await post('/admin/image-audit/repair',b);fx+=j.fixed;j.failed.forEach(x=>skip.push(x));if(!j.left)break}catch(e){st.textContent='Stopped: '+e;break}}
 st.textContent='Finished. Fixed '+fx+', could not fix '+skip.length+'. Reloading…';setTimeout(()=>location.reload(),1200)};
</script>
@endsection
