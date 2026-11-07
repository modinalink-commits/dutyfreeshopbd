<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Approval Source: who or what approved an order (AI / MANUAL / SYSTEM / API). Saved when the approval happens, never worked out from the status afterwards.
 *  Current values live on orders; every approval is also appended to order_approval_history (immutable). Never throws: a failure here must not block an approval. */
class ApprovalSource {
  private static ?bool $ready = null;
  public static function ready(): bool {
    if (self::$ready === null) { try { self::$ready = Schema::hasColumn('orders', 'approval_source') && Schema::hasTable('order_approval_history'); } catch (\Throwable $e) { self::$ready = false; } }
    return self::$ready;
  }
  /** Call right after an order has moved to Approved. $by = staff id (MANUAL only). $ref = e.g. the AI verification id. */
  public static function stamp(int $orderId, string $source, ?int $by = null, ?string $ref = null, ?string $note = null, ?string $byName = null): void {
    if (!self::ready()) return;
    try {
      $source = strtoupper($source); if (!in_array($source, ['AI', 'MANUAL', 'SYSTEM', 'API'], true)) $source = 'SYSTEM';
      if ($source === 'MANUAL' && $by === null) $by = auth()->id();
      if ($byName === null && $by) $byName = auth()->id() === $by ? (auth()->user()->name ?? null) : DB::table('users')->where('id', $by)->value('name');
      DB::table('orders')->where('id', $orderId)->update(['approval_source' => $source, 'approved_by' => $source === 'MANUAL' ? $by : null, 'approved_at' => now(), 'approval_reference' => $ref ? mb_substr($ref, 0, 120) : null]);
      DB::table('order_approval_history')->insert(['order_id' => $orderId, 'source' => $source, 'approved_by' => $source === 'MANUAL' ? $by : null, 'approved_by_name' => $byName ? mb_substr($byName, 0, 120) : null, 'approved_at' => now(), 'reference' => $ref ? mb_substr($ref, 0, 120) : null, 'note' => $note ? mb_substr($note, 0, 300) : null, 'created_at' => now()]);
    } catch (\Throwable $e) { report($e); return; }
    try { OrderWaConfirm::onApproved($orderId, $source); } catch (\Throwable $e) { report($e); }
  }
  /** Manual path helper: stamp MANUAL when a staff change moved an order into Approved. */
  public static function manual(int $orderId, ?string $fromStatus, string $toStatus, ?string $note = null): void {
    if ($toStatus === 'Approved' && $fromStatus !== 'Approved') self::stamp($orderId, 'MANUAL', auth()->id(), null, $note);
  }
  /** Statuses before approval: an old approval stamp is not shown there. */
  const PRE = ['New', 'Incomplete', 'Pre Order', 'AI Order', 'Assign', 'Pending', 'Scheduled'];
  /** Display info for a page of orders: [order_id => ['src','label','tip']] */
  public static function badges(iterable $orders): array {
    $out = []; if (!self::ready()) return $out;
    $rows = collect($orders)->filter(fn($o) => !empty($o->approval_source) && !in_array($o->status, self::PRE, true) && $o->status !== 'Cancel');
    if ($rows->isEmpty()) return $out;
    $names = DB::table('users')->whereIn('id', $rows->pluck('approved_by')->filter()->unique()->all())->pluck('name', 'id');
    $multi = DB::table('order_approval_history')->whereIn('order_id', $rows->pluck('id')->all())->groupBy('order_id')->havingRaw('count(*) > 1')->pluck('order_id')->flip();
    foreach ($rows as $o) {
      $at = $o->approved_at ? \Carbon\Carbon::parse($o->approved_at, 'UTC')->timezone('Asia/Dhaka')->format('d M Y, h:i A') : '—';
      $s = $o->approval_source; $ov = isset($multi[$o->id]) ? "\nHistory: more than one approval (see audit log)" : '';
      if ($s === 'AI') $tip = "AI Approved\nApproved At: $at\nVerification: Passed" . ($o->approval_reference ? "\nRef: ".$o->approval_reference : '') . $ov;
      elseif ($s === 'MANUAL') $tip = "Manual Approved\nApproved By: " . ($names[$o->approved_by] ?? 'Staff') . "\nApproved At: $at" . $ov;
      else $tip = ($s === 'API' ? 'API' : 'System') . " Approved\nApproved At: $at" . ($o->approval_reference ? "\nRef: ".$o->approval_reference : '') . $ov;
      $out[$o->id] = ['src' => $s, 'label' => ['AI' => 'AI', 'MANUAL' => 'Manual', 'SYSTEM' => 'System', 'API' => 'API'][$s] ?? $s, 'tip' => $tip, 'ov' => $ov !== ''];
    }
    return $out;
  }
}
