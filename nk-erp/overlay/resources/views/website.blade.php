@extends('layout')
@section('title', 'Public website')
@section('content')
<style>.fld{display:flex;flex-direction:column;gap:4px;font-size:var(--fs-11);font-weight:600;color:#51607a}.kv{display:flex;gap:10px;font-size:var(--fs-13);padding:6px 0;border-bottom:1px solid #eef2f9}.kv span:first-child{width:130px;flex-shrink:0;color:#51607a}</style>
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Public website</h1><span class="sub">nklpgbd.com · notice bar, order form and contact details · changes show on the site within a minute, no code needed</span></div>
  <a class="btn" href="https://nklpgbd.com/" target="_blank" rel="noopener">View live site</a>
  <a class="btn" href="{{ route('hero.index') }}">Hero slider</a>
  <a class="btn pri" href="{{ route('orders.index') }}">Website orders{{ $stats['new'] ? ' · '.$stats['new'].' new' : '' }}</a>
</header>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
  <div class="card" style="padding:16px"><span class="sub" style="margin:0">Order form</span><div style="font-size:var(--fs-22);font-weight:700;color:{{ $c['orders_open'] ? '#0f6b3c' : '#b42318' }}">{{ $c['orders_open'] ? 'Open' : 'Paused' }}</div></div>
  <div class="card" style="padding:16px"><span class="sub" style="margin:0">Website orders today</span><div style="font-size:var(--fs-22);font-weight:700">{{ $stats['today'] }}</div></div>
  <div class="card" style="padding:16px"><span class="sub" style="margin:0">This month</span><div style="font-size:var(--fs-22);font-weight:700">{{ $stats['month'] }}</div></div>
  <div class="card" style="padding:16px"><span class="sub" style="margin:0">Notice bar</span><div style="font-size:var(--fs-22);font-weight:700;color:{{ $c['notice_live'] ? '#0f6b3c' : '#51607a' }}">{{ $c['notice_live'] ? 'Showing' : 'Off' }}</div></div>
</div>

<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <form class="card" method="post" action="{{ route('website.save') }}" style="flex:999 1 520px;min-width:0;padding:20px;display:flex;flex-direction:column;gap:14px">@csrf
    <h2 style="margin:0;font-size:var(--fs-15)">Notice bar (top of every page)</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
      <label class="fld">Status<select name="site_notice_on"><option value="1" @selected(($c['site_notice_on'] ?? '0') === '1')>Show</option><option value="0" @selected(($c['site_notice_on'] ?? '0') !== '1')>Hide</option></select></label>
      <label class="fld">Show until (optional)<input type="date" name="site_notice_until" value="{{ $c['site_notice_until'] ?? '' }}"></label>
    </div>
    <label class="fld">Notice (English)<input name="site_notice_en" maxlength="200" value="{{ old('site_notice_en', $c['site_notice_en'] ?? '') }}" placeholder="e.g. Home delivery available on Friday too"></label>
    <label class="fld">Notice (Bangla)<input name="site_notice_bn" maxlength="200" value="{{ old('site_notice_bn', $c['site_notice_bn'] ?? '') }}" style="font-family:'Hind Siliguri',sans-serif" placeholder="যেমন: শুক্রবারেও হোম ডেলিভারি চালু"></label>

    <h2 style="margin:8px 0 0;font-size:var(--fs-15)">Order gas form</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
      <label class="fld">Online orders<select name="site_orders_open"><option value="1" @selected($c['orders_open'])>Open — accept orders</option><option value="0" @selected(! $c['orders_open'])>Paused — show message</option></select></label>
    </div>
    <label class="fld">Message while paused<input name="site_closed_msg" maxlength="200" value="{{ old('site_closed_msg', $c['site_closed_msg'] ?? '') }}" placeholder="Online orders are paused right now. Please call us."></label>
    <h2 style="margin:8px 0 0;font-size:var(--fs-15)">Today's prices</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
      <label class="fld">Show prices on the website<select name="site_prices_on"><option value="1" @selected(($c['site_prices_on'] ?? '1') === '1')>Show — from Products &amp; prices</option><option value="0" @selected(($c['site_prices_on'] ?? '1') === '0')>Hide — "call for today's price"</option></select></label>
    </div>
    <div class="dt-wrap"><table class="dt"><thead><tr><th>Size</th><th>Refill</th><th>New cylinder with gas</th></tr></thead><tbody>
      @forelse($prices['sizes'] as $sz => $p)<tr><td class="c">{{ $sz }} kg</td><td class="r">{{ isset($p['refill']) ? \App\Support\Ui::bd($p['refill']) : '—' }}</td><td class="r">{{ isset($p['pkg']) ? \App\Support\Ui::bd($p['pkg']) : '—' }}</td></tr>
      @empty<tr><td colspan="3" class="empty">No retail prices yet. Set them in Products &amp; prices; the website shows "call for today's price" until then.</td></tr>@endforelse
    </tbody></table></div>
    <span class="sub" style="margin:0">Prices come from <a href="{{ route('products.index') }}">Products &amp; prices</a> (retail price){{ $prices['updated'] ? ' · last changed '.$prices['updated'] : '' }}.</span>
    <h2 style="margin:8px 0 0;font-size:var(--fs-15)">Meet our owners</h2>
    <label class="fld">Owner names, one per line<textarea name="site_owners" rows="3" maxlength="600" placeholder="Kazi Nuzrul">{{ old('site_owners', $c['site_owners'] ?? '') }}</textarea></label>
    <span class="sub" style="margin:0">Empty = the website keeps its current owner card.</span>
    <button class="btn pri" type="submit" style="align-self:flex-start">Save website</button>
  </form>

  <div style="flex:1 1 300px;min-width:280px;display:flex;flex-direction:column;gap:16px">
    <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:4px">
      <h2 style="margin:0 0 6px;font-size:var(--fs-15)">Contact details on the site</h2>
      <div class="kv"><span>Phone</span><span>{{ $c['phone'] ?? '—' }}</span></div>
      <div class="kv"><span>WhatsApp</span><span>{{ $c['whatsapp'] ?? ($c['phone'] ?? '—') }}</span></div>
      <div class="kv"><span>Address</span><span>{{ $c['address_en'] ?? '—' }}</span></div>
      <div class="kv"><span>Opening hours</span><span>{{ $c['opening_hours'] ?? '—' }}</span></div>
      <span class="sub" style="margin:6px 0 0">These come from Company settings, so the invoice and the website always match.</span>
      <a href="{{ route('settings.edit') }}" style="font-size:var(--fs-12);font-weight:600;text-decoration:none">Edit in Company settings →</a>
    </section>
    <section class="card" style="padding:20px;display:flex;flex-direction:column;gap:6px">
      <h2 style="margin:0 0 4px;font-size:var(--fs-15)">Recent website changes</h2>
      @if($heroAt)<span class="sub" style="margin:0">Hero slider last published {{ \Illuminate\Support\Carbon::parse($heroAt)->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}</span>@endif
      @forelse($log as $l)
        <div style="font-size:var(--fs-12);padding:6px 0;border-bottom:1px solid #eef2f9"><b>{{ $l->user?->name ?? 'System' }}</b> · {{ $l->details ?: $l->action }}<br><span style="color:#7b8aa8">{{ $l->created_at->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}</span></div>
      @empty
        <span class="sub" style="margin:0">No changes yet.</span>
      @endforelse
    </section>
  </div>
</div>
@endsection
