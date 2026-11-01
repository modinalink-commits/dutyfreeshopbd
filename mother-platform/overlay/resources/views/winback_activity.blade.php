@php $wa = \App\Support\Winback::activity($phone); @endphp
<div class="card" style="max-width:640px"><h2>Customer Activity</h2><div style="font-size:12.5px;line-height:1.8">
<div><b>Last Order (delivered):</b> {{ $wa['last'] ?? '—' }}</div>
<div><b>Days Since Last Order:</b> {{ $wa['days'] ?? '—' }}{{ $wa['reset'] ? ' (cycle reset by a newer order)' : '' }}</div>
<div><b>Current Inactivity Stage:</b> {{ $wa['stage'] ? $wa['stage'][0].' days' : 'None' }}</div>
<div><b>Current Win-Back Discount:</b> {{ $wa['active'] ? rtrim(rtrim(number_format($wa['active']->pct,2),'0'),'.').'%' : '—' }}</div>
<div><b>Last Offer Sent:</b> {{ $wa['event'] ? \Carbon\Carbon::parse($wa['event']->created_at)->timezone('Asia/Dhaka')->format('d M Y').' · '.rtrim(rtrim(number_format($wa['event']->pct,2),'0'),'.').'%' : '—' }}</div>
<div><b>Offer Used:</b> {{ $wa['event'] ? ($wa['event']->used_at ? 'Used' : 'Not used') : '—' }}</div>
<div><b>Next Stage:</b> {{ $wa['next'] ? $wa['next'][0].' days → '.rtrim(rtrim(number_format($wa['next'][1],2),'0'),'.').'%' : '—' }}</div></div></div>
