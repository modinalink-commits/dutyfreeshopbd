(function(){
var API='https://dutyfreebd.com/marine-api/',h=location.hash.slice(1),id=/^([0-9]{7}|[0-9]{9})$/.test(h)?h:'',K='mv',bar=document.getElementById('tact'),sv=document.getElementById('tsaved'),qi=document.getElementById('timo'),sg=document.getElementById('tsug'),nm='',t;
function get(){try{var a=JSON.parse(localStorage.getItem(K)||'[]');return Array.isArray(a)?a:[]}catch(e){return[]}}
function put(a){try{localStorage.setItem(K,JSON.stringify(a.slice(0,20)))}catch(e){}}
function el(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e}
function kind(i){return String(i).length===9?'MMSI ':'IMO '}
function getj(u,ok,no){var x=new XMLHttpRequest();x.open('GET',API+u);x.onload=function(){try{ok(JSON.parse(x.responseText),x.status)}catch(e){if(no)no()}};x.onerror=function(){if(no)no()};x.send()}
function go(v){location.hash=v;location.reload()}
// search by name, IMO or MMSI
function hide(){sg.hidden=true;qi.setAttribute('aria-expanded','false')}
function sug(){var q=qi.value.trim();if(q.length<2||/^([0-9]{7}|[0-9]{9})$/.test(q)){hide();return}getj('vessels?q='+encodeURIComponent(q),function(d){if(qi.value.trim()!==q)return;sg.textContent='';var a=d.items||[];
if(!a.length){sg.appendChild(el('span',null,/^[0-9]+$/.test(q)?'Keep typing the full 7-digit IMO number.':'No vessel with this name in our list yet. Enter the 7-digit IMO number instead.'))}
a.forEach(function(v){var l=el('a',null,v.name||('MMSI '+v.mmsi));l.href='/track#'+(v.imo||v.mmsi);l.setAttribute('role','option');l.appendChild(el('small',null,[v.imo?'IMO '+v.imo:'MMSI '+v.mmsi,v.type,v.flag].filter(Boolean).join(' · ')));l.addEventListener('click',function(e){e.preventDefault();go(v.imo||v.mmsi)});sg.appendChild(l)});
sg.hidden=false;qi.setAttribute('aria-expanded','true')},hide)}
qi.addEventListener('input',function(){clearTimeout(t);t=setTimeout(sug,250)});qi.addEventListener('focus',function(){if(sg.children.length&&qi.value.trim().length>1&&!id)sg.hidden=false});
document.addEventListener('click',function(e){if(!e.target.closest||!e.target.closest('.tq'))hide()});qi.addEventListener('keydown',function(e){if(e.key==='Escape')hide();if(e.key==='ArrowDown'&&!sg.hidden){var f=sg.querySelector('a');if(f){e.preventDefault();f.focus()}}});
document.getElementById('tf').addEventListener('submit',function(e){e.preventDefault();var q=qi.value.trim(),d=q.replace(/[^0-9]/g,'');if(/^[0-9 ]+$/.test(q)&&(d.length===7||d.length===9)){go(d);return}var f=sg.hidden?null:sg.querySelector('a');if(f){f.click();return}sug();qi.focus()});
// saved vessels (this browser only)
function draw(){var a=get();sv.textContent='';if(!a.length){sv.hidden=true;return}sv.hidden=false;sv.appendChild(el('b',null,'My vessels'));a.forEach(function(v){var c=el('span','sv'),l=el('a',null,(v.n?v.n+' · ':'')+kind(v.i)+v.i);l.href='/track#'+v.i;l.addEventListener('click',function(e){e.preventDefault();go(v.i)});c.appendChild(l);var x=el('button',null,'×');x.type='button';x.setAttribute('aria-label','Remove '+v.i);x.addEventListener('click',function(){put(get().filter(function(o){return o.i!==v.i}));draw();mark()});c.appendChild(x);sv.appendChild(c)})}
var sb=document.getElementById('tsave');function saved(){return get().filter(function(o){return o.i===id})[0]}
function mark(){if(!id)return;var s=saved();sb.className=s?'on':'';sb.textContent=s?'Saved ✓':'Save vessel'}
draw();
// ships near a port
var pk={chattogram:'Chattogram',mongla:'Mongla',payra:'Payra'}[h];if(pk){getj('vessels-near?port='+h,function(d){var a=d.items||[];if(!a.length)return;var bx=document.getElementById('tlist');bx.appendChild(el('h2',null,'Ships at '+pk+' now ('+a.length+')'));var sc=el('div','sc'),tb=el('table'),hd=el('tr');['Vessel','Type','Flag','Length','Status','Destination'].forEach(function(x){hd.appendChild(el('th',null,x))});var th=el('thead');th.appendChild(hd);tb.appendChild(th);var bd=el('tbody');
a.forEach(function(v){var r=el('tr'),c=el('td'),l=el('a',null,v.name);l.href='/track#'+(v.imo||v.mmsi);l.addEventListener('click',function(e){e.preventDefault();go(v.imo||v.mmsi)});c.appendChild(l);r.appendChild(c);[v.type,v.flag,v.length?v.length+' m':'',v.status,v.destination].forEach(function(x){r.appendChild(el('td',null,x||'-'))});bd.appendChild(r)});tb.appendChild(bd);sc.appendChild(tb);bx.appendChild(sc);bx.hidden=false})}
if(!id){bar.hidden=true;return}
var s0=saved();if(s0)nm=s0.n||'';var url='https://marinedutyfreebd.com/track#'+id;
function label(){document.getElementById('tlab').textContent=(nm?nm+' · ':'')+kind(id)+id;document.getElementById('twa').href='https://wa.me/?text='+encodeURIComponent('Live position of '+(nm||'vessel')+' ('+kind(id)+id+') on DutyFree BD Marine Supply: '+url);
document.getElementById('pinfo').textContent='Vessel tracking report · '+(nm?nm+' · ':'')+kind(id)+id+' · generated '+new Date().toISOString().slice(0,16).replace('T',' ')+' UTC · '+url+' · DutyFree BD Marine Supply'}
label();mark();
sb.addEventListener('click',function(){if(saved())put(get().filter(function(o){return o.i!==id}));else put([{i:id,n:nm}].concat(get()));draw();mark()});
var cb=document.getElementById('tcopy');cb.addEventListener('click',function(){function ok(){cb.textContent='Link copied';setTimeout(function(){cb.textContent='Copy link'},2000)}if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(url).then(ok,function(){window.getSelection().selectAllChildren(document.getElementById('tlab'))})});
document.getElementById('tpdf').addEventListener('click',function(){label();window.print()});
// full details from our own vessel data
function ago(s){if(!s)return'';var m=Math.round((Date.now()-new Date(s).getTime())/60000);if(m<2)return'just now';if(m<90)return m+' min ago';var hr=Math.round(m/60);if(hr<36)return hr+' h ago';return Math.round(hr/24)+' days ago'}
getj('vessel/'+id,function(d){if(!d.found)return;var v=d.vessel,c=document.getElementById('tcard');nm=v.name||nm;document.title=(nm||'Vessel')+' · live position | DutyFree BD Marine Supply';label();if(saved()){put(get().map(function(o){return o.i===id?{i:id,n:nm}:o}));draw()}
c.appendChild(el('h2',null,v.name||'Vessel'));c.appendChild(el('span','sub',[v.type,v.flag?'Flag: '+v.flag:''].filter(Boolean).join(' · ')));var dl=el('dl');
function row(k,x){if(x===null||x===undefined||x==='')return;var w=el('div');w.appendChild(el('dt',null,k));w.appendChild(el('dd',null,x));dl.appendChild(w)}
row('IMO number',v.imo);row('MMSI',v.mmsi);row('Call sign',v.callsign);row('Vessel type',v.type);row('Flag',v.flag);row('Length × beam',v.length&&v.beam?v.length+' m × '+v.beam+' m':(v.length?v.length+' m':''));row('Draught',v.draught?v.draught+' m':'');
row('Status',v.status);row('Destination (as reported)',v.destination);row('ETA (month-day, UTC)',v.eta);row('Speed / course',v.sog!==null?v.sog+' kn'+(v.cog!==null?' / '+v.cog+'°':''):'');
row('Last position',v.lat!==null&&v.lon!==null?Math.abs(v.lat).toFixed(4)+'°'+(v.lat<0?'S':'N')+', '+Math.abs(v.lon).toFixed(4)+'°'+(v.lon<0?'W':'E'):'');row('Position received',ago(v.position_at));row('Last seen',ago(v.seen));
c.appendChild(dl);c.appendChild(el('span','sub','From AIS signals broadcast by the vessel and received near the Bangladesh coast. For information only, not for navigation.'));c.hidden=false});
})();