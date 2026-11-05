<?php
namespace App\Http\Controllers;

use App\Http\Middleware\ModulePermission as P;
use App\Support\ReportCatalog as C;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Report Center, phase 1. READ-ONLY over the existing reports: it lists them (All Reports) and checks that none is missing (Report System Audit).
 * It never changes, hides or removes an existing report, route or calculation. The old menu stays exactly as it is.
 */
class ReportCenterController extends Controller
{
  const TYPES = ['grid' => 'Table', 'document' => 'Document', 'dashboard' => 'Dashboard'];
  const SORTS = ['code' => 'report_code', 'name' => 'name', 'group' => 'group_code', 'updated' => 'updated_at'];
  const PER = [20, 25, 50, 100];

  private function isAdmin(): bool { return in_array(Auth::user()->role ?? '', ['super', 'admin'], true); }

  /** Registry rows the signed-in person may see (permission of the report's module). */
  private function visible()
  {
    $q = DB::table('report_registry');
    if (!$this->isAdmin()) {
      $u = Auth::user(); $ok = [];
      foreach (array_keys(P::MODULES) as $m) if (P::can($u, $m, 'view')) $ok[] = $m;
      $q->whereIn('permission_key', $ok ?: ['-']);
    }
    return $q;
  }

  private static function missing($row): array
  {
    $m = [];
    if (!$row->supports_pdf) $m[] = 'PDF'; if (!$row->supports_xlsx) $m[] = 'Excel'; if (!$row->supports_csv) $m[] = 'CSV'; if (!$row->supports_print) $m[] = 'Print';
    return $m;
  }

  /** Applies the page filters to a query on report_registry. */
  private function filtered(Request $r)
  {
    $q = $this->visible();
    if ($r->filled('q')) {
      $s = '%' . addcslashes(mb_substr(trim((string)$r->query('q')), 0, 80), '%_\\') . '%';
      $q->where(function ($w) use ($s) { $w->where('name', 'ilike', $s)->orWhere('report_code', 'ilike', $s)->orWhere('sub_group', 'ilike', $s)->orWhere('description', 'ilike', $s)->orWhere('owner_module', 'ilike', $s); });
    }
    if ($r->filled('group') && isset(C::GROUPS[$r->query('group')])) $q->where('group_code', $r->query('group'));
    if ($r->filled('sub')) $q->where('sub_group', mb_substr((string)$r->query('sub'), 0, 80));
    if ($r->filled('type') && isset(self::TYPES[$r->query('type')])) $q->where('report_type', $r->query('type'));
    if ($r->query('status') === 'active') $q->where('is_active', true);
    if ($r->query('status') === 'inactive') $q->where('is_active', false);
    if ($r->query('status') === 'hidden') $q->where('is_hidden_from_sidebar', true);
    if ($r->query('legacy') === 'legacy') $q->where('is_legacy', true);
    if ($r->query('legacy') === 'current') $q->where('is_legacy', false);
    if ($r->query('export') === 'full') $q->where('supports_pdf', true)->where('supports_xlsx', true)->where('supports_csv', true)->where('supports_print', true);
    if ($r->query('export') === 'missing') $q->where(function ($w) { $w->where('supports_pdf', false)->orWhere('supports_xlsx', false)->orWhere('supports_csv', false)->orWhere('supports_print', false); });
    return $q;
  }

