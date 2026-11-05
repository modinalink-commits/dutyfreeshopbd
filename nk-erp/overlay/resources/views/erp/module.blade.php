@extends('layout')
@section('title', $def['title'])
@section('content')
@php($form = $def['forms'][$tab])
@php($role = auth()->user()->role)
<style>.f{display:flex;flex-direction:column;gap:4px;min-width:0}.f>span{font-size:11px;font-weight:600;color:#51607a}.box.auto{min-height:40px;border:1px solid #9db4e3;border-radius:8px;background:#e6eeff;color:#123a86;font-weight:700;padding:0 12px;display:flex;align-items:center;font-size:13px}.ttl{font-size:10px;font-weight:700;letter-spacing:.08em;color:#51607a}</style>
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><span class="sub" style="margin:0;font-size:12px">{{ $def['path'] }} · Module {{ $def['no'] }} of 15</span><h1>{{ $def['title'] }} · <span style="font-family:'Hind Siliguri'">{{ $def['bn'] }}</span></h1></div>
  <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#51607a">Module<select onchange="location='{{ url('/erp') }}/'+this.value" style="width:auto">@foreach($defs as $k => $d)<option value="{{ $k }}" @selected($k === $m)>{{ $d['no'] }} · {{ $d['title'] }}</option>@endforeach</select></label>
  <a class="btn" href="{{ route('exports.index') }}">Export ▾</a>
  <a class="btn pri" href="{{ route('erp.module', ['m' => $m, 'tab' => 0]) }}#form">＋ {{ $def['cta'] }}</a>
</header>
<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
  @foreach($def['kpiLabels'] as $i => $l)
    @php([$v, $foot] = $kpis[$i] ?? ['—', ''])
    <div class="card" style="padding:14px 16px;display:flex;flex-direction:column;gap:2px"><span style="font-size:11px;font-weight:600;color:#51607a">{{ $l }}</span><b style="font-size:20px;font-variant-numeric:tabular-nums">{{ is_numeric($v) ? number_format($v) : $v }}</b><span style="font-size:11px;color:#51607a">{{ $foot }}</span></div>
  @endforeach
</section>
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <form class="card" id="form" method="post" action="{{ route('erp.store', $m) }}" enctype="multipart/form-data" style="flex:999 1 560px;min-width:0;padding:20px;display:flex;flex-direction:column;gap:14px">@csrf
    <input type="hidden" name="form" value="{{ $tab }}"><input type="hidden" name="no" value="{{ $edit?->record_no ?? $nextNo }}">@if($edit)<input type="hidden" name="id" value="{{ $edit->id }}">@endif
    <div class="tabs">@foreach($def['forms'] as $i => $fm)<a href="{{ route('erp.module', ['m' => $m, 'tab' => $i]) }}#form" class="{{ $i === $tab ? 'on' : '' }}">{{ $fm['tab'] }}</a>@endforeach</div>
    <span class="ttl">REQUIRED</span>
    @php($fieldHtml = function ($fl, $i) use ($edit, $nextNo) {
      $val = old('f.'.$i, $edit ? ($edit->data[$fl['l']] ?? '') : '');
      $name = 'f['.$i.']';
      if ($fl['t'] === 'auto') return '<div class="box auto">'.e(str_contains(strtolower($fl['l']), 'no') || str_contains(strtolower($fl['l']), 'id') ? ($edit?->record_no ?? $nextNo).' (auto)' : (str_contains(strtolower($fl['l']), 'date') ? now()->timezone('Asia/Dhaka')->format('d-m-Y') : 'Worked out after saving')).'</div>';
      if ($fl['t'] === 'file') return '<input type="file" name="file" accept="image/*,application/pdf" style="padding:8px">';
      if ($fl['t'] === 'select') {
        $opts = \App\Http\Controllers\ErpController::options($fl['l']);
        if (! $opts) return '<input name="'.$name.'" value="'.e($val).'" list="dl'.$i.'" placeholder="'.e($fl['hint']).'"><datalist id="dl'.$i.'"><option value="'.e($fl['hint']).'"></datalist>';
        return '<select name="'.$name.'"><option value="">—</option>'.collect($opts)->map(fn ($o) => '<option'.($val === $o ? ' selected' : '').'>'.e($o).'</option>')->implode('').'</select>';
      }
      return '<input name="'.$name.'" type="'.($fl['t'] === 'date' ? 'date' : ($fl['t'] === 'number' ? 'number" step="any' : 'text')).'" value="'.e($val).'">';
    })
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px">
      @foreach($form['fields'] as $i => $fl)@if(! $fl['adv'])<label class="f"><span>{{ $fl['l'] }}</span>{!! $fieldHtml($fl, $i) !!}</label>@endif @endforeach
    </div>
    <details @if($edit) open @endif><summary style="cursor:pointer;font-weight:700;font-size:13px;color:#1d4ed8">More details (optional)</summary>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin-top:10px">
        @foreach($form['fields'] as $i => $fl)@if($fl['adv'])<label class="f"><span>{{ $fl['l'] }}</span>{!! $fieldHtml($fl, $i) !!}</label>@endif @endforeach
      </div></details>
    @if($form['note'])<div style="background:#def5f7;color:#0b6670;border-radius:12px;padding:10px 12px;font-size:12px;line-height:18px">{{ $form['note'] }}</div>@endif
    <div style="display:flex;flex-wrap:wrap;gap:8px;border-top:1px solid #dbe3f0;padding-top:14px">
      <button class="btn" type="submit" name="action" value="draft">Save draft</button>
      <button class="btn pri" type="submit" name="action" value="submit">{{ $form['submit'] }}</button>
      <button class="btn" type="button" onclick="window.print()">Print</button>
    </div>
  </form>
  <aside style="flex:1 1 300px;min-width:280px;display:flex;flex-direction:column;gap:16px">
    <section class="card" style="padding:18px;display:flex;flex-direction:column;gap:8px">
      <h2 style="margin:0;font-size:15px">Status &amp; approval</h2>
      @foreach($def['flow'] as $i => $s)
        <div style="display:flex;gap:10px;align-items:center;font-size:13px"><span style="width:24px;height:24px;border-radius:999px;background:#eef2f9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px">{{ $i + 1 }}</span><span style="flex-grow:1;font-weight:600">{{ $s['t'] }}</span><span style="color:#51607a;font-size:12px">{{ $s['who'] }}</span></div>
      @endforeach
    </section>
    <section class="card" style="padding:18px;display:flex;flex-direction:column;gap:6px">
      <h2 style="margin:0 0 4px;font-size:15px">Activity history</h2>
      @forelse($hist as $h)<div style="display:flex;gap:10px;font-size:12px;padding:5px 0;border-top:1px solid #eef2f9"><span style="color:#51607a;width:40px">{{ $h->created_at->timezone('Asia/Dhaka')->format('H:i') }}</span><span>{{ $h->user?->name }} {{ \Illuminate\Support\Str::afterLast($h->action, '_') }} {{ $h->details }}</span></div>
      @empty<p class="sub" style="margin:0">Nothing yet.</p>@endforelse
    </section>
    <section class="card" style="padding:18px;display:flex;flex-direction:column;gap:8px">
      <h2 style="margin:0;font-size:15px">Reports</h2>
      <div style="display:flex;flex-wrap:wrap;gap:6px">@foreach($def['reports'] as $r)<a class="btn sm" href="{{ route('exports.index') }}" title="Open the export center">{{ $r }}</a>@endforeach</div>
    </section>
  </aside>
</div>
<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:8px"><h2 style="margin:0;font-size:15px;flex-grow:1">{{ $def['tableTitle'] }}</h2>
    <form method="get" style="display:flex;gap:6px"><input type="hidden" name="tab" value="{{ $tab }}"><input name="q" value="{{ request('q') }}" placeholder="Search" style="width:200px"><button class="btn sm" type="submit">Filters ▾</button></form></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr>@foreach($def['cols'] as $c)<th>{{ $c }}</th>@endforeach<th>Status</th><th class="no-sort"></th></tr></thead>
    <tbody>
      @foreach($rows as [$cells, $st])<tr>@foreach($cells as $c)<td class="{{ is_numeric($c) ? 'r' : '' }}">{{ $c }}</td>@endforeach<td class="c"><span class="pill">{{ $st }}</span></td><td></td></tr>@endforeach
      @foreach($recRows as [$cells, $st, $r])
        <tr>@foreach($cells as $c)<td>{{ $c }}</td>@endforeach<td class="c"><span class="pill" style="{{ ['Draft' => '', 'Submitted' => 'background:#efe7fb;color:#5b2fa8', 'Checked' => 'background:#e6eeff;color:#1d4ed8', 'Approved' => 'background:#fff1d6;color:#8a4b00', 'Posted' => 'background:#e3f6ec;color:#0f6b3c', 'Closed' => 'background:#e3f6ec;color:#0f6b3c', 'Rejected' => 'background:#fde9e7;color:#b3261e'][$st] ?? '' }}">{{ $st }}</span></td>
          <td class="nw" style="font-size:12px">
            @if($r->status === 'draft')<a href="{{ route('erp.module', ['m' => $m, 'r' => $r->id]) }}#form">Edit</a>@endif
            @php($next = ['submitted' => 'checked', 'checked' => 'approved', 'approved' => 'posted', 'posted' => 'closed'][$r->status] ?? null)
            @if($next)<form method="post" action="{{ route('erp.step', $r) }}" style="display:inline">@csrf<input type="hidden" name="to" value="{{ $next }}"><button class="btn sm" type="submit">{{ ucfirst($next === 'checked' ? 'check' : ($next === 'approved' ? 'approve' : ($next === 'posted' ? 'post' : 'close'))) }}</button></form>@endif
            @if(in_array($r->status, ['submitted', 'checked']))<form method="post" action="{{ route('erp.step', $r) }}" style="display:inline" onsubmit="const x=prompt('Reason?');if(!x)return false;this.reason.value=x">@csrf<input type="hidden" name="to" value="rejected"><input type="hidden" name="reason"><button class="btn sm" type="submit">Reject</button></form>@endif
            @if($r->file)<a href="{{ route('erp.file', $r) }}" target="_blank">file</a>@endif
            <span class="sub" style="font-size:11px" title="{{ collect($r->trail)->map(fn ($t) => $t['s'].' · '.$t['by'].' · '.$t['at'])->implode("\n") }}">{{ count($r->trail ?? []) }} steps</span>
          </td></tr>
      @endforeach
      @if(! $rows && ! $recRows)<tr><td colspan="{{ count($def['cols']) + 2 }}" class="empty">No records yet. Use the form above.</td></tr>@endif
    </tbody>
  </table></div>
</section>
@endsection
