@php
  $cur = $paginator->currentPage(); $last = $paginator->lastPage(); $tot = $paginator->total();
  if ($last <= 7) $pgs = range(1, $last);
  elseif ($cur <= 3) $pgs = [1, 2, 3, 4, '…', $last];
  elseif ($cur >= $last - 2) $pgs = [1, '…', $last - 3, $last - 2, $last - 1, $last];
  else $pgs = [1, '…', $cur - 1, $cur, $cur + 1, '…', $last];
  $perOpts = $perSel ?? false; $perNow = (int)request('per', $paginator->perPage());
@endphp
@once
<style>
.pgx{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px 16px;margin:18px 0 6px;font-family:inherit}
.pgx .inf{font-size:13px;color:#6B7280}.pgx .inf b{color:var(--n,#0B1220)}
.pgx .bar{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.pgx .b{min-width:38px;height:38px;padding:0 13px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--bd,#E5E7EB);border-radius:10px;background:#fff;color:var(--n,#0B1220);font-size:13.5px;font-weight:700;text-decoration:none;transition:all .15s;line-height:1}
.pgx a.b:hover{background:#FFF1E8;border-color:var(--o,#FF5A00);color:var(--o,#FF5A00);transform:translateY(-1px)}
.pgx .b.on{background:var(--o,#FF5A00);border-color:var(--o,#FF5A00);color:#fff;box-shadow:0 2px 8px rgba(255,90,0,.35)}
.pgx .b.dis{opacity:.4;cursor:not-allowed;background:#F8FAFC}
.pgx .dots{padding:0 4px;color:#9CA3AF;font-weight:700}
.pgx .pm{display:none;font-weight:700;color:var(--n,#0B1220);padding:0 6px;font-size:14px}
.pgx .ps{display:flex;align-items:center;gap:6px;font-size:13px;color:#6B7280}.pgx .ps select{width:auto;height:36px;border-radius:9px;padding:0 8px;font-weight:700;color:var(--n,#0B1220)}
@media(max-width:640px){.pgx{justify-content:center}.pgx .inf{width:100%;text-align:center}.pgx .pn{display:none}.pgx .pm{display:inline}.pgx .b{min-width:44px;height:44px}.pgx .bar{justify-content:center;flex-wrap:nowrap}.pgx .lbl{display:none}}
</style>
@endonce
@if($tot > 0)
<nav class="pgx" aria-label="Pagination">
<div class="inf">Showing <b>{{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}</b> of <b>{{ number_format($tot) }}</b> results</div>
@if($paginator->hasPages())<div class="bar">
@if($paginator->onFirstPage())<span class="b dis">←<span class="lbl">&nbsp;Previous</span></span>@else<a class="b" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">←<span class="lbl">&nbsp;Previous</span></a>@endif
@foreach($pgs as $n)@if($n === '…')<span class="dots pn">…</span>@elseif($n === $cur)<span class="b on pn" aria-current="page">{{ $n }}</span>@else<a class="b pn" href="{{ $paginator->url($n) }}">{{ $n }}</a>@endif @endforeach
<span class="pm">{{ $cur }} / {{ $last }}</span>
@if($paginator->hasMorePages())<a class="b" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page"><span class="lbl">Next&nbsp;</span>→</a>@else<span class="b dis"><span class="lbl">Next&nbsp;</span>→</span>@endif
</div>@endif
@if($perOpts)<label class="ps">Show: <select onchange="var u=new URL(location.href);u.searchParams.set('per',this.value);u.searchParams.delete('page');location.href=u.toString()">@foreach([24,50,100] as $o)<option value="{{ $o }}" {{ $perNow === $o ? 'selected' : '' }}>{{ $o }} per page</option>@endforeach</select></label>@endif
</nav>
@endif
