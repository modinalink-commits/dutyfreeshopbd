@if($p->hasPages())
<nav aria-label="Pages" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
  @if($p->onFirstPage())<span class="btn sm" style="opacity:.5">‹ Previous</span>@else<a class="btn sm" href="{{ $p->previousPageUrl() }}" rel="prev">‹ Previous</a>@endif
  <span class="sub" style="margin:0">Page {{ $p->currentPage() }} of {{ $p->lastPage() }} · {{ number_format($p->total()) }} rows</span>
  @if($p->hasMorePages())<a class="btn sm" href="{{ $p->nextPageUrl() }}" rel="next">Next ›</a>@else<span class="btn sm" style="opacity:.5">Next ›</span>@endif
</nav>
@endif
