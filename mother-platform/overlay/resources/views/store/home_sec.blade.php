<div class="w sec-cs{{ !empty($lazy) ? ' hsl' : '' }}" @if(!empty($lazy)) data-hs="{{ $c->slug }}" @endif style="padding-top:34px"><div class="cs">
@php $__vm = '<details class="vd"><summary>View All</summary><div class="vm"><a class="vall" href="/'.e($c->slug).'/">All '.e($c->name).'</a>'; foreach (($subs[$c->id] ?? []) as $sb) { $__vm .= '<a href="/'.e($c->slug).'/'.e(\App\Support\Slugs::subSlug($sb->sub)).'">'.e($sb->sub).'</a>'; } $__vm .= '</div></details>'; @endphp
<div class="hh"><span class="t">{{ $c->name }}</span><span class="sp"></span>{!! $__vm !!}</div>
<div class="mt"><span>{{ $c->name }}</span>{!! $__vm !!}</div>
@if(empty($lazy))<div class="gr">@foreach($items as $q)@include('store.card', ['fd'=>true])@endforeach</div>@else<div class="gr" style="min-height:300px"></div>@endif
<div class="mv"><a href="/{{ $c->slug }}/">Browse all {{ $c->name }}</a></div></div></div>
