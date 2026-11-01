@extends('layout')@section('title','SMS Templates')
@section('body')
<h1 style="margin:0 0 12px">SMS Templates</h1>@include('smsmk.nav')
<form class="card" method="post" action="/admin/sms-marketing/templates" style="max-width:760px">@csrf @if($edit)<input type="hidden" name="id" value="{{ $edit->id }}">@endif
<b>{{ $edit ? 'Edit template' : 'New template' }}</b>
<label>Template name</label><input name="name" required maxlength="120" value="{{ $edit->name ?? '' }}">
<label>Category</label><select name="category">@foreach($cats as $c)<option {{ ($edit->category ?? 'Custom') === $c ? 'selected' : '' }}>{{ $c }}</option>@endforeach</select>
<label>Message</label><textarea name="body" id="tb" rows="4" required maxlength="1000">{{ $edit->body ?? '' }}</textarea>
<div style="font-size:12px;color:#6B7280;margin:6px 0">Variables (click to insert): @foreach($vars as $v)<a href="#" onclick="var t=document.getElementById('tb');t.setRangeText('{{ '{'.$v.'}' }}',t.selectionStart,t.selectionEnd,'end');t.focus();return false" class="chip" style="padding:2px 8px">{{ '{'.$v.'}' }}</a> @endforeach</div>
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="active" value="1" style="width:auto" {{ ($edit->active ?? true) ? 'checked' : '' }}> Active</label>
<div style="margin-top:12px"><button>Save template</button> @if($edit)<a class="btn g" href="/admin/sms-marketing/templates">Cancel</a>@endif</div></form>
<div class="card tw"><table><thead><tr><th>Name</th><th>Category</th><th>Message</th><th>Status</th><th></th></tr></thead><tbody>
@foreach($rows as $t)<tr><td>{{ $t->name }}</td><td>{{ $t->category }}</td><td style="max-width:420px;font-size:12px">{{ $t->body }}</td><td><span class="bd {{ $t->active ? 'sent' : '' }}">{{ $t->active ? 'Active' : 'Inactive' }}</span></td>
<td style="white-space:nowrap"><a class="btn g" href="/admin/sms-marketing/send?tpl={{ $t->id }}">Use</a> <a class="btn g" href="?edit={{ $t->id }}">Edit</a> <form method="post" action="/admin/sms-marketing/templates/delete" style="display:inline" onsubmit="return confirm('Delete this template?')">@csrf<input type="hidden" name="id" value="{{ $t->id }}"><button class="g">Delete</button></form></td></tr>@endforeach</tbody></table></div>
@endsection
