@php $cname = \Illuminate\Support\Facades\DB::table('customers')->where('id', (int)$cid)->value('name') ?: ($phone ?? 'customer'); $au = \App\Support\AppUsers::forCustomers([(int)$cid])[(int)$cid] ?? null; $arw = \App\Support\AppReward::of((string)($phone ?? '')); $aord = \Illuminate\Support\Facades\DB::table('orders')->where('customer_id', (int)$cid)->where('via_app', true)->count(); @endphp
<div class="card" style="max-width:640px"><h2>📱 App User</h2><div style="font-size:12.5px;line-height:1.8">
@if($au)
<div><b>Status:</b> {!! $au['live'] ? '<span style="color:#166534;font-weight:700">🟢 Live in the App now</span>' : 'Offline' !!}</div>
<div><b>App installed:</b> Yes{{ $au['installs'] > 1 ? ' ('.$au['installs'].' devices)' : '' }}</div>
<div><b>Installed / first seen:</b> {{ $au['first'] ? \Carbon\Carbon::parse($au['first'])->timezone('Asia/Dhaka')->format('d M Y, h:i A') : '—' }}</div>
<div><b>Last seen in App:</b> {{ $au['last'] ? \Carbon\Carbon::parse($au['last'])->timezone('Asia/Dhaka')->format('d M Y, h:i A').' · '.\Carbon\Carbon::parse($au['last'])->diffForHumans() : '—' }}</div>
<div><b>Logged in via App:</b> {{ $au['logged'] ? 'Yes' : 'No (installed only)' }}</div>
<div><b>Device:</b> {{ $au['dev'] }}</div>
@else
<div><b>App installed:</b> Not seen in the App yet</div>
@endif
@php $pn = \Illuminate\Support\Facades\DB::table('push_subscriptions')->where('customer_id', (int)$cid)->where('active', true)->count(); @endphp
<div><b>Push notifications:</b> {{ $pn > 0 ? 'On ('.$pn.' device'.($pn > 1 ? 's' : '').')' : 'Off / not subscribed' }}</div>
<div><b>App orders:</b> {{ $aord }}</div>
<div><b>App Install Reward:</b> {{ $arw ? '৳'.number_format((float)$arw->amount).' · '.ucfirst($arw->status).($arw->invoice && in_array($arw->status,['used','reserved']) ? ' · Invoice '.$arw->invoice : '') : 'Not unlocked' }}</div>
</div><div style="margin-top:10px;font-size:12.5px"><b style="font-size:13px">🔔 Send notification to this customer</b>@include('components.app-notify-form',['cid'=>(int)$cid,'name'=>$cname ?? 'customer'])</div></div>
