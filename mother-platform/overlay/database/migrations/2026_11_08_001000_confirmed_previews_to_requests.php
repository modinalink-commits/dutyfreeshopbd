<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};
// Customers who saw a full AI order preview and confirmed it, but no order / Order Request followed: bring them into Order Requests for staff.
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('chat_order_requests') || !Schema::hasTable('chat_messages')) return;
      $prev = DB::table('chat_messages')->where('dir', 'out')->where('body', 'ilike', '%confirm%')->where(function ($q) { $q->where('body', 'like', '%ঠিকানা%')->orWhere('body', 'ilike', '%address%'); })->where(function ($q) { $q->where('body', 'like', '%মোবাইল%')->orWhere('body', 'ilike', '%mobile%'); })->orderBy('id')->get(['id', 'thread_id', 'body', 'created_at'])->groupBy('thread_id');
      foreach ($prev as $tid => $rows) {
        try {
          $pm = $rows->last(); $th = DB::table('chat_threads')->where('id', $tid)->first(); if (!$th) continue;
          $yes = DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'in')->where('id', '>', $pm->id)->get(['id', 'body'])->first(fn($m) => \App\Support\AgentTools::isConfirm((string)$m->body));
          if (!$yes) continue;
          if (DB::table('chat_order_requests')->where('thread_id', $tid)->where('status', '!=', 'dismissed')->where('created_at', '>=', $pm->created_at)->exists()) continue;
          $f = []; foreach (preg_split('/\R/u', (string)$pm->body) as $ln) if (preg_match('/^\s*([^:：]{2,20})[:：]\s*(.+)$/u', trim($ln), $m)) $f[trim($m[1])] = trim($m[2]);
          $g = function (array $keys) use ($f) { foreach ($keys as $k) foreach ($f as $a => $v) if (mb_stripos($a, $k) !== false) return $v; return ''; };
          $phone = preg_replace('/\D/', '', $g(['মোবাইল', 'Mobile', 'Phone']) ?: (string)$th->phone); if (strlen($phone) < 10) $phone = (string)$th->phone;
          $phone = preg_replace('/^(\+?88)/', '', $phone);
          if (DB::table('orders')->where('phone', $phone)->where('created_at', '>=', $pm->created_at)->exists()) continue;
          $prod = $g(['পণ্য', 'Product', 'Item']); $total = 0; $dm = $g(['মোট', 'Total']) ?: $g(['দাম', 'Price']); if (preg_match('/[\d,]+/', strtr($dm, ['০'=>'0','১'=>'1','২'=>'2','৩'=>'3','৪'=>'4','৫'=>'5','৬'=>'6','৭'=>'7','৮'=>'8','৯'=>'9']), $mm)) $total = (float)str_replace(',', '', $mm[0]);
          $cart = []; $items = [];
          if ($prod !== '') { $nm = trim(preg_split('/[,،]/u', $prod)[0]); $p = DB::table('products')->where('name', 'ilike', '%'.$nm.'%')->first(['id', 'name', 'price']); if ($p) { $cart[(int)$p->id] = 1; $items[] = ['id'=>$p->id, 'name'=>$p->name, 'qty'=>1]; } }
          DB::table('chat_order_requests')->insert(['thread_id'=>$tid, 'channel'=>$th->channel ?? null, 'name'=>mb_substr($g(['নাম', 'Name']) ?: (string)($th->name ?? 'Customer'), 0, 160), 'phone'=>$phone, 'address'=>$g(['ঠিকানা', 'Address']) ?: '-', 'area'=>1, 'note'=>'Product: '.$prod, 'cart'=>json_encode($cart ?: new \stdClass), 'items'=>json_encode($items), 'total'=>$total, 'status'=>'review', 'reason'=>'Customer confirmed the preview; AI did not finish', 'created_at'=>now(), 'updated_at'=>now()]);
        } catch (\Throwable $e) { report($e); }
      }
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
