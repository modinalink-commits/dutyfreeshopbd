@extends('layout')
@section('title', 'Import data')
@section('content')
@php($U = \App\Support\Ui::class)
@php($L = ['Upload', 'Validate', 'Column mapping', 'Preview', 'Duplicate check', 'Confirm', 'Import log'])
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Import data</h1><span class="sub">Legacy ledger → Company account ledger · Super Admin only · the source file is kept unchanged</span></div>
  <a class="btn" href="#history">Import history</a>
</header>
<section class="card" style="padding:16px 20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
  @foreach($L as $i => $l)
    <div style="display:flex;align-items:center;gap:8px"><span style="font-size:12px;font-weight:700;padding:6px 12px;border-radius:999px;{{ $i + 1 < $step ? 'background:#e3f6ec;color:#0f6b3c' : ($i + 1 === $step ? 'background:#123a86;color:#fff' : 'background:#eef2f9;color:#51607a') }}">{{ $i + 1 }} {{ $l }}</span>@if(! $loop->last)<span aria-hidden="true" style="color:#7b8aa8">→</span>@endif</div>
  @endforeach
</section>

@if(! $a)
  <form class="card pad" method="post" action="{{ route('imports.upload') }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:12px;align-items:end">@csrf
    <label class="f" style="flex:1 1 320px">Ledger export file (.json from the old NK LPG Ledger)<input type="file" name="file" accept=".json,application/json" required style="padding:8px"></label>
    <button class="btn pri" type="submit">Upload &amp; validate</button>
    @if($hasEntries)<span class="sub" style="margin:0;flex-basis:100%">The company ledger already has entries. A new import skips challans already there (same date, challan and size).</span>@endif
  </form>
@else
  @php($t = [['Source', $name, ($a['from'] ? \Carbon\Carbon::parse($a['from'])->format('d-m-Y') : '—').' → '.($a['to'] ? \Carbon\Carbon::parse($a['to'])->format('d-m-Y') : '—'), 'blue'],
    ['Rows read', $a['rows'], $a['challans'].' challan · '.$a['deposits'].' deposit', 'info'], ['Valid', $a['valid'], 'dates and amounts OK', 'pos'],
    ['Need your decision', ($a['depPaid']->isNotEmpty() ? 1 : 0) + ($a['comm']->isNotEmpty() ? 1 : 0) + 1, 'not guessed', 'warn'], ['Duplicates', $a['dups'], 'checked on date + challan + size', $a['dups'] ? 'warn' : 'gray']])
  <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
    @foreach($t as [$k, $v, $f, $c])<div style="background:{{ $U::bg($c) }};border:1px solid #dbe3f0;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:2px"><span style="font-size:11px;font-weight:600;color:#51607a">{{ $k }}</span><span style="font-size:18px;font-weight:700;color:{{ $U::ink($c) }};overflow-wrap:anywhere">{{ $v }}</span><span style="font-size:11px;color:#51607a">{{ $f }}</span></div>@endforeach
  </section>
  <form method="post" action="{{ route('imports.confirm') }}" style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">@csrf
    <section class="card" style="flex:1 1 420px;min-width:0;overflow:hidden">
      <div style="padding:16px 20px"><h2 style="margin:0;font-size:15px">Column mapping</h2></div>
      <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
        <thead><tr><th>Source column</th><th>Sample</th><th>Goes to</th></tr></thead>
        <tbody>
          @foreach([['date', 'Transaction date'], ['challan', 'Challan no.'], ['kg', 'Cylinder size'], ['qty', 'Quantity'], ['rate', 'Check only — bill comes from Rate Master'], ['deposit', 'Deposit in (bank)'], ['withdraw', 'Deposit withdrawn']] as [$c, $to])
            <tr><td><code>{{ $c }}</code></td><td>{{ $a['sample'][$c] ?? '' }}</td><td>{{ $to }}</td></tr>
          @endforeach
          <tr><td><code>depPaid</code></td><td>{{ $a['sample']['depPaid'] }}</td><td><select name="dep_paid" @disabled($a['depPaid']->isEmpty())><option value="">Decide: bill money or cylinder security?</option><option value="bill_payment" @selected(old('dep_paid') === 'bill_payment')>Paid against bill (bill money held by the company)</option><option value="security" @selected(old('dep_paid') === 'security')>Cylinder security deposit</option></select></td></tr>
          <tr><td><code>depKind = comm</code></td><td>{{ $a['sample']['comm'] }}</td><td><select name="comm" @disabled($a['comm']->isEmpty())><option value="">Decide: incentive or commission credit?</option><option value="incentive" @selected(old('comm') === 'incentive')>Incentive (net after 10% AIT)</option><option value="commission" @selected(old('comm') === 'commission')>Commission</option></select></td></tr>
        </tbody>
      </table></div>
    </section>
    <section class="card" style="flex:1 1 380px;min-width:0;padding:20px;display:flex;flex-direction:column;gap:10px">
      <h2 style="margin:0;font-size:15px">Waiting for your decision</h2>
      @if($a['depPaid']->isNotEmpty())<div style="background:#fff1d6;color:#8a4b00;border-radius:12px;padding:12px;font-size:12px;line-height:18px">{{ $a['depPaid']->count() }} rows, {{ \Carbon\Carbon::parse($a['depPaid']->min('date'))->format('d-m') }} to {{ \Carbon\Carbon::parse($a['depPaid']->max('date'))->format('d-m') }}, {{ $U::tk($a['depPaid']->sum('depPaid')) }} in the old "Deposit paid" field. Bill money held by the company, or cylinder security deposit? Pick in the mapping.</div>@endif
      @foreach($a['comm'] as $c)<div style="background:#fff1d6;color:#8a4b00;border-radius:12px;padding:12px;font-size:12px;line-height:18px">{{ \Carbon\Carbon::parse($c['date'])->format('d-m-Y') }}, {{ $U::tk((float) ($c['depPaid'] ?? $c['deposit'] ?? 0)) }} "{{ $c['challan'] ?? 'commission' }}". If the company statement shows this as the incentive NET, choose Incentive; otherwise Commission.</div>@endforeach
      <div style="background:#fff1d6;color:#8a4b00;border-radius:12px;padding:12px;font-size:12px;line-height:18px;display:flex;flex-direction:column;gap:6px">Opening balance on {{ $a['from'] ? \Carbon\Carbon::parse($a['from'])->format('d-m-Y') : 'the first date' }}: enter the real figure from the company, or confirm ৳0.
        <label style="display:flex;gap:6px;align-items:center"><input type="radio" name="opening_mode" value="zero" style="width:auto;min-height:0" @checked(old('opening_mode') === 'zero')> Confirm ৳0</label>
        <label style="display:flex;gap:6px;align-items:center"><input type="radio" name="opening_mode" value="amount" style="width:auto;min-height:0" @checked(old('opening_mode') === 'amount')> Amount (+ company owes us, − we owe) <input type="number" step="0.01" name="opening" value="{{ old('opening') }}" style="max-width:160px"></label></div>
      @if($a['emptyRates']->isNotEmpty())<div style="background:#def5f7;color:#0b6670;border-radius:12px;padding:12px;font-size:12px;line-height:18px">{{ $a['emptyRates']->map(fn ($m) => \Carbon\Carbon::parse($m.'-01')->format('F'))->implode(' and ') }} Rate Master rows have no target, slab, 1–15, 16–31 or DD discount. Imported as entered; incentive for those months is ৳0 until the rates are filled.</div>@endif
      @if($a['bad'])<div class="err" style="font-size:12px">{{ $a['bad'] }} rows have a bad date, size or no matching rate and must be fixed in the source first.</div>@endif
      <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn pri" type="submit" @disabled($a['bad'] > 0)>Confirm import</button><button class="btn" type="submit" formaction="{{ route('imports.cancel') }}">Cancel</button></div>
    </section>
  </form>
