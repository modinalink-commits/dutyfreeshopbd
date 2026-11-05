<?php
$qs = request()->except('partial', 'page');
$u = fn($extra = []) => url('/admin/report-center/all-reports') . '?' . http_build_query(array_filter(array_merge($qs, $extra), fn($v) => $v !== null && $v !== ''));
$th = function ($key, $label, $cls = '') use ($sort, $dir, $u) {
  $nd = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
  return '<th scope="col" class="' . $cls . '"><a data-rc href="' . e($u(['sort' => $key, 'dir' => $nd])) . '" style="color:inherit;text-decoration:none">' . e($label) . ' ' . ($sort === $key ? ($dir === 'asc' ? '↑' : '↓') : '↕') . '</a></th>';
};
$from = $total ? ($page - 1) * $pp + 1 : 0; $to = min($total, $page * $pp);
$win = array_values(array_unique(array_filter([1, 2, 3, $page - 1, $page, $page + 1, $pages], fn($p) => $p >= 1 && $p <= $pages))); sort($win);
?>
<div class="rc-tw"><table class="rc-g">
<thead><tr>{!! $th('code', 'Code') !!}{!! $th('name', 'Report Name') !!}{!! $th('group', 'Group') !!}<th scope="col">Sub Group</th><th scope="col" class="c">Type</th>{!! $th('updated', 'Last Updated', 'c') !!}<th scope="col" class="c">Export</th><th scope="col" class="c">Status</th><th scope="col" class="c">Open</th></tr></thead>
<tbody>
@forelse($rows as $x)
@php
  $miss = []; if (!$x->supports_pdf) $miss[] = 'PDF'; if (!$x->supports_xlsx) $miss[] = 'Excel'; if (!$x->supports_csv) $miss[] = 'CSV'; if (!$x->supports_print) $miss[] = 'Print';
  $has = array_filter([$x->supports_pdf ? 'PDF' : '', $x->supports_xlsx ? 'XLSX' : '', $x->supports_csv ? 'CSV' : '', $x->supports_print ? 'Print' : '', $x->supports_docx ? 'DOCX' : '']);
  $dim = $x->is_legacy || !$x->is_active;
@endphp
<tr style="{{ $dim ? 'color:#6B7280' : '' }}">
  <td class="nw"><b>{{ $x->report_code }}</b></td>
  <td>{{ $x->name }}@if($x->is_legacy && $x->replacement_report_id)<div class="mut">Replaced By: <b>{{ $repl[$x->replacement_report_id] ?? '—' }}</b></div>@endif @if($x->duplicate_group)<div class="mut" title="May overlap with another report. Not decided yet.">Possible overlap · under review</div>@endif</td>
  <td>{{ $groups[$x->group_code] ?? $x->group_code }}</td>
  <td>{{ $x->sub_group }}</td>
  <td class="c">{{ $types[$x->report_type] ?? $x->report_type }}</td>
  <td class="c nw">{{ \Carbon\Carbon::parse($x->updated_at)->timezone('Asia/Dhaka')->format('d M Y') }}</td>
  <td class="c nw">{{ $has ? implode(' · ', $has) : 'None' }}@if($x->is_active && $miss)<div class="mut" style="color:#92400E">Missing: {{ implode(', ', $miss) }}</div>@endif</td>
  <td class="c">
    @if($x->is_legacy)<span class="rc-tg" style="background:#E5E7EB;color:#374151">Legacy</span>
    @elseif(!$x->is_active)<span class="rc-tg" style="background:#E5E7EB;color:#374151">Inactive</span>
    @else<span class="rc-tg" style="background:#DCFCE7;color:#166534">Active</span>@endif
    @if($x->is_hidden_from_sidebar)<span class="rc-tg" style="background:#E0E7FF;color:#3730A3">Hidden</span>@endif
  </td>
  <td class="c"><a href="{{ url('/admin/report-center/open/' . $x->report_code) }}">Open</a></td>
</tr>
@empty
<tr><td colspan="9" style="text-align:center;color:#6B7280;padding:18px">No report matches these filters.</td></tr>
@endforelse
</tbody></table></div>
<div class="rc-foot">
  <span class="mut">Showing {{ $from }}–{{ $to }} of {{ $total }}</span>
  <span class="rc-pg" role="navigation" aria-label="Pagination">
    @if($page > 1)<a data-rc href="{{ $u(['page' => $page - 1]) }}">Previous</a>@else<span class="off">Previous</span>@endif
    @php $prev = 0; @endphp
    @foreach($win as $p)@if($p - $prev > 1)<span class="off">…</span>@endif<a data-rc class="{{ $p === $page ? 'on' : '' }}" href="{{ $u(['page' => $p]) }}">{{ $p }}</a>@php $prev = $p; @endphp @endforeach
    @if($page < $pages)<a data-rc href="{{ $u(['page' => $page + 1]) }}">Next</a>@else<span class="off">Next</span>@endif
  </span>
</div>
