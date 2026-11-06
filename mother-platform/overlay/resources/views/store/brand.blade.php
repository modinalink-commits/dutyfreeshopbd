@extends('store.layout')
@section('ld')<script type="application/ld+json">{!! json_encode(['@'.'context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>url('/').'/'],['@type'=>'ListItem','position'=>2,'name'=>$crumb,'item'=>url('/brand/'.$b->slug)]]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script><script type="application/ld+json">{!! json_encode(array_filter(['@'.'context'=>'https://schema.org','@type'=>(($b->schema_type ?? '') ?: 'Brand'),'name'=>$b->name,'url'=>url('/brand/'.$b->slug),'description'=>$desc,'logo'=>!empty($b->logo) ? url($b->logo) : null]), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>@endsection
@section('main')
@php
  $cur = '/brand/'.$b->slug;
  $qs = fn(array $o) => $cur.'?'.http_build_query(array_filter(array_merge(request()->only(['min','max','sort']), $o), fn($v) => $v !== null && $v !== ''));
  $sorts = [''=>'Newest','new'=>'Newest first','popular'=>'Most in stock','price_asc'=>'Price: low to high','price_desc'=>'Price: high to low'];
@endphp
<style>
.cb{display:flex;gap:8px;align-items:center;font-size:13px;color:#6B7280;padding-top:16px}.cb a:hover{color:var(--o)}.cb b{color:var(--n);font-weight:600}
.bh{margin-top:10px;border-radius:16px;background:linear-gradient(120deg,#0B1220,#1B2540 65%,#3A2418);color:#fff;padding:18px 26px;display:flex;gap:18px;align-items:center}.bh img{width:72px;height:72px;object-fit:contain;background:#fff;border-radius:12px;padding:6px}.bh h1{margin:0 0 4px;font-size:26px}.bh p{margin:0;font-size:14px;opacity:.9}
.tb2{display:flex;align-items:center;gap:12px;border:1px solid #EAEAEA;border-radius:14px;padding:12px 16px;background:#fff;margin:18px 0 14px;font-size:14px;color:#4B5563}.tb2 .sp{flex:1}.tb2 select{border:1px solid #D1D5DB;border-radius:8px;padding:8px 10px;font-size:14px;background:#fff}
.bg .gr{grid-template-columns:repeat(5,minmax(0,1fr))}@media(max-width:1200px){.bg .gr{grid-template-columns:repeat(4,minmax(0,1fr))}}@media(max-width:900px){.bg .gr{grid-template-columns:repeat(2,minmax(0,1fr))}.bh{padding:14px;gap:12px}.bh h1{font-size:20px}.bh img{width:54px;height:54px}}
.bd{padding:6px 0 34px;font-size:14px;line-height:1.8;color:#374151;max-width:900px}.bd h2{font-size:17px;color:var(--n)}
</style>
<div class="w">
<div class="cb"><a href="/">Home</a>›<b>{{ $crumb }}</b></div>
<div class="bh">@if(!empty($b->logo))<img src="{{ $b->logo }}" alt="{{ $alt }}" width="72" height="72">@endif<div><h1>{{ $b->name }}</h1><p>{{ trim((string)($b->short_description ?? '')) ?: 'Browse '.$b->name.' products at DutyFreeBD.' }}</p></div></div>
<div class="bg"><div class="tb2"><span>Showing <b>{{ $items->count() }}</b> of {{ number_format($items->total()) }} products</span><span class="sp"></span><label for="so">Sort by</label><select id="so" onchange="location=this.value">@foreach($sorts as $k=>$lb)<option value="{{ $qs(['sort'=>$k ?: null,'page'=>null]) }}" {{ (string)request('sort')===(string)$k ? 'selected' : '' }}>{{ $lb }}</option>@endforeach</select></div>
@if($items->count())<div class="gr">@foreach($items as $q)@include('store.card', ['fd'=>true])@endforeach</div>@else<div style="text-align:center;padding:60px 10px;color:#6B7280"><b style="display:block;font-size:18px;color:var(--n)">No products available right now</b></div>@endif
@include('store.pager', ['pg'=>$items])</div>
@if($items->currentPage() === 1 && (trim((string)($b->description ?? '')) !== '' || trim((string)($b->seo_content ?? '')) !== ''))<div class="bd">@if(trim((string)($b->description ?? '')) !== '')<p>{{ $b->description }}</p>@endif @if(trim((string)($b->seo_content ?? '')) !== '')<div>{!! nl2br(e($b->seo_content)) !!}</div>@endif</div>@endif
</div>
@endsection
