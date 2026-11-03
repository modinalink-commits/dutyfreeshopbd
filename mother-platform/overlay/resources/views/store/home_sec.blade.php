<div class="w sec-cs{{ !empty($lazy) ? ' hsl' : '' }}" @if(!empty($lazy)) data-hs="{{ $c->slug }}" @endif style="padding-top:34px"><div class="cs">
<div class="hh"><span class="t">{{ $c->name }}</span><span class="sp"></span>@foreach(array_slice($subs[$c->id] ?? [], 0, 5) as $sb)<a class="tb" href="/{{ $c->slug }}/{{ \App\Support\Slugs::subSlug($sb->sub) }}">{{ $sb->sub }}</a>@endforeach<a class="va" href="/{{ $c->slug }}/">View All</a></div>
<div class="mt"><span>{{ $c->name }}</span><a href="/{{ $c->slug }}/">View all ›</a></div>
@if(empty($lazy))<div class="gr">@foreach($items as $q)@include('store.card', ['fd'=>true])@endforeach</div>@else<div class="gr" style="min-height:300px"></div>@endif
<div class="mv"><a href="/{{ $c->slug }}/">Browse all {{ $c->name }}</a></div></div></div>
