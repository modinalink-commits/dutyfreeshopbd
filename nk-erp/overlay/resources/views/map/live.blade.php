@extends('layout')
@section('title', 'Live tracking')
@section('content')
@include('map._leaflet')
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Live tracking</h1><span class="sub">Distribution → salesman phones on duty · <span id="at">{{ now()->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}</span></span></div>
  <span class="pill" id="conn" style="background:#e3f6ec;color:#0f6b3c">Live · connected</span>
  <input id="q" placeholder="Search salesman" style="width:200px">
  <select id="rt" style="width:auto"><option value="">All routes</option>@foreach($routes as $r)<option>{{ $r->name }}</option>@endforeach</select>
</header>
<div id="banner"></div>
<section id="counts" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px"></section>
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section class="card" style="flex:999 1 560px;min-width:0;overflow:hidden"><div id="map" style="height:520px"></div></section>
  <aside class="card" id="side" style="flex:1 1 300px;min-width:280px;padding:18px;display:flex;flex-direction:column;gap:4px"><p class="sub" style="margin:0">Pick a salesman on the map or in the list.</p></aside>
</div>
<section class="card" style="padding:18px 20px;display:flex;flex-direction:column;gap:8px" id="histBox"><h2 style="margin:0;font-size:var(--fs-15)" id="histT">Route history</h2><div id="hist" class="sub" style="margin:0">Pick a salesman to see today's route.</div></section>
<p class="sub" style="margin:0;font-size:var(--fs-12)">Location comes from the salesman's phone only while on duty, with permission and a visible tracking notice. Vehicles have no GPS hardware, so this is phone tracking, not vehicle tracking. Every view of this page is written to the location audit log.</p>
<script>
(function(){
  const ST={active:['GPS active','#e3f6ec','#0f6b3c','#1f9d55'],stale:['Location stale','#fff1d6','#8a4b00','#c46f00'],offline:['Offline','#fde9e7','#b3261e','#d93a2f'],waiting:['Waiting for GPS','#e6eeff','#1d4ed8','#1d4ed8'],ended:['Duty ended','#eef2f9','#51607a','#7b8aa8']};
  const map=L.map('map').setView([22.77,91.56],12); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);
  const marks=L.layerGroup().addTo(map), trailL=L.layerGroup().addTo(map); let sel=null, data=[], fitted=false;
  const esc=s=>String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])), ini=n=>n.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
  function side(p){ const c=ST[p.st]; document.getElementById('side').innerHTML='<div style="display:flex;gap:10px;align-items:center"><span class="mk" style="width:40px;height:40px;background:'+c[3]+'">'+esc(ini(p.name))+'</span><div style="flex-grow:1"><b>'+esc(p.name)+'</b><br><span class="sub" style="font-size:var(--fs-11)">'+esc(p.emp)+'</span></div><span class="pill" style="background:'+c[1]+';color:'+c[2]+'">'+c[0]+'</span></div>'+
    [['Phone · authorized',p.phone],['Vehicle · driver',p.veh],['Area · route',p.route],['Duty started',p.start],['Last GPS update',p.last],['Location',p.lat?p.lat.toFixed(5)+', '+p.lng.toFixed(5):'—'],['GPS accuracy',p.acc]].map(([k,v])=>'<div class="kv"><span>'+k+'</span><b>'+esc(v)+'</b></div>').join('')+
    '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">'+(p.lat?'<a class="btn sm" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination='+p.lat+','+p.lng+'">Open directions</a>':'')+(p.trip?'<a class="btn sm" href="/trips/'+p.trip+'/sales">Today\'s sales</a>':'')+'<a class="btn sm" href="/hr/employees/'+p.id+'">Profile</a></div>'; }
  async function load(){
    try{ const r=await fetch(@json(route('map.live.json'))+(sel?'?e='+sel:''),{headers:{Accept:'application/json'}}); const j=await r.json(); data=j.people; document.getElementById('at').textContent=j.at; document.getElementById('conn').textContent='Live · connected';
      const f=document.getElementById('q').value.toLowerCase(), rt=document.getElementById('rt').value, list=data.filter(p=>(!f||p.name.toLowerCase().includes(f))&&(!rt||p.route===rt));
      const cnt=k=>data.filter(p=>p.st===k).length, onDuty=data.filter(p=>p.st!=='ended').length;
      document.getElementById('counts').innerHTML=[['On duty',onDuty+' of '+data.length,null],['GPS active',cnt('active'),'active'],['Waiting for GPS',cnt('waiting'),'waiting'],['Location stale',cnt('stale'),'stale'],['Offline',cnt('offline'),'offline'],['Duty ended',cnt('ended'),null]].map(([l,v,k])=>'<div style="background:'+(k?ST[k][1]:'#fff')+';border:1px solid #dbe3f0;border-radius:12px;padding:10px 12px;display:flex;flex-direction:column"><span style="font-size:var(--fs-11);font-weight:600;color:#51607a">'+l+'</span><b style="font-size:var(--fs-20);color:'+(k?ST[k][2]:'#14213d')+'">'+v+'</b></div>').join('');
      marks.clearLayers(); const pts=[];
      list.forEach(p=>{ if(!p.lat) return; const on=p.id===sel, s=on?38:30; pts.push([p.lat,p.lng]);
        L.marker([p.lat,p.lng],{icon:L.divIcon({className:'',html:'<span class="mk" style="width:'+s+'px;height:'+s+'px;background:'+ST[p.st][3]+'">'+esc(ini(p.name))+'</span>',iconSize:[s,s],iconAnchor:[s/2,s/2]})}).addTo(marks).on('click',()=>{sel=p.id;load();}); });
      if(!fitted&&pts.length){ map.fitBounds(pts,{padding:[40,40],maxZoom:14}); fitted=true; }
      const newest=data.filter(p=>p.st==='active').sort((a,b)=>b.start.localeCompare(a.start))[0];
      if(newest&&!sel) document.getElementById('banner').innerHTML='<div class="flash" style="display:flex;align-items:center;gap:10px"><span style="flex-grow:1">'+esc(newest.name)+' has started duty and GPS tracking is active. Started '+esc(newest.start)+' · '+esc(newest.veh)+'.</span><button class="btn sm" type="button" id="openN">Open on map</button></div>', document.getElementById('openN').onclick=()=>{sel=newest.id;load();};
      const p=data.find(x=>x.id===sel); if(p){ side(p); }
      trailL.clearLayers();
      if(j.hist&&p){ if(j.trail.length>1) L.polyline(j.trail,{color:'#1d4ed8',weight:4,opacity:.8}).addTo(trailL);
        j.hist.stops.forEach(s=>{ if(s.lat) L.circleMarker([s.lat,s.lng],{radius:6,color:'#0f6b3c',fillOpacity:.9}).bindTooltip(s.t+' '+s.n).addTo(trailL); });
        document.getElementById('histT').textContent='Route history — '+p.name+' · today';
        document.getElementById('hist').innerHTML='<div style="display:flex;flex-wrap:wrap;gap:18px;color:#14213d"><span>Duty <b>'+esc(p.start)+'</b> → '+(p.st==='ended'?'ended':'on duty')+'</span><span>Distance <b>'+j.hist.km+' km</b></span><span>GPS points kept <b>'+j.hist.kept+'</b> · '+j.hist.dropped+' noisy dropped</span><span>Offline gaps <b>'+j.hist.gaps.length+'</b>'+(j.hist.gaps.length?' · '+esc(j.hist.gaps.join(', ')):'')+'</span></div>'+
          '<div style="display:flex;flex-direction:column;gap:2px;margin-top:6px">'+j.hist.stops.map(s=>'<span style="color:#14213d"><b class="n">'+esc(s.t)+'</b> '+esc(s.n)+(s.lat?'':' <span style="color:#51607a">(no shop location yet)</span>')+'</span>').join('')+(j.hist.stops.length?'':'No sales yet.')+'</div>'; }
    }catch(e){ document.getElementById('conn').textContent='Reconnecting…'; }
  }
  document.getElementById('q').oninput=load; document.getElementById('rt').onchange=load; load(); setInterval(load,20000);
})();
</script>
@endsection
