@extends('layout')
@section('title', 'Activity log')
@section('content')
<div><h1>Activity log</h1><p class="sub">Logins and changes, newest first.</p></div>
<section class="card"><div class="scroll"><table>
  <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
  <tbody>
  @forelse($logs as $l)
    <tr><td style="white-space:nowrap">{{ $l->created_at?->timezone('Asia/Dhaka')->format('d-m-Y H:i') }}</td><td>{{ $l->user?->name ?? '—' }}</td><td>{{ str_replace('_', ' ', $l->action) }}</td><td>{{ $l->details }}</td><td>{{ $l->ip }}</td></tr>
  @empty
    <tr><td colspan="5" style="color:var(--muted);padding:20px 12px">Nothing yet.</td></tr>
  @endforelse
  </tbody>
</table></div></section>
{{ $logs->links('pagination::default') }}
@endsection
