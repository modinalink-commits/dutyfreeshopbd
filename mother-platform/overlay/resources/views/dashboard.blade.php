@extends('layout')@section('title','Overview')
@section('body')
@php
$ST = ['New'=>['#E6EFF9','#1D5FA8'],'Incomplete'=>['#F3F4F6','#4B5563'],'Pre Order'=>['#ECE8F8','#4B33A0'],'Assign'=>['#E3F1F8','#0F5C80'],'Pending'=>['#FFF1E8','#C2410C'],'AI Approve'=>['#DCFCE7','#166534'],'Approved'=>['#DDF1EE','#0E5F58'],'Scheduled'=>['#E9EEF8','#2A4C8C'],'WFP'=>['#FFF1E8','#9A3412'],'Packaging'=>['#F1EAF8','#5B3FA6'],'Shipment'=>['#E3EEF9','#1D4F94'],'Partial Delivered'=>['#E8F5E9','#2E7D32'],'Delivered'=>['#DCFCE7','#166534'],'Late Delivery'=>['#F8E4E1','#A3281E'],'Return Pending'=>['#F8E4E1','#A3281E'],'Returned'=>['#F3F4F6','#374151'],'Cancel'=>['#F3F4F6','#6B7280']];
$LV = ['CRITICAL'=>['#F8E4E1','#A3281E'],'WARNING'=>['#FFF1E8','#C2410C'],'INFO'=>['#E6EFF9','#1D5FA8']];
$IC = ['sales'=>'<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>','cart'=>'<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/>','tag'=>'<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/>','coin'=>'<circle cx="12" cy="12" r="9"/><path d="M14.8 9.2c-.6-.8-1.6-1.2-2.8-1.2-1.7 0-2.8.8-2.8 2s1 1.7 2.8 2 2.8.8 2.8 2-1.1 2-2.8 2c-1.2 0-2.3-.4-2.9-1.3M12 6v2m0 8v2"/>','truck'=>'<path d="M1 3h13v13H1zM14 8h4l4 4v4h-8z"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>','undo'=>'<path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-15-6.7L3 13"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','box'=>'<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>','plus'=>'<path d="M12 5v14M5 12h14"/>','list'=>'<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>','stock'=>'<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/>','chat'=>'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>','chart'=>'<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>','users'=>'<circle cx="9" cy="8" r="4"/><path d="M1 21a8 8 0 0 1 16 0M17 4a4 4 0 0 1 0 8M23 21a8 8 0 0 0-5-7.4"/>','phone'=>'<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>','check'=>'<path d="M20 6 9 17l-5-5"/>','alert'=>'<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>','bell'=>'<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4"/>','dl'=>'<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>','refresh'=>'<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M3 21v-5h5"/>','search'=>'<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>','edit'=>'<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>','up'=>'<path d="m6 15 6-6 6 6"/>','down'=>'<path d="m6 9 6 6 6-6"/>','flat'=>'<path d="M5 12h14"/>','target'=>'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>','shield'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>','pulse'=>'<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>','x'=>'<path d="M18 6 6 18M6 6l12 12"/>','store'=>'<path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>','crown'=>'<path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5z"/>','mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'];
$ico = fn($n, $s = 18) => '<svg viewBox="0 0 24 24" width="'.$s.'" height="'.$s.'" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($IC[$n] ?? '').'</svg>';
$tone = ['orange'=>['#FFF1E8','#FF5A00'],'blue'=>['#E6EFF9','#1D5FA8'],'violet'=>['#F1EAF8','#5B3FA6'],'green'=>['#DCFCE7','#166534'],'teal'=>['#DDF1EE','#0E5F58'],'red'=>['#FEE2E2','#B91C1C'],'amber'=>['#FEF3C7','#B45309'],'slate'=>['#EEF2F6','#4B5563']];
$q = http_build_query(array_filter(['range'=>$range, 'store'=>$storeId ?: null, 'from'=>request('from'), 'to'=>request('to')]));
$nT = count($trend);
$spark = function ($a, $col, $w = 62, $h = 22) { if (!$a || count($a) < 2) return ''; $m = max(1, max($a)); $pp = []; foreach (array_values($a) as $i => $y) $pp[] = round($i/(count($a)-1)*$w,1).','.round($h-2-($y/$m)*($h-5),1); return '<svg class="sp" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'" aria-hidden="true"><polyline points="'.implode(' ',$pp).'" fill="none" stroke="'.$col.'" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>'; };
$P5C = ['New'=>['#1D5FA8','#E6EFF9','plus'],'Pending'=>['#C2410C','#FFF1E8','clock'],'Processing'=>['#5B3FA6','#F1EAF8','box'],'Shipment'=>['#0E7490','#E0F2FE','truck'],'Delivered'=>['#166534','#DCFCE7','check']];
$PIPE = ['New'=>'plus','Incomplete'=>'alert','Assign'=>'users','Pending'=>'clock','Scheduled'=>'clock','WFP'=>'clock','Approved'=>'check','Packaging'=>'box','Shipment'=>'truck','Partial Delivered'=>'truck','Delivered'=>'check','Late Delivery'=>'alert','Return Pending'=>'undo','Returned'=>'undo','Cancel'=>'x'];
$qs = fn($extra = []) => http_build_query(array_filter(array_merge(['store'=>$storeId ?: null, 'channel'=>$ch ?: null], $extra)));
@endphp
<style>
.dx{display:flex;flex-direction:column;gap:12px;font-size:12.5px;color:#0B1220}
.dx a{text-decoration:none;color:inherit}
.dx .card{background:#fff;border:1px solid #E6EAF0;border-radius:12px;padding:12px 14px;box-shadow:0 1px 2px rgba(11,18,32,.04);min-width:0}
.dx h2{margin:0;font-size:12.5px;font-weight:700;letter-spacing:0}
.dx .head{display:flex;align-items:center;gap:8px;margin-bottom:9px;min-height:22px}.dx .head h2{flex:1}
.dx .more{font-size:11px;font-weight:700;color:#FF5A00}
.dx .muted{color:#6B7280;font-size:11px}
.dx-top{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#fff;border:1px solid #E6EAF0;border-radius:12px;padding:8px 12px}
.dx-top .ttl{flex:1 1 200px;min-width:0}.dx-top h1{margin:0;font-size:15px;font-weight:800;letter-spacing:-.01em;line-height:1.2}
.dx-top form{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:0}
.dx-pills{display:inline-flex;background:#F3F5F8;border-radius:9px;padding:2px;gap:1px}
.dx-pills a{padding:5px 10px;border-radius:7px;font-size:11.5px;font-weight:700;color:#4B5563}.dx-pills a.on{background:#FF5A00;color:#fff}
.dx select,.dx input[type=date]{width:auto;height:32px;border:1px solid #E6EAF0;border-radius:9px;background:#fff;padding:0 8px;font-size:11.5px;font-weight:600;margin:0}
.dx-ibtn{width:32px;height:32px;border-radius:9px;border:1px solid #E6EAF0;background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;position:relative;color:#0B1220;padding:0;font-size:11.5px}
.dx-ibtn:hover{background:#FFF1E8;border-color:#FDC2A5}.dx-dot{position:absolute;top:6px;right:7px;width:7px;height:7px;border-radius:50%;background:#FF5A00;border:1.5px solid #fff}
.dx-search{display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #E6EAF0;border-radius:9px;padding:0 10px;height:32px;min-width:170px;margin:0;color:#6B7280}
.dx-search input{border:0;outline:0;font:inherit;font-size:12px;flex:1;background:transparent;min-width:0;padding:0;width:auto;height:auto;margin:0}
.dx-menu{position:relative}.dx-menu summary{list-style:none;cursor:pointer}.dx-menu summary::-webkit-details-marker{display:none}
.dx-menu .pop{position:absolute;right:0;top:38px;background:#fff;border:1px solid #E6EAF0;border-radius:10px;box-shadow:0 14px 34px -12px rgba(11,18,32,.25);padding:4px;z-index:30;min-width:140px}
.dx-menu .pop a{display:block;padding:7px 10px;border-radius:7px;font-size:12px;font-weight:600}.dx-menu .pop a:hover{background:#FFF1E8;color:#FF5A00}
.dx-live{display:inline-flex;align-items:center;gap:6px;font-size:11px;color:#6B7280;font-weight:600;margin:0}
.dx-live i{width:7px;height:7px;border-radius:50%;background:#16A34A;box-shadow:0 0 0 0 rgba(22,163,74,.6);animation:dxp 2s infinite}.dx-live.off i{background:#9CA3AF;animation:none}
@keyframes dxp{70%{box-shadow:0 0 0 6px rgba(22,163,74,0)}100%{box-shadow:0 0 0 0 rgba(22,163,74,0)}}
.dx-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
.dx-k{display:flex;flex-direction:column;gap:3px;position:relative;overflow:hidden;padding:10px 12px!important;transition:border-color .15s,box-shadow .15s}
.dx-k:hover{border-color:#FDC2A5;box-shadow:0 6px 16px -10px rgba(11,18,32,.3)}
.dx-k .r1{display:flex;align-items:center;gap:7px}.dx-k .chip{width:24px;height:24px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex:none}
.dx-k .lb{font-size:11px;color:#6B7280;font-weight:600;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.dx-k .v{font-size:19px;font-weight:800;letter-spacing:-.02em;line-height:1.15;margin-top:2px}
.dx-k .sub{font-size:10.5px;color:#9CA3AF;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dx-tr{display:inline-flex;align-items:center;gap:2px;font-size:10.5px;font-weight:700;border-radius:999px;padding:1px 6px;align-self:flex-start;max-width:100%;white-space:nowrap}
.dx-tr.good{background:#DCFCE7;color:#166534}.dx-tr.bad{background:#FEE2E2;color:#B91C1C}.dx-tr.flat{background:#F3F4F6;color:#4B5563}
.dx-k svg.sp{position:absolute;right:10px;bottom:8px;opacity:.9}
.dx-2{display:grid;grid-template-columns:minmax(0,2.2fr) minmax(0,1fr);gap:10px}
.dx-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.dx-p{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.dx-tabs{display:inline-flex;background:#F3F5F8;border-radius:8px;padding:2px;gap:1px}
.dx-tabs button{border:0;background:transparent;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:700;color:#6B7280;cursor:pointer;width:auto;height:auto;margin:0}
.dx-tabs button.on{background:#fff;color:#FF5A00;box-shadow:0 1px 2px rgba(0,0,0,.1)}
.dx-tabs a{padding:4px 9px;border-radius:6px;font-size:11px;font-weight:700;color:#6B7280}.dx-tabs a.on{background:#fff;color:#FF5A00;box-shadow:0 1px 2px rgba(0,0,0,.1)}
.dx-chart{position:relative}.dx-chart svg{display:block;width:100%;height:auto;overflow:visible}
.dx-tip{position:absolute;pointer-events:none;background:#0B1220;color:#fff;border-radius:8px;padding:6px 9px;font-size:11px;line-height:1.5;box-shadow:0 10px 24px -8px rgba(0,0,0,.4);opacity:0;transition:opacity .1s;white-space:nowrap;z-index:5;transform:translate(-50%,-100%)}
.dx-qa{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px}
.dx-qa a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;border:1px solid #EEF1F5;background:#FAFBFC;border-radius:10px;padding:10px 4px;font-size:10.5px;font-weight:700;text-align:center;transition:all .15s;position:relative;line-height:1.2}
.dx-qa a:hover{background:#FFF1E8;border-color:#FF5A00}
.dx-qa a.pri{background:#FF5A00;color:#fff;border-color:#FF5A00}
.dx-qa .qi{width:28px;height:28px;border-radius:8px;background:#fff;color:#FF5A00;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,.08)}
.dx-qa a.pri .qi{background:rgba(255,255,255,.22);color:#fff}
.dx-qa em{font-style:normal;position:absolute;top:4px;right:5px;background:#E11D48;color:#fff;border-radius:20px;font-size:9.5px;padding:0 5px;line-height:15px}
.dx-bar{height:6px;background:#EEF1F5;border-radius:4px;overflow:hidden}.dx-bar>i{display:block;height:100%;border-radius:4px}
.dx-row{display:grid;gap:8px;align-items:center;font-size:12px;margin:6px 0}
.dx-pipe{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:7px}
.dx-pipe a{border:1px solid rgba(11,18,32,.26);border-radius:10px;padding:7px 9px;display:flex;flex-direction:column;gap:2px;background:var(--b);transition:all .15s}
.dx-pipe a:hover{border-color:var(--c);transform:translateY(-1px)}
.dx-pipe .tp{display:flex;align-items:center;gap:5px;color:var(--c)}.dx-pipe .nm{font-size:10.5px;font-weight:700;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dx-pipe .ct{font-size:17px;font-weight:800;line-height:1.1;color:#0B1220}
.dx-t5 .it{display:flex;align-items:center;gap:9px;padding:6px 0;border-top:1px solid #F1F3F6}.dx-t5 .it:first-of-type{border-top:0}
.dx-t5 img{width:30px;height:30px;border-radius:7px;object-fit:cover;background:#F3F5F8;flex:none}
.dx-av{width:30px;height:30px;border-radius:50%;background:#0B1220;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex:none}
.dx-bd{font-size:9.5px;font-weight:800;border-radius:5px;padding:1px 6px;letter-spacing:.02em;white-space:nowrap}
.dx-feed{display:flex;flex-direction:column}.dx-fi{display:flex;gap:9px;padding:6px 0;border-top:1px solid #F1F3F6;align-items:center}.dx-fi:first-child{border-top:0}
.dx-fi .fi{width:24px;height:24px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex:none}
.dx-fi .t{font-size:12px;font-weight:600;line-height:1.3;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical}.dx-fi .w{font-size:10.5px;color:#9CA3AF}
.dx-al{display:flex;gap:8px;align-items:center;border-radius:9px;padding:7px 9px;margin-top:6px;background:var(--b);border:1px solid var(--bd);transition:filter .12s}.dx-al:hover{filter:brightness(.97)}
.dx-lv{font-size:9.5px;font-weight:800;border-radius:5px;padding:2px 6px;flex:none;letter-spacing:.03em}
.dx-cov{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.dx-cov a{border:1px solid #EEF1F5;border-radius:10px;padding:9px 11px;background:#FAFBFC}.dx-cov a:hover{border-color:#FDC2A5;background:#FFF8F3}
.dx-cov b{display:block;font-size:19px;font-weight:800;line-height:1.1}.dx-cov span{font-size:10.5px;color:#6B7280;font-weight:600}
.dx-tbl{width:100%;min-width:640px;border-collapse:collapse;font-size:12px}.dx-tbl th{text-align:left;color:#6B7280;font-size:10.5px;padding:5px 8px;font-weight:700}.dx-tbl td{padding:7px 8px;border-top:1px solid #F1F3F6}
.dx-tbl tbody tr:hover td{background:#FAFBFC}
.dx-sk .card{position:relative;overflow:hidden}.dx-sk .card::after{content:"";position:absolute;inset:0;background:linear-gradient(100deg,transparent 30%,rgba(255,255,255,.75) 50%,transparent 70%);animation:dxs 1s infinite;transform:translateX(-100%)}
@keyframes dxs{to{transform:translateX(100%)}}
.dx-rep{margin-top:10px;padding-top:10px;border-top:1px solid #F1F3F6}.dx-lv{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;margin-top:10px}.dx-lv a{border-radius:9px;padding:6px 4px;text-align:center;text-decoration:none;display:flex;flex-direction:column;line-height:1.2}.dx-lv b{font-size:15px}.dx-lv span{font-size:10.5px;font-weight:700}
.dx-crh{display:flex;gap:10px;align-items:baseline}.dx-crg{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px}.dx-crg>div{background:#F8FAFC;border:1px solid #EEF1F5;border-radius:10px;padding:7px 10px;display:flex;flex-direction:column}.dx-crg b{font-size:16px}.dx-crg span{font-size:11px;color:#6B7280}.dx-cro{display:flex;flex-wrap:wrap;gap:6px 16px;font-size:12px;margin-top:10px;padding-top:8px;border-top:1px solid #F1F3F6}@media (max-width:900px){.dx-crg{grid-template-columns:repeat(3,minmax(0,1fr))}}@media (max-width:560px){.dx-crg{grid-template-columns:repeat(2,minmax(0,1fr))}}
.dx-empty{color:#9CA3AF;font-size:12px;padding:4px 0}
@media (max-width:1280px){.dx-kpis{grid-template-columns:repeat(4,minmax(0,1fr))}.dx-pipe{grid-template-columns:repeat(6,minmax(0,1fr))}}
@media (max-width:1000px){.dx-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.dx-3{grid-template-columns:repeat(2,minmax(0,1fr))}.dx-2{grid-template-columns:minmax(0,1fr)}}
@media (max-width:700px){.dx-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.dx-3,.dx-p{grid-template-columns:minmax(0,1fr)}.dx-pipe{grid-template-columns:repeat(3,minmax(0,1fr))}.dx-search{flex:1 1 140px;min-width:0}.dx-k .v{font-size:17px}.dx-k svg.sp{display:none}}
.dx-p5{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}
.dx-p5 a{position:relative;border:1px solid #E6EAF0;border-top:3px solid var(--c);border-radius:11px;padding:9px 12px;background:#fff;display:flex;flex-direction:column;gap:2px;transition:all .15s}
.dx-p5 a:hover{border-color:var(--c);background:var(--b);transform:translateY(-1px)}
.dx-p5 a:not(:last-child)::after{content:"";position:absolute;right:-12px;top:50%;width:8px;height:8px;border-top:2px solid #C5CCD6;border-right:2px solid #C5CCD6;transform:translateY(-50%) rotate(45deg)}
.dx-p5 .tp{display:flex;align-items:center;gap:6px;color:var(--c);font-weight:800;font-size:11.5px}.dx-p5 .n{font-size:17px;font-weight:800;line-height:1.15;color:#0B1220}.dx-p5 .n small{font-size:11px;font-weight:600;color:#6B7280;margin-left:3px}.dx-p5 .vl{font-size:12.5px;font-weight:700;color:var(--c)}
@media (max-width:900px){.dx-p5{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.dx-p5 a::after{display:none!important}}
#dxroot .dx-ibtn,#dxroot .dx-top select,#dxroot .dx-pills,#dxroot .dx-tabs,#dxroot .dx-search,#dxroot .dx-qa a,#dxroot .dx-menu summary,#dxroot .dx-live{border:1px solid rgba(11,18,32,.28)!important}
#dxroot .dx-search input{border:0!important;box-shadow:none!important;background:transparent!important;padding:0!important;height:auto!important;border-radius:0!important;outline:0!important}
#dxroot .dx-pills a,#dxroot .dx-tabs button{border:0}
#dxroot .dx-qa a:hover,#dxroot .dx-ibtn:hover{border-color:rgba(11,18,32,.5)!important}
/* Header: every control the same 34px height, same radius and border; title has no box */
#dxroot .dx-top{gap:8px;align-items:center}
#dxroot .dx-top h1{background:none!important;border:0!important;padding:0!important;box-shadow:none!important;border-radius:0!important;height:auto!important;width:auto!important;margin:0!important}
#dxroot .dx-top select,#dxroot .dx-top input[type=date],#dxroot .dx-search,#dxroot .dx-pills,#dxroot .dx-live,#dxroot .dx-ibtn,#dxroot .dx-menu summary{height:34px!important;box-sizing:border-box!important;border-radius:9px!important;background:#fff}
#dxroot .dx-pills{background:#F3F5F8!important;align-items:center;padding:2px!important}
#dxroot .dx-pills a{display:inline-flex;align-items:center;height:28px;padding:0 12px;border-radius:7px;line-height:1}
#dxroot a.dx-ibtn,#dxroot button#dxref{width:34px!important;padding:0!important}
#dxroot .dx-menu summary{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:0 12px!important;width:auto}
#dxroot .dx-search{min-width:210px;padding:0 11px!important}
#dxroot .dx-live{display:inline-flex;align-items:center;gap:7px;padding:0 11px;white-space:nowrap;background:#fff}
#dxroot .dx-live input[type=checkbox]{margin:0!important;width:14px!important;height:14px!important;accent-color:#FF5A00}
</style>
<div class="dx" id="dxroot" data-q="{{ $q }}">
<header class="dx-top">
  <div class="ttl"><h1>Dashboard Overview</h1><div class="muted">{{ $now->format('D, j M Y') }} · <span id="dxclock">{{ $now->format('h:i A') }}</span> · {{ $storeId ? ($storeList->firstWhere('id',$storeId)->name ?? 'Store') : ($storeList->count() === 1 ? $storeList->first()->name : 'All stores') }}{{ $ch ? ' · '.$ch : '' }}</div></div>
  <form method="get" action="/admin">
    @if($storeList->count() > 1)<select name="store" onchange="this.form.submit()" aria-label="Store"><option value="">All stores</option>@foreach($storeList as $st)<option value="{{ $st->id }}" {{ $storeId==$st->id?'selected':'' }}>{{ $st->name }}</option>@endforeach</select>@endif
    <select name="channel" onchange="this.form.submit()" aria-label="Channel"><option value="">All channels</option>@foreach($channels as $cn0)<option value="{{ $cn0 }}" {{ $ch===$cn0?'selected':'' }}>{{ $cn0 }}</option>@endforeach</select>
    <input type="hidden" name="range" value="{{ $range }}">
    <span class="dx-pills">@foreach(['today'=>'Today','7'=>'7 Days','30'=>'30 Days'] as $rk=>$rl)<a href="/admin?{{ $qs(['range'=>$rk]) }}" class="{{ $range===(string)$rk ? 'on' : '' }}">{{ $rl }}</a>@endforeach<a href="#" onclick="document.getElementById('dxcu').style.display='flex';return false" class="{{ $isCustom ? 'on' : '' }}">Custom</a></span>
    <span id="dxcu" style="display:{{ $isCustom ? 'flex' : 'none' }};gap:5px;align-items:center"><input type="date" name="from" value="{{ request('from') }}"><input type="date" name="to" value="{{ request('to') }}"><button type="submit" onclick="this.form.range.value='custom'" class="dx-ibtn" style="width:auto;padding:0 10px;font-weight:700">Apply</button></span>
  </form>
  <form action="/admin/orders" class="dx-search">{!! $ico('search',14) !!}<input name="q" aria-label="Search orders" placeholder="Search order, phone, SKU"></form>
  <a href="/admin/orders?status=New" class="dx-ibtn" aria-label="Notifications" title="{{ count($alerts) }} items need attention">{!! $ico('bell',15) !!}@if(count($alerts))<span class="dx-dot"></span>@endif</a>
  <button type="button" class="dx-ibtn" id="dxref" title="Refresh now" aria-label="Refresh">{!! $ico('refresh',15) !!}</button>
  <details class="dx-menu"><summary class="dx-ibtn" style="width:auto;padding:0 10px;gap:5px;font-weight:700" title="Export">{!! $ico('dl',14) !!}Export</summary>
    <div class="pop"><a target="_blank" href="/admin/m/r-orders/export/pdf">PDF</a><a href="/admin/m/r-orders/export/xls">Excel</a><a href="/admin/m/r-orders/export/doc">Word (DOCX)</a><a href="/admin/m/r-orders/export/csv">CSV</a></div></details>
  <label class="dx-live" id="dxlive" title="Auto refresh every 60 seconds"><i></i><span>Updated <b id="dxupd">{{ $now->format('h:i:s A') }}</b></span><input type="checkbox" id="dxauto" checked style="width:auto;height:auto;margin:0"> auto</label>
</header>

<section class="card" id="dxp5" style="padding:10px 14px">
  <div class="head" style="margin-bottom:8px"><h2>Order Pipeline · {{ $label }}</h2><span class="muted">Active: <b style="color:#0B1220">{{ number_format($pipeAct['count']) }} orders</b> · <b style="color:#0B1220">৳{{ number_format($pipeAct['value']) }}</b></span></div>
  <div class="dx-p5">@foreach($pipe5 as $p5)@php $pc = $P5C[$p5['name']]; @endphp
    <a href="/admin/orders?status={{ urlencode($p5['filter']) }}" style="--c:{{ $pc[0] }};--b:{{ $pc[1] }}" title="{{ $p5['name'] }}: {{ $p5['count'] }} orders, ৳{{ number_format($p5['value']) }}"><span class="tp">{!! $ico($pc[2],14) !!}{{ $p5['name'] }}</span><span class="n">{{ number_format($p5['count']) }}<small>Orders</small></span><span class="vl">৳{{ number_format($p5['value']) }}</span></a>@endforeach</div>
</section>

<section class="card" id="dxstages" style="padding:10px 14px">
  <div class="head" style="margin-bottom:8px"><h2>All Order Stages</h2><span class="muted">All time · click a stage to filter</span></div>
    <div class="dx-pipe">@foreach($PIPE as $sg => $pi)@php $cn2 = (int)($counts[$sg] ?? 0); @endphp
      <a href="/admin/orders?status={{ urlencode($sg) }}" style="--c:{{ $ST[$sg][1] ?? '#4B5563' }};--b:{{ $ST[$sg][0] ?? '#F3F4F6' }}"><span class="tp">{!! $ico($pi,13) !!}<span class="nm">{{ $sg }}</span></span><span class="ct">{{ $cn2 }}</span></a>@endforeach</div>
</section>

<section class="dx-kpis" id="dxkpis">
@foreach($kpis as [$l,$v,$sub,$icn,$tr,$href,$tn,$spk])
@php [$dir,$ttx,$good] = $tr; $tc = $tone[$tn]; $cls = $good === null ? 'flat' : ($good ? 'good' : 'bad'); @endphp
<a href="{{ $href }}" class="card dx-k"><div class="r1"><span class="chip" style="background:{{ $tc[0] }};color:{{ $tc[1] }}">{!! $ico($icn,14) !!}</span><span class="lb">{{ $l }}</span></div>
  <span class="v">{{ $v }}</span>
  @if($ttx!=='')<span class="dx-tr {{ $cls }}">{!! $ico($dir>0?'up':($dir<0?'down':'flat'),10) !!}{{ $ttx }}</span>@else<span class="sub">{{ $sub }}</span>@endif
  {!! $spk && $nT > 1 ? $spark($spk, $tc[1]) : '' !!}
</a>
@endforeach
</section>

<section class="dx-2">
  <div class="card">
    <div class="head"><h2>Sales &amp; Orders Trend · {{ $nT }} days</h2>
      <span class="dx-tabs">@foreach(['today'=>'Today','7'=>'7D','30'=>'30D'] as $rk=>$rl)<a href="/admin?{{ $qs(['range'=>$rk]) }}" class="{{ $range===(string)$rk ? 'on' : '' }}">{{ $rl }}</a>@endforeach<a href="#" onclick="document.getElementById('dxcu').style.display='flex';window.scrollTo(0,0);return false" class="{{ $isCustom ? 'on' : '' }}">Custom</a></span>
      <span class="dx-tabs" id="dxtabs"><button type="button" data-m="sales" class="on">Sales &amp; Orders</button>@if($canFin)<button type="button" data-m="profit">Profit</button>@endif<button type="button" data-m="delivery">Delivery</button></span></div>
    <div class="dx-chart" id="dxchart" data-trend="{{ json_encode($trend) }}"></div>
    <div class="muted" id="dxleg" style="margin-top:4px;display:flex;gap:14px;flex-wrap:wrap"></div>
  </div>
  <div class="card"><div class="head"><h2>Quick Actions</h2></div>
    <div class="dx-qa">
      <a class="pri" href="/admin/orders/new"><span class="qi">{!! $ico('plus',15) !!}</span>Create Order</a>
      <a href="/admin/products/new"><span class="qi">{!! $ico('tag',15) !!}</span>Add Product</a>
      <a href="/admin/products/from-image"><span class="qi">{!! $ico('tag',15) !!}</span>Create from Image</a>
      <a href="/admin/orders"><span class="qi">{!! $ico('list',15) !!}</span>View Orders @if($newN)<em>{{ $newN }}</em>@endif</a>
      <a href="/admin/m/customers"><span class="qi">{!! $ico('users',15) !!}</span>Customers</a>
      <a href="/admin/inventory"><span class="qi">{!! $ico('stock',15) !!}</span>Inventory</a>
      <a href="/admin/chat"><span class="qi">{!! $ico('chat',15) !!}</span>Messages @if($chatUnread)<em>{{ $chatUnread }}</em>@endif</a>
      <a href="/admin/call-center/phone"><span class="qi">{!! $ico('phone',15) !!}</span>Call Center</a>
      <a href="/admin/analytics"><span class="qi">{!! $ico('chart',15) !!}</span>Reports</a>
    </div></div>
</section>

<section class="dx-3">
  <div class="card dx-t5"><div class="head"><h2>Top Products</h2><a class="more" href="/admin/analytics/product-sales">All →</a></div>
    @php $tp = max(1, $topProducts->max(2) ?? 1); @endphp
    @forelse($topProducts as $ti => [$pn,$pq,$pg,$pim])<a href="/admin/products?q={{ urlencode($pn) }}" class="it"><img src="{{ $pim ?: '/product-placeholder' }}" alt="" loading="lazy" onerror="this.onerror=null;this.src='/product-placeholder'"><div style="flex:1;min-width:0"><div style="display:flex;gap:6px;font-size:12px"><span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600">{{ $pn }}</span><b>৳{{ number_format($pg) }}</b></div><div style="display:flex;align-items:center;gap:6px;margin-top:3px"><div class="dx-bar" style="flex:1"><i style="width:{{ round($pg/$tp*100) }}%;background:#FF5A00"></i></div><span class="muted">{{ $pq }} sold</span></div></div></a>
    @empty<div class="dx-empty">No sales in this period yet.</div>@endforelse</div>
  <div class="card dx-t5"><div class="head"><h2>Top Customers</h2><a class="more" href="/admin/analytics/top-customers">All →</a></div>
    @php $BD = ['VIP'=>['#FFF1E8','#C2410C'],'Regular'=>['#E6EFF9','#1D5FA8'],'New'=>['#F3F4F6','#4B5563']]; $RK = ['High'=>['#FEE2E2','#B91C1C'],'Medium'=>['#FEF3C7','#B45309']]; @endphp
    @forelse($topCust as [$cn,$cc,$co,$cs,$cr,$cph])<a href="/admin/m/customers?q={{ urlencode($cph) }}" class="it"><span class="dx-av">{{ mb_strtoupper(mb_substr($cn ?: '?',0,1)) }}</span><div style="flex:1;min-width:0"><div style="display:flex;gap:5px;align-items:center"><span style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $cn }}</span><span class="dx-bd" style="background:{{ ($BD[$cc] ?? $BD['New'])[0] }};color:{{ ($BD[$cc] ?? $BD['New'])[1] }}">{{ $cc }}</span>@if(isset($RK[$cr]))<span class="dx-bd" style="background:{{ $RK[$cr][0] }};color:{{ $RK[$cr][1] }}">{{ $cr }} risk</span>@endif</div><div class="muted">{{ $co }} orders</div></div><b>৳{{ number_format($cs) }}</b></a>
    @empty<div class="dx-empty">No customers in this period yet.</div>@endforelse</div>
  <div class="card"><div class="head"><h2>Sales by Category</h2><a class="more" href="/admin/analytics/category-sales">All →</a></div>
    @php $ct = max(1, $cats->sum(1)); $pal = ['#FF5A00','#0B1220','#F59E0B','#16A34A','#9CA3AF']; $R = 15.9; $C = 2*M_PI*$R; $off = 0; @endphp
    @if($cats->count())<div style="display:flex;gap:12px;align-items:center">
      <svg viewBox="0 0 42 42" width="96" height="96" style="flex:none;transform:rotate(-90deg)" aria-hidden="true"><circle cx="21" cy="21" r="{{ $R }}" fill="none" stroke="#EEF1F5" stroke-width="6"/>@foreach($cats as $ci => [$cl,$cv])@php $len = $cv/$ct*$C; @endphp<circle cx="21" cy="21" r="{{ $R }}" fill="none" stroke="{{ $pal[$ci%5] }}" stroke-width="6" stroke-dasharray="{{ round($len,2) }} {{ round($C-$len,2) }}" stroke-dashoffset="{{ -round($off,2) }}"><title>{{ $cl }}: ৳{{ number_format($cv) }}</title></circle>@php $off += $len; @endphp @endforeach</svg>
      <div style="flex:1;min-width:0">@foreach($cats as $ci => [$cl,$cv])<a href="/admin/analytics/category-sales" class="dx-row" style="grid-template-columns:8px 1fr auto;margin:4px 0"><i style="width:8px;height:8px;border-radius:2px;background:{{ $pal[$ci%5] }}"></i><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $cl }}</span><b>{{ round($cv/$ct*100) }}%</b></a>@endforeach</div></div>
    @else<div class="dx-empty">No category sales in this period yet.</div>@endif</div>
</section>
@php $__rg = (string)request('tv', '30'); try { $__tv = \App\Support\ProductTrack::dash($__rg, request('tvf'), request('tvt')); } catch (\Throwable $__e) { $__tv = null; } $__tabs = ['top'=>'Top Viewed','search'=>'Most Searched','cart'=>'Most Added to Cart','low'=>'Low Conversion','rise'=>'Rising Products']; @endphp
@if($__tv)
<section class="card" id="dxtv" style="padding:10px 14px">
  <div class="head" style="margin-bottom:6px;flex-wrap:wrap;gap:6px"><h2>Top Viewed Products <small style="font-weight:500;color:#6B7280">· See which products customers are viewing most.</small></h2>
    <span style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;margin-left:auto">@foreach(['today'=>'Today','yesterday'=>'Yesterday','7'=>'Last 7 Days','30'=>'Last 30 Days'] as $__k=>$__l)<a href="/admin?tv={{ $__k }}" style="font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px;border:1px solid {{ $__tv['range']===(string)$__k ? '#FF5A00' : '#E5E7EB' }};background:{{ $__tv['range']===(string)$__k ? '#FFF1E8' : '#fff' }};color:{{ $__tv['range']===(string)$__k ? '#C2410C' : '#4B5563' }};text-decoration:none">{{ $__l }}</a>@endforeach<form method="get" action="/admin" style="display:inline-flex;gap:3px;align-items:center"><input type="hidden" name="tv" value="custom"><input type="date" name="tvf" value="{{ $__tv['from'] }}" style="font-size:11px;padding:2px 4px;border:1px solid #E5E7EB;border-radius:8px"><input type="date" name="tvt" value="{{ $__tv['to'] }}" style="font-size:11px;padding:2px 4px;border:1px solid #E5E7EB;border-radius:8px"><button style="font-size:11px;font-weight:600;padding:3px 9px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;cursor:pointer">Custom</button></form><a class="more" href="/admin/analytics/top-viewed?from={{ $__tv['from'] }}&to={{ $__tv['to'] }}">Full report →</a></span></div>
  <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;margin-bottom:8px"><span><b style="font-size:18px;font-weight:700;color:#0B1220">{{ number_format($__tv['tot']['views']) }}</b> Views</span><span><b style="font-size:18px;font-weight:700;color:#0B1220">{{ number_format($__tv['tot']['uniq']) }}</b> Unique</span><span><b style="font-size:18px;font-weight:700;color:#0B1220">{{ number_format($__tv['tot']['cart']) }}</b> Cart</span><span><b style="font-size:18px;font-weight:700;color:#0B1220">{{ number_format($__tv['tot']['orders']) }}</b> Orders</span></div>
  <div id="dxtvt" style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:6px">@foreach($__tabs as $__k=>$__l)<button type="button" data-t="{{ $__k }}" onclick="var b=this,w=document.getElementById('dxtv');w.querySelectorAll('#dxtvt button').forEach(function(x){var on=x===b;x.style.background=on?'#FF5A00':'#fff';x.style.color=on?'#fff':'#4B5563';x.style.borderColor=on?'#FF5A00':'#E5E7EB'});w.querySelectorAll('.dxtvp').forEach(function(p){p.style.display=p.getAttribute('data-t')===b.getAttribute('data-t')?'':'none'})" style="font-size:11.5px;font-weight:600;padding:4px 10px;border-radius:999px;border:1px solid {{ $__k==='top' ? '#FF5A00' : '#E5E7EB' }};background:{{ $__k==='top' ? '#FF5A00' : '#fff' }};color:{{ $__k==='top' ? '#fff' : '#4B5563' }};cursor:pointer">{{ $__l }}</button>@endforeach</div>
  @foreach($__tabs as $__k=>$__l)
  <div class="dxtvp" data-t="{{ $__k }}" @if($__k!=='top') style="display:none" @endif>
  @if(count($__tv['lists'][$__k]))
  <table class="no-export" style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr style="text-align:left;color:#6B7280"><th style="padding:4px 6px;font-weight:600">Product</th><th style="padding:4px 6px;font-weight:600;text-align:right">Views</th><th style="padding:4px 6px;font-weight:600;text-align:right">Unique</th><th style="padding:4px 6px;font-weight:600;text-align:right">Cart</th><th style="padding:4px 6px;font-weight:600;text-align:right">Orders</th><th style="padding:4px 6px;font-weight:600;text-align:right">Conv.</th><th style="padding:4px 6px;font-weight:600">Status</th></tr></thead><tbody>
  @foreach($__tv['lists'][$__k] as $__r)<tr style="border-top:1px solid #EEF0F3"><td style="padding:5px 6px"><span style="display:flex;align-items:center;gap:8px"><img src="{{ $__r['image'] ?: '/product-placeholder' }}" alt="" width="28" height="28" loading="lazy" style="border-radius:6px;object-fit:contain;background:#fff" onerror="this.onerror=null;this.src='/product-placeholder'"><a href="/admin/products?q={{ urlencode($__r['product']) }}" style="color:#0B1220;font-weight:600;text-decoration:none">{{ \Illuminate\Support\Str::limit($__r['product'], 44) }}</a></span></td><td style="padding:5px 6px;text-align:right;font-variant-numeric:tabular-nums">{{ number_format($__r['views']) }}</td><td style="padding:5px 6px;text-align:right;font-variant-numeric:tabular-nums">{{ number_format($__r['uniq']) }}</td><td style="padding:5px 6px;text-align:right;font-variant-numeric:tabular-nums">{{ number_format($__r['cart']) }}</td><td style="padding:5px 6px;text-align:right;font-variant-numeric:tabular-nums">{{ number_format($__r['orders']) }}</td><td style="padding:5px 6px;text-align:right;font-variant-numeric:tabular-nums">{{ $__r['conv'] }}%</td><td style="padding:5px 6px"><span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;{{ str_contains($__r['status'], 'Low Stock') ? 'background:#FEE2E2;color:#B91C1C' : (str_contains($__r['status'], 'Low Conversion') ? 'background:#FEF3C7;color:#B45309' : (in_array($__r['status'], ['High Demand','Rising']) ? 'background:#DCFCE7;color:#166534' : 'background:#F3F4F6;color:#4B5563')) }}">{{ $__r['status'] }}</span>@if($__k==='rise')<b style="color:#166534;font-size:11px"> +{{ round($__r['growth']) }}%</b>@endif</td></tr>@endforeach
  </tbody></table>
  @else<div class="dx-empty">{{ ['top'=>'No product views in these dates yet.','search'=>'No search clicks yet. Tracking started on 6 Nov, so data builds up over the next days.','cart'=>'No add-to-cart events yet. Tracking started on 6 Nov, so data builds up over the next days.','low'=>'No product has high interest but low sales yet. A product needs about 50 unique visitors to appear here.','rise'=>'No rising product yet. A product needs 20+ views and 40% more than the previous period.'][$__k] }}</div>@endif
  </div>
  @endforeach
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;font-size:12px">
    @if($__tv['high'])<div style="flex:1;min-width:190px;border:1px solid #E5E7EB;border-radius:10px;padding:8px 10px"><div style="color:#6B7280">Highest Demand</div><b>{{ \Illuminate\Support\Str::limit($__tv['high']['product'], 34) }}</b><div>{{ number_format($__tv['high']['views']) }} views</div></div>@endif
    @if($__tv['opp'])<div style="flex:1;min-width:190px;border:1px solid #FDE68A;background:#FFFBEB;border-radius:10px;padding:8px 10px"><div style="color:#B45309">Opportunity Product</div><b>{{ \Illuminate\Support\Str::limit($__tv['opp']['product'], 34) }}</b><div>{{ number_format($__tv['opp']['views']) }} views / {{ number_format($__tv['opp']['orders']) }} orders</div><div style="margin-top:4px;display:flex;gap:6px"><a href="/admin/products?q={{ urlencode($__tv['opp']['product']) }}" style="color:#C2410C;font-weight:600">Review Price</a><a href="/admin/discounts" style="color:#C2410C;font-weight:600">Create Offer</a></div></div>@endif
    @if($__tv['rise'])<div style="flex:1;min-width:190px;border:1px solid #BBF7D0;background:#F0FDF4;border-radius:10px;padding:8px 10px"><div style="color:#166534">Rising Product</div><b>{{ \Illuminate\Support\Str::limit($__tv['rise']['product'], 34) }}</b><div>+{{ round($__tv['rise']['growth']) }}% views</div></div>@endif
  </div>
</section>
@endif

@if($canFin)
@php
try { $__ao = \App\Support\AreaOrders::summary(0); $__at = \App\Support\AreaOrders::top($__ao['rows'], 'customers', 10); } catch (\Throwable $e) { $__at = []; }
$__am = max(1, $__at ? $__at[0]['customers'] : 1);
@endphp
@if($__at)
<section class="card" id="dxarea"><div class="head"><h2>Top 10 areas by customers <small style="font-weight:500;color:#6B7280">· campaign targets</small></h2><a class="more" href="/admin/analytics/area-orders">All 64 districts →</a></div>
<div class="dx-ar">@foreach($__at as $__i => $__x)<a href="/admin/analytics/area-orders" class="dx-ari"><span class="dx-arr">{{ $__i+1 }}</span><div style="flex:1;min-width:0"><div style="display:flex;gap:6px;font-size:12.5px"><b>{{ $__x['name'] }}</b><span class="muted">{{ $__x['bn'] }}</span><b style="margin-left:auto">{{ number_format($__x['customers']) }}</b></div><div class="dx-bar" style="margin-top:3px"><i style="width:{{ round($__x['customers']/$__am*100) }}%;background:linear-gradient(90deg,#FFB27A,#FF5A00)"></i></div><div class="muted" style="font-size:11px">{{ number_format($__x['orders']) }} orders · ৳{{ number_format($__x['sales']) }}</div></div></a>@endforeach</div></section>
<style>.dx-ar{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:2px 22px}.dx-ari{display:flex;gap:9px;align-items:center;padding:6px 0;border-top:1px solid #F1F3F6;text-decoration:none;color:inherit}.dx-arr{width:22px;height:22px;border-radius:50%;background:#FFF1E8;color:#C2410C;font-size:11.5px;font-weight:800;display:flex;align-items:center;justify-content:center;flex:none}@media (max-width:800px){.dx-ar{grid-template-columns:minmax(0,1fr)}}</style>
@endif
@endif

<section class="dx-p">
  <div class="card dx-cr" style="grid-column:1/-1"><div class="head"><h2>Courier Performance <small style="font-weight:500;color:#6B7280">· {{ $label }}</small></h2><a class="more" href="/admin/courier-dashboard">Open courier dashboard →</a></div>
    @php $__cn = fn($v) => '৳'.number_format($v); @endphp
    @forelse($cov['couriers'] ?? [] as $__c)
    <div class="dx-crh"><b style="font-size:14px">{{ $__c['name'] }}</b><span class="muted">{{ number_format($__c['total']) }} parcels</span><b style="margin-left:auto;color:{{ ($__c['success'] ?? 0) >= 90 ? '#166534' : (($__c['success'] ?? 0) >= 75 ? '#C2410C' : '#A3281E') }}">{{ $__c['success'] !== null ? $__c['success'].'% success' : 'no finished parcels' }}</b></div>
    <div class="dx-bar" style="margin:4px 0 10px"><i style="width:{{ min(100, $__c['success'] ?? 0) }}%;background:#16A34A"></i></div>
    <div class="dx-crg">
      @foreach([['Pickup requested',$__c['pickup'],'#1D5FA8'],['In transit',$__c['transit'],'#1D5FA8'],['At hub',$__c['hub'],'#1D5FA8'],['Out for delivery',$__c['ofd'],'#0E7490'],['Delivered',$__c['delivered'],'#166534'],['Returned',$__c['returned'],'#A3281E'],['Failed / Hold',$__c['failed'],'#C2410C'],['Cancelled',$__c['cancelled'],'#6B7280']] as [$__l,$__v,$__k])<div><b style="color:{{ $__k }}">{{ number_format($__v) }}</b><span>{{ $__l }}</span></div>@endforeach
      <div><b>{{ $__c['avg'] !== null ? $__c['avg'].' d' : '—' }}</b><span>Avg delivery time</span></div>
      <div><b>{{ $__cn($__c['cod']) }}</b><span>COD collected</span></div>
      <div><b style="color:#C2410C">{{ $__cn($__c['pending']) }}</b><span>Payment pending</span></div>
      <div><b style="color:#166534">{{ $__cn($__c['received']) }}</b><span>Payment received</span></div>
    </div>
    @empty<div class="dx-empty">No active courier. Turn one on in Courier settings.</div>@endforelse
    @if(!empty($cov['couriers']))<div class="dx-cro"><span class="muted">Return outcomes:</span>@foreach(['Partial Return','Regular Return','Paid Return','Exchange'] as $__o)<span><b>{{ number_format($cov['outcomes'][$__o] ?? 0) }}</b> {{ $__o }}</span>@endforeach<span style="margin-left:auto">@if(($cov['flagged'] ?? 0) > 0)<a href="/admin/courier-dashboard" style="color:#B45309;font-weight:700">⚠ {{ $cov['flagged'] }} flagged</a>@else<span class="muted">No flagged parcels</span>@endif</span></div>@endif</div>
  @if($storeList->count() > 1)
  <div class="card"><div class="head"><h2>Store Performance</h2><a class="more" href="/admin/analytics">Compare →</a></div>
    @php $smx = max(1, $storePerf->max(2) ?? 1); @endphp
    @forelse($storePerf as [$sn,$so,$sv,$sd])<a href="/admin/orders" style="display:block;margin:7px 0"><div style="display:flex;gap:6px;font-size:12px"><b style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $sn }}</b><b>৳{{ number_format($sv) }}</b></div><div class="dx-bar" style="margin:3px 0"><i style="width:{{ round($sv/$smx*100) }}%;background:#0B1220"></i></div><span class="muted">{{ $so }} orders · {{ $sd === null ? 'no deliveries yet' : $sd.'% delivered' }}</span></a>
    @empty<div class="dx-empty">No store data for this period.</div>@endforelse</div>
  @endif
</section>

<section class="dx-3">
  <div class="card"><div class="head"><h2>Customer Overview</h2><span class="muted">{{ $label }}</span></div>
    <div class="dx-cov">
      <a href="/admin/m/customers"><b style="color:#FF5A00">{{ $cust['new'] }}</b><span>New customers</span></a>
      <a href="/admin/m/customers"><b>{{ $cust['returning'] }}</b><span>Returning</span></a>
      <a href="/admin/m/customers"><b style="color:#C2410C">{{ $cust['vip'] }}</b><span>VIP customers</span></a>
      <a href="/admin/m/customers"><b style="color:#1D5FA8">{{ $cust['member'] }}</b><span>Members</span></a>
    </div>
    @if(($cust['ordered'] ?? 0) > 0)
    <div class="dx-rep"><div style="display:flex;justify-content:space-between;font-size:12px"><span><b>Repeat customers</b> <span class="muted">(ordered 2+ times, all time)</span></span><b style="color:#16A34A">{{ $cust['repeat_pct'] }}%</b></div><div class="dx-bar" style="margin:4px 0"><i style="width:{{ min(100, $cust['repeat_pct']) }}%;background:#16A34A"></i></div><div class="muted" style="font-size:11px">{{ number_format($cust['repeat']) }} of {{ number_format($cust['ordered']) }} customers who ordered</div></div>
    <div class="dx-lv">@foreach(['silver'=>['Silver','#64748B','#F1F5F9'],'gold'=>['Gold','#B45309','#FEF3C7'],'platinum'=>['Platinum','#0E7490','#E0F2FE'],'diamond'=>['Diamond','#6D28D9','#EDE9FE']] as $__k=>[$__n,$__c,$__b])<a href="/admin/m/customers" style="background:{{ $__b }};color:{{ $__c }}"><b>{{ number_format($cust['levels'][$__k] ?? 0) }}</b><span>{{ $__n }}</span></a>@endforeach</div>
    @endif
    </div>
  <div class="card"><div class="head"><h2>Recent Activity</h2></div>
    <div class="dx-feed">@php $FI = ['order'=>['cart','#FFF1E8','#FF5A00'],'edit'=>['edit','#E6EFF9','#1D5FA8'],'stock'=>['stock','#DDF1EE','#0E5F58'],'export'=>['dl','#F1EAF8','#5B3FA6'],'incomplete'=>['alert','#FEF3C7','#B45309'],'call'=>['phone','#DCFCE7','#166534']]; @endphp
    @forelse($feed->take(6) as [$ty,$tx,$ago,$abs,$lk])@php $fi = $FI[$ty] ?? $FI['edit']; @endphp
    <a href="{{ $lk ?: '#' }}" class="dx-fi"><span class="fi" style="background:{{ $fi[1] }};color:{{ $fi[2] }}">{!! $ico($fi[0],13) !!}</span><div style="min-width:0;flex:1"><div class="t">{{ $tx }}</div><div class="w" title="{{ $abs }}">{{ $ago }}</div></div></a>
    @empty<div class="dx-empty">No activity yet.</div>@endforelse</div></div>
  <div class="card"><div class="head"><h2>Attention Required</h2>@if(count($alerts))<span class="dx-lv" style="background:#FEE2E2;color:#B91C1C">{{ count($alerts) }}</span>@endif</div>
    @forelse(array_slice($alerts,0,8) as [$lvl,$tt,$sub,$lk])<a href="{{ $lk }}" class="dx-al" style="--b:{{ $LV[$lvl][0] }};--bd:{{ $LV[$lvl][0] }}"><span class="dx-lv" style="background:#fff;color:{{ $LV[$lvl][1] }}">{{ $lvl }}</span><div style="min-width:0"><div style="font-size:12px;font-weight:700;color:{{ $LV[$lvl][1] }}">{{ $tt }}</div><div class="muted">{{ $sub }}</div></div></a>
    @empty<div style="display:flex;gap:6px;align-items:center;color:#166534;font-weight:700;font-size:12px">{!! $ico('check',14) !!} All clear right now.</div>@endforelse</div>
</section>

<section>
  <div class="card" style="overflow:hidden">
    <div class="head"><h2>Live Orders</h2><span class="dx-live"><i></i>Live</span><a class="more" href="/admin/orders">Open work queue →</a></div>
    <div style="overflow-x:auto"><table class="dx-tbl">
      <thead><tr><th>Order</th><th>Customer</th><th style="text-align:right">Total</th><th>Payment</th><th>Risk</th><th>Status</th><th style="text-align:right">Age</th></tr></thead>
      <tbody>@forelse($orders as $o)@php $b = $bad[$o->phone] ?? 0; $risk2 = $b >= 2 ? 'High' : ($b == 1 ? 'Medium' : 'Low'); $rf = ['Low'=>'#166534','Medium'=>'#C2410C','High'=>'#A3281E'][$risk2]; $ph = $o->phone; $c = $ST[$o->status] ?? ['#F3F4F6','#374151']; @endphp
      <tr><td style="font-weight:700"><a href="/admin/orders/{{ $o->id }}" style="color:#FF5A00">{{ $o->invoice }}</a></td><td style="font-weight:600">{{ $ph }}</td><td style="text-align:right;font-weight:700">৳{{ number_format($o->total) }}</td><td>@include('pay_badge',['o'=>$o,'h'=>18])</td><td style="font-weight:700;color:{{ $rf }}">{{ $risk2 }}</td>
      <td><span class="dx-bd" style="background:{{ $c[0] }};color:{{ $c[1] }}">{{ $o->status }}</span></td><td style="text-align:right;color:#6B7280">{{ \Carbon\Carbon::parse($o->created_at)->diffForHumans(null, true, true) }}</td></tr>
      @empty<tr><td colspan="7" class="dx-empty">No orders yet. <a style="color:#FF5A00;font-weight:700" href="/admin/orders/new">Create order</a></td></tr>@endforelse</tbody></table></div>
  </div>
</section>
</div>
@verbatim
<script>
(function(){
var COL={sales:'#F59E6B',orders:'#7C8DA6',profit:'#4FB57F',del:'#3FA79B'};
function fmt(n){return '৳'+Math.round(n).toLocaleString('en-US')}
function smooth(p,lo,hi){var K=function(v){return Math.max(lo,Math.min(hi,v))};if(p.length<2)return '';var d='M'+p[0][0]+','+p[0][1];for(var i=0;i<p.length-1;i++){var a=p[i-1]||p[i],b=p[i],c=p[i+1],e=p[i+2]||c;d+=' C'+(b[0]+(c[0]-a[0])/6)+','+K(b[1]+(c[1]-a[1])/6)+' '+(c[0]-(e[0]-b[0])/6)+','+K(c[1]-(e[1]-b[1])/6)+' '+c[0]+','+c[1]}return d}
function draw(){
 var box=document.getElementById('dxchart');if(!box)return;var T=JSON.parse(box.dataset.trend||'[]');var m=(document.querySelector('#dxtabs .on')||{dataset:{m:'sales'}}).dataset.m;
 var W=960,H=165,L=40,R=12,Tp=10,B=24,n=T.length,cw=(W-L-R)/Math.max(1,n),iw=W-L-R,ih=H-Tp-B;
 var A,Bs,la,lb,fa,fb;
 if(m==='sales'){A=T.map(function(t){return t.sales});Bs=T.map(function(t){return t.orders});la='Sales (৳)';lb='Orders';fa=fmt;fb=function(v){return v+' orders'}}
 else if(m==='profit'){A=T.map(function(t){return t.profit});Bs=null;la='Gross profit (৳)';fa=fmt}
 else{A=T.map(function(t){return t.orders});Bs=T.map(function(t){return t.delivered});la='Orders';lb='Delivered';fa=function(v){return v+' orders'};fb=function(v){return v+' delivered'}}
 var mxA=Math.max(1,Math.max.apply(null,A)),mxB=Bs?Math.max(1,Math.max.apply(null,Bs)):1;
 var s='<svg viewBox="0 0 '+W+' '+H+'">';
 for(var g=0;g<=4;g++){var y=Tp+ih*(1-g/4);s+='<line x1="'+L+'" x2="'+(W-R)+'" y1="'+y+'" y2="'+y+'" stroke="#EFE9E4" stroke-dasharray="2 4"/><text x="'+(L-6)+'" y="'+(y+3)+'" text-anchor="end" font-size="9.5" fill="#9CA3AF">'+(m==='sales'||m==='profit'?(mxA*g/4>=1000000?(Math.round(mxA*g/4/10000)/100)+'M':mxA*g/4>=1000?Math.round(mxA*g/4/1000)+'k':Math.round(mxA*g/4)):Math.round(mxA*g/4))+'</text>'}
 var cA=(m==='profit'?COL.profit:COL.sales),cB=(m==='sales'?COL.orders:COL.del);
 var pa=A.map(function(v,i){return [L+cw*i+cw/2,Tp+ih*(1-Math.max(0,v)/mxA)]});
 var line=smooth(pa,Tp,Tp+ih),base=Tp+ih;
 s+='<defs><linearGradient id="dxga" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'+cA+'" stop-opacity=".30"/><stop offset="1" stop-color="'+cA+'" stop-opacity="0"/></linearGradient></defs>';
 if(pa.length>1){s+='<path d="'+line+' L'+pa[pa.length-1][0].toFixed(1)+','+base+' L'+pa[0][0].toFixed(1)+','+base+' Z" fill="url(#dxga)"/>';s+='<path d="'+line+'" fill="none" stroke="'+cA+'" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'}
 if(Bs){var pts=Bs.map(function(v,i){return [L+cw*i+cw/2,Tp+ih*(1-v/mxB)]});s+='<path d="'+smooth(pts,Tp,Tp+ih)+'" fill="none" stroke="'+cB+'" stroke-width="1.4" stroke-dasharray="1 4.5" stroke-linecap="round"/>'}
 if(pa.length){var lp=pa[pa.length-1];s+='<circle cx="'+lp[0].toFixed(1)+'" cy="'+lp[1].toFixed(1)+'" r="4.2" fill="#fff" stroke="'+cA+'" stroke-width="2"/>'}
 s+='<line id="dxch" x1="0" x2="0" y1="'+Tp+'" y2="'+base+'" stroke="#D1D5DB" stroke-width="1" stroke-dasharray="3 3" opacity="0"/><circle id="dxcd" r="4.5" fill="'+cA+'" stroke="#fff" stroke-width="2" opacity="0"/>';
 var step=Math.ceil(n/9);T.forEach(function(t,i){if(i%step===0||i===n-1)s+='<text x="'+(L+cw*i+cw/2).toFixed(1)+'" y="'+(H-8)+'" text-anchor="middle" font-size="9.5" fill="#6B7280">'+t.d+'</text>'});
 T.forEach(function(t,i){s+='<rect class="hz" data-i="'+i+'" x="'+(L+cw*i).toFixed(1)+'" y="'+Tp+'" width="'+cw.toFixed(1)+'" height="'+ih+'" fill="transparent"/>'});
 s+='</svg><div class="dx-tip" id="dxtip"></div>';box.innerHTML=s;
 document.getElementById('dxleg').innerHTML='<span><i style="display:inline-block;width:16px;height:3px;border-radius:2px;background:'+(m==='profit'?COL.profit:COL.sales)+';margin-right:6px;vertical-align:middle"></i>'+la+'</span>'+(Bs?'<span><i style="display:inline-block;width:16px;height:0;border-top:2px dotted '+(m==='sales'?COL.orders:COL.del)+';margin-right:6px;vertical-align:middle"></i>'+lb+'</span>':'');
 var tip=document.getElementById('dxtip'),svg=box.querySelector('svg');
 box.querySelectorAll('.hz').forEach(function(r){r.addEventListener('mousemove',function(){var i=+r.dataset.i,t=T[i];var rb=svg.getBoundingClientRect(),k=rb.width/W;tip.innerHTML='<b>'+t.d+'</b><br>'+la+': <b>'+fa(A[i])+'</b>'+(Bs?'<br>'+lb+': <b>'+fb(Bs[i])+'</b>':'');tip.style.left=((L+cw*i+cw/2)*k)+'px';tip.style.top=((Tp+ih*(1-A[i]/mxA))*k-6)+'px';var cx=L+cw*i+cw/2,ch=svg.querySelector('#dxch'),cd=svg.querySelector('#dxcd');ch.setAttribute('x1',cx);ch.setAttribute('x2',cx);ch.setAttribute('opacity',1);cd.setAttribute('cx',cx);cd.setAttribute('cy',Tp+ih*(1-Math.max(0,A[i])/mxA));cd.setAttribute('opacity',1);tip.style.opacity=1});r.addEventListener('mouseleave',function(){tip.style.opacity=0;var ch=svg.querySelector('#dxch'),cd=svg.querySelector('#dxcd');if(ch)ch.setAttribute('opacity',0);if(cd)cd.setAttribute('opacity',0)})});
}
function wire(){document.querySelectorAll('#dxtabs button').forEach(function(b){b.onclick=function(){document.querySelectorAll('#dxtabs button').forEach(function(x){x.classList.remove('on')});b.classList.add('on');draw()}});draw()}
wire();
var root=document.getElementById('dxroot'),busy=false;
function auto(){try{return localStorage.getItem('dxauto')!=='0'}catch(e){return true}}
var cb=document.getElementById('dxauto');if(cb){cb.checked=auto();cb.onchange=function(){try{localStorage.setItem('dxauto',cb.checked?'1':'0')}catch(e){}document.getElementById('dxlive').classList.toggle('off',!cb.checked)};document.getElementById('dxlive').classList.toggle('off',!cb.checked)}
function refresh(quiet){if(busy)return;busy=true;if(quiet!==true)root.classList.add('dx-sk');
 var tab=(document.querySelector('#dxtabs .on')||{dataset:{}}).dataset.m;
 fetch(location.href,{headers:{'X-Requested-With':'fetch'},cache:'no-store'}).then(function(r){return r.text()}).then(function(h){var d=new DOMParser().parseFromString(h,'text/html').getElementById('dxroot');if(!d)return;
  ['dxkpis'].forEach(function(id){var a=document.getElementById(id),b=d.querySelector('#'+id);if(a&&b)a.innerHTML=b.innerHTML});
  var secs=root.querySelectorAll(':scope > section'),ns=d.querySelectorAll(':scope > section');secs.forEach(function(s,i){if(s.id!=='dxkpis'&&ns[i])s.innerHTML=ns[i].innerHTML});
  if(tab){var tb=root.querySelector('#dxtabs [data-m="'+tab+'"]');if(tb){root.querySelectorAll('#dxtabs button').forEach(function(x){x.classList.remove('on')});tb.classList.add('on')}}
  wire();var t=new Date();document.getElementById('dxupd').textContent=t.toLocaleTimeString('en-US')}).catch(function(){}).then(function(){busy=false;root.classList.remove('dx-sk')})}
document.getElementById('dxref').onclick=function(){refresh()};
setInterval(function(){if(cb&&cb.checked&&!document.hidden)refresh(true)},60000);
})();
</script>
@endverbatim
@endsection

