@extends('layout')@section('title','Category Wise Profit')
@section('body')
<style>
.cp{--o:#F95505;--od:#C2410C;--n:#0C161F;--g:#E6E7E9;--pe:#FFEEE6;color:var(--n)}
.cp h1{margin:0;font-size:20px}.cp .sub{font-size:12px;color:#6B7280}
.cp .bar{display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin:10px 0}.cp .bar label{font-size:11px;color:#6B7280;display:block;margin-bottom:2px}
.cp .bar input,.cp .bar select{height:30px;border:1px solid var(--g);border-radius:6px;padding:0 6px;font-size:12px;background:#fff;max-width:150px}
.cp .pre{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px}.cp .pre button{height:26px;border:1px solid var(--g);background:#fff;color:#0B1220;border-radius:999px;font-size:11px;padding:0 10px;cursor:pointer}.cp .pre button:hover{border-color:var(--o);color:var(--od)}
.cp .btn{height:30px;border-radius:6px;border:1px solid var(--g);background:#fff;padding:0 12px;font-size:12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;color:var(--n)}.cp .btn.p{background:var(--od);border-color:var(--od);color:#fff}
.cp .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin:10px 0}
.cp .card{border:1px solid var(--g);border-radius:8px;padding:8px 10px;background:#fff}.cp .card b{display:block;font-size:17px;margin-top:2px;font-variant-numeric:tabular-nums}.cp .card span{font-size:11px;color:#6B7280}.cp .card small{display:block;font-size:11px;margin-top:2px}
.cp .up{color:#166534}.cp .dn{color:#B91C1C}.cp .warn{background:#FEE2E2;border:1px solid #FECACA;color:#B91C1C;border-radius:8px;padding:7px 10px;font-size:12px;margin:8px 0}.cp .okb{background:#DCFCE7;border:1px solid #BBF7D0;color:#166534;border-radius:8px;padding:6px 10px;font-size:12px;margin:8px 0}
.cp .ins{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0}.cp .ins div{border:1px solid var(--g);background:#FAFAFB;border-radius:6px;padding:5px 8px;font-size:11px;max-width:260px}.cp .ins b{display:block;color:var(--od);font-size:10px;text-transform:uppercase;letter-spacing:.04em}
.cp .box{border:1px solid var(--g);border-radius:8px;background:#fff;padding:10px;margin:10px 0}
.cp .tg{display:flex;gap:4px;margin-bottom:8px}.cp .tg button{height:26px;border:1px solid var(--g);background:#fff;color:#0B1220;border-radius:6px;font-size:11px;padding:0 10px;cursor:pointer}.cp .tg button.on{background:var(--pe);border-color:var(--o);color:var(--od);font-weight:600}
.cp .ch{display:grid;grid-template-columns:150px 1fr 90px;gap:8px;align-items:center;font-size:11px;margin:3px 0}.cp .ch i{display:block;height:12px;border-radius:3px;background:var(--o)}.cp .ch i.r{background:#B91C1C}.cp .ch i.n{background:#94A3B8}.cp .ref{position:relative;height:0}
.cp .tw{overflow:auto;max-height:70vh;border:1px solid var(--g);border-radius:8px}
.cp table{width:100%;border-collapse:collapse;font-size:12px;background:#fff}.cp th,.cp td{border:1px solid var(--g);padding:0 8px;height:32px;white-space:nowrap}
.cp thead th{position:sticky;top:0;background:#F3F4F6;height:34px;z-index:2;cursor:pointer;user-select:none}.cp .n{text-align:right;font-variant-numeric:tabular-nums}.cp td.c{text-align:center}
.cp tr.m td{font-weight:600;background:#fff}.cp tr.s td{background:#FAFAFB}.cp tr.c2 td{background:#F5F7FA;color:#4B5563}.cp tr.pr td{background:#F8FAFC;font-size:11.5px}.cp tr.od td{background:#fff;font-size:11px;color:#4B5563}
.cp tr.t td{background:#FFF1E8;font-weight:700;position:sticky;bottom:0}.cp .x{cursor:pointer;color:var(--od);font-weight:700;display:inline-block;width:14px}
.cp .st{display:inline-block;border-radius:999px;padding:1px 8px;font-size:10.5px;font-weight:600}.cp .st.Strong,.cp .st.Healthy{background:#DCFCE7;color:#166534}.cp .st.Watch{background:#FEF3C7;color:#B45309}.cp .st.LowMargin{background:#FFF1E8;color:#B45309}.cp .st.Loss{background:#FEE2E2;color:#B91C1C}
.cp .mc{display:none}.cp .empty,.cp .load{padding:18px;text-align:center;color:#6B7280;font-size:12px}
@media(max-width:760px){.cp .tw{display:none}.cp .mc{display:block}.cp .mcard{border:1px solid var(--g);border-radius:10px;padding:10px;margin:8px 0;background:#fff;font-size:13px}.cp .mcard b{font-size:15px}.cp .mcard div{display:flex;justify-content:space-between;padding:2px 0}.cp .ch{grid-template-columns:90px 1fr 70px}}
@media print{.noprint,aside,nav,.sidebar,header{display:none!important}.cp .tw{max-height:none;overflow:visible}}
</style>
<div class="cp">
<div class="noprint" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
  <div><h1>Category Wise Profit</h1><span class="sub">Sales → Returns → Discounts → FIFO COGS → Gross profit → Margin · same rules as the Profit &amp; Loss Statement · protected margin target {{ rtrim(rtrim(number_format($prot,2),'0'),'.') }}%</span></div>
  <div style="display:flex;gap:6px"><a class="btn" id="xcsv" href="#">Export CSV (Excel)</a><button class="btn" onclick="window.print()">Print / PDF</button><button class="btn p" id="rf">Refresh</button></div>
</div>
<form class="bar noprint" id="fm" method="get">
  <div class="pre" style="width:100%"><button type="button" data-p="0">Today</button><button type="button" data-p="1">Yesterday</button><button type="button" data-p="7">Last 7 days</button><button type="button" data-p="30">Last 30 days</button><button type="button" data-p="m">This month</button><button type="button" data-p="lm">Last month</button></div>
  <div><label>From</label><input type="date" name="from" value="{{ $f['from'] }}"></div><div><label>To</label><input type="date" name="to" value="{{ $f['to'] }}"></div>
  <div><label>Orders</label><select name="status"><option value="delivered" @selected($f['status']==='delivered')>Delivered + returned parcels</option><option value="all" @selected($f['status']==='all')>All (not cancelled)</option></select></div>
  <div><label>Store / website</label><select name="store"><option value="">All stores</option><option value="main" @selected($f['store']==='main')>DutyFreeBD</option>@foreach($opts['stores'] as $s)<option value="{{ $s->id }}" @selected($f['store']===(string)$s->id)>{{ $s->name }}</option>@endforeach</select></div>
  <div><label>Main category</label><select name="cat"><option value="">All</option>@foreach($opts['cats'] as $c)<option value="{{ $c->id }}" @selected($f['cat']===(string)$c->id)>{{ $c->name }}</option>@endforeach</select></div>
  <div><label>Sub category</label><input name="sub" value="{{ $f['sub'] }}" placeholder="exact name"></div>
  <div><label>Child category</label><input name="child" value="{{ $f['child'] }}" placeholder="exact name"></div>
  <div><label>Brand</label><select name="brand"><option value="">All</option>@foreach($opts['brands'] as $b)<option value="{{ $b->id }}" @selected($f['brand']===(string)$b->id)>{{ $b->name }}</option>@endforeach</select></div>
  <div><label>Payment</label><select name="pay"><option value="">All</option>@foreach($opts['pay'] as $v)<option @selected($f['pay']===$v)>{{ $v }}</option>@endforeach</select></div>
  <div><label>Courier</label><select name="courier"><option value="">All</option>@foreach($opts['courier'] as $v)<option @selected($f['courier']===$v)>{{ $v }}</option>@endforeach</select></div>
  <div><label>Approval</label><select name="appr"><option value="">All</option><option value="AI" @selected($f['appr']==='AI')>AI approved</option><option value="MANUAL" @selected($f['appr']==='MANUAL')>Manual approved</option></select></div>
  <div><label>Search</label><input name="q" value="{{ $f['q'] }}" placeholder="category, brand, product" style="min-width:150px"></div>
  <div><label>Compare</label><select name="cmp"><option value="">Off</option><option value="1" @selected($f['cmp']==='1')>Previous period</option></select></div>
  <div><button class="btn p">Apply</button> <a class="btn" href="/admin/category-profit">Reset</a></div>
</form>
<div id="msg"></div><div class="cards" id="cards"></div><div class="ins" id="ins"></div>
<div class="box noprint"><div class="tg" id="tg"></div><div id="chart" class="sub">Loading…</div></div>
<div class="tw"><table><thead><tr id="hd"></tr></thead><tbody id="tb"><tr><td class="load" colspan="20">Loading…</td></tr></tbody></table></div>
<div class="mc" id="mc"></div>
<div class="sub" style="margin-top:8px" id="note">Net sales = gross − discount − return · Gross profit = net sales − FIFO COGS · Net profit = gross profit − courier cost (per parcel: the real Pathao payout-invoice fee, else the fixed Pathao charge from Courier settings; a returned parcel also pays its return fee; spread over the order's lines) · Margin = gross profit ÷ net sales · Overall margin = total profit ÷ total sales (not an average of category margins). Order date is the basis. General expenses (rent, salary, marketing) are not deducted here. Delivery charge collected from customers is not added. Returned parcels are included: their sale is reversed and their courier cost stays as a loss.</div>
</div>
<script>
(function(){
var F=@json($f),PROT=@json((float)$prot),Q=new URLSearchParams(F),R=[],T=null,SORT={k:'profit',d:-1},MET='profit',OPEN={};
var fmt=function(n){n=Math.round(+n||0);var s=Math.abs(n).toString(),l=s.slice(-3),r=s.slice(0,-3);if(r)l=r.replace(/\B(?=(\d{2})+(?!\d))/g,',')+','+l;return(n<0?'-':'')+'৳'+l};
var num=function(n){return (+n||0).toLocaleString('en-IN')},pc=function(n){return (+n||0).toFixed(1)+'%'};
var esc=function(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})};
var qs=function(x){var u=new URLSearchParams(F);for(var k in x)u.set(k,x[k]);return u.toString()};
var cols=[['name','Category',0],['orders','Orders',1],['delivered','Delivered',1],['qty','Qty sold',1],['gross','Gross sales',1],['disc','Discount',1],['ret_qty','Return qty',1],['ret_amt','Return amt',1],['net','Net sales',1],['cogs','COGS (FIFO)',1],['profit','Gross profit',1],['margin','Margin %',1],['courier','Courier cost',1],['net_profit','Net profit',1],['net_margin','Net margin %',1],['avg_order','Avg/order',1],['avg_unit','Avg/unit',1],['ret_rate','Return %',1],['disc_rate','Disc %',1],['profit_share','Profit share',1],['sales_share','Sales share',1],['status','Status',0]];
function get(level,p,cb){var u='/admin/category-profit/data?'+qs(Object.assign({level:level},p||{}));fetch(u,{headers:{'Accept':'application/json'},credentials:'same-origin'}).then(function(r){if(!r.ok)throw new Error(r.status);return r.json()}).then(cb).catch(function(e){document.getElementById('msg').innerHTML='<div class="warn">Could not load the report ('+esc(e.message)+'). Check permission or try Refresh.</div>';document.getElementById('tb').innerHTML=''})}
function delta(cur,prev,money,pts){if(!prev&&prev!==0)return '';var d=cur-prev;if(pts){return '<small class="'+(d>=0?'up':'dn')+'">'+(d>=0?'↑ ':'↓ ')+Math.abs(d).toFixed(1)+' pts</small>'}if(!prev)return '';var p=d/Math.abs(prev)*100;return '<small class="'+(d>=0?'up':'dn')+'">'+(d>=0?'↑ ':'↓ ')+Math.abs(p).toFixed(1)+'%</small>'}
function cards(d){var t=d.totals,p=d.prev||{};var best=R.length?R.slice().sort(function(a,b){return b.profit-a.profit})[0]:null;var lowm=R.filter(function(x){return x.net>0}).sort(function(a,b){return a.margin-b.margin})[0];
var c=[['Total net sales',fmt(t.net),delta(t.net,p.net)],['Total COGS',fmt(t.cogs),delta(t.cogs,p.cogs)],['Gross profit',fmt(t.profit),delta(t.profit,p.profit)],['Courier cost',fmt(t.courier),delta(t.courier,p.courier)],['Net profit (after courier)',fmt(t.net_profit),delta(t.net_profit,p.net_profit)],['Overall margin',pc(t.margin),delta(t.margin,p.margin,0,1)],['Total discount',fmt(t.disc),(t.disc_rate+'% of gross')],['Return impact',fmt(t.ret_amt),(t.ret_qty+' qty · '+pc(t.ret_rate))],['Best profit category',best?esc(best.name):'—',best?fmt(best.profit):''],['Highest margin',d.best_margin?esc(d.best_margin):'—',''],['Lowest margin',lowm?esc(lowm.name):'—',lowm?pc(lowm.margin):'']];
document.getElementById('cards').innerHTML=c.map(function(x){return '<div class="card"><span>'+x[0]+'</span><b>'+x[1]+'</b><small>'+(x[2]||'')+'</small></div>'}).join('');
var m='';if(d.recon){m+=d.recon.ok===false?'<div class="warn"><b>Report Reconciliation Issue.</b> '+esc(d.recon.note)+'</div>':(d.recon.ok?'<div class="okb">✓ '+esc(d.recon.note)+'</div>':'<div class="sub">'+esc(d.recon.note)+'</div>')}
if(t.nocost_sales>0)m+='<div class="warn">'+fmt(t.nocost_sales)+' of sales are lines with no cost on record (cost 0), so profit is overstated by up to that amount. Fix the product cost / batch.</div>';
if(d.prev_range)m+='<div class="sub">Compared with '+d.prev_range[0]+' → '+d.prev_range[1]+'</div>';document.getElementById('msg').innerHTML=m;
document.getElementById('ins').innerHTML=(d.insights||[]).map(function(i){return '<div><b>'+esc(i.k)+'</b>'+esc(i.v)+' · '+esc(i.n)+'</div>'}).join('')}
function chart(){var tg=[['sales','Sales'],['cogs','COGS'],['profit','Gross profit'],['net_profit','Net profit'],['margin','Margin'],['sp','Sales vs Profit']];document.getElementById('tg').innerHTML=tg.map(function(x){return '<button type="button" data-m="'+x[0]+'" class="'+(MET===x[0]?'on':'')+'">'+x[1]+'</button>'}).join('');
var rows=R.filter(function(x){return x.net||x.profit||x.cogs}).slice(0,14);if(!rows.length){document.getElementById('chart').innerHTML='<div class="empty">No data for this filter.</div>';return}
var key={sales:'net',cogs:'cogs',profit:'profit',net_profit:'net_profit',margin:'margin'}[MET],h='';
if(MET==='sp'){var mx=Math.max.apply(null,rows.map(function(x){return Math.max(Math.abs(x.net),Math.abs(x.profit))}))||1;rows.forEach(function(x){h+='<div class="ch"><span>'+esc(x.name)+'</span><div><i class="n" style="width:'+Math.abs(x.net)/mx*100+'%"></i><i class="'+(x.profit<0?'r':'')+'" style="width:'+Math.abs(x.profit)/mx*100+'%;margin-top:2px"></i></div><span>'+fmt(x.net)+' / '+fmt(x.profit)+'</span></div>'});h+='<div class="sub">Grey = net sales · orange = gross profit</div>'}
else{var mx=Math.max.apply(null,rows.map(function(x){return Math.abs(x[key])}).concat(MET==='margin'?[PROT]:[]))||1;rows.forEach(function(x){var v=x[key],red=(MET==='margin'&&x.net>0&&v<PROT)||v<0;h+='<div class="ch"><span>'+esc(x.name)+'</span><div><i class="'+(red?'r':'')+'" style="width:'+Math.max(1,Math.abs(v)/mx*100)+'%"></i></div><span>'+(MET==='margin'?pc(v):fmt(v))+'</span></div>'});if(MET==='margin')h+='<div class="sub">Red = below the protected margin target of '+PROT+'%</div>'}
document.getElementById('chart').innerHTML=h}
function cell(x,k){var v=x[k];if(k==='name')return esc(v);if(k==='status')return '<span class="st '+String(v).replace(' ','')+'">'+esc(v)+'</span>';if(['gross','disc','ret_amt','net','cogs','profit','courier','net_profit','avg_order','avg_unit'].indexOf(k)>-1)return '<span class="'+((k==='profit'||k==='net_profit'||k==='avg_order'||k==='avg_unit')&&v<0?'dn':((k==='profit'||k==='net_profit')?'up':''))+'">'+fmt(v)+'</span>';if(['margin','net_margin','ret_rate','disc_rate','profit_share','sales_share'].indexOf(k)>-1){var w=k==='margin'&&x.below?' <span class="dn" title="Below '+PROT+'% target">⚠</span>':'';return pc(v)+w}return num(v)}
function head(){document.getElementById('hd').innerHTML=cols.map(function(c){return '<th class="'+(c[2]?'n':'')+'" data-k="'+c[0]+'">'+c[1]+(SORT.k===c[0]?(SORT.d<0?' ▼':' ▲'):'')+'</th>'}).join('')}
function rowHtml(x,cls,lvl,ids){var pad=(cls==='s'?16:cls==='c2'?32:cls==='pr'?48:0),tog=lvl==='order'?'':'<span class="x">'+(OPEN[ids]?'−':'+')+'</span>';
return '<tr class="'+cls+'" data-id="'+esc(ids)+'" data-l="'+lvl+'">'+cols.map(function(c,i){return '<td class="'+(c[2]?'n':'')+'" '+(i===0?'style="padding-left:'+(8+pad)+'px"':'')+'>'+(i===0?tog:'')+cell(x,c[0])+'</td>'}).join('')+'</tr>'}
function render(){head();var rows=R.slice().sort(function(a,b){var k=SORT.k,x=a[k],y=b[k];return(typeof x==='string'?x.localeCompare(y):x-y)*SORT.d});var tb=document.getElementById('tb');
if(!rows.length){tb.innerHTML='<tr><td class="empty" colspan="20">No delivered or returned sales in this period and filter.</td></tr>';document.getElementById('mc').innerHTML='';return}
tb.innerHTML=rows.map(function(x){return rowHtml(x,'m','main','m:'+x.key)}).join('')+(T?'<tr class="t">'+cols.map(function(c,i){return '<td class="'+(c[2]?'n':'')+'">'+(i===0?'TOTAL':(c[0]==='status'?'':cell(T,c[0])))+'</td>'}).join('')+'</tr>':'');
document.getElementById('mc').innerHTML=rows.map(function(x){return '<div class="mcard"><b>'+esc(x.name)+'</b> <span class="st '+x.status.replace(' ','')+'">'+x.status+'</span><div><span>Sales</span><span>'+fmt(x.net)+'</span></div><div><span>COGS</span><span>'+fmt(x.cogs)+'</span></div><div><span>Gross profit</span><span class="'+(x.profit<0?'dn':'up')+'">'+fmt(x.profit)+'</span></div><div><span>Net profit</span><span class="'+(x.net_profit<0?'dn':'up')+'">'+fmt(x.net_profit)+'</span></div><div><span>Margin</span><span>'+pc(x.margin)+'</span></div><div><span>Return</span><span>'+pc(x.ret_rate)+'</span></div></div>'}).join('')}
function orderRows(rows,id){return rows.map(function(o){return '<tr class="od" data-p="'+esc(id)+'"><td style="padding-left:'+(8+64)+'px">'+esc(o.invoice)+' · '+o.date+'</td><td class="n" colspan="2">'+o.ret+'</td><td class="n">'+o.qty+'</td><td class="n">'+fmt(o.price)+'</td><td class="n">'+fmt(o.disc)+'</td><td colspan="3"></td><td class="n">'+fmt(o.net)+'</td><td class="n">'+fmt(o.cogs)+(o.fifo?'':' *')+'</td><td class="n '+(o.profit<0?'dn':'up')+'">'+fmt(o.profit)+'</td><td class="n">'+pc(o.margin)+'</td><td colspan="7"></td></tr>'}).join('')}
function drill(tr){var id=tr.dataset.id,l=tr.dataset.l;if(OPEN[id]){var del=function(n){var nx=tr.nextElementSibling;while(nx&&nx.dataset.p&&nx.dataset.p.indexOf(id)===0){var d=nx;nx=nx.nextElementSibling;d.remove()}};del();OPEN[id]=0;tr.querySelector('.x').textContent='+';return}
var p=id.split('|').reduce(function(a,s){var kv=s.split(':');a[kv[0]]=kv.slice(1).join(':');return a},{}),next={main:'sub',sub:'child',child:'product',product:'order'}[l],cls={sub:'s',child:'c2',product:'pr'}[next];
var pr={};if(p.m!==undefined)pr.pm=p.m;if(p.s!==undefined)pr.ps=p.s;if(p.c!==undefined)pr.pc=p.c;if(p.p!==undefined)pr.pp=p.p;OPEN[id]=1;tr.querySelector('.x').textContent='−';
get(next,pr,function(d){var h='';if(next==='order'){h=orderRows(d.rows,id)}else d.rows.forEach(function(x){var nid=id+'|'+({sub:'s',child:'c',product:'p'}[next])+':'+x.key;h+=rowHtml(x,cls,next,nid).replace('<tr ','<tr data-p="'+esc(id)+'" ')});tr.insertAdjacentHTML('afterend',h||'<tr class="od" data-p="'+esc(id)+'"><td colspan="20" class="empty">Nothing here.</td></tr>')})}
function load(){get('main',{},function(d){R=d.rows;T=d.totals;cards(d);chart();render();document.getElementById('xcsv').href='/admin/category-profit?'+qs({export:'csv'})})}
document.getElementById('tb').addEventListener('click',function(e){var tr=e.target.closest('tr');if(tr&&tr.dataset.l)drill(tr)});
document.getElementById('hd').addEventListener('click',function(e){var k=e.target.dataset&&e.target.dataset.k;if(!k)return;SORT.d=SORT.k===k?-SORT.d:-1;SORT.k=k;OPEN={};render()});
document.getElementById('tg').addEventListener('click',function(e){var m=e.target.dataset&&e.target.dataset.m;if(m){MET=m;chart()}});
document.getElementById('rf').addEventListener('click',function(){OPEN={};load()});
document.querySelector('.pre').addEventListener('click',function(e){var p=e.target.dataset&&e.target.dataset.p;if(p==null)return;var f=document.getElementById('fm'),t=new Date(),iso=function(d){var z=d.getTimezoneOffset();return new Date(d.getTime()-z*60000).toISOString().slice(0,10)},a=new Date(t),b=new Date(t);
if(p==='m')a=new Date(t.getFullYear(),t.getMonth(),1);else if(p==='lm'){a=new Date(t.getFullYear(),t.getMonth()-1,1);b=new Date(t.getFullYear(),t.getMonth(),0)}else if(p==='1'){a.setDate(a.getDate()-1);b=new Date(a)}else a.setDate(a.getDate()-(+p===0?0:(+p-1)));
f.from.value=iso(a);f.to.value=iso(b);f.submit()});
load()})();
</script>
@endsection
