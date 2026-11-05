@extends('layout')
@section('title', 'Document & export center')
@section('content')
@php($U = \App\Support\Ui::class)
@php($role = auth()->user()->role)
<style>.fld{display:flex;flex-direction:column;gap:4px;font-size:11px;font-weight:600;color:#51607a}</style>
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Document &amp; export center</h1><span class="sub">Every report from every module, in one place · you see only what your role may export</span></div>
  @if(auth()->user()->isSuperAdmin())<a class="btn" href="{{ route('imports.index') }}">Import data →</a>@endif
</header>
@if(session('dl'))<div class="flash">Your file is ready: <a href="{{ session('dl') }}">Download</a></div>@endif

<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <form class="card" method="get" action="{{ route('exports.index') }}" id="exf" style="flex:1 1 340px;min-width:300px;padding:20px;display:flex;flex-direction:column;gap:12px">
    <h2 style="margin:0;font-size:15px">New export</h2>
    <label class="fld">Report<select name="report" onchange="this.form.submit()">@foreach(\App\Support\Reports::all() as $mod => $list)<optgroup label="{{ $mod }}">@foreach($list as $k => [$l, $roles])<option value="{{ $k }}" @selected($report === $k) @disabled(! in_array($role, $roles, true))>{{ $l }}{{ in_array($role, $roles, true) ? '' : ' (not for your role)' }}</option>@endforeach</optgroup>@endforeach<optgroup label="Distribution map"><option disabled>Coming with the distribution map</option></optgroup></select></label>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
      <label class="fld">From<input type="date" name="from" value="{{ $f['from'] }}"></label>
      <label class="fld">To<input type="date" name="to" value="{{ $f['to'] }}"></label>
      <label class="fld">Brand<select name="brand"><option value="">All brands</option>@foreach($brands as $b)<option value="{{ $b->id }}" @selected($f['brand'] == $b->id)>{{ $b->name }}</option>@endforeach</select></label>
      <label class="fld">Customer / supplier<select name="customer"><option value="">All</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected($f['customer'] == $c->id)>{{ $c->name }}</option>@endforeach</select></label>
      <label class="fld">Vehicle<select name="vehicle"><option value="">All {{ $vehicles->count() }}</option>@foreach($vehicles as $v)<option value="{{ $v->id }}" @selected($f['vehicle'] == $v->id)>{{ $v->code }}</option>@endforeach</select></label>
      <label class="fld">Route<select name="route"><option value="">All routes</option>@foreach($routes as $r)<option value="{{ $r->id }}" @selected($f['route'] == $r->id)>{{ $r->name }}</option>@endforeach</select></label>
    </div>
    <div class="fld">File format
      <div style="display:flex;flex-wrap:wrap;gap:6px">@foreach(\App\Http\Controllers\ExportController::FORMATS as $x)<label style="cursor:pointer"><input type="radio" name="fmt" value="{{ $x }}" @checked($fmt === $x) style="display:none" onchange="document.querySelectorAll('.fb').forEach(b=>{b.style.background='#fff';b.style.color='#14213d';b.style.borderColor='#7b8aa8'});const s=this.nextElementSibling;s.style.background='#123a86';s.style.color='#fff';s.style.borderColor='#123a86';document.getElementById('genBtn').textContent='Generate '+this.value"><span class="fb" style="display:inline-flex;align-items:center;min-height:34px;padding:0 12px;border-radius:999px;font:600 12px 'Plus Jakarta Sans',sans-serif;border:1px solid {{ $fmt === $x ? '#123a86' : '#7b8aa8' }};background:{{ $fmt === $x ? '#123a86' : '#fff' }};color:{{ $fmt === $x ? '#fff' : '#14213d' }}{{ ($x === 'ZIP' && ! $zip) ? ';opacity:.5' : '' }}">{{ $x }}</span></label>@endforeach</div></div>
    <label class="fld">Page<select name="page"><option value="landscape" @selected($f['page'] === 'landscape')>A4 landscape (wide table)</option><option value="portrait" @selected($f['page'] === 'portrait')>A4 portrait</option></select></label>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <button class="btn pri" id="genBtn" onclick="document.getElementById('tok').disabled=false" type="submit" formmethod="post" formaction="{{ route('exports.generate') }}">Generate {{ $fmt }}</button>
      <a class="btn" target="_blank" href="{{ route('exports.print', ['report' => $report, 'size' => 'a4', 'auto' => 1] + $f) }}">Print</a>
      <button class="btn" type="submit">Refresh preview</button>
    </div>
    <input type="hidden" name="_token" value="{{ csrf_token() }}" id="tok" disabled>
    <span style="font-size:11px;color:#51607a">Share links are signed and expire in 7 days. Super Admin rates, deposits, commission and profit never appear in other roles' exports.</span>
  </form>

  <section class="card" style="flex:999 1 560px;min-width:0;padding:20px;display:flex;flex-direction:column;gap:10px">
    <div style="display:flex;align-items:center"><h2 style="margin:0;font-size:15px;flex-grow:1">Preview · {{ $fmt }}</h2><span class="sub" style="margin:0;font-size:12px">Page 1 of 1</span></div>
    <div style="border:1px solid #dbe3f0;border-radius:12px;padding:16px;background:#fff;display:flex;flex-direction:column;gap:8px">
      <div style="display:flex;gap:12px;align-items:center;border-bottom:2px solid #123a86;padding-bottom:8px">
        <img src="{{ \App\Models\Setting::logo() }}" alt="Company logo" style="width:40px;height:40px;border-radius:8px">
        <div style="flex-grow:1;font-size:11px"><b style="font-size:13px;color:#123a86">{{ \App\Models\Setting::get('company_name', 'NK Corporation') }}</b> {{ \App\Models\Setting::get('address_en') }} · {{ \App\Models\Setting::get('phone') }}</div>
        <div style="text-align:right;font-size:11px"><b>{{ strtoupper(\App\Support\Reports::find($report)['label']) }}</b><br>{{ \Carbon\Carbon::parse($f['from'])->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($f['to'])->format('d-m-Y') }}</div>
      </div>
      <span style="font-size:10px;color:#51607a">Filters: {{ $f['brand'] ? 'one brand' : 'all brands' }} · {{ $f['vehicle'] ? 'one vehicle' : 'all vehicles' }} · {{ $f['route'] ? 'one route' : 'all routes' }} · Generated {{ now()->timezone('Asia/Dhaka')->format('d-m-Y H:i') }} by {{ auth()->user()->name }} ({{ auth()->user()->roleLabel() }})</span>
      <div class="dt-wrap"><table class="dt">
        <thead><tr>@foreach($head as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
        <tbody>
          @forelse(array_slice($rows, 0, 50) as $r)<tr>@foreach($r as $c)<td class="{{ is_int($c) || is_float($c) ? 'r' : '' }}">{{ is_float($c) ? number_format($c, 2) : (is_int($c) ? number_format($c) : $c) }}</td>@endforeach</tr>
          @empty<tr><td colspan="{{ max(1, count($head)) }}" class="empty">No rows for these filters.</td></tr>@endforelse
          @if($total)<tr style="font-weight:700">@foreach($total as $c)<td class="{{ is_numeric($c) ? 'r' : '' }}" style="background:#e6eeff">{{ is_float($c) ? number_format($c, 2) : (is_int($c) ? number_format($c) : $c) }}</td>@endforeach</tr>@endif
        </tbody>
      </table></div>
      @if(count($rows) > 50)<span class="sub" style="font-size:11px;margin:0">Showing 50 of {{ count($rows) }} rows; the file has all of them.</span>@endif
    </div>
    <span style="font-size:11px;color:#51607a">Excel version: frozen header row, filters on, amounts as numbers (never text).</span>
  </section>
</div>

<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px;display:flex;align-items:center"><h2 style="margin:0;font-size:15px;flex-grow:1">Export history &amp; bulk jobs</h2>
    <form method="post" action="{{ route('exports.bulk') }}">@csrf<button class="btn sm" type="submit" @disabled(! $zip) title="All customer statements in one ZIP">＋ Bulk ZIP export</button></form></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Export ID</th><th>Requested by</th><th>Module · report</th><th>Format</th><th>Range</th><th>Generated</th><th class="r">Size</th><th>Status</th><th>Expires</th><th class="r">Downloads</th><th class="no-sort"></th></tr></thead>
    <tbody>
    @forelse($hist as $h)
      @php($rp = \App\Support\Reports::find($h->report))
      @php($stl = ucfirst($h->status))
      <tr><td class="nw">{{ $h->code }}</td><td>{{ $h->user->name }} ({{ $h->user->roleLabel() }})</td><td>{{ ($h->filters['bulk'] ?? null) ? $h->rows.' customer statements' : ($rp['module'] ?? '').' · '.($rp['label'] ?? $h->report) }}</td><td class="c">{{ $h->format }}</td>
        <td class="nw">{{ \Carbon\Carbon::parse($h->filters['from'])->format('d-m') }} – {{ \Carbon\Carbon::parse($h->filters['to'])->format('d-m') }}</td><td class="nw">{{ $h->created_at->timezone('Asia/Dhaka')->format('d-m H:i') }}</td>
        <td class="r">{{ $h->size ? number_format($h->size / 1024, 0).' KB' : '—' }}</td>
        <td class="c"><span class="pill" style="{{ ['Ready' => 'background:#e3f6ec;color:#0f6b3c', 'Expired' => 'background:#eef2f9;color:#51607a', 'Blocked' => 'background:#fde9e7;color:#b3261e'][$stl] ?? '' }}">{{ $stl }}</span></td>
        <td class="nw">{{ $h->expires_at?->timezone('Asia/Dhaka')->format('d-m-Y') ?? '—' }}</td><td class="r">{{ $h->downloads ?: '—' }}</td>
        <td class="nw" style="font-size:12px">@if($h->status === 'ready')<a href="{{ route('exports.download', $h) }}">Download</a> · <form method="post" action="{{ route('exports.share', $h) }}" style="display:inline">@csrf<button type="submit" style="border:0;background:none;color:#1d4ed8;cursor:pointer;padding:0;font:inherit">Share link</button></form>@endif</td></tr>
    @empty
      <tr><td colspan="11" class="empty">No exports yet.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>

<section class="card" style="padding:20px;display:flex;flex-direction:column;gap:12px">
  <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px"><h2 style="margin:0;font-size:15px;flex-grow:1">Document library</h2>
    <form method="get" style="display:flex;gap:6px"><input type="hidden" name="tab" value="{{ $tab }}"><input name="q" value="{{ $q }}" placeholder="Search invoice, statement, report" style="min-width:240px"></form></div>
  <div class="tabs">@foreach(['all' => 'All', 'invoices' => 'Invoices', 'statements' => 'Statements', 'closing' => 'Closing reports', 'archived' => 'Archived'] as $k => $l)<a href="{{ route('exports.index', ['tab' => $k, 'q' => $q]) }}" class="{{ $tab === $k ? 'on' : '' }}">{{ $l }}</a>@endforeach</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px">
    @forelse($docs as $d)
      <div style="border:1px solid #dbe3f0;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:6px">
        <div style="display:flex;gap:6px;align-items:center"><span style="font-size:10px;font-weight:800;padding:2px 8px;border-radius:6px;background:{{ ['PDF' => '#fde9e7;color:#b3261e', 'XLSX' => '#e3f6ec;color:#0f6b3c', 'ZIP' => '#efe7fb;color:#5b2fa8', 'CSV' => '#e6eeff;color:#1d4ed8'][$d['f']] ?? '#eef2f9' }}">{{ $d['f'] }}</span><span style="font-size:11px;color:#51607a">v{{ $d['v'] }}</span></div>
        <b style="font-size:13px">{{ $d['n'] }}</b><span style="font-size:11px;color:#51607a">{{ $d['meta'] }}</span>
        <div style="display:flex;gap:6px"><a class="btn sm" href="{{ $d['url'] }}" target="_blank">Preview</a><a class="btn sm" href="{{ $d['url'] }}{{ str_contains($d['url'], '?') ? '&' : '?' }}auto=1" target="_blank">Reprint</a><button class="btn sm" type="button" disabled title="Only one version so far">Versions</button></div>
      </div>
    @empty
      <p class="sub" style="margin:0">Nothing found.</p>
    @endforelse
  </div>
  <span style="font-size:11px;color:#51607a">Archiving or deleting a file never removes the posted transaction behind it.</span>
</section>
@endsection
