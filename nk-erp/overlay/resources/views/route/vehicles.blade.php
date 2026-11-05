@extends('layout')
@section('title', 'Vehicles, staff & routes')
@section('content')
@include('route._css')
@php($U = \App\Support\Ui::class)
@php($canEdit = in_array(auth()->user()->role, ['super_admin', 'admin', 'manager'], true))
@php($drivers = $staff->where('role', 'driver')) @php($salesmen = $staff->where('role', 'salesman'))
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="flex-grow:1;display:flex;flex-direction:column"><h1>Vehicles, staff &amp; routes</h1>
    <span class="sub">{{ $vehicles->count() }} vehicles · {{ $drivers->count() }} drivers · {{ $salesmen->count() }} salesmen · {{ $routes->count() }} routes</span></div>
  @if($canEdit)
  <a class="btn" href="#route-form">＋ Route</a>
  <a class="btn" href="#staff-form">＋ Driver / salesman</a>
  <a class="btn pri" href="{{ route('fleet.vehicles', ['new' => 1]) }}">＋ Add vehicle</a>
  @endif
</header>

<div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <section style="flex:999 1 560px;min-width:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px">
    @forelse($vehicles as $v)
      <a class="card" href="{{ route('fleet.vehicles', ['v' => $v->id]) }}" style="padding:14px;display:flex;flex-direction:column;gap:8px;border-radius:12px;text-decoration:none;color:inherit;{{ $sel && $sel->id === $v->id ? 'border:2px solid #1d4ed8' : '' }}">
        <div style="display:flex;align-items:center;gap:8px"><span style="font-size:var(--fs-16);font-weight:700;flex-grow:1">{{ $v->code }}</span><span class="pill" style="{{ $U::pill($v->status) }}">{{ $v->status }}</span></div>
        <span style="font-size:var(--fs-12);color:#51607a">{{ $v->reg_no ?: 'No reg. no.' }} · {{ $v->type }} · {{ $v->capacity }} cyl</span>
        <div style="display:flex;flex-direction:column;gap:4px;font-size:var(--fs-12);border-top:1px solid #dbe3f0;padding-top:8px">
          <div style="display:flex"><span style="flex-grow:1;color:#51607a">Route</span><span style="font-weight:600">{{ $v->route?->name ?? 'Spare' }}</span></div>
          <div style="display:flex"><span style="flex-grow:1;color:#51607a">Driver</span><span style="font-weight:600">{{ $v->driver?->name ?? '—' }}</span></div>
          <div style="display:flex"><span style="flex-grow:1;color:#51607a">Salesman</span><span style="font-weight:600">{{ $v->salesman?->name ?? '—' }}</span></div>
        </div>
      </a>
    @empty
      <div class="card pad sub" style="margin:0">No vehicles yet. Add the first one with the form.</div>
    @endforelse
  </section>

  <aside class="card" style="flex:1 1 320px;min-width:290px;padding:20px">
    <form method="post" action="{{ route('fleet.vehicles.save') }}" style="display:flex;flex-direction:column;gap:12px">@csrf
    @if($sel)<input type="hidden" name="id" value="{{ $sel->id }}">@endif
    <div style="display:flex;align-items:center"><h2 class="h2" style="flex-grow:1">{{ $sel ? 'Vehicle '.$sel->code : 'New vehicle' }}</h2>@if($sel)<span class="pill" style="{{ $U::pill($sel->status) }}">{{ $sel->status }}</span>@endif</div>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
      <label class="fld">Vehicle ID<input name="code" required maxlength="10" value="{{ old('code', $sel?->code ?? 'V-'.str_pad((string) ($vehicles->count() + 1), 2, '0', STR_PAD_LEFT)) }}"></label>
      <label class="fld">Registration no.<input name="reg_no" maxlength="40" value="{{ old('reg_no', $sel?->reg_no) }}" placeholder="DM-NA 11-0000"></label>
      <label class="fld">Vehicle type<select name="type">@foreach(\App\Models\Vehicle::TYPES as $t)<option @selected(old('type', $sel?->type) === $t)>{{ $t }}</option>@endforeach</select></label>
      <label class="fld">Capacity (cylinders)<input name="capacity" type="number" min="1" required value="{{ old('capacity', $sel?->capacity ?: 110) }}"></label>
    </div>
    <span class="ttl" style="font-size:var(--fs-10);margin-top:4px">ASSIGNMENT</span>
    <label class="fld">Route<select name="route_id"><option value="">Spare / no fixed route</option>@foreach($routes as $r)<option value="{{ $r->id }}" @selected(old('route_id', $sel?->route_id) == $r->id)>{{ $r->name }}</option>@endforeach</select></label>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
      <label class="fld">Driver<select name="driver_id"><option value="">—</option>@foreach($drivers as $e)<option value="{{ $e->id }}" @selected(old('driver_id', $sel?->driver_id) == $e->id)>{{ $e->name }}</option>@endforeach</select></label>
      <label class="fld">Salesman<select name="salesman_id"><option value="">—</option>@foreach($salesmen as $e)<option value="{{ $e->id }}" @selected(old('salesman_id', $sel?->salesman_id) == $e->id)>{{ $e->name }}</option>@endforeach</select></label>
    </div>
    <label class="fld">Status<select name="status">@foreach(\App\Models\Vehicle::STATUSES as $s)<option @selected(old('status', $sel?->status ?? 'Available') === $s)>{{ $s }}</option>@endforeach</select></label>
    <label class="fld">Remarks<input name="remarks" maxlength="255" value="{{ old('remarks', $sel?->remarks) }}"></label>
    @if($stats)
    <div style="background:#eef2f9;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:4px;font-size:var(--fs-12)">
      <div style="display:flex"><span style="flex-grow:1;color:#51607a">Trips this month</span><span style="font-weight:700">{{ $stats['trips'] }}</span></div>
      <div style="display:flex"><span style="flex-grow:1;color:#51607a">Cylinders sold this month</span><span style="font-weight:700">{{ $stats['sold'] }}</span></div>
      <div style="display:flex"><span style="flex-grow:1;color:#51607a">Net profit this month</span><span style="font-weight:700;color:#0f6b3c">{{ auth()->user()->isSuperAdmin() ? $U::tk($stats['net']) : 'Super Admin only' }}</span></div>
    </div>
    @endif
    @if($canEdit)<button class="btn pri" type="submit">Save vehicle</button>@endif
    </form>
  </aside>
