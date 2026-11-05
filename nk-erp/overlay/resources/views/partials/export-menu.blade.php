{{-- Standard export menu (design: ExportMenu). Params: r = report key, from, to, optional extra filters xf[]. --}}
@php($em_from = $from ?? now('Asia/Dhaka')->startOfMonth()->toDateString())
@php($em_to = $to ?? now('Asia/Dhaka')->toDateString())
@if(\App\Support\Reports::allowed($r, auth()->user()->role))
@once
<style>
.xm{position:relative}
.xm>summary{list-style:none;display:inline-flex;align-items:center;gap:8px;min-height:38px;padding:0 16px;border-radius:999px;border:1px solid var(--navy);background:var(--navy);color:#fff;font:600 var(--fs-13) var(--font-ui);cursor:pointer}
.xm>summary::-webkit-details-marker{display:none}
.xm .xmm{position:absolute;right:0;top:44px;z-index:30;width:300px;background:#fff;border:1px solid #dbe3f0;border-radius:14px;box-shadow:0 8px 24px rgba(20,33,61,.14);padding:6px;display:flex;flex-direction:column;gap:2px}
.xm .it{display:flex;align-items:center;gap:10px;min-height:44px;padding:0 12px;border:0;background:#fff;border-radius:10px;font:600 var(--fs-13) var(--font-ui);color:#14213d;text-align:left;width:100%;cursor:pointer}
.xm .it:hover,.xm .it:focus{background:#eef2f9;outline:none}
.xm .it span:last-child{margin-left:auto;font-size:var(--fs-11);font-weight:500;color:#51607a}
.xm .tag{width:40px;font-size:var(--fs-10);font-weight:700;text-align:center;padding:3px 0;border-radius:6px}
.xm hr{height:1px;background:#dbe3f0;border:0;margin:4px 6px}
.xm form{margin:0}
</style>
@endonce
<details class="xm">
  <summary aria-label="Export">Export <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"></path></svg></summary>
  <div class="xmm" role="menu">
    @foreach([['PDF', 'PDF', '#fde9e7', '#b3261e', 'Download PDF', 'A4'], ['Excel', 'XLSX', '#e3f6ec', '#0f6b3c', 'Download Excel', ''], ['CSV', 'CSV', '#e6eeff', '#1d4ed8', 'Download CSV', 'UTF-8'], null, ['A4 print', 'A4', '#eef2f9', '#14213d', 'Print A4', ''], ['POS 80 mm', 'POS', '#eef2f9', '#14213d', 'Print POS', '58 / 80 mm'], null, ['Excel', 'LINK', '#efe7fb', '#5b2fa8', 'Share file', 'expires 7 days', 1]] as $x)
      @if($x === null)<hr>@continue @endif
      <form method="post" action="{{ route('exports.generate') }}" @if(in_array($x[0], ['PDF', 'A4 print', 'POS 80 mm'], true)) target="_blank" @endif>@csrf
        <input type="hidden" name="report" value="{{ $r }}"><input type="hidden" name="fmt" value="{{ $x[0] }}"><input type="hidden" name="from" value="{{ $em_from }}"><input type="hidden" name="to" value="{{ $em_to }}"><input type="hidden" name="stay" value="1">
        @foreach(($xf ?? []) as $fk => $fv)<input type="hidden" name="{{ $fk }}" value="{{ $fv }}">@endforeach
        @if(! empty($x[6]))<input type="hidden" name="share" value="1">@endif
        <button class="it" role="menuitem" type="submit"><span class="tag" style="background:{{ $x[2] }};color:{{ $x[3] }}">{{ $x[1] }}</span>{{ $x[4] }}<span>{{ $x[5] }}</span></button>
      </form>
    @endforeach
    <a href="{{ route('exports.index', ['report' => $r, 'from' => $em_from, 'to' => $em_to]) }}" style="font-size:var(--fs-12);font-weight:600;color:#1d4ed8;text-decoration:none;padding:8px 12px">More formats in Export center →</a>
  </div>
</details>
@endif
