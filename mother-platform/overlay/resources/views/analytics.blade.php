@extends('layout')@section('title', $title.' · Reports & Analytics')
@section('body')
@php
  $has = fn($k) => in_array($k, $fset, true);
  $qsx = $qs ? '?'.$qs : '';
  $notes = [
    'dashboard' => 'A quick view of sales, profit, orders, delivery and stores for the selected dates. Sales are product revenue after discount; cancelled and returned orders are left out.',
    'profit-loss' => 'Net sales minus product cost gives gross profit. Net profit also adds delivery charged to customers and takes off courier charges, return fees and expense entries from Accounts.',
    'stock-tracking' => 'All stores share one stock pool, so stock is shown once. Opening and closing come from the stock ledger; Sold and Returned come from orders in the dates.',
    'top-customers' => 'Category: VIP = ৳20,000+ delivered spend or 5+ delivered orders, Regular = 2+ orders, New = first order. Reliability = delivered ÷ (delivered + returned + cancelled). Risk is High below 50% (2+ finished orders) or with 3+ returns or cancels, Medium below 75%.',
    'courier-wise' => 'Success rate = delivered ÷ (delivered + returned). Settlement pending = COD collected minus courier charge on delivered parcels not yet marked Paid.',
    'low-stock' => 'Products at or below their low stock alert (10 when none is set on the product).',
    'category-sales' => 'Return rate = returned quantity ÷ all quantity sold in the category (cancelled orders left out).',
  ];
  $note = $notes[$slug] ?? 'Sales are product revenue after discount (shipping is not included). Cancelled and returned orders are left out unless you pick a status.';
  $srcRows = $dash ? $summaryRows : $rows; $srcCols = $dash ? $summaryCols : $cols;
  $noSum = ['price','unit_profit','aov','alert','avg_days','margin','rate','success','score','new_share'];
  $showTotals = !in_array($slug, ['margin','low-stock','out-of-stock','top-customers'], true);