@endif

<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:8px"><h2 style="margin:0;font-size:15px;flex-grow:1">Balance check — month by month</h2><span class="sub" style="margin:0;font-size:12px">Bill = qty × (DO − Transport) from that month's Rate Master</span></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Month</th><th class="r">Opening</th><th class="r">Deposits</th><th class="r">Incentive / commission in</th><th class="r">Product bill</th><th class="r">Tonnes</th><th class="r">Software balance</th><th>Company statement</th></tr></thead>
    <tbody>
    @forelse($monthly as $i => $m)
      <tr><td>{{ $m['m'] }}</td><td class="r">{{ $i === 0 && ! $m['hasOpening'] ? '0 (unverified)' : $U::tk($m['opening']) }}</td><td class="r">{{ $U::tk($m['deposits']) }}</td><td class="r">{{ $U::tk($m['credits']) }}</td><td class="r">{{ $U::tk($m['bill']) }}</td><td class="r">{{ number_format($m['tons'], 3) }}</td><td class="r" style="font-weight:700">{{ $U::tk($m['close']) }}</td><td>Not uploaded</td></tr>
    @empty
      <tr><td colspan="8" class="empty">Nothing in the company ledger yet.</td></tr>
    @endforelse
    </tbody>
  </table></div>
  <p class="sub" style="margin:0 20px 16px;font-size:12px">When a company statement is uploaded, each month shows Reconciled when Statement balance = System balance; otherwise the date, transaction, difference and likely cause are listed for approval.</p>
</section>

<section class="card pad" id="history" style="display:flex;flex-direction:column;gap:6px">
  <h2 style="margin:0 0 4px;font-size:15px">Import history</h2>
  @forelse($log as $l)
    <div style="display:flex;flex-direction:column;padding:6px 0;border-top:1px solid #dbe3f0;font-size:12px"><span style="font-weight:600">{{ $l->action === 'import_uploaded' ? 'Uploaded '.$l->details : 'Imported: '.$l->details }}</span><span style="color:#51607a;font-size:11px">{{ $l->user?->name }} · {{ $l->created_at->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}</span></div>
  @empty
    <p class="sub" style="margin:0">No imports yet.</p>
  @endforelse
</section>
@endsection
