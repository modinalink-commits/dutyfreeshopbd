@if($paginator->hasPages())
<nav class="pgn" aria-label="Pagination"><style>.pgn{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:14px 0 4px}.pgn a,.pgn span.p{min-width:34px;height:34px;padding:0 10px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--bd);border-radius:9px;background:#fff;color:var(--n);font-size:13px;font-weight:600;text-decoration:none}.pgn a:hover{background:#FFF1E8;border-color:var(--o)}.pgn span.on{background:var(--n);color:#fff;border-color:var(--n)}.pgn span.dis{opacity:.4}.pgn span.dots{border:0;background:none}.pgn .tot{margin-left:auto;font-size:12px;color:#6B7280}</style>
@if($paginator->onFirstPage())<span class="p dis">‹ Prev</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Prev</a>@endif
@foreach($elements as $element)
@if(is_string($element))<span class="p dots">…</span>@endif
@if(is_array($element))@foreach($element as $page => $url)@if($page == $paginator->currentPage())<span class="p on" aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif @endforeach @endif
@endforeach
@if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>@else<span class="p dis">Next ›</span>@endif
<span class="tot">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ number_format($paginator->total()) }}</span></nav>
@endif
