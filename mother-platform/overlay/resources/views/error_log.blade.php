@extends('layout')@section('title','Error Log')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px"><h1 style="margin:0">Error Log</h1><span style="font-size:13px;color:#6B7280">Last part of the application log. Passwords and keys are hidden.</span><a href="/admin/error-log?errs=1" style="margin-left:auto;font-size:13px">Only errors (text)</a></div>
<pre style="margin:0;padding:12px 14px;background:#fff;border:1px solid #E5E7EB;border-radius:10px;font:12px/1.5 Inter,'Bornomala',sans-serif;color:#0B1220;white-space:pre-wrap;word-break:break-word;overflow:auto;max-height:78vh">{{ $t }}</pre>
@endsection
