<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Refer & Earn: friend gets a first-order discount, referrer earns wallet credit once the referred order is Delivered. */
class Referral
{
  public static function set(string $k, string $def = ''): string { $v = DB::table('settings')->where('key', 'ref_' . $k)->value('value'); return $v === null || $v === '' ? $def : (string)$v; }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => 'ref_' . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }
  public static function on(): bool { return self::set('on', '1') === '1'; }
  public static function discount(): float { return (float)self::set('discount', '100'); }
  public static function reward(): float { return (float)self::set('reward', '100'); }
  public static function norm(string $p): string { return substr(preg_replace('/\D/', '', $p), -11); }
  public static function cookieCode(): ?string { $c = strtoupper(trim((string)request()->cookie('dfref'))); return preg_match('/^[A-Z0-9]{4,20}$/', $c) ? $c : null; }

  public static function codeFor(object $c): string {
    if (!empty($c->ref_code)) return $c->ref_code;
    $base = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)$c->name), 0, 5)); if (strlen($base) < 3) $base = 'DF';
    do { $code = $base . random_int(100, 999); } while (DB::table('customers')->where('ref_code', $code)->exists());
    DB::table('customers')->where('id', $c->id)->update(['ref_code' => $code]); return $code;
  }
  public static function link(string $code): string { return rtrim((string)config('app.url', 'https://dutyfreeshopbd.com'), '/') . '/ref/' . $code; }
  public static function referrer(string $code): ?object { return DB::table('customers')->whereRaw('upper(ref_code) = ?', [strtoupper($code)])->where('blocked', false)->first(); }

  /** Rules 1-3, 6: returns ['code','referrer','discount'] when this phone may use the code on this subtotal. */
  public static function eligible(string $phone, string $code, float $sub): ?array {
    if (!self::on()) return null; $phone = self::norm($phone); if (!preg_match('/^01[3-9]\d{8}$/', $phone)) return null;
    $ref = self::referrer($code); if (!$ref) return null;
    if (self::norm((string)$ref->phone) === $phone) return null;                                           // own link
    if (DB::table('referrals')->where('referred_phone', $phone)->exists()) return null;                    // benefit only once
    $alt = [$phone, '+88' . $phone, '88' . $phone];
    if (DB::table('orders')->whereIn('phone', $alt)->exists()) return null;                                 // first order only
    $cu = DB::table('customers')->whereIn('phone', $alt)->first(); if ($cu && ((int)($cu->legacy_orders ?? 0) > 0)) return null;
    if ($sub < (float)self::set('min_order', '0')) return null;
    $max = (int)self::set('max', '0'); if ($max > 0 && DB::table('referrals')->where('referrer_id', $ref->id)->whereIn('status', ['pending', 'rewarded'])->count() >= $max) return null;
    return ['code' => strtoupper($ref->ref_code), 'referrer' => $ref, 'discount' => self::discount()];
  }
  /** Code to try for a checkout: the cookie from the link, or the code typed at registration. */
  public static function codeFlow(?string $phone): ?string {
    $c = self::cookieCode(); if ($c) return $c;
    if ($phone) { $r = DB::table('customers')->whereIn('phone', [$phone, '+88' . $phone, '88' . $phone])->value('referred_by'); $r = strtoupper(trim((string)$r)); if (preg_match('/^[A-Z0-9]{4,20}$/', $r)) return $r; }
    return null;
  }

  public static function balance(string $phone): float { return (float)DB::table('customers')->whereIn('phone', [self::norm($phone), '+88' . self::norm($phone), '88' . self::norm($phone)])->value('ref_balance'); }

  /** Called right after a website order is saved. */
  public static function onOrder(int $orderId, string $phone, array $qt): void {
    $o = DB::table('orders')->find($orderId); if (!$o) return; $cust = DB::table('customers')->find($o->customer_id);
    foreach ($qt['applied'] ?? [] as $l) {
      if (($l['key'] ?? '') === 'referral') {
        $ref = self::referrer((string)($l['refcode'] ?? '')); if (!$ref) continue;
        try { DB::table('referrals')->insert(['referrer_id' => $ref->id, 'referred_id' => $cust->id ?? null, 'referred_phone' => self::norm($phone), 'code' => strtoupper($ref->ref_code), 'link' => self::link($ref->ref_code), 'order_id' => $orderId,
          'order_amount' => (float)$o->total, 'discount' => (float)$l['amount'], 'reward' => self::reward(), 'order_status' => $o->status, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
      }
      if (($l['key'] ?? '') === 'refcredit' && $cust) self::move((int)$cust->id, 'redeem', -(float)$l['amount'], null, $orderId, 'Used on ' . $o->invoice);
    }
  }
  public static function move(int $cid, string $kind, float $amt, ?int $refId, ?int $orderId, string $note): void {
    DB::table('referral_ledger')->insert(['customer_id' => $cid, 'kind' => $kind, 'amount' => $amt, 'referral_id' => $refId, 'order_id' => $orderId, 'note' => $note, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('customers')->where('id', $cid)->update(['ref_balance' => DB::raw('greatest(0, ref_balance + (' . $amt . '))')]);
  }

  /** Background worker: confirm rewards after Delivered, void cancelled/returned, restore credit used on cancelled orders. */
  public static function tick(): void {
    $after = self::set('after', 'Delivered'); $bad = ['Cancel', 'Cancelled', 'Returned'];
    foreach (DB::table('referrals')->whereIn('status', ['pending', 'rewarded'])->whereNotNull('order_id')->limit(300)->get() as $r) {
      $st = (string)DB::table('orders')->where('id', $r->order_id)->value('status'); $u = [];
      if ($st !== $r->order_status) $u['order_status'] = $st;
      if ($r->status === 'pending') {
        if (in_array($st, $bad, true)) { $u += ['status' => 'void', 'note' => 'Order ' . $st]; }
        elseif ($st === $after && (float)$r->reward > 0) { self::move((int)$r->referrer_id, 'reward', (float)$r->reward, (int)$r->id, (int)$r->order_id, 'Friend order delivered'); $u += ['status' => 'rewarded', 'rewarded_at' => now()]; }
        elseif ($st === $after) $u += ['status' => 'rewarded', 'rewarded_at' => now()];
      } elseif ($r->status === 'rewarded' && in_array($st, ['Returned'], true)) {
        self::move((int)$r->referrer_id, 'adjust', -(float)$r->reward, (int)$r->id, (int)$r->order_id, 'Order returned: reward reversed'); $u += ['status' => 'void', 'note' => 'Order returned after reward'];
      }
      if ($u) DB::table('referrals')->where('id', $r->id)->update($u + ['updated_at' => now()]);
    }
    foreach (DB::table('referral_ledger')->where('kind', 'redeem')->where('created_at', '>', now()->subDays(60))->get() as $l) {
      $st = (string)DB::table('orders')->where('id', $l->order_id)->value('status');
      if (in_array($st, $bad, true) && !DB::table('referral_ledger')->where('kind', 'restore')->where('order_id', $l->order_id)->exists()) self::move((int)$l->customer_id, 'restore', abs((float)$l->amount), null, (int)$l->order_id, 'Order cancelled: credit restored');
    }
  }

  public static function stats(int $cid): array {
    $q = DB::table('referrals')->where('referrer_id', $cid);
    return ['total' => (clone $q)->count(), 'ok' => (clone $q)->where('status', 'rewarded')->count(), 'pending' => (clone $q)->where('status', 'pending')->count(),
      'earned' => (float)DB::table('referral_ledger')->where('customer_id', $cid)->where('kind', 'reward')->sum('amount'), 'balance' => (float)DB::table('customers')->where('id', $cid)->value('ref_balance')];
  }
}
