@extends('layout')@section('title','Modules')
@section('body')<h1>Modules</h1><div class="card"><table><thead><tr><th>Module</th><th>Tables</th><th>Records</th><th>State</th></tr></thead><tbody>
@foreach($rows as $r)<tr><td>{{ $r['name'] }}</td><td>{{ $r['tables'] }}</td><td>{{ number_format($r['records']) }}</td><td><span class="pill {{ $r['state']==='Working'?'ok':'' }}">{{ $r['state'] }}</span></td></tr>@endforeach</tbody></table></div>
<p style="font-size:12px;color:#6B7280">Working = pages are live. Structure ready = database tables exist, pages come next, built to match the design board by board.</p>@endsection
