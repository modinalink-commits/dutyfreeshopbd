@extends('layout')
@section('title', 'Users & roles')
@section('content')
<div><h1>Users &amp; roles</h1><p class="sub">Each person gets their own login. New users must change the temporary password at first login.</p></div>
@if(session('temp'))
<div class="card pad" style="border-color:#1d4ed8">
  <b>Temporary password for {{ session('temp')['email'] }}:</b>
  <code style="font-size:16px;margin-left:8px">{{ session('temp')['password'] }}</code>
  <p class="sub">Give it to the person privately. It is shown only once.</p>
</div>
@endif
<section class="card"><div class="scroll"><table>
  <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Role</th><th>Status</th><th>Last login</th>@if(auth()->user()->isSuperAdmin())<th></th>@endif</tr></thead>
  <tbody>
  @foreach($users as $usr)
    <tr>
      <td><b>{{ $usr->name }}</b></td><td>{{ $usr->email }}</td><td>{{ $usr->phone }}</td><td>{{ $usr->roleLabel() }}</td>
      <td><span class="pill {{ $usr->is_active ? 'st-delivered' : 'st-cancelled' }}">{{ $usr->is_active ? 'Active' : 'Off' }}</span></td>
      <td>{{ $usr->last_login_at?->timezone('Asia/Dhaka')->format('d-m-Y H:i') ?? '—' }}</td>
      @if(auth()->user()->isSuperAdmin())
      <td style="white-space:nowrap">
        @if($usr->id !== auth()->id())
        <form method="post" action="{{ route('users.toggle', $usr) }}" style="display:inline">@csrf<button class="btn sm" type="submit">{{ $usr->is_active ? 'Switch off' : 'Switch on' }}</button></form>
        <form method="post" action="{{ route('users.reset', $usr) }}" style="display:inline" onsubmit="return confirm('Make a new temporary password for {{ $usr->email }}?')">@csrf<button class="btn sm" type="submit">New password</button></form>
        @endif
      </td>
      @endif
    </tr>
  @endforeach
  </tbody>
</table></div></section>
@if(auth()->user()->isSuperAdmin())
<form class="card pad" method="post" action="{{ route('users.store') }}" style="display:flex;flex-direction:column;gap:14px">
  @csrf
  <h2 style="margin:0;font-size:15px">Add a user</h2>
  <div class="grid2">
    <label class="f">Full name<input type="text" name="name" value="{{ old('name') }}" required></label>
    <label class="f">Email (used to log in)<input type="email" name="email" value="{{ old('email') }}" required></label>
    <label class="f">Mobile<input type="text" name="phone" value="{{ old('phone') }}"></label>
    <label class="f">Role<select name="role">@foreach($roles as $k => $label)<option value="{{ $k }}" @selected(old('role') === $k)>{{ $label }}</option>@endforeach</select></label>
  </div>
  <div><button class="btn pri" type="submit">Create user</button></div>
</form>
@endif
@endsection
