@if($live->count())
<div class="lg2" data-cr data-sp="3800" data-sig="{{ md5(json_encode($live->map(fn($l) => [$l->pid, $l->created_at, $l->qty, $l->price])->all())) }}">@foreach($live as $l)
@php
  $lu = $l->pid ? \App\Http\Controllers\StoreController::url((object)['id'=>$l->pid,'ext_id'=>$l->ext_id,'name'=>$l->pname,'category_id'=>$l->pcat,'sub'=>$l->psub,'sub_sub'=>$l->psubsub ?? null,'path_slug'=>$l->pslug]) : null;
  $lt = \Carbon\Carbon::parse($l->created_at); $lm = max(0, (int) $lt->diffInMinutes(now(), true));
  $lw = $lm < 1 ? 'Just now' : ($lm < 60 ? $lm.' minute'.($lm > 1 ? 's' : '').' ago' : ($lm < 1440 ? intdiv($lm, 60).' hour'.(intdiv($lm, 60) > 1 ? 's' : '').' ago' : intdiv($lm, 1440).' day'.(intdiv($lm, 1440) > 1 ? 's' : '').' ago'));
@endphp
<div class="lo" data-ts="{{ $lt->toIso8601String() }}"><a class="bx" @if($lu) href="{{ $lu }}" @endif>
<div class="im"><span class="tg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><i class="ago">{{ $lw }}</i></span>@if($l->image)<img src="{{ $l->image }}" @php $__ss = \App\Support\Img::srcset($l->image); @endphp @if($__ss) srcset="{{ $__ss }}" sizes="170px" @endif alt="{{ $l->name }}" width="150" height="150" loading="lazy" draggable="false">@endif</div>
<span class="wh">Verified order</span><span class="nm">{{ $l->name }}</span>
<div class="rw"><b>{{ number_format($l->price) }} TK</b><span>x{{ $l->qty }}</span></div><span class="dl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7.5" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/></svg>{{ (float)$l->shipping > 0 ? 'Delivery ৳'.number_format($l->shipping) : 'FREE delivery' }}</span></a></div>
@endforeach</div>
@else
<div class="lvempty">Recent orders will appear here soon.</div>
@endif
