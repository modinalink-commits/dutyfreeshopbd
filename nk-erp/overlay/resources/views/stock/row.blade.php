@php($sg = fn ($n) => $n > 0 ? '+'.number_format($n) : ($n < 0 ? '−'.number_format(abs($n)) : ''))
<tr>
  <td style="white-space:nowrap">{{ $m->created_at->timezone('Asia/Dhaka')->format($withReverse ? 'd-m-Y H:i' : 'H:i') }}</td>
  <td style="white-space:nowrap">{{ $m->ref_no }}</td>
  <td><span class="pill">{{ \App\Models\StockMovement::KINDS[$m->kind] ?? $m->kind }}</span></td>
  <td>{{ $m->brand?->name }}</td><td>{{ $m->size_kg }} kg</td>
  <td class="c">{{ $sg($m->full_delta) }}</td>
  <td class="c">{{ $sg($m->empty_delta) }}@if($m->company_delta) <span class="sub">(company {{ $sg($m->company_delta) }})</span>@endif @if($m->damaged_delta) <span class="sub">(damaged {{ $sg($m->damaged_delta) }})</span>@endif</td>
  <td>{{ $m->reference }}@if($m->reference && $m->note) · @endif<span class="sub">{{ $m->note }}</span></td>
  <td>{{ $m->user?->name }}</td>
  @if($withReverse)
  <td style="white-space:nowrap">
    @if($m->reversedBy)<span class="pill st-cancelled">Reversed by {{ $m->reversedBy->ref_no }}</span>
    @elseif(! in_array($m->kind, ['reversal', 'exchange']) && in_array(auth()->user()->role, ['super_admin', 'admin']))
      <form method="post" action="{{ route('stock.reverse', $m) }}" style="display:flex;gap:4px" onsubmit="return this.reason.value.trim().length > 0">@csrf<input type="text" name="reason" placeholder="Reason" style="min-height:30px;width:120px"><button class="btn sm" type="submit">Reverse</button></form>
    @endif
  </td>
  @endif
</tr>
