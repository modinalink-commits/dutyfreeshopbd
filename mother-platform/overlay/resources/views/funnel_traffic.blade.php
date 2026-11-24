{{-- Where visitors come from and where they stop (VisitTrack). Counts browsers that ran our script only. --}}
@php $tr = $tr ?? []; $T = $tr['tot'] ?? null; $N = (int)($T->n ?? 0); $p = fn($a, $b) => $b > 0 ? round($a / $b * 100, 1) . '%' : '—'; @endphp
<div class="card"><h2>Where visitors come from</h2>
@if(!$tr || !$N)<div style="font-size:12.5px;color:#6B7280">Counting started on 24 Nov 2026 (evening). Numbers appear here as visitors arrive; the first full day is the day after.</div>
@else
<div style="font-size:11.5px;color:#6B7280;margin:-4px 0 8px">One row per visit. The source is taken from the first page of the visit. Only browsers that ran the page script are counted, so crawlers that never run scripts are not here. Counting began {{ \Carbon\Carbon::parse($tr['first'], 'UTC')->timezone('Asia/Dhaka')->format('d M Y H:i') }}.</div>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;font-size:12.5px">
@foreach([['Visits', number_format($N)], ['Different browsers', number_format((int)$T->people)], ['Left within 10 s on the first page', number_format((int)$T->bounce) . ' (' . $p($T->bounce, $N) . ')'], ['Outside Bangladesh', number_format((int)$T->abroad) . ' (' . $p($T->abroad, $N) . ')']] as [$l, $val])
<div style="flex:1;min-width:150px;border:1px solid #E5E7EB;border-radius:10px;padding:8px 12px"><span style="display:block;color:#6B7280;font-size:11.5px">{{ $l }}</span><b style="font-size:16px">{{ $val }}</b></div>
@endforeach</div>
@if(($tr['srv'] ?? null) !== null)<div style="font-size:12.5px;margin-bottom:10px;padding:8px 12px;border:1px solid #FDC2A5;border-radius:10px;background:#FFFBF8"><b>Today, two ways of counting:</b> the server saw {{ number_format($tr['srv']) }} different address + browser pairs (the Live Dashboard number); {{ number_format($tr['js']) }} visits ran the page script since counting began. A large gap means automated visits or one person counted several times.</div>@endif
<div style="overflow-x:auto"><table><thead><tr><th>Source</th><th>Visits</th><th>Share</th><th>Left in 10 s</th><th>Saw a product</th><th>Added to cart</th><th>Opened checkout</th><th>Ordered</th><th>Order rate</th><th>Mobile</th><th>Avg time</th><th>Page load</th></tr></thead><tbody>
@foreach($tr['src'] as $x)<tr><td><b>{{ $x->source }}</b></td><td>{{ number_format($x->n) }}</td><td>{{ $p($x->n, $N) }}</td><td>{{ $p($x->bounce, $x->n) }}</td><td>{{ number_format($x->s1) }}</td><td>{{ number_format($x->s2) }}</td><td>{{ number_format($x->s3) }}</td><td>{{ number_format($x->s4) }}</td><td>{{ $p($x->s4, $x->n) }}</td><td>{{ $p($x->mob, $x->n) }}</td><td>{{ $x->secs ? $x->secs . ' s' : '—' }}</td><td>{{ $x->ms ? number_format($x->ms / 1000, 1) . ' s' : '—' }}</td></tr>@endforeach
</tbody></table></div>
<div style="font-size:11.5px;color:#6B7280;margin-top:6px">"Facebook / Instagram" = a click from Facebook or Instagram (ad or post; only ads with tags can be told apart). "Direct / unknown" = no referrer: typed address, bookmark, app, and links in SMS that carry no tag. "Ordered" counts orders placed on the website in the same visit.</div>
@endif</div>
@if($tr && $N)
<div class="card"><h2>Where visits stop, and measured reasons</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;font-size:12.5px">
<div><b>Furthest step reached</b><table><tbody>@foreach($tr['stop'] as $x)<tr><td>{{ \App\Support\VisitTrack::STEPS[(int)$x->step] ?? $x->step }}</td><td style="text-align:right">{{ number_format($x->n) }}</td><td style="text-align:right">{{ $p($x->n, $N) }}</td></tr>@endforeach</tbody></table></div>
<div><b>Measured problems</b><table><tbody>
<tr><td>Landed on "page not found"</td><td style="text-align:right">{{ number_format((int)$T->missing) }}</td><td style="text-align:right">{{ $p($T->missing, $N) }}</td></tr>
<tr><td>Saw an out-of-stock product</td><td style="text-align:right">{{ number_format((int)$T->oos) }}</td><td style="text-align:right">{{ $p($T->oos, $N) }}</td></tr>
<tr><td>First page took over 4 s</td><td style="text-align:right">{{ number_format((int)$T->slow) }}</td><td style="text-align:right">{{ $p($T->slow, $T->timed) }}</td></tr>
<tr><td>Opened checkout, did not order</td><td style="text-align:right">{{ number_format((int)$T->co_left) }}</td><td style="text-align:right">{{ $p($T->co_left, $N) }}</td></tr>
<tr><td>Checkout showed an error message</td><td style="text-align:right">{{ number_format((int)$T->err) }}</td><td style="text-align:right">{{ $p($T->err, $N) }}</td></tr>
</tbody></table></div>
<div><b>First page of the visit</b><table><thead><tr><th>Page</th><th>Visits</th><th>Left in 10 s</th><th>Ordered</th></tr></thead><tbody>@foreach($tr['land'] as $x)<tr><td>{{ $x->land_type }}</td><td>{{ number_format($x->n) }}</td><td>{{ $p($x->bounce, $x->n) }}</td><td>{{ number_format($x->ord) }}</td></tr>@endforeach</tbody></table></div>
@if(count($tr['errs']))<div><b>Checkout error messages</b><table><tbody>@foreach($tr['errs'] as $x)<tr><td>{{ $x->err }}</td><td style="text-align:right">{{ number_format($x->n) }}</td></tr>@endforeach</tbody></table></div>@endif
@if(count($tr['miss']))<div><b>"Not found" addresses people landed on</b><table><tbody>@foreach($tr['miss'] as $x)<tr><td style="word-break:break-all">{{ $x->land_path }}</td><td style="text-align:right">{{ number_format($x->n) }}</td></tr>@endforeach</tbody></table></div>@endif
@if(count($tr['refs']))<div><b>Other websites sending visitors</b><table><tbody>@foreach($tr['refs'] as $x)<tr><td>{{ $x->ref_host }}</td><td style="text-align:right">{{ number_format($x->n) }}</td></tr>@endforeach</tbody></table></div>@endif
</div></div>
@endif
