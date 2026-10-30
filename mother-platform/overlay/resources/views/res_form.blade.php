@extends('layout')@section('title',$c['title'])
@section('body')<h1>{{ $row ? 'Edit' : 'Add' }}: {{ $c['title'] }}</h1>
@if($errors->any())<div class="card err">{{ $errors->first() }}</div>@endif
<form class="card" method="post" action="/admin/m/{{ $c['slug'] }}{{ $row ? '/'.$row->id : '' }}" style="max-width:640px">@csrf
@foreach($c['fields'] as $f)@php if(!empty($f['auto']) || !empty($f['formHide'])) continue; if($row && !empty($c['editOnly']) && !in_array($f['n'],$c['editOnly'])) continue;
$cur = $row ? ($row->{$f['n']} ?? null) : null; if ($cur===null && !$row && ($f['default'] ?? null)==='today') $cur = date('Y-m-d'); if ($cur===null && !$row && $f['t']==='bool' && $f['n']==='active') $cur = true; $val = old($f['n'], $cur);
if($f['t']==='perm' && $val) { $a=json_decode($val,true); $val=is_array($a)?implode(', ',$a):$val; }
if($f['t']==='json' && $val && !is_string($val)) $val=json_encode($val);
if($f['t']==='datetime' && $val) $val=str_replace(' ','T',substr((string)$val,0,16)); @endphp
<label>{{ $f['l'] }}{{ !empty($f['req']) ? ' *' : '' }}</label>
@switch($f['t'])
@case('textarea')@case('json')<textarea name="{{ $f['n'] }}" rows="{{ $f['t']==='json'?4:3 }}">{{ $val }}</textarea>@break
@case('bool')<input type="hidden" name="{{ $f['n'] }}" value="0"><input type="checkbox" name="{{ $f['n'] }}" value="1" {{ $val ? 'checked':'' }} style="width:auto">@break
@case('select')<select name="{{ $f['n'] }}">@foreach($f['o'] as $ov=>$ol)<option value="{{ $ov }}" {{ (string)$val===(string)$ov?'selected':'' }}>{{ $ol }}</option>@endforeach</select>@break
@case('fk')<select name="{{ $f['n'] }}"><option value="">-</option>@foreach($lk[$f['n']] ?? [] as $ov=>$ol)<option value="{{ $ov }}" {{ (string)$val===(string)$ov?'selected':'' }}>{{ $ol }}</option>@endforeach</select>@break
@case('number')<input type="number" step="any" name="{{ $f['n'] }}" value="{{ $val }}">@break
@case('date')<input type="date" name="{{ $f['n'] }}" value="{{ $val }}">@break
@case('datetime')<input type="datetime-local" name="{{ $f['n'] }}" value="{{ $val }}">@break
@case('password')<input type="password" name="{{ $f['n'] }}" autocomplete="new-password" placeholder="{{ $row ? 'leave blank to keep' : '' }}">@break
@default<input name="{{ $f['n'] }}" value="{{ $val }}">@endswitch
@error($f['n'])<div class="err">{{ $message }}</div>@enderror
@endforeach
<p style="margin-top:16px"><button>Save</button> <a class="btn g" href="/admin/m/{{ $c['slug'] }}">Cancel</a></p></form>@endsection
