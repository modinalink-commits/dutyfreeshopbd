<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner rule (6 Nov 2026): a customer who leaves an incomplete order gets ONE SMS about 2 minutes after the last activity, asking them to complete it via WhatsApp or the call center. Only new incomplete orders created after this rule went live; never twice to the same incomplete order or to the same phone within 24 hours; never to a customer who already ordered or opted out of promotions. */
class IncompleteNudge {
  public static function set(string $k, string $d = ''): string { return Procure::set('inc_nudge_' . $k, $d); }
  public static function body(?string $name): string {
    $n = trim((string)$name); $n = ($n !== '' && mb_strlen($n) <= 24 && !preg_match('/\d/', $n)) ? 'প্রিয় ' . $n : 'প্রিয় গ্রাহক';
    return $n . ', আপনার অর্ডারটি অসম্পূর্ণ রয়ে গেছে। অর্ডার কমপ্লিট করতে আমাদের WhatsApp ' . self::set('wa', '01871600600') . ' নম্বরে মেসেজ করুন অথবা কল সেন্টারে ' . self::set('call', '09613828081') . ' নম্বরে কল দিন। ধন্যবাদ, DutyFreeBD';
  }
  public static function tick(): int {
    if (self::set('on', '1') !== '1' || !Schema::hasTable('incomplete_orders') || !Schema::hasColumn('incomplete_orders', 'nudged_at') || !Sms::configured()) return 0;
    $cut = self::set('cutoff', ''); if ($cut === '') return 0;
    $mins = max(1, (int)self::set('delay', '2')); $n = 0;
    $rows = DB::table('incomplete_orders')->where('status', 'open')->whereNull('nudged_at')->where('created_at', '>=', $cut)->where('created_at', '>=', now()->subHours(6))
      ->whereRaw('coalesce(last_activity_at, created_at) <= ?', [now()->subMinutes($mins)])->orderBy('id')->limit(20)->get();
    foreach ($rows as $i) {
      if (!DB::table('incomplete_orders')->where('id', $i->id)->whereNull('nudged_at')->update(['nudged_at' => now()])) continue; // claim: never twice
      try {
        $to = Sms::number((string)$i->phone); if (!$to) continue;
        $d = substr($to, -11);
        if (DB::table('orders')->whereRaw('right(phone,11) = ?', [$d])->where('created_at', '>=', $i->created_at)->exists()) continue; // already ordered
        if (DB::table('customers')->whereRaw('right(phone,11) = ?', [$d])->where('promo_sms', false)->exists()) continue; // opted out
        if (DB::table('sms_logs')->where('event', 'incomplete_nudge')->where('to', $to)->where('created_at', '>=', now()->subDay())->exists()) continue; // one per phone per day
        Sms::queue($to, self::body($i->name), 'incomplete_nudge'); $n++;
      } catch (\Throwable $e) {}
    }
    if ($n) { try { Sms::flush(25); } catch (\Throwable $e) {} }
    return $n;
  }
}
