@extends('store.layout')
@section('main')
@php
  $bd = ['Delivered'=>['#DCFCE7','#15803D'], 'Pending'=>['#FFEDD5','#C2410C'], 'Confirmed'=>['#E0F2FE','#0369A1'], 'Processing'=>['#E0F2FE','#0369A1'], 'Shipment'=>['#DBEAFE','#1D4ED8'], 'Cancelled'=>['#FEE2E2','#B91C1C'], 'Returned'=>['#F3E8FF','#7E22CE']];
  $badge = fn($g) => 'background:'.($bd[$g][0] ?? '#F3F4F6').';color:'.($bd[$g][1] ?? '#374151');
  $gl = ['Pending'=>'Pending','Confirmed'=>'Confirmed','Processing'=>'Processing','Shipment'=>'Shipment','Delivered'=>'Delivered','Cancelled'=>'Cancelled','Returned'=>'Returned'];
  $catc = ['VIP'=>['#FEF3C7','#B45309'], 'High Value'=>['#EDE9FE','#6D28D9'], 'Regular'=>['#DBEAFE','#1D4ED8'], 'New'=>['#DCFCE7','#15803D']][$cat] ?? ['#F3F4F6','#374151'];
  $tk = fn($n) => '৳'.number_format((float)$n);
  $nav = [['dashboard','Dashboard','▦'],['profile','My Profile','👤'],['orders','My Orders','🧾'],['track','Track Order','📍'],['addresses','Address Book','🏠'],['wishlist','My Wishlist','♡'],['coupons','My Coupons','🎟'],['reviews','My Reviews','★'],['support','Support / Chat','💬'],['notifications','Notifications','🔔'],['security','Change Password','🔒']];
  $dt = fn($v) => \Carbon\Carbon::parse($v)->timezone('Asia/Dhaka');
  $img = fn($u) => $u ?: '/icon.png';
