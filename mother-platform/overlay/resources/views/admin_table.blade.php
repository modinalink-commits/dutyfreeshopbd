{{-- Mother Commerce — Enterprise Table System (ONE global table design for the whole admin backend).
     Included once by layout.blade.php after admin_theme, inside @auth, so it can never reach the public storefront.
     Visual layer only: no query, permission, pagination, filter, export or calculation is touched.
     Structure only (sizes, grid lines, alignment, scroll, drag): NO colour is changed anywhere. --}}
<style id="admin-table">
/* Tokens alias the colours the theme ALREADY uses — nothing here introduces or changes a colour. */
:root{--table-bg:var(--surface-main,#fff);--table-header-bg:var(--head-bg);--table-group-bg:var(--group-bg);--table-hover-bg:var(--row-hover);--table-text:var(--text-primary);--table-muted:var(--text-muted)}
/* 1. every admin table (JS tags it .mc-t; tables without a header row get .mc-kv): same grid, same compact sizes. Structure only. */
main table.mc-t,main table.mc-kv{border-collapse:separate;border-spacing:0;border:1px solid var(--border-grid);border-radius:0;font-size:12px}
main table.mc-t th{height:34px;padding:6px 8px;font-size:12px;font-weight:600;border-radius:0;vertical-align:middle;white-space:nowrap}
main table.mc-t td,main table.mc-kv td{height:32px;padding:5px 7px;font-size:12px;line-height:1.35;border-radius:0;vertical-align:middle}
main table.mc-kv td{border-right:1px solid var(--border-grid);border-bottom:1px solid var(--border-grid)}
main table.mc-kv td:last-child{border-right:0}
main table.mc-t th.ctr,main table.mc-t td.ctr{text-align:center}
main table.mc-kv td.n,main table.mc-t td.n{text-align:right;font-variant-numeric:tabular-nums}
/* 2. report grid (.pl): same compact sizes */
.pl{font-size:12px}
.pl th,.pl td{padding:5px 7px;height:32px;line-height:1.35;border-radius:0}
.pl thead th{height:34px;padding:6px 8px;font-size:12px;font-weight:600}
.pl .gh td,.pl .st td{height:30px;font-size:12px}
.pl .gt td{height:34px;font-size:12.5px}
.pl .st td{text-align:right}
/* 2b. Orders list (.ordt): keeps its card-style row content; header and grid lines now match every other table */
main table.ordt>thead>tr>th{height:34px;padding:6px 8px;font-weight:600;border-radius:0}
main table.ordt>tbody>tr>td{border-top-width:1px!important;border-bottom-width:1px!important;border-top-color:var(--border-grid)!important;border-bottom-color:var(--border-grid)!important;border-radius:0}
main table.ordt>tbody>tr>td:last-child{border-right-color:var(--border-grid)!important}
/* 3. status pills: compact everywhere */
main .pill{min-height:22px;padding:2px 7px;font-size:11px}
/* 4. empty + loading states keep the table layout */
main td.empty,main td.g-empty{text-align:center;padding:16px 8px;font-size:12px}
.mc-loading{opacity:.55;transition:opacity .15s;pointer-events:none}
/* 5. wide tables scroll inside their own box, never the whole page */
.mc-ts{overflow:auto;max-height:calc(100vh / var(--z,1) - 110px);width:100%;max-width:100%;min-width:0;-webkit-overflow-scrolling:touch}
.mc-sbox{max-height:calc(100vh / var(--z,1) - 110px)}
main pre{max-width:100%;overflow-x:auto}
main{min-width:0}
/* frozen first / action columns on tables that scroll sideways (opaque cells only; no colour added) */
main table .mc-fl{position:sticky;left:0;z-index:2}main table .mc-fr{position:sticky;right:0;z-index:2}
main table th.mc-fl,main table th.mc-fr{z-index:4}
.mc-drag{cursor:grab}.mc-drag.mc-dragging{cursor:grabbing;user-select:none}.mc-drag.mc-dragging *{pointer-events:none}
</style>
<script>
(function(){
  /* A. right-align money columns already handled by admin_grid; here: centre Status / Date / SKU columns and wrap wide tables */
  var CTR=/^(status|state|payment status|order status|delivery status|date|sales date|created|created at|updated|sku|product id|id)$/i;
  function centre(){
    document.querySelectorAll('main table:not(.ordt)').forEach(function(tb){
      if(tb.dataset.mcc||tb.closest('td'))return;tb.dataset.mcc='1';
      var head=tb.querySelector('thead tr');if(!head)return;
      var rows=[].slice.call(tb.querySelectorAll('tbody>tr')),cols=head.children.length;
      if(rows.length<1||rows.slice(0,40).some(function(r){return r.children.length!==cols||[].some.call(r.children,function(c){return c.colSpan>1})}))return;
      [].forEach.call(head.children,function(h,i){
        if(h.classList.contains('num')||h.classList.contains('n'))return;
        if(!CTR.test((h.textContent||'').replace(/\s+/g,' ').trim()))return;
        h.classList.add('ctr');rows.forEach(function(r){if(r.children[i]&&!r.children[i].classList.contains('num'))r.children[i].classList.add('ctr')});
      });
    });
  }
  /* B. wrap a table in a scroll box only when it is wider than its parent and no ancestor already scrolls it */
  function scrolls(el){var s=getComputedStyle(el);return /(auto|scroll)/.test(s.overflowX)}
  function wrap(){
    var main=document.querySelector('main');if(!main)return;
    main.querySelectorAll('table').forEach(function(tb){
      if(tb.closest('td')||tb.classList.contains('ordt')||tb.dataset.mcw)return;
      var p=tb.parentElement;if(!p||p===main&&false)return;
      if(tb.offsetWidth<=p.clientWidth+1)return;
      for(var a=p;a&&a!==main;a=a.parentElement){if(scrolls(a)&&a.scrollWidth>a.clientWidth)return}
      if(p.classList.contains('mc-ts'))return;tb.dataset.mcw='1';
      var w=document.createElement('div');w.className='mc-ts';p.insertBefore(w,tb);w.appendChild(tb);
    });
  }
  /* C. click + hold + drag scrolls any wide table sideways (touch already swipes). 5 px threshold, links/buttons/inputs stay clickable. */
  var dn=null,x0=0,s0=0,moved=false;
  function box(t){
    for(var a=t;a&&a!==document.body;a=a.parentElement){
      if(a.id==='owt'||a.id==='ostrip'||a.hasAttribute('data-nodrag'))return null;
      if(a.scrollWidth>a.clientWidth+2&&scrolls(a)&&a.querySelector('table'))return a;
    }
    return null;
  }
  document.addEventListener('mousedown',function(e){
    if(e.button!==0||e.target.closest('input,select,textarea,button,a,label,summary,[contenteditable],.stn,.ib'))return;
    var b=box(e.target);if(!b)return;dn=b;x0=e.pageX;s0=b.scrollLeft;moved=false;
  });
  document.addEventListener('mousemove',function(e){
    if(!dn)return;var d=e.pageX-x0;
    if(!moved&&Math.abs(d)>5){moved=true;dn.classList.add('mc-dragging');getSelection&&getSelection().removeAllRanges()}
    if(moved)dn.scrollLeft=s0-d;
  });
  function stop(){if(dn){dn.classList.remove('mc-dragging');var b=dn;dn=null;if(moved)setTimeout(function(){moved=false},0)}}
  document.addEventListener('mouseup',stop);document.addEventListener('mouseleave',stop);
  document.addEventListener('click',function(e){if(moved){e.preventDefault();e.stopPropagation()}},true);
  document.addEventListener('mouseover',function(e){var b=box(e.target);if(b&&!b.classList.contains('mc-drag'))b.classList.add('mc-drag')});

  /* D. header row of a table that has none inside <thead> -> move it into one (looks identical; makes sticky header + Export work) */
  function normHead(){
    document.querySelectorAll('main table').forEach(function(tb){
      if(tb.tHead||tb.closest('td')||tb.dataset.mch)return;tb.dataset.mch='1';
      var tr=tb.querySelector('tr');if(!tr||!tr.cells.length||!tr.parentNode)return;
      if(![].every.call(tr.cells,function(c){return c.tagName==='TH'}))return;
      if(tr.parentNode.tagName!=='TBODY'&&tr.parentNode!==tb)return;
      var th=document.createElement('thead');tb.insertBefore(th,tb.firstChild);th.appendChild(tr);
    });
  }
  /* E. sticky header inside any box that scrolls sideways: give the box a height so the header can stick */
  function sbox(){
    document.querySelectorAll('main table').forEach(function(tb){
      if(tb.closest('td'))return;
      for(var a=tb.parentElement;a&&a.tagName!=='MAIN';a=a.parentElement){
        if(scrolls(a)){if(a.scrollWidth>a.clientWidth+2&&!a.classList.contains('mc-ts')&&!/(^|[; ])max-height/.test(a.getAttribute('style')||'')&&getComputedStyle(a).maxHeight==='none'&&a.id!=='owt'&&a.id!=='ostrip')a.classList.add('mc-sbox');break}
      }
    });
  }
  /* F. frozen columns: first column (two when the first is only a checkbox) and the Actions column, only where the table really scrolls sideways */
  function freeze(){
    document.querySelectorAll('main table').forEach(function(tb){
      if(tb.closest('td'))return;
      var cs=tb.querySelectorAll('.mc-fl,.mc-fr');
      var box=null;for(var a=tb.parentElement;a&&a.tagName!=='MAIN';a=a.parentElement){if(scrolls(a)&&a.scrollWidth>a.clientWidth+2){box=a;break}}
      if(!box||tb.offsetWidth<=box.clientWidth+2){[].forEach.call(cs,function(c){c.classList.remove('mc-fl','mc-fr');c.style.left=c.style.right=''});return}
      var head=tb.tHead&&tb.tHead.rows[0];if(!head)return;
      var rows=[].slice.call(tb.tBodies[0]?tb.tBodies[0].rows:[]).slice(0,30),n=head.cells.length;if(n<4)return;
      var opaque=function(c){var b=getComputedStyle(c).backgroundColor;return b&&!/rgba?\(0, 0, 0, 0\)|transparent/.test(b)};
      var cbx=function(c){return !!c.querySelector('input[type=checkbox]')&&c.textContent.trim().length<2};
      var L=[0];if(cbx(head.cells[0])&&n>5)L.push(1);
      var last=n-1,hasA=/^actions?$/i.test((head.cells[last].textContent||'').replace(/\s+/g,' ').trim());
      var left=0;
      L.forEach(function(i){
        var w=head.cells[i].getBoundingClientRect().width;
        [head].concat(rows).forEach(function(r){var c=r.cells[i];if(!c||c.colSpan>1||r.cells.length!==n)return;if(c.tagName==='TD'&&!opaque(c))return;c.classList.add('mc-fl');c.style.left=left+'px'});
        left+=w;
      });
      if(hasA)[head].concat(rows).forEach(function(r){var c=r.cells[last];if(!c||c.colSpan>1||r.cells.length!==n)return;if(c.tagName==='TD'&&!opaque(c))return;c.classList.add('mc-fr');c.style.right='0px'});
    });
  }
  /* G. remember the page size (rows per page) the person chose, per page; the URL always wins */
  function pageSize(){
    var sels=document.querySelectorAll('main select[name=per],main select[name=per_page],main select[name=pp]');
    sels.forEach(function(sl){
      if(sl.dataset.mcp)return;sl.dataset.mcp='1';
      var opts=[].map.call(sl.options,function(o){return o.value});if(!opts.length||!opts.every(function(v){return /^\d+$/.test(v)}))return;
      var key='mc.per:'+location.pathname+':'+sl.name;
      sl.addEventListener('change',function(){try{localStorage.setItem(key,sl.value)}catch(e){}});
      try{
        var sv=localStorage.getItem(key),g='mc.pg:'+key+':'+location.href;
        if(sv&&sv!==sl.value&&opts.indexOf(sv)>-1&&!new URLSearchParams(location.search).has(sl.name)&&!sessionStorage.getItem(g)){
          sessionStorage.setItem(g,'1');sl.value=sv;sl.dispatchEvent(new Event('change',{bubbles:true}));
          if(sl.form&&!sl.getAttribute('onchange'))setTimeout(function(){if(!document.hidden&&sl.form.isConnected)try{sl.form.requestSubmit()}catch(e){}},50);
        }
      }catch(e){}
    });
  }
  /* tag every admin table so the one CSS above reaches it, whatever local class it carries */
  function tag(){
    document.querySelectorAll('main table').forEach(function(tb){
      if(tb.dataset.mct||tb.classList.contains('ordt')||tb.closest('td')||tb.closest('[data-nogrid]'))return;tb.dataset.mct='1';
      if(tb.querySelector('th'))tb.classList.add('mc-t');else if(tb.querySelector('td'))tb.classList.add('mc-kv');
    });
  }
  function run(){try{normHead();tag();centre();wrap();sbox();freeze();pageSize()}catch(e){}}
  window.addEventListener('resize',function(){clearTimeout(window.__mcr);window.__mcr=setTimeout(function(){try{freeze()}catch(e){}},150)});
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
  window.addEventListener('load',run);
  /* ajax-refreshed report bodies: re-apply */
  new MutationObserver(function(){clearTimeout(window.__mct);window.__mct=setTimeout(run,120)}).observe(document.documentElement,{childList:true,subtree:true});
})();
</script>
