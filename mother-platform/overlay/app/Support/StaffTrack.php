<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff Activity & Order Tracking helpers. Everything is read from the append-only order_activity_logs (nobody can edit or delete a row),
 * plus call_logs, sms_logs, audit_logs and the new staff_login_logs. This class only groups, labels and flags; it never writes to the order logs.
 */
class StaffTrack {
  /** Group of an action type (a type matches the first group whose regex fits). */
  const GROUPS = [
    'status'  => ['Status Changes', '#(^status_changed$|^order_created$|^order_scheduled$|^schedule_cancelled$|duplicate|^return_received$|reopen|^order_cancel)#'],
    'payment' => ['Payment Changes', '#^payment_#'],
    'courier' => ['Courier Changes', '#^(courier_(assigned|changed|flag|booked|sent|status|cancel)|consignment_|pathao_|delivery_|late_|shipment_|tracking_|billing_)#'],
    'price'   => ['Price & Discount Changes', '#^(discount_|order_edited|price_|quantity_|delivery_charge|product_|customer_|manual_override)#'],
    'comm'    => ['Customer Communication', '#^(call_|note_added|courier_note|message_|whatsapp_|sms_)#'],
  ];
  const MODULE = ['status' => 'Order Management', 'payment' => 'Payments', 'courier' => 'Courier', 'price' => 'Order Editing', 'comm' => 'Communication', 'other' => 'Order Management'];

  public static function group(string $type): string { foreach (self::GROUPS as $k => [$l, $re]) if (preg_match($re, $type)) return $k; return 'other'; }
  /** Regex (no delimiters) for a group, usable both in PHP (wrap in #) and as a PostgreSQL ~ pattern. */
  public static function re(string $g): string { return isset(self::GROUPS[$g]) ? trim(self::GROUPS[$g][1], '#') : '^$'; }
  /** "12 Nov 2026, 03:40 PM" in Dhaka time from a UTC timestamp. */
  public static function dt($v): string { try { return $v ? \Carbon\Carbon::parse($v)->timezone('Asia/Dhaka')->format('d M Y, h:i A') : '—'; } catch (\Throwable $e) { return (string)$v; } }

  public static function label(string $type): string {
    static $x = ['status_changed' => 'Status Changed', 'order_created' => 'Order Created', 'order_edited' => 'Order Edited', 'discount_line_changed' => 'Discount Changed', 'courier_changed' => 'Courier Changed', 'courier_assigned' => 'Courier Assigned',
      'consignment_changed' => 'Tracking / Consignment Changed', 'payment_verified' => 'Payment Verified', 'payment_rejected' => 'Payment Rejected', 'payment_submitted' => 'Payment Added', 'payment_settled' => 'Payment Status Changed', 'payment_request_sent' => 'Payment Request Sent',
      'note_added' => 'Internal Note Added', 'call_started' => 'Call Made', 'invoice_printed' => 'Invoice Printed', 'label_printed' => 'Label Printed', 'order_scheduled' => 'Order Scheduled', 'schedule_cancelled' => 'Schedule Cancelled', 'marked_duplicate' => 'Marked Duplicate',
      'duplicate_unmarked' => 'Duplicate Unmarked', 'duplicate_linked' => 'Duplicate Linked', 'return_received' => 'Return Received', 'pathao_outcome' => 'Pathao Outcome', 'pathao_invoice' => 'Pathao Invoice', 'courier_note' => 'Courier Note', 'courier_flag' => 'Courier Flag',
      'delivery_outcome' => 'Delivery Outcome', 'late_followup' => 'Late Follow-up', 'late_delivery' => 'Late Delivery Flag', 'billing_flag' => 'Billing Flag'];
    return $x[$type] ?? ucwords(str_replace('_', ' ', $type));
  }

  /** "Chrome · Windows" from a user agent; 'System' when there is none. */
  public static function device(?string $ua, ?string $ip = null): string {
    $ua = (string)$ua; if ($ua === '') return $ip ? 'System / ' . $ip : 'System';
    $b = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR\/|Opera/', $ua) ? 'Opera' : (preg_match('/Firefox\//', $ua) ? 'Firefox' : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'Browser'))));
    $o = preg_match('/Android/', $ua) ? 'Android' : (preg_match('/iPhone|iPad/', $ua) ? 'iOS' : (preg_match('/Windows/', $ua) ? 'Windows' : (preg_match('/Mac OS/', $ua) ? 'Mac' : (preg_match('/Linux/', $ua) ? 'Linux' : ''))));
    return trim($b . ($o ? ' · ' . $o : '') . ($ip ? ' · ' . $ip : ''));
  }

