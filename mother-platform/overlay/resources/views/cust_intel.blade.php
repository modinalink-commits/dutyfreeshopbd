@php
 $cc = ['VIP'=>['#8B5CF6','#F5F3FF'],'Regular'=>['#3B6FD8','#EFF6FF'],'New'=>['#0E9AB8','#ECFEFF'],'High Value'=>['#B7791F','#FFFBEB'],'Watchlist'=>['#DC4A4A','#FEF2F2']][$ci['cat']];
 $rc = ['Low'=>['#2F7D4F','#F3FBF6','#CDEFD8','#2F7D4F'],'Medium'=>['#A15C1A','#FFFAEB','#FBE3A6','#A15C1A'],'High'=>['#B33A3A','#FEF5F5','#FBCFCF','#B33A3A']][$ci['risk']];
 $wa = \App\Support\CustomerIntel::wa($o->phone);
@endphp
<div class="ci" style="min-width:230px;max-width:340px;font-size:12.5px;line-height:1.3;font-family:Inter,'Hind Siliguri','Noto Sans Bengali','Segoe UI',Arial,sans-serif">
 <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap"><b style="font-size:14px;color:#0B1220;font-weight:700">{{ $o->customer_name }}</b><span style="background:{{ $cc[1] }};color:{{ $cc[0] }};border:1px solid {{ $cc[0] }}33;border-radius:999px;padding:1px 8px;font-weight:600;font-size:10.5px">{{ $ci['cat'] }}</span>@if(!empty($ci['nth']))@php($__n = (int)$ci['nth'])@php($__sf = ($__n % 100 >= 11 && $__n % 100 <= 13) ? 'th' : ([1=>'st',2=>'nd',3=>'rd'][$__n % 10] ?? 'th'))<span title="This is the customer's {{ $__n }}{{ $__sf }} order" style="background:#fff;color:#6B7280;border:1px solid #D1D5DB;border-radius:999px;padding:1px 7px;font-weight:700;font-size:10.5px;white-space:nowrap">{{ $__n }}{{ $__sf }} order</span>@endif
@if(!empty($ci['level']))@include('components.customer-level-stars', ['level' => $ci['level'], 'orders' => $ci['delivered'] + $ci['legacy']])@endif</div>
 <div style="color:#0B1220;font-weight:600">{{ $o->phone }} · ID {{ $ci['cid'] }}</div>
 @php($__bf = \App\Support\CustomerBenefit::check($o->phone))@php($__bc = ['Eligible'=>['#15803D','#DCFCE7'],'Limited'=>['#B45309','#FEF3C7'],'Review Required'=>['#C2410C','#FFEDD5'],'Suspended'=>['#B91C1C','#FEE2E2']][$__bf['status']] ?? ['#475569','#F1F5F9'])<div><span title="Benefit eligibility: {{ implode(', ', $__bf['reasons']) }} · received {{ $__bf['received'] }} parcel(s)" style="background:{{ $__bc[1] }};color:{{ $__bc[0] }};border-radius:999px;padding:1px 8px;font-weight:700;font-size:10.5px">{{ $__bf['status'] === 'Suspended' ? 'Benefit Suspended' : $__bf['status'] }}</span></div>
 <div style="color:#0B1220;font-weight:600">{{ \Illuminate\Support\Str::limit(trim((string)($o->address ?? '')) ?: '—', $full ?? false ? 400 : 60) }}@if(!empty($o->addr_saved)) <span title="Customer has not typed an address yet — this is the address from their last order" style="font-weight:500;font-size:10.5px;color:#9CA3AF">· saved</span>@endif</div>
