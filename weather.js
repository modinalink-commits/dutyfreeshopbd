(function(){
var API='https://dutyfreebd.com/marine-api/weather',EVERY=300000,D=null,loaded=0,failed=false,ORDER=['chattogram','mongla','payra'];
var SY={clearsky:'Clear sky',fair:'Fair',partlycloudy:'Partly cloudy',cloudy:'Cloudy',fog:'Fog',lightrain:'Light rain',rain:'Rain',heavyrain:'Heavy rain',lightrainshowers:'Light showers',rainshowers:'Showers',heavyrainshowers:'Heavy showers',lightrainandthunder:'Light rain, thunder',rainandthunder:'Rain and thunder',heavyrainandthunder:'Heavy rain, thunder',lightrainshowersandthunder:'Showers, thunder',rainshowersandthunder:'Showers, thunder',heavyrainshowersandthunder:'Heavy showers, thunder'};
var MO=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function p2(n){return(n<10?'0':'')+n}
/* Bangladesh time is a fixed UTC+6, whatever the visitor's own clock zone is */
function bd(iso){var t=typeof iso==='number'?iso:Date.parse(iso);return isNaN(t)?null:new Date(t+21600000)}
function hm(iso){var d=bd(iso);return d?p2(d.getUTCHours())+':'+p2(d.getUTCMinutes()):'-'}
function dm(iso){var d=bd(iso);return d?d.getUTCDate()+' '+MO[d.getUTCMonth()]:'-'}
function dt(iso){return iso?dm(iso)+' '+hm(iso):'-'}
function sym(s){return s?(SY[String(s).replace(/_(day|night|polartwilight)$/,'')]||''):''}
function link(u){return /^https:\/\//.test(u||'')?u:'https://www.bmd.gov.bd/web/en/p/Marine-Warning'}
function warn(p,b){
 if(p.warnings&&p.warnings.length)return p.warnings.map(function(w){return '<div class="wx-w al" role="status"><b>'+(w.signal?'Signal '+esc(w.signal)+' · ':'')+'BMD warning</b>'+esc(w.title)+'<br>Issued '+esc(dt(w.issued_at))+(w.expires_at?' · valid to '+esc(dt(w.expires_at)):'')+' · <a href="'+esc(link(w.url))+'" target="_blank" rel="noopener">Original bulletin</a></div>'}).join('');
 if(p.no_active_warning)return '<div class="wx-w ok">No active BMD warning <span style="font-weight:400">· checked '+esc(hm(b.checked_at))+'</span></div>';
 return '<div class="wx-w na">BMD warnings: Update unavailable'+(b&&b.last_ok_at?' · last checked '+esc(dt(b.last_ok_at)):'')+' · <a href="'+esc(link(b&&b.page))+'" target="_blank" rel="noopener">BMD Marine Warning</a></div>';
}
function src(label,forT,s){
 var a=label+(forT?' for '+esc(hm(forT)):'')+' · '+esc(s.name);
 if(!s.checked_at)return a+' · <span class="bad">Unavailable</span>';
 if(!s.ok)return a+' · <span class="bad">Update unavailable</span>'+(s.last_ok_at?' · last good check '+esc(dt(s.last_ok_at)):'');
 return a+' · checked '+esc(hm(s.checked_at));
}
function card(k,p,b){
 var c=p.current,w=p.wave,ws=p.weather_source,vs=p.wave_source,NA='<small>Unavailable</small>';
 return '<article class="wx-c" data-port="'+esc(k)+'"><div class="wx-t"><b>'+esc(p.name)+'</b><span class="wx-b">'+esc(p.kind||'Model estimate')+'</span></div>'+warn(p,b)+
 '<div class="wx-m"><strong>'+(c?esc(Math.round(c.temp_c))+' °C':'&ndash;')+'</strong><span>'+(c?esc(sym(c.symbol)):'Temperature unavailable')+'</span></div>'+
 '<dl class="wx-r"><dt>Wind</dt><dd>'+(c?esc(c.wind_kn)+' knots <small>from '+esc(c.wind_dir)+' ('+esc(c.wind_deg)+'°)</small>':NA)+'</dd>'+
 '<dt>Rain</dt><dd>'+(c&&c.rain_mm!=null?esc(Number(c.rain_mm).toFixed(1))+' mm <small>in the next hour</small>':NA)+'</dd>'+
 '<dt>Waves</dt><dd>'+(w?esc(Number(w.hs_m).toFixed(1))+' m <small>at the outer anchorage</small>':NA)+'</dd></dl>'+
 '<div class="wx-s">'+src('Weather',c&&c.time,ws)+(ws.data_time?' · model run '+esc(dt(ws.data_time)):'')+'<br>'+src('Waves',w&&w.time,vs)+'</div>'+
 '<button type="button" class="wx-btn" data-wx="'+esc(k)+'"'+(p.forecast&&p.forecast.length?'':' disabled')+'>View 24-hour forecast</button></article>';
}
function draw(){
 var g=document.getElementById('wx-g'),u=document.getElementById('wx-up');if(!g)return;
 if(D&&D.ports&&D.ports.chattogram){g.innerHTML=ORDER.filter(function(k){return D.ports[k]}).map(function(k){return card(k,D.ports[k],D.bmd||{})}).join('');g.setAttribute('data-r','1')}
 else if(failed){g.innerHTML=ORDER.map(function(k){return '<article class="wx-c"><div class="wx-t"><b>'+k.charAt(0).toUpperCase()+k.slice(1)+'</b></div><div class="wx-w na">Update unavailable. Port weather could not be loaded just now; this page tries again every 5 minutes.</div></article>'}).join('');g.setAttribute('data-r','1')}
 if(u){u.className='wx-up'+(failed?' bad':'');u.textContent=failed?('Update unavailable'+(loaded?' · showing data loaded at '+hm(loaded):'')):(loaded?'Page updated '+hm(loaded)+' · checks again every 5 minutes':'')}
}
function load(){
 var x=new XMLHttpRequest();x.open('GET',API+'?t='+Math.floor(Date.now()/60000));x.timeout=15000;
 x.onload=function(){try{var d=JSON.parse(x.responseText);if(x.status!==200||!d.ports||!d.ports.chattogram)throw 0;D=d;loaded=Date.now();failed=false}catch(e){failed=true}draw()};
 x.onerror=x.ontimeout=function(){failed=true;draw()};x.send();
}
function rows(p){
 var o='',last='';(p.forecast||[]).forEach(function(f){var d=dm(f.time);if(d!==last){o+='<tr class="dy"><td colspan="5">'+esc(d)+'</td></tr>';last=d}
  o+='<tr><td>'+esc(hm(f.time))+'</td><td>'+esc(Math.round(f.temp_c))+' °C</td><td>'+esc(f.wind_kn)+' knots '+esc(f.wind_dir)+'</td><td>'+(f.rain_mm!=null?esc(Number(f.rain_mm).toFixed(1))+' mm/h':'&ndash;')+'</td><td>'+(f.wave_m!=null?esc(Number(f.wave_m).toFixed(1))+' m':'')+'</td></tr>'});
 return o;
}
function open(k){
 var p=D&&D.ports&&D.ports[k];if(!p)return;var d=document.getElementById('wx-d');
 if(!d){d=document.createElement('dialog');d.id='wx-d';d.className='wx-d';document.body.appendChild(d);d.addEventListener('click',function(e){if(e.target===d||e.target.closest('.wx-x'))d.close()})}
 d.innerHTML='<div class="wx-dh"><div><h3>'+esc(p.name)+': next 24 hours</h3><p>Bangladesh time (UTC+6) · '+esc(p.kind||'Model estimate')+'<br>Weather at the terminal ('+esc(p.terminal.lat)+'N '+esc(p.terminal.lon)+'E) · waves at the outer anchorage ('+esc(p.anchorage.lat)+'N '+esc(p.anchorage.lon)+'E), every 3 hours</p></div><button type="button" class="wx-x" aria-label="Close forecast">×</button></div>'+
 '<div class="wx-db"><table class="wx-tb"><thead><tr><th>Time</th><th>Temp</th><th>Wind from</th><th>Rain</th><th>Waves</th></tr></thead><tbody>'+rows(p)+'</tbody></table></div>'+
 '<div class="wx-df">Weather: '+esc(p.weather_source.name)+', model run '+esc(dt(p.weather_source.data_time))+', checked '+esc(dt(p.weather_source.checked_at))+(p.weather_source.ok?'':' (Update unavailable)')+'. Waves: '+esc(p.wave_source.name)+', checked '+esc(dt(p.wave_source.checked_at))+(p.wave_source.ok?'':' (Update unavailable)')+'. Not for navigation.</div>';
 if(d.showModal){if(!d.open)d.showModal()}else d.setAttribute('open','')
}
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-wx]');if(b)open(b.getAttribute('data-wx'))});
/* the home page runtime can redraw its content: put the cards back when that happens */
var app=document.getElementById('app');if(app&&window.MutationObserver)new MutationObserver(function(){var g=document.getElementById('wx-g');if(g&&!g.getAttribute('data-r')&&(D||failed))draw()}).observe(app,{childList:true,subtree:true});
load();setInterval(load,EVERY);document.addEventListener('visibilitychange',function(){if(!document.hidden&&Date.now()-loaded>EVERY)load()});
window.__wx={load:load,draw:draw,get data(){return D}};
})();
