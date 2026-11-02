/* Global live search: any GET form with a q field re-renders the tables and pager without reloading (400 ms debounce, Enter = now). */
(function(){
var DELAY=400,MIN=2;
function init(inp){
  var form=inp.form;
  if(!form||(form.method||'get').toLowerCase()!=='get'||form.classList.contains('dx-search')||inp.dataset.live==='off')return;
  if(!document.querySelector('main table'))return;
  var tmr=null,ctl=null,st=document.createElement('span');
  st.style.cssText='font-size:12px;color:#6B7280;margin-left:6px;white-space:nowrap';
  inp.insertAdjacentElement('afterend',st);
  function run(){
    clearTimeout(tmr);
    var v=inp.value.trim();
    if(v.length>0&&v.length<MIN){st.textContent='';return}
    if(ctl)ctl.abort();
    ctl=new AbortController();
    var p=new URLSearchParams(new FormData(form));p.delete('page');
    for(var k of Array.from(p.keys())){if(p.get(k)==='')p.delete(k)}
    var url=(form.getAttribute('action')||location.pathname)+(p.toString()?'?'+p.toString():'');
    st.textContent='Searching…';
    fetch(url,{signal:ctl.signal,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'},credentials:'same-origin'}).then(function(r){return r.text()}).then(function(h){
      var d=new DOMParser().parseFromString(h,'text/html');
      var a=document.querySelectorAll('main table'),b=d.querySelectorAll('main table');
      if(a.length&&a.length===b.length){a.forEach(function(t,i){t.replaceWith(b[i])})}
      var pa=document.querySelectorAll('main .pgx'),pb=d.querySelectorAll('main .pgx');
      if(pa.length===pb.length)pa.forEach(function(t,i){t.replaceWith(pb[i])});
      var rows=0;b.forEach(function(t){rows+=t.querySelectorAll('tbody tr').length});
      var empty=b.length&&rows<=1&&/No .* (yet|found)|No matching/i.test(b[0].textContent);
      st.textContent=empty?'No matching records found':(v?rows+' results shown':'');
      history.replaceState(null,'',url);
    }).catch(function(e){if(e.name!=='AbortError')st.textContent=''});
  }
  inp.addEventListener('input',function(){clearTimeout(tmr);tmr=setTimeout(run,DELAY)});
  inp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();run()}});
}
function boot(){document.querySelectorAll('main form input[name=q]').forEach(init)}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
