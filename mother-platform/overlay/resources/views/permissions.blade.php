@extends('layout')@section('title','Admin Users and Permissions')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:260px"><h1 style="margin:0">Admin Users and Permissions</h1><span style="font-size:13px;color:#6B7280">Staff accounts, what each person can do, and who can download.</span></div>
<span style="display:inline-flex;gap:6px;font-size:12px;font-weight:600;color:#FF5A00;background:#FFF1E8;border-radius:8px;padding:6px 10px">⬇ Download: <a target="_blank" href="/admin/m/users/export/pdf">PDF</a> · <a href="/admin/m/users/export/xls">Excel</a> · <a href="/admin/m/users/export/doc">Word</a> · <a href="/admin/m/users/export/csv">CSV</a></span>
<a class="btn" href="/admin/m/users/new">Add admin</a></div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
<form method="post" action="/admin/permissions">@csrf
<div class="card"><b>Admin list</b><table><thead><tr><th>Name</th><th>Login</th><th>Type</th><th>Role (permissions)</th><th>Status</th><th>Action</th></tr></thead><tbody>
@foreach($users as $u)<tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ ucfirst($u->role) }}</td><td>@if(in_array($u->role,['super','admin']))<span style="color:#6B7280">Full access</span>@else<select name="ur[{{ $u->id }}]" style="width:auto"><option value="">No role (orders view only)</option>@foreach($roles as $r)<option value="{{ $r->id }}" {{ $u->role_id==$r->id?'selected':'' }}>{{ $r->name }}</option>@endforeach</select>@endif</td><td><span class="pill {{ $u->active?'ok':'' }}">{{ $u->active?'Active':'De-active' }}</span></td><td><a class="btn g" href="/admin/m/users/{{ $u->id }}/edit">Edit / reset password</a></td></tr>@endforeach</tbody></table></div>
<div class="card" style="overflow-x:auto"><b>Permissions by role (view, edit, delete = all, download)</b>
<table><thead><tr><th>Module</th>@foreach($roles as $r)<th>{{ $r->name }}</th>@endforeach</tr></thead><tbody>
@foreach($mods as $mk=>$ml)<tr><td>{{ $ml }}</td>@foreach($roles as $r)<td style="white-space:nowrap"><select name="p[{{ $r->id }}][{{ $mk }}]" style="width:auto;padding:5px">@foreach($levels as $lv)<option value="{{ $lv }}" {{ ($r->p[$mk] ?? 'none')===$lv?'selected':'' }}>{{ $lv==='none'?'—':ucfirst($lv) }}</option>@endforeach</select> <label style="display:inline;font-size:11px"><input type="checkbox" name="p[{{ $r->id }}][{{ $mk }}_download]" value="1" {{ !empty($r->p[$mk.'_download'])?'checked':'' }} style="width:auto"> ⬇</label></td>@endforeach</tr>@endforeach
</tbody></table><p style="font-size:12px;color:#6B7280">Download rights (⬇) are set separately, so a staff member can see data without being able to export it. Super admin and admin always have full access. Users and permissions are admin-only.</p>
<button>Save permissions</button> <a class="btn g" href="/admin/m/roles/new">Add role</a></div></form>
@endsection