  /** Date range from the filter: [fromUtc, toUtcExclusive, label, key, fromDate, toDate]. Default Last 30 Days. */
  public static function range(?string $k, ?string $from, ?string $to): array {
    $tz = new \DateTimeZone('Asia/Dhaka'); $today = new \DateTime('today', $tz);
    $k = in_array($k, ['today', 'yesterday', '7', '30', 'custom'], true) ? $k : '30';
    switch ($k) {
      case 'today': $a = clone $today; $b = clone $today; break;
      case 'yesterday': $a = (clone $today)->modify('-1 day'); $b = clone $a; break;
      case '7': $a = (clone $today)->modify('-6 days'); $b = clone $today; break;
      case 'custom':
        $ok = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
        $a = $ok($from) ? new \DateTime($from, $tz) : (clone $today)->modify('first day of this month'); $b = $ok($to) ? new \DateTime($to, $tz) : clone $today; if ($a > $b) [$a, $b] = [$b, $a]; break;
      default: $a = (clone $today)->modify('first day of this month'); $b = clone $today;
    }
    $e = (clone $b)->modify('+1 day'); $u = fn(\DateTime $d) => (clone $d)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $names = ['today' => 'Today', 'yesterday' => 'Yesterday', '7' => 'Last 7 Days', '30' => 'Last 30 Days', 'custom' => 'Custom'];
    return [$u($a), $u($e), $names[$k], $k, $a->format('Y-m-d'), $b->format('Y-m-d')];
  }

  /** Why an entry is high-risk, or null. Rules are simple and visible: nothing is hidden or scored. */
  public static function flag(object $a): ?string {
    $t = (string)$a->action_type; $old = (string)($a->old_value ?? ''); $new = (string)($a->new_value ?? '');
    $late = ['Shipment', 'Delivered', 'Partial Delivered'];
    if ($t === 'status_changed' && $new === 'Cancel' && in_array($old, $late, true)) return 'Cancelled after ' . $old;
    if ($t === 'status_changed' && in_array($old, ['Delivered', 'Shipment', 'Packaging'], true) && in_array($new, ['New', 'Pending', 'Approved', 'WFP', 'Assign'], true)) return 'Status moved backwards';
    if ($t === 'status_changed' && $old === 'Returned' && $new !== 'Returned') return 'Returned order re-opened';
    if ($t === 'manual_override') return 'Manual override';
    if ($t === 'payment_verified' && ($a->source ?? '') === 'staff' && (float)$new >= 20000) return 'Large payment verified (৳' . number_format((float)$new) . ')';
    if ($t === 'discount_line_changed' || $t === 'order_edited') {
      $m = json_decode((string)($a->metadata ?? ''), true); $d = (string)($a->description ?? '');
      if (preg_match('/discount[^0-9৳]*৳?\s*([\d,]+)\D+৳?\s*([\d,]+)/i', $d, $x)) { $o = (float)str_replace(',', '', $x[1]); $n = (float)str_replace(',', '', $x[2]); if ($n - $o >= 500) return 'Discount raised by ৳' . number_format($n - $o); }
    }
    if (($a->source ?? '') === 'staff' && !in_array($t, ['invoice_printed', 'label_printed'], true)) { $h = (int)\Carbon\Carbon::parse($a->created_at)->timezone('Asia/Dhaka')->format('G'); if ($h < 5) return 'Off-hours edit (' . \Carbon\Carbon::parse($a->created_at)->timezone('Asia/Dhaka')->format('h:i A') . ')'; }
    return null;
  }

  public static function tableOk(string $t): bool { try { return Schema::hasTable($t); } catch (\Throwable $e) { return false; } }

  /** Sign-in history entry (success, failed, sign-out). Never stores a password; failures are never able to block a sign-in. */
  public static function login(string $event, $user, ?string $email, $r): void {
    try {
      if (!self::tableOk('staff_login_logs')) return;
      DB::table('staff_login_logs')->insert(['user_id' => $user->id ?? null, 'user_name' => $user->name ?? null, 'email' => $email ? mb_substr($email, 0, 160) : null, 'event' => $event, 'ip_address' => $r?->ip(), 'user_agent' => mb_substr((string)$r?->userAgent(), 0, 300), 'created_at' => now()]);
    } catch (\Throwable $e) { report($e); }
  }
}