  public function index(Request $r)
  {
    C::ensure();
    $ready = C::ready();
    $data = ['ready' => $ready, 'isAdmin' => $this->isAdmin(), 'groups' => C::GROUPS, 'types' => self::TYPES, 'per' => self::PER];
    if (!$ready) return view('report_center', $data + ['rows' => collect(), 'total' => 0, 'page' => 1, 'pages' => 1, 'pp' => 25, 'counts' => [], 'gcount' => [], 'subs' => [], 'sort' => 'code', 'dir' => 'asc', 'repl' => []]);

    $pp = in_array((int)$r->query('pp'), self::PER, true) ? (int)$r->query('pp') : 25;
    $sort = isset(self::SORTS[$r->query('sort')]) ? (string)$r->query('sort') : 'code';
    $dir = $r->query('dir') === 'desc' ? 'desc' : 'asc';
    $q = $this->filtered($r);
    $total = (clone $q)->count();
    $pages = max(1, (int)ceil($total / $pp)); $page = min(max(1, (int)$r->query('page', 1)), $pages);
    $rows = $q->orderBy(self::SORTS[$sort], $dir)->orderBy('sort_order')->forPage($page, $pp)->get();

    $base = $this->visible();
    $counts = [
      'total' => (clone $base)->count(), 'active' => (clone $base)->where('is_active', true)->count(), 'legacy' => (clone $base)->where('is_legacy', true)->count(),
      'hidden' => (clone $base)->where('is_hidden_from_sidebar', true)->count(),
      'missing' => (clone $base)->where('is_active', true)->where(function ($w) { $w->where('supports_pdf', false)->orWhere('supports_xlsx', false)->orWhere('supports_csv', false)->orWhere('supports_print', false); })->count(),
    ];
    $gcount = (clone $base)->select('group_code', DB::raw('count(*) n'))->groupBy('group_code')->pluck('n', 'group_code')->all();
    $subs = (clone $base)->when($r->filled('group') && isset(C::GROUPS[$r->query('group')]), fn($x) => $x->where('group_code', $r->query('group')))->whereNotNull('sub_group')->distinct()->orderBy('sub_group')->pluck('sub_group')->all();

    $rid = $rows->pluck('replacement_report_id')->filter()->unique()->all();
    $repl = $rid ? DB::table('report_registry')->whereIn('id', $rid)->pluck('report_code', 'id')->all() : [];
    $data += compact('rows', 'total', 'page', 'pages', 'pp', 'counts', 'gcount', 'subs', 'sort', 'dir', 'repl');
    if ($r->query('partial')) return view('report_center_list', $data);
    return view('report_center', $data);
  }

