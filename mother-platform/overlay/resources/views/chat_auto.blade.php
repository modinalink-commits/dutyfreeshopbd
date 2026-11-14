@extends('layout')@section('title','Automatic Messages')
@section('body')
<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:6px"><h1 style="margin:0;flex:1">Automatic Messages</h1><a class="btn g" href="/admin/chat" style="font-size:12px">Back to Messages</a></div>
<p style="font-size:13px;color:#6B7280;margin:0 0 12px">Messages the system sent by itself on WhatsApp: order confirmations, order updates and campaign (promotional) messages. They are kept here and do not bring a conversation into the Messages working list. When the customer answers, the conversation opens in Messages as usual.</p>
<div class="card" style="margin-bottom:12px"><form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
<input name="q" value="{{ $q }}" placeholder="Mobile number or name" style="width:220px">
<select name="type" style="width:auto"><option value="">All types</option>@foreach(['order'=>'Order confirmation','update'=>'Order update','campaign'=>'Campaign / promotion'] as $k=>$v)<option value="{{ $k }}" @selected($type===$k)>{{ $v }}</option>@endforeach</select>
<select name="st" style="width:auto"><option value="">Sent and failed</option><option value="sent" @selected($st==='sent')>Sent</option><option value="failed" @selected($st==='failed')>Failed</option></select>
<input type="date" name="from" value="{{ $from }}" style="width:auto"> <span style="color:#6B7280">to</span> <input type="date" name="to" value="{{ $to }}" style="width:auto">
<button class="btn" type="submit">Apply</button> <a class="btn g" href="/admin/chat/auto">Reset</a>
<span style="margin-left:auto;font-size:13px;color:#0B1220"><b>{{ number_format($total) }}</b> messages · Order confirmation <b>{{ number_format($cnt['order']) }}</b> · Order update <b>{{ number_format($cnt['update']) }}</b> · Campaign <b>{{ number_format($cnt['campaign']) }}</b> · Failed <b style="color:#B91C1C">{{ number_format($cnt['failed']) }}</b></span>
</form></div>
<div class="card" style="padding:0;overflow:hidden"><table style="width:100%"><thead><tr><th style="text-align:left;width:130px">Time</th><th style="text-align:left;width:200px">Customer</th><th style="text-align:left;width:170px">Type</th><th style="text-align:left">Message</th><th style="text-align:left;width:90px">Status</th></tr></thead><tbody>
@forelse($rows as $m)
@php $ty = str_starts_with((string)$m->user_name, 'Campaign:') ? ['Campaign', '#F3E8FF', '#7E22CE'] : (str_starts_with((string)$m->user_name, 'Order update') ? ['Order update', '#E0F2FE', '#075985'] : ['Order confirmation', '#DCFCE7', '#166534']); @endphp
<tr><td style="white-space:nowrap;font-size:12px;color:#4B5563">{{ \Carbon\Carbon::parse($m->created_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</td>
<td><b>{{ $m->name ?: 'Customer' }}</b><br><a href="/admin/chat/auto?q={{ urlencode((string)$m->phone) }}" style="font-size:12px;color:#1D5FA8">{{ $m->phone }}</a></td>
<td><span class="pill" style="background:{{ $ty[1] }};color:{{ $ty[2] }}">{{ $ty[0] }}</span>@if($ty[0]==='Campaign')<br><small style="color:#6B7280">{{ \Illuminate\Support\Str::limit(trim(substr((string)$m->user_name, 9)), 40) }}</small>@endif</td>
<td style="font-size:13px;white-space:pre-wrap;word-break:break-word">{{ \Illuminate\Support\Str::limit((string)$m->body, 320) }}</td>
<td>@if($m->status==='failed')<span class="pill" style="background:#FEE2E2;color:#B91C1C" title="{{ $m->error }}">Failed</span>@else<span class="pill" style="background:#DCFCE7;color:#166534">Sent</span>@endif</td></tr>
@empty
<tr><td colspan="5" style="color:#6B7280;padding:18px">No automatic messages found for this filter.</td></tr>
@endforelse
</tbody></table></div>
<div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;font-size:13px">
@php $qs = array_filter(['q'=>$q, 'type'=>$type, 'st'=>$st, 'from'=>$from, 'to'=>$to]); @endphp
@if($page > 1)<a class="btn g" href="/admin/chat/auto?{{ http_build_query($qs + ['page'=>$page - 1]) }}">‹ Previous</a>@else <span></span> @endif
<span>Page {{ $page }} / {{ $pages }}</span>
@if($page < $pages)<a class="btn g" href="/admin/chat/auto?{{ http_build_query($qs + ['page'=>$page + 1]) }}">Next ›</a>@else <span></span> @endif
</div>
@endsection
