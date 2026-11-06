@extends('layout')
@section('title', 'Distribution map')
@section('content')
@include('map._leaflet')
@php($U = \App\Support\Ui::class)
@php($canEdit = in_array(auth()->user()->role, ['super_admin', 'admin', 'manager'], true))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Distribution map</h1><span class="sub">{{ \App\Models\Setting::get('area', 'Jorarganj') }}, {{ \App\Models\Setting::get('upazila', 'Mirsarai') }}, {{ \App\Models\Setting::get('division', 'Chattogram') }} · {{ $zones->whereNotNull('zone')->count() }} zones · {{ now()->timezone('Asia/Dhaka')->format('d-m-Y') }}</span></div>
  <input id="q" list="cl" placeholder="Search place, shop or customer ID" style="width:260px"><datalist id="cl">@foreach($customers as $c)<option value="{{ $c['code'] }} · {{ $c['name'] }}">@endforeach</datalist>
  <a class="btn" href="{{ route('exports.index', ['report' => 'route_vehicle']) }}">Export area report</a>
  @if($canEdit)<select id="zr" style="width:auto"><option value="">✎ Draw zone for…</option>@foreach($routes as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>@endif
</header>
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section class="card" style="flex:999 1 560px;min-width:0;overflow:hidden;position:relative">
    <div style="position:absolute;z-index:500;top:10px;left:56px;display:flex;flex-wrap:wrap;gap:6px">@foreach(['zones' => 'Zones', 'customers' => 'Customers', 'vehicles' => 'Vehicles', 'route' => 'Routes'] as $k => $l)<button type="button" class="btn sm ly" data-l="{{ $k }}" style="background:#123a86;color:#fff;border-color:#123a86">{{ $l }}</button>@endforeach
      <button type="button" class="btn sm" id="bm">Map</button><button type="button" class="btn sm" id="bs">Satellite</button></div>
    <div id="map" style="height:560px"></div>
    <div id="drawBar" style="display:none;position:absolute;z-index:500;bottom:10px;left:10px;right:10px;background:#fff;border-radius:12px;padding:10px;box-shadow:0 2px 8px rgba(0,0,0,.2);gap:8px;align-items:center"><span style="flex-grow:1;font-size:var(--fs-13)">Click the map to add corners of the zone.</span><button class="btn sm" type="button" id="dUndo">Undo</button><button class="btn sm" type="button" id="dClear">Clear zone</button><button class="btn sm pri" type="button" id="dSave">Save zone</button></div>
    <div style="display:flex;flex-wrap:wrap;gap:14px;padding:10px 14px;font-size:var(--fs-12);border-top:1px solid #dbe3f0"><span><span class="mk" style="display:inline-flex;width:12px;height:12px;background:#5b2fa8"></span> Dealer</span><span><span class="mk" style="display:inline-flex;width:12px;height:12px;background:#1d4ed8"></span> Shop / hotel</span><span><span class="mk" style="display:inline-flex;width:12px;height:12px;background:#c46f00"></span> Not visited today</span><span style="color:#0f6b3c">━ Vehicle route today</span></div>
  </section>
  <aside class="card" id="side" style="flex:1 1 300px;min-width:280px;padding:18px;display:flex;flex-direction:column;gap:4px"><p class="sub" style="margin:0">Click a zone, shop or vehicle. Shops appear once their location is known (from the salesman app at the first sale, or set here).</p>
    @php($noLoc = \App\Models\Customer::query()->where('is_active', true)->whereNull('lat')->count())
    @if($noLoc && $canEdit)<p class="sub" style="margin:8px 0 0">{{ $noLoc }} shops have no location yet. Search one below, then click the map to place it.</p>
      <select id="place" style="margin-top:6px"><option value="">Place a shop on the map…</option>@foreach(\App\Models\Customer::query()->where('is_active', true)->whereNull('lat')->orderBy('name')->limit(300)->get() as $c)<option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>@endforeach</select>@endif
  </aside>
</div>
<script>
(function(){
  const Z=@json($zones), C=@json($customers), V=@json($veh), tk=n=>'৳'+Math.round(n).toLocaleString('en-IN'), esc=s=>String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const map=L.map('map').setView([22.77,91.56],12);
  const osm=L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);
  const sat=L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{maxZoom:19,attribution:'Imagery &copy; Esri'});
  document.getElementById('bm').onclick=()=>{map.removeLayer(sat);osm.addTo(map);}; document.getElementById('bs').onclick=()=>{map.removeLayer(osm);sat.addTo(map);};
  const L_={zones:L.layerGroup().addTo(map),customers:L.layerGroup().addTo(map),vehicles:L.layerGroup().addTo(map),route:L.layerGroup().addTo(map)};
  const side=h=>document.getElementById('side').innerHTML=h, kv=(k,v)=>'<div class="kv"><span>'+k+'</span><b>'+esc(v)+'</b></div>', all=[];
  Z.forEach(z=>{ if(!z.zone) return; z.zone.forEach(p=>all.push(p)); L.polygon(z.zone,{color:'#123a86',weight:2,fillOpacity:.08}).addTo(L_.zones).bindTooltip(z.name).on('click',()=>side('<span class="sub" style="margin:0;font-size:var(--fs-10);font-weight:700;letter-spacing:.08em">ZONE · Active</span><b style="font-size:var(--fs-16)">'+esc(z.name)+'</b>'+kv('Markets',z.markets)+kv('Sales today',tk(z.sales))+kv('Cylinders',z.cyl)+kv('Collection',tk(z.coll))+kv('Shops (in zone / listed)',z.shops)+kv('Not visited today',z.unv)+kv('Salesman',z.sm)+kv('Vehicle',z.veh)+'<a class="btn sm" style="margin-top:8px" href="/vehicles">Delivery route</a>')); });
  C.forEach(c=>{ all.push([c.lat,c.lng]); const col=!c.visited?'#c46f00':(c.type==='dealer'?'#5b2fa8':'#1d4ed8');
    const cm=L.circleMarker([c.lat,c.lng],{radius:7,color:'#fff',weight:2,fillColor:col,fillOpacity:1}).addTo(L_.customers).bindTooltip(c.name); if(String(c.id)===new URLSearchParams(location.search).get('c')) setTimeout(()=>{ map.setView([c.lat,c.lng],17); cm.fire('click'); cm.openTooltip(); },300); cm.on('click',()=>side('<span class="sub" style="margin:0;font-size:var(--fs-10);font-weight:700;letter-spacing:.08em">CUSTOMER</span><b style="font-size:var(--fs-16)">'+esc(c.name)+'</b>'+kv('Customer ID',c.code)+kv('Mobile',c.mob)+kv('Category',c.cat)+kv('Last purchase',c.last||'—')+kv('Total purchases ('+new Date().getFullYear()+')',tk(c.total))+(c.due!==null?kv('Outstanding · Super Admin',tk(c.due)):'')+'<a class="btn sm" style="margin-top:8px" href="/customers?q='+encodeURIComponent(c.code)+'">Open profile</a>')); });
  V.forEach(v=>{ if(v.trail.length>1) L.polyline(v.trail,{color:'#0f6b3c',weight:3}).addTo(L_.route); if(!v.lat) return; all.push([v.lat,v.lng]);
    L.marker([v.lat,v.lng],{icon:L.divIcon({className:'',html:'<span class="mk" style="width:38px;height:24px;border-radius:8px;background:#0f6b3c">'+esc(v.code)+'</span>',iconSize:[38,24],iconAnchor:[19,12]})}).addTo(L_.vehicles)
     .on('click',()=>side('<span class="sub" style="margin:0;font-size:var(--fs-10);font-weight:700;letter-spacing:.08em">VEHICLE</span><b style="font-size:var(--fs-16)">'+esc(v.code)+' · '+esc(v.reg||'')+'</b>'+kv('Route',v.route)+kv('Driver · salesman',v.crew)+kv('Last known location',(v.at||'—')+' · from salesman app')+kv('Stops done',v.stops)+'<span class="sub" style="margin:8px 0 0;font-size:var(--fs-12)">Location comes from the salesman\'s phone while on duty (every 15–30 s), with permission. Full view in <a href="/live-tracking">Live tracking</a>.</span>')); });
  if(all.length) map.fitBounds(all,{padding:[40,40],maxZoom:14});
  document.querySelectorAll('.ly').forEach(b=>b.onclick=()=>{ const g=L_[b.dataset.l], on=map.hasLayer(g); on?map.removeLayer(g):g.addTo(map); b.style.background=on?'#fff':'#123a86'; b.style.color=on?'#14213d':'#fff'; });
  document.getElementById('q').onchange=e=>{ const code=e.target.value.split(' · ')[0]; const c=C.find(x=>x.code===code||x.name.toLowerCase()===e.target.value.toLowerCase()); if(c){ map.setView([c.lat,c.lng],16); } };
  const tok=document.querySelector('meta[name=csrf-token]')?.content||@json(csrf_token());
  const post=(u,b)=>fetch(u,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':tok},body:JSON.stringify(b)});
  let draw=null, pts=[], tmp=null;
  const zr=document.getElementById('zr'); if(zr) zr.onchange=()=>{ draw=zr.value||null; pts=[]; if(tmp) map.removeLayer(tmp); document.getElementById('drawBar').style.display=draw?'flex':'none'; };
  const redraw=()=>{ if(tmp) map.removeLayer(tmp); tmp=L.polygon(pts,{color:'#b45309',dashArray:'4'}).addTo(map); };
  map.on('click',async e=>{ const pl=document.getElementById('place');
    if(pl&&pl.value){ if(confirm('Place this shop here?')){ await post('/map/customers/'+pl.value+'/place',{lat:e.latlng.lat,lng:e.latlng.lng}); location.reload(); } return; }
    if(draw){ pts.push([e.latlng.lat,e.latlng.lng]); redraw(); } });
  if(zr){ document.getElementById('dUndo').onclick=()=>{pts.pop();redraw();}; document.getElementById('dClear').onclick=async()=>{ if(confirm('Remove this zone?')){ await post('/map/routes/'+draw+'/zone',{zone:[]}); location.reload(); } };
    document.getElementById('dSave').onclick=async()=>{ if(pts.length<3){alert('Add at least 3 corners.');return;} await post('/map/routes/'+draw+'/zone',{zone:pts}); location.reload(); }; }
})();
</script>
@endsection