  /** CSV of the list exactly as filtered on screen (all matching rows). */
  public function export(Request $r)
  {
    C::ensure(); abort_unless(C::ready(), 404);
    $rows = $this->filtered($r)->orderBy('group_code')->orderBy('sort_order')->get();
    DB::table('audit_logs')->insert(['user_id' => Auth::id(), 'user_name' => Auth::user()->name, 'action' => 'export', 'subject' => 'report-center-list.csv', 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]);
    $out = fopen('php://temp', 'r+'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Code', 'Report Name', 'Group', 'Sub Group', 'Type', 'Last Updated', 'PDF', 'Excel', 'CSV', 'Print', 'Status', 'Legacy', 'Hidden', 'Path']);
    $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
    foreach ($rows as $x) fputcsv($out, array_map($safe, [$x->report_code, $x->name, C::GROUPS[$x->group_code] ?? $x->group_code, $x->sub_group, self::TYPES[$x->report_type] ?? $x->report_type, substr((string)$x->updated_at, 0, 10),
      $x->supports_pdf ? 'Yes' : 'No', $x->supports_xlsx ? 'Yes' : 'No', $x->supports_csv ? 'Yes' : 'No', $x->supports_print ? 'Yes' : 'No', $x->is_active ? 'Active' : 'Inactive', $x->is_legacy ? 'Yes' : 'No', $x->is_hidden_from_sidebar ? 'Yes' : 'No', '/' . $x->route_path]));
    rewind($out);
    return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="All-Reports_' . now('Asia/Dhaka')->format('Y-m-d') . '.csv"']);
  }

  /** Opens a report from the registry (so the Open button keeps working if a path is ever moved). */
  public function open(string $code)
  {
    abort_unless(C::ready() && preg_match('/^[A-Z]{3}-\d{3}$/', $code), 404);
    $row = $this->visible()->where('report_code', $code)->first(); abort_unless($row, 404);
    return redirect('/' . $row->route_path);
  }

  public function sync(Request $r)
  {
    abort_unless($this->isAdmin(), 403);
    $n = C::sync();
    DB::table('audit_logs')->insert(['user_id' => Auth::id(), 'user_name' => Auth::user()->name, 'action' => 'Report registry synced', 'subject' => $n . ' new', 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]);
    return redirect('/admin/report-center/audit')->with('ok', $n ? "$n new report(s) added to the registry." : 'Registry is up to date. Nothing was removed or changed in any report.');
  }

  public function audit(Request $r)
  {
    abort_unless($this->isAdmin(), 403);
    C::ensure();
    if (!C::ready()) return view('report_audit', ['ready' => false, 'groups' => C::GROUPS]);

    $reg = DB::table('report_registry')->orderBy('sort_order')->get();
    $found = C::discoveredRoutes();
    $regKeys = []; foreach ($reg as $x) $regKeys[C::key(strtok($x->route_path, '?'))] = true;

    $issues = []; $add = function (string $type, $x, string $where, string $todo) use (&$issues) { $issues[] = compact('type', 'x', 'where', 'todo'); };
    $nBroken = $nPerm = $nExport = 0;
    foreach ($reg as $x) {
      if (!C::routeExists($x->route_path)) { $nBroken++; $add('Broken Route', $x, 'Registry only', 'Route not found: fix it or mark the report Legacy'); }
      if (!$x->permission_key || !isset(P::MODULES[$x->permission_key])) { $nPerm++; $add('Missing Permission', $x, 'Registry', 'Assign a permission module'); }
      if ($x->is_active && ($m = self::missing($x))) { $nExport++; $add('Missing Export', $x, 'Missing: ' . implode(', ', $m), 'Add the missing format(s) with the standard export'); }
      if ($x->is_legacy) $add('Legacy', $x, 'Registry', $x->replacement_report_id ? 'Replaced; kept for history' : 'Legacy without a replacement code');
    }
    $orphans = array_diff_key($found, $regKeys);
    foreach ($orphans as $k => $u) $add('Unregistered', (object)['report_code' => '—', 'name' => '/' . $u], 'Code only', 'Add it to the catalogue with a code and group');

    $dupRoute = $reg->groupBy(fn($x) => C::key($x->route_path))->filter(fn($g) => $g->count() > 1);
    foreach ($dupRoute as $g) foreach ($g as $x) $add('Duplicate Route', $x, 'Same path as ' . ($g->count() - 1) . ' other', 'Compare the reports');
    $dupName = $reg->groupBy(fn($x) => mb_strtolower($x->name))->filter(fn($g) => $g->count() > 1);
    foreach ($dupName as $g) foreach ($g as $x) $add('Duplicate Name', $x, 'Same name as ' . ($g->count() - 1) . ' other', 'Check if the calculation differs');

    $dups = $reg->filter(fn($x) => $x->duplicate_group)->groupBy('duplicate_group')->filter(fn($g) => $g->count() > 1);
    $action = function (string $path): string {
      try { $m = Route::getRoutes()->match(\Illuminate\Http\Request::create('/' . trim(strtok($path, '?'), '/'), 'GET')); return $m->isFallback ? '—' : class_basename($m->getActionName()); } catch (\Throwable $e) { return '—'; }
    };
    $dupView = $dups->map(fn($g) => $g->map(fn($x) => (object)['code' => $x->report_code, 'name' => $x->name, 'path' => '/' . $x->route_path, 'action' => $action($x->route_path), 'module' => $x->permission_key, 'owner' => $x->owner_module, 'type' => self::TYPES[$x->report_type] ?? $x->report_type,
      'exports' => implode(' ', array_filter([$x->supports_pdf ? 'PDF' : '', $x->supports_xlsx ? 'XLSX' : '', $x->supports_csv ? 'CSV' : '', $x->supports_print ? 'Print' : '']))]));

    $active = $reg->where('is_active', true);
    $full = $active->filter(fn($x) => !self::missing($x))->count();
    $c = [
      'discovered' => count($found), 'registered' => $reg->count(), 'active' => $active->count(), 'legacy' => $reg->where('is_legacy', true)->count(), 'hidden' => $reg->where('is_hidden_from_sidebar', true)->count(),
      'broken' => $nBroken, 'perm' => $nPerm, 'export' => $nExport, 'dup' => $dups->sum(fn($g) => $g->count()), 'orphans' => count($orphans),
    ];
    $byType = collect($issues)->groupBy('type')->map->count()->all();
    $filter = (string)$r->query('type');
    if ($filter !== '') $issues = array_values(array_filter($issues, fn($i) => $i['type'] === $filter));
    return view('report_audit', ['ready' => true, 'groups' => C::GROUPS, 'c' => $c, 'full' => $full, 'activeN' => $active->count(), 'issues' => $issues, 'byType' => $byType, 'filter' => $filter, 'dups' => $dupView, 'nFound' => count($found)]);
  }
}
