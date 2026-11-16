<?php
namespace App\Http\Controllers;

use App\Http\Middleware\ModulePermission as MP;
use App\Support\PastReturn;
use App\Support\ProfitFloor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Goods of an earlier order received back with a later parcel. Receiving needs order-edit rights; money and corrections are for admins. */
class PastReturnController extends Controller {
  private function can(): bool { return MP::can(Auth::user(), 'orders', 'edit'); }
  private function no() { return response()->json(['success' => false, 'message' => 'Your role does not allow this.'], 403); }
  public function options(int $id) { if (!$this->can()) return $this->no(); [$ok, $m, $list] = PastReturn::options($id); return response()->json(['success' => $ok, 'message' => $m, 'orders' => $list]); }
  public function receive(Request $r, int $id) {
    if (!$this->can()) return $this->no();
    $d = $r->validate(['token' => 'required|string|max:60', 'lines' => 'required|array|max:60', 'lines.*.item' => 'required|integer', 'lines.*.cartons' => 'nullable|integer|min:0|max:10000', 'lines.*.packets' => 'nullable|integer|min:0|max:100000', 'lines.*.damaged' => 'nullable|integer|min:0|max:100000']);
    [$ok, $m, $rows] = PastReturn::receive($id, $d['lines'], $d['token']);
    return response()->json(['success' => $ok, 'message' => $m, 'count' => count($rows)]);
  }
  public function settle(Request $r, int $row) {
    if (!ProfitFloor::admin()) return $this->no();
    $d = $r->validate(['type' => 'required|in:refunded,adjusted,none', 'amount' => 'nullable|numeric|min:0|max:10000000', 'method' => 'nullable|string|max:40', 'note' => 'nullable|string|max:300', 'order_id' => 'nullable|integer']);
    [$ok, $m] = PastReturn::settle($row, $d['type'], (float)($d['amount'] ?? 0), $d['method'] ?? null, $d['note'] ?? null, isset($d['order_id']) ? (int)$d['order_id'] : null);
    return response()->json(['success' => $ok, 'message' => $m]);
  }
  public function void(Request $r, int $row) {
    if (!ProfitFloor::admin()) return $this->no();
    $d = $r->validate(['reason' => 'required|string|max:300']);
    [$ok, $m] = PastReturn::void($row, $d['reason']);
    return response()->json(['success' => $ok, 'message' => $m]);
  }
}
