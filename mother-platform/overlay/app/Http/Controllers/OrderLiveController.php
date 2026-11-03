<?php
namespace App\Http\Controllers;
use App\Support\ShipmentOutcome;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
/** Read-only "latest status" feed for the live status bar. orders.status stays the single source of truth; nothing is stored or computed here. */
class OrderLiveController extends Controller {
  private function pack(object $o, $items): array {
    $min = $o->courier_synced_at ? (int)Carbon::parse($o->courier_synced_at)->diffInMinutes(now()) : null;
    return ['id'=>(int)$o->id, 'status'=>(string)$o->status, 'slug'=>Str::slug((string)$o->status), 'note'=>ShipmentOutcome::rowNote($o, $items), 'courier'=>$o->courier, 'consignment'=>$o->consignment_id,
      'courier_status'=>$o->courier_status, 'stage'=>$o->courier_stage, 'synced_min'=>$min, 'has_courier'=>!empty($o->consignment_id), 'outcome'=>$o->pathao_outcome ?? null, 'raw'=>$o->pathao_raw_status ?? null, 'cflag'=>$o->courier_flag ?? null, 'bflag'=>$o->billing_flag ?? null, 'at'=>$o->updated_at ? Carbon::parse($o->updated_at)->timezone('Asia/Dhaka')->format('h:i A') : null,
      'ts'=>$o->updated_at ? Carbon::parse($o->updated_at)->timestamp : 0];
  }
  public function one($id) {
    $o = DB::table('orders')->find((int)$id); abort_unless($o, 404);
    return response()->json($this->pack($o, DB::table('order_items')->where('order_id', $o->id)->orderBy('id')->get()))->header('Cache-Control', 'no-store');
  }
  /** Statuses of the orders currently on screen (one grouped request, at most 80 ids). */
  public function many(Request $r) {
    $ids = collect(explode(',', (string)$r->query('ids')))->map(fn($x) => (int)$x)->filter()->unique()->take(80)->values()->all();
    if (!$ids) return response()->json([]);
    $items = DB::table('order_items')->whereIn('order_id', $ids)->orderBy('id')->get()->groupBy('order_id');
    $out = []; foreach (DB::table('orders')->whereIn('id', $ids)->get() as $o) $out[$o->id] = $this->pack($o, $items[$o->id] ?? collect());
    return response()->json($out)->header('Cache-Control', 'no-store');
  }
}
