@extends('layout')@section('title','Image Optimization')
@section('body')
@php $fmt = fn($b) => $b >= 1048576 ? number_format($b/1048576, 2).' MB' : number_format($b/1024, 1).' KB'; @endphp
<h1 style="margin:0 0 4px">Image Optimization &amp; Media Library</h1>
<span style="font-size:13px;color:#6B7280">New uploads are resized, compressed and given WebP + thumbnail versions automatically. Use the button below to do the same for images uploaded earlier.</span>
@if(!$gd)<div class="card err" style="margin-top:12px">PHP GD extension is not enabled on the server yet, so optimization is paused. Uploads still work normally.</div>@endif
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin:14px 0">
<div class="card k" style="margin:0"><span style="font-size:13px">Total images</span><b style="font-size:22px">{{ $total }}</b></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Optimized</span><b style="font-size:22px" id="sOpt">{{ $opt }}</b></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Pending</span><b style="font-size:22px" id="sPen">{{ $pending }}</b></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Failed</span><b style="font-size:22px">{{ $failed }}</b></div>
<div class="card k" style="margin:0"><span style="font-size:13px">Space saved</span><b style="font-size:22px" id="sSav">{{ $fmt($saved) }}</b></div>
</div>
<div class="card" style="max-width:760px">
<b>Bulk optimize existing images</b>
<p style="font-size:12px;color:#6B7280;margin:4px 0 10px">Runs in small batches (12 images at a time), so thousands of images never load into memory together. You can leave the page and continue later.</p>
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" id="bk" style="width:auto" {{ $backup ? 'checked' : '' }}> Keep a backup of every original before optimizing</label>
<div style="margin:12px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center"><button id="go" {{ $gd ? '' : 'disabled' }}>Optimize All Images</button>@if($failed)<button id="rt" type="button" style="background:#6B7280">Retry failed ({{ $failed }})</button>@endif<span id="msg" style="font-size:13px;color:#6B7280"></span></div>
<div style="height:8px;background:#E5E7EB;border-radius:6px;overflow:hidden"><div id="bar" style="height:8px;width:0;background:#16A34A"></div></div>
@if($ext)<p style="font-size:12px;color:#6B7280;margin:10px 0 0">{{ $ext }} product image(s) are hosted on other websites and cannot be optimized here.</p>@endif
</div>
<form method="get" style="margin:14px 0"><input name="q" value="{{ $q }}" placeholder="Search file name" style="max-width:260px"> <button>Search</button></form>
<div class="card" style="overflow:auto"><table style="width:100%;border-collapse:collapse;font-size:13px">
<tr style="text-align:left;color:#6B7280"><th>Preview</th><th>File</th><th>Original</th><th>Optimized</th><th>Size px</th><th>Format</th><th>Saved</th><th>Used by</th></tr>
@forelse($items as $i)
<tr style="border-top:1px solid #E5E7EB"><td style="padding:6px"><img src="{{ $i['url'] }}" loading="lazy" style="width:56px;height:56px;object-fit:cover;border-radius:6px"></td>
<td style="word-break:break-all;max-width:180px">{{ $i['name'] }}<br><small style="color:{{ $i['status']==='optimized' ? '#16A34A' : ($i['status']==='failed' ? '#DC2626' : '#6B7280') }}">{{ ucfirst($i['status']) }}{{ $i['webp'] ? ' · WebP' : '' }}@if($i['error']) — {{ $i['error'] }}@endif</small></td>
<td>{{ $fmt($i['orig']) }}</td><td>{{ $fmt($i['now']) }}</td><td>{{ $i['w'] }}×{{ $i['h'] }}</td><td>{{ $i['fmt'] }}</td>
<td>{{ $i['orig'] > 0 && $i['status']==='optimized' ? max(0, round((1 - $i['now']/$i['orig'])*100)).'%' : '—' }}</td>
<td>{{ count($i['used']) ? implode(', ', array_slice($i['used'], 0, 3)) : '—' }}</td></tr>
@empty
<tr><td colspan="8" style="padding:14px;color:#6B7280">No uploaded images yet.</td></tr>
@endforelse
</table></div>
@if($pages > 1)<div style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap">@for($p=1;$p<=$pages;$p++)<a href="?q={{ urlencode($q) }}&page={{ $p }}" style="padding:4px 10px;border:1px solid #E5E7EB;border-radius:6px;{{ $p===$page ? 'background:#111;color:#fff' : '' }}">{{ $p }}</a>@endfor</div>@endif
<script>window.MT=@json(csrf_token());</script>
<script>
@verbatim
(function(){
  var T=window.MT||'';
  function post(u,b){return fetch(u,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':T},body:JSON.stringify(b||{})}).then(function(r){return r.json()});}
  var go=document.getElementById('go'),msg=document.getElementById('msg'),bar=document.getElementById('bar'),bk=document.getElementById('bk'),rt=document.getElementById('rt');
  bk.onchange=function(){post('/admin/media/settings',{backup:bk.checked?1:0});};
  if(rt)rt.onclick=function(){post('/admin/media/retry').then(function(){location.reload();});};
  var done=0,total=0,saved=0;
  function step(){
    post('/admin/media/optimize').then(function(d){
      if(!d.ok){msg.textContent=d.error||'Error';go.disabled=false;return;}
      done+=d.processed;saved+=d.saved;if(!total)total=d.processed+d.remaining;
      bar.style.width=(total?Math.min(100,done/total*100):100)+'%';
      msg.textContent=done+' done, '+d.remaining+' left';
      if(d.remaining>0&&d.processed>0)step();else{msg.textContent='Finished: '+done+' image(s) processed.';setTimeout(function(){location.reload();},1200);}
    }).catch(function(){msg.textContent='Network error — press the button again to continue.';go.disabled=false;});
  }
  go.onclick=function(){go.disabled=true;done=0;total=0;saved=0;msg.textContent='Working…';step();};
})();
@endverbatim
</script>
@endsection
