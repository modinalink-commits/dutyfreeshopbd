@extends('layout')@section('title', 'Report Center')
@section('body')
<style>
.rh{font-size:13px;color:#0C161F}
.rh .top{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.rh .top h1{margin:2px 0 0;font-size:22px;line-height:1.2}.rh .mut{color:#6B7280;font-size:11.5px}.rh .crumb{font-size:12px;color:#6B7280}
.rh .btn2{display:inline-flex;align-items:center;min-height:40px;padding:0 14px;border:1px solid #D5D8DC;border-radius:8px;background:#fff;color:#0C161F;font-weight:600;text-decoration:none;cursor:pointer;font-size:13px}
.rh .btn2.p{background:#C2410C;border-color:#C2410C;color:#fff;font-weight:700}
.rh .srch{display:flex;gap:8px;margin-bottom:12px}.rh .srch input{flex:1;min-height:44px;padding:0 14px;border:1px solid #D5D8DC;border-radius:10px;font:inherit;font-size:14px;box-sizing:border-box}
.rh .kp{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));border:1px solid #E6E7E9;border-radius:10px;overflow:hidden;background:#fff;margin-bottom:14px}
.rh .kp>div{padding:8px 12px;border-right:1px solid #E6E7E9}.rh .kp>div:last-child{border-right:0}
.rh .kp span{display:block;font-size:11.5px;color:#4B5563}.rh .kp b{display:block;font-size:19px;line-height:1.25}
.rh .box{background:#fff;border:1px solid #E6E7E9;border-radius:12px;padding:12px 14px;margin-bottom:14px}
.rh .box h2{margin:0 0 8px;font-size:14px}
.rh .gl{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:8px}
.rh .gl a{display:flex;justify-content:space-between;align-items:center;gap:8px;min-height:44px;padding:0 14px;border:1px solid #F5C4AA;background:#FFEEE6;border-radius:10px;color:#0C161F;font-weight:600;text-decoration:none}
.rh .gl a b{background:#fff;border-radius:999px;padding:1px 10px;font-size:12px}
.rh .two{display:flex;flex-wrap:wrap;gap:14px}.rh .two>.box{flex:1 1 340px;min-width:0}
.rh ul{list-style:none;margin:0;padding:0}.rh li{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid #F1F3F6}.rh li:last-child{border-bottom:0}
.rh li a{color:#0C161F;font-weight:600;text-decoration:none}.rh li a:hover{text-decoration:underline}
.rh .empty{color:#6B7280;padding:6px 0}
</style>
<div class="rh">
@if(!$ready)<div class="box">The report registry is not set up on this server yet.</div>
@else
<div class="top">
  <div style="flex:1;min-width:260px">
    <div class="crumb">Reports &amp; Analytics</div>
    <h1>Report Center</h1>
    <span class="mut" style="font-size:12.5px">One place to find every report. Your existing report pages and menus work exactly as before.</span>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn2 p" href="{{ url('/admin/report-center/all-reports') }}">All Reports</a>
    <a class="btn2" href="{{ url('/admin/report-center/all-reports?fav=1') }}">★ My Reports</a>
    @if($isAdmin)<a class="btn2" href="{{ url('/admin/report-center/audit') }}">Report System Audit</a>@endif
  </div>
</div>

<form class="srch" method="get" action="{{ url('/admin/report-center/all-reports') }}">
  <input type="text" name="q" placeholder="Search reports by name, code or module…" aria-label="Search reports" autocomplete="off">
  <button class="btn2 p" type="submit">Search</button>
</form>

<div class="kp">
  <div style="box-shadow:inset 0 3px 0 #F95505"><span>Total reports</span><b>{{ number_format($counts['total']) }}</b></div>
  <div><span>Active</span><b>{{ number_format($counts['active']) }}</b></div>
  <div><span>★ Favorites</span><b>{{ number_format($counts['favs']) }}</b></div>
  <div><span>Full export built in</span><b>{{ number_format($counts['native']) }}</b></div>
  <div><span>Use the standard Export bar</span><b>{{ number_format($counts['nonative']) }}</b></div>
</div>

<div class="box">
  <h2>Report groups</h2>
  <div class="gl">@foreach($groups as $gc => $gl)@if(($gcount[$gc] ?? 0) > 0)<a href="{{ url('/admin/report-center/all-reports') }}?group={{ $gc }}"><span>{{ $gl }}</span><b>{{ $gcount[$gc] }}</b></a>@endif @endforeach</div>
</div>

<div class="two">
  <div class="box">
    <h2>Recently viewed</h2>
    @if($recent->count())<ul>@foreach($recent as $x)<li><a href="{{ url('/admin/report-center/open/' . $x->report_code) }}">{{ $x->name }}</a><span class="mut">{{ $x->report_code }} · {{ \Carbon\Carbon::parse($x->viewed_at)->timezone('Asia/Dhaka')->format('d M, h:i A') }}</span></li>@endforeach</ul>
    @else<div class="empty">Reports you open will appear here.</div>@endif
  </div>
  <div class="box">
    <h2>★ Favorite reports</h2>
    @if($favRows->count())<ul>@foreach($favRows as $x)<li><a href="{{ url('/admin/report-center/open/' . $x->report_code) }}">{{ $x->name }}</a><span class="mut">{{ $x->report_code }}</span></li>@endforeach</ul>
    @else<div class="empty">Click the ☆ next to a report in All Reports to keep it here.</div>@endif
  </div>
</div>
@endif
</div>
@endsection