@once
<style>
.ci .sx{display:flex;gap:6px;margin:6px 0 2px;max-width:260px}
.ci .sbar{all:unset;box-sizing:border-box;flex:1;min-width:0;height:22px;border:1px solid #B8BFC9;border-radius:6px;display:flex;overflow:hidden;cursor:pointer;background:#fff}
.ci .sbar:hover{border-color:#FF5A00;box-shadow:0 0 0 2px rgba(255,90,0,.12)}
.ci .sbar:focus-visible{outline:2px solid #FF5A00}
.ci .sbar .sg{display:flex;align-items:center;justify-content:center;font-size:10.5px;font-weight:700;white-space:nowrap;min-width:24px;padding:0 5px}
.ci .sbar .sg.g{background:#DCFCE7;color:#166534}.ci .sbar .sg.r{background:#FEE2E2;color:#B91C1C}.ci .sbar .sg+.sg{border-left:1px solid #fff}
#dfci-ov{position:fixed;inset:0;background:rgba(11,18,32,.45);display:none;align-items:center;justify-content:center;padding:16px;z-index:99999}
#dfci-ov.on{display:flex}
#dfci-ov .pop{background:#fff;border:1px solid #B8BFC9;border-radius:8px;width:100%;max-width:340px;box-shadow:0 12px 32px rgba(11,18,32,.25);font:13px/1.35 Inter,'Hind Siliguri','Segoe UI',Arial,sans-serif;color:#0B1220}
#dfci-ov header{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-bottom:1px solid #B8BFC9;background:#F3F4F6;border-radius:8px 8px 0 0;font-weight:700}
#dfci-ov header button{border:0;background:none;font-size:18px;cursor:pointer;line-height:1;color:#0B1220}
#dfci-ov dl{margin:0;padding:6px 12px 12px;display:grid;grid-template-columns:1fr auto}
#dfci-ov dt,#dfci-ov dd{margin:0;padding:6px 0;border-bottom:1px solid #E5E7EB}#dfci-ov dd{text-align:right;font-weight:700}
#dfci-ov .g{color:#166534}#dfci-ov .r{color:#B91C1C}#dfci-ov .a{color:#B45309}
</style>
<script>
window.dfCi=function(b,ev){if(ev){ev.stopPropagation();ev.preventDefault()}
var d=JSON.parse(b.getAttribute('data-d')),o=document.getElementById('dfci-ov');
if(!o){o=document.createElement('div');o.id='dfci-ov';o.innerHTML='<div class="pop" role="dialog" aria-modal="true"><header><span></span><button type="button" aria-label="Close">\u00d7</button></header><dl></dl></div>';document.body.appendChild(o);
o.addEventListener('click',function(e){if(e.target===o||e.target.tagName==='BUTTON')o.classList.remove('on')});
document.addEventListener('keydown',function(e){if(e.key==='Escape')o.classList.remove('on')})}
o.querySelector('header span').textContent=d.t;
var dl=o.querySelector('dl');dl.innerHTML='';
d.r.forEach(function(r){var a=document.createElement('dt'),c=document.createElement('dd');a.textContent=r[0];c.textContent=r[1];if(r[2])c.className=r[2];dl.appendChild(a);dl.appendChild(c)});
o.classList.add('on');return false};
</script>
@endonce
 @php($__dv=(int)$ci['delivered'])@php($__bad=(int)$ci['returned']+(int)$ci['cancelled'])
 @php($__ow=($__dv+$__bad)>0?[max($__dv,0.0001),max($__bad,0.0001)]:[1,1])
 @php($__rl=(int)$ci['rel'])@php($__fr=(int)$ci['fraud'])
 @php($__tw=($__rl+$__fr)>0?[max($__rl,0.0001),max($__fr,0.0001)]:[1,1])
 @php($__vt=$ci['verdict']==='safe'?['Safe to Confirm','g']:($ci['verdict']==='call'?['Verify by Call','a']:['Verify / Advance Payment','r']))
 @php($__od=['t'=>'Orders · '.$o->customer_name,'r'=>array_values(array_filter([['Total orders',(string)$ci['total']],['Delivered',(string)$ci['delivered'],'g'],['Returned',(string)$ci['returned']],['Cancelled',(string)$ci['cancelled'],'r'],['Pending',(string)$ci['pending'],'a'],['Success rate',$ci['rate']===null?'—':$ci['rate'].'%'],$ci['legacy']?['Older imported orders','+'.$ci['legacy']]:null]))])
 @php($__td=['t'=>'Trust · '.$o->customer_name,'r'=>[['Reliability score',(string)$ci['rel'],'g'],['Fraud risk score',(string)$ci['fraud'],'r'],['Risk level',$ci['risk']],['Verdict',$__vt[0],$__vt[1]]]])
 <div class="sx"><button type="button" class="sbar" title="Order statistics — click for details" aria-label="Order statistics" data-d="{{ json_encode($__od, JSON_UNESCAPED_UNICODE) }}" onclick="return dfCi(this,event)"><span class="sg g" style="flex:{{ $__ow[0] }} 1 0">{{ $__dv }}</span><span class="sg r" style="flex:{{ $__ow[1] }} 1 0">{{ $__bad }}</span></button><button type="button" class="sbar" title="Reliability / fraud risk — click for details" aria-label="Reliability and risk" data-d="{{ json_encode($__td, JSON_UNESCAPED_UNICODE) }}" onclick="return dfCi(this,event)"><span class="sg g" style="flex:{{ $__tw[0] }} 1 0">{{ $__rl }}</span><span class="sg r" style="flex:{{ $__tw[1] }} 1 0">{{ $__fr }}</span></button></div>
 <div class="ibar"><a title="Call Customer" aria-label="Call Customer" href="tel:{{ $o->phone }}" onclick="if(window.dfCall){dfCall('{{ $o->phone }}',{order:'{{ addslashes($o->invoice ?? '') }}',name:'{{ addslashes($o->customer_name ?? '') }}'});return false}return true" class="ib-call"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>Call</a><a title="Messages" aria-label="Open Messages" href="#" onclick="return window.dfSms?!dfSms('{{ $o->phone }}','{{ addslashes($o->customer_name ?? '') }}'):false" class="ib ch"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></a><a title="Open WhatsApp" aria-label="Open WhatsApp" target="_blank" rel="noopener" href="https://wa.me/{{ $wa }}" class="ib wa"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M9 10.5c.5 1.6 1.9 3 3.5 3.5l1.2-1.2 2 1-.4 1.4c-3.3.3-6.8-3.2-6.5-6.5l1.4-.4 1 2Z"/></svg></a>@if($ci['cuid'])<a title="Customer Profile" aria-label="Customer Profile" href="/admin/m/customers/{{ $ci['cuid'] }}/edit" class="ib pf"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></a>@endif<a title="Previous orders" aria-label="Previous orders" href="/admin/orders?q={{ $o->phone }}" class="ib hs"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 2v20l3-2 3 2 3-2 3 2 3-2V2l-3 2-3-2-3 2-3-2-3 2Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></a><a title="Copy mobile number" aria-label="Copy mobile number" href="#" onclick="return dfCopyNo(this,'{{ $o->phone }}')" class="ib cp"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></a></div>
</div>
