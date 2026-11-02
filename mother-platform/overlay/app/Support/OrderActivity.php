<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Append-only per-order staff activity. Staff identity always comes from the authenticated session, never from request input. */
class OrderActivity {
  public static function log(int $orderId, string $type, ?string $old = null, ?string $new = null, ?string $desc = null, array $meta = [], ?string $batch = null): void {
    try {
      if (!Schema::hasTable('order_activity_logs')) return;
      $u = auth()->user(); $r = request();
      DB::table('order_activity_logs')->insert(['order_id'=>$orderId,'staff_id'=>$u?->id,'staff_name'=>$u?->name ?? 'System','action_type'=>$type,'old_value'=>$old,'new_value'=>$new,'description'=>$desc,
        'metadata'=>$meta ? json_encode($meta) : null,'ip_address'=>$r?->ip(),'user_agent'=>mb_substr((string)$r?->userAgent(),0,300),'source'=>$u ? 'staff' : 'system','batch_id'=>$batch,'created_at'=>now()]);
    } catch (\Throwable $e) { report($e); }
  }
  public static function rows(int $orderId, int $limit = 300) {
    if (!Schema::hasTable('order_activity_logs')) return collect();
    return DB::table('order_activity_logs')->where('order_id',$orderId)->orderByDesc('id')->limit($limit)->get();
  }
  public static function label(object $a): string {
    $t = $a->action_type;
    return match (true) {
      $t === 'status_changed' => ($a->old_value ?: '—').' → '.$a->new_value,
      $t === 'order_created' => 'Order Created',
      default => ucwords(str_replace('_',' ',$t)).($a->description ? ': '.mb_substr($a->description,0,160) : ''),
    };
  }
}
