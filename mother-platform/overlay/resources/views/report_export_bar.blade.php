@php
  // Standard report header for every registered report page: code, name context, quick date range, data quality, and the Export menu.
  $__rx = null;
  try {
    if (empty($__emb ?? null)) {
      $__rx = \App\Support\ReportCatalog::current(request());
      if ($__rx) \App\Support\ReportCatalog::touch($__rx->report_code);
    }
  } catch (\Throwable $e) { $__rx = null; }
  $__rxNative = $__rx ? \App\Support\ReportCatalog::nativeFull($__rx->native_export ?? '') : true;
  $__rxExp = false; $__rxAll = []; $__rxDq = []; $__rxGroup = '';
  if ($__rx) {
    try {
      $__rxExp = !$__rxNative && \App\Http\Middleware\ModulePermission::can(auth()->user(), $__rx->permission_key ?? null, 'download');
      $__rxAll = $__rxExp ? \App\Support\ReportCatalog::allUrls($__rx, request()) : [];
      $__rxDq = \App\Support\DataQuality::forGroup($__rx->group_code ?? null);
      $__rxGroup = \App\Support\ReportCatalog::GROUPS[$__rx->group_code] ?? '';
    } catch (\Throwable $e) {}
  }
  $__rxQ = collect(request()->query())->except(['page', 'partial', '_token', 'sort', 'dir', 'pp', 'rxdebug'])->filter(fn($v) => is_scalar($v) && $v !== '')->map(fn($v, $k) => ucwords(str_replace('_', ' ', $k)) . ': ' . $v)->values()->implode('  ·  ');
@endphp
@if(in_array(auth()->user()->role ?? '', ['super','admin'], true) && request()->query('rxdebug'))
<div id="rxb-dbg" style="font:11px monospace;padding:4px">{{ 'cur=' . ($__rx->report_code ?? 'null') . ' native=' . (int)$__rxNative . ' export=' . (int)$__rxExp }}</div>
@endif
@if($__rx)
<div id="rxb-ph" style="display:none">
  <div class="rxb-t">{{ $__rx->report_code }} · {{ $__rx->name }}</div>
  <div class="rxb-m">Store: {{ request()->query('store') ?: 'All stores' }}@if($__rxQ) · {{ $__rxQ }}@endif</div>
  <div class="rxb-m">Generated {{ now('Asia/Dhaka')->format('d M Y, h:i A') }} by {{ auth()->user()->name ?? '' }} · DutyFreeBD / Mother Commerce</div>
</div>
<div id="rxh" class="rxh" hidden>
  <div class="rxh-l">
    <span class="rxh-code">{{ $__rx->report_code }}</span>
    <span class="rxh-g">{{ $__rxGroup }}</span>
    @if(!empty($__rx->description))<span class="rxh-d">{{ $__rx->description }}</span>@endif
    @foreach($__rxDq as $q)<span class="rxh-q {{ $q['ok'] ? 'ok' : 'warn' }}" title="Data quality">{{ $q['text'] }}</span>@endforeach
  </div>
  <div class="rxh-r">
    <span class="rxh-rng" hidden aria-label="Quick date range">
      <button type="button" data-r="today">Today</button><button type="button" data-r="yesterday">Yesterday</button><button type="button" data-r="7">Last 7 Days</button><button type="button" data-r="30">Last 30 Days</button><button type="button" data-r="month">This Month</button><button type="button" data-r="last">Last Month</button>
    </span>
    @if($__rxExp)
    <span class="rxb" id="rxb">
      <button type="button" class="rxb-b" aria-haspopup="true" aria-expanded="false">Export ▾</button>
      <div class="rxb-menu" role="menu">
        <small>Current view · the tables shown on screen</small>
        <button type="button" data-f="pdf">PDF (save from print)</button><button type="button" data-f="xlsx">Excel (.xlsx)</button><button type="button" data-f="csv">CSV</button><button type="button" data-f="print">Print</button>
        @if($__rxAll['csv'] ?? null)
        <small>All filtered results · every matching row</small>
        <button type="button" data-all="csv" data-u="{{ $__rxAll['csv'] }}">CSV (all rows)</button>
        @if($__rxAll['xls'] ?? null)<button type="button" data-all="xls" data-u="{{ $__rxAll['xls'] }}">Excel (all rows)</button>@endif
        @endif
      </div>
    </span>
    @endif
  </div>
