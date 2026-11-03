<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Middleware\ModulePermission as MP;
use App\Support\ShipmentOutcome as SO;
class ShipmentOutcomeController extends Controller {
  private function allowed(): bool { return MP::can(Auth::user(), 'orders', 'edit'); }
  public function save(Request $r, $id) {
    if (!$this->allowed()) return response()->json(['success'=>false, 'message'=>'Your role does not allow updating orders.'], 403);
    $d = $r->validate(['ret'=>'nullable|array', 'ret.*'=>'nullable|integer|min:0|max:100000', 'note'=>'nullable|string|max:300']);
    [$ok, $m, $st] = SO::save((int)$id, array_map('intval', $d['ret'] ?? []), $d['note'] ?? null);
    return response()->json(['success'=>$ok, 'message'=>$m, 'status'=>$st]);
  }
  public function receive(Request $r, $id) {
    if (!$this->allowed()) return response()->json(['success'=>false, 'message'=>'Your role does not allow updating orders.'], 403);
    [$ok, $m, $st] = SO::receive((int)$id);
    return response()->json(['success'=>$ok, 'message'=>$m, 'status'=>$st]);
  }
}
