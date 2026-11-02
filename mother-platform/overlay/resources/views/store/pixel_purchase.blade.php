@php
  $pxItems = collect($items ?? [])->map(fn($i) => ['id' => (string)(($i->ext_id ?? null) ?: ($i->product_id ?? '')), 'quantity' => (int)$i->qty, 'item_price' => (float)$i->price, 'name' => (string)($i->name ?? '')])->filter(fn($x) => $x['id'] !== '')->values()->all();
  $pxAm = [];
  if (!empty($withAm)) {
    $d = preg_replace('/\D/', '', (string)($o->phone ?? '')); $d = substr($d, -10);
    $nm = preg_split('/\s+/', trim(mb_strtolower((string)($o->customer_name ?? ''))), 2);
    $pxAm = array_filter(['em' => mb_strtolower(trim((string)($o->email ?? ''))), 'ph' => $d !== '' ? '880' . $d : '', 'fn' => $nm[0] ?? '', 'ln' => $nm[1] ?? '', 'ct' => preg_replace('/[^a-z]/', '', mb_strtolower((string)($o->district ?? ''))), 'country' => 'bd', 'external_id' => (string)($o->customer_id ?? '')], fn($v) => $v !== '' && $v !== null);
  }
  $pxFire = false;
  try { if (!empty($o->id) && !empty($o->created_at) && \Carbon\Carbon::parse($o->created_at)->gt(now()->subHours(6))) $pxFire = \Illuminate\Support\Facades\DB::table('orders')->where('id', $o->id)->whereNull('ga_purchase_at')->update(['ga_purchase_at' => now()]) > 0; } catch (\Throwable $e) { $pxFire = false; }
@endphp
@if($pxFire)
<script>(function(){var k='dfp_'+@json($o->invoice);try{if(localStorage.getItem(k))return;localStorage.setItem(k,'1')}catch(e){}
var ids=@json(array_column($pxItems, 'id')),cn=@json($pxItems),v={{ (float)$o->total }};
window.dataLayer.push({ecommerce:null});window.dataLayer.push({event:'purchase',ecommerce:{transaction_id:@json($o->invoice),currency:'BDT',value:v,shipping:{{ (float)($o->shipping ?? 0) }},items:cn.map(function(x){return{item_id:x.id,item_name:x.name,quantity:x.quantity,price:x.item_price}})}});
if(window.gtag&&!{{ !empty($s['gtm_id']) ? 'true' : 'false' }})gtag('event','purchase',{transaction_id:@json($o->invoice),currency:'BDT',value:v,shipping:{{ (float)($o->shipping ?? 0) }},items:cn.map(function(x){return{item_id:x.id,item_name:x.name,quantity:x.quantity,price:x.item_price}})});
if(window.fbq){@if($pxAm && !empty($s['pixel_id']))fbq('init',@json($s['pixel_id']),@json($pxAm));@endif@if($pxAm)try{var __a=@json($pxAm);delete __a.external_id;localStorage.setItem('dfam',JSON.stringify(__a))}catch(e){}@endif
fbq('track','Purchase',{currency:'BDT',value:v,content_type:'product',content_ids:ids,contents:cn,num_items:cn.reduce(function(a,x){return a+x.quantity},0),order_id:@json($o->invoice)},{eventID:@json($o->invoice)})}})();</script>
@endif