@endphp
<style>
.ac{display:grid;grid-template-columns:250px minmax(0,1fr);gap:20px;padding:22px 0 46px;align-items:start}
.acs{background:#fff;border:1.25px solid var(--bd);border-radius:16px;padding:16px 12px;position:sticky;top:10px}
.acp{text-align:center;padding:4px 6px 12px}.acp .av{width:68px;height:68px;border-radius:50%;background:var(--n);color:#fff;font-size:28px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 8px}
.acp b{display:block;font-size:15px;color:var(--n)}.acp small{display:block;color:var(--mut);font-size:12px;margin-top:2px}.cb{display:inline-block;margin-top:7px;padding:3px 11px;border-radius:14px;font-size:11.5px;font-weight:800}
.acs a.i{display:flex;gap:10px;align-items:center;padding:10px 12px;border-radius:10px;font-size:13.5px;color:#374151;text-decoration:none;font-weight:600}.acs a.i:hover{background:#FFF1E8}.acs a.i.on{background:var(--o);color:#fff}.acs a.i span{width:18px;text-align:center}
.acm{min-width:0}.hello{background:linear-gradient(100deg,#EEF4FF,#FFF4EC);border:1.25px solid var(--bd);border-radius:16px;padding:18px 20px;margin-bottom:14px}.hello h1{margin:0;font-size:20px;color:var(--n)}.hello p{margin:4px 0 0;color:#4B5563;font-size:13px}
.cards{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:14px}.k{background:#fff;border:1.25px solid #F1E3D8;border-radius:14px;padding:13px 14px}.k small{display:block;color:var(--mut);font-size:12px}.k b{display:block;font-size:21px;color:var(--n);margin-top:3px}
.bx{background:#fff;border:1.25px solid #F1E3D8;border-radius:16px;padding:16px 18px;margin-bottom:14px}.bx h2{margin:0 0 12px;font-size:15.5px;color:var(--n);display:flex;justify-content:space-between;align-items:center}.bx h2 a{font-size:12.5px;color:var(--o);text-decoration:none;font-weight:600}
table.t{width:100%;border-collapse:collapse;font-size:13px}table.t th{text-align:left;color:var(--mut);font-weight:600;font-size:12px;padding:8px 8px;border-bottom:1px solid #F1E3D8}table.t td{padding:10px 8px;border-bottom:1px solid #F8EFE8;vertical-align:middle}
.st{display:inline-block;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:700}.th{display:flex;gap:4px}.th img{width:36px;height:36px;border-radius:8px;object-fit:cover;border:1px solid #eee;background:#fff}
.b1{display:inline-block;border:1.25px solid #E5E7EB;background:#fff;border-radius:9px;padding:6px 11px;font-size:12.5px;font-weight:600;cursor:pointer;color:var(--n);text-decoration:none}.b1:hover{border-color:var(--o);color:var(--o)}.b1.p{background:var(--o);border-color:var(--o);color:#fff}.b1.p:hover{color:#fff;opacity:.92}.b1.r{color:#B91C1C}
.qa{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}.qa a{background:#fff;border:1.25px solid #F1E3D8;border-radius:14px;padding:14px;text-decoration:none;color:var(--n);display:flex;gap:11px;align-items:center}.qa a:hover{border-color:var(--o)}.qa i{font-style:normal;font-size:20px;width:40px;height:40px;border-radius:11px;background:#FFF1E8;display:flex;align-items:center;justify-content:center}.qa b{display:block;font-size:13.5px}.qa small{color:var(--o);font-size:12px}
.f label{display:block;font-size:12px;font-weight:700;margin:11px 0 5px;color:var(--n)}.f input,.f select,.f textarea{width:100%;border:1.25px solid #E5E7EB;border-radius:10px;padding:10px 12px;font:inherit;font-size:14px;box-sizing:border-box;background:#fff}.f input:focus,.f select:focus,.f textarea:focus{outline:0;border-color:var(--o)}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}.msg{font-size:12.5px;margin-top:9px;min-height:16px}.er{color:#B91C1C}.ok2{color:#15803D}
.flt{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px}.flt a{padding:6px 12px;border-radius:16px;border:1.25px solid #E5E7EB;font-size:12.5px;font-weight:600;text-decoration:none;color:#374151;background:#fff}.flt a.on{background:var(--n);border-color:var(--n);color:#fff}
.tl{display:flex;margin:6px 0 4px}.tl div{flex:1;text-align:center;font-size:11px;font-weight:600;color:#6B7280;position:relative}.tl i{display:block;width:26px;height:26px;border-radius:50%;margin:0 auto 6px;line-height:26px;font-style:normal;color:#fff;background:#D1D5DB;position:relative;z-index:1}.tl div:before{content:'';position:absolute;top:12px;left:-50%;width:100%;height:3px;background:#E5E7EB}.tl div:first-child:before{display:none}.tl .d i{background:#16A34A}.tl .d:before,.tl .c:before{background:#16A34A}.tl .c i{background:var(--o)}.tl small{display:block;font-weight:400;color:#9CA3AF;font-size:10.5px}
.ads{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.ad{border:1.25px solid #F1E3D8;border-radius:13px;padding:13px 15px;font-size:13px;line-height:1.55}.ad .lb{display:inline-block;background:#FFF1E8;color:#C2410C;font-weight:700;font-size:11.5px;padding:2px 9px;border-radius:9px;margin-right:6px}
.wg{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.wc{border:1.25px solid #F1E3D8;border-radius:13px;padding:10px;text-align:center;font-size:13px}.wc img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px}.wc b{display:block;margin:7px 0 3px;font-size:13px;color:var(--n);height:34px;overflow:hidden}
.cp{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.cpc{border:1.5px dashed var(--o);border-radius:13px;padding:13px 15px;background:#FFFAF6}.cpc b{font-size:17px;letter-spacing:1px;color:var(--o)}
.ntf{display:flex;gap:12px;padding:11px 0;border-bottom:1px solid #F8EFE8;font-size:13.5px;text-decoration:none;color:var(--n)}.ntf i{font-style:normal;font-size:20px}.ntf small{display:block;color:#9CA3AF;font-size:11.5px}
.cht{background:#FFF8F3;border-radius:12px;padding:12px;max-height:280px;overflow:auto;display:flex;flex-direction:column;gap:6px;margin-bottom:12px}.cht div{max-width:82%;padding:8px 11px;border-radius:13px;font-size:13px;white-space:pre-wrap;word-break:break-word}.cht .in{align-self:flex-end;background:var(--o);color:#fff}.cht .out{align-self:flex-start;background:#fff;border:1px solid #F1E3D8}.cht small{display:block;font-size:10.5px;opacity:.7;margin-top:2px}
.stars button{border:0;background:none;font-size:24px;color:#D1D5DB;cursor:pointer;padding:0 2px}.stars button.on{color:#F59E0B}
.mo{display:none;position:fixed;inset:0;background:rgba(11,18,32,.5);z-index:80;align-items:center;justify-content:center;padding:12px}.mo.op{display:flex}.mo .bd{background:#fff;border-radius:16px;padding:18px 20px;width:520px;max-width:100%;max-height:92vh;overflow:auto}
.mnav{display:none}
@media(max-width:900px){.cards{grid-template-columns:repeat(3,1fr)}.wg{grid-template-columns:repeat(3,1fr)}.qa{grid-template-columns:repeat(2,1fr)}}
@media(max-width:760px){.ac{grid-template-columns:1fr;gap:12px;padding:10px 0 26px}.acs{position:static;padding:10px}.acp{display:flex;gap:12px;align-items:center;text-align:left;padding:2px 4px 8px}.acp .av{width:46px;height:46px;font-size:19px;margin:0}.navs{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none}.navs::-webkit-scrollbar{display:none}.acs a.i{white-space:nowrap;padding:8px 12px;background:#F9FAFB;font-size:13px}
.cards{grid-template-columns:repeat(2,1fr)}.ads,.cp,.g2{grid-template-columns:1fr}.wg{grid-template-columns:repeat(2,1fr)}.qa{grid-template-columns:repeat(2,1fr)}.hello{padding:14px}.hello h1{font-size:17px}
table.t.rs thead{display:none}table.t.rs,table.t.rs tbody,table.t.rs tr,table.t.rs td{display:block;width:100%;box-sizing:border-box}table.t.rs tr{border:1.25px solid #F1E3D8;border-radius:13px;padding:8px 12px;margin-bottom:10px}table.t.rs td{border:0;padding:4px 0;display:flex;justify-content:space-between;gap:10px;align-items:center}table.t.rs td:before{content:attr(data-l);color:var(--mut);font-size:12px}
.tl div{font-size:9.5px}.tl i{width:22px;height:22px;line-height:22px}.tl div:before{top:10px}}
</style>
<div class="w"><div class="ac">
<aside class="acs"><div class="acp"><div class="av">{{ mb_strtoupper(mb_substr($c->name, 0, 1)) }}</div><div><b>{{ $c->name }}</b><small>{{ $c->phone }} · {{ $cid }}</small><span class="cb" style="background:{{ $catc[0] }};color:{{ $catc[1] }}">{{ $cat }} Customer</span></div></div>
<div class="navs">@foreach($nav as [$k,$l,$ic])<a class="i {{ $tab===$k ? 'on' : '' }}" href="{{ $k==='track' ? '/track' : '/account'.($k==='dashboard' ? '' : '/'.$k) }}"><span>{{ $ic }}</span>{{ $l }}</a>@endforeach<a class="i" href="/account/logout"><span>⎋</span>Logout</a></div></aside>
<div class="acm">

@if($tab==='dashboard')
<div class="hello"><h1>আসসালামু আলাইকুম, {{ $c->name }}! 👋</h1><p>আপনার অ্যাকাউন্টে স্বাগতম। আমরা সবসময় আপনার সেবায় প্রস্তুত।</p></div>
<div class="cards"><div class="k"><small>Total Orders</small><b>{{ $totalOrders }}</b></div><div class="k"><small>Delivered</small><b style="color:#15803D">{{ $cnt['Delivered'] }}</b></div><div class="k"><small>Pending</small><b style="color:#C2410C">{{ $cnt['Pending'] + $cnt['Confirmed'] + $cnt['Processing'] + $cnt['Shipment'] }}</b></div><div class="k"><small>Cancelled</small><b style="color:#B91C1C">{{ $cnt['Cancelled'] }}</b></div><div class="k"><small>Returned</small><b style="color:#7E22CE">{{ $cnt['Returned'] }}</b></div><div class="k"><small>Total Spend</small><b>{{ $tk($spend) }}</b></div></div>
<div class="bx"><h2>Recent Orders <a href="/account/orders">View All Orders →</a></h2>
@if($recent->isEmpty())<p style="color:#6B7280;font-size:13.5px;margin:0">এখনো কোনো অর্ডার নেই। <a href="/" style="color:var(--o);font-weight:700">শপিং শুরু করুন →</a></p>@else
<table class="t rs"><thead><tr><th>Order ID</th><th>Date</th><th>Products</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($recent as $o)@php $g = collect($groups)->first(fn($x) => in_array($o->status, \App\Http\Controllers\AccountController::GROUPS[$x])) ?? 'Pending'; @endphp
<tr><td data-l="Order ID"><b>{{ $o->invoice }}</b></td><td data-l="Date">{{ $dt($o->created_at)->format('d M Y') }}</td><td data-l="Products"><div class="th">@foreach(array_slice($items[$o->id] ?? [], 0, 3) as $it)<img src="{{ $img($it->image) }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/icon.png'">@endforeach</div></td><td data-l="Amount">{{ $tk($o->total) }}</td><td data-l="Status"><span class="st" style="{{ $badge($g) }}">{{ $g }}</span></td><td><a class="b1" href="/account/orders/{{ $o->invoice }}">View</a></td></tr>
@endforeach</tbody></table>@endif</div>
<div class="qa"><a href="/account/wishlist"><i>♡</i><div><b>My Wishlist</b><small>{{ $wishN }} Products</small></div></a><a href="/account/coupons"><i>🎟</i><div><b>My Coupons</b><small>{{ $couponN }} Available</small></div></a><a href="/account/addresses"><i>🏠</i><div><b>Address Book</b><small>{{ $addrN }} Addresses</small></div></a><a href="/account/support"><i>💬</i><div><b>Support / Chat</b><small>Chat with us</small></div></a></div>
<div class="bx"><h2>Account Information <a href="/account/profile">Edit</a></h2><table class="t"><tr><td style="color:#6B7280;width:160px">Full Name</td><td>{{ $c->name }}</td></tr><tr><td style="color:#6B7280">Mobile Number</td><td>{{ $c->phone }}</td></tr><tr><td style="color:#6B7280">Email Address</td><td>{{ $c->email ?: '—' }}</td></tr><tr><td style="color:#6B7280">Customer Type</td><td>{{ $cat }} Customer</td></tr><tr><td style="color:#6B7280">Member Since</td><td>{{ $dt($c->registered_at ?? $c->created_at)->format('d M Y') }}</td></tr></table></div>
@endif

@if($tab==='profile')
<div class="bx"><h2>My Profile <span class="cb" style="background:{{ $catc[0] }};color:{{ $catc[1] }}">{{ $cat }} Customer</span></h2>
<form class="f" id="pf"><div class="g2"><div><label>Full Name</label><input name="name" value="{{ $c->name }}" required maxlength="80"></div><div><label>Mobile Number</label><input value="{{ $c->phone }}" disabled><div style="font-size:11.5px;color:#6B7280;margin-top:4px">মোবাইল নম্বর বদলাতে সাপোর্টে যোগাযোগ করুন।</div></div>
<div><label>Email</label><input type="email" name="email" value="{{ $c->email }}" maxlength="120"></div><div><label>Date of Birth (ঐচ্ছিক)</label><input type="date" name="dob" value="{{ $c->dob ? \Carbon\Carbon::parse($c->dob)->format('Y-m-d') : '' }}" max="{{ now()->format('Y-m-d') }}"></div>
<div><label>Customer ID</label><input value="{{ $cid }}" disabled></div><div><label>Member Since</label><input value="{{ $dt($c->registered_at ?? $c->created_at)->format('d M Y') }}" disabled></div></div>
<div style="margin-top:14px"><button class="b1 p" style="padding:10px 22px;font-size:14px">Save Changes</button></div><div class="msg" id="pm"></div></form></div>
@endif

@if($tab==='orders' && !isset($oi))
<div class="bx"><h2>My Orders</h2>
<form action="/account/orders" method="get" style="display:flex;gap:8px;margin-bottom:12px"><input type="hidden" name="status" value="{{ $f }}"><input name="q" value="{{ $q }}" placeholder="Order ID বা Product Name দিয়ে খুঁজুন" style="flex:1;border:1.25px solid #E5E7EB;border-radius:10px;padding:10px 12px;font-size:14px"><button class="b1 p">Search</button></form>
<div class="flt">@foreach(array_merge(['All'], $groups) as $g)<a class="{{ $f===$g ? 'on' : '' }}" href="/account/orders?status={{ $g }}{{ $q!=='' ? '&q='.urlencode($q) : '' }}">{{ $g==='All' ? 'All Orders' : $g }}</a>@endforeach</div>
@if($list->isEmpty())<p style="color:#6B7280;font-size:13.5px">কোনো অর্ডার পাওয়া যায়নি।</p>@else
<table class="t rs"><thead><tr><th>Order ID</th><th>Date</th><th>Products</th><th>Amount</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead><tbody>
@foreach($list as $o)@php $g = collect($groups)->first(fn($x) => in_array($o->status, \App\Http\Controllers\AccountController::GROUPS[$x])) ?? 'Pending'; $first = ($items[$o->id] ?? [])[0] ?? null; @endphp
<tr><td data-l="Order ID"><b>{{ $o->invoice }}</b></td><td data-l="Date">{{ $dt($o->created_at)->format('d M Y') }}</td><td data-l="Products"><div class="th">@foreach(array_slice($items[$o->id] ?? [], 0, 3) as $it)<img src="{{ $img($it->image) }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/icon.png'">@endforeach</div></td><td data-l="Amount">{{ $tk($o->total) }}</td><td data-l="Payment">{{ $o->pay_status ?: ($o->payment_method ?: 'COD') }}</td><td data-l="Status"><span class="st" style="{{ $badge($g) }}">{{ $g }}</span></td><td><a class="b1" href="/account/orders/{{ $o->invoice }}">View</a></td></tr>
@endforeach</tbody></table>@endif</div>
@endif

@if($tab==='orders' && isset($oi))
<div class="bx"><h2>Order {{ $o->invoice }} <a href="/account/orders">← সব অর্ডার</a></h2>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px"><span class="st" style="{{ $badge($grp) }}">{{ $grp }}</span><span style="font-size:13px;color:#6B7280">Placed {{ $dt($o->created_at)->format('d M Y, h:i A') }}</span></div>
@if(!$bad)<div class="tl">@foreach(\App\Http\Controllers\AccountController::STEPS as $i => $sn)<div class="{{ $i < $step || ($i===$step && $step===5) ? 'd' : ($i===$step ? 'c' : '') }}"><i>{{ $i < $step || ($i===$step && $step===5) ? '✓' : $i+1 }}</i>{{ $sn }}@if(isset($at[$i]))<small>{{ $dt($at[$i])->format('d M') }}</small>@endif</div>@endforeach</div>
@else<div style="background:#FEF2F2;color:#B91C1C;border-radius:10px;padding:10px 14px;font-size:13.5px;font-weight:600">এই অর্ডারের বর্তমান অবস্থা: {{ $grp }}</div>@endif</div>
<div class="bx"><h2>Items</h2><table class="t">@foreach($oi as $it)<tr><td style="width:56px"><img src="{{ $img($it->image) }}" alt="" width="46" height="46" style="border-radius:8px;object-fit:cover" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/icon.png'"></td><td>{{ $it->name }}<div style="color:#6B7280;font-size:12px">Qty {{ $it->qty }} × {{ $tk($it->price) }}</div></td><td style="text-align:right">{{ $tk($it->price * $it->qty) }}</td></tr>@endforeach
<tr><td></td><td>Delivery Charge</td><td style="text-align:right">{{ $tk($o->shipping) }}</td></tr>@if((float)$o->discount > 0)<tr><td></td><td>Discount</td><td style="text-align:right;color:#15803D">-{{ $tk($o->discount) }}</td></tr>@endif<tr><td></td><td><b>Grand Total</b></td><td style="text-align:right"><b>{{ $tk($o->total) }}</b></td></tr></table></div>
<div class="bx"><h2>Payment & Delivery</h2><table class="t"><tr><td style="color:#6B7280;width:170px">Invoice Number</td><td>{{ $o->invoice }}</td></tr><tr><td style="color:#6B7280">Payment Method</td><td>{{ $o->payment_method ?: 'Cash on Delivery' }}</td></tr><tr><td style="color:#6B7280">Payment Status</td><td>{{ $o->pay_status ?: ($grp==='Delivered' ? 'Paid' : 'Due') }}</td></tr><tr><td style="color:#6B7280">Courier</td><td>{{ $o->courier ?: '—' }}</td></tr><tr><td style="color:#6B7280">Tracking ID</td><td>{{ $o->tracking_code ?: ($o->consignment_id ?: '—') }}@if($o->courier_status) · {{ $o->courier_status }}@endif</td></tr></table>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px"><a class="b1 p" href="{{ $inv }}" target="_blank" rel="noopener">⬇ Download Invoice</a><a class="b1" href="/track?invoice={{ $o->invoice }}">📍 Track Order</a><a class="b1" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode('DutyFree Shop BD ইনভয়েস '.$o->invoice.': '.$inv) }}">WhatsApp এ শেয়ার</a><button class="b1" type="button" data-reorder="{{ $o->invoice }}">↻ Reorder</button></div><div class="msg" id="rom"></div></div>
@endif

@if($tab==='addresses')
<div class="bx"><h2>Address Book <button class="b1 p" type="button" id="adn">+ নতুন ঠিকানা</button></h2>
@if($addrs->isEmpty())<p style="color:#6B7280;font-size:13.5px">কোনো ঠিকানা সেভ করা নেই।</p>@endif
<div class="ads">@foreach($addrs as $a)<div class="ad"><span class="lb">{{ $a->label }}</span>@if($a->is_default)<span class="lb" style="background:#DCFCE7;color:#15803D">Default</span>@endif<br><b>{{ $a->receiver }}</b> · {{ $a->phone }}<br>{{ implode(', ', array_filter([$a->road, $a->area, $a->thana, $a->district])) }}@if($a->landmark)<br><span style="color:#6B7280">Landmark: {{ $a->landmark }}</span>@endif
<div style="margin-top:9px;display:flex;gap:6px;flex-wrap:wrap"><button class="b1" type="button" data-edit='@json($a)'>Edit</button>@if(!$a->is_default)<button class="b1" type="button" data-def="{{ $a->id }}">Default করুন</button>@endif<button class="b1 r" type="button" data-del="{{ $a->id }}">Delete</button></div></div>@endforeach</div></div>
<div class="mo" id="amo"><div class="bd"><h2 style="margin:0 0 6px;font-size:16px" id="amt">ঠিকানা</h2><form class="f" id="af"><input type="hidden" name="id"><div class="g2"><div><label>Label</label><select name="label"><option>Home</option><option>Office</option><option>Other</option></select></div><div><label>Receiver Name</label><input name="receiver" required maxlength="80" value="{{ $c->name }}"></div>
<div><label>Mobile Number</label><input name="phone" required inputmode="tel" value="{{ $c->phone }}"></div><div><label>District</label><input name="district" required maxlength="60" list="dl"></div><div><label>Thana</label><input name="thana" maxlength="60"></div><div><label>Area</label><input name="area" maxlength="80"></div></div>
<label>Road / House / Flat</label><input name="road" required maxlength="200"><label>Landmark</label><input name="landmark" maxlength="120"><label style="display:flex;gap:8px;align-items:center;font-weight:500"><input type="checkbox" name="is_default" value="1" style="width:auto"> Default Address</label>
<datalist id="dl">@foreach(['Dhaka','Chattogram','Gazipur','Narayanganj','Cumilla','Sylhet','Rajshahi','Khulna','Barishal','Rangpur','Mymensingh','Feni','Noakhali','Bogura','Jashore'] as $dn)<option value="{{ $dn }}">@endforeach</datalist>
<div style="display:flex;gap:8px;margin-top:14px"><button class="b1 p" style="padding:10px 22px">Save</button><button type="button" class="b1" id="acl">Cancel</button></div><div class="msg er" id="am"></div></form></div></div>
@endif

@if($tab==='wishlist')
<div class="bx"><h2>My Wishlist</h2><div id="wl">
@if($wish->isEmpty())<p style="color:#6B7280;font-size:13.5px" id="wle">উইশলিস্ট খালি। প্রোডাক্টের ♡ চাপলে এখানে জমা হবে।</p>@endif
<div class="wg">@foreach($wish as $p)<div class="wc"><a href="{{ $p->url }}"><img src="{{ $img($p->image) }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/icon.png'"></a><b>{{ $p->name }}</b><div style="color:var(--o);font-weight:800">{{ $tk($p->offer_price ?: $p->price) }}</div><div style="font-size:12px;color:{{ $p->stock>0 ? '#15803D' : '#B91C1C' }}">{{ $p->stock>0 ? 'In Stock' : 'Out of Stock' }}</div>
<div style="display:flex;gap:6px;justify-content:center;margin-top:8px">@if($p->stock>0)<button class="b1 p" type="button" data-cart="{{ $p->id }}" data-price="{{ $p->offer_price ?: $p->price }}">Add to Cart</button>@endif<button class="b1 r" type="button" data-wrm="{{ $p->id }}">✕</button></div></div>@endforeach</div></div></div>
@endif

@if($tab==='coupons')
<div class="bx"><h2>My Coupons</h2>@if($coupons->isEmpty())<p style="color:#6B7280;font-size:13.5px">এই মুহূর্তে কোনো কুপন নেই। নতুন অফার এলে এখানে দেখতে পাবেন।</p>@endif
<div class="cp">@foreach($coupons as $cp)<div class="cpc"><b>{{ $cp->code }}</b><div style="font-size:14px;font-weight:700;margin:4px 0">{{ $cp->type==='fixed' ? $tk($cp->value).' ছাড়' : rtrim(rtrim(number_format($cp->value,2),'0'),'.').'% ছাড়' }}@if($cp->max_discount) <span style="font-weight:400;color:#6B7280">(সর্বোচ্চ {{ $tk($cp->max_discount) }})</span>@endif</div><div style="font-size:12.5px;color:#4B5563">ন্যূনতম অর্ডার: {{ $tk($cp->min_order) }}<br>মেয়াদ: {{ $cp->valid_to ? \Carbon\Carbon::parse($cp->valid_to)->format('d M Y') : 'সীমাহীন' }} · <span style="color:#15803D;font-weight:700">Active</span></div><button class="b1" style="margin-top:8px" type="button" data-copy="{{ $cp->code }}">কোড কপি</button></div>@endforeach</div></div>
@endif

@if($tab==='reviews')
<div class="bx"><h2>রিভিউ দিন</h2>@if($todo->isEmpty())<p style="color:#6B7280;font-size:13.5px;margin:0">রিভিউ দেওয়ার মতো ডেলিভারি হওয়া প্রোডাক্ট নেই।</p>@endif
@foreach($todo as $t)<div style="border-bottom:1px solid #F8EFE8;padding:12px 0"><div style="display:flex;gap:10px;align-items:center"><img src="{{ $img($t->image) }}" width="44" height="44" style="border-radius:8px;object-fit:cover" alt="" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='/icon.png'"><b style="font-size:13.5px">{{ $t->name }}</b></div>
<form class="rv f" data-pid="{{ $t->product_id }}"><div class="stars">@for($i=1;$i<=5;$i++)<button type="button" data-s="{{ $i }}">★</button>@endfor</div><textarea rows="2" maxlength="1000" placeholder="আপনার মতামত লিখুন (ঐচ্ছিক)"></textarea><div style="margin-top:8px"><button class="b1 p">Submit Review</button> <span class="msg"></span></div></form></div>@endforeach</div>
<div class="bx"><h2>আমার আগের রিভিউ</h2>@if($mine->isEmpty())<p style="color:#6B7280;font-size:13.5px;margin:0">এখনো কোনো রিভিউ নেই।</p>@endif
@foreach($mine as $m)<div style="border-bottom:1px solid #F8EFE8;padding:10px 0;font-size:13.5px"><b>{{ $m->pname }}</b> · <span style="color:#F59E0B">{{ str_repeat('★', $m->rating) }}</span><span style="color:#D1D5DB">{{ str_repeat('★', 5-$m->rating) }}</span> <span class="st" style="{{ $m->status==='approved' ? 'background:#DCFCE7;color:#15803D' : 'background:#FFEDD5;color:#C2410C' }}">{{ $m->status==='approved' ? 'Published' : 'Under review' }}</span>@if($m->body)<div style="color:#4B5563;margin-top:3px">{{ $m->body }}</div>@endif @if($m->reply)<div style="background:#F9FAFB;border-radius:8px;padding:7px 10px;margin-top:5px;font-size:12.5px"><b>DutyFreeBD:</b> {{ $m->reply }}</div>@endif</div>@endforeach</div>
@endif

@if($tab==='support')
<div class="bx"><h2>Support / Chat</h2><div style="display:flex;gap:8px;flex-wrap:wrap"><button class="b1 p" type="button" id="oc" style="padding:10px 18px;font-size:14px">💬 লাইভ চ্যাট শুরু করুন</button><a class="b1" style="padding:10px 18px;font-size:14px" target="_blank" rel="noopener" href="https://wa.me/{{ $wa }}">WhatsApp Support</a><a class="b1" style="padding:10px 18px;font-size:14px" href="tel:{{ $s['call_number'] }}">📞 {{ $s['call_number'] }}</a></div></div>
@foreach($threads as $th)<div class="bx"><h2>Conversation · {{ $dt($th['t']->last_at ?? $th['t']->created_at)->format('d M Y') }}</h2><div class="cht">@foreach($th['msgs'] as $m)<div class="{{ $m->dir }}">{{ $m->body ?: '[ছবি]' }}<small>{{ $m->dir==='out' ? ($m->is_ai ? '🤖 DutyFreeBD Assistant' : 'DutyFreeBD Support') : 'You' }} · {{ $dt($m->created_at)->format('d M, h:i A') }}</small></div>@endforeach</div></div>@endforeach
@if($threads->isEmpty())<div class="bx"><p style="color:#6B7280;font-size:13.5px;margin:0">আগের কোনো কথোপকথন নেই। উপরের বাটনে চ্যাট শুরু করুন — আপনার নাম ও নম্বর আবার দিতে হবে না।</p></div>@endif
@endif

@if($tab==='notifications')
<div class="bx"><h2>Notifications</h2>@if(!count($notes))<p style="color:#6B7280;font-size:13.5px;margin:0">কোনো নোটিফিকেশন নেই।</p>@endif
@foreach($notes as $n)<a class="ntf" href="{{ $n['href'] }}"><i>{{ $n['icon'] }}</i><div>{{ $n['text'] }}<small>{{ $dt($n['at'])->format('d M Y, h:i A') }}</small></div></a>@endforeach</div>
@endif

@if($tab==='security')
<div class="bx"><h2>Change Password</h2><form class="f" id="pwf" style="max-width:420px"><label>বর্তমান পাসওয়ার্ড</label><input type="password" name="current" autocomplete="current-password" required><label>নতুন পাসওয়ার্ড</label><input type="password" name="password" minlength="6" autocomplete="new-password" required><label>নতুন পাসওয়ার্ড (আবার)</label><input type="password" name="password_confirmation" minlength="6" autocomplete="new-password" required><div style="margin-top:14px"><button class="b1 p" style="padding:10px 22px">Update Password</button></div><div class="msg" id="pwm"></div></form></div>
<div class="bx"><h2>Login Sessions @if($sessions->count()>1)<button class="b1" type="button" id="eo">অন্য সব ডিভাইস থেকে লগআউট</button>@endif</h2>
@foreach($sessions as $s2)<div style="display:flex;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px solid #F8EFE8;font-size:13px"><div>{{ \Illuminate\Support\Str::limit(preg_match('/(Android|iPhone|iPad|Windows|Macintosh|Linux)/', (string)$s2->device, $mm) ? $mm[1].' · '.(preg_match('/(Chrome|Firefox|Safari|Edg)/', (string)$s2->device, $bm) ? $bm[1] : 'Browser') : 'Device', 40) }}@if($s2->id===$c->sid) <span class="st" style="background:#DCFCE7;color:#15803D">এই ডিভাইস</span>@endif<div style="color:#9CA3AF;font-size:11.5px">{{ $s2->ip }} · {{ $s2->last_at ? $dt($s2->last_at)->format('d M, h:i A') : '' }}</div></div>@if($s2->id!==$c->sid)<button class="b1 r" type="button" data-end="{{ $s2->id }}">Logout</button>@endif</div>@endforeach</div>
@endif

</div></div></div>
@endsection
@section('js')
<script>window.ACC=@json(['p'=>$c->phone,'n'=>$c->name,'e'=>$c->email ?: '']);</script>
@verbatim
<script>(function(){
var $=function(s,r){return(r||document).querySelector(s)},$$=function(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))};
function post(u,b){return fetch(u,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(b||{})}).then(function(r){return r.json().catch(function(){return{}}).then(function(j){j._s=r.status;return j})})}
function em(j){return j.error||(j.errors&&Object.values(j.errors)[0][0])||'কিছু ভুল হয়েছে'}
function say(el,ok,t){if(!el)return;el.className='msg '+(ok?'ok2':'er');el.textContent=t}
try{var o=JSON.parse(localStorage.getItem('df_cust')||'{}')||{};o.p=ACC.p;o.n=ACC.n;if(ACC.e)o.e=ACC.e;localStorage.setItem('df_cust',JSON.stringify(o))}catch(e){}
var pf=$('#pf');if(pf)pf.onsubmit=function(e){e.preventDefault();var f=new FormData(pf);post('/account/profile',{name:f.get('name'),email:f.get('email'),dob:f.get('dob')}).then(function(j){say($('#pm'),j.ok,j.ok?'সেভ হয়েছে ✓':em(j))})};
/* addresses */
var amo=$('#amo');if(amo){var af=$('#af');
 function openA(a){$('#amt').textContent=a?'ঠিকানা এডিট':'নতুন ঠিকানা';['id','label','receiver','phone','district','thana','area','road','landmark'].forEach(function(k){if(a&&a[k]!=null)af.elements[k].value=a[k];else if(!a&&k==='id')af.elements[k].value=''});af.elements.is_default.checked=!!(a&&a.is_default);amo.classList.add('op')}
 $('#adn').onclick=function(){openA(null)};$('#acl').onclick=function(){amo.classList.remove('op')};
 $$('[data-edit]').forEach(function(b){b.onclick=function(){openA(JSON.parse(b.getAttribute('data-edit')))}});
 $$('[data-del]').forEach(function(b){b.onclick=function(){if(confirm('ঠিকানাটি মুছবেন?'))post('/account/address/'+b.getAttribute('data-del')+'/delete').then(function(){location.reload()})}});
 $$('[data-def]').forEach(function(b){b.onclick=function(){post('/account/address/'+b.getAttribute('data-def')+'/default').then(function(){location.reload()})}});
 af.onsubmit=function(e){e.preventDefault();var f=new FormData(af),d={};f.forEach(function(v,k){d[k]=v});d.is_default=af.elements.is_default.checked?1:0;if(!d.id)delete d.id;post('/account/address',d).then(function(j){if(j.ok)location.reload();else say($('#am'),false,em(j))})}}
/* wishlist */
var wl=$('#wl');if(wl){
 try{var ids=JSON.parse(localStorage.getItem('dfw')||'[]');if(ids.length&&!sessionStorage.getItem('wsync')){post('/account/wishlist/sync',{ids:ids}).then(function(j){sessionStorage.setItem('wsync','1');if((j.ids||[]).length&&$('#wle'))location.reload()})}}catch(e){}
 $$('[data-wrm]').forEach(function(b){b.onclick=function(){var id=b.getAttribute('data-wrm');post('/account/wishlist/'+id+'/remove').then(function(){try{var w=JSON.parse(localStorage.getItem('dfw')||'[]').filter(function(x){return String(x)!==String(id)});localStorage.setItem('dfw',JSON.stringify(w))}catch(e){}location.reload()})}});
 $$('[data-cart]').forEach(function(b){b.onclick=function(){if(window.DF)DF.add(b.getAttribute('data-cart'),1,b.getAttribute('data-price'));b.textContent='✓ যোগ হয়েছে';setTimeout(function(){b.textContent='Add to Cart'},1500)}})}
$$('[data-copy]').forEach(function(b){b.onclick=function(){try{navigator.clipboard.writeText(b.getAttribute('data-copy'));b.textContent='✓ কপি হয়েছে'}catch(e){}}});
/* reorder */
$$('[data-reorder]').forEach(function(b){b.onclick=function(){fetch('/account/reorder/'+b.getAttribute('data-reorder'),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(l){if(!l.length){say($('#rom'),false,'প্রোডাক্টগুলো এখন স্টকে নেই');return}l.forEach(function(x){DF.add(x.id,x.q,x.price,true)});location.href='/checkout'})}});
/* reviews */
$$('.rv').forEach(function(f){var s=0;$$('[data-s]',f).forEach(function(b){b.onclick=function(){s=+b.getAttribute('data-s');$$('[data-s]',f).forEach(function(x){x.classList.toggle('on',+x.getAttribute('data-s')<=s)})}});
 f.onsubmit=function(e){e.preventDefault();var m=$('.msg',f);if(!s){say(m,false,'রেটিং দিন');return}post('/account/review',{product_id:+f.getAttribute('data-pid'),rating:s,body:$('textarea',f).value}).then(function(j){if(j.ok){say(m,true,'ধন্যবাদ! রিভিউ যাচাইয়ের পর প্রকাশ হবে।');f.querySelector('button.p').disabled=true}else say(m,false,em(j))})}});
/* support */
var oc=$('#oc');if(oc)oc.onclick=function(){var b=document.getElementById('dfcb');if(b)b.click()};
/* security */
var pw=$('#pwf');if(pw)pw.onsubmit=function(e){e.preventDefault();var f=new FormData(pw);post('/account/password',{current:f.get('current'),password:f.get('password'),password_confirmation:f.get('password_confirmation')}).then(function(j){say($('#pwm'),j.ok,j.ok?'পাসওয়ার্ড বদলানো হয়েছে ✓':(j.errors&&/confirmation/.test(Object.values(j.errors)[0][0])?'দুটি পাসওয়ার্ড মেলেনি':em(j)));if(j.ok)pw.reset()})};
$$('[data-end]').forEach(function(b){b.onclick=function(){post('/account/session/'+b.getAttribute('data-end')+'/end').then(function(){location.reload()})}});
var eo=$('#eo');if(eo)eo.onclick=function(){post('/account/session/others/end').then(function(){location.reload()})};
})();</script>
@endverbatim
@endsection