</div>

<section class="card" style="overflow:hidden">
  <div style="padding:16px 20px"><h2 class="h2">Routes</h2></div>
  <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
    <thead><tr><th>Route</th><th>Markets covered</th><th class="r">Shops</th><th>Usual vehicle</th><th class="r">Avg. sold / trip</th><th class="r">Due on route</th></tr></thead>
    <tbody>
    @forelse($routeRows as $r)
      <tr><td style="font-weight:600">{{ $r['r']->name }}</td><td>{{ $r['r']->markets ?: '—' }}</td><td class="r">{{ $r['r']->shops ?? '—' }}</td><td>{{ $r['v'] }}</td><td class="r">{{ $r['avg'] ?? '—' }}</td><td class="r" style="font-weight:600">{{ $U::tk($r['due']) }}</td></tr>
    @empty
      <tr><td colspan="6" class="empty">No routes yet.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>

@if($canEdit)
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;align-items:start">
  <section class="card pad" id="route-form">
    <form method="post" action="{{ route('fleet.routes.save') }}" style="display:flex;flex-direction:column;gap:10px">@csrf
      <h2 class="h2">＋ Route</h2>
      <label class="fld">Route name<input name="name" required maxlength="120" placeholder="Barahat – Joarganj"></label>
      <label class="fld">Markets covered<input name="markets" maxlength="255" placeholder="Barahat, Joarganj, Bhuiyan Market"></label>
      <label class="fld">Shops<input name="shops" type="number" min="0"></label>
      <button class="btn pri" type="submit" style="align-self:flex-start">Save route</button>
    </form>
  </section>
  <section class="card" id="staff-form" style="overflow:hidden">
    <form method="post" action="{{ route('fleet.staff.save') }}" style="padding:18px 20px;display:flex;flex-direction:column;gap:10px">@csrf
      <h2 class="h2">＋ Driver / salesman</h2>
      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
        <label class="fld">Name<input name="name" required maxlength="120"></label>
        <label class="fld">Mobile<input name="phone" maxlength="20" inputmode="tel"></label>
        <label class="fld">Role<select name="role">@foreach(\App\Models\Employee::ROLES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
      </div>
      <button class="btn pri" type="submit" style="align-self:flex-start">Save person</button>
    </form>
    <div class="dt-wrap" style="margin:0 16px 16px"><table class="dt">
      <thead><tr><th>ID</th><th>Name</th><th>Mobile</th><th>Role</th><th>Status</th></tr></thead>
      <tbody>
      @forelse($staff as $e)
        <tr><td class="c">{{ $e->code }}</td><td>{{ $e->name }}</td><td class="nw">{{ $e->phone ?: '—' }}</td><td>{{ \App\Models\Employee::ROLES[$e->role] ?? $e->role }}</td>
          <td class="c"><form method="post" action="{{ route('fleet.staff.save') }}">@csrf<input type="hidden" name="id" value="{{ $e->id }}"><input type="hidden" name="name" value="{{ $e->name }}"><input type="hidden" name="phone" value="{{ $e->phone }}"><input type="hidden" name="role" value="{{ $e->role }}"><input type="hidden" name="is_active" value="{{ $e->is_active ? 0 : 1 }}">
            <button class="pill" type="submit" style="border:0;cursor:pointer;{{ $e->is_active ? 'background:#e3f6ec;color:#0f6b3c' : '' }}" title="Click to {{ $e->is_active ? 'deactivate' : 'activate' }}">{{ $e->is_active ? 'Active' : 'Inactive' }}</button></form></td></tr>
      @empty
        <tr><td colspan="5" class="empty">No drivers or salesmen yet.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </section>
</div>
@endif
@endsection