@endphp
<style>
.ra{max-width:1440px}
.ra .hd1{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:10px;margin-bottom:14px}
.ra .hd1 h1{margin:0}.ra .crumb{font-size:12px;color:#6B7280;margin-bottom:4px}.ra .crumb b{color:var(--o)}
.ra .note{font-size:12.5px;color:#6B7280;margin:0 0 14px;max-width:900px}
.ra .box{background:#fff;border:1px solid #F1E3D8;border-radius:16px;box-shadow:0 1px 3px rgba(11,18,32,.05),0 8px 24px rgba(11,18,32,.04);padding:16px 18px;margin-bottom:16px}
.ra .fl{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px 12px;align-items:end}
.ra .fl label{margin:0 0 4px;font-size:11px;font-weight:600;letter-spacing:.03em;text-transform:uppercase}
.ra .fl input,.ra .fl select,.ra .ms>summary{height:38px;padding:0 10px;font-size:13px;border-radius:10px}
.ra .pre{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.ra .pre button{background:#fff;color:var(--n);border:1px solid var(--bd);padding:5px 11px;font-size:12px;border-radius:999px}
.ra .pre button:hover{background:var(--n);color:#fff;border-color:var(--n)}
.ra .acts{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:12px}
.ra .acts .btn,.ra .acts button{padding:9px 16px;border-radius:10px}
.ra .vw{display:inline-flex;border:1px solid var(--bd);border-radius:10px;overflow:hidden}
.ra .vw label{margin:0;padding:8px 12px;font-size:12px;font-weight:600;text-transform:none;letter-spacing:0;color:var(--n);cursor:pointer;background:#fff}
.ra .vw input{display:none}.ra .vw input:checked+span{background:var(--n);color:#fff}
.ra .vw label span{display:block;margin:-8px -12px;padding:8px 12px}
.ra .ms{position:relative}.ra .ms>summary{display:flex;align-items:center;border:1px solid var(--bd);background:#fff;cursor:pointer;list-style:none}
.ra .ms>div{position:absolute;z-index:20;top:42px;left:0;min-width:220px;max-height:260px;overflow:auto;background:#fff;border:1px solid var(--bd);border-radius:12px;box-shadow:0 12px 30px rgba(11,18,32,.15);padding:8px}
.ra .ms label{display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:13px;color:var(--n);padding:5px 6px;margin:0;font-weight:500}.ra .ms input{width:auto;height:auto}
.ra .dd{position:relative;display:inline-block}.ra .dd>summary{list-style:none;cursor:pointer}
.ra .dd>div{position:absolute;right:0;z-index:20;top:40px;min-width:170px;background:#fff;border:1px solid var(--bd);border-radius:12px;box-shadow:0 12px 30px rgba(11,18,32,.15);padding:6px}
.ra .dd a{display:block;padding:8px 10px;border-radius:8px;color:var(--n);text-decoration:none;font-size:13px}.ra .dd a:hover{background:#FFF1E8}
.ra .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:16px}
.ra .cd{background:#fff;border:1px solid #F1E3D8;border-radius:16px;padding:14px 16px;box-shadow:0 1px 3px rgba(11,18,32,.05)}
.ra .cd span{font-size:12px;color:#6B7280;display:block}.ra .cd b{display:block;font-size:22px;margin-top:4px;color:var(--n)}.ra .cd.hl{background:var(--n);border-color:var(--n)}.ra .cd.hl span{color:#9CA3AF}.ra .cd.hl b{color:#fff}
.ra .chs{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:14px;margin-bottom:16px}
.ra .chb{margin:0}.ra .chb h3{margin:0 0 6px;font-size:14px}.ra .ch{width:100%;min-height:220px}
.ra .leg{display:flex;flex-wrap:wrap;gap:12px;font-size:12px;color:#6B7280;margin-bottom:4px}.ra .leg i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:5px}
.ra .tbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px}
.ra .tbar input[type=search]{max-width:260px;height:38px;border-radius:10px}.ra .tbar select{width:auto;height:38px;border-radius:10px}
.ra .tw{overflow:auto;border:1px solid #F1E3D8;border-radius:12px}
.ra table{border-collapse:separate;border-spacing:0;width:100%;font-size:13px}
.ra th{position:sticky;top:0;background:#FFF7F1;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:.04em;padding:10px 12px;white-space:nowrap;cursor:pointer;user-select:none;border-bottom:1px solid #F1E3D8}
.ra th.r,.ra td.r{text-align:right}.ra td{padding:9px 12px;border-top:1px solid #FBEFE6;white-space:nowrap}.ra tbody tr:hover{background:#FFFBF8}
.ra tfoot td{font-weight:700;background:#FFF7F1;border-top:1px solid #F1E3D8}
.ra td img{width:34px;height:34px;border-radius:8px;object-fit:cover;display:block;background:#F3F4F6}
.ra td.w{white-space:normal;min-width:180px;max-width:320px}
.ra .bg{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;background:#F3F4F6;color:#374151}
.ra .bg.g{background:#DCFCE7;color:#166534}.ra .bg.r{background:#FEE2E2;color:#B91C1C}.ra .bg.o{background:#FFEDD5;color:#C2410C}.ra .bg.b{background:#E0E7FF;color:#3730A3}
.ra .pg{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:12px;font-size:13px}.ra .pg button{background:#fff;color:var(--n);border:1px solid var(--bd);padding:6px 11px;min-width:34px}.ra .pg button.on{background:var(--n);color:#fff;border-color:var(--n)}.ra .pg button:disabled{opacity:.4}.ra .pg .tot{margin-left:auto;color:#6B7280;font-size:12px}
.ra .warn{background:#FFF7ED;border:1px solid #FED7AA;color:#9A3412;border-radius:10px;padding:8px 12px;font-size:12.5px;margin-bottom:12px}
.ra .cmp table th{cursor:default}
@media(max-width:700px){.ra .chs{grid-template-columns:1fr}.ra .cd b{font-size:18px}}
@media print{aside,.noprint{display:none!important}}
</style>
<div class="ra">
<div class="hd1"><div><div class="crumb">Reports &amp; Analytics › <b>{{ $menu[$group][0] }}</b></div><h1>{{ $title }}</h1></div>
@if(!$dash)<div class="dd noprint"><details><summary class="btn g">Export ▾</summary><div>
<a href="/admin/analytics/{{ $slug }}/export/print{{ $qsx }}" target="_blank">PDF (save from print)</a><a href="/admin/analytics/{{ $slug }}/export/xls{{ $qsx }}">Excel</a><a href="/admin/analytics/{{ $slug }}/export/csv{{ $qsx }}">CSV</a><a href="/admin/analytics/{{ $slug }}/export/print{{ $qsx }}" target="_blank">Print</a></div></details></div>@endif</div>
<p class="note">{{ $note }}</p>

<form class="box noprint" method="get" id="ff">
@if($has('date'))<div class="pre"><button type="button" data-p="0">Today</button><button type="button" data-p="1">Yesterday</button><button type="button" data-p="7">Last 7 days</button><button type="button" data-p="30">Last 30 days</button><button type="button" data-p="m">This month</button><button type="button" data-p="lm">Last month</button></div>@endif
<div class="fl">
@if($has('date'))<div><label>From</label><input type="date" name="from" value="{{ $f['from'] }}"></div><div><label>To</label><input type="date" name="to" value="{{ $f['to'] }}"></div>@endif
@if($has('store') && count($opts['stores']))<div><label>Store</label><details class="ms"><summary>{{ $f['stores'] ? count($f['stores']).' selected' : 'All stores' }}</summary><div>@foreach($opts['stores'] as $st)<label><input type="checkbox" name="stores[]" value="{{ $st['id'] }}" @checked(in_array((int)$st['id'], $f['stores']))>{{ $st['name'] }}</label>@endforeach</div></details></div>@endif
@if($has('category'))<div><label>Category</label><select name="category"><option value="">All categories</option>@foreach($opts['categories'] as $c)<option value="{{ $c['id'] }}" @selected($f['category']==$c['id'])>{{ $c['name'] }}</option>@endforeach</select></div>@endif
@if($has('product'))<div><label>Product</label><input name="product" value="{{ $f['product'] }}" placeholder="Name or SKU"></div>@endif
@if($has('customer'))<div><label>{{ $flabel['customer'] }}</label><input name="customer" value="{{ $f['customer'] }}" placeholder="Search"></div>@endif
@if($has('courier'))<div><label>Courier</label><select name="courier"><option value="">All couriers</option>@foreach($opts['couriers'] as $c)<option @selected($f['courier']===$c)>{{ $c }}</option>@endforeach</select></div>@endif
@if($has('status'))<div><label>Order status</label><select name="status"><option value="">Default</option>@foreach($opts['statuses'] as $c)<option @selected($f['status']===$c)>{{ $c }}</option>@endforeach</select></div>@endif
@if($has('pay'))<div><label>Payment status</label><select name="pay"><option value="">Any</option>@foreach($opts['pays'] as $c)<option @selected($f['pay']===$c)>{{ $c }}</option>@endforeach</select></div>@endif
</div>
<div class="acts">
@if($has('store'))<div class="vw">@foreach(['combined'=>'Combined','store'=>'Store wise','compare'=>'Comparison'] as $k=>$l)<label><input type="radio" name="view" value="{{ $k }}" @checked($f['view']===$k)><span>{{ $l }}</span></label>@endforeach</div>@endif
<button type="submit">Apply filter</button><a class="btn g" href="/admin/analytics{{ $slug === 'dashboard' ? '' : '/'.$slug }}">Reset</a></div>
</form>

@if($truncated)<div class="warn">Showing the first 5,000 rows. Narrow the dates or filters to see everything.</div>@endif
@if($cards)<div class="cards">@foreach($cards as $i => [$l,$v,$t])<div class="cd {{ $i === 3 && $dash ? 'hl' : '' }}"><span>{{ $l }}</span><b data-v="{{ $v }}" data-t="{{ $t }}">{{ $v }}</b></div>@endforeach</div>@endif

@if($stores)
<div class="box cmp"><h3 style="margin:0 0 8px;font-size:14px">Store comparison</h3><div class="tw"><table><thead><tr><th>Store</th><th class="r">Orders</th><th class="r">Sales</th><th class="r">Cost</th><th class="r">Profit</th><th class="r">Margin</th><th class="r">Avg order</th></tr></thead><tbody>
@foreach($stores as $s)<tr><td><b>{{ $s['store'] }}</b></td><td class="r">{{ number_format($s['orders']) }}</td><td class="r">৳{{ number_format($s['sales']) }}</td><td class="r">৳{{ number_format($s['cost']) }}</td><td class="r">৳{{ number_format($s['profit']) }}</td><td class="r">{{ $s['margin'] }}%</td><td class="r">৳{{ number_format($s['aov']) }}</td></tr>@endforeach</tbody></table></div></div>
@endif

@if($charts)<div class="chs">@foreach($charts as $i => $c)<div class="box chb"><h3>{{ $c['title'] }}</h3><div class="leg" id="lg{{ $i }}"></div><div class="ch" id="ch{{ $i }}"></div></div>@endforeach</div>@endif

<div class="box">
@if($dash)<h3 style="margin:0 0 10px;font-size:14px">Day by day</h3>@endif
<div class="tbar noprint"><input type="search" id="q" placeholder="Search in this report"><select id="sz"><option>25</option><option>50</option><option>100</option><option>500</option></select><span style="font-size:12px;color:#6B7280">rows per page</span>
<div class="dd" style="margin-left:auto"><details><summary class="btn g">Columns ▾</summary><div id="cv"></div></details></div></div>
<div class="tw"><table id="tb"><thead></thead><tbody></tbody><tfoot></tfoot></table></div>
<div class="pg noprint" id="pg"></div>
</div>
</div>
<script>
(function(){
var COLS=@json($srcCols),ROWS=@json(array_values($srcRows)),CH=@json($charts),SLUG=@json($slug),SUM={{ $showTotals ? 'true' : 'false' }},NOSUM=@json($noSum);
var $=function(i){return document.getElementById(i)};
function money(v){v=+v||0;return (v<0?'-':'')+'৳'+Math.abs(Math.round(v)).toLocaleString('en-IN')}
function fmt(v,t){if(v===null||v===undefined||v==='')return '';if(t==='money')return money(v);if(t==='num')return (+v).toLocaleString('en-IN');if(t==='pct')return (+v)+'%';return v}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
document.querySelectorAll('.cd b').forEach(function(b){b.textContent=fmt(b.dataset.v,b.dataset.t)});
/* presets */
function iso(d){var o=new Date(d.getTime()+6*3600*1000);return o.toISOString().slice(0,10)}
document.querySelectorAll('.pre button').forEach(function(b){b.onclick=function(){var n=new Date(),p=b.dataset.p,f=n,t=n;
if(p==='0'){}else if(p==='1'){f=t=new Date(n-864e5)}else if(p==='7'||p==='30'){f=new Date(n-(p-1)*864e5)}
else{var dh=new Date(n.getTime()+6*3600*1000);var y=dh.getUTCFullYear(),m=dh.getUTCMonth();if(p==='m'){f=new Date(Date.UTC(y,m,1)-6*3600*1000);t=n}else{f=new Date(Date.UTC(y,m-1,1)-6*3600*1000);t=new Date(Date.UTC(y,m,0)-6*3600*1000)}}
document.querySelector('[name=from]').value=iso(f);document.querySelector('[name=to]').value=iso(t);$('ff').submit()}});
/* table */
var key='ra-cols-'+SLUG,hid={};try{hid=JSON.parse(localStorage.getItem(key)||'{}')}catch(e){}
var st={q:'',k:null,d:1,p:1,n:25},keys=Object.keys(COLS);
var badgeCls={Delivered:'g','Partial Delivered':'g','Late Delivery':'o',Returned:'r','Return Pending':'r',Cancel:'r',New:'b',VIP:'g',Regular:'b',High:'r',Medium:'o',Low:'g',Paid:'g',Pending:'o'};
function cell(r,k){var t=COLS[k][1],v=r[k];if(t==='img')return v?'<img src="'+esc(v)+'" alt="" loading="lazy">':'<img alt="" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==">';
if(t==='badge'){var c=badgeCls[v]||'';return '<span class="bg '+c+'">'+esc(v)+'</span>'}return esc(fmt(v,t))}
function view(){var q=st.q.toLowerCase(),rows=ROWS.filter(function(r){return !q||keys.some(function(k){return String(r[k]==null?'':r[k]).toLowerCase().indexOf(q)>-1})});
if(st.k){var t=COLS[st.k][1],num=(t==='num'||t==='money'||t==='pct');rows=rows.slice().sort(function(a,b){var x=a[st.k],y=b[st.k];if(num){x=+x||0;y=+y||0}else{x=String(x==null?'':x).toLowerCase();y=String(y==null?'':y).toLowerCase()}return x<y?-st.d:(x>y?st.d:0)})}
return rows}
function draw(){var rows=view(),pages=Math.max(1,Math.ceil(rows.length/st.n));if(st.p>pages)st.p=pages;var vis=keys.filter(function(k){return !hid[k]});
var h='<tr>'+vis.map(function(k){var t=COLS[k][1],r=(t==='num'||t==='money'||t==='pct')?' r':'';return '<th class="'+r.trim()+'" data-k="'+k+'">'+esc(COLS[k][0])+(st.k===k?(st.d>0?' ▲':' ▼'):'')+'</th>'}).join('')+'</tr>';
$('tb').tHead.innerHTML=h;
var slice=rows.slice((st.p-1)*st.n,st.p*st.n);
$('tb').tBodies[0].innerHTML=slice.length?slice.map(function(r){return '<tr>'+vis.map(function(k){var t=COLS[k][1],c=(t==='num'||t==='money'||t==='pct')?'r':(k==='product'||k==='customer'||k==='comment'||k==='line'||k==='note'?'w':'');return '<td class="'+c+'">'+cell(r,k)+'</td>'}).join('')+'</tr>'}).join(''):'<tr><td colspan="'+Math.max(1,vis.length)+'" style="padding:24px;text-align:center;color:#6B7280">No data for these filters.</td></tr>';
var f='';if(SUM&&rows.length>1&&slug_ok()){f='<tr>'+vis.map(function(k,i){var t=COLS[k][1];if((t==='num'||t==='money')&&NOSUM.indexOf(k)<0){var s=0;rows.forEach(function(r){s+=(+r[k]||0)});return '<td class="r">'+fmt(s,t)+'</td>'}return '<td>'+(i===0?'Total ('+rows.length+')':'')+'</td>'}).join('')+'</tr>'}$('tb').tFoot.innerHTML=f;
var pg='<button '+(st.p<2?'disabled':'')+' data-g="'+(st.p-1)+'">‹ Prev</button>';var lo=Math.max(1,st.p-2),hi=Math.min(pages,st.p+2);if(lo>1)pg+='<button data-g="1">1</button>'+(lo>2?'…':'');for(var i=lo;i<=hi;i++)pg+='<button class="'+(i===st.p?'on':'')+'" data-g="'+i+'">'+i+'</button>';if(hi<pages)pg+=(hi<pages-1?'…':'')+'<button data-g="'+pages+'">'+pages+'</button>';pg+='<button '+(st.p>=pages?'disabled':'')+' data-g="'+(st.p+1)+'">Next ›</button><span class="tot">'+rows.length.toLocaleString('en-IN')+' rows</span>';$('pg').innerHTML=pg}
function slug_ok(){return SLUG!=='profit-loss'}
$('tb').tHead.addEventListener('click',function(e){var th=e.target.closest('th');if(!th)return;var k=th.dataset.k;if(st.k===k)st.d=-st.d;else{st.k=k;st.d=1}st.p=1;draw()});
$('pg').addEventListener('click',function(e){var b=e.target.closest('button[data-g]');if(b&&!b.disabled){st.p=+b.dataset.g;draw()}});
$('q').addEventListener('input',function(){st.q=this.value;st.p=1;draw()});$('sz').addEventListener('change',function(){st.n=+this.value;st.p=1;draw()});
$('cv').innerHTML=keys.filter(function(k){return COLS[k][1]!=='img'}).map(function(k){return '<label style="display:flex;gap:8px;padding:5px 8px;margin:0;font-size:13px;color:#0B1220;align-items:center"><input type="checkbox" style="width:auto" data-c="'+k+'" '+(hid[k]?'':'checked')+'>'+esc(COLS[k][0])+'</label>'}).join('');
$('cv').addEventListener('change',function(e){var k=e.target.dataset.c;if(!k)return;if(e.target.checked)delete hid[k];else hid[k]=1;try{localStorage.setItem(key,JSON.stringify(hid))}catch(x){}draw()});
draw();
/* charts */
var PAL=['#0B1220','#FF5A00','#94A3B8','#F59E0B'];
function short(n){n=+n;var a=Math.abs(n);if(a>=1e7)return (n/1e7).toFixed(1).replace(/\.0$/,'')+'Cr';if(a>=1e5)return (n/1e5).toFixed(1).replace(/\.0$/,'')+'L';if(a>=1e3)return (n/1e3).toFixed(1).replace(/\.0$/,'')+'k';return String(Math.round(n*10)/10)}
function nice(m){if(m<=0)return 1;var p=Math.pow(10,Math.floor(Math.log10(m))),f=m/p;return (f<=1?1:f<=2?2:f<=5?5:10)*p}
function drawChart(i){var c=CH[i],el=$('ch'+i);if(!el)return;var W=Math.max(260,el.clientWidth||520),H=230,pl=48,pr=12,pt=10,pb=40,L=c.labels.length;
$('lg'+i).innerHTML=c.series.length>1||c.type==='bar'&&c.series[0].name?c.series.map(function(s,j){return '<span><i style="background:'+PAL[j%4]+'"></i>'+esc(s.name)+'</span>'}).join(''):'';
if(!L){el.innerHTML='<div style="padding:60px 0;text-align:center;color:#6B7280;font-size:13px">No data for these filters.</div>';return}
var mx=0;c.series.forEach(function(s){s.data.forEach(function(v){if(v>mx)mx=v})});var top=nice(mx*1.05),iw=W-pl-pr,ih=H-pt-pb,svg='<svg width="'+W+'" height="'+H+'" role="img">';
for(var t=0;t<=4;t++){var y=pt+ih-ih*t/4;svg+='<line x1="'+pl+'" x2="'+(W-pr)+'" y1="'+y+'" y2="'+y+'" stroke="#F1E3D8"/><text x="'+(pl-6)+'" y="'+(y+4)+'" text-anchor="end" font-size="10" fill="#6B7280">'+short(top*t/4)+'</text>'}
var step=Math.max(1,Math.ceil(L/Math.max(2,Math.floor(iw/58))));
function lab(x,j){if(j%step)return '';var s=String(c.labels[j]);if(c.type==='line'&&s.length>5)s=s.slice(5);if(s.length>11)s=s.slice(0,10)+'…';return '<text x="'+x+'" y="'+(H-pb+16)+'" text-anchor="middle" font-size="10" fill="#6B7280">'+esc(s)+'</text>'}
if(c.type==='line'){c.series.forEach(function(s,j){var pts=s.data.map(function(v,k){return [pl+(L>1?iw*k/(L-1):iw/2),pt+ih-ih*v/top]});var d=pts.map(function(p,k){return (k?'L':'M')+p[0].toFixed(1)+','+p[1].toFixed(1)}).join('');
svg+='<path d="'+d+'L'+pts[L-1][0].toFixed(1)+','+(pt+ih)+'L'+pts[0][0].toFixed(1)+','+(pt+ih)+'Z" fill="'+PAL[1]+'" opacity=".08"/><path d="'+d+'" fill="none" stroke="'+PAL[1]+'" stroke-width="2.2" stroke-linejoin="round"/>';
pts.forEach(function(p,k){svg+='<circle cx="'+p[0].toFixed(1)+'" cy="'+p[1].toFixed(1)+'" r="'+(L>40?2:3.5)+'" fill="#fff" stroke="'+PAL[1]+'" stroke-width="2"><title>'+esc(c.labels[k])+': '+(+s.data[k]).toLocaleString('en-IN')+'</title></circle>'})});
for(var k=0;k<L;k++)svg+=lab(pl+(L>1?iw*k/(L-1):iw/2),k)}
else{var gw=iw/L,bw=Math.max(4,Math.min(46,gw*.72/c.series.length));c.series.forEach(function(s,j){s.data.forEach(function(v,k){var h=ih*v/top,x=pl+gw*k+(gw-bw*c.series.length)/2+bw*j;svg+='<rect x="'+x.toFixed(1)+'" y="'+(pt+ih-h).toFixed(1)+'" width="'+bw.toFixed(1)+'" height="'+Math.max(0,h).toFixed(1)+'" rx="4" fill="'+PAL[j%4]+'"><title>'+esc(c.labels[k])+' · '+esc(s.name)+': '+(+v).toLocaleString('en-IN')+'</title></rect>'})});
for(var k=0;k<L;k++)svg+=lab(pl+gw*k+gw/2,k)}
el.innerHTML=svg+'</svg>'}
function all(){CH.forEach(function(c,i){drawChart(i)})}all();var rz;window.addEventListener('resize',function(){clearTimeout(rz);rz=setTimeout(all,150)});
})();
</script>
@endsection
