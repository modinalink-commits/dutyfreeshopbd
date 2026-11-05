@extends('layout')
@section('title', 'Company settings')
@section('content')
@php($sa = auth()->user()->isSuperAdmin())
@php($v = fn ($k) => old($k, $values[$k] ?? ''))
@php($logo = $values['logo'] ?? '/img/logo.webp')
<style>.fld{display:flex;flex-direction:column;gap:4px;font-size:11px;font-weight:600;color:#51607a}.sec{font-size:10px;font-weight:700;letter-spacing:.08em;color:#51607a}
.up{min-height:88px;border:1px dashed #7b8aa8;border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;font-size:12px;color:#51607a;background:#f4f7fc;cursor:pointer;position:relative}
.up input{position:absolute;inset:0;opacity:0;cursor:pointer}</style>
<form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:16px">@csrf
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Company settings</h1><span class="sub">One company profile used on every invoice, challan, slip and report · admin only</span></div>
  <span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#5b2fa8;background:#efe7fb;padding:6px 12px;border-radius:999px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 11h12v10H6zM8 11V7a4 4 0 018 0v4"></path></svg>Only Super Admin can edit</span>
  @if($sa)<button class="btn pri" type="submit">Save changes</button>@endif
</header>
<fieldset @disabled(! $sa) style="border:0;padding:0;margin:0;min-width:0">
<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section class="card" style="flex:999 1 560px;min-width:0;padding:20px;display:flex;flex-direction:column;gap:14px">
    <span class="sec">IDENTITY</span>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
      @foreach(['company_name', 'brand', 'slogan', 'category'] as $k)<label class="fld">{{ $fields[$k] }}<input name="{{ $k }}" value="{{ $v($k) }}" placeholder="Not provided"></label>@endforeach
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
      <div class="fld">Company logo<label class="up" style="flex-direction:row;justify-content:flex-start;gap:12px;padding:0 12px"><img src="{{ $logo }}" alt="Company logo" style="width:64px;height:64px;border-radius:12px;object-fit:cover;flex-shrink:0"><span style="display:flex;flex-direction:column;gap:2px"><b style="color:#14213d">{{ basename($logo) }}</b><span>{{ ! empty($values['logo_at']) ? 'Uploaded '.$values['logo_at'] : 'Default logo' }}</span><span style="font-weight:700;color:#123a86">Replace logo</span></span><input type="file" name="logo" accept="image/*" aria-label="Replace logo"></label></div>
      <div class="fld">Authorized signature<label class="up">@if(! empty($values['signature']))<img src="{{ $values['signature'] }}" alt="Signature" style="max-height:44px">@endif<span style="font-weight:700;color:#123a86">{{ ! empty($values['signature']) ? 'Replace signature' : 'Upload signature' }}</span><span>{{ ! empty($values['signature_at']) ? 'Uploaded '.$values['signature_at'] : 'Not uploaded yet' }}</span><input type="file" name="signature" accept="image/*" aria-label="Upload signature"></label></div>
    </div>
    <span class="sec" style="margin-top:6px">ADDRESS</span>
    <label class="fld">{{ $fields['address_bn'] }}<textarea name="address_bn" rows="2" placeholder="Not provided">{{ $v('address_bn') }}</textarea></label>
    <label class="fld">{{ $fields['address_en'] }}<textarea name="address_en" rows="2" placeholder="Not provided">{{ $v('address_en') }}</textarea></label>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
      @foreach(['country', 'division', 'upazila', 'area'] as $k)<label class="fld">{{ $fields[$k] }}<input name="{{ $k }}" value="{{ $v($k) }}" placeholder="Not provided"></label>@endforeach
    </div>
    <span class="sec" style="margin-top:6px">CONTACT</span>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
      @foreach(['phone' => 'tel', 'whatsapp' => 'tel', 'email' => 'email', 'website' => 'text'] as $k => $t)<label class="fld">{{ $fields[$k] }}<input type="{{ $t }}" name="{{ $k }}" value="{{ $v($k) }}" placeholder="Not provided"></label>@endforeach
    </div>
    <span class="sec" style="margin-top:6px">LEGAL &amp; TAX</span>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
      @foreach(['trade_license', 'bin', 'tin'] as $k)<label class="fld">{{ $fields[$k] }}<input name="{{ $k }}" value="{{ $v($k) }}" placeholder="Not provided"></label>@endforeach
    </div>
    <span class="sec" style="margin-top:6px">OPERATIONS &amp; DOCUMENTS</span>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
      <label class="fld">Vehicle fleet<input value="{{ $fleet }} vehicles" readonly title="From Vehicles & routes"></label>
      <label class="fld">Cylinder categories<input value="{{ $sizes }}" readonly title="From Cylinder brands"></label>
      <label class="fld">{{ $fields['opening_hours'] }}<input name="opening_hours" value="{{ $v('opening_hours') }}" placeholder="Not provided"></label>
      <label class="fld">{{ $fields['home_delivery_charge'] }}<input name="home_delivery_charge" value="{{ $v('home_delivery_charge') }}" placeholder="Not provided"></label>
    </div>
    <label class="fld">{{ $fields['invoice_footer'] }}<textarea name="invoice_footer" rows="2" placeholder="Not provided — printed at the bottom of invoices and receipts">{{ $v('invoice_footer') }}</textarea></label>
  </section>

  <aside style="flex:1 1 340px;min-width:300px;display:flex;flex-direction:column;gap:16px">
    <div class="card" style="padding:20px;display:flex;flex-direction:column;gap:10px">
      <h2 style="margin:0;font-size:15px">Header preview</h2>
      <div style="border:1px solid #dbe3f0;border-radius:12px;padding:14px;display:flex;gap:12px;align-items:center">
        <img src="{{ $logo }}" alt="Company logo" style="width:60px;height:60px;border-radius:12px;object-fit:cover;flex-shrink:0">
        <div style="display:flex;flex-direction:column;gap:2px">
          <span style="font-size:16px;font-weight:700;color:#123a86">{{ $values['company_name'] ?? 'NK Corporation' }}</span>
          <span style="font-size:11px;color:#51607a">{{ collect([$values['brand'] ?? null, $values['slogan'] ?? null])->filter()->implode(' · ') }}</span>
          <span style="font-size:11px;color:#51607a;line-height:16px">{{ $values['address_en'] ?? '' }}</span>
          <span style="font-size:11px;color:#51607a">{{ $values['phone'] ?? '' }}</span>
        </div>
      </div>
      @php($anySale = \App\Models\Sale::query()->latest('id')->first())
      @if($anySale)<a href="{{ route('sales.print', $anySale) }}" target="_blank" style="font-size:12px;font-weight:600;text-decoration:none">See it on an A4 invoice →</a>@endif
    </div>
    <div class="card" style="padding:20px;display:flex;flex-direction:column;gap:8px">
      <h2 style="margin:0 0 4px;font-size:15px">Used automatically on</h2>
      @foreach(['Sales invoice', 'Delivery challan', 'Warehouse stock transfer', 'Vehicle loading slip', 'Vehicle dispatch slip', 'Empty cylinder receipt', 'Cash collection receipt', 'Daily vehicle closing report', 'Customer statement', 'Profit & loss report'] as $d)
        <div style="display:flex;align-items:center;gap:8px;font-size:12px;padding:4px 0"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0f6b3c" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7"></path></svg><span>{{ $d }}</span></div>
      @endforeach
      <div style="background:#def5f7;color:#0b6670;border-radius:12px;padding:10px 12px;font-size:12px;line-height:18px;margin-top:6px">A change shows on new documents only. Invoices already issued keep the company details they were printed with.</div>
    </div>
    <div class="card" style="padding:20px;display:flex;flex-direction:column;gap:6px">
      <h2 style="margin:0 0 4px;font-size:15px">Change log</h2>
      @forelse($log as $l)
        <div style="display:flex;flex-direction:column;padding:6px 0;border-top:1px solid #dbe3f0;font-size:12px"><span style="font-weight:600">{{ $l->action === 'settings_changed' ? 'Updated: '.$l->details : $l->details }}</span><span style="color:#51607a;font-size:11px">{{ $l->user?->name ?? 'System' }} · {{ $l->created_at->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}</span></div>
      @empty
        <p class="sub" style="margin:0">No changes yet.</p>
      @endforelse
    </div>
  </aside>
</div>
</fieldset>
</form>
@endsection
