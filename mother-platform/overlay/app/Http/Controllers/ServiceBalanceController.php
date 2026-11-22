<?php
namespace App\Http\Controllers;

use App\Support\Procure;
use App\Support\ServiceBalances as SB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Services and Balances page: what each paid service/API still has, when it renews, and the alert limits. Owner/admin only. */
class ServiceBalanceController extends Controller {
  private function guard(): void { abort_unless(in_array(optional(auth()->user())->role, ['super', 'admin'], true), 403); }

  public function index() {
    $this->guard();
    if (!SB::ready()) return view('service_balances', ['off' => true]);
    $rows = DB::table('service_accounts')->orderBy('sort')->orderBy('id')->get();
    $st = []; foreach ($rows as $r) $st[$r->id] = SB::state($r);
    return view('service_balances', ['off' => false, 'rows' => $rows, 'st' => $st]);
  }

  /** Check every auto service now (or one). */
  public function refresh(Request $r) {
    $this->guard(); abort_unless(SB::ready(), 404);
    $q = DB::table('service_accounts')->where('kind', 'auto')->where('active', true); if ($r->filled('id')) $q->where('id', (int)$r->input('id'));
    $n = 0; $bad = 0; foreach ($q->get() as $row) { $f = SB::refresh($row); $f['ok'] ? $n++ : $bad++; SB::alertFor(DB::table('service_accounts')->where('id', $row->id)->first()); }
    Procure::audit('services.refresh', 'Services and Balances: checked ' . ($n + $bad) . ' service(s)');
    return back()->with($bad ? 'err' : 'ok', $n . ' checked' . ($bad ? ', ' . $bad . ' failed (see the status line of each)' : ''));
  }

  /** Create or update one service. Auto services keep their name/kind; only limits, date and note change. */
  public function save(Request $r) {
    $this->guard(); abort_unless(SB::ready(), 404);
    $d = $r->validate([
      'id' => 'nullable|integer', 'name' => 'required|string|max:120', 'category' => 'nullable|string|max:30', 'unit' => 'nullable|string|max:16',
      'balance' => 'nullable|numeric|min:-1000000000|max:1000000000', 'low_at' => 'nullable|numeric|min:0|max:1000000000', 'expires_on' => 'nullable|date',
      'remind_days' => 'nullable|integer|min:0|max:365', 'note' => 'nullable|string|max:1000', 'active' => 'nullable|boolean',
    ]);
    $by = (string)(optional(auth()->user())->name ?? ''); $now = now();
    $f = ['low_at' => $d['low_at'] ?? null, 'expires_on' => $d['expires_on'] ?? null, 'remind_days' => (int)($d['remind_days'] ?? 14), 'note' => $d['note'] ?? null, 'active' => (bool)($d['active'] ?? true), 'updated_by' => $by, 'updated_at' => $now];
    if (!empty($d['id'])) {
      $row = DB::table('service_accounts')->where('id', (int)$d['id'])->first(); abort_unless($row, 404);
      if ($row->kind === 'manual') { $f += ['name' => $d['name'], 'category' => $d['category'] ?: 'Other', 'unit' => $d['unit'] ?: 'BDT', 'balance' => $d['balance'] ?? null, 'checked_at' => $now, 'last_ok' => null, 'last_text' => 'Typed in by ' . ($by ?: 'staff')]; }
      DB::table('service_accounts')->where('id', $row->id)->update($f);
      Procure::audit('services.update', $row->name, ['balance' => $row->balance, 'low_at' => $row->low_at, 'expires_on' => $row->expires_on], ['balance' => $f['balance'] ?? $row->balance, 'low_at' => $f['low_at'], 'expires_on' => $f['expires_on']]);
    } else {
      DB::table('service_accounts')->insert($f + ['name' => $d['name'], 'category' => $d['category'] ?: 'Other', 'unit' => $d['unit'] ?: 'BDT', 'kind' => 'manual', 'balance' => $d['balance'] ?? null, 'sort' => 200, 'checked_at' => $now, 'last_text' => 'Typed in by ' . ($by ?: 'staff'), 'created_at' => $now]);
      Procure::audit('services.create', $d['name']);
    }
    return back()->with('ok', 'Saved');
  }

  /** Only typed-in services can be removed; automatic ones can be switched off. */
  public function delete(int $id) {
    $this->guard(); abort_unless(SB::ready(), 404);
    $row = DB::table('service_accounts')->where('id', $id)->where('kind', 'manual')->first(); abort_unless($row, 404);
    DB::table('service_accounts')->where('id', $id)->delete(); Procure::audit('services.delete', $row->name);
    return back()->with('ok', 'Removed');
  }
}
