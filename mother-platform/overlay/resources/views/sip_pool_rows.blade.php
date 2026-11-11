@php($n=max($min,$list->count()+1))
@for($i=0;$i<$n;$i++)@php($p=$list[$i] ?? null)
<tr class="prow" style="border-top:1px solid #E5E7EB"><td style="padding:7px 6px;color:#6B7280;white-space:nowrap">Pool #{{ $i+1 }}<input form="poolForm{{ $cid }}" type="hidden" name="rows[{{ $i }}][id]" value="{{ $p->id ?? '' }}"></td>
<td><input form="poolForm{{ $cid }}" name="rows[{{ $i }}][extension]" value="{{ $p->extension ?? '' }}" style="width:90px" autocomplete="off"></td>
<td style="white-space:nowrap">@if($p && $p->extension)@php($rl=in_array((string)$p->extension, $relay, true) || in_array((string)($p->auth_username ?? ''), $relay, true))<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:{{ $rl ? '#FFEDD5' : '#F3F4F6' }};color:{{ $rl ? '#B45309' : '#6B7280' }}">{{ $cid > 0 ? 'COMPANY' : ($rl ? 'AGENT + RELAY' : 'FREE') }}</span>{!! $cid === 0 ? ($regTag[(string)($p->auth_username ?: $p->extension)] ?? '') : '' !!}@endif</td>
<td><input form="poolForm{{ $cid }}" name="rows[{{ $i }}][auth_username]" value="{{ $p->auth_username ?? '' }}" placeholder="same" style="width:90px" autocomplete="off"></td>
<td><input form="poolForm{{ $cid }}" name="rows[{{ $i }}][display_name]" value="{{ ($p->display_name ?? '') === 'DutyFreeBD' ? '' : ($p->display_name ?? '') }}" placeholder="DutyFreeBD" style="width:130px" autocomplete="off"></td>
<td><input form="poolForm{{ $cid }}" type="text" name="rows[{{ $i }}][password]" value="{{ \App\Http\Controllers\CallCenterController::pwPlain($p) }}" autocomplete="off" autocapitalize="off" spellcheck="false" data-lpignore="true" style="width:150px;font-family:Inter,'Bornomala',sans-serif"></td><td></td></tr>
@endfor
