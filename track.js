(function(){
var h=location.hash.slice(1),imo=/^[0-9]{7}$/.test(h)?h:'',K='mv',bar=document.getElementById('tact'),sv=document.getElementById('tsaved'),nameI=document.getElementById('tname');
function get(){try{var a=JSON.parse(localStorage.getItem(K)||'[]');return Array.isArray(a)?a:[]}catch(e){return[]}}
function put(a){try{localStorage.setItem(K,JSON.stringify(a.slice(0,20)))}catch(e){}}
function el(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e}
function draw(){var a=get();sv.textContent='';if(!a.length){sv.hidden=true;return}sv.hidden=false;sv.appendChild(el('b',null,'My vessels'));a.forEach(function(v){var c=el('span','sv'),l=el('a',null,(v.n?v.n+' · ':'')+'IMO '+v.i);l.href='/track.html#'+v.i;c.appendChild(l);var x=el('button',null,'×');x.type='button';x.setAttribute('aria-label','Remove IMO '+v.i);x.addEventListener('click',function(){put(get().filter(function(o){return o.i!==v.i}));draw();mark()});c.appendChild(x);sv.appendChild(c)})}
var sb=document.getElementById('tsave');function saved(){return get().filter(function(o){return o.i===imo})[0]}
function mark(){if(!imo)return;var s=saved();sb.className=s?'on':'';sb.textContent=s?'Saved ✓':'Save vessel'}
draw();if(!imo){bar.hidden=true;return}
var nm='';try{var t=JSON.parse(sessionStorage.getItem('tn')||'null');if(t&&t.i===imo)nm=t.n||''}catch(e){}var s0=saved();if(!nm&&s0)nm=s0.n||'';if(nm)nameI.value=nm;
var url='https://marinedutyfreebd.com/track.html#'+imo;
function label(){var n=nameI.value.trim().slice(0,40);document.getElementById('tlab').textContent=(n?n+' · ':'')+'IMO '+imo;document.getElementById('twa').href='https://wa.me/?text='+encodeURIComponent('Live position of '+(n||'vessel')+' (IMO '+imo+') on DutyFree BD Marine Supply: '+url);
var d=new Date();document.getElementById('pinfo').textContent='Vessel tracking report · '+(n?n+' · ':'')+'IMO '+imo+' · generated '+d.toISOString().slice(0,16).replace('T',' ')+' UTC · '+url+' · DutyFree BD Marine Supply (map and AIS data: VesselFinder)'}
label();mark();nameI.addEventListener('input',function(){label();if(saved()){put(get().map(function(o){return o.i===imo?{i:imo,n:nameI.value.trim().slice(0,40)}:o}));draw()}});
sb.addEventListener('click',function(){if(saved())put(get().filter(function(o){return o.i!==imo}));else put([{i:imo,n:nameI.value.trim().slice(0,40)}].concat(get()));draw();mark()});
var cb=document.getElementById('tcopy');cb.addEventListener('click',function(){function ok(){cb.textContent='Link copied';setTimeout(function(){cb.textContent='Copy link'},2000)}if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(url).then(ok,function(){window.getSelection().selectAllChildren(document.getElementById('tlab'))});});
document.getElementById('tpdf').addEventListener('click',function(){label();window.print()});
})();