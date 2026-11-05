@extends('layout')@section('title', 'All Reports')
@section('body')
<?php $f = fn($k) => request()->query($k); $exportUrl = url('/admin/report-center/export') . '?' . http_build_query(request()->except('partial', 'page')); ?>
<style>
.rc{font-size:13px;color:#0C161F}
.rc .top{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.rc .top h1{margin:2px 0 0;font-size:22px;line-height:1.2}.rc .mut{color:#6B7280;font-size:11.5px}
.rc .crumb{font-size:12px;color:#6B7280}
.rc .btn2{display:inline-flex;align-items:center;min-height:40px;padding:0 14px;border:1px solid #D5D8DC;border-radius:8px;background:#fff;color:#0C161F;font-weight:600;text-decoration:none;cursor:pointer;font-size:13px}
.rc .btn2.p{background:#C2410C;border-color:#C2410C;color:#fff;font-weight:700}
.rc .kp{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));border:1px solid #E6E7E9;border-radius:10px;overflow:hidden;background:#fff;margin-bottom:12px}
.rc .kp>div{padding:8px 12px;border-right:1px solid #E6E7E9}.rc .kp>div:last-child{border-right:0}
.rc .kp span{display:block;font-size:11.5px;color:#4B5563}.rc .kp b{display:block;font-size:19px;line-height:1.25}
.rc .gc{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}
.rc .gc a{border:1px solid #F5C4AA;background:#FFEEE6;color:#0C161F;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap}
.rc .gc a.on{background:#0C161F;border-color:#0C161F;color:#fff}
.rc form.flt{display:flex;flex-wrap:wrap;gap:8px;align-items:end;background:#fff;border:1px solid #E6E7E9;border-radius:12px;padding:10px 12px;margin-bottom:12px}
.rc form.flt label{display:block;margin:0 0 3px;font-size:11.5px;color:#4B5563}
.rc form.flt select,.rc form.flt input{width:auto;min-width:120px;min-height:40px;padding:0 10px;border:1px solid #D5D8DC;border-radius:8px;font:inherit;background:#fff;box-sizing:border-box}
.rc .box{background:#fff;border:1px solid #E6E7E9;border-radius:12px;padding:12px 14px}
.rc .rc-tw{overflow-x:auto}
.rc table.rc-g{width:100%;border-collapse:collapse;font-size:12px;min-width:860px}
.rc .rc-g th{height:34px;padding:0 10px;background:#F3F4F6;border:1px solid #D1D5DB;font-weight:700;color:#374151;text-align:left;white-space:nowrap;position:sticky;top:0}
.rc .rc-g td{min-height:32px;padding:5px 10px;border:1px solid #E5E7EB;vertical-align:middle}
.rc .rc-g .c{text-align:center}.rc .rc-g .nw{white-space:nowrap}
.rc .rc-g tbody tr:hover td{background:#FAFAFB}
.rc .rc-tg{padding:1px 8px;font-weight:600;display:inline-block;margin:1px}
.rc .rc-foot{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-top:10px}
.rc .rc-pg{display:flex;gap:4px;flex-wrap:wrap}
.rc .rc-pg a,.rc .rc-pg .off{border:1px solid #D5D8DC;background:#fff;border-radius:6px;min-width:36px;min-height:34px;padding:0 10px;display:inline-flex;align-items:center;justify-content:center;color:#0C161F;text-decoration:none;font-size:12px}
.rc .rc-pg a.on{background:#0C161F;border-color:#0C161F;color:#fff}.rc .rc-pg .off{color:#9CA3AF}
.rc .empty{padding:18px;text-align:center;color:#6B7280}
</style>
<div class="rc">
@if(!$ready)<div class="box"><div class="empty">The report registry is not set up on this server yet.</div></div>
@else
<div class="top">
  <div style="flex:1;min-width:260px">
    <div class="crumb">Reports &amp; Analytics</div>
    <h1>All Reports</h1>
    <span class="mut" style="font-size:12.5px">The permanent list of every report. A report hidden from the menu still appears here. Nothing here changes or removes a report.</span>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn2" href="{{ $exportUrl }}">Export list (CSV)</a>
    @if($isAdmin)<a class="btn2 p" href="{{ url('/admin/report-center/audit') }}">Report System Audit</a>@endif
  </div>
</div>

<div class="kp">
  <div style="box-shadow:inset 0 3px 0 #F95505"><span>Total reports</span><b>{{ number_format($counts['total']) }}</b></div>
  <div><span>Active</span><b>{{ number_format($counts['active']) }}</b></div>
  <div><span>Legacy</span><b>{{ number_format($counts['legacy']) }}</b></div>
  <div><span>Hidden</span><b>{{ number_format($counts['hidden']) }}</b></div>
  <div><span>Missing export</span><b style="color:{{ $counts['missing'] ? '#B91C1C' : '#166534' }}">{{ number_format($counts['missing']) }}</b></div>
</div>

<div class="gc" aria-label="Report groups"><a data-rc-g href="{{ url('/admin/report-center/all-reports') }}" class="{{ $f('group') ? '' : 'on' }}">All groups ({{ $counts['total'] }})</a>@foreach($groups as $gc => $gl)@if(($gcount[$gc] ?? 0) > 0)<a data-rc-g href="{{ url('/admin/report-center/all-reports') }}?group={{ $gc }}" class="{{ $f('group') === $gc ? 'on' : '' }}">{{ $gl }} ({{ $gcount[$gc] }})</a>@endif @endforeach</div>

<form class="flt" id="rc-form" method="get" action="{{ url('/admin/report-center/all-reports') }}">
  <div style="flex:1 1 220px"><label for="rc-q">Search</label><input id="rc-q" type="text" name="q" value="{{ $f('q') }}" placeholder="Report name, code, module…" style="width:100%" autocomplete="off"></div>
  <div><label for="rc-g">Group</label><select id="rc-g" name="group"><option value="">All groups</option>@foreach($groups as $gc => $gl)<option value="{{ $gc }}" {{ $f('group') === $gc ? 'selected' : '' }}>{{ $gl }}</option>@endforeach</select></div>
  <div><label for="rc-s">Sub Group</label><select id="rc-s" name="sub"><option value="">All</option>@foreach($subs as $s)<option {{ $f('sub') === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach</select></div>
  <div><label for="rc-t">Report Type</label><select id="rc-t" name="type"><option value="">All</option>@foreach($types as $k => $l)<option value="{{ $k }}" {{ $f('type') === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select></div>
  <div><label for="rc-st">Status</label><select id="rc-st" name="status"><option value="">All</option><option value="active" {{ $f('status') === 'active' ? 'selected' : '' }}>Active</option><option value="inactive" {{ $f('status') === 'inactive' ? 'selected' : '' }}>Inactive</option><option value="hidden" {{ $f('status') === 'hidden' ? 'selected' : '' }}>Hidden from menu</option></select></div>
  <div><label for="rc-l">Legacy / Active</label><select id="rc-l" name="legacy"><option value="">Both</option><option value="current" {{ $f('legacy') === 'current' ? 'selected' : '' }}>Not legacy</option><option value="legacy" {{ $f('legacy') === 'legacy' ? 'selected' : '' }}>Legacy only</option></select></div>
  <div><label for="rc-e">Export Support</label><select id="rc-e" name="export"><option value="">Any</option><option value="full" {{ $f('export') === 'full' ? 'selected' : '' }}>All four formats</option><option value="missing" {{ $f('export') === 'missing' ? 'selected' : '' }}>Missing a format</option></select></div>
  <div><label for="rc-pp">Show</label><select id="rc-pp" name="pp">@foreach($per as $n)<option {{ (int)$pp === $n ? 'selected' : '' }}>{{ $n }}</option>@endforeach</select></div>
  <div style="display:flex;gap:6px"><button class="btn2 p" type="submit">Apply</button><a class="btn2" href="{{ url('/admin/report-center/all-reports') }}" id="rc-reset">Reset</a></div>
</form>

<div class="box" id="rc-list">@include('report_center_list')</div>
<div class="mut" style="margin-top:8px">The four standard formats are PDF, Excel, CSV and Print. “PDF” means print and save as PDF. Counts come from the registry, not typed by hand.</div>
@endif
</div>
<script>
(function () {
  var form = document.getElementById('rc-form'), box = document.getElementById('rc-list'); if (!form || !box) return;
  var base = form.getAttribute('action'), timer = null, seq = 0;
  function load(url) {
    var my = ++seq;
    fetch(url + (url.indexOf('?') < 0 ? '?' : '&') + 'partial=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw 0; return r.text(); })
      .then(function (h) { if (my !== seq) return; box.innerHTML = h; history.replaceState(null, '', url); })
      .catch(function () { window.location.href = url; });
  }
  function urlFromForm() {
    var p = new URLSearchParams(new FormData(form)), o = new URLSearchParams();
    p.forEach(function (v, k) { if (v !== '') o.set(k, v); });
    var s = o.toString(); return base + (s ? '?' + s : '');
  }
  form.addEventListener('submit', function (e) { e.preventDefault(); load(urlFromForm()); });
  form.addEventListener('change', function () { load(urlFromForm()); });
  document.getElementById('rc-q').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { load(urlFromForm()); }, 250); });
  box.addEventListener('click', function (e) { var a = e.target.closest('a[data-rc]'); if (a) { e.preventDefault(); load(a.getAttribute('href')); } });
})();
</script>
@endsection