</div>
<style>
.rxh{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;justify-content:space-between;margin:0 0 10px;padding:6px 10px;border:1px solid #E6E7E9;border-radius:10px;background:#fff;font:12px system-ui,Arial,sans-serif;color:#0C161F}
.rxh[hidden],.rxh-rng[hidden]{display:none}
.rxh-l{display:flex;flex-wrap:wrap;gap:6px 10px;align-items:center;min-width:0;flex:1 1 320px}
.rxh-r{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.rxh-code{background:#0C161F;color:#fff;border-radius:6px;padding:2px 8px;font-weight:700}
.rxh-g{color:#4B5563;font-weight:600}.rxh-d{color:#6B7280}
.rxh-q{border-radius:999px;padding:2px 9px;font-size:11.5px;font-weight:600}.rxh-q.ok{background:#DCFCE7;color:#166534}.rxh-q.warn{background:#FEF3C7;color:#92400E}
.rxh-rng{display:inline-flex;flex-wrap:wrap;gap:4px}
.rxh-rng button{min-height:30px;padding:0 10px;border:1px solid #D5D8DC;border-radius:999px;background:#fff;color:#0C161F;font:600 11.5px system-ui,Arial,sans-serif;cursor:pointer}
.rxh-rng button.on{background:#0C161F;border-color:#0C161F;color:#fff}
.rxb{position:relative;display:inline-block}
.rxb button.rxb-b{display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:0 14px;border:1px solid #C2410C;border-radius:8px;background:#C2410C;color:#fff;font:700 13px system-ui,Arial,sans-serif;cursor:pointer}
.rxb .rxb-menu{display:none;position:absolute;right:0;top:38px;z-index:60;min-width:220px;background:#fff;border:1px solid #D5D8DC;border-radius:10px;box-shadow:0 8px 24px rgba(12,22,31,.14);padding:6px}
.rxb.open .rxb-menu{display:block}
.rxb .rxb-menu button{display:block;width:100%;text-align:left;min-height:38px;padding:0 12px;border:0;border-radius:6px;background:transparent;color:#0C161F;font:13px system-ui,Arial,sans-serif;cursor:pointer}
.rxb .rxb-menu button:hover{background:#FFEEE6}
.rxb .rxb-menu small{display:block;padding:6px 12px 2px;color:#6B7280;font-size:11px}
@media (max-width:640px){.rxh-rng{overflow-x:auto;flex-wrap:nowrap;max-width:100%}}
#rxb-ph{display:none}
@media print{
  .rxh,aside,.sb,#dfsm{display:none!important}
  #rxb-ph{display:block!important;margin:0 0 10px;padding:0 0 6px;border-bottom:1px solid #D5D8DC}
  #rxb-ph .rxb-t{font:700 18px system-ui,Arial,sans-serif;color:#0C161F}
  #rxb-ph .rxb-m{font:11px system-ui,Arial,sans-serif;color:#4B5563;margin-top:2px}
  main{padding:0!important;margin:0!important;width:100%!important}
}
@page{margin:12mm 10mm 14mm;@bottom-left{content:"Generated by Mother Commerce";font:9px Arial,sans-serif;color:#6B7280}@bottom-right{content:"Page " counter(page) " of " counter(pages);font:9px Arial,sans-serif;color:#6B7280}}
</style>
<script>
(function () {
  var CODE = @json($__rx->report_code), NAME = @json($__rx->name), LOG = @json(url('/admin/report-center/log-export')), XLSX = @json(url('/admin/report-center/xlsx'));
  var main = document.querySelector('main'), hd = document.getElementById('rxh'); if (!main || !hd) return;
  main.insertBefore(hd, main.firstChild); hd.hidden = false;
  var QS = new URLSearchParams(location.search);
  function csrf() { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; }
  function slug(s) { return String(s || '').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); }
  function fname(ext) {
    var p = [slug(NAME), QS.get('store') ? slug(QS.get('store')) : 'All-Stores'];
    var f = QS.get('from') || QS.get('date_from') || QS.get('start'), t = QS.get('to') || QS.get('date_to') || QS.get('end');
    p.push(f || t ? (f || '') + '_to_' + (t || '') : new Date().toISOString().slice(0, 10)); return p.join('_') + '.' + ext;
  }

  /* ---------- quick date range ---------- */
  var fromI = main.querySelector('input[name=from],input[name=date_from],input[name=start]'), toI = main.querySelector('input[name=to],input[name=date_to],input[name=end]');
  var rng = hd.querySelector('.rxh-rng');
  if (fromI && toI && rng) {
    rng.hidden = false;
    var fmt = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Dhaka', year: 'numeric', month: '2-digit', day: '2-digit' });
    function ymd(d) { return fmt.format(d); }
    function today() { var p = ymd(new Date()).split('-'); return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2])); }
    function iso(d) { return d.toISOString().slice(0, 10); }
    function range(k) {
      var t = today(), y = t.getUTCFullYear(), m = t.getUTCMonth(), a = new Date(t), b = new Date(t);
      if (k === 'yesterday') { a.setUTCDate(a.getUTCDate() - 1); b = new Date(a); }
      else if (k === '7') a.setUTCDate(a.getUTCDate() - 6);
      else if (k === '30') a.setUTCDate(a.getUTCDate() - 29);
      else if (k === 'month') a = new Date(Date.UTC(y, m, 1));
      else if (k === 'last') { a = new Date(Date.UTC(y, m - 1, 1)); b = new Date(Date.UTC(y, m, 0)); }
      return [iso(a), iso(b)];
    }
    function mark() { rng.querySelectorAll('button').forEach(function (b) { var r = range(b.getAttribute('data-r')); b.classList.toggle('on', fromI.value === r[0] && toI.value === r[1]); }); }
    mark();
    rng.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-r]'); if (!b) return; var r = range(b.getAttribute('data-r')); fromI.value = r[0]; toI.value = r[1]; mark();
      var f = fromI.form;
      if (f) { try { f.requestSubmit(); } catch (x) { f.submit(); } }
      else { var u = new URLSearchParams(location.search); u.set(fromI.name, r[0]); u.set(toI.name, r[1]); u.delete('page'); location.search = u.toString(); }
    });
  }

  /* ---------- export ---------- */
  var bar = document.getElementById('rxb'); if (!bar) return;
  /* the page already ships its own export controls: do not show a second Export menu */
  var own = [].slice.call(document.querySelectorAll('main a, main button, main summary')).some(function (e) {
    return !e.closest('.rxh') && !e.closest('#rxb-ph') && /^\s*(Export|Download)\b/i.test(e.textContent || '') && e.offsetParent !== null;
  });
  if (own) { var hold = bar.parentNode; bar.remove(); return; }
  function tables() {
    var all = [].slice.call(main.querySelectorAll('table')).filter(function (t) {
      if (t.closest('.rxh') || t.closest('#rxb-ph') || t.closest('[hidden]') || t.closest('[data-noexport]')) return false;
      if (t.offsetParent === null) return false;
      return t.rows.length >= 2 && t.querySelectorAll('th').length > 0;
    });
    return all.filter(function (t) { return !all.some(function (o) { return o !== t && t.contains(o); }); });
  }
  function title(t) {
    var n = t, h = null;
    while (n && n !== main && !h) {
      var p = n.previousElementSibling;
      while (p && !h) { var q = /^H[1-4]$/.test(p.tagName) ? p : p.querySelector && p.querySelector('h2,h3,h4'); if (q && !p.querySelector('table')) h = q; p = p.previousElementSibling; }
      n = n.parentElement;
    }
    return h ? h.textContent.trim() : '';
  }
  function cells(tr) { return [].slice.call(tr.cells).map(function (c) { return { v: c.innerText.replace(/\s+/g, ' ').trim(), s: c.colSpan || 1 }; }); }
  function numeric(v) { var s = v.replace(/[৳$,\s%]/g, ''); if (!/^-?(0|[1-9]\d*)(\.\d+)?$/.test(s)) return null; if (s.indexOf('.') < 0 && s.replace('-', '').length >= 10) return null; return s; }
  function grid() { return tables().map(function (t) { return { title: title(t), rows: [].slice.call(t.rows).filter(function (r) { return r.offsetParent !== null; }).map(cells) }; }); }
  function csvCell(v) { if (!numeric(v) && /^[=+\-@]/.test(v)) v = "'" + v; return /[",\r\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }
  function toCSV(g) {
    var out = [];
    g.forEach(function (t, i) { if (i) out.push(''); if (g.length > 1 && t.title) out.push(csvCell(t.title)); t.rows.forEach(function (r) { var a = []; r.forEach(function (c) { a.push(csvCell(c.v)); for (var k = 1; k < c.s; k++) a.push(''); }); out.push(a.join(',')); }); });
    return '﻿' + out.join('\r\n');
  }
  function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
  function toXLS(g) {
    var meta = document.getElementById('rxb-ph'), h = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"><style>td,th{border:1px solid #BBB;font:12px Arial}th{background:#EEF1F5;font-weight:bold}</style></head><body>';
    h += '<h3>' + esc(NAME) + '</h3><p>' + esc(meta ? meta.innerText.replace(/\n+/g, ' | ') : '') + '</p>';
    g.forEach(function (t) {
      if (t.title && g.length > 1) h += '<h4>' + esc(t.title) + '</h4>';
      h += '<table>' + t.rows.map(function (r, i) { return '<tr>' + r.map(function (c) { var n = i ? numeric(c.v) : null, tag = i === 0 ? 'th' : 'td'; return '<' + tag + (c.s > 1 ? ' colspan="' + c.s + '"' : '') + (n !== null ? ' x:num="' + n + '"' : '') + '>' + esc(c.v) + '</' + tag + '>'; }).join('') + '</tr>'; }).join('') + '</table><br>';
    });
    return h + '</body></html>';
  }
  function save(blobOrText, type, name) {
    var b = blobOrText instanceof Blob ? blobOrText : new Blob([blobOrText], { type: type });
    var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = name; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 800);
  }
  function log(fmt) { try { fetch(LOG, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: JSON.stringify({ code: CODE, fmt: fmt, qs: location.search }) }); } catch (e) {} }
  function htmlXls(g) { save(toXLS(g), 'application/vnd.ms-excel;charset=utf-8', fname('xls')); log('xlsx'); }
  function run(fmt) {
    if (fmt === 'print' || fmt === 'pdf') { log(fmt); var old = document.title; document.title = fname('pdf').replace(/\.pdf$/, ''); window.print(); setTimeout(function () { document.title = old; }, 1500); return; }
    var g = grid(); if (!g.length) { alert('There is no table on this page to export.'); return; }
    if (fmt === 'csv') { save(toCSV(g), 'text/csv;charset=utf-8', fname('csv')); log('csv'); return; }
    var body = JSON.stringify({ code: CODE, qs: location.search, tables: g.map(function (t) { return { title: t.title, rows: t.rows.map(function (r) { var a = []; r.forEach(function (c) { a.push(c.v); for (var k = 1; k < c.s; k++) a.push(''); }); return a; }) }; }) });
    if (body.length > 900000) { htmlXls(g); return; }
    fetch(XLSX, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }, body: body })
      .then(function (r) { if (!r.ok) throw r.status; return r.blob(); }).then(function (b) { save(b, '', fname('xlsx')); }).catch(function () { htmlXls(g); });
  }
  var btn = bar.querySelector('.rxb-b');
  btn.addEventListener('click', function (e) { e.stopPropagation(); var o = bar.classList.toggle('open'); btn.setAttribute('aria-expanded', o ? 'true' : 'false'); });
  bar.querySelector('.rxb-menu').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return; bar.classList.remove('open');
    if (b.getAttribute('data-all')) { log(b.getAttribute('data-all') + '-all'); location.href = b.getAttribute('data-u'); return; }
    if (b.getAttribute('data-f')) run(b.getAttribute('data-f'));
  });
  document.addEventListener('click', function () { bar.classList.remove('open'); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') bar.classList.remove('open'); });
})();
</script>
@endif
