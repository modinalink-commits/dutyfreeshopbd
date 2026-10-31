@extends('store.layout')
@section('ld')<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'হোম','item'=>url('/').'/'],['@type'=>'ListItem','position'=>2,'name'=>$c->name,'item'=>url('/'.$c->slug).'/']]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) !!}</script>@endsection
@section('main')
<style>.bc{font-size:11px;display:flex;gap:8px;align-items:center;text-transform:uppercase}.bch{display:flex;gap:6px;flex-wrap:wrap}.bch a{background:var(--n);color:#fff;font-size:10px;font-weight:600;padding:5px 8px;text-transform:uppercase}.bch a.on{background:var(--o)}.grp{padding-top:18px;display:flex;flex-direction:column;gap:14px;align-items:center}.grp .gt{background:#14594A;color:#fff;font-size:11px;font-weight:700;padding:6px 16px;letter-spacing:.02em;text-transform:uppercase}.grp .gr{width:100%}
@media (max-width:760px){.bc{font-size:10px;padding-top:8px}.bch a{font-size:9px;padding:4px 7px}.grp{padding-top:12px;gap:6px}.grp .gt{font-size:10px;padding:5px 14px}.pgn{padding-top:16px}}</style>
<div class="w" style="padding-top:16px;display:flex;flex-direction:column;gap:12px">
<div class="bc"><a href="/" aria-label="Home">⌂</a><span>›</span>@if(request('sub'))<a href="/{{ $c->slug }}/">{{ $c->name }}</a><span>›</span><h1 style="font-size:inherit;font-weight:inherit">{{ request('sub') }}</h1>@else<h1 style="font-size:inherit;font-weight:inherit">{{ $c->name }}</h1>@endif</div>
@if(!empty($subs[$c->id]))<div class="bch">@foreach($subs[$c->id] as $sb)<a href="/{{ $c->slug }}/?sub={{ rawurlencode($sb->sub) }}" class="{{ request('sub')===$sb->sub ? 'on' : '' }}">{{ $sb->sub }}</a>@endforeach</div>@endif
</div>
<div class="w" style="padding-bottom:40px">
@forelse($groups as $gname => $list)
<div class="grp"><span class="gt">{{ $gname }}</span><div class="gr">@foreach($list as $q)@include('store.card', ['fd'=>true])@endforeach</div></div>
@empty
<p style="padding-top:20px;font-size:13px">No products yet.</p>
@endforelse
@include('store.pager', ['pg'=>$items])
@if($c->intro && $items->currentPage() === 1 && !request('sub'))<div style="margin-top:30px;font-size:12px;line-height:1.8;color:#374151">{{ $c->intro }}</div>@endif
</div>
@endsection
