@php $vh = \App\Support\Voice::history($phone); $vc = \Illuminate\Support\Facades\DB::table('customers')->whereRaw('right(phone,11) = ?', [\App\Support\Voice::norm($phone)])->first(); $S = \App\Support\Voice::STATUS; @endphp
@if($vc)
<div class="card" style="max-width:760px"><h2>Communication Preferences</h2><form method="post" action="/admin/voice/prefs/{{ $vc->id }}" style="display:flex;flex-wrap:wrap;gap:14px;align-items:center">@csrf
<label style="margin:0"><input type="checkbox" name="promo_sms" value="1" @checked($vc->promo_sms ?? true)> Promotional SMS allowed</label>
<label style="margin:0"><input type="checkbox" name="promo_whatsapp" value="1" @checked($vc->promo_whatsapp ?? true)> Promotional WhatsApp allowed</label>
<label style="margin:0"><input type="checkbox" name="promo_call" value="1" @checked($vc->promo_call ?? true)> Promotional Call allowed</label><button>Save</button></form></div>
<div class="card" style="max-width:760px"><h2>Voice Offer History</h2>
@forelse($vh as $h)<div style="font-size:12.5px;line-height:1.7;border-bottom:1px solid #EEF1F5;padding:6px 0"><b>{{ \Carbon\Carbon::parse($h->created_at,'UTC')->timezone('Asia/Dhaka')->format('d M Y') }}</b> · {{ $h->cname }}<br>
{{ $h->product_name }} · {{ \App\Support\Voice::pct((float)$h->discount_value) }}% discount · Call: {{ $S[$h->call_status] ?? $h->call_status }} · Offer: {{ ucfirst($h->offer_status) }}{{ $h->offer_expires_at ? ' (expires '.\Carbon\Carbon::parse($h->offer_expires_at,'UTC')->timezone('Asia/Dhaka')->format('d M Y').')' : '' }} · Conversion: {{ $h->converted ? 'Yes — order #'.$h->converted_order_id.' (−৳'.number_format($h->used_discount).')' : 'No' }}</div>
@empty<div style="font-size:12.5px;color:#6B7280">No voice offers yet.</div>@endforelse</div>
@endif
