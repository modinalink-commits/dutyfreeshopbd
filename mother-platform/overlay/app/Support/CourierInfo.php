<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/** JSON for the courier status / tracking popup on the orders list. */
class CourierInfo {
  public static function json(int $id) {
    $o = DB::table('orders')->find($id); if (!$o) return response()->json(['ok'=>false,'error'=>'Order not found'], 404);
    $a = (array)$o; $g = fn($k) => $a[$k] ?? null;
    $tz = fn($v) => $v ? \Carbon\Carbon::parse($v, 'UTC')->timezone('Asia/Dhaka')->format('d M Y, h:i A') : null;
    $ev = [];
    try {
      foreach (DB::table('courier_events')->where('order_id', $id)->orderByDesc('id')->limit(30)->get() as $e) {
        $ev[] = ['at'=>$tz($e->happened_at ?? $e->created_at), 'status'=>(string)$e->status, 'stage'=>$e->stage, 'loc'=>$e->location];
      }
    } catch (\Throwable $x) {}
    $status = trim((string)(($g('pathao_raw_status') ?: '') ?: ($g('courier_status') ?: '')));
    return response()->json([
      'ok'=>true, 'invoice'=>$g('invoice'), 'courier'=>$g('courier') ?: null, 'cid'=>$g('consignment_id') ?: null,
      'status'=>$status !== '' ? $status : null, 'stage'=>$g('courier_stage'), 'location'=>$g('courier_location'),
      'synced'=>$tz($g('courier_synced_at')), 'delivered'=>$tz($g('courier_delivered_at')),
      'cod'=>($g('courier_cod') ?? $g('total')) !== null ? (float)($g('courier_cod') ?? $g('total')) : null,
      'collected'=>$g('collected_amount') !== null ? (float)$g('collected_amount') : null,
      'fee'=>$g('courier_fee') !== null ? (float)$g('courier_fee') : null,
      'settlement'=>$g('settlement_status'), 'url'=>TrackLink::url($o), 'events'=>$ev,
    ]);
  }
}
