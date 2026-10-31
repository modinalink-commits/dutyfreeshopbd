@if($pg->hasPages())
<div class="pgn">@if($pg->previousPageUrl())<a href="{{ $pg->previousPageUrl() }}">‹ Prev</a>@endif
@foreach(range(max(1, $pg->currentPage() - 2), min($pg->lastPage(), $pg->currentPage() + 2)) as $n)
@if($n === $pg->currentPage())<span class="cur">{{ $n }}</span>@else<a href="{{ $pg->url($n) }}">{{ $n }}</a>@endif
@endforeach
@if($pg->nextPageUrl())<a href="{{ $pg->nextPageUrl() }}">Next ›</a>@endif</div>
@endif
