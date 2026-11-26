(function(){
var B='https://dutyfreebd.com/marine-api/';
function arts(){return document.querySelectorAll('#ports article')}
function key(a){var b=a.querySelector('b');return b?b.textContent.trim().toLowerCase():''}
function find(k){var A=arts();for(var i=0;i<A.length;i++)if(key(A[i])===k)return A[i];return null}
function badge(k,cls,txt){var a=find(k);if(!a)return;var l=a.querySelector('.pp-lv');if(!l){l=document.createElement('div');l.className='pp-lv';a.appendChild(l)}
var e=l.querySelector('.'+cls);if(!txt){if(e)e.remove();return}if(!e){e=document.createElement('span');e.className='pp-bd '+cls;if(cls==='pp-sh')l.insertBefore(e,l.firstChild);else l.appendChild(e)}e.textContent=txt}
function get(u,ok){try{var x=new XMLHttpRequest();x.open('GET',B+u);x.timeout=12000;x.onload=function(){try{if(x.status===200)ok(JSON.parse(x.responseText))}catch(e){}};x.send()}catch(e){}}
var P=['chattogram','mongla','payra'];
function run(){if(!arts().length)return;
get('weather',function(d){var p=d&&d.ports||{};P.forEach(function(k){var c=p[k]&&p[k].current;badge(k,'pp-wx',c&&typeof c.temp_c==='number'?Math.round(c.temp_c)+'°C · Wind '+Math.round(c.wind_kn)+' kn':'')})});
P.forEach(function(k){get('vessels-near?port='+k,function(d){var n=d&&d.items?d.items.length:0;badge(k,'pp-sh',n>0?(n>=40?'40+':n)+(n===1?' ship':' ships')+' nearby now':'')})})}
if(document.readyState==='complete')setTimeout(run,900);else window.addEventListener('load',function(){setTimeout(run,900)});
setInterval(function(){if(!document.hidden)run()},300000);
})();
