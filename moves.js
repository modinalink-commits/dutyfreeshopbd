(function(){
var API='https://dutyfreebd.com/marine-api/movements',PORTAL='https://dutyfreebd.com/marine-portal/orders/new';
var MO=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
var S={type:'arrivals',port:'',from:'',to:'',q:'',page:1,pages:1,busy:false,seq:0};
var DEMO=/[?&]demo=1/.test(location.search);
function $(i){return document.getElementById(i)}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function p2(n){return(n<10?'0':'')+n}
/* Bangladesh time is a fixed UTC+6 */
function bd(iso){var t=Date.parse(iso);if(isNaN(t))return'-';var d=new Date(t+21600000);return d.getUTCDate()+' '+MO[d.getUTCMonth()]+' '+d.getUTCFullYear()+', '+p2(d.getUTCHours())+':'+p2(d.getUTCMinutes())}
function img(u){return /^https:\/\//.test(u||'')||/^\/[a-z0-9._-]+\.(jpg|png|webp)$/i.test(u||'')}
function card(x){
 var dep=x.event==='departure',ph=x.photo&&img(x.photo.url);
 var pic=ph?'<div class="mvp"><img src="'+esc(x.photo.url)+'" alt="'+esc(x.vessel)+'" loading="lazy" width="640" height="400"><span class="cr">Photo: '+esc(x.photo.credit)+'</span></div>'
  :'<div class="mvp none" role="img" aria-label="No verified photo of '+esc(x.vessel)+'"><b>No verified photo</b><span>Placeholder, not the vessel</span></div>';
 var req=(!dep&&x.in_port)?'<a class="b1" href="'+PORTAL+'?imo='+encodeURIComponent(x.imo)+'&amp;vessel='+encodeURIComponent(x.vessel)+'&amp;port='+encodeURIComponent(x.port)+'">Request Supply</a>':'';
 return '<article class="mvc">'+pic+'<div class="mvb"><h2>'+esc(x.vessel)+'</h2><div class="id">IMO '+esc(x.imo)+(x.vessel_type?' · '+esc(x.vessel_type):'')+'</div>'+
 '<dl><dt>Port</dt><dd>'+esc(x.port_name)+'</dd>'+(x.location?'<dt>Location</dt><dd>'+esc(x.location)+'</dd>':'')+'</dl>'+
 '<div class="tm'+(dep?' dep':'')+'"><b>'+(dep?'Actual departure':'Actual arrival')+'</b><span>'+esc(bd(x.time))+'</span> <small>Bangladesh time</small></div>'+
 '<div class="mva"><a class="b2" href="/track#'+encodeURIComponent(x.imo)+'">View on Map</a>'+req+'</div>'+(req?'<div class="mvn">Request Supply opens the requisition form for verified accounts, with this vessel and port filled in.</div>':'')+'</div></article>';
}
function demo(){var n=Date.now(),h=3600000,A=[
 {id:1,vessel:'OCEAN PRIDE',imo:'9321483',vessel_type:'Container ship',port:'chattogram',port_name:'Chattogram',location:'CCT berth 2',arr:n-2*h,dep:null,photo:{url:'/port-chattogram.jpg',credit:'Sample photo'}},
 {id:2,vessel:'OCEAN PRIDE',imo:'9456712',vessel_type:'Bulk carrier',port:'mongla',port_name:'Mongla',location:'Harbaria anchorage',arr:n-5*h,dep:null,photo:null},
 {id:3,vessel:'BAY TRADER',imo:'9288451',vessel_type:'General cargo',port:'payra',port_name:'Payra',location:'Terminal 1',arr:n-20*h,dep:n-3*h,photo:{url:'/port-payra.jpg',credit:'Sample photo'}},
 {id:4,vessel:'MEGHNA STAR',imo:'9612087',vessel_type:'Tanker',port:'chattogram',port_name:'Chattogram',location:null,arr:n-30*h,dep:n-9*h,photo:null}];
 var t=S.type==='departures',o=A.filter(function(v){var e=t?v.dep:v.arr;if(!e)return false;if(S.port&&v.port!==S.port)return false;if(S.q){var q=S.q.toLowerCase();if(v.vessel.toLowerCase().indexOf(q)<0&&v.imo.indexOf(q)!==0)return false}
  if(!S.from&&!S.to)return e>=n-24*h;var a=Date.parse((S.from||S.to)+'T00:00:00+06:00'),b=Date.parse((S.to||S.from)+'T23:59:59+06:00');return e>=a&&e<=b})
 .map(function(v){var e=t?v.dep:v.arr;return {id:v.id,event:t?'departure':'arrival',vessel:v.vessel,imo:v.imo,vessel_type:v.vessel_type,port:v.port,port_name:v.port_name,location:v.location,time:new Date(e).toISOString(),in_port:!v.dep,photo:v.photo}})
 .sort(function(a,b){return Date.parse(b.time)-Date.parse(a.time)});
 return {items:o,total:o.length,page:1,pages:1,feed:null}}
function status(d,err){var s=$('mv-s');if(err){s.innerHTML='<span class="late">The list could not be loaded just now. Please try again.</span>';return}
 var rng=(S.from||S.to)?('From '+esc(S.from||S.to)+' to '+esc(S.to||S.from)):'Latest 24 hours';
 var f=d.feed?('<span'+(d.feed.delayed?' class="late"':'')+'>'+(d.feed.delayed?'Feed update delayed · ':'')+'Last feed update '+(d.feed.last_ok?esc(bd(d.feed.last_ok)):'not yet received')+'</span>'):'<span>Entered by our marine desk from confirmed port and agent records</span>';
 s.innerHTML='<span><b>'+d.total+'</b> '+(S.type==='departures'?'departure':'arrival')+(d.total===1?'':'s')+' · '+rng+' · newest first · Bangladesh time (UTC+6)</span>'+f}
function show(d,append){var g=$('mv-g');var h=d.items.map(card).join('');if(append)g.insertAdjacentHTML('beforeend',h);else g.innerHTML=h||'<div class="mve card" style="grid-column:1/-1">No confirmed '+(S.type==='departures'?'departure':'arrival')+' in this period'+(S.port||S.q?' for this filter':'')+'.<br>Only events confirmed by the port record or the local agent are listed. Expected times are not shown here.</div>';
 S.pages=d.pages||1;$('mv-more').hidden=!(S.page<S.pages);status(d)}
function load(append){var id=++S.seq;if(!append){S.page=1;$('mv-g').innerHTML='<div class="sk"></div><div class="sk"></div><div class="sk"></div>';$('mv-more').hidden=true}
 if(DEMO){setTimeout(function(){if(id===S.seq)show(demo(),false)},120);return}
 var u=API+'?type='+S.type+'&page='+S.page+(S.port?'&port='+encodeURIComponent(S.port):'')+(S.from?'&from='+encodeURIComponent(S.from):'')+(S.to?'&to='+encodeURIComponent(S.to):'')+(S.q?'&q='+encodeURIComponent(S.q):'');
 var x=new XMLHttpRequest();x.open('GET',u);x.timeout=15000;x.onload=function(){if(id!==S.seq)return;try{if(x.status!==200)throw 0;show(JSON.parse(x.responseText),append)}catch(e){fail()}};x.onerror=x.ontimeout=function(){if(id===S.seq)fail()};x.send();
 function fail(){if(!append)$('mv-g').innerHTML='<div class="mve card" style="grid-column:1/-1">The list could not be loaded just now. Please try again in a minute.</div>';status(null,true)}}
function tab(t){S.type=t;[].forEach.call(document.querySelectorAll('.mvt button'),function(b){var on=b.getAttribute('data-t')===t;b.setAttribute('aria-selected',on?'true':'false');b.tabIndex=on?0:-1});$('mv-g').setAttribute('aria-labelledby','mv-t-'+t);try{history.replaceState(null,'',location.pathname+location.search+'#'+t)}catch(e){}load()}
document.querySelector('.mvt').addEventListener('click',function(e){var b=e.target.closest('button');if(b)tab(b.getAttribute('data-t'))});
document.querySelector('.mvt').addEventListener('keydown',function(e){if(e.key==='ArrowRight'||e.key==='ArrowLeft'){var t=S.type==='arrivals'?'departures':'arrivals';tab(t);$('mv-t-'+t).focus()}});
$('mv-f').addEventListener('submit',function(e){e.preventDefault();S.port=$('mv-port').value;S.from=$('mv-from').value;S.to=$('mv-to').value;S.q=$('mv-q').value.trim().slice(0,40);load()});
$('mv-reset').addEventListener('click',function(){$('mv-f').reset();S.port=S.from=S.to=S.q='';load()});
$('mv-more').addEventListener('click',function(){S.page++;load(true)});
if(DEMO)$('mv-demo').hidden=false;
tab(location.hash==='#departures'?'departures':'arrivals');
window.__moves={state:S,bd:bd};
})();
